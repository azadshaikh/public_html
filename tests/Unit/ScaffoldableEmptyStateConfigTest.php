<?php

namespace Tests\Unit;

use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ScaffoldableEmptyStateConfigTest extends TestCase
{
    public function test_empty_state_omits_create_action_when_current_user_cannot_add_resource(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn(new class
        {
            public function can(string $permission): bool
            {
                return false;
            }
        });

        $service = new class
        {
            use Scaffoldable;

            public function getScaffoldDefinition(): ScaffoldDefinition
            {
                return new class extends ScaffoldDefinition
                {
                    protected string $entityName = 'User';

                    protected string $entityPlural = 'Users';

                    protected string $routePrefix = 'app.users';

                    protected string $permissionPrefix = 'users';

                    public function columns(): array
                    {
                        return [];
                    }

                    public function getModelClass(): string
                    {
                        return User::class;
                    }
                };
            }

            public function exposedEmptyStateConfig(): array
            {
                return $this->getEmptyStateConfig();
            }
        };

        $this->assertArrayNotHasKey('action', $service->exposedEmptyStateConfig());
    }

    public function test_empty_state_includes_create_action_when_current_user_can_add_resource(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn(new class
        {
            public function can(string $permission): bool
            {
                return $permission === 'add_users';
            }
        });

        $service = new class
        {
            use Scaffoldable;

            public function getScaffoldDefinition(): ScaffoldDefinition
            {
                return new class extends ScaffoldDefinition
                {
                    protected string $entityName = 'User';

                    protected string $entityPlural = 'Users';

                    protected string $routePrefix = 'app.users';

                    protected string $permissionPrefix = 'users';

                    public function columns(): array
                    {
                        return [];
                    }

                    public function getModelClass(): string
                    {
                        return User::class;
                    }
                };
            }

            public function exposedEmptyStateConfig(): array
            {
                return $this->getEmptyStateConfig();
            }
        };

        $config = $service->exposedEmptyStateConfig();

        $this->assertSame('Create User', $config['action']['label']);
        $this->assertSame(route('app.users.create'), $config['action']['url']);
    }
}
