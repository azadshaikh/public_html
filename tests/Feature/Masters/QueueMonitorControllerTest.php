<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Definitions\QueueMonitorDefinition;
use App\Enums\MonitorStatus;
use App\Enums\Status;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Models\Monitor;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkerMonitorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class QueueMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->administrator = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->administrator->assignRole(Role::findByName('administrator', 'web'));

        $this->mock(WorkerMonitorService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWorkerStats')
                ->andReturn([
                    'supervisor_running' => true,
                    'supervisor_status' => 'active (running)',
                    'program_name' => 'queue-worker',
                    'configured_workers' => 2,
                    'running_workers' => 2,
                    'processes' => [
                        [
                            'pid' => 1201,
                            'memory_mb' => 32.5,
                            'uptime' => '2h 4m',
                            'status' => 'running',
                        ],
                    ],
                    'command' => 'php artisan queue:work --sleep=3',
                    'log_file' => '/var/log/supervisor/queue-worker.log',
                ]);
        });
    }

    public function test_super_user_can_view_queue_monitor_dashboard(): void
    {
        $syncMonitor = $this->createMonitor([
            'name' => 'App\\Jobs\\SyncWebsites',
            'queue' => 'default',
            'status' => MonitorStatus::SUCCEEDED,
            'queued_at' => now()->subMinutes(6),
            'started_at' => now()->subMinutes(5),
            'started_at_exact' => now()->subMinutes(5)->toDateTimeString(),
            'finished_at' => now()->subMinutes(4),
            'finished_at_exact' => now()->subMinutes(4)->toDateTimeString(),
        ]);
        $failedMonitor = $this->createMonitor([
            'name' => 'App\\Jobs\\ProvisionServer',
            'queue' => 'provisioning',
            'status' => MonitorStatus::FAILED,
            'queued_at' => now()->subMinutes(8),
            'started_at' => now()->subMinutes(7),
            'started_at_exact' => now()->subMinutes(7)->toDateTimeString(),
            'finished_at' => now()->subMinutes(6),
            'finished_at_exact' => now()->subMinutes(6)->toDateTimeString(),
            'exception_message' => 'Provisioning failed.',
        ]);
        $runningMonitor = $this->createMonitor([
            'name' => 'App\\Jobs\\DispatchEmails',
            'queue' => 'emails',
            'status' => MonitorStatus::RUNNING,
            'queued_at' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
            'started_at_exact' => now()->subMinute()->toDateTimeString(),
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.masters.queue-monitor.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/queue-monitor/index')
                ->has('config.columns', 10)
                ->where('config.columns.1.width', 100)
                ->has('monitors')
                ->where('monitors.data', function (Collection $rows) use ($failedMonitor, $runningMonitor, $syncMonitor): bool {
                    $ids = $rows->pluck('id');

                    return $rows->count() >= 3
                        && $ids->contains($syncMonitor->id)
                        && $ids->contains($failedMonitor->id)
                        && $ids->contains($runningMonitor->id);
                })
                ->has('statistics')
                ->has('filters')
                ->has('metrics')
                ->has('queueStats')
                ->has('chartData')
                ->has('workerStats')
                ->has('queueOptions')
                ->where('filters.status', 'all')
                ->where('ui.refreshInterval', 10)
                ->where('ui.allowClearQueue', true)
                ->where('workerStats.supervisor_running', true)
            );
    }

    public function test_non_super_users_cannot_view_queue_monitor_dashboard(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('app.masters.queue-monitor.index'))
            ->assertForbidden();
    }

    public function test_queue_monitor_uses_common_super_user_middleware(): void
    {
        $middleware = collect((new QueueMonitorDefinition)->getMiddleware())
            ->map(fn ($middleware) => $middleware->middleware)
            ->all();

        $this->assertContains(EnsureSuperUserAccess::class, $middleware);
    }

    public function test_workers_endpoint_returns_worker_stats_json(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.queue-monitor.workers'))
            ->assertOk()
            ->assertJson([
                'supervisor_running' => true,
                'configured_workers' => 2,
                'running_workers' => 2,
                'program_name' => 'queue-worker',
            ]);
    }

    public function test_retry_route_redirects_back_and_marks_failed_job_retried(): void
    {
        $monitor = $this->createMonitor([
            'status' => MonitorStatus::FAILED,
            'exception_message' => 'Job failed',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => $monitor->job_uuid])
            ->andReturn(0);

        $this->actingAs($this->superUser)
            ->from(route('app.masters.queue-monitor.index'))
            ->patch(route('app.masters.queue-monitor.retry', $monitor->id))
            ->assertRedirect(route('app.masters.queue-monitor.index'))
            ->assertSessionHas('status', 'Job queued for retry.');

        $this->assertTrue($monitor->fresh()->retried);
    }

    public function test_running_cancellable_job_can_receive_stop_request(): void
    {
        $monitor = $this->createMonitor([
            'status' => MonitorStatus::RUNNING,
            'finished_at' => null,
            'finished_at_exact' => null,
            'metadata' => [
                '_label' => 'Website #42',
                'cancellable' => true,
            ],
        ]);

        $this->actingAs($this->superUser)
            ->from(route('app.masters.queue-monitor.index'))
            ->patch(route('app.masters.queue-monitor.cancel', $monitor->id))
            ->assertRedirect(route('app.masters.queue-monitor.index'))
            ->assertSessionHas('status', 'Stop requested. The job will stop after the current step.');

        $this->assertNotNull(data_get($monitor->fresh()->metadata, 'cancel_requested_at'));
    }

    public function test_running_job_can_be_marked_stale_manually(): void
    {
        $monitor = $this->createMonitor([
            'status' => MonitorStatus::RUNNING,
            'finished_at' => null,
            'finished_at_exact' => null,
        ]);

        $this->actingAs($this->superUser)
            ->from(route('app.masters.queue-monitor.index'))
            ->patch(route('app.masters.queue-monitor.mark-stale', $monitor->id))
            ->assertRedirect(route('app.masters.queue-monitor.index'))
            ->assertSessionHas('status', 'Running monitor marked as stale. This does not kill any live worker process.');

        $monitor->refresh();

        $this->assertSame(MonitorStatus::STALE, $monitor->status);
        $this->assertNotNull($monitor->finished_at);
        $this->assertNotNull(data_get($monitor->metadata, 'manually_marked_stale_at'));
        $this->assertSame($this->superUser->id, data_get($monitor->metadata, 'manually_marked_stale_by'));
    }

    public function test_clear_queue_route_calls_queue_clear_for_target_queue(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:clear', [
                'connection' => config('queue.default'),
                '--queue' => 'default',
                '--force' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->never();

        $this->actingAs($this->superUser)
            ->from(route('app.masters.queue-monitor.index'))
            ->post(route('app.masters.queue-monitor.clear-queue'), [
                'queue' => 'default',
            ])
            ->assertRedirect(route('app.masters.queue-monitor.index'))
            ->assertSessionHas('status', 'Queued jobs cleared for "default".');
    }

    public function test_queue_monitor_dashboard_includes_stop_action_for_running_cancellable_jobs(): void
    {
        $monitor = $this->createMonitor([
            'status' => MonitorStatus::RUNNING,
            'finished_at' => null,
            'finished_at_exact' => null,
            'metadata' => [
                '_label' => 'Website #42',
                'cancellable' => true,
            ],
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.masters.queue-monitor.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('monitors.data.0.id', $monitor->id)
                ->where('monitors.data.0.actions.cancel.label', 'Stop')
                ->where('monitors.data.0.actions.cancel.method', 'PATCH')
                ->where('monitors.data.0.actions.mark_stale.label', 'Mark stale')
                ->where('monitors.data.0.actions.mark_stale.method', 'PATCH'));
    }

    public function test_purge_bulk_action_removes_all_monitor_entries_without_selected_ids(): void
    {
        $initialCount = Monitor::query()->count();

        $this->createMonitor();
        $this->createMonitor(['queue' => 'emails']);

        $this->assertSame($initialCount + 2, Monitor::query()->count());

        $this->actingAs($this->superUser)
            ->from(route('app.masters.queue-monitor.index'))
            ->post(route('app.masters.queue-monitor.bulk-action'), [
                'action' => 'purge',
            ])
            ->assertRedirect(route('app.masters.queue-monitor.index'))
            ->assertSessionHas('status', sprintf('Purged %d monitor entries.', $initialCount + 2));

        $this->assertSame(0, Monitor::query()->count());
    }

    private function createMonitor(array $overrides = []): Monitor
    {
        return Monitor::query()->create(array_merge([
            'job_uuid' => (string) Str::uuid(),
            'job_id' => (string) Str::uuid(),
            'name' => 'App\\Jobs\\ExampleJob',
            'queue' => 'default',
            'status' => MonitorStatus::SUCCEEDED,
            'queued_at' => now()->subMinutes(4),
            'started_at' => now()->subMinutes(3),
            'started_at_exact' => now()->subMinutes(3)->toDateTimeString(),
            'finished_at' => now()->subMinutes(2),
            'finished_at_exact' => now()->subMinutes(2)->toDateTimeString(),
            'attempt' => 1,
            'retried' => false,
            'metadata' => ['_label' => 'Website #42'],
        ], $overrides));
    }
}
