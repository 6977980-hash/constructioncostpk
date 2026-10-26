<?php
/**
 * v1.8.0 — owner settings (Customizer) + Tools → ConstructionCostPK status page.
 * Every value the audit said must come from the owner lives here, so nothing
 * has to be invented in code: bio/credentials, domain email, WhatsApp, social
 * profiles, analytics IDs, AdSense ID, real rate sources and the rates date.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** A rate field: a positive number, or empty to fall back to the theme default. */
function hcc_sanitize_rate( $v ) {
	$v = str_replace( ',', '', trim( (string) $v ) );
	return is_numeric( $v ) && (float) $v > 0 ? (string) (float) $v : '';
}

add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'hcc_settings', array(
		'title' => 'ConstructionCostPK Settings', 'priority' => 30,
		'description' => 'Owner information used across the site (schema, author box, contact page, analytics). Leave a field empty to hide it.',
	) );
	$fields = array(
		'rates_updated' => array( 'Rates last updated (YYYY-MM-DD)', 'text', HCC_RATES_DATE_DEFAULT, 'Shown as "Rates last updated" on every calculator page and used as dateModified of the calculator schema. Update it whenever you change the default rates.' ),
		'rate_cement' => array( 'Cement rate (PKR per 50 kg bag)', 'number', '', 'Leave empty to use the theme default (PKR 1,600). Every page, title and the calculator use this rate. Update "Rates last updated" too.' ),
		'rate_steel' => array( 'Steel / saria rate (PKR per kg)', 'number', '', 'Leave empty to use the theme default (PKR 275).' ),
		'rate_bricksPer1000' => array( 'Bricks rate (PKR per 1,000)', 'number', '', 'Leave empty to use the theme default (PKR 20,000).' ),
		'rate_sand' => array( 'Sand rate (PKR per cft)', 'number', '', 'Leave empty to use the theme default (PKR 90).' ),
		'rate_crush' => array( 'Crush / bajri rate (PKR per cft)', 'number', '', 'Leave empty to use the theme default (PKR 130).' ),
		'rate_approvalPerMarla' => array( 'Map approval fee (PKR per Marla)', 'number', '', 'Leave empty to use the theme default (PKR 18,000).' ),
		'sources_note' => array( 'Rate sources note (shown in Sources box)', 'textarea', '', 'Example: Cement: Lucky/DG dealer quotes, Lahore, 18 Sep 2026; Steel: Amreli price list 17 Sep 2026; PBS SPI weekly report.' ),
		'source_cement' => array( 'Source for cement rate', 'text', '', 'e.g. Dealer quote, Ravi Road Lahore, 18 Sep 2026' ),
		'source_steel' => array( 'Source for steel rate', 'text', '', '' ),
		'source_bricks' => array( 'Source for bricks rate', 'text', '', '' ),
		'source_sand' => array( 'Source for sand rate', 'text', '', '' ),
		'source_crush' => array( 'Source for crush rate', 'text', '', '' ),
		'market_cement' => array( 'Cement market prices (one per line: Brand | Price | Source | Date)', 'textarea', '', 'Only enter prices you verified. Example: Lucky Cement | PKR 1,450/bag | Dealer quote Lahore | 2026-09-18' ),
		'market_steel' => array( 'Steel market prices (one per line: Brand/Grade | Price | Source | Date)', 'textarea', '', '' ),
		'author_linkedin' => array( 'Ali Ahmad — LinkedIn URL', 'url', '', '' ),
		'author_credentials' => array( 'Ali Ahmad — credentials line (only if true)', 'text', '', 'e.g. B.Sc. Civil Engineering (UET Lahore), 8 years in residential construction. Leave empty if not applicable.' ),
		'changelog' => array( 'About page update log (one entry per line)', 'textarea', '', 'Leave empty to use the built-in log.' ),
		'contact_email' => array( 'Contact email (domain email, e.g. info@constructioncostpk.com)', 'email', '', 'Contact form messages are sent here (falls back to the WordPress admin email).' ),
		'whatsapp' => array( 'WhatsApp number (international, e.g. 923001234567)', 'text', '', '' ),
		'contact_city' => array( 'City shown on Contact page', 'text', '', '' ),
		'social_facebook' => array( 'Facebook page URL', 'url', '', '' ),
		'social_youtube' => array( 'YouTube channel URL', 'url', '', '' ),
		'social_linkedin' => array( 'LinkedIn company page URL', 'url', '', '' ),
		'social_x' => array( 'X (Twitter) profile URL', 'url', '', '' ),
		'social_instagram' => array( 'Instagram URL', 'url', '', '' ),
		'social_pinterest' => array( 'Pinterest URL', 'url', '', '' ),
		'ga4_id' => array( 'Google Analytics 4 Measurement ID (G-XXXXXXX)', 'text', '', 'Leave empty to use the site\'s own ID (G-SZFCLBZJ9S).' ),
		'clarity_id' => array( 'Microsoft Clarity project ID', 'text', '', '' ),
		'adsense_pub' => array( 'AdSense publisher ID (pub-XXXXXXXXXXXXXXXX) — creates /ads.txt', 'text', '', 'Only after your AdSense account exists.' ),
	);
	foreach ( $fields as $key => $f ) {
		$sanitize = 'url' === $f[1] ? 'esc_url_raw' : ( 'email' === $f[1] ? 'sanitize_email' : ( 'textarea' === $f[1] ? 'wp_kses_post' : ( 'number' === $f[1] ? 'hcc_sanitize_rate' : 'sanitize_text_field' ) ) );
		$wp_customize->add_setting( 'hcc_' . $key, array( 'default' => $f[2], 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ) );
		$wp_customize->add_control( 'hcc_' . $key, array( 'label' => $f[0], 'section' => 'hcc_settings', 'type' => $f[1], 'description' => $f[3] ) );
	}
} );

