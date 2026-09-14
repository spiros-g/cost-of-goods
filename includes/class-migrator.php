<?php

namespace COGS_Studio;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Migrator {
	public const LEGACY_META_KEY = 'cog_cost';

	private COGS_Service $cogs;

	public function __construct( COGS_Service $cogs ) {
		$this->cogs = $cogs;
	}

	public function candidate_count(): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE pm.meta_key = %s
			AND p.post_type IN ('product', 'product_variation')
			AND p.post_status NOT IN ('trash', 'auto-draft')",
			self::LEGACY_META_KEY
		);

		return (int) $wpdb->get_var( $sql );
	}

	public function migrate_batch( int $page = 1, int $batch_size = 50 ): array {
		$page       = max( 1, $page );
		$batch_size = max( 1, min( 200, $batch_size ) );

		$query = new WP_Query(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => $batch_size,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_key'               => self::LEGACY_META_KEY,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$stats = array(
			'processed' => 0,
			'migrated'  => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'page'      => $page,
			'total_pages' => (int) $query->max_num_pages,
		);

		foreach ( $query->posts as $product_id ) {
			++$stats['processed'];
			$legacy_raw = get_post_meta( $product_id, self::LEGACY_META_KEY, true );

			if ( '' === $legacy_raw || ! is_numeric( $legacy_raw ) ) {
				++$stats['skipped'];
				continue;
			}

			try {
				$product = $this->cogs->get_product( (int) $product_id );
				$current = $this->cogs->nominal_cost( $product );

				if ( null !== $current && $current > 0 ) {
					++$stats['skipped'];
					continue;
				}

				$legacy_cost = (float) wc_format_decimal( (string) $legacy_raw, 6 );
				$this->cogs->set_cost( (int) $product_id, $legacy_cost, 'legacy-migration' );
				++$stats['migrated'];
			} catch ( \Throwable $e ) {
				++$stats['errors'];
			}
		}

		$stats['has_more'] = $page < $stats['total_pages'];

		if ( ! $stats['has_more'] ) {
			update_option( 'cogs_studio_legacy_migration_completed_at', current_time( 'mysql', true ), false );
		}

		return $stats;
	}
}
