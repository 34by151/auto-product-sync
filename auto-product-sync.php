<?php
/**
 * Plugin Name: Auto Product Sync
 * Plugin URI: https://yoursite.com/auto-product-sync
 * Description: Automatically sync product prices from external URLs for WooCommerce products
 * Version: 1.0.9
 * Author: ArtInMetal
 * License: GPL v2 or later
 * Text Domain: auto-product-sync
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('APS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('APS_VERSION', '1.0.9');

// Include required files
require_once APS_PLUGIN_PATH . 'includes/class-aps-core.php';
require_once APS_PLUGIN_PATH . 'includes/class-aps-admin.php';
require_once APS_PLUGIN_PATH . 'includes/class-aps-product-tab.php';
require_once APS_PLUGIN_PATH . 'includes/class-aps-price-extractor.php';
require_once APS_PLUGIN_PATH . 'includes/class-aps-scheduler.php';
require_once APS_PLUGIN_PATH . 'includes/class-aps-logger.php';

// Initialize the plugin
function aps_init() {
    new APS_Core();
}
add_action('plugins_loaded', 'aps_init');

// Activation hook
register_activation_hook(__FILE__, 'aps_activate');
function aps_activate() {
    // Create custom database tables if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'aps_sync_log';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        sync_time datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) NOT NULL,
        message text,
        old_price decimal(10,2),
        new_price decimal(10,2),
        old_sale_price decimal(10,2),
        new_sale_price decimal(10,2),
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Set default options
    if (!get_option('aps_schedule_frequency')) {
        update_option('aps_schedule_frequency', 'off');
    }
    if (!get_option('aps_schedule_time')) {
        update_option('aps_schedule_time', '02:00');
    }
    if (!get_option('aps_detailed_logging')) {
        update_option('aps_detailed_logging', 'no');
    }
    if (!get_option('aps_admin_email')) {
        update_option('aps_admin_email', get_option('admin_email'));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'aps_deactivate');
function aps_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('aps_scheduled_sync');
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aps_add_settings_link');
function aps_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=auto-product-sync">' . __('Settings', 'auto-product-sync') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}