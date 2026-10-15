<?php
/**
 * v1.8.0 — server-rendered content blocks: material rates table (O3.4),
 * grey-structure guide (O3.5, T7.6, L1.1–L1.4, G9), city data (C1.3–C1.5),
 * extra/Roman-Urdu FAQs (C2.3), hub lists (L4), methodology tables (G5),
 * cost index (G2) and small shortcodes used by the new pages.
 *
 * Every figure here comes from hcc_compute_estimate() — the same engine as
 * the calculator — so nothing on the page can contradict the tool.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hcc_table( $head, $rows, $class = 'mini-table' ) {
	$h = '<div class="table-scroll"><table class="' . esc_attr( $class ) . '"><thead><tr>';
	foreach ( $head as $c ) $h .= '<th>' . $c . '</th>';
	$h .= '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$h .= '<tr>';
		foreach ( $r as $c ) $h .= '<td>' . $c . '</td>';
		$h .= '</tr>';
	}
	return $h . '</tbody></table></div>';
}
function hcc_pkr( $n ) { return esc_html( hcc_format_pkr( $n ) ); }

/* --------------------------------------------------------------------------
 * O3.4 / G4 — current material rates table with date + source per rate.
 * Sources come from the owner (Customizer). Until the owner enters them the
 * column honestly says these are ConstructionCostPK's planning defaults —
 * nothing is invented.
 * ------------------------------------------------------------------------ */
function hcc_material_rates_rows() {
	$r = hcc_default_calc_rates();
	return array(
		'cement' => array( 'Cement (50 kg bag)', 'PKR ' . number_format( $r['cement'] ) . ' per bag' ),
		'steel'  => array( 'Steel / saria (Grade 60)', 'PKR ' . number_format( $r['steel'] ) . ' per kg (PKR ' . number_format( $r['steel'] * 1000 ) . ' per ton)' ),
		'bricks' => array( 'Bricks (A-class)', 'PKR ' . number_format( $r['bricksPer1000'] ) . ' per 1,000' ),
		'sand'   => array( 'Sand', 'PKR ' . number_format( $r['sand'] ) . ' per cft' ),
		'crush'  => array( 'Crush / bajri', 'PKR ' . number_format( $r['crush'] ) . ' per cft' ),
	);
}
function hcc_material_rates_table() {
	$rows = array();
	$date = esc_html( hcc_rates_date_human() );
	foreach ( hcc_material_rates_rows() as $k => $row ) {
		$src = hcc_opt( 'source_' . $k );
		$src_html = $src ? wp_kses( $src, array( 'a' => array( 'href' => array(), 'rel' => array(), 'target' => array() ) ) ) : 'ConstructionCostPK planning rate — confirm with your local supplier';
		$rows[] = array( esc_html( $row[0] ), '<strong>' . esc_html( $row[1] ) . '</strong>', $date, $src_html );
	}
	return hcc_table( array( 'Material', 'Rate used', 'Rate date', 'Source' ), $rows )
		. '<p class="hint">These are the default rates the calculator uses (editable in its "Advanced" panel). Market prices change weekly — always get a fresh quotation before buying. See <a href="' . esc_url( home_url( '/construction-material-rates/' ) ) . '">construction material rates</a> for notes on each material.</p>';
}
add_shortcode( 'hcc_material_rates', 'hcc_material_rates_table' );

/* --------------------------------------------------------------------------
 * City tables.
 * ------------------------------------------------------------------------ */
function hcc_city_rate_rows( $city ) {
	$rows = array();
	$types = array(
		array( 'grey', 'standard', 'Grey structure (standard)' ),
		array( 'complete', 'economy', 'Complete house — economy' ),
		array( 'complete', 'standard', 'Complete house — standard' ),
		array( 'complete', 'premium', 'Complete house — premium' ),
		array( 'turnkey', 'standard', 'Turnkey — standard' ),
	);
	foreach ( $types as $t ) {
		$rows[] = array( esc_html( $t[2] ), hcc_pkr( hcc_rate_per_sqft( $t[0], $city, $t[1] ) ) . ' / sq ft' );
	}
	return $rows;
}
function hcc_sc_city_rates( $atts ) {
	$a = shortcode_atts( array( 'city' => 'lahore' ), $atts );
	$labels = hcc_city_labels();
	$label = isset( $labels[ $a['city'] ] ) ? $labels[ $a['city'] ] : 'Lahore';
	return hcc_table( array( 'Construction type (' . esc_html( $label ) . ')', 'Cost per sq ft (' . esc_html( hcc_year() ) . ')' ), hcc_city_rate_rows( $a['city'] ) )
		. '<p class="hint">1,000 sq ft single storey, typical design, calculator default material rates (' . esc_html( hcc_rates_date_human() ) . ').</p>';
}
add_shortcode( 'hcc_city_rates', 'hcc_sc_city_rates' );

/** Common local plot sizes per city, in the units people actually use there. */
function hcc_city_plot_defs( $city ) {
	$defs = array(
		'lahore' => array(
			array( '5 Marla', 1125, 1 ), array( '10 Marla', 2250, 2 ), array( '1 Kanal', 4500, 2 ),
		),
		'karachi' => array(
			array( '120 sq yd (1,080 sq ft)', 1080, 2 ), array( '240 sq yd (2,160 sq ft)', 2160, 2 ), array( '400 sq yd (3,600 sq ft)', 3600, 2 ),
		),
		'islamabad' => array(
			array( '25×50 ft (1,250 sq ft)', 1250, 2 ), array( '30×60 ft (1,800 sq ft)', 1800, 2 ), array( '35×70 ft (2,450 sq ft)', 2450, 2 ), array( '50×90 ft (4,500 sq ft)', 4500, 2 ),
		),
	);
	return isset( $defs[ $city ] ) ? $defs[ $city ] : $defs['lahore'];
}
function hcc_sc_city_plots( $atts ) {
	$a = shortcode_atts( array( 'city' => 'lahore' ), $atts );
	$rows = array();
	foreach ( hcc_city_plot_defs( $a['city'] ) as $d ) {
		$grey = hcc_estimate( $d[1], $d[2], 'grey', 'standard', $a['city'] );
		$comp = hcc_estimate( $d[1], $d[2], 'complete', 'standard', $a['city'] );
		$rows[] = array( esc_html( $d[0] ), 1 === $d[2] ? 'Single' : 'Double', number_format( $comp['covered'] ) . ' sq ft', hcc_pkr( $grey['grandTotal'] ), hcc_pkr( $comp['grandTotal'] ) );
	}
	return hcc_table( array( 'Plot', 'Storeys', 'Covered area', 'Grey structure', 'Complete (standard)' ), $rows );
}
add_shortcode( 'hcc_city_plots', 'hcc_sc_city_plots' );

