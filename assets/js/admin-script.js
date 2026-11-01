/**
 * Floating Notices for WooCommerce - Admin Script
 */

jQuery(document).ready(function($) {
    'use strict';

    // Tab navigation
    $('.wfn-tab-nav a').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');

        // Remove active class from all tabs
        $('.wfn-tab-nav a').removeClass('active');
        $(this).addClass('active');

        // Remove active class from all tab content
        $('.wfn-tab-content').removeClass('active');

        // Add active class to target tab content
        $(target).addClass('active');
    });

    // Range sliders
    $('#wfn_border_radius').on('input', function() {
        $('#border_radius_value').text($(this).val() + 'px');
    });

    $('#wfn_padding').on('input', function() {
        $('#padding_value').text($(this).val() + 'px');
    });

    $('#wfn_font_size').on('input', function() {
        $('#font_size_value').text($(this).val() + 'px');
    });

    // Background type toggle
    $('.wfn-bg-type').on('change', function() {
        var type = $(this).data('type');
        var bgType = $(this).val();
        var $gradientField = $(this).closest('table').find('.wfn-gradient-color2');

        if (bgType === 'gradient') {
            $gradientField.show();
        } else {
            $gradientField.hide();
        }
    });

    // Initialize color pickers
    if ($.fn.wpColorPicker) {
        $('.wfn-color-picker').wpColorPicker();
        $('.wfn-color-picker-alpha').wpColorPicker({
            palettes: ['rgba(255,255,255,0.2)', 'rgba(0,0,0,0.2)', 'rgba(255,255,255,0.1)', 'rgba(0,0,0,0.1)']
        });
    }

    // Notification Management Functions
    var wfnNotificationConfig = {};
    var saveConfigTimeout = null;

    // Auto-save notification changes with debounce
    $(document).on('change', '.wfn-notification-enabled, .wfn-notification-timing, .wfn-notification-position', function() {
        clearTimeout(saveConfigTimeout);
        saveConfigTimeout = setTimeout(function() {
            saveNotificationConfig();
        }, 500);
    });

    function saveNotificationConfig() {
        var config = {};
        $('.wfn-notifications-table tbody tr[data-notification-key]').each(function() {
            var key = $(this).data('notification-key');
            config[key] = {
                enabled: $(this).find('.wfn-notification-enabled').is(':checked'),
                timing: $(this).find('.wfn-notification-timing').val(),
                position: $(this).find('.wfn-notification-position').val()
            };
        });

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_save_notification_config',
                nonce: wfn_admin.nonce,
                config: config
            },
            success: function(response) {
                if (response.success) {
                    showAdminNotice('✓ Notification settings saved!', 'success');
                }
            },
            error: function() {
                showAdminNotice('Error saving settings', 'error');
            }
        });
    }

    // Handle detected notifications toggle
    $(document).on('change', '.wfn-detected-notification-enabled', function() {
        var hash = $(this).data('hash');
        var enabled = $(this).is(':checked');

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_update_detected_notification',
                nonce: wfn_admin.nonce,
                hash: hash,
                data: { enabled: enabled }
            },
            success: function(response) {
                if (response.success) {
                    showAdminNotice('✓ Notification updated!', 'success');
                }
            }
        });
    });

    // Edit detected notification
    $(document).on('click', '.wfn-edit-detected-notification', function() {
        var hash = $(this).data('hash');
        openDetectedNotificationModal(hash);
    });

    // Delete detected notification
    $(document).on('click', '.wfn-delete-detected-notification', function() {
        if (!confirm('Are you sure you want to delete this detected notification?')) return;

        var hash = $(this).data('hash');
        var $row = $(this).closest('tr');

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_delete_detected_notification',
                nonce: wfn_admin.nonce,
                hash: hash
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() { $(this).remove(); });
                    showAdminNotice('✓ Notification deleted!', 'success');
                }
            }
        });
    });

    function openDetectedNotificationModal(hash) {
        // Get notification data from the row
        var $row = $('tr[data-notification-hash="' + hash + '"]');
        var name = $row.find('strong').first().text();
        var originalMessage = $row.find('.wfn-notification-preview').text();

        var $modal = $('<div class="wfn-modal"><div class="wfn-modal-content">' +
            '<div class="wfn-modal-header"><span class="wfn-modal-close">&times;</span><h2>Configure Detected Notification</h2></div>' +
            '<div class="wfn-modal-body">' +
                '<div class="wfn-form-group"><label>Custom Name (optional)</label>' +
                '<input type="text" id="wfn-detected-name" placeholder="Give this notification a custom name" /></div>' +
                '<div class="wfn-form-group"><label>Custom Message (leave empty to use original)</label>' +
                '<textarea id="wfn-detected-message" rows="3"></textarea>' +
                '<small style="color: #666;" class="wfn-detected-original"></small></div>' +
                '<div class="wfn-form-group"><label>Notification Type</label>' +
                '<select id="wfn-detected-type">' +
                    '<option value="">Keep Original</option>' +
                    '<option value="success">Success</option>' +
                    '<option value="error">Error</option>' +
                    '<option value="info">Info</option>' +
                    '<option value="warning">Warning</option>' +
                '</select></div>' +
                '<div class="wfn-form-group"><label>Auto-Dismiss Time (ms, 0 = never)</label>' +
                '<input type="number" id="wfn-detected-timing" value="5000" min="0" step="1000" /></div>' +
                '<div class="wfn-form-row">' +
                    '<div class="wfn-form-group"><label>Desktop Position</label>' +
                    '<select id="wfn-detected-desktop-pos">' +
                        '<option value="top-right">Top Right</option>' +
                        '<option value="top-left">Top Left</option>' +
                        '<option value="bottom-right">Bottom Right</option>' +
                        '<option value="bottom-left">Bottom Left</option>' +
                        '<option value="top-center">Top Center</option>' +
                    '</select></div>' +
                    '<div class="wfn-form-group"><label>Mobile Position</label>' +
                    '<select id="wfn-detected-mobile-pos">' +
                        '<option value="top-full">Top Full Width</option>' +
                        '<option value="bottom-full">Bottom Full Width</option>' +
                        '<option value="same">Same as Desktop</option>' +
                    '</select></div>' +
                '</div>' +
            '</div>' +
            '<div class="wfn-modal-footer">' +
                '<button class="button button-secondary wfn-modal-cancel">Cancel</button>' +
                '<button class="button button-primary wfn-modal-save-detected">Save</button>' +
            '</div>' +
        '</div></div>');

        // Set text values via the DOM so quotes/HTML in messages can't break the markup
        $modal.find('#wfn-detected-name').val(name);
        $modal.find('#wfn-detected-message').attr('placeholder', originalMessage);
        $modal.find('.wfn-detected-original').text('Original: ' + originalMessage);
        $modal.find('.wfn-modal-save-detected').attr('data-hash', hash);

        $('body').append($modal);
        $modal.fadeIn();

        $modal.find('.wfn-modal-close, .wfn-modal-cancel').on('click', function() {
            $modal.fadeOut(function() { $modal.remove(); });
        });

        $modal.find('.wfn-modal-save-detected').on('click', function() {
            var data = {
                custom_name: $('#wfn-detected-name').val(),
                custom_message: $('#wfn-detected-message').val(),
                custom_type: $('#wfn-detected-type').val(),
                timing: 'custom',
                auto_dismiss: parseInt($('#wfn-detected-timing').val()),
                position: 'custom',
                desktop_position: $('#wfn-detected-desktop-pos').val(),
                mobile_position: $('#wfn-detected-mobile-pos').val()
            };

            $.ajax({
                url: wfn_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wfn_update_detected_notification',
                    nonce: wfn_admin.nonce,
                    hash: hash,
                    data: data
                },
                success: function(response) {
                    if (response.success) {
                        showAdminNotice('✓ Configuration saved!', 'success');
                        location.reload();
                    }
                }
            });

            $modal.fadeOut(function() { $modal.remove(); });
        });
    }

    // Configure notification (modal)
    $(document).on('click', '.wfn-edit-notification', function() {
        var key = $(this).data('key');
        openNotificationConfigModal(key);
    });

    function openNotificationConfigModal(key) {
        // Get current configuration for this notification
        var $row = $('tr[data-notification-key="' + key + '"]');
        var timing = $row.find('.wfn-notification-timing').val();
        var position = $row.find('.wfn-notification-position').val();

        var $modal = $('<div class="wfn-modal"><div class="wfn-modal-content">' +
            '<div class="wfn-modal-header"><span class="wfn-modal-close">&times;</span><h2>Configure Notification</h2></div>' +
            '<div class="wfn-modal-body">' +
                '<div class="wfn-form-group"><label>Auto-Dismiss Time (ms, 0 = never)</label>' +
                '<input type="number" id="wfn-modal-timing" value="5000" min="0" step="1000" /></div>' +
                '<div class="wfn-form-row">' +
                    '<div class="wfn-form-group"><label>Desktop Position</label>' +
                    '<select id="wfn-modal-desktop-pos">' +
                        '<option value="top-right">Top Right</option>' +
                        '<option value="top-left">Top Left</option>' +
                        '<option value="bottom-right">Bottom Right</option>' +
                        '<option value="bottom-left">Bottom Left</option>' +
                        '<option value="top-center">Top Center</option>' +
                    '</select></div>' +
                    '<div class="wfn-form-group"><label>Mobile Position</label>' +
                    '<select id="wfn-modal-mobile-pos">' +
                        '<option value="top-full">Top Full Width</option>' +
                        '<option value="bottom-full">Bottom Full Width</option>' +
                        '<option value="same">Same as Desktop</option>' +
                    '</select></div>' +
                '</div>' +
                '<div class="wfn-form-group"><label>Animation Style</label>' +
                '<select id="wfn-modal-animation">' +
                    '<option value="slide">Slide</option>' +
                    '<option value="fade">Fade</option>' +
                    '<option value="bounce">Bounce</option>' +
                    '<option value="zoom">Zoom</option>' +
                '</select></div>' +
            '</div>' +
            '<div class="wfn-modal-footer">' +
                '<button class="button button-secondary wfn-modal-cancel">Cancel</button>' +
                '<button class="button button-primary wfn-modal-save" data-key="' + key + '">Save Configuration</button>' +
            '</div>' +
        '</div></div>');

        $('body').append($modal);
        $modal.fadeIn();

        // Auto-set timing and position to custom when opening modal
        $row.find('.wfn-notification-timing').val('custom');
        $row.find('.wfn-notification-position').val('custom');

        $modal.find('.wfn-modal-close, .wfn-modal-cancel').on('click', function() {
            $modal.fadeOut(function() { $modal.remove(); });
        });

        $modal.find('.wfn-modal-save').on('click', function() {
            var key = $(this).data('key');
            var config = {
                timing: 'custom',
                auto_dismiss: parseInt($('#wfn-modal-timing').val()),
                position: 'custom',
                desktop_position: $('#wfn-modal-desktop-pos').val(),
                mobile_position: $('#wfn-modal-mobile-pos').val(),
                animation: 'custom',
                animation_style: $('#wfn-modal-animation').val()
            };

            // Update the row
            $row.find('.wfn-notification-timing').val('custom');
            $row.find('.wfn-notification-position').val('custom');

            // Build full config and save
            var fullConfig = {};
            $('.wfn-notifications-table tbody tr[data-notification-key]').each(function() {
                var k = $(this).data('notification-key');
                if (k === key) {
                    fullConfig[k] = Object.assign({
                        enabled: $(this).find('.wfn-notification-enabled').is(':checked')
                    }, config);
                } else {
                    fullConfig[k] = {
                        enabled: $(this).find('.wfn-notification-enabled').is(':checked'),
                        timing: $(this).find('.wfn-notification-timing').val(),
                        position: $(this).find('.wfn-notification-position').val()
                    };
                }
            });

            // Save via AJAX
            $.ajax({
                url: wfn_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wfn_save_notification_config',
                    nonce: wfn_admin.nonce,
                    config: fullConfig
                },
                success: function(response) {
                    if (response.success) {
                        showAdminNotice('✓ Configuration saved!', 'success');
                    }
                }
            });

            $modal.fadeOut(function() { $modal.remove(); });
        });
    }

    // Add custom notification
    $(document).on('click', '.wfn-add-custom-notification', function() {
        openCustomNotificationModal();
    });

    // Edit custom notification
    $(document).on('click', '.wfn-edit-custom-notification', function() {
        var id = $(this).data('id');
        var data = (wfn_admin.custom_notifications || {})[id];
        if (!data) {
            alert('Could not load notification data. Please reload the page and try again.');
            return;
        }
        openCustomNotificationModal(id, data);
    });

    function openCustomNotificationModal(editId, editData) {
        var isEdit = !!editId;
        var $modal = $('<div class="wfn-modal"><div class="wfn-modal-content">' +
            '<div class="wfn-modal-header"><span class="wfn-modal-close">&times;</span><h2>' + (isEdit ? 'Edit' : 'Add') + ' Custom Notification</h2></div>' +
            '<div class="wfn-modal-body">' +
                '<div class="wfn-form-group"><label>Notification Name</label>' +
                '<input type="text" id="wfn-custom-name" placeholder="e.g., Welcome Message" /></div>' +
                '<div class="wfn-form-group"><label>Message</label>' +
                '<textarea id="wfn-custom-message" placeholder="Enter your custom message..."></textarea></div>' +
                '<div class="wfn-form-row">' +
                    '<div class="wfn-form-group"><label>Type</label>' +
                    '<select id="wfn-custom-type">' +
                        '<option value="success">Success</option>' +
                        '<option value="error">Error</option>' +
                        '<option value="info">Info</option>' +
                        '<option value="warning">Warning</option>' +
                    '</select></div>' +
                    '<div class="wfn-form-group"><label>Category</label>' +
                    '<select id="wfn-custom-category">' +
                        '<option value="general">General</option>' +
                        '<option value="custom">Custom</option>' +
                    '</select></div>' +
                '</div>' +
                '<div class="wfn-form-group"><label>Auto-Dismiss Time (ms, 0 = never)</label>' +
                '<input type="number" id="wfn-custom-timing" value="5000" min="0" step="1000" /></div>' +
                '<div class="wfn-form-row">' +
                    '<div class="wfn-form-group"><label>Desktop Position</label>' +
                    '<select id="wfn-custom-desktop-pos">' +
                        '<option value="top-right">Top Right</option>' +
                        '<option value="top-left">Top Left</option>' +
                        '<option value="bottom-right">Bottom Right</option>' +
                        '<option value="bottom-left">Bottom Left</option>' +
                        '<option value="top-center">Top Center</option>' +
                    '</select></div>' +
                    '<div class="wfn-form-group"><label>Mobile Position</label>' +
                    '<select id="wfn-custom-mobile-pos">' +
                        '<option value="top-full">Top Full Width</option>' +
                        '<option value="bottom-full">Bottom Full Width</option>' +
                        '<option value="same">Same as Desktop</option>' +
                    '</select></div>' +
                '</div>' +
                '<hr style="margin: 20px 0;">' +
                '<h4 style="margin-bottom: 15px;">Display Conditions</h4>' +
                '<div class="wfn-form-group"><label>Show On</label>' +
                '<select id="wfn-custom-show-on">' +
                    '<option value="all">All Pages</option>' +
                    '<option value="shop">Shop/Archive Pages</option>' +
                    '<option value="product">Product Pages</option>' +
                    '<option value="cart">Cart Page</option>' +
                    '<option value="checkout">Checkout Page</option>' +
                    '<option value="account">My Account</option>' +
                    '<option value="home">Homepage Only</option>' +
                '</select></div>' +
                '<div class="wfn-form-group"><label>Trigger Delay (ms, 0 = immediate)</label>' +
                '<input type="number" id="wfn-custom-delay" value="0" min="0" step="500" />' +
                '<small style="color: #666;">Delay before showing notification after page load</small></div>' +
            '</div>' +
            '<div class="wfn-modal-footer">' +
                '<button class="button button-secondary wfn-modal-cancel">Cancel</button>' +
                '<button class="button button-primary wfn-modal-save-custom">Save Notification</button>' +
            '</div>' +
        '</div></div>');

        // Prefill values via the DOM so quotes/HTML can't break the markup
        if (editData) {
            $modal.find('#wfn-custom-name').val(editData.name || '');
            $modal.find('#wfn-custom-message').val(editData.message || '');
            $modal.find('#wfn-custom-type').val(editData.type || 'success');
            $modal.find('#wfn-custom-category').val(editData.category || 'general');
            $modal.find('#wfn-custom-timing').val(editData.auto_dismiss !== undefined ? editData.auto_dismiss : 5000);
            $modal.find('#wfn-custom-desktop-pos').val(editData.desktop_position || 'top-right');
            $modal.find('#wfn-custom-mobile-pos').val(editData.mobile_position || 'top-full');
            $modal.find('#wfn-custom-show-on').val(editData.show_on || 'all');
            $modal.find('#wfn-custom-delay').val(editData.trigger_delay || 0);
        }

        $('body').append($modal);
        $modal.fadeIn();

        $modal.find('.wfn-modal-close, .wfn-modal-cancel').on('click', function() {
            $modal.fadeOut(function() { $modal.remove(); });
        });

        $modal.find('.wfn-modal-save-custom').on('click', function() {
            var notification = {
                name: $('#wfn-custom-name').val(),
                message: $('#wfn-custom-message').val(),
                type: $('#wfn-custom-type').val(),
                category: $('#wfn-custom-category').val(),
                auto_dismiss: parseInt($('#wfn-custom-timing').val()) || 0,
                desktop_position: $('#wfn-custom-desktop-pos').val(),
                mobile_position: $('#wfn-custom-mobile-pos').val(),
                show_on: $('#wfn-custom-show-on').val(),
                trigger_delay: parseInt($('#wfn-custom-delay').val()) || 0
            };

            if (!notification.name || !notification.message) {
                alert('Please fill in all required fields');
                return;
            }

            var data = {
                action: isEdit ? 'wfn_update_custom_notification' : 'wfn_add_custom_notification',
                nonce: wfn_admin.nonce,
                notification: notification
            };
            if (isEdit) {
                data.notification_id = editId;
            }

            $.ajax({
                url: wfn_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showAdminNotice(isEdit ? '✓ Custom notification updated!' : '✓ Custom notification added!', 'success');
                        location.reload(); // Reload to show the change
                    } else {
                        showAdminNotice('Error saving notification: ' + (response.data || 'unknown error'), 'error');
                    }
                },
                error: function() {
                    showAdminNotice('Error saving notification', 'error');
                }
            });

            $modal.fadeOut(function() { $modal.remove(); });
        });
    }

    // Delete custom notification
    $(document).on('click', '.wfn-delete-custom-notification', function() {
        if (!confirm('Are you sure you want to delete this custom notification?')) return;

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_delete_custom_notification',
                nonce: wfn_admin.nonce,
                notification_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() { $(this).remove(); });
                    showAdminNotice('✓ Custom notification deleted!', 'success');
                }
            }
        });
    });

    // Scan for plugins
    $(document).on('click', '.wfn-scan-plugins', function() {
        var $button = $(this);
        var $icon = $button.find('.dashicons');
        var originalText = $button.html();

        $button.prop('disabled', true);
        $icon.addClass('rotating');
        $button.html('<span class="dashicons dashicons-update rotating" style="vertical-align: middle;"></span> Scanning...');

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_scan_plugins',
                nonce: wfn_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAdminNotice('✓ ' + response.data.message, 'success');
                    if (response.data.count > 0) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showAdminNotice('Error scanning plugins', 'error');
                }
            },
            error: function() {
                showAdminNotice('Error scanning plugins', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.html(originalText);
                $icon.removeClass('rotating');
            }
        });
    });

    // Bulk actions
    $('.wfn-enable-all-notifications').on('click', function() {
        $('.wfn-notification-enabled').prop('checked', true).trigger('change');
    });

    $('.wfn-disable-all-notifications').on('click', function() {
        $('.wfn-notification-enabled').prop('checked', false).trigger('change');
    });

    $('.wfn-reset-all-notifications').on('click', function() {
        if (!confirm('Are you sure you want to reset all notifications to default settings?')) return;

        $.ajax({
            url: wfn_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wfn_save_notification_config',
                nonce: wfn_admin.nonce,
                reset: 1
            },
            success: function(response) {
                if (response.success) {
                    showAdminNotice('✓ Notifications reset to defaults!', 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    showAdminNotice('Error resetting notifications', 'error');
                }
            },
            error: function() {
                showAdminNotice('Error resetting notifications', 'error');
            }
        });
    });

    function showAdminNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wfn-admin-wrap h1').after($notice);
        setTimeout(function() {
            $notice.fadeOut(function() { $(this).remove(); });
        }, 3000);
    }

    // Preview notice function - renders a real notice in the preview area
    // using the same markup and styles as the storefront.
    window.wfnPreviewNotice = function(type) {
        var messages = {
            success: 'Product successfully added to your cart! <a href="#">View Cart</a>',
            error: 'Please select product options before adding to cart.',
            info: 'Shipping calculated at checkout based on your location.',
            warning: 'Only 3 items left in stock - order soon!'
        };

        var icons = {
            success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
            warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
        };

        var animation = $('#wfn_animation').val() || 'slide';
        var $notice = $(
            '<div class="floating-notice ' + type + ' animation-' + animation + '">' +
                '<div class="floating-notice-icon">' + icons[type] + '</div>' +
                '<div class="floating-notice-content">' + messages[type] + '</div>' +
                '<button type="button" class="floating-notice-close" aria-label="Close">' +
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
                '</button>' +
            '</div>'
        );

        $notice.find('.floating-notice-close').on('click', function() {
            $notice.remove();
        });

        $('#wfn-preview-area').append($notice);

        // Auto-dismiss the preview using the configured dismiss time
        var autoDismiss = parseInt($('#wfn_auto_dismiss').val()) || 5000;
        if (autoDismiss > 0) {
            setTimeout(function() {
                $notice.addClass('removing');
                setTimeout(function() { $notice.remove(); }, 300);
            }, autoDismiss);
        }
    };
});
