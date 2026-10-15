<?php
/**
 * v1.8.0 — the_content enhancements rendered server-side on every managed
 * page (visible to crawlers without JavaScript):
 *   O3.3 "Rates last updated" line · G3 citable sentence · G4/G10 sources box ·
 *   L1.10 methodology link · E1 author box · L2 related calculators ·
 *   T1.6 "more guides" on home · O3.8 hub headline figures · E3 About ·
 *   E4/O3.11 Contact · privacy additions · O3.9 self-link removal ·
 *   R8 WebP + fetchpriority on hero images, T6.8 hero preload.
 * Nothing here edits the database — remove the file and the pages revert.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hcc_author_user() {
	$u = get_user_by( 'email', HCC_OWNER_EMAIL );
	if ( ! $u ) {
		$post = get_post();
		if ( $post ) $u = get_userdata( $post->post_author );
	}
	return $u ? $u : null;
}
function hcc_author_name() {
	$u = hcc_author_user();
	return ( $u && false === strpos( $u->display_name, '@' ) ) ? $u->display_name : HCC_OWNER_NAME;
}
function hcc_author_bio() {
	$u = hcc_author_user();
	$bio = $u ? trim( get_the_author_meta( 'description', $u->ID ) ) : '';
	return $bio ? $bio : HCC_OWNER_NAME . ' founded and maintains ConstructionCostPK, a free, independent house construction cost calculator for Pakistan. He reviews the default material and labour rates and publishes every formula the calculator uses.';
}

/** G3 — one self-contained, citable sentence per page. */
function hcc_citable_sentence( $key ) {
	$asof = 'As of ' . date_i18n( 'F Y', strtotime( hcc_rates_date() ) );
	$plots = array( '3-marla-house-construction-cost' => array( '3 Marla', 675, 1 ), '4-marla-house-construction-cost' => array( '4 Marla', 900, 1 ), '5-marla-house-construction-cost' => array( '5 Marla', 1125, 1 ), '7-marla-house-construction-cost' => array( '7 Marla', 1575, 1 ), '10-marla-house-construction-cost' => array( '10 Marla', 2250, 2 ), '1-kanal-house-construction-cost' => array( '1 Kanal', 4500, 2 ) );
	if ( isset( $plots[ $key ] ) ) {
		$p = $plots[ $key ];
		$e = hcc_estimate( $p[1], $p[2] );
		return $asof . ', a standard-quality complete ' . ( 2 === $p[2] ? 'double storey ' : 'single storey ' ) . $p[0] . ' house in Lahore costs about ' . hcc_format_pkr( $e['grandTotal'] ) . ' (PKR ' . hcc_lakh_crore( $e['grandTotal'] ) . ') to build (ConstructionCostPK).';
	}
	if ( 0 === strpos( $key, 'construction-cost-' ) && in_array( substr( $key, 18 ), array( 'lahore', 'karachi', 'islamabad' ), true ) ) {
		$c = substr( $key, 18 );
		$labels = hcc_city_labels();
		return $asof . ', a standard-quality complete house in ' . $labels[ $c ] . ' costs about ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', $c ) ) . ' per sq ft, and grey structure about ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey', $c ) ) . ' per sq ft (ConstructionCostPK).';
	}
	$r = hcc_default_calc_rates();
	switch ( $key ) {
		case 'home':
			return $asof . ', a standard-quality complete house in Lahore costs about ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete' ) ) . ' per sq ft and grey structure about ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey' ) ) . ' per sq ft (ConstructionCostPK).';
		case 'grey-structure-cost-calculator':
			return $asof . ', grey structure in Lahore costs about ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey' ) ) . ' per sq ft at standard quality, of which about PKR ' . number_format( hcc_quality_table()['standard']['greyLabour'] ) . ' is labour (ConstructionCostPK).';
		case 'marla-kanal-standards-pakistan':
			return 'In Pakistan, 1 Marla is 225 sq ft under the common Society standard but 272.25 sq ft under the Patwari revenue standard, so 1 Kanal ranges from 4,500 to 5,445 sq ft (ConstructionCostPK).';
		case 'construction-cost-guides':
			return $asof . ', a standard 5 Marla house in Lahore costs about PKR ' . hcc_lakh_crore( hcc_estimate( 1125, 1 )['grandTotal'] ) . ' and a double storey 10 Marla house about PKR ' . hcc_lakh_crore( hcc_estimate( 2250, 2 )['grandTotal'] ) . ' to build (ConstructionCostPK).';
		case 'construction-cost-by-city':
			return $asof . ', complete house construction at standard quality ranges from about ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', 'peshawar' ) ) . ' per sq ft in Peshawar to about ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', 'islamabad' ) ) . ' per sq ft in Islamabad (ConstructionCostPK).';
		case 'construction-material-rates':
		case 'cement-rate-today-pakistan':
			return $asof . ', ConstructionCostPK uses a planning rate of PKR ' . number_format( $r['cement'] ) . ' per 50 kg cement bag; a single storey 5 Marla house needs about ' . number_format( round( hcc_suggest_covered_area( 1125, 1 ) * hcc_base_qty_per_sqft()['cementBags'] ) ) . ' bags (ConstructionCostPK).';
		case 'steel-saria-rate-today':
			return $asof . ', ConstructionCostPK uses a planning rate of PKR ' . number_format( $r['steel'] ) . ' per kg for Grade 60 steel; a single storey 5 Marla house needs about ' . number_format( round( hcc_suggest_covered_area( 1125, 1 ) * hcc_base_qty_per_sqft()['steelKg'] ) ) . ' kg (ConstructionCostPK).';
		case 'pakistan-construction-cost-index':
			return $asof . ', the ConstructionCostPK index puts standard complete construction at ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete' ) ) . ' per sq ft in Lahore, ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', 'karachi' ) ) . ' in Karachi and ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', 'islamabad' ) ) . ' in Islamabad.';
		case 'methodology':
			$b = hcc_base_qty_per_sqft();
			return 'ConstructionCostPK estimates assume ' . $b['cementBags'] . ' bags of cement, ' . $b['steelKg'] . ' kg of steel and ' . $b['bricks'] . ' bricks per sq ft of covered area at standard quality.';
	}
	return '';
}

