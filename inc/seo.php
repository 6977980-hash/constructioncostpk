<?php
/**
 * v1.8.0 — technical SEO: T2 (author/privacy), T3/T4 (Rank Math graph),
 * T5 (empty menu links), T7.1/G1 (llms.txt), T7.3/T7.4 (locale),
 * T7.5 (ads.txt), G8 (robots.txt), T6.4 (LiteSpeed-safe calculator.js).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------- T2.4 — hide the public users REST endpoint when logged out ---------- */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

/* ---------- T2.2 / T2.5 — author enumeration + old slug redirect ---------- */
add_action( 'template_redirect', function () {
	// /?author=1 enumeration → home (logged-out visitors only).
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) && ! is_admin() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
	// Old e-mail-based author slug → new slug.
	if ( is_404() ) {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		$old = (array) get_option( 'hcc_old_author_slugs', array() );
		foreach ( $old as $slug => $new ) {
			if ( 0 === strpos( $path, 'author/' . $slug ) ) {
				wp_safe_redirect( home_url( '/author/' . $new . '/' ), 301 );
				exit;
			}
		}
	}
}, 1 );

/** Never print an e-mail-looking display name anywhere on the front end. */
add_filter( 'the_author', function ( $name ) {
	return ( is_string( $name ) && false !== strpos( $name, '@' ) ) ? HCC_OWNER_NAME : $name;
} );
add_filter( 'get_the_author_display_name', function ( $name ) {
	return ( is_string( $name ) && false !== strpos( $name, '@' ) ) ? HCC_OWNER_NAME : $name;
} );

/* ---------- v1.9.0 — XML-RPC off (nothing on this site uses it; it is a brute-force target) ---------- */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () { return array(); } );
add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );
add_action( 'init', function () {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "XML-RPC is disabled on this site.\n";
		exit;
	}
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
}, 0 );

/* ---------- v1.9.0 — contact form page is never cached (its form carries a nonce) ---------- */
add_action( 'template_redirect', function () {
	if ( ! is_page( 'contact-us' ) ) return;
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'contact form nonce' );
} );

/* ---------- T7.3 — <html lang="en-PK"> ---------- */
add_filter( 'language_attributes', function ( $output ) {
	return str_replace( 'lang="en-US"', 'lang="en-PK"', $output );
} );
/* ---------- T7.4 — og:locale en_PK (Rank Math) ---------- */
add_filter( 'rank_math/opengraph/facebook/og_locale', function () { return 'en_PK'; } );

/* ---------- T5 — never render empty menu links ---------- */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
	return array_values( array_filter( $items, function ( $i ) {
		return '' !== trim( wp_strip_all_tags( (string) $i->title ) ) && '' !== trim( (string) $i->url );
	} ) );
} );

/* ---------- T6.4 — keep calculator.js out of LiteSpeed "Delay JS"/combine ---------- */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( in_array( $handle, array( 'hcc-calculator', 'hcc-consent' ), true ) ) {
		$tag = str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" ', $tag );
	}
	return $tag;
}, 10, 2 );

/* ---------- G8 — robots.txt: explicitly welcome AI/answer-engine crawlers ---------- */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) return $output;
	if ( false === stripos( $output, 'GPTBot' ) ) {
		$bots = array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'ClaudeBot', 'Claude-SearchBot', 'Google-Extended', 'Bingbot' );
		$add = "\n# AI / answer engines are welcome (ConstructionCostPK)\n";
		// A crawler obeys only its own group, so each group repeats the
		// wp-admin rule of "User-agent: *".
		foreach ( $bots as $b ) $add .= "User-agent: {$b}\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nAllow: /\n\n";
		$output = $add . "\n" . $output;
	}
	if ( false === stripos( $output, 'Sitemap:' ) ) {
		$output .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
	}
	return $output;
}, 99, 2 );