/** All-city comparison table (city hub + cost index). */
function hcc_all_cities_rows() {
	$rows = array();
	$pages = array( 'lahore' => 'construction-cost-lahore', 'karachi' => 'construction-cost-karachi', 'islamabad' => 'construction-cost-islamabad' );
	foreach ( hcc_city_labels() as $k => $label ) {
		$name = isset( $pages[ $k ] ) && hcc_page_exists( $pages[ $k ] ) ? '<a href="' . esc_url( hcc_page_url( $pages[ $k ] ) ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
		$rows[ $k ] = array(
			'name' => $name,
			'grey' => hcc_rate_per_sqft( 'grey', $k ),
			'complete' => hcc_rate_per_sqft( 'complete', $k ),
			'5m' => hcc_estimate( 1125, 1, 'complete', 'standard', $k )['grandTotal'],
		);
	}
	return $rows;
}
function hcc_sc_city_table() {
	$rows = array();
	foreach ( hcc_all_cities_rows() as $r ) {
		$rows[] = array( $r['name'], hcc_pkr( $r['grey'] ), hcc_pkr( $r['complete'] ), hcc_pkr( $r['5m'] ) );
	}
	return hcc_table( array( 'City', 'Grey structure / sq ft', 'Complete house / sq ft', '5 Marla complete (' . number_format( hcc_suggest_covered_area( 1125, 1 ) ) . ' sq ft)' ), $rows )
		. '<p class="hint">Standard quality, typical design. Rates last updated ' . esc_html( hcc_rates_date_human() ) . '. City differences come mainly from local labour rates and transport; see the <a href="' . esc_url( home_url( '/methodology/' ) ) . '">methodology</a>.</p>';
}
add_shortcode( 'hcc_city_table', 'hcc_sc_city_table' );

/* --------------------------------------------------------------------------
 * Plot-size tables for the new 3 / 4 Marla pages.
 * ------------------------------------------------------------------------ */
function hcc_sc_plot_tables( $atts ) {
	$a = shortcode_atts( array( 'marla' => 3, 'storeys' => 1, 'part' => 'cost' ), $atts );
	$plot = (float) $a['marla'] * 225;
	$st = max( 1, (int) $a['storeys'] );
	if ( 'qty' === $a['part'] ) {
		return hcc_qty_table( hcc_estimate( $plot, $st, 'grey' ) );
	}
	if ( 'storeys' === $a['part'] ) {
		$rows = array();
		foreach ( array( 1, 2 ) as $s ) {
			$c = hcc_estimate( $plot, $s ); $g = hcc_estimate( $plot, $s, 'grey' );
			$rows[] = array( 1 === $s ? 'Single storey' : 'Double storey', number_format( $c['covered'] ) . ' sq ft', hcc_pkr( $g['grandTotal'] ), hcc_pkr( $c['grandTotal'] ) );
		}
		return hcc_table( array( 'Option', 'Covered area', 'Grey structure', 'Complete (standard)' ), $rows );
	}
	$rows = array();
	$g = hcc_estimate( $plot, $st, 'grey' );
	$rows[] = array( 'Grey structure only', hcc_pkr( $g['costPerSqft'] ), hcc_pkr( $g['grandTotal'] ) );
	foreach ( array( 'economy', 'standard', 'premium' ) as $q ) {
		$c = hcc_estimate( $plot, $st, 'complete', $q );
		$rows[] = array( 'Complete house — ' . $q . ' quality', hcc_pkr( $c['costPerSqft'] ), hcc_pkr( $c['grandTotal'] ) );
	}
	return hcc_table( array( 'Construction type', 'Cost / sq ft', 'Estimated total' ), $rows );
}
add_shortcode( 'hcc_plot_tables', 'hcc_sc_plot_tables' );

/* --------------------------------------------------------------------------
 * v1.10 — size answers: plot size under each Marla standard, a conversion
 * table and a small Marla / Kanal / sq ft / gaz converter.
 * ------------------------------------------------------------------------ */
function hcc_marla_standards() {
	return array( 225 => 'Society Marla (225 sq ft)', 250 => 'Karachi / Cantonment (250 sq ft)', 272 => 'Land Revenue (272 sq ft)', '272.25' => 'Patwari / revenue record (272.25 sq ft)' );
}
function hcc_num( $n, $dec = 0 ) {
	return hcc_trim_number( number_format( $n, $dec ) );
}
/** [hcc_size_box marla="7"] — how big an N Marla plot is, under every standard. */
function hcc_sc_size_box( $atts ) {
	$a = shortcode_atts( array( 'marla' => 5 ), $atts );
	$m = max( 0.5, (float) $a['marla'] );
	$label = 20 == $m ? '1 Kanal (20 Marla)' : hcc_num( $m, 1 ) . ' Marla';
	$dims = array( '3' => '22.5 × 30 ft or 20 × 34 ft', '4' => '25 × 36 ft', '5' => '25 × 45 ft', '7' => '35 × 45 ft', '10' => 'about 35 × 65 ft', '20' => '50 × 90 ft' );
	$k = hcc_num( $m, 1 );
	$sq = $m * 225;
	$rows = array();
	foreach ( hcc_marla_standards() as $per => $name ) {
		$f = $m * (float) $per;
		$rows[] = array( esc_html( $name ), hcc_num( $f, 2 ) . ' sq ft', hcc_num( $f / 9, 1 ) . ' sq yd (gaz)', hcc_num( $f * 0.09290304, 1 ) . ' m²' );
	}
	return '<p class="size-answer"><strong>' . esc_html( $label ) . ' = ' . number_format( $sq ) . ' sq ft</strong> (' . hcc_num( $sq / 9, 1 ) . ' sq yd or gaz, about ' . hcc_num( $sq * 0.09290304 ) . ' m², ' . hcc_num( $m / 20, 2 ) . ' Kanal) under the common 225 sq ft Marla.'
		. ( isset( $dims[ $k ] ) ? ' Common plot dimensions: ' . esc_html( $dims[ $k ] ) . '.' : '' ) . ' Under the older 272.25 sq ft Marla the same plot is ' . hcc_num( $m * 272.25, 2 ) . ' sq ft.</p>'
		. hcc_table( array( 'Marla standard', esc_html( $label ), 'Square yards', 'Square metres' ), $rows );
}
add_shortcode( 'hcc_size_box', 'hcc_sc_size_box' );

/** [hcc_marla_conversions] — the conversions people search for most. */
function hcc_sc_marla_conversions() {
	$rows = array();
	foreach ( array( 1, 2, 3, 4, 5, 6, 7, 8, 10, 12, 15 ) as $m ) {
		$rows[] = array( $m . ' Marla', number_format( $m * 225 ) . ' sq ft', hcc_num( $m * 25 ) . ' gaz', hcc_num( $m * 272.25, 2 ) . ' sq ft', hcc_num( $m / 20, 2 ) . ' Kanal' );
	}
	foreach ( array( 1, 2, 4, 8 ) as $k ) {
		$rows[] = array( $k . ' Kanal', number_format( $k * 4500 ) . ' sq ft', number_format( $k * 500 ) . ' gaz', number_format( $k * 5445 ) . ' sq ft', ( $k * 20 ) . ' Marla' );
	}
	return hcc_table( array( 'Size', 'Sq ft (225 Marla)', 'Sq yd / gaz (225 Marla)', 'Sq ft (272.25 Marla)', 'In Marla / Kanal' ), $rows )
		. '<p class="hint">1 Kanal is always 20 Marla. 1 acre = 43,560 sq ft = 8 Kanal = 160 Marla under the 272.25 sq ft revenue Marla.</p>';
}
add_shortcode( 'hcc_marla_conversions', 'hcc_sc_marla_conversions' );

/** [hcc_area_converter] — converts between Marla, Kanal, sq ft, sq yd, m² and acres in the browser. */
function hcc_sc_area_converter() {
	$opts = '';
	foreach ( hcc_marla_standards() as $per => $name ) $opts .= '<option value="' . esc_attr( $per ) . '">' . esc_html( $name ) . '</option>';
	$h = '<div class="area-converter card" id="area-converter"><h3>Marla, Kanal &amp; square feet converter</h3><div class="row-inline">'
		. '<label>Amount <input type="number" id="ac-val" value="7" min="0" step="any" inputmode="decimal"></label>'
		. '<label>Unit <select id="ac-unit"><option value="marla">Marla</option><option value="kanal">Kanal</option><option value="sqft">Sq ft</option><option value="sqyd">Sq yd (gaz)</option><option value="sqm">Square metres</option><option value="acre">Acre</option></select></label>'
		. '<label>Marla standard <select id="ac-std">' . $opts . '</select></label></div>'
		. '<output id="ac-out" for="ac-val ac-unit ac-std" aria-live="polite"></output></div>';
	$h .= '<script data-no-optimize="1">(function(){var v=document.getElementById("ac-val"),u=document.getElementById("ac-unit"),s=document.getElementById("ac-std"),o=document.getElementById("ac-out");if(!v)return;'
		. 'function f(n,d){return (+n.toFixed(d)).toLocaleString("en-US",{maximumFractionDigits:d});}'
		. 'function run(){var x=parseFloat(v.value)||0,m=parseFloat(s.value),sq={marla:m,kanal:20*m,sqft:1,sqyd:9,sqm:10.7639104,acre:43560}[u.value]*x;'
		. 'o.innerHTML="<strong>"+f(sq,2)+" sq ft</strong> = "+f(sq/9,2)+" sq yd (gaz) = "+f(sq/10.7639104,2)+" m² = <strong>"+f(sq/m,2)+" Marla</strong> = "+f(sq/m/20,3)+" Kanal = "+f(sq/43560,4)+" acre";}'
		. 'v.addEventListener("input",run);u.addEventListener("change",run);s.addEventListener("change",run);run();})();</script>';
	return $h;
}
add_shortcode( 'hcc_area_converter', 'hcc_sc_area_converter' );

/** [hcc_sqyd_table] — Karachi plot sizes in square yards, single storey and ground + 1. */
function hcc_sc_sqyd_table( $atts ) {
	$a = shortcode_atts( array( 'city' => 'karachi' ), $atts );
	$rows = array();
	foreach ( array( 80, 120, 200, 240, 400, 500, 1000 ) as $yd ) {
		$p = $yd * 9;
		$one = hcc_estimate( $p, 1, 'complete', 'standard', $a['city'] );
		$two = hcc_estimate( $p, 2, 'complete', 'standard', $a['city'] );
		$g2 = hcc_estimate( $p, 2, 'grey', 'standard', $a['city'] );
		$rows[] = array( $yd . ' sq yd', number_format( $p ) . ' sq ft', hcc_pkr( $one['grandTotal'] ), number_format( $two['covered'] ) . ' sq ft', hcc_pkr( $g2['grandTotal'] ), hcc_pkr( $two['grandTotal'] ) );
	}
	return hcc_table( array( 'Plot', 'Plot area', 'Ground floor only (complete)', 'Ground + 1 covered', 'Ground + 1 grey structure', 'Ground + 1 complete' ), $rows )
		. '<p class="hint">Standard quality, typical design, calculator default rates (' . esc_html( hcc_rates_date_human() ) . '). 1 sq yd = 9 sq ft.</p>';
}
add_shortcode( 'hcc_sqyd_table', 'hcc_sc_sqyd_table' );

/** [hcc_steel_rate_table] — the planning steel rate per kg, per 40 kg (maund) and per ton, with its date. */
function hcc_sc_steel_rate_table() {
	$r = hcc_default_calc_rates()['steel'];
	return hcc_table( array( 'Grade 60 saria', 'Planning rate', 'Checked on' ), array(
		array( 'Per kg', 'PKR ' . number_format( $r ), esc_html( hcc_rates_date_human() ) ),
		array( 'Per 40 kg (1 maund)', 'PKR ' . number_format( $r * 40 ), esc_html( hcc_rates_date_human() ) ),
		array( 'Per ton (1,000 kg)', 'PKR ' . number_format( $r * 1000 ), esc_html( hcc_rates_date_human() ) ),
	) );
}
add_shortcode( 'hcc_steel_rate_table', 'hcc_sc_steel_rate_table' );

/* --------------------------------------------------------------------------
 * Material-page tables (cement / steel) — quantities per plot size and the
 * budget impact of a price move. Uses the engine's quantity factors.
 * ------------------------------------------------------------------------ */
/** Material quantity table for one estimate — quantities come from the engine. */
function hcc_qty_table( $e ) {
	$qt = $e['qty'];
	$rows = array(
		array( 'Cement', number_format( round( $qt['cementBags'] ) ) . ' bags' ),
		array( 'Steel (saria)', number_format( round( $qt['steelKg'] ) ) . ' kg' ),
		array( 'Bricks', number_format( round( $qt['bricks'] ) ) ),
		array( 'Sand', number_format( round( $qt['sandCft'] ) ) . ' cft' ),
		array( 'Crush (aggregate)', number_format( round( $qt['crushCft'] ) ) . ' cft' ),
	);
	return hcc_table( array( 'Material', 'Approx. quantity (' . number_format( $e['covered'] ) . ' sq ft)' ), $rows );
}

function hcc_std_plots() {
	return array( array( '3 Marla', 675, 1 ), array( '5 Marla', 1125, 1 ), array( '7 Marla', 1575, 1 ), array( '10 Marla', 2250, 2 ), array( '1 Kanal', 4500, 2 ) );
}
function hcc_sc_material_need( $atts ) {
	$a = shortcode_atts( array( 'material' => 'cement' ), $atts );
	$b = hcc_base_qty_per_sqft();
	$r = hcc_default_calc_rates();
	$rows = array();
	foreach ( hcc_std_plots() as $p ) {
		$e = hcc_estimate( $p[1], $p[2], 'grey' );
		$cv = $e['covered'];
		if ( 'steel' === $a['material'] ) {
			$kg = round( $e['qty']['steelKg'] );
			$rows[] = array( esc_html( $p[0] ) . ( 2 === $p[2] ? ' (double storey)' : '' ), number_format( $cv ) . ' sq ft', number_format( $kg ) . ' kg (' . number_format( $kg / 1000, 1 ) . ' ton)', hcc_pkr( $kg * $r['steel'] ), hcc_pkr( $kg * 10 ) );
		} else {
			$bags = round( $e['qty']['cementBags'] );
			$rows[] = array( esc_html( $p[0] ) . ( 2 === $p[2] ? ' (double storey)' : '' ), number_format( $cv ) . ' sq ft', number_format( $bags ) . ' bags', hcc_pkr( $bags * $r['cement'] ), hcc_pkr( $bags * 100 ) );
		}
	}
	$head = 'steel' === $a['material']
		? array( 'House', 'Covered area', 'Steel needed', 'Cost at planning rate', 'Budget change per PKR 10/kg move' )
		: array( 'House', 'Covered area', 'Cement needed', 'Cost at planning rate', 'Budget change per PKR 100/bag move' );
	return hcc_table( $head, $rows ) . '<p class="hint">Standard quality, typical design; excludes the city factor and delivery. Quantities: ' . ( 'steel' === $a['material'] ? $b['steelKg'] . ' kg' : $b['cementBags'] . ' bags' ) . ' per sq ft of covered area, plus 3% per storey above the ground floor — see the <a href="' . esc_url( home_url( '/methodology/' ) ) . '">methodology</a>.</p>';
}
add_shortcode( 'hcc_material_need', 'hcc_sc_material_need' );

/** Owner-entered market price list (brand|price|source|date per line). */
function hcc_sc_market_prices( $atts ) {
	$a = shortcode_atts( array( 'material' => 'cement' ), $atts );
	$raw = hcc_opt( 'market_' . $a['material'] );
	if ( ! $raw ) {
		return '<div class="notice-box">We only publish brand-wise market prices once they are checked against a named dealer or published price list. Until then, use the planning rate above and ask two or three local dealers for today\'s quote.</div>';
	}
	$rows = array();
	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) < 2 || '' === $p[0] ) continue;
		$rows[] = array( esc_html( $p[0] ), esc_html( $p[1] ), isset( $p[2] ) ? esc_html( $p[2] ) : '', isset( $p[3] ) ? esc_html( $p[3] ) : '' );
	}
	return hcc_table( array( 'Brand / grade', 'Price', 'Source', 'Date' ), $rows );
}
add_shortcode( 'hcc_market_prices', 'hcc_sc_market_prices' );

