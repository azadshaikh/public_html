<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\DataTransferObjects\CouponValidationResult;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use RuntimeException;

class CouponService
{
    /**
     * Validate a coupon code against the given context.
     *
     * Checks (in order):
     *  1. Coupon exists and is_active
     *  2. Not expired
     *  3. Not exhausted (uses_count < max_uses)
     *  4. Customer has not exceeded max_uses_per_customer
     *  5. subtotal >= min_order_amount
     *  6. plan restriction (applicable_plan_ids)
     */
    public function validate(
        string $code,
        int $customerId,
        float $subtotal,
        ?int $planId = null,
    ): CouponValidationResult {
        $coupon = Coupon::query()
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->withoutTrashed()
            ->first();

        if (! $coupon instanceof Coupon) {
            return CouponValidationResult::failure('Invalid coupon code.');
        }

        if ($coupon->isExpired()) {
            return CouponValidationResult::failure('This coupon has expired.');
        }

        if ($coupon->isExhausted()) {
            return CouponValidationResult::failure('This coupon has reached its usage limit.');
        }

        $customerUses = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('customer_id', $customerId)
            ->count();

        if ($customerUses >= $coupon->max_uses_per_customer) {
            return CouponValidationResult::failure('You have already used this coupon.');
        }

        if ($coupon->min_order_amount !== null && $subtotal < $coupon->min_order_amount) {
            return CouponValidationResult::failure(
                'This coupon requires a minimum order of '.number_format($coupon->min_order_amount, 2).'.'
            );
        }

        if (! $coupon->isApplicableToPlan($planId)) {
            return CouponValidationResult::failure('This coupon is not valid for the selected plan.');
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return CouponValidationResult::success($coupon, $discount);
    }

    /**
     * Record a coupon redemption and increment the uses_count.
     * Must be called after the order is confirmed as paid.
     */
    public function redeem(
        Coupon $coupon,
        int $customerId,
        int $orderId,
        float $discountApplied,
    ): CouponRedemption {
        return DB::transaction(function () use ($coupon, $customerId, $orderId, $discountApplied): CouponRedemption {
            $lockedCoupon = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->first();

            throw_unless($lockedCoupon instanceof Coupon, RuntimeException::class, 'Invalid coupon code.');

            // Idempotency: Stripe/onboarding retries for the same order must not consume uses twice.
            $existingRedemption = CouponRedemption::query()
                ->where('coupon_id', $lockedCoupon->id)
                ->where('order_id', $orderId)
                ->first();

            if ($existingRedemption instanceof CouponRedemption) {
                return $existingRedemption;
            }

            throw_if($lockedCoupon->isExhausted(), RuntimeException::class, 'This coupon has reached its usage limit.');

            $customerUses = CouponRedemption::query()
                ->where('coupon_id', $lockedCoupon->id)
                ->where('customer_id', $customerId)
                ->count();

            throw_if($customerUses >= $lockedCoupon->max_uses_per_customer, RuntimeException::class, 'You have already used this coupon.');

            $lockedCoupon->increment('uses_count');

            return CouponRedemption::query()->create([
                'coupon_id' => $lockedCoupon->id,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'discount_applied' => $discountApplied,
                'redeemed_at' => now(),
            ]);
        });
    }
}
