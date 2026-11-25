<!DOCTYPE html>
<html lang="{{ config('localization.supported.'.app()->getLocale().'.html_lang', 'en-IN') }}">
@php
    $configuredName = config('app.name');
    $brandName = (!$configuredName || $configuredName === 'Laravel') ? 'LeadFlow CRM' : $configuredName;
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }} — Simple Customer &amp; Sales Manager</title>
    <meta name="description"
        content="Stop losing customer inquiries across WhatsApp, Instagram, and spreadsheets. Track which ads drive real buyers, follow up on time, and grow stress-free.">
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

            <button type="button" class="lp-mobile-toggle" data-lp-nav-toggle aria-label="Open menu"
                aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>

            <ul class="lp-nav-links">
                <li><a href="#why-us">Why Us</a></li>
                <li><a href="#who-it-helps">B2B &amp; B2C</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#plans">Pricing</a></li>
                <li><a href="#offers">Offers</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>

            <div class="lp-nav-actions">
                @include('partials.language-switcher', ['switcherId' => 'landing'])
                @auth
                    <a href="{{ route('organizations.index') }}" class="crm-btn crm-btn-secondary">{{ __('Organizations') }}</a>
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
                    <span>Built for growing Indian SMBs</span>
                </div>

                <h1 class="lp-hero-title">
                    Stop Losing Leads Across WhatsApp, Instagram &amp; Spreadsheets. <span>Know Which Ad Drives Real Buyers.</span>
                </h1>

                <p class="lp-hero-sub">
                    {{ $brandName }} captures every inquiry in one shared workspace, reminds your team to follow up on
                    time, and shows which campaigns actually close—so no buyer slips through the cracks.
                </p>

                <div class="lp-hero-ctas">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-primary">
                            <span>Go to Your Dashboard</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('organizations.index') }}" class="lp-btn-hero lp-btn-secondary">
                            <span>Switch Company</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary">
                            <span>Create Company Account</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="#plans" class="lp-btn-hero lp-btn-secondary">
                            <span>See Plans (From ₹2,499/-)</span>
                        </a>
                    @endauth
                </div>

                <div class="lp-hero-micro">
                    <span><span class="material-symbols-outlined">check_circle</span> Up &amp; running in 30 seconds</span>
                    <span><span class="material-symbols-outlined">check_circle</span> 100% private to your team</span>
                    <span><span class="material-symbols-outlined">check_circle</span> Zero surprise overage bills</span>
                </div>
            </div>

            {{-- Side-by-Side Desktop & Mobile Preview --}}
            <div class="lp-device-stage" aria-label="Desktop and mobile CRM preview">
                {{-- Desktop Browser Window --}}
                <div class="lp-preview-card lp-desktop-mockup">
                    <div class="lp-preview-top">
                        <div class="lp-preview-dots"><i></i><i></i><i></i></div>
                        <div class="lp-browser-url">
                            <span class="material-symbols-outlined" style="font-size:13px;">lock</span>
                            <span>app.leadflow.in/dashboard</span>
                        </div>
                        <span class="lp-preview-pill">● Live</span>
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
                                    <div class="lp-kpi-label">New Inquiries</div>
                                    <div class="lp-kpi-val">148</div>
                                    <div class="lp-kpi-delta">+24 today</div>
                                </div>
                                <div class="lp-kpi-box">
                                    <div class="lp-kpi-label">Open Deals</div>
                                    <div class="lp-kpi-val">₹12.4L</div>
                                    <div class="lp-kpi-delta">18 active</div>
                                </div>
                                <div class="lp-kpi-box">
                                    <div class="lp-kpi-label">Sales Won</div>
                                    <div class="lp-kpi-val">39</div>
                                    <div class="lp-kpi-delta">Verified buyers</div>
                                </div>
                            </div>

                            <div class="lp-desktop-split">
                                <div class="lp-mini-panel">
                                    <div class="lp-mini-title">Today's Follow-Ups</div>
                                    <div class="lp-mini-lead">
                                        <div>
                                            <strong>Meera Sharma</strong>
                                            <span>₹48,000 • Replied on WhatsApp</span>
                                        </div>
                                        <em class="lp-tag-green">Hot</em>
                                    </div>
                                    <div class="lp-mini-lead">
                                        <div>
                                            <strong>Aarav Builders</strong>
                                            <span>₹3.2L • Call at 4:00 PM</span>
                                        </div>
                                        <em class="lp-tag-indigo">Quote Sent</em>
                                    </div>
                                </div>

                                <div class="lp-mini-panel">
                                    <div class="lp-mini-title">Where Buyers Came From</div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head"><span>Instagram Ads</span><strong>62%</strong></div>
                                        <div class="lp-bar-track"><i style="width:62%;"></i></div>
                                    </div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head"><span>Google Search</span><strong>24%</strong></div>
                                        <div class="lp-bar-track"><i
                                                style="width:24%;background:var(--crm-success);"></i></div>
                                    </div>
                                    <div class="lp-bar-row">
                                        <div class="lp-bar-head"><span>Referral Link</span><strong>14%</strong></div>
                                        <div class="lp-bar-track"><i
                                                style="width:14%;background:var(--crm-tertiary);"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile Phone Mockup --}}
                <div class="lp-mobile-mockup" aria-label="Mobile view preview">
                    <div class="lp-phone-notch"></div>
                    <div class="lp-phone-status">
                        <span>9:41</span>
                        <span>5G • 100%</span>
                    </div>
                    <div class="lp-phone-screen">
                        <div class="lp-phone-head">
                            <strong>My Sales</strong>
                            <span class="lp-phone-dot">● On the go</span>
                        </div>

                        <div class="lp-phone-alert">
                            <span class="material-symbols-outlined">notifications_active</span>
                            <div>
                                <strong>New Inquiry!</strong>
                                <p>Rahul Verma clicked your Instagram ad just now.</p>
                            </div>
                        </div>

                        <div class="lp-phone-actions">
                            <span><span class="material-symbols-outlined">call</span> Call</span>
                            <span><span class="material-symbols-outlined">chat</span> WhatsApp</span>
                        </div>

                        <div class="lp-phone-card">
                            <div class="lp-phone-card-top">
                                <strong>Priya Textiles</strong>
                                <em>₹85,000</em>
                            </div>
                            <span>Stage: Waiting for payment</span>
                        </div>

                        <div class="lp-phone-quota">
                            <div class="lp-bar-head"><span>Monthly Leads</span><strong>80%</strong></div>
                            <div class="lp-bar-track"><i style="width:80%;background:var(--crm-tertiary);"></i></div>
                            <small>Owner alerted via Email &amp; WhatsApp</small>
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
                    <strong>100% Private Workspace</strong>
                    <span>Only your invited staff can ever access your customer list</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">fact_check</span>
                </div>
                <div>
                    <strong>True Ad Attribution</strong>
                    <span>Track closed revenue back to the exact ad—not just clicks</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">notifications_active</span>
                </div>
                <div>
                    <strong>Protected Monthly Caps</strong>
                    <span>Pre-limit alerts and safe auto-pause prevent unexpected charges</span>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon">
                    <span class="material-symbols-outlined">chat</span>
                </div>
                <div>
                    <strong>Instant WhatsApp Handoff</strong>
                    <span>Route inquiries straight to reps with one-tap WhatsApp replies</span>
                </div>
            </div>
        </div>
    </section>

    {{-- B2B vs. B2C Switcher --}}
    <section id="who-it-helps" class="lp-section">
        <div class="lp-container">
            <div class="lp-section-head">
                <h2>One Workspace for Multi-Touch B2B Deals &amp; Fast B2C Inquiries</h2>
                <p>Switch between sales motions below to see how {{ $brandName }} adapts to the way your team closes.</p>
            </div>

            <div style="text-align:center;">
                <div class="lp-mode-tabs" role="tablist" aria-label="Business Types">
                    <button type="button" class="lp-mode-tab is-active" data-lp-mode-btn="b2b" role="tab"
                        aria-selected="true">Selling to Businesses (B2B)</button>
                    <button type="button" class="lp-mode-tab" data-lp-mode-btn="b2c" role="tab"
                        aria-selected="false">Selling to Direct Buyers (B2C)</button>
                </div>
            </div>

            <div class="lp-mode-panel is-active" data-lp-mode-panel="b2b">
                <div>
                    <h3 class="lp-display" style="font-size:1.5rem;margin-bottom:0.65rem;">Keep Multi-Week Deals Moving Forward</h3>
                    <p class="crm-muted" style="line-height:1.65;">
                        When a sale spans calls, quotes, and multiple stakeholders at a company, context gets lost in
                        personal chats. Keep every decision-maker, quote, and follow-up date visible to your team.
                    </p>
                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span> Group multiple contacts under one company account</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Track deals stage-by-stage from Inquiry to Paid</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Spot stalled deals and assign manager follow-ups</li>
                    </ul>
                </div>
                <div class="lp-mode-steps">
                    <div class="lp-step-card">
                        <span class="lp-step-num">1</span>
                        <div>
                            <strong>Convert inquiries into deals in one click</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">No re-typing phone numbers, company names, or call notes.</div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">2</span>
                        <div>
                            <strong>Collaborate with role-based permissions</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">Control who can view, edit, export, or manage deals.</div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">3</span>
                        <div>
                            <strong>Review monthly pipeline &amp; revenue forecasts</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">Export clean deal summaries anytime with one click.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lp-mode-panel" data-lp-mode-panel="b2c">
                <div>
                    <h3 class="lp-display" style="font-size:1.5rem;margin-bottom:0.65rem;">Reply in Seconds to High-Volume Inquiries</h3>
                    <p class="crm-muted" style="line-height:1.65;">
                        When shoppers message from Instagram, Facebook, Google Ads, or your website, the first business
                        to reply wins the sale. Route and respond before buyers go cold.
                    </p>
                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span> Branded inquiry links &amp; forms ready for any ad</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Auto-assign incoming leads across available reps</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Reach buyers fast via WhatsApp, Call, or Email</li>
                    </ul>
                </div>
                <div class="lp-mode-steps">
                    <div class="lp-step-card">
                        <span class="lp-step-num">1</span>
                        <div>
                            <strong>Capture every ad inquiry automatically</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">Every form fill lands in your team's live inbox instantly.</div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">2</span>
                        <div>
                            <strong>Get notified before a buyer cools off</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">Instant alerts and follow-up reminders keep response times under 5 minutes.</div>
                        </div>
                    </div>
                    <div class="lp-step-card">
                        <span class="lp-step-num">3</span>
                        <div>
                            <strong>See which campaigns convert to cash</strong>
                            <div class="crm-muted" style="font-size:0.84rem;">Compare cost-per-sale across Instagram, Google, and referrals.</div>
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
                <div class="lp-eyebrow">Core Capabilities</div>
                <h2>Everything Your Sales Team Needs—Nothing It Doesn't</h2>
                <p>Built for speed and daily adoption, with zero bloat or steep learning curves.</p>
            </div>

            <div class="lp-bento">
                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">link</span></div>
                    <h3>Ad Tracking Links &amp; Forms</h3>
                    <p>Generate UTM-tagged inquiry links and lightweight forms for Instagram, Google, and WhatsApp ads to see which campaigns bring real buyers.</p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">forum</span></div>
                    <h3>WhatsApp Business Workflow</h3>
                    <p>Trigger instant WhatsApp lead alerts to reps, send pre-approved reply templates, and manage conversations from a shared team workflow—or start with your existing WhatsApp number.</p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">view_kanban</span></div>
                    <h3>Visual Sales Pipeline</h3>
                    <p>Drag deals across custom stages, schedule call-back reminders, and see every overdue follow-up at a glance each morning.</p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">tune</span></div>
                    <h3>No-Code Custom Fields</h3>
                    <p>Add fields like City, Budget, Property Type, or Course Interest in seconds—tailored to your workflow without developer help.</p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">monitoring</span></div>
                    <h3>Team Speed &amp; ROI Reports</h3>
                    <p>Track rep response times, weekly conversion rates, and revenue by source, with one-click CSV/spreadsheet exports.</p>
                </article>

                <article class="lp-feature-card">
                    <div class="lp-feature-icon"><span class="material-symbols-outlined">group</span></div>
                    <h3>Role-Based Team Access</h3>
                    <p>Invite sales reps and managers in clicks, and control exactly who can view, edit, or export customer records.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- Pricing & Quota Protection --}}
    <section id="plans" class="lp-section">
        <div class="lp-container">
            <div class="lp-section-head">
                <div class="lp-eyebrow">Transparent Pricing &amp; Quota Protection</div>
                <h2>Simple Plans With Built-In Bill Protection</h2>
                <p>Know your exact monthly leads, included seats, and overage options upfront. We warn you before you hit your cap and pause safely—never billing without your go-ahead.</p>
            </div>

            <div class="lp-pricing-grid">
                {{-- Starter Plan --}}
                <div class="lp-plan-card">
                    <h3 class="lp-plan-name">Starter Plan</h3>
                    <p class="crm-muted" style="margin:0;">Ideal for growing teams organizing ad inquiries and daily follow-ups.</p>

                    <div class="lp-plan-price">
                        <span class="lp-plan-amount">₹2,499/-</span>
                        <span class="lp-plan-period">per month</span>
                    </div>

                    <div class="lp-plan-limits">
                        <div><span>Monthly Leads Included</span><strong>[X leads/mo]</strong></div>
                        <div><span>Team Seats Included</span><strong>[Y seats]</strong></div>
                        <div><span>Extra Team Seat</span><strong>₹[Z] / seat / mo</strong></div>
                    </div>

                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span> Full B2B &amp; B2C pipeline &amp; contact manager</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Ad tracking links &amp; embeddable inquiry forms</li>
                        <li><span class="material-symbols-outlined">check_circle</span> WhatsApp lead handoff, Email &amp; Phone logging</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Pre-limit alerts &amp; auto-pause on cap reach</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Optional industry pay-per-lead top-ups</li>
                    </ul>

                    <div class="lp-plan-cta">
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-secondary"
                            style="width:100%;justify-content:center;">
                            <span>Choose Starter (₹2,499/-)</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    </div>
                </div>

                {{-- Gold Plan --}}
                <div class="lp-plan-card is-featured">
                    <span class="lp-plan-ribbon">Best Value</span>
                    <h3 class="lp-plan-name">Gold Plan</h3>
                    <p class="crm-muted" style="margin:0;">For active sales teams needing higher lead volume, more seats, and lower overage rates.</p>

                    <div class="lp-plan-price">
                        <span class="lp-plan-amount">₹4,999/-</span>
                        <span class="lp-plan-period">per month</span>
                    </div>

                    <div class="lp-plan-limits">
                        <div><span>Monthly Leads Included</span><strong>[X2 leads/mo]</strong></div>
                        <div><span>Team Seats Included</span><strong>[Y2 seats]</strong></div>
                        <div><span>Extra Team Seat</span><strong>₹[Z] / seat / mo</strong></div>
                    </div>

                    <ul class="lp-check-list">
                        <li><span class="material-symbols-outlined">check_circle</span> Everything in Starter with expanded lead &amp; seat quotas</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Lower per-lead overage rate across all industries</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Shared WhatsApp Business templates &amp; team reports</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Priority pre-limit alerts &amp; instant top-up controls</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Priority onboarding &amp; dedicated support</li>
                    </ul>

                    <div class="lp-plan-cta">
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary"
                            style="width:100%;justify-content:center;">
                            <span>Choose Gold (₹4,999/-)</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Quota Protection & Interactive Industry Overage Switcher --}}
            <div class="lp-overage-box">
                <div class="lp-overage-grid">
                    <div>
                        <div class="lp-eyebrow" style="margin-bottom:0.65rem;">No Surprise Bills Guarantee</div>
                        <h3 class="lp-display" style="font-size:1.3rem;margin-bottom:0.65rem;">
                            What Happens When You Reach Your Monthly Lead Cap?
                        </h3>
                        <ul class="lp-protection-list">
                            <li>
                                <span class="material-symbols-outlined">notifications_active</span>
                                <div>
                                    <strong>Pre-Limit Warnings:</strong> The Business Owner (Org Admin) is alerted before
                                    your quota runs out—and again at 100%—via <strong>Email, SMS, WhatsApp, and Push</strong>.
                                </div>
                            </li>
                            <li>
                                <span class="material-symbols-outlined">pause_circle</span>
                                <div>
                                    <strong>Safe Auto-Pause:</strong> New lead capture pauses automatically at your cap so
                                    you are never hit with unexpected overage charges.
                                </div>
                            </li>
                            <li>
                                <span class="material-symbols-outlined">bolt</span>
                                <div>
                                    <strong>Optional Pay-Per-Lead Top-Up:</strong> Running a weekend campaign? Unlock
                                    extra leads on demand at your industry rate or upgrade anytime.
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="lp-industry-panel">
                        <div class="lp-industry-head">
                            <span>Check Pay-Per-Lead Top-Up Rate by Industry</span>
                            <small>Pick your industry to compare Starter vs. Gold overage rates</small>
                        </div>

                        <div class="lp-industry-pills" role="tablist" aria-label="Select your industry">
                            <button type="button" class="lp-industry-pill is-active" data-lp-industry="saas" role="tab" aria-selected="true">Software &amp; IT</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="realestate" role="tab" aria-selected="false">Real Estate</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="education" role="tab" aria-selected="false">Coaching &amp; Schools</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="ecommerce" role="tab" aria-selected="false">Online Store &amp; Retail</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="healthcare" role="tab" aria-selected="false">Clinics &amp; Health</button>
                            <button type="button" class="lp-industry-pill" data-lp-industry="finance" role="tab" aria-selected="false">Loans &amp; Finance</button>
                        </div>

                        <div class="lp-industry-result-card" data-lp-industry-result aria-live="polite">
                            <div class="lp-industry-selected-name" data-lp-industry-label>Software &amp; IT Services</div>
                            <div class="lp-rate-compare">
                                <div class="lp-rate-tier">
                                    <span>Starter Plan</span>
                                    <strong data-lp-rate-starter>₹25 / extra lead</strong>
                                </div>
                                <div class="lp-rate-tier is-gold">
                                    <span>Gold Plan</span>
                                    <strong data-lp-rate-gold>₹18 / extra lead</strong>
                                </div>
                            </div>
                            <p class="lp-rate-note">Only charged if you choose to top up after your monthly quota pauses.</p>
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
                        <span>For Organization Admins (Business Owners)</span>
                    </div>
                    <h2>Claim Your Welcome Coupon &amp; Earn B2B Referral Rewards</h2>
                    <p>
                        Registering your company for the first time? A personal <strong>welcome discount coupon</strong>
                        is issued automatically on sign-up—apply it right away on Starter or Gold.
                    </p>
                    <p style="font-size:0.92rem;color:rgba(255,255,255,0.9);">
                        Already an Org Admin? Share your <strong>B2B referral link</strong> with fellow business owners
                        to earn referral credits whenever a new company joins through your link.
                    </p>

                    <div class="lp-offer-pills">
                        <span class="lp-offer-pill">✓ Auto-Issued Welcome Coupon</span>
                        <span class="lp-offer-pill">✓ B2B Referral Link in Dashboard</span>
                        <span class="lp-offer-pill">✓ Org Admins Only (Excludes Staff Seats)</span>
                    </div>
                </div>

                <div class="lp-offer-card">
                    <div
                        style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;opacity:0.9;margin-bottom:0.4rem;">
                        Org Admin Perks
                    </div>
                    <h3 style="font-family:var(--crm-font-display);font-size:1.25rem;margin:0 0 0.75rem;color:#fff;">
                        Unlock Your First-Time Savings
                    </h3>
                    <p style="font-size:0.86rem;margin:0 0 1.15rem;color:rgba(255,255,255,0.9);">
                        Welcome coupons and referral rewards apply exclusively to Organization Admin accounts creating or
                        referring a company workspace—never to invited staff seats.
                    </p>
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-white">
                            <span>Open Dashboard for Referral Link</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-white">
                            <span>Register &amp; Claim Welcome Coupon</span>
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('login') }}" class="lp-btn-hero lp-btn-ghost">
                            <span>Log In for Your Referral Link</span>
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
                <div class="lp-eyebrow">Customer Stories</div>
                <h2>Trusted by Sales Teams Across India</h2>
                <p>See how SMB owners cut response times, eliminate missed follow-ups, and track real ad ROI.</p>
            </div>

            <div class="lp-testimonials">
                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">trending_up</span>
                            <span>Zero missed follow-ups in 4 months</span>
                        </div>
                        <p class="lp-quote-text">
                            “Before this, we couldn't tell which Instagram campaign brought paying clients versus junk
                            clicks. Now every inquiry is tagged to its ad, and my 6-person team hasn't missed a single
                            callback.”
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">RD</span>
                        <div>
                            <strong>Rohan Deshmukh</strong>
                            <span>Founder, NexaSoft Solutions • Pune (IT Services)</span>
                        </div>
                    </div>
                </article>

                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">bolt</span>
                            <span>Under 5-min weekend lead response</span>
                        </div>
                        <p class="lp-quote-text">
                            “Our admission ads spiked on Sunday evening. Instead of a surprise invoice, I got a WhatsApp
                            and Email alert at 80%, topped up extra leads in two clicks, and closed 11 enrollments that
                            night.”
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">AN</span>
                        <div>
                            <strong>Ananya Nair</strong>
                            <span>Director, SkillBridge Academy • Kochi (EdTech)</span>
                        </div>
                    </div>
                </article>

                <article class="lp-quote-card">
                    <div>
                        <div class="lp-quote-metric">
                            <span class="material-symbols-outlined">groups</span>
                            <span>14 reps onboarded in one afternoon</span>
                        </div>
                        <p class="lp-quote-text">
                            “We handle both wholesale B2B distributors and direct retail buyers in the same portal. All
                            14 of my sales reps learned the pipeline board in one afternoon without formal training.”
                        </p>
                    </div>
                    <div class="lp-quote-author">
                        <span class="lp-author-avatar" aria-hidden="true">VM</span>
                        <div>
                            <strong>Vikram Mehta</strong>
                            <span>Sales Head, Mehta Looms • Surat (Wholesale &amp; Retail)</span>
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
                <div class="lp-eyebrow">Common Questions</div>
                <h2>Everything You Might Be Wondering</h2>
            </div>

            <div class="lp-faq-list">
                <div class="lp-faq-item is-open">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="true">
                        <span>What happens when my monthly lead limit is reached?</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        New lead capture pauses automatically so you never receive an unexpected bill. Before your quota
                        runs out—and again when it hits 100%—the Business Owner (Org Admin) receives alerts via Email,
                        SMS, WhatsApp, and Push Notification. You can top up extra leads at your industry rate or upgrade
                        to Gold (₹4,999/-) anytime.
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>How do team seats and extra member fees work?</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        Starter (₹2,499/-) includes <strong>[Y seats]</strong> and Gold (₹4,999/-) includes
                        <strong>[Y2 seats]</strong>. If your team grows beyond your plan's included seats, you can add
                        extra staff members anytime for a fixed <strong>₹[Z] per seat/month</strong>.
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>Who is eligible for the welcome coupon and referral rewards?</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        Welcome coupons and B2B referral rewards are strictly for <strong>Organization Admins (Business
                            Owners)</strong>—invited staff or team members are not eligible. Your welcome coupon is
                        generated automatically on first-time company registration, and referral credits apply when
                        another business registers through your Org Admin referral link.
                    </div>
                </div>

                <div class="lp-faq-item">
                    <button type="button" class="lp-faq-btn" data-lp-faq-btn aria-expanded="false">
                        <span>How does WhatsApp integration work—do I need an API setup to start?</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div class="lp-faq-body">
                        No complex setup is required to begin. You can start immediately using your existing business
                        WhatsApp number for instant lead handoff alerts and one-tap replies, alongside Email and Phone
                        tracking. When you're ready, you can enable official WhatsApp Business templates and shared team
                        inbox workflows anytime.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final Call to Action --}}
    <section class="lp-section">
        <div class="lp-container">
            <div class="lp-final-cta">
                <h2>Ready to Turn More Inquiries Into Paying Customers?</h2>
                <p>
                    Create your company workspace today, organize every B2B and B2C inquiry, and follow up faster with
                    zero surprise bills.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:0.85rem;justify-content:center;">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="lp-btn-hero lp-btn-primary">
                            <span>Go to Dashboard</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="lp-btn-hero lp-btn-primary">
                            <span>Create Your Account Now</span>
                            <span class="material-symbols-outlined" style="font-size:19px;">arrow_forward</span>
                        </a>
                        <a href="{{ route('login') }}" class="lp-btn-hero lp-btn-secondary">
                            <span>Already Registered? Log In</span>
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
                <strong>{{ $brandName }}</strong> &copy; {{ date('Y') }}. Simple Customer &amp; Sales Management for
                Growing Businesses.
            </div>
            <div class="lp-footer-links">
                <a href="#why-us">Why Us</a>
                <a href="#features">Features</a>
                <a href="#plans">Pricing</a>
                <a href="#offers">Offers</a>
                @auth
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Log In</a>
                    <a href="{{ route('register') }}">Register</a>
                @endauth
            </div>
        </div>
    </footer>

    @include('partials.language-selection-popup')

    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/landing.js') }}"></script>
</body>

</html>
