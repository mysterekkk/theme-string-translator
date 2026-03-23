<?php
/**
 * Uninstall Theme String Translator.
 *
 * Removes all plugin data from the database when the plugin is deleted
 * via the WordPress admin.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tst_strings" );

delete_option( 'tst_db_version' );
delete_option( 'tst_languages' );
delete_option( 'tst_settings' );
delete_transient( 'tst_scan_progress' );
delete_transient( 'tst_github_release' );
