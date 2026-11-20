<?php
defined( 'ABSPATH' ) || exit;

/**
 * String translation engine. Covers:
 *  - gettext strings from themes & plugins (__(), _e(), esc_html__(), _x(), _n() ...)
 *  - hard-coded literal text in theme templates (via output-buffer replacement)
 *  - site options (blog name, tagline, any option/theme_mod you register)
 *  - widget titles / text, block content strings
 *
 * Tables:
 *  {prefix}lumos_tr_strings      id, hash, source, context, domain, origin, last_seen
 *  {prefix}lumos_tr_translations string_id, lang, translation
 */
class Lumos_TR_Strings {

	const ORIGIN_GETTEXT = 'gettext';
	const ORIGIN_LITERAL = 'literal';
	const ORIGIN_OPTION  = 'option';
	const ORIGIN_MANUAL  = 'manual';

	protected static $map      = null;    // hash => translation (current lang)
	protected static $literals = null;    // source => translation (current lang)
	protected static $known    = null;    // hash => true
	protected static $pending  = array(); // discovered strings to insert
	protected static $busy     = false;

	public static function table( $which = 'strings' ) {
		global $wpdb;
		return $wpdb->prefix . ( 'strings' === $which ? 'lumos_tr_strings' : 'lumos_tr_translations' );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			hash CHAR(32) NOT NULL,
			source LONGTEXT NOT NULL,
			context VARCHAR(191) NOT NULL DEFAULT '',
			domain VARCHAR(191) NOT NULL DEFAULT '',
			origin VARCHAR(20) NOT NULL DEFAULT 'gettext',
			last_seen DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY hash (hash),
			KEY domain (domain),
			KEY origin (origin)
		) $c;" );
		dbDelta( 'CREATE TABLE ' . self::table( 'tr' ) . " (
			string_id BIGINT UNSIGNED NOT NULL,
			lang VARCHAR(10) NOT NULL,
			translation LONGTEXT NOT NULL,
			PRIMARY KEY  (string_id,lang)
		) $c;" );
		update_option( 'lumos_tr_db_version', LUMOS_TR_VERSION );
	}

	public static function hash( $source, $context = '', $domain = '' ) {
		return md5( $domain . "\x04" . $context . "\x04" . $source );
	}

	public static function init() {
		if ( get_option( 'lumos_tr_db_version' ) !== LUMOS_TR_VERSION ) {
			self::install();
		}
		$front = ! is_admin() || wp_doing_ajax();
		if ( ! $front ) {
			return;
		}
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'ngettext' ), 20, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'ngettext_context' ), 20, 6 );

		foreach ( array_merge( array( 'blogname', 'blogdescription' ), self::registered_options() ) as $opt ) {
			add_filter( 'option_' . $opt, function ( $v ) use ( $opt ) {
				return Lumos_TR_Strings::translate_value( $v, 'option:' . $opt );
			} );
		}
		foreach ( self::registered_theme_mods() as $mod ) {
			add_filter( 'theme_mod_' . $mod, function ( $v ) use ( $mod ) {
				return Lumos_TR_Strings::translate_value( $v, 'theme_mod:' . $mod );
			} );
		}
		add_filter( 'widget_title', function ( $t ) {
			return Lumos_TR_Strings::translate_value( $t, 'widget' );
		} );
		add_filter( 'widget_text', function ( $t ) {
			return Lumos_TR_Strings::translate_value( $t, 'widget' );
		} );

		if ( get_option( 'lumos_tr_literal_mode', 1 ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 0 );
		}
		add_action( 'shutdown', array( __CLASS__, 'flush_pending' ) );
	}

	public static function registered_options() {
		return array_filter( array_map( 'trim', explode( "\n", (string) get_option( 'lumos_tr_options', '' ) ) ) );
	}

	public static function registered_theme_mods() {
		return array_filter( array_map( 'trim', explode( "\n", (string) get_option( 'lumos_tr_theme_mods', '' ) ) ) );
	}

	protected static function discovery_on() {
		return (bool) get_option( 'lumos_tr_discovery', 1 ) && ! is_admin();
	}

	protected static function load() {
		if ( null !== self::$map ) {
			return;
		}
		global $wpdb;
		self::$map = self::$literals = self::$known = array();
		$lang  = Lumos_TR_Router::current();
		$cache = wp_cache_get( 'map_' . $lang, 'lumos_tr' );
		if ( false === $cache ) {
			$cache = get_transient( 'lumos_tr_map_' . $lang );
		}
		if ( false === $cache ) {
			$rows  = $wpdb->get_results( $wpdb->prepare(
				'SELECT s.hash, s.source, s.origin, t.translation FROM ' . self::table() . ' s LEFT JOIN ' . self::table( 'tr' ) . ' t ON t.string_id = s.id AND t.lang = %s',
				$lang
			), ARRAY_A );
			$cache = array( 'map' => array(), 'lit' => array(), 'known' => array() );
			foreach ( $rows as $r ) {
				$cache['known'][ $r['hash'] ] = 1;
				if ( null === $r['translation'] || '' === $r['translation'] ) {
					continue;
				}
				$cache['map'][ $r['hash'] ] = $r['translation'];
				if ( self::ORIGIN_LITERAL === $r['origin'] || self::ORIGIN_MANUAL === $r['origin'] ) {
					$cache['lit'][ $r['source'] ] = $r['translation'];
				}
			}
			set_transient( 'lumos_tr_map_' . $lang, $cache, DAY_IN_SECONDS );
			wp_cache_set( 'map_' . $lang, $cache, 'lumos_tr' );
		}
		self::$map      = $cache['map'];
		self::$literals = $cache['lit'];
		self::$known    = $cache['known'];
	}

	public static function clear_cache() {
		foreach ( Lumos_TR_Languages::slugs() as $l ) {
			delete_transient( 'lumos_tr_map_' . $l );
			wp_cache_delete( 'map_' . $l, 'lumos_tr' );
		}
	}

	/** Core lookup. */
	public static function lookup( $source, $context = '', $domain = '', $origin = self::ORIGIN_GETTEXT ) {
		if ( self::$busy || '' === $source || ! is_string( $source ) ) {
			return null;
		}
		self::$busy = true;
		self::load();
		$h = self::hash( $source, $context, $domain );
		if ( ! isset( self::$known[ $h ] ) && self::discovery_on() && count( self::$pending ) < 2000 ) {
			self::$pending[ $h ] = compact( 'source', 'context', 'domain', 'origin' );
			self::$known[ $h ]   = 1;
		}
		self::$busy = false;
		return self::$map[ $h ] ?? null;
	}

	public static function gettext( $translation, $text, $domain ) {
		return self::lookup( $text, '', $domain ) ?? $translation;
	}

	public static function gettext_context( $translation, $text, $context, $domain ) {
		return self::lookup( $text, $context, $domain ) ?? $translation;
	}

	public static function ngettext( $translation, $single, $plural, $n, $domain ) {
		return self::lookup( 1 === (int) $n ? $single : $plural, '', $domain ) ?? $translation;
	}

	public static function ngettext_context( $translation, $single, $plural, $n, $context, $domain ) {
		return self::lookup( 1 === (int) $n ? $single : $plural, $context, $domain ) ?? $translation;
	}

	/** Translate a scalar or (recursively) an array value. */
	public static function translate_value( $value, $domain ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::translate_value( $v, $domain );
			}
			return $value;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) || is_numeric( $value ) ) {
			return $value;
		}
		return self::lookup( $value, '', $domain, self::ORIGIN_OPTION ) ?? $value;
	}

	/* ---------- Hard-coded text: output buffer ---------- */

	public static function start_buffer() {
		if ( is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'replace_literals' ) );
	}

	public static function replace_literals( $html ) {
		self::load();
		if ( empty( self::$literals ) || stripos( $html, '<html' ) === false ) {
			return $html;
		}
		$lit = self::$literals;
		// Text nodes, skipping <script>/<style>.
		$parts = preg_split( '#(<script\b.*?</script>|<style\b.*?</style>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( $i % 2 ) {
				continue;
			}
			$part = preg_replace_callback( '#>([^<>]+)<#u', function ( $m ) use ( $lit ) {
				$raw  = $m[1];
				$trim = trim( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $trim || ! isset( $lit[ $trim ] ) ) {
					return $m[0];
				}
				$lead  = preg_match( '/^\s*/u', $raw, $a ) ? $a[0] : '';
				$trail = preg_match( '/\s*$/u', $raw, $b ) ? $b[0] : '';
				return '>' . $lead . esc_html( $lit[ $trim ] ) . $trail . '<';
			}, $part );
			// Translatable attributes.
			$part = preg_replace_callback( '#\b(placeholder|title|alt|aria-label|value|content)=("|\')([^"\']+)\2#iu', function ( $m ) use ( $lit ) {
				$v = trim( html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' ) );
				return isset( $lit[ $v ] ) ? $m[1] . '=' . $m[2] . esc_attr( $lit[ $v ] ) . $m[2] : $m[0];
			}, $part );
			$parts[ $i ] = $part;
		}
		return implode( '', $parts );
	}

	/* ---------- Storage ---------- */

	public static function flush_pending() {
		if ( empty( self::$pending ) ) {
			return;
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		foreach ( self::$pending as $h => $s ) {
			$wpdb->query( $wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table() . ' (hash, source, context, domain, origin, last_seen) VALUES (%s,%s,%s,%s,%s,%s)',
				$h, $s['source'], $s['context'], $s['domain'], $s['origin'], $now
			) );
		}
		self::$pending = array();
		self::clear_cache();
	}

	public static function add( $source, $context = '', $domain = '', $origin = self::ORIGIN_MANUAL ) {
		global $wpdb;
		$source = trim( (string) $source );
		if ( '' === $source ) {
			return 0;
		}
		$h = self::hash( $source, $context, $domain );
		$wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . self::table() . ' (hash, source, context, domain, origin, last_seen) VALUES (%s,%s,%s,%s,%s,%s)',
			$h, $source, $context, $domain, $origin, current_time( 'mysql' )
		) );
		self::clear_cache();
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE hash=%s', $h ) );
	}

	public static function save_translation( $string_id, $lang, $translation ) {
		global $wpdb;
		if ( '' === trim( (string) $translation ) ) {
			$wpdb->delete( self::table( 'tr' ), array( 'string_id' => $string_id, 'lang' => $lang ) );
		} else {
			$wpdb->replace( self::table( 'tr' ), array( 'string_id' => $string_id, 'lang' => $lang, 'translation' => $translation ) );
		}
	}

	public static function delete( $ids ) {
		global $wpdb;
		$ids = array_map( 'absint', (array) $ids );
		if ( ! $ids ) {
			return;
		}
		$in = implode( ',', $ids );
		$wpdb->query( 'DELETE FROM ' . self::table() . " WHERE id IN ($in)" );
		$wpdb->query( 'DELETE FROM ' . self::table( 'tr' ) . " WHERE string_id IN ($in)" );
		self::clear_cache();
	}
}
