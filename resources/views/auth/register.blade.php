@extends('layouts.crm')

@section('title', __('Create Account & Organization') . ' — CRM')
@section('auth_width_class', 'crm-auth-wide')

@section('content')
    @php
        $hasPlans = isset($plans) && $plans->isNotEmpty();
        $step2Fields = ['subscription_plan_id', 'coupon_code'];
        $hasStep1Errors = collect($errors->keys())->diff($step2Fields)->isNotEmpty();
        $hasStep2Errors = collect($errors->keys())->intersect($step2Fields)->isNotEmpty();
        $initialStep = ($hasPlans && $hasStep2Errors && ! $hasStep1Errors) ? 2 : 1;
    @endphp

    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:1rem;">
        <div>
            <div class="crm-pill-badge" style="margin-bottom:0.35rem;">
                <span class="material-symbols-outlined" style="font-size: 15px; font-variation-settings: 'FILL' 1;">bolt</span>
                <span>{{ __('Get started in 30 seconds') }}</span>
            </div>
            <h1 class="crm-page-title" style="font-size: 1.45rem; margin:0;">{{ __('Create Account & Organization') }}</h1>
            <p class="crm-muted" style="margin:0.15rem 0 0; font-size:0.86rem;">{{ __('Provision a new tenant workspace as Org Admin.') }}</p>
        </div>

        @if($hasPlans)
            <div id="reg-stepper" style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <button type="button" id="reg-step-tab-1" data-step-tab="1"
                    style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.85rem; border-radius:10px; border:1.5px solid #cbd5e1; background:#fff; cursor:pointer; text-align:left;">
                    <span class="reg-step-num"
                        style="width:22px; height:22px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">1</span>
                    <span>
                        <strong style="display:block; font-size:0.8rem; line-height:1.15;">{{ __('Registration Details') }}</strong>
                        <span class="crm-muted" style="font-size:0.71rem;">{{ __('Account & workspace') }}</span>
                    </span>
                </button>
                <button type="button" id="reg-step-tab-2" data-step-tab="2"
                    style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.85rem; border-radius:10px; border:1.5px solid #cbd5e1; background:#fff; cursor:pointer; text-align:left;">
                    <span class="reg-step-num"
                        style="width:22px; height:22px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">2</span>
                    <span>
                        <strong style="display:block; font-size:0.8rem; line-height:1.15;">{{ __('Subscription & Coupon') }}</strong>
                        <span class="crm-muted" style="font-size:0.71rem;">{{ __('Plan & redemption') }}</span>
                    </span>
                </button>
            </div>
        @endif
    </div>

    <form id="reg-form" method="POST" action="{{ route('register') }}">
        @csrf

        {{-- Step 1: Registration Details --}}
        <div id="reg-step-1" @if($initialStep !== 1) style="display:none;" @endif>
            <div id="reg-step1-error" style="display:none; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:0.55rem 0.85rem; font-size:0.84rem; margin-bottom:0.85rem;"></div>

            <div class="reg-panel">
                <div class="reg-panel-title">
                    <span class="material-symbols-outlined" style="font-size:16px;">badge</span>
                    <span>{{ __('Account & Workspace') }}</span>
                </div>

                <div class="reg-grid-3">
                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="name">{{ __('Full Name') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">person</span>
                            <input id="name" class="crm-input" type="text" name="name" value="{{ old('name') }}"
                                placeholder="Alex Morgan" required>
                        </div>
                    </div>

                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="email">{{ __('Work Email') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">mail</span>
                            <input id="email" class="crm-input" type="email" name="email" value="{{ old('email') }}"
                                placeholder="alex@example.com" required>
                        </div>
                    </div>

                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="phone">{{ __('Phone (Optional)') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">call</span>
                            <input id="phone" class="crm-input" type="text" name="phone" value="{{ old('phone') }}"
                                placeholder="+1 555 0100">
                        </div>
                    </div>
                </div>

                <div class="reg-grid-2" style="margin-top:0.85rem;">
                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="organization_name">{{ __('Organization Name') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">corporate_fare</span>
                            <input id="organization_name" class="crm-input" type="text" name="organization_name"
                                value="{{ old('organization_name') }}" placeholder="Acme Corp" required>
                        </div>
                    </div>

                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="organization_slug">{{ __('Organization Slug (Optional)') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">link</span>
                            <input id="organization_slug" class="crm-input" type="text" name="organization_slug"
                                value="{{ old('organization_slug') }}" placeholder="e.g. acme-corp">
                        </div>
                    </div>
                </div>

                @if(config('app.organization_type'))
                    <div class="reg-grid-2" style="margin-top:0.85rem;">
                        <div class="crm-form-group" style="margin-bottom:0;">
                            <label class="crm-label" for="registration_identifier">{{ __('Registration Identifier (Optional)') }}</label>
                            <input id="registration_identifier" class="crm-input" type="text" name="registration_identifier"
                                value="{{ old('registration_identifier') }}">
                        </div>
                        <div class="crm-form-group" style="margin-bottom:0;">
                            <label class="crm-label" for="primary_contact_email">{{ __('Primary Contact Email (Optional)') }}</label>
                            <input id="primary_contact_email" class="crm-input" type="email" name="primary_contact_email"
                                value="{{ old('primary_contact_email') }}">
                        </div>
                        <div class="crm-form-group" style="margin-bottom:0;">
                            <label class="crm-label" for="timezone">{{ __('Timezone (Optional)') }}</label>
                            <input id="timezone" class="crm-input" type="text" name="timezone" value="{{ old('timezone') }}" placeholder="UTC">
                        </div>
                        <div class="crm-form-group" style="margin-bottom:0;">
                            <label class="crm-label" for="currency">{{ __('Currency (Optional)') }}</label>
                            <input id="currency" class="crm-input" type="text" name="currency" value="{{ old('currency') }}" placeholder="USD" maxlength="3">
                        </div>
                    </div>
                @endif
            </div>

            <div class="reg-panel">
                <div class="reg-panel-title">
                    <span class="material-symbols-outlined" style="font-size:16px;">shield_lock</span>
                    <span>{{ __('Security') }}</span>
                </div>

                <div class="reg-grid-2">
                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="password">{{ __('Password') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">lock</span>
                            <input id="password" class="crm-input" type="password" name="password"
                                placeholder="{{ __('Create a password') }}" data-password-strength="#crm-reg-strength" required>
                            <button type="button" class="crm-input-action" data-toggle-password="#password"
                                aria-label="{{ __('Toggle password visibility') }}">
                                <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                            </button>
                        </div>
                        <div id="crm-reg-strength" class="crm-strength-grid">
                            <div class="crm-strength-bar"></div>
                            <div class="crm-strength-bar"></div>
                            <div class="crm-strength-bar"></div>
                            <div class="crm-strength-bar"></div>
                        </div>
                    </div>

                    <div class="crm-form-group" style="margin-bottom:0;">
                        <label class="crm-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
                        <div class="crm-input-wrap">
                            <span class="material-symbols-outlined crm-input-icon">lock_reset</span>
                            <input id="password_confirmation" class="crm-input" type="password" name="password_confirmation"
                                placeholder="{{ __('Confirm your password') }}" required>
                        </div>
                        <p class="crm-muted" style="font-size: 0.74rem; margin: 0.35rem 0 0;">{{ __('Use 8+ characters with a mix of letters & numbers') }}</p>
                    </div>
                </div>
            </div>

            @if($hasPlans)
                <button type="button" id="reg-next-step-btn" class="crm-btn crm-btn-primary crm-btn-lg crm-btn-block">
                    <span>{{ __('Continue to Subscription') }}</span>
                    <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
                </button>
            @else
                <button type="submit" class="crm-btn crm-btn-primary crm-btn-lg crm-btn-block">
                    <span>{{ __('Register & Provision') }}</span>
                    <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
                </button>
            @endif
        </div>

        {{-- Step 2: Subscription & Coupon Redemption --}}
        @if($hasPlans)
            <div id="reg-step-2" @if($initialStep !== 2) style="display:none;" @endif>
                <div class="reg-step2-layout">
                    <div class="reg-panel" style="margin-bottom:0;">
                        <div class="reg-panel-title">
                            <span class="material-symbols-outlined" style="font-size:16px;">workspace_premium</span>
                            <span>{{ __('Select Subscription Plan') }}</span>
                        </div>

                        <div style="display:grid; gap:0.75rem;">
                            @foreach($plans as $plan)
                                @php
                                    $isSelected = (string) old('subscription_plan_id') === (string) $plan->id
                                        || (old('subscription_plan_id') === null && (($selectedPlanCode ?? null) === $plan->code || ($loop->first && empty($selectedPlanCode))));
                                @endphp
                                <label class="reg-plan-card">
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
                                        <div>
                                            <span style="font-weight:700; font-size:0.98rem;">{{ $plan->name }}</span>
                                            <div class="crm-muted" style="font-size:0.78rem; margin-top:0.15rem;">
                                                {{ $plan->lead_limit }} {{ __('leads/mo') }} · {{ $plan->user_limit }} {{ __('users included') }}
                                            </div>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:0.65rem;">
                                            <div style="text-align:right;">
                                                <strong style="font-size:1.15rem;">{{ $plan->formattedPrice() }}</strong>
                                                <span class="crm-muted" style="font-size:0.76rem; display:block;">/ {{ $plan->billing_interval }}</span>
                                            </div>
                                            <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}"
                                                data-plan-price="{{ $plan->price_paise }}"
                                                data-plan-name="{{ $plan->name }}"
                                                {{ $isSelected ? 'checked' : '' }} required>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="reg-panel" style="margin-bottom:0;">
                        <div class="reg-panel-title">
                            <span class="material-symbols-outlined" style="font-size:16px;">local_offer</span>
                            <span>{{ __('Coupon & Summary') }}</span>
                        </div>

                        <div class="crm-form-group" style="margin-bottom:0.85rem;">
                            <label class="crm-label" for="coupon_code">{{ __('Coupon Code (Optional)') }}</label>
                            <div style="display:flex; gap:0.5rem;">
                                <input id="coupon_code" class="crm-input" type="text" name="coupon_code"
                                    value="{{ old('coupon_code') }}" placeholder="e.g. WELCOME20" style="text-transform:uppercase;">
                                <button type="button" id="reg-apply-coupon-btn" class="crm-btn crm-btn-secondary">
                                    {{ __('Apply') }}
                                </button>
                            </div>
                            <div id="reg-coupon-feedback" class="crm-muted" style="font-size:0.8rem; margin-top:0.35rem;"></div>
                        </div>

                        <div id="reg-pricing-summary" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem 0.95rem; font-size:0.85rem;">
                            <div style="display:flex; justify-content:space-between; gap:0.5rem;">
                                <span>{{ __('Selected Plan') }}</span>
                                <strong id="reg-summary-plan">-</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:0.5rem; margin-top:0.3rem;">
                                <span>{{ __('Discount') }}</span>
                                <strong id="reg-summary-discount" style="color:#15803d;">₹0</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:0.5rem; margin-top:0.45rem; padding-top:0.45rem; border-top:1px dashed #cbd5e1; font-size:0.95rem;">
                                <span>{{ __('Due Today (Razorpay Mandate)') }}</span>
                                <strong id="reg-summary-total">-</strong>
                            </div>
                            <div class="crm-muted" style="font-size:0.74rem; margin-top:0.45rem; line-height:1.35;">
                                {{ __('Recurring autopay via Razorpay Subscriptions. You can cancel your subscription anytime.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:1rem;">
                    <button type="button" id="reg-prev-step-btn" class="crm-btn crm-btn-secondary crm-btn-lg" style="flex:0 0 auto;">
                        <span class="material-symbols-outlined" style="font-size: 19px;">arrow_back</span>
                        <span>{{ __('Back') }}</span>
                    </button>
                    <button type="submit" class="crm-btn crm-btn-primary crm-btn-lg" style="flex:1; min-width:220px;">
                        <span>{{ __('Register & Proceed to Payment') }}</span>
                        <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
                    </button>
                </div>
            </div>
        @endif
    </form>

    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem; margin-top:1rem; padding-top:0.9rem; border-top:1px solid var(--crm-border, #e2e8f0);">
        <div style="font-size:0.86rem;">
            <span class="crm-muted">{{ __('Already registered?') }}</span>
            <a href="{{ route('login') }}" style="font-weight: 600; margin-left: 0.25rem;">{{ __('Sign in') }}</a>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <span class="crm-muted" style="font-size:0.78rem;">{{ __('Or sign up with') }}</span>
            <button type="button" class="crm-social-btn" style="padding:0.4rem 0.75rem; font-size:0.8rem;" title="Google Sign-Up (Coming Soon)">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z" fill="#4285F4" />
                    <path d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z" fill="#34A853" />
                    <path d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z" fill="#FBBC05" />
                    <path d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z" fill="#EA4335" />
                </svg>
                <span>Google</span>
            </button>
            <button type="button" class="crm-social-btn" style="padding:0.4rem 0.75rem; font-size:0.8rem;" title="Apple Sign-Up (Coming Soon)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.42c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.57.65-1.07 1.71-.94 2.73 1 .08 2.02-.48 2.64-1.23z" />
                </svg>
                <span>Apple</span>
            </button>
        </div>
    </div>

    @if($hasPlans)
        <script>
            (function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const step1El = document.getElementById('reg-step-1');
                const step2El = document.getElementById('reg-step-2');
                const tab1El = document.getElementById('reg-step-tab-1');
                const tab2El = document.getElementById('reg-step-tab-2');
                const step1Error = document.getElementById('reg-step1-error');
                const nextBtn = document.getElementById('reg-next-step-btn');
                const prevBtn = document.getElementById('reg-prev-step-btn');

                const planRadios = document.querySelectorAll('input[name="subscription_plan_id"]');
                const couponInput = document.getElementById('coupon_code');
                const applyBtn = document.getElementById('reg-apply-coupon-btn');
                const feedback = document.getElementById('reg-coupon-feedback');
                const summaryPlan = document.getElementById('reg-summary-plan');
                const summaryDiscount = document.getElementById('reg-summary-discount');
                const summaryTotal = document.getElementById('reg-summary-total');

                let currentStep = {{ $initialStep }};

                function renderStepTabs(step) {
                    const tabs = [
                        { el: tab1El, num: 1 },
                        { el: tab2El, num: 2 },
                    ];
                    tabs.forEach(({ el, num }) => {
                        if (!el) return;
                        const badge = el.querySelector('.reg-step-num');
                        const isActive = num === step;
                        const isCompleted = num < step;
                        el.style.borderColor = isActive ? '#2563eb' : (isCompleted ? '#16a34a' : '#cbd5e1');
                        el.style.background = isActive ? '#eff6ff' : '#fff';
                        if (badge) {
                            badge.style.background = isActive ? '#2563eb' : (isCompleted ? '#16a34a' : '#e2e8f0');
                            badge.style.color = (isActive || isCompleted) ? '#fff' : '#475569';
                        }
                    });
                }

                function goToStep(step) {
                    currentStep = step;
                    step1El.style.display = step === 1 ? '' : 'none';
                    step2El.style.display = step === 2 ? '' : 'none';
                    renderStepTabs(step);
                }

                function validateStep1() {
                    step1Error.style.display = 'none';
                    step1Error.textContent = '';

                    const step1Inputs = step1El.querySelectorAll('input, select, textarea');
                    for (const input of step1Inputs) {
                        if (!input.checkValidity()) {
                            input.reportValidity();
                            return false;
                        }
                    }

                    const pw = document.getElementById('password')?.value || '';
                    const pwConfirm = document.getElementById('password_confirmation')?.value || '';
                    if (pw.length < 8) {
                        step1Error.textContent = '{{ __('Password must be at least 8 characters.') }}';
                        step1Error.style.display = 'block';
                        return false;
                    }
                    if (pw !== pwConfirm) {
                        step1Error.textContent = '{{ __('Password and confirmation do not match.') }}';
                        step1Error.style.display = 'block';
                        return false;
                    }

                    return true;
                }

                nextBtn?.addEventListener('click', () => {
                    if (validateStep1()) {
                        goToStep(2);
                    }
                });

                prevBtn?.addEventListener('click', () => goToStep(1));
                tab1El?.addEventListener('click', () => goToStep(1));
                tab2El?.addEventListener('click', () => {
                    if (currentStep === 2 || validateStep1()) {
                        goToStep(2);
                    }
                });

                function formatRupees(paise) {
                    return '₹' + (paise / 100).toLocaleString('en-IN');
                }

                function updateBaseSummary() {
                    const checked = document.querySelector('input[name="subscription_plan_id"]:checked');
                    if (!checked) return;
                    const price = parseInt(checked.dataset.planPrice || '0', 10);
                    summaryPlan.textContent = `${checked.dataset.planName} (${formatRupees(price)}/mo)`;
                    summaryDiscount.textContent = '₹0';
                    summaryTotal.textContent = formatRupees(price);
                }

                async function applyCoupon() {
                    const checked = document.querySelector('input[name="subscription_plan_id"]:checked');
                    if (!checked || !couponInput.value.trim()) {
                        updateBaseSummary();
                        feedback.textContent = '';
                        return;
                    }

                    const res = await fetch('{{ route('ajax.coupons.validate') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            coupon_code: couponInput.value,
                            subscription_plan_id: checked.value,
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        feedback.style.color = '#15803d';
                        feedback.textContent = data.message;
                        summaryDiscount.textContent = '-' + formatRupees(data.discount_paise);
                        summaryTotal.textContent = formatRupees(data.initial_charge_paise) + (data.is_mandate_only ? ' (₹1 Mandate)' : '');
                    } else {
                        feedback.style.color = '#b91c1c';
                        feedback.textContent = data.errors?.coupon_code?.[0] || data.message || 'Invalid coupon.';
                        updateBaseSummary();
                    }
                }

                planRadios.forEach((r) => r.addEventListener('change', () => {
                    updateBaseSummary();
                    if (couponInput?.value.trim()) applyCoupon();
                }));
                applyBtn?.addEventListener('click', applyCoupon);

                renderStepTabs(currentStep);
                updateBaseSummary();
                if (couponInput?.value.trim()) {
                    applyCoupon();
                }
            })();
        </script>
    @endif
@endsection
