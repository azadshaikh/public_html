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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Http\Requests\ServerTestConnectionRequest;
use Modules\Platform\Http\Requests\ServerVerifyConnectionRequest;
use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Jobs\ServerUpdateReleases;
use Modules\Platform\Jobs\ServerUpdateScripts;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;
use Modules\Platform\Services\SSHKeyService;
use RuntimeException;

class ServerController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly ServerService $serverService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_servers', only: ['index', 'show', 'data', 'websites', 'optimizationTool']),
            new Middleware('permission:add_servers', only: ['create', 'createWizard', 'store', 'generateSSHKey', 'verifyConnection']),
            new Middleware('permission:edit_servers', only: ['edit', 'update', 'generateSSHKey', 'updateReleases', 'syncServer', 'updateScripts', 'testConnection', 'provision', 'executeProvisioningStep', 'retryProvisioning', 'reprovisionServer', 'stopProvisioning', 'revealSecret', 'revealSshKeyPair', 'revealAccessKeySecret']),
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
        $server->load(['providers']);

        // Get provisioning steps data
        $provisioningSteps = $this->getProvisioningStepsConfig();
        $provisioningStepsData = $server->getMetadata('provisioning_steps') ?? [];

        $creationMode = (string) ($server->getMetadata('creation_mode') ?? 'manual');
        $isProvisionModeServer = $creationMode === 'provision'
            || in_array((string) $server->provisioning_status, [
                Server::PROVISIONING_STATUS_PENDING,
                Server::PROVISIONING_STATUS_PROVISIONING,
                Server::PROVISIONING_STATUS_FAILED,
            ], true)
            || ! empty($provisioningStepsData);

        // Initialize provisioning steps only for provision-mode servers.
        if ($isProvisionModeServer && empty($provisioningStepsData) && $server->hasSshCredentials()) {
            $provisioningStepsData = $this->initializeProvisioningSteps($server, $provisioningSteps);
        }

        // Calculate progress
        $totalSteps = count($provisioningSteps);
        $completedSteps = collect($provisioningStepsData)->where('status', 'completed')->count();
        $skippedSteps = collect($provisioningStepsData)->where('status', 'skipped')->count();
        $progressPercent = $totalSteps > 0 ? round(($completedSteps + $skippedSteps) / $totalSteps * 100) : 0;

        // Check for JSON request (polling)
        if (request()->wantsJson() || request()->boolean('json')) {
            return response()->json([
                'status' => 'success',
                'provisioning_status' => $server->provisioning_status,
                'provisioning_steps' => $provisioningStepsData,
                'progress_percent' => $progressPercent,
            ]);
        }

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
            ])->values()->all(),
            'websiteCounts' => [
                'total' => $websiteTotal,
                'active' => $websiteActive,
                'inactive' => ($websiteCounts['inactive'] ?? 0) + ($websiteCounts['failed'] ?? 0),
                'provisioning' => $websiteCounts[WebsiteStatus::Provisioning->value] ?? 0,
            ],
            'provisioningSteps' => collect($provisioningSteps)
                ->map(fn (array $config, string $key): array => [
                    'key' => $key,
                    'title' => $config['title'],
                    'description' => $config['description'],
                    'status' => $provisioningStepsData[$key]['status'] ?? 'pending',
                    'message' => $provisioningStepsData[$key]['message'] ?? null,
                ])
                ->values()
                ->all(),
            'progressPercent' => $progressPercent,
            'canRevealSecrets' => $canRevealSecrets,
            'canRevealSshKeyPair' => $canRevealSshKeyPair,
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
     * Provision a server with HestiaCP and Astero scripts.
     */
    public function provision(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if (! $server->hasSshCredentials()) {
            $message = 'SSH credentials not configured. Please add SSH private key first.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

        if (! $server->canProvision()) {
            $message = 'Server cannot be provisioned. Current status: '.$server->provisioning_status;

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

        try {
            $this->resetProvisioningStopRequest($server);
            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                'status' => 'provisioning',
            ]);
            dispatch(new ServerProvision($server))->onQueue('default');

            $message = 'Server provisioning started. This may take 15-30 minutes.';
            $this->logActivity($server, ActivityAction::CREATE, 'Queued server provisioning.');

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return back()->with('success', $message);
        } catch (Exception $exception) {
            $message = 'Failed to start provisioning: '.$exception->getMessage();

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
            $exitCode = Artisan::call('platform:server:setup-acme', [
                'server_id' => $server->id,
            ]);

            if ($exitCode === 0) {
                $message = 'acme.sh setup completed successfully.';
                $this->logActivity($server, ActivityAction::UPDATE, $message);

                if ($request->expectsJson()) {
                    return response()->json(['status' => 'success', 'message' => $message]);
                }

                return back()->with('success', $message);
            }

            $message = 'acme.sh setup failed. Check server logs for details.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 500);
            }

            return back()->with('error', $message);
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

    /**
     * Execute a specific provisioning step.
     */
    public function executeProvisioningStep(Request $request, int|string $id, string $step): JsonResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        // Validate step exists
        $validSteps = array_keys($this->getProvisioningStepsConfig());
        if ($step !== 'all' && ! in_array($step, $validSteps, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid step specified.',
            ], 400);
        }

        // Check if server has SSH credentials
        if (! $server->hasSshCredentials()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server does not have SSH credentials configured.',
            ], 400);
        }

        // If executing all, dispatch the full provisioning job
        if ($step === 'all') {
            $this->resetProvisioningStopRequest($server);
            // Set to provisioning immediately so UI reflects the state
            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                'status' => 'provisioning',
            ]);
            dispatch(new ServerProvision($server));

            return response()->json([
                'status' => 'success',
                'message' => 'Provisioning started. This may take 15-30 minutes.',
            ]);
        }

        // Execute individual step
        try {
            $sshService = resolve(ServerSSHService::class);
            $result = $this->executeStep($server, $sshService, $step);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], $result['success'] ? 200 : 500);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Step execution failed: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Force-stop an active provisioning run.
     */
    public function stopProvisioning(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        if ($server->provisioning_status !== Server::PROVISIONING_STATUS_PROVISIONING) {
            $message = 'Server is not currently provisioning.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 400);
            }

            return back()->with('error', $message);
        }

        $steps = $server->getMetadata('provisioning_steps') ?? [];
        foreach ($steps as &$stepData) {
            if (($stepData['status'] ?? '') === 'running') {
                $stepData['status'] = 'failed';
                $stepData['completed_at'] = now()->toISOString();
                $stepData['data'] = ['error' => 'Provisioning stopped manually by user.'];
            }
        }

        unset($stepData);

        $server->setMetadata('provisioning_steps', $steps);
        $server->setMetadata('provisioning_control.stop_requested', true);
        $server->setMetadata('provisioning_control.stop_requested_at', now()->toISOString());
        $server->setMetadata('provisioning_control.stop_requested_by', auth()->id());
        $server->setMetadata('provisioning_retry', null);
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_FAILED,
            'status' => 'failed',
        ]);

        $remoteStopMessage = null;
        if ($server->hasSshCredentials()) {
            $sshService = resolve(ServerSSHService::class);
            $remoteStopResult = $sshService->executeCommand(
                $server,
                "screen -S hestia_install -X quit >/dev/null 2>&1 || true; pkill -f '/tmp/hst-install\\.sh' >/dev/null 2>&1 || true; pkill -f 'bash /tmp/hst-install.sh' >/dev/null 2>&1 || true; echo 'STOP_REQUESTED'",
                20
            );

            if (! ($remoteStopResult['success'] ?? false)) {
                $remoteStopMessage = ' Stop request saved, but remote installer termination could not be confirmed.';
            }
        }

        $message = 'Provisioning stop requested. You can retry provisioning when ready.'.($remoteStopMessage ?? '');
        $this->logActivity($server, ActivityAction::UPDATE, 'Provisioning stopped manually by user');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Retry provisioning for a failed server.
     */
    public function retryProvisioning(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        // Only allow retry for failed or pending servers
        if (! in_array($server->provisioning_status, [Server::PROVISIONING_STATUS_FAILED, Server::PROVISIONING_STATUS_PENDING], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Server provisioning cannot be retried in its current state.',
                ], 400);
            }

            return back()->with('error', 'Server provisioning cannot be retried in its current state.');
        }

        // Reset failed steps to pending
        $steps = $server->getMetadata('provisioning_steps') ?? [];
        foreach ($steps as &$stepData) {
            if (($stepData['status'] ?? '') === 'failed') {
                $stepData['status'] = 'pending';
                $stepData['data'] = null;
            }
        }

        $this->resetProvisioningStopRequest($server);
        $server->setMetadata('provisioning_steps', $steps);
        // Set to provisioning immediately so UI reflects the state
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
            'status' => 'provisioning',
        ]);
        $server->save();

        // Dispatch provisioning job
        dispatch(new ServerProvision($server));

        $this->logActivity($server, ActivityAction::UPDATE, 'Provisioning retry initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Provisioning retry has been initiated.',
            ]);
        }

        return back()->with('success', 'Provisioning retry has been initiated.');
    }

    /**
     * Reprovision a server - reset all steps and run full provisioning again.
     */
    public function reprovisionServer(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        // Check if server has SSH credentials
        if (! $server->hasSshCredentials()) {
            $message = 'SSH credentials not configured. Please add SSH private key first.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

        // Reset ALL provisioning steps to pending
        $stepsConfig = $this->getProvisioningStepsConfig();
        $steps = [];
        foreach ($stepsConfig as $key => $config) {
            $steps[$key] = [
                'label' => $config['title'],
                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
                'data' => null,
            ];
        }

        $this->resetProvisioningStopRequest($server);
        $server->setMetadata('provisioning_steps', $steps);
        $server->setMetadata('provisioning_started_at', now()->toISOString());

        // Set server to provisioning state
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
            'status' => 'provisioning',
        ]);
        $server->save();

        // Dispatch provisioning job
        dispatch(new ServerProvision($server));

        $this->logActivity($server, ActivityAction::UPDATE, 'Server reprovisioning initiated - all steps reset');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Server reprovisioning has been initiated. All steps will be executed.',
            ]);
        }

        return back()->with('success', 'Server reprovisioning has been initiated. All steps will be executed.');
    }

    /**
     * Get server optimization data (PostgreSQL settings + recommendations).
     */
    public function optimizationTool(int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if ($server->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Server must be active to retrieve optimization data.',
            ], 400);
        }

        try {
            // For localhost servers or when HestiaClient fails, read PG config directly
            $pgData = $this->fetchPgConfig($server);

            $ramMb = (int) $pgData['ram_mb'];
            $cpuCores = (int) $pgData['cpu_cores'];
            $currentSettings = $pgData['settings'];

            $recommendations = $this->calculatePgRecommendations($ramMb, $cpuCores);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'categories' => [
                        [
                            'id' => 'postgresql',
                            'label' => 'PostgreSQL',
                            'icon' => 'ri-database-2-line',
                            'pg_version' => $pgData['pg_version'],
                            'hardware' => [
                                'ram_mb' => $ramMb,
                                'cpu_cores' => $cpuCores,
                                'storage_type' => 'ssd',
                            ],
                            'settings' => $this->buildOptimizationSettings($currentSettings, $recommendations),
                        ],
                    ],
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve optimization data: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply PostgreSQL optimization settings.
     *
     * Uses ALTER SYSTEM SET to write settings to postgresql.auto.conf.
     * For remote servers, uses HestiaClient with a-apply-pg-config script.
     * For localhost/direct access, runs SQL commands directly.
     */
    public function applyOptimization(Request $request, int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if ($server->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Server must be active to apply optimizations.',
            ], 400);
        }

        $settings = $request->input('settings', []);

        if (empty($settings)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No settings provided.',
            ], 422);
        }

        // Whitelist of allowed PostgreSQL settings
        $allowedSettings = [
            'shared_buffers', 'effective_cache_size', 'work_mem', 'maintenance_work_mem',
            'wal_buffers', 'max_connections', 'random_page_cost', 'effective_io_concurrency',
            'max_worker_processes', 'max_parallel_workers_per_gather', 'max_parallel_workers',
            'max_parallel_maintenance_workers', 'huge_pages', 'min_wal_size', 'max_wal_size',
            'checkpoint_completion_target', 'default_statistics_target',
            'wal_compression', 'wal_log_hints', 'checkpoint_timeout', 'log_checkpoints',
            'log_temp_files', 'log_lock_waits', 'idle_in_transaction_session_timeout',
            'shared_preload_libraries',
        ];

        // Filter to only allowed settings
        $settings = array_intersect_key($settings, array_flip($allowedSettings));

        if ($settings === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid settings provided.',
            ], 422);
        }

        try {
            $result = $this->applyPgSettings($server, $settings);

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
                'data' => [
                    'applied' => $result['applied'],
                    'failed' => $result['failed'],
                    'restart_required' => $result['restart_required'],
                    'restarted' => $result['restarted'],
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to apply optimizations: '.$exception->getMessage(),
            ], 500);
        }
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

        return [
            'initialValues' => $this->buildServerInitialValues($server),
            'typeOptions' => $this->serverService->getTypeOptionsForForm(),
            'providerOptions' => $this->serverService->getProviderOptionsForForm(),
            'statusOptions' => $this->serverService->getStatusOptionsForForm(),
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
        ];
    }

    private function buildServerInitialValues(Server $server, ?string $sshPublicKey = null, ?string $sshPrivateKey = null): array
    {
        $provider = $server->relationLoaded('serverProviders')
            ? ($server->serverProviders->firstWhere('pivot.is_primary', true) ?? $server->serverProviders->first())
            : $server->provider;

        $installOptions = $server->getMetadata('install_options', []);
        $creationMode = (string) ($server->getMetadata('creation_mode') ?? ($server->exists ? 'manual' : 'provision'));

        return [
            'creation_mode' => in_array($creationMode, ['manual', 'provision'], true) ? $creationMode : 'manual',
            'name' => (string) ($server->name ?? ''),
            'ip' => (string) ($server->ip ?? ''),
            'fqdn' => (string) ($server->fqdn ?? ''),
            'type' => (string) ($server->type ?? ''),
            'provider_id' => $provider ? (string) $provider->getKey() : '',
            'monitor' => (bool) ($server->monitor ?? false),
            'status' => (string) ($server->status ?? 'active'),
            'port' => $server->port !== null ? (string) $server->port : '8443',
            'access_key_id' => (string) ($server->access_key_id ?? ''),
            'access_key_secret' => '',
            'release_api_key' => (string) ($server->release_api_key ?? ''),
            'max_domains' => $server->max_domains !== null ? (string) $server->max_domains : '',
            'ssh_port' => $server->ssh_port !== null ? (string) $server->ssh_port : '22',
            'ssh_user' => (string) ($server->ssh_user ?? 'root'),
            'ssh_public_key' => (string) ($server->ssh_public_key ?? $sshPublicKey ?? ''),
            'ssh_private_key' => (string) ($sshPrivateKey ?? ''),
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
            'provider_name' => $provider?->name,
            'port' => $server->port,
            'ssh_port' => $server->ssh_port,
            'ssh_user' => $server->ssh_user,
            'current_domains' => (int) ($server->current_domains ?? 0),
            'max_domains' => $server->max_domains,
            'creation_mode' => (string) ($server->getMetadata('creation_mode') ?? 'manual'),
            'astero_version' => $server->astero_version,
            'hestia_version' => $server->hestia_version,
            'server_os' => $server->server_os,
            'server_uptime' => $server->server_uptime,
            'created_at' => app_date_time_format($server->created_at, 'datetime'),
            'updated_at' => app_date_time_format($server->updated_at, 'datetime'),
        ];
    }

    /**
     * Initialize provisioning steps in server metadata.
     */
    protected function initializeProvisioningSteps(Server $server, array $stepsConfig): array
    {
        $steps = [];
        foreach ($stepsConfig as $key => $config) {
            $steps[$key] = [
                'label' => $config['title'],
                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
                'data' => null,
            ];
        }

        $server->setMetadata('provisioning_steps', $steps);
        $server->save();

        return $steps;
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

    protected function resetProvisioningStopRequest(Server $server): void
    {
        $server->setMetadata('provisioning_control.stop_requested', false);
        $server->setMetadata('provisioning_control.stop_requested_at', null);
        $server->setMetadata('provisioning_control.stop_requested_by', null);
        $server->save();
    }

    /**
     * Execute a single provisioning step.
     */
    protected function executeStep(Server $server, ServerSSHService $sshService, string $step): array
    {
        $steps = $server->getMetadata('provisioning_steps') ?? [];

        // Update step status to running
        if (isset($steps[$step])) {
            $steps[$step]['status'] = 'running';
            $steps[$step]['started_at'] = now()->toISOString();
            $steps[$step]['data'] = null; // Clear previous error data
            $server->setMetadata('provisioning_steps', $steps);
            $server->save();
        }

        try {
            // Quick steps can be executed inline
            $inlineSteps = ['ssh_connection', 'hestia_check'];

            if (in_array($step, $inlineSteps, true)) {
                $result = match ($step) {
                    'ssh_connection' => $sshService->testConnection($server),
                    'hestia_check' => $sshService->isHestiaInstalled($server),
                };
            } else {
                // Long-running steps: dispatch job with specific step
                // Reset this step to pending and set server to provisioning state
                $steps[$step]['status'] = 'pending';
                $steps[$step]['data'] = null;
                $this->resetProvisioningStopRequest($server);
                $server->setMetadata('provisioning_steps', $steps);
                $server->update([
                    'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                    'status' => 'provisioning',
                ]);

                // Dispatch job - it will pick up from the first pending step
                dispatch(new ServerProvision($server));

                return [
                    'success' => true,
                    'message' => sprintf("Step '%s' queued for execution. This may take several minutes.", $step),
                ];
            }

            // Update step status
            $steps = $server->getMetadata('provisioning_steps') ?? [];
            if (isset($steps[$step])) {
                $steps[$step]['status'] = $result['success'] ? 'completed' : 'failed';
                $steps[$step]['completed_at'] = now()->toISOString();
                // Store error message if failed, or data if success
                $steps[$step]['data'] = $result['success'] ? $result['data'] ?? null : ['error' => $result['message'] ?? 'Step failed'];

                $server->setMetadata('provisioning_steps', $steps);
                $server->save();
            }

            return $result;
        } catch (Exception $exception) {
            // Update step status to failed
            $steps = $server->getMetadata('provisioning_steps') ?? [];
            if (isset($steps[$step])) {
                $steps[$step]['status'] = 'failed';
                $steps[$step]['completed_at'] = now()->toISOString();
                $steps[$step]['data'] = ['error' => $exception->getMessage()];
                $server->setMetadata('provisioning_steps', $steps);
                $server->save();
            }

            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Apply PostgreSQL settings by editing postgresql.conf via HestiaClient.
     *
     * Uses HestiaClient to execute a-apply-pg-config which directly edits
     * postgresql.conf, then restarts or reloads PostgreSQL as needed.
     *
     * @param  array<string, string>  $settings  Settings to apply
     * @return array{status: string, message: string, applied: array, failed: array, restart_required: bool, restarted: bool}
     */
    private function applyPgSettings(Server $server, array $settings): array
    {
        $result = HestiaClient::execute(
            'a-apply-pg-config',
            $server,
            [json_encode($settings)],
            120
        );

        if (! $result['success'] || empty($result['data'])) {
            return [
                'status' => 'error',
                'message' => $result['message'] ?? 'Failed to apply settings via HestiaClient.',
                'applied' => [],
                'failed' => array_keys($settings),
                'restart_required' => false,
                'restarted' => false,
            ];
        }

        $data = $result['data'];

        // Filter out empty strings from applied/failed arrays (bash JSON edge case)
        $applied = array_values(array_filter($data['applied'] ?? [], fn ($v): bool => $v !== ''));
        $failed = array_values(array_filter($data['failed'] ?? [], fn ($v): bool => $v !== ''));

        return [
            'status' => $failed === [] ? 'success' : 'partial',
            'message' => $data['message'] ?? 'Settings applied.',
            'applied' => $applied,
            'failed' => $failed,
            'restart_required' => $data['restart_required'] ?? false,
            'restarted' => $data['restarted'] ?? false,
        ];
    }

    /**
     * Fetch PostgreSQL configuration from server.
     *
     * Uses HestiaClient to get hardware + PG config via a-get-pg-config script.
     * PG settings are also read from the app's DB connection as a reliable fallback.
     * Hardware info always comes from server metadata (synced by a-get-server-info).
     *
     * @return array{ram_mb: int, cpu_cores: int, pg_version: string, settings: array}
     */
    private function fetchPgConfig(Server $server): array
    {
        $hestiaData = [];

        // Try HestiaClient to get full config (hardware + PG settings)
        try {
            $result = HestiaClient::execute('a-get-pg-config', $server);
            if ($result['success'] && ! empty($result['data'])) {
                $hestiaData = $result['data'];
            }
        } catch (Exception) {
            // HestiaClient failed, proceed with fallback
        }

        // PG settings: prefer Hestia script data, fallback to direct DB query
        $settings = $hestiaData['settings'] ?? [];
        if (empty($settings)) {
            $settings = $this->fetchPgSettingsFromDb();
        }

        // PG version: prefer Hestia data, fallback to DB
        $pgVersion = $hestiaData['pg_version'] ?? '';
        if (empty($pgVersion)) {
            $db = DB::connection();
            $versionRow = $db->selectOne('SELECT version()');
            $fullVersion = $versionRow->version ?? '';
            if (preg_match('/PostgreSQL\s+([\d.]+)/', $fullVersion, $m)) {
                $pgVersion = $m[1];
            }
        }

        // Hardware info: prefer Hestia data, fallback to server metadata (synced by a-get-server-info)
        $ramMb = (int) ($hestiaData['ram_mb'] ?? $server->server_ram ?? 0);
        $cpuCores = (int) ($hestiaData['cpu_cores'] ?? $server->server_ccore ?? 1);

        return [
            'ram_mb' => $ramMb,
            'cpu_cores' => $cpuCores,
            'pg_version' => $pgVersion,
            'settings' => $settings,
        ];
    }

    /**
     * Fetch PostgreSQL settings directly from the app's database connection.
     *
     * @return array<string, string>
     */
    private function fetchPgSettingsFromDb(): array
    {
        $db = DB::connection();

        if ($db->getDriverName() !== 'pgsql') {
            return [];
        }

        $settingNames = [
            'shared_buffers', 'effective_cache_size', 'work_mem', 'maintenance_work_mem',
            'wal_buffers', 'max_connections', 'random_page_cost', 'effective_io_concurrency',
            'max_worker_processes', 'max_parallel_workers_per_gather', 'max_parallel_workers',
            'max_parallel_maintenance_workers', 'huge_pages', 'min_wal_size', 'max_wal_size',
            'checkpoint_completion_target', 'default_statistics_target', 'log_min_duration_statement',
            'wal_compression', 'wal_log_hints', 'checkpoint_timeout', 'log_checkpoints',
            'log_temp_files', 'log_lock_waits', 'idle_in_transaction_session_timeout',
            'shared_preload_libraries',
        ];

        $settings = [];
        foreach ($settingNames as $name) {
            try {
                $row = $db->selectOne('SHOW '.$name);
                $settings[$name] = $row->{$name} ?? 'N/A';
            } catch (Exception) {
                $settings[$name] = 'N/A';
            }
        }

        return $settings;
    }

    /**
     * Calculate recommended PostgreSQL settings based on hardware.
     *
     * @return array<string, array{value: string, description: string}>
     */
    private function calculatePgRecommendations(int $ramMb, int $cpuCores): array
    {
        $ramGb = $ramMb / 1024;

        // shared_buffers: 25% of RAM, max 8GB for most workloads
        $sharedBuffersMb = min((int) ($ramMb * 0.25), 8192);
        $sharedBuffers = $sharedBuffersMb >= 1024
            ? round($sharedBuffersMb / 1024, 1).'GB'
            : $sharedBuffersMb.'MB';

        // effective_cache_size: 75% of RAM (OS + PG cache)
        $effectiveCacheMb = (int) ($ramMb * 0.75);
        $effectiveCache = $effectiveCacheMb >= 1024
            ? round($effectiveCacheMb / 1024, 1).'GB'
            : $effectiveCacheMb.'MB';

        // max_connections: GREATEST(4 × CPU cores, 100) for balanced concurrency
        $maxConn = max(4 * $cpuCores, 100);
        $maxConnections = (string) $maxConn;

        // work_mem: PGTune formula — (RAM - shared_buffers) / (max_connections * 3)
        $workMemMb = max(4, (int) (($ramMb - $sharedBuffersMb) / ($maxConn * 3)));
        $workMem = $workMemMb.'MB';

        // maintenance_work_mem: RAM / 16, max 2GB
        $maintenanceWorkMemMb = min((int) ($ramMb / 16), 2048);
        $maintenanceWorkMem = $maintenanceWorkMemMb >= 1024
            ? round($maintenanceWorkMemMb / 1024, 1).'GB'
            : $maintenanceWorkMemMb.'MB';

        // wal_buffers: 3% of shared_buffers, max 64MB, min 1MB
        $walBuffersMb = max(1, min(64, (int) ($sharedBuffersMb * 0.03)));
        $walBuffers = $walBuffersMb.'MB';

        // random_page_cost: 1.1 for SSD
        $randomPageCost = '1.1';

        // effective_io_concurrency: 200 for SSD
        $effectiveIoConcurrency = '200';

        // Worker processes — match CPU core count (PGTune behavior)
        $maxWorkerProcesses = (string) $cpuCores;
        $maxParallelWorkers = (string) $cpuCores;
        $maxParallelWorkersPerGather = (string) max(2, (int) ($cpuCores / 2));
        $maxParallelMaintenanceWorkers = (string) max(2, (int) ($cpuCores / 4));

        // huge_pages: 'off' for < 32GB RAM, 'on' for >= 32GB (PGTune behavior)
        $hugePages = $ramGb >= 32 ? 'on' : 'off';

        // WAL settings
        $minWalSize = $ramGb >= 4 ? '1GB' : '512MB';
        $maxWalSize = $ramGb >= 8 ? '4GB' : ($ramGb >= 4 ? '2GB' : '1GB');

        // checkpoint_timeout: 15min reduces I/O load vs default 5min
        $checkpointTimeout = '15min';

        // idle_in_transaction_session_timeout: terminate idle transactions after 10min
        $idleTimeout = '10min';

        return [
            'max_connections' => [
                'value' => $maxConnections,
                'description' => 'GREATEST(4 × CPU cores, 100). Use a pooler like pgBouncer for extra connections.',
            ],
            'shared_buffers' => [
                'value' => $sharedBuffers,
                'description' => '25% of total RAM. Primary PostgreSQL memory cache for frequently accessed data.',
            ],
            'effective_cache_size' => [
                'value' => $effectiveCache,
                'description' => '75% of total RAM. Helps the query planner estimate available caching (OS + PG).',
            ],
            'maintenance_work_mem' => [
                'value' => $maintenanceWorkMem,
                'description' => 'Memory for maintenance operations like VACUUM and CREATE INDEX.',
            ],
            'checkpoint_completion_target' => [
                'value' => '0.9',
                'description' => 'Spreads checkpoint I/O over 90% of the interval. Reduces I/O spikes.',
            ],
            'wal_buffers' => [
                'value' => $walBuffers,
                'description' => '~3% of shared_buffers. Buffers for write-ahead log entries.',
            ],
            'default_statistics_target' => [
                'value' => '100',
                'description' => 'Statistics sampling for query planner. Default 100 is good for most workloads.',
            ],
            'random_page_cost' => [
                'value' => $randomPageCost,
                'description' => 'Set low (1.1) for SSD storage — random I/O is nearly as fast as sequential.',
            ],
            'effective_io_concurrency' => [
                'value' => $effectiveIoConcurrency,
                'description' => 'High (200) for SSD — supports many concurrent I/O operations.',
            ],
            'work_mem' => [
                'value' => $workMem,
                'description' => '(RAM - shared_buffers) / (connections × 3). Memory per sort/hash operation.',
            ],
            'huge_pages' => [
                'value' => $hugePages,
                'description' => $ramGb >= 32 ? 'Enabled for large RAM systems — reduces page table overhead.' : 'Disabled — not beneficial below 32 GB RAM.',
            ],
            'min_wal_size' => [
                'value' => $minWalSize,
                'description' => 'Minimum WAL disk space retained. Higher values reduce checkpoint frequency.',
            ],
            'max_wal_size' => [
                'value' => $maxWalSize,
                'description' => 'Maximum WAL disk space before automatic checkpoint trigger.',
            ],
            'max_worker_processes' => [
                'value' => $maxWorkerProcesses,
                'description' => 'Maximum background worker processes. Matches CPU core count.',
            ],
            'max_parallel_workers_per_gather' => [
                'value' => $maxParallelWorkersPerGather,
                'description' => 'Parallel workers per query. Half of CPU cores for balanced parallelism.',
            ],
            'max_parallel_workers' => [
                'value' => $maxParallelWorkers,
                'description' => 'Total parallel workers available. Matches CPU core count.',
            ],
            'max_parallel_maintenance_workers' => [
                'value' => $maxParallelMaintenanceWorkers,
                'description' => 'Parallel workers for maintenance (VACUUM, index builds). Quarter of CPU cores.',
            ],
            'wal_compression' => [
                'value' => 'pglz',
                'description' => 'Compresses full-page writes in WAL using pglz — reduces I/O during heavy write operations.',
            ],
            'wal_log_hints' => [
                'value' => 'on',
                'description' => 'Required for pg_rewind and recovery tools. Logs hint bit changes in WAL.',
            ],
            'checkpoint_timeout' => [
                'value' => $checkpointTimeout,
                'description' => '15min reduces checkpoint I/O vs default 5min. Slight increase in crash recovery time.',
            ],
            'log_checkpoints' => [
                'value' => 'on',
                'description' => 'Logs checkpoint activity — essential for monitoring and verifying checkpoint behavior.',
            ],
            'log_temp_files' => [
                'value' => '0',
                'description' => 'Logs all temp file usage — indicates when work_mem may need adjustment.',
            ],
            'log_lock_waits' => [
                'value' => 'on',
                'description' => 'Logs queries waiting on locks — helps identify lock contention issues.',
            ],
            'idle_in_transaction_session_timeout' => [
                'value' => $idleTimeout,
                'description' => 'Terminates idle transactions after 10 minutes to prevent lock blocking.',
            ],
            'shared_preload_libraries' => [
                'value' => 'pg_stat_statements',
                'description' => 'Enables pg_stat_statements for detailed query-level performance monitoring.',
            ],
        ];
    }

    /**
     * Build optimization settings array comparing current vs recommended.
     *
     * @return array<int, array{name: string, current: string, recommended: string, description: string, status: string}>
     */
    private function buildOptimizationSettings(array $currentSettings, array $recommendations): array
    {
        $settings = [];

        foreach ($recommendations as $name => $rec) {
            $current = $currentSettings[$name] ?? 'N/A';
            $recommended = $rec['value'];

            // Normalize values for comparison
            $currentBytes = $this->parsePostgresSize($current);
            $recommendedBytes = $this->parsePostgresSize($recommended);

            if ($current === 'N/A') {
                $status = 'unknown';
            } elseif ($name === 'shared_preload_libraries') {
                // Check if recommended library is already in the comma-separated list
                $currentLibs = array_map(trim(...), explode(',', (string) $current));
                $status = in_array($recommended, $currentLibs, true) ? 'ok' : 'needs_tuning';
            } elseif ($this->pgSizeValuesMatch($currentBytes, $recommendedBytes)) {
                $status = 'ok';
            } else {
                $status = 'needs_tuning';
            }

            $settings[] = [
                'name' => $name,
                'current' => $current,
                'recommended' => $recommended,
                'description' => $rec['description'],
                'status' => $status,
            ];
        }

        return $settings;
    }

    /**
     * Compare two parsed PG size values with tolerance.
     *
     * Handles unit conversion rounding (e.g. 2150MB ≈ 2.1GB)
     * by allowing up to 1% difference for size values.
     */
    private function pgSizeValuesMatch(int $a, int $b): bool
    {
        if ($a === $b) {
            return true;
        }

        // For non-size values (crc32 hashes), exact match only
        if ($a > 1_000_000_000_000 || $b > 1_000_000_000_000) {
            return $a === $b;
        }

        // Allow 1% tolerance for size rounding (e.g. 2.1GB vs 2150MB)
        $max = max($a, $b);
        if ($max === 0) {
            return true;
        }

        return abs($a - $b) / $max < 0.01;
    }

    /**
     * Parse PostgreSQL size notation to bytes for comparison.
     */
    private function parsePostgresSize(string $value): int
    {
        $value = trim($value);

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (preg_match('/^([\d.]+)\s*(kB|MB|GB|TB)$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return (int) match ($unit) {
                'KB' => $number * 1024,
                'MB' => $number * 1024 * 1024,
                'GB' => $number * 1024 * 1024 * 1024,
                'TB' => $number * 1024 * 1024 * 1024 * 1024,
                default => $number,
            };
        }

        // For non-size values (like 'try', 'on', '0.9'), return a hash for comparison
        return crc32($value);
    }
}
