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

/*
 * LUMOS menu icon. It sits outside the guard below, so the newest copy of this file wins
 * even when another plugin's older copy loaded first. WordPress recolors SVG menu icons
 * to match the admin color scheme, so this one icon works on light and dark schemes.
 */
$lumos_menu_file_version = '1.1.0';
if ( empty( $GLOBALS['lumos_menu_icon_version'] ) || version_compare( $lumos_menu_file_version, $GLOBALS['lumos_menu_icon_version'], '>' ) ) {
	$GLOBALS['lumos_menu_icon_version'] = $lumos_menu_file_version;
	$GLOBALS['lumos_menu_icon']         = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0yMS42NjcyIDE5LjMwMjVMMTkuMjY4NCAyMS42Njg2QzE5LjAxNjYgMjEuNDE2NyAxOC42MjIzIDIxLjAzOTcgMTguMDg1NSAyMC41Mzc3QzE3LjU0ODggMjAuMDM1NiAxNy4xMzcxIDE5LjY0MTMgMTYuODUyMyAxOS4zNTQ2QzE2LjU2NTcgMTkuMDY5NyAxNi4yMTgzIDE4LjY4NTggMTUuODA2NiAxOC4yMDYzQzE1LjM5NDkgMTcuNzI2OCAxNS4wODc1IDE3LjI4NzMgMTQuODgwOCAxNi44ODZDMTQuNjc1OCAxNi40ODY0IDE0LjQ5MTcgMTYuMDA3IDE0LjMzMTkgMTUuNDQ1OEMxNC4xNzIxIDE0Ljg4NjQgMTQuMDkyMiAxNC4yOTc1IDE0LjA5MjIgMTMuNjgwOEgxMy42ODA1VjI0SDEwLjI4NjVDMTAuMjg2NSAyMy42MzUyIDEwLjI3NDMgMjMuMDgxIDEwLjI1MTcgMjIuMzM3NUMxMC4yMjkxIDIxLjU5NTcgMTAuMjE3IDIxLjAyNDEgMTAuMjE3IDIwLjYyMjhDMTAuMjE3IDIwLjIyMTUgMTAuMjQ0OCAxOS43MDkgMTAuMzAyMSAxOS4wODAxQzEwLjM1OTQgMTguNDUxMyAxMC40NTY3IDE3LjkxOTcgMTAuNTkzOSAxNy40ODUzQzEwLjczMTEgMTcuMDUxIDEwLjk0MTMgMTYuNTgyIDExLjIyNzkgMTYuMDc5OUMxMS41MTI4IDE1LjU3NzkgMTEuODYxOSAxNS4xMDg4IDEyLjI3MzYgMTQuNjc0NUwxMS45OTkxIDE0LjRMNC42OTY4MiAyMS42Njg2TDIuMzMxMDQgMTkuMzAyNUMyLjU4MjkgMTkuMDI4IDIuOTU5ODMgMTguNjIzMiAzLjQ2MTgyIDE4LjA4NDdDMy45NjM4MSAxNy41NDc5IDQuMzU4MTEgMTcuMTM2MiA0LjY0NDcxIDE2Ljg1MTJDNC45Mjk1OCAxNi41NjYzIDUuMzEzNDUgMTYuMjIyNCA1Ljc5Mjg2IDE1LjgyMjhDNi4yNzIyNyAxNS40MjMyIDYuNzEzNDcgMTUuMTE5MiA3LjExMjk4IDE0LjkxNDJDNy41MTI0OSAxNC43MDkyIDcuOTkxODkgMTQuNTI1MSA4LjU1Mjk0IDE0LjM2NTNDOS4xMTIyNSAxNC4yMDU0IDkuNzAxMDkgMTQuMTI1NSAxMC4zMTc3IDE0LjEyNTVWMTMuNjc5SDBWMTAuMzE5MkMwLjM4OTA4NiAxMC4zMTkyIDAuOTQ4Mzk3IDEwLjMwODggMS42Nzk2NyAxMC4yODQ1QzIuNDEwOTQgMTAuMjYxOSAyLjk3NzIgMTAuMjQ0NSAzLjM3NjcxIDEwLjIzMjRDMy43NzYyMiAxMC4yMjE5IDQuMjkwMzcgMTAuMjQ0NSA0LjkxOTE2IDEwLjMwMDFDNS41NDc5NSAxMC4zNTc0IDYuMDc5NDcgMTAuNDU0NyA2LjUxMzcyIDEwLjU5MkM2Ljk0Nzk2IDEwLjcyOTIgNy40MTY5NSAxMC45NDEyIDcuOTE4OTQgMTEuMjI2MUM4LjQyMDkzIDExLjUxMjcgOC44ODk5MiAxMS44NzIzIDkuMzI0MTcgMTIuMzA2Nkw5LjU5ODYxIDExLjk5NzRMMi4zMzEwNCA0LjcyODc3TDQuNjk2ODIgMi4zMjk2NEM0Ljk3MTI3IDIuNTgxNTQgNS4zNzU5OSAyLjk1ODUyIDUuOTE0NDUgMy40NjA1OUM2LjQ1MTE4IDMuOTY0MzkgNi44NjI4NSA0LjM1ODc0IDcuMTQ3NzIgNC42NDM2NUM3LjQzMjU4IDQuOTMwMjkgNy43NzY1MSA1LjMxMjQ5IDguMTc2MDEgNS43OTE5N0M4LjU3NTUyIDYuMjcxNDQgOC44Nzk0OSA2LjcxMjcgOS4wODQ0NiA3LjExMjI3QzkuMjg5NDIgNy41MTE4NCA5LjQ3MzU1IDcuOTkzMDUgOS42MzMzNSA4LjU1MjQ0QzkuNzkzMTUgOS4xMTM1NyA5Ljg3MzA1IDkuNzAwNzYgOS44NzMwNSAxMC4zMTc1SDEwLjMxOTVWMEgxMy42Nzg4QzEzLjY3ODggMC4zODkxNDIgMTMuNjg5MiAwLjk0ODUzNCAxMy43MTM1IDEuNjc5OTFDMTMuNzM2MSAyLjQxMTI5IDEzLjc1MzUgMi45Nzc2MyAxMy43NjU3IDMuMzc3MkMxMy43NzYxIDMuNzc2NzYgMTMuNzUzNSA0LjI5MDk5IDEzLjY5NzkgNC45MTk4N0MxMy42NDA2IDUuNTQ4NzUgMTMuNTQzMyA2LjA3NTE0IDEzLjQwNjEgNi40OTcyOUMxMy4yNjg5IDYuOTIxMTcgMTMuMDU3IDcuMzg4NDkgMTIuNzcyMSA3LjkwMjcyQzEyLjQ4NTUgOC40MTY5NCAxMi4xMjU5IDguODc5MDUgMTEuNjkxNyA5LjI5MDc3TDEyLjAwMDkgOS42TDE5LjMwMzIgMi4zMzEzOEwyMS42NjkgNC42OTc1QzIxLjQxNzEgNC45NzE5OSAyMS4wNDAyIDUuMzc4NSAyMC41MzgyIDUuOTE1MzFDMjAuMDM0NCA2LjQ1MjEyIDE5LjY0MDIgNi44NjM4NCAxOS4zNTUzIDcuMTQ4NzVDMTkuMDY4NyA3LjQzNTQgMTguNjg2NSA3Ljc3NzYzIDE4LjIwNzEgOC4xNzcyQzE3LjcyNzcgOC41NzY3NyAxNy4yODY1IDguODgwNzggMTYuODg3IDkuMDg1NzhDMTYuNDg3NSA5LjI5MDc3IDE2LjAwNjQgOS40NzQ5MiAxNS40NDcxIDkuNjM0NzRDMTQuODg2IDkuNzk0NTcgMTQuMjk4OSA5Ljg3NDQ5IDEzLjY4MjMgOS44NzQ0OVYxMC4zMjFIMjRWMTMuNjgwOEMyMy42MTA5IDEzLjY4MDggMjMuMDUxNiAxMy42OTI5IDIyLjMyMDMgMTMuNzE1NUMyMS41ODkxIDEzLjczODEgMjEuMDIyOCAxMy43NTU1IDIwLjYyMzMgMTMuNzY3NkMyMC4yMjM4IDEzLjc3OTggMTkuNzA3OSAxMy43NTU1IDE5LjA4MDggMTMuNjk4MkMxOC40NTIxIDEzLjY0MDggMTcuOTIwNSAxMy41NDM1IDE3LjQ4NjMgMTMuNDA2M0MxNy4wNTIgMTMuMjY5MSAxNi41ODMgMTMuMDU4OCAxNi4wODExIDEyLjc3MjJDMTUuNTc3MyAxMi40ODczIDE1LjExMDEgMTIuMTI2IDE0LjY3NTggMTEuNjkxNkwxNC40MDE0IDEyLjAwMDlMMjEuNjY5IDE5LjMwNDJMMjEuNjY3MiAxOS4zMDI1WiIvPjwvc3ZnPg==';
}
unset( $lumos_menu_file_version );

if ( ! function_exists( 'lumos_menu_add_product' ) ) {

	define( 'LUMOS_MENU_VERSION', '1.1.0' );
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
			$GLOBALS['lumos_menu_icon'],
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
