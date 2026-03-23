<?php
/**
 * Importer.
 *
 * Imports translations from PO and JSON files into the database.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Importer
 *
 * Parses PO and JSON translation files and saves the translations
 * to the database for matching strings.
 *
 * @since 1.0.0
 */
class TST_Importer {

	/**
	 * String manager instance.
	 *
	 * @since 1.0.0
	 * @var TST_String_Manager
	 */
	private $string_manager;

	/**
	 * Translator instance.
	 *
	 * @since 1.0.0
	 * @var TST_Translator
	 */
	private $translator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param TST_String_Manager $string_manager The string manager instance.
	 * @param TST_Translator     $translator     The translator instance.
	 */
	public function __construct( TST_String_Manager $string_manager, TST_Translator $translator ) {
		$this->string_manager = $string_manager;
		$this->translator     = $translator;
	}

	/**
	 * Import translations from PO file content.
	 *
	 * Parses PO format extracting msgid, msgstr, and msgctxt entries.
	 * Handles multiline concatenated strings. For each found pair,
	 * looks up the string in the database and saves the translation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_content The PO file content.
	 * @param string $locale       The locale code to save translations under.
	 * @return array {
	 *     Import summary.
	 *
	 *     @type int $total    Total entries parsed.
	 *     @type int $imported Number of translations saved.
	 *     @type int $skipped  Number of entries with no matching string in DB.
	 * }
	 */
	public function import_po( $file_content, $locale ) {
		$entries  = $this->parse_po( $file_content );
		$imported = 0;
		$skipped  = 0;

		foreach ( $entries as $entry ) {
			if ( empty( $entry['msgid'] ) || empty( $entry['msgstr'] ) ) {
				$skipped++;
				continue;
			}

			$string = $this->find_string_by_original( $entry['msgid'] );
			if ( $string ) {
				$this->translator->save_translation( $string->id, $locale, $entry['msgstr'] );
				$imported++;
			} else {
				$skipped++;
			}
		}

		return array(
			'total'    => count( $entries ),
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Import translations from JSON file content.
	 *
	 * Parses JSON and iterates key-value pairs. Supports both
	 * flat format and Jed locale_data format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_content The JSON file content.
	 * @param string $locale       The locale code to save translations under.
	 * @return array {
	 *     Import summary.
	 *
	 *     @type int $total    Total entries parsed.
	 *     @type int $imported Number of translations saved.
	 *     @type int $skipped  Number of entries with no matching string in DB.
	 * }
	 */
	public function import_json( $file_content, $locale ) {
		$data = json_decode( $file_content, true );
		if ( ! is_array( $data ) ) {
			return array(
				'total'    => 0,
				'imported' => 0,
				'skipped'  => 0,
			);
		}

		// Support Jed format.
		if ( isset( $data['locale_data']['messages'] ) ) {
			$data = $data['locale_data']['messages'];
		}

		$imported = 0;
		$skipped  = 0;
		$total    = 0;

		foreach ( $data as $original => $translation ) {
			// Skip the metadata entry.
			if ( '' === $original ) {
				continue;
			}

			$total++;

			// Handle array values (Jed format uses arrays).
			if ( is_array( $translation ) ) {
				$translation = isset( $translation[0] ) ? $translation[0] : '';
			}

			if ( ! is_string( $translation ) || '' === $translation ) {
				$skipped++;
				continue;
			}

			$string = $this->find_string_by_original( $original );
			if ( $string ) {
				$this->translator->save_translation( $string->id, $locale, $translation );
				$imported++;
			} else {
				$skipped++;
			}
		}

		return array(
			'total'    => $total,
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Parse PO file content into an array of entries.
	 *
	 * Handles multiline concatenated strings by joining consecutive
	 * quoted lines.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The PO file content.
	 * @return array Array of entries, each with 'msgid', 'msgstr', and optional 'msgctxt'.
	 */
	private function parse_po( $content ) {
		$entries      = array();
		$current      = array();
		$current_key  = '';
		$lines        = explode( "\n", $content );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip comments and empty lines.
			if ( '' === $line || '#' === $line[0] ) {
				// If we have a complete entry, save it.
				if ( ! empty( $current['msgid'] ) ) {
					$entries[] = $current;
					$current   = array();
				}
				continue;
			}

			// Detect new keyword.
			if ( preg_match( '/^(msgid|msgstr|msgctxt)\s+"(.*)"$/', $line, $matches ) ) {
				// Save previous entry if starting a new msgid and we have data.
				if ( 'msgid' === $matches[1] && ! empty( $current['msgid'] ) ) {
					$entries[] = $current;
					$current   = array();
				}

				$current_key              = $matches[1];
				$current[ $current_key ]  = $this->po_decode_string( $matches[2] );
				continue;
			}

			// Continuation line (quoted string).
			if ( preg_match( '/^"(.*)"$/', $line, $matches ) && '' !== $current_key ) {
				$current[ $current_key ] .= $this->po_decode_string( $matches[1] );
			}
		}

		// Don't forget the last entry.
		if ( ! empty( $current['msgid'] ) ) {
			$entries[] = $current;
		}

		return $entries;
	}

	/**
	 * Decode a PO-encoded string.
	 *
	 * Reverses the escaping applied during PO encoding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string The PO-encoded string.
	 * @return string The decoded string.
	 */
	private function po_decode_string( $string ) {
		$string = str_replace( '\\n', "\n", $string );
		$string = str_replace( '\\t', "\t", $string );
		$string = str_replace( '\\"', '"', $string );
		$string = str_replace( '\\\\', '\\', $string );
		return $string;
	}

	/**
	 * Find a string in the database by its original text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $original The original string text.
	 * @return object|null The string row or null if not found.
	 */
	private function find_string_by_original( $original ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tst_strings';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE original_string = %s LIMIT 1",
				$original
			)
		);
	}
}
