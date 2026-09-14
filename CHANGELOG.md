# Changelog

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
- Added safe migration from legacy cog_cost meta.
- Added native variation COGS modes: inherit parent, override, and add to parent.
- Preserve explicit zero COGS values on variations during migration.
- Added live system/migration status and JavaScript syntax CI.
- Added HPOS compatibility declaration.
- Added CI PHP syntax checks.
