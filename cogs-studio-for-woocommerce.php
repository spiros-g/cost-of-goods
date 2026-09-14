<?php
/**
 * Plugin Name: COGS Studio for WooCommerce
 * Plugin URI: https://github.com/spiros-g/COGS-Studio-for-WooCommerce
 * Description: Native WooCommerce COGS management, profitability insights, stock valuation, and cost audit history.
 * Version: 2.1.2
 * Author: Spiros G.
 * Author URI: https://spirosg.dev/
 * Text Domain: cogs-studio-for-woocommerce
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.3
 * WC tested up to: 11.1
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'COGS_STUDIO_VERSION', '2.1.2' );
define( 'COGS_STUDIO_FILE', __FILE__ );
define( 'COGS_STUDIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'COGS_STUDIO_URL', plugin_dir_url( __FILE__ ) );

require_once COGS_STUDIO_PATH . 'includes/class-installer.php';
require_once COGS_STUDIO_PATH . 'includes/class-compatibility.php';
require_once COGS_STUDIO_PATH . 'includes/class-cost-history.php';
require_once COGS_STUDIO_PATH . 'includes/class-dashboard-cache.php';
require_once COGS_STUDIO_PATH . 'includes/class-order-cogs-snapshot.php';
require_once COGS_STUDIO_PATH . 'includes/class-product-cost-audit.php';
require_once COGS_STUDIO_PATH . 'includes/class-cogs-service.php';
require_once COGS_STUDIO_PATH . 'includes/class-profit-calculator.php';
require_once COGS_STUDIO_PATH . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once COGS_STUDIO_PATH . 'admin/class-admin.php';
}

register_activation_hook( __FILE__, array( 'COGS_Studio\\Installer', 'activate' ) );

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		COGS_Studio\Plugin::instance()->boot();
	},
	20
);
