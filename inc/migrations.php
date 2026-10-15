<?php
/**
 * v1.8.0 one-time migration. Runs once, on the first wp-admin page load after
 * the theme zip is uploaded (or on activation). Every change is logged to the
 * "hcc_migration_log" option (visible under Tools → ConstructionCostPK SEO)
 * and the previous values are saved in "hcc_v18_backup" so they can be
 * restored by hand. Page content edits go through wp_update_post(), so
 * WordPress keeps a revision of each page as it was before.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hcc_log( $msg ) {
	$log = (array) get_option( 'hcc_migration_log', array() );
	$log[] = gmdate( 'Y-m-d H:i' ) . ' — ' . $msg;
	update_option( 'hcc_migration_log', array_slice( $log, -200 ), false );
}

function hcc_run_v18_migration() {
	if ( get_option( 'hcc_v18_migrated' ) ) return;
	$backup = array();

	/* ---- T2.1 / T2.2 — owner display name + author slug ---- */
	$u = get_user_by( 'email', HCC_OWNER_EMAIL );
	if ( $u ) {
		$backup['user'] = array( 'ID' => $u->ID, 'display_name' => $u->display_name, 'nickname' => get_user_meta( $u->ID, 'nickname', true ), 'user_nicename' => $u->user_nicename );
		$upd = array( 'ID' => $u->ID );
		if ( false !== strpos( $u->display_name, '@' ) || $u->display_name === $u->user_login ) $upd['display_name'] = HCC_OWNER_NAME;
		if ( false !== strpos( (string) get_user_meta( $u->ID, 'nickname', true ), '@' ) ) $upd['nickname'] = HCC_OWNER_NAME;
		if ( ! get_user_meta( $u->ID, 'first_name', true ) ) $upd['first_name'] = 'Ali';
		if ( ! get_user_meta( $u->ID, 'last_name', true ) ) $upd['last_name'] = 'Ahmad';
		$taken = get_user_by( 'slug', HCC_OWNER_SLUG );
		if ( $u->user_nicename !== HCC_OWNER_SLUG && ( ! $taken || (int) $taken->ID === (int) $u->ID ) ) {
			$upd['user_nicename'] = HCC_OWNER_SLUG;
			$old = (array) get_option( 'hcc_old_author_slugs', array() );
			$old[ $u->user_nicename ] = HCC_OWNER_SLUG;
			update_option( 'hcc_old_author_slugs', $old );
		}
		if ( count( $upd ) > 1 ) {
			$r = wp_update_user( $upd );
			hcc_log( is_wp_error( $r ) ? 'User update failed: ' . $r->get_error_message() : 'Author updated: ' . implode( ', ', array_keys( array_diff_key( $upd, array( 'ID' => 1 ) ) ) ) . ' (slug now /author/' . HCC_OWNER_SLUG . '/)' );
		}
	}

	/* ---- Brand name (site title) ---- */
	$backup['blogname'] = get_option( 'blogname' );
	$backup['blogdescription'] = get_option( 'blogdescription' );
	if ( HCC_BRAND !== get_option( 'blogname' ) ) {
		update_option( 'blogname', HCC_BRAND );
		update_option( 'blogdescription', HCC_BRAND_ALT );
		hcc_log( 'Site title set to "' . HCC_BRAND . '" (was "' . $backup['blogname'] . '"), tagline "' . HCC_BRAND_ALT . '".' );
	}

	/* ---- T3.4 — logo attachment ---- */
	$logo_id = hcc_v18_logo_attachment();

	/* ---- T3.1–T3.4 — Rank Math knowledge graph settings ---- */
	$titles = get_option( 'rank-math-options-titles' );
	if ( is_array( $titles ) ) {
		$backup['rank-math-options-titles'] = array_intersect_key( $titles, array_flip( array( 'knowledgegraph_type', 'knowledgegraph_name', 'website_name', 'website_alternate_name', 'knowledgegraph_logo', 'knowledgegraph_logo_id' ) ) );
		$titles['knowledgegraph_type'] = 'company';
		$titles['knowledgegraph_name'] = HCC_BRAND;
		$titles['website_name'] = HCC_BRAND;
		$titles['website_alternate_name'] = HCC_BRAND_ALT;
		if ( $logo_id && empty( $titles['knowledgegraph_logo'] ) ) {
			$titles['knowledgegraph_logo'] = wp_get_attachment_url( $logo_id );
			$titles['knowledgegraph_logo_id'] = $logo_id;
		}
		update_option( 'rank-math-options-titles', $titles );
		hcc_log( 'Rank Math: Local SEO type → Organization, name/website name → ' . HCC_BRAND . ', alternate name set' . ( $logo_id ? ', logo set' : '' ) . '.' );
	} else {
		hcc_log( 'Rank Math titles option not found — schema is still corrected by the theme filter.' );
	}

	/* ---- T1.5 — Rank Math Instant Indexing (IndexNow) module ---- */
	$mods = get_option( 'rank_math_modules' );
	if ( is_array( $mods ) && ! in_array( 'instant-indexing', $mods, true ) ) {
		$backup['rank_math_modules'] = $mods;
		$mods[] = 'instant-indexing';
		update_option( 'rank_math_modules', $mods );
		hcc_log( 'Rank Math Instant Indexing (IndexNow) module enabled.' );
	}

	/* ---- T5.1 — delete empty custom menu links ---- */
	$removed = 0;
	foreach ( wp_get_nav_menus() as $menu ) {
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) ) as $item ) {
			if ( 'custom' === $item->type && '' === trim( (string) $item->url ) && '' === trim( wp_strip_all_tags( (string) $item->title ) ) ) {
				$backup['menu_items'][] = array( 'menu' => $menu->name, 'id' => $item->ID );
				wp_delete_post( $item->ID, true );
				$removed++;
			}
		}
	}
	hcc_log( 'Menus: removed ' . $removed . ' empty custom link(s).' );
	// Short, readable header labels instead of full page titles.
	$short = array( 'construction-cost-guides' => 'Guides', 'grey-structure-cost-calculator' => 'Grey Structure Calculator' );
	foreach ( wp_get_nav_menus() as $menu ) {
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
			if ( 'post_type' !== $item->type ) continue;
			$pg = get_post( $item->object_id );
			if ( $pg && isset( $short[ $pg->post_name ] ) && $item->title === $pg->post_title && $item->title !== $short[ $pg->post_name ] ) {
				update_post_meta( $item->ID, '_hcc_old_title', $item->post_title );
				wp_update_post( array( 'ID' => $item->ID, 'post_title' => $short[ $pg->post_name ] ) );
				hcc_log( 'Menu label shortened: ' . $pg->post_name . ' → ' . $short[ $pg->post_name ] );
			}
		}
	}

	/* ---- C1 / L4 / G2 / G5 / X1 — create new pages ---- */
	$created = array();
	foreach ( hcc_v18_page_definitions() as $slug => $def ) {
		if ( get_page_by_path( $slug ) ) continue;
		$id = wp_insert_post( array(
			'post_title' => $def['title'], 'post_name' => $slug, 'post_content' => $def['content'],
			'post_status' => 'publish', 'post_type' => 'page', 'post_author' => $u ? $u->ID : 1,
			'comment_status' => 'closed', 'ping_status' => 'closed',
		) );
		if ( $id && ! is_wp_error( $id ) ) $created[] = $slug;
	}
	hcc_log( 'Created ' . count( $created ) . ' new page(s): ' . implode( ', ', $created ) );

	/* ---- O1 / O2 — Rank Math titles + descriptions, T4.1/T4.2 schema fix ---- */
	$map = hcc_seo_meta_map();
	$schema_pages = array( 'grey-structure-cost-calculator', '5-marla-house-construction-cost', '7-marla-house-construction-cost', '10-marla-house-construction-cost', '1-kanal-house-construction-cost', 'marla-kanal-standards-pakistan' );
	foreach ( $map as $key => $m ) {
		$pid = 'home' === $key ? (int) get_option( 'page_on_front' ) : ( ( $p = get_page_by_path( $key ) ) ? $p->ID : 0 );
		if ( ! $pid ) continue;
		$backup['meta'][ $pid ] = array( 'rank_math_title' => get_post_meta( $pid, 'rank_math_title', true ), 'rank_math_description' => get_post_meta( $pid, 'rank_math_description', true ), 'rank_math_rich_snippet' => get_post_meta( $pid, 'rank_math_rich_snippet', true ) );
		update_post_meta( $pid, 'rank_math_title', $m['title'] );
		update_post_meta( $pid, 'rank_math_description', $m['desc'] );
		if ( in_array( $key, $schema_pages, true ) && 'off' === get_post_meta( $pid, 'rank_math_rich_snippet', true ) ) {
			delete_post_meta( $pid, 'rank_math_rich_snippet' );
			hcc_log( 'Schema: removed Rank Math "None" schema flag on ' . $key . ' (restores default Article + global graph).' );
		}
	}
	hcc_log( 'Rank Math titles/descriptions written for ' . count( $map ) . ' pages.' );

	/* ---- O3.7 / O3.11 / E4 — safe text fixes in existing pages ---- */
	$fixes = array(
		'about-us' => array(
			'/,?\s*with backgrounds in civil engineering,? quantity surveying and construction-cost research/i' => '',
			'/Ali Ahmad and the Construction Cost Calculator team/' => 'Ali Ahmad (ConstructionCostPK)',
			'/reach Ali Ahmad and the team directly/' => 'reach Ali Ahmad directly',
			'/nothing you enter is transmitted to any server/' => 'nothing you type into the calculator is sent to any server unless you choose to send optional feedback',
		),
		'contact-us' => array(
			'#<p>\s*<strong>Email:</strong>\s*<a href="mailto:' . preg_quote( HCC_OWNER_EMAIL, '#' ) . '">[^<]*</a>\s*</p>\s*#i' => '',
		),
		'privacy-policy' => array(
			'#please email us at <a href="mailto:' . preg_quote( HCC_OWNER_EMAIL, '#' ) . '">[^<]*</a> or visit our#i' => 'please use the form on our',
			'/This data is never transmitted to, stored on, or processed by any server\./' => 'This data is not transmitted to, stored on, or processed by any server unless you choose to send the optional estimate feedback described below.',
		),
	);
	foreach ( $fixes as $slug => $rules ) {
		$p = get_page_by_path( $slug );
		if ( ! $p ) continue;
		$new = $p->post_content;
		foreach ( $rules as $re => $rep ) $new = preg_replace( $re, $rep, $new );
		// Any remaining raw e-mail link to the owner's Gmail → contact page.
		$new = preg_replace( '#<a href="mailto:' . preg_quote( HCC_OWNER_EMAIL, '#' ) . '">[^<]*</a>#i', '<a href="' . esc_url( home_url( '/contact-us/' ) ) . '">our contact form</a>', $new );
		if ( $new !== $p->post_content ) {
			$backup['content'][ $p->ID ] = $p->post_content;
			wp_update_post( array( 'ID' => $p->ID, 'post_content' => $new ) );
			hcc_log( 'Content updated: ' . $slug );
		}
	}

	/* ---- featured images for new plot pages ---- */
	hcc_ensure_plot_thumbnails();

	update_option( 'hcc_v18_backup', $backup, false );
	update_option( 'hcc_v18_migrated', gmdate( 'c' ) );
	flush_rewrite_rules();
	hcc_log( 'v1.8.0 migration complete. Purge LiteSpeed cache now.' );
}

function hcc_v18_logo_attachment() {
	$existing = get_option( 'hcc_logo_attachment_id' );
	if ( $existing && get_post( $existing ) ) return (int) $existing;
	$src = get_template_directory() . '/assets/img/constructioncostpk-logo-512.png';
	$up = wp_upload_dir();
	if ( ! file_exists( $src ) || empty( $up['path'] ) ) return 0;
	$name = wp_unique_filename( $up['path'], 'constructioncostpk-logo-512.png' );
	$dest = trailingslashit( $up['path'] ) . $name;
	if ( ! @copy( $src, $dest ) ) return 0;
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'ConstructionCostPK logo', 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $name ), $dest );
	if ( ! $id || is_wp_error( $id ) ) return 0;
	if ( file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', 'ConstructionCostPK logo' );
	update_option( 'hcc_logo_attachment_id', $id );
	return (int) $id;
}
