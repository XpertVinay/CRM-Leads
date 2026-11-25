<!DOCTYPE html>
<html lang="{{ config('localization.supported.'.app()->getLocale().'.html_lang', 'en-IN') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Link Unavailable') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
</head>
<body class="crm-capture-body">
    <div class="crm-capture-wrapper">
        <div class="crm-capture-card" style="text-align: center;">
            <div style="display:flex; justify-content:flex-end; margin-bottom:0.5rem;">
                @include('partials.language-switcher', ['switcherId' => 'unavailable'])
            </div>
            <h1>{{ __('Link Unavailable') }}</h1>
            <p class="crm-muted">
                {{ __('This tracking link is either inactive or has expired and can no longer accept new inquiries.') }}
            </p>
        </div>
    </div>
    @include('partials.language-selection-popup')
</body>
</html>
