<?php
defined( 'ABSPATH' ) || exit;

/**
 * Per-language menus: every theme menu location gets a copy per language
 * (e.g. "primary___fr"). The front end swaps locations to the current language.
 * Menu item titles are also passed through the string table as a fallback.
 */
class Lumos_TR_Menus {

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_locations' ), 999 );
		add_filter( 'theme_mod_nav_menu_locations', array( __CLASS__, 'swap_locations' ) );
		if ( ! is_admin() ) {
			add_filter( 'nav_menu_item_title', function ( $title ) {
				return Lumos_TR_Strings::lookup( wp_strip_all_tags( $title ), '', 'menu', Lumos_TR_Strings::ORIGIN_OPTION ) ?? $title;
			} );
			add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'translate_links' ) );
		}
	}

	public static function register_locations() {
		$default = Lumos_TR_Languages::default_slug();
		$extra   = array();
		foreach ( get_registered_nav_menus() as $loc => $label ) {
			if ( false !== strpos( $loc, '___' ) ) {
				continue;
			}
			foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
				if ( $slug !== $default ) {
					$extra[ $loc . '___' . $slug ] = $label . ' (' . $l['name'] . ')';
				}
			}
		}
		if ( $extra ) {
			register_nav_menus( $extra );
		}
	}

	public static function swap_locations( $locations ) {
		if ( is_admin() || ! is_array( $locations ) ) {
			return $locations;
		}
		$lang = Lumos_TR_Router::current();
		foreach ( $locations as $loc => $menu ) {
			if ( false === strpos( $loc, '___' ) && ! empty( $locations[ $loc . '___' . $lang ] ) ) {
				$locations[ $loc ] = $locations[ $loc . '___' . $lang ];
			}
		}
		return $locations;
	}

	/** Point menu items that link to posts at their translation. */
	public static function translate_links( $items ) {
		$lang = Lumos_TR_Router::current();
		foreach ( $items as $item ) {
			if ( 'post_type' === $item->type ) {
				$t = Lumos_TR_Posts::get_translation( $item->object_id, $lang );
				if ( $t && (int) $t !== (int) $item->object_id ) {
					$item->url = get_permalink( $t );
					if ( $item->title === get_the_title( $item->object_id ) ) {
						$item->title = get_the_title( $t );
					}
				}
			} elseif ( 'taxonomy' === $item->type ) {
				$t = Lumos_TR_Terms::get_translation( $item->object_id, $lang );
				if ( $t ) {
					$item->url = get_term_link( (int) $t );
				}
			}
		}
		return $items;
	}
}
