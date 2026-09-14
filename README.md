# COGS Studio for WooCommerce

COGS Studio is a WooCommerce cost and profitability management layer built on the **native WooCommerce Cost of Goods Sold (COGS) API**.

## Requirements

- WordPress 6.6+
- PHP 8.1+
- WooCommerce 10.3+
- WooCommerce **Cost of Goods Sold** enabled under **WooCommerce → Settings → Advanced → Features**

## What v2.1.1 includes

- Native WooCommerce COGS as the only cost source of truth
- AJAX product/variation cost manager
- Product profit and margin calculations
- Recent order profitability using WooCommerce order COGS snapshots
- Inventory cost, retail value, and potential gross profit dashboard
- Full native COGS audit history across COGS Studio, WooCommerce admin, REST API, WP-CLI, and other WooCommerce CRUD save paths
- Safe migration from the legacy `cog_cost` product meta
- HPOS compatibility declaration
- Non-destructive uninstall behavior

## Legacy migration

The original plugin stored costs in `cog_cost`. COGS Studio can migrate these values into WooCommerce native COGS.

Safety behavior:

- existing native non-zero costs are not overwritten;
- legacy `cog_cost` values are not deleted;
- migrated changes are added to COGS Studio history;
- migration can be run in batches from **WooCommerce → COGS Studio → System**.

## Profit calculation basis

Product gross profit is:

`current product price - effective native COGS`

Order profitability uses product line totals after item-level refunds and excludes tax and shipping. This keeps the calculation focused on merchandise gross profit rather than treating tax or shipping as product revenue.

## Plugin identity

`cogs-studio-for-woocommerce/cogs-studio-for-woocommerce.php`

## Repository

https://github.com/spiros-g/COGS-Studio-for-WooCommerce

## Development

PHP syntax can be checked with:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

## License

GPL-3.0-or-later.