/* --------------------------------------------------------------------------
 * L4 — hub lists. Every hub lists all of its published spokes.
 * ------------------------------------------------------------------------ */
function hcc_sc_hub_list( $atts ) {
	$a = shortcode_atts( array( 'cluster' => 'plot' ), $atts );
	$figs = hcc_headline_figures();
	$h = '<ul class="hub-list">';
	foreach ( hcc_page_registry() as $key => $d ) {
		if ( $d['cluster'] !== $a['cluster'] || 'hub' === $d['type'] || ! hcc_page_exists( $key ) ) continue;
		$h .= '<li><a href="' . esc_url( hcc_page_url( $key ) ) . '"><strong>' . esc_html( $d['label'] ) . '</strong></a>'
			. ( isset( $figs[ $key ] ) ? ' <span class="hub-fig">' . esc_html( $figs[ $key ] ) . '</span>' : '' )
			. '<br><span class="hub-sum">' . esc_html( $d['card'] ) . '</span></li>';
	}
	return $h . '</ul>';
}
add_shortcode( 'hcc_hub_list', 'hcc_sc_hub_list' );

/** O3.8 — headline figure per guide, computed live from the engine. */
function hcc_headline_figures() {
	$f = array();
	foreach ( array( '3-marla' => array( 675, 1 ), '4-marla' => array( 900, 1 ), '5-marla' => array( 1125, 1 ), '7-marla' => array( 1575, 1 ), '10-marla' => array( 2250, 2 ), '1-kanal' => array( 4500, 2 ) ) as $k => $p ) {
		$f[ $k . '-house-construction-cost' ] = '≈ PKR ' . hcc_lakh_crore( hcc_estimate( $p[0], $p[1] )['grandTotal'] ) . ' complete';
	}
	$f['grey-structure-cost-calculator'] = '≈ ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey' ) ) . ' / sq ft';
	$f['marla-kanal-standards-pakistan'] = '1 Marla = 225–272.25 sq ft';
	foreach ( array( 'lahore', 'karachi', 'islamabad' ) as $c ) {
		$f[ 'construction-cost-' . $c ] = '≈ ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', $c ) ) . ' / sq ft';
	}
	$r = hcc_default_calc_rates();
	$f['cement-rate-today-pakistan'] = 'PKR ' . number_format( $r['cement'] ) . ' / bag (planning rate)';
	$f['steel-saria-rate-today'] = 'PKR ' . number_format( $r['steel'] ) . ' / kg (planning rate)';
	return $f;
}