/* Tools → ConstructionCostPK: figures vs titles (O1.10), migration log, owner to-do. */
add_action( 'admin_menu', function () {
	add_management_page( 'ConstructionCostPK SEO', 'ConstructionCostPK SEO', 'manage_options', 'hcc-seo', 'hcc_render_status_page' );
} );
function hcc_render_status_page() {
	$map = hcc_seo_meta_map();
	$log = (array) get_option( 'hcc_migration_log', array() );
	echo '<div class="wrap"><h1>ConstructionCostPK — SEO status</h1>';
	echo '<h2>Live calculator figures (use these when updating titles)</h2><table class="widefat striped"><thead><tr><th>Page</th><th>Complete (Lahore, standard)</th><th>Grey</th><th>Current Rank Math title</th></tr></thead><tbody>';
	foreach ( hcc_plot_sizes_sqft() as $k => $p ) {
		$slug = $k . '-house-construction-cost';
		$page = get_page_by_path( $slug );
		$t = $page ? get_post_meta( $page->ID, 'rank_math_title', true ) : '';
		echo '<tr><td>' . esc_html( $slug ) . '</td><td>' . esc_html( hcc_format_pkr( hcc_estimate( $p[0], $p[1] )['grandTotal'] ) . ' (' . hcc_lakh_crore( hcc_estimate( $p[0], $p[1] )['grandTotal'] ) . ')' ) . '</td><td>' . esc_html( hcc_lakh_crore( hcc_estimate( $p[0], $p[1], 'grey' )['grandTotal'] ) ) . '</td><td>' . esc_html( $t ? $t : ( isset( $map[ $slug ] ) ? $map[ $slug ]['title'] . ' (theme default)' : '' ) ) . '</td></tr>';
	}
	echo '</tbody></table><p>Titles and descriptions live in each page\'s Rank Math box (Edit page → Rank Math → Edit Snippet). The theme defaults are in <code>inc/hcc-data.php → hcc_seo_meta_map()</code>. Rates date: <strong>' . esc_html( hcc_rates_date_human() ) . '</strong> (Appearance → Customize → ConstructionCostPK Settings).</p>';
	echo '<h2>Page content</h2><p>"Live" pages build their figures from the calculator and today\'s default rates on every view. A page you edited by hand keeps your text; switch it to live only if you no longer need those edits (your current text is kept as a page revision).</p><table class="widefat striped"><thead><tr><th>Page</th><th>Status</th><th></th></tr></thead><tbody>';
	$labels = array( 'live' => 'Live figures', 'theme' => 'Theme text, not switched yet', 'edited' => 'Edited by hand — frozen figures', 'missing' => 'Page not found' );
	foreach ( hcc_live_page_status() as $slug => $st ) {
		echo '<tr><td>' . esc_html( $slug ) . '</td><td>' . esc_html( $labels[ $st['status'] ] ) . '</td><td>';
		if ( in_array( $st['status'], array( 'theme', 'edited' ), true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Replace this page\\\'s text with the live theme version? The current text stays available under Revisions.\');">';
			wp_nonce_field( 'hcc_make_live' );
			echo '<input type="hidden" name="action" value="hcc_make_live"><input type="hidden" name="slug" value="' . esc_attr( $slug ) . '"><button class="button">Use live theme version</button></form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<h2>Migration log</h2><ul>';
	foreach ( $log as $l ) echo '<li>' . esc_html( $l ) . '</li>';
	echo '</ul></div>';
}