function hcc_page_meta_line( $key ) {
	$post = get_post();
	$modified = $post ? get_the_modified_date( 'j F Y', $post ) : '';
	$author_url = hcc_page_exists( 'about-us' ) ? hcc_page_url( 'about-us' ) : home_url( '/' );
	return '<p class="page-meta"><span><strong>Rates last updated:</strong> <time datetime="' . esc_attr( hcc_rates_date() ) . '">' . esc_html( hcc_rates_date_human() ) . '</time></span>'
		. ( $modified ? '<span><strong>Page updated:</strong> <time datetime="' . esc_attr( get_the_modified_date( 'Y-m-d', $post ) ) . '">' . esc_html( $modified ) . '</time></span>' : '' )
		. '<span><strong>By</strong> <a href="' . esc_url( $author_url ) . '">' . esc_html( hcc_author_name() ) . '</a></span></p>';
}

function hcc_sources_box() {
	$custom = hcc_opt( 'sources_note' );
	$h = '<aside class="sources-box" aria-label="Sources and method"><h2>Sources &amp; how we calculate these estimates</h2><ul>';
	$h .= '<li>Default material rates (cement, steel, bricks, sand, crush) were last updated on <strong>' . esc_html( hcc_rates_date_human() ) . '</strong>. ' . ( $custom ? wp_kses_post( $custom ) : 'They are planning rates set from Pakistani supplier and marketplace price lists; each one is editable in the calculator so you can use your own quotation.' ) . '</li>';
	$h .= '<li>Material quantities use standard per-sq-ft thumb rules, adjusted for quality, design complexity, storeys and city — read <a href="' . esc_url( home_url( '/methodology/' ) ) . '">how we calculate these estimates</a>.</li>';
	$h .= '<li>Estimates are for planning only — see our <a href="' . esc_url( home_url( '/disclaimer/' ) ) . '">disclaimer</a>.</li>';
	$h .= '</ul>';
	if ( ! empty( $GLOBALS['hcc_cite'] ) ) {
		$h .= '<p class="citable"><strong>Cite this page:</strong> ' . esc_html( $GLOBALS['hcc_cite'] ) . '</p>';
		$GLOBALS['hcc_cite'] = '';
	}
	return $h . '</aside>';
}

function hcc_author_box() {
	$u = hcc_author_user();
	$name = hcc_author_name();
	$avatar = $u ? get_avatar( $u->ID, 72, '', $name, array( 'class' => 'author-photo', 'loading' => 'lazy' ) ) : '';
	$li = hcc_opt( 'author_linkedin' );
	$about = hcc_page_exists( 'about-us' ) ? hcc_page_url( 'about-us' ) : home_url( '/' );
	return '<aside class="author-box" aria-label="About the author">' . $avatar . '<div><p class="author-by">Written by <strong>' . esc_html( $name ) . '</strong></p><p>' . esc_html( hcc_author_bio() ) . '</p><p class="author-links"><a href="' . esc_url( $about ) . '">About ConstructionCostPK</a>' . ( $li ? ' · <a href="' . esc_url( $li ) . '" rel="me noopener" target="_blank">LinkedIn</a>' : '' ) . ' · <a href="' . esc_url( home_url( '/methodology/' ) ) . '">Methodology</a></p></div></aside>';
}

