@extends('layouts.crm')

@section('title', __('Coupons') . ' — CRM')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">{{ __('Coupons') }}</h1>
            <p class="crm-muted">{{ __('Create and manage subscription discount coupons, plan applicability, and redemption limits.') }}</p>
        </div>
        <button type="button" id="new-coupon-btn" class="crm-btn crm-btn-primary">+ {{ __('New Coupon') }}</button>
    </div>

    <div class="crm-card" style="padding:1rem; margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
            <input id="coupon-search" class="crm-input" type="text" placeholder="{{ __('Search by code or description...') }}" style="max-width:280px;">
            <select id="coupon-status-filter" class="crm-input" style="max-width:170px;">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="active">{{ __('Active Only') }}</option>
                <option value="inactive">{{ __('Inactive Only') }}</option>
            </select>
            <div id="coupon-list-feedback" class="crm-muted" style="font-size:0.82rem; margin-left:auto;"></div>
        </div>
    </div>

    <div class="crm-card" style="overflow-x:auto;">
        <table class="crm-table" style="width:100%;">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Discount') }}</th>
                    <th>{{ __('Applicable Plans') }}</th>
                    <th>{{ __('Validity Window') }}</th>
                    <th>{{ __('Redemptions') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="text-align:right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody id="coupons-tbody">
                <tr>
                    <td colspan="7" class="crm-muted">{{ __('Loading coupons...') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="coupon-modal" class="crm-modal-backdrop">
        <div class="crm-modal" style="max-width:600px;">
            <div class="crm-between crm-mb-2">
                <h3 id="coupon-modal-title">{{ __('Create Coupon') }}</h3>
                <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" data-crm-modal-close>{{ __('Close') }}</button>
            </div>
            <div id="coupon-form-errors" style="color:#b91c1c; font-size:0.84rem; margin-bottom:0.75rem;"></div>
            <form id="coupon-form">
                <input type="hidden" id="coupon_id" value="">

                <div class="crm-form-row">
                    <div class="crm-field">
                        <label for="coupon_code_input">{{ __('Coupon Code') }}</label>
                        <input id="coupon_code_input" name="code" type="text" maxlength="64" placeholder="e.g. WELCOME20" style="text-transform:uppercase;" required>
                    </div>
                    <div class="crm-field">
                        <label for="coupon_discount_type">{{ __('Discount Type') }}</label>
                        <select id="coupon_discount_type" name="discount_type">
                            <option value="percentage">{{ __('Percentage (%)') }}</option>
                            <option value="fixed">{{ __('Fixed Amount (₹)') }}</option>
                        </select>
                    </div>
                </div>

                <div class="crm-form-row">
                    <div class="crm-field">
                        <label id="coupon_discount_value_label" for="coupon_discount_value">{{ __('Discount (%)') }}</label>
                        <input id="coupon_discount_value" name="discount_value" type="number" min="1" max="100" step="1" required>
                    </div>
                    <div class="crm-field">
                        <label for="coupon_max_discount_rupees">{{ __('Max Discount Cap (₹, Optional)') }}</label>
                        <input id="coupon_max_discount_rupees" name="max_discount_rupees" type="number" min="1" step="1" placeholder="e.g. 1000">
                    </div>
                </div>

                <div class="crm-field">
                    <label for="coupon_description">{{ __('Description (Optional)') }}</label>
                    <input id="coupon_description" name="description" type="text" maxlength="255" placeholder="e.g. 20% off your first billing cycle">
                </div>

                <div class="crm-form-row">
                    <div class="crm-field">
                        <label for="coupon_valid_from">{{ __('Valid From (Optional)') }}</label>
                        <input id="coupon_valid_from" name="valid_from" type="datetime-local">
                    </div>
                    <div class="crm-field">
                        <label for="coupon_valid_until">{{ __('Valid Until (Optional)') }}</label>
                        <input id="coupon_valid_until" name="valid_until" type="datetime-local">
                    </div>
                </div>

                <div class="crm-form-row">
                    <div class="crm-field">
                        <label for="coupon_max_redemptions">{{ __('Max Redemptions (Optional)') }}</label>
                        <input id="coupon_max_redemptions" name="max_redemptions" type="number" min="1" step="1" placeholder="{{ __('Unlimited') }}">
                    </div>
                    <div class="crm-field" style="display:flex; align-items:center; gap:0.5rem; padding-top:1.6rem;">
                        <input id="coupon_is_active" name="is_active" type="checkbox" checked style="width:auto;">
                        <label for="coupon_is_active" style="margin:0;">{{ __('Coupon is Active') }}</label>
                    </div>
                </div>

                <div class="crm-field">
                    <label>{{ __('Applicable Plans (Leave unchecked for all plans)') }}</label>
                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:0.35rem;">
                        @foreach($plans as $plan)
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-weight:500; cursor:pointer;">
                                <input type="checkbox" name="applicable_plan_codes[]" value="{{ $plan->code }}" class="coupon-plan-checkbox" style="width:auto;">
                                <span>{{ $plan->name }} ({{ $plan->code }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                    <button type="button" class="crm-btn crm-btn-secondary" data-crm-modal-close>{{ __('Cancel') }}</button>
                    <button type="submit" id="coupon-save-btn" class="crm-btn crm-btn-primary">{{ __('Save Coupon') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const tbody = document.getElementById('coupons-tbody');
            const searchInput = document.getElementById('coupon-search');
            const statusFilter = document.getElementById('coupon-status-filter');
            const listFeedback = document.getElementById('coupon-list-feedback');
            const modalTitle = document.getElementById('coupon-modal-title');
            const form = document.getElementById('coupon-form');
            const formErrors = document.getElementById('coupon-form-errors');

            const idField = document.getElementById('coupon_id');
            const codeField = document.getElementById('coupon_code_input');
            const typeField = document.getElementById('coupon_discount_type');
            const valueLabel = document.getElementById('coupon_discount_value_label');
            const valueField = document.getElementById('coupon_discount_value');
            const maxCapField = document.getElementById('coupon_max_discount_rupees');
            const descField = document.getElementById('coupon_description');
            const validFromField = document.getElementById('coupon_valid_from');
            const validUntilField = document.getElementById('coupon_valid_until');
            const maxRedemptionsField = document.getElementById('coupon_max_redemptions');
            const isActiveField = document.getElementById('coupon_is_active');
            const planCheckboxes = document.querySelectorAll('.coupon-plan-checkbox');

            let cachedCoupons = [];

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function (c) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
                });
            }

            function syncDiscountInputAttributes() {
                if (typeField.value === 'percentage') {
                    valueLabel.textContent = '{{ __('Discount (%)') }}';
                    valueField.max = '100';
                    valueField.placeholder = 'e.g. 20';
                } else {
                    valueLabel.textContent = '{{ __('Discount Amount (₹)') }}';
                    valueField.removeAttribute('max');
                    valueField.placeholder = 'e.g. 500';
                }
            }

            function toLocalInputValue(isoString) {
                if (!isoString) return '';
                const d = new Date(isoString);
                if (Number.isNaN(d.getTime())) return '';
                const pad = (n) => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }

            function formatRupees(paise) {
                return '₹' + (paise / 100).toLocaleString('en-IN');
            }

            function resetForm() {
                formErrors.textContent = '';
                idField.value = '';
                codeField.value = '';
                typeField.value = 'percentage';
                valueField.value = '';
                maxCapField.value = '';
                descField.value = '';
                validFromField.value = '';
                validUntilField.value = '';
                maxRedemptionsField.value = '';
                isActiveField.checked = true;
                planCheckboxes.forEach((cb) => { cb.checked = false; });
                syncDiscountInputAttributes();
            }

            function openCreateModal() {
                resetForm();
                modalTitle.textContent = '{{ __('Create Coupon') }}';
                CrmUi.openModal('#coupon-modal');
            }

            function openEditModal(coupon) {
                resetForm();
                modalTitle.textContent = '{{ __('Edit Coupon') }} — ' + coupon.code;
                idField.value = coupon.id;
                codeField.value = coupon.code;
                typeField.value = coupon.discount_type;
                valueField.value = coupon.discount_type === 'fixed'
                    ? Math.round(coupon.discount_value / 100)
                    : coupon.discount_value;
                maxCapField.value = coupon.max_discount_paise ? Math.round(coupon.max_discount_paise / 100) : '';
                descField.value = coupon.description || '';
                validFromField.value = toLocalInputValue(coupon.valid_from);
                validUntilField.value = toLocalInputValue(coupon.valid_until);
                maxRedemptionsField.value = coupon.max_redemptions || '';
                isActiveField.checked = Boolean(coupon.is_active);
                const selectedPlans = Array.isArray(coupon.applicable_plan_codes) ? coupon.applicable_plan_codes : [];
                planCheckboxes.forEach((cb) => {
                    cb.checked = selectedPlans.includes(cb.value);
                });
                syncDiscountInputAttributes();
                CrmUi.openModal('#coupon-modal');
            }

            function renderCoupons(rows) {
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="crm-muted">{{ __('No coupons found.') }}</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map((c) => {
                    const discountText = c.discount_type === 'percentage'
                        ? `${c.discount_value}%`
                        : formatRupees(c.discount_value);
                    const capText = c.max_discount_paise
                        ? `<div class="crm-muted" style="font-size:0.75rem;">Max ${formatRupees(c.max_discount_paise)}</div>`
                        : '';
                    const plans = Array.isArray(c.applicable_plan_codes) && c.applicable_plan_codes.length
                        ? c.applicable_plan_codes.map((p) => `<span class="crm-badge" style="margin-right:0.25rem;">${escapeHtml(p)}</span>`).join('')
                        : '<span class="crm-muted">{{ __('All Plans') }}</span>';
                    const fromStr = c.valid_from ? new Date(c.valid_from).toLocaleDateString() : '—';
                    const untilStr = c.valid_until ? new Date(c.valid_until).toLocaleDateString() : '—';
                    const validity = (!c.valid_from && !c.valid_until) ? '{{ __('Always') }}' : `${fromStr} → ${untilStr}`;
                    const redemptions = `${c.redemptions_count ?? 0} / ${c.max_redemptions ?? '∞'}`;
                    const statusBadge = c.is_active
                        ? '<span class="crm-badge" style="background:#dcfce7; color:#166534;">{{ __('Active') }}</span>'
                        : '<span class="crm-badge" style="background:#f1f5f9; color:#475569;">{{ __('Inactive') }}</span>';

                    const deleteBtn = (c.redemptions_count ?? 0) === 0
                        ? `<button type="button" class="crm-btn crm-btn-danger crm-btn-sm coupon-delete-btn" data-id="${c.id}">{{ __('Delete') }}</button>`
                        : '';

                    return `<tr>
                        <td>
                            <strong>${escapeHtml(c.code)}</strong>
                            ${c.description ? `<div class="crm-muted" style="font-size:0.76rem;">${escapeHtml(c.description)}</div>` : ''}
                        </td>
                        <td><strong>${discountText}</strong>${capText}</td>
                        <td>${plans}</td>
                        <td style="font-size:0.82rem;">${validity}</td>
                        <td>${redemptions}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align:right; white-space:nowrap;">
                            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm coupon-edit-btn" data-id="${c.id}">{{ __('Edit') }}</button>
                            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm coupon-toggle-btn" data-id="${c.id}">${c.is_active ? '{{ __('Disable') }}' : '{{ __('Enable') }}'}</button>
                            ${deleteBtn}
                        </td>
                    </tr>`;
                }).join('');
            }

            async function loadCoupons() {
                const params = new URLSearchParams({
                    q: searchInput.value.trim(),
                    status: statusFilter.value,
                });
                const res = await fetch(`{{ route('ajax.coupons.index') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                cachedCoupons = json.data || [];
                renderCoupons(cachedCoupons);
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                formErrors.textContent = '';

                const isEdit = Boolean(idField.value);
                const discountType = typeField.value;
                const rawVal = parseInt(valueField.value || '0', 10);
                const discountValue = discountType === 'fixed' ? rawVal * 100 : rawVal;
                const rawCap = maxCapField.value ? parseInt(maxCapField.value, 10) : null;
                const selectedPlans = Array.from(planCheckboxes).filter((cb) => cb.checked).map((cb) => cb.value);

                const payload = {
                    code: codeField.value.trim().toUpperCase(),
                    description: descField.value.trim() || null,
                    discount_type: discountType,
                    discount_value: discountValue,
                    max_discount_paise: rawCap ? rawCap * 100 : null,
                    valid_from: validFromField.value || null,
                    valid_until: validUntilField.value || null,
                    max_redemptions: maxRedemptionsField.value ? parseInt(maxRedemptionsField.value, 10) : null,
                    applicable_plan_codes: selectedPlans.length ? selectedPlans : null,
                    is_active: isActiveField.checked,
                };

                const url = isEdit ? `/ajax/coupons/${idField.value}` : '{{ route('ajax.coupons.store') }}';
                const method = isEdit ? 'PATCH' : 'POST';

                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    const firstErr = data.errors ? Object.values(data.errors).flat()[0] : null;
                    formErrors.textContent = firstErr || data.message || '{{ __('Unable to save coupon.') }}';
                    return;
                }

                CrmUi.closeModal('#coupon-modal');
                listFeedback.textContent = data.message;
                await loadCoupons();
            });

            tbody.addEventListener('click', async (e) => {
                const editBtn = e.target.closest('.coupon-edit-btn');
                if (editBtn) {
                    const coupon = cachedCoupons.find((c) => String(c.id) === editBtn.dataset.id);
                    if (coupon) openEditModal(coupon);
                    return;
                }

                const toggleBtn = e.target.closest('.coupon-toggle-btn');
                if (toggleBtn) {
                    const coupon = cachedCoupons.find((c) => String(c.id) === toggleBtn.dataset.id);
                    if (!coupon) return;
                    const res = await fetch(`/ajax/coupons/${coupon.id}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            code: coupon.code,
                            description: coupon.description,
                            discount_type: coupon.discount_type,
                            discount_value: coupon.discount_value,
                            max_discount_paise: coupon.max_discount_paise,
                            valid_from: coupon.valid_from,
                            valid_until: coupon.valid_until,
                            max_redemptions: coupon.max_redemptions,
                            applicable_plan_codes: coupon.applicable_plan_codes,
                            is_active: !coupon.is_active,
                        }),
                    });
                    const data = await res.json();
                    listFeedback.textContent = data.message || '';
                    await loadCoupons();
                    return;
                }

                const delBtn = e.target.closest('.coupon-delete-btn');
                if (delBtn) {
                    if (!confirm('{{ __('Delete this coupon?') }}')) return;
                    const res = await fetch(`/ajax/coupons/${delBtn.dataset.id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    listFeedback.textContent = data.message || '';
                    await loadCoupons();
                }
            });

            document.getElementById('new-coupon-btn').addEventListener('click', openCreateModal);
            typeField.addEventListener('change', syncDiscountInputAttributes);
            searchInput.addEventListener('input', loadCoupons);
            statusFilter.addEventListener('change', loadCoupons);

            loadCoupons();
        })();
    </script>
@endpush
