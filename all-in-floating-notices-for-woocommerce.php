<?php
/**
 * Plugin Name: All-IN Floating Notices for WooCommerce
 * Plugin URI: https://a-in.co.il/plugins/floating-notices/
 * Description: Converts all WooCommerce notices into sleek, modern floating message boxes with smooth animations
 * Version: 1.2.0
 * Author: Eli Shvarzman
 * Author URI: https://a-in.co.il/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: all-in-floating-notices-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 11.2
 * Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AINFN_VERSION', '1.2.0');
define('AINFN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AINFN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AINFN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class AINFN_Floating_Notices {
    
    /**
     * Maximum number of auto-detected notifications kept in the database.
     */
    const MAX_DETECTED_NOTIFICATIONS = 100;

    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings = array();

    /**
     * Cached option values to avoid repeated DB queries
     */
    private $cache = array();
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialize plugin
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load default settings
        $this->load_settings();
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('plugin_action_links_' . AINFN_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Add AJAX handlers for customization
        add_action('wp_ajax_ainfn_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ainfn_save_notification_config', array($this, 'ajax_save_notification_config'));
        add_action('wp_ajax_ainfn_add_custom_notification', array($this, 'ajax_add_custom_notification'));
        add_action('wp_ajax_ainfn_update_custom_notification', array($this, 'ajax_update_custom_notification'));
        add_action('wp_ajax_ainfn_delete_custom_notification', array($this, 'ajax_delete_custom_notification'));
        add_action('wp_ajax_ainfn_save_detected_notification', array($this, 'ajax_save_detected_notification'));
        // Guests are rejected in the handler unless a developer opts in (see can_detect_notifications()).
        add_action('wp_ajax_nopriv_ainfn_save_detected_notification', array($this, 'ajax_save_detected_notification'));
        add_action('wp_ajax_ainfn_update_detected_notification', array($this, 'ajax_update_detected_notification'));
        add_action('wp_ajax_ainfn_delete_detected_notification', array($this, 'ajax_delete_detected_notification'));
        add_action('wp_ajax_ainfn_scan_plugins', array($this, 'ajax_scan_plugins'));
        add_action('wp_ajax_ainfn_show_custom_notification', array($this, 'ajax_show_custom_notification'));
        add_action('wp_ajax_nopriv_ainfn_show_custom_notification', array($this, 'ajax_show_custom_notification'));

        // Register shortcode for custom notifications
        add_shortcode('ainfn_show', array($this, 'shortcode_show_notification'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('All-IN Floating Notices for WooCommerce requires WooCommerce to be installed and active.', 'all-in-floating-notices-for-woocommerce'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $defaults = array(
            'enabled' => 'yes',
            'position' => 'top-right',
            'auto_dismiss' => 5000,
            'show_progress_bar' => 'yes',
            'animation_style' => 'slide',
            'mobile_position' => 'top-full',

            // Design settings
            'design_preset' => 'modern',
            'font_family' => 'inherit',
            'font_size' => '14',
            'font_weight' => '500',
            'text_transform' => 'none',
            'border_radius' => '12',
            'padding' => '16',
            'shadow_style' => 'medium',

            // Color settings for each notice type
            'success_bg_type' => 'gradient',
            'success_bg_color1' => '#10b981',
            'success_bg_color2' => '#059669',
            'success_text_color' => '#ffffff',
            'success_icon_bg' => 'rgba(255,255,255,0.2)',

            'error_bg_type' => 'gradient',
            'error_bg_color1' => '#ef4444',
            'error_bg_color2' => '#dc2626',
            'error_text_color' => '#ffffff',
            'error_icon_bg' => 'rgba(255,255,255,0.2)',

            'info_bg_type' => 'gradient',
            'info_bg_color1' => '#3b82f6',
            'info_bg_color2' => '#2563eb',
            'info_text_color' => '#ffffff',
            'info_icon_bg' => 'rgba(255,255,255,0.2)',

            'warning_bg_type' => 'gradient',
            'warning_bg_color1' => '#f59e0b',
            'warning_bg_color2' => '#d97706',
            'warning_text_color' => '#ffffff',
            'warning_icon_bg' => 'rgba(255,255,255,0.2)',

        );

        $saved_settings = get_option('ainfn_settings', array());
        $this->settings = wp_parse_args($saved_settings, $defaults);
        // Arbitrary custom CSS is not supported (removed in 1.2.0); never carry it over.
        unset($this->settings['custom_css']);
    }

    /**
     * Get all available notification types
     */
    public function get_notification_types() {
        return array(
            'cart_add' => array(
                'name' => __('Product Added to Cart', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'cart',
                'default_enabled' => true,
            ),
            'cart_remove' => array(
                'name' => __('Product Removed from Cart', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'info',
                'category' => 'cart',
                'default_enabled' => true,
            ),
            'cart_update' => array(
                'name' => __('Cart Updated', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'cart',
                'default_enabled' => true,
            ),
            'cart_error' => array(
                'name' => __('Cart Error', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'cart',
                'default_enabled' => true,
            ),
            'checkout_validation' => array(
                'name' => __('Checkout Validation Error', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'checkout',
                'default_enabled' => true,
            ),
            'checkout_payment_error' => array(
                'name' => __('Payment Error', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'checkout',
                'default_enabled' => true,
            ),
            'order_success' => array(
                'name' => __('Order Placed Successfully', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'checkout',
                'default_enabled' => true,
            ),
            'coupon_applied' => array(
                'name' => __('Coupon Applied', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'coupon',
                'default_enabled' => true,
            ),
            'coupon_removed' => array(
                'name' => __('Coupon Removed', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'info',
                'category' => 'coupon',
                'default_enabled' => true,
            ),
            'coupon_invalid' => array(
                'name' => __('Invalid Coupon', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'coupon',
                'default_enabled' => true,
            ),
            'stock_warning' => array(
                'name' => __('Low Stock Warning', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'warning',
                'category' => 'product',
                'default_enabled' => true,
            ),
            'out_of_stock' => array(
                'name' => __('Out of Stock', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'product',
                'default_enabled' => true,
            ),
            'product_options_required' => array(
                'name' => __('Product Options Required', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'product',
                'default_enabled' => true,
            ),
            'wishlist_added' => array(
                'name' => __('Added to Wishlist', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'wishlist',
                'default_enabled' => true,
            ),
            'account_updated' => array(
                'name' => __('Account Updated', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'success',
                'category' => 'account',
                'default_enabled' => true,
            ),
            'account_error' => array(
                'name' => __('Account Error', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'account',
                'default_enabled' => true,
            ),
            'shipping_calculated' => array(
                'name' => __('Shipping Calculated', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'info',
                'category' => 'shipping',
                'default_enabled' => true,
            ),
            'general_info' => array(
                'name' => __('General Information', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'info',
                'category' => 'general',
                'default_enabled' => true,
            ),
            'general_error' => array(
                'name' => __('General Error', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'error',
                'category' => 'general',
                'default_enabled' => true,
            ),
            'general_warning' => array(
                'name' => __('General Warning', 'all-in-floating-notices-for-woocommerce'),
                'type' => 'warning',
                'category' => 'general',
                'default_enabled' => true,
            ),
        );
    }

    /**
     * Get notification configuration
     */
    public function get_notification_config() {
        if ( isset( $this->cache['notification_config'] ) ) {
            return $this->cache['notification_config'];
        }

        $default_config = array();
        $notification_types = $this->get_notification_types();

        foreach ($notification_types as $key => $notification) {
            $default_config[$key] = array(
                'enabled' => $notification['default_enabled'],
                'timing' => 'default', // 'default', 'custom'
                'auto_dismiss' => 5000,
                'position' => 'default', // 'default', 'custom'
                'desktop_position' => 'top-right',
                'mobile_position' => 'top-full',
                'animation' => 'default', // 'default', 'custom'
                'animation_style' => 'slide',
            );
        }

        $saved_config = get_option('ainfn_notification_config', array());
        $config = $default_config;

        // Merge each notification entry individually so saved partial entries
        // (e.g. only enabled/timing/position) keep their default sub-keys.
        if (is_array($saved_config)) {
            foreach ($saved_config as $key => $values) {
                if (!is_array($values)) {
                    continue;
                }
                $base = isset($config[$key]) ? $config[$key] : array();
                $values = wp_parse_args($values, $base);
                // Normalize legacy "true"/"false" strings saved by older versions
                if (isset($values['enabled']) && !is_bool($values['enabled'])) {
                    $values['enabled'] = filter_var($values['enabled'], FILTER_VALIDATE_BOOLEAN);
                }
                $config[$key] = $values;
            }
        }

        $this->cache['notification_config'] = $config;
        return $this->cache['notification_config'];
    }

    /**
     * Get custom notifications
     */
    public function get_custom_notifications() {
        if ( ! isset( $this->cache['custom_notifications'] ) ) {
            $this->cache['custom_notifications'] = get_option('ainfn_custom_notifications', array());
        }
        return $this->cache['custom_notifications'];
    }

    /**
     * Get detected notifications from any plugin
     */
    public function get_detected_notifications() {
        if ( ! isset( $this->cache['detected_notifications'] ) ) {
            $detected = get_option('ainfn_detected_notifications', array());
            if ( ! is_array( $detected ) ) {
                $detected = array();
            }
            // Normalize legacy "true"/"false" strings saved by older versions
            foreach ( $detected as $key => $notif ) {
                if ( isset( $notif['enabled'] ) && ! is_bool( $notif['enabled'] ) ) {
                    $detected[ $key ]['enabled'] = filter_var( $notif['enabled'], FILTER_VALIDATE_BOOLEAN );
                }
            }
            $this->cache['detected_notifications'] = $detected;
        }
        return $this->cache['detected_notifications'];
    }

    /**
     * Compute the same hash as the frontend JS md5() function.
     * This ensures detected notifications can be looked up by the frontend
     * without triggering redundant AJAX save calls.
     */
    private function js_compatible_hash( $str ) {
        // JS charCodeAt() works on UTF-16 code units, so convert before hashing
        // or non-ASCII messages would produce different hashes than the frontend.
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $converted = mb_convert_encoding( $str, 'UTF-16LE', 'UTF-8' );
            if ( false !== $converted ) {
                $str = $converted;
                $step = 2;
            } else {
                $step = 1;
            }
        } else {
            $step = 1;
        }

        $hash = 0;
        $len  = strlen( $str );
        for ( $i = 0; $i < $len; $i += $step ) {
            if ( 2 === $step ) {
                $char = ord( $str[ $i ] ) + ( isset( $str[ $i + 1 ] ) ? ord( $str[ $i + 1 ] ) << 8 : 0 );
            } else {
                $char = ord( $str[ $i ] );
            }
            // Replicate JS: hash = ((hash << 5) - hash) + char; hash = hash & hash (32-bit int)
            $hash = ( ( $hash << 5 ) - $hash ) + $char;
            // Force 32-bit signed integer (same as JS bitwise OR 0)
            $hash = $hash & 0xFFFFFFFF;
            if ( $hash >= 0x80000000 ) {
                $hash -= 0x100000000;
            }
        }
        return 'hash_' . abs( $hash );
    }

    /**
     * Re-key detected notifications by the frontend-compatible hash
     * so the JS lookup succeeds and doesn't fire redundant AJAX saves.
     */
    private function get_detected_notifications_for_frontend() {
        $detected = $this->get_detected_notifications();
        $frontend = array();
        foreach ( $detected as $notif ) {
            if ( empty( $notif['message'] ) ) {
                continue;
            }
            $js_hash = $this->js_compatible_hash( $notif['message'] );
            // Only ship the fields the frontend actually uses to keep page weight down
            $frontend[ $js_hash ] = array(
                'enabled'          => isset( $notif['enabled'] ) ? (bool) $notif['enabled'] : true,
                'custom_message'   => isset( $notif['custom_message'] ) ? $notif['custom_message'] : '',
                'custom_type'      => isset( $notif['custom_type'] ) ? $notif['custom_type'] : '',
                'auto_dismiss'     => isset( $notif['auto_dismiss'] ) ? intval( $notif['auto_dismiss'] ) : null,
                'desktop_position' => isset( $notif['desktop_position'] ) ? $notif['desktop_position'] : '',
                'mobile_position'  => isset( $notif['mobile_position'] ) ? $notif['mobile_position'] : '',
            );
        }
        return $frontend;
    }

    /**
     * Scan installed plugins for known notification hooks
     */
    public function scan_plugin_notifications() {
        $plugin_notifications = array();

        // Check which plugins are active
        $active_plugins = get_option('active_plugins', array());

        // SUMO Reward Points
        if ($this->is_plugin_active('sumo-reward-points/sumo-reward-points.php', $active_plugins)) {
            $plugin_notifications['sumo_reward_points'] = array(
                'name' => 'SUMO Reward Points',
                'notifications' => array(
                    array(
                        'key' => 'srp_points_earned',
                        'name' => 'Points Earned',
                        'type' => 'success',
                        'example' => 'You have earned X points',
                    ),
                    array(
                        'key' => 'srp_points_redeemed',
                        'name' => 'Points Redeemed',
                        'type' => 'success',
                        'example' => 'X points have been redeemed',
                    ),
                    array(
                        'key' => 'srp_referral_points',
                        'name' => 'Referral Points Earned',
                        'type' => 'success',
                        'example' => 'You earned referral points',
                    ),
                    array(
                        'key' => 'srp_points_expired',
                        'name' => 'Points Expired',
                        'type' => 'warning',
                        'example' => 'Your points have expired',
                    ),
                ),
            );
        }

        // PW Gift Cards
        if ($this->is_plugin_active('pw-woocommerce-gift-cards/pw-woocommerce-gift-cards.php', $active_plugins) ||
            $this->is_plugin_active('pw-gift-cards/pw-gift-cards.php', $active_plugins)) {
            $plugin_notifications['pw_gift_cards'] = array(
                'name' => 'PW Gift Cards',
                'notifications' => array(
                    array(
                        'key' => 'pwgc_applied',
                        'name' => 'Gift Card Applied',
                        'type' => 'success',
                        'example' => 'Gift card applied successfully',
                    ),
                    array(
                        'key' => 'pwgc_removed',
                        'name' => 'Gift Card Removed',
                        'type' => 'info',
                        'example' => 'Gift card has been removed',
                    ),
                    array(
                        'key' => 'pwgc_insufficient',
                        'name' => 'Insufficient Balance',
                        'type' => 'error',
                        'example' => 'Gift card has insufficient balance',
                    ),
                    array(
                        'key' => 'pwgc_invalid',
                        'name' => 'Invalid Gift Card',
                        'type' => 'error',
                        'example' => 'Invalid gift card number',
                    ),
                ),
            );
        }

        // SUMO Waitlist (SUMO Subscriptions)
        if ($this->is_plugin_active('sumo-waitlist/sumo-waitlist.php', $active_plugins) ||
            $this->is_plugin_active('waitlist-woocommerce/waitlist-woocommerce.php', $active_plugins)) {
            $plugin_notifications['sumo_waitlist'] = array(
                'name' => 'SUMO Waitlist',
                'notifications' => array(
                    array(
                        'key' => 'waitlist_joined',
                        'name' => 'Joined Waitlist',
                        'type' => 'success',
                        'example' => 'You have been added to the waitlist',
                    ),
                    array(
                        'key' => 'waitlist_left',
                        'name' => 'Left Waitlist',
                        'type' => 'info',
                        'example' => 'You have been removed from the waitlist',
                    ),
                    array(
                        'key' => 'waitlist_back_in_stock',
                        'name' => 'Back in Stock',
                        'type' => 'success',
                        'example' => 'Product is back in stock',
                    ),
                ),
            );
        }

        // YITH Wishlist
        if ($this->is_plugin_active('yith-woocommerce-wishlist/init.php', $active_plugins)) {
            $plugin_notifications['yith_wishlist'] = array(
                'name' => 'YITH Wishlist',
                'notifications' => array(
                    array(
                        'key' => 'yith_wishlist_added',
                        'name' => 'Added to Wishlist',
                        'type' => 'success',
                        'example' => 'Product added to wishlist',
                    ),
                    array(
                        'key' => 'yith_wishlist_removed',
                        'name' => 'Removed from Wishlist',
                        'type' => 'info',
                        'example' => 'Product removed from wishlist',
                    ),
                ),
            );
        }

        // WooCommerce Subscriptions
        if ($this->is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php', $active_plugins)) {
            $plugin_notifications['wc_subscriptions'] = array(
                'name' => 'WooCommerce Subscriptions',
                'notifications' => array(
                    array(
                        'key' => 'subscription_activated',
                        'name' => 'Subscription Activated',
                        'type' => 'success',
                        'example' => 'Your subscription has been activated',
                    ),
                    array(
                        'key' => 'subscription_cancelled',
                        'name' => 'Subscription Cancelled',
                        'type' => 'info',
                        'example' => 'Your subscription has been cancelled',
                    ),
                    array(
                        'key' => 'subscription_renewed',
                        'name' => 'Subscription Renewed',
                        'type' => 'success',
                        'example' => 'Your subscription has been renewed',
                    ),
                    array(
                        'key' => 'subscription_payment_failed',
                        'name' => 'Payment Failed',
                        'type' => 'error',
                        'example' => 'Subscription payment failed',
                    ),
                ),
            );
        }

        // WooCommerce Points and Rewards
        if ($this->is_plugin_active('woocommerce-points-and-rewards/woocommerce-points-and-rewards.php', $active_plugins)) {
            $plugin_notifications['wc_points_rewards'] = array(
                'name' => 'WooCommerce Points and Rewards',
                'notifications' => array(
                    array(
                        'key' => 'points_earned',
                        'name' => 'Points Earned',
                        'type' => 'success',
                        'example' => 'You earned X points',
                    ),
                    array(
                        'key' => 'points_redeemed',
                        'name' => 'Points Redeemed',
                        'type' => 'success',
                        'example' => 'Points applied to your order',
                    ),
                ),
            );
        }

        return apply_filters('ainfn_scanned_plugin_notifications', $plugin_notifications);
    }

    /**
     * Check if a plugin is active
     */
    private function is_plugin_active($plugin_path, $active_plugins) {
        return in_array($plugin_path, $active_plugins) || array_key_exists($plugin_path, $active_plugins);
    }

    /**
     * Import scanned plugins as detected notifications
     */
    public function import_scanned_plugins() {
        $scanned = $this->scan_plugin_notifications();
        $detected = $this->get_detected_notifications();
        $imported_count = 0;

        foreach ($scanned as $plugin_key => $plugin_data) {
            foreach ($plugin_data['notifications'] as $notif) {
                $hash = 'plugin_' . $plugin_key . '_' . $notif['key'];

                // Only add if not already exists
                if (!isset($detected[$hash])) {
                    $detected[$hash] = array(
                        'message' => $notif['example'],
                        'type' => $notif['type'],
                        'first_seen' => current_time('mysql'),
                        'count' => 0,
                        'enabled' => true,
                        'auto_name' => $plugin_data['name'] . ': ' . $notif['name'],
                        'custom_name' => $plugin_data['name'] . ': ' . $notif['name'],
                        'source' => $plugin_data['name'],
                    );
                    $imported_count++;
                }
            }
        }

        if ($imported_count > 0) {
            update_option('ainfn_detected_notifications', $detected);
            unset( $this->cache['detected_notifications'] );
        }

        return $imported_count;
    }

    /**
     * Save detected notification
     */
    public function save_detected_notification($message, $type) {
        $detected = $this->get_detected_notifications();

        // Create a hash of the message to use as key
        $hash = md5($message);

        // Only save if not already detected
        if (!isset($detected[$hash])) {
            // Cap stored entries so this autoloaded option can't grow unbounded
            // (messages often contain dynamic content like product names).
            if (count($detected) >= self::MAX_DETECTED_NOTIFICATIONS) {
                $evicted = false;
                foreach ($detected as $key => $existing) {
                    // Evict the oldest entry the admin hasn't customized
                    if (empty($existing['custom_name']) && empty($existing['custom_message'])) {
                        unset($detected[$key]);
                        $evicted = true;
                        break;
                    }
                }
                if (!$evicted) {
                    return $hash;
                }
            }
            $detected[$hash] = array(
                'message' => $message,
                'type' => $type,
                'first_seen' => current_time('mysql'),
                'count' => 1,
                'enabled' => true,
                'auto_name' => $this->generate_notification_name($message),
                'source' => 'auto-detected',
            );
        } else {
            // Increment count
            $detected[$hash]['count']++;
            $detected[$hash]['last_seen'] = current_time('mysql');
        }

        update_option('ainfn_detected_notifications', $detected);
        unset( $this->cache['detected_notifications'] );
        return $hash;
    }

    /**
     * Generate a friendly name from notification message
     */
    private function generate_notification_name($message) {
        // Strip HTML tags
        $clean = wp_strip_all_tags($message);

        // Get first 50 characters
        $name = substr($clean, 0, 50);

        // Clean up
        $name = trim($name);

        if (strlen($clean) > 50) {
            $name .= '...';
        }

        return $name;
    }

    /**
     * Update detected notification settings
     */
    public function update_detected_notification($hash, $data) {
        $detected = $this->get_detected_notifications();

        if (isset($detected[$hash])) {
            $detected[$hash] = array_merge($detected[$hash], $data);
            update_option('ainfn_detected_notifications', $detected);
            unset( $this->cache['detected_notifications'] );
            return true;
        }

        return false;
    }

    /**
     * Delete detected notification
     */
    public function delete_detected_notification($hash) {
        $detected = $this->get_detected_notifications();

        if (isset($detected[$hash])) {
            unset($detected[$hash]);
            update_option('ainfn_detected_notifications', $detected);
            unset( $this->cache['detected_notifications'] );
            return true;
        }

        return false;
    }

    /**
     * Save notification configuration
     */
    public function save_notification_config($config) {
        $result = update_option('ainfn_notification_config', $config);
        unset( $this->cache['notification_config'] );
        return $result;
    }

    /**
     * Add custom notification
     */
    public function add_custom_notification($notification) {
        $custom_notifications = $this->get_custom_notifications();
        $id = 'custom_' . uniqid();
        $custom_notifications[$id] = $notification;
        update_option('ainfn_custom_notifications', $custom_notifications);
        unset( $this->cache['custom_notifications'] );
        return $id;
    }

    /**
     * Update custom notification
     */
    public function update_custom_notification($id, $notification) {
        $custom_notifications = $this->get_custom_notifications();
        if (!isset($custom_notifications[$id])) {
            return false;
        }
        $custom_notifications[$id] = array_merge($custom_notifications[$id], $notification);
        update_option('ainfn_custom_notifications', $custom_notifications);
        unset( $this->cache['custom_notifications'] );
        return true;
    }

    /**
     * Delete custom notification
     */
    public function delete_custom_notification($id) {
        $custom_notifications = $this->get_custom_notifications();
        if (isset($custom_notifications[$id])) {
            unset($custom_notifications[$id]);
            update_option('ainfn_custom_notifications', $custom_notifications);
            unset( $this->cache['custom_notifications'] );
            return true;
        }
        return false;
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets() {
        if (!is_admin() && $this->settings['enabled'] === 'yes') {
            // Enqueue CSS
            wp_enqueue_style(
                'ainfn-styles',
                AINFN_PLUGIN_URL . 'assets/css/floating-notices.css',
                array(),
                AINFN_VERSION
            );
            
            // Add dynamic CSS based on settings
            // Cache dynamic CSS in a single transient keyed by a settings hash,
            // so changing settings never leaves orphan transient rows behind.
            // The plugin version and the CSS revision are part of the key, so an
            // update that changes the generated CSS does not serve a stale copy.
            $settings_hash = md5(AINFN_VERSION . '|css-rev-2|' . wp_json_encode($this->settings));
            $cached = get_transient('ainfn_dynamic_css');
            if (!is_array($cached) || !isset($cached['hash'], $cached['css']) || $cached['hash'] !== $settings_hash) {
                $cached = array(
                    'hash' => $settings_hash,
                    'css'  => $this->generate_dynamic_css(),
                );
                set_transient('ainfn_dynamic_css', $cached, DAY_IN_SECONDS);
            }
            wp_add_inline_style('ainfn-styles', $cached['css']);
            
            // Enqueue JavaScript
            wp_enqueue_script(
                'ainfn-script',
                AINFN_PLUGIN_URL . 'assets/js/floating-notices.js',
                array('jquery'),
                AINFN_VERSION,
                true
            );
            
            // Localize script with settings
            wp_localize_script('ainfn-script', 'ainfn_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ainfn_frontend_nonce'),
                'can_detect' => $this->can_detect_notifications(),
                'position' => $this->settings['position'],
                'auto_dismiss' => intval($this->settings['auto_dismiss']),
                'show_progress' => $this->settings['show_progress_bar'],
                'animation' => $this->settings['animation_style'],
                'mobile_position' => $this->settings['mobile_position'],
                'notification_config' => $this->get_notification_config(),
                'custom_notifications' => $this->get_custom_notifications(),
                'detected_notifications' => $this->get_detected_notifications_for_frontend(),
                'enable_auto_detection' => true,
            ));
        }
    }
    
    /**
     * Sanitize a CSS color value to prevent injection.
     */
    private function sanitize_css_color( $value ) {
        if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,.\s%]+\)|transparent|inherit)$/', $value ) ) {
            return $value;
        }
        return 'inherit';
    }

    /**
     * Generate dynamic CSS based on settings
     */
    private function generate_dynamic_css() {
        $css = '';

        $border_radius = intval( $this->settings['border_radius'] );
        $padding       = intval( $this->settings['padding'] );
        $font_size     = intval( $this->settings['font_size'] );
        $font_weight   = sanitize_text_field( $this->settings['font_weight'] );
        $text_transform = sanitize_text_field( $this->settings['text_transform'] );
        $font_family   = sanitize_text_field( $this->settings['font_family'] );

        // Font settings
        if ($font_family !== 'inherit') {
            $css .= ".floating-notice-content { font-family: {$font_family}; }";
        }

        // Keep the text clear of the close button, which sits 8px from the
        // edge and is 24px wide (28px on small screens), plus an 8px gap.
        $close_room        = max( $padding, 40 );
        $close_room_mobile = max( $padding, 44 );

        $css .= "
        .floating-notice {
            border-radius: {$border_radius}px;
            padding: {$padding}px;
            padding-right: {$close_room}px;
        }
        [dir=\"rtl\"] .floating-notice,
        .rtl .floating-notice {
            padding-right: {$padding}px;
            padding-left: {$close_room}px;
        }
        @media (max-width: 480px) {
            .floating-notice { padding-right: {$close_room_mobile}px; }
            [dir=\"rtl\"] .floating-notice,
            .rtl .floating-notice { padding-right: {$padding}px; padding-left: {$close_room_mobile}px; }
        }
        .floating-notice-content {
            font-size: {$font_size}px;
            font-weight: {$font_weight};
            text-transform: {$text_transform};
        }
        ";
        
        // Shadow styles
        $shadows = array(
            'none' => 'none',
            'small' => '0 4px 15px rgba(0, 0, 0, 0.1)',
            'medium' => '0 10px 40px rgba(0, 0, 0, 0.15)',
            'large' => '0 20px 60px rgba(0, 0, 0, 0.2)',
            'xl' => '0 25px 80px rgba(0, 0, 0, 0.25)',
        );
        
        if (isset($shadows[$this->settings['shadow_style']])) {
            $css .= ".floating-notice { box-shadow: {$shadows[$this->settings['shadow_style']]}; }";
        }
        
        // Notice type specific colors
        $notice_types = array('success', 'error', 'info', 'warning');
        
        foreach ($notice_types as $type) {
            $bg_type = $this->settings[$type . '_bg_type'];
            $bg_color1 = $this->sanitize_css_color( $this->settings[$type . '_bg_color1'] );
            $bg_color2 = $this->sanitize_css_color( $this->settings[$type . '_bg_color2'] );
            $text_color = $this->sanitize_css_color( $this->settings[$type . '_text_color'] );
            $icon_bg = $this->sanitize_css_color( $this->settings[$type . '_icon_bg'] );
            
            if ($bg_type === 'gradient') {
                $css .= "
                .floating-notice.{$type} {
                    background: linear-gradient(135deg, {$bg_color1} 0%, {$bg_color2} 100%);
                    color: {$text_color};
                }
                ";
            } else {
                $css .= "
                .floating-notice.{$type} {
                    background: {$bg_color1};
                    color: {$text_color};
                }
                ";
            }
            
            $css .= "
            .floating-notice.{$type} .floating-notice-icon {
                background: {$icon_bg};
            }
            .floating-notice.{$type} .floating-notice-content a {
                color: {$text_color};
                opacity: 0.9;
            }
            ";
        }
        
        return $css;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('woocommerce_page_ainfn-settings' !== $hook) {
            return;
        }
        
        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Admin styles
        wp_enqueue_style(
            'ainfn-admin-styles',
            AINFN_PLUGIN_URL . 'assets/css/admin-styles.css',
            array('wp-color-picker'),
            AINFN_VERSION
        );

        // Frontend notice styles (plus dynamic colors) so the Preview tab
        // renders notices exactly as they appear on the storefront.
        wp_enqueue_style(
            'ainfn-styles',
            AINFN_PLUGIN_URL . 'assets/css/floating-notices.css',
            array(),
            AINFN_VERSION
        );
        wp_add_inline_style('ainfn-styles', $this->generate_dynamic_css());

        // Admin script
        wp_enqueue_script(
            'ainfn-admin-script',
            AINFN_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'wp-color-picker'),
            AINFN_VERSION,
            true
        );

        wp_localize_script('ainfn-admin-script', 'ainfn_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ainfn_admin_nonce'),
            'custom_notifications' => $this->get_custom_notifications(),
        ));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Floating Notices', 'all-in-floating-notices-for-woocommerce'),
            __('Floating Notices', 'all-in-floating-notices-for-woocommerce'),
            'manage_woocommerce',
            'ainfn-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ainfn_settings_group', 'ainfn_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ));
    }

    /**
     * Sanitize plugin settings before saving.
     */
    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return $this->settings;
        }

        $sanitized = array();

        // Whitelist of allowed setting keys and their sanitization types
        $fields = array(
            'enabled'            => 'select:yes,no',
            'position'           => 'select:top-right,top-left,bottom-right,bottom-left,top-center',
            'auto_dismiss'       => 'int',
            'show_progress_bar'  => 'select:yes,no',
            'animation_style'    => 'select:slide,fade,bounce,zoom',
            'mobile_position'    => 'select:top-full,bottom-full,top-right,top-left,bottom-right,bottom-left,same',
            'design_preset'      => 'select:modern,flat,minimal,glass,custom',
            'font_family'        => 'font',
            'font_size'          => 'int',
            'font_weight'        => 'select:300,400,500,600,700',
            'text_transform'     => 'select:none,uppercase,lowercase,capitalize',
            'border_radius'      => 'int',
            'padding'            => 'int',
            'shadow_style'       => 'select:none,small,medium,large,xl',
        );

        // Color fields for each notice type
        $notice_types = array( 'success', 'error', 'info', 'warning' );
        foreach ( $notice_types as $type ) {
            $fields[ $type . '_bg_type' ]    = 'select:gradient,solid';
            $fields[ $type . '_bg_color1' ]  = 'color';
            $fields[ $type . '_bg_color2' ]  = 'color';
            $fields[ $type . '_text_color' ] = 'color';
            $fields[ $type . '_icon_bg' ]    = 'color';
        }

        // Font values contain commas so they can't live in a select: list
        $allowed_fonts = array(
            'inherit',
            "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            "'Helvetica Neue', Helvetica, Arial, sans-serif",
            'Georgia, serif',
            "'Courier New', monospace",
            "'Inter', sans-serif",
            "'Poppins', sans-serif",
        );

        foreach ( $fields as $key => $type ) {
            if ( ! isset( $input[ $key ] ) ) {
                continue;
            }

            $value = $input[ $key ];

            if ( $type === 'font' ) {
                $sanitized[ $key ] = in_array( $value, $allowed_fonts, true ) ? $value : $this->settings[ $key ];
            } elseif ( strpos( $type, 'select:' ) === 0 ) {
                $allowed = explode( ',', substr( $type, 7 ) );
                $sanitized[ $key ] = in_array( $value, $allowed, true ) ? $value : $this->settings[ $key ];
            } elseif ( $type === 'int' ) {
                $sanitized[ $key ] = intval( $value );
            } elseif ( $type === 'color' ) {
                // Allow hex colors and rgba()
                if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,.\s%]+\))$/', $value ) ) {
                    $sanitized[ $key ] = $value;
                } else {
                    $sanitized[ $key ] = $this->settings[ $key ];
                }
            } else {
                $sanitized[ $key ] = sanitize_text_field( $value );
            }
        }

        return wp_parse_args( $sanitized, $this->settings );
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['ainfn_settings'], $_POST['ainfn_nonce']) && wp_verify_nonce(sanitize_key($_POST['ainfn_nonce']), 'ainfn_settings_nonce')) {
            update_option('ainfn_settings', $this->sanitize_settings(wp_unslash($_POST['ainfn_settings']))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the dedicated sanitize_* method.
            $this->load_settings();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved!', 'all-in-floating-notices-for-woocommerce') . '</p></div>';
        }
        ?>
        <div class="wrap ainfn-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ainfn-settings-container">
                <div class="ainfn-tabs">
                    <ul class="ainfn-tab-nav">
                        <li><a href="#general-settings" class="active"><?php esc_html_e('General', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                        <li><a href="#notifications-settings"><?php esc_html_e('Notifications', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                        <li><a href="#design-settings"><?php esc_html_e('Design', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                        <li><a href="#colors-settings"><?php esc_html_e('Colors', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                        <li><a href="#typography-settings"><?php esc_html_e('Typography', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                        <li><a href="#preview"><?php esc_html_e('Preview', 'all-in-floating-notices-for-woocommerce'); ?></a></li>
                    </ul>
                    
                    <form method="post" action="" id="ainfn-settings-form">
                        <?php wp_nonce_field('ainfn_settings_nonce', 'ainfn_nonce'); ?>
                        
                        <!-- General Settings Tab -->
                        <div id="general-settings" class="ainfn-tab-content active">
                            <h2><?php esc_html_e('General Settings', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_enabled"><?php esc_html_e('Enable Floating Notices', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[enabled]" id="ainfn_enabled">
                                            <option value="yes" <?php selected($this->settings['enabled'], 'yes'); ?>><?php esc_html_e('Yes', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="no" <?php selected($this->settings['enabled'], 'no'); ?>><?php esc_html_e('No', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_position"><?php esc_html_e('Notice Position', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[position]" id="ainfn_position">
                                            <option value="top-right" <?php selected($this->settings['position'], 'top-right'); ?>><?php esc_html_e('Top Right', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="top-left" <?php selected($this->settings['position'], 'top-left'); ?>><?php esc_html_e('Top Left', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="bottom-right" <?php selected($this->settings['position'], 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="bottom-left" <?php selected($this->settings['position'], 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="top-center" <?php selected($this->settings['position'], 'top-center'); ?>><?php esc_html_e('Top Center', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_auto_dismiss"><?php esc_html_e('Auto Dismiss Time (ms)', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="ainfn_settings[auto_dismiss]" id="ainfn_auto_dismiss" 
                                               value="<?php echo esc_attr($this->settings['auto_dismiss']); ?>" 
                                               min="0" step="1000" />
                                        <p class="description"><?php esc_html_e('Time in milliseconds. Set to 0 to disable auto-dismiss.', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_progress_bar"><?php esc_html_e('Show Progress Bar', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[show_progress_bar]" id="ainfn_progress_bar">
                                            <option value="yes" <?php selected($this->settings['show_progress_bar'], 'yes'); ?>><?php esc_html_e('Yes', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="no" <?php selected($this->settings['show_progress_bar'], 'no'); ?>><?php esc_html_e('No', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_animation"><?php esc_html_e('Animation Style', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[animation_style]" id="ainfn_animation">
                                            <option value="slide" <?php selected($this->settings['animation_style'], 'slide'); ?>><?php esc_html_e('Slide', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="fade" <?php selected($this->settings['animation_style'], 'fade'); ?>><?php esc_html_e('Fade', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="bounce" <?php selected($this->settings['animation_style'], 'bounce'); ?>><?php esc_html_e('Bounce', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="zoom" <?php selected($this->settings['animation_style'], 'zoom'); ?>><?php esc_html_e('Zoom', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_mobile_position"><?php esc_html_e('Mobile Position', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[mobile_position]" id="ainfn_mobile_position">
                                            <option value="top-full" <?php selected($this->settings['mobile_position'], 'top-full'); ?>><?php esc_html_e('Top Full Width', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="bottom-full" <?php selected($this->settings['mobile_position'], 'bottom-full'); ?>><?php esc_html_e('Bottom Full Width', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="same" <?php selected($this->settings['mobile_position'], 'same'); ?>><?php esc_html_e('Same as Desktop', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Notifications Settings Tab -->
                        <div id="notifications-settings" class="ainfn-tab-content">
                            <h2><?php esc_html_e('Notification Management', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            <p><?php esc_html_e('Control individual notifications, customize their behavior, and add custom notifications.', 'all-in-floating-notices-for-woocommerce'); ?></p>

                            <?php
                            $notification_types = $this->get_notification_types();
                            $notification_config = $this->get_notification_config();
                            $custom_notifications = $this->get_custom_notifications();

                            // Group notifications by category
                            $categories = array(
                                'cart' => __('Cart Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'checkout' => __('Checkout Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'coupon' => __('Coupon Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'product' => __('Product Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'account' => __('Account Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'shipping' => __('Shipping Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'wishlist' => __('Wishlist Notifications', 'all-in-floating-notices-for-woocommerce'),
                                'general' => __('General Notifications', 'all-in-floating-notices-for-woocommerce'),
                            );
                            ?>

                            <div class="ainfn-notifications-manager">
                                <!-- Built-in Notifications -->
                                <div class="ainfn-notifications-section">
                                    <h3><?php esc_html_e('Built-in Notifications', 'all-in-floating-notices-for-woocommerce'); ?></h3>

                                    <?php foreach ($categories as $category_key => $category_name) :
                                        $category_notifications = array_filter($notification_types, function($n) use ($category_key) {
                                            return $n['category'] === $category_key;
                                        });

                                        if (empty($category_notifications)) continue;
                                    ?>
                                        <div class="ainfn-notification-category">
                                            <h4><?php echo esc_html($category_name); ?></h4>

                                            <table class="ainfn-notifications-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 30px;"><?php esc_html_e('Enabled', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Notification', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Type', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Timing', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Position', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                        <th><?php esc_html_e('Actions', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($category_notifications as $key => $notification) :
                                                        $config = isset($notification_config[$key]) ? $notification_config[$key] : array();
                                                        $enabled = isset($config['enabled']) ? $config['enabled'] : true;
                                                        $timing = isset($config['timing']) ? $config['timing'] : 'default';
                                                        $position = isset($config['position']) ? $config['position'] : 'default';
                                                    ?>
                                                        <tr data-notification-key="<?php echo esc_attr($key); ?>">
                                                            <td>
                                                                <label class="ainfn-toggle">
                                                                    <input type="checkbox"
                                                                           name="ainfn_notification[<?php echo esc_attr($key); ?>][enabled]"
                                                                           <?php checked($enabled, true); ?>
                                                                           class="ainfn-notification-enabled" />
                                                                    <span class="ainfn-toggle-slider"></span>
                                                                </label>
                                                            </td>
                                                            <td><strong><?php echo esc_html($notification['name']); ?></strong></td>
                                                            <td>
                                                                <span class="ainfn-badge ainfn-badge-<?php echo esc_attr($notification['type']); ?>">
                                                                    <?php echo esc_html(ucfirst($notification['type'])); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <select name="ainfn_notification[<?php echo esc_attr($key); ?>][timing]" class="ainfn-notification-timing">
                                                                    <option value="default" <?php selected($timing, 'default'); ?>><?php esc_html_e('Default', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                                                    <option value="custom" <?php selected($timing, 'custom'); ?>><?php esc_html_e('Custom', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select name="ainfn_notification[<?php echo esc_attr($key); ?>][position]" class="ainfn-notification-position">
                                                                    <option value="default" <?php selected($position, 'default'); ?>><?php esc_html_e('Default', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                                                    <option value="custom" <?php selected($position, 'custom'); ?>><?php esc_html_e('Custom', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="button button-small ainfn-edit-notification" data-key="<?php echo esc_attr($key); ?>">
                                                                    <?php esc_html_e('Configure', 'all-in-floating-notices-for-woocommerce'); ?>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Detected Notifications (3rd Party Plugins) -->
                                <div class="ainfn-notifications-section">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                        <div>
                                            <h3 style="margin: 0 0 5px 0;"><?php esc_html_e('Auto-Detected Notifications', 'all-in-floating-notices-for-woocommerce'); ?></h3>
                                            <p style="margin: 0;"><?php esc_html_e('Notifications automatically detected from 3rd party plugins (SUMO Reward Points, Gift Cards, Waitlist, etc.). These are captured as they appear on your site.', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                        </div>
                                        <button type="button" class="button button-secondary ainfn-scan-plugins" style="flex-shrink: 0;">
                                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                            <?php esc_html_e('Scan for Plugins', 'all-in-floating-notices-for-woocommerce'); ?>
                                        </button>
                                    </div>

                                    <?php
                                    $detected_notifications = $this->get_detected_notifications();
                                    if (!empty($detected_notifications)) :
                                    ?>
                                        <table class="ainfn-notifications-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30px;"><?php esc_html_e('Enabled', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Notification Preview', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Type', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Source', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Times Seen', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Actions', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="ainfn-detected-notifications-list">
                                                <?php foreach ($detected_notifications as $hash => $notification) : ?>
                                                    <tr data-notification-hash="<?php echo esc_attr($hash); ?>">
                                                        <td>
                                                            <label class="ainfn-toggle">
                                                                <input type="checkbox"
                                                                       class="ainfn-detected-notification-enabled"
                                                                       data-hash="<?php echo esc_attr($hash); ?>"
                                                                       <?php checked($notification['enabled'], true); ?> />
                                                                <span class="ainfn-toggle-slider"></span>
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo isset($notification['custom_name']) ? esc_html($notification['custom_name']) : esc_html($notification['auto_name']); ?></strong>
                                                            <div class="ainfn-notification-preview"><?php echo wp_kses_post(wp_trim_words($notification['message'], 15)); ?></div>
                                                        </td>
                                                        <td>
                                                            <span class="ainfn-badge ainfn-badge-<?php echo esc_attr($notification['type']); ?>">
                                                                <?php echo esc_html(ucfirst($notification['type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="ainfn-source-badge"><?php echo esc_html($notification['source']); ?></span>
                                                        </td>
                                                        <td><?php echo esc_html($notification['count']); ?>x</td>
                                                        <td>
                                                            <button type="button" class="button button-small ainfn-edit-detected-notification" data-hash="<?php echo esc_attr($hash); ?>">
                                                                <?php esc_html_e('Configure', 'all-in-floating-notices-for-woocommerce'); ?>
                                                            </button>
                                                            <button type="button" class="button button-small ainfn-delete-detected-notification" data-hash="<?php echo esc_attr($hash); ?>">
                                                                <?php esc_html_e('Delete', 'all-in-floating-notices-for-woocommerce'); ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else : ?>
                                        <div class="ainfn-empty-state">
                                            <p><?php esc_html_e('No notifications detected yet. Notifications from 3rd party plugins will appear here automatically when they are displayed on your site.', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Custom Notifications -->
                                <div class="ainfn-notifications-section">
                                    <h3><?php esc_html_e('Custom Notifications', 'all-in-floating-notices-for-woocommerce'); ?></h3>
                                    <p><?php esc_html_e('Create custom notifications to display anywhere on your site.', 'all-in-floating-notices-for-woocommerce'); ?></p>

                                    <button type="button" class="button button-primary ainfn-add-custom-notification">
                                        <?php esc_html_e('+ Add Custom Notification', 'all-in-floating-notices-for-woocommerce'); ?>
                                    </button>

                                    <!-- Usage Instructions -->
                                    <div class="ainfn-help-box" style="margin: 20px 0; padding: 15px; background: #f0f6fc; border-left: 4px solid #0366d6; border-radius: 4px;">
                                        <h4 style="margin-top: 0;"><?php esc_html_e('How to Display Custom Notifications', 'all-in-floating-notices-for-woocommerce'); ?></h4>
                                        <p style="margin-bottom: 10px;"><strong><?php esc_html_e('Using Shortcode:', 'all-in-floating-notices-for-woocommerce'); ?></strong></p>
                                        <code style="background: #fff; padding: 8px; display: inline-block; border-radius: 3px; margin-bottom: 10px;">[ainfn_show id="<?php echo !empty($custom_notifications) ? esc_attr(key(array_slice($custom_notifications, 0, 1, true))) : 'custom_xxx'; ?>"]</code>
                                        <p style="margin-bottom: 10px;"><strong><?php esc_html_e('Or by name:', 'all-in-floating-notices-for-woocommerce'); ?></strong></p>
                                        <code style="background: #fff; padding: 8px; display: inline-block; border-radius: 3px; margin-bottom: 10px;">[ainfn_show name="<?php echo !empty($custom_notifications) ? esc_attr(reset($custom_notifications)['name']) : 'Your Notification Name'; ?>"]</code>
                                        <p style="margin-bottom: 10px;"><strong><?php esc_html_e('Using JavaScript:', 'all-in-floating-notices-for-woocommerce'); ?></strong></p>
                                        <code style="background: #fff; padding: 8px; display: inline-block; border-radius: 3px;">AinfnFloatingNotices.displayCustomNotification('<?php echo !empty($custom_notifications) ? esc_html(key(array_slice($custom_notifications, 0, 1, true))) : 'custom_xxx'; ?>');</code>
                                        <p style="margin-top: 10px; font-size: 12px; color: #666;"><?php esc_html_e('Note: Copy the ID from the table below to use in your shortcode or JavaScript.', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                    </div>

                                    <?php if (!empty($custom_notifications)) : ?>
                                        <table class="ainfn-notifications-table" style="margin-top: 20px;">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Name', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('ID', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Message', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Type', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                    <th><?php esc_html_e('Actions', 'all-in-floating-notices-for-woocommerce'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="ainfn-custom-notifications-list">
                                                <?php foreach ($custom_notifications as $id => $notification) : ?>
                                                    <tr data-notification-id="<?php echo esc_attr($id); ?>">
                                                        <td><strong><?php echo esc_html($notification['name']); ?></strong></td>
                                                        <td>
                                                            <code style="font-size: 11px; background: #f5f5f5; padding: 4px 8px; border-radius: 3px; cursor: pointer;"
                                                                  class="ainfn-copy-id" data-ainfn-copy="<?php echo esc_attr($id); ?>"
                                                                  title="Click to copy">
                                                                <?php echo esc_html($id); ?>
                                                            </code>
                                                        </td>
                                                        <td><?php echo esc_html(wp_trim_words($notification['message'], 10)); ?></td>
                                                        <td>
                                                            <span class="ainfn-badge ainfn-badge-<?php echo esc_attr($notification['type']); ?>">
                                                                <?php echo esc_html(ucfirst($notification['type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="button button-small ainfn-edit-custom-notification" data-id="<?php echo esc_attr($id); ?>">
                                                                <?php esc_html_e('Edit', 'all-in-floating-notices-for-woocommerce'); ?>
                                                            </button>
                                                            <button type="button" class="button button-small ainfn-delete-custom-notification" data-id="<?php echo esc_attr($id); ?>">
                                                                <?php esc_html_e('Delete', 'all-in-floating-notices-for-woocommerce'); ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Bulk Actions -->
                                <div class="ainfn-bulk-actions">
                                    <h3><?php esc_html_e('Bulk Actions', 'all-in-floating-notices-for-woocommerce'); ?></h3>
                                    <button type="button" class="button ainfn-enable-all-notifications">
                                        <?php esc_html_e('Enable All', 'all-in-floating-notices-for-woocommerce'); ?>
                                    </button>
                                    <button type="button" class="button ainfn-disable-all-notifications">
                                        <?php esc_html_e('Disable All', 'all-in-floating-notices-for-woocommerce'); ?>
                                    </button>
                                    <button type="button" class="button ainfn-reset-all-notifications">
                                        <?php esc_html_e('Reset to Defaults', 'all-in-floating-notices-for-woocommerce'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Design Settings Tab -->
                        <div id="design-settings" class="ainfn-tab-content">
                            <h2><?php esc_html_e('Design Settings', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_design_preset"><?php esc_html_e('Design Preset', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[design_preset]" id="ainfn_design_preset">
                                            <option value="modern" <?php selected($this->settings['design_preset'], 'modern'); ?>><?php esc_html_e('Modern Gradient', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="flat" <?php selected($this->settings['design_preset'], 'flat'); ?>><?php esc_html_e('Flat Design', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="minimal" <?php selected($this->settings['design_preset'], 'minimal'); ?>><?php esc_html_e('Minimal', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="glass" <?php selected($this->settings['design_preset'], 'glass'); ?>><?php esc_html_e('Glassmorphism', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="custom" <?php selected($this->settings['design_preset'], 'custom'); ?>><?php esc_html_e('Custom', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Choose a design preset or select Custom to use your own colors', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_border_radius"><?php esc_html_e('Border Radius (px)', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <input type="range" name="ainfn_settings[border_radius]" id="ainfn_border_radius" 
                                               value="<?php echo esc_attr($this->settings['border_radius']); ?>" 
                                               min="0" max="30" />
                                        <span id="border_radius_value"><?php echo esc_attr($this->settings['border_radius']); ?>px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_padding"><?php esc_html_e('Padding (px)', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <input type="range" name="ainfn_settings[padding]" id="ainfn_padding" 
                                               value="<?php echo esc_attr($this->settings['padding']); ?>" 
                                               min="8" max="30" />
                                        <span id="padding_value"><?php echo esc_attr($this->settings['padding']); ?>px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_shadow_style"><?php esc_html_e('Shadow Style', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[shadow_style]" id="ainfn_shadow_style">
                                            <option value="none" <?php selected($this->settings['shadow_style'], 'none'); ?>><?php esc_html_e('None', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="small" <?php selected($this->settings['shadow_style'], 'small'); ?>><?php esc_html_e('Small', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="medium" <?php selected($this->settings['shadow_style'], 'medium'); ?>><?php esc_html_e('Medium', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="large" <?php selected($this->settings['shadow_style'], 'large'); ?>><?php esc_html_e('Large', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="xl" <?php selected($this->settings['shadow_style'], 'xl'); ?>><?php esc_html_e('Extra Large', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Colors Settings Tab -->
                        <div id="colors-settings" class="ainfn-tab-content">
                            <h2><?php esc_html_e('Color Settings', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            
                            <?php
                            $notice_types = array(
                                'success' => __('Success Notices', 'all-in-floating-notices-for-woocommerce'),
                                'error' => __('Error Notices', 'all-in-floating-notices-for-woocommerce'),
                                'info' => __('Info Notices', 'all-in-floating-notices-for-woocommerce'),
                                'warning' => __('Warning Notices', 'all-in-floating-notices-for-woocommerce'),
                            );
                            
                            foreach ($notice_types as $type => $label) : ?>
                                <h3><?php echo esc_html($label); ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Background Type', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                        </th>
                                        <td>
                                            <select name="ainfn_settings[<?php echo esc_attr($type); ?>_bg_type]" class="ainfn-bg-type" data-type="<?php echo esc_attr($type); ?>">
                                                <option value="solid" <?php selected($this->settings[$type . '_bg_type'], 'solid'); ?>><?php esc_html_e('Solid Color', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                                <option value="gradient" <?php selected($this->settings[$type . '_bg_type'], 'gradient'); ?>><?php esc_html_e('Gradient', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Background Color', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" name="ainfn_settings[<?php echo esc_attr($type); ?>_bg_color1]" 
                                                   value="<?php echo esc_attr($this->settings[$type . '_bg_color1']); ?>" 
                                                   class="ainfn-color-picker" />
                                            <span class="ainfn-gradient-color2" style="<?php echo ($this->settings[$type . '_bg_type'] !== 'gradient') ? 'display:none;' : ''; ?>">
                                                <label><?php esc_html_e('Gradient End Color', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                                <input type="text" name="ainfn_settings[<?php echo esc_attr($type); ?>_bg_color2]" 
                                                       value="<?php echo esc_attr($this->settings[$type . '_bg_color2']); ?>" 
                                                       class="ainfn-color-picker" />
                                            </span>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Text Color', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" name="ainfn_settings[<?php echo esc_attr($type); ?>_text_color]" 
                                                   value="<?php echo esc_attr($this->settings[$type . '_text_color']); ?>" 
                                                   class="ainfn-color-picker" />
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Icon Background', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" name="ainfn_settings[<?php echo esc_attr($type); ?>_icon_bg]" 
                                                   value="<?php echo esc_attr($this->settings[$type . '_icon_bg']); ?>" 
                                                   class="ainfn-color-picker-alpha" />
                                            <p class="description"><?php esc_html_e('Use rgba values for transparency, e.g., rgba(255,255,255,0.2)', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                <hr>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Typography Settings Tab -->
                        <div id="typography-settings" class="ainfn-tab-content">
                            <h2><?php esc_html_e('Typography Settings', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_font_family"><?php esc_html_e('Font Family', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[font_family]" id="ainfn_font_family">
                                            <option value="inherit" <?php selected($this->settings['font_family'], 'inherit'); ?>><?php esc_html_e('Theme Default', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php selected($this->settings['font_family'], "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"); ?>><?php esc_html_e('System Font', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="'Helvetica Neue', Helvetica, Arial, sans-serif" <?php selected($this->settings['font_family'], "'Helvetica Neue', Helvetica, Arial, sans-serif"); ?>><?php esc_html_e('Helvetica', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="Georgia, serif" <?php selected($this->settings['font_family'], "Georgia, serif"); ?>><?php esc_html_e('Georgia', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="'Courier New', monospace" <?php selected($this->settings['font_family'], "'Courier New', monospace"); ?>><?php esc_html_e('Courier', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="'Inter', sans-serif" <?php selected($this->settings['font_family'], "'Inter', sans-serif"); ?>><?php esc_html_e('Inter', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="'Poppins', sans-serif" <?php selected($this->settings['font_family'], "'Poppins', sans-serif"); ?>><?php esc_html_e('Poppins', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_font_size"><?php esc_html_e('Font Size (px)', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <input type="range" name="ainfn_settings[font_size]" id="ainfn_font_size" 
                                               value="<?php echo esc_attr($this->settings['font_size']); ?>" 
                                               min="10" max="20" />
                                        <span id="font_size_value"><?php echo esc_attr($this->settings['font_size']); ?>px</span>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_font_weight"><?php esc_html_e('Font Weight', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[font_weight]" id="ainfn_font_weight">
                                            <option value="300" <?php selected($this->settings['font_weight'], '300'); ?>><?php esc_html_e('Light (300)', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="400" <?php selected($this->settings['font_weight'], '400'); ?>><?php esc_html_e('Normal (400)', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="500" <?php selected($this->settings['font_weight'], '500'); ?>><?php esc_html_e('Medium (500)', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="600" <?php selected($this->settings['font_weight'], '600'); ?>><?php esc_html_e('Semi Bold (600)', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="700" <?php selected($this->settings['font_weight'], '700'); ?>><?php esc_html_e('Bold (700)', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ainfn_text_transform"><?php esc_html_e('Text Transform', 'all-in-floating-notices-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="ainfn_settings[text_transform]" id="ainfn_text_transform">
                                            <option value="none" <?php selected($this->settings['text_transform'], 'none'); ?>><?php esc_html_e('None', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="uppercase" <?php selected($this->settings['text_transform'], 'uppercase'); ?>><?php esc_html_e('UPPERCASE', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="lowercase" <?php selected($this->settings['text_transform'], 'lowercase'); ?>><?php esc_html_e('lowercase', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                            <option value="capitalize" <?php selected($this->settings['text_transform'], 'capitalize'); ?>><?php esc_html_e('Capitalize', 'all-in-floating-notices-for-woocommerce'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Preview Tab -->
                        <div id="preview" class="ainfn-tab-content">
                            <h2><?php esc_html_e('Live Preview', 'all-in-floating-notices-for-woocommerce'); ?></h2>
                            
                            <div class="ainfn-preview-container">
                                <p><?php esc_html_e('Click the buttons below to preview different notice types with your current settings:', 'all-in-floating-notices-for-woocommerce'); ?></p>
                                
                                <div class="ainfn-preview-buttons">
                                    <button type="button" class="button button-success" data-ainfn-preview="success"><?php esc_html_e('Preview Success', 'all-in-floating-notices-for-woocommerce'); ?></button>
                                    <button type="button" class="button button-error" data-ainfn-preview="error"><?php esc_html_e('Preview Error', 'all-in-floating-notices-for-woocommerce'); ?></button>
                                    <button type="button" class="button button-info" data-ainfn-preview="info"><?php esc_html_e('Preview Info', 'all-in-floating-notices-for-woocommerce'); ?></button>
                                    <button type="button" class="button button-warning" data-ainfn-preview="warning"><?php esc_html_e('Preview Warning', 'all-in-floating-notices-for-woocommerce'); ?></button>
                                </div>
                                
                                <div id="ainfn-preview-area" style="position: relative; min-height: 200px; background: #f5f5f5; border: 1px solid #ddd; margin-top: 20px; border-radius: 8px;">
                                    <!-- Preview notices will appear here -->
                                </div>
                            </div>
                        </div>
                        
                        <?php submit_button(__('Save All Settings', 'all-in-floating-notices-for-woocommerce'), 'primary', 'submit', true, array('id' => 'ainfn-save-settings')); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ainfn-settings') . '">' . __('Settings', 'all-in-floating-notices-for-woocommerce') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * AJAX save settings handler
     */
    public function ajax_save_settings() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['settings'])) {
            update_option('ainfn_settings', $this->sanitize_settings(wp_unslash($_POST['settings']))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the dedicated sanitize_* method.
            wp_send_json_success('Settings saved');
        }

        wp_send_json_error('No settings provided');
    }

    /**
     * Sanitize a notification config array submitted from the admin screen.
     * Casts the "true"/"false" strings sent by jQuery to real booleans and
     * whitelists every value.
     */
    private function sanitize_notification_config( $config ) {
        if ( ! is_array( $config ) ) {
            return array();
        }

        $known_types       = $this->get_notification_types();
        $desktop_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center' );
        $mobile_positions  = array( 'top-full', 'bottom-full', 'same' );
        $animations        = array( 'slide', 'fade', 'bounce', 'zoom' );
        $modes             = array( 'default', 'custom' );

        $clean = array();
        foreach ( $config as $key => $values ) {
            if ( ! isset( $known_types[ $key ] ) || ! is_array( $values ) ) {
                continue;
            }
            $entry = array();
            if ( isset( $values['enabled'] ) ) {
                $entry['enabled'] = filter_var( $values['enabled'], FILTER_VALIDATE_BOOLEAN );
            }
            if ( isset( $values['timing'] ) ) {
                $entry['timing'] = in_array( $values['timing'], $modes, true ) ? $values['timing'] : 'default';
            }
            if ( isset( $values['auto_dismiss'] ) ) {
                $entry['auto_dismiss'] = max( 0, intval( $values['auto_dismiss'] ) );
            }
            if ( isset( $values['position'] ) ) {
                $entry['position'] = in_array( $values['position'], $modes, true ) ? $values['position'] : 'default';
            }
            if ( isset( $values['desktop_position'] ) ) {
                $entry['desktop_position'] = in_array( $values['desktop_position'], $desktop_positions, true ) ? $values['desktop_position'] : 'top-right';
            }
            if ( isset( $values['mobile_position'] ) ) {
                $entry['mobile_position'] = in_array( $values['mobile_position'], $mobile_positions, true ) ? $values['mobile_position'] : 'top-full';
            }
            if ( isset( $values['animation'] ) ) {
                $entry['animation'] = in_array( $values['animation'], $modes, true ) ? $values['animation'] : 'default';
            }
            if ( isset( $values['animation_style'] ) ) {
                $entry['animation_style'] = in_array( $values['animation_style'], $animations, true ) ? $values['animation_style'] : 'slide';
            }
            $clean[ $key ] = $entry;
        }
        return $clean;
    }

    /**
     * AJAX save notification config handler
     */
    public function ajax_save_notification_config() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        // "Reset to Defaults" sends a reset flag instead of a config array
        if (!empty($_POST['reset'])) {
            delete_option('ainfn_notification_config');
            unset( $this->cache['notification_config'] );
            wp_send_json_success('Notification configuration reset');
        }

        if (isset($_POST['config'])) {
            $this->save_notification_config($this->sanitize_notification_config(wp_unslash($_POST['config']))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the dedicated sanitize_* method.
            wp_send_json_success('Notification configuration saved');
        }

        wp_send_json_error('No configuration provided');
    }

    /**
     * Sanitize custom notification data submitted from the admin screen.
     */
    private function sanitize_custom_notification( $raw ) {
        $types             = array( 'success', 'error', 'info', 'warning' );
        $desktop_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center' );
        $mobile_positions  = array( 'top-full', 'bottom-full', 'same' );
        $show_on_values    = array( 'all', 'shop', 'product', 'cart', 'checkout', 'account', 'home' );

        $type    = isset( $raw['type'] ) ? sanitize_text_field( $raw['type'] ) : 'info';
        $desktop = isset( $raw['desktop_position'] ) ? sanitize_text_field( $raw['desktop_position'] ) : 'top-right';
        $mobile  = isset( $raw['mobile_position'] ) ? sanitize_text_field( $raw['mobile_position'] ) : 'top-full';
        $show_on = isset( $raw['show_on'] ) ? sanitize_text_field( $raw['show_on'] ) : 'all';

        return array(
            'name' => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
            'message' => isset( $raw['message'] ) ? wp_kses_post( $raw['message'] ) : '',
            'type' => in_array( $type, $types, true ) ? $type : 'info',
            'category' => isset( $raw['category'] ) ? sanitize_text_field( $raw['category'] ) : 'general',
            'enabled' => isset( $raw['enabled'] ) ? filter_var( $raw['enabled'], FILTER_VALIDATE_BOOLEAN ) : true,
            'timing' => ( isset( $raw['timing'] ) && 'custom' === $raw['timing'] ) ? 'custom' : 'default',
            'auto_dismiss' => isset( $raw['auto_dismiss'] ) ? max( 0, intval( $raw['auto_dismiss'] ) ) : 5000,
            'position' => ( isset( $raw['position'] ) && 'custom' === $raw['position'] ) ? 'custom' : 'default',
            'desktop_position' => in_array( $desktop, $desktop_positions, true ) ? $desktop : 'top-right',
            'mobile_position' => in_array( $mobile, $mobile_positions, true ) ? $mobile : 'top-full',
            'show_on' => in_array( $show_on, $show_on_values, true ) ? $show_on : 'all',
            'trigger_delay' => isset( $raw['trigger_delay'] ) ? max( 0, intval( $raw['trigger_delay'] ) ) : 0,
        );
    }

    /**
     * AJAX add custom notification handler
     */
    public function ajax_add_custom_notification() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['notification']) && is_array($_POST['notification'])) {
            $notification = $this->sanitize_custom_notification(wp_unslash($_POST['notification'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the dedicated sanitize_* method.

            if (empty($notification['name']) || empty($notification['message'])) {
                wp_send_json_error('Name and message are required');
            }

            $id = $this->add_custom_notification($notification);
            wp_send_json_success(array(
                'message' => 'Custom notification added',
                'id' => $id,
                'notification' => $notification,
            ));
        }

        wp_send_json_error('No notification data provided');
    }

    /**
     * AJAX update custom notification handler
     */
    public function ajax_update_custom_notification() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['notification_id'], $_POST['notification']) && is_array($_POST['notification'])) {
            $id = sanitize_text_field(wp_unslash($_POST['notification_id']));
            $notification = $this->sanitize_custom_notification(wp_unslash($_POST['notification'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the dedicated sanitize_* method.

            if (empty($notification['name']) || empty($notification['message'])) {
                wp_send_json_error('Name and message are required');
            }

            if ($this->update_custom_notification($id, $notification)) {
                wp_send_json_success(array(
                    'message' => 'Custom notification updated',
                    'id' => $id,
                    'notification' => $notification,
                ));
            }

            wp_send_json_error('Notification not found');
        }

        wp_send_json_error('No notification data provided');
    }

    /**
     * AJAX delete custom notification handler
     */
    public function ajax_delete_custom_notification() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['notification_id'])) {
            $id = sanitize_text_field(wp_unslash($_POST['notification_id']));
            if ($this->delete_custom_notification($id)) {
                wp_send_json_success('Custom notification deleted');
            } else {
                wp_send_json_error('Notification not found');
            }
        }

        wp_send_json_error('No notification ID provided');
    }

    /**
     * AJAX save detected notification handler (from frontend)
     */
    /**
     * Whether visitors who are not logged in may report detected notices.
     * Off by default: otherwise any visitor could add entries to the admin list.
     *
     * @return bool
     */
    private function guest_detection_allowed() {
        return (bool) apply_filters('ainfn_allow_guest_notice_detection', false);
    }

    /**
     * Whether the current visitor may report detected notices
     * (store managers always; guests only when the filter allows it).
     *
     * @return bool
     */
    public function can_detect_notifications() {
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        return $this->guest_detection_allowed();
    }

    public function ajax_save_detected_notification() {
        // Verify nonce to prevent abuse
        if ( ! check_ajax_referer( 'ainfn_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        if ( ! $this->can_detect_notifications() ) {
            wp_send_json_error( 'Not allowed', 403 );
        }

        if (isset($_POST['message']) && isset($_POST['type'])) {
            $message = wp_kses_post(wp_unslash($_POST['message']));
            $type = sanitize_text_field(wp_unslash($_POST['type']));
            if (!in_array($type, array('success', 'error', 'info', 'warning'), true)) {
                $type = 'info';
            }

            // Rate limit: only allow saving once per message per 5 minutes
            $hash = md5($message);
            $transient_key = 'ainfn_detect_' . $hash;
            if ( get_transient( $transient_key ) ) {
                wp_send_json_success(array(
                    'hash' => $hash,
                    'message' => 'Already detected (cached)',
                ));
            }
            set_transient( $transient_key, 1, 5 * MINUTE_IN_SECONDS );

            $hash = $this->save_detected_notification($message, $type);

            wp_send_json_success(array(
                'hash' => $hash,
                'message' => 'Notification detected',
            ));
        }

        wp_send_json_error('Missing data');
    }

    /**
     * AJAX update detected notification handler
     */
    public function ajax_update_detected_notification() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['hash']) && isset($_POST['data']) && is_array($_POST['data'])) {
            $hash = sanitize_text_field(wp_unslash($_POST['hash']));
            $raw = wp_unslash($_POST['data']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is validated against allowlists below.
            $data = array();

            $types             = array( '', 'success', 'error', 'info', 'warning' );
            $desktop_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center' );
            $mobile_positions  = array( 'top-full', 'bottom-full', 'same' );

            if (isset($raw['enabled'])) {
                // jQuery serializes booleans as "true"/"false" strings
                $data['enabled'] = filter_var($raw['enabled'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($raw['custom_name'])) {
                $data['custom_name'] = sanitize_text_field($raw['custom_name']);
            }
            if (isset($raw['custom_message'])) {
                $data['custom_message'] = wp_kses_post($raw['custom_message']);
            }
            if (isset($raw['custom_type'])) {
                $custom_type = sanitize_text_field($raw['custom_type']);
                $data['custom_type'] = in_array($custom_type, $types, true) ? $custom_type : '';
            }
            if (isset($raw['timing'])) {
                $data['timing'] = ('custom' === $raw['timing']) ? 'custom' : 'default';
            }
            if (isset($raw['auto_dismiss'])) {
                $data['auto_dismiss'] = max(0, intval($raw['auto_dismiss']));
            }
            if (isset($raw['position'])) {
                $data['position'] = ('custom' === $raw['position']) ? 'custom' : 'default';
            }
            if (isset($raw['desktop_position'])) {
                $desktop = sanitize_text_field($raw['desktop_position']);
                $data['desktop_position'] = in_array($desktop, $desktop_positions, true) ? $desktop : 'top-right';
            }
            if (isset($raw['mobile_position'])) {
                $mobile = sanitize_text_field($raw['mobile_position']);
                $data['mobile_position'] = in_array($mobile, $mobile_positions, true) ? $mobile : 'top-full';
            }

            if ($this->update_detected_notification($hash, $data)) {
                wp_send_json_success('Detected notification updated');
            } else {
                wp_send_json_error('Notification not found');
            }
        }

        wp_send_json_error('Missing data');
    }

    /**
     * AJAX delete detected notification handler
     */
    public function ajax_delete_detected_notification() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (isset($_POST['hash'])) {
            $hash = sanitize_text_field(wp_unslash($_POST['hash']));

            if ($this->delete_detected_notification($hash)) {
                wp_send_json_success('Detected notification deleted');
            } else {
                wp_send_json_error('Notification not found');
            }
        }

        wp_send_json_error('No hash provided');
    }

    /**
     * AJAX scan plugins handler
     */
    public function ajax_scan_plugins() {
        check_ajax_referer('ainfn_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $count = $this->import_scanned_plugins();

        wp_send_json_success(array(
            /* translators: %d: number of notifications found */
            'message' => sprintf(__('%d notification(s) found and imported', 'all-in-floating-notices-for-woocommerce'), $count),
            'count' => $count,
        ));
    }

    /**
     * Shortcode to display custom notification
     * Usage: [ainfn_show id="custom_xxx"] or [ainfn_show name="My Notification"]
     */
    public function shortcode_show_notification($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'name' => '',
        ), $atts);

        if (empty($atts['id']) && empty($atts['name'])) {
            return '<!-- AINFN: ID or name required -->';
        }

        $custom_notifications = $this->get_custom_notifications();
        $notification = null;

        // Find by ID
        if (!empty($atts['id']) && isset($custom_notifications[$atts['id']])) {
            $notification = $custom_notifications[$atts['id']];
            $notification['id'] = $atts['id'];
        }
        // Find by name
        elseif (!empty($atts['name'])) {
            foreach ($custom_notifications as $id => $notif) {
                if ($notif['name'] === $atts['name']) {
                    $notification = $notif;
                    $notification['id'] = $id;
                    break;
                }
            }
        }

        if (!$notification || !$notification['enabled']) {
            return '<!-- AINFN: Notification not found or disabled -->';
        }

        $config = array(
            'auto_dismiss'     => isset($notification['auto_dismiss']) ? intval($notification['auto_dismiss']) : 5000,
            'desktop_position' => isset($notification['desktop_position']) ? sanitize_key($notification['desktop_position']) : 'top-right',
            'mobile_position'  => isset($notification['mobile_position']) ? sanitize_key($notification['mobile_position']) : 'top-full',
        );

        // Queue the notice on the enqueued front-end script with
        // wp_add_inline_script() instead of printing a <script> tag.
        if (!wp_script_is('ainfn-script', 'enqueued')) {
            $this->enqueue_assets();
        }
        if (wp_script_is('ainfn-script', 'enqueued')) {
            $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
            wp_add_inline_script(
                'ainfn-script',
                sprintf(
                    'jQuery(function(){if(window.AinfnFloatingNotices){window.AinfnFloatingNotices.createNotice(%1$s,%2$s,%3$s);}});',
                    wp_json_encode($notification['message'], $json_flags),
                    wp_json_encode($notification['type'], $json_flags),
                    wp_json_encode($config, $json_flags)
                )
            );
        }

        return '';
    }

    /**
     * AJAX handler to show custom notification
     */
    public function ajax_show_custom_notification() {
        if ( ! check_ajax_referer( 'ainfn_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $notification_id = isset($_POST['notification_id']) ? sanitize_text_field(wp_unslash($_POST['notification_id'])) : '';
        $notification_name = isset($_POST['notification_name']) ? sanitize_text_field(wp_unslash($_POST['notification_name'])) : '';

        if (empty($notification_id) && empty($notification_name)) {
            wp_send_json_error('Notification ID or name required');
        }

        $custom_notifications = $this->get_custom_notifications();
        $notification = null;

        // Find by ID
        if (!empty($notification_id) && isset($custom_notifications[$notification_id])) {
            $notification = $custom_notifications[$notification_id];
        }
        // Find by name
        elseif (!empty($notification_name)) {
            foreach ($custom_notifications as $id => $notif) {
                if ($notif['name'] === $notification_name) {
                    $notification = $notif;
                    break;
                }
            }
        }

        if (!$notification) {
            wp_send_json_error('Notification not found');
        }

        if (!$notification['enabled']) {
            wp_send_json_error('Notification is disabled');
        }

        wp_send_json_success($notification);
    }
}

// Initialize plugin
add_action('plugins_loaded', array('AINFN_Floating_Notices', 'get_instance'));

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Activation hook
register_activation_hook(__FILE__, 'ainfn_activation');
function ainfn_activation() {
    // Create default settings with new design options
    $defaults = array(
        'enabled' => 'yes',
        'position' => 'top-right',
        'auto_dismiss' => 5000,
        'show_progress_bar' => 'yes',
        'animation_style' => 'slide',
        'mobile_position' => 'top-full',
        
        // Design settings
        'design_preset' => 'modern',
        'font_family' => 'inherit',
        'font_size' => '14',
        'font_weight' => '500',
        'text_transform' => 'none',
        'border_radius' => '12',
        'padding' => '16',
        'shadow_style' => 'medium',
        
        // Color settings
        'success_bg_type' => 'gradient',
        'success_bg_color1' => '#10b981',
        'success_bg_color2' => '#059669',
        'success_text_color' => '#ffffff',
        'success_icon_bg' => 'rgba(255,255,255,0.2)',
        
        'error_bg_type' => 'gradient',
        'error_bg_color1' => '#ef4444',
        'error_bg_color2' => '#dc2626',
        'error_text_color' => '#ffffff',
        'error_icon_bg' => 'rgba(255,255,255,0.2)',
        
        'info_bg_type' => 'gradient',
        'info_bg_color1' => '#3b82f6',
        'info_bg_color2' => '#2563eb',
        'info_text_color' => '#ffffff',
        'info_icon_bg' => 'rgba(255,255,255,0.2)',
        
        'warning_bg_type' => 'gradient',
        'warning_bg_color1' => '#f59e0b',
        'warning_bg_color2' => '#d97706',
        'warning_text_color' => '#ffffff',
        'warning_icon_bg' => 'rgba(255,255,255,0.2)',
        
    );
    
    if (!get_option('ainfn_settings')) {
        add_option('ainfn_settings', $defaults);
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'ainfn_deactivation');
function ainfn_deactivation() {
    // Remove the cached dynamic CSS. The short-lived (5 minute) detection
    // throttle transients expire on their own.
    delete_transient('ainfn_dynamic_css');
}
