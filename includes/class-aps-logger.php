<?php
/**
 * Logging functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class APS_Logger {
    
    private $detailed_logging;
    
    public function __construct() {
        $this->detailed_logging = get_option('aps_detailed_logging', 'no') === 'yes';
    }
    
    /**
     * Log a message
     */
    public function log($message, $level = 'info') {
        // Always log errors and success messages
        if (!$this->detailed_logging && !in_array($level, array('error', 'success'))) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        
        // Write to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('APS: ' . $log_entry);
        }
        
        // Write to custom log file
        $this->write_to_log_file($log_entry);
    }
    
    /**
     * Write to custom log file
     */
    private function write_to_log_file($log_entry) {
        $uploads_dir = wp_upload_dir();
        $log_dir = $uploads_dir['basedir'] . '/aps-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Create .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($log_dir . '/.htaccess', $htaccess_content);
        }
        
        $log_file = $log_dir . '/aps-' . date('Y-m') . '.log';
        
        // Append to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Rotate logs (keep only last 3 months)
        $this->rotate_logs($log_dir);
    }
    
    /**
     * Rotate log files
     */
    private function rotate_logs($log_dir) {
        $files = glob($log_dir . '/aps-*.log');
        
        if (count($files) <= 3) {
            return;
        }
        
        // Sort files by modification time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files
        $files_to_remove = array_slice($files, 0, -3);
        foreach ($files_to_remove as $file) {
            unlink($file);
        }
    }
    
    /**
     * Get log files
     */
    public function get_log_files() {
        $uploads_dir = wp_upload_dir();
        $log_dir = $uploads_dir['basedir'] . '/aps-logs';
        
        if (!file_exists($log_dir)) {
            return array();
        }
        
        $files = glob($log_dir . '/aps-*.log');
        $log_files = array();
        
        foreach ($files as $file) {
            $log_files[] = array(
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            );
        }
        
        // Sort by modification time (newest first)
        usort($log_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $log_files;
    }
    
    /**
     * Read log file content
     */
    public function read_log_file($filename) {
        $uploads_dir = wp_upload_dir();
        $log_file = $uploads_dir['basedir'] . '/aps-logs/' . basename($filename);
        
        if (!file_exists($log_file)) {
            return false;
        }
        
        return file_get_contents($log_file);
    }
    
    /**
     * Clear log file
     */
    public function clear_log_file($filename) {
        $uploads_dir = wp_upload_dir();
        $log_file = $uploads_dir['basedir'] . '/aps-logs/' . basename($filename);
        
        if (file_exists($log_file)) {
            return unlink($log_file);
        }
        
        return false;
    }
}