/* --------------------------------------------------------------------------
 * O3.5 / T7.6 / L1.1–L1.4 / G9 — grey structure guide.
 * ------------------------------------------------------------------------ */
/** Inline bar chart of grey structure cost per sq ft by city, from the engine (v1.9.0; replaces a static image with frozen prices). */
function hcc_grey_city_chart_svg() {
	$rates = array();
	foreach ( hcc_city_labels() as $k => $label ) $rates[ $label ] = hcc_rate_per_sqft( 'grey', $k );
	arsort( $rates );
	$max = max( $rates );
	$rowh = 30; $top = 10; $h = $top + count( $rates ) * $rowh + 26;
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 ' . $h . '" width="600" height="' . $h . '" role="img" aria-labelledby="greyCityTitle"><title id="greyCityTitle">Grey structure cost per square foot in ' . count( $rates ) . ' Pakistani cities, ' . esc_html( hcc_rates_date_human() ) . ' default rates</title><g font-family="sans-serif" font-size="13">';
	$y = $top;
	foreach ( $rates as $label => $r ) {
		$w = round( 330 * $r / $max );
		$svg .= '<text x="0" y="' . ( $y + 15 ) . '" fill="#122c4a" font-weight="700">' . esc_html( $label ) . '</text>';
		$svg .= '<rect x="110" y="' . $y . '" width="330" height="20" rx="4" fill="#eef2f8"/><rect x="110" y="' . $y . '" width="' . $w . '" height="20" rx="4" fill="#d79a2b"/>';
		$svg .= '<text x="600" y="' . ( $y + 15 ) . '" text-anchor="end" fill="#122c4a" font-weight="700">PKR ' . esc_html( number_format( round( $r ) ) ) . '</text>';
		$y += $rowh;
	}
	return $svg . '</g><text x="0" y="' . ( $h - 6 ) . '" font-family="sans-serif" font-size="11" fill="#8a94a3">Standard quality, 1,000 sq ft single storey, structure materials + labour, no finishing</text></svg>';
}

