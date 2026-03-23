<?php
/**
 * Theme scanner.
 *
 * Scans the active theme for translatable strings using regex patterns.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Scanner
 *
 * Recursively scans theme PHP files for translatable strings,
 * including gettext functions, hardcoded text, theme mods,
 * ACF fields, and WooCommerce template overrides.
 *
 * @since 1.0.0
 */
class TST_Scanner {

	/**
	 * Regex pattern for __() calls.
	 *
	 * @var string
	 */
	const PATTERN_GETTEXT = '/__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for _e() calls.
	 *
	 * @var string
	 */
	const PATTERN_GETTEXT_E = '/_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for esc_html__() calls.
	 *
	 * @var string
	 */
	const PATTERN_ESC_HTML = '/esc_html__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for esc_attr__() calls.
	 *
	 * @var string
	 */
	const PATTERN_ESC_ATTR = '/esc_attr__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for esc_html_e() calls.
	 *
	 * @var string
	 */
	const PATTERN_ESC_HTML_E = '/esc_html_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for esc_attr_e() calls.
	 *
	 * @var string
	 */
	const PATTERN_ESC_ATTR_E = '/esc_attr_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for _x() calls with context.
	 *
	 * @var string
	 */
	const PATTERN_X = '/_x\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

	/**
	 * Regex pattern for _n() plural calls.
	 *
	 * @var string
	 */
	const PATTERN_N = '/_n\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,/';

	/**
	 * Regex pattern for get_theme_mod() default values.
	 *
	 * @var string
	 */
	const PATTERN_THEME_MOD = '/get_theme_mod\(\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)/';

	/**
	 * Regex pattern for hardcoded text between HTML tags.
	 *
	 * @var string
	 */
	const PATTERN_HARDCODED = '/>([^<>{}$\n]{3,100})</';

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
	 * Scan the active theme for all translatable strings.
	 *
	 * Recursively finds all PHP files in the active theme directory,
	 * scans each for translatable strings, and optionally scans ACF fields
	 * and WooCommerce templates.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total number of strings found.
	 */
	public function scan_theme() {
		$theme_dir = get_stylesheet_directory();
		$files     = $this->get_php_files( $theme_dir );
		$count     = 0;
		$total     = count( $files );
		$processed = 0;

		foreach ( $files as $file ) {
			$relative = str_replace( $theme_dir . '/', '', $file );
			$strings  = $this->scan_file( $file, $relative );

			foreach ( $strings as $string_data ) {
				$this->string_manager->add_string( $string_data );
				$count++;
			}

			$processed++;
			set_transient( 'tst_scan_progress', array(
				'current' => $processed,
				'total'   => $total,
				'file'    => $relative,
				'found'   => $count,
			), 300 );
		}

		// Scan ACF fields if available.
		$settings = get_option( 'tst_settings', array() );

		if ( ! empty( $settings['scan_acf'] ) && class_exists( 'ACF' ) ) {
			$count += $this->scan_acf_fields();
		}

		// Scan WooCommerce templates if available.
		if ( ! empty( $settings['scan_woocommerce'] ) && class_exists( 'WooCommerce' ) ) {
			$count += $this->scan_woocommerce_templates( $theme_dir );
		}

		// Mark scan as complete.
		set_transient( 'tst_scan_progress', array(
			'current'  => $total,
			'total'    => $total,
			'file'     => '',
			'found'    => $count,
			'complete' => true,
		), 300 );

		return $count;
	}

	/**
	 * Scan a single PHP file for translatable strings.
	 *
	 * Applies all regex patterns against the file content and collects
	 * matching strings with their metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filepath     Absolute path to the PHP file.
	 * @param string $relative_path Relative path from the theme root.
	 * @return array Array of string data arrays.
	 */
	public function scan_file( $filepath, $relative_path = '' ) {
		$content = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return array();
		}

		$found = array();
		$lines = explode( "\n", $content );

