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
/** "Oct 2026" — the month of the last rates review, for titles. */
function hcc_rates_month() {
	return date_i18n( 'M Y', strtotime( hcc_rates_date() . ' 12:00:00' ) );
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
	$out = array(
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
	// Page HTML only: a no-break space keeps "PKR" with its number on phones.
	foreach ( array( 'c', 'c_psf', 'g', 'g_psf', 'e', 'e_psf', 'p', 'p_psf' ) as $k ) $out[ $k ] = str_replace( 'PKR ', 'PKR&nbsp;', $out[ $k ] );
	return $out;
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
/** v1.11 — every plot-size guide: variant => array( label, plot sq ft, usual storeys ). */
function hcc_plot_sizes() {
	return array(
		'3-marla' => array( '3 Marla', 675, 1 ), '4-marla' => array( '4 Marla', 900, 1 ), '5-marla' => array( '5 Marla', 1125, 1 ),
		'6-marla' => array( '6 Marla', 1350, 1 ), '7-marla' => array( '7 Marla', 1575, 1 ), '8-marla' => array( '8 Marla', 1800, 2 ),
		'10-marla' => array( '10 Marla', 2250, 2 ), '1-kanal' => array( '1 Kanal', 4500, 2 ), '2-kanal' => array( '2 Kanal', 9000, 2 ),
	);
}
/** variant => array( plot sq ft, storeys ). */
function hcc_plot_sizes_sqft() {
	return array_map( function ( $s ) { return array( $s[1], $s[2] ); }, hcc_plot_sizes() );
}
function hcc_page_registry() {
	return array(
		'home' => array( 'cluster' => 'core', 'type' => 'calculator', 'label' => 'House Construction Cost Calculator', 'card' => 'Main calculator — any plot size, 11 cities, PDF report' ),
		'grey-structure-cost-calculator' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => 'Grey Structure Cost Calculator', 'card' => 'Structural shell only — cement, steel, bricks & labour', 'hero' => 'grey-structure-cost-per-sq-ft-by-city' ),
		'3-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '3 Marla House Cost', 'card' => '675 sq ft plot — compact single storey budget', 'hero' => 'hero-3-marla' ),
		'4-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '4 Marla House Cost', 'card' => '900 sq ft plot — 2-bedroom family home', 'hero' => 'hero-4-marla' ),
		'5-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '5 Marla House Cost', 'card' => '1,125 sq ft plot — the most common first home', 'hero' => 'hero-5-marla' ),
		'6-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '6 Marla House Cost', 'card' => '1,350 sq ft plot — 3 bedrooms on one floor', 'hero' => 'hero-6-marla' ),
		'7-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '7 Marla House Cost', 'card' => '1,575 sq ft plot — 3-bedroom step-up size', 'hero' => 'hero-7-marla' ),
		'8-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '8 Marla House Cost', 'card' => '1,800 sq ft plot — 30 × 60 double storey', 'hero' => 'hero-8-marla' ),
		'10-marla-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '10 Marla House Cost', 'card' => '2,250 sq ft plot — double storey, 4–5 bedrooms', 'hero' => 'hero-10-marla' ),
		'1-kanal-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '1 Kanal House Cost', 'card' => '4,500 sq ft plot — large double storey home', 'hero' => 'hero-1-kanal' ),
		'2-kanal-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => '2 Kanal House Cost', 'card' => '9,000 sq ft plot — large family home', 'hero' => 'hero-2-kanal' ),
		'double-storey-house-construction-cost' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => 'Double Storey House Cost', 'card' => 'Single vs double storey for every plot size' ),
		'lanter-roof-slab-cost-pakistan' => array( 'cluster' => 'material', 'type' => 'calculator', 'label' => 'Lanter (Roof Slab) Cost', 'card' => 'Cement, steel & cost per sq ft of roof slab' ),
		'marla-kanal-standards-pakistan' => array( 'cluster' => 'plot', 'type' => 'calculator', 'label' => 'Marla & Kanal Standards', 'card' => '225, 250, 272 or 272.25 sq ft per Marla?', 'hero' => 'hero-marla-standards' ),
		'construction-cost-guides' => array( 'cluster' => 'plot', 'type' => 'hub', 'label' => 'All Cost Guides (Plot Sizes)', 'card' => 'Every plot-size guide in one place' ),
		'construction-cost-by-city' => array( 'cluster' => 'city', 'type' => 'hub', 'label' => 'Construction Cost by City', 'card' => 'Per sq ft rates for 11 Pakistani cities' ),
		'construction-cost-lahore' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => 'Construction Cost in Lahore', 'card' => 'LDA, DHA & Bahria — Lahore per sq ft rates' ),
		'construction-cost-karachi' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => 'Construction Cost in Karachi', 'card' => '120 / 240 / 400 sq yd plots, SBCA approvals' ),
		'120-sq-yard-house-construction-cost-karachi' => array( 'cluster' => 'city', 'type' => 'calculator', 'label' => '120 Sq Yard House Cost (Karachi)', 'card' => '1,080 sq ft plot — ground + 1 budget' ),
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
			'title' => '5 Marla House Cost ' . $y . ': PKR ' . hcc_title_amount( hcc_estimate( 1125, 1 )['grandTotal'] ) . ' Single, ' . hcc_title_amount( hcc_estimate( 1125, 2 )['grandTotal'] ) . ' Double',
			'desc'  => '5 Marla (1,125 sq ft) house in ' . $y . ': PKR ' . hcc_lakh_crore( hcc_estimate( 1125, 1 )['grandTotal'] ) . ' single storey, PKR ' . hcc_lakh_crore( hcc_estimate( 1125, 2 )['grandTotal'] ) . ' double, grey structure from PKR ' . hcc_lakh_crore( hcc_estimate( 1125, 1, 'grey' )['grandTotal'] ) . '. Lahore rates + calculator.',
		),
		'7-marla-house-construction-cost' => array(
			'title' => '7 Marla House Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 1575, 1 )['grandTotal'] ) . ' | 1,575 Sq Ft Plot',
			'desc'  => '7 Marla = 1,575 sq ft (175 sq yd). A single storey house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 1575, 1 )['grandTotal'] ) . ', double storey PKR ' . hcc_lakh_crore( hcc_estimate( 1575, 2 )['grandTotal'] ) . ', grey structure PKR ' . hcc_lakh_crore( hcc_estimate( 1575, 1, 'grey' )['grandTotal'] ) . ' (' . $y . ', Lahore).',
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
			'title' => '1 Marla = 225 Sq Ft, 1 Kanal = 20 Marla: Converter (' . $y . ')',
			'desc'  => '7 Marla = 1,575 sq ft (175 gaz), 4 Kanal = 80 Marla, 1 Marla = 25 sq yd. All 4 Marla standards (225 to 272.25 sq ft) with a Marla, Kanal & sq ft converter.',
		),
		'construction-cost-guides' => array(
			'title' => 'Construction Cost Guides Pakistan ' . $y . ' – Marla, Kanal & Per Sq Ft',
			'desc'  => 'Every ConstructionCostPK guide in one place: 3 Marla to 2 Kanal house costs, double storey, grey structure, lanter, city rates and material prices (' . $y . ').',
		),
		'6-marla-house-construction-cost' => array(
			'title' => '6 Marla House Cost ' . $y . ': PKR ' . hcc_title_amount( hcc_estimate( 1350, 1 )['grandTotal'] ) . ' Single, ' . hcc_title_amount( hcc_estimate( 1350, 2 )['grandTotal'] ) . ' Double',
			'desc'  => '6 Marla (1,350 sq ft) house in ' . $y . ': about PKR ' . hcc_lakh_crore( hcc_estimate( 1350, 1 )['grandTotal'] ) . ' single storey or ' . hcc_lakh_crore( hcc_estimate( 1350, 2 )['grandTotal'] ) . ' double, grey structure from PKR ' . hcc_lakh_crore( hcc_estimate( 1350, 1, 'grey' )['grandTotal'] ) . '. Lahore rates + calculator.',
		),
		'8-marla-house-construction-cost' => array(
			'title' => '8 Marla House Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 1800, 2 )['grandTotal'] ) . ' Double Storey',
			'desc'  => '8 Marla (1,800 sq ft, 30 × 60) double storey house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 1800, 2 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 1800, 2, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator.',
		),
		'2-kanal-house-construction-cost' => array(
			'title' => '2 Kanal House Construction Cost ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 9000, 2 )['grandTotal'] ),
			'desc'  => 'A double storey 2 Kanal (9,000 sq ft) house costs about PKR ' . hcc_lakh_crore( hcc_estimate( 9000, 2 )['grandTotal'] ) . ' complete or PKR ' . hcc_lakh_crore( hcc_estimate( 9000, 2, 'grey' )['grandTotal'] ) . ' grey structure (' . $y . ', Lahore). Free calculator.',
		),
		'double-storey-house-construction-cost' => array(
			'title' => 'Double Storey House Cost in Pakistan ' . $y . ' (All Plot Sizes)',
			'desc'  => 'Double storey house cost ' . $y . ': 5 Marla about PKR ' . hcc_lakh_crore( hcc_estimate( 1125, 2 )['grandTotal'] ) . ', 10 Marla PKR ' . hcc_lakh_crore( hcc_estimate( 2250, 2 )['grandTotal'] ) . '. Single vs double storey for 3 Marla to 2 Kanal, with calculator.',
		),
		'120-sq-yard-house-construction-cost-karachi' => array(
			'title' => '120 Sq Yard House Cost Karachi ' . $y . ': ~PKR ' . hcc_title_amount( hcc_estimate( 1080, 2, 'complete', 'standard', 'karachi' )['grandTotal'] ),
			'desc'  => '120 gaz (1,080 sq ft) ground + 1 house in Karachi costs about PKR ' . hcc_lakh_crore( hcc_estimate( 1080, 2, 'complete', 'standard', 'karachi' )['grandTotal'] ) . ' complete, PKR ' . hcc_lakh_crore( hcc_estimate( 1080, 2, 'grey', 'standard', 'karachi' )['grandTotal'] ) . ' grey structure (' . $y . '). Calculator inside.',
		),
		'lanter-roof-slab-cost-pakistan' => array(
			'title' => 'Lanter Cost per Sq Ft ' . $y . ': PKR ' . number_format( round( hcc_lanter_qty( 1000 )['per_sqft'] ) ) . ' Material (Calculator)',
			'desc'  => 'Lanter (roof slab) cost in Pakistan: about PKR ' . number_format( round( hcc_lanter_qty( 1000 )['per_sqft'] ) ) . '/sq ft material for 5 inch 1:2:4. Cement bags, steel kg and cost for any area (' . $y . ').',
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
			'title' => '3 Marla House Cost ' . $y . ': PKR ' . hcc_title_amount( hcc_estimate( 675, 1 )['grandTotal'] ) . ' Single, ' . hcc_title_amount( hcc_estimate( 675, 2 )['grandTotal'] ) . ' Double',
			'desc'  => '3 Marla (675 sq ft) house in ' . $y . ': about PKR ' . hcc_lakh_crore( hcc_estimate( 675, 1 )['grandTotal'] ) . ' single storey or ' . hcc_lakh_crore( hcc_estimate( 675, 2 )['grandTotal'] ) . ' double, grey structure from PKR ' . hcc_lakh_crore( hcc_estimate( 675, 1, 'grey' )['grandTotal'] ) . '. Lahore rates + calculator.',
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
			'title' => 'Islamabad Construction Cost ' . $y . ': PKR ' . number_format( round( hcc_rate_per_sqft( 'complete', 'islamabad' ) ) ) . '/Sq Ft (CDA, DHA)',
			'desc'  => 'Building in Islamabad costs about PKR ' . number_format( round( hcc_rate_per_sqft( 'complete', 'islamabad' ) ) ) . '/sq ft complete and PKR ' . number_format( round( hcc_rate_per_sqft( 'grey', 'islamabad' ) ) ) . ' grey (' . $y . '). 25x50 to 50x90 plots, Rawalpindi, DHA & Bahria + free calculator.',
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
			'title' => 'Cement Price Today in Pakistan: PKR ' . number_format( hcc_default_calc_rates()['cement'] ) . ' per Bag (' . hcc_rates_month() . ')',
			'desc'  => 'Cement ki ek bori: PKR ' . number_format( hcc_default_calc_rates()['cement'] ) . ' planning rate, checked ' . hcc_rates_date_human() . '. Brands (Lucky, DG, Bestway, Maple Leaf) and bags needed for 3 Marla to 1 Kanal houses.',
		),
		'steel-saria-rate-today' => array(
			'title' => 'Saria Rate Today: PKR ' . number_format( hcc_default_calc_rates()['steel'] ) . '/Kg, ' . number_format( hcc_default_calc_rates()['steel'] * 40 ) . ' per 40 Kg (' . hcc_rates_month() . ')',
			'desc'  => 'Aaj ka sariya rate: PKR ' . number_format( hcc_default_calc_rates()['steel'] ) . ' per kg, PKR ' . number_format( hcc_default_calc_rates()['steel'] * 40 ) . ' per 40 kg, PKR ' . number_format( hcc_default_calc_rates()['steel'] * 1000 ) . ' per ton (Grade 60, ' . hcc_rates_date_human() . '). Steel for 5 Marla to 1 Kanal.',
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
