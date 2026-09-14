<?php

namespace COGS_Studio;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$compatibility = new Compatibility();
		if ( ! $compatibility->supported_woocommerce() ) {
			$this->register_dependency_notice( $compatibility );
			return;
		}

		Installer::maybe_upgrade();
		$history       = new Cost_History();
		$cache         = new Dashboard_Cache();
		$cache->register();
		$audit           = new Product_Cost_Audit( $history );
		$audit->register();
		$order_snapshots = new Order_COGS_Snapshot();
		$order_snapshots->register();
		$cogs            = new COGS_Service( $compatibility, $history );
		$profit          = new Profit_Calculator( $cogs, $order_snapshots );

		if ( is_admin() ) {
			$admin = new Admin( $compatibility, $cogs, $profit, $history );
			$admin->register();
		}
	}

	private function register_dependency_notice( Compatibility $compatibility ): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $compatibility ): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				if ( ! $compatibility->woocommerce_active() ) {
					$message = __( 'COGS Studio for WooCommerce requires WooCommerce to be installed and active.', 'cogs-studio-for-woocommerce' );
				} else {
					$message = sprintf(
						/* translators: %s: minimum WooCommerce version. */
						__( 'COGS Studio for WooCommerce requires WooCommerce %s or newer.', 'cogs-studio-for-woocommerce' ),
						Compatibility::MIN_WC_VERSION
					);
				}

				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		);
	}
}
