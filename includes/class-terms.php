<?php
defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy term translations: term meta `_lumos_lang` + `_lumos_group`.
 */
class Lumos_TR_Terms {

	public static function init() {
		add_action( 'created_term', array( __CLASS__, 'on_create' ), 10, 3 );
		add_filter( 'terms_clauses', array( __CLASS__, 'filter_terms' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'admin_fields' ) );
	}

	public static function get_lang( $term_id ) {
		return get_term_meta( $term_id, '_lumos_lang', true ) ?: Lumos_TR_Languages::default_slug();
	}

	public static function get_translation( $term_id, $lang ) {
		$group = get_term_meta( $term_id, '_lumos_group', true );
		if ( ! $group ) {
			return self::get_lang( $term_id ) === $lang ? $term_id : null;
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key='_lumos_group' AND meta_value=%s", $group ) );
		foreach ( $ids as $id ) {
			if ( self::get_lang( $id ) === $lang ) {
				return (int) $id;
			}
		}
		return null;
	}

	public static function on_create( $term_id ) {
		$lang = isset( $_POST['lumos_term_lang'] ) ? sanitize_key( $_POST['lumos_term_lang'] ) : Lumos_TR_Router::current();
		update_term_meta( $term_id, '_lumos_lang', Lumos_TR_Languages::exists( $lang ) ? $lang : Lumos_TR_Languages::default_slug() );
		$from = absint( $_POST['lumos_term_from'] ?? 0 );
		$group = $from ? ( get_term_meta( $from, '_lumos_group', true ) ?: 't' . $from ) : 't' . $term_id;
		if ( $from ) {
			update_term_meta( $from, '_lumos_group', $group );
		}
		update_term_meta( $term_id, '_lumos_group', $group );
	}

	/** Front end: show only terms of the current language. */
	public static function filter_terms( $clauses, $taxonomies, $args ) {
		if ( is_admin() || ! empty( $args['lumos_no_filter'] ) || ! empty( $args['include'] ) || ! empty( $args['object_ids'] ) ) {
			return $clauses;
		}
		global $wpdb;
		$lang = esc_sql( Lumos_TR_Router::current() );
		$clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS lumos_tm ON (t.term_id = lumos_tm.term_id AND lumos_tm.meta_key = '_lumos_lang')";
		$is_default = $lang === Lumos_TR_Languages::default_slug();
		$clauses['where'] .= $is_default ? " AND (lumos_tm.meta_value = '$lang' OR lumos_tm.meta_value IS NULL)" : " AND lumos_tm.meta_value = '$lang'";
		return $clauses;
	}

	public static function admin_fields() {
		foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $tax ) {
			add_action( "{$tax}_add_form_fields", array( __CLASS__, 'add_field' ) );
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'edit_field' ) );
		}
	}

	public static function add_field() {
		echo '<div class="form-field"><label>' . esc_html__( 'Language', 'lumos-translate' ) . '</label><select name="lumos_term_lang">';
		foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
			printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $l['name'] ) );
		}
		echo '</select><p>' . esc_html__( 'Translation of term ID (optional):', 'lumos-translate' ) . ' <input type="number" name="lumos_term_from" style="width:90px"></p></div>';
	}

	public static function edit_field( $term ) {
		echo '<tr class="form-field"><th>' . esc_html__( 'Translations', 'lumos-translate' ) . '</th><td>';
		foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
			$t = self::get_translation( $term->term_id, $slug );
			echo esc_html( $l['name'] ) . ': ';
			echo $t ? '<a href="' . esc_url( get_edit_term_link( $t, $term->taxonomy ) ) . '">#' . (int) $t . '</a>' : '—';
			echo '<br>';
		}
		echo '<p class="description">' . esc_html__( 'Create a new term with "Translation of term ID" set to link it here.', 'lumos-translate' ) . '</p></td></tr>';
	}
}
