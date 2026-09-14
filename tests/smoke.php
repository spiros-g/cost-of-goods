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
cogs_studio_smoke_assert( 'cost-of-goods/cost-of-goods.php' === plugin_basename( COGS_STUDIO_FILE ), 'Plugin basename must remain upgrade-compatible with the legacy plugin.' );

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

$legacy_simple = new WC_Product_Simple();
$legacy_simple->set_name( 'COGS Studio Legacy Simple' );
$legacy_simple->set_status( 'publish' );
$legacy_simple->set_regular_price( '25' );
$legacy_simple_id = $legacy_simple->save();
update_post_meta( $legacy_simple_id, COGS_Studio\Migrator::LEGACY_META_KEY, '7.25' );

$zero_variation = new WC_Product_Variation();
$zero_variation->set_parent_id( $parent_id );
$zero_variation->set_status( 'publish' );
$zero_variation->set_regular_price( '50' );
$zero_variation->set_cogs_value( 0.0 );
$zero_variation->set_cogs_value_is_additive( false );
$zero_variation_id = $zero_variation->save();
update_post_meta( $zero_variation_id, COGS_Studio\Migrator::LEGACY_META_KEY, '9.00' );

$migrator = new COGS_Studio\Migrator( $service );
$migrator->migrate_batch( 1, 50 );

$legacy_simple = wc_get_product( $legacy_simple_id );
cogs_studio_smoke_assert( abs( $legacy_simple->get_cogs_total_value() - 7.25 ) < 0.0001, 'Legacy simple cost was not migrated.' );

$zero_variation = wc_get_product( $zero_variation_id );
cogs_studio_smoke_assert( 0.0 === (float) $zero_variation->get_cogs_value(), 'Explicit zero variation cost was overwritten.' );
cogs_studio_smoke_assert( abs( $zero_variation->get_cogs_total_value() - 0.0 ) < 0.0001, 'Explicit zero variation should continue overriding the parent.' );

echo "COGS Studio runtime smoke test passed.\n";
