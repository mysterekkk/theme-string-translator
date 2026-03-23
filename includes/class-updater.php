<?php
/**
 * GitHub updater.
 *
 * Handles automatic plugin updates from GitHub releases.
 *
 * @package Theme_String_Translator
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TST_Updater
 *
 * Checks for new releases on GitHub and integrates with the WordPress
 * plugin update system to provide seamless updates.
 *
 * @since 1.0.0
 */
class TST_Updater {

	/**
	 * Plugin slug derived from the plugin file basename.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * GitHub repository in owner/repo format.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $github_repo = 'mysterekkk/theme-string-translator';

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Cached GitHub API response.
	 *
	 * @since 1.0.0
	 * @var object|null
	 */
	private $github_response = null;

	/**
	 * Constructor.
	 *
	 * Registers the update-related WordPress filters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	/**
	 * Fetch the latest release data from the GitHub API.
	 *
	 * Caches the response in a transient for 6 hours to avoid
	 * excessive API requests.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null The release data or null on failure.
	 */
	private function get_github_release() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		$cached = get_transient( 'tst_github_release' );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $this->github_response;
		}

		$url      = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $body ) || ! isset( $body->tag_name ) ) {
			return null;
		}

		$this->github_response = $body;
		set_transient( 'tst_github_release', $body, 6 * HOUR_IN_SECONDS );

		return $this->github_response;
	}

	/**
	 * Check for available updates.
	 *
	 * Compares the latest GitHub release tag with the current plugin version
	 * and injects an update object into the WordPress update transient.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object Modified transient data.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_github_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release->tag_name, 'v' );

		if ( version_compare( TST_VERSION, $remote_version, '<' ) ) {
			$download_url = '';
			if ( ! empty( $release->zipball_url ) ) {
				$download_url = $release->zipball_url;
			}

			$update = (object) array(
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $remote_version,
				'url'         => "https://github.com/{$this->github_repo}",
				'package'     => $download_url,
			);

			$transient->response[ $this->plugin_slug ] = $update;
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the WordPress plugins API.
	 *
	 * Returns a detailed plugin information object when WordPress
	 * requests info for this plugin's slug.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $result The result object or false.
	 * @param string             $action The API action being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object The plugin info object or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( $this->plugin_slug ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_github_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release->tag_name, 'v' );

		$info = (object) array(
			'name'          => 'Theme String Translator',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $remote_version,
			'author'        => '<a href="https://luroweb.pl">LuroWeb - Łukasz Rosikoń</a>',
			'author_profile' => 'https://luroweb.pl',
			'homepage'      => "https://github.com/{$this->github_repo}",
			'download_link' => $release->zipball_url ?? '',
			'requires'      => '5.6',
			'tested'        => '6.7',
			'requires_php'  => '7.4',
			'last_updated'  => $release->published_at ?? '',
			'sections'      => array(
				'description' => 'Lightweight theme string translation without WPML. Scan your theme, translate strings, export to PO/MO/JSON.',
				'changelog'   => ! empty( $release->body ) ? nl2br( esc_html( $release->body ) ) : 'See GitHub releases for changelog.',
			),
		);

		return $info;
	}

	/**
	 * Handle post-install tasks after a plugin update.
	 *
	 * Renames the installed directory from the GitHub zip name to the
	 * expected plugin directory name and re-activates the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param bool  $response   Installation response data.
	 * @param array $hook_extra Extra data passed by the upgrader.
	 * @param array $result     Installation result data.
	 * @return array Modified result data.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		// Only process our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $result;
		}

		global $wp_filesystem;

		$proper_destination = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );
		$install_directory  = $result['destination'];

		// Move to proper directory if needed.
		if ( $install_directory !== $proper_destination ) {
			$wp_filesystem->move( $install_directory, $proper_destination );
			$result['destination'] = $proper_destination;
		}

		// Re-activate the plugin.
		$activate = activate_plugin( $this->plugin_slug );

		return $result;
	}
}
