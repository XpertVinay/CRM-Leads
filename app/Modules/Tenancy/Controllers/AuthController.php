<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Services\CouponService;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Services\TenantProvisioningService;
use App\Modules\Tenancy\Support\OrganizationInputNormalizer;
use App\Support\Security\DataEncrypter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => Str::lower(trim($request->input('email'))),
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower((string) $credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $firstActiveOrg = $user->organizations()
            ->where('organizations.status', 'active')
            ->orderBy('organizations.id')
            ->first();

        if ($firstActiveOrg !== null) {
            $request->session()->put('active_organization_id', (int) $firstActiveOrg->id);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended(route('organizations.index'));
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', [
            'plans' => SubscriptionPlan::activeCached(),
            'selectedPlanCode' => $request->query('plan'),
        ]);
    }

    public function register(
        Request $request,
        TenantProvisioningService $provisioning,
        SubscriptionService $subscriptionService,
        CouponService $couponService,
    ): RedirectResponse {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => Str::lower(trim($request->input('email'))),
            ]);
        }

        $input = $request->all();

        ['slug' => $normalizedSlug, 'invalid_slug' => $slugNormalizedToEmpty] = OrganizationInputNormalizer::normalizeSlug(
            $input['organization_slug'] ?? null,
        );
        $input['organization_slug'] = $normalizedSlug;
        $input['registration_identifier'] = OrganizationInputNormalizer::normalizeNullableString(
            $input['registration_identifier'] ?? null,
        );
        $input['primary_contact_email'] = OrganizationInputNormalizer::normalizeNullableEmail(
            $input['primary_contact_email'] ?? null,
        );
        $input['timezone'] = OrganizationInputNormalizer::normalizeNullableString($input['timezone'] ?? null);
        $input['currency'] = OrganizationInputNormalizer::normalizeNullableString($input['currency'] ?? null);

        $activePlans = SubscriptionPlan::activeCached();
        $planRule = $activePlans->isNotEmpty()
            ? ['required', 'integer', 'exists:subscription_plans,id']
            : ['nullable', 'integer', 'exists:subscription_plans,id'];

        $encrypter = DataEncrypter::instance();
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'string',
                'email',
                'max:191',
                function (string $attribute, mixed $value, \Closure $fail) use ($encrypter): void {
                    $hash = is_string($value) ? $encrypter->blindIndex($value, 'email') : null;
                    if ($hash !== null && \App\Models\User::query()->where('email_hash', $hash)->exists()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_name' => ['required', 'string', 'max:191'],
            'organization_slug' => ['nullable', 'string', 'max:191', 'unique:organizations,slug'],
            'registration_identifier' => ['nullable', 'string', 'max:100', 'unique:organizations,registration_identifier'],
            'primary_contact_email' => [
                'nullable',
                'email',
                'max:191',
                function (string $attribute, mixed $value, \Closure $fail) use ($encrypter): void {
                    $hash = is_string($value) ? $encrypter->blindIndex($value, 'email') : null;
                    if ($hash !== null && \App\Models\Organization::withTrashed()->where('primary_contact_email_hash', $hash)->exists()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'subscription_plan_id' => $planRule,
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($slugNormalizedToEmpty): void {
            if ($slugNormalizedToEmpty) {
                $validator->errors()->add(
                    'organization_slug',
                    'The organization slug must contain at least one alphanumeric character.',
                );
            }
        });

        $validated = $validator->validate();

        $selectedPlan = isset($validated['subscription_plan_id'])
            ? SubscriptionPlan::query()->where('is_active', true)->find($validated['subscription_plan_id'])
            : null;
        $couponCode = isset($validated['coupon_code']) && trim((string) $validated['coupon_code']) !== ''
            ? trim((string) $validated['coupon_code'])
            : null;

        if ($selectedPlan !== null && $couponCode !== null) {
            $couponService->validateAndCalculate($couponCode, $selectedPlan);
        }

        [$user, $organization, $subscription] = DB::transaction(function () use ($validated, $provisioning, $selectedPlan, $couponCode, $subscriptionService): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
            ]);

            $primaryContactEmail = $validated['primary_contact_email'] ?? null;
            if ($primaryContactEmail === null) {
                $emailCandidate = $validated['email'];
                $emailCandidateHash = DataEncrypter::instance()->blindIndex($emailCandidate, 'email');
                if ($emailCandidateHash === null || !Organization::withTrashed()->where('primary_contact_email_hash', $emailCandidateHash)->exists()) {
                    $primaryContactEmail = $emailCandidate;
                }
            }

            $organization = $provisioning->provisionOrganization($user, [
                'name' => $validated['organization_name'],
                'slug' => $validated['organization_slug'] ?? null,
                'registration_identifier' => $validated['registration_identifier'] ?? null,
                'primary_contact_email' => $primaryContactEmail,
                'timezone' => $validated['timezone'] ?? 'UTC',
                'currency' => $validated['currency'] ?? ($selectedPlan?->currency ?? 'USD'),
            ]);

            $subscription = $selectedPlan !== null
                ? $subscriptionService->createPendingSubscription($organization, $selectedPlan, $couponCode, $user)
                : null;

            return [$user, $organization, $subscription];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_organization_id', (int) $organization->id);

        if ($subscription !== null) {
            return redirect()->route('subscription.index', ['checkout' => 1]);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        Auth::logout();
        $tenantContext->clear();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
