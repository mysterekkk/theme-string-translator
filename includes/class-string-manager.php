<?php
/**
 * String manager.
 *
 * Handles all database operations for translatable strings.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_String_Manager
 *
 * Provides CRUD operations and statistics for the tst_strings table.
 *
 * @since 1.0.0
 */
class TST_String_Manager {

	/**
	 * Full database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * Sets the table name using the WordPress database prefix.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'tst_strings';
	}

	/**
	 * Add or update a string in the database.
	 *
	 * Uses an upsert strategy via $wpdb->replace(). The string_hash is computed
	 * from the combination of original string, source type, and text domain.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     String data.
	 *
	 *     @type string $original_string The original translatable string.
	 *     @type string $source_type     Type of source (gettext, hardcoded, theme_mod, acf, woocommerce).
	 *     @type string $source_file     Relative path to the source file.
	 *     @type int    $source_line     Line number in the source file.
	 *     @type string $text_domain     The text domain.
	 *     @type string $context         Translation context, if any.
	 * }
	 * @return int|false Number of rows affected or false on error.
	 */
	public function add_string( $data ) {
		global $wpdb;

		$original    = isset( $data['original_string'] ) ? $data['original_string'] : '';
		$source_type = isset( $data['source_type'] ) ? sanitize_text_field( $data['source_type'] ) : '';
		$text_domain = isset( $data['text_domain'] ) ? sanitize_text_field( $data['text_domain'] ) : '';
		$string_hash = md5( $original . $source_type . $text_domain );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->replace(
			$this->table_name,
			array(
				'string_hash'     => $string_hash,
				'original_string' => $original,
				'source_type'     => $source_type,
				'source_file'     => isset( $data['source_file'] ) ? sanitize_text_field( $data['source_file'] ) : '',
				'source_line'     => isset( $data['source_line'] ) ? absint( $data['source_line'] ) : 0,
				'text_domain'     => $text_domain,
				'context'         => isset( $data['context'] ) ? sanitize_text_field( $data['context'] ) : '',
				'translations'    => '{}',
				'status'          => 'untranslated',
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Retrieve a paginated list of strings with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $page        Current page number. Default 1.
	 *     @type int    $per_page    Items per page. Default 50.
	 *     @type string $status      Filter by status (translated, partial, untranslated).
	 *     @type string $source_type Filter by source type.
	 *     @type string $search      Search term for original_string LIKE match.
	 * }
	 * @return array {
	 *     @type array $items Array of string objects.
	 *     @type int   $total Total number of matching strings.
	 * }
	 */
	public function get_strings( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'        => 1,
			'per_page'    => 50,
			'status'      => '',
			'source_type' => '',
			'search'      => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['source_type'] ) ) {
			$where[]  = 'source_type = %s';
			$values[] = sanitize_text_field( $args['source_type'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'original_string LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count.
		$offset   = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$per_page = absint( $args['per_page'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode( ' AND ', $where ), $values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		);

		// Get paginated results.
		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE " . implode( ' AND ', $where ) . " ORDER BY id DESC LIMIT %d OFFSET %d", $values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Retrieve a single string by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The string ID.
	 * @return object|null The string row or null if not found.
	 */
	public function get_string( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Update the translation for a specific locale.
	 *
	 * Decodes the existing translations JSON, sets the locale key,
	 * re-encodes, and updates the status based on configured languages.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id          The string ID.
	 * @param string $locale      The locale code (e.g. 'pl_PL').
	 * @param string $translation The translated text.
	 * @return int|false Number of rows updated or false on error.
	 */
	public function update_translation( $id, $locale, $translation ) {
		global $wpdb;

		$string = $this->get_string( $id );
		if ( ! $string ) {
			return false;
		}

		$translations = json_decode( $string->translations, true );
		if ( ! is_array( $translations ) ) {
			$translations = array();
		}

		$locale      = sanitize_text_field( $locale );
		$translation = wp_kses_post( $translation );

		if ( '' === $translation ) {
			unset( $translations[ $locale ] );
		} else {
			$translations[ $locale ] = $translation;
		}

		$translations_json = wp_json_encode( $translations, JSON_UNESCAPED_UNICODE );

		// Compute status based on configured languages.
		$configured_languages = get_option( 'tst_languages', array() );
		$status               = $this->compute_status( $translations, $configured_languages );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
			$this->table_name,
			array(
				'translations' => $translations_json,
				'status'       => $status,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a string by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The string ID.
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function delete_string( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete(
			$this->table_name,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Get translation statistics.
	 *
	 * Returns counts grouped by status and source type.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     @type array $by_status      Associative array of status => count.
	 *     @type array $by_source_type Associative array of source_type => count.
	 *     @type int   $total          Total number of strings.
	 * }
	 */
	public function get_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_status = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			OBJECT_K
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_source_type = $wpdb->get_results(
			"SELECT source_type, COUNT(*) as count FROM {$this->table_name} GROUP BY source_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			OBJECT_K
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		$status_counts = array();
		foreach ( $by_status as $key => $row ) {
			$status_counts[ $key ] = (int) $row->count;
		}

		$source_counts = array();
		foreach ( $by_source_type as $key => $row ) {
			$source_counts[ $key ] = (int) $row->count;
		}

		return array(
			'by_status'      => $status_counts,
			'by_source_type' => $source_counts,
			'total'          => $total,
		);
	}

	/**
	 * Remove all strings from the table.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|int True on success, number of rows affected, or false on error.
	 */
	public function clear_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
	}

	/**
	 * Compute the translation status based on configured languages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $translations        Associative array of locale => translation.
	 * @param array $configured_languages Array of configured locale codes.
	 * @return string One of 'translated', 'partial', or 'untranslated'.
	 */
	private function compute_status( $translations, $configured_languages ) {
		if ( empty( $configured_languages ) || ! is_array( $configured_languages ) ) {
			return ! empty( $translations ) ? 'translated' : 'untranslated';
		}

		$translated_count = 0;
		foreach ( $configured_languages as $lang ) {
			if ( ! empty( $translations[ $lang ] ) ) {
				$translated_count++;
			}
		}

		if ( 0 === $translated_count ) {
			return 'untranslated';
		}

		if ( $translated_count === count( $configured_languages ) ) {
			return 'translated';
		}

		return 'partial';
	}
}
