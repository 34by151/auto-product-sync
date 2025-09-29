<?php
/**
 * Price extraction functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class APS_Price_Extractor {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new APS_Logger();
        
        // Hook for bulk sync process
        add_action('aps_bulk_sync_process', array($this, 'bulk_sync_process'));
        add_action('aps_retry_failed_sync', array($this, 'retry_failed_syncs'));
    }
    
    /**
     * Sync single product price
     */
    public function sync_product_price($product_id, $is_retry = false, $retry_count = 0) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array('success' => false, 'message' => 'Product not found');
        }
        
        $enable_sync = APS_Core::get_product_meta($product_id, '_aps_enable_sync');
        if ($enable_sync !== 'yes') {
            return array('success' => false, 'message' => 'Sync not enabled for this product');
        }
        
        $url = APS_Core::get_product_meta($product_id, '_aps_url');
        if (empty($url)) {
            return array('success' => false, 'message' => 'No URL specified');
        }
        
        $this->logger->log("Starting price extraction for product ID: $product_id, URL: $url", 'info');
        
        // Extract prices from URL
        $extraction_result = $this->extract_prices_from_url($url);
        
        if (!$extraction_result['success']) {
            $this->handle_extraction_error($product_id, $extraction_result['message'], $retry_count);
            return array('success' => false, 'message' => $extraction_result['message']);
        }
        
        // Clear any previous error
        APS_Core::delete_product_meta($product_id, '_aps_error_date');
        APS_Core::delete_product_meta($product_id, '_aps_retry_count');
        APS_Core::update_product_meta($product_id, '_aps_last_status', 'Success: Prices updated');
        
        // Update product with extracted prices
        $this->update_product_prices($product_id, $extraction_result['prices']);
        
        $this->logger->log("Successfully updated prices for product ID: $product_id", 'success');
        APS_Core::log_sync_activity($product_id, 'success', 'Prices updated successfully');
        
        return array('success' => true, 'message' => 'Prices updated successfully');
    }
    
    /**
     * Extract prices from URL
     */
    private function extract_prices_from_url($url) {
        $this->logger->log("Fetching content from: $url", 'debug');
        
        // Try multiple methods to fetch the URL
        $content = $this->fetch_url_content($url);
        
        if (!$content) {
            return array('success' => false, 'message' => 'Failed to fetch content from URL after trying all methods');
        }
        
        $this->logger->log("Content fetched successfully, length: " . strlen($content), 'debug');
        
        // Parse prices from content
        $prices = $this->parse_prices_from_content($content);
        
        if (empty($prices['regular_price']) || $prices['regular_price'] <= 0) {
            return array('success' => false, 'message' => 'No valid regular price found');
        }
        
        return array('success' => true, 'prices' => $prices);
    }
    
    /**
     * Fetch URL content using multiple fallback methods
     */
    private function fetch_url_content($url) {
        // Method 1: Standard wp_remote_get with modern SSL
        $content = $this->fetch_with_wp_remote($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'sslverify' => true,
        ));
        
        if ($content) {
            $this->logger->log("Successfully fetched with standard wp_remote_get", 'debug');
            return $content;
        }
        
        // Method 2: wp_remote_get with relaxed SSL verification
        $content = $this->fetch_with_wp_remote($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'sslverify' => false,
            'sslcertificates' => false,
        ));
        
        if ($content) {
            $this->logger->log("Successfully fetched with relaxed SSL verification", 'debug');
            return $content;
        }
        
        // Method 3: wp_remote_get with specific SSL/TLS settings
        $content = $this->fetch_with_wp_remote($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; APS-Bot/1.0)',
            'sslverify' => false,
            'curl' => array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            )
        ));
        
        if ($content) {
            $this->logger->log("Successfully fetched with custom cURL SSL settings", 'debug');
            return $content;
        }
        
        // Method 4: Direct cURL as fallback
        if (function_exists('curl_init')) {
            $content = $this->fetch_with_curl($url);
            if ($content) {
                $this->logger->log("Successfully fetched with direct cURL", 'debug');
                return $content;
            }
        }
        
        // Method 5: File get contents as last resort (if allowed)
        if (ini_get('allow_url_fopen')) {
            $content = $this->fetch_with_file_get_contents($url);
            if ($content) {
                $this->logger->log("Successfully fetched with file_get_contents", 'debug');
                return $content;
            }
        }
        
        $this->logger->log("All fetch methods failed for URL: $url", 'error');
        return false;
    }
    
    /**
     * Fetch using wp_remote_get
     */
    private function fetch_with_wp_remote($url, $args = array()) {
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->logger->log("wp_remote_get error: " . $response->get_error_message(), 'debug');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $this->logger->log("wp_remote_get HTTP error: $http_code", 'debug');
            return false;
        }
        
        $content = wp_remote_retrieve_body($response);
        return !empty($content) ? $content : false;
    }
    
    /**
     * Fetch using direct cURL
     */
    private function fetch_with_curl($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => '',
            CURLOPT_COOKIEFILE => '',
        ));
        
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->log("cURL error: $error", 'debug');
            return false;
        }
        
        if ($http_code !== 200) {
            $this->logger->log("cURL HTTP error: $http_code", 'debug');
            return false;
        }
        
        return $content;
    }
    
    /**
     * Fetch using file_get_contents
     */
    private function fetch_with_file_get_contents($url) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'follow_location' => true,
                'max_redirects' => 5,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            )
        ));
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            $error = error_get_last();
            $this->logger->log("file_get_contents error: " . ($error['message'] ?? 'Unknown error'), 'debug');
            return false;
        }
        
        return $content;
    }
    
    /**
     * Parse prices from HTML content
     */
    private function parse_prices_from_content($content) {
        $prices = array(
            'regular_price' => 0,
            'sale_price' => 0
        );
        
        // Load HTML into DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        $xpath = new DOMXPath($dom);
        
        // Common price selectors to try
        $price_selectors = array(
            // Common price classes
            '.price',
            '.product-price',
            '.regular-price',
            '.current-price',
            '.price-current',
            '.woocommerce-price-amount',
            '.amount',
            
            // Specific selectors for the example sites
            '.price .amount',
            '.wc-price',
            '.product-price-value',
            '.price-box .price',
            
            // ID selectors
            '#price',
            '#product-price',
            
            // Microdata
            '[itemprop="price"]',
            '[itemprop="lowPrice"]',
            '[itemprop="offers"] .price'
        );
        
        // Sale price selectors
        $sale_price_selectors = array(
            '.sale-price',
            '.special-price',
            '.discount-price',
            '.offer-price',
            '.reduced-price',
            '.on-sale',
            '.price-sale',
            '.sale .amount',
            '[itemprop="offers"] .sale-price'
        );
        
        // Try to find regular price
        foreach ($price_selectors as $selector) {
            $nodes = $xpath->query("//*[contains(@class, '" . str_replace('.', '', $selector) . "')]");
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $price_text = $node->textContent;
                    $price = $this->extract_price_from_text($price_text);
                    if ($price > 0) {
                        $prices['regular_price'] = $price;
                        $this->logger->log("Found regular price using selector '$selector': $price", 'debug');
                        break 2;
                    }
                }
            }
        }
        
        // If no price found with selectors, try regex on entire content
        if ($prices['regular_price'] == 0) {
            $prices['regular_price'] = $this->extract_price_with_regex($content);
            if ($prices['regular_price'] > 0) {
                $this->logger->log("Found regular price using regex: " . $prices['regular_price'], 'debug');
            }
        }
        
        // Try to find sale price
        foreach ($sale_price_selectors as $selector) {
            $nodes = $xpath->query("//*[contains(@class, '" . str_replace('.', '', $selector) . "')]");
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $price_text = $node->textContent;
                    $price = $this->extract_price_from_text($price_text);
                    if ($price > 0 && $price < $prices['regular_price']) {
                        $prices['sale_price'] = $price;
                        $this->logger->log("Found sale price using selector '$selector': $price", 'debug');
                        break 2;
                    }
                }
            }
        }
        
        // Look for crossed-out prices (usually regular price when on sale)
        $crossed_out_selectors = array(
            '.strikethrough',
            '.line-through',
            '.was-price',
            '.old-price',
            '.crossed-out',
            '[style*="text-decoration: line-through"]',
            '[style*="text-decoration:line-through"]'
        );
        
        foreach ($crossed_out_selectors as $selector) {
            $nodes = $xpath->query("//*[contains(@class, '" . str_replace('.', '', $selector) . "') or contains(@style, 'line-through')]");
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $price_text = $node->textContent;
                    $price = $this->extract_price_from_text($price_text);
                    if ($price > 0) {
                        // This might be the original regular price
                        if ($prices['sale_price'] == 0 && $price > $prices['regular_price']) {
                            // Swap them - crossed out is regular, current is sale
                            $prices['sale_price'] = $prices['regular_price'];
                            $prices['regular_price'] = $price;
                            $this->logger->log("Found crossed-out regular price: $price, sale price: " . $prices['sale_price'], 'debug');
                        }
                        break 2;
                    }
                }
            }
        }
        
        // GENTRONICS-SPECIFIC CSS SELECTOR FALLBACK METHOD (SECOND TO LAST)
        if ($prices['regular_price'] == 0) {
            $gentronics_prices = $this->extract_gentronics_css_prices($content, $xpath);
            if (!empty($gentronics_prices)) {
                $prices['regular_price'] = $gentronics_prices['regular_price'];
                $prices['sale_price'] = $gentronics_prices['sale_price'];
                $this->logger->log("Found prices using Gentronics CSS selector: Regular {$prices['regular_price']}, Sale {$prices['sale_price']}", 'debug');
            }
        }
        
        // "PER ITEM" PATTERN FALLBACK METHOD (LAST RESORT)
        if ($prices['regular_price'] == 0) {
            $per_item_prices = $this->extract_per_item_prices($content);
            if (!empty($per_item_prices)) {
                $prices['regular_price'] = $per_item_prices['regular_price'];
                $prices['sale_price'] = $per_item_prices['sale_price'];
                $this->logger->log("Found prices using per-item pattern: Regular {$prices['regular_price']}, Sale {$prices['sale_price']}", 'debug');
            }
        }
        
        return $prices;
    }
    
    /**
     * Extract prices using Gentronics-specific CSS selector: p.gentronics-price.price
     */
    private function extract_gentronics_css_prices($content, $xpath) {
        $found_prices = array();
        
        // Primary selector: p.gentronics-price.price
        $primary_query = "//p[contains(@class, 'gentronics-price') and contains(@class, 'price')]";
        $nodes = $xpath->query($primary_query);
        
        $this->logger->log("Trying Gentronics CSS selector - found " . $nodes->length . " elements", 'debug');
        
        if ($nodes->length > 0) {
            foreach ($nodes as $node) {
                $price_text = trim($node->textContent);
                $this->logger->log("Found Gentronics element with text: '$price_text'", 'debug');
                
                // Extract price from text like "$2640per item" or "$1197.9per item"
                $price = $this->extract_gentronics_price_from_text($price_text);
                if ($price > 0) {
                    $found_prices[] = $price;
                    $this->logger->log("Extracted Gentronics price: $price", 'debug');
                }
            }
        }
        
        // Fallback selectors if primary doesn't work
        if (empty($found_prices)) {
            $fallback_queries = array(
                "//p[@class='gentronics-price price']", // Exact class match
                "//*[contains(@class, 'gentronics-price')]", // Any element with gentronics-price
                "//p[contains(@class, 'price')]//*[contains(text(), 'per item')]/..", // Look for "per item" within price elements
            );
            
            foreach ($fallback_queries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $price_text = trim($node->textContent);
                        $price = $this->extract_gentronics_price_from_text($price_text);
                        if ($price > 0) {
                            $found_prices[] = $price;
                            $this->logger->log("Found Gentronics price using fallback query '$query': $price", 'debug');
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }
        
        // Sort prices (highest first) for regular/sale price assignment
        rsort($found_prices);
        
        $result = array('regular_price' => 0, 'sale_price' => 0);
        
        if (count($found_prices) >= 2) {
            // Multiple prices - highest is regular, next highest is sale
            $result['regular_price'] = $found_prices[0];
            $result['sale_price'] = $found_prices[1];
        } elseif (count($found_prices) == 1) {
            // Single price - treat as regular price
            $result['regular_price'] = $found_prices[0];
        }
        
        return $result;
    }
    
    /**
     * Extract price from Gentronics-specific text format
     */
    private function extract_gentronics_price_from_text($text) {
        // Clean up the text
        $text = trim($text);
        
        // Patterns specifically for Gentronics format: "$2640per item", "$1197.9per item", etc.
        $patterns = array(
            '/\$(\d+(?:\.\d+)?)per\s*item/i',     // $2640per item, $1197.9per item
            '/\$(\d+(?:\.\d+)?)per\s*unit/i',     // $2640per unit
            '/\$(\d+(?:\.\d+)?)per\s*piece/i',    // $2640per piece
            '/\$(\d+(?:\.\d+)?)\s*per\s*item/i',  // $2640 per item (with space)
            '/\$(\d+(?:\.\d+)?)\s*per\s*unit/i',  // $2640 per unit
            '/\$(\d+(?:,\d{3})*(?:\.\d+)?)per\s*item/i', // $2,640per item (with comma)
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $price = str_replace(',', '', $matches[1]);
                $price = floatval($price);
                if ($price > 0) {
                    return $price;
                }
            }
        }
        
        // If no "per item" pattern found, try to extract any price from the text
        if (preg_match('/\$(\d+(?:,\d{3})*(?:\.\d+)?)/', $text, $matches)) {
            $price = str_replace(',', '', $matches[1]);
            $price = floatval($price);
            if ($price > 0) {
                return $price;
            }
        }
        
        return 0;
    }
    
    /**
     * Extract prices using "per item/per unit" pattern matching
     */
    private function extract_per_item_prices($content) {
        $found_prices = array();
        
        // Patterns to match "per item/per unit" pricing
        $per_item_patterns = array(
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*item/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*unit/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*piece/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*each/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?\/\s*item/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?\/\s*unit/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?\/\s*piece/i',
            '/\$(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?\/\s*each/i'
        );
        
        foreach ($per_item_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = str_replace(',', '', $match);
                    $price = floatval($price);
                    if ($price > 0) {
                        $found_prices[] = $price;
                        $this->logger->log("Found per-item price using pattern '$pattern': $price", 'debug');
                    }
                }
            }
        }
        
        // Also look for patterns without $ symbol but with per item text
        $no_dollar_patterns = array(
            '/(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*item/i',
            '/(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*unit/i',
            '/(\d{1,4}(?:,\d{3})*(?:\.\d{2})?)(?:\s*)?per\s*piece/i'
        );
        
        foreach ($no_dollar_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = str_replace(',', '', $match);
                    $price = floatval($price);
                    if ($price > 0) {
                        $found_prices[] = $price;
                        $this->logger->log("Found per-item price (no $) using pattern '$pattern': $price", 'debug');
                    }
                }
            }
        }
        
        // Remove duplicates and sort (highest first)
        $found_prices = array_unique($found_prices);
        rsort($found_prices);
        
        $result = array('regular_price' => 0, 'sale_price' => 0);
        
        if (count($found_prices) >= 2) {
            // Multiple prices - highest is regular, next highest is sale
            $result['regular_price'] = $found_prices[0];
            $result['sale_price'] = $found_prices[1];
        } elseif (count($found_prices) == 1) {
            // Single price - treat as regular price
            $result['regular_price'] = $found_prices[0];
        }
        
        return $result;
    }
    
    /**
     * Extract price from text using regex
     */
    private function extract_price_from_text($text) {
        // Remove extra whitespace
        $text = trim($text);
        
        // Try different price patterns
        $patterns = array(
            '/\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i',  // $1,234.56 or $123.45
            '/(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/',     // 1,234.56 or 123.45
            '/(\d+\.\d{2})/',                         // 123.45
            '/(\d+)/'                                 // 123
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $price = str_replace(',', '', $matches[1]);
                $price = floatval($price);
                if ($price > 0) {
                    return $price;
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Extract price using regex on entire content
     */
    private function extract_price_with_regex($content) {
        // Look for price patterns in the HTML
        $patterns = array(
            '/\$(\d{1,3}(?:,\d{3})*\.\d{2})/i',  // $1,234.56
            '/"price"[^>]*>.*?\$?(\d{1,3}(?:,\d{3})*\.\d{2})/i',  // "price" with amount
            '/class="[^"]*price[^"]*"[^>]*>.*?\$?(\d{1,3}(?:,\d{3})*\.\d{2})/i'  // price class
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = str_replace(',', '', $match);
                    $price = floatval($price);
                    if ($price > 0) {
                        return $price;
                    }
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Update product prices (HPOS compatible)
     */
    private function update_product_prices($product_id, $prices) {
        $add_gst = APS_Core::get_product_meta($product_id, '_aps_add_gst') === 'yes';
        
        // Get old prices for logging
        $old_regular = APS_Core::get_product_meta($product_id, '_aps_external_regular_price_inc_gst');
        $old_sale = APS_Core::get_product_meta($product_id, '_aps_external_sale_price_inc_gst');
        
        // Store extracted prices using HPOS-compatible methods
        APS_Core::update_product_meta($product_id, '_aps_external_regular_price', $prices['regular_price']);
        APS_Core::update_product_meta($product_id, '_aps_external_sale_price', $prices['sale_price']);
        
        // Calculate prices with GST
        $regular_price_inc_gst = $add_gst ? round($prices['regular_price'] * 1.1, 2) : $prices['regular_price'];
        $sale_price_inc_gst = $add_gst ? round($prices['sale_price'] * 1.1, 2) : $prices['sale_price'];
        
        // Store prices with GST using HPOS-compatible methods
        APS_Core::update_product_meta($product_id, '_aps_external_regular_price_inc_gst', $regular_price_inc_gst);
        APS_Core::update_product_meta($product_id, '_aps_external_sale_price_inc_gst', $sale_price_inc_gst);
        
        // Get the WooCommerce product object for price updates
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Update WooCommerce product prices using the product object
        $product->set_regular_price($regular_price_inc_gst);
        
        if ($sale_price_inc_gst > 0) {
            $product->set_sale_price($sale_price_inc_gst);
            $product->set_price($sale_price_inc_gst);
        } else {
            $product->set_sale_price('');
            $product->set_price($regular_price_inc_gst);
        }
        
        // Save the product
        $product->save();
        
        // Clear product cache
        wc_delete_product_transients($product_id);
        
        // Log the changes
        APS_Core::log_sync_activity(
            $product_id, 
            'success', 
            'Prices updated', 
            $old_regular, 
            $regular_price_inc_gst, 
            $old_sale, 
            $sale_price_inc_gst
        );
        
        $this->logger->log("Updated product $product_id: Regular: $regular_price_inc_gst, Sale: $sale_price_inc_gst", 'info');
    }
    
    /**
     * Handle extraction error
     */
    private function handle_extraction_error($product_id, $message, $retry_count = 0) {
        $max_retries = 3; // Total of 4 attempts (initial + 3 retries)
        
        // Update last status
        APS_Core::update_product_meta($product_id, '_aps_last_status', 'Error: ' . $message);
        
        APS_Core::log_sync_activity($product_id, 'error', $message);
        $this->logger->log("Error extracting prices for product $product_id: $message (Attempt: " . ($retry_count + 1) . ")", 'error');
        
        if ($retry_count < $max_retries) {
            // Schedule retry
            $retry_count++;
            APS_Core::update_product_meta($product_id, '_aps_retry_count', $retry_count);
            
            // Schedule next retry (1 hour for first retry, then 4 hour intervals)
            $delay = $retry_count == 1 ? 3600 : 14400; // 1 hour or 4 hours
            wp_schedule_single_event(time() + $delay, 'aps_retry_single_product', array($product_id, $retry_count));
            
            $this->logger->log("Scheduled retry #$retry_count for product $product_id in " . ($delay / 3600) . " hours", 'info');
        } else {
            // All retries failed - hide product and send admin notification
            APS_Core::update_product_meta($product_id, '_aps_error_date', current_time('mysql'));
            
            // Hide product using WooCommerce API
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_catalog_visibility('hidden');
                $product->save();
            }
            
            APS_Core::delete_product_meta($product_id, '_aps_retry_count');
            APS_Core::update_product_meta($product_id, '_aps_last_status', 'Failed: All retries exhausted');
            
            $this->send_admin_notification($product_id, $message);
            $this->logger->log("All retry attempts failed for product $product_id. Product hidden.", 'error');
        }
    }
    
    /**
     * Bulk sync process
     */
    public function bulk_sync_process() {
        $products = APS_Core::get_sync_enabled_products();
        
        if (empty($products)) {
            set_transient('aps_bulk_sync_status', array(
                'running' => false,
                'completed' => 0,
                'total' => 0,
                'failed' => 0,
                'finished' => true,
                'current_product' => ''
            ), 3600);
            return;
        }
        
        set_transient('aps_bulk_sync_status', array(
            'running' => true,
            'completed' => 0,
            'total' => count($products),
            'failed' => 0,
            'current_product' => ''
        ), 3600);
        
        $completed = 0;
        $failed = 0;
        $retry_queue = array();
        
        foreach ($products as $product) {
            // Update status with current product name
            set_transient('aps_bulk_sync_status', array(
                'running' => true,
                'completed' => $completed,
                'total' => count($products),
                'failed' => $failed,
                'current_product' => $product->post_title
            ), 3600);
            
            // Skip products that are scheduled for retry
            $retry_count = APS_Core::get_product_meta($product->ID, '_aps_retry_count');
            if ($retry_count) {
                $retry_queue[] = $product->ID;
                continue;
            }
            
            $result = $this->sync_product_price($product->ID);
            
            if ($result['success']) {
                $completed++;
            } else {
                $failed++;
            }
            
            // Update progress after each product
            set_transient('aps_bulk_sync_status', array(
                'running' => true,
                'completed' => $completed,
                'total' => count($products),
                'failed' => $failed,
                'current_product' => $product->post_title
            ), 3600);
            
            // Small delay to prevent server overload
            sleep(1);
        }
        
        // Process retry queue
        foreach ($retry_queue as $product_id) {
            $product_post = get_post($product_id);
            $product_name = $product_post ? $product_post->post_title : "Product #$product_id";
            
            // Update status with current retry product
            set_transient('aps_bulk_sync_status', array(
                'running' => true,
                'completed' => $completed,
                'total' => count($products),
                'failed' => $failed,
                'current_product' => $product_name . ' (retry)'
            ), 3600);
            
            $retry_count = APS_Core::get_product_meta($product_id, '_aps_retry_count');
            $result = $this->sync_product_price($product_id, true, $retry_count);
            
            if ($result['success']) {
                $completed++;
            } else {
                $failed++;
            }
            
            sleep(1);
        }
        
        // Final status
        set_transient('aps_bulk_sync_status', array(
            'running' => false,
            'completed' => $completed,
            'total' => count($products),
            'failed' => $failed,
            'finished' => true,
            'current_product' => ''
        ), 3600);
        
        $this->logger->log("Bulk sync completed. $completed successful, $failed failed.", 'info');
    }
    
    /**
     * Retry single product (scheduled event)
     */
    public function retry_single_product($product_id, $retry_count) {
        $this->sync_product_price($product_id, true, $retry_count);
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification($product_id, $error_message) {
        $admin_email = get_option('aps_admin_email', get_option('admin_email'));
        $product = get_post($product_id);
        $product_name = $product ? $product->post_title : "Product #$product_id";
        
        $subject = '[' . get_bloginfo('name') . '] Auto Product Sync Error';
        
        $message = "Hello,\n\n";
        $message .= "The Auto Product Sync plugin encountered an error while trying to update prices for the following product:\n\n";
        $message .= "Product: $product_name\n";
        $message .= "Product ID: $product_id\n";
        $message .= "Error: $error_message\n\n";
        $message .= "The product has been hidden from your catalog until the issue is resolved.\n\n";
        $message .= "You can edit the product here: " . admin_url("post.php?post=$product_id&action=edit") . "\n\n";
        $message .= "Best regards,\n";
        $message .= "Auto Product Sync Plugin";
        
        wp_mail($admin_email, $subject, $message);
        
        // Also show admin notice
        add_action('admin_notices', function() use ($product_name, $error_message) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Auto Product Sync Error:</strong> Failed to sync prices for "' . esc_html($product_name) . '". ' . esc_html($error_message);
            echo '</p></div>';
        });
    }
}

// Hook for single product retry
add_action('aps_retry_single_product', function($product_id, $retry_count) {
    $extractor = new APS_Price_Extractor();
    $extractor->retry_single_product($product_id, $retry_count);
}, 10, 2);