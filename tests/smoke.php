<?php
/**
 * Runtime smoke test executed by WP-CLI in CI.
 */

defined( 'ABSPATH' ) || exit;

function cogs_studio_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "COGS Studio smoke test failed: {$message}\n" );
		exit( 1 );
	}
}

cogs_studio_smoke_assert( class_exists( 'WooCommerce' ), 'WooCommerce is not loaded.' );
cogs_studio_smoke_assert( class_exists( 'COGS_Studio\\Plugin' ), 'COGS Studio is not loaded.' );
cogs_studio_smoke_assert( 'cogs-studio-for-woocommerce/cogs-studio-for-woocommerce.php' === plugin_basename( COGS_STUDIO_FILE ), 'Plugin basename must match the COGS Studio identity.' );

$compatibility = new COGS_Studio\Compatibility();
cogs_studio_smoke_assert( $compatibility->supported_woocommerce(), 'WooCommerce version is not supported.' );
cogs_studio_smoke_assert( $compatibility->cogs_enabled(), 'Native WooCommerce COGS is not enabled.' );

$history = new COGS_Studio\Cost_History();
$service = new COGS_Studio\COGS_Service( $compatibility, $history );

$simple = new WC_Product_Simple();
$simple->set_name( 'COGS Studio Smoke Simple' );
$simple->set_status( 'publish' );
$simple->set_regular_price( '100' );
$simple_id = $simple->save();

$service->set_cost( $simple_id, 40.0, 'smoke' );
$simple = wc_get_product( $simple_id );
cogs_studio_smoke_assert( abs( $simple->get_cogs_total_value() - 40.0 ) < 0.0001, 'Simple product COGS should equal 40.' );

$simple_history = array_values(
	array_filter(
		$history->recent( 200 ),
		static fn ( array $entry ): bool => (int) $entry['product_id'] === $simple_id
	)
);
cogs_studio_smoke_assert( 1 === count( $simple_history ), 'COGS Studio save should create exactly one audit entry.' );
cogs_studio_smoke_assert( 'smoke' === $simple_history[0]['source'], 'COGS Studio audit source should be preserved.' );
cogs_studio_smoke_assert( null === $simple_history[0]['old_cost'], 'Initial native COGS should be null in history.' );
cogs_studio_smoke_assert( abs( (float) $simple_history[0]['new_cost'] - 40.0 ) < 0.0001, 'COGS Studio audit new cost should equal 40.' );

$external = new WC_Product_Simple();
$external->set_name( 'COGS Studio External Audit' );
$external->set_status( 'publish' );
$external->set_regular_price( '50' );
$external_id = $external->save();
$external->set_cogs_value( 12.0 );
$external->save();

$external_history = array_values(
	array_filter(
		$history->recent( 200 ),
		static fn ( array $entry ): bool => (int) $entry['product_id'] === $external_id
	)
);
cogs_studio_smoke_assert( 1 === count( $external_history ), 'External WooCommerce save should create one audit entry.' );
cogs_studio_smoke_assert( 'wp-cli' === $external_history[0]['source'], 'WP-CLI COGS save should be identified as wp-cli.' );
cogs_studio_smoke_assert( abs( (float) $external_history[0]['new_cost'] - 12.0 ) < 0.0001, 'External audit new cost should equal 12.' );

$parent = new WC_Product_Variable();
$parent->set_name( 'COGS Studio Smoke Variable' );
$parent->set_status( 'publish' );
$parent_id = $parent->save();
$service->set_cost( $parent_id, 10.0, 'smoke' );

$variation = new WC_Product_Variation();
$variation->set_parent_id( $parent_id );
$variation->set_status( 'publish' );
$variation->set_regular_price( '100' );
$variation_id = $variation->save();

$service->set_cost( $variation_id, null, 'smoke', 'inherit' );
$variation = wc_get_product( $variation_id );
cogs_studio_smoke_assert( abs( $variation->get_cogs_total_value() - 10.0 ) < 0.0001, 'Inherited variation COGS should equal parent cost.' );

