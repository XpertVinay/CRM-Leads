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

    window.CRM = {
        flash: function (message, type) {
            window.CrmUi.flash(message, type);
        },
        modal: function (id) {
            $('#' + id).addClass('open');
        },
        closeModal: function (id) {
            $('#' + id).removeClass('open');
        },
        loadTimeline: function (selector) {
            var $el = $(selector);
            if (!$el.length) return;
            var type = $el.data('type');
            var id = $el.data('id');
            $.get('/ajax/activities', { activitable_type: type, activitable_id: id }, function (resp) {
                var items = resp.data || [];
                if (!items.length) {
                    $el.html('<p class="crm-muted">No activities recorded yet.</p>');
                    return;
                }
                var html = items.map(function (a) {
                    var actor = a.user ? a.user.name : 'System';
                    var ts = a.created_at ? new Date(a.created_at).toLocaleString() : '';
                    return '<div class="crm-list-item"><strong>' + a.action + '</strong> — ' + (a.description || '') + ' <span class="crm-muted">(' + actor + ', ' + ts + ')</span></div>';
                }).join('');
                $el.html(html);
            });
        }
    };

    $(function () {
        $(document).on('click', '[data-crm-modal-close]', function () {
            $(this).closest('.crm-modal-backdrop').removeClass('open');
        });

        $(document).on('click', '.crm-modal-backdrop[id]', function (e) {
            if (e.target === this) {
                $(this).removeClass('open');
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.crm-modal-backdrop[id].open').removeClass('open');
            }
        });
    });
})(window.jQuery);


(function ($) {
    'use strict';

    window.CrmCharts = {
        renderBars: function (containerSelector, items, color) {
            var $el = $(containerSelector);
            if (!$el.length) return;
            if (!items || !items.length) {
                $el.html('<p class="crm-muted">No data for selected filters.</p>');
                return;
            }
            var max = Math.max.apply(null, items.map(function (i) { return Number(i.value) || 0; })) || 1;
            var fill = color || '#4f46e5';
            var html = items.map(function (i) {
                var val = Number(i.value) || 0;
                var pct = Math.round((val / max) * 100);
                return '<div class="crm-bar-row">' +
                    '<span title="' + $('<div>').text(i.label).html() + '" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<div>').text(i.label).html() + '</span>' +
                    '<div class="crm-bar-track"><div class="crm-bar-fill" style="width:' + pct + '%;background:' + fill + ';"></div></div>' +
                    '<strong style="text-align:right;">' + val + '</strong>' +
                    '</div>';
            }).join('');
            $el.html(html);
        }
    };

    $(function () {
        var searchTimer = null;
        $('#crm-global-search-input').on('input', function () {
            var q = $(this).val().trim();
            var $panel = $('#crm-global-search-results');
            clearTimeout(searchTimer);
            if (q.length < 2) {
                $panel.hide().empty();
                return;
            }
            searchTimer = setTimeout(function () {
                $.get('/ajax/search', { q: q }, function (resp) {
                    var data = resp.data || {};
                    var html = '';
                    $.each(['leads', 'contacts', 'deals', 'campaigns'], function (_i, group) {
                        var list = data[group] || [];
                        if (list.length) {
                            html += '<div class="crm-dropdown-group">' + group + '</div>';
                            $.each(list, function (_j, item) {
                                html += '<a class="crm-dropdown-item" href="' + item.url + '"><strong>' + $('<div>').text(item.title).html() + '</strong><div class="crm-muted" style="font-size:0.78rem;">' + $('<div>').text(item.subtitle || '').html() + '</div></a>';
                            });
                        }
                    });
                    if (!html) {
                        html = '<div class="crm-muted" style="padding:0.5rem;">No matches found.</div>';
                    }
                    $panel.html(html).show();
                });
            }, 220);
        });

        function loadNotifications() {
            var $btn = $('#crm-notif-btn');
            if (!$btn.length) return;
            $.get('/ajax/notifications', function (resp) {
                var items = resp.data || [];
                var unread = items.filter(function (n) { return !n.read_at; }).length;
                var $count = $('#crm-notif-count');
                if (unread > 0) {
                    $count.text(unread).show();
                } else {
                    $count.hide();
                }
                var html = '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.25rem 0.5rem;"><strong>Notifications</strong><button type="button" id="crm-notif-read-all" class="crm-btn crm-btn-secondary crm-btn-sm">Mark all read</button></div>';
                if (!items.length) {
                    html += '<div class="crm-muted" style="padding:0.5rem;">No notifications.</div>';
                } else {
                    $.each(items.slice(0, 10), function (_i, n) {
                        var msg = (n.data && n.data.message) ? n.data.message : (n.type || 'Notification');
                        html += '<div class="crm-dropdown-item">' + $('<div>').text(msg).html() + '</div>';
                    });
                }
                $('#crm-notif-panel').html(html);
            });
        }

        $('#crm-notif-btn').on('click', function (e) {
            e.stopPropagation();
            $('#crm-notif-panel').toggle();
            loadNotifications();
        });

        $(document).on('click', '#crm-notif-read-all', function () {
            $.post('/ajax/notifications/read-all', function () {
                loadNotifications();
            });
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.crm-global-search').length) {
                $('#crm-global-search-results').hide();
            }
            if (!$(e.target).closest('.crm-notif-wrap').length) {
                $('#crm-notif-panel').hide();
            }
        });

        loadNotifications();

        // Mobile sidebar drawer toggle
        function toggleSidebar(forceOpen) {
            var $sidebar = $('#crm-sidebar');
            var $backdrop = $('#crm-sidebar-backdrop');
            if (!$sidebar.length) return;
            var isOpen = typeof forceOpen === 'boolean' ? forceOpen : !$sidebar.hasClass('open');
            $sidebar.toggleClass('open', isOpen);
            $backdrop.toggleClass('open', isOpen);
        }

        $('#crm-mobile-menu-btn, #crm-bottom-menu-btn').on('click', function () {
            toggleSidebar();
        });

        $('#crm-sidebar-backdrop').on('click', function () {
            toggleSidebar(false);
        });

        // Password visibility toggle
        $(document).on('click', '[data-toggle-password]', function () {
            var targetSelector = $(this).data('toggle-password');
            var $input = $(targetSelector);
            var $icon = $(this).find('.material-symbols-outlined');
            if (!$input.length) return;
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.text('visibility_off');
            } else {
                $input.attr('type', 'password');
                $icon.text('visibility');
            }
        });

        // Password strength indicator
        $(document).on('input', '[data-password-strength]', function () {
            var val = $(this).val() || '';
            var $bars = $($(this).data('password-strength')).find('.crm-strength-bar');
            var score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score++;
            var cls = score <= 1 ? 'weak' : (score <= 3 ? 'medium' : 'strong');
            $bars.each(function (idx) {
                $(this).removeClass('weak medium strong');
                if (val.length > 0 && idx < score) {
                    $(this).addClass(cls);
                }
            });
        });
    });
})(window.jQuery);
