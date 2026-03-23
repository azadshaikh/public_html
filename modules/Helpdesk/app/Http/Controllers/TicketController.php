<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Helpdesk\Definitions\TicketDefinition;
use Modules\Helpdesk\Http\Requests\TicketReplyRequest;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketReplies;
use Modules\Helpdesk\Services\TicketService;
use Throwable;

class TicketController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly TicketService $ticketService
    ) {}

    public static function middleware(): array
    {
        return [
            ...(new TicketDefinition)->getMiddleware(),
            new Middleware('permission:edit_helpdesk_tickets', only: ['storeReply', 'deleteReply']),
        ];
    }

    protected function inertiaPage(): string
    {
        return 'helpdesk/tickets';
    }

    // =========================================================================
    // REPLIES (ticket detail view)
    // =========================================================================

    public function storeReply(TicketReplyRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $ticketInput = [
                'department_id' => $validated['department_id'],
                'assigned_to' => $validated['assigned_to'],
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'updated_by' => auth()->id(),
            ];

            if ($validated['status'] === 'closed' && $ticket->status !== 'closed') {
                $ticketInput['closed_at'] = now();
                $ticketInput['closed_by'] = auth()->id();
            } elseif ($validated['status'] !== 'closed' && $ticket->status === 'closed') {
                $ticketInput['closed_at'] = null;
                $ticketInput['closed_by'] = null;
            }

            $ticket->fill($ticketInput)->save();

            $attachments = [];
            if (! empty($validated['attachments']) && is_array($validated['attachments'])) {
                $attachments = $this->ticketService->uploadAttachments($validated['attachments']);
            }

            $ticket->replies()->create([
                'ticket_id' => $ticket->id,
                'content' => $validated['content'],
                'is_internal' => (int) $validated['is_internal'],
                'attachments' => $attachments !== [] ? $attachments : null,
                'reply_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Reply stored successfully.',
                ]);
            }

            return to_route('helpdesk.tickets.show', $ticket)
                ->with('success', 'Reply added successfully.');
        } catch (Throwable) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error storing reply.',
                ], 500);
            }

            return back()
                ->with('error', 'Error storing reply.');
        }
    }

    public function deleteReply(Ticket $ticket, TicketReplies $reply): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();

        try {
            $replyAttachments = $reply->attachments;
            if (is_string($replyAttachments)) {
                $replyAttachments = json_decode($replyAttachments, true) ?: [];
            }

            if (is_array($replyAttachments)) {
                foreach ($replyAttachments as $replyAttachment) {
                    $path = $replyAttachment['file_path'] ?? null;
                    if ($path) {
                        Storage::disk(get_storage_disk())->delete($path);
                    }
                }
            }

            $reply->delete();

            DB::commit();

            if (! request()->expectsJson()) {
                return to_route('helpdesk.tickets.show', $ticket)
                    ->with('success', 'Reply deleted successfully.');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reply deleted successfully.',
            ]);
        } catch (Throwable) {
            DB::rollBack();

            if (! request()->expectsJson()) {
                return back()->with('error', 'Error deleting reply.');
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting reply.',
            ], 500);
        }
    }

    protected function service(): TicketService
    {
        return $this->ticketService;
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Ticket $ticket */
        $ticket = $model;

        return [
            'initialValues' => [
                'ticket_number' => (string) ($ticket->ticket_number ?: $this->ticketService->generateTicketNumber()),
                'department_id' => $ticket->department_id ? (string) $ticket->department_id : '',
                'user_id' => $ticket->user_id ? (string) $ticket->user_id : '',
                'subject' => (string) ($ticket->subject ?? ''),
                'description' => (string) ($ticket->description ?? ''),
                'priority' => (string) ($ticket->priority ?? 'low'),
                'assigned_to' => $ticket->assigned_to ? (string) $ticket->assigned_to : '',
                'status' => (string) ($ticket->status ?? 'open'),
                'attachments' => null,
                'attachments_urls' => collect($ticket->attachments ?? [])
                    ->pluck('file_path')
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'departments' => $this->ticketService->getDepartmentOptions(),
            'users' => $this->ticketService->getUserOptions(),
            'priorityOptions' => $this->ticketService->getPriorityOptions(),
            'statusOptions' => $this->ticketService->getStatusOptions(),
            'existingAttachments' => collect($ticket->attachments ?? [])->values()->all(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Ticket $ticket */
        $ticket = $model;

        return [
            'id' => $ticket->getKey(),
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => (string) ($ticket->status ?? 'open'),
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Ticket $ticket */
        $ticket = $model;
        $ticket->loadMissing([
            'department:id,name',
            'assignedTo:id,first_name,last_name,name,avatar',
            'user:id,first_name,last_name,name,avatar',
        ]);

        return [
            'id' => $ticket->getKey(),
            'ticket_number' => $ticket->ticket_number,
            'subject' => (string) ($ticket->subject ?? ''),
            'description' => (string) ($ticket->description ?? ''),
            'department_name' => $ticket->department?->name ?? 'Unassigned',
            'requester_name' => $ticket->user?->full_name ?? $ticket->user?->name ?? 'Unknown requester',
            'requester_avatar' => $ticket->user?->avatar ?? null,
            'assigned_to_name' => $ticket->assignedTo?->full_name ?? $ticket->assignedTo?->name ?? 'Unassigned',
            'assigned_to_avatar' => $ticket->assignedTo?->avatar ?? null,
            'priority' => (string) ($ticket->priority ?? 'low'),
            'priority_label' => str((string) ($ticket->priority ?? 'low'))->headline()->toString(),
            'status' => (string) ($ticket->status ?? 'open'),
            'status_label' => str((string) ($ticket->status ?? 'open'))->headline()->toString(),
            'opened_at' => app_date_time_format($ticket->opened_at, 'datetime'),
            'closed_at' => app_date_time_format($ticket->closed_at, 'datetime'),
            'created_at' => app_date_time_format($ticket->created_at, 'datetime'),
            'updated_at' => app_date_time_format($ticket->updated_at, 'datetime'),
            'deleted_at' => app_date_time_format($ticket->deleted_at, 'datetime'),
            'is_trashed' => (bool) $ticket->trashed(),
            'attachments' => collect($ticket->attachments ?? [])
                ->map(fn (array $attachment): array => [
                    'file_name' => (string) ($attachment['file_name'] ?? basename((string) ($attachment['file_path'] ?? 'attachment'))),
                    'file_path' => (string) ($attachment['file_path'] ?? ''),
                    'file_size' => $attachment['file_size'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'url' => isset($attachment['file_path'])
                        ? Storage::disk(get_storage_disk())->url((string) $attachment['file_path'])
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Ticket $ticket */
        $ticket = $model;
        $ticket->loadMissing([
            'replies.replyBy:id,first_name,last_name,name,avatar',
        ]);

        $activities = ActivityLog::query()
            ->forModel(Ticket::class, $ticket->getKey())
            ->with('causer')
            ->latest('created_at')
            ->limit(25)
            ->get();

        return [
            'replies' => $ticket->replies
                ->sortBy('created_at')
                ->values()
                ->map(fn (TicketReplies $reply): array => [
                    'id' => $reply->getKey(),
                    'content' => (string) ($reply->content ?? ''),
                    'is_internal' => (bool) $reply->is_internal,
                    'reply_by_name' => $reply->replyBy?->full_name ?? $reply->replyBy?->name ?? 'Unknown staff member',
                    'reply_by_avatar' => $reply->replyBy?->avatar ?? null,
                    'created_at' => app_date_time_format($reply->created_at, 'datetime'),
                    'attachments' => collect($reply->attachments ?? [])
                        ->map(fn (array $attachment): array => [
                            'file_name' => (string) ($attachment['file_name'] ?? basename((string) ($attachment['file_path'] ?? 'attachment'))),
                            'file_path' => (string) ($attachment['file_path'] ?? ''),
                            'url' => isset($attachment['file_path'])
                                ? Storage::disk(get_storage_disk())->url((string) $attachment['file_path'])
                                : null,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'replyInitialValues' => [
                'department_id' => $ticket->department_id ? (string) $ticket->department_id : '',
                'assigned_to' => $ticket->assigned_to ? (string) $ticket->assigned_to : '',
                'priority' => (string) ($ticket->priority ?? 'low'),
                'status' => (string) ($ticket->status ?? 'open'),
                'content' => '',
                'is_internal' => false,
                'attachments' => null,
            ],
            'departments' => $this->ticketService->getDepartmentOptions(),
            'users' => $this->ticketService->getUserOptions(),
            'priorityOptions' => $this->ticketService->getPriorityOptions(),
            'statusOptions' => $this->ticketService->getStatusOptions(),
            'activities' => $activities->map(fn (ActivityLog $activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? ActivityAction::UPDATE->value),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }
}
