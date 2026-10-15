<?php
/**
 * v1.8.0 — shared data layer: page registry (clusters, SEO titles/descriptions,
 * hero images), engine convenience wrappers, brand constants and owner
 * settings accessors. Every other v1.8.0 module reads from here so titles,
 * figures and links can never drift apart.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HCC_BRAND', 'ConstructionCostPK' );
define( 'HCC_BRAND_ALT', 'House Construction Cost Calculator Pakistan' );
define( 'HCC_OWNER_NAME', 'Ali Ahmad' );
define( 'HCC_OWNER_EMAIL', '6977980@gmail.com' );
define( 'HCC_OWNER_SLUG', 'ali-ahmad' );
define( 'HCC_RATES_DATE_DEFAULT', '2026-10-15' );

/* --------------------------------------------------------------------------
 * Owner settings (Appearance → Customize → ConstructionCostPK Settings).
 * ------------------------------------------------------------------------ */
function hcc_opt( $key, $default = '' ) {
	$v = get_theme_mod( 'hcc_' . $key, $default );
	return is_string( $v ) ? trim( $v ) : $v;
}

/** Date the default material/labour rates were last reviewed (Y-m-d). */
function hcc_rates_date() {
	$d = hcc_opt( 'rates_updated', HCC_RATES_DATE_DEFAULT );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : HCC_RATES_DATE_DEFAULT;
}
function hcc_rates_date_human() {
	return date_i18n( 'j F Y', strtotime( hcc_rates_date() . ' 12:00:00' ) );
}

function hcc_social_profiles() {
	$out = array();
	foreach ( array( 'facebook', 'youtube', 'linkedin', 'x', 'instagram', 'pinterest' ) as $k ) {
		$u = hcc_opt( 'social_' . $k );
		if ( $u && wp_http_validate_url( $u ) ) $out[ $k ] = esc_url_raw( $u );
	}
	return $out;
}

function hcc_logo_url() {
	return get_template_directory_uri() . '/assets/img/constructioncostpk-logo-512.png';
}

/* --------------------------------------------------------------------------
 * Engine wrappers.
 * ------------------------------------------------------------------------ */
function hcc_estimate( $plot_sqft, $storeys, $type = 'complete', $quality = 'standard', $city = 'lahore', $covered = null ) {
	if ( null === $covered ) $covered = hcc_suggest_covered_area( $plot_sqft, $storeys );
	$r = hcc_compute_estimate( array(
		'coveredArea' => $covered, 'storeys' => $storeys, 'hasBasement' => false, 'basementArea' => 0,
		'hasBoundaryWall' => false, 'wallLength' => 0, 'hasApprovalFee' => false, 'plotSqft' => $plot_sqft,
		'constructionType' => $type, 'quality' => $quality, 'designComplexity' => 'typical', 'city' => $city,
		'bedrooms' => 3, 'bathrooms' => 3, 'kitchens' => 1, 'rates' => hcc_default_calc_rates(),
	) );
	$r['covered'] = $covered;
	return $r;
}
/** Cost per sq ft for 1,000 sq ft single storey — the "headline" rate. */
function hcc_rate_per_sqft( $type = 'complete', $city = 'lahore', $quality = 'standard' ) {
	$r = hcc_estimate( 0, 1, $type, $quality, $city, 1000 );
	return $r['costPerSqft'];
}
/**
 * 4,065,450 → "40.7 lakh"; 14,346,172 → "1.43 crore". v1.9.0: rounds before
 * choosing the unit (99.96 lakh → "1 crore", not "100 lakh") and only trims
 * zeros after a decimal point (precision 0 used to print 40 lakh as "4").
 */
