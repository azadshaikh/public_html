<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Definitions\WebsiteDefinition;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Jobs\WebsiteRemoveFromServer;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerSSHService;
use Modules\Platform\Services\WebsiteLifecycleService;
use Modules\Platform\Services\WebsiteProvisioningService;
use Modules\Platform\Services\WebsiteService;
use RuntimeException;

/**
 * Website Controller following BaseCrudController patterns.
 *
 * This controller handles the CRUD operations for websites using the
 * standardized BaseCrudController architecture with DataGrid support.
 * It also handles website lifecycle events (status, trash) and provisioning steps.
 */
class WebsiteController extends ScaffoldController implements HasMiddleware
{
    private const int WEBSITE_LARAVEL_LOG_TAIL_LINES = 400;

    public function __construct(
        private readonly WebsiteService $websiteService,
        private readonly WebsiteLifecycleService $websiteLifecycleService,
        private readonly WebsiteProvisioningService $websiteProvisioningService
    ) {}

    // =============================================================================
    // MIDDLEWARE
    // =============================================================================

    public static function middleware(): array
    {
        return [
            // Standard CRUD middleware from definition
            ...(new WebsiteDefinition)->getMiddleware(),
            // Custom endpoint permissions
            new Middleware('permission:view_websites', only: ['websiteLog']),
            new Middleware('permission:add_websites', only: ['createFromOrder']),
            new Middleware('permission:edit_websites', only: ['updateStatus', 'updateVersion', 'syncWebsite', 'recacheApplication', 'retryProvision', 'executeStep', 'revertStep', 'updateSetupStatus', 'setupQueueWorker', 'scaleQueueWorker', 'reprovision', 'revealSecret', 'confirmDns', 'stopDnsValidation', 'clearWebsiteLog', 'websiteEnv', 'updateWebsiteEnv']),
            new Middleware('permission:delete_websites', only: ['removeFromServer']),
        ];
    }

    public function revealSecret(Request $request, int|string $website, int|string $secret): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->findOrFail((int) $website);

        /** @var Secret $secretModel */
        $secretModel = $websiteModel->secrets()->whereKey((int) $secret)->firstOrFail();

        $this->logActivity($websiteModel, ActivityAction::VIEW, sprintf("Revealed website secret '%s'.", $secretModel->key));

