# RSS-Bridge Custom Bridge

This file is a custom bridge for [RSS-Bridge](https://github.com/RSS-Bridge/rss-bridge), a PHP project that generates RSS feeds for websites that don't provide them.

## Installation

1. Copy this file to your RSS-Bridge installation directory:
   ```
   /var/www/rss-bridge/bridges/
   ```
2. Set the correct permissions:
   ```bash
   sudo chown rss-bridge:www-data /var/www/rss-bridge/bridges/MyleneNetBridge.php
   sudo chmod 644 /var/www/rss-bridge/bridges/MyleneNetBridge.php
   ```
3. The bridge should now be available in your RSS-Bridge instance.

## Requirements
- PHP 7.4+
- RSS-Bridge installed and configured
- Proper permissions set as shown above

## Usage
Access this bridge through your RSS-Bridge web interface or directly via URL parameters.