function hcc_lakh_crore( $n, $precision = null ) {
	$lp = null === $precision ? 1 : (int) $precision;
	if ( round( $n / 100000, $lp ) >= 100 ) {
		return hcc_trim_number( number_format( $n / 10000000, null === $precision ? 2 : (int) $precision ) ) . ' crore';
	}
	return hcc_trim_number( number_format( $n / 100000, $lp ) ) . ' lakh';
}
function hcc_trim_number( $s ) {
	return false !== strpos( $s, '.' ) ? rtrim( rtrim( $s, '0' ), '.' ) : $s;
}
/** The one year used in titles and copy ("2026") — the year of the rates review. */
function hcc_year() {
	return gmdate( 'Y', strtotime( hcc_rates_date() . ' 12:00:00' ) );
}
/** Formatted figures for a plot-size guide page (used inside page templates). */
function hcc_plot_page_figs( $plot_sqft, $storeys ) {
	$c = hcc_estimate( $plot_sqft, $storeys );
	$g = hcc_estimate( $plot_sqft, $storeys, 'grey' );
	$e = hcc_estimate( $plot_sqft, $storeys, 'complete', 'economy' );
	$p = hcc_estimate( $plot_sqft, $storeys, 'complete', 'premium' );
	return array(
		'covered' => number_format( $c['covered'] ),
		'coverage' => round( hcc_coverage_ratio( $plot_sqft ) * 100 ),
		'c' => hcc_format_pkr( $c['grandTotal'] ), 'c_psf' => hcc_format_pkr( $c['costPerSqft'] ), 'c_lakh' => hcc_lakh_crore( $c['grandTotal'] ),
		'g' => hcc_format_pkr( $g['grandTotal'] ), 'g_psf' => hcc_format_pkr( $g['costPerSqft'] ), 'g_lakh' => hcc_lakh_crore( $g['grandTotal'] ),
		'e' => hcc_format_pkr( $e['grandTotal'] ), 'e_psf' => hcc_format_pkr( $e['costPerSqft'] ),
		'p' => hcc_format_pkr( $p['grandTotal'] ), 'p_psf' => hcc_format_pkr( $p['costPerSqft'] ),
		'q_cement' => number_format( round( $g['qty']['cementBags'] ) ), 'q_steel' => number_format( round( $g['qty']['steelKg'] ) ),
		'q_bricks' => number_format( round( $g['qty']['bricks'] ) ), 'q_sand' => number_format( round( $g['qty']['sandCft'] ) ),
		'q_crush' => number_format( round( $g['qty']['crushCft'] ) ),
	);
}
function hcc_city_labels() {
	return array(
		'lahore' => 'Lahore', 'karachi' => 'Karachi', 'islamabad' => 'Islamabad', 'rawalpindi' => 'Rawalpindi',
		'faisalabad' => 'Faisalabad', 'multan' => 'Multan', 'peshawar' => 'Peshawar', 'gujranwala' => 'Gujranwala',
		'sialkot' => 'Sialkot', 'hyderabad' => 'Hyderabad', 'sargodha' => 'Sargodha',
	);
}

/* --------------------------------------------------------------------------
 * Page registry. cluster: plot | city | material | core | trust.
 * type: calculator (has a calculator), guide (article), hub, trust, legal.
 * ------------------------------------------------------------------------ */
