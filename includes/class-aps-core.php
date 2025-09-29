<?php
/**
 * Core plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class APS_Core {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'loaded'));
        
        // Initialize components
        new APS_Admin();
        new APS_Product_Tab();
        new APS_Scheduler();
        
        // Add AJAX handlers - make sure these are properly registered
        add_action('wp_ajax_aps_sync_single_product', array($this, 'ajax_sync_single_product'));
        add_action('wp_ajax_aps_sync_all_products', array($this, 'ajax_sync_all_products'));
        add_action('wp_ajax_aps_get_sync_status', array($this, 'ajax_get_sync_status'));
        
        // Add scheduled sync action and bulk sync process action
        add_action('aps_scheduled_sync', array($this, 'run_scheduled_sync_process'));
        add_action('aps_bulk_sync_process', array($this, 'run_bulk_sync_process'));
        
        // Debug: Log when AJAX handlers are registered
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('APS: AJAX handlers registered for aps_sync_single_product, aps_sync_all_products, aps_get_sync_status');
        }
    }
    
    public function init() {
        // Load textdomain for translations
        load_plugin_textdomain('auto-product-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function loaded() {
        // Plugin fully loaded
    }
    
    /**
     * AJAX handler for single product sync
     */
    public function ajax_sync_single_product() {
        check_ajax_referer('aps_nonce', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $product_id = intval($_POST['product_id']);
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }
        
        $extractor = new APS_Price_Extractor();
        $result = $extractor->sync_product_price($product_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX handler for bulk sync
     */
    public function ajax_sync_all_products() {
        check_ajax_referer('aps_nonce', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        // Get products to sync
        $products = self::get_sync_enabled_products();
        
        if (empty($products)) {
            wp_send_json_error('No products with sync enabled found.');
            return;
        }
        
        // Initialize status tracking
        set_transient('aps_bulk_sync_status', array(
            'running' => true,
            'completed' => 0,
            'total' => count($products),
            'failed' => 0,
            'current_product' => 'Preparing...'
        ), 3600);
        
        // Schedule the bulk sync to run immediately via WordPress cron
        wp_schedule_single_event(time(), 'aps_bulk_sync_process');
        
        // Trigger WordPress cron to run immediately
        if (function_exists('wp_cron')) {
            spawn_cron();
        }
        
        wp_send_json_success('Bulk sync started with ' . count($products) . ' products.');
    }
    
    /**
     * Run scheduled sync process
     */
    public function run_scheduled_sync_process() {
        $extractor = new APS_Price_Extractor();
        $extractor->bulk_sync_process();
    }
    
    /**
     * Run bulk sync process (triggered by WordPress cron)
     */
    public function run_bulk_sync_process() {
        // Ensure this runs in background
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('APS: Starting bulk sync process via WordPress cron');
        }
        
        $extractor = new APS_Price_Extractor();
        $extractor->bulk_sync_process();
    }
    
    /**
     * AJAX handler for sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('aps_nonce', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $status = get_transient('aps_bulk_sync_status');
        if (!$status) {
            $status = array(
                'running' => false, 
                'completed' => 0, 
                'total' => 0, 
                'failed' => 0,
                'current_product' => ''
            );
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('APS: Status check - ' . json_encode($status));
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * Get products with sync enabled (HPOS compatible)
     */
    public static function get_sync_enabled_products() {
        // Use WooCommerce product query for HPOS compatibility
        $query = new WC_Product_Query(array(
            'limit' => -1,
            'status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_aps_enable_sync',
                    'value' => 'yes',
                    'compare' => '='
                ),
                array(
                    'key' => '_aps_url',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));
        
        $products = $query->get_products();
        
        // Convert WC_Product objects to post objects for backward compatibility
        $post_objects = array();
        foreach ($products as $product) {
            $post = get_post($product->get_id());
            if ($post) {
                $post_objects[] = $post;
            }
        }
        
        return $post_objects;
    }
    
    /**
     * Get all products with URLs (for admin table) - HPOS compatible
     * Updated to exclude products without URLs
     */
    public static function get_products_with_urls() {
        // Use WooCommerce product query for HPOS compatibility
        $query = new WC_Product_Query(array(
            'limit' => -1,
            'status' => array('publish', 'draft', 'private'),
            'meta_query' => array(
                array(
                    'key' => '_aps_url',
                    'value' => '',
                    'compare' => '!='
                ),
                array(
                    'key' => '_aps_url',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $products = $query->get_products();
        
        // Convert WC_Product objects to post objects and filter out empty URLs
        $post_objects = array();
        foreach ($products as $product) {
            $url = self::get_product_meta($product->get_id(), '_aps_url');
            if (!empty(trim($url))) { // Double check URL is not empty
                $post = get_post($product->get_id());
                if ($post) {
                    $post_objects[] = $post;
                }
            }
        }
        
        return $post_objects;
    }
    
    /**
     * Get product meta value (HPOS compatible)
     */
    public static function get_product_meta($product_id, $meta_key, $single = true) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $single ? '' : array();
        }
        
        // Use WooCommerce meta data API for HPOS compatibility
        $meta_data = $product->get_meta($meta_key, $single);
        return $meta_data;
    }
    
    /**
     * Update product meta value (HPOS compatible)
     */
    public static function update_product_meta($product_id, $meta_key, $meta_value) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Use WooCommerce meta data API for HPOS compatibility
        $product->update_meta_data($meta_key, $meta_value);
        $product->save();
        
        return true;
    }
    
    /**
     * Get product categories (HPOS compatible)
     */
    public static function get_product_categories($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }
        
        $category_ids = $product->get_category_ids();
        $categories = array();
        
        foreach ($category_ids as $category_id) {
            $term = get_term($category_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = $term->name;
            }
        }
        
        return $categories;
    }
    
    /**
     * Delete product meta value (HPOS compatible)
     */
    public static function delete_product_meta($product_id, $meta_key) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Use WooCommerce meta data API for HPOS compatibility
        $product->delete_meta_data($meta_key);
        $product->save();
        
        return true;
    }
    
    /**
     * Format currency according to WooCommerce settings
     */
    public static function format_currency($amount) {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }
        return '$' . number_format($amount, 2);
    }
    
    /**
     * Log sync activity
     */
    public static function log_sync_activity($product_id, $status, $message = '', $old_price = 0, $new_price = 0, $old_sale_price = 0, $new_sale_price = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aps_sync_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'product_id' => $product_id,
                'status' => $status,
                'message' => $message,
                'old_price' => $old_price,
                'new_price' => $new_price,
                'old_sale_price' => $old_sale_price,
                'new_sale_price' => $new_sale_price,
                'sync_time' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s')
        );
    }
}