$service->set_cost( $variation_id, 5.0, 'smoke', 'override' );
$variation = wc_get_product( $variation_id );
cogs_studio_smoke_assert( abs( $variation->get_cogs_total_value() - 5.0 ) < 0.0001, 'Override variation COGS should equal 5.' );

$service->set_cost( $variation_id, 5.0, 'smoke', 'additive' );
$variation = wc_get_product( $variation_id );
cogs_studio_smoke_assert( abs( $variation->get_cogs_total_value() - 15.0 ) < 0.0001, 'Additive variation COGS should equal parent plus variation.' );

$zero_variation = new WC_Product_Variation();
$zero_variation->set_parent_id( $parent_id );
$zero_variation->set_status( 'publish' );
$zero_variation->set_regular_price( '50' );
$zero_variation_id = $zero_variation->save();

$service->set_cost( $zero_variation_id, 0.0, 'smoke', 'override' );
$zero_variation = wc_get_product( $zero_variation_id );
cogs_studio_smoke_assert( 0.0 === (float) $zero_variation->get_cogs_value(), 'Explicit zero variation cost must remain defined.' );
cogs_studio_smoke_assert( 0.0 === (float) $zero_variation->get_cogs_total_value(), 'Explicit zero variation must override the parent cost.' );

// New products created with COGS in the first save must also be audited.
$initial_cogs = new WC_Product_Simple();
$initial_cogs->set_name( 'COGS Studio Initial COGS Audit' );
$initial_cogs->set_status( 'publish' );
$initial_cogs->set_regular_price( '30' );
$initial_cogs->set_cogs_value( 8.0 );
$initial_cogs_id = $initial_cogs->save();

$initial_history = array_values(
	array_filter(
		$history->recent( 200 ),
		static fn ( array $entry ): bool => (int) $entry['product_id'] === $initial_cogs_id
	)
);
cogs_studio_smoke_assert( 1 === count( $initial_history ), 'Initial product COGS should create one audit entry.' );
cogs_studio_smoke_assert( null === $initial_history[0]['old_cost'], 'Initial product COGS history should start from null.' );
cogs_studio_smoke_assert( abs( (float) $initial_history[0]['new_cost'] - 8.0 ) < 0.0001, 'Initial product COGS audit should equal 8.' );

// Unrelated product saves must not create COGS history entries.
$external = wc_get_product( $external_id );
$external->set_name( 'COGS Studio External Audit Renamed' );
$external->save();
$external_history_after_name_change = array_values(
	array_filter(
		$history->recent( 200 ),
		static fn ( array $entry ): bool => (int) $entry['product_id'] === $external_id
	)
);
cogs_studio_smoke_assert( 1 === count( $external_history_after_name_change ), 'Unrelated product saves must not create COGS audit entries.' );

// Order COGS must be snapshotted and remain independent from later product cost changes.
$order_product = new WC_Product_Simple();
$order_product->set_name( 'COGS Studio Order Snapshot Product' );
$order_product->set_status( 'publish' );
$order_product->set_regular_price( '100' );
$order_product_id = $order_product->save();
$service->set_cost( $order_product_id, 40.0, 'smoke' );
$order_product = wc_get_product( $order_product_id );

$order = wc_create_order();
$item_id = $order->add_product(
	$order_product,
	2,
	array(
		'subtotal' => 200,
		'total'    => 200,
	)
);
$order->calculate_totals( false );
$order->save();

// Persisted items are required for consistent COGS calculation across WooCommerce 10.3+.
$order = wc_get_order( $order->get_id() );
$order->calculate_cogs_total_value();
$order->set_status( 'processing' );
$order->save();

$order      = wc_get_order( $order->get_id() );
$order_items = $order->get_items( 'line_item' );
$test_item   = reset( $order_items );
$test_product = $test_item ? $test_item->get_product() : false;
$order_cogs  = (float) $order->get_cogs_total_value();
$item_cogs   = $test_item && method_exists( $test_item, 'get_cogs_value' ) ? (float) $test_item->get_cogs_value() : -1.0;
$product_cogs = $test_product && method_exists( $test_product, 'get_cogs_total_value' ) ? (float) $test_product->get_cogs_total_value() : -1.0;

