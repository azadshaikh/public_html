<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\EmailProviderDefinition;
use App\Scaffold\ScaffoldController;
use App\Services\EmailProviderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;

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

    // ================================================================
    // VALIDATION
    // ================================================================

    // ================================================================
    // FORM VIEW DATA (dropdowns, options for create/edit)
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->emailProviderService->getStatusOptions(),
            'encryptionOptions' => $this->emailProviderService->getEncryptionOptions(),
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
