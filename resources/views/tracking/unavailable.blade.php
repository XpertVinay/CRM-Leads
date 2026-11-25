<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link Unavailable</title>
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
</head>
<body class="crm-capture-body">
    <div class="crm-capture-wrapper">
        <div class="crm-capture-card" style="text-align: center;">
            <h1>Link Unavailable</h1>
            <p class="crm-muted">
                This tracking link is either inactive or has expired and can no longer accept new inquiries.
            </p>
        </div>
    </div>
</body>
</html>
