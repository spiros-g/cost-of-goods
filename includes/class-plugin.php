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
		Installer::maybe_upgrade();

		$compatibility = new Compatibility();
		$history       = new Cost_History();
		$cache         = new Dashboard_Cache();
		$cache->register();
		$audit         = new Product_Cost_Audit( $history );
		$audit->register();
		$cogs          = new COGS_Service( $compatibility, $history );
		$profit        = new Profit_Calculator( $cogs );

		if ( is_admin() ) {
			$admin = new Admin( $compatibility, $cogs, $profit, $history );
			$admin->register();
		}
	}
}
