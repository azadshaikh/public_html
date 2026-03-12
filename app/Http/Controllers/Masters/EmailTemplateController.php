<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\EmailTemplateDefinition;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldController;
use App\Services\EmailService;
use App\Services\EmailTemplateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmailTemplateController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly EmailTemplateService $emailTemplateService) {}

    public static function middleware(): array
    {
        return array_merge(
            (new EmailTemplateDefinition)->getMiddleware(),
            [
                new Middleware('permission:edit_email_templates', only: ['sendTestEmail']),
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

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->emailTemplateService->getStatusOptions(),
            'providerOptions' => $this->emailTemplateService->getProviderOptions(),
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