/** L2 — "Related calculators" cards, same cluster first, 4–6 cards. */
function hcc_related_block( $key ) {
	$reg = hcc_page_registry();
	$cluster = isset( $reg[ $key ] ) ? $reg[ $key ]['cluster'] : 'plot';
	$same = $other = array();
	foreach ( $reg as $k => $d ) {
		if ( $k === $key || empty( $d['card'] ) || ! hcc_page_exists( $k ) || in_array( $d['type'], array( 'legal', 'about', 'contact' ), true ) ) continue;
		if ( 'trust' === $d['cluster'] ) continue;
		if ( $d['cluster'] === $cluster ) $same[ $k ] = $d; else $other[ $k ] = $d;
	}
	// spoke → hub + home first (L4.4), then siblings, then other clusters.
	$hubs = array( 'plot' => 'construction-cost-guides', 'city' => 'construction-cost-by-city', 'material' => 'construction-material-rates' );
	$pick = array();
	if ( isset( $hubs[ $cluster ] ) && isset( $same[ $hubs[ $cluster ] ] ) ) $pick[ $hubs[ $cluster ] ] = $same[ $hubs[ $cluster ] ];
	if ( 'home' !== $key ) $pick['home'] = $reg['home'];
	$sib = array_diff_key( $same, $pick );
	// nearest siblings in registry order
	$keys = array_keys( $reg );
	$pos = array_search( $key, $keys, true );
	uksort( $sib, function ( $a, $b ) use ( $keys, $pos ) { return abs( array_search( $a, $keys, true ) - $pos ) - abs( array_search( $b, $keys, true ) - $pos ); } );
	foreach ( $sib as $k => $d ) { if ( count( $pick ) >= 5 ) break; $pick[ $k ] = $d; }
	foreach ( $other as $k => $d ) { if ( count( $pick ) >= 6 ) break; if ( 'hub' === $d['type'] ) $pick[ $k ] = $d; }
	$figs = hcc_headline_figures();
	$h = '<nav class="related-calcs" aria-labelledby="related-heading"><h2 id="related-heading">Related calculators &amp; guides</h2><div class="related-grid">';
	foreach ( $pick as $k => $d ) {
		$h .= '<a class="related-card" href="' . esc_url( hcc_page_url( $k ) ) . '"><strong>' . esc_html( $d['label'] ) . '</strong><span>' . esc_html( isset( $figs[ $k ] ) ? $figs[ $k ] : $d['card'] ) . '</span></a>';
	}
	return $h . '</div></nav>';
}

/** Home: T1.6 — every new page reachable from the home page. */
function hcc_home_more_guides() {
	$groups = array( 'plot' => 'By plot size', 'city' => 'By city', 'material' => 'Materials & data' );
	$reg = hcc_page_registry();
	$figs = hcc_headline_figures();
	$h = '<section id="more-guides" class="more-guides" aria-labelledby="more-guides-heading"><div class="guide-title-row"><h2 id="more-guides-heading">Explore All Cost Guides &amp; Calculators</h2></div><div class="title-underline" aria-hidden="true"></div><div class="more-grid">';
	foreach ( $groups as $cl => $title ) {
		$h .= '<div class="more-col"><h3>' . esc_html( $title ) . '</h3><ul>';
		foreach ( $reg as $k => $d ) {
			if ( $d['cluster'] !== $cl || ! hcc_page_exists( $k ) ) continue;
			$h .= '<li><a href="' . esc_url( hcc_page_url( $k ) ) . '">' . esc_html( $d['label'] ) . '</a>' . ( isset( $figs[ $k ] ) ? ' <span class="hub-fig">' . esc_html( $figs[ $k ] ) . '</span>' : '' ) . '</li>';
		}
		$h .= '</ul></div>';
	}
	return $h . '</div></section>';
}

