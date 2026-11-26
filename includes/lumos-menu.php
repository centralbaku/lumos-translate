<?php
/**
 * Shared "LUMOS" admin menu used by every Lumos plugin.
 *
 * An identical copy of this file ships inside each Lumos plugin. Whichever plugin loads
 * first defines the functions; the other copies are skipped. Each plugin then adds its
 * own page under the LUMOS menu with lumos_menu_add_product().
 *
 * Keep every copy of this file identical across plugins. Bump LUMOS_MENU_VERSION when
 * changing it.
 *
 * @package Lumos
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'lumos_menu_add_product' ) ) {

	define( 'LUMOS_MENU_VERSION', '1.0.0' );
	define( 'LUMOS_MENU_SLUG', 'lumos' );

	$GLOBALS['lumos_menu_products'] = array();

	/**
	 * Add a product page under the LUMOS menu. Call it from an admin_menu callback.
	 *
	 * @param string   $slug        Page slug (used as ?page=<slug>).
	 * @param string   $title       Menu label, e.g. "Backup".
	 * @param callable $callback    Function that renders the page.
	 * @param string   $description One line shown on the LUMOS overview page.
	 * @param string   $capability  Capability required to see the page.
	 * @param string   $icon        Dashicons class shown on the overview card.
	 * @return string|false Page hook suffix, or false if the user lacks the capability.
	 */
	function lumos_menu_add_product( $slug, $title, $callback, $description = '', $capability = 'manage_options', $icon = 'dashicons-admin-plugins' ) {
		$GLOBALS['lumos_menu_products'][ $slug ] = array(
			'title'       => $title,
			'description' => $description,
			'capability'  => $capability,
			'icon'        => $icon,
		);

		return add_submenu_page( LUMOS_MENU_SLUG, $title, $title, $capability, $slug, $callback );
	}

	/**
	 * Register a page that has no menu item of its own (for example a product's sub-screen)
	 * but should keep the LUMOS menu open with the given product highlighted.
	 *
	 * @param string   $slug        Page slug.
	 * @param string   $title       Page title.
	 * @param callable $callback    Render callback.
	 * @param string   $product     Slug of the product whose menu item stays highlighted.
	 * @param string   $capability  Capability.
	 * @return string|false Page hook suffix.
	 */
	function lumos_menu_add_hidden_page( $slug, $title, $callback, $product, $capability = 'manage_options' ) {
		$GLOBALS['lumos_menu_hidden'][ $slug ] = $product;

		// options.php as parent registers the page without showing it in any menu.
		return add_submenu_page( 'options.php', $title, $title, $capability, $slug, $callback );
	}

	/**
	 * Create the top-level LUMOS menu before products add their pages.
	 */
	function lumos_menu_register_parent() {
		add_menu_page(
			'LUMOS',
			'LUMOS',
			'manage_options',
			LUMOS_MENU_SLUG,
			'lumos_menu_render_overview',
			'dashicons-lightbulb',
			58
		);
	}
	add_action( 'admin_menu', 'lumos_menu_register_parent', 5 );

	/**
	 * WordPress repeats the parent as the first submenu item; remove it so the submenu
	 * lists only products (Backup, Slider, Translate, ...), sorted by name.
	 */
	function lumos_menu_tidy_submenu() {
		global $submenu;

		remove_submenu_page( LUMOS_MENU_SLUG, LUMOS_MENU_SLUG );

		if ( ! empty( $submenu[ LUMOS_MENU_SLUG ] ) ) {
			usort(
				$submenu[ LUMOS_MENU_SLUG ],
				function ( $a, $b ) {
					return strcasecmp( $a[0], $b[0] );
				}
			);
		}
	}
	add_action( 'admin_menu', 'lumos_menu_tidy_submenu', 999 );

	/**
	 * Keep LUMOS open and the right product highlighted on hidden sub-screens.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	function lumos_menu_parent_file( $parent_file ) {
		global $plugin_page, $submenu_file;

		if ( $plugin_page && isset( $GLOBALS['lumos_menu_hidden'][ $plugin_page ] ) ) {
			$submenu_file = $GLOBALS['lumos_menu_hidden'][ $plugin_page ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return LUMOS_MENU_SLUG;
		}

		return $parent_file;
	}
	add_filter( 'parent_file', 'lumos_menu_parent_file' );

	/**
	 * Overview page shown when the LUMOS menu itself is clicked.
	 */
	function lumos_menu_render_overview() {
		echo '<div class="wrap"><h1>LUMOS</h1>';
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-top:16px;">';

		$products = $GLOBALS['lumos_menu_products'];
		uasort(
			$products,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		foreach ( $products as $slug => $p ) {
			if ( ! current_user_can( $p['capability'] ) ) {
				continue;
			}
			printf(
				'<a href="%1$s" style="display:block;padding:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;text-decoration:none;color:inherit;">' .
					'<span class="dashicons %2$s" style="font-size:28px;width:28px;height:28px;color:#2271b1;"></span>' .
					'<h2 style="margin:12px 0 6px;">%3$s</h2><p style="margin:0;color:#50575e;">%4$s</p></a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				esc_attr( $p['icon'] ),
				esc_html( $p['title'] ),
				esc_html( $p['description'] )
			);
		}

		echo '</div></div>';
	}
}
