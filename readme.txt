=== SG Cost Manager ===
Contributors: spiros-g
Tags: woocommerce, cogs, cost of goods, profit, inventory
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.3.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Native WooCommerce COGS management, profitability, stock valuation, and cost audit history.

== Description ==

SG Cost Manager uses WooCommerce's native Cost of Goods Sold API as its source of truth and adds a management dashboard, inline AJAX cost editing, order profitability, stock valuation, and full native COGS change history.

WooCommerce 10.3 or newer is required and the WooCommerce Cost of Goods Sold feature must be enabled.

== Changelog ==

= 2.3.1 =
* Standardized public branding as SG Cost Manager.
* Updated canonical GitHub identity to spiros-g/sg-cost-manager and added Update URI metadata.
* Preserved the cogs-studio-for-woocommerce install folder, namespace, text domain and native WooCommerce COGS data contracts.
* Switched releases to the common SG Plugins tag-driven workflow.

= 2.3.0 =
* Aligned the COGS Studio admin UI with the shared WC Analytics Bridge design system.
* Added the shared dark violet application header, version/readiness pills, violet navigation and canonical card styling.
* Standardized dashboard KPIs, filters, tables, bulk controls, system cards and form focus states to the shared plugin motif.
* Added icons to the COGS Studio section navigation.
* No COGS calculations, audit, CSV, order profitability or product-cost behavior changes.

= 2.2.0 =
* Added 7/30/90-day and custom dashboard profitability ranges.
* Added product filters for category, type, stock status, COGS state, and margin threshold.
* Added bulk COGS set/clear actions with variation-safe inherit/override behavior.
* Added CSV COGS export/import with validation and audit tracking.
* Added order status and date-range filters.

= 2.1.2 =
* Production hardening for reporting accuracy, audit performance, cache invalidation, and minimum/current stack testing.
* Removed the unused legacy migration layer.

= 2.1.1 =
* Fully renamed plugin directory and main entry file to cogs-studio-for-woocommerce.

= 2.1.0 =
* Added global native COGS audit tracking across WooCommerce CRUD save paths.
* Added audit source tracking and duplicate prevention.

= 2.0.0 =
* Rebuilt around native WooCommerce COGS.
* Added product cost manager and profitability dashboard.
* Added order profitability and stock valuation.
* Added cost history.