function hcc_about_additions() {
	$u = hcc_author_user();
	$name = hcc_author_name();
	$li = hcc_opt( 'author_linkedin' );
	$cred = hcc_opt( 'author_credentials' );
	$h = '<h2>Who builds this calculator</h2><div class="author-box">' . ( $u ? get_avatar( $u->ID, 96, '', $name, array( 'class' => 'author-photo', 'loading' => 'lazy' ) ) : '' ) . '<div><p class="author-by"><strong>' . esc_html( $name ) . '</strong> — founder, ConstructionCostPK</p><p>' . esc_html( hcc_author_bio() ) . '</p>'
		. ( $cred ? '<p>' . esc_html( $cred ) . '</p>' : '' )
		. ( $li ? '<p><a href="' . esc_url( $li ) . '" rel="me noopener" target="_blank">' . esc_html( $name ) . ' on LinkedIn</a></p>' : '' ) . '</div></div>';
	$h .= '<h2>Why this site exists</h2><p>Most construction cost figures online are either a single flat number or a lead form for a contractor. ConstructionCostPK shows every assumption behind its estimate, lets you replace any rate with your own quotation, and never asks for your phone number.</p>';
	$h .= '<h2>How rates are collected</h2><p>Default rates for cement, steel, bricks, sand and crush are set from Pakistani supplier and marketplace price lists and reviewed periodically; the latest review was on <strong>' . esc_html( hcc_rates_date_human() ) . '</strong>. Labour and finishing rates per sq ft, city factors and quantity factors are documented in full on our <a href="' . esc_url( home_url( '/methodology/' ) ) . '">methodology page</a>. If you have a recent quotation that disagrees with our defaults, please <a href="' . esc_url( home_url( '/contact-us/' ) ) . '">tell us</a>.</p>';
	$log = hcc_opt( 'changelog' );
	$h .= '<h2>Update log</h2><ul class="changelog">';
	if ( $log ) {
		foreach ( preg_split( '/\r?\n/', $log ) as $line ) if ( trim( $line ) ) $h .= '<li>' . esc_html( trim( $line ) ) . '</li>';
	} else {
		$h .= '<li><strong>13 September 2026</strong> — site launched with the main calculator, grey structure calculator and 5/7/10 Marla &amp; 1 Kanal guides.</li>';
		$h .= '<li><strong>' . esc_html( hcc_rates_date_human() ) . '</strong> — default material rates reviewed.</li>';
		$h .= '<li><strong>September 2026</strong> — added 3 &amp; 4 Marla guides, Lahore/Karachi/Islamabad city pages, cement and steel rate pages, methodology and the monthly cost index.</li>';
	}
	return $h . '</ul>';
}

function hcc_contact_additions() {
	$email = hcc_opt( 'contact_email' );
	$wa = preg_replace( '/[^0-9]/', '', (string) hcc_opt( 'whatsapp' ) );
	$city = hcc_opt( 'contact_city' );
	$h = '<h2>Send us a message</h2>' . hcc_contact_form_html();
	$items = array();
	if ( $email && is_email( $email ) ) $items[] = '<li><strong>Email:</strong> <a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a></li>';
	if ( $wa ) $items[] = '<li><strong>WhatsApp:</strong> <a href="https://wa.me/' . esc_attr( $wa ) . '" rel="noopener" target="_blank">+' . esc_html( $wa ) . '</a></li>';
	if ( $city ) $items[] = '<li><strong>Based in:</strong> ' . esc_html( $city ) . ', Pakistan</li>';
	if ( $items ) $h .= '<h2>Other ways to reach us</h2><ul>' . implode( '', $items ) . '</ul>';
	return $h;
}

function hcc_privacy_additions() {
	return '<h2>Contact Form</h2><p>If you use our contact form, we receive the name, email address and message you type so we can reply. We do not add you to any mailing list and we delete messages we no longer need.</p>'
		. '<h2>Optional Estimate Feedback</h2><p>After calculating, you can optionally tell us whether the estimate was close to your real cost. Only if you click a feedback button do we store your answer together with the non-personal estimate details (page, city, construction type, covered area, estimated total and any actual cost you choose to type). No name, email or IP address is stored with it. We may publish aggregated results (for example, "most visitors in Lahore said estimates were within 10%").</p>'
		. '<h2>Cookie Consent</h2><p>Analytics (Google Analytics 4 and/or Microsoft Clarity) only set cookies after you click "Accept" on our cookie notice. If you decline, analytics run in a cookieless, anonymised mode or not at all. You can change your choice at any time using the "Cookie settings" link in the footer.</p>';
}

/* --------------------------------------------------------------------------
 * The main filter.
 * ------------------------------------------------------------------------ */
