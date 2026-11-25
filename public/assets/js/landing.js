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

        // Interactive industry pills for Starter vs Gold overage rates
        var industryRates = {
            'saas': { label: 'Software & IT Services', starter: '₹25 / extra lead', gold: '₹18 / extra lead' },
            'realestate': { label: 'Real Estate & Property', starter: '₹45 / extra lead', gold: '₹32 / extra lead' },
            'education': { label: 'Coaching, Courses & Schools', starter: '₹15 / extra lead', gold: '₹10 / extra lead' },
            'ecommerce': { label: 'Online Store & Retail', starter: '₹12 / extra lead', gold: '₹8 / extra lead' },
            'healthcare': { label: 'Clinics & Healthcare', starter: '₹30 / extra lead', gold: '₹22 / extra lead' },
            'finance': { label: 'Loans, Insurance & Finance', starter: '₹50 / extra lead', gold: '₹35 / extra lead' }
        };

        $('[data-lp-industry]').on('click', function () {
            var key = $(this).data('lp-industry');
            var info = industryRates[key] || industryRates['saas'];

            $('[data-lp-industry]').removeClass('is-active').attr('aria-selected', 'false');
            $(this).addClass('is-active').attr('aria-selected', 'true');

            $('[data-lp-industry-label]').text(info.label);
            $('[data-lp-rate-starter]').text(info.starter);
            $('[data-lp-rate-gold]').text(info.gold);
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
