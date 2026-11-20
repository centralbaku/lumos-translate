<?php
defined( 'ABSPATH' ) || exit;

/** Current language slug, e.g. "fr". */
function lumos_tr_current_language() {
	return Lumos_TR_Router::current();
}

/** Print the language switcher. Args: style (list|dropdown), flags, names. */
function lumos_tr_switcher( $args = array() ) {
	echo Lumos_TR_Switcher::render( $args ); // phpcs:ignore
}

/**
 * Translate any hard-coded string in your theme without gettext:
 *   echo lumos_t( 'Free shipping on all orders' );
 * The string is registered automatically and appears in Lumos Translate → Strings.
 */
function lumos_t( $text, $group = 'theme' ) {
	return Lumos_TR_Strings::lookup( $text, '', $group, Lumos_TR_Strings::ORIGIN_MANUAL ) ?? $text;
}

function lumos_e( $text, $group = 'theme' ) {
	echo esc_html( lumos_t( $text, $group ) );
}

/** Get the ID of a post's translation in $lang (defaults to current). */
function lumos_tr_get_post( $post_id, $lang = null ) {
	return Lumos_TR_Posts::get_translation( $post_id, $lang ?: lumos_tr_current_language() );
}

/** Polylang-compatible shims so themes written for Polylang keep working. */
if ( ! function_exists( 'pll_current_language' ) ) {
	function pll_current_language() {
		return lumos_tr_current_language();
	}
	function pll__( $s ) {
		return lumos_t( $s, 'polylang' );
	}
	function pll_e( $s ) {
		echo esc_html( pll__( $s ) );
	}
	function pll_register_string( $name, $string, $group = 'polylang' ) {
		if ( is_admin() ) {
			Lumos_TR_Strings::add( $string, '', $group, Lumos_TR_Strings::ORIGIN_MANUAL );
		}
	}
	function pll_get_post( $id, $lang = '' ) {
		return lumos_tr_get_post( $id, $lang );
	}
	function pll_home_url( $lang = '' ) {
		return Lumos_TR_Router::url_for( home_url( '/' ), $lang ?: lumos_tr_current_language() );
	}
	function pll_languages_list() {
		return Lumos_TR_Languages::slugs();
	}
}
