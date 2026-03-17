<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Domain;
use Modules\Platform\Services\DomainService;
use Tests\TestCase;

class DomainServiceStatusFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_apply_filters_does_not_apply_status_filter_when_status_is_all(): void
    {
        $service = new DomainService;
        /** @var Builder<Domain>&MockInterface $query */
        $query = Mockery::mock(Builder::class);
        $request = new Request(['status' => 'all']);

        $query->shouldNotReceive('where');
        $query->shouldNotReceive('whereDate');
        $query->shouldNotReceive('whereHas');

        $service->applyFilters($query, $request);

        $this->assertInstanceOf(Builder::class, $query);
    }
}
