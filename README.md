## Version History

### Version 1.0.9 (Latest)
**Released:** Current Version  
**Changes:**
- **Fixed:** Bulk sync connection errors by using proper WordPress cron background processing
- **Fixed:** Progress bar stuck at 0% - now uses proper WordPress action hooks
- **Enhanced:** Better error handling and debugging for bulk sync AJAX calls
- **Enhanced:** Improved status polling with connection error recovery
- **Enhanced:** Added spawn_cron() to trigger background processing immediately
- **Enhanced:** Better error messages and debugging for connection issues
- **Enhanced:** Enhanced JavaScript polling with retry logic and error recovery
- **Enhanced:** More detailed console logging for troubleshooting bulk sync issues
- **Fixed:** Proper WordPress hook registration for aps_bulk_sync_process action
- **Enhanced:** Background process validation and error reporting

### Version 1.0.8
**Released:** Previous Version  
**Changes:**
- **Fixed:** Progress bar now properly shows 0% → 100% during bulk sync operations
- **Enhanced:** "In Progress" message now shows current product name being synced
- **Enhanced:** Real-time progress tracking with product names
- **Enhanced:** Improved bulk sync status tracking with current product display

### Version 1.0.4
**Released:** Earlier Version  
**Changes:**
- **Enhanced:** Full WooCommerce High-Performance Order Storage (HPOS) compatibility
- **Enhanced:** Declared HPOS compatibility using WooCommerce FeaturesUtil
- **Enhanced:** Replaced direct post meta queries with WooCommerce data APIs
- **Enhanced:** Updated plugin requirements (WC 6.0+, WP 5.0+, PHP 7.4+)

### Version 1.0.3
**Released:** Earlier Version  
**Changes:**
- **Fixed:** Replaced generic Gentronics extraction with precise CSS selector targeting
- **Enhanced:** New method specifically targets `p.gentronics-price.price` elements
- **Fixed:** Now successfully extracts prices from Gentronics URLs

### Version 1.0.2
**Released:** Earlier Version  
**Changes:**
- **Enhanced:** Added specialized Gentronics-specific price extraction
- **Enhanced:** "Per item/per unit" pattern fallback extraction
- **Enhanced:** Intelligent price sorting - highest price becomes regular price

### Version 1.0.1
**Released:** Earlier Version  
**Changes:**
- **Fixed:** Changed plugin author from "Your Name" to "ArtInMetal"
- **Enhanced:** Admin table now shows retry count column (0/3, 1/3, etc.)
- **Enhanced:** Error column now displays both error date and last status message
- **Fixed:** SSL/TLS connectivity issues with multiple fallback methods

### Version 1.0.0
**Released:** Initial Version  
**Features:**
- Basic price extraction functionality
- WordPress admin menu integration
- WooCommerce product tab
- Scheduled sync capabilities
- Error handling and retry logic
- Logging system
- GST calculation  
**Changes:**
- **Fixed:** Individual "Sync" button now properly updates product prices
- **Fixed:** "Download Prices" bulk sync functionality restored and improved
- **Fixed:** Scheduled sync now properly triggers price updates for all enabled products
- **Enhanced:** Added progress messages next to individual "Sync" buttons with success/error feedback
- **Enhanced:** Added progress messages next to "Download Prices" button showing completion status
- **Enhanced:** Added "Category" column to products table showing WooCommerce product categories
- **Enhanced:** Multiple categories are displayed on separate lines for better readability
- **Enhanced:** Improved table sorting - click column headers to sort by any column
- **Enhanced:** Products table now only shows products that actually have URLs set
- **Enhanced:** Better error handling and display in both individual and bulk sync operations
- **Enhanced:** Enhanced responsive design for mobile devices
- **Enhanced:** Improved AJAX error handling and user feedback
- **Enhanced:** Category column hidden on mobile devices to save space

### Version 1.0.4
**Released:** Previous Version  
**Changes:**
- **Enhanced:** Full WooCommerce High-Performance Order Storage (HPOS) compatibility
- **Enhanced:** Declared HPOS compatibility using WooCommerce FeaturesUtil
- **Enhanced:** Replaced direct post meta queries with WooCommerce data APIs
- **Enhanced:** Updated plugin requirements (WC 6.0+, WP 5.0+, PHP 7.4+)