cogs_studio_smoke_assert(
	abs( $order_cogs - 80.0 ) < 0.0001,
	sprintf(
		'Order COGS snapshot should equal 80. Actual order=%s item=%s product=%s.',
		$order_cogs,
		$item_cogs,
		$product_cogs
	)
);

$order_snapshots   = new COGS_Studio\Order_COGS_Snapshot();
$profit_calculator = new COGS_Studio\Profit_Calculator( $service, $order_snapshots );
$order_metrics     = $profit_calculator->order_metrics( $order );
cogs_studio_smoke_assert( abs( $order_metrics['revenue'] - 200.0 ) < 0.0001, 'Order product revenue should equal 200.' );
cogs_studio_smoke_assert( abs( $order_metrics['profit'] - 120.0 ) < 0.0001, 'Order gross profit should equal 120.' );
cogs_studio_smoke_assert( abs( $order_metrics['margin'] - 60.0 ) < 0.0001, 'Order gross margin should equal 60%.' );

$service->set_cost( $order_product_id, 60.0, 'smoke' );
$order = wc_get_order( $order->get_id() );
cogs_studio_smoke_assert( abs( $order->get_cogs_total_value() - 80.0 ) < 0.0001, 'Historical native order COGS must not change before a refund recalculation.' );

$order_item = $order->get_item( $item_id );
cogs_studio_smoke_assert(
	abs( (float) $order_item->get_meta( COGS_Studio\Order_COGS_Snapshot::ITEM_META_KEY, true ) - 80.0 ) < 0.0001,
	'COGS Studio must preserve the original order-item COGS snapshot.'
);

$refund = wc_create_refund(
	array(
		'amount'         => 100,
		'order_id'       => $order->get_id(),
		'line_items'     => array(
			$item_id => array(
				'qty'          => 1,
				'refund_total' => 100,
				'refund_tax'   => array(),
			),
		),
		'refund_payment' => false,
		'restock_items'  => false,
	)
);
cogs_studio_smoke_assert( ! is_wp_error( $refund ), 'WooCommerce refund creation failed.' );

$order = wc_get_order( $order->get_id() );
$order_metrics = $profit_calculator->order_metrics( $order );
$order_item    = $order->get_item( $item_id );

cogs_studio_smoke_assert(
	abs( (float) $order_item->get_meta( COGS_Studio\Order_COGS_Snapshot::ITEM_META_KEY, true ) - 80.0 ) < 0.0001,
	'Original order-item COGS snapshot must remain immutable after refund recalculation.'
);
cogs_studio_smoke_assert( abs( $order_metrics['revenue'] - 100.0 ) < 0.0001, 'Refunded product revenue should be reduced to 100.' );
cogs_studio_smoke_assert( abs( $order_metrics['cogs'] - 40.0 ) < 0.0001, 'COGS Studio net historical COGS should be reduced to 40 after refunding one of two units.' );
cogs_studio_smoke_assert( abs( $order_metrics['profit'] - 60.0 ) < 0.0001, 'Refunded order gross profit should equal 60.' );
cogs_studio_smoke_assert( abs( $order_metrics['margin'] - 60.0 ) < 0.0001, 'Refunded order gross margin should remain 60%.' );

// Dashboard cache must invalidate on relevant product/order changes.
set_transient( COGS_Studio\Dashboard_Cache::TRANSIENT_KEY, array( 'stale' => true ), HOUR_IN_SECONDS );
$order_product = wc_get_product( $order_product_id );
$order_product->set_regular_price( '105' );
$order_product->save();
cogs_studio_smoke_assert( false === get_transient( COGS_Studio\Dashboard_Cache::TRANSIENT_KEY ), 'Product profitability changes must invalidate the dashboard cache.' );

echo "COGS Studio runtime smoke test passed.\n";
