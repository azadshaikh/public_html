<?php

declare(strict_types=1);

namespace Modules\Billing\DataTransferObjects;

use Modules\Billing\Models\Coupon;

/**
 * Returned by CouponService::validate().
 */
readonly class CouponValidationResult
{
    public function __construct(
        public bool $valid,
        public ?Coupon $coupon,
        public float $discount,
        public ?string $error,
    ) {}

    public static function success(Coupon $coupon, float $discount): self
    {
        return new self(
            valid: true,
            coupon: $coupon,
            discount: $discount,
            error: null,
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            valid: false,
            coupon: null,
            discount: 0.0,
            error: $error,
        );
    }
}
