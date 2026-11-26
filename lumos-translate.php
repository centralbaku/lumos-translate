<?php
/**
 * Plugin Name:       Lumos Translate
 * Description:       Multilingual WordPress: translate pages, posts, CPTs, taxonomies, menus, site options, theme/plugin gettext strings AND hard-coded theme text.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Lumos
 * License:           GPL-2.0-or-later
 * Text Domain:       lumos-translate
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMOS_TR_VERSION', '1.0.0' );
define( 'LUMOS_TR_FILE', __FILE__ );
define( 'LUMOS_TR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMOS_TR_URL', plugin_dir_url( __FILE__ ) );

// Shared LUMOS admin menu (identical copy ships in every Lumos plugin)
require_once LUMOS_TR_DIR . 'includes/lumos-menu.php';

require_once LUMOS_TR_DIR . 'includes/class-languages.php';
require_once LUMOS_TR_DIR . 'includes/class-router.php';
require_once LUMOS_TR_DIR . 'includes/class-posts.php';
require_once LUMOS_TR_DIR . 'includes/class-terms.php';
require_once LUMOS_TR_DIR . 'includes/class-strings.php';
require_once LUMOS_TR_DIR . 'includes/class-scanner.php';
require_once LUMOS_TR_DIR . 'includes/class-menus.php';
require_once LUMOS_TR_DIR . 'includes/class-switcher.php';
require_once LUMOS_TR_DIR . 'includes/class-admin.php';
require_once LUMOS_TR_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'Lumos_TR_Strings', 'install' ) );
register_activation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', function () {
	Lumos_TR_Router::init();   // Must run first: detects language from URL.
	Lumos_TR_Posts::init();
	Lumos_TR_Terms::init();
	Lumos_TR_Strings::init();
	Lumos_TR_Menus::init();
	Lumos_TR_Switcher::init();
	if ( is_admin() ) {
		Lumos_TR_Admin::init();
	}
}, 1 );
