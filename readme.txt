=== All-IN Floating Notices for WooCommerce ===
Contributors: elis85
Donate link: https://a-in.co.il/plugins/floating-notices/
Tags: woocommerce, notices, notifications, toast, cart message
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.2.0
WC requires at least: 3.0
WC tested up to: 11.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WooCommerce notices into floating, dismissible messages, with per-notice control, custom notifications and full design settings.

== Description ==

All-IN Floating Notices for WooCommerce replaces the standard WooCommerce message bars (added to cart, coupon applied, checkout errors and so on) with floating, dismissible messages with smooth animations. Control each notice type, add your own notifications, and style everything to match your store.

**Features**

* **Floating notices.** Intercepts the standard WooCommerce notices (`woocommerce-message`, `woocommerce-error`, `woocommerce-info`, `woocommerce-notice`), including the ones added by AJAX (cart fragments, checkout errors, applied coupons).
* **Per-notification control.** Enable, disable and fine-tune the timing, position and animation of 20 built-in WooCommerce notification types, grouped by category (cart, checkout, coupon, product, account, shipping, wishlist, general).
* **Auto-detection.** Notices from other plugins are detected while a store manager browses the shop, and listed in the admin so you can rename, restyle, retype or disable them. Storage is capped at 100 entries.
* **Plugin scanner.** One-click import of known notification types from supported plugins (see the FAQ).
* **Custom notifications.** Create your own notifications with display conditions (page type), trigger delay and position, and show them with a shortcode or JavaScript.
* **Design controls.** Presets, colors and gradients per notice type, typography, border radius, padding, shadows and a live preview.
* **Responsive and accessible.** Separate desktop and mobile positions, plus reduced-motion, high-contrast, dark-mode and print support. RTL ready.
* No external services.

All-IN Floating Notices for WooCommerce is an independent plugin by A-in (a-in.co.il). It is not affiliated with or endorsed by WooCommerce or Automattic.

**Shortcode**

`[ainfn_show id="custom_xxx"]` or `[ainfn_show name="My Notification"]`

**JavaScript**

`AinfnFloatingNotices.displayCustomNotification('custom_xxx');`

**Developer hooks**

* `ainfn_scanned_plugin_notifications` (filter): add or change the third-party notification definitions used by the plugin scanner.
* `ainfn_allow_guest_notice_detection` (filter, default `false`): let visitors who are not logged in report new notices for the admin list.

= More from A-in =

This plugin is complete on its own: no licence key, no locked features. Premium plugins, add-ons and support from the same developer are available at [a-in.co.il](https://a-in.co.il/plugins/).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Go to **WooCommerce → Floating Notices** to configure it.

== Frequently Asked Questions ==

= Does it work with notices from other plugins? =

Yes. Any notice printed with the standard WooCommerce notice markup is shown as a floating notice. Browse your shop while logged in as a store manager, and the new notices appear in the admin list, where you can customize them.

= Does it work with the block-based Cart and Checkout? =

It handles the classic WooCommerce notice markup. Block-based cart and checkout pages show their own notices.

= Are coupon errors shown as floating notices? =

Coupon success messages are, and so are coupon errors that WooCommerce shows as a notice, for example "Sorry, it seems the coupon … is invalid - it has now been removed from your order." Those use the Invalid Coupon setting. On the classic cart, an invalid-code error is shown inline next to the coupon field, so it stays where WooCommerce puts it.

= Which plugins does the scanner know? =

SUMO Reward Points, PW Gift Cards, SUMO Waitlist, YITH Wishlist, WooCommerce Subscriptions and WooCommerce Points and Rewards. Developers can add more with the `ainfn_scanned_plugin_notifications` filter.

= Can I add my own notifications? =

Yes. Create them in the Custom Notifications tab and show them with the `[ainfn_show]` shortcode or the JavaScript API.

== Screenshots ==

1. Standard WooCommerce notices shown as floating, dismissible messages.
2. Per-notification control for the built-in WooCommerce notices.
3. Auto-detected notices from other plugins and your own custom notifications.
4. Design controls: presets, colours per notice type, radius, padding and shadows.
5. Live preview of every notice type.

== Changelog ==

= 1.2.0 =
* Renamed to All-IN Floating Notices for WooCommerce (slug and text domain `all-in-floating-notices-for-woocommerce`).
* Removed: the Custom CSS setting. Arbitrary CSS is no longer stored or printed; use the design settings or your theme's Additional CSS.
* Changed: the `[ainfn_show]` shortcode queues its notice with `wp_add_inline_script()` on the enqueued front-end script instead of printing a `<script>` tag. The admin preview and copy-ID buttons no longer use inline `onclick` handlers.
* Changed: every function, class, constant, option, transient, AJAX action, hook, shortcode, script handle and JavaScript global now uses the `ainfn` / `AINFN` / `AinfnFloatingNotices` prefix.

= 1.1.2 =
* Security: only store managers can add auto-detected notices (visitors can't add entries to the admin list any more). Developers can allow guests with the `ainfn_allow_guest_notice_detection` filter.
* Fixed: all admin output is escaped (`esc_html_e`, `esc_attr`).
* Added: readme.txt, GPL LICENSE file, translation template, `Requires Plugins: woocommerce`, Donate link, screenshots. The readme no longer says invalid-coupon errors are converted (WooCommerce shows them inline on the classic cart).
* Fixed: the "Invalid Coupon" setting now works. Coupon errors that WooCommerce shows as a notice (for example "Sorry, it seems the coupon … is invalid - it has now been removed from your order.") use that setting. Before, they used "Coupon Removed" when the message mentioned "removed", and "General Error" otherwise. Errors WooCommerce shows inline next to the coupon field are unchanged.
* Fixed: long notice text no longer runs under the × close button, on desktop, mobile and RTL, at any "Padding" setting.

= 1.1.1 and earlier =
* See the repository history.

== Upgrade Notice ==

= 1.2.0 =
New name and prefix (All-IN Floating Notices for WooCommerce). The Custom CSS setting is removed.

= 1.1.2 =
Hardening: auto-detection of notices now runs only for store managers.
