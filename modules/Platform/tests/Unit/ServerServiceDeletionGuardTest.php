<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class ServerServiceDeletionGuardTest extends TestCase
{
    public function test_before_delete_throws_when_server_has_associated_websites(): void
    {
        $service = new class(true) extends ServerService
        {
            public function __construct(private readonly bool $hasWebsites) {}

            protected function hasAssociatedWebsites(Server $server): bool
            {
                return $this->hasWebsites;
            }
        };

        $method = (new ReflectionClass($service))->getMethod('beforeDelete');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot trash or delete this server because websites are associated with it.');

        $method->invoke($service, new Server(['name' => 'Main Server']));
    }

    public function test_before_force_delete_throws_when_server_has_associated_websites(): void
    {
        $service = new class(true) extends ServerService
        {
            public function __construct(private readonly bool $hasWebsites) {}

            protected function hasAssociatedWebsites(Server $server): bool
            {
                return $this->hasWebsites;
            }
        };

        $method = (new ReflectionClass($service))->getMethod('beforeForceDelete');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot trash or delete this server because websites are associated with it.');

        $method->invoke($service, new Server(['name' => 'Main Server']));
    }

    public function test_before_delete_allows_server_without_associated_websites(): void
    {
        $service = new class(false) extends ServerService
        {
            public function __construct(private readonly bool $hasWebsites) {}

            protected function hasAssociatedWebsites(Server $server): bool
            {
                return $this->hasWebsites;
            }
        };

        $method = (new ReflectionClass($service))->getMethod('beforeDelete');

        $model = $method->invoke($service, new Server(['name' => 'Main Server']));

        $this->assertNull($model);
    }

    public function test_before_delete_ignores_non_server_models(): void
    {
        $service = new class(true) extends ServerService
        {
            public function __construct(private readonly bool $hasWebsites) {}

            protected function hasAssociatedWebsites(Server $server): bool
            {
                return $this->hasWebsites;
            }
        };

        $method = (new ReflectionClass($service))->getMethod('beforeDelete');

        $result = $method->invoke($service, new class extends Model
        {
            use HasFactory;

            protected $table = 'users';
        });

        $this->assertNull($result);
    }
}
