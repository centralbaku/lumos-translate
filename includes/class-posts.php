<?php
defined( 'ABSPATH' ) || exit;

/**
 * Post / page / CPT translations.
 * Each post has meta `_lumos_lang` and `_lumos_group` (shared by all translations).
 */
class Lumos_TR_Posts {

	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_filter( 'option_page_on_front', array( __CLASS__, 'translate_page_option' ) );
		add_filter( 'option_page_for_posts', array( __CLASS__, 'translate_page_option' ) );
		add_action( 'save_post', array( __CLASS__, 'ensure_lang' ), 10, 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_post_lumos_tr_new_translation', array( __CLASS__, 'create_translation' ) );
		add_action( 'admin_init', array( __CLASS__, 'list_columns' ) );
	}

	public static function translatable_types() {
		$types = get_post_types( array( 'show_ui' => true ), 'names' );
		unset( $types['attachment'], $types['wp_block'], $types['wp_navigation'] );
		return apply_filters( 'lumos_tr_post_types', array_values( $types ) );
	}

	public static function get_lang( $post_id ) {
		return get_post_meta( $post_id, '_lumos_lang', true ) ?: Lumos_TR_Languages::default_slug();
	}

	public static function get_group( $post_id ) {
		$g = get_post_meta( $post_id, '_lumos_group', true );
		if ( ! $g ) {
			$g = 'g' . $post_id;
			update_post_meta( $post_id, '_lumos_group', $g );
		}
		return $g;
	}

	/** @return array lang => post_id */
	public static function get_translations( $post_id ) {
		$group = self::get_group( $post_id );
		$ids   = get_posts( array(
			'post_type'        => 'any',
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'meta_key'         => '_lumos_group',
			'meta_value'       => $group,
			'suppress_filters' => true,
			'lumos_no_filter'  => true,
		) );
		$out = array( self::get_lang( $post_id ) => (int) $post_id );
		foreach ( $ids as $id ) {
			$out[ self::get_lang( $id ) ] = (int) $id;
		}
		return $out;
	}

	public static function get_translation( $post_id, $lang ) {
		$t = self::get_translations( $post_id );
		return $t[ $lang ] ?? null;
	}

