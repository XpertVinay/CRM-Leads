<!DOCTYPE html>
<html lang="{{ config('localization.supported.'.app()->getLocale().'.html_lang', 'en-IN') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Connect With Us') }} — {{ $organization?->name ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
</head>
<body class="crm-capture-body">
    @php
        $orgName = $organization?->name ?? config('app.name');
        $orgInitial = mb_strtoupper(mb_substr(trim((string) $orgName), 0, 1) ?: 'C');
    @endphp

    <div class="crm-capture-wrapper crm-capture-wrapper--wide">
        <div class="crm-capture-split">
            <aside class="crm-capture-aside">
                <div class="crm-capture-aside-top">
                    <div class="crm-capture-org-row">
                        <div class="crm-capture-org-avatar" aria-hidden="true">{{ $orgInitial }}</div>
                        <div>
                            <div class="crm-capture-org-name">{{ $orgName }}</div>
                            <div class="crm-capture-badge">
                                <span class="crm-capture-badge-dot" aria-hidden="true"></span>
                                {{ __('WhatsApp Business Inquiry') }}
                            </div>
                        </div>
                    </div>

                    <h1>{{ __('Connect With Us') }}</h1>
                    <p class="crm-capture-aside-desc">
                        {{ __('Please share your contact details to continue directly to WhatsApp.') }}
                    </p>

                    @if($link->campaign)
                        <div class="crm-capture-campaign-pill">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                            </svg>
                            <span>{{ __('Campaign') }}: <strong>{{ $link->campaign->name }}</strong></span>
                        </div>
                    @endif
                </div>

                <div class="crm-capture-aside-bottom">
                    <ul class="crm-capture-perks">
                        <li>
                            <span class="crm-capture-perk-icon" aria-hidden="true">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <span>{{ __('Instant WhatsApp chat with your inquiry pre-filled') }}</span>
                        </li>
                        <li>
                            <span class="crm-capture-perk-icon" aria-hidden="true">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <span>{{ __('Direct response from our dedicated team') }}</span>
                        </li>
                        <li>
                            <span class="crm-capture-perk-icon" aria-hidden="true">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <span>{{ __('100% private — your details are never shared') }}</span>
                        </li>
                    </ul>

                    <div class="crm-capture-trust-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span>{{ __('Encrypted & secure inquiry submission') }}</span>
                    </div>
                </div>
            </aside>

            <main class="crm-capture-main">
                <div class="crm-capture-topbar">
                    <div>
                        <h2 class="crm-capture-form-title">{{ __('Quick Details') }}</h2>
                        <p class="crm-capture-form-subtitle">{{ __('Takes less than 30 seconds') }}</p>
                    </div>
                    @include('partials.language-switcher', ['switcherId' => 'capture'])
                </div>

                @if($errors->any())
                    <div class="crm-alert crm-alert-error" style="margin-bottom: 0.85rem;">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('tracking-links.capture.submit', ['slug' => $link->slug ?: $link->code]) }}" class="crm-capture-form" id="crm-capture-form">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                    @foreach(($socialParams ?? []) as $paramKey => $paramVal)
                        <input type="hidden" name="social_params[{{ $paramKey }}]" value="{{ old('social_params.'.$paramKey, $paramVal) }}">
                    @endforeach

                    <div class="crm-capture-grid">
                        <div class="crm-field">
                            <label for="full_name">{{ __('Full Name') }} <span class="crm-required">*</span></label>
                            <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required autocomplete="name" placeholder="e.g. Priya Sharma">
                        </div>

                        <div class="crm-field">
                            <label for="phone">{{ __('Mobile / WhatsApp Number') }} <span class="crm-required">*</span></label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel" placeholder="e.g. +91 98765 43210">
                        </div>

                        <div class="crm-field">
                            <label for="email">{{ __('Work Email') }} @if($settings->require_email)<span class="crm-required">*</span>@else<span class="crm-muted">({{ __('Optional') }})</span>@endif</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" @if($settings->require_email) required @endif autocomplete="email" placeholder="you@company.com">
                        </div>

                        <div class="crm-field">
                            <label for="company_name">{{ __('Company Name') }} @if($settings->require_company)<span class="crm-required">*</span>@else<span class="crm-muted">({{ __('Optional') }})</span>@endif</label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" @if($settings->require_company) required @endif autocomplete="organization" placeholder="Acme Pvt Ltd">
                        </div>

                        <div class="crm-field crm-field-full">
                            <label for="requirement">{{ __('Requirement / Message') }} <span class="crm-muted">({{ __('Optional') }})</span></label>
                            <textarea id="requirement" name="requirement" rows="2" placeholder="{{ __('Tell us briefly what you are looking for...') }}">{{ old('requirement') }}</textarea>
                        </div>

                        @foreach(($customFields ?? collect()) as $field)
                            @php
                                $fieldKey = $field->field_key;
                                $inputId = 'cf_'.$fieldKey;
                                $inputName = 'custom_fields['.$fieldKey.']';
                                $oldVal = old('custom_fields.'.$fieldKey);
                                $options = $field->normalizedOptions();
                                $isFullWidth = in_array($field->data_type, ['textarea', 'multiselect'], true);
                            @endphp
                            <div class="crm-field {{ $isFullWidth ? 'crm-field-full' : '' }}">
                                <label for="{{ $inputId }}">
                                    {{ $field->label }}
                                    @if($field->is_required)
                                        <span class="crm-required">*</span>
                                    @else
                                        <span class="crm-muted">({{ __('Optional') }})</span>
                                    @endif
                                </label>

                                @if($field->data_type === 'textarea')
                                    <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="2" @if($field->is_required) required @endif>{{ is_string($oldVal) ? $oldVal : '' }}</textarea>
                                @elseif($field->data_type === 'number')
                                    <input id="{{ $inputId }}" name="{{ $inputName }}" type="number" step="any" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($field->is_required) required @endif>
                                @elseif($field->data_type === 'date')
                                    <input id="{{ $inputId }}" name="{{ $inputName }}" type="date" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($field->is_required) required @endif>
                                @elseif($field->data_type === 'boolean')
                                    <select id="{{ $inputId }}" name="{{ $inputName }}" @if($field->is_required) required @endif>
                                        <option value="">-- {{ __('Select') }} --</option>
                                        <option value="1" @selected((string) $oldVal === '1' || $oldVal === true)>{{ __('Yes') }}</option>
                                        <option value="0" @selected((string) $oldVal === '0' || $oldVal === false)>{{ __('No') }}</option>
                                    </select>
                                @elseif($field->data_type === 'select')
                                    <select id="{{ $inputId }}" name="{{ $inputName }}" @if($field->is_required) required @endif>
                                        <option value="">-- {{ __('Select') }} {{ $field->label }} --</option>
                                        @foreach($options as $option)
                                            <option value="{{ $option }}" @selected((string) $oldVal === (string) $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif($field->data_type === 'multiselect')
                                    @php($selectedOptions = is_array($oldVal) ? array_map('strval', $oldVal) : [])
                                    <div class="crm-capture-checkbox-group">
                                        @foreach($options as $option)
                                            <label class="crm-capture-checkbox">
                                                <input type="checkbox" name="{{ $inputName }}[]" value="{{ $option }}" @checked(in_array((string) $option, $selectedOptions, true))>
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <input id="{{ $inputId }}" name="{{ $inputName }}" type="text" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($field->is_required) required @endif>
                                @endif

                                @error('custom_fields.'.$fieldKey)
                                    <div class="crm-field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach

                        <div class="crm-capture-submit-wrap">
                            <button type="submit" class="crm-btn crm-btn-whatsapp crm-btn-block" id="crm-capture-submit">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.46 0 .1 5.36.1 11.96c0 2.11.55 4.17 1.6 5.98L0 24l6.23-1.63a11.9 11.9 0 0 0 5.83 1.49h.01c6.6 0 11.96-5.36 11.96-11.96 0-3.2-1.25-6.2-3.51-8.42ZM12.07 21.84h-.01a9.9 9.9 0 0 1-5.05-1.38l-.36-.22-3.7.97.99-3.6-.24-.37a9.9 9.9 0 0 1-1.52-5.28c0-5.46 4.44-9.9 9.9-9.9 2.64 0 5.13 1.03 7 2.9a9.83 9.83 0 0 1 2.9 7c0 5.46-4.44 9.88-9.91 9.88Zm5.43-7.41c-.3-.15-1.76-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/>
                                </svg>
                                <span>{{ __('Continue to WhatsApp') }} &rarr;</span>
                            </button>
                            <p class="crm-capture-footer-hint">
                                {{ __('You will be redirected to WhatsApp with your details ready to send.') }}
                            </p>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('crm-capture-form');
            var btn = document.getElementById('crm-capture-submit');
            if (!form || !btn) return;
            form.addEventListener('submit', function () {
                if (form.checkValidity()) {
                    btn.disabled = true;
                }
            });
        })();
    </script>
    @include('partials.language-selection-popup')
</body>
</html>