/* ---------- T7.1 / G1 — /llms.txt and T7.5 — /ads.txt served dynamically ---------- */
function hcc_llms_txt() {
	$reg = hcc_page_registry();
	$line = function ( $k, $title, $note = '' ) {
		return hcc_page_exists( $k ) ? '- [' . $title . '](' . hcc_page_url( $k ) . ')' . ( $note ? ': ' . $note : '' ) . "\n" : '';
	};
	$t  = "# ConstructionCostPK\n";
	$t .= "> Free house construction cost calculator for Pakistan (" . hcc_year() . " rates). Estimates grey\n> structure, complete and turnkey cost per sq ft, material quantities (cement, steel,\n> bricks, sand, crush) and an itemized PDF report. Rates in PKR, editable per city.\n\n";
	$t .= "Headline figures (Lahore, standard quality, rates dated " . hcc_rates_date() . "): complete house " . hcc_format_pkr( hcc_rate_per_sqft( 'complete' ) ) . "/sq ft; grey structure " . hcc_format_pkr( hcc_rate_per_sqft( 'grey' ) ) . "/sq ft; 5 Marla complete " . hcc_format_pkr( hcc_estimate( 1125, 1 )['grandTotal'] ) . ".\n\n";
	$t .= "## Calculators\n";
	$t .= '- [House Construction Cost Calculator](' . home_url( '/' ) . "): main tool, all cities\n";
	$t .= $line( 'grey-structure-cost-calculator', 'Grey Structure Cost Calculator' );
	$t .= "\n## Cost guides\n";
	foreach ( array( '3-marla', '4-marla', '5-marla', '7-marla', '10-marla', '1-kanal' ) as $s ) {
		$t .= $line( $s . '-house-construction-cost', ucwords( str_replace( '-', ' ', $s ) ) . ' house cost' );
	}
	$t .= $line( 'marla-kanal-standards-pakistan', 'Marla & Kanal standards' );
	$t .= $line( 'construction-cost-guides', 'All guides (hub)' );
	$t .= "\n## Cities\n";
	$t .= $line( 'construction-cost-by-city', 'Construction cost by city (hub)' );
	foreach ( array( 'lahore', 'karachi', 'islamabad' ) as $c ) $t .= $line( 'construction-cost-' . $c, 'Construction cost ' . ucfirst( $c ) );
	$t .= "\n## Materials & data\n";
	$t .= $line( 'construction-material-rates', 'Construction material rates (hub)' );
	$t .= $line( 'cement-rate-today-pakistan', 'Cement rate in Pakistan' );
	$t .= $line( 'steel-saria-rate-today', 'Steel (saria) rate' );
	$t .= $line( 'pakistan-construction-cost-index', 'Pakistan Construction Cost Index (monthly)' );
	$t .= "\n## About\n";
	$t .= '- [About & methodology](' . home_url( '/about-us/' ) . ")\n";
	$t .= $line( 'methodology', 'Methodology (formulas & assumptions)' );
	$t .= '- Rates last updated: ' . hcc_rates_date() . "\n";
	return $t;
}
add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?hcc_txt=llms', 'top' );
	add_rewrite_rule( '^ads\.txt$', 'index.php?hcc_txt=ads', 'top' );
} );
add_filter( 'query_vars', function ( $v ) { $v[] = 'hcc_txt'; $v[] = 'hcc_embed'; return $v; } );
/* Serve before WordPress routing so it works even if rewrite rules were not flushed yet. */
add_action( 'parse_request', function ( $wp ) {
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ), '/' );
	$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $home_path && 0 === strpos( $path, $home_path . '/' ) ) $path = substr( $path, strlen( $home_path ) + 1 );
	$which = ! empty( $wp->query_vars['hcc_txt'] ) ? $wp->query_vars['hcc_txt'] : ( 'llms.txt' === $path ? 'llms' : ( 'ads.txt' === $path ? 'ads' : '' ) );
	if ( ! $which ) return;
	if ( 'ads' === $which ) {
		$pub = preg_replace( '/[^a-z0-9-]/i', '', (string) hcc_opt( 'adsense_pub' ) );
		if ( ! $pub ) {
			// No publisher ID yet: a real 404. Falling through to WordPress
			// served a 200 HTML page here (a soft 404 crawlers indexed).
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			echo "Not found\n";
			exit;
		}
		$body = 'google.com, ' . ( 0 === strpos( $pub, 'pub-' ) ? $pub : 'pub-' . $pub ) . ", DIRECT, f08c47fec0942fa0\n";
	} else {
		$body = hcc_llms_txt();
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $body; // phpcs:ignore
	exit;
}, 0 );

