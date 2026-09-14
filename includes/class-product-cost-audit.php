<?php

namespace COGS_Studio;

use WC_Product;

defined( 'ABSPATH' ) || exit;

final class Product_Cost_Audit {
	private Cost_History $history;

	/**
	 * Pre-save COGS state keyed by the in-memory product object ID.
	 *
	 * @var array<int, array{product_id:int,cost:?float,mode:string}>
	 */
	private array $before = array();

	public function __construct( Cost_History $history ) {
		$this->history = $history;
	}

	public function register(): void {
		add_action( 'woocommerce_before_product_object_save', array( $this, 'capture_before_save' ), 5, 1 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'record_after_save' ), 95, 1 );
	}

	public function capture_before_save( $product ): void {
		if ( ! $product instanceof WC_Product || ! method_exists( $product, 'get_cogs_value' ) ) {
			return;
		}

		$changes = $product->get_changes();
		if ( ! array_key_exists( 'cogs_value', $changes ) && ! array_key_exists( 'cogs_value_is_additive', $changes ) ) {
			return;
		}

		$key = spl_object_id( $product );

		if ( $product->get_id() <= 0 ) {
			$this->before[ $key ] = array(
				'product_id' => 0,
				'cost'       => null,
				'mode'       => method_exists( $product, 'get_cogs_value_is_additive' ) ? 'inherit' : 'simple',
			);
			return;
		}

		$persisted = wc_get_product( $product->get_id() );
		if ( ! $persisted instanceof WC_Product || ! method_exists( $persisted, 'get_cogs_value' ) ) {
			return;
		}

		$this->before[ $key ] = array(
			'product_id' => $product->get_id(),
			'cost'       => $this->nominal_cost( $persisted ),
			'mode'       => $this->variation_mode( $persisted ),
		);
	}

	public function record_after_save( $product ): void {
		if ( ! $product instanceof WC_Product || ! method_exists( $product, 'get_cogs_value' ) ) {
			return;
		}

		$key = spl_object_id( $product );
		if ( ! isset( $this->before[ $key ] ) ) {
			return;
		}

		$before = $this->before[ $key ];
		unset( $this->before[ $key ] );

		$new_cost     = $this->nominal_cost( $product );
		$new_mode     = $this->variation_mode( $product );
		$mode_changed = $before['mode'] !== $new_mode;
		$source       = Cost_History::current_source() ?? $this->detect_source();

		if ( $mode_changed && 'simple' !== $new_mode ) {
			$source .= '-' . $new_mode;
		}

		$this->history->log(
			(int) ( $before['product_id'] ?: $product->get_id() ),
			$before['cost'],
			$new_cost,
			$source,
			$mode_changed
		);
	}

	private function nominal_cost( WC_Product $product ): ?float {
		$value = $product->get_cogs_value();
		return null === $value ? null : (float) $value;
	}

	private function variation_mode( WC_Product $product ): string {
		if ( ! method_exists( $product, 'get_cogs_value_is_additive' ) ) {
			return 'simple';
		}

		if ( $product->get_cogs_value_is_additive() ) {
			return 'additive';
		}

		return null === $this->nominal_cost( $product ) ? 'inherit' : 'override';
	}

	private function detect_source(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest-api';
		}

		if ( is_admin() ) {
			return 'woocommerce-admin';
		}

		return 'woocommerce';
	}
}
