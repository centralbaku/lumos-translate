<?php
defined( 'ABSPATH' ) || exit;

/**
 * Language registry. Stored in option `lumos_tr_languages`:
 * [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'flag' => '🇬🇧', 'rtl' => 0 ], ... ]
 */
class Lumos_TR_Languages {

	const OPTION  = 'lumos_tr_languages';
	const DEFAULT = 'lumos_tr_default_language';

	public static function all() {
		$langs = get_option( self::OPTION, array() );
		if ( empty( $langs ) ) {
			// Read the site language directly. get_locale() would run our own 'locale'
			// filter, which calls back into all() and loops until PHP runs out of memory.
			$locale = get_option( 'WPLANG' ) ?: 'en_US';
			$slug   = strtolower( substr( $locale, 0, 2 ) );
			$langs  = array( $slug => array( 'name' => $locale, 'locale' => $locale, 'flag' => '', 'rtl' => 0 ) );
		}
		return $langs;
	}

	public static function slugs() {
		return array_keys( self::all() );
	}

	public static function exists( $slug ) {
		return isset( self::all()[ $slug ] );
	}

	public static function get( $slug ) {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	public static function default_slug() {
		$d = get_option( self::DEFAULT );
		$slugs = self::slugs();
		return ( $d && in_array( $d, $slugs, true ) ) ? $d : reset( $slugs );
	}

	public static function save( array $langs ) {
		$clean = array();
		foreach ( $langs as $slug => $l ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug ) {
				continue;
			}
			$clean[ $slug ] = array(
				'name'   => sanitize_text_field( $l['name'] ?? $slug ),
				'locale' => preg_replace( '/[^A-Za-z_]/', '', $l['locale'] ?? $slug ),
				'flag'   => sanitize_text_field( $l['flag'] ?? '' ),
				'rtl'    => empty( $l['rtl'] ) ? 0 : 1,
			);
		}
		update_option( self::OPTION, $clean );
	}
}
