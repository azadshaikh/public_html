<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Http\Controllers\Concerns\InteractsWithServerOptimization;
use Modules\Platform\Http\Controllers\Concerns\InteractsWithServerProvisioning;
use Modules\Platform\Http\Requests\ServerTestConnectionRequest;
use Modules\Platform\Http\Requests\ServerVerifyConnectionRequest;
use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Jobs\ServerUpdateReleases;
use Modules\Platform\Jobs\ServerUpdateScripts;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerAcmeSetupService;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;
use Modules\Platform\Services\SSHKeyService;
use RuntimeException;

class ServerController extends ScaffoldController implements HasMiddleware
{
    use InteractsWithServerOptimization;
    use InteractsWithServerProvisioning;

    private const string ASTERO_SCRIPTS_LOG_PATH = '/usr/local/hestia/data/astero/logs/astero-scripts.log';

    private const int ASTERO_SCRIPTS_LOG_TAIL_LINES = 400;

    public function __construct(
        private readonly ServerService $serverService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_servers', only: ['index', 'show', 'data', 'websites', 'optimizationTool']),
            new Middleware('permission:view_servers', only: ['scriptLog']),
            new Middleware('permission:add_servers', only: ['create', 'createWizard', 'store', 'generateSSHKey', 'verifyConnection']),
            new Middleware('permission:edit_servers', only: ['edit', 'update', 'generateSSHKey', 'updateReleases', 'syncServer', 'updateScripts', 'testConnection', 'provision', 'executeProvisioningStep', 'retryProvisioning', 'reprovisionServer', 'stopProvisioning', 'revealSecret', 'revealSshKeyPair', 'revealAccessKeySecret', 'clearScriptLog']),
            new Middleware('permission:delete_servers', only: ['destroy', 'bulkAction', 'forceDelete']),
            new Middleware('permission:restore_servers', only: ['restore']),
        ];
    }

    public function revealSshKeyPair(Request $request, int|string $server): JsonResponse
    {
        $user = $request->user();
        $isSuperUser = $user?->isSuperUser() || $user?->hasRole('super_user');

        abort_unless($isSuperUser, 403, 'Only super users can reveal SSH key pairs.');

        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);
        $sshKeyService = resolve(SSHKeyService::class);

        $sshPrivateKey = $serverModel->getSshPrivateKeyForConnection();

        if (empty($sshPrivateKey) && empty($serverModel->ssh_public_key)) {
            return response()->json([
                'success' => false,
                'message' => 'SSH key pair is not configured for this server.',
            ], 422);
        }

        $this->logActivity($serverModel, ActivityAction::VIEW, 'Revealed server SSH key pair.');

        return response()
            ->json([
                'success' => true,
                'public_key' => $serverModel->ssh_public_key,
                'private_key' => $sshPrivateKey,
                'authorize_command' => empty($serverModel->ssh_public_key)
                    ? null
                    : $sshKeyService->generateAuthorizedKeysCommand((string) $serverModel->ssh_public_key, SSHKeyService::DEFAULT_KEY_COMMENT),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function revealAccessKeySecret(Request $request, int|string $server): JsonResponse
    {
        $user = $request->user();
        $isSuperUser = $user?->isSuperUser() || $user?->hasRole('super_user');

        abort_unless($isSuperUser, 403, 'Only super users can reveal access key secrets.');

        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);

        if (empty($serverModel->access_key_secret)) {
            return response()->json([
                'success' => false,
                'message' => 'No secret key is configured for this server.',
            ], 422);
        }

        $this->logActivity($serverModel, ActivityAction::VIEW, 'Revealed server access key secret.');

        return response()
            ->json([
                'success' => true,
                'value' => $serverModel->access_key_secret,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function revealSecret(Request $request, int|string $server, int|string $secret): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);

        /** @var Secret $secretModel */
        $secretModel = $serverModel->secrets()->whereKey((int) $secret)->firstOrFail();

        abort_if((string) $secretModel->key === 'ssh_private_key', 403, 'Use the SSH key pair endpoint to reveal private key.');

        $this->logActivity($serverModel, ActivityAction::VIEW, sprintf("Revealed server secret '%s'.", $secretModel->key));

        return response()
            ->json([
                'success' => true,
                'value' => $secretModel->decrypted_value,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        /** @var Server $server */
        $server = $this->serverService->create($validated);

        $this->handleCreationSideEffects($server);
        $this->logActivity($server, ActivityAction::CREATE, sprintf("Server '%s' created successfully.", $server->name));

        // Dispatch provisioning job if in provision mode
        if ($server->provisioning_status === Server::PROVISIONING_STATUS_PENDING) {
            $this->resetProvisioningStopRequest($server);
            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                'status' => 'provisioning',
            ]);
            $this->markProvisioningRunStarted($server);
            dispatch(new ServerProvision($server));
        }

        $message = sprintf("Server '%s' created successfully.", $server->name);

        // Determine redirect URL - go to provisioning tab if in provision mode
        $isProvisionMode = in_array($server->provisioning_status, [
            Server::PROVISIONING_STATUS_PENDING,
            Server::PROVISIONING_STATUS_PROVISIONING,
        ], true);
        $redirectUrl = route('platform.servers.show', $server).($isProvisionMode ? '?section=provisioning' : '');

        return redirect($redirectUrl)
            ->with('success', $message);
    }

    public function show(int|string $id): Response
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);
        $server->load(['providers', 'agencies']);
        $provisioningPayload = $this->buildProvisioningStatusPayload($server);

        $websiteCounts = Website::query()
            ->where('server_id', $server->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $websiteTotal = array_sum($websiteCounts);
        $websiteActive = $websiteCounts['active'] ?? 0;

        $activities = ActivityLog::query()
            ->forModel(Server::class, $server->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $currentUser = auth()->user();
        $canRevealSecrets = (bool) $currentUser?->can('edit_servers');
        $canRevealSshKeyPair = $canRevealSecrets && ($currentUser->isSuperUser() || $currentUser->hasRole('super_user'));
        $secrets = $canRevealSecrets
            ? $server->secrets()->where('key', '!=', 'ssh_private_key')->orderBy('key')->get()
            : collect();

        return Inertia::render($this->inertiaPage().'/show', [
            'server' => $this->transformServerForShow($server),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
            'secrets' => $secrets->map(fn ($secret): array => [
                'id' => $secret->getKey(),
                'key' => (string) $secret->key,
                'label' => str($secret->key)->replace('_', ' ')->headline()->toString(),
                'username' => $secret->username,
            ])->values()->all(),
            'agencies' => $server->agencies->map(fn ($agency): array => [
                'id' => $agency->getKey(),
                'name' => (string) $agency->name,
                'status' => $agency->status,
                'is_primary' => (bool) ($agency->pivot?->is_primary ?? false),
            ])->values()->all(),
            'websiteCounts' => [
                'total' => $websiteTotal,
                'active' => $websiteActive,
                'inactive' => ($websiteCounts['inactive'] ?? 0) + ($websiteCounts['failed'] ?? 0),
                'provisioning' => $websiteCounts[WebsiteStatus::Provisioning->value] ?? 0,
            ],
            'provisioningSteps' => $provisioningPayload['provisioning_steps'],
            'provisioningRun' => $provisioningPayload['provisioning_run'],
            'metadataItems' => $this->buildServerMetadataItems($server),
            'canRevealSecrets' => $canRevealSecrets,
            'canRevealSshKeyPair' => $canRevealSshKeyPair,
            'canManageScriptLog' => (bool) $currentUser?->can('edit_servers'),
        ]);
    }

    public function websites(Request $request, int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        $perPage = min(max($request->integer('per_page', 10), 5), 50);

        $websites = Website::query()
            ->where('server_id', $server->id)->latest()
            ->paginate($perPage);

        /** @var Collection<int, Website> $websiteCollection */
        $websiteCollection = $websites->getCollection();
        $items = $websiteCollection->map(function (Website $website): array {
            // Status is cast to WebsiteStatus enum, so we use its methods
            $statusValue = $website->status instanceof WebsiteStatus
                ? $website->status->value
                : (string) $website->status;
            $statusLabel = $website->status instanceof WebsiteStatus
                ? $website->status->label()
                : ucfirst($statusValue);
            $statusColor = $website->status instanceof WebsiteStatus
                ? $website->status->color()
                : 'secondary';

            return [
                'id' => $website->id,
                'uid' => $website->uid,
                'name' => $website->name,
                'domain' => $website->domain,
                'status' => [
                    'value' => $statusValue,
                    'label' => $statusLabel,
                    'color' => $statusColor,
                ],
                'created_at' => $website->created_at?->format('M d, Y'),
                'urls' => [
                    'show' => route('platform.websites.show', $website->id),
                    'domain' => $website->domain ? 'http://'.$website->domain : null,
                ],
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $websites->total(),
                    'current_page' => $websites->currentPage(),
                    'last_page' => $websites->lastPage(),
                    'per_page' => $websites->perPage(),
                    'from' => $websites->firstItem(),
                    'to' => $websites->lastItem(),
                    'has_pages' => $websites->hasPages(),
                    'on_first_page' => $websites->onFirstPage(),
                    'has_more_pages' => $websites->hasMorePages(),
                ],
            ],
        ]);
    }

    public function destroy(int|string $id): RedirectResponse
    {
        try {
            return parent::destroy($id);
        } catch (RuntimeException $runtimeException) {
            return $this->deletionBlockedResponse($runtimeException->getMessage());
        }
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        try {
            return parent::forceDelete($id);
        } catch (RuntimeException $runtimeException) {
            return $this->deletionBlockedResponse($runtimeException->getMessage());
        }
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        try {
            return parent::bulkAction($request);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    public function updateReleases(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        try {
            dispatch(new ServerUpdateReleases($server, auth()->id()))->onQueue('default');

            $message = 'Server release update started. We will sync the server once the update completes.';
            $this->logActivity($server, ActivityAction::UPDATE, 'Queued server release update and sync.');

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return back()->with('success', $message);
        } catch (Exception $exception) {
            $message = 'Failed to queue release update: '.$exception->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    public function syncServer(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        try {
            $result = $this->serverService->syncServerInfo($server);

            if ($result['success']) {
                $this->logActivity($server, ActivityAction::UPDATE, $result['message']);

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => $result['message'],
                        'data' => $result['data'] ?? null,
                    ]);
                }

                return back()->with('success', $result['message']);
            }

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $result['message']], 400);
            }

            return back()->with('error', $result['message']);
        } catch (Exception $exception) {
            $message = 'Failed to sync server: '.$exception->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    /**
     * Update Astero scripts on a server.
     */
    public function updateScripts(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if (! $server->hasSshCredentials()) {
            $message = 'SSH credentials not configured.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

        try {
            dispatch(new ServerUpdateScripts($server, auth()->id()))->onQueue('default');

            $message = 'Script update started. This may take a minute.';
            $this->logActivity($server, ActivityAction::UPDATE, 'Queued script update.');

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return back()->with('success', $message);
        } catch (Exception $exception) {
            $message = 'Failed to start script update: '.$exception->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    /**
     * Setup acme.sh on a server for SSL certificate automation.
     */
    public function setupAcme(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if ($server->acme_configured) {
            $message = 'ACME is already configured on this server.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'info', 'message' => $message]);
            }

            return back()->with('info', $message);
        }

        if (! $server->hasSshCredentials()) {
            $message = 'SSH credentials not configured. Please add SSH private key first.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

        try {
            $result = resolve(ServerAcmeSetupService::class)->setup($server);
            $message = $result['summary'];

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return back()->with('success', $message);
        } catch (Exception $exception) {
            $message = 'acme.sh setup failed: '.$exception->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    /**
     * Test SSH connection to a server.
     */
    public function testConnection(ServerTestConnectionRequest $request, int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        $hasDraftSshInput = $request->hasAny(['ip', 'ssh_port', 'ssh_user', 'ssh_private_key']);

        if ($hasDraftSshInput) {
            $ip = trim((string) $request->input('ip', ''));
            $sshPort = (int) ($request->input('ssh_port') ?: 22);
            $sshUser = trim((string) $request->input('ssh_user', 'root'));
            $sshPrivateKey = (string) $request->input('ssh_private_key', '');

            $server = new Server([
                'ip' => $ip,
                'ssh_port' => $sshPort,
                'ssh_user' => $sshUser,
                'ssh_private_key' => $sshPrivateKey,
            ]);
        }

        if (! $server->hasSshCredentials()) {
            return response()->json([
                'status' => 'error',
                'message' => 'SSH credentials not configured. Please add IP, SSH user, and SSH private key.',
            ], 400);
        }

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->testConnection($server);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => 'SSH connection successful!',
                'data' => $result['data'],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
        ], 400);
    }

    /**
     * Show the server creation wizard.
     */
    public function createWizard(Request $request): Response
    {
        $sshKeyService = resolve(SSHKeyService::class);

        // Pre-generate SSH keys for provision mode
        $keyPair = $sshKeyService->generateKeyPair();

        return Inertia::render($this->inertiaPage().'/create', [
            'initialValues' => $this->buildServerInitialValues(
                new Server,
                $keyPair['public_key'],
                $keyPair['private_key']
            ),
            'typeOptions' => $this->serverService->getTypeOptionsForForm(),
            'providerOptions' => $this->serverService->getProviderOptionsForForm(),
            'statusOptions' => $this->serverService->getStatusOptionsForForm(),
            'sshPublicKey' => $keyPair['public_key'],
            'sshPrivateKey' => $keyPair['private_key'],
            'sshCommand' => $sshKeyService->generateAuthorizedKeysCommand($keyPair['public_key'], SSHKeyService::DEFAULT_KEY_COMMENT),
        ]);
    }

    /**
     * Generate a new SSH key pair (AJAX endpoint).
     */
    public function generateSSHKey(Request $request): JsonResponse
    {
        $sshKeyService = resolve(SSHKeyService::class);

        try {
            $keyPair = $sshKeyService->generateKeyPair();

            return response()->json([
                'success' => true,
                'public_key' => $keyPair['public_key'],
                'private_key' => $keyPair['private_key'],
                'command' => $sshKeyService->generateAuthorizedKeysCommand($keyPair['public_key'], SSHKeyService::DEFAULT_KEY_COMMENT),
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate SSH key: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify SSH connection to a server (AJAX endpoint for provisioning wizard).
     */
    public function verifyConnection(ServerVerifyConnectionRequest $request): JsonResponse
    {
        $ip = $request->input('ip');
        $sshPort = (int) ($request->input('ssh_port') ?: 22);
        $sshPrivateKey = $request->input('ssh_private_key');

        if (empty($ip) || empty($sshPrivateKey)) {
            return response()->json([
                'success' => false,
                'message' => 'IP address and SSH private key are required.',
            ], 400);
        }

        // Create a temporary server object for SSH service
        $tempServer = new Server([
            'ip' => $ip,
            'ssh_port' => $sshPort,
            'ssh_user' => 'root',
            'ssh_private_key' => $sshPrivateKey,
        ]);

        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->testConnection($tempServer);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful!',
                'os_info' => $result['data']['os_info'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    protected function service(): ServerService
    {
        return $this->serverService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/servers';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Server $server */
        $server = $model;
        $sshKeyService = resolve(SSHKeyService::class);
        $sshPublicKey = (string) ($server->ssh_public_key ?? '');

        return [
            'initialValues' => $this->buildServerInitialValues($server),
            'typeOptions' => $this->serverService->getTypeOptionsForForm(),
            'providerOptions' => $this->serverService->getProviderOptionsForForm(),
            'statusOptions' => $this->serverService->getStatusOptionsForForm(),
            'sshCommand' => $sshPublicKey !== ''
                ? $sshKeyService->generateAuthorizedKeysCommand($sshPublicKey, SSHKeyService::DEFAULT_KEY_COMMENT)
                : null,
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Server $server */
        $server = $model;

        return [
            'id' => $server->getKey(),
            'name' => $server->name,
            'provisioning_status' => $server->provisioning_status,
            'has_ssh_credentials' => $server->hasSshCredentials(),
            'has_ssh_private_key' => $server->hasSshPrivateKey(),
        ];
    }

    /**
     * Get the provisioning steps configuration.
     */
    protected function getProvisioningStepsConfig(): array
    {
        return [
            'ssh_connection' => [
                'title' => 'SSH Connection',
                'description' => 'Test SSH connectivity to the server',
                'icon' => 'ri-terminal-box-line',
            ],
            'hestia_check' => [
                'title' => 'HestiaCP Check',
                'description' => 'Check if HestiaCP is installed',
                'icon' => 'ri-search-line',
            ],
            'hestia_install' => [
                'title' => 'HestiaCP Install',
                'description' => 'Install HestiaCP control panel',
                'icon' => 'ri-install-line',
            ],
            'server_reboot' => [
                'title' => 'Server Reboot',
                'description' => 'Reboot server after HestiaCP installation',
                'icon' => 'ri-restart-line',
            ],
            'scripts_upload' => [
                'title' => 'Upload Scripts',
                'description' => 'Upload Astero scripts to the server',
                'icon' => 'ri-upload-cloud-line',
            ],
            'server_setup' => [
                'title' => 'Server Setup',
                'description' => 'Configure HestiaCP, PHP, and Astero directories',
                'icon' => 'ri-settings-3-line',
            ],
            'acme_setup' => [
                'title' => 'ACME Setup',
                'description' => 'Install acme.sh and wildcard SSL helper scripts',
                'icon' => 'ri-shield-check-line',
            ],
            'release_api_key' => [
                'title' => 'Release API Key',
                'description' => 'Configure release API key on the target server',
                'icon' => 'ri-shield-keyhole-line',
            ],
            'access_key' => [
                'title' => 'Create Access Key',
                'description' => 'Create HestiaCP API access key',
                'icon' => 'ri-key-2-line',
            ],
            'verification' => [
                'title' => 'Verification',
                'description' => 'Verify the installation',
                'icon' => 'ri-checkbox-circle-line',
            ],
            'update_releases' => [
                'title' => 'Update Releases',
                'description' => 'Update Astero releases from remote',
                'icon' => 'ri-refresh-line',
            ],
            'server_sync' => [
                'title' => 'Sync Server',
                'description' => 'Sync server information and stats',
                'icon' => 'ri-macbook-line',
            ],
            'pg_optimize' => [
                'title' => 'Optimize PostgreSQL',
                'description' => 'Apply PostgreSQL settings based on server resources',
                'icon' => 'ri-database-2-line',
            ],
        ];
    }

    private function buildServerInitialValues(Server $server, ?string $sshPublicKey = null, ?string $sshPrivateKey = null): array
    {
        $provider = $server->relationLoaded('serverProviders')
            ? ($server->serverProviders->firstWhere('pivot.is_primary', true) ?? $server->serverProviders->first())
            : $server->provider;

        $installOptions = $server->getMetadata('install_options', []);
        $creationMode = (string) ($server->getMetadata('creation_mode') ?? ($server->exists ? 'manual' : 'provision'));
        $metadataValue = static fn (string $key): mixed => $server->getMetadata($key);

        return [
            'creation_mode' => in_array($creationMode, ['manual', 'provision'], true) ? $creationMode : 'manual',
            'name' => (string) ($server->name ?? ''),
            'ip' => (string) ($server->ip ?? ''),
            'fqdn' => (string) ($server->fqdn ?? ''),
            'type' => (string) ($server->type ?? ''),
            'provider_id' => $provider ? (string) $provider->getKey() : '',
            'monitor' => (bool) ($server->monitor ?? false),
            'status' => (string) ($server->status ?? 'active'),
            'location_country_code' => (string) ($server->location_country_code ?? $metadataValue('location_country_code') ?? ''),
            'location_country' => (string) ($server->location_country ?? $metadataValue('location_country') ?? ''),
            'location_city_code' => (string) ($server->location_city_code ?? $metadataValue('location_city_code') ?? ''),
            'location_city' => (string) ($server->location_city ?? $metadataValue('location_city') ?? ''),
            'port' => $server->port !== null ? (string) $server->port : '8443',
            'access_key_id' => (string) ($server->access_key_id ?? ''),
            'access_key_secret' => '',
            'release_api_key' => (string) ($server->release_api_key ?? ''),
            'max_domains' => $server->max_domains !== null ? (string) $server->max_domains : '',
            'ssh_port' => $server->ssh_port !== null ? (string) $server->ssh_port : '22',
            'ssh_user' => (string) ($server->ssh_user ?? 'root'),
            'ssh_public_key' => (string) ($server->ssh_public_key ?? $sshPublicKey ?? ''),
            'ssh_private_key' => (string) ($sshPrivateKey ?? ''),
            'server_cpu' => (string) ($server->server_cpu ?? $metadataValue('server_cpu') ?? ''),
            'server_ccore' => (string) ($server->server_ccore ?? $metadataValue('server_ccore') ?? ''),
            'server_ram' => (string) ($server->server_ram ?? $metadataValue('server_ram') ?? ''),
            'server_storage' => (string) ($server->server_storage ?? $metadataValue('server_storage') ?? ''),
            'server_os' => (string) ($server->server_os ?? $metadataValue('server_os') ?? ''),
            'astero_version' => (string) ($server->astero_version ?? $metadataValue('astero_version') ?? ''),
            'hestia_version' => (string) ($server->hestia_version ?? $metadataValue('hestia_version') ?? ''),
            'release_zip_url' => (string) ($server->getMetadata('release_zip_url') ?? ''),
            'install_port' => (string) ($installOptions['port'] ?? '8443'),
            'install_lang' => (string) ($installOptions['lang'] ?? 'en'),
            'install_apache' => (bool) ($installOptions['apache'] ?? false),
            'install_phpfpm' => (bool) ($installOptions['phpfpm'] ?? true),
            'install_multiphp' => (bool) ($installOptions['multiphp'] ?? false),
            'install_multiphp_versions' => (string) ($installOptions['multiphp_versions'] ?? '8.4'),
            'install_vsftpd' => (bool) ($installOptions['vsftpd'] ?? false),
            'install_proftpd' => (bool) ($installOptions['proftpd'] ?? false),
            'install_named' => (bool) ($installOptions['named'] ?? false),
            'install_mysql' => (bool) ($installOptions['mysql'] ?? false),
            'install_mysql8' => (bool) ($installOptions['mysql8'] ?? false),
            'install_postgresql' => (bool) ($installOptions['postgresql'] ?? true),
            'install_exim' => (bool) ($installOptions['exim'] ?? false),
            'install_dovecot' => (bool) ($installOptions['dovecot'] ?? false),
            'install_sieve' => (bool) ($installOptions['sieve'] ?? false),
            'install_clamav' => (bool) ($installOptions['clamav'] ?? false),
            'install_spamassassin' => (bool) ($installOptions['spamassassin'] ?? false),
            'install_iptables' => (bool) ($installOptions['iptables'] ?? true),
            'install_fail2ban' => (bool) ($installOptions['fail2ban'] ?? true),
            'install_quota' => (bool) ($installOptions['quota'] ?? false),
            'install_resourcelimit' => (bool) ($installOptions['resourcelimit'] ?? false),
            'install_webterminal' => (bool) ($installOptions['webterminal'] ?? true),
            'install_api' => (bool) ($installOptions['api'] ?? true),
            'install_force' => (bool) ($installOptions['force'] ?? false),
        ];
    }

    private function transformServerForShow(Server $server): array
    {
        $provider = $server->relationLoaded('serverProviders')
            ? ($server->serverProviders->firstWhere('pivot.is_primary', true) ?? $server->serverProviders->first())
            : $server->provider;

        $lastSyncedAt = $server->getMetadata('last_synced_at');

        return [
            'id' => $server->getKey(),
            'uid' => $server->uid,
            'name' => $server->name,
            'ip' => $server->ip,
            'fqdn' => $server->fqdn,
            'type' => $server->type,
            'type_label' => $server->type_label,
            'status' => $server->status,
            'status_label' => $server->status_label,
            'provisioning_status' => $server->provisioning_status,
            'provider_id' => $provider?->getKey(),
            'provider_name' => $provider?->name,
            'location_label' => $server->location_label,
            'port' => $server->port,
            'ssh_port' => $server->ssh_port,
            'ssh_user' => $server->ssh_user,
            'access_key_id' => $server->access_key_id,
            'has_access_key_secret' => ! empty($server->access_key_secret),
            'has_ssh_credentials' => $server->hasSshCredentials(),
            'current_domains' => (int) ($server->current_domains ?? 0),
            'max_domains' => $server->max_domains,
            'creation_mode' => (string) ($server->getMetadata('creation_mode') ?? 'manual'),
            'server_ccore' => $server->server_ccore,
            'server_ram' => $server->server_ram,
            'server_storage' => $server->server_storage,
            'server_ram_used' => $server->getMetadata('server_ram_used'),
            'server_storage_used' => $server->getMetadata('server_storage_used'),
            'astero_version' => $server->astero_version,
            'hestia_version' => $server->hestia_version,
            'server_os' => $server->server_os,
            'server_uptime' => $server->server_uptime,
            'last_synced_at' => $lastSyncedAt ? app_date_time_format($lastSyncedAt, 'datetime') : null,
            'acme_configured' => (bool) $server->acme_configured,
            'acme_email' => $server->acme_email,
            'is_trashed' => method_exists($server, 'trashed') ? (bool) $server->trashed() : false,
            'created_at' => app_date_time_format($server->created_at, 'datetime'),
            'updated_at' => app_date_time_format($server->updated_at, 'datetime'),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function buildServerMetadataItems(Server $server): array
    {
        return collect([
            ['key' => 'creation_mode', 'label' => 'Creation Mode', 'value' => (string) ($server->getMetadata('creation_mode') ?? 'manual')],
            ['key' => 'provisioning_status', 'label' => 'Provisioning Status', 'value' => (string) ($server->provisioning_status ?? 'unknown')],
            ['key' => 'last_synced_at', 'label' => 'Last Synced', 'value' => (string) ($server->getMetadata('last_synced_at') ? app_date_time_format($server->getMetadata('last_synced_at'), 'datetime') : 'Never')],
            ['key' => 'access_key_id', 'label' => 'Access Key ID', 'value' => (string) ($server->access_key_id ?? '—')],
            ['key' => 'ssh_user', 'label' => 'SSH User', 'value' => (string) ($server->ssh_user ?? '—')],
            ['key' => 'ssh_port', 'label' => 'SSH Port', 'value' => (string) ($server->ssh_port ?? '—')],
            ['key' => 'hestia_port', 'label' => 'Hestia Port', 'value' => (string) ($server->port ?? '—')],
            ['key' => 'acme_email', 'label' => 'ACME Email', 'value' => (string) ($server->acme_email ?? '—')],
        ])->values()->all();
    }

    protected function deletionBlockedResponse(string $message): RedirectResponse
    {
        return back()->with('error', $message);
    }

    protected function handleRestorationSideEffects(Model $model): void
    {
        if ($model instanceof Server) {
            $model->update(['status' => 'active']);
        }
    }
}
