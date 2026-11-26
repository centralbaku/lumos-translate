<?php
/**
 * Shared GitHub updater for Lumos plugins.
 *
 * An identical copy ships inside each Lumos plugin; the first loaded copy defines the
 * class. Each plugin creates its own instance:
 *
 *     new Lumos_GitHub_Updater( __FILE__, 'owner/repo', '1.2.3' );
 *
 * How releases work: every push to the repository's main branch runs a GitHub Actions
 * workflow. When the plugin's "Version:" header has a version that has no release yet,
 * the workflow builds "<slug>-v<version>.zip" and publishes release "v<version>".
 * WordPress then offers the update on Dashboard → Updates and the Plugins screen (and
 * installs it automatically if auto-updates are on for the plugin).
 *
 * Private repositories: define LUMOS_GITHUB_TOKEN in wp-config.php with a GitHub token
 * that can read the repository (fine-grained token, "Contents: read-only").
 *
 * Keep every copy of this file identical across plugins. Bump VERSION when changing it.
 *
 * @package Lumos
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Lumos_GitHub_Updater' ) ) {

	/**
	 * Class Lumos_GitHub_Updater
	 */
	class Lumos_GitHub_Updater {

		const VERSION = '1.0.0';

		/** @var string owner/repo */
		private $repo;

		/** @var string e.g. lumos-backup/lumos-backup.php */
		private $basename;

		/** @var string e.g. lumos-backup */
		private $slug;

		/** @var string Installed version. */
		private $version;

		/** @var string Plugin display name. */
		private $name;

		/** @var string Transient key for the cached release. */
		private $cache_key;

		/**
		 * Constructor: registers the update hooks.
		 *
		 * @param string $plugin_file Main plugin file (__FILE__).
		 * @param string $repo        GitHub repository, "owner/repo".
		 * @param string $version     Installed version.
		 */
		public function __construct( $plugin_file, $repo, $version ) {
			$this->repo      = $repo;
			$this->basename  = plugin_basename( $plugin_file );
			$this->slug      = dirname( $this->basename );
			$this->version   = $version;
			$this->cache_key = 'lumos_gh_' . md5( $repo );

			$data       = function_exists( 'get_file_data' ) ? get_file_data( $plugin_file, array( 'Name' => 'Plugin Name' ) ) : array();
			$this->name = ! empty( $data['Name'] ) ? $data['Name'] : $this->slug;

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
			add_filter( 'upgrader_pre_download', array( $this, 'download_private_package' ), 10, 4 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		}

		/**
		 * Request headers for the GitHub API.
		 *
		 * @param string $accept Accept header.
		 * @return array
		 */
		private function headers( $accept = 'application/vnd.github+json' ) {
			$headers = array(
				'Accept'     => $accept,
				'User-Agent' => 'Lumos-Updater',
			);
			if ( defined( 'LUMOS_GITHUB_TOKEN' ) && LUMOS_GITHUB_TOKEN ) {
				$headers['Authorization'] = 'Bearer ' . LUMOS_GITHUB_TOKEN;
			}
			return $headers;
		}

		/**
		 * Latest release for the repository, cached for 6 hours (failures for 30 minutes,
		 * so an outage or rate limit does not trigger a GitHub call on every admin page).
		 *
		 * @param bool $force Skip the cache.
		 * @return array|null
		 */
		public function get_release( $force = false ) {
			if ( ! $force ) {
				$cached = get_site_transient( $this->cache_key );
				if ( is_array( $cached ) ) {
					return empty( $cached['tag_name'] ) ? null : $cached;
				}
			}

			$response = wp_remote_get(
				'https://api.github.com/repos/' . $this->repo . '/releases/latest',
				array(
					'timeout' => 10,
					'headers' => $this->headers(),
				)
			);

			$body = null;
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
			}

			if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
				set_site_transient( $this->cache_key, array(), 30 * MINUTE_IN_SECONDS );
				return null;
			}

			set_site_transient( $this->cache_key, $body, 6 * HOUR_IN_SECONDS );
			return $body;
		}

		/**
		 * Find this plugin's zip among the release assets.
		 *
		 * @param array $release Release data.
		 * @return array|null Asset data.
		 */
		private function find_asset( $release ) {
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				$name = $asset['name'] ?? '';
				if ( 0 === strpos( $name, $this->slug ) && '.zip' === substr( $name, -4 ) ) {
					return $asset;
				}
			}
			return null;
		}

		/**
		 * Package URL WordPress should download.
		 *
		 * For private repositories the API asset URL is returned; it is downloaded by
		 * download_private_package() with the token.
		 *
		 * @param array $release Release data.
		 * @return string
		 */
		private function package_url( $release ) {
			$asset = $this->find_asset( $release );
			if ( ! $asset ) {
				return '';
			}
			if ( defined( 'LUMOS_GITHUB_TOKEN' ) && LUMOS_GITHUB_TOKEN ) {
				return $asset['url'] ?? '';
			}
			return $asset['browser_download_url'] ?? '';
		}

		/**
		 * Version string from a release tag ("v1.2.3" -> "1.2.3").
		 *
		 * @param array $release Release data.
		 * @return string
		 */
		private function release_version( $release ) {
			return ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );
		}

		/**
		 * Tell WordPress about a newer release.
		 *
		 * @param object $transient update_plugins transient.
		 * @return object
		 */
		public function inject_update( $transient ) {
			if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}

			global $pagenow;
			$force   = ( 'update-core.php' === $pagenow && isset( $_GET['force-check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$release = $this->get_release( $force );
			if ( ! $release ) {
				return $transient;
			}

			$latest  = $this->release_version( $release );
			$package = $this->package_url( $release );

			$item = (object) array(
				'id'           => 'github.com/' . $this->repo,
				'slug'         => $this->slug,
				'plugin'       => $this->basename,
				'new_version'  => $latest,
				'url'          => 'https://github.com/' . $this->repo,
				'package'      => $package,
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => get_bloginfo( 'version' ),
				'requires_php' => '7.4',
			);

			if ( $package && version_compare( $latest, $this->version, '>' ) ) {
				$transient->response[ $this->basename ] = $item;
			} else {
				// Listing it under no_update lets the auto-update toggle appear.
				$item->new_version                       = $this->version;
				$transient->no_update[ $this->basename ] = $item;
			}

			return $transient;
		}

		/**
		 * "View details" popup.
		 *
		 * @param false|object|array $result Default.
		 * @param string             $action API action.
		 * @param object             $args   Arguments.
		 * @return false|object|array
		 */
		public function plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$release = $this->get_release();
			if ( ! $release ) {
				return $result;
			}

			return (object) array(
				'name'          => $this->name,
				'slug'          => $this->slug,
				'version'       => $this->release_version( $release ),
				'author'        => 'Lumos',
				'homepage'      => 'https://github.com/' . $this->repo,
				'download_link' => $this->package_url( $release ),
				'last_updated'  => $release['published_at'] ?? '',
				'sections'      => array(
					'changelog' => nl2br( esc_html( $release['body'] ?? '' ) ),
				),
			);
		}

		/**
		 * Download a private release asset with the token.
		 *
		 * GitHub answers the API asset URL with a redirect to a pre-signed storage URL that
		 * rejects an Authorization header, so the redirect is resolved here and the file
		 * fetched without the token.
		 *
		 * @param bool|string|WP_Error $reply    Short-circuit value.
		 * @param string               $package  Package URL.
		 * @param WP_Upgrader          $upgrader Upgrader.
		 * @param array                $hook_extra Extra data.
		 * @return bool|string|WP_Error Path to downloaded file, or $reply to let WordPress continue.
		 */
		public function download_private_package( $reply, $package, $upgrader = null, $hook_extra = array() ) {
			$prefix = 'https://api.github.com/repos/' . $this->repo . '/releases/assets/';
			if ( false !== $reply || 0 !== strpos( (string) $package, $prefix ) ) {
				return $reply;
			}

			$response = wp_remote_get(
				$package,
				array(
					'timeout'     => 30,
					'redirection' => 0,
					'headers'     => $this->headers( 'application/octet-stream' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! $location ) {
				return new WP_Error( 'lumos_update_download', 'GitHub did not return a download link. Check LUMOS_GITHUB_TOKEN.' );
			}

			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			return download_url( $location, 300 );
		}

		/**
		 * Make sure the unpacked folder is named after the plugin slug, so the update
		 * replaces the installed plugin instead of creating a second copy.
		 *
		 * @param string      $source        Unpacked source folder.
		 * @param string      $remote_source Parent temp folder.
		 * @param WP_Upgrader $upgrader      Upgrader.
		 * @param array       $hook_extra    Extra data.
		 * @return string|WP_Error
		 */
		public function fix_source_folder( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
			global $wp_filesystem;

			if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
				return $source;
			}

			$wanted = trailingslashit( $remote_source ) . $this->slug . '/';
			if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
				return $source;
			}

			if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
				return $wanted;
			}

			return new WP_Error( 'lumos_update_folder', 'Could not rename the update folder to ' . $this->slug . '.' );
		}
	}
}
