<?php

namespace App\Jobs;

use App\DataTransferObjects\EmailSendResult;
use App\Models\User;
use App\Services\EmailService;
use App\Traits\IsMonitored;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Job to send authentication-related emails asynchronously.
 *
 * Supports: verification emails, password reset emails, welcome emails
 */
class SendAuthEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    // public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $type,
        public int $userId,
        public array $data = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            Log::warning('SendAuthEmail: User not found', [
                'user_id' => $this->userId,
                'type' => $this->type,
            ]);

            return;
        }

        $result = match ($this->type) {
            'verification' => $this->sendVerificationEmail($emailService, $user),
            'password_reset' => $this->sendPasswordResetEmail($emailService, $user),
            'welcome' => $this->sendWelcomeEmail($emailService, $user),
            default => $this->handleUnknownType(),
        };

        if ($result instanceof EmailSendResult && $result->failed()) {
            Log::warning('SendAuthEmail: Email sending failed', [
                'user_id' => $this->userId,
                'type' => $this->type,
                'error' => $result->error,
            ]);

            // Re-throw to trigger retry
            throw new RuntimeException(sprintf('Failed to send %s email: %s', $this->type, $result->error));
        }

        Log::info('SendAuthEmail: Email sent successfully', [
            'user_id' => $this->userId,
            'type' => $this->type,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendAuthEmail: Job failed after all retries', [
            'user_id' => $this->userId,
            'type' => $this->type,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Send verification email.
     */
    protected function sendVerificationEmail(EmailService $emailService, User $user): EmailSendResult
    {
        return $emailService->sendVerificationEmail($user);
    }

    /**
     * Send password reset email.
     */
    protected function sendPasswordResetEmail(EmailService $emailService, User $user): bool
    {
        $token = $this->data['token'] ?? null;

        if (! $token) {
            Log::error('SendAuthEmail: Password reset token missing', [
                'user_id' => $this->userId,
            ]);

            return false;
        }

        return $emailService->sendPasswordResetEmail($user->email, $token);
    }

    /**
     * Send welcome email after registration.
     */
    protected function sendWelcomeEmail(EmailService $emailService, User $user): bool
    {
        return $emailService->sendEmail(
            $user->email,
            'Welcome Email',
            [
                'user_name' => $user->name,
                'contact_firstname' => $user->first_name ?: $user->name,
                'email' => $user->email,
                'login_url' => route('login'),
            ]
        );
    }

    /**
     * Handle unknown email type.
     */
    protected function handleUnknownType(): bool
    {
        Log::error('SendAuthEmail: Unknown email type', [
            'type' => $this->type,
            'user_id' => $this->userId,
        ]);

        return false;
    }
}
