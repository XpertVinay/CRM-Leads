(function ($) {
    'use strict';

    $(function () {
        // Mobile navigation toggle
        $('[data-lp-nav-toggle]').on('click', function () {
            var $nav = $('.lp-nav');
            var isOpen = $nav.toggleClass('is-open').hasClass('is-open');
            $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close mobile nav when clicking an anchor link
        $('.lp-nav-links a').on('click', function () {
            $('.lp-nav').removeClass('is-open');
            $('[data-lp-nav-toggle]').attr('aria-expanded', 'false');
        });

        // Smooth scrolling for in-page anchor links
        $('a[href^="#"]').on('click', function (e) {
            var targetId = $(this).attr('href');
            if (!targetId || targetId === '#') {
                return;
            }
            var $target = $(targetId);
            if ($target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $target.offset().top - 76
                }, 320);
            }
        });

        // B2B / B2C interactive workflow mode switcher
        $('[data-lp-mode-btn]').on('click', function () {
            var mode = $(this).data('lp-mode-btn');
            $('[data-lp-mode-btn]').removeClass('is-active').attr('aria-selected', 'false');
            $(this).addClass('is-active').attr('aria-selected', 'true');

            $('[data-lp-mode-panel]').removeClass('is-active');
            $('[data-lp-mode-panel="' + mode + '"]').addClass('is-active');
        });

        // Interactive industry pills: distinct Starter vs. Gold rates by industry
        var i18n = window.landingI18n || {};
        var industryLabels = i18n.industries || {};
        var extraLeadSuffix = i18n.extraLeadSuffix || ' / extra lead';
        var extraUserSuffix = i18n.extraUserSuffix || ' / extra user / mo';

        var industryProfiles = {
            'saas': {
                label: industryLabels.saas || 'Software & IT Services',
                starterLead: 300,
                goldLead: 220,
                starterSeat: 900,
                goldSeat: 700
            },
            'realestate': {
                label: industryLabels.realestate || 'Real Estate & Property',
                starterLead: 400,
                goldLead: 300,
                starterSeat: 1100,
                goldSeat: 850
            },
            'education': {
                label: industryLabels.education || 'Coaching, Courses & Schools',
                starterLead: 225,
                goldLead: 165,
                starterSeat: 750,
                goldSeat: 575
            },
            'ecommerce': {
                label: industryLabels.ecommerce || 'Online Store & Retail',
                starterLead: 200,
                goldLead: 150,
                starterSeat: 700,
                goldSeat: 550
            },
            'healthcare': {
                label: industryLabels.healthcare || 'Clinics & Healthcare',
                starterLead: 350,
                goldLead: 260,
                starterSeat: 1000,
                goldSeat: 750
            },
            'finance': {
                label: industryLabels.finance || 'Loans, Insurance & Finance',
                starterLead: 450,
                goldLead: 330,
                starterSeat: 1200,
                goldSeat: 900
            }
        };

        function formatInr(amount) {
            return '₹' + Number(amount).toLocaleString('en-IN');
        }

        $('[data-lp-industry]').on('click', function () {
            var key = $(this).data('lp-industry');
            var profile = industryProfiles[key] || industryProfiles['saas'];

            $('[data-lp-industry]').removeClass('is-active').attr('aria-selected', 'false');
            $(this).addClass('is-active').attr('aria-selected', 'true');

            $('[data-lp-industry-label]').text(profile.label);
            $('[data-lp-rate-starter]').text(formatInr(profile.starterLead) + extraLeadSuffix);
            $('[data-lp-rate-gold]').text(formatInr(profile.goldLead) + extraLeadSuffix);
            $('[data-lp-seat-starter]').text(formatInr(profile.starterSeat) + extraUserSuffix);
            $('[data-lp-seat-gold]').text(formatInr(profile.goldSeat) + extraUserSuffix);
        });

        // FAQ Accordion
        $('[data-lp-faq-btn]').on('click', function () {
            var $item = $(this).closest('.lp-faq-item');
            var willOpen = !$item.hasClass('is-open');

            $('.lp-faq-item').removeClass('is-open').find('[data-lp-faq-btn]').attr('aria-expanded', 'false');
            if (willOpen) {
                $item.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });
    });
})(jQuery);
