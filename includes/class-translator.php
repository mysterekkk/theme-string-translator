<?php
/**
 * Translator.
 *
 * Handles saving and retrieving translations for specific locales.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Translator
 *
 * Provides translation management operations including saving translations,
 * retrieving locale-specific translations, and computing status.
 *
 * @since 1.0.0
 */
class TST_Translator {

	/**
	 * String manager instance.
	 *
	 * @since 1.0.0
	 * @var TST_String_Manager
	 */
	private $string_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param TST_String_Manager $string_manager The string manager instance.
	 */
	public function __construct( TST_String_Manager $string_manager ) {
		$this->string_manager = $string_manager;
	}

	/**
	 * Save a translation for a specific string and locale.
	 *
	 * Validates that the locale is in the list of configured languages
	 * before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $string_id   The string ID.
	 * @param string $locale      The locale code (e.g. 'pl_PL').
	 * @param string $translation The translated text.
	 * @return int|false|WP_Error Number of rows updated, false on DB error,
	 *                            or WP_Error if locale is not configured.
	 */
	public function save_translation( $string_id, $locale, $translation ) {
		$configured_languages = get_option( 'tst_languages', array() );

		if ( ! empty( $configured_languages ) && ! in_array( $locale, $configured_languages, true ) ) {
			return new \WP_Error(
				'tst_invalid_locale',
				sprintf(
					/* translators: %s: locale code */
					__( 'Locale "%s" is not in the configured languages list.', 'theme-string-translator' ),
					$locale
				),
				array( 'status' => 400 )
			);
		}

		return $this->string_manager->update_translation( $string_id, $locale, $translation );
	}

	/**
	 * Retrieve all translations for a specific locale.
	 *
	 * Queries the database for all strings that have a translation
	 * for the given locale and returns them as an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale The locale code.
	 * @return array Associative array of original_string => translation.
	 */
	public function get_translations_for_locale( $locale ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tst_strings';
		$locale     = sanitize_text_field( $locale );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT original_string, translations FROM {$table_name} WHERE status IN ('translated', 'partial') AND translations LIKE %s", '%' . $wpdb->esc_like( '"' . $locale . '"' ) . '%' )
		);

		$translations = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->translations, true );
			if ( is_array( $decoded ) && ! empty( $decoded[ $locale ] ) ) {
				$translations[ $row->original_string ] = $decoded[ $locale ];
			}
		}

		return $translations;
	}

	/**
	 * Compute the translation status based on configured languages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $translations_json   JSON-encoded translations array.
	 * @param array  $configured_languages Array of configured locale codes.
	 * @return string One of 'translated', 'partial', or 'untranslated'.
	 */
	public function compute_status( $translations_json, $configured_languages ) {
		$translations = json_decode( $translations_json, true );
		if ( ! is_array( $translations ) ) {
			return 'untranslated';
		}

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
