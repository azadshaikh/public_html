<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\EmailLogDefinition;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use App\Scaffold\ScaffoldController;
use App\Services\EmailLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
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
        return (new EmailLogDefinition)->getMiddleware();
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

        $filters = $this->emailLogService->collectRequestFilters($request);

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->emailLogService->getInertiaConfig(),
            'emailLogs' => $this->emailLogService->getPaginatedEmailLogs($request),
            'statistics' => $this->emailLogService->getStatistics(),
            'providerOptions' => $this->emailLogService->getProviderOptions(),
            'templateOptions' => $this->emailLogService->getTemplateOptions(),
            'filters' => $filters,
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