### Version 1.0.3
**Released:** Earlier Version  
**Changes:**
- **Fixed:** Replaced generic Gentronics extraction with precise CSS selector targeting
- **Enhanced:** New method specifically targets `p.gentronics-price.price` elements
- **Fixed:** Now successfully extracts prices from Gentronics URLs# Auto Product Sync Plugin Directory Structure

Create the following directory structure in your WordPress plugins folder:

```
wp-content/plugins/auto-product-sync/
├── auto-product-sync.php                    (Main plugin file)
├── README.md                                (Plugin documentation)
├── includes/
│   ├── class-aps-core.php                  (Core functionality class)
│   ├── class-aps-admin.php                 (Admin interface class)
│   ├── class-aps-product-tab.php           (Product tab functionality)
│   ├── class-aps-price-extractor.php       (Price extraction logic)
│   ├── class-aps-scheduler.php             (Scheduling functionality)
│   └── class-aps-logger.php                (Logging functionality)
├── assets/
│   ├── admin.js                            (Admin JavaScript)
│   └── admin.css                           (Admin CSS styles)
└── languages/
    └── auto-product-sync.pot               (Translation template - optional)
```

## System Requirements

### Version 1.0.4 Requirements
- **WordPress**: 5.0 or higher
- **WooCommerce**: 6.0 or higher (for HPOS support)
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher / MariaDB 10.0 or higher

### HPOS Compatibility
This plugin fully supports WooCommerce's High-Performance Order Storage (HPOS):
- Declares compatibility with `custom_order_tables` feature
- Uses WooCommerce data APIs instead of direct database queries
- Compatible with both traditional and HPOS storage modes
- Future-ready for WooCommerce's storage evolution

## Installation Instructions

1. **Download/Create Files**: Create all the files listed above with their respective content
2. **Upload to WordPress**: Upload the entire `auto-product-sync` folder to `wp-content/plugins/`
3. **Activate Plugin**: Go to WordPress Admin → Plugins and activate "Auto Product Sync"
4. **Configure Settings**: Go to "Auto Product Sync" in the admin menu to configure settings

**Note**: If you're using WooCommerce 8.2+ with HPOS enabled, this plugin will automatically detect and use the appropriate data storage methods.

## File Descriptions

### Main Files
- **auto-product-sync.php**: Main plugin file with headers and initialization
- **README.md**: Documentation for the plugin

### Includes Directory
- **class-aps-core.php**: Core plugin functionality and AJAX handlers
- **class-aps-admin.php**: Admin interface, settings page, and product table
- **class-aps-product-tab.php**: WooCommerce product tab integration
- **class-aps-price-extractor.php**: Price extraction and sync logic
- **class-aps-scheduler.php**: Cron scheduling functionality
- **class-aps-logger.php**: Logging system with file rotation

### Assets Directory
- **admin.js**: JavaScript for admin interface functionality
- **admin.css**: CSS styles for admin interface

### Languages Directory
- **auto-product-sync.pot**: Translation template (create if you need multilingual support)

## Post-Installation Setup

After installing the plugin:

1. **Configure Settings**:
   - Go to "Auto Product Sync" in WordPress admin menu
   - Set your preferred schedule frequency
   - Configure the schedule time
   - Set admin email for error notifications
   - Enable detailed logging if needed

2. **Configure Products**:
   - Edit any WooCommerce product
   - Go to the "Auto Product Sync" tab
   - Enable sync and enter the URL to extract prices from
   - Optionally enable GST calculation
   - Test with "Download Prices" button

3. **Test Functionality**:
   - Use the provided example URLs to test price extraction
   - Monitor the activity log for successful operations
   - Check error handling by testing with invalid URLs

## Features Included

✅ **Admin Menu Tab**: Complete admin interface with product table  
✅ **Product Tab**: Integration with WooCommerce product editing  
✅ **Price Extraction**: Smart price detection from HTML content  
✅ **GST Calculation**: Optional 10% GST addition with proper rounding  
✅ **Error Handling**: 4-attempt retry system with exponential backoff  
✅ **Scheduling**: Configurable cron-based automatic updates  
✅ **Logging**: Detailed logging with file rotation  
✅ **Admin Notifications**: Email and WordPress admin notices  
✅ **Bulk Operations**: Sync all products at once  
✅ **Table Sorting**: Sortable product table with filtering  
✅ **AJAX Interface**: Responsive admin interface  
✅ **Currency Formatting**: WooCommerce-compatible price display  
✅ **Product Visibility**: Auto-hide products with persistent errors  

The plugin is fully functional and ready for immediate use!