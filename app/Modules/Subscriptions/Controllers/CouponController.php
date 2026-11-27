<?php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $plans = SubscriptionPlan::activeCached();

        return view('coupons.index', [
            'plans' => $plans,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $query = Coupon::query()
            ->with('creator:id,name')
            ->withCount('redemptions')
            ->latest('id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', '%' . Str::upper($search) . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $validated = $this->validateCouponPayload($request);

        $coupon = Coupon::query()->create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Coupon created successfully.',
            'coupon' => $coupon->load('creator:id,name')->loadCount('redemptions'),
        ], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        $validated = $this->validateCouponPayload($request, $coupon);

        $coupon->update($validated);

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'coupon' => $coupon->fresh()->load('creator:id,name')->loadCount('redemptions'),
        ]);
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);

        if ($coupon->redemptions()->exists() || $coupon->redemptions_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a coupon that has already been redeemed. Deactivate it instead.',
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCouponPayload(Request $request, ?Coupon $existing = null): array
    {
        if (is_string($request->input('code'))) {
            $request->merge([
                'code' => Str::upper(trim($request->input('code'))),
            ]);
        }

        $discountType = (string) $request->input('discount_type', $existing?->discount_type ?? 'percentage');
        $discountValueRules = ['required', 'integer', 'min:1'];
        if ($discountType === 'percentage') {
            $discountValueRules[] = 'max:100';
        }

        $uniqueRule = Rule::unique('coupons', 'code');
        if ($existing instanceof Coupon) {
            $uniqueRule = $uniqueRule->ignore($existing->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:64', $uniqueRule],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => $discountValueRules,
            'max_discount_paise' => ['nullable', 'integer', 'min:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'applicable_plan_codes' => ['nullable', 'array'],
            'applicable_plan_codes.*' => ['string', 'exists:subscription_plans,code'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
