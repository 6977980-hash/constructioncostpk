<?php
/**
 * v1.9.0 — live page content.
 *
 * Up to v1.8.0 every theme page was copied into the database once, when it
 * was created, so every number in it (quick answers, tables, alt texts) froze
 * at the rates of that day. Now the post content of a theme page is just
 * [hcc_page key="<slug>"], and this shortcode renders the page's template
 * (hcc_page_definitions() / hcc_v18_page_definitions()) on every view, so
 * every figure comes from the calculator engine and today's default rates.
 *
 * The v1.9.0 migration only switches a page to [hcc_page] when its stored
 * content still matches the theme's own template (ignoring numbers). A page
 * the owner edited by hand is left alone and listed under
 * Tools → ConstructionCostPK SEO, where it can be switched manually.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Theme pages whose content carries calculator figures. */
function hcc_live_page_slugs() {
	return array(
		'grey-structure-cost-calculator', 'construction-cost-guides', 'marla-kanal-standards-pakistan',
		'3-marla-house-construction-cost', '4-marla-house-construction-cost', '5-marla-house-construction-cost',
		'7-marla-house-construction-cost', '10-marla-house-construction-cost', '1-kanal-house-construction-cost',
		'construction-cost-lahore', 'construction-cost-karachi', 'construction-cost-islamabad', 'construction-cost-by-city',
		'construction-material-rates', 'cement-rate-today-pakistan', 'steel-saria-rate-today',
		'pakistan-construction-cost-index', 'methodology', 'embed-construction-cost-calculator',
		'6-marla-house-construction-cost', '8-marla-house-construction-cost', '2-kanal-house-construction-cost',
		'double-storey-house-construction-cost', '120-sq-yard-house-construction-cost-karachi', 'lanter-roof-slab-cost-pakistan',
	);
}

/** slug => template HTML (shortcodes not yet run). */
function hcc_live_page_templates() {
	static $cache = null;
	if ( null !== $cache ) return $cache;
	$all = array();
	foreach ( hcc_page_definitions() as $def ) $all[ $def['slug'] ] = $def['content'];
	foreach ( hcc_v18_page_definitions() as $slug => $def ) $all[ $slug ] = $def['content'];
	foreach ( hcc_v110_page_definitions() as $slug => $def ) $all[ $slug ] = $def['content'];
	$cache = array_intersect_key( $all, array_flip( hcc_live_page_slugs() ) );
	return $cache;
}

function hcc_live_page_shortcode_text( $slug ) {
	return '[hcc_page key="' . $slug . '"]';
}

/**
 * [hcc_page key="slug"] — runs the same filters WordPress runs on stored
 * page content (texturize, autop, shortcodes), so the output is identical
 * to the old stored page apart from the now-live numbers.
 */
function hcc_sc_page( $atts ) {
	static $depth = 0;
	$a = shortcode_atts( array( 'key' => '' ), $atts, 'hcc_page' );
	$templates = hcc_live_page_templates();
	if ( $depth > 0 || ! isset( $templates[ $a['key'] ] ) ) return '';
	$depth++;
	$html = do_shortcode( shortcode_unautop( wpautop( wptexturize( $templates[ $a['key'] ] ) ) ) );
	$depth--;
	return $html;
}
add_shortcode( 'hcc_page', 'hcc_sc_page' );

/** True when a post shows the calculator, directly or through [hcc_page]. */
function hcc_post_has_calculator( $post ) {
	$content = (string) $post->post_content;
	if ( has_shortcode( $content, 'house_cost_calculator' ) ) return true;
	if ( preg_match( '/\[hcc_page\s+key="([a-z0-9-]+)"/', $content, $m ) ) {
		$t = hcc_live_page_templates();
		return isset( $t[ $m[1] ] ) && false !== strpos( $t[ $m[1] ], '[house_cost_calculator' );
	}
	return false;
}

/**
 * Fingerprint of page text that keeps only its letters, so "the theme's
 * template at September's rates" and "at October's rates" (and the same
 * text after WordPress turned quotes into entities) compare equal, while
 * any wording the owner changed does not.
 */
