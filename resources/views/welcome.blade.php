<!DOCTYPE html>
<html lang="{{ config('localization.supported.' . app()->getLocale() . '.html_lang', 'en-IN') }}">
@php
    $configuredName = config('app.name');
    $brandName = (!$configuredName || $configuredName === 'Laravel') ? 'LeadFlow CRM' : $configuredName;
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __(':brand — Simple Customer & Sales Manager', ['brand' => $brandName]) }}</title>
    <meta name="description"
        content="{{ __('Stop losing customer inquiries across WhatsApp, Instagram, and spreadsheets. Track which ads drive real buyers, follow up on time, and grow stress-free.') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}">
</head>

<body class="lp-body">

    {{-- Top Navigation --}}
    <header class="lp-nav">
        <div class="lp-container lp-nav-inner">
            <a href="{{ route('home') }}" class="lp-logo">
                <span class="lp-logo-badge">
                    <span class="material-symbols-outlined"
                        style="font-size:21px;font-variation-settings:'FILL' 1;">dataset</span>
                </span>
                <span>{{ $brandName }}</span>
            </a>

            <button type="button" class="lp-mobile-toggle" data-lp-nav-toggle aria-label="{{ __('Open menu') }}"
                aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>

            <ul class="lp-nav-links">
                <li><a href="#why-us">{{ __('Why Us') }}</a></li>
                <li><a href="#who-it-helps">{{ __('B2B & B2C') }}</a></li>
                <li><a href="#features">{{ __('Features') }}</a></li>
                <li><a href="#plans">{{ __('Pricing') }}</a></li>
                <li><a href="#offers">{{ __('Offers') }}</a></li>
                <li><a href="#faq">{{ __('FAQ') }}</a></li>
            </ul>

            <div class="lp-nav-actions">
                @include('partials.language-switcher', ['switcherId' => 'landing'])
                @auth
                    <a href="{{ route('organizations.index') }}"
                        class="crm-btn crm-btn-secondary">{{ __('Organizations') }}</a>
                    <a href="{{ url('/dashboard') }}" class="crm-btn crm-btn-primary">
                        <span>{{ __('Dashboard') }}</span>
                        <span class="material-symbols-outlined" style="font-size:17px;">arrow_forward</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="crm-btn crm-btn-secondary">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="crm-btn crm-btn-primary">
                        <span>{{ __('Get Started') }}</span>
                        <span class="material-symbols-outlined" style="font-size:17px;">arrow_forward</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Hero Section: Core Value Prop, Primary CTA, Live Preview --}}
    <section class="lp-hero">
        <div class="lp-hero-glow-1" aria-hidden="true"></div>
        <div class="lp-hero-glow-2" aria-hidden="true"></div>

        <div class="lp-container lp-hero-grid">
            <div>
                <div class="lp-eyebrow">
                    <span class="material-symbols-outlined"
                        style="font-size:16px;font-variation-settings:'FILL' 1;">verified_user</span>
                    <span>{{ __('Built for growing Indian SMBs') }}</span>
                </div>

                <h1 class="lp-hero-title">
                    {{ __('Stop Losing Leads Across WhatsApp, Instagram & Spreadsheets.') }}
                    <span>{{ __('Know Which Ad Drives Real Buyers.') }}</span>
                </h1>

                <p class="lp-hero-sub">
                    {{ __(':brand captures every inquiry in one shared workspace, reminds your team to follow up on time, and shows which campaigns actually close—so no buyer slips through the cracks.', ['brand' => $brandName]) }}
                </p>

                <div class="lp-hero-ctas">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-primary">
                            <span>{{ __('Go to Your Dashboard') }}</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('organizations.index') }}" class="lp-btn-hero lp-btn-secondary">
                            <span>{{ __('Switch Company') }}</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary">
                            <span>{{ __('Create Company Account') }}</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="#plans" class="lp-btn-hero lp-btn-secondary">
                            <span>{{ __('See Plans (From ₹2,499/mo)') }}</span>
                        </a>
                    @endauth
                </div>

                <div class="lp-hero-micro">
                    <span><span class="material-symbols-outlined">check_circle</span>
                        {{ __('Up & running in 30 seconds') }}</span>
                    <span><span class="material-symbols-outlined">check_circle</span>
                        {{ __('100% private to your team') }}</span>
                    <span><span class="material-symbols-outlined">check_circle</span>
                        {{ __('Zero unapproved overage bills') }}</span>
                </div>
            </div>

            {{-- Side-by-Side Desktop & Mobile Preview --}}
            <div class="lp-device-stage" aria-label="{{ __('Desktop and mobile CRM preview') }}">
                {{-- Desktop Browser Window --}}
                <div class="lp-preview-card lp-desktop-mockup">
                    <div class="lp-preview-top">
                        <div class="lp-preview-dots"><i></i><i></i><i></i></div>
                        <div class="lp-browser-url">
                            <span class="material-symbols-outlined" style="font-size:13px;">lock</span>
                            <span>app.leadflow.in/dashboard</span>
                        </div>
                        <span class="lp-preview-pill">● {{ __('Live') }}</span>
                    </div>

                    <div class="lp-desktop-body">
                        <aside class="lp-mini-sidebar" aria-hidden="true">
                            <span class="lp-mini-nav is-active"><span
                                    class="material-symbols-outlined">dashboard</span></span>
                            <span class="lp-mini-nav"><span class="material-symbols-outlined">inbox</span></span>
                            <span class="lp-mini-nav"><span class="material-symbols-outlined">view_kanban</span></span>
                            <span class="lp-mini-nav"><span class="material-symbols-outlined">chat</span></span>
                            <span class="lp-mini-nav"><span class="material-symbols-outlined">bar_chart</span></span>
                        </aside>

                        <div class="lp-desktop-main">
                            <div class="lp-kpi-row">
                                <div class="lp-kpi-box">
                                    <div class="lp-kpi-label">{{ __('New Inquiries') }}</div>
                                    <div class="lp-kpi-val">148</div>
                                    <div class="lp-kpi-delta">{{ __('+24 today') }}</div>
                                </div>
                                <div class="lp-kpi-box">
                                    <div class="lp-kpi-label">{{ __('Open Deals') }}</div>
                                    <div class="lp-kpi-val">₹12.4L</div>
                                    <div class="lp-kpi-delta">{{ __('18 active') }}</div>
                                </div>
                                <div class="lp-kpi-box">
                                    <div class="lp-kpi-label">{{ __('Sales Won') }}</div>
                                    <div class="lp-kpi-val">39</div>
                                    <div class="lp-kpi-delta">{{ __('Verified buyers') }}</div>
                                </div>
                            </div>

                            <div class="lp-desktop-split">
                                <div class="lp-mini-panel">
                                    <div class="lp-mini-title">{{ __("Today's Follow-Ups") }}</div>
                                    <div class="lp-mini-lead">
                                        <div>
                                            <strong>{{ __('Meera Sharma') }}</strong>
                                            <span>{{ __('₹48,000 • Replied on WhatsApp') }}</span>
                                        </div>
                                        <em class="lp-tag-green">{{ __('Hot') }}</em>
                                    </div>
                                    <div class="lp-mini-lead">
                                        <div>
                                            <strong>{{ __('Aarav Builders') }}</strong>
                                            <span>{{ __('₹3.2L • Call at 4:00 PM') }}</span>
                                        </div>
                                        <em class="lp-tag-indigo">{{ __('Quote Sent') }}</em>
                                    </div>
                                </div>

                                <div class="lp-mini-panel">
                                    <div class="lp-mini-title">{{ __('Where Buyers Came From') }}</div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head">
                                            <span>{{ __('Instagram Ads') }}</span><strong>62%</strong>
                                        </div>
                                        <div class="lp-bar-track"><i style="width:62%;"></i></div>
                                    </div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head">
                                            <span>{{ __('Google Search') }}</span><strong>24%</strong>
                                        </div>
                                        <div class="lp-bar-track"><i
                                                style="width:24%;background:var(--crm-success);"></i></div>
                                    </div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head">
                                            <span>{{ __('Referral Link') }}</span><strong>14%</strong>
                                        </div>
                                        <div class="lp-bar-track"><i
                                                style="width:14%;background:var(--crm-tertiary);"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile Phone Mockup --}}
                <div class="lp-mobile-mockup" aria-label="{{ __('Mobile view preview') }}">
                    <div class="lp-phone-notch"></div>
                    <div class="lp-phone-status">
                        <span>9:41</span>
                        <span>5G • 100%</span>
                    </div>
                    <div class="lp-phone-screen">
                        <div class="lp-phone-head">
                            <strong>{{ __('My Sales') }}</strong>
                            <span class="lp-phone-dot">● {{ __('On the go') }}</span>
                        </div>

                        <div class="lp-phone-alert">
                            <span class="material-symbols-outlined">notifications_active</span>
                            <div>
                                <strong>{{ __('New Inquiry!') }}</strong>
                                <p>{{ __('Rahul Verma clicked your Instagram ad just now.') }}</p>
                            </div>
                        </div>

                        <div class="lp-phone-actions">
                            <span><span class="material-symbols-outlined">call</span> {{ __('Call') }}</span>
                            <span><span class="material-symbols-outlined">chat</span> {{ __('WhatsApp') }}</span>
                        </div>

                        <div class="lp-phone-card">
                            <div class="lp-phone-card-top">
                                <strong>{{ __('Priya Textiles') }}</strong>
                                <em>₹85,000</em>
                            </div>
                            <span>{{ __('Stage: Waiting for payment') }}</span>
                        </div>

                        <div class="lp-phone-quota">
                            <div class="lp-bar-head"><span>{{ __('Monthly Leads') }}</span><strong>80%</strong></div>
                            <div class="lp-bar-track"><i style="width:80%;background:var(--crm-tertiary);"></i></div>
                            <small>{{ __('Owner alerted via Email & WhatsApp') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust Strip --}}
    <section id="why-us" class="lp-trust-bar">
        <div class="lp-container lp-trust-grid">
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">lock</span>
                </div>
                <div>
                    <strong>{{ __('100% Private Workspace') }}</strong>
                    <span>{{ __('Only your invited staff can ever access your customer list') }}</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">fact_check</span>
                </div>
                <div>
                    <strong>{{ __('True Ad Attribution') }}</strong>
                    <span>{{ __('Track closed revenue back to the exact ad—not just clicks') }}</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">notifications_active</span>
                </div>
                <div>
                    <strong>{{ __('Protected Monthly Caps') }}</strong>
                    <span>{{ __('Pre-limit alerts and safe auto-pause prevent unexpected charges') }}</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">chat</span>
                </div>
                <div>
                    <strong>{{ __('Instant WhatsApp Handoff') }}</strong>
                    <span>{{ __('Route inquiries straight to reps with one-tap WhatsApp replies') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- B2B vs. B2C Switcher --}}
    <section id="who-it-helps" class="lp-section">
        <div class="lp-container">
            <div class="lp-section-head">
                <h2>{{ __('One Workspace for Multi-Touch B2B Deals & Fast B2C Inquiries') }}</h2>
                <p>{{ __('Switch between sales motions below to see how :brand adapts to the way your team closes.', ['brand' => $brandName]) }}
                </p>
            </div>

            <div style="text-align:center;">
                <div class="lp-mode-tabs" role="tablist" aria-label="{{ __('Business Types') }}">
                    <button type="button" class="lp-mode-tab is-active" data-lp-mode-btn="b2b" role="tab"
                        aria-selected="true">{{ __('Selling to Businesses (B2B)') }}</button>
                    <button type="button" class="lp-mode-tab" data-lp-mode-btn="b2c" role="tab"
                        aria-selected="false">{{ __('Selling to Direct Buyers (B2C)') }}</button>
                </div>
            </div>

            <div class="lp-mode-panel is-active" data-lp-mode-panel="b2b">
                <div>
                    <h3 class="lp-display" style="font-size:1.5rem;margin-bottom:0.65rem;">
                        {{ __('Keep Multi-Week Deals Moving Forward') }}
                    </h3>
                    <p class="crm-muted" style="line-height:1.65;">
                        {{ __('When a sale spans calls, quotes, and multiple stakeholders at a company, context gets lost in personal chats. Keep every decision-maker, quote, and follow-up date visible to your team.') }}
                    </p>
                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Group multiple contacts under one company account') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Track deals stage-by-stage from Inquiry to Paid') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Spot stalled deals and assign manager follow-ups') }}
                        </li>
                    </ul>
                </div>
                <div class="lp-mode-steps">
                    <div class="lp-step-card">
                        <span class="lp-step-num">1</span>
                        <div>
                            <strong>{{ __('Convert inquiries into deals in one click') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __('No re-typing phone numbers, company names, or call notes.') }}
                            </div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">2</span>
                        <div>
                            <strong>{{ __('Collaborate with role-based permissions') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __('Control who can view, edit, export, or manage deals.') }}
                            </div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">3</span>
                        <div>
                            <strong>{{ __('Review monthly pipeline & revenue forecasts') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __('Export clean deal summaries anytime with one click.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lp-mode-panel" data-lp-mode-panel="b2c">
                <div>
                    <h3 class="lp-display" style="font-size:1.5rem;margin-bottom:0.65rem;">
                        {{ __('Reply in Seconds to High-Volume Inquiries') }}
                    </h3>
                    <p class="crm-muted" style="line-height:1.65;">
                        {{ __('When shoppers message from Instagram, Facebook, Google Ads, or your website, the first business to reply wins the sale. Route and respond before buyers go cold.') }}
                    </p>
                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Branded inquiry links & forms ready for any ad') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Auto-assign incoming leads across available reps') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Reach buyers fast via WhatsApp, Call, or Email') }}
                        </li>
                    </ul>
                </div>
                <div class="lp-mode-steps">
                    <div class="lp-step-card">
                        <span class="lp-step-num">1</span>
                        <div>
                            <strong>{{ __('Capture every ad inquiry automatically') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __("Every form fill lands in your team's live inbox instantly.") }}
                            </div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">2</span>
                        <div>
                            <strong>{{ __('Get notified before a buyer cools off') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __('Instant alerts and follow-up reminders keep response times under 5 minutes.') }}
                            </div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">3</span>
                        <div>
                            <strong>{{ __('See which campaigns convert to cash') }}</strong>
                            <div class="crm-muted" style="font-size:0.9rem;">
                                {{ __('Compare cost-per-sale across Instagram, Google, and referrals.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Core Features (6 Scannable Cards) --}}
    <section id="features" class="lp-section lp-section-alt">
        <div class="lp-container">
            <div class="lp-section-head">
                <div class="lp-eyebrow">{{ __('Core Capabilities') }}</div>
                <h2>{{ __("Everything Your Sales Team Needs—Nothing It Doesn't") }}</h2>
                <p>{{ __('Built for speed and daily adoption, with zero bloat or steep learning curves.') }}</p>
            </div>

            <div class="lp-bento">
                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">link</span></div>
                    <h3>{{ __('Ad Tracking Links & Forms') }}</h3>
                    <p>{{ __('Generate UTM-tagged inquiry links and lightweight forms for Instagram, Google, and WhatsApp ads to see which campaigns bring real buyers.') }}
                    </p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">forum</span></div>
                    <h3>{{ __('WhatsApp Business Workflow') }}</h3>
                    <p>{{ __('Trigger instant WhatsApp lead alerts to reps, send pre-approved reply templates, and manage conversations from a shared team workflow—or start with your existing WhatsApp number.') }}
                    </p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">view_kanban</span></div>
                    <h3>{{ __('Visual Sales Pipeline') }}</h3>
                    <p>{{ __('Drag deals across custom stages, schedule call-back reminders, and see every overdue follow-up at a glance each morning.') }}
                    </p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">tune</span></div>
                    <h3>{{ __('No-Code Custom Fields') }}</h3>
                    <p>{{ __('Add fields like City, Budget, Property Type, or Course Interest in seconds—tailored to your workflow without developer help.') }}
                    </p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">monitoring</span></div>
                    <h3>{{ __('Team Speed & ROI Reports') }}</h3>
                    <p>{{ __('Track rep response times, weekly conversion rates, and revenue by source, with one-click CSV/spreadsheet exports.') }}
                    </p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">group</span></div>
                    <h3>{{ __('Role-Based Team Access') }}</h3>
                    <p>{{ __('Invite sales reps and managers in clicks, and control exactly who can view, edit, or export customer records.') }}
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- Pricing & Quota Protection --}}
    <section id="plans" class="lp-section">
        <div class="lp-container">
            <div class="lp-section-head">
                <div class="lp-eyebrow">{{ __('Transparent Pricing & Quota Protection') }}</div>
                <h2>{{ __('Simple Plans With Built-In Bill Protection') }}</h2>
                <p>{{ __('Know your exact monthly leads, included seats, and optional top-up rates upfront. We alert you before you hit your cap and pause capture safely—no extra charge ever happens without your explicit approval.') }}
                </p>
            </div>

            <div class="lp-pricing-grid">
                {{-- Starter Plan --}}
                <div class="lp-plan-card">
                    <h3 class="lp-plan-name">{{ __('Starter Plan') }}</h3>
                    <p class="crm-muted" style="margin:0;">
                        {{ __('Ideal for growing teams organizing ad inquiries and daily follow-ups.') }}
                    </p>

                    <div class="lp-plan-price">
                        <span class="lp-plan-amount">₹2,499</span>
                        <span class="lp-plan-period">{{ __('/ month') }}</span>
                    </div>

                    <div class="lp-plan-limits">
                        <div>
                            <span>{{ __('Monthly Leads Included') }}</span><strong>{{ __('Up to 25 leads/month') }}</strong>
                        </div>
                        <div>
                            <span>{{ __('Team Seats Included') }}</span><strong>{{ __('Up to 2 users included') }}</strong>
                        </div>
                        <div><span>{{ __('Extra Team Seat') }}</span><strong>{{ __('₹900 / user / month') }}</strong>
                        </div>
                    </div>

                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Full B2B & B2C pipeline & contact manager') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Ad tracking links & embeddable inquiry forms') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('WhatsApp lead handoff, Email & Phone logging') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Pre-limit alerts & auto-pause at 100% cap') }}
                        </li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Admin-approved pay-per-lead top-ups') }}
                        </li>
                    </ul>

                    <div class="lp-plan-cta">
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-secondary"
                            style="width:100%;justify-content:center;">
                            <span>{{ __('Choose Starter — ₹2,499/mo') }}</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    </div>
                </div>

                {{-- Gold Plan --}}
                <div class="lp-plan-card is-featured">
                    <span class="lp-plan-ribbon">{{ __('Best Value') }}</span>
                    <h3 class="lp-plan-name">{{ __('Gold Plan') }}</h3>
                    <p class="crm-muted" style="margin:0;">
                        {{ __('For active sales teams needing higher lead volume, more seats, and lower overage rates.') }}
                    </p>

                    <div class="lp-plan-price">
                        <span class="lp-plan-amount">₹4,999</span>
                        <span class="lp-plan-period">{{ __('/ month') }}</span>
                    </div>

                    <div class="lp-plan-limits">
                        <div>
                            <span>{{ __('Monthly Leads Included') }}</span><strong>{{ __('Up to 75 leads/month') }}</strong>
                        </div>
                        <div>
                            <span>{{ __('Team Seats Included') }}</span><strong>{{ __('Up to 5 users included') }}</strong>
                        </div>
                        <div><span>{{ __('Extra Team Seat') }}</span><strong>{{ __('₹700 / user / month') }}</strong>
                        </div>
                    </div>

                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Everything in Starter with expanded lead & seat quotas') }}</li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('~25% lower overage rates across all industries') }}</li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Shared WhatsApp Business templates & team reports') }}</li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Priority pre-limit alerts & instant top-up controls') }}</li>
                        <li><span class="material-symbols-outlined">check_circle</span>
                            {{ __('Priority onboarding & dedicated support') }}</li>
                    </ul>

                    <div class="lp-plan-cta">
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary"
                            style="width:100%;justify-content:center;">
                            <span>{{ __('Choose Gold — ₹4,999/mo') }}</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Quota Protection & Interactive Industry Overage Switcher --}}
            <div class="lp-overage-box">
                <div class="lp-overage-grid">
                    <div>
                        <div class="lp-eyebrow" style="margin-bottom:0.65rem;">{{ __('No Surprise Bills Guarantee') }}
                        </div>
                        <h3 class="lp-display" style="font-size:1.3rem;margin-bottom:0.65rem;">
                            {{ __('What Happens When You Reach Your Monthly Lead Cap?') }}
                        </h3>
                        <ul class="lp-protection-list">
                            <li>
                                <span class="material-symbols-outlined">notifications_active</span>
                                <div>
                                    <strong>{{ __('Pre-Limit Warnings:') }}</strong>
                                    {{ __('The Business Owner (Org Admin) is alerted at 80% and 100% of your monthly quota via') }}
                                    <strong>{{ __('Email, SMS, WhatsApp, and Push') }}</strong>.
                                </div>
                            </li>
                            <li>
                                <span class="material-symbols-outlined">pause_circle</span>
                                <div>
                                    <strong>{{ __('Safe Auto-Pause:') }}</strong>
                                    {{ __('New lead capture pauses automatically at 100% so you are never billed without consent.') }}
                                </div>
                            </li>
                            <li>
                                <span class="material-symbols-outlined">bolt</span>
                                <div>
                                    <strong>{{ __('Admin-Approved Top-Ups:') }}</strong>
                                    {{ __('Need more leads mid-month? Unlock extra leads at your industry rate or upgrade anytime—only when an Org Admin explicitly approves.') }}
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="lp-industry-panel">
                        <div class="lp-industry-head">
                            <span>{{ __('Check Pay-Per-Lead Top-Up Rate by Industry') }}</span>
                            <small>{{ __('Pick your industry to compare Starter vs. Gold overage rates') }}</small>
                        </div>

                        <div class="lp-industry-pills" role="tablist" aria-label="{{ __('Select your industry') }}">
                            <button type="button" class="lp-industry-pill is-active" data-lp-industry="saas" role="tab"
                                aria-selected="true">{{ __('Software & IT') }}</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="realestate" role="tab"
                                aria-selected="false">{{ __('Real Estate') }}</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="education" role="tab"
                                aria-selected="false">{{ __('Coaching & Schools') }}</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="ecommerce" role="tab"
                                aria-selected="false">{{ __('Online Store & Retail') }}</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="healthcare" role="tab"
                                aria-selected="false">{{ __('Clinics & Health') }}</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="finance" role="tab"
                                aria-selected="false">{{ __('Loans & Finance') }}</button>
                        </div>

                        <div class="lp-industry-result-card" data-lp-industry-result aria-live="polite">
                            <div class="lp-industry-selected-name" data-lp-industry-label>
                                {{ __('Software & IT Services') }}
                            </div>
                            <div class="lp-rate-compare">
                                <div class="lp-rate-tier">
                                    <span>{{ __('Starter Plan') }}</span>
                                    <strong data-lp-rate-starter>₹300{{ __(' / extra lead') }}</strong>
                                    <small data-lp-seat-starter
                                        style="display:block;font-size:0.85rem;color:var(--crm-muted);margin-top:0.2rem;">₹900{{ __(' / extra user / mo') }}</small>
                                </div>
                                <div class="lp-rate-tier is-gold">
                                    <span>{{ __('Gold Plan') }}</span>
                                    <strong data-lp-rate-gold>₹220{{ __(' / extra lead') }}</strong>
                                    <small data-lp-seat-gold
                                        style="display:block;font-size:0.85rem;color:var(--crm-muted);margin-top:0.2rem;">₹700{{ __(' / extra user / mo') }}</small>
                                </div>
                            </div>
                            <p class="lp-rate-note">
                                {{ __('Gold saves ~25% on every extra lead and seat. Rates are only charged when an Org Admin approves a top-up after your monthly quota pauses.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Welcome Discount & Referral Block (Org Admin Only) --}}
    <section id="offers" class="lp-section lp-section-alt">
        <div class="lp-container">
            <div class="lp-offer-banner">
                <div>
                    <div class="lp-eyebrow" style="background:rgba(255,255,255,0.16);color:#fff;">
                        <span class="material-symbols-outlined" style="font-size:16px;">redeem</span>
                        <span>{{ __('For Organization Admins (Business Owners)') }}</span>
                    </div>
                    <h2>{{ __('Claim Your Welcome Coupon & Earn B2B Referral Rewards') }}</h2>
                    <p>
                        {{ __('Registering your company for the first time? A personal') }}
                        <strong>{{ __('welcome discount coupon') }}</strong>
                        {{ __('is issued automatically on sign-up—apply it right away on Starter or Gold.') }}
                    </p>
                    <p style="font-size:0.92rem;color:rgba(255,255,255,0.9);">
                        {{ __('Already an Org Admin? Share your') }} <strong>{{ __('B2B referral link') }}</strong>
                        {{ __('with fellow business owners to earn referral credits whenever a new company joins through your link.') }}
                    </p>

                    <div class="lp-offer-pills">
                        <span class="lp-offer-pill">✓ {{ __('Auto-Issued Welcome Coupon') }}</span>
                        <span class="lp-offer-pill">✓ {{ __('B2B Referral Link in Dashboard') }}</span>
                        <span class="lp-offer-pill">✓ {{ __('Org Admins Only (Excludes Staff Seats)') }}</span>
                    </div>
                </div>

                <div class="lp-offer-card">
                    <div
                        style="font-size:0.84rem;text-transform:uppercase;letter-spacing:0.06em;opacity:0.9;margin-bottom:0.4rem;">
                        {{ __('Org Admin Perks') }}
                    </div>
                    <h3 style="font-family:var(--crm-font-display);font-size:1.25rem;margin:0 0 0.75rem;color:#fff;">
                        {{ __('Unlock Your First-Time Savings') }}
                    </h3>
                    <p style="font-size:0.92rem;margin:0 0 1.15rem;color:rgba(255,255,255,0.9);">
                        {{ __('Welcome coupons and referral rewards apply exclusively to Organization Admin accounts creating or referring a company workspace—never to invited staff seats.') }}
                    </p>
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-white">
                            <span>{{ __('Open Dashboard for Referral Link') }}</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-white">
                            <span>{{ __('Register & Claim Welcome Coupon') }}</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('login') }}" class="lp-btn-hero lp-btn-ghost">
                            <span>{{ __('Log In for Your Referral Link') }}</span>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- Social Proof --}}
    <section id="stories" class="lp-section">
        <div class="lp-container">
            <div class="lp-section-head">
                <div class="lp-eyebrow">{{ __('Customer Stories') }}</div>
                <h2>{{ __('Trusted by Sales Teams Across India') }}</h2>
                <p>{{ __('How small and growing businesses organize inquiries, keep follow-ups on track, and see which ads bring real leads.') }}
                </p>
            </div>

            <div class="lp-testimonials">
                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">trending_up</span>
                            <span>{{ __('Clearer ad-to-lead tracking') }}</span>
                        </div>
                        <p class="lp-quote-text">
                            {{ __('“Earlier, our Instagram and website inquiries lived in spreadsheets and WhatsApp chats, so it was hard to tell which campaign actually converted. Now every inquiry is tagged to its source, and follow-ups stay organized in one place.”') }}
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">RD</span>
                        <div>
                            <strong>{{ __('Rohan Deshmukh') }}</strong>
                            <span>{{ __('Founder, NexaSoft Solutions • Pune (IT Services)') }}</span>
                        </div>
                    </div>
                </article>

                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">bolt</span>
                            <span>{{ __('Predictable monthly spend') }}</span>
                        </div>
                        <p class="lp-quote-text">
                            {{ __('“When our admission inquiries picked up over the weekend, I got a WhatsApp alert before we hit our monthly limit. I could review the incoming leads and decide whether to top up instead of getting hit with an unexpected bill.”') }}
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">AN</span>
                        <div>
                            <strong>{{ __('Ananya Nair') }}</strong>
                            <span>{{ __('Director, SkillBridge Academy • Kochi (Coaching & Courses)') }}</span>
                        </div>
                    </div>
                </article>

                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">groups</span>
                            <span>{{ __('B2B & retail in one view') }}</span>
                        </div>
                        <p class="lp-quote-text">
                            {{ __('“We handle both wholesale distributor inquiries and direct customer orders. Moving our 4-person sales team onto one shared pipeline took a couple of days, and everyone now sees the same customer history.”') }}
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">VM</span>
                        <div>
                            <strong>{{ __('Vikram Mehta') }}</strong>
                            <span>{{ __('Sales Head, Mehta Looms • Surat (Wholesale & Retail)') }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="lp-section lp-section-alt">
        <div class="lp-container">
            <div class="lp-section-head">
                <div class="lp-eyebrow">{{ __('Common Questions') }}</div>
                <h2>{{ __('Everything You Might Be Wondering') }}</h2>
            </div>

            <div class="lp-faq-list">
                <div class="lp-faq-item is-open">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="true">
                        <span>{{ __('What happens when my monthly lead limit is reached?') }}</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        {{ __('New lead capture pauses automatically at 100% so you never receive an unexpected bill. The Business Owner (Org Admin) is alerted at 80% and again at 100% via Email, SMS, WhatsApp, and Push Notification. Extra leads are only billed if an Org Admin explicitly approves a pay-per-lead top-up or upgrades to Gold (₹4,999/mo).') }}
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>{{ __('How do team seats and extra member fees work?') }}</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        {{ __('Starter (₹2,499/mo) includes') }} <strong>{{ __('2 users') }}</strong>
                        {{ __('and Gold (₹4,999/mo) includes') }}
                        <strong>{{ __('5 users') }}</strong>.
                        {{ __("If your team grows beyond your plan's included seats, you can add extra staff members anytime—starting at") }}
                        <strong>{{ __('₹900/user/mo on Starter and ₹700/user/mo on Gold') }}</strong>
                        {{ __('(rates vary by industry).') }}
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>{{ __('Who is eligible for the welcome coupon and referral rewards?') }}</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        {{ __('Welcome coupons and B2B referral rewards are strictly for') }}
                        <strong>{{ __('Organization Admins (Business Owners)') }}</strong>—{{ __('invited staff or team members are not eligible. Your welcome coupon is generated automatically on first-time company registration, and referral credits apply when another business registers through your Org Admin referral link.') }}
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>{{ __('How does WhatsApp integration work—do I need an API setup to start?') }}</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        {{ __("No complex setup is required to begin. You can start immediately using your existing business WhatsApp number for instant lead handoff alerts and one-tap replies, alongside Email and Phone tracking. When you're ready, you can enable official WhatsApp Business templates and shared team inbox workflows anytime.") }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final Call to Action --}}
    <section class="lp-section">
        <div class="lp-container">
            <div class="lp-final-cta">
                <h2>{{ __('Ready to Turn More Inquiries Into Paying Customers?') }}</h2>
                <p>
                    {{ __('Create your company workspace today, organize every B2B and B2C inquiry, and follow up faster with zero surprise bills.') }}
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:0.85rem;justify-content:center;">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-primary">
                            <span>{{ __('Go to Dashboard') }}</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary">
                            <span>{{ __('Create Your Account Now') }}</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('login') }}" class="lp-btn-hero lp-btn-secondary">
                            <span>{{ __('Already Registered? Log In') }}</span>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="lp-footer">
        <div class="lp-container lp-footer-inner">
            <div>
                <strong>{{ $brandName }}</strong> &copy; {{ date('Y') }}.
                {{ __('Simple Customer & Sales Management for Growing Businesses.') }}
            </div>
            <div class="lp-footer-links">
                <a href="#why-us">{{ __('Why Us') }}</a>
                <a href="#features">{{ __('Features') }}</a>
                <a href="#plans">{{ __('Pricing') }}</a>
                <a href="#offers">{{ __('Offers') }}</a>
                @auth
                    <a href="{{ url('/dashboard') }}">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}">{{ __('Log In') }}</a>
                    <a href="{{ route('register') }}">{{ __('Register') }}</a>
                @endauth
            </div>
        </div>
    </footer>

    @include('partials.language-selection-popup')

    <script>
        window.landingI18n = {{ \Illuminate\Support\Js::from([
    'extraLeadSuffix' => __(' / extra lead'),
    'extraUserSuffix' => __(' / extra user / mo'),
    'industries' => [
        'saas' => __('Software & IT Services'),
        'realestate' => __('Real Estate & Property'),
        'education' => __('Coaching, Courses & Schools'),
        'ecommerce' => __('Online Store & Retail'),
        'healthcare' => __('Clinics & Healthcare'),
        'finance' => __('Loans, Insurance & Finance'),
    ],
]) }};
    </script>
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/landing.js') }}"></script>
</body>

</html>