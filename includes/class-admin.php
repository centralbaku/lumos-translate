<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin: Lumos Translate menu with Languages, Strings, Scanner and Settings tabs.
 */
class Lumos_TR_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'lumos-translate';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_lumos_tr_save_languages', array( __CLASS__, 'save_languages' ) );
		add_action( 'admin_post_lumos_tr_save_strings', array( __CLASS__, 'save_strings' ) );
		add_action( 'admin_post_lumos_tr_scan', array( __CLASS__, 'scan' ) );
		add_action( 'admin_post_lumos_tr_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_lumos_tr_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_lumos_tr_import', array( __CLASS__, 'import' ) );
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( false !== strpos( $hook, self::SLUG ) ) {
				wp_enqueue_style( 'lumos-tr-admin', LUMOS_TR_URL . 'assets/admin.css', array(), LUMOS_TR_VERSION );
			}
		} );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filter' ) );
	}

	public static function menu() {
		// Listed as "Translate" under the shared LUMOS menu.
		lumos_menu_add_product(
			self::SLUG,
			__( 'Translate', 'lumos-translate' ),
			array( __CLASS__, 'page' ),
			__( 'Translate pages, menus and theme strings.', 'lumos-translate' ),
			self::CAP,
			'dashicons-translation'
		);
	}

	protected static function url( $tab, $extra = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $extra ), admin_url( 'admin.php' ) );
	}

	protected static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'lumos-translate' ) );
		}
		check_admin_referer( $action );
	}

	public static function list_filter() {
		$cur = isset( $_GET['lumos_lang'] ) ? sanitize_key( $_GET['lumos_lang'] ) : '';
		echo '<select name="lumos_lang"><option value="">' . esc_html__( 'All languages', 'lumos-translate' ) . '</option>';
		foreach ( Lumos_TR_Languages::all() as $slug => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $cur, $slug, false ), esc_html( $l['name'] ) );
		}
		echo '</select>';
	}

	public static function page() {
		$tab  = sanitize_key( $_GET['tab'] ?? 'languages' );
		$tabs = array(
			'languages' => __( 'Languages', 'lumos-translate' ),
			'strings'   => __( 'Strings', 'lumos-translate' ),
			'scanner'   => __( 'Theme Scanner', 'lumos-translate' ),
			'settings'  => __( 'Settings', 'lumos-translate' ),
		);
		echo '<div class="wrap lumos-tr"><h1>Lumos Translate</h1>';
		if ( ! empty( $_GET['msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( wp_unslash( $_GET['msg'] ) ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a class="nav-tab%s" href="%s">%s</a>', $k === $tab ? ' nav-tab-active' : '', esc_url( self::url( $k ) ), esc_html( $label ) );
		}
		echo '</nav>';
		$method = 'tab_' . ( isset( $tabs[ $tab ] ) ? $tab : 'languages' );
		self::$method();
		echo '</div>';
	}

	/* ---------- Languages ---------- */

	protected static function tab_languages() {
		$langs   = Lumos_TR_Languages::all();
		$default = Lumos_TR_Languages::default_slug();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lumos_tr_save_languages">
			<?php wp_nonce_field( 'lumos_tr_save_languages' ); ?>
			<table class="widefat striped">
				<thead><tr><th>Default</th><th>Slug (URL)</th><th>Name</th><th>Locale</th><th>Flag/Emoji</th><th>RTL</th><th>Remove</th></tr></thead>
				<tbody>
				<?php
				$i = 0;
				foreach ( $langs + array( '' => array( 'name' => '', 'locale' => '', 'flag' => '', 'rtl' => 0 ) ) as $slug => $l ) :
					?>
					<tr>
						<td><input type="radio" name="default" value="<?php echo (int) $i; ?>" <?php checked( $slug, $default ); ?>></td>
						<td><input name="l[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" size="5" placeholder="<?php echo $slug ? '' : 'fr'; ?>"></td>
						<td><input name="l[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $l['name'] ); ?>" placeholder="Français"></td>
						<td><input name="l[<?php echo (int) $i; ?>][locale]" value="<?php echo esc_attr( $l['locale'] ); ?>" size="8" placeholder="fr_FR"></td>
						<td><input name="l[<?php echo (int) $i; ?>][flag]" value="<?php echo esc_attr( $l['flag'] ); ?>" size="4"></td>
						<td><input type="checkbox" name="l[<?php echo (int) $i; ?>][rtl]" value="1" <?php checked( $l['rtl'] ); ?>></td>
						<td><?php if ( $slug ) : ?><input type="checkbox" name="l[<?php echo (int) $i; ?>][remove]" value="1"><?php endif; ?></td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
				</tbody>
			</table>
			<p class="description">Leave the last row empty, or fill it to add a language. The default language has no URL prefix; others use <code>/slug/</code>.</p>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	public static function save_languages() {
		self::guard( 'lumos_tr_save_languages' );
		$rows   = (array) ( $_POST['l'] ?? array() );
		$def_i  = (int) ( $_POST['default'] ?? 0 );
		$langs  = array();
		$def    = '';
		foreach ( $rows as $i => $r ) {
			$slug = sanitize_key( wp_unslash( $r['slug'] ?? '' ) );
			if ( ! $slug || ! empty( $r['remove'] ) ) {
				continue;
			}
			$langs[ $slug ] = wp_unslash( $r );
			if ( (int) $i === $def_i ) {
				$def = $slug;
			}
		}
		Lumos_TR_Languages::save( $langs );
		if ( $def ) {
			update_option( Lumos_TR_Languages::DEFAULT, $def );
		}
		Lumos_TR_Strings::clear_cache();
		flush_rewrite_rules();
		wp_safe_redirect( self::url( 'languages', array( 'msg' => 'Languages saved.' ) ) );
		exit;
	}

	/* ---------- Strings ---------- */

	protected static function tab_strings() {
		global $wpdb;
		$langs   = Lumos_TR_Languages::all();
		$search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$domain  = sanitize_text_field( wp_unslash( $_GET['domain'] ?? '' ) );
		$origin  = sanitize_key( $_GET['origin'] ?? '' );
		$untr    = ! empty( $_GET['untranslated'] );
		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per     = 50;
		$st      = Lumos_TR_Strings::table();
		$tt      = Lumos_TR_Strings::table( 'tr' );

		$where = array( '1=1' );
		$args  = array();
		if ( $search ) {
			$where[] = "(s.source LIKE %s OR EXISTS (SELECT 1 FROM $tt x WHERE x.string_id=s.id AND x.translation LIKE %s))";
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}
		if ( $domain ) {
			$where[] = 's.domain = %s';
			$args[]  = $domain;
		}
		if ( $origin ) {
			$where[] = 's.origin = %s';
			$args[]  = $origin;
		}
		if ( $untr ) {
			$where[] = "(SELECT COUNT(*) FROM $tt x WHERE x.string_id=s.id) < %d";
			$args[]  = max( 1, count( $langs ) - 1 );
		}
		$w     = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM $st s WHERE $w", $args ) : "SELECT COUNT(*) FROM $st s WHERE $w" );
		$q     = "SELECT s.* FROM $st s WHERE $w ORDER BY s.domain, s.id LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $wpdb->prepare( $q, array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) );
		$trans = array();
		if ( $rows ) {
			$ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
			foreach ( $wpdb->get_results( "SELECT * FROM $tt WHERE string_id IN ($ids)" ) as $t ) {
				$trans[ $t->string_id ][ $t->lang ] = $t->translation;
			}
		}
		$domains = $wpdb->get_col( "SELECT DISTINCT domain FROM $st ORDER BY domain" );
		?>
		<form method="get" class="lumos-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<input type="hidden" name="tab" value="strings">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search text…">
			<select name="domain"><option value="">All groups / text domains</option>
				<?php foreach ( $domains as $d ) : ?>
					<option <?php selected( $domain, $d ); ?>><?php echo esc_html( $d ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="origin"><option value="">All types</option>
				<?php foreach ( array( 'gettext' => 'Theme/plugin gettext', 'literal' => 'Hard-coded text', 'option' => 'Options / menus / widgets', 'manual' => 'Manual / lumos_t()' ) as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $origin, $k ); ?>><?php echo esc_html( $v ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><input type="checkbox" name="untranslated" value="1" <?php checked( $untr ); ?>> Untranslated only</label>
			<button class="button">Filter</button>
			<span class="lumos-count"><?php echo (int) $total; ?> strings</span>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lumos_tr_save_strings">
			<input type="hidden" name="back" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); ?>">
			<?php wp_nonce_field( 'lumos_tr_save_strings' ); ?>
			<table class="widefat striped lumos-strings">
				<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.lumos-del').forEach(c=>c.checked=this.checked)"></td><th>Original</th>
				<?php foreach ( $langs as $slug => $l ) : ?>
					<th><?php echo esc_html( $l['flag'] . ' ' . $l['name'] ); ?></th>
				<?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<th class="check-column"><input type="checkbox" class="lumos-del" name="delete[]" value="<?php echo (int) $r->id; ?>"></th>
						<td class="lumos-src"><div><?php echo esc_html( $r->source ); ?></div>
							<small><?php echo esc_html( $r->origin . ' · ' . ( $r->domain ?: 'default' ) . ( $r->context ? ' · ctx: ' . $r->context : '' ) ); ?></small></td>
						<?php foreach ( $langs as $slug => $l ) : ?>
							<td><textarea rows="2" name="t[<?php echo (int) $r->id; ?>][<?php echo esc_attr( $slug ); ?>]" dir="<?php echo $l['rtl'] ? 'rtl' : 'ltr'; ?>"><?php echo esc_textarea( $trans[ $r->id ][ $slug ] ?? '' ); ?></textarea></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="<?php echo count( $langs ) + 2; ?>">No strings yet. Browse your site's front end (strings are discovered automatically) or run the Theme Scanner.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<p>
				<?php submit_button( 'Save translations', 'primary', 'submit', false ); ?>
				<button class="button" name="do_delete" value="1" onclick="return confirm('Delete selected strings and their translations?')">Delete selected</button>
			</p>
			<p><strong>Add a custom string</strong> (any exact text visible on your site):
				<input name="new_source" class="regular-text" placeholder="e.g. Free shipping on all orders">
			</p>
		</form>
		<?php
		echo wp_kses_post( paginate_links( array(
			'base'    => add_query_arg( 'paged', '%#%' ),
			'current' => $paged,
			'total'   => max( 1, (int) ceil( $total / $per ) ),
		) ) ?: '' );
		?>
		<hr>
		<h2>Import / Export</h2>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lumos_tr_export' ), 'lumos_tr_export' ) ); ?>">Export all strings (JSON)</a></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lumos_tr_import">
			<?php wp_nonce_field( 'lumos_tr_import' ); ?>
			<input type="file" name="file" accept=".json"> <button class="button">Import JSON</button>
		</form>
		<?php
	}

	public static function save_strings() {
		self::guard( 'lumos_tr_save_strings' );
		if ( ! empty( $_POST['do_delete'] ) ) {
			Lumos_TR_Strings::delete( array_map( 'absint', (array) ( $_POST['delete'] ?? array() ) ) );
		} else {
			foreach ( (array) ( $_POST['t'] ?? array() ) as $id => $per_lang ) {
				foreach ( (array) $per_lang as $lang => $val ) {
					$lang = sanitize_key( $lang );
					if ( Lumos_TR_Languages::exists( $lang ) ) {
						Lumos_TR_Strings::save_translation( absint( $id ), $lang, wp_kses_post( wp_unslash( $val ) ) );
					}
				}
			}
			$new = sanitize_text_field( wp_unslash( $_POST['new_source'] ?? '' ) );
			if ( $new ) {
				Lumos_TR_Strings::add( $new, '', 'custom', Lumos_TR_Strings::ORIGIN_MANUAL );
			}
		}
		Lumos_TR_Strings::clear_cache();
		$back = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ?? '' ) ), self::url( 'strings' ) );
		wp_safe_redirect( add_query_arg( 'msg', rawurlencode( 'Saved.' ), $back ) );
		exit;
	}

	public static function export() {
		self::guard( 'lumos_tr_export' );
		global $wpdb;
		$out = array();
		$tt  = Lumos_TR_Strings::table( 'tr' );
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . Lumos_TR_Strings::table(), ARRAY_A ) as $s ) {
			$s['translations'] = $wpdb->get_results( $wpdb->prepare( "SELECT lang, translation FROM $tt WHERE string_id=%d", $s['id'] ), OBJECT_K );
			$s['translations'] = wp_list_pluck( $s['translations'], 'translation' );
			unset( $s['id'], $s['hash'], $s['last_seen'] );
			$out[] = $s;
		}
		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename=lumos-translate-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function import() {
		self::guard( 'lumos_tr_import' );
		$data = json_decode( (string) file_get_contents( $_FILES['file']['tmp_name'] ?? '' ), true );
		$n    = 0;
		foreach ( (array) $data as $s ) {
			if ( empty( $s['source'] ) ) {
				continue;
			}
			$id = Lumos_TR_Strings::add( $s['source'], $s['context'] ?? '', $s['domain'] ?? '', $s['origin'] ?? 'manual' );
			foreach ( (array) ( $s['translations'] ?? array() ) as $lang => $t ) {
				if ( Lumos_TR_Languages::exists( $lang ) ) {
					Lumos_TR_Strings::save_translation( $id, $lang, wp_kses_post( $t ) );
				}
			}
			$n++;
		}
		Lumos_TR_Strings::clear_cache();
		wp_safe_redirect( self::url( 'strings', array( 'msg' => "Imported $n strings." ) ) );
		exit;
	}

	/* ---------- Scanner ---------- */

	protected static function tab_scanner() {
		$plugins = get_plugins();
		?>
		<p>Scan theme / plugin source files and register every string, including <strong>hard-coded text</strong> that was never wrapped in <code>__()</code>. Translate them afterwards in the <a href="<?php echo esc_url( self::url( 'strings' ) ); ?>">Strings</a> tab.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lumos_tr_scan">
			<?php wp_nonce_field( 'lumos_tr_scan' ); ?>
			<p><label><input type="checkbox" name="theme" value="1" checked> Active theme: <strong><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></strong> (and parent)</label></p>
			<p><strong>Plugins:</strong></p>
			<?php foreach ( $plugins as $file => $p ) : if ( 0 === strpos( $file, 'lumos-translate' ) ) { continue; } ?>
				<label style="display:block"><input type="checkbox" name="plugins[]" value="<?php echo esc_attr( $file ); ?>"> <?php echo esc_html( $p['Name'] ); ?></label>
			<?php endforeach; ?>
			<?php submit_button( 'Scan now' ); ?>
		</form>
		<?php
	}

	public static function scan() {
		self::guard( 'lumos_tr_scan' );
		@set_time_limit( 300 ); // phpcs:ignore
		$tot = array( 'gettext' => 0, 'literal' => 0, 'files' => 0 );
		$add = function ( $s ) use ( &$tot ) {
			foreach ( $s as $k => $v ) {
				$tot[ $k ] += $v;
			}
		};
		if ( ! empty( $_POST['theme'] ) ) {
			$add( Lumos_TR_Scanner::scan_active_theme() );
		}
		$all = get_plugins();
		foreach ( (array) ( $_POST['plugins'] ?? array() ) as $file ) {
			$file = sanitize_text_field( wp_unslash( $file ) );
			if ( isset( $all[ $file ] ) && false !== strpos( $file, '/' ) ) {
				$add( Lumos_TR_Scanner::scan_dir( WP_PLUGIN_DIR . '/' . dirname( $file ), $all[ $file ]['TextDomain'] ) );
			}
		}
		Lumos_TR_Strings::clear_cache();
		$msg = sprintf( 'Scanned %d files: %d gettext strings, %d hard-coded strings.', $tot['files'], $tot['gettext'], $tot['literal'] );
		wp_safe_redirect( self::url( 'strings', array( 'msg' => $msg ) ) );
		exit;
	}

	/* ---------- Settings ---------- */

	protected static function tab_settings() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lumos_tr_save_settings">
			<?php wp_nonce_field( 'lumos_tr_save_settings' ); ?>
			<table class="form-table">
				<tr><th>Automatic string discovery</th><td><label><input type="checkbox" name="discovery" value="1" <?php checked( get_option( 'lumos_tr_discovery', 1 ) ); ?>> Record every gettext / option string rendered on the front end</label><p class="description">Turn off once your string list is complete to save DB writes.</p></td></tr>
				<tr><th>Hard-coded text replacement</th><td><label><input type="checkbox" name="literal_mode" value="1" <?php checked( get_option( 'lumos_tr_literal_mode', 1 ) ); ?>> Replace hard-coded / literal text in the final HTML output</label></td></tr>
				<tr><th>Hide missing translations in switcher</th><td><input type="checkbox" name="hide_missing" value="1" <?php checked( get_option( 'lumos_tr_hide_missing' ) ); ?>></td></tr>
				<tr><th>Translatable options</th><td><textarea name="options" rows="4" class="large-text code" placeholder="one option name per line, e.g. woocommerce_demo_store_notice"><?php echo esc_textarea( get_option( 'lumos_tr_options', '' ) ); ?></textarea><p class="description">Site title &amp; tagline are always translatable. Array options are translated recursively.</p></td></tr>
				<tr><th>Translatable theme mods (Customizer)</th><td><textarea name="theme_mods" rows="4" class="large-text code" placeholder="one theme_mod key per line, e.g. footer_copyright"><?php echo esc_textarea( get_option( 'lumos_tr_theme_mods', '' ) ); ?></textarea></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2>Usage</h2>
		<ul class="lumos-usage">
			<li>Switcher: shortcode <code>[lumos_switcher style="dropdown"]</code>, widget “Lumos Language Switcher”, or <code>&lt;?php lumos_tr_switcher(); ?&gt;</code></li>
			<li>Hard-coded PHP string: <code>&lt;?php lumos_e( 'Call us today' ); ?&gt;</code> or <code>echo lumos_t( '...' )</code></li>
			<li>Menus: Appearance → Menus → assign a menu to each “Location (Language)”.</li>
			<li>Polylang functions (<code>pll__</code>, <code>pll_e</code>, <code>pll_current_language</code>, <code>pll_get_post</code>…) are supported.</li>
		</ul>
		<?php
	}

	public static function save_settings() {
		self::guard( 'lumos_tr_save_settings' );
		update_option( 'lumos_tr_discovery', empty( $_POST['discovery'] ) ? 0 : 1 );
		update_option( 'lumos_tr_literal_mode', empty( $_POST['literal_mode'] ) ? 0 : 1 );
		update_option( 'lumos_tr_hide_missing', empty( $_POST['hide_missing'] ) ? 0 : 1 );
		update_option( 'lumos_tr_options', sanitize_textarea_field( wp_unslash( $_POST['options'] ?? '' ) ) );
		update_option( 'lumos_tr_theme_mods', sanitize_textarea_field( wp_unslash( $_POST['theme_mods'] ?? '' ) ) );
		wp_safe_redirect( self::url( 'settings', array( 'msg' => 'Settings saved.' ) ) );
		exit;
	}
}