function hcc_content_fingerprint( $text ) {
	$t = strtolower( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
	$t = preg_replace( '/\b(january|february|march|april|may|june|july|august|september|october|november|december)\b/', '', $t );
	$t = str_replace( 'crore', 'lakh', $t );
	$t = preg_replace( '/[^a-z]+/', '', $t );
	return md5( $t );
}

/**
 * Fingerprints of the v1.8.0 templates as the theme inserted them
 * (generated from the v1.8.0 code). The plot pages also list the older
 * wording of their Marla-standard note, which is what the live site has
 * because those pages were created before v1.8.0. A stored page whose
 * fingerprint is listed here was never edited by hand.
 */
function hcc_v18_content_fingerprints() {
	return array(
		'grey-structure-cost-calculator' => array( '6902a80e094b403187243410d85d4c26' ),
		'construction-cost-guides' => array( '156420afc355137b9703f68e7d9c2ce4' ),
		'marla-kanal-standards-pakistan' => array( 'e0466ec8e2bf5ea63a36f51ea245d3e5' ),
		'3-marla-house-construction-cost' => array( '5b3604428a0f2bb900d5a86a6f492308' ),
		'4-marla-house-construction-cost' => array( '4b9f33f9d6a81574a179e84fa2d07b7f' ),
		'5-marla-house-construction-cost' => array( '6e51786e4745a52f8b764e521b4404a0', '4c25f5dc6cf5b05cd0ce242fab92d4b4' ),
		'7-marla-house-construction-cost' => array( 'ea2e68e942e0cdbabe5529145a21d8d4', '04693ab77b96423a6460cb7b61bc605d' ),
		'10-marla-house-construction-cost' => array( 'd7a546e5fa6bb729e005d61b8104d441', '813d2ae9b3cf16171a83aa841fb34e79' ),
		'1-kanal-house-construction-cost' => array( '22d3a9c09da932e5fa328ea8e49fcacb', 'd54a552c3a3bff468429651b470b28a5' ),
		'construction-cost-lahore' => array( '0fa211c14c5838710217246afb294150' ),
		'construction-cost-karachi' => array( 'f364c8c6c165438c0c30297070d9dbae' ),
		'construction-cost-islamabad' => array( 'b7dde46fd8b5bf2835abb77fe61856ad' ),
		'construction-cost-by-city' => array( '6f904b54a011b1c149585600f2baf7e1' ),
		'construction-material-rates' => array( '2870d9adf8ca6ce274b863ea9eb51d7e' ),
		'cement-rate-today-pakistan' => array( '750394f1ed7e65a4b8c581faa1d0a260' ),
		'steel-saria-rate-today' => array( 'a7cd268c302ccbc5c19c2482a7f6fc95' ),
		'pakistan-construction-cost-index' => array( 'e499427f49fa54d4fe3e274b53b584ae' ),
		'methodology' => array( '7997750dbef19f4da9367dd00118c197' ),
		'embed-construction-cost-calculator' => array( '441e896447845c85e5d7cfb9206810dc' ),
	);
}

/** Fingerprints of the Rank Math title / description v1.8.0 wrote (theme defaults with frozen numbers). */
function hcc_v18_meta_fingerprints() {
	return array(
		'home' => array( '0233689914be8e6a9a0e93e6d29f40ce', 'ed2fd0356b92fb9924b15af9a53ace63' ),
		'grey-structure-cost-calculator' => array( '78d724aaf5b9c03ed713a2acd4bce258', '2efbf515677a102be216cd748f29e908' ),
		'5-marla-house-construction-cost' => array( '919109621ed1460de974a71fe10c69ad', 'b20db0d7a04740ac771cca672113c716' ),
		'7-marla-house-construction-cost' => array( '919109621ed1460de974a71fe10c69ad', 'b20db0d7a04740ac771cca672113c716' ),
		'10-marla-house-construction-cost' => array( 'e876bfd494b4df800e2d3b214e9095ac', 'bd0049717dec1f393a0415eac12ddd30' ),
		'1-kanal-house-construction-cost' => array( '9652d41cb8cc782641a186c7920f3bfb', '748127e120952a23c70afdb11817ab08' ),
		'marla-kanal-standards-pakistan' => array( '33be86a3bd46e69b8a5cb4a98adac2da', '67cdab6637131864ee4bf1ddeaf2365f' ),
		'construction-cost-guides' => array( '25c1fd7e722bf83f7d07253d28d20443', '2cdb261676fbd71c2c0df555f1458759' ),
		'about-us' => array( '94d7db5c6e0e55d1b34a4c958e559cef', '4193f6d7dc3ac41ced01094b4223601f' ),
		'contact-us' => array( '06c1f54f65f9e57c7603c17cfe05b430', 'b80fe081880d4050507167c4cf8af240' ),
		'privacy-policy' => array( 'd4e7205309ebab0a21691048361f9c32', 'b7b164ce19c3001a21803541f7a6f828' ),
		'disclaimer' => array( '0cc916aca035c328583efc5c9e0cc5e7', '3432e13ddf81f62e5341cade59d728e8' ),
		'3-marla-house-construction-cost' => array( '919109621ed1460de974a71fe10c69ad', 'b20db0d7a04740ac771cca672113c716' ),
		'4-marla-house-construction-cost' => array( '919109621ed1460de974a71fe10c69ad', 'b20db0d7a04740ac771cca672113c716' ),
		'construction-cost-lahore' => array( 'b1c9a1dff0a891d253dc73d9412ef4da', '9c309809e6dc6486b5928e07f8d7ba25' ),
		'construction-cost-karachi' => array( '1e4143cb10fbb1e2f92c15b5e25c2c18', '5115cc016e12b7bfda4d56a736a17725' ),
		'construction-cost-islamabad' => array( '2f9519d9a4ba1053916038b32aace93b', '8cf282050d7dab3b60b16ee6f6a8fa77' ),
		'construction-cost-by-city' => array( '2804e580337b9c49ff335b08cefbbd61', 'f516045bdde2b8c88d91cd4005c77af0' ),
		'construction-material-rates' => array( 'f0c8bc4bdd0e93bbb70bcdd9960966cb', 'a982be6d27bb49fcbeb08c47108029e4' ),
		'cement-rate-today-pakistan' => array( 'd554a77779f258551a9afeb7d5e15736', 'f2e0e78d05f4a4e1ddc097354ce9775c' ),
		'steel-saria-rate-today' => array( '47d3d939a42eecee9282feb83ebc400b', 'b5db999d0f4fba71f905cc777cee105a' ),
		'pakistan-construction-cost-index' => array( '5830980ac0d547e6dd416518454d6c2d', 'cd7b0a6b527b54b18de51806d569ab51' ),
		'methodology' => array( '0abf97947338f48a7757700c212b49e0', '014e1cb41e7e395557a550b6f6b59f46' ),
		'embed-construction-cost-calculator' => array( '516603754c7387f1be3054c348c07a9e', '2b7897a61742c08e8a268fd15778deb5' ),
	);
}

/** Status of each live page: 'live', 'theme' (unedited, not switched yet), 'edited' or 'missing'. */
function hcc_live_page_status() {
	$fps = hcc_v18_content_fingerprints();
	// A page created from this version's template (e.g. by hcc_create_pages_and_menu()) also counts as unedited.
	foreach ( hcc_live_page_templates() as $slug => $tpl ) $fps[ $slug ][] = hcc_content_fingerprint( $tpl );
	$out = array();
	foreach ( hcc_live_page_slugs() as $slug ) {
		$p = get_page_by_path( $slug );
		if ( ! $p ) { $out[ $slug ] = array( 'status' => 'missing', 'id' => 0 ); continue; }
		$c = trim( (string) $p->post_content );
		if ( $c === hcc_live_page_shortcode_text( $slug ) ) $status = 'live';
		elseif ( isset( $fps[ $slug ] ) && in_array( hcc_content_fingerprint( $c ), (array) $fps[ $slug ], true ) ) $status = 'theme';
		else $status = 'edited';
		$out[ $slug ] = array( 'status' => $status, 'id' => $p->ID );
	}
	return $out;
}

/** Switch one page to live content. WordPress keeps the old text as a revision. */
function hcc_make_page_live( $page_id, $slug ) {
	$p = get_post( $page_id );
	if ( ! $p || $p->post_name !== $slug ) return false;
	$backup = (array) get_option( 'hcc_v19_content_backup', array() );
	if ( ! isset( $backup[ $page_id ] ) ) {
		$backup[ $page_id ] = $p->post_content;
		update_option( 'hcc_v19_content_backup', $backup, false );
	}
	$r = wp_update_post( array( 'ID' => $page_id, 'post_content' => hcc_live_page_shortcode_text( $slug ) ), true );
	return ! is_wp_error( $r );
}

/* Tools → ConstructionCostPK SEO: "use the live theme version" button. */
add_action( 'admin_post_hcc_make_live', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
	check_admin_referer( 'hcc_make_live' );
	$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	$p = $slug && in_array( $slug, hcc_live_page_slugs(), true ) ? get_page_by_path( $slug ) : null;
	if ( $p && hcc_make_page_live( $p->ID, $slug ) ) {
		hcc_log( 'Page switched to live theme content by an admin: ' . $slug . ' (old text kept as a revision).' );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=hcc-seo' ) );
	exit;
} );

/**
 * v1.9.0 one-time migration: switch unedited theme pages to live content,
 * drop the frozen Rank Math titles/descriptions v1.8.0 wrote (so the live
 * theme defaults in hcc_seo_meta_map() are used — anything the owner typed
 * in Rank Math is kept), and swap the plot pages' featured images for the
 * new versions without a printed price.
 */
function hcc_run_v19_migration() {
	if ( get_option( 'hcc_v19_migrated' ) ) return;
	$live = $kept = array();
	foreach ( hcc_live_page_status() as $slug => $st ) {
		if ( 'theme' === $st['status'] ) {
			if ( hcc_make_page_live( $st['id'], $slug ) ) $live[] = $slug;
		} elseif ( 'edited' === $st['status'] ) {
			$kept[] = $slug;
		}
	}
	hcc_log( 'v1.9.0: ' . count( $live ) . ' page(s) now show live figures: ' . implode( ', ', $live ) );
	if ( $kept ) hcc_log( 'v1.9.0: kept as they are because they were edited by hand (switch them under Tools → ConstructionCostPK SEO if you want the live version): ' . implode( ', ', $kept ) );

	$meta_fps = hcc_v18_meta_fingerprints();
	$backup = (array) get_option( 'hcc_v19_meta_backup', array() );
	$cleared = array();
	foreach ( $meta_fps as $key => $fp ) {
		if ( in_array( $key, array( 'about-us', 'contact-us', 'privacy-policy', 'disclaimer' ), true ) ) continue;
		$pid = 'home' === $key ? (int) get_option( 'page_on_front' ) : ( ( $p = get_page_by_path( $key ) ) ? $p->ID : 0 );
		if ( ! $pid ) continue;
		foreach ( array( 'rank_math_title' => $fp[0], 'rank_math_description' => $fp[1] ) as $mk => $want ) {
			$v = (string) get_post_meta( $pid, $mk, true );
			if ( '' === $v || hcc_content_fingerprint( $v ) !== $want ) continue;
			$backup[ $pid ][ $mk ] = $v;
			delete_post_meta( $pid, $mk );
			$cleared[] = $key . ' ' . ( 'rank_math_title' === $mk ? 'title' : 'description' );
		}
	}
	update_option( 'hcc_v19_meta_backup', $backup, false );
	hcc_log( 'v1.9.0: frozen Rank Math meta removed so the live theme version shows: ' . ( $cleared ? implode( ', ', $cleared ) : 'none' ) );

	hcc_v19_refresh_plot_thumbnails();

	update_option( 'hcc_v19_migrated', gmdate( 'c' ) );
	flush_rewrite_rules();
	hcc_log( 'v1.9.0 migration complete. Purge the LiteSpeed and CDN cache now.' );
}

/**
 * The v1.8.0 plot-page featured images printed a price. Replace the ones
 * the theme itself set (file name hero-*.png) with a fresh copy of the new
 * image; a featured image the owner chose by hand is left alone.
 */
function hcc_v19_refresh_plot_thumbnails() {
	if ( ! function_exists( 'get_post_thumbnail_id' ) ) return;
	foreach ( hcc_plot_thumbnail_defs() as $slug => $def ) {
		if ( 'marla-kanal-standards-pakistan' === $slug ) continue;
		$page = get_page_by_path( $slug );
		if ( ! $page ) continue;
		$tid = get_post_thumbnail_id( $page->ID );
		if ( ! $tid ) continue; // hcc_ensure_plot_thumbnails() adds the new one
		$file = basename( (string) get_attached_file( $tid ) );
		$base = pathinfo( $def['file'], PATHINFO_FILENAME );
		if ( ! preg_match( '/^' . preg_quote( $base, '/' ) . '(-\d+)?\.png$/', $file ) ) continue;
		delete_post_thumbnail( $page->ID );
		hcc_ensure_plot_thumbnails();
		if ( get_post_thumbnail_id( $page->ID ) ) {
			hcc_log( 'v1.9.0: new featured image (no printed price) for ' . $slug . '.' );
		} else {
			set_post_thumbnail( $page->ID, $tid ); // copy failed — keep the old one
		}
	}
}
