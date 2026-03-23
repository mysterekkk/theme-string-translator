<?php
/**
 * REST API controller.
 *
 * Registers and handles all REST API routes for the plugin.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_REST_API
 *
 * Provides REST API endpoints for string management, scanning,
 * translation, import/export, and configuration.
 *
 * @since 1.0.0
 */
class TST_REST_API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'tst/v1';

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
	 * Exporter instance.
	 *
	 * @since 1.0.0
	 * @var TST_Exporter
	 */
	private $exporter;

	/**
	 * Importer instance.
	 *
	 * @since 1.0.0
	 * @var TST_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param TST_String_Manager $string_manager The string manager instance.
	 * @param TST_Translator     $translator     The translator instance.
	 * @param TST_Exporter       $exporter       The exporter instance.
	 * @param TST_Importer       $importer       The importer instance.
	 */
	public function __construct(
		TST_String_Manager $string_manager,
		TST_Translator $translator,
		TST_Exporter $exporter,
		TST_Importer $importer
	) {
		$this->string_manager = $string_manager;
		$this->translator     = $translator;
		$this->exporter       = $exporter;
		$this->importer       = $importer;
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// Strings.
		register_rest_route( self::NAMESPACE, '/strings', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_strings' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'page'        => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
				'per_page'    => array(
					'default'           => 50,
					'sanitize_callback' => 'absint',
				),
				'status'      => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'source_type' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'search'      => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/strings/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_string' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/strings/(?P<id>\d+)/translate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'translate_string' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/strings/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'delete_string' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/strings/bulk', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'bulk_action' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Scan.
		register_rest_route( self::NAMESPACE, '/scan', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'scan_theme' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/scan/status', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'scan_status' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Languages.
		register_rest_route( self::NAMESPACE, '/languages', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_languages' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/languages', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_languages' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Export.
		register_rest_route( self::NAMESPACE, '/export', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'export' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Import.
		register_rest_route( self::NAMESPACE, '/import', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'import' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Stats.
		register_rest_route( self::NAMESPACE, '/stats', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	/**
	 * Check if the current user has permission to access the API.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the user has manage_options capability.
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /strings — Retrieve paginated list of strings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_strings( $request ) {
		$result = $this->string_manager->get_strings( array(
			'page'        => $request->get_param( 'page' ),
			'per_page'    => $request->get_param( 'per_page' ),
			'status'      => $request->get_param( 'status' ),
			'source_type' => $request->get_param( 'source_type' ),
			'search'      => $request->get_param( 'search' ),
		) );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /strings/{id} — Retrieve a single string.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_string( $request ) {
		$string = $this->string_manager->get_string( $request->get_param( 'id' ) );

		if ( ! $string ) {
			return new \WP_REST_Response( array( 'message' => __( 'String not found.', 'theme-string-translator' ) ), 404 );
		}

		return new \WP_REST_Response( $string, 200 );
	}

	/**
	 * POST /strings/{id}/translate — Save a translation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function translate_string( $request ) {
		$id          = $request->get_param( 'id' );
		$locale      = sanitize_text_field( $request->get_param( 'locale' ) );
		$translation = wp_kses_post( $request->get_param( 'translation' ) );

		if ( empty( $locale ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Locale is required.', 'theme-string-translator' ) ), 400 );
		}

		$result = $this->translator->save_translation( $id, $locale, $translation );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'message' => $result->get_error_message() ),
				$result->get_error_data()['status'] ?? 400
			);
		}

		// Return updated string.
		$string = $this->string_manager->get_string( $id );

		return new \WP_REST_Response( $string, 200 );
	}

	/**
	 * DELETE /strings/{id} — Delete a string.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function delete_string( $request ) {
		$result = $this->string_manager->delete_string( $request->get_param( 'id' ) );

		if ( false === $result ) {
			return new \WP_REST_Response( array( 'message' => __( 'Failed to delete string.', 'theme-string-translator' ) ), 500 );
		}

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * POST /strings/bulk — Perform bulk actions on strings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function bulk_action( $request ) {
		$action = sanitize_text_field( $request->get_param( 'action' ) );
		$ids    = $request->get_param( 'ids' );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'No string IDs provided.', 'theme-string-translator' ) ), 400 );
		}

		$ids   = array_map( 'absint', $ids );
		$count = 0;

		switch ( $action ) {
			case 'delete':
				foreach ( $ids as $id ) {
					if ( $this->string_manager->delete_string( $id ) ) {
						$count++;
					}
				}
				break;

			case 'clear_all':
				$this->string_manager->clear_all();
				$count = count( $ids );
				break;

			default:
				return new \WP_REST_Response( array( 'message' => __( 'Invalid bulk action.', 'theme-string-translator' ) ), 400 );
		}

		return new \WP_REST_Response( array(
			'action'    => $action,
			'processed' => $count,
		), 200 );
	}

	/**
	 * POST /scan — Trigger a theme scan.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function scan_theme( $request ) {
		$scanner = new TST_Scanner( $this->string_manager );
		$count   = $scanner->scan_theme();

		return new \WP_REST_Response( array(
			'message' => sprintf(
				/* translators: %d: number of strings found */
				__( 'Scan complete. Found %d strings.', 'theme-string-translator' ),
				$count
			),
			'count'   => $count,
		), 200 );
	}

	/**
	 * GET /scan/status — Get current scan progress.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function scan_status( $request ) {
		$progress = get_transient( 'tst_scan_progress' );

		if ( false === $progress ) {
			return new \WP_REST_Response( array( 'status' => 'idle' ), 200 );
		}

		return new \WP_REST_Response( $progress, 200 );
	}

	/**
	 * GET /languages — Get configured languages.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_languages( $request ) {
		$languages = get_option( 'tst_languages', array() );

		return new \WP_REST_Response( $languages, 200 );
	}

	/**
	 * POST /languages — Update configured languages.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function set_languages( $request ) {
		$languages = $request->get_param( 'languages' );

		if ( ! is_array( $languages ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Languages must be an array.', 'theme-string-translator' ) ), 400 );
		}

		$languages = array_map( 'sanitize_text_field', $languages );
		$languages = array_values( array_filter( $languages ) );

		update_option( 'tst_languages', $languages );

		return new \WP_REST_Response( array(
			'languages' => $languages,
			'message'   => __( 'Languages updated.', 'theme-string-translator' ),
		), 200 );
	}

	/**
	 * POST /export — Export translations in the requested format.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function export( $request ) {
		$format = sanitize_text_field( $request->get_param( 'format' ) );
		$locale = sanitize_text_field( $request->get_param( 'locale' ) );

		if ( empty( $format ) || empty( $locale ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Format and locale are required.', 'theme-string-translator' ) ), 400 );
		}

		switch ( $format ) {
			case 'po':
				$content = $this->exporter->export_po( $locale );
				return new \WP_REST_Response( array(
					'content'  => $content,
					'filename' => $locale . '.po',
					'type'     => 'text/x-gettext-translation',
				), 200 );

			case 'mo':
				$content = $this->exporter->export_mo( $locale );
				return new \WP_REST_Response( array(
					'content'  => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'filename' => $locale . '.mo',
					'type'     => 'application/octet-stream',
					'encoding' => 'base64',
				), 200 );

			case 'json':
				$content = $this->exporter->export_json( $locale );
				return new \WP_REST_Response( array(
					'content'  => $content,
					'filename' => $locale . '.json',
					'type'     => 'application/json',
				), 200 );

			case 'php':
				$content = $this->exporter->export_php_array( $locale );
				return new \WP_REST_Response( array(
					'content'  => $content,
					'filename' => $locale . '.php',
					'type'     => 'application/x-php',
				), 200 );

			case 'theme':
				$result = $this->exporter->write_to_theme( $locale );
				if ( is_wp_error( $result ) ) {
					return new \WP_REST_Response(
						array( 'message' => $result->get_error_message() ),
						$result->get_error_data()['status'] ?? 500
					);
				}
				return new \WP_REST_Response( array(
					'message' => __( 'Files written to theme languages directory.', 'theme-string-translator' ),
					'files'   => $result,
				), 200 );

			default:
				return new \WP_REST_Response( array( 'message' => __( 'Invalid export format.', 'theme-string-translator' ) ), 400 );
		}
	}

	/**
	 * POST /import — Import translations from an uploaded file.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function import( $request ) {
		$files  = $request->get_file_params();
		$locale = sanitize_text_field( $request->get_param( 'locale' ) );

		if ( empty( $locale ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Locale is required.', 'theme-string-translator' ) ), 400 );
		}

		if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'No file uploaded.', 'theme-string-translator' ) ), 400 );
		}

		$file_content = file_get_contents( $files['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $file_content ) {
			return new \WP_REST_Response( array( 'message' => __( 'Failed to read uploaded file.', 'theme-string-translator' ) ), 500 );
		}

		$filename  = sanitize_file_name( $files['file']['name'] );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		switch ( $extension ) {
			case 'po':
				$result = $this->importer->import_po( $file_content, $locale );
				break;

			case 'json':
				$result = $this->importer->import_json( $file_content, $locale );
				break;

			default:
				return new \WP_REST_Response( array( 'message' => __( 'Unsupported file format. Use .po or .json files.', 'theme-string-translator' ) ), 400 );
		}

		return new \WP_REST_Response( array(
			'message'  => sprintf(
				/* translators: 1: imported count, 2: total count */
				__( 'Imported %1$d of %2$d translations.', 'theme-string-translator' ),
				$result['imported'],
				$result['total']
			),
			'summary'  => $result,
		), 200 );
	}

	/**
	 * GET /stats — Get translation statistics.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$stats = $this->string_manager->get_stats();

		return new \WP_REST_Response( $stats, 200 );
	}
}