function hcc_variant_before_calculator( $variant ) {
	if ( 'grey-structure' === $variant ) {
		$rows = array();
		foreach ( hcc_all_cities_rows() as $r ) $rows[] = array( $r['name'], hcc_pkr( $r['grey'] ) . ' / sq ft' );
		ob_start(); ?>
		<section class="static-page pre-calc"><div class="card">
			<h2>Grey Structure Rates by City (<?php echo esc_html( hcc_year() ); ?>)</h2>
			<?php echo hcc_table( array( 'City', 'Grey structure cost (standard)' ), $rows ); ?>
			<figure class="illo">
				<?php echo hcc_grey_city_chart_svg(); // phpcs:ignore -- built from escaped engine numbers ?>
				<figcaption>Grey structure cost per sq ft by city — standard quality, 1,000 sq ft single storey.</figcaption>
			</figure>
		</div></section>
		<?php return ob_get_clean();
	}
	return '';
}

function hcc_variant_after_calculator( $variant ) {
	if ( 'grey-structure' !== $variant ) return '';
	$q = hcc_quality_table();
	$g = hcc_rate_per_sqft( 'grey' );
	$labour_share = $q['standard']['greyLabour'] / $g;
	$e5 = hcc_estimate( 1125, 1, 'grey' ); $e5d = hcc_estimate( 1125, 2, 'grey' );
	$e10 = hcc_estimate( 2250, 2, 'grey' ); $e10s = hcc_estimate( 2250, 1, 'grey' );
	$u = function ( $k ) { return esc_url( home_url( '/' . $k . '/' ) ); };
	$labour_rows = array();
	foreach ( array( 'economy' => 'Economy', 'standard' => 'Standard', 'premium' => 'Premium' ) as $k => $l ) {
		$labour_rows[] = array( $l, 'PKR ' . number_format( $q[ $k ]['greyLabour'] ) . ' / sq ft', hcc_pkr( hcc_rate_per_sqft( 'grey', 'lahore', $k ) ) . ' / sq ft' );
	}
	ob_start(); ?>
	<section class="static-page guide-extra"><div class="card">
		<h2>Grey Structure in Pakistan: The Complete Guide</h2>
		<p>Grey structure (also called the "structure" or "dhancha" stage) is the part of a house that holds it up and closes it in: excavation, foundation, damp-proof course (DPC), columns, beams, roof slabs, brick walls and the staircase. Everything that makes the house liveable — plaster, flooring, paint, doors, windows, plumbing and electrical fittings — belongs to the finishing stage. At current calculator rates, grey structure costs about <strong><?php echo hcc_pkr( $g ); ?> per sq ft</strong> in Lahore at standard quality, about <?php echo esc_html( hcc_cost_shares()['grey_of_complete'] ); ?>% of a complete house.</p>

		<h3>What is included in grey structure?</h3>
		<ul>
			<li>Excavation, plain cement concrete (PCC) under footings and the RCC or brick foundation</li>
			<li>Damp-proof course and back-filling with compaction</li>
			<li>RCC columns, beams, lintels and roof slabs, including shuttering and steel fixing</li>
			<li>Brick masonry for external and internal walls, and the staircase structure</li>
			<li>Conduit and pipe sleeves that must be placed before concrete is poured</li>
		</ul>
		<h3>What is usually excluded?</h3>
		<ul>
			<li>Plaster (many contractors quote it separately), flooring, tiles and paint</li>
			<li>Doors, windows, grills, kitchen and bathroom fittings</li>
			<li>Electrical wiring and fittings, water tanks, gas connection</li>
			<li>Boundary wall, gate and map approval fees — add these in the calculator above</li>
		</ul>
		<p>Always ask your contractor to write the scope in the agreement. Two "grey structure" quotes can differ by 15% simply because one includes plaster and water-proofing and the other does not.</p>

		<h3>Labour only vs with material: contractor rates</h3>
		<p>Contractors in Pakistan quote grey structure in two ways. A <strong>labour-only</strong> ("theka labour") rate covers masons, steel fixers and helpers while you buy every bag of cement and every bar of steel yourself. A <strong>with-material</strong> rate covers labour and materials together. The calculator's assumptions for Lahore are:</p>
		<?php echo hcc_table( array( 'Quality', 'Labour only (structure)', 'With material (grey total)' ), $labour_rows ); ?>
		<p>At standard quality, labour is about <?php echo esc_html( round( $labour_share * 100 ) ); ?>% of the grey structure cost and materials the remaining <?php echo esc_html( 100 - round( $labour_share * 100 ) ); ?>%. A labour-only contract gives you control over material quality and price, but you carry the risk of wastage and must be on site to receive deliveries. A with-material contract is simpler but hides the material margin, so ask for an itemized bill of quantities.</p>

		<h3>Worked examples: 5 Marla and 10 Marla</h3>
		<?php echo hcc_table( array( 'House', 'Covered area', 'Grey structure cost (Lahore, standard)' ), array(
			array( '<a href="' . $u( '5-marla-house-construction-cost' ) . '">5 Marla, single storey</a>', number_format( $e5['covered'] ) . ' sq ft', hcc_pkr( $e5['grandTotal'] ) ),
			array( '5 Marla, double storey', number_format( $e5d['covered'] ) . ' sq ft', hcc_pkr( $e5d['grandTotal'] ) ),
			array( '10 Marla, single storey', number_format( $e10s['covered'] ) . ' sq ft', hcc_pkr( $e10s['grandTotal'] ) ),
			array( '<a href="' . $u( '10-marla-house-construction-cost' ) . '">10 Marla, double storey</a>', number_format( $e10['covered'] ) . ' sq ft', hcc_pkr( $e10['grandTotal'] ) ),
		) ); ?>
		<p>So the <a href="<?php echo $u( '5-marla-house-construction-cost' ); ?>">grey structure cost of a 5 Marla house</a> is about PKR <?php echo esc_html( hcc_lakh_crore( $e5['grandTotal'] ) ); ?> for a single storey, while a <a href="<?php echo $u( '10-marla-house-construction-cost' ); ?>">10 Marla double storey grey structure</a> comes to about PKR <?php echo esc_html( hcc_lakh_crore( $e10['grandTotal'] ) ); ?>. For a large plot, the <a href="<?php echo $u( '1-kanal-house-construction-cost' ); ?>">1 Kanal grey structure budget</a> is about PKR <?php echo esc_html( hcc_lakh_crore( hcc_estimate( 4500, 2, 'grey' )['grandTotal'] ) ); ?> for a double storey. Double storey buildings cost a little more per sq ft because columns, steel and scaffolding increase with height.</p>

		<h3>Pre-calculated grey structure examples</h3>
		<?php
		$ex = array();
		foreach ( hcc_std_plots() as $p ) {
			$e = hcc_estimate( $p[1], $p[2], 'grey' );
			$ex[] = array( esc_html( $p[0] ), 1 === $p[2] ? 'Single' : 'Double', number_format( $e['covered'] ) . ' sq ft', hcc_pkr( $e['costPerSqft'] ), hcc_pkr( $e['grandTotal'] ), hcc_pkr( round( $e['grandTotal'] / ( $p[1] / 225 ), -3 ) ) );
		}
		echo hcc_table( array( 'Plot', 'Storeys', 'Covered area', 'Grey / sq ft', 'Grey structure total', 'Per Marla of plot' ), $ex );
		?>

		<h3>Common grey structure mistakes that cost money</h3>
		<ol>
			<li><strong>Starting without a structural drawing.</strong> Column sizes and steel are then decided on site, which usually means either over-use of steel or an unsafe structure.</li>
			<li><strong>Not curing concrete.</strong> Slabs and columns need to be kept wet for at least 7 days (longer in winter); skipping it weakens concrete you have already paid for.</li>
			<li><strong>Buying all cement at once.</strong> Cement loses strength in storage, especially in humid weather — buy in batches that match the work schedule.</li>
			<li><strong>Ignoring conduit and plumbing sleeves.</strong> Breaking a finished slab to pass a pipe costs more than planning the sleeve before the pour.</li>
			<li><strong>Comparing quotes with different scope.</strong> Check whether plaster, water-proofing, DPC and shuttering are included before choosing the cheapest rate.</li>
		</ol>
		<p>For finishing costs, material prices and every other plot size, see <a href="<?php echo $u( 'construction-cost-guides' ); ?>">all construction cost guides</a> or check today's <a href="<?php echo $u( 'construction-material-rates' ); ?>">material rates</a>.</p>

		<h3>Material rates used on this page</h3>
		<?php echo hcc_material_rates_table(); ?>
	</div></section>
	<?php
	return ob_get_clean();
}

