<?php
/**
 * Main plugin class.
 *
 * Singleton that bootstraps all plugin components.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Plugin
 *
 * Central plugin class that initializes all components including
 * runtime translation filters, REST API, admin interface, and
 * the GitHub auto-updater.
 *
 * @since 1.0.0
 */
class TST_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var TST_Plugin|null
	 */
	private static $instance = null;

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
	 * REST API controller instance.
	 *
	 * @since 1.0.0
	 * @var TST_REST_API
	 */
	private $rest_api;

	/**
	 * Admin interface instance.
	 *
	 * @since 1.0.0
	 * @var TST_Admin
	 */
	private $admin;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return TST_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initializes all plugin components, registers hooks, and loads
	 * the plugin text domain.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load text domain.
		load_plugin_textdomain( 'theme-string-translator', false, dirname( TST_PLUGIN_BASENAME ) . '/languages' );

		// Initialize core components.
		$this->string_manager = new TST_String_Manager();
		$this->translator     = new TST_Translator( $this->string_manager );
		$this->exporter       = new TST_Exporter( $this->translator );
		$this->importer       = new TST_Importer( $this->string_manager, $this->translator );

		// Initialize runtime translation filters.
		$runtime = new TST_Runtime();
		$runtime->register_filters();

		// Initialize GitHub updater.
		new TST_Updater( TST_PLUGIN_FILE );

		// Initialize REST API.
		$this->rest_api = new TST_REST_API(
			$this->string_manager,
			$this->translator,
			$this->exporter,
			$this->importer
		);
		add_action( 'rest_api_init', array( $this->rest_api, 'register_routes' ) );

		// Initialize admin.
		$this->admin = new TST_Admin();
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @since 1.0.0
	 *
	 * @throws \Exception Always.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
