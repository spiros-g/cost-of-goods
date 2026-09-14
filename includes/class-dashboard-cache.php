<?php

namespace COGS_Studio;

use WC_Product;

defined( 'ABSPATH' ) || exit;

final class Dashboard_Cache {
	public const TRANSIENT_KEY = 'cogs_studio_dashboard_v2';

	/**
	 * Product fields that affect inventory valuation or profitability.
	 *
	 * @var string[]
	 */
	private const PRODUCT_FIELDS = array(
		'cogs_value',
		'cogs_value_is_additive',
		'price',
		'regular_price',
		'sale_price',
		'stock_quantity',
		'stock_status',
		'manage_stock',
	);

	public function register(): void {
		add_action( 'woocommerce_before_product_object_save', array( $this, 'maybe_clear_for_product' ), 20, 1 );
		add_action( 'woocommerce_new_order', array( $this, 'clear' ), 20 );
		add_action( 'woocommerce_update_order', array( $this, 'clear' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'clear' ), 20 );
		add_action( 'woocommerce_order_refunded', array( $this, 'clear' ), 20 );
		add_action( 'woocommerce_delete_product', array( $this, 'clear' ), 20 );
		add_action( 'woocommerce_trash_product', array( $this, 'clear' ), 20 );
	}

	public static function clear_dashboard(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	public function clear( ...$ignored ): void {
		self::clear_dashboard();
	}

	public function maybe_clear_for_product( $product ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$changes = array_keys( $product->get_changes() );
		if ( array_intersect( self::PRODUCT_FIELDS, $changes ) ) {
			self::clear_dashboard();
		}
	}
}