        return response()
            ->json([
                'success' => true,
                'value' => $secretModel->decrypted_value,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function createFromOrder($order_id): Response
    {
        $ordersModelClass = 'Modules\\Orders\\Models\\Order';
        abort_unless(class_exists($ordersModelClass), 404, 'Order module is not available on this deployment.');

        $order = $ordersModelClass::findOrFail($order_id);
        $website = new Website;

        $viewData = [
            ...$this->getFormViewData($website),
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference ?? null,
            ],
            'order_id' => $order->id,
        ];

        $orderItem = $order->orderItems->first();
        if ($orderItem) {
            $website->domain = $orderItem->domain_name;
            $viewData['initialValues']['domain'] = $orderItem->domain_name;
            $viewData['initialValues']['item_id'] = (string) $orderItem->id;
        }

        $viewData['initialValues']['order_id'] = (string) $order->id;

        $viewData['website'] = [
            'id' => null,
            'name' => $website->name,
            'uid' => null,
        ];

        return Inertia::render($this->inertiaPage().'/create', $viewData);
    }

    public function show(int|string $id): Response
    {
        $website = Website::withTrashed()
            ->with(['server', 'agency', 'providers', 'domainRecord', 'sslCertificate'])
            ->findOrFail((int) $id);

        abort_unless($website instanceof Website, 404);

        // Get activity logs for this website
        $activities = ActivityLog::query()
            ->forModel(Website::class, $website->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $canRevealSecrets = (bool) auth()->user()?->can('edit_websites');
        $provisioningPayload = $this->buildProvisioningStatusPayload($website);

        return Inertia::render($this->inertiaPage().'/show', [
            'website' => $this->transformWebsiteForShow($website),
            'secrets' => ($canRevealSecrets ? $website->secrets()->orderBy('key')->get() : collect())
                ->map(fn ($secret): array => [
                    'id' => $secret->getKey(),
                    'key' => (string) $secret->key,
                    'label' => str($secret->key)->replace('_', ' ')->headline()->toString(),
                    'username' => $secret->username,
                ])
                ->values()
                ->all(),
            'provisioningSteps' => $provisioningPayload['provisioning_steps'],
            'provisioningRun' => $provisioningPayload['provisioning_run'],
            'updates' => collect($website->getUpdateHistoryForView())
                ->map(function ($value, $key): array {
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }

                    return [
                        'key' => (string) $key,
                        'label' => str((string) $key)->replace('_', ' ')->headline()->toString(),
                        'value' => (string) ($value ?? '—'),
                    ];
                })
                ->values()
                ->all(),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
            'pullzoneId' => $website->pullzone_id,
            'canRevealSecrets' => $canRevealSecrets,
            'canManageLaravelLog' => (bool) auth()->user()?->can('edit_websites'),
            'canManageWebsiteEnv' => (bool) auth()->user()?->can('edit_websites'),
        ]);
    }

    public function websiteEnv(int|string $website): JsonResponse
    {
        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->with('server')->findOrFail((int) $website);

        if (! $websiteModel->server) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have a server assigned.',
            ], 422);
        }

        if (blank($websiteModel->website_username) || blank($websiteModel->domain)) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have enough runtime details to locate the shared environment file.',
            ], 422);
        }

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand($websiteModel->server, $this->buildWebsiteEnvReadCommand($websiteModel), 30);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to read website environment file.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $this->parseWebsiteEnvOutput((string) data_get($result, 'data.output', '')),
        ]);
    }

    public function updateWebsiteEnv(Request $request, int|string $website): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->with('server')->findOrFail((int) $website);

        if (! $websiteModel->server) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have a server assigned.',
            ], 422);
        }

        if (blank($websiteModel->website_username) || blank($websiteModel->domain)) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have enough runtime details to locate the shared environment file.',
            ], 422);
        }

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand(
            $websiteModel->server,
            $this->buildWebsiteEnvWriteCommand($websiteModel, $validated['content']),
            30
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to update website environment file.',
            ], 500);
        }

        $this->logActivity($websiteModel, ActivityAction::UPDATE, 'Updated website shared environment file.');

        return response()->json([
            'success' => true,
            'message' => 'Website environment file updated successfully. Run recache if the application is using cached configuration.',
        ]);
    }

    public function websiteLog(int|string $website): JsonResponse
    {
        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->with('server')->findOrFail((int) $website);

        if (! $websiteModel->server) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have a server assigned.',
            ], 422);
        }

        if (blank($websiteModel->website_username) || blank($websiteModel->domain)) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have enough runtime details to locate the Laravel log.',
            ], 422);
        }

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand($websiteModel->server, $this->buildWebsiteLaravelLogReadCommand($websiteModel), 30);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to read website Laravel log.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $this->parseWebsiteLaravelLogOutput((string) data_get($result, 'data.output', '')),
        ]);
    }

    public function clearWebsiteLog(int|string $website): JsonResponse
    {
        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->with('server')->findOrFail((int) $website);

        if (! $websiteModel->server) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have a server assigned.',
            ], 422);
        }

        if (blank($websiteModel->website_username) || blank($websiteModel->domain)) {
            return response()->json([
                'success' => false,
                'message' => 'This website does not have enough runtime details to locate the Laravel log.',
            ], 422);
        }

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand($websiteModel->server, $this->buildWebsiteLaravelLogClearCommand($websiteModel), 30);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to clear website Laravel log.',
            ], 500);
        }

        $this->logActivity($websiteModel, ActivityAction::UPDATE, 'Cleared website Laravel log.');

        return response()->json([
            'success' => true,
            'message' => 'Website Laravel log cleared successfully.',
        ]);
    }

    public function provisioningStatus(int|string $website): JsonResponse
    {
        $websiteModel = Website::withTrashed()->findOrFail((int) $website);

        return response()->json($this->buildProvisioningStatusPayload($websiteModel));
    }

    public function confirmDns(int|string $website): JsonResponse
    {
        $websiteModel = Website::withTrashed()->findOrFail((int) $website);
        $statusValue = $websiteModel->status instanceof WebsiteStatus
            ? $websiteModel->status->value
            : (string) $websiteModel->status;

        if ($statusValue !== WebsiteStatus::WaitingForDns->value) {
            return response()->json([
                'status' => 'error',
                'message' => 'Website is not waiting for DNS verification.',
            ], 400);
        }

        $websiteModel->setMetadata('dns_confirmed_by_user', true);
        $websiteModel->setMetadata('dns_confirmed_at', now()->toIso8601String());
        $websiteModel->setMetadata('dns_check_count', 0);
        $websiteModel->setMetadata('dns_last_checked_at', null);
        $websiteModel->setMetadata('dns_check_result', null);
        $websiteModel->setMetadata('dns_domain_not_registered', false);
        $websiteModel->save();

        $websiteModel->updateProvisioningStep(
            'verify_dns',
            'User confirmed DNS update. Verification checks starting.',
            'waiting'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'DNS validation started. Verification checks will begin shortly.',
        ]);
    }

    public function stopDnsValidation(int|string $website): JsonResponse
    {
        $websiteModel = Website::withTrashed()->findOrFail((int) $website);
        $statusValue = $websiteModel->status instanceof WebsiteStatus
            ? $websiteModel->status->value
            : (string) $websiteModel->status;

        if ($statusValue !== WebsiteStatus::WaitingForDns->value) {
            return response()->json([
                'status' => 'error',
                'message' => 'Website is not waiting for DNS verification.',
            ], 400);
        }

        $websiteModel->setMetadata('dns_confirmed_by_user', false);
        $websiteModel->setMetadata('dns_confirmed_at', null);
        $websiteModel->setMetadata('dns_check_count', 0);
        $websiteModel->setMetadata('dns_last_checked_at', null);
        $websiteModel->setMetadata('dns_check_result', null);
        $websiteModel->setMetadata('dns_domain_not_registered', false);
        $websiteModel->save();

        $websiteModel->updateProvisioningStep(
            'verify_dns',
            $this->defaultDnsWaitingMessage($websiteModel),
            'waiting'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'DNS validation stopped. Automatic checks are paused until you start validation again.',
        ]);
    }

    /**
     * @return array{
     *     status: string,
     *     website_steps_data: Collection,
     *     provisioning_steps: array<int, array<string, mixed>>,
     *     provisioning_run: array{started_at: string|null, completed_at: string|null},
     *     percentage: float|int,
     *     current_status: string|null
     * }
     */
    private function buildProvisioningStatusPayload(Website $website): array
    {
        $websiteStepsData = $website->getProvisioningStepsForView();
        $websiteStepsConfig = config('platform.website.steps');
        $provisioningSteps = collect($websiteStepsConfig)
            ->map(function ($config, $key) use ($websiteStepsData, $website): array {
                $stepData = $websiteStepsData->get($key);
                $dnsInstructions = $key === 'verify_dns'
                    ? $this->buildDnsInstructionsPayload($website)
                    : null;

                return [
                    'key' => (string) $key,
                    'title' => $config['title'] ?? str((string) $key)->headline()->toString(),
                    'description' => $config['description'] ?? null,
                    'status' => (string) data_get($stepData, 'status', 'pending'),
                    'message' => data_get($stepData, 'meta_value', data_get($stepData, 'message')),
                    'dns_instructions' => $dnsInstructions,
                    'dns_validation' => $key === 'verify_dns'
                        ? $this->buildDnsValidationPayload($website, $dnsInstructions)
                        : null,
                    'started_at' => $this->formatProvisioningTimestamp(data_get($stepData, 'started_at')),
                    'completed_at' => $this->formatProvisioningTimestamp(data_get($stepData, 'completed_at')),
                ];
            })
            ->values()
            ->all();

        $totalSteps = count($websiteStepsConfig);
        $completedSteps = $websiteStepsData
            ->filter(fn ($step): bool => (string) data_get($step, 'status', 'pending') === 'done')
            ->count();
        $percentage = $totalSteps > 0 ? round($completedSteps / $totalSteps * 100) : 0;

        return [
            'status' => 'success',
            'website_steps_data' => $websiteStepsData,
            'provisioning_steps' => $provisioningSteps,
            'provisioning_run' => [
                'started_at' => $this->formatProvisioningTimestamp($website->getMetadata('provisioning_started_at')),
                'completed_at' => $this->formatProvisioningTimestamp($website->getMetadata('provisioning_completed_at')),
            ],
            'percentage' => $percentage,
            'current_status' => $website->status instanceof WebsiteStatus ? $website->status->value : $website->status,
        ];
    }

    private function buildWebsiteLaravelLogReadCommand(Website $website): string
    {
        $quotedUsername = escapeshellarg((string) $website->website_username);
        $quotedDomain = escapeshellarg((string) $website->domain);
        $tailLines = self::WEBSITE_LARAVEL_LOG_TAIL_LINES;

        return <<<BASH
USERNAME={$quotedUsername}
DOMAIN={$quotedDomain}
TAIL_LINES={$tailLines}
PRIMARY="/home/\${USERNAME}/web/\${DOMAIN}/public_html/current/storage/logs/laravel.log"
FALLBACK="/home/\${USERNAME}/web/\${DOMAIN}/public_html/storage/logs/laravel.log"
LOG=""
for candidate in "\$PRIMARY" "\$FALLBACK"; do
    if [ -f "\$candidate" ]; then
        LOG="\$candidate"
        break
    fi
done

if [ -z "\$LOG" ]; then
    LOG="\$PRIMARY"
fi

if [ -f "\$LOG" ]; then
    SIZE=\$(wc -c < "\$LOG" | tr -d ' ')
    MTIME=\$(stat -c %Y "\$LOG")
    echo "__ASTERO_EXISTS__=1"
    echo "__ASTERO_PATH__=\$LOG"
    echo "__ASTERO_SIZE__=\$SIZE"
    echo "__ASTERO_MTIME__=\$MTIME"
    echo "__ASTERO_CONTENT_START__"
    tail -n {$tailLines} "\$LOG"
else
    echo "__ASTERO_EXISTS__=0"
    echo "__ASTERO_PATH__=\$LOG"
    echo "__ASTERO_SIZE__=0"
    echo "__ASTERO_MTIME__="
    echo "__ASTERO_CONTENT_START__"
fi
BASH;
    }

    private function buildWebsiteEnvReadCommand(Website $website): string
    {
        $quotedUsername = escapeshellarg((string) $website->website_username);
        $quotedDomain = escapeshellarg((string) $website->domain);

        return <<<BASH
USERNAME={$quotedUsername}
DOMAIN={$quotedDomain}
ENV_PATH="/home/\${USERNAME}/web/\${DOMAIN}/public_html/shared/.env"

if [ -f "\$ENV_PATH" ]; then
    SIZE=\$(wc -c < "\$ENV_PATH" | tr -d ' ')
    MTIME=\$(stat -c %Y "\$ENV_PATH")
    echo "__ASTERO_EXISTS__=1"
    echo "__ASTERO_PATH__=\$ENV_PATH"
    echo "__ASTERO_SIZE__=\$SIZE"
    echo "__ASTERO_MTIME__=\$MTIME"
    echo "__ASTERO_CONTENT_START__"
    cat "\$ENV_PATH"
else
    echo "__ASTERO_EXISTS__=0"
    echo "__ASTERO_PATH__=\$ENV_PATH"
    echo "__ASTERO_SIZE__=0"
    echo "__ASTERO_MTIME__="
    echo "__ASTERO_CONTENT_START__"
fi
BASH;
    }

    private function buildWebsiteEnvWriteCommand(Website $website, string $content): string
    {
        $quotedUsername = escapeshellarg((string) $website->website_username);
        $quotedDomain = escapeshellarg((string) $website->domain);
        $encodedContent = base64_encode($content);
        $quotedContent = escapeshellarg($encodedContent);

        return <<<BASH
USERNAME={$quotedUsername}
DOMAIN={$quotedDomain}
ENV_B64={$quotedContent}
SHARED_DIR="/home/\${USERNAME}/web/\${DOMAIN}/public_html/shared"
ENV_PATH="\$SHARED_DIR/.env"
BACKUP_DIR="\$SHARED_DIR/backups/env"

mkdir -p "\$SHARED_DIR"
mkdir -p "\$BACKUP_DIR"

if [ -f "\$ENV_PATH" ]; then
    cp "\$ENV_PATH" "\$BACKUP_DIR/env.\$(date +%Y%m%d%H%M%S).bak"
fi

printf '%s' "\$ENV_B64" | base64 -d > "\$ENV_PATH"
chmod 600 "\$ENV_PATH" 2>/dev/null || true
chown "\$USERNAME:\$USERNAME" "\$ENV_PATH" 2>/dev/null || true
BASH;
    }

    private function buildWebsiteLaravelLogClearCommand(Website $website): string
    {
        $quotedUsername = escapeshellarg((string) $website->website_username);
        $quotedDomain = escapeshellarg((string) $website->domain);

        return <<<BASH
USERNAME={$quotedUsername}
DOMAIN={$quotedDomain}
PRIMARY="/home/\${USERNAME}/web/\${DOMAIN}/public_html/current/storage/logs/laravel.log"
FALLBACK="/home/\${USERNAME}/web/\${DOMAIN}/public_html/storage/logs/laravel.log"
LOG=""
for candidate in "\$PRIMARY" "\$FALLBACK"; do
    if [ -f "\$candidate" ]; then
        LOG="\$candidate"
        break
    fi
done

if [ -z "\$LOG" ]; then
    LOG="\$PRIMARY"
fi

mkdir -p "\$(dirname "\$LOG")"
: > "\$LOG"
BASH;
    }

    /**
     * @return array{path: string, exists: bool, size_bytes: int, modified_at: string|null, tail_lines: int, content: string}
     */
    private function parseWebsiteLaravelLogOutput(string $output): array
    {
        $exists = false;
        $path = '';
        $size = 0;
        $modifiedAt = null;
        $content = '';

        $contentStart = "__ASTERO_CONTENT_START__\n";
        if (str_contains($output, $contentStart)) {
            [$metaSection, $content] = explode($contentStart, $output, 2);
        } else {
            $metaSection = $output;
        }

        foreach (preg_split("/\r\n|\n|\r/", trim($metaSection)) ?: [] as $line) {
            if (str_starts_with($line, '__ASTERO_EXISTS__=')) {
                $exists = trim(substr($line, strlen('__ASTERO_EXISTS__='))) === '1';
            } elseif (str_starts_with($line, '__ASTERO_PATH__=')) {
                $path = trim(substr($line, strlen('__ASTERO_PATH__=')));
            } elseif (str_starts_with($line, '__ASTERO_SIZE__=')) {
                $size = (int) trim(substr($line, strlen('__ASTERO_SIZE__=')));
            } elseif (str_starts_with($line, '__ASTERO_MTIME__=')) {
                $timestamp = trim(substr($line, strlen('__ASTERO_MTIME__=')));
                if ($timestamp !== '' && is_numeric($timestamp)) {
                    $modifiedAt = app_date_time_format(Date::createFromTimestamp((int) $timestamp), 'datetime');
                }
            }
        }

        return [
            'path' => $path,
            'exists' => $exists,
            'size_bytes' => $size,
            'modified_at' => $modifiedAt,
            'tail_lines' => self::WEBSITE_LARAVEL_LOG_TAIL_LINES,
            'content' => rtrim($content),
        ];
    }

    /**
     * @return array{path: string, exists: bool, size_bytes: int, modified_at: string|null, line_count: int, content: string}
     */
    private function parseWebsiteEnvOutput(string $output): array
    {
        $exists = false;
        $path = '';
        $size = 0;
        $modifiedAt = null;
        $content = '';

        $contentStart = "__ASTERO_CONTENT_START__\n";
        if (str_contains($output, $contentStart)) {
            [$metaSection, $content] = explode($contentStart, $output, 2);
        } else {
            $metaSection = $output;
        }

        foreach (preg_split("/\r\n|\n|\r/", trim($metaSection)) ?: [] as $line) {
            if (str_starts_with($line, '__ASTERO_EXISTS__=')) {
                $exists = trim(substr($line, strlen('__ASTERO_EXISTS__='))) === '1';
            } elseif (str_starts_with($line, '__ASTERO_PATH__=')) {
                $path = trim(substr($line, strlen('__ASTERO_PATH__=')));
            } elseif (str_starts_with($line, '__ASTERO_SIZE__=')) {
                $size = (int) trim(substr($line, strlen('__ASTERO_SIZE__=')));
            } elseif (str_starts_with($line, '__ASTERO_MTIME__=')) {
                $timestamp = trim(substr($line, strlen('__ASTERO_MTIME__=')));
                if ($timestamp !== '' && is_numeric($timestamp)) {
                    $modifiedAt = app_date_time_format(Date::createFromTimestamp((int) $timestamp), 'datetime');
                }
            }
        }

        $content = rtrim($content);

        return [
            'path' => $path,
            'exists' => $exists,
            'size_bytes' => $size,
            'modified_at' => $modifiedAt,
            'line_count' => $content === '' ? 0 : count(preg_split("/\r\n|\n|\r/", $content) ?: []),
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDnsInstructionsPayload(Website $website): ?array
    {
        $domainRecord = $website->domainRecord;
        if (! $domainRecord) {
            return null;
        }

        $instructions = $domainRecord->getMetadata('dns_instructions');
        if (! is_array($instructions)) {
            return null;
        }

        $domain = (string) ($domainRecord->name ?? $website->domain);
        $mode = (string) ($instructions['mode'] ?? '');

        if ($mode === 'managed') {
            $nameservers = collect($instructions['nameservers'] ?? [])
                ->filter(fn ($nameserver): bool => is_string($nameserver) && $nameserver !== '')
                ->values()
                ->all();

            return [
                'mode' => 'managed',
                'domain' => $domain,
                'nameservers' => $nameservers,
            ];
        }

        if ($mode !== 'external') {
            return null;
        }

        $records = collect($instructions['records'] ?? [])
            ->filter(fn ($record): bool => is_array($record))
            ->map(function (array $record) use ($domain): array {
                $name = (string) ($record['name'] ?? '');
                $type = (string) ($record['type'] ?? '');
                $value = (string) ($record['value'] ?? '');

                return [
                    'type' => $type,
                    'name' => $name,
                    'host_label' => $this->formatDnsInstructionHostLabel($domain, $name),
                    'fqdn' => $this->formatDnsInstructionFqdn($domain, $name),
                    'value' => $value,
                ];
            })
            ->filter(fn (array $record): bool => $record['type'] !== '' && $record['value'] !== '')
            ->values()
            ->all();

        return [
            'mode' => 'external',
            'domain' => $domain,
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $dnsInstructions
     * @return array<string, mixed>|null
     */
    private function buildDnsValidationPayload(Website $website, ?array $dnsInstructions): ?array
    {
        if ($dnsInstructions === null) {
            return null;
        }

        return [
            'confirmed_by_user' => (bool) $website->getMetadata('dns_confirmed_by_user'),
            'confirmed_at' => $this->formatProvisioningTimestamp($website->getMetadata('dns_confirmed_at')),
            'check_count' => (int) ($website->getMetadata('dns_check_count') ?? 0),
            'domain_not_registered' => (bool) $website->getMetadata('dns_domain_not_registered', false),
            'observed_nameservers' => collect($website->getMetadata('dns_check_result.observed_ns', []))
                ->filter(fn ($nameserver): bool => is_string($nameserver) && $nameserver !== '')
                ->values()
                ->all(),
            'confirm_url' => route('platform.websites.confirm-dns', ['website' => $website]),
            'stop_url' => route('platform.websites.stop-dns-validation', ['website' => $website]),
        ];
    }

    private function defaultDnsWaitingMessage(Website $website): string
    {
        $domainRecord = $website->domainRecord;
        $dnsInstructions = $domainRecord?->getMetadata('dns_instructions');
        $mode = is_array($dnsInstructions) ? (string) ($dnsInstructions['mode'] ?? '') : '';

        return $mode === 'managed'
            ? 'Waiting for customer to update nameservers.'
            : 'Waiting for customer DNS records to propagate.';
    }

    private function formatDnsInstructionHostLabel(string $domain, string $name): string
    {
        $normalizedName = trim($name);

        if ($normalizedName === '' || $normalizedName === '@' || $normalizedName === $domain) {
            return '@';
        }

        $suffix = '.'.$domain;

        if (str_ends_with($normalizedName, $suffix)) {
            return substr($normalizedName, 0, -strlen($suffix));
        }

        return $normalizedName;
    }

    private function formatDnsInstructionFqdn(string $domain, string $name): string
    {
        $normalizedName = trim($name);

        if ($normalizedName === '' || $normalizedName === '@' || $normalizedName === $domain) {
            return $domain;
        }

        if (str_contains($normalizedName, '.')) {
            return $normalizedName;
        }

        return $normalizedName.'.'.$domain;
    }

    // =============================================================================
    // CUSTOM DESTROY & RESTORE (delegates to WebsiteLifecycleService)
    // =============================================================================

    public function destroy(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->destroy($website);

        if ($result['status'] === 'success') {
            $message = $result['message'];
            $redirect = $result['redirect'] ?? route($this->scaffold()->getIndexRoute());

            return redirect($redirect)->with('success', $message);
        }

        return back()
            ->with('error', $result['message']);
    }

    public function restore(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->restore($website);

        if ($result['status'] === 'success') {
            return back()
                ->with('success', $result['message']);
        }

        return back()
            ->with('error', $result['message']);
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        // Force the deleted_at to trigger permanent delete logic
        if (empty($website->deleted_at)) {
            $website->delete();
        }

        $result = $this->websiteLifecycleService->destroy($website);

        if ($result['status'] === 'success') {
            return to_route($this->scaffold()->getIndexRoute(), ['status' => 'trash'])
                ->with('success', 'Website has been permanently deleted');
        }

        return back()
            ->with('error', $result['message']);
    }

    // =============================================================================
    // ADDITIONAL ACTIONS
    // =============================================================================

    public function updateStatus(Request $request, $id, string $status): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            $result = $this->websiteLifecycleService->updateStatus($website, $status);

            if ($result['status'] === 'success') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => $result['message'],
                    ]);
                }

                return to_route($this->scaffold()->getIndexRoute())
                    ->with('success', $result['message']);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['code'] ?? 500);
            }

            return back()
                ->with('error', $result['message']);
        } catch (Exception $exception) {
            Log::error('Error updating website status', [
                'website_id' => $id,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error updating website status: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error updating website status');
        }
    }

    public function updateVersion(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->updateVersion($website);

        if ($result['status'] === 'success') {
            $this->logActivity($website, ActivityAction::UPDATE, 'Website version update initiated');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                ]);
            }

            return back()
                ->with('success', $result['message']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        return back()
            ->with('error', $result['message']);
    }

    /**
     * Sync website information from the remote server.
     */
    public function syncWebsite(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        $result = $this->websiteService->syncWebsiteInfo($website);

        if ($result['success']) {
            $this->logActivity($website, ActivityAction::UPDATE, 'Website synced: '.$result['message']);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'] ?? [],
                ]);
            }

            return back()
                ->with('success', $result['message']);
        }

        // Check if it's just an info response (no changes)
        if (! empty($result['info'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'info',
                    'message' => $result['message'],
                ]);
            }

            return back()
                ->with('info', $result['message']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        return back()
            ->with('error', $result['message']);
    }

    /**
     * Run astero:recache on the remote website application.
     */
    public function recacheApplication(Request $request, $id): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            $exitCode = Artisan::call('platform:hestia:recache-application', [
                'website_id' => $website->id,
            ]);

            throw_if($exitCode !== 0, RuntimeException::class, 'Remote recache command returned a non-zero exit code.');

            $this->logActivity($website, ActivityAction::UPDATE, 'Application recache initiated');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Application recache has been executed successfully.',
                ]);
            }

            return back()
                ->with('success', 'Application recache has been executed successfully.');
        } catch (Exception $exception) {
            Log::error('Error running website application recache', [
                'website_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error running application recache: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error running application recache: '.$exception->getMessage());
        }
    }

    // =============================================================================
    // PROVISIONING STEPS
    // =============================================================================

    public function executeStep(Request $request, $id, string $step): JsonResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);
        $result = $this->websiteProvisioningService->executeStep($website, $step);

        return response()->json($result);
    }

    public function revertStep(Request $request, $id, string $step): JsonResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);
        $result = $this->websiteProvisioningService->revertStep($website, $step);

        return response()->json($result);
    }

    /**
     * Retry provisioning for a failed website.
     * Resets failed step statuses to pending and dispatches the provision job.
     */
    public function retryProvision(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        // Only allow retry for failed websites
        if ($website->status !== WebsiteStatus::Failed) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Website is not in a failed state.',
                ], 400);
            }

            return back()
                ->with('error', 'Website is not in a failed state.');
        }

        // Reset failed provisioning steps to pending so they can be retried
        $metadata = $website->metadata ?? [];
        if (isset($metadata['provisioning_steps'])) {
            // Check if we need to reset steps to ensure re-run
            foreach ($metadata['provisioning_steps'] as $key => $step) {
                if (isset($step['status']) && $step['status'] === 'failed') {
                    $metadata['provisioning_steps'][$key]['status'] = 'pending';
                    $metadata['provisioning_steps'][$key]['message'] = null;
                    $metadata['provisioning_steps'][$key]['started_at'] = null;
                    $metadata['provisioning_steps'][$key]['completed_at'] = null;
                }
            }

            $website->metadata = $metadata;
        }

        // Set status back to provisioning
        $website->status = WebsiteStatus::Provisioning;
        $website->save();
        $website->resetProvisioningRun();

        // Dispatch the provisioning job
        dispatch(new WebsiteProvision($website));

        $this->logActivity($website, ActivityAction::UPDATE, 'Provisioning retry initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Provisioning retry has been initiated. The website will be provisioned in the background.',
            ]);
        }

        return to_route('platform.websites.show', $website->id)
            ->with('success', 'Provisioning retry has been initiated. The website will be provisioned in the background.');
    }

    /**
     * Revert a website update.
     *
     * @todo This endpoint is not yet implemented. Implementation should:
     *       1. Fetch the WebsiteUpdate record using $update_id
     *       2. Restore backup files and database from before the update
     *       3. Update the website status and log the revert action
     *       See original StepController lines 116-148 for reference logic.
     */
    public function revertUpdate(Request $request, $id): JsonResponse
    {
        // Validate that the website exists
        Website::query()->findOrFail((int) $id);

        // TODO: Implement revert update logic
        // - Fetch WebsiteUpdate by $request->route('update_id')
        // - Restore files/database from backup
        // - Update website status
        // - Log the revert action

        return response()->json([
            'status' => 'error',
            'message' => 'Revert update functionality is not yet implemented. Please contact support.',
        ], 501);
    }

    public function updateSetupStatus(Request $request, $id): JsonResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);
            $updateData = ['updated_by' => auth()->id()];

            if ($request->has('backup_setup')) {
                $updateData['backup_setup'] = $request->backup_setup;
            }

            if ($request->has('setup_complete_flag')) {
                $updateData['setup_complete_flag'] = $request->setup_complete_flag;
            }

            $website->update($updateData);
            $this->logActivity($website, ActivityAction::UPDATE, 'Website setup status updated');

            return response()->json([
                'status' => 'success',
                'message' => 'Website setup status updated successfully',
            ]);
        } catch (Exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating website setup status',
            ]);
        }
    }

    /**
     * Setup Supervisor queue workers for a website.
     */
    public function setupQueueWorker(Request $request, $id): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            // Call the Artisan command to setup queue workers
            Artisan::call('platform:hestia:setup-queue-worker', [
                'website_id' => $website->id,
            ]);

            $this->logActivity($website, ActivityAction::UPDATE, 'Queue workers setup initiated');

            // Sync website info to update queue worker status in metadata
            $this->websiteService->syncWebsiteInfo($website);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Queue workers have been set up. Syncing website info...',
                ]);
            }

            return back()
                ->with('success', 'Queue workers have been set up successfully.');
        } catch (Exception $exception) {
            Log::error('Error setting up queue workers', [
                'website_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error setting up queue workers: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error setting up queue workers: '.$exception->getMessage());
        }
    }

    /**
     * Scale Supervisor queue workers for a website.
     */
    public function scaleQueueWorker(Request $request, $id, $count): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);
            $workerCount = max(1, min(10, (int) $count)); // Limit between 1-10

            // Call the Artisan command to scale queue workers
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'scale',
                '--workers' => $workerCount,
            ]);

            $this->logActivity($website, ActivityAction::UPDATE, 'Queue workers scaled to '.$workerCount);

            // Sync website info to update queue worker status in metadata
            $this->websiteService->syncWebsiteInfo($website);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => sprintf('Queue workers scaled to %d.', $workerCount),
                ]);
            }

            return back()
                ->with('success', sprintf('Queue workers scaled to %d successfully.', $workerCount));
        } catch (Exception $exception) {
            Log::error('Error scaling queue workers', [
                'website_id' => $id,
                'count' => $count,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error scaling queue workers: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error scaling queue workers: '.$exception->getMessage());
        }
    }

    /**
     * Remove website from Hestia server but keep database record.
     */
    public function removeFromServer(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        if (empty($website->deleted_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only trashed websites can be removed from the server',
                ], 400);
            }

            return back()
                ->with('error', 'Only trashed websites can be removed from the server');
        }

        if ($website->status === WebsiteStatus::Deleted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This website has already been removed from the server',
                ], 409);
            }

            return back()
                ->with('error', 'This website has already been removed from the server');
        }

        // Dispatch the job to remove from server
        dispatch(new WebsiteRemoveFromServer($website->id));

        $this->logActivity($website, ActivityAction::UPDATE, 'Website removal from server initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Website removal from server has been queued',
            ]);
        }

        return back()
            ->with('success', 'Website removal from server has been queued. The record will be kept for historical tracking.');
    }

    /**
     * Re-provision a deleted website (create fresh Hestia user/files).
     */
    public function reprovision(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        // Only allow re-provisioning of deleted websites
        if ($website->status !== WebsiteStatus::Deleted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only websites with "Deleted" status can be re-provisioned',
                ], 400);
            }

            return back()
                ->with('error', 'Only websites with "Deleted" status can be re-provisioned');
        }

        if (empty($website->deleted_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This website is not in trash and cannot be re-provisioned',
                ], 400);
            }

            return back()
                ->with('error', 'This website is not in trash and cannot be re-provisioned');
        }

        // Reset provisioning steps in metadata
        $website->setMetadata('provisioning_steps', null);
        $website->status = WebsiteStatus::Provisioning;
        $website->restore(); // Restore from soft delete
        $website->deleted_by = null;
        $website->updated_by = auth()->id();
        $website->save();
        $website->resetProvisioningRun();

        // Dispatch the provision job
        dispatch(new WebsiteProvision($website));

        $this->logActivity($website, ActivityAction::UPDATE, 'Website re-provisioning initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Website re-provisioning has been started',
            ]);
        }

        return to_route($this->scaffold()->getShowRoute(), $website->id)
            ->with('success', 'Website re-provisioning has been started. Check the Provision tab for progress.');
    }

    protected function service(): WebsiteService
    {
        return $this->websiteService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/websites';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Website $website */
        $website = $model;

        return [
            'initialValues' => $this->buildWebsiteInitialValues($website),
            'serverOptions' => Server::getServerOptions(),
            'agencyOptions' => Agency::getAgencyOptions(),
            'statusOptions' => $this->websiteService->getStatusOptionsForForm(),
            'typeOptions' => $this->websiteService->getTypeOptionsForForm(),
            'planOptions' => $this->websiteService->getPlanOptionsForForm(),
            'dnsModeOptions' => $this->websiteService->getDnsModeOptionsForForm(),
            'dnsProviderOptions' => Provider::getProviderOptions(Provider::TYPE_DNS),
            'cdnProviderOptions' => Provider::getProviderOptions(Provider::TYPE_CDN),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Website $website */
        $website = $model;

        return [
            'id' => $website->getKey(),
            'name' => $website->name,
            'uid' => $website->uid,
        ];
    }

    // =============================================================================
    // CUSTOMIZATIONS
    // =============================================================================

    protected function capturePreviousValues(Model $model): array
    {
        if (! $model instanceof Website) {
            return [];
        }

        return [
            'name' => $model->name,
            'domain' => $model->domain,
            'server_id' => $model->server_id,
            'agency_id' => $model->agency_id,
            'status' => $model->status,
            'type' => $model->type,
        ];
    }

    /**
     * Handle side effects after website creation.
     * Processes order updates if created via "Create from Order" flow.
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        if (! $model instanceof Website) {
            return;
        }

        // Handle order update if an order_id is present in the request
        if (request()->has('order_id')) {
            $this->processOrderUpdate($model, request());
        }
    }

    /**
     * Override success message for website creation
     */
    protected function buildCreateSuccessMessage(Model $model): string
    {
        return 'Website created successfully. Provisioning started.';
    }

    /**
     * Convert key => value array to array of ['value' => key, 'label' => value].
     */
    private function formatOptions(array $options): array
    {
        return collect($options)->map(fn ($label, $value): array => [
            'value' => $value,
            'label' => $label,
        ])->values()->all();
    }

    private function buildWebsiteInitialValues(Website $website): array
    {
        $website->loadMissing(['providers']);

        return [
            'name' => (string) ($website->name ?? ''),
            'domain' => (string) ($website->domain ?? ''),
            'type' => (string) ($website->type ?? 'paid'),
            'plan' => (string) ($website->plan_tier ?? 'basic'),
            'order_id' => '',
            'item_id' => '',
            'server_id' => $website->server_id ? (string) $website->server_id : '',
            'agency_id' => $website->agency_id ? (string) $website->agency_id : '',
            'dns_provider_id' => $website->dnsProvider?->getKey() ? (string) $website->dnsProvider?->getKey() : '',
            'cdn_provider_id' => $website->cdnProvider?->getKey() ? (string) $website->cdnProvider?->getKey() : '',
            'dns_mode' => (string) ($website->dns_mode ?? 'subdomain'),
            'website_username' => (string) ($website->uid ?? ''),
            'owner_password' => '',
            'customer_name' => (string) ($website->customer_data['name'] ?? ''),
            'customer_email' => (string) ($website->customer_data['email'] ?? ''),
            'status' => (string) (($website->status instanceof WebsiteStatus ? $website->status->value : $website->status) ?? WebsiteStatus::Provisioning->value),
            'expired_on' => $website->expired_on?->format('Y-m-d') ?? '',
            'is_www' => (bool) ($website->is_www ?? false),
            'is_agency' => (bool) ($website->is_agency ?? false),
            'skip_cdn' => (bool) ($website->skip_cdn ?? false),
            'skip_dns' => (bool) ($website->skip_dns ?? false),
            'skip_ssl_issue' => (bool) ($website->skip_ssl_issue ?? false),
            'skip_email' => (bool) ($website->skip_email ?? false),
        ];
    }

    private function transformWebsiteForShow(Website $website): array
    {
        $website->loadMissing(['server', 'agency', 'providers', 'domainRecord', 'sslCertificate.websites']);

        $status = $website->status instanceof WebsiteStatus ? $website->status : WebsiteStatus::tryFrom((string) $website->status);
        $diskUsageBytes = (int) $website->getMetadata('disk_usage_bytes', 0);
        $lastSyncedAt = $website->getMetadata('last_synced_at');
        $queueWorkerRunning = (int) $website->getMetadata('queue_worker_running_count', 0);
        $queueWorkerTotal = (int) $website->getMetadata('queue_worker_total_count', 0);
        $customerInfo = $website->customer_info;

        return [
            'id' => $website->getKey(),
            'uid' => $website->uid,
            'name' => $website->name,
            'domain' => $website->domain,
            'domain_url' => $website->domain ? 'https://'.ltrim($website->domain, '/') : null,
            'type' => $website->type,
            'plan' => $website->plan_tier,
            'status' => $status?->value ?? (string) $website->status,
            'status_label' => $status?->label() ?? ucfirst((string) $website->status),
            'dns_mode' => $website->dns_mode,
            'astero_version' => $website->astero_version,
            'admin_slug' => $website->admin_slug,
            'media_slug' => $website->media_slug,
            'is_www' => (bool) ($website->is_www ?? false),
            'is_agency' => (bool) ($website->is_agency ?? false),
            'skip_cdn' => (bool) ($website->skip_cdn ?? false),
            'skip_dns' => (bool) ($website->skip_dns ?? false),
            'skip_ssl_issue' => (bool) ($website->skip_ssl_issue ?? false),
            'skip_email' => (bool) ($website->skip_email ?? false),
            'created_at' => app_date_time_format($website->created_at, 'datetime'),
            'updated_at' => $website->updated_at ? $website->updated_at->diffForHumans() : null,
            'expired_on' => $website->expired_on?->format('M d, Y'),
            'is_trashed' => ! empty($website->deleted_at),
            'has_update' => $website->hasUpdateAvailable(),
            'server_version' => $website->server?->astero_version,
            'niches' => $website->getNichesLabels(),

            // Infrastructure
            'server_id' => $website->server_id,
            'server_name' => $website->server?->name ?? $website->server?->fqdn,
            'server_ip' => $website->server?->ip,
            'server_fqdn' => $website->server?->fqdn,
            'dns_provider_name' => $website->dnsProvider?->name,
            'cdn_provider_name' => $website->cdnProvider?->name,

            // Ownership
            'agency_id' => $website->agency_id,
            'agency_name' => $website->agency?->name,
            'customer_name' => $customerInfo['name'] ?? $customerInfo['email'] ?? $customerInfo['ref'] ?? null,
            'ssl_summary' => $this->buildSslSummary($website),

            // Runtime
            'disk_usage' => $diskUsageBytes > 0 ? Number::fileSize($diskUsageBytes, precision: 2) : null,
            'last_synced_at' => $lastSyncedAt ? Carbon::parse($lastSyncedAt)->diffForHumans() : null,
            'queue_worker_status' => $website->getMetadata('queue_worker_status'),
            'queue_worker_running' => $queueWorkerRunning,
            'queue_worker_total' => $queueWorkerTotal,
            'cron_status' => $website->getMetadata('cron_status'),
        ];
    }

    /**
     * @return array{
     *   certificate_name: string,
     *   certificate_href: string|null,
     *   expires_at: string|null,
     *   websites_count: int,
     *   websites: array<int, array{id: int, name: string, domain: string, href: string}>,
     *   domain_name: string|null,
     *   domain_href: string|null
     * }|null
     */
    private function buildSslSummary(Website $website): ?array
    {
        $certificate = $website->sslCertificate;

        if (! $certificate instanceof Secret) {
            return null;
        }

        $websites = $certificate->websites
            ->filter(fn (Website $linkedWebsite): bool => ! $linkedWebsite->trashed())
            ->sortBy('domain')
            ->values();

        return [
            'certificate_name' => (string) ($certificate->username ?? $certificate->key),
            'certificate_href' => $website->domain_id
                ? route('platform.domains.ssl-certificates.show', [$website->domain_id, $certificate->id])
                : null,
            'expires_at' => app_date_time_format($certificate->expires_at, 'date'),
            'websites_count' => $websites->count(),
            'websites' => $websites->map(fn (Website $linkedWebsite): array => [
                'id' => $linkedWebsite->id,
                'name' => (string) ($linkedWebsite->name ?? $linkedWebsite->domain ?? 'Website'),
                'domain' => (string) ($linkedWebsite->domain ?? '—'),
                'href' => route('platform.websites.show', $linkedWebsite->id),
            ])->all(),
            'domain_name' => $website->domainRecord?->name,
            'domain_href' => $website->domain_id ? route('platform.domains.show', $website->domain_id) : null,
        ];
    }

    private function formatProvisioningTimestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return app_date_time_format($value, 'datetime');
        }

        if (is_string($value) && trim($value) !== '') {
            return app_date_time_format($value, 'datetime');
        }

        return null;
    }

    /**
     * Update the associated order after a website is created.
     */
    private function processOrderUpdate(Website $website, Request $request): void
    {
        $ordersModelClass = 'Modules\\SaaS\\Models\\Orders';
        if (! class_exists($ordersModelClass)) {
            return;
        }

        $order = $ordersModelClass::find($request->order_id);
        if ($order && $request->has('item_id')) {
            $orderItem = $order->orderItems->where('id', $request->item_id)->first();
            if ($orderItem) {
                $orderItem->update([
                    'website_id' => $website->id,
                    'domain_id' => $website->domain_id,
                ]);

                $expiryDate = $orderItem->invoice_interval === 'year'
                    ? Date::now()->addYear()
                    : Date::now()->addMonth();

                $website->update(['expired_on' => $expiryDate]);
            }
        }
    }
}
