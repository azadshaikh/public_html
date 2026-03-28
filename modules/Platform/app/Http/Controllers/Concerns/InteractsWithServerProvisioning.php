<?php

namespace Modules\Platform\Http\Controllers\Concerns;

use App\Enums\ActivityAction;
use DateTimeInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;

trait InteractsWithServerProvisioning
{
    public function scriptLog(int|string $server): JsonResponse
    {
        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);
        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand($serverModel, $this->buildScriptLogReadCommand(), 30);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to read Astero script log.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $this->parseScriptLogOutput((string) data_get($result, 'data.output', '')),
        ]);
    }

    public function clearScriptLog(int|string $server): JsonResponse
    {
        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);
        $sshService = resolve(ServerSSHService::class);
        $result = $sshService->executeCommand($serverModel, $this->buildScriptLogClearCommand(), 30);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to clear Astero script log.',
            ], 500);
        }

        $this->logActivity($serverModel, ActivityAction::UPDATE, 'Cleared Astero scripts log.');

        return response()->json([
            'success' => true,
            'message' => 'Astero scripts log cleared successfully.',
        ]);
    }

    public function provisioningStatus(int|string $server): JsonResponse
    {
        /** @var Server $serverModel */
        $serverModel = $this->findModel((int) $server);

        return response()->json($this->buildProvisioningStatusPayload($serverModel));
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
            $this->markProvisioningRunStarted($server);
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
     * Execute a specific provisioning step.
     */
    public function executeProvisioningStep(Request $request, int|string $id, string $step): JsonResponse
    {
        /** @var Server $server */
        $server = $this->findModel((int) $id);

        $validSteps = array_keys($this->getProvisioningStepsConfig());
        if ($step !== 'all' && ! in_array($step, $validSteps, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid step specified.',
            ], 400);
        }

        if (! $server->hasSshCredentials()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server does not have SSH credentials configured.',
            ], 400);
        }

        if ($step === 'all') {
            $this->resetProvisioningStopRequest($server);
            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                'status' => 'provisioning',
            ]);
            $this->markProvisioningRunStarted($server);
            dispatch(new ServerProvision($server));

            return response()->json([
                'status' => 'success',
                'message' => 'Provisioning started. This may take 15-30 minutes.',
            ]);
        }

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

        if (! in_array($server->provisioning_status, [Server::PROVISIONING_STATUS_FAILED, Server::PROVISIONING_STATUS_PENDING], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Server provisioning cannot be retried in its current state.',
                ], 400);
            }

            return back()->with('error', 'Server provisioning cannot be retried in its current state.');
        }

        $steps = $server->getMetadata('provisioning_steps') ?? [];
        foreach ($steps as &$stepData) {
            if (($stepData['status'] ?? '') === 'failed') {
                $stepData['status'] = 'pending';
                $stepData['data'] = null;
                $stepData['started_at'] = null;
                $stepData['completed_at'] = null;
            }
        }

        unset($stepData);

        $this->resetProvisioningStopRequest($server);
        $server->setMetadata('provisioning_steps', $steps);
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
            'status' => 'provisioning',
        ]);
        $server->save();
        $this->markProvisioningRunStarted($server);

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

        if (! $server->hasSshCredentials()) {
            $message = 'SSH credentials not configured. Please add SSH private key first.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 400);
            }

            return back()->with('error', $message);
        }

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
        $server->setMetadata('provisioning_completed_at', null);
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
            'status' => 'provisioning',
        ]);
        $server->save();

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
     * @return array{
     *     status: string,
     *     current_status: string|null,
     *     provisioning_steps: array<int, array<string, mixed>>,
     *     provisioning_run: array{started_at: string|null, completed_at: string|null},
     *     progress_percent: float|int
     * }
     */
    protected function buildProvisioningStatusPayload(Server $server): array
    {
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

        if ($isProvisionModeServer && empty($provisioningStepsData) && $server->hasSshCredentials()) {
            $provisioningStepsData = $this->initializeProvisioningSteps($server, $provisioningSteps);
        }

        $normalizedSteps = collect($provisioningSteps)
            ->map(function (array $config, string $key) use ($provisioningStepsData): array {
                $stepData = $provisioningStepsData[$key] ?? [];
                $rawStatus = (string) ($stepData['status'] ?? 'pending');
                $status = match ($rawStatus) {
                    'completed', 'skipped' => 'done',
                    'running' => 'provisioning',
                    default => $rawStatus,
                };
                $message = $stepData['message']
                    ?? data_get($stepData, 'data.message')
                    ?? data_get($stepData, 'data.error')
                    ?? data_get($stepData, 'data.output_tail')
                    ?? data_get($stepData, 'data.log_tail');

                return [
                    'key' => $key,
                    'title' => $config['title'],
                    'description' => $config['description'],
                    'status' => $status,
                    'message' => is_string($message) ? $message : null,
                    'started_at' => $this->formatProvisioningTimestamp(data_get($stepData, 'started_at')),
                    'completed_at' => $this->formatProvisioningTimestamp(data_get($stepData, 'completed_at')),
                ];
            })
            ->values()
            ->all();

        $totalSteps = count($provisioningSteps);
        $completedSteps = collect($normalizedSteps)->where('status', 'done')->count();
        $progressPercent = $totalSteps > 0 ? round($completedSteps / $totalSteps * 100) : 0;

        return [
            'status' => 'success',
            'current_status' => $server->provisioning_status,
            'provisioning_steps' => $normalizedSteps,
            'provisioning_run' => [
                'started_at' => $this->formatProvisioningTimestamp($server->getMetadata('provisioning_started_at')),
                'completed_at' => $this->formatProvisioningTimestamp($server->getMetadata('provisioning_completed_at')),
            ],
            'progress_percent' => $progressPercent,
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

    protected function resetProvisioningStopRequest(Server $server): void
    {
        $server->setMetadata('provisioning_control.stop_requested', false);
        $server->setMetadata('provisioning_control.stop_requested_at', null);
        $server->setMetadata('provisioning_control.stop_requested_by', null);
        $server->save();
    }

    protected function markProvisioningRunStarted(Server $server): void
    {
        $server->setMetadata('provisioning_started_at', now()->toISOString());
        $server->setMetadata('provisioning_completed_at', null);
        $server->save();
    }

    /**
     * Execute a single provisioning step.
     */
    protected function executeStep(Server $server, ServerSSHService $sshService, string $step): array
    {
        $steps = $server->getMetadata('provisioning_steps') ?? [];

        if (isset($steps[$step])) {
            $steps[$step]['status'] = 'running';
            $steps[$step]['started_at'] = now()->toISOString();
            $steps[$step]['data'] = null;
            $server->setMetadata('provisioning_steps', $steps);
            $server->save();
        }

        try {
            $inlineSteps = ['ssh_connection', 'hestia_check'];

            if (in_array($step, $inlineSteps, true)) {
                $result = match ($step) {
                    'ssh_connection' => $sshService->testConnection($server),
                    'hestia_check' => $sshService->isHestiaInstalled($server),
                };
            } else {
                $steps[$step]['status'] = 'pending';
                $steps[$step]['data'] = null;
                $steps[$step]['started_at'] = null;
                $steps[$step]['completed_at'] = null;
                $this->resetProvisioningStopRequest($server);
                $server->setMetadata('provisioning_steps', $steps);
                $server->update([
                    'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
                    'status' => 'provisioning',
                ]);
                $this->markProvisioningRunStarted($server);

                dispatch(new ServerProvision($server));

                return [
                    'success' => true,
                    'message' => sprintf("Step '%s' queued for execution. This may take several minutes.", $step),
                ];
            }

            $steps = $server->getMetadata('provisioning_steps') ?? [];
            if (isset($steps[$step])) {
                $steps[$step]['status'] = $result['success'] ? 'completed' : 'failed';
                $steps[$step]['completed_at'] = now()->toISOString();
                $steps[$step]['data'] = $result['success'] ? $result['data'] ?? null : ['error' => $result['message'] ?? 'Step failed'];

                $server->setMetadata('provisioning_steps', $steps);
                $server->save();
            }

            return $result;
        } catch (Exception $exception) {
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

    protected function buildScriptLogReadCommand(): string
    {
        $logPath = self::ASTERO_SCRIPTS_LOG_PATH;
        $tailLines = self::ASTERO_SCRIPTS_LOG_TAIL_LINES;
        $script = <<<BASH
LOG="{$logPath}"
if [ -f "\$LOG" ]; then
    SIZE=\$(wc -c < "\$LOG" | tr -d ' ')
    MTIME=\$(date -r "\$LOG" +%s 2>/dev/null || stat -c %Y "\$LOG" 2>/dev/null || printf '')
    printf '__ASTERO_EXISTS__=1\n__ASTERO_SIZE__=%s\n__ASTERO_MTIME__=%s\n__ASTERO_CONTENT_START__\n' "\$SIZE" "\$MTIME"
    tail -n {$tailLines} "\$LOG"
else
    printf '__ASTERO_EXISTS__=0\n__ASTERO_SIZE__=0\n__ASTERO_MTIME__=\n__ASTERO_CONTENT_START__\n'
fi
BASH;

        return 'bash -lc '.escapeshellarg($script);
    }

    protected function buildScriptLogClearCommand(): string
    {
        $logPath = self::ASTERO_SCRIPTS_LOG_PATH;
        $script = <<<BASH
LOG="{$logPath}"
mkdir -p "\$(dirname "\$LOG")"
: > "\$LOG"
BASH;

        return 'bash -lc '.escapeshellarg($script);
    }

    /**
     * @return array{
     *   path: string,
     *   exists: bool,
     *   size_bytes: int,
     *   modified_at: string|null,
     *   tail_lines: int,
     *   content: string
     * }
     */
    protected function parseScriptLogOutput(string $output): array
    {
        preg_match('/^__ASTERO_EXISTS__=(?<exists>[01])$/m', $output, $existsMatch);
        preg_match('/^__ASTERO_SIZE__=(?<size>\d+)$/m', $output, $sizeMatch);
        preg_match('/^__ASTERO_MTIME__=(?<mtime>\d+)?$/m', $output, $mtimeMatch);

        $contentMarker = "__ASTERO_CONTENT_START__\n";
        $content = str_contains($output, $contentMarker)
            ? explode($contentMarker, $output, 2)[1]
            : '';

        $modifiedAt = null;
        $mtime = $mtimeMatch['mtime'] ?? null;
        if (is_string($mtime) && $mtime !== '') {
            $modifiedAt = app_date_time_format(Carbon::createFromTimestamp((int) $mtime), 'datetime');
        }

        return [
            'path' => self::ASTERO_SCRIPTS_LOG_PATH,
            'exists' => ($existsMatch['exists'] ?? '0') === '1',
            'size_bytes' => (int) ($sizeMatch['size'] ?? 0),
            'modified_at' => $modifiedAt,
            'tail_lines' => self::ASTERO_SCRIPTS_LOG_TAIL_LINES,
            'content' => rtrim($content),
        ];
    }

    protected function formatProvisioningTimestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return app_date_time_format($value, 'datetime');
        }

        if (is_string($value) && trim($value) !== '') {
            return app_date_time_format($value, 'datetime');
        }

        return null;
    }
}
