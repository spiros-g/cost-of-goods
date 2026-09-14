<?php

namespace COGS_Studio;

defined( 'ABSPATH' ) || exit;

final class Cost_History {
	private static ?string $source_context = null;

	public static function current_source(): ?string {
		return self::$source_context;
	}

	public function with_source( string $source, callable $callback ): mixed {
		$previous             = self::$source_context;
		self::$source_context = sanitize_key( $source );

		try {
			return $callback();
		} finally {
			self::$source_context = $previous;
		}
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cogs_studio_history';
	}

	public function log( int $product_id, ?float $old_cost, ?float $new_cost, string $source = 'manual', bool $force = false ): void {
		global $wpdb;

		$old_normalized = null === $old_cost ? null : round( $old_cost, 6 );
		$new_normalized = null === $new_cost ? null : round( $new_cost, 6 );

		if ( ! $force && $old_normalized === $new_normalized ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Dedicated audit table write.
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

		wp_cache_delete( 'recent_50', 'cogs_studio_history' );
		wp_cache_delete( 'recent_100', 'cogs_studio_history' );
		wp_cache_delete( 'recent_200', 'cogs_studio_history' );
	}

	public function recent( int $limit = 50 ): array {
		global $wpdb;

		$limit     = max( 1, min( 200, $limit ) );
		$cache_key = 'recent_' . $limit;
		$cached    = wp_cache_get( $cache_key, 'cogs_studio_history' );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Dedicated audit table read with object-cache layer.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, product_id, old_cost, new_cost, user_id, source, created_at FROM %i ORDER BY id DESC LIMIT %d',
				self::table_name(),
				$limit
			),
			ARRAY_A
		);

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( $cache_key, $rows, 'cogs_studio_history', 60 );

		return $rows;
	}
}
