<?php
/**
 * Plugin activator.
 *
 * Creates the database table and sets default options on activation.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Activator
 *
 * Handles plugin activation tasks including database table creation
 * and default option initialization.
 *
 * @since 1.0.0
 */
class TST_Activator {

	/**
	 * Run activation routines.
	 *
	 * Creates the tst_strings database table using dbDelta and sets
	 * default plugin options if they do not already exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'tst_strings';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			string_hash varchar(32) NOT NULL,
			original_string text NOT NULL,
			source_type varchar(20) NOT NULL DEFAULT '',
			source_file varchar(255) NOT NULL DEFAULT '',
			source_line int(11) NOT NULL DEFAULT 0,
			text_domain varchar(100) NOT NULL DEFAULT '',
			context varchar(255) NOT NULL DEFAULT '',
			translations longtext,
			status varchar(20) NOT NULL DEFAULT 'untranslated',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY string_hash (string_hash)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tst_db_version', '1.0.0' );

		if ( false === get_option( 'tst_languages' ) ) {
			update_option( 'tst_languages', array() );
		}

		if ( false === get_option( 'tst_settings' ) ) {
			$defaults = array(
				'scan_hardcoded'  => true,
				'scan_theme_mods' => true,
				'scan_acf'        => true,
				'scan_woocommerce' => true,
				'runtime_enabled' => true,
			);
			update_option( 'tst_settings', $defaults );
		}
	}
}
