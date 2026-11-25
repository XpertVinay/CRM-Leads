(function ($) {
    'use strict';

    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    if (csrfToken) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
    }

    window.CrmUi = {
        flash: function (message, type) {
            type = type || 'success';
            var $container = $('#crm-flash-area');
            if (!$container.length) {
                $container = $('<div id="crm-flash-area"></div>').prependTo('.crm-content');
            }
            var cls = type === 'error' ? 'crm-alert-error' : (type === 'warning' ? 'crm-alert-warning' : 'crm-alert-success');
            var $alert = $('<div class="crm-alert ' + cls + '"></div>').text(message);
            $container.empty().append($alert);
        },

        renderErrors: function ($target, xhr) {
            var message = 'Request failed.';
            if (xhr && xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var msgs = [];
                    $.each(xhr.responseJSON.errors, function (_field, list) {
                        $.each(list, function (_idx, msg) {
                            msgs.push(msg);
                        });
                    });
                    message = msgs.join(' ');
                } else if (xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
            }
            if ($target && $target.length) {
                $target.html($('<div class="crm-alert crm-alert-error"></div>').text(message));
            } else {
                window.CrmUi.flash(message, 'error');
            }
        },

        openModal: function (selector) {
            $(selector).addClass('open');
        },

        closeModal: function (selector) {
            $(selector).removeClass('open');
        }
    };

    $(function () {
        $(document).on('click', '[data-crm-modal-close]', function () {
            $(this).closest('.crm-modal-backdrop').removeClass('open');
        });
    });
})(window.jQuery);