		// Standard gettext patterns: function(string, domain).
		$standard_patterns = array(
			self::PATTERN_GETTEXT     => 'gettext',
			self::PATTERN_GETTEXT_E   => 'gettext',
			self::PATTERN_ESC_HTML    => 'gettext',
			self::PATTERN_ESC_ATTR    => 'gettext',
			self::PATTERN_ESC_HTML_E  => 'gettext',
			self::PATTERN_ESC_ATTR_E  => 'gettext',
		);

		foreach ( $standard_patterns as $pattern => $source_type ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[1] as $index => $match ) {
					$line_number = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
					$found[]     = array(
						'original_string' => $match[0],
						'source_type'     => $source_type,
						'source_file'     => $relative_path,
						'source_line'     => $line_number,
						'text_domain'     => $matches[2][ $index ][0],
						'context'         => '',
					);
				}
			}
		}

		// _x() pattern: function(string, context, domain).
		if ( preg_match_all( self::PATTERN_X, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $index => $match ) {
				$line_number = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
				$found[]     = array(
					'original_string' => $match[0],
					'source_type'     => 'gettext',
					'source_file'     => $relative_path,
					'source_line'     => $line_number,
					'text_domain'     => $matches[3][ $index ][0],
					'context'         => $matches[2][ $index ][0],
				);
			}
		}

		// _n() pattern: function(singular, plural, ...).
		if ( preg_match_all( self::PATTERN_N, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $index => $match ) {
				$line_number = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;

				// Add singular form.
				$found[] = array(
					'original_string' => $match[0],
					'source_type'     => 'gettext',
					'source_file'     => $relative_path,
					'source_line'     => $line_number,
					'text_domain'     => '',
					'context'         => '',
				);

				// Add plural form.
				$found[] = array(
					'original_string' => $matches[2][ $index ][0],
					'source_type'     => 'gettext',
					'source_file'     => $relative_path,
					'source_line'     => $line_number,
					'text_domain'     => '',
					'context'         => '',
				);
			}
		}

		// get_theme_mod() pattern.
		$settings = get_option( 'tst_settings', array() );
		if ( ! empty( $settings['scan_theme_mods'] ) ) {
			if ( preg_match_all( self::PATTERN_THEME_MOD, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[2] as $index => $match ) {
					$line_number = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
					$found[]     = array(
						'original_string' => $match[0],
						'source_type'     => 'theme_mod',
						'source_file'     => $relative_path,
						'source_line'     => $line_number,
						'text_domain'     => '',
						'context'         => $matches[1][ $index ][0],
					);
				}
			}
		}

		// Hardcoded strings between HTML tags.
		if ( ! empty( $settings['scan_hardcoded'] ) ) {
			if ( preg_match_all( self::PATTERN_HARDCODED, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[1] as $match ) {
					$text = trim( $match[0] );

					// Skip strings that are purely numeric, whitespace, or contain PHP variables.
					if ( $this->should_skip_hardcoded( $text ) ) {
						continue;
					}

					$line_number = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
					$found[]     = array(
						'original_string' => $text,
						'source_type'     => 'hardcoded',
						'source_file'     => $relative_path,
						'source_line'     => $line_number,
						'text_domain'     => '',
						'context'         => '',
					);
				}
			}
		}

		return $found;
	}

	/**
	 * Scan ACF field groups for translatable labels and instructions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of strings found.
	 */
	private function scan_acf_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return 0;
		}

		$count        = 0;
		$field_groups = acf_get_field_groups();

		foreach ( $field_groups as $group ) {
			// Add group title.
			if ( ! empty( $group['title'] ) ) {
				$this->string_manager->add_string( array(
					'original_string' => $group['title'],
					'source_type'     => 'acf',
					'source_file'     => 'acf-field-group',
					'source_line'     => 0,
					'text_domain'     => '',
					'context'         => 'ACF Field Group Title',
				) );
				$count++;
			}

			$fields = acf_get_fields( $group );
			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$count += $this->scan_acf_field( $field );
			}
		}

		return $count;
	}

	/**
	 * Recursively scan a single ACF field for translatable strings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field The ACF field array.
	 * @return int Number of strings found.
	 */
	private function scan_acf_field( $field ) {
		$count = 0;

		$translatable_keys = array( 'label', 'instructions', 'placeholder', 'prepend', 'append', 'message' );

		foreach ( $translatable_keys as $key ) {
			if ( ! empty( $field[ $key ] ) && is_string( $field[ $key ] ) ) {
				$this->string_manager->add_string( array(
					'original_string' => $field[ $key ],
					'source_type'     => 'acf',
					'source_file'     => 'acf-field',
					'source_line'     => 0,
					'text_domain'     => '',
					'context'         => 'ACF Field: ' . ( $field['name'] ?? '' ),
				) );
				$count++;
			}
		}

		// Scan choices for select, checkbox, radio.
		if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
			foreach ( $field['choices'] as $choice ) {
				if ( is_string( $choice ) && '' !== $choice ) {
					$this->string_manager->add_string( array(
						'original_string' => $choice,
						'source_type'     => 'acf',
						'source_file'     => 'acf-field',
						'source_line'     => 0,
						'text_domain'     => '',
						'context'         => 'ACF Choice: ' . ( $field['name'] ?? '' ),
					) );
					$count++;
				}
			}
		}

		// Recurse into sub_fields (repeater, group, flexible content).
		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$count += $this->scan_acf_field( $sub_field );
			}
		}

		// Recurse into layouts (flexible content).
		if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( ! empty( $layout['label'] ) ) {
					$this->string_manager->add_string( array(
						'original_string' => $layout['label'],
						'source_type'     => 'acf',
						'source_file'     => 'acf-field',
						'source_line'     => 0,
						'text_domain'     => '',
						'context'         => 'ACF Layout Label',
					) );
					$count++;
				}
				if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					foreach ( $layout['sub_fields'] as $sub_field ) {
						$count += $this->scan_acf_field( $sub_field );
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Scan WooCommerce template overrides in the active theme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme_dir The active theme directory path.
	 * @return int Number of strings found.
	 */
	private function scan_woocommerce_templates( $theme_dir ) {
		$woo_dir = $theme_dir . '/woocommerce';
		if ( ! is_dir( $woo_dir ) ) {
			return 0;
		}

		$files = $this->get_php_files( $woo_dir );
		$count = 0;

		foreach ( $files as $file ) {
			$relative = str_replace( $theme_dir . '/', '', $file );
			$strings  = $this->scan_file( $file, $relative );

			foreach ( $strings as $string_data ) {
				$string_data['source_type'] = 'woocommerce';
				$this->string_manager->add_string( $string_data );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Recursively find all PHP files in a directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $directory The directory to scan.
	 * @return array Array of absolute file paths.
	 */
	private function get_php_files( $directory ) {
		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				// Skip vendor and node_modules directories.
				$path = $file->getPathname();
				if ( preg_match( '#/(vendor|node_modules)/#', $path ) ) {
					continue;
				}
				$files[] = $path;
			}
		}

		return $files;
	}

	/**
	 * Determine whether a hardcoded string should be skipped.
	 *
	 * Filters out strings that are purely numeric, only whitespace,
	 * contain PHP variables, or are common non-translatable patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to check.
	 * @return bool True if the string should be skipped.
	 */
	private function should_skip_hardcoded( $text ) {
		// Skip empty or whitespace-only strings.
		if ( '' === $text || ctype_space( $text ) ) {
			return true;
		}

		// Skip purely numeric strings.
		if ( is_numeric( $text ) ) {
			return true;
		}

		// Skip strings containing PHP variables.
		if ( false !== strpos( $text, '$' ) ) {
			return true;
		}

		// Skip strings that look like code or CSS classes.
		if ( preg_match( '/^[a-z0-9_-]+$/i', $text ) ) {
			return true;
		}

		// Skip strings that are just punctuation or special characters.
		if ( preg_match( '/^[\s\p{P}\p{S}]+$/u', $text ) ) {
			return true;
		}

		return false;
	}
}
