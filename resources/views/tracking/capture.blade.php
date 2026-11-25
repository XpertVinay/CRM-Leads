<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect with {{ $organization?->name ?? config('app.name') }} on WhatsApp</title>
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
</head>
<body class="crm-capture-body">
    <div class="crm-capture-wrapper">
        <div class="crm-capture-card">
            <div class="crm-capture-header">
                <div class="crm-capture-badge">WhatsApp Business Inquiry</div>
                <h1>{{ $organization?->name ?? 'Connect With Us' }}</h1>
                <p class="crm-muted">
                    @if($link->campaign)
                        Campaign: <strong>{{ $link->campaign->name }}</strong>
                    @else
                        Please share your contact details to continue directly to WhatsApp.
                    @endif
                </p>
            </div>

            @if($errors->any())
                <div class="crm-alert crm-alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('tracking-links.capture.submit', ['slug' => $link->slug ?: $link->code]) }}" class="crm-capture-form">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                @foreach(($socialParams ?? []) as $paramKey => $paramVal)
                    <input type="hidden" name="social_params[{{ $paramKey }}]" value="{{ old('social_params.'.$paramKey, $paramVal) }}">
                @endforeach

                <div class="crm-field">
                    <label for="full_name">Full Name <span class="crm-required">*</span></label>
                    <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required placeholder="e.g. Priya Sharma">
                </div>

                <div class="crm-field">
                    <label for="phone">Mobile / WhatsApp Number <span class="crm-required">*</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required placeholder="e.g. +91 98765 43210">
                </div>

                <div class="crm-field">
                    <label for="email">Work Email @if($settings->require_email)<span class="crm-required">*</span>@else<span class="crm-muted">(Optional)</span>@endif</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" @if($settings->require_email) required @endif placeholder="you@company.com">
                </div>

                <div class="crm-field">
                    <label for="company_name">Company Name @if($settings->require_company)<span class="crm-required">*</span>@else<span class="crm-muted">(Optional)</span>@endif</label>
                    <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" @if($settings->require_company) required @endif placeholder="Acme Pvt Ltd">
                </div>

                <div class="crm-field">
                    <label for="requirement">Requirement / Message <span class="crm-muted">(Optional)</span></label>
                    <textarea id="requirement" name="requirement" rows="3" placeholder="Tell us briefly what you are looking for...">{{ old('requirement') }}</textarea>
                </div>

                @foreach(($customFields ?? collect()) as $field)
                    @php
                        $fieldKey = $field->field_key;
                        $inputId = 'cf_'.$fieldKey;
                        $inputName = 'custom_fields['.$fieldKey.']';
                        $oldVal = old('custom_fields.'.$fieldKey);
                        $options = $field->normalizedOptions();
                    @endphp
                    <div class="crm-field">
                        <label for="{{ $inputId }}">
                            {{ $field->label }}
                            @if($field->is_required)
                                <span class="crm-required">*</span>
                            @else
                                <span class="crm-muted">(Optional)</span>
                            @endif
                        </label>

                        @if($field->data_type === 'textarea')
                            <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="3" @if($field->is_required) required @endif>{{ is_string($oldVal) ? $oldVal : '' }}</textarea>
                        @elseif($field->data_type === 'number')
                            <input id="{{ $inputId }}" name="{{ $inputName }}" type="number" step="any" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($field->is_required) required @endif>
                        @elseif($field->data_type === 'date')
                            <input id="{{ $inputId }}" name="{{ $inputName }}" type="date" value="{{ is_scalar($oldVal) ? $oldVal : '' }}" @if($field->is_required) required @endif>
                        @elseif($field->data_type === 'boolean')
                            <select id="{{ $inputId }}" name="{{ $inputName }}" @if($field->is_required) required @endif>
                                <option value="">-- Select --</option>
                                <option value="1" @selected((string) $oldVal === '1' || $oldVal === true)>Yes</option>
                                <option value="0" @selected((string) $oldVal === '0' || $oldVal === false)>No</option>
                            </select>
                        @elseif($field->data_type === 'select')
                            <select id="{{ $inputId }}" name="{{ $inputName }}" @if($field->is_required) required @endif>
                                <option value="">-- Select {{ $field->label }} --</option>
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

                <button type="submit" class="crm-btn crm-btn-whatsapp crm-btn-block">
                    Continue to WhatsApp &rarr;
                </button>
            </form>
        </div>
    </div>
</body>
</html>
