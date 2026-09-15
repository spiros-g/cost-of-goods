# SG Cost Manager

SG Cost Manager is a WooCommerce cost and profitability management layer built on the **native WooCommerce Cost of Goods Sold (COGS) API**.

## SG Plugins identity

- Product name: **SG Cost Manager**
- Repository: `spiros-g/sg-cost-manager`
- Legacy install folder/text domain/COGS_Studio namespace are preserved for compatibility.
- Release artifact: `sg-cost-manager-vX.Y.Z.zip`

## Requirements

- WordPress 6.7+
- PHP 8.1+
- WooCommerce 10.3+
- WooCommerce **Cost of Goods Sold** enabled under **WooCommerce → Settings → Advanced → Features**

## What v2.3.1 includes

- Native WooCommerce COGS as the only cost source of truth
- AJAX product/variation cost manager
- Product profit and margin calculations
- Historical order profitability using immutable order-item COGS snapshots, including quantity refunds
- Inventory cost, retail value, and potential gross profit dashboard
- Full native COGS audit history across SG Cost Manager, WooCommerce admin, REST API, WP-CLI, and other WooCommerce CRUD save paths
- HPOS compatibility declaration
- Non-destructive uninstall behavior
- Dashboard profitability periods: 7 / 30 / 90 days and custom date ranges
- Product management filters for category, type, stock status, defined/undefined COGS, and margin threshold
- Bulk COGS editing with variation-safe clear/inherit semantics
- CSV export/import with validation and audit source tracking
- Order profitability filters by realized status and date range
- Shared SpirosG WooCommerce admin design system based on WC Analytics Bridge

## Profit calculation basis

Product gross profit is:

`current product price - effective native COGS`

Order profitability uses product line totals after item-level refunds and excludes tax and shipping. SG Cost Manager preserves the original native line-item COGS on realized orders and reduces it by refunded quantity, preventing later product-cost changes from re-pricing historical gross profit.

## Plugin identity

`cogs-studio-for-woocommerce/cogs-studio-for-woocommerce.php`

## Repository

https://github.com/spiros-g/sg-cost-manager

## Development

CI validates PHP 8.1–8.4 syntax and runs WordPress/WooCommerce runtime smoke tests against the minimum supported stack and the current stable stack.

PHP syntax can also be checked locally with:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

## License

GPL-3.0-or-later.

## Releases

Releases are published only from explicit semantic version tags such as `v2.3.1`.

The release workflow:

- validates that the plugin header version matches the `readme.txt` stable tag;
- builds the production ZIP with the canonical `cogs-studio-for-woocommerce/` root;
- excludes tests, GitHub workflow files, development files, and legacy plugin identities;
- validates ZIP integrity and package identity;
- generates a SHA-256 checksum;
- validates that the pushed `vX.Y.Z` tag matches the plugin version and creates the GitHub Release;
- attaches the installable ZIP and `SHA256SUMS.txt`.

To publish a new version, update the plugin version, `readme.txt` stable tag and changelog, validate the exact commit, then create the matching `vX.Y.Z` tag.
