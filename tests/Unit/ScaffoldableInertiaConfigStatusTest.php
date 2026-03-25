<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScaffoldableInertiaConfigStatusTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function test_it_resolves_current_status_for_inertia_config(
        ?string $inputStatus,
        ?string $routeStatus,
        string $expectedStatus,
    ): void {
        $request = Request::create('/scaffold', 'GET', array_filter([
            'status' => $inputStatus,
        ], static fn (?string $value): bool => $value !== null));

        if ($routeStatus !== null) {
            $request->setRouteResolver(static fn () => new class($routeStatus)
            {
                public function __construct(
                    private readonly string $status,
                ) {}

                public function parameter(string $key): ?string
                {
                    return $key === 'status' ? $this->status : null;
                }
            });
        }

        $service = new class
        {
            use Scaffoldable;

            public function getScaffoldDefinition(): ScaffoldDefinition
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function exposeResolvedStatus(?Request $request): string
            {
                return $this->resolveInertiaConfigStatus($request);
            }

            public function buildListQuery(Request $request): Builder
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function transformItems(array $items): array
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function getPerPage(Request $request): int
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function authorizeBulkAction(string $action): void
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function executeBulkAction(string $action, array $ids, Request $request): array
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function executeBulkActionOnQuery(string $action, Builder $query, Request $request): array
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function customizeStatisticsQuery(Builder $query): void
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function prepareCreateData(array $data): array
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function prepareUpdateData(array $data): array
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function afterCreate(Model $model, array $data): void
            {
                throw new \RuntimeException('Not needed for this test.');
            }

            public function afterUpdate(Model $model, array $data): void
            {
                throw new \RuntimeException('Not needed for this test.');
            }
        };

        $this->assertSame($expectedStatus, $service->exposeResolvedStatus($request));
    }

    public static function statusProvider(): array
    {
        return [
            'query parameter wins' => ['trash', 'all', 'trash'],
            'route parameter fallback' => [null, 'trash', 'trash'],
            'empty string falls back to all' => ['', null, 'all'],
            'missing status falls back to all' => [null, null, 'all'],
        ];
    }
}
