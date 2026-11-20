<?php
defined( 'ABSPATH' ) || exit;

/**
 * Detects the current language from the URL prefix (/fr/...), a ?lang= param
 * or a cookie, strips the prefix so WordPress routes normally, and rewrites
 * outgoing links to carry the prefix.
 */
class Lumos_TR_Router {

	protected static $current = null;

	public static function init() {
		self::detect();

		add_filter( 'locale', array( __CLASS__, 'filter_locale' ) );
		if ( ! is_admin() ) {
			foreach ( array( 'post_link', 'page_link', 'post_type_link', 'term_link', 'home_url' ) as $hook ) {
				add_filter( $hook, array( __CLASS__, 'filter_link' ), 20, 2 );
			}
			add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ) );
			add_action( 'wp_head', array( __CLASS__, 'hreflang' ) );
		}
	}

	protected static function detect() {
		$slugs   = Lumos_TR_Languages::slugs();
		$default = Lumos_TR_Languages::default_slug();

		// Admin: language chosen through the admin bar / list filter.
		if ( is_admin() ) {
			$l = isset( $_GET['lumos_lang'] ) ? sanitize_key( $_GET['lumos_lang'] ) : ( $_COOKIE['lumos_admin_lang'] ?? '' );
			self::$current = in_array( $l, $slugs, true ) ? $l : $default;
			return;
		}

		$uri  = $_SERVER['REQUEST_URI'] ?? '/';
		$base = rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
		$path = substr( $uri, strlen( $base ) );

		if ( preg_match( '#^/([a-z]{2,5})(/|$|\?)#', $path, $m ) && in_array( $m[1], $slugs, true ) && $m[1] !== $default ) {
			self::$current = $m[1];
			// Strip the prefix so WP resolves the request as usual.
			$_SERVER['REQUEST_URI'] = $base . substr( $path, strlen( $m[1] ) + 1 );
			if ( '' === $_SERVER['REQUEST_URI'] || '?' === $_SERVER['REQUEST_URI'][0] ) {
				$_SERVER['REQUEST_URI'] = '/' . ltrim( $_SERVER['REQUEST_URI'], '/' );
			}
		} elseif ( isset( $_GET['lang'] ) && in_array( $_GET['lang'], $slugs, true ) ) {
			self::$current = sanitize_key( $_GET['lang'] );
		} else {
			self::$current = $default;
		}
	}

	public static function current() {
		return self::$current ?: Lumos_TR_Languages::default_slug();
	}

	public static function set_current( $slug ) {
		if ( Lumos_TR_Languages::exists( $slug ) ) {
			self::$current = $slug;
		}
	}

	public static function filter_locale( $locale ) {
		if ( is_admin() ) {
			return $locale; // Keep the admin UI in the user's own locale.
		}
		$l = Lumos_TR_Languages::get( self::current() );
		return $l['locale'] ?? $locale;
	}

	/** Add language prefix to a URL. */
	public static function url_for( $url, $lang ) {
		$home  = untrailingslashit( get_option( 'home' ) );
		$slugs = implode( '|', array_map( 'preg_quote', Lumos_TR_Languages::slugs() ) );
		if ( 0 !== strpos( $url, $home ) ) {
			return $url;
		}
		$rest = substr( $url, strlen( $home ) );
		$rest = preg_replace( '#^/(' . $slugs . ')(?=/|$)#', '', $rest ); // Drop existing prefix.
		if ( $lang === Lumos_TR_Languages::default_slug() ) {
			return $home . $rest;
		}
		return $home . '/' . $lang . ( '' === $rest ? '/' : $rest );
	}

	public static function filter_link( $url, $obj = null ) {
		$lang = self::current();
		if ( $obj instanceof WP_Term ) {
			$lang = Lumos_TR_Terms::get_lang( $obj->term_id ) ?: $lang;
		} elseif ( is_numeric( $obj ) || $obj instanceof WP_Post ) {
			$id   = is_object( $obj ) ? $obj->ID : (int) $obj;
			$lang = Lumos_TR_Posts::get_lang( $id ) ?: $lang;
		}
		return self::url_for( $url, $lang );
	}

	public static function language_attributes( $output ) {
		$l = Lumos_TR_Languages::get( self::current() );
		if ( ! $l ) {
			return $output;
		}
		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( str_replace( '_', '-', $l['locale'] ) ) . '"', $output );
		if ( $l['rtl'] && false === strpos( $output, 'dir=' ) ) {
			$output .= ' dir="rtl"';
		}
		return $output;
	}

	public static function hreflang() {
		foreach ( Lumos_TR_Switcher::links() as $slug => $link ) {
			printf( "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n", esc_attr( str_replace( '_', '-', $link['locale'] ) ), esc_url( $link['url'] ) );
		}
	}
}
