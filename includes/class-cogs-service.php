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

	public function variation_mode( WC_Product $product ): string {
		if ( ! method_exists( $product, 'get_cogs_value_is_additive' ) ) {
			return 'simple';
		}

		if ( $product->get_cogs_value_is_additive() ) {
			return 'additive';
		}

		return null === $this->nominal_cost( $product ) ? 'inherit' : 'override';
	}

	public function set_cost( int $product_id, ?float $cost, string $source = 'manual', ?string $variation_mode = null ): WC_Product {
		if ( ! $this->compatibility->cogs_enabled() ) {
			throw new RuntimeException( __( 'WooCommerce Cost of Goods Sold must be enabled first.', 'cogs-studio-for-woocommerce' ) );
		}

		$product = $this->get_product( $product_id );

		if ( ! method_exists( $product, 'set_cogs_value' ) ) {
			throw new RuntimeException( __( 'This WooCommerce version does not expose the native COGS API.', 'cogs-studio-for-woocommerce' ) );
		}

		$old_cost = $this->nominal_cost( $product );
		$old_mode = $this->variation_mode( $product );

		if ( method_exists( $product, 'set_cogs_value_is_additive' ) && null !== $variation_mode ) {
			if ( ! in_array( $variation_mode, array( 'inherit', 'override', 'additive' ), true ) ) {
				throw new RuntimeException( __( 'Invalid variation COGS mode.', 'cogs-studio-for-woocommerce' ) );
			}

			if ( 'inherit' === $variation_mode ) {
				$cost = null;
			}

			$product->set_cogs_value_is_additive( 'additive' === $variation_mode );
		}

		$product->set_cogs_value( $cost );
		$product->save();

		$reloaded = $this->get_product( $product_id );
		$new_cost = $this->nominal_cost( $reloaded );
		$new_mode = $this->variation_mode( $reloaded );
		$mode_changed = $old_mode !== $new_mode;
		$history_source = $mode_changed && 'simple' !== $new_mode ? $source . '-' . $new_mode : $source;

		$this->history->log( $product_id, $old_cost, $new_cost, $history_source, $mode_changed );
		$this->clear_caches();

		return $reloaded;
	}

	public function clear_caches(): void {
		delete_transient( 'cogs_studio_dashboard_v2' );
	}
}
