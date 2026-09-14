<?php

namespace COGS_Studio;

defined( 'ABSPATH' ) || exit;

final class Installer {
	public const DB_VERSION = '1.0.0';

	public static function activate(): void {
		self::create_tables();
		update_option( 'cogs_studio_db_version', self::DB_VERSION, false );
		update_option( 'cogs_studio_version', COGS_STUDIO_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( 'cogs_studio_db_version' ) ) {
			self::create_tables();
			update_option( 'cogs_studio_db_version', self::DB_VERSION, false );
		}

		if ( COGS_STUDIO_VERSION !== get_option( 'cogs_studio_version' ) ) {
			update_option( 'cogs_studio_version', COGS_STUDIO_VERSION, false );
		}
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Cost_History::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			old_cost decimal(20,6) NULL,
			new_cost decimal(20,6) NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(50) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
