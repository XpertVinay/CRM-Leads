<?php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Services\CouponService;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class SubscriptionController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $organization = $tenantContext->getTenant();
        $user = $request->user();
        $canManageBilling = $user->hasPermissionIn($organization, 'subscription.view');

        $plans = SubscriptionPlan::activeCached();
        $subscription = Subscription::query()
            ->with(['plan', 'coupon'])
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->first();

        $leadsThisMonth = Schema::hasTable('leads')
            ? DB::table('leads')
                ->where('organization_id', $organization->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count()
            : 0;

        $usersCount = DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->count();

        $orgLogs = $canManageBilling
            ? PaymentGatewayLog::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->limit(25)
                ->get()
            : collect();

        $coupons = $user->isSuperadmin()
            ? Coupon::query()->latest('id')->get()
            : collect();

        $globalLogs = $user->isSuperadmin()
            ? PaymentGatewayLog::query()->with('organization')->latest('id')->limit(50)->get()
            : collect();

        return view('subscriptions.index', [
            'plans' => $plans,
            'subscription' => $subscription,
            'canManageBilling' => $canManageBilling,
            'isSuperadmin' => $user->isSuperadmin(),
            'leadsThisMonth' => $leadsThisMonth,
            'usersCount' => $usersCount,
            'orgLogs' => $orgLogs,
            'coupons' => $coupons,
            'globalLogs' => $globalLogs,
            'autoOpenCheckout' => $request->boolean('checkout'),
        ]);
    }

    public function validateCoupon(Request $request, CouponService $couponService, TenantContext $tenantContext): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:64'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $organizationId = $tenantContext->getTenantId();

        $result = $couponService->validateAndCalculate(
            (string) $validated['coupon_code'],
            $plan,
            $organizationId,
        );

        return response()->json([
            'valid' => true,
            'code' => $result['coupon']->code,
            'base_amount_paise' => $result['base_amount_paise'],
            'discount_paise' => $result['discount_paise'],
            'discounted_amount_paise' => $result['discounted_amount_paise'],
            'initial_charge_paise' => $result['initial_charge_paise'],
            'is_mandate_only' => $result['is_mandate_only'],
            'message' => $result['message'],
        ]);
    }

    public function checkout(Request $request, TenantContext $tenantContext, SubscriptionService $subscriptionService): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        $user = $request->user();
        $this->authorizeBillingManager($user, $organization);

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $couponCode = isset($validated['coupon_code']) && trim((string) $validated['coupon_code']) !== ''
            ? trim((string) $validated['coupon_code'])
            : null;

        $existingPending = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'pending_payment')
            ->latest('id')
            ->first();

        $canReuseExisting = $existingPending instanceof Subscription
            && (int) $existingPending->subscription_plan_id === (int) $plan->id
            && (
                ($couponCode === null && $existingPending->coupon_id === null)
                || ($couponCode !== null && Str::upper($couponCode) === $existingPending->coupon?->code)
            );

        $subscription = $canReuseExisting
            ? $existingPending
            : $subscriptionService->createPendingSubscription($organization, $plan, $couponCode, $user);

        try {
            $checkoutPayload = $subscriptionService->initiateCheckout($subscription, $user);

            return response()->json($checkoutPayload);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to initialize Razorpay subscription checkout: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function verify(Request $request, TenantContext $tenantContext, SubscriptionService $subscriptionService): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        $user = $request->user();
        $this->authorizeBillingManager($user, $organization);

        $validated = $request->validate([
            'local_subscription_id' => ['required', 'integer'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_subscription_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $subscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($validated['local_subscription_id']);

        $updated = $subscriptionService->verifyCheckoutCallback($subscription, $validated, $user);

        return response()->json([
            'message' => 'Subscription activated successfully.',
            'status' => $updated->status,
            'redirect_url' => route('dashboard'),
        ]);
    }

    public function cancel(Request $request, TenantContext $tenantContext, SubscriptionService $subscriptionService): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        $user = $request->user();
        $this->authorizeBillingManager($user, $organization);

        $subscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'authenticated', 'pending_payment'])
            ->latest('id')
            ->firstOrFail();

        $cancelAtCycleEnd = $request->boolean('cancel_at_cycle_end', true);
        $updated = $subscriptionService->cancelSubscription($subscription, $user, $cancelAtCycleEnd);

        return response()->json([
            'message' => 'Subscription cancellation processed.',
            'status' => $updated->status,
            'cancel_at_period_end' => $updated->cancel_at_period_end,
        ]);
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        if (is_string($request->input('code'))) {
            $request->merge([
                'code' => Str::upper(trim($request->input('code'))),
            ]);
        }

        $discountValueRules = ['required', 'integer', 'min:1'];
        if ($request->input('discount_type') === 'percentage') {
            $discountValueRules[] = 'max:100';
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:coupons,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => $discountValueRules,
            'max_discount_paise' => ['nullable', 'integer', 'min:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'applicable_plan_codes' => ['nullable', 'array'],
            'applicable_plan_codes.*' => ['string', 'exists:subscription_plans,code'],
        ]);

        $coupon = Coupon::query()->create(array_merge($validated, [
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Coupon created successfully.',
            'coupon' => $coupon,
        ], 201);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'price_paise' => ['sometimes', 'integer', 'min:100'],
            'lead_limit' => ['sometimes', 'integer', 'min:1'],
            'user_limit' => ['sometimes', 'integer', 'min:1'],
            'extra_user_price_paise' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['price_paise']) && (int) $validated['price_paise'] !== (int) $plan->price_paise) {
            // Force Razorpay plan re-creation on next checkout if price changed.
            $validated['razorpay_plan_id'] = null;
        }

        $plan->update($validated);

        return response()->json([
            'message' => 'Plan updated and cache refreshed.',
            'plan' => $plan->fresh(),
        ]);
    }

    private function authorizeBillingManager(mixed $user, mixed $organization): void
    {
        if ($user->isSuperadmin()) {
            return;
        }

        abort_unless($user->hasPermissionIn($organization, 'subscription.view'), 403, 'Insufficient permissions to manage subscription billing.');
    }
}
