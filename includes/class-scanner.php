<?php
defined( 'ABSPATH' ) || exit;

/**
 * Scans theme (and optionally plugin) files and registers every translatable
 * string, including text that was never wrapped in __():
 *  - gettext calls: __('x','d'), _e(), esc_html__(), esc_attr_e(), _x(), _n() ...
 *  - literal HTML text between tags outside of <?php ?> blocks
 *  - literal text inside echo '...<b>Text</b>...'
 *  - translatable attributes (placeholder, title, alt, aria-label)
 *  - block theme templates / patterns (.html)
 */
class Lumos_TR_Scanner {

	const GETTEXT_RE = '#\b(__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x|_ex|esc_html_x|esc_attr_x|_n|_nx)\s*\(\s*([\'"])((?:\\\\.|(?!\2).)*)\2\s*(?:,\s*([\'"])((?:\\\\.|(?!\4).)*)\4)?\s*(?:,\s*([\'"])((?:\\\\.|(?!\6).)*)\6)?#s';

	/** @return array{gettext:int,literal:int,files:int} */
	public static function scan_dir( $dir, $default_domain = '' ) {
		$stats = array( 'gettext' => 0, 'literal' => 0, 'files' => 0 );
		if ( ! is_dir( $dir ) ) {
			return $stats;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$path = $file->getPathname();
			if ( preg_match( '#/(node_modules|vendor|\.git)/#', $path ) || ! preg_match( '/\.(php|html)$/i', $path ) || $file->getSize() > 1048576 ) {
				continue;
			}
			$code = file_get_contents( $path );
			$stats['files']++;
			$stats['gettext'] += self::scan_gettext( $code, $default_domain );
			$stats['literal'] += self::scan_literals( $code, '.html' === substr( strtolower( $path ), -5 ) );
		}
		return $stats;
	}

	public static function scan_gettext( $code, $default_domain ) {
		$n = 0;
		if ( ! preg_match_all( self::GETTEXT_RE, $code, $all, PREG_SET_ORDER ) ) {
			return 0;
		}
		foreach ( $all as $m ) {
			$fn   = $m[1];
			$text = stripcslashes( $m[3] );
			$a2   = isset( $m[5] ) ? stripcslashes( $m[5] ) : '';
			$a3   = isset( $m[7] ) ? stripcslashes( $m[7] ) : '';
			$ctx  = '';
			$dom  = $a2;
			if ( in_array( $fn, array( '_x', '_ex', 'esc_html_x', 'esc_attr_x' ), true ) ) {
				$ctx = $a2;
				$dom = $a3;
			} elseif ( in_array( $fn, array( '_n', '_nx' ), true ) ) {
				// Register both forms; domain comes later in the call and is not captured reliably.
				Lumos_TR_Strings::add( $a2, '', $default_domain, Lumos_TR_Strings::ORIGIN_GETTEXT );
				$dom = $default_domain;
			}
			Lumos_TR_Strings::add( $text, $ctx, $dom ?: $default_domain, Lumos_TR_Strings::ORIGIN_GETTEXT );
			$n++;
		}
		return $n;
	}

	public static function scan_literals( $code, $is_html ) {
		$chunks = array();
		if ( $is_html ) {
			$chunks[] = preg_replace( '#<!--.*?-->#s', ' ', $code ); // Block comments.
		} else {
			// HTML outside PHP tags.
			$chunks[] = preg_replace( '#<\?(php|=)?.*?(\?>|$)#s', ' ', $code );
			// HTML inside quoted PHP strings that contain tags.
			if ( preg_match_all( '#([\'"])((?:\\\\.|(?!\1).)*<[a-z/][^\'"]*)\1#is', $code, $q ) ) {
				foreach ( $q[2] as $s ) {
					$chunks[] = stripcslashes( $s );
				}
			}
		}
		$n = 0;
		foreach ( $chunks as $html ) {
			$html = preg_replace( '#<(script|style)\b.*?</\1>#is', ' ', $html );
			preg_match_all( '#>([^<>]+)<#u', $html, $t );
			preg_match_all( '#\b(?:placeholder|title|alt|aria-label)=["\']([^"\'<>$]+)["\']#iu', $html, $at );
			foreach ( array_merge( $t[1], $at[1] ) as $txt ) {
				$txt = trim( html_entity_decode( $txt, ENT_QUOTES, 'UTF-8' ) );
				if ( self::is_human_text( $txt ) ) {
					Lumos_TR_Strings::add( $txt, '', 'literal', Lumos_TR_Strings::ORIGIN_LITERAL );
					$n++;
				}
			}
		}
		return $n;
	}

	protected static function is_human_text( $s ) {
		if ( mb_strlen( $s ) < 2 || mb_strlen( $s ) > 500 ) {
			return false;
		}
		if ( ! preg_match( '/\p{L}{2,}/u', $s ) ) {
			return false; // Needs at least one real word.
		}
		if ( preg_match( '/[{}$;]|=>|\(\)|^\w+\(|^[\w.-]+\.(php|js|css)$/', $s ) ) {
			return false; // Looks like code.
		}
		return true;
	}

	public static function scan_active_theme() {
		$theme  = wp_get_theme();
		$stats  = self::scan_dir( get_stylesheet_directory(), $theme->get( 'TextDomain' ) );
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$parent = self::scan_dir( get_template_directory(), $theme->parent() ? $theme->parent()->get( 'TextDomain' ) : '' );
			foreach ( $stats as $k => $v ) {
				$stats[ $k ] += $parent[ $k ];
			}
		}
		return $stats;
	}
}