function hcc_grey_structure_extra_faq_items() {
	$q = hcc_quality_table();
	return array(
		array(
			'q' => 'What is the labour rate for grey structure in Pakistan?',
			'a' => 'The calculator assumes a structure labour rate of about PKR ' . number_format( $q['economy']['greyLabour'] ) . ' per sq ft at economy quality, PKR ' . number_format( $q['standard']['greyLabour'] ) . ' at standard quality and PKR ' . number_format( $q['premium']['greyLabour'] ) . ' at premium quality in Lahore, adjusted by a city factor. Labour-only contractors quote this rate while the owner buys the materials.',
		),
	);
}

/* --------------------------------------------------------------------------
 * City FAQs (computed) + C2.3 Roman Urdu FAQs.
 * ------------------------------------------------------------------------ */
function hcc_city_faq_items( $city ) {
	$labels = hcc_city_labels();
	$L = isset( $labels[ $city ] ) ? $labels[ $city ] : 'Lahore';
	$comp = hcc_rate_per_sqft( 'complete', $city );
	$grey = hcc_rate_per_sqft( 'grey', $city );
	$items = array(
		array(
			'q' => 'What is the construction cost per sq ft in ' . $L . ' in ' . hcc_year() . '?',
			'a' => 'At standard quality, the calculator estimates about ' . hcc_format_pkr( $comp ) . ' per sq ft for a complete house and ' . hcc_format_pkr( $grey ) . ' per sq ft for grey structure in ' . $L . ' (rates last updated ' . hcc_rates_date_human() . '). Economy finishing is cheaper and premium finishing costs more — see the table on this page.',
		),
	);
	$authority = array(
		'lahore' => 'Inside LDA-controlled areas, building plans are approved by the Lahore Development Authority under its building and zoning regulations. Private schemes such as DHA Lahore and Bahria Town approve plans through their own building control departments, each with its own fee schedule and coverage rules.',
		'karachi' => 'Most of Karachi falls under the Sindh Building Control Authority (SBCA), which approves building plans under the Karachi Building & Town Planning Regulations. Cantonment areas — including DHA Karachi, which falls under Clifton Cantonment Board — and some housing schemes follow their own approval process.',
		'islamabad' => 'In CDA sectors, building plans are approved by the Capital Development Authority\'s building control section. Private housing societies in the capital territory, such as DHA and Bahria Town, approve plans through their own offices under rules that must follow the CDA framework.',
	);
	if ( isset( $authority[ $city ] ) ) {
		$items[] = array( 'q' => 'Who approves house maps in ' . $L . '?', 'a' => $authority[ $city ] . ' Fees change — always get the current schedule from the relevant authority before budgeting.' );
	}
	$first = hcc_city_plot_defs( $city )[0];
	$e = hcc_estimate( $first[1], $first[2], 'complete', 'standard', $city );
	$items[] = array(
		'q' => 'How much does a ' . preg_replace( '/\s*\(.*\)$/', '', $first[0] ) . ' house cost to build in ' . $L . '?',
		'a' => 'Built ' . ( 1 === $first[2] ? 'single storey' : 'double storey' ) . ' with about ' . number_format( $e['covered'] ) . ' sq ft of covered area, a standard-quality complete house costs roughly ' . hcc_format_pkr( $e['grandTotal'] ) . ' in ' . $L . ', or about ' . hcc_format_pkr( hcc_estimate( $first[1], $first[2], 'grey', 'standard', $city )['grandTotal'] ) . ' for grey structure only.',
	);
	return $items;
}

