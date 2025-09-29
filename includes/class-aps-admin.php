<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class APS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'save_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Auto Product Sync', 'auto-product-sync'),
            __('Auto Product Sync', 'auto-product-sync'),
            'manage_options',
            'auto-product-sync',
            array($this, 'admin_page'),
            'dashicons-update',
            56
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_auto-product-sync' && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('aps-admin', APS_PLUGIN_URL . 'assets/admin.js', array('jquery'), APS_VERSION, true);
        wp_enqueue_style('aps-admin', APS_PLUGIN_URL . 'assets/admin.css', array(), APS_VERSION);
        
        // Fix JavaScript localization - ensure proper escaping
        wp_localize_script('aps-admin', 'aps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps_nonce'),
            'strings' => array(
                'sync_single' => esc_js(__('Syncing product...', 'auto-product-sync')),
                'sync_all' => esc_js(__('Starting bulk sync...', 'auto-product-sync')),
                'sync_complete' => esc_js(__('Sync completed!', 'auto-product-sync')),
                'sync_error' => esc_js(__('Sync failed!', 'auto-product-sync')),
                'confirm_bulk' => esc_js(__('This will sync all enabled products. This may take several minutes. Continue?', 'auto-product-sync'))
            )
        ));
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (isset($_POST['aps_save_settings']) && wp_verify_nonce($_POST['aps_nonce'], 'aps_settings')) {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            update_option('aps_schedule_frequency', sanitize_text_field($_POST['aps_schedule_frequency']));
            update_option('aps_schedule_time', sanitize_text_field($_POST['aps_schedule_time']));
            update_option('aps_detailed_logging', sanitize_text_field($_POST['aps_detailed_logging']));
            update_option('aps_admin_email', sanitize_email($_POST['aps_admin_email']));
            
            // Reschedule cron job
            $scheduler = new APS_Scheduler();
            $scheduler->schedule_sync();
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'auto-product-sync') . '</p></div>';
            });
        }
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $products = APS_Core::get_products_with_urls();
        $schedule_frequency = get_option('aps_schedule_frequency', 'off');
        $schedule_time = get_option('aps_schedule_time', '02:00');
        $detailed_logging = get_option('aps_detailed_logging', 'no');
        $admin_email = get_option('aps_admin_email', get_option('admin_email'));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Auto Product Sync', 'auto-product-sync'); ?></h1>
            
            <div class="aps-admin-container">
                <!-- Settings Section -->
                <div class="aps-settings-section">
                    <h2><?php _e('Settings', 'auto-product-sync'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('aps_settings', 'aps_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Schedule Updates', 'auto-product-sync'); ?></th>
                                <td>
                                    <select name="aps_schedule_frequency">
                                        <option value="off" <?php selected($schedule_frequency, 'off'); ?>><?php _e('Off', 'auto-product-sync'); ?></option>
                                        <option value="daily" <?php selected($schedule_frequency, 'daily'); ?>><?php _e('Daily', 'auto-product-sync'); ?></option>
                                        <option value="weekly" <?php selected($schedule_frequency, 'weekly'); ?>><?php _e('Weekly', 'auto-product-sync'); ?></option>
                                        <option value="monthly" <?php selected($schedule_frequency, 'monthly'); ?>><?php _e('Monthly', 'auto-product-sync'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Schedule Time', 'auto-product-sync'); ?></th>
                                <td>
                                    <input type="time" name="aps_schedule_time" value="<?php echo esc_attr($schedule_time); ?>">
                                    <p class="description"><?php _e('Time when scheduled sync should run (24-hour format)', 'auto-product-sync'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Detailed Logging', 'auto-product-sync'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aps_detailed_logging" value="yes" <?php checked($detailed_logging, 'yes'); ?>>
                                        <?php _e('Enable detailed logging', 'auto-product-sync'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Admin Email', 'auto-product-sync'); ?></th>
                                <td>
                                    <input type="email" name="aps_admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                                    <p class="description"><?php _e('Email address for error notifications', 'auto-product-sync'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Save Settings', 'auto-product-sync'), 'primary', 'aps_save_settings'); ?>
                    </form>
                </div>
                
                <!-- Products Section -->
                <div class="aps-products-section">
                    <h2><?php _e('Products with URLs', 'auto-product-sync'); ?></h2>
                    
                    <div class="aps-actions">
                        <button type="button" id="aps-sync-all" class="button button-primary">
                            <?php _e('Download Prices', 'auto-product-sync'); ?>
                        </button>
                        <span id="aps-sync-status" class="aps-status"></span>
                        <div id="aps-progress-container" style="display: none; margin-top: 10px;">
                            <div class="aps-progress">
                                <div id="aps-progress-bar" class="aps-progress-bar" style="width: 0%;"></div>
                                <div id="aps-progress-text" class="aps-progress-text">0%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="aps-table-container">
                        <table id="aps-products-table" class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="sortable aps-sortable" data-column="name"><?php _e('Product Name', 'auto-product-sync'); ?> <span class="sort-indicator">⇅</span></th>
                                    <th class="sortable aps-sortable" data-column="category"><?php _e('Category', 'auto-product-sync'); ?> <span class="sort-indicator">⇅</span></th>
                                    <th class="sortable aps-sortable" data-column="enabled"><?php _e('Enabled', 'auto-product-sync'); ?> <span class="sort-indicator">⇅</span></th>
                                    <th class="sortable aps-sortable" data-column="error"><?php _e('Error', 'auto-product-sync'); ?> <span class="sort-indicator">⇅</span></th>
                                    <th class="sortable aps-sortable" data-column="retry"><?php _e('Retry', 'auto-product-sync'); ?> <span class="sort-indicator">⇅</span></th>
                                    <th><?php _e('Actions', 'auto-product-sync'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="6"><?php _e('No products with URLs found', 'auto-product-sync'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): 
                                        $enabled = APS_Core::get_product_meta($product->ID, '_aps_enable_sync') === 'yes';
                                        $error_date = APS_Core::get_product_meta($product->ID, '_aps_error_date');
                                        $last_status = APS_Core::get_product_meta($product->ID, '_aps_last_status');
                                        $retry_count = APS_Core::get_product_meta($product->ID, '_aps_retry_count') ?: 0;
                                        $categories = APS_Core::get_product_categories($product->ID);
                                        $edit_url = admin_url('post.php?post=' . $product->ID . '&action=edit');
                                    ?>
                                        <tr data-product-id="<?php echo $product->ID; ?>">
                                            <td class="product-name">
                                                <a href="<?php echo esc_url($edit_url); ?>" target="_blank">
                                                    <?php echo esc_html($product->post_title); ?>
                                                </a>
                                            </td>
                                            <td class="product-category">
                                                <?php if (!empty($categories)): ?>
                                                    <?php foreach ($categories as $category): ?>
                                                        <div class="category-item"><?php echo esc_html($category); ?></div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="no-category">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-enabled">
                                                <span class="aps-status-<?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                                                    <?php echo $enabled ? __('Yes', 'auto-product-sync') : __('No', 'auto-product-sync'); ?>
                                                </span>
                                            </td>
                                            <td class="product-error">
                                                <?php if ($error_date): ?>
                                                    <div class="aps-error-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($error_date))); ?></div>
                                                <?php endif; ?>
                                                <?php if ($last_status): ?>
                                                    <div class="aps-last-status"><?php echo esc_html($last_status); ?></div>
                                                <?php endif; ?>
                                                <?php if (!$error_date && !$last_status): ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-retry">
                                                <span class="aps-retry-count"><?php echo intval($retry_count); ?>/3</span>
                                            </td>
                                            <td class="product-actions">
                                                <button type="button" class="button button-small aps-sync-single" data-product-id="<?php echo $product->ID; ?>">
                                                    <?php _e('Sync', 'auto-product-sync'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Log Section -->
                <div class="aps-log-section">
                    <h2><?php _e('Recent Activity', 'auto-product-sync'); ?></h2>
                    <?php $this->display_recent_logs(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display recent sync logs
     */
    private function display_recent_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aps_sync_log';
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, p.post_title 
            FROM $table_name l 
            LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID 
            ORDER BY l.sync_time DESC 
            LIMIT %d
        ", 20));
        
        if (empty($logs)) {
            echo '<p>' . __('No sync activity yet.', 'auto-product-sync') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Time', 'auto-product-sync') . '</th>';
        echo '<th>' . __('Product', 'auto-product-sync') . '</th>';
        echo '<th>' . __('Status', 'auto-product-sync') . '</th>';
        echo '<th>' . __('Message', 'auto-product-sync') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->sync_time))) . '</td>';
            echo '<td>' . esc_html($log->post_title ?: 'Product #' . $log->product_id) . '</td>';
            echo '<td><span class="aps-log-status-' . esc_attr($log->status) . '">' . esc_html(ucfirst($log->status)) . '</span></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
}