<?php
defined( 'ABSPATH' ) || exit;

/**
 * Language switcher: shortcode [lumos_switcher], widget, template tag lumos_tr_switcher().
 */
class Lumos_TR_Switcher {

	public static function init() {
		add_shortcode( 'lumos_switcher', array( __CLASS__, 'shortcode' ) );
		add_action( 'widgets_init', function () {
			register_widget( 'Lumos_TR_Switcher_Widget' );
		} );
	}

	/** @return array slug => [url, name, flag, locale, current] */
	public static function links() {
		$out     = array();
		$current = Lumos_TR_Router::current();
		$obj     = get_queried_object();
		foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
			$url = null;
			if ( $obj instanceof WP_Post ) {
				$t = Lumos_TR_Posts::get_translation( $obj->ID, $slug );
				if ( $t && 'publish' === get_post_status( $t ) ) {
					$url = get_permalink( $t );
				} elseif ( ! get_option( 'lumos_tr_hide_missing' ) ) {
					$url = Lumos_TR_Router::url_for( home_url( '/' ), $slug );
				}
			} elseif ( $obj instanceof WP_Term ) {
				$t   = Lumos_TR_Terms::get_translation( $obj->term_id, $slug );
				$url = $t ? get_term_link( (int) $t ) : Lumos_TR_Router::url_for( home_url( '/' ), $slug );
			} else {
				$scheme = is_ssl() ? 'https://' : 'http://';
				$here   = $scheme . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' );
				$url    = Lumos_TR_Router::url_for( $here, $slug );
			}
			if ( $url ) {
				$out[ $slug ] = array(
					'url'     => Lumos_TR_Router::url_for( $url, $slug ),
					'name'    => $l['name'],
					'flag'    => $l['flag'],
					'locale'  => $l['locale'],
					'current' => $slug === $current,
				);
			}
		}
		return $out;
	}

	public static function render( $args = array() ) {
		$args = wp_parse_args( $args, array( 'style' => 'list', 'flags' => 1, 'names' => 1 ) );
		$links = self::links();
		if ( 'dropdown' === $args['style'] ) {
			$html = '<select class="lumos-switcher" onchange="location.href=this.value">';
			foreach ( $links as $slug => $l ) {
				$html .= sprintf( '<option value="%s"%s>%s</option>', esc_url( $l['url'] ), selected( $l['current'], true, false ), esc_html( trim( ( $args['flags'] ? $l['flag'] . ' ' : '' ) . ( $args['names'] ? $l['name'] : strtoupper( $slug ) ) ) ) );
			}
			return $html . '</select>';
		}
		$html = '<ul class="lumos-switcher">';
		foreach ( $links as $slug => $l ) {
			$label = trim( ( $args['flags'] ? $l['flag'] . ' ' : '' ) . ( $args['names'] ? $l['name'] : strtoupper( $slug ) ) );
			$html .= sprintf( '<li class="lang-%s%s"><a href="%s" hreflang="%s">%s</a></li>', esc_attr( $slug ), $l['current'] ? ' current-lang' : '', esc_url( $l['url'] ), esc_attr( $slug ), esc_html( $label ) );
		}
		return $html . '</ul>';
	}

	public static function shortcode( $atts ) {
		return self::render( shortcode_atts( array( 'style' => 'list', 'flags' => 1, 'names' => 1 ), $atts ) );
	}
}

class Lumos_TR_Switcher_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'lumos_switcher', __( 'Lumos Language Switcher', 'lumos-translate' ) );
	}
	public function widget( $args, $instance ) {
		echo $args['before_widget'] . Lumos_TR_Switcher::render( array( 'style' => $instance['style'] ?? 'list' ) ) . $args['after_widget']; // phpcs:ignore
	}
	public function form( $instance ) {
		$s = $instance['style'] ?? 'list';
		printf( '<p><select name="%s"><option value="list"%s>List</option><option value="dropdown"%s>Dropdown</option></select></p>', esc_attr( $this->get_field_name( 'style' ) ), selected( $s, 'list', false ), selected( $s, 'dropdown', false ) );
	}
	public function update( $new, $old ) {
		return array( 'style' => 'dropdown' === ( $new['style'] ?? '' ) ? 'dropdown' : 'list' );
	}
}