	public static function ensure_lang( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, self::translatable_types(), true ) ) {
			return;
		}
		if ( ! get_post_meta( $post_id, '_lumos_lang', true ) ) {
			update_post_meta( $post_id, '_lumos_lang', Lumos_TR_Router::current() );
		}
		self::get_group( $post_id );
	}

	/** Show only posts of the current language on the front end. */
	public static function filter_query( $q ) {
		if ( is_admin() && ! wp_doing_ajax() && ! isset( $_GET['lumos_lang'] ) ) {
			return;
		}
		if ( $q->get( 'lumos_no_filter' ) || $q->get( 'p' ) || $q->get( 'page_id' ) || $q->get( 'attachment' ) ) {
			return;
		}
		$lang = Lumos_TR_Router::current();
		$mq   = (array) $q->get( 'meta_query' );
		$cond = array( 'key' => '_lumos_lang', 'value' => $lang );
		if ( $lang === Lumos_TR_Languages::default_slug() ) {
			// Posts created before activation have no language: treat as default.
			$cond = array( 'relation' => 'OR', $cond, array( 'key' => '_lumos_lang', 'compare' => 'NOT EXISTS' ) );
		}
		$mq[] = $cond;
		$q->set( 'meta_query', $mq );
	}

	public static function translate_page_option( $id ) {
		if ( ! $id || is_admin() ) {
			return $id;
		}
		return self::get_translation( $id, Lumos_TR_Router::current() ) ?: $id;
	}

	/* ---------- Admin UI ---------- */

	public static function meta_box() {
		add_meta_box( 'lumos-tr', __( 'Languages', 'lumos-translate' ), array( __CLASS__, 'render_meta_box' ), self::translatable_types(), 'side', 'high' );
	}

	public static function render_meta_box( $post ) {
		$lang  = self::get_lang( $post->ID );
		$trans = self::get_translations( $post->ID );
		echo '<p><strong>' . esc_html__( 'Language:', 'lumos-translate' ) . '</strong> ' . esc_html( Lumos_TR_Languages::get( $lang )['name'] ?? $lang ) . '</p><ul>';
		foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
			if ( $slug === $lang ) {
				continue;
			}
			echo '<li>' . esc_html( $l['flag'] . ' ' . $l['name'] ) . ': ';
			if ( isset( $trans[ $slug ] ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $trans[ $slug ] ) ) . '">' . esc_html__( 'Edit', 'lumos-translate' ) . '</a>';
			} elseif ( 'auto-draft' !== $post->post_status ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=lumos_tr_new_translation&from=' . $post->ID . '&lang=' . $slug ), 'lumos_tr_new_' . $post->ID );
				echo '<a href="' . esc_url( $url ) . '">+ ' . esc_html__( 'Add translation', 'lumos-translate' ) . '</a>';
			} else {
				esc_html_e( 'save first', 'lumos-translate' );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/** Duplicate a post (content, meta, terms) as a draft in another language. */
	public static function create_translation() {
		$from = absint( $_GET['from'] ?? 0 );
		$lang = sanitize_key( $_GET['lang'] ?? '' );
		check_admin_referer( 'lumos_tr_new_' . $from );
		if ( ! current_user_can( 'edit_post', $from ) || ! Lumos_TR_Languages::exists( $lang ) ) {
			wp_die( esc_html__( 'Not allowed.', 'lumos-translate' ) );
		}
		$existing = self::get_translation( $from, $lang );
		if ( $existing ) {
			wp_safe_redirect( get_edit_post_link( $existing, 'url' ) );
			exit;
		}
		$src    = get_post( $from );
		$new_id = wp_insert_post( array(
			'post_type'    => $src->post_type,
			'post_title'   => $src->post_title,
			'post_content' => $src->post_content,
			'post_excerpt' => $src->post_excerpt,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			'menu_order'   => $src->menu_order,
			'post_parent'  => $src->post_parent ? ( self::get_translation( $src->post_parent, $lang ) ?: $src->post_parent ) : 0,
		) );
		foreach ( get_post_meta( $from ) as $key => $values ) {
			if ( in_array( $key, array( '_lumos_lang', '_lumos_group', '_edit_lock', '_edit_last' ), true ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $v ) );
			}
		}
		foreach ( get_object_taxonomies( $src->post_type ) as $tax ) {
			$terms = wp_get_object_terms( $from, $tax, array( 'fields' => 'ids' ) );
			$terms = array_map( function ( $t ) use ( $lang ) {
				return Lumos_TR_Terms::get_translation( $t, $lang ) ?: $t;
			}, $terms );
			wp_set_object_terms( $new_id, $terms, $tax );
		}
		update_post_meta( $new_id, '_lumos_lang', $lang );
		update_post_meta( $new_id, '_lumos_group', self::get_group( $from ) );
		wp_safe_redirect( get_edit_post_link( $new_id, 'url' ) );
		exit;
	}

	public static function list_columns() {
		foreach ( self::translatable_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", function ( $cols ) {
				$cols['lumos_lang'] = __( 'Languages', 'lumos-translate' );
				return $cols;
			} );
			add_action( "manage_{$type}_posts_custom_column", function ( $col, $id ) {
				if ( 'lumos_lang' !== $col ) {
					return;
				}
				$trans = Lumos_TR_Posts::get_translations( $id );
				foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
					$label = esc_html( strtoupper( $slug ) );
					if ( isset( $trans[ $slug ] ) ) {
						$style = (int) $trans[ $slug ] === (int) $id ? 'font-weight:bold' : '';
						echo '<a style="' . esc_attr( $style ) . '" href="' . esc_url( get_edit_post_link( $trans[ $slug ] ) ) . '">' . $label . '</a> ';
					} else {
						$url = wp_nonce_url( admin_url( 'admin-post.php?action=lumos_tr_new_translation&from=' . $id . '&lang=' . $slug ), 'lumos_tr_new_' . $id );
						echo '<a style="opacity:.4" title="' . esc_attr__( 'Add translation', 'lumos-translate' ) . '" href="' . esc_url( $url ) . '">+' . $label . '</a> ';
					}
				}
			}, 10, 2 );
		}
	}
}
