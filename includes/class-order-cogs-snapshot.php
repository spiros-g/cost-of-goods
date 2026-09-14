<?php

namespace COGS_Studio;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves the native order-item COGS value before WooCommerce refund
 * recalculation can re-price historical items from the current product COGS.
 *
 * This is a reporting snapshot only. Product COGS remains owned entirely by
 * WooCommerce core.
 */
final class Order_COGS_Snapshot {
	public const ITEM_META_KEY = '_cogs_studio_original_cogs';

	public function register(): void {
		add_action( 'woocommerce_after_order_object_save', array( $this, 'snapshot_saved_order' ), 100, 1 );
		add_action( 'woocommerce_create_refund', array( $this, 'snapshot_before_refund' ), 1, 2 );
	}

	public function snapshot_saved_order( $order ): void {
		if ( ! $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			return;
		}

		$eligible_statuses = array_values( array_unique( array_merge( wc_get_is_paid_statuses(), array( 'refunded' ) ) ) );
		if ( ! in_array( $order->get_status(), $eligible_statuses, true ) ) {
			return;
		}

		$this->snapshot_order( $order );
	}

	public function snapshot_before_refund( $refund, array $args ): void {
		$order_id = absint( $args['order_id'] ?? 0 );

		if ( $order_id <= 0 && $refund instanceof WC_Order_Refund ) {
			$order_id = $refund->get_parent_id();
		}

		if ( $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$this->snapshot_order( $order );
		}
	}

	public function snapshot_order( WC_Order $order ): void {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( $item->meta_exists( self::ITEM_META_KEY ) ) {
				continue;
			}

			if ( ! method_exists( $item, 'get_cogs_value' ) ) {
				continue;
			}

			$item->add_meta_data( self::ITEM_META_KEY, (float) $item->get_cogs_value( 'edit' ), true );
			$item->save_meta_data();
		}
	}

	public function original_item_cogs( $item ): float {
		if ( $item->meta_exists( self::ITEM_META_KEY ) ) {
			return (float) $item->get_meta( self::ITEM_META_KEY, true );
		}

		return method_exists( $item, 'get_cogs_value' ) ? (float) $item->get_cogs_value( 'edit' ) : 0.0;
	}

	public function net_order_cogs( WC_Order $order ): float {
		$total = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$quantity = abs( (float) $item->get_quantity() );
			if ( $quantity <= 0 ) {
				continue;
			}

			$refunded_quantity = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
			$remaining_ratio   = max( 0.0, min( 1.0, ( $quantity - $refunded_quantity ) / $quantity ) );

			$total += $this->original_item_cogs( $item ) * $remaining_ratio;
		}

		return max( 0.0, $total );
	}
}
