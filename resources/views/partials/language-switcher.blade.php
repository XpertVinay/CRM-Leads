@php
    $supportedLocales = config('localization.supported', []);
    $currentLocale = app()->getLocale();
@endphp

<form method="POST" action="{{ route('locale.update') }}" class="crm-lang-switcher-form" style="margin:0; display:inline-flex; align-items:center;">
    @csrf
    <label for="crm-lang-select-{{ $switcherId ?? 'default' }}" class="crm-sr-only">{{ __('Language') }}</label>
    <select
        id="crm-lang-select-{{ $switcherId ?? 'default' }}"
        name="locale"
        class="crm-lang-select"
        aria-label="{{ __('Language') }}"
        onchange="this.form.submit()"
    >
        @foreach($supportedLocales as $code => $meta)
            <option value="{{ $code }}" @selected($currentLocale === $code)>
                {{ $meta['native'] ?? $meta['label'] ?? $code }}
            </option>
        @endforeach
    </select>
</form>
