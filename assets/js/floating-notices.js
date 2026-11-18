/**
 * All-IN Floating Notices for WooCommerce - JavaScript
 * Version: 1.0.0
 */

(function($, window, document) {
    'use strict';
    
    // Plugin object
    var AinfnFloatingNotices = {

        // Settings from localized script
        settings: window.ainfn_params || {},

        // Notification configuration
        notificationConfig: (window.ainfn_params && window.ainfn_params.notification_config) || {},

        // Custom notifications
        customNotifications: (window.ainfn_params && window.ainfn_params.custom_notifications) || {},

        // Detected notifications
        detectedNotifications: (window.ainfn_params && window.ainfn_params.detected_notifications) || {},

        // Auto-detection enabled
        enableAutoDetection: (window.ainfn_params && window.ainfn_params.enable_auto_detection) || false,

        // Containers keyed by "desktopPosition|mobilePosition"
        containers: {},

        // Active notices
        notices: {},

        // Notice counter
        noticeCounter: 0,

        // Device detection
        isMobile: function() {
            return window.innerWidth <= 768;
        },

        // Initialize the plugin
        init: function() {
            this.getContainer();
            this.interceptNotices();
            this.bindEvents();
            this.setupMutationObserver();
        },

        // Get (or lazily create) a container for the given position pair.
        // One container per position so notices with custom positions never
        // destroy or move notices that are already on screen. Desktop/mobile
        // switching is handled purely by the CSS media queries.
        getContainer: function(desktopPosition, mobilePosition) {
            desktopPosition = desktopPosition || this.settings.position || 'top-right';
            mobilePosition = mobilePosition || this.settings.mobile_position || 'top-full';

            var key = desktopPosition + '|' + mobilePosition;
            var existing = this.containers[key];
            if (existing && existing.closest('body').length) {
                return existing;
            }

            var containerClass = 'floating-notices-container ' + desktopPosition;
            if (mobilePosition !== 'same') {
                containerClass += ' mobile-' + mobilePosition;
            }

            var $container = $('<div></div>').addClass(containerClass);
            $('body').append($container);
            this.containers[key] = $container;
            return $container;
        },

        // Get notification configuration by type
        getNotificationConfigByType: function(noticeType, message) {
            // Try to match notification to a specific config
            var config = null;
            var lowerMessage = message.toLowerCase();

            // Match based on message content. Coupon errors are checked first: an
            // error such as "Sorry, it seems the coupon "X" is invalid - it has now
            // been removed from your order." also contains "removed", and must use
            // the Invalid Coupon setting rather than Coupon Removed.
            if (noticeType === 'error' && lowerMessage.includes('coupon')) {
                config = this.notificationConfig['coupon_invalid'];
            } else if (lowerMessage.includes('added to') && lowerMessage.includes('cart')) {
                config = this.notificationConfig['cart_add'];
            } else if (lowerMessage.includes('removed from cart')) {
                config = this.notificationConfig['cart_remove'];
            } else if (lowerMessage.includes('cart') && lowerMessage.includes('updated')) {
                config = this.notificationConfig['cart_update'];
            } else if (lowerMessage.includes('coupon') && (lowerMessage.includes('applied') || lowerMessage.includes('removed'))) {
                config = lowerMessage.includes('applied') ?
                    this.notificationConfig['coupon_applied'] :
                    this.notificationConfig['coupon_removed'];
            } else if (lowerMessage.includes('out of stock')) {
                // Must be checked before the generic 'stock' match
                config = this.notificationConfig['out_of_stock'];
            } else if (lowerMessage.includes('stock')) {
                config = this.notificationConfig['stock_warning'];
            } else {
                // Fall back to general notification type
                config = this.notificationConfig['general_' + noticeType];
            }

            return config || { enabled: true, timing: 'default', position: 'default' };
        },

        // Treat both booleans and the "false"/"0" strings that legacy saved
        // configs may contain as disabled.
        isDisabled: function(config) {
            if (!config || config.enabled === undefined) {
                return false;
            }
            return config.enabled === false || config.enabled === 'false' || config.enabled === '0' || config.enabled === 0;
        },

        // Get notice type from WooCommerce class
        getNoticeType: function(className) {
            if (className.includes('error')) return 'error';
            if (className.includes('message')) return 'success';
            if (className.includes('info')) return 'info';
            if (className.includes('warning')) return 'warning';
            return 'info';
        },
        
        // Get icon SVG based on notice type
        getIcon: function(type) {
            var icons = {
                success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
                warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
            };
            return icons[type] || icons.info;
        },
        
        // Create a floating notice
        createNotice: function(message, type, customConfig) {
            var self = this;

            // Get notification configuration
            var config = this.getNotificationConfigByType(type, message);

            // Merge with custom config if provided
            if (customConfig) {
                config = $.extend({}, config, customConfig);
            }

            // Check if notification is enabled
            if (this.isDisabled(config)) {
                return; // Don't show disabled notifications
            }

            var id = 'ainfn-notice-' + (++this.noticeCounter);
            var icon = this.getIcon(type);

            // Determine animation style
            var animation = this.settings.animation;
            if (config && config.animation === 'custom' && config.animation_style) {
                animation = config.animation_style;
            }
            var animationClass = 'animation-' + animation;

            // Determine position for this notification. Only explicit custom
            // config (custom/detected notifications) or a per-notification
            // "custom" position may override the global setting - the config
            // defaults must not shadow it.
            var desktopPosition = this.settings.position;
            var mobilePosition = this.settings.mobile_position;
            if (customConfig && (customConfig.desktop_position || customConfig.mobile_position)) {
                desktopPosition = customConfig.desktop_position || desktopPosition;
                mobilePosition = customConfig.mobile_position || mobilePosition;
            } else if (config && config.position === 'custom') {
                desktopPosition = config.desktop_position || desktopPosition;
                mobilePosition = config.mobile_position || mobilePosition;
            }
            var $container = this.getContainer(desktopPosition, mobilePosition);

            // Determine animation direction
            if (String(desktopPosition).includes('left')) {
                animationClass += ' from-left';
            }

            // Determine auto-dismiss time (same precedence as position)
            var autoDismiss = this.settings.auto_dismiss;
            if (customConfig && customConfig.auto_dismiss !== undefined && customConfig.auto_dismiss !== null) {
                autoDismiss = customConfig.auto_dismiss;
            } else if (config && config.timing === 'custom' && config.auto_dismiss !== undefined) {
                autoDismiss = config.auto_dismiss;
            }
            
            var noticeHtml = 
                '<div id="' + id + '" class="floating-notice ' + type + ' ' + animationClass + '">' +
                    '<div class="floating-notice-icon">' + icon + '</div>' +
                    '<div class="floating-notice-content">' + message + '</div>' +
                    '<button class="floating-notice-close" aria-label="Close">' +
                        '<svg viewBox="0 0 20 20" fill="currentColor">' +
                            '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>' +
                        '</svg>' +
                    '</button>';
            
            // Add progress bar if enabled
            if (this.settings.show_progress === 'yes' && autoDismiss > 0) {
                noticeHtml += '<div class="floating-notice-progress"></div>';
            }

            noticeHtml += '</div>';

            var $notice = $(noticeHtml);

            // Add CSS variable for progress duration
            if (this.settings.show_progress === 'yes' && autoDismiss > 0) {
                $notice.addClass('has-progress');
                $notice[0].style.setProperty('--progress-duration', autoDismiss + 'ms');
            }

            // Append to container
            $container.append($notice);

            // Store notice reference
            this.notices[id] = $notice;

            // Set up auto-dismiss
            if (autoDismiss > 0) {
                this.notices[id].dismissTimer = setTimeout(function() {
                    self.removeNotice(id);
                }, autoDismiss);
            }
            
            // Bind close button
            $notice.find('.floating-notice-close').on('click', function(e) {
                e.preventDefault();
                self.removeNotice(id);
            });
            
            // Pause auto-dismiss on hover
            if (autoDismiss > 0) {
                $notice.on('mouseenter', function() {
                    if (self.notices[id] && self.notices[id].dismissTimer) {
                        clearTimeout(self.notices[id].dismissTimer);
                        $notice.find('.floating-notice-progress').css('animation-play-state', 'paused');
                    }
                }).on('mouseleave', function() {
                    if (self.notices[id]) {
                        self.notices[id].dismissTimer = setTimeout(function() {
                            self.removeNotice(id);
                        }, 2000); // Give 2 more seconds after mouse leave
                        $notice.find('.floating-notice-progress').css('animation-play-state', 'running');
                    }
                });
            }

            return $notice;
        },

        // Display a custom notification by ID
        displayCustomNotification: function(notificationId) {
            var notification = this.customNotifications[notificationId];
            if (!notification || this.isDisabled(notification)) {
                console.log('AINFN: Custom notification not found or disabled:', notificationId);
                return;
            }

            // Create notice with custom settings
            var config = {
                auto_dismiss: notification.auto_dismiss || this.settings.auto_dismiss,
                desktop_position: notification.desktop_position || this.settings.position,
                mobile_position: notification.mobile_position || this.settings.mobile_position
            };

            this.createNotice(notification.message, notification.type, config);
        },
        
        // Remove a notice
        removeNotice: function(id) {
            var self = this;
            var $notice = this.notices[id];
            
            if ($notice && !$notice.hasClass('removing')) {
                // Clear timer if exists
                if ($notice.dismissTimer) {
                    clearTimeout($notice.dismissTimer);
                }
                
                // Add removing class for animation
                $notice.addClass('removing');
                
                // Remove after animation
                setTimeout(function() {
                    $notice.remove();
                    delete self.notices[id];
                }, 300);
            }
        },
        
        // Process WooCommerce notice element
        processNotice: function($element) {
            var self = this;
            var type = this.getNoticeType($element.attr('class') || '');
            var message = '';

            // Extract message content
            if ($element.find('li').length) {
                // Handle list notices
                var messages = [];
                $element.find('li').each(function() {
                    messages.push($(this).html());
                });
                message = messages.join('<br>');
            } else {
                // Handle single message
                message = $element.html();

                // Remove any button elements but keep the text
                var $tempDiv = $('<div>').html(message);
                $tempDiv.find('button, .button').remove();
                message = $tempDiv.html();
            }

            // Clean up the message
            message = message.trim();

            if (message) {
                // Check if this notification is in detected notifications
                var messageHash = this.md5(message);
                var detectedNotif = this.detectedNotifications[messageHash];

                // If auto-detection is enabled and this is a new notification, save it
                if (this.enableAutoDetection && !detectedNotif) {
                    this.saveDetectedNotification(message, type, messageHash);
                }

                // Check if detected notification is disabled
                if (detectedNotif && this.isDisabled(detectedNotif)) {
                    return; // Don't show disabled detected notifications
                }

                // Use custom message if available, otherwise use original
                var displayMessage = message;
                var displayType = type;
                var customConfig = null;

                if (detectedNotif) {
                    // Use custom message if set
                    if (detectedNotif.custom_message && detectedNotif.custom_message.trim() !== '') {
                        displayMessage = detectedNotif.custom_message;
                    }

                    // Use custom type if set
                    if (detectedNotif.custom_type) {
                        displayType = detectedNotif.custom_type;
                    }

                    // Build custom config if settings exist
                    if (detectedNotif.auto_dismiss || detectedNotif.desktop_position || detectedNotif.mobile_position) {
                        customConfig = {
                            auto_dismiss: detectedNotif.auto_dismiss,
                            desktop_position: detectedNotif.desktop_position,
                            mobile_position: detectedNotif.mobile_position
                        };
                    }
                }

                this.createNotice(displayMessage, displayType, customConfig);
            }
        },

        // Simple hash for message matching
        md5: function(str) {
            var hash = 0;
            if (str.length === 0) return hash;
            for (var i = 0; i < str.length; i++) {
                var char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return 'hash_' + Math.abs(hash);
        },

        // Save detected notification to backend
        saveDetectedNotification: function(message, type, messageHash) {
            // Cache locally right away (keyed by the same hash used for
            // lookups) so the same message never triggers a second request.
            this.detectedNotifications[messageHash] = {
                message: message,
                type: type,
                enabled: true
            };

            // Only store managers report new notices (see can_detect_notifications() in PHP).
            if (!this.settings.can_detect) {
                return;
            }

            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'ainfn_save_detected_notification',
                    nonce: this.settings.nonce,
                    message: message,
                    type: type
                }
            });
        },
        
        // Intercept WooCommerce notices
        interceptNotices: function() {
            var self = this;
            
            // Process existing notices
            $('.woocommerce-error, .woocommerce-info, .woocommerce-message, .woocommerce-notice').each(function() {
                self.processNotice($(this));
                $(this).remove();
            });
        },
        
        // Set up mutation observer to catch dynamically added notices
        setupMutationObserver: function() {
            var self = this;
            
            // Check if MutationObserver is available
            if (!window.MutationObserver) {
                // Fallback for older browsers
                setInterval(function() {
                    self.interceptNotices();
                }, 1000);
                return;
            }
            
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        $(mutation.addedNodes).each(function() {
                            var $node = $(this);
                            
                            // Check if this is a notice
                            if ($node.hasClass('woocommerce-error') || 
                                $node.hasClass('woocommerce-info') || 
                                $node.hasClass('woocommerce-message') ||
                                $node.hasClass('woocommerce-notice')) {
                                self.processNotice($node);
                                $node.remove();
                            }
                            
                            // Check for notices within added nodes
                            $node.find('.woocommerce-error, .woocommerce-info, .woocommerce-message, .woocommerce-notice').each(function() {
                                self.processNotice($(this));
                                $(this).remove();
                            });
                        });
                    }
                });
            });
            
            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // WooCommerce specific events
            $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function() {
                setTimeout(function() {
                    self.interceptNotices();
                }, 100);
            });
            
            // Checkout events
            $(document.body).on('checkout_error', function() {
                setTimeout(function() {
                    self.interceptNotices();
                }, 100);
            });
            
            // Cart events
            $(document.body).on('added_to_cart removed_from_cart', function() {
                setTimeout(function() {
                    self.interceptNotices();
                }, 100);
            });
            
            // AJAX complete
            $(document).ajaxComplete(function(event, xhr, settings) {
                // Check if this is a WooCommerce AJAX request
                if (settings.url && (settings.url.indexOf('wc-ajax') !== -1 || settings.url.indexOf('woocommerce') !== -1)) {
                    setTimeout(function() {
                        self.interceptNotices();
                    }, 100);
                }
            });
            
            // Handle cart update
            $(document.body).on('updated_cart_totals', function() {
                setTimeout(function() {
                    self.interceptNotices();
                }, 100);
            });
            
            // Handle coupon events
            $(document.body).on('applied_coupon removed_coupon', function() {
                setTimeout(function() {
                    self.interceptNotices();
                }, 100);
            });

            // Auto-display custom notifications based on page conditions
            this.autoDisplayCustomNotifications();
        },

        // Auto-display custom notifications
        autoDisplayCustomNotifications: function() {
            var self = this;

            // Check each custom notification
            for (var id in this.customNotifications) {
                var notification = this.customNotifications[id];

                // Skip if disabled
                if (this.isDisabled(notification)) {
                    continue;
                }

                // Check if should display on this page
                if (this.shouldDisplayNotification(notification)) {
                    var delay = notification.trigger_delay || 0;

                    // Display after specified delay
                    (function(notif, notifId) {
                        setTimeout(function() {
                            var config = {
                                auto_dismiss: notif.auto_dismiss || self.settings.auto_dismiss,
                                desktop_position: notif.desktop_position || self.settings.position,
                                mobile_position: notif.mobile_position || self.settings.mobile_position
                            };
                            self.createNotice(notif.message, notif.type, config);
                        }, delay);
                    })(notification, id);
                }
            }
        },

        // Check if notification should display on current page
        shouldDisplayNotification: function(notification) {
            var showOn = notification.show_on || 'all';

            // Always show on all pages
            if (showOn === 'all') {
                return true;
            }

            var $body = $('body');

            // Check specific page types
            switch(showOn) {
                case 'shop':
                    return $body.hasClass('post-type-archive-product') ||
                           $body.hasClass('tax-product_cat') ||
                           $body.hasClass('tax-product_tag');

                case 'product':
                    return $body.hasClass('single-product');

                case 'cart':
                    return $body.hasClass('woocommerce-cart');

                case 'checkout':
                    return $body.hasClass('woocommerce-checkout');

                case 'account':
                    return $body.hasClass('woocommerce-account');

                case 'home':
                    return $body.hasClass('home');

                default:
                    return false;
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Initialize on all pages (for custom notifications) but only intercept WooCommerce notices on WooCommerce pages
        AinfnFloatingNotices.init();
    });

    // Also initialize after page fully loads (for late-loading elements)
    $(window).on('load', function() {
        // Only intercept WooCommerce notices on WooCommerce pages
        if ($('body').hasClass('woocommerce') || $('body').hasClass('woocommerce-page') || $('.woocommerce').length) {
            setTimeout(function() {
                AinfnFloatingNotices.interceptNotices();
            }, 500);
        }
    });
    
    // Expose to global scope for debugging and custom notification triggering
    window.AinfnFloatingNotices = AinfnFloatingNotices;

    // Expose function to display custom notifications
    window.ainfnDisplayCustomNotification = function(notificationId) {
        if (AinfnFloatingNotices.displayCustomNotification) {
            AinfnFloatingNotices.displayCustomNotification(notificationId);
        }
    };

    // Mobile/desktop position switching is handled by the CSS media queries
    // on the container classes, so no resize handling is needed.

})(jQuery, window, document);