=== COGS Studio for WooCommerce ===
Contributors: spiros-g
Tags: woocommerce, cogs, cost of goods, profit, inventory
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.1.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Native WooCommerce COGS management, profitability, stock valuation, and cost audit history.

== Description ==

COGS Studio uses WooCommerce's native Cost of Goods Sold API as its source of truth and adds a management dashboard, inline AJAX cost editing, order profitability, stock valuation, and full native COGS change history.

WooCommerce 10.3 or newer is required and the WooCommerce Cost of Goods Sold feature must be enabled.

== Changelog ==

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
