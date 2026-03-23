<?php
/**
 * Runtime translation filter.
 *
 * Applies translations at runtime via WordPress gettext filters.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Runtime
 *
 * Hooks into WordPress gettext filters to override theme translations
 * with those stored in the plugin's database. Uses lazy-loaded caching
 * to minimize database queries.
 *
 * @since 1.0.0
 */
class TST_Runtime {

	/**
	 * Cached translations keyed by md5 hash.
	 *
	 * Lazy-loaded on first filter call. Null indicates cache has not been loaded.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private static $translations = null;

	/**
	 * Register gettext filters.
	 *
	 * Hooks into WordPress translation filters to override strings
	 * at runtime. Only registers if runtime translation is enabled
	 * in settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_filters() {
		$settings = get_option( 'tst_settings', array() );
		if ( empty( $settings['runtime_enabled'] ) ) {
			return;
		}

		add_filter( 'gettext', array( $this, 'filter_gettext' ), 10, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 10, 4 );
	}

	/**
	 * Filter gettext translations.
	 *
	 * Intercepts the standard gettext filter and returns a database override
	 * if one exists for the given text and domain combination.
	 *
	 * @since 1.0.0
	 *
	 * @param string $translation The current translation.
	 * @param string $text        The original text.
	 * @param string $domain      The text domain.
	 * @return string The overridden translation or the original.
	 */
	public function filter_gettext( $translation, $text, $domain ) {
		$this->maybe_load_translations();

		$hash = md5( $text . $domain );
		if ( isset( self::$translations[ $hash ] ) ) {
			return self::$translations[ $hash ];
		}

		return $translation;
	}

	/**
	 * Filter gettext translations with context.
	 *
	 * Intercepts the contextual gettext filter and returns a database override
	 * if one exists for the given text and domain combination.
	 *
	 * @since 1.0.0
	 *
	 * @param string $translation The current translation.
	 * @param string $text        The original text.
	 * @param string $context     The translation context.
	 * @param string $domain      The text domain.
	 * @return string The overridden translation or the original.
	 */
	public function filter_gettext_with_context( $translation, $text, $context, $domain ) {
		$this->maybe_load_translations();

		$hash = md5( $text . $domain );
		if ( isset( self::$translations[ $hash ] ) ) {
			return self::$translations[ $hash ];
		}

		return $translation;
	}

	/**
	 * Load translations from the database if not already cached.
	 *
	 * Queries all translated strings for the current locale and builds
	 * a hash-keyed lookup array for fast runtime access.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function maybe_load_translations() {
		if ( null !== self::$translations ) {
			return;
		}

		self::$translations = array();

		global $wpdb;

		$locale     = get_locale();
		$table_name = $wpdb->prefix . 'tst_strings';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT original_string, text_domain, translations FROM {$table_name} WHERE status IN ('translated', 'partial') AND translations LIKE %s",
				'%' . $wpdb->esc_like( '"' . $locale . '"' ) . '%'
			)
		);

		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->translations, true );
			if ( is_array( $decoded ) && ! empty( $decoded[ $locale ] ) ) {
				$hash = md5( $row->original_string . $row->text_domain );
				self::$translations[ $hash ] = $decoded[ $locale ];
			}
		}
	}
}
