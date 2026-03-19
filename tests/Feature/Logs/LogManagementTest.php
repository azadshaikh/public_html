<?php

declare(strict_types=1);

namespace Tests\Feature\Logs;

use App\Enums\Status;
use App\Models\LoginAttempt;
use App\Models\NotFoundLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Log',
            'last_name' => 'Super',
            'name' => 'Log Super',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));
        $this->superUser->givePermissionTo([
            'view_activity_logs',
            'delete_activity_logs',
            'manage_activity_logs',
            'view_login_attempts',
            'delete_login_attempts',
            'manage_login_attempts',
            'view_not_found_logs',
            'delete_not_found_logs',
            'manage_not_found_logs',
        ]);
    }

    public function test_activity_log_pages_and_actions_work_for_authorized_users(): void
    {
        $subject = User::factory()->create([
            'first_name' => 'Subject',
            'last_name' => 'User',
            'name' => 'Subject User',
            'status' => Status::ACTIVE,
        ]);

        $activityId = DB::table('activity_log')->insertGetId([
            'log_name' => 'default',
            'description' => 'User profile updated',
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'causer_type' => User::class,
            'causer_id' => $this->superUser->id,
            'event' => 'updated',
            'properties' => json_encode([
                'changes' => [
                    'name' => [
                        'old' => 'Before',
                        'new' => 'After',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fromDate = now()->subDays(7)->toDateString();
        $toDate = now()->toDateString();

        DB::table('activity_log')->insert([
            'log_name' => 'default',
            'description' => 'Old activity',
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'causer_type' => User::class,
            'causer_id' => $this->superUser->id,
            'event' => 'deleted',
            'properties' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now()->subDays(500),
            'updated_at' => now()->subDays(500),
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.logs.activity-logs.index', [
                'causer_id' => (string) $this->superUser->id,
                'created_at_from' => $fromDate,
                'created_at_to' => $toDate,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/activity-logs/index')
                ->has('logs.data')
                ->where('statistics.total', 2)
                ->has('filterOptions.event')
                ->where('config.filters.0.key', 'event')
                ->where('config.filters.0.options.update', 'Update')
                ->where('config.filters.1.key', 'causer_id')
                ->where('config.filters.1.options.'.$this->superUser->id, fn (string $label): bool => str_contains($label, $this->superUser->email))
                ->where('filters.causer_id', (string) $this->superUser->id)
                ->where('filters.created_at', $fromDate.','.$toDate)
            );

        $this->actingAs($this->superUser)
            ->get(route('app.logs.activity-logs.show', $activityId))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/activity-logs/show')
                ->where('activityLog.id', $activityId)
                ->has('changes_summary')
            );

        $this->actingAs($this->superUser)
            ->postJson(route('app.logs.activity-logs.export'), [
                'limit' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('count', 2);

        $this->actingAs($this->superUser)
            ->postJson(route('app.logs.activity-logs.cleanup'), [
                'days_to_keep' => 365,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('deleted_count', 1);
    }

    public function test_login_attempt_pages_and_actions_work_for_authorized_users(): void
    {
        $loginAttempt = LoginAttempt::recordFailure(
            email: 'person@example.com',
            ipAddress: '192.0.2.44',
            reason: LoginAttempt::REASON_INVALID_CREDENTIALS,
            userId: $this->superUser->id,
            userAgent: 'PHPUnit'
        );

        LoginAttempt::recordBlocked(
            email: 'person@example.com',
            ipAddress: '192.0.2.44',
            userAgent: 'PHPUnit'
        );

        LoginAttempt::recordFailure(
            email: 'old@example.com',
            ipAddress: '198.51.100.9',
            reason: LoginAttempt::REASON_INVALID_CREDENTIALS,
            userId: $this->superUser->id,
            userAgent: 'PHPUnit',
            metadata: []
        )->forceFill([
            'created_at' => now()->subDays(45),
        ])->save();

        $fromDate = now()->subDays(7)->toDateString();
        $toDate = now()->toDateString();

        $this->actingAs($this->superUser)
            ->get(route('app.logs.login-attempts.index', [
                'created_at_from' => $fromDate,
                'created_at_to' => $toDate,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/login-attempts/index')
                ->has('loginAttempts.data')
                ->where('statistics.total', 3)
                ->where('statistics.blocked', 1)
                ->where('config.filters.0.key', 'created_at')
                ->where('filters.created_at', $fromDate.','.$toDate)
            );

        $this->actingAs($this->superUser)
            ->get(route('app.logs.login-attempts.show', $loginAttempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/login-attempts/show')
                ->where('loginAttempt.id', $loginAttempt->id)
                ->has('recentEmailStats')
                ->has('recentIpStats')
            );

        $this->actingAs($this->superUser)
            ->getJson(route('app.logs.login-attempts.blocked-ips'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.ip_address', '192.0.2.44');

        $this->actingAs($this->superUser)
            ->postJson(route('app.logs.login-attempts.clear-rate-limit'), [
                'clear_all' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($this->superUser)
            ->postJson(route('app.logs.login-attempts.cleanup'), [
                'days_to_keep' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('deleted_count', 1);
    }

    public function test_not_found_log_pages_and_actions_work_for_authorized_users(): void
    {
        $recentLog = NotFoundLog::record(
            url: '/missing-page',
            ipAddress: '203.0.113.7',
            fullUrl: 'https://example.test/missing-page',
            referer: 'https://example.test',
            userAgent: 'Mozilla/5.0',
            userId: $this->superUser->id,
            method: 'GET'
        );

        $oldLog = NotFoundLog::record(
            url: '/legacy-page',
            ipAddress: '203.0.113.8',
            fullUrl: 'https://example.test/legacy-page',
            referer: null,
            userAgent: 'Mozilla/5.0',
            userId: $this->superUser->id,
            method: 'GET'
        );
        $oldLog->forceFill([
            'created_at' => now()->subDays(40),
        ])->save();

        $fromDate = now()->subDays(7)->toDateString();
        $toDate = now()->toDateString();

        $this->actingAs($this->superUser)
            ->get(route('app.logs.not-found-logs.index', [
                'created_at_from' => $fromDate,
                'created_at_to' => $toDate,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/not-found-logs/index')
                ->has('notFoundLogs.data')
                ->where('statistics.total', fn (int $total): bool => $total >= 2)
                ->where('config.filters.0.key', 'created_at')
                ->where('filters.created_at', $fromDate.','.$toDate)
            );

        $this->actingAs($this->superUser)
            ->get(route('app.logs.not-found-logs.show', $recentLog))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/not-found-logs/show')
                ->where('notFoundLog.id', $recentLog->id)
                ->has('recentUrlStats')
                ->has('recentIpStats')
            );

        $statisticsResponse = $this->actingAs($this->superUser)
            ->getJson(route('app.logs.not-found-logs.statistics'))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertGreaterThanOrEqual(1, (int) $statisticsResponse->json('data.total'));

        $this->actingAs($this->superUser)
            ->postJson(route('app.logs.not-found-logs.cleanup'), [
                'days_to_keep' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('deleted_count', 1);
    }
}
