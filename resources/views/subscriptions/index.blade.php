@extends('layouts.crm')

@section('title', __('Subscription & Billing') . ' — CRM')

@section('content')
    <div class="crm-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h1 class="crm-page-title">{{ __('Subscription & Billing') }}</h1>
            <p class="crm-muted" style="margin:0;">
                {{ __('View your organization plan, usage limits, and Razorpay recurring mandate status. You can cancel your subscription anytime.') }}
            </p>
        </div>
        @if($subscription)
            <span class="crm-badge" style="font-size:0.85rem; padding:0.35rem 0.75rem;">
                {{ strtoupper(str_replace('_', ' ', $subscription->status)) }}
            </span>
        @endif
    </div>

    @if(session('warning'))
        <div style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.85rem 1rem; border-radius:8px; margin-bottom:1rem;">
            {{ session('warning') }}
        </div>
    @endif

    @if($subscription && $subscription->isPendingPayment())
        <div style="background:#eff6ff; border:1px solid #93c5fd; color:#1e40af; padding:1rem; border-radius:8px; margin-bottom:1.25rem;">
            <strong>{{ __('Payment Pending') }}:</strong>
            {{ __('Complete your Razorpay subscription authorization to activate your workspace.') }}
            @if($subscription->is_mandate_only)
                <span>{{ __('Your 100% coupon reduces the initial charge to ₹1 for mandate authorization; future renewals will be billed at the standard plan rate.') }}</span>
            @endif
        </div>
    @endif

    {{-- Current Plan & Usage Summary (Visible to ALL roles) --}}
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
        <div class="crm-card" style="padding:1.25rem;">
            <div class="crm-muted" style="font-size:0.8rem; text-transform:uppercase; font-weight:600;">{{ __('Current Plan') }}</div>
            <div style="font-size:1.35rem; font-weight:700; margin:0.35rem 0;">
                {{ $subscription?->plan?->name ?? __('No Active Plan') }}
            </div>
            @if($subscription)
                <div class="crm-muted" style="font-size:0.85rem;">
                    {{ __('Base Price') }}: ₹{{ number_format($subscription->base_amount_paise / 100) }}/{{ $subscription->plan?->billing_interval ?? 'month' }}
                    @if($subscription->coupon)
                        · {{ __('Coupon') }}: <strong>{{ $subscription->coupon->code }}</strong>
                    @endif
                </div>
                @if($subscription->current_period_end)
                    <div class="crm-muted" style="font-size:0.82rem; margin-top:0.25rem;">
                        {{ __('Current Period Ends') }}: {{ $subscription->current_period_end->format('d M Y') }}
                    </div>
                @endif
            @endif
            <div class="crm-muted" style="font-size:0.78rem; margin-top:0.65rem;">
                {{ __('You can cancel your subscription anytime.') }}
            </div>
        </div>

        <div class="crm-card" style="padding:1.25rem;">
            <div class="crm-muted" style="font-size:0.8rem; text-transform:uppercase; font-weight:600;">{{ __('Monthly Lead Usage') }}</div>
            <div style="font-size:1.35rem; font-weight:700; margin:0.35rem 0;">
                {{ $leadsThisMonth }} / {{ $subscription?->plan?->lead_limit ?? '—' }}
            </div>
            <div class="crm-muted" style="font-size:0.82rem;">{{ __('Leads created this calendar month') }}</div>
        </div>

        <div class="crm-card" style="padding:1.25rem;">
            <div class="crm-muted" style="font-size:0.8rem; text-transform:uppercase; font-weight:600;">{{ __('Team Seats') }}</div>
            <div style="font-size:1.35rem; font-weight:700; margin:0.35rem 0;">
                {{ $usersCount }} / {{ $subscription?->plan?->user_limit ?? '—' }}
            </div>
            <div class="crm-muted" style="font-size:0.82rem;">{{ __('Active organization members') }}</div>
        </div>
    </div>

    {{-- Plan Selection & Razorpay Checkout (Org Admin & Superadmin) --}}
    @if($canManageBilling)
        <div class="crm-card" style="padding:1.25rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.1rem; font-weight:700; margin:0 0 0.75rem;">{{ __('Choose or Upgrade Plan') }}</h2>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; margin-bottom:1rem;">
                @foreach($plans as $plan)
                    @php
                        $selected = ($subscription?->subscription_plan_id === $plan->id) || ($subscription === null && $loop->first);
                    @endphp
                    <label style="border:1px solid #cbd5e1; border-radius:10px; padding:1rem; cursor:pointer; display:block;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>{{ $plan->name }}</strong>
                            <input type="radio" name="billing_plan_id" value="{{ $plan->id }}" {{ $selected ? 'checked' : '' }}>
                        </div>
                        <div style="font-size:1.3rem; font-weight:700; margin:0.35rem 0;">
                            {{ $plan->formattedPrice() }} <span class="crm-muted" style="font-size:0.82rem; font-weight:400;">/ {{ $plan->billing_interval }}</span>
                        </div>
                        <p class="crm-muted" style="font-size:0.82rem; margin:0 0 0.5rem;">{{ $plan->description }}</p>
                        <div style="font-size:0.8rem;">
                            {{ $plan->lead_limit }} {{ __('leads/mo') }} · {{ $plan->user_limit }} {{ __('users') }} · {{ __('Extra seat') }}: {{ $plan->formattedExtraUserPrice() }}/mo
                        </div>
                    </label>
                @endforeach
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; margin-bottom:1rem;">
                <div style="flex:1; min-width:220px;">
                    <label class="crm-label" for="billing_coupon_code">{{ __('Coupon Code (Optional)') }}</label>
                    <input id="billing_coupon_code" class="crm-input" type="text"
                        value="{{ $subscription?->coupon?->code ?? '' }}" placeholder="e.g. WELCOME20" style="text-transform:uppercase;">
                </div>
                <button type="button" id="billing-apply-coupon-btn" class="crm-btn crm-btn-secondary">{{ __('Validate Coupon') }}</button>
                <button type="button" id="billing-pay-btn" class="crm-btn crm-btn-primary">
                    {{ $subscription?->isPendingPayment() ? __('Complete Razorpay Payment') : __('Subscribe / Switch Plan via Razorpay') }}
                </button>
                @if($subscription && ($subscription->isOperational() || $subscription->isPendingPayment()) && !$subscription->cancel_at_period_end)
                    <button type="button" id="billing-cancel-btn" class="crm-btn crm-btn-danger">{{ __('Cancel Subscription') }}</button>
                @endif
            </div>

            <div id="billing-feedback" class="crm-muted" style="font-size:0.85rem;"></div>
        </div>

        {{-- Organization Payment & Webhook Audit Logs --}}
        <div class="crm-card" style="padding:1.25rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.1rem; font-weight:700; margin:0 0 0.75rem;">{{ __('Payment & Webhook Audit Logs') }}</h2>
            <div style="overflow-x:auto;">
                <table class="crm-table" style="width:100%; font-size:0.82rem;">
                    <thead>
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Direction') }}</th>
                            <th>{{ __('Event / Endpoint') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Razorpay Sub / Pay ID') }}</th>
                            <th>{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orgLogs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $log->direction }}</td>
                                <td>{{ $log->event_or_endpoint }}</td>
                                <td><strong>{{ strtoupper($log->status) }}</strong> ({{ $log->http_status ?? '-' }})</td>
                                <td>{{ $log->razorpay_subscription_id ?? '-' }} / {{ $log->razorpay_payment_id ?? '-' }}</td>
                                <td>{{ $log->error_message ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="crm-muted">{{ __('No payment gateway logs recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Superadmin Controls --}}
    @if($isSuperadmin)
        <div class="crm-card" style="padding:1.25rem;">
            <div class="crm-between" style="margin-bottom:0.5rem;">
                <h2 style="font-size:1.1rem; font-weight:700; margin:0;">{{ __('Superadmin: Active Coupons & Global Gateway Logs') }}</h2>
                <a href="{{ route('coupons.index') }}" class="crm-btn crm-btn-secondary crm-btn-sm">{{ __('Manage Coupons') }}</a>
            </div>
            <p class="crm-muted" style="font-size:0.82rem; margin:0;">
                {{ __('Total Coupons') }}: {{ $coupons->count() }} · {{ __('Recent Global Gateway Events') }}: {{ $globalLogs->count() }}
            </p>
        </div>
    @endif

    @if($canManageBilling)
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            (function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const feedback = document.getElementById('billing-feedback');
                const couponInput = document.getElementById('billing_coupon_code');
                const applyBtn = document.getElementById('billing-apply-coupon-btn');
                const payBtn = document.getElementById('billing-pay-btn');
                const cancelBtn = document.getElementById('billing-cancel-btn');

                function selectedPlanId() {
                    return document.querySelector('input[name="billing_plan_id"]:checked')?.value || '';
                }

                const defaultPayLabel = payBtn?.textContent?.trim() || 'Complete Razorpay Payment';
                let isCheckoutInFlight = false;

                function setPayProcessing(processing, statusText) {
                    isCheckoutInFlight = processing;
                    if (payBtn) {
                        payBtn.disabled = processing;
                        payBtn.setAttribute('aria-busy', processing ? 'true' : 'false');
                        payBtn.textContent = processing ? (statusText || '{{ __('Processing...') }}') : defaultPayLabel;
                    }
                    if (applyBtn) {
                        applyBtn.disabled = processing;
                    }
                    if (statusText && feedback) {
                        feedback.textContent = statusText;
                    }
                }

                applyBtn?.addEventListener('click', async () => {
                    if (isCheckoutInFlight) return;
                    applyBtn.disabled = true;
                    feedback.textContent = '{{ __('Validating coupon...') }}';
                    try {
                        const res = await fetch('{{ route('ajax.coupons.validate') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                coupon_code: couponInput.value,
                                subscription_plan_id: selectedPlanId(),
                            }),
                        });
                        const data = await res.json();
                        feedback.textContent = res.ok
                            ? `${data.message} Initial charge: ₹${(data.initial_charge_paise / 100).toFixed(2)}.`
                            : (data.message || '{{ __('Invalid coupon.') }}');
                    } catch (err) {
                        feedback.textContent = '{{ __('Unable to validate coupon right now. Please try again.') }}';
                    } finally {
                        applyBtn.disabled = isCheckoutInFlight;
                    }
                });

                async function startCheckout() {
                    if (isCheckoutInFlight) {
                        return;
                    }

                    if (typeof Razorpay === 'undefined') {
                        feedback.textContent = '{{ __('Razorpay checkout failed to load. Please refresh the page and try again.') }}';
                        return;
                    }

                    setPayProcessing(true, '{{ __('Creating Razorpay checkout session, please wait...') }}');

                    try {
                        const res = await fetch('{{ route('ajax.subscription.checkout') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                subscription_plan_id: selectedPlanId(),
                                coupon_code: couponInput.value || null,
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            setPayProcessing(false);
                            feedback.textContent = data.message || '{{ __('Unable to start Razorpay checkout.') }}';
                            return;
                        }

                        setPayProcessing(true, '{{ __('Opening Razorpay checkout...') }}');

                        const rzp = new Razorpay({
                            key: data.key_id,
                            subscription_id: data.subscription_id,
                            name: data.name,
                            description: data.description,
                            prefill: data.prefill,
                            modal: {
                                ondismiss: function () {
                                    setPayProcessing(false);
                                    feedback.textContent = '{{ __('Payment checkout was cancelled.') }}';
                                },
                            },
                            handler: async function (response) {
                                setPayProcessing(true, '{{ __('Verifying Razorpay payment, please wait...') }}');
                                try {
                                    const verifyRes = await fetch('{{ route('ajax.subscription.verify') }}', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                        body: JSON.stringify({
                                            local_subscription_id: data.local_subscription_id,
                                            razorpay_payment_id: response.razorpay_payment_id,
                                            razorpay_subscription_id: response.razorpay_subscription_id,
                                            razorpay_signature: response.razorpay_signature,
                                        }),
                                    });
                                    const verifyData = await verifyRes.json();
                                    if (verifyRes.ok) {
                                        feedback.textContent = '{{ __('Payment verified! Redirecting...') }}';
                                        window.location.href = verifyData.redirect_url || '{{ route('dashboard') }}';
                                    } else {
                                        setPayProcessing(false);
                                        feedback.textContent = verifyData.message || '{{ __('Payment verification failed.') }}';
                                    }
                                } catch (err) {
                                    setPayProcessing(false);
                                    feedback.textContent = '{{ __('Network error while verifying payment. Please do not retry immediately; check your payment status.') }}';
                                }
                            },
                        });
                        rzp.on('payment.failed', function (resp) {
                            setPayProcessing(false);
                            feedback.textContent = resp?.error?.description || '{{ __('Payment failed. Please try again.') }}';
                        });
                        rzp.open();
                    } catch (err) {
                        setPayProcessing(false);
                        feedback.textContent = '{{ __('Network error while starting Razorpay checkout. Please try again.') }}';
                    }
                }

                payBtn?.addEventListener('click', startCheckout);

                cancelBtn?.addEventListener('click', async () => {
                    if (isCheckoutInFlight) return;
                    if (!confirm('{{ __('Cancel your recurring subscription at the end of the current billing period?') }}')) return;
                    cancelBtn.disabled = true;
                    feedback.textContent = '{{ __('Cancelling subscription...') }}';
                    try {
                        const res = await fetch('{{ route('ajax.subscription.cancel') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ cancel_at_cycle_end: true }),
                        });
                        const data = await res.json();
                        feedback.textContent = data.message || '{{ __('Updated.') }}';
                        if (res.ok) {
                            window.location.reload();
                        } else {
                            cancelBtn.disabled = false;
                        }
                    } catch (err) {
                        cancelBtn.disabled = false;
                        feedback.textContent = '{{ __('Unable to cancel subscription right now. Please try again.') }}';
                    }
                });

                @if(!empty($autoOpenCheckout) && $subscription && $subscription->isPendingPayment())
                    // Auto-trigger checkout prompt after registration if Razorpay key is configured.
                    if (typeof Razorpay !== 'undefined') {
                        startCheckout();
                    }
                @endif
            })();
        </script>
    @endif
@endsection
