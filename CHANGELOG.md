# Changelog

## 2.2.0

- Added selectable dashboard profitability periods for 7, 30, 90 days, plus custom date ranges.
- Added product filters for category, product type, stock status, defined/undefined COGS, and maximum margin.
- Added bulk COGS set and clear actions for up to 200 selected products per operation.
- Bulk variation updates use native override semantics when setting a cost and native inherit semantics when clearing.
- Added CSV export of product/variation COGS and profitability fields.
- Added validated CSV import using product ID or SKU, with native variation mode support and cost-history source tracking.
- Added realized-order filtering by order status and date range.
- Expanded the AJAX management UI while keeping WooCommerce native COGS as the sole product-cost source of truth.

## 2.1.2

- Aligned the minimum WordPress requirement with WooCommerce 10.3: WordPress 6.7+.
- Declared testing through WooCommerce 11.1.
- Filtered profitability orders to paid/refunded statuses only.
- Added dashboard cache invalidation for relevant product and order changes.
- Optimized the COGS audit observer to skip unrelated product saves.
- Added audit coverage for COGS set on a product's first save.
- Added immutable paid-order item COGS snapshots so refund reporting remains historical even if product COGS changes later.
- Added runtime regression tests for historical order COGS snapshots and refunds.
- Added CI coverage for the minimum stack (WordPress 6.7 + WooCommerce 10.3 + PHP 8.1) and the current stable stack.
- Removed the unused legacy `cog_cost` migration layer.
- Reduced front-end overhead by loading the admin controller only in admin requests.

## 2.1.1

- Fully renamed the WordPress plugin identity to COGS Studio for WooCommerce.
- Plugin directory is now `cogs-studio-for-woocommerce/`.
- Main plugin file is now `cogs-studio-for-woocommerce.php`.
- CI, runtime tests, packaging, and plugin basename checks now use the new identity.
- Removed the legacy install-path compatibility constraint.

## 2.1.0

- Added global native COGS audit tracking for WooCommerce product saves.
- Cost History now captures changes made through COGS Studio, WooCommerce admin, REST API, WP-CLI, and other WooCommerce CRUD save paths.
- Prevented duplicate audit entries by routing COGS Studio writes through the same observer.
- Added audit source tracking and regression coverage.
- Made CI package names derive automatically from the plugin version.

## 2.0.0

- Rebuilt the plugin around WooCommerce native COGS.
- Added modular service architecture.
- Added AJAX product cost manager.
- Added profitability dashboard and order profitability view.
- Added inventory valuation metrics.
- Added cost history table.
- Added native variation COGS modes: inherit parent, override, and add to parent.
- Preserved explicit zero COGS values on variations.
- Added live system/migration status and JavaScript syntax CI.
- Added HPOS compatibility declaration.
- Added CI PHP syntax checks.