function hcc_enhance_content( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || is_feed() ) return $content;
	if ( get_query_var( 'hcc_embed' ) ) return $content;
	$key = hcc_page_key();
	if ( ! $key ) return $content;
	$reg = hcc_page_registry();
	$type = $reg[ $key ]['type'];

	$top = $bottom = '';
	if ( in_array( $type, array( 'calculator', 'guide', 'hub' ), true ) ) {
		$cite = hcc_citable_sentence( $key );
		if ( 'home' !== $key ) {
			$top .= hcc_page_meta_line( $key );
			$GLOBALS['hcc_cite'] = $cite; // printed inside the Sources box at the end
		} elseif ( $cite ) {
			$bottom .= '<section class="static-page home-cite"><div class="card"><p class="citable">' . esc_html( $cite ) . '</p></div></section>';
		}
		if ( 'home' === $key ) $bottom .= hcc_home_more_guides();
		if ( in_array( $key, array( 'construction-cost-guides' ), true ) ) {
			$top .= '<h2>All guides at a glance</h2>' . hcc_sc_hub_list( array( 'cluster' => 'plot' ) ) . '<p>More: <a href="' . esc_url( home_url( '/construction-cost-by-city/' ) ) . '">construction cost by city</a> · <a href="' . esc_url( home_url( '/construction-material-rates/' ) ) . '">material rates</a> · <a href="' . esc_url( home_url( '/pakistan-construction-cost-index/' ) ) . '">monthly cost index</a></p>';
		}
		$wrap_open = 'home' === $key ? '<section class="static-page home-trust"><div class="card">' : '';
		$wrap_close = 'home' === $key ? '</div></section>' : '';
		$bottom .= $wrap_open . hcc_sources_box() . hcc_author_box() . $wrap_close;
		$bottom .= 'home' === $key ? '<section class="static-page"><div class="card">' . hcc_related_block( $key ) . '</div></section>' : hcc_related_block( $key );
	} elseif ( 'about' === $type ) {
		$bottom .= hcc_about_additions();
	} elseif ( 'contact' === $type ) {
		$bottom .= hcc_contact_additions();
	} elseif ( 'privacy-policy' === $key ) {
		// Insert before the policy's closing "Contact" section when present.
		$add = hcc_privacy_additions();
		if ( false !== strpos( $content, '<h2>Changes to This Policy</h2>' ) ) {
			return str_replace( '<h2>Changes to This Policy</h2>', $add . '<h2>Changes to This Policy</h2>', $content );
		}
		$bottom .= $add;
	} elseif ( 'trust' === $type ) {
		$bottom .= hcc_author_box();
	}
	return $top . $content . $bottom;
}
add_filter( 'the_content', 'hcc_enhance_content', 20 );

/**
 * O3.9 — strip links that point at the page they are on (e.g. the Marla
 * standards guide linking to itself from the calculator hint). Runs after
 * shortcodes (priority 11) so it also catches links inside calculator markup.
 */
function hcc_remove_self_links( $content ) {
	if ( is_admin() || ! is_singular() || is_front_page() ) return $content;
	$url = get_permalink();
	if ( ! $url ) return $content;
	$pattern = '#<a\s[^>]*href=["\']' . preg_quote( untrailingslashit( $url ), '#' ) . '/?["\'][^>]*>(.*?)</a>#is';
	return preg_replace( $pattern, '$1', $content );
}
add_filter( 'the_content', 'hcc_remove_self_links', 99 );

/**
 * R8 — serve bundled hero infographics as WebP (PNG stays as the og:image),
 * and mark the first, above-the-fold hero fetchpriority="high".
 */
function hcc_hero_images( $content ) {
	if ( is_admin() ) return $content;
	$base = preg_quote( get_template_directory_uri() . '/assets/img/', '#' );
	$content = preg_replace( '#(' . $base . 'hero-[a-z0-9-]+)\.png#', '$1.webp', $content );
	$done = false;
	$content = preg_replace_callback( '#<img\s[^>]*' . $base . 'hero-[^>]*>#', function ( $m ) use ( &$done ) {
		if ( $done ) return $m[0];
		$done = true;
		$tag = $m[0];
		if ( false === strpos( $tag, 'fetchpriority' ) ) $tag = str_replace( '<img ', '<img fetchpriority="high" decoding="async" ', $tag );
		return $tag;
	}, $content );
	return $content;
}
add_filter( 'the_content', 'hcc_hero_images', 100 );

/** T6.8 — preload the above-the-fold hero on pages that have one. */
function hcc_preload_hero() {
	$key = hcc_page_key();
	if ( ! $key ) return;
	$reg = hcc_page_registry();
	if ( empty( $reg[ $key ]['hero'] ) || 'grey-structure-cost-calculator' === $key ) return;
	$url = get_template_directory_uri() . '/assets/img/' . $reg[ $key ]['hero'] . '.webp?ver=' . HCC_VERSION;
	echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" type="image/webp" fetchpriority="high">' . "\n";
}
add_action( 'wp_head', 'hcc_preload_hero', 2 );
