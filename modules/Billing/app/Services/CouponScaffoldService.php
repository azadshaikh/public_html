<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\CouponDefinition;
use Modules\Billing\Http\Resources\CouponResource;
use Modules\Billing\Models\Coupon;

class CouponScaffoldService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new CouponDefinition;
    }

    // ================================================================
    // RESOURCE
    // ================================================================

    protected function getResourceClass(): ?string
    {
        return CouponResource::class;
    }

    // ================================================================
    // STATISTICS
    // ================================================================

    public function getStatistics(): array
    {
        $now = now();

        return [
            'total' => Coupon::query()->whereNull('deleted_at')->count(),
            'active' => Coupon::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => Coupon::query()->where('is_active', false)->whereNull('deleted_at')->count(),
            'expired' => Coupon::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->whereNull('deleted_at')
                ->count(),
            'trash' => Coupon::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // OVERRIDE: Status filter for boolean is_active and computed expired
    // ================================================================

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status');

        if (empty($status) || $status === 'all' || $status === 'trash') {
            return;
        }

        match ($status) {
            'active' => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now())
                ),
            'inactive' => $query->where('is_active', false),
            'expired' => $query->whereNotNull('expires_at')
                ->where('expires_at', '<', now()),
            default => null,
        };
    }

    // ================================================================
    // FORM OPTIONS
    // ================================================================

    public function getTypeOptions(): array
    {
        return [
            ['value' => Coupon::TYPE_PERCENT, 'label' => 'Percentage (%)'],
            ['value' => Coupon::TYPE_FIXED, 'label' => 'Fixed Amount'],
        ];
    }

    public function getDurationOptions(): array
    {
        return [
            ['value' => Coupon::DURATION_ONCE, 'label' => 'Once — applied to first payment only'],
            ['value' => Coupon::DURATION_REPEATING, 'label' => 'Repeating — applied for N months'],
            ['value' => Coupon::DURATION_FOREVER, 'label' => 'Forever — applied to every payment'],
        ];
    }
}
