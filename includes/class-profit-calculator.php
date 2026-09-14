<?php

namespace COGS_Studio;

use WC_Order;
use WC_Product;

defined( 'ABSPATH' ) || exit;

final class Profit_Calculator {
	private COGS_Service $cogs;
	private Order_COGS_Snapshot $order_snapshots;

	public function __construct( COGS_Service $cogs, Order_COGS_Snapshot $order_snapshots ) {
		$this->cogs            = $cogs;
		$this->order_snapshots = $order_snapshots;
	}

	public function product_metrics( WC_Product $product ): array {
		$price  = (float) $product->get_price( 'edit' );
		$cost   = $this->cogs->total_cost( $product );
		$profit = $price - $cost;
		$margin = $price > 0 ? ( $profit / $price ) * 100 : 0.0;
		$markup = $cost > 0 ? ( $profit / $cost ) * 100 : 0.0;

		$stock_quantity = $product->managing_stock() ? (float) ( $product->get_stock_quantity() ?? 0 ) : null;
		$stock_cost     = null === $stock_quantity ? null : $stock_quantity * $cost;
		$stock_value    = null === $stock_quantity ? null : $stock_quantity * $price;
		$stock_profit   = null === $stock_quantity ? null : $stock_quantity * $profit;

		return array(
			'price'          => $price,
			'cost'           => $cost,
			'profit'         => $profit,
			'margin'         => $margin,
			'markup'         => $markup,
			'stock_quantity' => $stock_quantity,
			'stock_cost'     => $stock_cost,
			'stock_value'    => $stock_value,
			'stock_profit'   => $stock_profit,
		);
	}

	public function order_metrics( WC_Order $order ): array {
		$revenue = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$line_total = (float) $item->get_total();
			$refunded   = abs( (float) $order->get_total_refunded_for_item( $item_id ) );
			$revenue   += max( 0.0, $line_total - $refunded );
		}

		$cogs = $this->order_snapshots->net_order_cogs( $order );

		$profit = $revenue - $cogs;
		$margin = $revenue > 0 ? ( $profit / $revenue ) * 100 : 0.0;

		return array(
			'revenue' => $revenue,
			'cogs'    => $cogs,
			'profit'  => $profit,
			'margin'  => $margin,
		);
	}
}