/* ==========================================================================
 * T3 / T4 — Rank Math JSON-LD graph repair.
 * Root cause found on the live site: on the 6 calculator pages Rank Math
 * printed ONLY a BreadcrumbList (per-page Schema set to "None"), so the
 * Organization / WebSite / WebPage / Article nodes were missing, and the
 * knowledge graph was typed ["Person","Organization"] named after the domain.
 * The v1.8.0 migration fixes the stored settings; this filter guarantees the
 * output regardless (idempotent — it only adds what is missing).
 * ======================================================================== */
function hcc_graph_ids() {
	$h = home_url( '/' );
	return array( 'org' => $h . '#organization', 'person_old' => $h . '#person', 'website' => $h . '#website', 'logo' => $h . '#logo' );
}
function hcc_graph_replace_ids( $node, $from, $to ) {
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) $node[ $k ] = hcc_graph_replace_ids( $v, $from, $to );
		return $node;
	}
	return ( $node === $from ) ? $to : $node;
}
function hcc_org_node() {
	$ids = hcc_graph_ids();
	$node = array(
		'@type' => 'Organization', '@id' => $ids['org'], 'name' => HCC_BRAND, 'alternateName' => HCC_BRAND_ALT,
		'url' => home_url( '/' ),
		'logo' => array( '@type' => 'ImageObject', '@id' => $ids['logo'], 'url' => hcc_logo_url(), 'contentUrl' => hcc_logo_url(), 'width' => 512, 'height' => 512, 'caption' => HCC_BRAND, 'inLanguage' => 'en-US' ),
		'founder' => array( '@type' => 'Person', 'name' => HCC_OWNER_NAME ),
	);
	$same = array_values( hcc_social_profiles() );
	if ( $same ) $node['sameAs'] = $same;
	return $node;
}
function hcc_author_node() {
	$u = hcc_author_user();
	$id = $u ? get_author_posts_url( $u->ID ) : home_url( '/about-us/' ) . '#author';
	$n = array( '@type' => 'Person', '@id' => $id, 'name' => hcc_author_name(), 'url' => hcc_page_exists( 'about-us' ) ? hcc_page_url( 'about-us' ) : home_url( '/' ), 'worksFor' => array( '@id' => hcc_graph_ids()['org'] ) );
	$li = hcc_opt( 'author_linkedin' );
	if ( $li ) $n['sameAs'] = array( esc_url_raw( $li ) );
	if ( $u ) {
		$av = get_avatar_url( $u->ID, array( 'size' => 256 ) );
		if ( $av ) $n['image'] = array( '@type' => 'ImageObject', 'url' => $av );
	}
	return $n;
}
function hcc_rank_math_json_ld( $data, $jsonld = null ) {
	if ( ! is_array( $data ) ) return $data;
	$ids = hcc_graph_ids();
	$data = hcc_graph_replace_ids( $data, $ids['person_old'], $ids['org'] );

	$has = array();
	foreach ( $data as $k => $node ) {
		if ( ! is_array( $node ) ) continue;
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		foreach ( $types as $t ) $has[ $t ][] = $k;
		// T3.1/T3.2 — the site's knowledge-graph entity is an Organization named ConstructionCostPK.
		if ( isset( $node['@id'] ) && $node['@id'] === $ids['org'] ) {
			$org = hcc_org_node();
			$node['@type'] = 'Organization';
			$node['name'] = HCC_BRAND;
			$node['alternateName'] = HCC_BRAND_ALT;
			if ( empty( $node['logo'] ) ) $node['logo'] = $org['logo'];
			if ( ! empty( $org['sameAs'] ) ) $node['sameAs'] = array_values( array_unique( array_merge( isset( $node['sameAs'] ) ? (array) $node['sameAs'] : array(), $org['sameAs'] ) ) );
			$data[ $k ] = $node;
		}
		// T3.3 — WebSite name + alternate name.
		if ( in_array( 'WebSite', $types, true ) ) {
			$data[ $k ]['name'] = HCC_BRAND;
			$data[ $k ]['alternateName'] = HCC_BRAND_ALT;
			$data[ $k ]['publisher'] = array( '@id' => $ids['org'] );
			$data[ $k ]['inLanguage'] = 'en-US';
		}
		// T2.5 — no e-mail addresses as a person's name.
		if ( in_array( 'Person', $types, true ) && isset( $node['name'] ) && false !== strpos( (string) $node['name'], '@' ) ) {
			$data[ $k ]['name'] = hcc_author_name();
		}
		if ( isset( $node['author']['name'] ) && false !== strpos( (string) $node['author']['name'], '@' ) ) {
			$data[ $k ]['author']['name'] = hcc_author_name();
		}
	}

	if ( ! is_singular() && ! is_front_page() ) return $data;
	$key = hcc_page_key();
	if ( ! $key ) return $data;
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) $post = get_post( get_option( 'page_on_front' ) );
	if ( ! $post ) return $data;

	$url = 'home' === $key ? home_url( '/' ) : get_permalink( $post );
	$webpage_id = $url . '#webpage';
	$meta = hcc_seo_meta_map();

	// T4.1 — restore the global entities when Rank Math left them out.
	if ( empty( $has['Organization'] ) ) $data['publisher'] = hcc_org_node();
	if ( empty( $has['WebSite'] ) ) {
		$data['WebSite'] = array( '@type' => 'WebSite', '@id' => $ids['website'], 'url' => home_url( '/' ), 'name' => HCC_BRAND, 'alternateName' => HCC_BRAND_ALT, 'publisher' => array( '@id' => $ids['org'] ), 'inLanguage' => 'en-US' );
	}
	if ( empty( $has['WebPage'] ) && empty( $has['CollectionPage'] ) && empty( $has['AboutPage'] ) && empty( $has['ContactPage'] ) ) {
		$wp_node = array(
			'@type' => 'WebPage', '@id' => $webpage_id, 'url' => $url,
			'name' => isset( $meta[ $key ]['title'] ) ? $meta[ $key ]['title'] : get_the_title( $post ),
			'datePublished' => get_post_time( 'c', true, $post ), 'dateModified' => get_post_modified_time( 'c', true, $post ),
			'isPartOf' => array( '@id' => $ids['website'] ), 'inLanguage' => 'en-US',
		);
		if ( ! empty( $has['BreadcrumbList'] ) ) {
			$bk = $has['BreadcrumbList'][0];
			if ( isset( $data[ $bk ]['@id'] ) ) $wp_node['breadcrumb'] = array( '@id' => $data[ $bk ]['@id'] );
		}
		$data['WebPage'] = $wp_node;
	}

	// T4.2 — Article with author = Ali Ahmad, dates, publisher (calculator/guide pages).
	$reg = hcc_page_registry();
	$is_article_page = 'home' !== $key && in_array( $reg[ $key ]['type'], array( 'calculator', 'guide', 'hub', 'trust' ), true );
	$author = hcc_author_node();
	if ( $is_article_page ) {
		if ( ! empty( $has['Article'] ) || ! empty( $has['BlogPosting'] ) || ! empty( $has['NewsArticle'] ) ) {
			$ak = ! empty( $has['Article'] ) ? $has['Article'][0] : ( ! empty( $has['BlogPosting'] ) ? $has['BlogPosting'][0] : $has['NewsArticle'][0] );
			$data[ $ak ]['author'] = array( '@id' => $author['@id'], 'name' => $author['name'] );
			$data[ $ak ]['publisher'] = array( '@id' => $ids['org'] );
			$data[ $ak ]['inLanguage'] = 'en-US';
			if ( empty( $data[ $ak ]['dateModified'] ) ) $data[ $ak ]['dateModified'] = get_post_modified_time( 'c', true, $post );
		} else {
			$article = array(
				'@type' => 'Article', '@id' => $url . '#article',
				'headline' => isset( $meta[ $key ]['title'] ) ? $meta[ $key ]['title'] : get_the_title( $post ),
				'description' => isset( $meta[ $key ]['desc'] ) ? $meta[ $key ]['desc'] : '',
				'datePublished' => get_post_time( 'c', true, $post ),
				'dateModified' => get_post_modified_time( 'c', true, $post ),
				'author' => array( '@id' => $author['@id'], 'name' => $author['name'] ),
				'publisher' => array( '@id' => $ids['org'] ),
				'isPartOf' => array( '@id' => $webpage_id ),
				'mainEntityOfPage' => array( '@id' => $webpage_id ),
				'inLanguage' => 'en-US',
			);
			$thumb = get_the_post_thumbnail_url( $post, 'full' );
			if ( ! $thumb && ! empty( $reg[ $key ]['hero'] ) ) $thumb = get_template_directory_uri() . '/assets/img/' . $reg[ $key ]['hero'] . ( 'grey-structure-cost-calculator' === $key ? '.webp' : '.png' );
			if ( $thumb ) $article['image'] = array( '@type' => 'ImageObject', 'url' => $thumb );
			$data['hccArticle'] = $article;
		}
		// Author Person node (once).
		$found = false;
		foreach ( $data as $node ) if ( is_array( $node ) && isset( $node['@id'] ) && $node['@id'] === $author['@id'] ) { $found = true; break; }
		if ( ! $found ) $data['hccAuthor'] = $author;
		else foreach ( $data as $k => $node ) if ( is_array( $node ) && isset( $node['@id'] ) && $node['@id'] === $author['@id'] ) $data[ $k ] = array_merge( $node, $author );
	}
	return $data;
}
add_filter( 'rank_math/json_ld', 'hcc_rank_math_json_ld', 999, 2 );

