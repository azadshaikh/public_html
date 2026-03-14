<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\EmailProviderDefinition;
use App\Http\Resources\EmailProviderResource;
use App\Models\EmailProvider;
use App\Scaffold\ScaffoldController;
use App\Services\EmailProviderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class EmailProviderController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly EmailProviderService $emailProviderService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return (new EmailProviderDefinition)->getMiddleware();
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): EmailProviderService
    {
        return $this->emailProviderService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/email/providers';
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->emailProviderService->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'emailProviders' => $this->emailProviderService->getPaginatedEmailProviders($request),
            'statistics' => $this->emailProviderService->getStatistics(),
            'filters' => [
                'search' => $request->input('search', ''),
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

    // ================================================================
    // VALIDATION
    // ================================================================

    // ================================================================
    // FORM VIEW DATA (dropdowns, options for create/edit)
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        $status = $model->getAttribute('status');

        return [
            'initialValues' => [
                'name' => (string) ($model->getAttribute('name') ?? ''),
                'description' => (string) ($model->getAttribute('description') ?? ''),
                'sender_name' => (string) ($model->getAttribute('sender_name') ?? ''),
                'sender_email' => (string) ($model->getAttribute('sender_email') ?? ''),
                'smtp_host' => (string) ($model->getAttribute('smtp_host') ?? ''),
                'smtp_user' => (string) ($model->getAttribute('smtp_user') ?? ''),
                'smtp_password' => '',
                'smtp_port' => (string) ($model->getAttribute('smtp_port') ?? ''),
                'smtp_encryption' => (string) ($model->getAttribute('smtp_encryption') ?? 'none'),
                'reply_to' => (string) ($model->getAttribute('reply_to') ?? ''),
                'bcc' => (string) ($model->getAttribute('bcc') ?? ''),
                'signature' => (string) ($model->getAttribute('signature') ?? ''),
                'status' => is_object($status) && isset($status->value)
                    ? (string) $status->value
                    : (string) ($status ?? 'active'),
                'order' => (string) ($model->getAttribute('order') ?? '0'),
            ],
            'statusOptions' => $this->emailProviderService->getStatusOptions(),
            'encryptionOptions' => $this->emailProviderService->getEncryptionOptions(),
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        $provider = $model instanceof EmailProvider ? $model : EmailProvider::withTrashed()->findOrFail((int) $model->getKey());
        $data = (new EmailProviderResource($provider))->resolve(request());

        $data['smtp_encryption_label'] = $provider->smtp_encryption
            ? strtoupper((string) $provider->smtp_encryption)
            : 'None';
        $data['has_smtp_password'] = filled($provider->getAttribute('smtp_password'));

        return $data;
    }

    protected function transformModelForEdit(Model $model): array
    {
        $status = $model->getAttribute('status');

        return [
            'id' => $model->getKey(),
            'name' => (string) ($model->getAttribute('name') ?? ''),
            'sender_email' => (string) ($model->getAttribute('sender_email') ?? ''),
            'status' => is_object($status) && isset($status->value)
                ? (string) $status->value
                : (string) ($status ?? 'active'),
        ];
    }

    // ================================================================
    // OPTIONAL: Side effects after CRUD operations
    // ================================================================

    protected function handleCreationSideEffects(Model $model): void
    {
        // Add any side effects after creating an email provider
        // e.g., cache invalidation, notifications, etc.
    }

    protected function handleUpdateSideEffects(Model $model): void
    {
        // Add any side effects after updating an email provider
    }

    protected function handleDeletionSideEffects(Model $model): void
    {
        // Add any side effects after deleting an email provider
    }
}
