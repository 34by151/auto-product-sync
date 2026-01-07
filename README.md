# Auto Product Sync

**Version:** 1.2.0  
**Author:** ArtInMetal  
**License:** GPL v2 or later  
**Requires WordPress:** 5.0+  
**Requires WooCommerce:** 6.0+  
**Requires PHP:** 7.4+  
**Tested up to:** WordPress 6.8.2, WooCommerce 8.5

Automatically sync product prices from external URLs for WooCommerce products using server cron jobs.

---

## Description

Auto Product Sync is a WooCommerce plugin that automatically extracts and updates product prices from external URLs. It's designed for businesses that need to keep their product prices synchronized with supplier websites or other external sources.

### Key Features

- **Automated Price Extraction** - Extracts regular and sale prices from external URLs
- **Server Cron Support** - Uses reliable server-side cron jobs instead of WordPress cron
- **Batch Processing** - Processes products in configurable batches to prevent server timeouts
- **Smart Filtering** - Skip recently synced products to reduce unnecessary API calls
- **GST Calculation** - Optionally add 10% GST to extracted prices
- **Retry Logic** - Automatically retries failed syncs with exponential backoff
- **Detailed Logging** - Track all sync activity and errors
- **HPOS Compatible** - Fully compatible with WooCommerce High-Performance Order Storage
- **Secure** - Uses secret keys to protect cron endpoints from unauthorized access

---

## Installation

### Automatic Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate**

### Manual Installation

1. Upload the `auto-product-sync` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Auto Product Sync → Settings** to configure

---

## Configuration

### 1. Plugin Settings

Go to **Auto Product Sync → Settings** to configure:

#### Server Cron Setup
- **Cron URL** - Your unique cron endpoint URL (copy this for server cron setup)
- **Secret Key** - Secure key that authenticates cron requests (regenerate if compromised)

#### Sync Settings
- **Skip Recently Synced Products** - Skip products synced within the last X hours (recommended: 24 hours)
- **URL Fetch Timeout** - Maximum time to wait when fetching prices (1-60 seconds, default: 30)
- **Batch Size** - Products per batch during sync (1-25, default: 10, recommended: 5)
- **Detailed Logging** - Enable comprehensive logging for troubleshooting
- **Admin Email** - Receive notifications for sync errors

### 2. Set Up Server Cron

Choose your hosting platform and follow the instructions in the Settings page:

#### Plesk (Recommended)
1. Log into Plesk
2. Navigate to **Scheduled Tasks**
3. Add new task with schedule: **Every 5 minutes** (`*/5` for minutes, `*` for all others)
4. Command: `/opt/plesk/php/8.3/bin/php /path/to/wp-content/plugins/auto-product-sync/aps-cron-trigger.php`

#### cPanel
1. Log into cPanel
2. Navigate to **Cron Jobs**
3. Select **Every 5 minutes**
4. Command: `/usr/bin/php -q /path/to/wp-content/plugins/auto-product-sync/aps-cron-trigger.php`

#### SSH/Crontab
```bash
crontab -e
```
Add line:
```bash
*/5 * * * * /usr/bin/php -q /path/to/wp-content/plugins/auto-product-sync/aps-cron-trigger.php
```

### 3. Configure Products

For each product you want to sync:

1. Edit the product in WooCommerce
2. Go to the **Auto Product Sync** tab
3. Check **Enable Sync**
4. Enter the **URL** where prices should be extracted from
5. Optionally check **Add GST** to add 10% to extracted prices
6. Click **Update** or **Publish**

---

## Usage

### Manual Sync

**Single Product:**
1. Go to **Auto Product Sync → Products**
2. Find the product in the list
3. Click **Sync** button

**All Products:**
1. Go to **Auto Product Sync → Products**
2. Click **Download Prices** button
3. Monitor progress in the status bar

### Automated Sync

Once server cron is configured, products sync automatically:
- Cron runs every 5 minutes
- Each run processes one batch (5-10 products)
- Products synced in last 24 hours are skipped (if enabled)
- Oldest products sync first

### View Activity

Go to **Auto Product Sync → Recent Activity** to see:
- Sync timestamps
- Success/failure status
- Price changes
- Error messages

---

## How It Works

### Price Extraction

The plugin attempts to extract prices using multiple methods:

1. **CSS Class Detection** - Searches for common price-related CSS classes
2. **Gentronics-Specific** - Handles custom Gentronics price formats
3. **Per-Item Patterns** - Detects "per item" pricing patterns
4. **Regex Fallback** - Uses pattern matching as last resort

### Batch Processing

Products are synced in batches to prevent server timeouts:
- Products without timestamps sync first
- Then products sync from oldest to newest timestamp
- Each cron run processes one batch
- 2-second delay between products within a batch

### Error Handling

When a product fails to sync:
1. Product marked with error status
2. Retry scheduled (1hr, 4hr, 12hr intervals)
3. After 3 failed attempts, product is hidden from catalog
4. Admin receives email notification

