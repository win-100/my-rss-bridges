# RSS-Bridge Custom Bridge

This file is a custom bridge for [RSS-Bridge](https://github.com/RSS-Bridge/rss-bridge), a PHP project that generates RSS feeds for websites that don't provide them.

## Installation

1. Log into your server (via SSH), then open a shell as the `rss-bridge` user:
   ```bash
   sudo yunohost app shell rss-bridge
   ```
2. Clone this repository into the rss-bridge home directory:
   ```bash
   git clone https://github.com/win-100/my-rss-bridges.git ~/my-rss-bridges
   ```
3. Run the deployment script (still as rss-bridge) to install the bridges:
   ```bash
   ~/my-rss-bridges/deploy-rss-bridges.sh
   ```
   This script will symlink the bridge files into your RSS-Bridge installation directory (usually /var/www/rss-bridge/bridges/).

## Usage
Once deployed, your custom bridges will be accessible through the RSS-Bridge web interface or directly via URL parameters.