function hcc_roman_urdu_faq_items( $variant ) {
	$sizes = array( '3-marla' => array( '3 marla', 675, 1 ), '4-marla' => array( '4 marla', 900, 1 ), '5-marla' => array( '5 marla', 1125, 1 ), '7-marla' => array( '7 marla', 1575, 1 ), '10-marla' => array( '10 marla', 2250, 2 ), '1-kanal' => array( '1 kanal', 4500, 2 ) );
	if ( isset( $sizes[ $variant ] ) ) {
		$s = $sizes[ $variant ];
		$c = hcc_estimate( $s[1], $s[2] ); $g = hcc_estimate( $s[1], $s[2], 'grey' );
		return array(
			array( 'q' => ucfirst( $s[0] ) . ' ghar banane mein kitna kharcha aata hai?', 'a' => 'Lahore ke standard rates par ' . $s[0] . ' ka ' . ( 1 === $s[2] ? 'single storey' : 'double storey' ) . ' mukammal ghar taqreeban PKR ' . hcc_lakh_crore( $c['grandTotal'] ) . ' mein banta hai, aur sirf grey structure taqreeban PKR ' . hcc_lakh_crore( $g['grandTotal'] ) . ' mein. Apne sheher aur quality ke hisaab se upar wala calculator istemal karein.' ),
			array( 'q' => ucfirst( $s[0] ) . ' ghar ke liye kitna cement aur saria chahiye?', 'a' => 'Taqreeban ' . number_format( round( $g['qty']['cementBags'] ) ) . ' bori cement, ' . number_format( round( $g['qty']['steelKg'] ) ) . ' kg saria aur ' . number_format( round( $g['qty']['bricks'] ) ) . ' eentein (standard quality, ' . number_format( $c['covered'] ) . ' sq ft covered area). Design aur quality ke saath yeh miqdaar badal sakti hai.' ),
		);
	}
	if ( 0 === strpos( $variant, 'city-' ) ) {
		$city = substr( $variant, 5 );
		$labels = hcc_city_labels();
		$L = isset( $labels[ $city ] ) ? $labels[ $city ] : 'Lahore';
		return array(
			array( 'q' => $L . ' mein ghar banane ka per square foot rate kya hai?', 'a' => $L . ' mein standard quality ka mukammal ghar taqreeban ' . hcc_format_pkr( hcc_rate_per_sqft( 'complete', $city ) ) . ' per sq ft aur grey structure taqreeban ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey', $city ) ) . ' per sq ft mein banta hai (' . hcc_year() . ').' ),
			array( 'q' => $L . ' mein naqsha (map) kahan se pass hota hai?', 'a' => 'Yeh aap ki society ya area par munhasir hai — sarkari authority ya society ka apna building control department. Fees aur rules pehle confirm kar lein.' ),
		);
	}
	if ( 'marla-standards' === $variant ) {
		return array(
			array( 'q' => '1 marla kitne square feet ka hota hai?', 'a' => 'Zyada tar societies (LDA, DHA, Bahria Town) mein 1 marla 225 sq ft ka hota hai, lekin Karachi/cantonment mein 250 sq ft aur purane revenue record (patwari) mein 272.25 sq ft bhi istemal hota hai.' ),
			array( 'q' => '1 kanal mein kitne marla hote hain?', 'a' => '1 kanal hamesha 20 marla ka hota hai — lekin marla ke standard ke hisaab se 1 kanal 4,500 se 5,445 sq ft tak ho sakta hai.' ),
		);
	}
	if ( 'grey-structure' === $variant ) {
		return array(
			array( 'q' => 'Grey structure ka rate per square foot kya hai?', 'a' => 'Lahore mein standard quality ka grey structure taqreeban ' . hcc_format_pkr( hcc_rate_per_sqft( 'grey' ) ) . ' per sq ft mein banta hai (material + labour). Sirf labour ka rate taqreeban PKR ' . number_format( hcc_quality_table()['standard']['greyLabour'] ) . ' per sq ft hai.' ),
			array( 'q' => 'Grey structure mein kya kya shamil hota hai?', 'a' => 'Foundation, DPC, columns, beams, lintel, chhat (slab), eenton ki deewarein aur seerhi. Plaster, tiles, rang, darwaze, khirkiyan, bijli aur plumbing fittings finishing mein aati hain.' ),
		);
	}
	return array();
}

/* --------------------------------------------------------------------------
 * G5 — methodology tables straight from the engine.
 * ------------------------------------------------------------------------ */
function hcc_sc_methodology() {
	$b = hcc_base_qty_per_sqft();
	$q = hcc_quality_table();
	$cf = hcc_city_factors();
	$labels = hcc_city_labels();
	$out = '<h2>Material quantities per sq ft of covered area</h2>';
	$out .= hcc_table( array( 'Material', 'Standard quality, per sq ft' ), array(
		array( 'Cement', $b['cementBags'] . ' bags (50 kg)' ), array( 'Steel (saria)', $b['steelKg'] . ' kg' ),
		array( 'Bricks', $b['bricks'] . ' nos.' ), array( 'Sand', $b['sandCft'] . ' cft' ), array( 'Crush', $b['crushCft'] . ' cft' ),
	) );
	$out .= '<h2>Quality factors and labour/finishing rates</h2>';
	$rows = array();
	foreach ( $q as $k => $v ) {
		$rows[] = array( ucfirst( $k ), $v['qtyFactor'], 'PKR ' . number_format( $v['greyLabour'] ), 'PKR ' . number_format( $v['finishRate'] ), round( $v['finishLabourShare'] * 100 ) . '%', 'PKR ' . number_format( $v['turnkeyRate'] ) );
	}
	$out .= hcc_table( array( 'Quality', 'Quantity factor', 'Structure labour / sq ft', 'Finishing / sq ft', 'Labour share of finishing', 'Turnkey add-on / sq ft' ), $rows );
	$out .= '<h2>City factors</h2>';
	$rows = array();
	foreach ( $labels as $k => $l ) $rows[] = array( esc_html( $l ), number_format( $cf[ $k ], 2 ) );
	$out .= hcc_table( array( 'City', 'Labour & finishing factor (Lahore = 1.00)' ), $rows );
	$out .= '<h2>Default material rates</h2>' . hcc_material_rates_table();
	return $out;
}
add_shortcode( 'hcc_methodology_tables', 'hcc_sc_methodology' );

/* --------------------------------------------------------------------------
 * G2 — Pakistan Construction Cost Index. A snapshot is stored the first time
 * the page renders in each month so month-on-month change is real history,
 * not a guess.
 * ------------------------------------------------------------------------ */
/**
 * v1.9.0: an edition is stored per month and tagged with the method version.
 * If the engine's formulas change (HCC_ENGINE_VERSION), old months stay as
 * history but the new method starts its own baseline, so a methodology change
 * (such as the bricks fix) is never reported as month-on-month inflation.
 * Within a month the edition is refreshed when the default rates change.
 */