---

## Troubleshooting

### Cron Not Running

**Check cron output in Plesk/cPanel:**
- Look for "APS Cron Trigger" output
- Verify database connection
- Confirm site URL and secret key are found

**Common issues:**
- Wrong PHP binary path
- Incorrect file path
- File permissions (should be readable)
- Secret key mismatch

### Products Not Syncing

**Verify product setup:**
- "Enable Sync" is checked
- URL field is not empty
- URL is valid and accessible

**Check Recent Activity page:**
- Look for error messages
- Verify timestamps are updating

**Enable Detailed Logging:**
- Go to Settings → Check "Detailed Logging"
- Check logs at: `/wp-content/uploads/aps-logs/`

### Gateway Timeouts

If you get 504 Gateway Timeout errors:

1. **Reduce Batch Size** - Go to Settings, set to 5 or lower
2. **Increase Server Timeout** - In Plesk: PHP Settings → max_execution_time → 300
3. **Check Cron Frequency** - Ensure it runs every 5 minutes, not longer intervals

### Lock Issues

If you see "Sync already running" repeatedly:

1. Go to **Auto Product Sync → Products**
2. Click **Clear Lock** button
3. Wait 5 minutes for next cron run

---

## Database

### Tables Created

**`wp_aps_sync_log`**
- Stores sync history
- Tracks price changes
- Records errors and timestamps
- Indexed for performance

### Options Stored

- `aps_cron_secret_key` - Cron authentication key
- `aps_detailed_logging` - Logging preference
- `aps_admin_email` - Notification email
- `aps_fetch_timeout` - URL timeout setting
- `aps_batch_size` - Products per batch
- `aps_skip_recent_sync` - Skip recently synced toggle
- `aps_skip_recent_hours` - Hours to skip

### Product Meta Fields

- `_aps_enable_sync` - Enable/disable sync per product
- `_aps_url` - URL to extract prices from
- `_aps_add_gst` - Add 10% GST toggle
- `_aps_external_regular_price` - Extracted regular price
- `_aps_external_sale_price` - Extracted sale price
- `_aps_external_regular_price_inc_gst` - Regular price with GST
- `_aps_external_sale_price_inc_gst` - Sale price with GST
- `_aps_last_sync_timestamp` - Unix timestamp of last sync
- `_aps_last_status` - Last sync status message
- `_aps_error_date` - Date of last error
- `_aps_retry_count` - Current retry attempt count

---

## Security

### Cron Endpoint Protection

The cron endpoint (`?aps_cron=1`) is protected by:
- 32-character random secret key
- Constant-time string comparison (`hash_equals`)
- IP logging for all access attempts
- Failed access logged to plugin logs

### Best Practices

1. **Keep Secret Key Private** - Never share in public repositories
2. **Regenerate After Compromise** - Use the "Regenerate Key" button if exposed
3. **Monitor Logs** - Check for unauthorized access attempts
4. **Use HTTPS** - Ensure your site uses SSL/TLS
5. **Restrict File Permissions** - Keep plugin files non-writable by web server

---

## Performance

### Optimization Tips

1. **Enable "Skip Recently Synced"** - Reduces unnecessary syncs
2. **Adjust Skip Hours** - Set to 24-48 hours for stable pricing
3. **Optimize Batch Size** - Balance speed vs. server load
4. **Monitor Fetch Timeout** - Lower values speed up sync but may cause timeouts
5. **Use Caching** - Consider server-level caching for supplier URLs

### Server Requirements

- PHP 7.4 or higher (8.0+ recommended)
- MySQL 5.6 or higher
- WooCommerce 6.0 or higher
- Memory limit: 128MB minimum (256MB recommended)
- Max execution time: 60+ seconds for manual syncs

---

## Changelog

### Version 1.2.0 (Current)
- Added server cron support with 5-minute intervals
- Added "Skip Recently Synced Products" feature
- Removed WordPress cron dependency
- Updated cron instructions for Plesk, cPanel, SSH
- Improved lock handling for batch processing
- Added timezone conversion for timestamps
- Enhanced error reporting and logging

### Version 1.1.0
- Added batch processing for bulk sync
- Improved price extraction algorithms
- Added Gentronics-specific price detection
- Fixed HPOS compatibility issues
- Added sortable product table
- Improved error handling with retry logic

### Version 1.0.0
- Initial release

---

## Support

For issues, questions, or feature requests:

1. **Check Documentation** - Review this README and settings page instructions
2. **Enable Detailed Logging** - Helps diagnose issues
3. **Check Recent Activity** - View sync status and errors
4. **Review Server Logs** - Check Plesk/cPanel cron execution logs

---

## Credits

**Developed by:** ArtInMetal  
**Built with:** WordPress, WooCommerce, PHP, MySQL

---

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```
