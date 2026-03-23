<?php
/**
 * Admin interface.
 *
 * Registers the admin menu page and enqueues assets for the
 * Theme String Translator admin UI.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Admin
 *
 * Handles the WordPress admin integration including menu registration,
 * page rendering, and asset enqueuing.
 *
 * @since 1.0.0
 */
class TST_Admin {

	/**
	 * The admin page hook suffix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Register the admin menu page.
	 *
	 * Adds a top-level menu page for Theme String Translator using
	 * the translation dashicon.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'Theme Translator', 'theme-string-translator' ),
			__( 'Theme Translator', 'theme-string-translator' ),
			'manage_options',
			'theme-string-translator',
			array( $this, 'render_page' ),
			'dashicons-translation',
			80
		);
	}

	/**
	 * Render the admin page.
	 *
	 * Outputs the root container for the React-based admin interface.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div class="wrap"><div id="tst-admin-root"></div></div>';
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * Only loads assets on the plugin's admin page. Enqueues the React
	 * application bundle along with WordPress component dependencies
	 * and passes configuration data via wp_localize_script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $this->hook_suffix !== $hook ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'tst-admin',
			TST_PLUGIN_URL . 'admin/css/admin.css',
			array( 'wp-components' ),
			TST_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'tst-admin',
			TST_PLUGIN_URL . 'admin/js/admin.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			TST_VERSION,
			true
		);

		// Pass configuration data to JavaScript.
		wp_localize_script( 'tst-admin', 'tstData', array(
			'restUrl' => esc_url_raw( rest_url( 'tst/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'version' => TST_VERSION,
		) );
	}
}
