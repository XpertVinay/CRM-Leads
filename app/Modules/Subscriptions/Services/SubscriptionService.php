<?php

namespace App\Modules\Subscriptions\Services;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Compliance\Services\AuditService;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\CouponRedemption;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Notifications\SubscriptionNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionService
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly RazorpayGatewayClient $razorpayClient,
        private readonly AuditService $auditService,
    ) {}

    public function createPendingSubscription(
        Organization $organization,
        SubscriptionPlan $plan,
        ?string $couponCode,
        User $actor,
    ): Subscription {
        $coupon = null;
        $baseAmount = (int) $plan->price_paise;
        $discountPaise = 0;
        $initialChargePaise = $baseAmount;
        $isMandateOnly = false;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $calc = $this->couponService->validateAndCalculate($couponCode, $plan, $organization->id);
            $coupon = $calc['coupon'];
            $discountPaise = $calc['discount_paise'];
            $initialChargePaise = $calc['initial_charge_paise'];
            $isMandateOnly = $calc['is_mandate_only'];
        }

        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'subscription_plan_id' => $plan->id,
            'coupon_id' => $coupon?->id,
            'status' => 'pending_payment',
            'base_amount_paise' => $baseAmount,
            'discount_paise' => $discountPaise,
            'initial_charge_paise' => $initialChargePaise,
            'is_mandate_only' => $isMandateOnly,
            'currency' => $plan->currency ?: 'INR',
            'metadata' => [
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'lead_limit' => $plan->lead_limit,
                'user_limit' => $plan->user_limit,
                'coupon_code' => $coupon?->code,
            ],
        ]);

        $this->auditService->record(
            action: 'subscription.created',
            auditable: $subscription,
            newValues: [
                'plan_code' => $plan->code,
                'base_amount_paise' => $baseAmount,
                'discount_paise' => $discountPaise,
                'initial_charge_paise' => $initialChargePaise,
                'coupon_code' => $coupon?->code,
                'status' => 'pending_payment',
            ],
            organizationId: $organization->id,
            userId: $actor->id,
        );

        $this->notifyOrgAdmins(
            $organization,
            $subscription,
            'subscription_registered',
            'Subscription Selected — Payment Pending',
            sprintf(
                'Organization %s registered with %s (%s/mo). Initial charge: %s. Complete Razorpay mandate setup to activate. You can cancel anytime.',
                $organization->name,
                $plan->name,
                $plan->formattedPrice(),
                $this->formatPaise($initialChargePaise, $subscription->currency),
            ),
            $actor,
        );

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function initiateCheckout(Subscription $subscription, User $actor): array
    {
        $subscription->loadMissing(['plan', 'coupon', 'organization']);
        $plan = $subscription->plan;

        if (! $plan->razorpay_plan_id) {
            $rzpPlan = $this->razorpayClient->createPlan($plan, $subscription->organization_id, $actor->id);
            $plan->update(['razorpay_plan_id' => (string) $rzpPlan['id']]);
        }

        if (! $subscription->razorpay_subscription_id) {
            $payload = [
                'plan_id' => $plan->razorpay_plan_id,
                'total_count' => 120,
                'quantity' => 1,
                'customer_notify' => 1,
                'notes' => [
                    'organization_id' => (string) $subscription->organization_id,
                    'local_subscription_id' => (string) $subscription->id,
                    'plan_code' => $plan->code,
                    'coupon_code' => (string) ($subscription->coupon?->code ?? ''),
                ],
            ];

            // When discounted (including 100% discount -> Rs 1 mandate), charge initial_charge_paise upfront
            // via addon and defer full-plan recurring billing to +1 month.
            if ($subscription->discount_paise > 0) {
                $payload['start_at'] = now()->addMonth()->timestamp;
                $payload['addons'] = [
                    [
                        'item' => [
                            'name' => $subscription->is_mandate_only
                                ? "{$plan->name} — ₹1 Mandate Authorization"
                                : "{$plan->name} — Discounted First Month",
                            'amount' => (int) $subscription->initial_charge_paise,
                            'currency' => $subscription->currency ?: 'INR',
                        ],
                    ],
                ];
            }

            $rzpSub = $this->razorpayClient->createSubscription($subscription, $payload, $actor->id);

            $subscription->update([
                'razorpay_subscription_id' => (string) $rzpSub['id'],
                'razorpay_plan_id' => $plan->razorpay_plan_id,
            ]);
        }

        return [
            'key_id' => (string) config('services.razorpay.key_id'),
            'subscription_id' => $subscription->razorpay_subscription_id,
            'local_subscription_id' => $subscription->id,
            'name' => config('app.name', 'CRM'),
            'description' => $subscription->is_mandate_only
                ? "{$plan->name} (₹1 Mandate Setup; {$plan->formattedPrice()}/mo thereafter)"
                : "{$plan->name} ({$plan->formattedPrice()}/mo)",
            'amount_paise' => $subscription->initial_charge_paise,
            'currency' => $subscription->currency ?: 'INR',
            'is_mandate_only' => $subscription->is_mandate_only,
            'prefill' => [
                'name' => $actor->name,
                'email' => $actor->email,
                'contact' => $actor->phone ?? '',
            ],
            'cancel_notice' => 'You can cancel your subscription anytime.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyCheckoutCallback(Subscription $subscription, array $payload, User $actor): Subscription
    {
        $razorpayPaymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $razorpaySubscriptionId = (string) ($payload['razorpay_subscription_id'] ?? '');
        $razorpaySignature = (string) ($payload['razorpay_signature'] ?? '');
        $keySecret = (string) config('services.razorpay.key_secret');

        try {
            $expected = hash_hmac('sha256', "{$razorpayPaymentId}|{$razorpaySubscriptionId}", $keySecret);
            $isValid = $keySecret !== ''
                && $razorpaySubscriptionId === (string) $subscription->razorpay_subscription_id
                && hash_equals($expected, $razorpaySignature);

            PaymentGatewayLog::query()->create([
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'user_id' => $actor->id,
                'direction' => 'checkout_callback',
                'event_or_endpoint' => 'checkout.verify',
                'http_method' => 'POST',
                'http_status' => $isValid ? 200 : 422,
                'razorpay_subscription_id' => $razorpaySubscriptionId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'request_payload' => [
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_subscription_id' => $razorpaySubscriptionId,
                    'razorpay_signature' => '[REDACTED]',
                ],
                'response_payload' => ['verified' => $isValid],
                'signature_valid' => $isValid,
                'status' => $isValid ? 'success' : 'failed',
                'error_message' => $isValid ? null : 'Razorpay signature verification failed.',
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            if (! $isValid) {
                $this->auditService->record(
                    action: 'subscription.signature_failed',
                    auditable: $subscription,
                    newValues: ['razorpay_payment_id' => $razorpayPaymentId],
                    organizationId: $subscription->organization_id,
                    userId: $actor->id,
                );

                throw ValidationException::withMessages([
                    'razorpay_signature' => ['Payment signature verification failed.'],
                ]);
            }

            DB::transaction(function () use ($subscription, $actor): void {
                $oldStatus = $subscription->status;
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => $subscription->current_period_start ?? now(),
                    'current_period_end' => $subscription->current_period_end ?? now()->addMonth(),
                ]);

                if ($subscription->coupon_id !== null) {
                    $redemption = CouponRedemption::query()->firstOrCreate(
                        [
                            'coupon_id' => $subscription->coupon_id,
                            'organization_id' => $subscription->organization_id,
                            'subscription_id' => $subscription->id,
                        ],
                        [
                            'user_id' => $actor->id,
                            'discount_paise' => $subscription->discount_paise,
                            'redeemed_at' => now(),
                        ],
                    );

                    if ($redemption->wasRecentlyCreated) {
                        Coupon::query()->whereKey($subscription->coupon_id)->increment('redemptions_count');
                    }
                }

                $this->auditService->record(
                    action: 'subscription.activated',
                    auditable: $subscription,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'active'],
                    organizationId: $subscription->organization_id,
                    userId: $actor->id,
                );
            });

            $subscription->refresh()->loadMissing(['plan', 'coupon', 'organization']);

            $this->notifyOrgAdmins(
                $subscription->organization,
                $subscription,
                'subscription_activated',
                'Subscription Activated',
                sprintf(
                    'Your %s subscription is now active (%s charged). You can cancel your subscription anytime.',
                    $subscription->plan->name,
                    $this->formatPaise($subscription->initial_charge_paise, $subscription->currency),
                ),
                $actor,
            );

            return $subscription;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            PaymentGatewayLog::query()->create([
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'user_id' => $actor->id,
                'direction' => 'checkout_callback',
                'event_or_endpoint' => 'checkout.verify',
                'http_method' => 'POST',
                'http_status' => 500,
                'razorpay_subscription_id' => $razorpaySubscriptionId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'status' => 'exception',
                'error_message' => $e->getMessage(),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            throw $e;
        }
    }

    public function cancelSubscription(Subscription $subscription, User $actor, bool $cancelAtCycleEnd = true): Subscription
    {
        if ($subscription->razorpay_subscription_id) {
            $this->razorpayClient->cancelSubscription($subscription, $cancelAtCycleEnd, $actor->id);
        }

        $oldStatus = $subscription->status;
        $subscription->update([
            'status' => $cancelAtCycleEnd && $subscription->isOperational() ? $subscription->status : 'cancelled',
            'cancel_at_period_end' => $cancelAtCycleEnd,
            'cancelled_at' => now(),
        ]);

        $this->auditService->record(
            action: 'subscription.cancelled',
            auditable: $subscription,
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status' => $subscription->status,
                'cancel_at_period_end' => $cancelAtCycleEnd,
            ],
            organizationId: $subscription->organization_id,
            userId: $actor->id,
        );

        $subscription->loadMissing(['plan', 'organization']);
        $this->notifyOrgAdmins(
            $subscription->organization,
            $subscription,
            'subscription_cancelled',
            'Subscription Cancellation Requested',
            "Your {$subscription->plan->name} subscription has been marked for cancellation.",
            $actor,
        );

        return $subscription;
    }

    public function notifyOrgAdmins(
        Organization $organization,
        Subscription $subscription,
        string $eventType,
        string $title,
        string $message,
        ?User $fallbackUser = null,
    ): void {
        $admins = $organization->users()
            ->wherePivot('role', 'org_admin')
            ->get();

        if ($admins->isEmpty() && $fallbackUser !== null) {
            $admins = collect([$fallbackUser]);
        }

        foreach ($admins as $admin) {
            $admin->notify(new SubscriptionNotification($subscription, $eventType, $title, $message));
        }
    }

    private function formatPaise(int $paise, string $currency): string
    {
        $symbol = $currency === 'INR' ? '₹' : $currency . ' ';

        return $symbol . number_format($paise / 100, $paise % 100 === 0 ? 0 : 2);
    }
}
