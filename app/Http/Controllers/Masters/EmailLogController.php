<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use App\Scaffold\ScaffoldController;
use App\Services\EmailLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly EmailLogService $emailLogService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_email_logs', only: ['index', 'show']),
        ];
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): EmailLogService
    {
        return $this->emailLogService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/email/logs';
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->emailLogService->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'emailLogs' => $this->emailLogService->getPaginatedEmailLogs($request),
            'statistics' => $this->emailLogService->getStatistics(),
            'providerOptions' => $this->emailLogService->getProviderOptions(),
            'templateOptions' => $this->emailLogService->getTemplateOptions(),
            'filters' => [
                'search' => $request->input('search', ''),
                'email_provider_id' => $request->input('email_provider_id', ''),
                'email_template_id' => $request->input('email_template_id', ''),
                'sent_at' => $request->input('sent_at', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'sent_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    protected function transformModelForShow(Model $model): array
    {
        $emailLog = $model instanceof EmailLog
            ? $model->loadMissing(['template:id,name', 'provider:id,name', 'sender:id,first_name,last_name'])
            : EmailLog::withTrashed()
                ->with(['template:id,name', 'provider:id,name', 'sender:id,first_name,last_name'])
                ->findOrFail((int) $model->getKey());

        $data = (new EmailLogResource($emailLog))->resolve(request());
        $data['body'] = (string) ($emailLog->getAttribute('body') ?? '');
        $data['context'] = $emailLog->getAttribute('context') ?? [];
        $data['sender_name'] = $emailLog->sender?->name;

        return $data;
    }
}