function hcc_page_registry() {
	return array(
		'home' => array( 'cluster' => 'core', 'type' => 'calculator', 'label' => 'House Construction Cost Calculator', 'card' => 'Main calculator — any plot size, 11 cities, PDF report' ),
		'grey-structure-cost-calculator' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => 'Grey Structure Cost Calculator', 'card' => 'Structural shell only — cement, steel, bricks & labour', 'hero' => 'grey-structure-cost-per-sq-ft-by-city' ),
		'3-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '3 Marla House Cost', 'card' => '675 sq ft plot — compact single storey budget', 'hero' => 'hero-3-marla' ),
		'4-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '4 Marla House Cost', 'card' => '900 sq ft plot — 2-bedroom family home', 'hero' => 'hero-4-marla' ),
		'5-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '5 Marla House Cost', 'card' => '1,125 sq ft plot — the most common first home', 'hero' => 'hero-5-marla' ),
		'7-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '7 Marla House Cost', 'card' => '1,575 sq ft plot — 3-bedroom step-up size', 'hero' => 'hero-7-marla' ),
		'10-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '10 Marla House Cost', 'card' => '2,250 sq ft plot — double storey, 4–5 bedrooms', 'hero' => 'hero-10-marla' ),
		'1-kanal-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '1 Kanal House Cost', 'card' => '4,500 sq ft plot — large double storey home', 'hero' => 'hero-1-kanal' ),
		'marla-kanal-standards-pakistan' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => 'Marla & Kanal Standards', 'card' => '225, 250, 272 or 272.25 sq ft per Marla?', 'hero' => 'hero-marla-standards' ),
		'construction-cost-guides' => array( 'cluster' => 'plot', 'type' => 'hub', 'label' => 'All Cost Guides (Plot Sizes)', 'card' => 'Every plot-size guide in one place' ),
		'construction-cost-by-city' => array( 'cluster' => 'city', 'type' => 'hub', 'label' => 'Construction Cost by City', 'card' => 'Per sq ft rates for 11 Pakistani cities' ),
		'construction-cost-lahore' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => 'Construction Cost in Lahore', 'card' => 'LDA, DHA & Bahria — Lahore per sq ft rates' ),
		'construction-cost-karachi' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => 'Construction Cost in Karachi', 'card' => '120 / 240 / 400 sq yd plots, SBCA approvals' ),
		'construction-cost-islamabad' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => 'Construction Cost in Islamabad', 'card' => 'CDA sector plot sizes & per sq ft rates' ),
		'construction-material-rates' => array( 'cluster' => 'material', 'type' => 'hub', 'label' => 'Construction Material Rates', 'card' => 'Cement, steel, bricks, sand & crush rates' ),
		'cement-rate-today-pakistan' => array( 'cluster' => 'material', 'type' => 'guide', 'label' => 'Cement Rate in Pakistan', 'card' => 'Bag price, bags per house & price impact' ),
		'steel-saria-rate-today' => array( 'cluster' => 'material', 'type' => 'guide', 'label' => 'Steel (Saria) Rate', 'card' => 'Per kg / per ton, grades, sutar sizes' ),
		'pakistan-construction-cost-index' => array( 'cluster' => 'material', 'type' => 'guide', 'label' => 'Pakistan Construction Cost Index', 'card' => 'Monthly city-wise cost per sq ft' ),
		'methodology' => array( 'cluster' => 'trust', 'type' => 'trust', 'label' => 'Methodology', 'card' => 'Formulas, quantities & limitations' ),
		'embed-construction-cost-calculator' => array( 'cluster' => 'trust', 'type' => 'trust', 'label' => 'Embed the Calculator', 'card' => 'Free widget for your website' ),
		'about-us' => array( 'cluster' => 'trust', 'type' => 'about', 'label' => 'About ConstructionCostPK' ),
		'contact-us' => array( 'cluster' => 'trust', 'type' => 'contact', 'label' => 'Contact' ),
		'privacy-policy' => array( 'cluster' => 'legal', 'type' => 'legal', 'label' => 'Privacy Policy' ),
		'disclaimer' => array( 'cluster' => 'legal', 'type' => 'legal', 'label' => 'Disclaimer' ),
	);
}

/** Registry key for the current (or given) page, or '' if unmanaged. */
function hcc_page_key( $post = null ) {
	if ( null === $post ) {
		if ( is_front_page() ) return 'home';
		if ( ! is_singular( 'page' ) ) return '';
		$post = get_queried_object();
	}
	$post = get_post( $post );
	if ( ! $post ) return '';
	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) return 'home';
	$reg = hcc_page_registry();
	return isset( $reg[ $post->post_name ] ) ? $post->post_name : '';
}
function hcc_page_url( $key ) {
	if ( 'home' === $key ) return home_url( '/' );
	return home_url( '/' . $key . '/' );
}
function hcc_page_exists( $key ) {
	if ( 'home' === $key ) return true;
	static $cache = array();
	if ( ! isset( $cache[ $key ] ) ) {
		$p = get_page_by_path( $key );
		$cache[ $key ] = ( $p && 'publish' === $p->post_status );
	}
	return $cache[ $key ];
}

/** Short amount for a title: "47 Lakh" or "1.43 Crore". */
function hcc_title_amount( $n ) {
	if ( round( $n / 100000 ) >= 100 ) return hcc_trim_number( number_format( $n / 10000000, 2 ) ) . ' Crore';
	return number_format( round( $n / 100000 ) ) . ' Lakh';
}

/**
 * SEO titles (O1) and meta descriptions (O2). Written into Rank Math's own
 * per-page fields once by the v1.8.0 migration (so they stay editable in the
 * Rank Math UI), and also used by the theme's fallback meta output when no
 * SEO plugin is active. See docs: "Where to update titles & figures".
 */
