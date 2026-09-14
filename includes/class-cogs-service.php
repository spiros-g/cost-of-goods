<?php

namespace COGS_Studio;

use RuntimeException;
use WC_Product;

defined( 'ABSPATH' ) || exit;

final class COGS_Service {
	private Compatibility $compatibility;
	private Cost_History $history;

	public function __construct( Compatibility $compatibility, Cost_History $history ) {
		$this->compatibility = $compatibility;
		$this->history       = $history;
	}

	public function get_product( int $product_id ): WC_Product {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			throw new RuntimeException( __( 'Product not found.', 'cogs-studio-for-woocommerce' ) );
		}

		return $product;
	}

	public function nominal_cost( WC_Product $product ): ?float {
		if ( ! method_exists( $product, 'get_cogs_value' ) ) {
			return null;
		}

		$value = $product->get_cogs_value();
		return null === $value ? null : (float) $value;
	}

	public function total_cost( WC_Product $product ): float {
		if ( ! method_exists( $product, 'get_cogs_total_value' ) ) {
			return 0.0;
		}

		return (float) $product->get_cogs_total_value();
	}

	public function set_cost( int $product_id, ?float $cost, string $source = 'manual' ): WC_Product {
		if ( ! $this->compatibility->cogs_enabled() ) {
			throw new RuntimeException( __( 'WooCommerce Cost of Goods Sold must be enabled first.', 'cogs-studio-for-woocommerce' ) );
		}

		$product = $this->get_product( $product_id );

		if ( ! method_exists( $product, 'set_cogs_value' ) ) {
			throw new RuntimeException( __( 'This WooCommerce version does not expose the native COGS API.', 'cogs-studio-for-woocommerce' ) );
		}

		$old_cost = $this->nominal_cost( $product );
		$product->set_cogs_value( $cost );
		$product->save();

		$reloaded = $this->get_product( $product_id );
		$new_cost = $this->nominal_cost( $reloaded );

		$this->history->log( $product_id, $old_cost, $new_cost, $source );
		$this->clear_caches();

		return $reloaded;
	}

	public function clear_caches(): void {
		delete_transient( 'cogs_studio_dashboard_v2' );
	}
}