/* ---------- Titles for new pages when Rank Math has no custom title yet ---------- */
add_filter( 'rank_math/frontend/title', function ( $title ) {
	$key = hcc_page_key();
	if ( ! $key || ( is_singular() && get_post_meta( get_queried_object_id(), 'rank_math_title', true ) ) ) return $title;
	$m = hcc_seo_meta_map();
	return isset( $m[ $key ]['title'] ) ? $m[ $key ]['title'] : $title;
} );
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	$key = hcc_page_key();
	if ( ! $key || ( is_singular() && get_post_meta( get_queried_object_id(), 'rank_math_description', true ) ) ) return $desc;
	$m = hcc_seo_meta_map();
	return isset( $m[ $key ]['desc'] ) ? $m[ $key ]['desc'] : $desc;
} );

/* ---------- Fallback <title> when no SEO plugin is active ---------- */
add_filter( 'pre_get_document_title', function ( $t ) {
	if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) return $t;
	$key = hcc_page_key();
	$m = hcc_seo_meta_map();
	return ( $key && isset( $m[ $key ]['title'] ) ) ? $m[ $key ]['title'] : $t;
}, 20 );

/* Don't let WordPress add a trailing slash to /llms.txt or /ads.txt. */
add_filter( 'redirect_canonical', function ( $redirect, $requested ) {
	return preg_match( '#/(llms|ads)\.txt/?$#', (string) wp_parse_url( $requested, PHP_URL_PATH ) ) ? false : $redirect;
}, 10, 2 );
