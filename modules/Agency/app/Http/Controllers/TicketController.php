<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Agency\Services\AgencyTicketService;
use Modules\Helpdesk\Models\Department; // Keep for constructor injection if explicitly needed elsewhere, but mainly we use AgencyTicketService now
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\TicketService;

class TicketController extends ScaffoldController
{
    public function __construct(
        private readonly AgencyTicketService $agencyTicketService,
        private readonly TicketService $baseTicketService
    ) {
        // Block 'staff' role from accessing agency tickets
        abort_if(auth()->check() && auth()->user()->hasRole('staff'), 403, 'Unauthorized access.');
    }

    // Index is handled by ScaffoldController now

    public function createTicket(): Response|RedirectResponse
    {
        if (! module_enabled('helpdesk')) {
            return to_route('agency.websites.index')
                ->with('error', 'Support is not available.');
        }

        return Inertia::render('agency/tickets/create');
    }

    public function storeTicket(Request $request): RedirectResponse|JsonResponse
    {
        if (! module_enabled('helpdesk')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Support is not available.'], 400);
            }

            return to_route('agency.websites.index')
                ->with('error', 'Support is not available.');
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'],
        ]);

        $defaultDepartmentId = $this->resolveDefaultDepartmentId();
        if ($defaultDepartmentId === null) {
            $message = 'No active support department is configured.';

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['subject' => $message]);
        }

        $user = auth()->user();

        // Use the TicketService to properly create the ticket with ticket_number
        // We can use base service or agency service here since they share logic
        $ticket = $this->baseTicketService->create([
            'user_id' => $user->id,
            'department_id' => $defaultDepartmentId,
            'subject' => $validated['subject'],
            'description' => $validated['message'],
            'priority' => 'medium',
            'status' => 'open',
            'attachments' => $request->file('attachments') ?? [],
        ]);
        /** @var Ticket $ticket */
        $redirectUrl = route('agency.tickets.show', $ticket);
        $successMessage = 'Your support ticket has been submitted.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'data' => ['id' => $ticket->id],
                'redirect' => $redirectUrl,
            ], 201);
        }

        return redirect()
            ->to($redirectUrl)
            ->with('status', $successMessage);
    }

    public function showTicket(int|string $id): Response|RedirectResponse|JsonResponse
    {
        if (! module_enabled('helpdesk')) {
            return to_route('agency.websites.index')
                ->with('error', 'Support is not available.');
        }

        // Use parent show for JSON requests (API)
        if (request()->expectsJson()) {
            return parent::show($id);
        }

        // Use custom logic for HTML view (preserving existing behavior)
        $model = $this->findModel($id);
        /** @var Ticket $ticket */
        $ticket = $model;

        return Inertia::render('agency/tickets/show', [
            'ticket' => $this->serializeTicket($ticket),
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse|JsonResponse
    {
        if (! module_enabled('helpdesk')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Support is not available.'], 400);
            }

            return to_route('agency.websites.index')
                ->with('error', 'Support is not available.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:5'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'],
        ]);

        $user = auth()->user();

        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Process attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            $attachments = $this->baseTicketService->uploadAttachments(
                $request->file('attachments')
            );
        }

        $ticket->replies()->create([
            'reply_by' => $user->id,
            'content' => $validated['message'],
            'attachments' => $attachments !== [] ? $attachments : null,
        ]);

        // Reopen ticket if it was closed
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open']);
        }

        $ticket->touch();

        $redirectUrl = route('agency.tickets.show', $ticket);
        $successMessage = 'Your reply has been added.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect()
            ->to($redirectUrl)
            ->with('status', $successMessage);
    }

    protected function service(): AgencyTicketService
    {
        return $this->agencyTicketService;
    }

    protected function findModel(int|string $id): Model
    {
        $user = auth()->user();

        // Custom finding logic to ensure user ownership + eager loading
        return Ticket::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->with(['department', 'replies.replyBy'])
            ->firstOrFail();
    }

    private function resolveDefaultDepartmentId(): ?int
    {
        $departments = Department::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        /** @var Department|null $supportDepartment */
        $supportDepartment = $departments->first(
            fn (Department $department): bool => strcasecmp($department->name, 'support') === 0
        );

        return $supportDepartment?->id ?? $departments->first()?->id; // @phpstan-ignore nullsafe.neverNull
    }

    private function serializeTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'description_html' => clean((string) $ticket->description),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'attachments' => $this->serializeAttachments($ticket->attachments),
            'replies' => $ticket->replies
                ->sortByDesc('created_at')
                ->map(fn ($reply): array => [
                    'id' => $reply->id,
                    'author_name' => $reply->replyBy->name ?? 'User',
                    'is_staff' => $reply->reply_by !== auth()->id(),
                    'content_html' => clean((string) $reply->content),
                    'created_at' => $reply->created_at?->toIso8601String(),
                    'attachments' => $this->serializeAttachments($reply->attachments),
                ])
                ->values()
                ->all(),
        ];
    }

    private function serializeAttachments(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->map(function ($attachment): ?array {
                $path = is_array($attachment) ? ($attachment['file_path'] ?? null) : $attachment;
                if (! is_string($path) || $path === '') {
                    return null;
                }

                $name = is_array($attachment)
                    ? ($attachment['file_name'] ?? basename($path))
                    : basename($path);

                return [
                    'name' => $name,
                    'url' => get_media_url($path),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