function hcc_seo_meta_map() {
	$l = hcc_rate_per_sqft( 'complete' );
	$g = hcc_rate_per_sqft( 'grey' );
	$y = hcc_year();
	$e5c = hcc_estimate( 1125, 1 ); $e5g = hcc_estimate( 1125, 1, 'grey' );
	return array(
		'home' => array(
			'title' => 'House Construction Cost Calculator Pakistan ' . $y . ' – Free, Per Sq Ft',
			'desc'  => 'A complete house in Pakistan costs about PKR ' . number_format( round( $l ) ) . '/sq ft, grey structure PKR ' . number_format( round( $g ) ) . '/sq ft (' . $y . ', Lahore). Free calculator for your city + PDF.',
		),
		'grey-structure-cost-calculator' => array(
			'title' => 'Grey Structure Cost Calculator Pakistan ' . $y . ' (Per Sq Ft Rates)',
			'desc'  => 'Grey structure costs about PKR ' . number_format( round( $g ) ) . ' per sq ft in Lahore (' . $y . ') — PKR ' . hcc_lakh_crore( $e5g['grandTotal'] ) . ' for a 5 Marla house. Free calculator with materials & PDF.',
		),
		'5-marla-house-construction-cost' => array(
			'title' => '5 Marla House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 1125, 1 )['grandTotal'] ) . ' (Calculator)',
			'desc'  => 'A 5 Marla house costs about PKR ' . hcc_lakh_crore( $e5c['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( $e5g['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator for your city with PDF report.',
		),
		'7-marla-house-construction-cost' => array(
			'title' => '7 Marla House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 1575, 1 )['grandTotal'] ) . ' (Calculator)',
			'desc'  => 'A 7 Marla house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 1575, 1 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 1575, 1, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator for your city with PDF report.',
		),
		'10-marla-house-construction-cost' => array(
			'title' => '10 Marla House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 2250, 2 )['grandTotal'] ),
			'desc'  => 'A double storey 10 Marla house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 2250, 2 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 2250, 2, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator with PDF.',
		),
		'1-kanal-house-construction-cost' => array(
			'title' => '1 Kanal House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 4500, 2 )['grandTotal'] ),
			'desc'  => 'A double storey 1 Kanal house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 4500, 2 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 4500, 2, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator with PDF.',
		),
		'marla-kanal-standards-pakistan' => array(
			'title' => '1 Marla in Sq Ft: Pakistan\'s 4 Marla & Kanal Standards (' . $y . ')',
			'desc'  => '1 Marla is 225 sq ft in most societies, but 250, 272 and 272.25 sq ft standards also exist — so 1 Kanal is 4,500–5,445 sq ft. See the table, then calculate.',
		),
		'construction-cost-guides' => array(
			'title' => 'Construction Cost Guides Pakistan ' . $y . ' – Marla, Kanal & Per Sq Ft',
			'desc'  => 'Every ConstructionCostPK guide in one place: 3 to 10 Marla and 1 Kanal house costs, grey structure, city rates and material prices, with ' . $y . ' figures.',
		),
		'about-us' => array(
			'title' => 'About ConstructionCostPK – Who Builds This Calculator',
			'desc'  => 'About ConstructionCostPK: a free, independent house construction cost calculator for Pakistan run by Ali Ahmad — why we built it and how estimates work.',
		),
		'contact-us' => array(
			'title' => 'Contact ConstructionCostPK',
			'desc'  => 'Contact ConstructionCostPK to report an outdated rate, suggest a city or ask about the house construction cost calculator. We read every message.',
		),
		'privacy-policy' => array(
			'title' => 'Privacy Policy – ConstructionCostPK',
			'desc'  => 'ConstructionCostPK privacy policy: calculator inputs stay in your browser, analytics run only with consent, and how contact and feedback data are used.',
		),
		'disclaimer' => array(
			'title' => 'Disclaimer – ConstructionCostPK',
			'desc'  => 'ConstructionCostPK disclaimer: our construction cost figures are planning estimates, not quotations. Always confirm with a licensed contractor or engineer.',
		),
		'3-marla-house-construction-cost' => array(
			'title' => '3 Marla House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 675, 1 )['grandTotal'] ) . ' (Calculator)',
			'desc'  => 'A 3 Marla house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 675, 1 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 675, 1, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator for your city with PDF report.',
		),
		'4-marla-house-construction-cost' => array(
			'title' => '4 Marla House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 900, 1 )['grandTotal'] ) . ' (Calculator)',
			'desc'  => 'A 4 Marla house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 900, 1 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 900, 1, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator for your city with PDF report.',
		),
		'construction-cost-lahore' => array(
			'title' => 'Construction Cost per Sq Ft in Lahore ' . $y . ' (LDA, DHA, Bahria)',
			'desc'  => 'Construction cost in Lahore is about PKR ' . number_format( round( hcc_rate_per_sqft( 'complete', 'lahore' ) ) ) . '/sq ft complete and PKR ' . number_format( round( hcc_rate_per_sqft( 'grey', 'lahore' ) ) ) . '/sq ft grey structure (' . $y . '). 5 Marla to 1 Kanal costs + free calculator.',
		),
		'construction-cost-karachi' => array(
			'title' => 'Construction Cost in Karachi ' . $y . ': Per Sq Ft & Per Sq Yd Plots',
			'desc'  => 'Building in Karachi costs about PKR ' . number_format( round( hcc_rate_per_sqft( 'complete', 'karachi' ) ) ) . '/sq ft complete, PKR ' . number_format( round( hcc_rate_per_sqft( 'grey', 'karachi' ) ) ) . '/sq ft grey (' . $y . '). 120, 240 & 400 sq yd house costs + free calculator.',
		),
		'construction-cost-islamabad' => array(
			'title' => 'Construction Cost Islamabad per Sq Ft ' . $y . ' (CDA Plot Sizes)',
			'desc'  => 'Construction in Islamabad costs about PKR ' . number_format( round( hcc_rate_per_sqft( 'complete', 'islamabad' ) ) ) . '/sq ft complete, PKR ' . number_format( round( hcc_rate_per_sqft( 'grey', 'islamabad' ) ) ) . '/sq ft grey (' . $y . '). 25x50 to 50x90 plot costs + free calculator.',
		),
		'construction-cost-by-city' => array(
			'title' => 'Construction Cost by City in Pakistan ' . $y . ' (Per Sq Ft Table)',
			'desc'  => 'Compare ' . $y . ' construction cost per sq ft in Lahore, Karachi, Islamabad, Rawalpindi, Faisalabad, Multan, Peshawar and more — grey structure and complete.',
		),
		'construction-material-rates' => array(
			'title' => 'Material Rates Pakistan ' . $y . ' – Cement, Steel, Bricks, Sand',
			'desc'  => 'Construction material rates used by our calculator: cement per bag, steel per kg, bricks per 1,000, sand and crush per cft — with rate date and notes.',
		),
		'cement-rate-today-pakistan' => array(
			'title' => 'Cement Rate in Pakistan Today (' . $y . '): Bag Price & Bags Needed',
			'desc'  => 'Cement rate in Pakistan: planning rate PKR ' . number_format( hcc_default_calc_rates()['cement'] ) . ' per 50 kg bag (' . $y . '). Bags needed for 3 Marla to 1 Kanal houses and how a price change moves your budget.',
		),
		'steel-saria-rate-today' => array(
			'title' => 'Saria Rate Today in Pakistan (' . $y . '): Steel Price per Kg & Ton',
			'desc'  => 'Steel (saria) planning rate: PKR ' . number_format( hcc_default_calc_rates()['steel'] ) . ' per kg (' . $y . '). Grade 40 vs 60, sutar sizes, weight per foot and steel needed for 5 Marla to 1 Kanal.',
		),
		'pakistan-construction-cost-index' => array(
			'title' => 'Pakistan Construction Cost Index ' . date_i18n( 'F Y' ) . ' (City-wise)',
			'desc'  => 'Monthly ConstructionCostPK index: city-wise cost per sq ft for grey structure and complete houses in Pakistan, cement and steel rates, monthly change.',
		),
		'methodology' => array(
			'title' => 'How We Calculate Construction Cost – Methodology',
			'desc'  => 'How ConstructionCostPK estimates cost: formulas, cement, steel, brick, sand and crush quantities per sq ft, city and quality factors, and limitations.',
		),
		'embed-construction-cost-calculator' => array(
			'title' => 'Embed a Free Construction Cost Calculator on Your Website',
			'desc'  => 'Add the free ConstructionCostPK house construction cost calculator to your blog or real estate website with one line of iframe code. No sign-up, no fees.',
		),
	);
}