function hcc_cost_index_snapshot() {
	$month = gmdate( 'Y-m' );
	$hist = get_option( 'hcc_cost_index_history', array() );
	if ( ! is_array( $hist ) ) $hist = array();
	$cur = isset( $hist[ $month ] ) ? $hist[ $month ] : null;
	$stale = $cur && ( ( isset( $cur['method'] ) ? (int) $cur['method'] : 1 ) !== HCC_ENGINE_VERSION || $cur['rates'] != hcc_default_calc_rates() || $cur['rates_date'] !== hcc_rates_date() );
	if ( ! $cur || $stale ) {
		$snap = array( 'rates_date' => hcc_rates_date(), 'rates' => hcc_default_calc_rates(), 'method' => HCC_ENGINE_VERSION, 'cities' => array() );
		foreach ( array_keys( hcc_city_labels() ) as $c ) {
			$snap['cities'][ $c ] = array( 'grey' => round( hcc_rate_per_sqft( 'grey', $c ) ), 'complete' => round( hcc_rate_per_sqft( 'complete', $c ) ) );
		}
		$hist[ $month ] = $snap;
		ksort( $hist );
		update_option( 'hcc_cost_index_history', $hist, false );
	}
	return $hist;
}
function hcc_sc_cost_index() {
	$hist = hcc_cost_index_snapshot();
	$months = array_keys( $hist );
	$cur_m = end( $months );
	$cur = $hist[ $cur_m ];
	$cur_meth = isset( $cur['method'] ) ? (int) $cur['method'] : 1;
	// Only compare with an earlier edition made with the same method.
	$same = array_values( array_filter( $months, function ( $m ) use ( $hist, $cur_meth ) {
		return ( isset( $hist[ $m ]['method'] ) ? (int) $hist[ $m ]['method'] : 1 ) === $cur_meth;
	} ) );
	$prev_m = count( $same ) > 1 ? $same[ count( $same ) - 2 ] : null;
	$prev = $prev_m ? $hist[ $prev_m ] : null;
	$chg = function ( $now, $before ) {
		if ( ! $before ) return 'baseline';
		$p = ( $now - $before ) / $before * 100;
		return ( $p > 0 ? '+' : '' ) . number_format( $p, 1 ) . '%';
	};
	$rows = array();
	foreach ( hcc_city_labels() as $k => $l ) {
		if ( ! isset( $cur['cities'][ $k ] ) ) continue;
		$c = $cur['cities'][ $k ];
		$p = ( $prev && isset( $prev['cities'][ $k ] ) ) ? $prev['cities'][ $k ] : null;
		$rows[] = array( esc_html( $l ), hcc_pkr( $c['grey'] ), esc_html( $chg( $c['grey'], $p ? $p['grey'] : 0 ) ), hcc_pkr( $c['complete'] ), esc_html( $chg( $c['complete'], $p ? $p['complete'] : 0 ) ) );
	}
	$label = date_i18n( 'F Y', strtotime( $cur_m . '-01' ) );
	$out = '<h2>City-wise construction cost — ' . esc_html( $label ) . '</h2>';
	$out .= hcc_table( array( 'City', 'Grey / sq ft', 'MoM', 'Complete / sq ft', 'MoM' ), $rows );
	$r = $cur['rates'];
	$out .= '<h2>Material rates in this edition</h2>' . hcc_table( array( 'Material', 'Rate', 'MoM' ), array(
		array( 'Cement (50 kg bag)', 'PKR ' . number_format( $r['cement'] ), esc_html( $chg( $r['cement'], $prev ? $prev['rates']['cement'] : 0 ) ) ),
		array( 'Steel (per kg)', 'PKR ' . number_format( $r['steel'] ), esc_html( $chg( $r['steel'], $prev ? $prev['rates']['steel'] : 0 ) ) ),
		array( 'Bricks (per 1,000)', 'PKR ' . number_format( $r['bricksPer1000'] ), esc_html( $chg( $r['bricksPer1000'], $prev ? $prev['rates']['bricksPer1000'] : 0 ) ) ),
	) );
	$out .= '<p class="hint">Edition: ' . esc_html( $label ) . ' · rates dated ' . esc_html( date_i18n( 'j F Y', strtotime( $cur['rates_date'] ) ) ) . ( $prev_m ? ' · compared with ' . esc_html( date_i18n( 'F Y', strtotime( $prev_m . '-01' ) ) ) : ( count( $months ) > 1 ? ' · new baseline: the calculation method was updated (see the <a href="' . esc_url( home_url( '/methodology/' ) ) . '">methodology</a>), so month-on-month change restarts from this edition' : ' · first edition (baseline) — month-on-month change starts next month' ) ) . '.</p>';
	if ( count( $months ) > 1 ) {
		$out .= '<h2>Index history</h2>';
		$hrows = array();
		foreach ( array_reverse( $months ) as $m ) {
			$old_method = ( isset( $hist[ $m ]['method'] ) ? (int) $hist[ $m ]['method'] : 1 ) !== $cur_meth;
			$hrows[] = array( esc_html( date_i18n( 'F Y', strtotime( $m . '-01' ) ) ) . ( $old_method ? ' (earlier method)' : '' ), hcc_pkr( $hist[ $m ]['cities']['lahore']['complete'] ), hcc_pkr( $hist[ $m ]['cities']['karachi']['complete'] ), hcc_pkr( $hist[ $m ]['cities']['islamabad']['complete'] ) );
		}
		$out .= hcc_table( array( 'Month', 'Lahore', 'Karachi', 'Islamabad' ), $hrows );
	}
	return $out;
}
add_shortcode( 'hcc_cost_index', 'hcc_sc_cost_index' );

/* --------------------------------------------------------------------------
 * X1 — embed code box.
 * ------------------------------------------------------------------------ */
function hcc_sc_embed_code() {
	$src = home_url( '/?hcc_embed=calculator' );
	$code = '<iframe src="' . esc_url( $src ) . '" width="100%" height="1500" style="border:0;max-width:960px" loading="lazy" title="House Construction Cost Calculator Pakistan"></iframe>' . "\n" . '<p style="font-size:13px">Powered by <a href="' . esc_url( home_url( '/' ) ) . '">ConstructionCostPK — House Construction Cost Calculator</a></p>';
	return '<label for="hcc-embed-code"><strong>Copy this code into your page (HTML view):</strong></label><textarea id="hcc-embed-code" class="embed-code" rows="5" readonly onclick="this.select()">' . esc_textarea( $code ) . '</textarea>'
		. '<p><a class="btn btn-secondary" href="' . esc_url( $src ) . '" target="_blank" rel="noopener">Preview the widget</a></p>';
}
add_shortcode( 'hcc_embed_code', 'hcc_sc_embed_code' );
