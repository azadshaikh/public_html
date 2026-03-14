<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\EmailTemplateDefinition;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldController;
use App\Services\EmailService;
use App\Services\EmailTemplateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly EmailTemplateService $emailTemplateService) {}

    public static function middleware(): array
    {
        return array_merge(
            (new EmailTemplateDefinition)->getMiddleware(),
            [
                new Middleware(EnsureSuperUserAccess::class, only: ['sendTestEmail']),
            ]
        );
    }

    public function sendTestEmail(Request $request, EmailTemplate $emailTemplate, EmailService $emailService): JsonResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'email'],
        ]);

        $result = $emailService->sendTemplate(
            $emailTemplate,
            $data['recipient'],
            [],
            $emailTemplate->provider instanceof EmailProvider ? $emailTemplate->provider : null
        );

        if ($result->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->error ?? 'Failed to send test email.',
                'context' => $result->context,
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Test email sent successfully.',
        ]);
    }

    protected function service(): EmailTemplateService
    {
        return $this->emailTemplateService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/email/templates';
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->emailTemplateService->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'emailTemplates' => $this->emailTemplateService->getPaginatedEmailTemplates($request),
            'statistics' => $this->emailTemplateService->getStatistics(),
            'providerOptions' => $this->emailTemplateService->getProviderOptions(),
            'filters' => [
                'search' => $request->input('search', ''),
                'provider_id' => $request->input('provider_id', ''),
                'created_at' => $request->input('created_at', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'name'),
                'direction' => $request->input('direction', 'asc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    protected function getFormViewData(Model $model): array
    {
        $status = $model->getAttribute('status');

        return [
            'initialValues' => [
                'name' => (string) ($model->getAttribute('name') ?? ''),
                'subject' => (string) ($model->getAttribute('subject') ?? ''),
                'message' => (string) ($model->getAttribute('message') ?? ''),
                'send_to' => (string) ($model->getAttribute('send_to') ?? ''),
                'provider_id' => (string) ($model->getAttribute('provider_id') ?? ''),
                'is_raw' => (bool) ($model->getAttribute('is_raw') ?? false),
                'status' => is_object($status) && isset($status->value)
                    ? (string) $status->value
                    : (string) ($status ?? 'active'),
            ],
            'statusOptions' => $this->emailTemplateService->getStatusOptions(),
            'providerOptions' => $this->emailTemplateService->getProviderOptions(),
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        $template = $model instanceof EmailTemplate
            ? $model->loadMissing('provider:id,name')
            : EmailTemplate::withTrashed()->with('provider:id,name')->findOrFail((int) $model->getKey());

        $data = (new EmailTemplateResource($template))->resolve(request());
        $data['send_to_list'] = $template->getSendToRecipients();

        return $data;
    }

    protected function transformModelForEdit(Model $model): array
    {
        $status = $model->getAttribute('status');

        return [
            'id' => $model->getKey(),
            'name' => (string) ($model->getAttribute('name') ?? ''),
            'subject' => (string) ($model->getAttribute('subject') ?? ''),
            'status' => is_object($status) && isset($status->value)
                ? (string) $status->value
                : (string) ($status ?? 'active'),
            'provider_id' => (string) ($model->getAttribute('provider_id') ?? ''),
        ];
    }

    protected function handleCreationSideEffects(Model $model): void
    {
        // Add any post-create side effects (activity log, cache, etc.) as needed
    }

    protected function handleUpdateSideEffects(Model $model): void
    {
        // Add any post-update side effects as needed
    }

    protected function handleDeletionSideEffects(Model $model): void
    {
        // Add any post-delete side effects as needed
    }
}
