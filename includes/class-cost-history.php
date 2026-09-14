<?php

namespace COGS_Studio;

defined( 'ABSPATH' ) || exit;

final class Cost_History {
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cogs_studio_history';
	}

	public function log( int $product_id, ?float $old_cost, ?float $new_cost, string $source = 'manual' ): void {
		global $wpdb;

		$old_normalized = null === $old_cost ? null : round( $old_cost, 6 );
		$new_normalized = null === $new_cost ? null : round( $new_cost, 6 );

		if ( $old_normalized === $new_normalized ) {
			return;
		}

		$wpdb->insert(
			self::table_name(),
			array(
				'product_id' => $product_id,
				'old_cost'   => $old_normalized,
				'new_cost'   => $new_normalized,
				'user_id'    => get_current_user_id(),
				'source'     => sanitize_key( $source ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%f', '%f', '%d', '%s', '%s' )
		);
	}

	public function recent( int $limit = 50 ): array {
		global $wpdb;

		$limit = max( 1, min( 200, $limit ) );
		$sql   = $wpdb->prepare(
			'SELECT id, product_id, old_cost, new_cost, user_id, source, created_at FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d',
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
