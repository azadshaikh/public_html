<?php

namespace App\Services;

use App\DataTransferObjects\EmailSendResult;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EmailService
{
    private ?EmailProvider $provider = null;

    private ?EmailTemplate $template = null;

    private array $variables = [];

    private array $recipients = [];

    private bool $fallbackToDefaultMailer = true;

    public function withProvider(?EmailProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function withTemplate(EmailTemplate|string $template): self
    {
        if (! $template instanceof EmailTemplate) {
            $template = $this->getTemplateByName($template);
        }

        $this->template = $template;

        return $this;
    }

    public function withVariables(array $variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    public function to(string|array $recipients): self
    {
        $this->recipients = array_filter(array_unique(array_merge(
            Arr::wrap($recipients),
            $this->template?->getSendToRecipients() ?? []
        )));

        return $this;
    }

    public function allowFallback(bool $value = true): self
    {
        $this->fallbackToDefaultMailer = $value;

        return $this;
    }

    public function reset(): void
    {
        $this->provider = null;
        $this->template = null;
        $this->variables = [];
        $this->recipients = [];
        $this->fallbackToDefaultMailer = true;
    }

    public function getDefaultProvider(): ?EmailProvider
    {
        return EmailProvider::query()
            ->active()
            ->orderBy('order')
            ->orderBy('id')
            ->first();
    }

    public function getTemplateByName(string $name): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->where('name', $name)
            ->active()
            ->first();
    }

    public function sendTemplate(
        EmailTemplate|string $template,
        string|array $recipients,
        array $variables = [],
        ?EmailProvider $provider = null,
        array $options = []
    ): EmailSendResult {
        $initialTemplateName = $template instanceof EmailTemplate ? $template->name : $template;
        $initialTemplateId = $template instanceof EmailTemplate ? $template->id : null;
        $manualRecipients = $this->normalizeRecipientsInput($recipients);
        $compiledSubject = null;
        $compiledMessage = null;

        try {
            $this->withTemplate($template);

            $resolvedTemplate = $this->template;

            if (! $resolvedTemplate instanceof EmailTemplate) {
                $result = EmailSendResult::failure('Email template not found.', [
                    'template' => $initialTemplateId ?? $initialTemplateName,
                ]);

                $this->recordEmailLog(
                    null,
                    null,
                    $initialTemplateName,
                    $compiledSubject,
                    $compiledMessage,
                    $result,
                    $variables,
                    $options,
                    $manualRecipients
                );

                return $result;
            }

            $templateProvider = $resolvedTemplate->provider;
            if (! $templateProvider instanceof EmailProvider) {
                $templateProvider = null;
            }

            $selectedProvider = $provider
                ?? $this->provider
                ?? $templateProvider
                ?? $this->getDefaultProvider();

            if (! $selectedProvider instanceof EmailProvider) {
                $result = EmailSendResult::failure('No active email provider is configured.', [
                    'template_id' => $resolvedTemplate->id,
                ]);

                $this->recordEmailLog(
                    $resolvedTemplate,
                    null,
                    $resolvedTemplate->name,
                    $compiledSubject,
                    $compiledMessage,
                    $result,
                    $variables,
                    $options,
                    $manualRecipients
                );

                return $result;
            }

            $this->withProvider($selectedProvider)
                ->withVariables($variables)
                ->to($recipients)
                ->allowFallback($options['fallback_to_default'] ?? true);

            if ($this->recipients === []) {
                $result = EmailSendResult::failure('No email recipients supplied.', [
                    'template_id' => $resolvedTemplate->id,
                ]);

                $this->recordEmailLog(
                    $resolvedTemplate,
                    $selectedProvider,
                    $resolvedTemplate->name,
                    $compiledSubject,
                    $compiledMessage,
                    $result,
                    $variables,
                    $options,
                    []
                );

                return $result;
            }

            [$subject, $message] = $this->compileTemplate($selectedProvider);

            $compiledSubject = $subject;
            $compiledMessage = $message;

            $result = $this->deliver($selectedProvider, $subject, $message, $this->recipients);

            $this->recordEmailLog(
                $resolvedTemplate,
                $selectedProvider,
                $resolvedTemplate->name,
                $compiledSubject,
                $compiledMessage,
                $result,
                $variables,
                $options
            );

            return $result;
        } catch (Throwable $throwable) {
            $templateForLog = $this->template;
            $providerForLog = $this->provider;
            $templateId = $initialTemplateId;
            $templateNameForLog = $initialTemplateName;

            if ($templateForLog instanceof EmailTemplate) {
                $templateId = $templateForLog->id;
                $templateNameForLog = $templateForLog->name;
            }

            Log::error('Unexpected email dispatch failure', [
                'error' => $throwable->getMessage(),
                'template_id' => $templateId,
                'provider_id' => $providerForLog?->id,
            ]);

            $result = EmailSendResult::failure($throwable->getMessage(), [
                'template_id' => $templateId,
                'provider_id' => $providerForLog?->id,
            ]);

            $this->recordEmailLog(
                $templateForLog,
                $providerForLog,
                $templateNameForLog,
                $compiledSubject,
                $compiledMessage,
                $result,
                $variables,
                $options
            );

            return $result;
        } finally {
            $this->reset();
        }
    }

    public function sendPasswordResetEmail(string $email, string $token): bool
    {
        $resetUrl = URL::temporarySignedRoute(
            'password.reset',
            now()->addMinutes(config('auth.passwords.users.expire', 60)),
            ['token' => $token, 'email' => $email]
        );

        $result = $this->sendTemplate(
            'Forget Password',
            $email,
            [
                'contact_firstname' => explode('@', $email)[0],
                'set_password_url' => $resetUrl,
                'reset_password_url' => $resetUrl,
            ]
        );

        if ($result->failed()) {
            Log::warning('Failed to send password reset email', $result->toArray());
        }

        return $result->success;
    }

    public function sendEmail(string $to, string $templateName, array $variables = [], ?EmailProvider $provider = null): bool
    {
        $result = $this->sendTemplate($templateName, $to, $variables, $provider);

        if ($result->failed()) {
            Log::warning('Failed to send templated email', $result->toArray());
        }

        return $result->success;
    }

    public function sendVerificationEmail(User $user): EmailSendResult
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $verificationTemplateId = (int) config('mail.templates.verify_email_id', 2);
        $verificationTemplate = EmailTemplate::query()->find($verificationTemplateId);

        if (! $verificationTemplate) {
            Log::warning('Verification email template not configured.', [
                'template_id' => $verificationTemplateId,
            ]);

            try {
                $user->sendFallbackEmailVerificationNotification();

                return EmailSendResult::success([
                    'fallback' => 'default_notification',
                    'primary_error' => 'Verification email template not configured.',
                ]);
            } catch (Throwable $fallbackException) {
                Log::error('Failed to send verification email', [
                    'user_id' => $user->getKey(),
                    'primary_error' => 'Verification email template not configured.',
                    'fallback_error' => $fallbackException->getMessage(),
                ]);

                return EmailSendResult::failure($fallbackException->getMessage(), [
                    'primary_error' => 'Verification email template not configured.',
                ]);
            }
        }

        $firstName = $user->first_name
            ?: Str::of($user->name)->before(' ')->trim()->value()
            ?: $user->name;

        $result = $this->sendTemplate(
            $verificationTemplate,
            $user->email,
            [
                'user_name' => $user->name,
                'email' => $user->email,
                'verification_url' => $verificationUrl,
                'verify_url' => $verificationUrl,
                'verification_link' => $verificationUrl,
                'email_verification_url' => $verificationUrl,
                'contact_firstname' => $firstName,
            ]
        );

        if ($result->failed()) {
            // Do not retry when the template itself is missing.
            if (str_contains(strtolower($result->error ?? ''), 'template not found')) {
                return $result;
            }

            try {
                $user->sendFallbackEmailVerificationNotification();

                return EmailSendResult::success([
                    'fallback' => 'default_notification',
                    'primary_error' => $result->error,
                ]);
            } catch (Throwable $fallbackException) {
                Log::error('Failed to send verification email', [
                    'user_id' => $user->getKey(),
                    'primary_error' => $result->error,
                    'fallback_error' => $fallbackException->getMessage(),
                ]);

                return EmailSendResult::failure($fallbackException->getMessage(), [
                    'primary_error' => $result->error,
                ]);
            }
        }

        return $result;
    }

    private function recordEmailLog(
        ?EmailTemplate $template,
        ?EmailProvider $provider,
        ?string $templateName,
        ?string $subject,
        ?string $body,
        EmailSendResult $result,
        array $variables,
        array $options,
        ?array $recipientsOverride = null
    ): void {
        try {
            $recipients = $recipientsOverride ?? $this->normalizeRecipientsArray($this->recipients);

            $context = $this->buildLogContext($variables, $options, $result->context ?? []);

            EmailLog::query()->create([
                'email_template_id' => $template?->id,
                'template_name' => $templateName ?? $template?->name,
                'email_provider_id' => $provider?->id,
                'provider_name' => $provider?->name,
                'sent_by' => Auth::id(),
                'status' => $result->success ? EmailLog::STATUS_SENT : EmailLog::STATUS_FAILED,
                'subject' => $subject,
                'body' => $body,
                'recipients' => $recipients === [] ? null : array_values($recipients),
                'error_message' => $result->error,
                'context' => $context,
                'sent_at' => $result->success ? now() : null,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Failed to record email log', [
                'error' => $throwable->getMessage(),
                'template_id' => $template?->id,
            ]);
        }
    }

    private function normalizeRecipientsInput(string|array $recipients): array
    {
        return $this->normalizeRecipientsArray(Arr::wrap($recipients));
    }

    private function normalizeRecipientsArray(?array $recipients): array
    {
        if ($recipients === null || $recipients === []) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(trim(...), $recipients))));
    }

    private function buildLogContext(array $variables, array $options, array $resultContext): ?array
    {
        $context = [];

        if ($variables !== []) {
            $context['variables'] = $variables;
        }

        if ($options !== []) {
            $context['options'] = $options;
        }

        if ($resultContext !== []) {
            $context['result'] = $resultContext;
        }

        return $context === [] ? null : $context;
    }

    private function compileTemplate(EmailProvider $provider): array
    {
        $subject = $this->processTemplate($this->template->subject ?? '', $provider);
        $message = $this->processTemplate($this->template->message ?? '', $provider);

        return [$subject, $message];
    }

    private function processTemplate(string $content, EmailProvider $provider): string
    {
        $defaults = [
            'app_name' => setting('site_title', config('app.name')),
            'app_url' => config('app.url'),
            'email_signature' => $provider->signature ?? '',
        ];

        $allVariables = array_merge($defaults, $this->variables);

        foreach ($allVariables as $key => $value) {
            $content = str_replace('{'.$key.'}', (string) $value, $content);
        }

        return $content;
    }

    private function deliver(EmailProvider $provider, string $subject, string $message, array $recipients): EmailSendResult
    {
        $this->configureMailSettings($provider);

        $context = [
            'provider_id' => $provider->id,
            'template_id' => $this->template?->id,
            'recipients' => $recipients,
        ];

        try {
            Mail::mailer('dynamic')
                ->to($recipients)
                ->send($this->buildMailable($subject, $message, $provider));

            return EmailSendResult::success($context);
        } catch (Throwable $throwable) {
            Log::warning('Primary mail transport failed', $context + [
                'error' => $throwable->getMessage(),
            ]);

            // Retry only for transport-level failures.
            if (! $this->fallbackToDefaultMailer || ! $this->isTransportFailure($throwable)) {
                return EmailSendResult::failure($throwable->getMessage(), $context);
            }

            $fallbackMailer = config('mail.default', 'smtp');

            if ($fallbackMailer === 'dynamic') {
                return EmailSendResult::failure($throwable->getMessage(), $context + [
                    'attempts' => 1,
                ]);
            }

            $attempts = 1;
            $lastException = $throwable;

            for ($attempt = 2; $attempt <= 3; $attempt++) {
                $attempts = $attempt;

                try {
                    Mail::mailer($fallbackMailer)
                        ->to($recipients)
                        ->send($this->buildMailable($subject, $message, $provider));

                    return EmailSendResult::success($context + [
                        'fallback_mailer' => $fallbackMailer,
                        'primary_error' => $throwable->getMessage(),
                        'attempts' => $attempt,
                    ]);
                } catch (Throwable $fallbackException) {
                    $lastException = $fallbackException;

                    Log::error('Fallback mail transport failed', $context + [
                        'primary_error' => $throwable->getMessage(),
                        'fallback_error' => $fallbackException->getMessage(),
                        'attempts' => $attempts,
                    ]);

                    if (! $this->isTransportFailure($fallbackException)) {
                        return EmailSendResult::failure($fallbackException->getMessage(), $context + [
                            'primary_error' => $throwable->getMessage(),
                            'attempts' => $attempts,
                        ]);
                    }
                }
            }

            return EmailSendResult::failure($lastException->getMessage(), $context + [
                'primary_error' => $throwable->getMessage(),
                'attempts' => $attempts,
            ]);
        }
    }

    private function configureMailSettings(EmailProvider $provider): void
    {
        Config::set([
            'mail.mailers.dynamic' => [
                'transport' => 'smtp',
                'host' => $provider->smtp_host,
                'port' => $provider->smtp_port,
                'encryption' => $provider->smtp_encryption,
                'username' => $provider->smtp_user,
                'password' => $provider->smtp_password,
                'timeout' => null,
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ],
            'mail.from' => [
                'address' => $provider->sender_email,
                'name' => $provider->sender_name,
            ],
        ]);
    }

    private function buildMailable(string $subject, string $message, ?EmailProvider $provider = null): Mailable
    {
        return new class($subject, $message, $provider) extends Mailable
        {
            public function __construct(
                private readonly string $emailSubject,
                private readonly string $emailMessage,
                private readonly ?EmailProvider $emailProvider = null
            ) {}

            public function build(): self
            {
                if ($this->emailProvider instanceof EmailProvider) {
                    $this->from(
                        $this->emailProvider->sender_email,
                        $this->emailProvider->sender_name
                    );
                }

                $mail = $this->subject($this->emailSubject)
                    ->html($this->emailMessage);

                if ($this->emailProvider?->reply_to) {
                    $mail->replyTo($this->emailProvider->reply_to);
                }

                if ($this->emailProvider?->bcc) {
                    $mail->bcc($this->emailProvider->bcc);
                }

                return $mail;
            }
        };
    }

    private function isTransportFailure(Throwable $exception): bool
    {
        if ($exception instanceof TransportExceptionInterface) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'connection timed out')
            || str_contains($message, 'could not be established')
            || str_contains($message, 'connection refused');
    }
}
