<?php

namespace App\Modules\Subscriptions\Services;

use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\CouponRedemption;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon code against a plan and compute the first-cycle charge.
     *
     * @return array{
     *     coupon: Coupon,
     *     base_amount_paise: int,
     *     discount_paise: int,
     *     discounted_amount_paise: int,
     *     initial_charge_paise: int,
     *     is_mandate_only: bool,
     *     message: string
     * }
     */
    public function validateAndCalculate(string $rawCode, SubscriptionPlan $plan, ?int $organizationId = null): array
    {
        $code = Str::upper(trim($rawCode));
        if ($code === '') {
            throw ValidationException::withMessages([
                'coupon_code' => ['Please enter a coupon code.'],
            ]);
        }

        $coupon = Coupon::query()->where('code', $code)->first();
        if (! $coupon instanceof Coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => ['The coupon code is invalid or inactive.'],
            ]);
        }

        $now = now();
        if ($coupon->valid_from !== null && $now->lt($coupon->valid_from)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not active yet.'],
            ]);
        }

        if ($coupon->valid_until !== null && $now->gt($coupon->valid_until)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has expired.'],
            ]);
        }

        if ($coupon->max_redemptions !== null && $coupon->redemptions_count >= $coupon->max_redemptions) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has reached its maximum redemption limit.'],
            ]);
        }

        $applicablePlans = $coupon->applicable_plan_codes;
        if (is_array($applicablePlans) && $applicablePlans !== [] && ! in_array($plan->code, $applicablePlans, true)) {
            throw ValidationException::withMessages([
                'coupon_code' => ["Coupon {$coupon->code} is not valid for the {$plan->name}."],
            ]);
        }

        if ($organizationId !== null) {
            $alreadyRedeemed = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('organization_id', $organizationId)
                ->exists();

            if ($alreadyRedeemed) {
                throw ValidationException::withMessages([
                    'coupon_code' => ["Coupon {$coupon->code} has already been used by this organization."],
                ]);
            }
        }

        $baseAmountPaise = (int) $plan->price_paise;
        if ($coupon->discount_type === 'percentage') {
            $percent = min(100, max(0, (int) $coupon->discount_value));
            $discountPaise = (int) floor(($baseAmountPaise * $percent) / 100);
        } else {
            $discountPaise = (int) $coupon->discount_value;
        }

        if ($coupon->max_discount_paise !== null) {
            $discountPaise = min($discountPaise, (int) $coupon->max_discount_paise);
        }

        $discountPaise = min($baseAmountPaise, max(0, $discountPaise));
        $discountedAmountPaise = max(0, $baseAmountPaise - $discountPaise);

        // If coupon reduces first-cycle total to Rs 0, charge Rs 1 (100 paise) to authorize recurring mandate.
        $isMandateOnly = $discountedAmountPaise === 0;
        $initialChargePaise = $isMandateOnly ? 100 : $discountedAmountPaise;

        $message = $isMandateOnly
            ? '100% discount applied! ₹1 will be charged to authorize your recurring mandate; future cycles will renew at the plan rate. You can cancel anytime.'
            : 'Coupon applied successfully.';

        return [
            'coupon' => $coupon,
            'base_amount_paise' => $baseAmountPaise,
            'discount_paise' => $discountPaise,
            'discounted_amount_paise' => $discountedAmountPaise,
            'initial_charge_paise' => $initialChargePaise,
            'is_mandate_only' => $isMandateOnly,
            'message' => $message,
        ];
    }
}
