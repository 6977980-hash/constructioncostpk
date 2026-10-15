<?php
/**
 * v1.11.0 — Phase 4 pages: 6 Marla, 8 Marla, 2 Kanal, double storey cost,
 * Karachi 120 sq yd and lanter (roof slab) cost. Like the v1.8 pages, every
 * figure comes from the calculator engine through shortcodes or values
 * computed here, and the pages are stored as [hcc_page key="slug"].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Sister site the owner asked us to cite wherever roof slab cost comes up. */
define( 'HCC_SLAB_SITE', 'https://slabcostcalculator.com/' );

/* --------------------------------------------------------------------------
 * Lanter (RCC roof slab) quantities. Stated assumptions, all editable in the
 * tool: 1:2:4 mix, dry volume = wet × 1.54, 1.25 cft per 50 kg cement bag,
 * 3-sutar (#3, 0.17 kg/ft) bars at 6 inch spacing both ways = 0.68 kg/sq ft,
 * plus 25% for laps, bends and extra top bars at supports = 0.85 kg/sq ft.
 * ------------------------------------------------------------------------ */
function hcc_lanter_defaults() {
	return array( 'thick' => 5, 'mix' => '1:2:4', 'steel' => 0.85, 'dry' => 1.54, 'bag_cft' => 1.25 );
}
function hcc_lanter_qty( $area, $thick = 5, $mix = '1:2:4', $steel_per_sqft = 0.85 ) {
	$d = hcc_lanter_defaults();
	$p = array_map( 'floatval', explode( ':', $mix ) );
	if ( 3 !== count( $p ) || ! $p[0] ) $p = array( 1, 2, 4 );
	$sum = $p[0] + $p[1] + $p[2];
	$wet = max( 0, $area ) * $thick / 12;
	$dry = $wet * $d['dry'];
	$r = hcc_default_calc_rates();
	$q = array(
		'wet' => $wet,
		'cement' => $dry * $p[0] / $sum / $d['bag_cft'],
		'sand' => $dry * $p[1] / $sum,
		'crush' => $dry * $p[2] / $sum,
		'steel' => max( 0, $area ) * $steel_per_sqft,
	);
	$q['cost'] = array(
		'cement' => $q['cement'] * $r['cement'], 'sand' => $q['sand'] * $r['sand'],
		'crush' => $q['crush'] * $r['crush'], 'steel' => $q['steel'] * $r['steel'],
	);
	$q['material'] = array_sum( $q['cost'] );
	$q['per_sqft'] = $area > 0 ? $q['material'] / $area : 0;
	return $q;
}

/** [hcc_lanter_table] — slab materials for the roof of common single storey houses. */
function hcc_sc_lanter_table() {
	$rows = array();
	foreach ( array( '3-marla', '5-marla', '7-marla', '10-marla', '1-kanal' ) as $k ) {
		$s = hcc_plot_sizes()[ $k ];
		$area = hcc_suggest_covered_area( $s[1], 1 );
		$q = hcc_lanter_qty( $area );
		$rows[] = array( esc_html( $s[0] ), number_format( $area ) . ' sq ft', number_format( round( $q['cement'] ) ) . ' bags', number_format( round( $q['steel'] ) ) . ' kg', number_format( round( $q['sand'] ) ) . ' / ' . number_format( round( $q['crush'] ) ) . ' cft', hcc_pkr( $q['material'] ) );
	}
	return hcc_table( array( 'Plot (one roof)', 'Slab area', 'Cement', 'Steel', 'Sand / crush', 'Material cost' ), $rows )
		. '<p class="hint">5 inch slab, 1:2:4 mix, 0.85 kg steel per sq ft, calculator default rates (' . esc_html( hcc_rates_date_human() ) . '). Labour and shuttering are not included.</p>';
}
add_shortcode( 'hcc_lanter_table', 'hcc_sc_lanter_table' );

/** [hcc_lanter_calc] — roof slab materials and cost for any area. */
function hcc_sc_lanter_calc() {
	$r = hcc_default_calc_rates();
	$d = hcc_lanter_defaults();
	$q = hcc_lanter_qty( 1000 );
	$num = function ( $id, $label, $val, $step = 'any' ) {
		return '<label>' . $label . ' <input type="number" id="' . $id . '" value="' . esc_attr( $val ) . '" min="0" step="' . $step . '" inputmode="decimal"></label>';
	};
	$h = '<div class="area-converter lanter-calc card" id="lanter-calc"><h3>Lanter (roof slab) cost calculator</h3><div class="row-inline">'
		. $num( 'lc-area', 'Slab area (sq ft)', 1000 )
		. '<label>Thickness <select id="lc-thick"><option value="4">4 inch</option><option value="4.5">4.5 inch</option><option value="5" selected>5 inch</option><option value="6">6 inch</option></select></label>'
		. '<label>Concrete mix <select id="lc-mix"><option value="1:2:4" selected>1:2:4 (standard)</option><option value="1:1.5:3">1:1.5:3 (richer)</option></select></label>'
		. $num( 'lc-steelq', 'Steel (kg per sq ft)', $d['steel'] )
		. '</div><details class="lc-rates"><summary>Material rates (edit to match your quote)</summary><div class="row-inline">'
		. $num( 'lc-cement', 'Cement (PKR per bag)', $r['cement'] )
		. $num( 'lc-steel', 'Steel (PKR per kg)', $r['steel'] )
		. $num( 'lc-sand', 'Sand (PKR per cft)', $r['sand'] )
		. $num( 'lc-crush', 'Crush (PKR per cft)', $r['crush'] )
		. '</div></details><div class="row-inline">'
		. '<label>Labour + shuttering (PKR per sq ft, from your contractor) <input type="number" id="lc-lab" min="0" step="any" inputmode="decimal" placeholder="e.g. your contractor’s rate"></label></div>'
		. '<output id="lc-out" for="lc-area lc-thick lc-mix" aria-live="polite">For 1,000 sq ft at 5 inch: <strong>' . number_format( round( $q['cement'] ) ) . ' cement bags</strong>, ' . number_format( round( $q['steel'] ) ) . ' kg steel, ' . number_format( round( $q['sand'] ) ) . ' cft sand, ' . number_format( round( $q['crush'] ) ) . ' cft crush. Material cost <strong>' . hcc_pkr( $q['material'] ) . '</strong> (' . hcc_pkr( $q['per_sqft'] ) . ' per sq ft), without labour and shuttering.</output></div>';
	$h .= '<script data-no-optimize="1">(function(){var g=function(i){return document.getElementById(i)},o=g("lc-out");if(!o)return;'
		. 'function n(i){return parseFloat(g(i).value)||0}function f(x){return Math.round(x).toLocaleString("en-US")}'
		. 'function run(){var a=n("lc-area"),t=parseFloat(g("lc-thick").value),p=g("lc-mix").value.split(":").map(parseFloat),s=p[0]+p[1]+p[2],dry=a*t/12*' . $d['dry'] . ','
		. 'c=dry*p[0]/s/' . $d['bag_cft'] . ',sa=dry*p[1]/s,cr=dry*p[2]/s,st=a*n("lc-steelq"),m=c*n("lc-cement")+sa*n("lc-sand")+cr*n("lc-crush")+st*n("lc-steel"),l=a*n("lc-lab");'
		. 'var h="For "+f(a)+" sq ft at "+t+" inch: <strong>"+f(c)+" cement bags</strong>, "+f(st)+" kg steel, "+f(sa)+" cft sand, "+f(cr)+" cft crush. Material cost <strong>PKR "+f(m)+"</strong>"+(a?" (PKR "+f(m/a)+" per sq ft)":"");'
		. 'h+=l?". With labour and shuttering: <strong>PKR "+f(m+l)+"</strong> (PKR "+f((m+l)/a)+" per sq ft).":", without labour and shuttering.";o.innerHTML=h;}'
		. 'document.querySelectorAll("#lanter-calc input,#lanter-calc select").forEach(function(e){e.addEventListener("input",run);e.addEventListener("change",run);});run();})();</script>';
	return $h;
}
add_shortcode( 'hcc_lanter_calc', 'hcc_sc_lanter_calc' );

/** [hcc_storey_compare] — single vs double storey for every common plot. */
function hcc_sc_storey_compare() {
	$rows = array();
	foreach ( hcc_plot_sizes() as $k => $s ) {
		$one = hcc_estimate( $s[1], 1 ); $two = hcc_estimate( $s[1], 2 );
		$name = hcc_page_exists( $k . '-house-construction-cost' ) ? '<a href="' . esc_url( hcc_page_url( $k . '-house-construction-cost' ) ) . '">' . esc_html( $s[0] ) . '</a>' : esc_html( $s[0] );
		$rows[] = array( $name, number_format( $one['covered'] ) . ' sq ft', hcc_pkr( $one['grandTotal'] ), number_format( $two['covered'] ) . ' sq ft', hcc_pkr( $two['grandTotal'] ), hcc_pkr( $two['grandTotal'] - $one['grandTotal'] ) );
	}
	return hcc_table( array( 'Plot', 'Single storey area', 'Single storey cost', 'Double storey area', 'Double storey cost', 'Extra for 2nd floor' ), $rows )
		. '<p class="hint">Complete house, standard quality, Lahore, typical design, calculator default rates (' . esc_html( hcc_rates_date_human() ) . ').</p>';
}
add_shortcode( 'hcc_storey_compare', 'hcc_sc_storey_compare' );

/* --------------------------------------------------------------------------
 * FAQs for the pages that are not a plot size (FAQPage schema only).
 * ------------------------------------------------------------------------ */
function hcc_v110_faq_items( $key ) {
	$r = hcc_default_calc_rates();
	if ( 'double-storey' === $key ) {
		$s5 = hcc_estimate( 1125, 1 ); $d5 = hcc_estimate( 1125, 2 ); $d10 = hcc_estimate( 2250, 2 ); $g5 = hcc_estimate( 1125, 2, 'grey' );
		return array(
			array( 'q' => 'How much does a double storey house cost in Pakistan in ' . hcc_year() . '?', 'a' => 'At standard quality in Lahore a double storey 5 Marla house (about ' . number_format( $d5['covered'] ) . ' sq ft) costs about ' . hcc_format_pkr( $d5['grandTotal'] ) . ', and a double storey 10 Marla house (about ' . number_format( $d10['covered'] ) . ' sq ft) about ' . hcc_format_pkr( $d10['grandTotal'] ) . '. Grey structure alone for the 5 Marla double storey is about ' . hcc_format_pkr( $g5['grandTotal'] ) . '.' ),
			array( 'q' => 'Is double storey cheaper per sq ft than single storey?', 'a' => 'Not per sq ft: this calculator adds about 3% per extra storey for columns, steel and scaffolding. It is cheaper per bedroom, because the land, foundation and boundary are shared. On 5 Marla the second floor adds about ' . hcc_format_pkr( $d5['grandTotal'] - $s5['grandTotal'] ) . ' for ' . number_format( $d5['covered'] - $s5['covered'] ) . ' sq ft of extra space.' ),
			array( 'q' => 'Can I build the second floor later?', 'a' => 'Yes, many families do. Ask your structural engineer to design the foundation, columns and steel for two storeys from the start, and leave column steel (starter bars) out at the roof. Adding strength later is far more expensive than building it in.' ),
			array( 'q' => 'Double storey ghar banane ka kharcha kitna hai?', 'a' => '5 Marla double storey ka kharcha standard quality par taqreeban ' . hcc_format_pkr( $d5['grandTotal'] ) . ' hai (Lahore rates). Upar wali manzil ka kharcha neeche wali se thora zyada hota hai, lekin zameen aur foundation dobara nahi lagti.' ),
		);
	}
	if ( 'karachi-120' === $key ) {
		$k1 = hcc_estimate( 1080, 1, 'complete', 'standard', 'karachi' ); $k2 = hcc_estimate( 1080, 2, 'complete', 'standard', 'karachi' ); $g2 = hcc_estimate( 1080, 2, 'grey', 'standard', 'karachi' );
		return array(
			array( 'q' => 'How much does it cost to build a 120 sq yd house in Karachi?', 'a' => 'A ground + 1 house on a 120 sq yd (1,080 sq ft) plot has about ' . number_format( $k2['covered'] ) . ' sq ft of covered area and costs about ' . hcc_format_pkr( $k2['grandTotal'] ) . ' complete at standard quality, or ' . hcc_format_pkr( $g2['grandTotal'] ) . ' for the grey structure (Karachi rates, ' . hcc_rates_date_human() . ').' ),
			array( 'q' => 'How many square feet is 120 gaz?', 'a' => '120 sq yd (gaz) is 1,080 sq ft, because 1 sq yd = 9 sq ft. That is 4.8 Marla under the 225 sq ft Marla. A common Karachi 120 yard plot is 20 × 54 ft.' ),
			array( 'q' => 'What does a ground floor only 120 yard house cost?', 'a' => 'About ' . hcc_format_pkr( $k1['grandTotal'] ) . ' complete at standard quality for roughly ' . number_format( $k1['covered'] ) . ' sq ft of covered area. Most owners still build ground + 1, because the land and foundation are already paid for.' ),
			array( 'q' => '120 gaz ka ghar banane mein kitna kharcha aata hai?', 'a' => 'Karachi mein 120 gaz ground + 1 ghar ka kharcha standard quality par taqreeban ' . hcc_format_pkr( $k2['grandTotal'] ) . ' hai, aur sirf grey structure ' . hcc_format_pkr( $g2['grandTotal'] ) . '. Apne rates ke saath hisaab neeche wale calculator se lagayein.' ),
		);
	}
	if ( 'lanter' === $key ) {
		$q = hcc_lanter_qty( 1000 ); $q5 = hcc_lanter_qty( hcc_suggest_covered_area( 1125, 1 ) );
		return array(
			array( 'q' => 'What is the lanter cost per sq ft in Pakistan?', 'a' => 'Materials for a 5 inch, 1:2:4 RCC roof slab cost about ' . hcc_format_pkr( $q['per_sqft'] ) . ' per sq ft at our ' . hcc_rates_date_human() . ' rates (cement PKR ' . number_format( $r['cement'] ) . ' per bag, steel PKR ' . number_format( $r['steel'] ) . ' per kg). Add your contractor\'s labour and shuttering rate on top.' ),
			array( 'q' => 'How many cement bags are needed for a 1,000 sq ft lanter?', 'a' => 'About ' . number_format( round( $q['cement'] ) ) . ' bags for a 5 inch slab in 1:2:4, with about ' . number_format( round( $q['sand'] ) ) . ' cft sand, ' . number_format( round( $q['crush'] ) ) . ' cft crush and ' . number_format( round( $q['steel'] ) ) . ' kg steel.' ),
			array( 'q' => 'How much does the lanter of a 5 Marla house cost?', 'a' => 'A single storey 5 Marla house has about ' . number_format( hcc_suggest_covered_area( 1125, 1 ) ) . ' sq ft of roof. Its slab needs about ' . number_format( round( $q5['cement'] ) ) . ' cement bags and ' . number_format( round( $q5['steel'] ) ) . ' kg steel, about ' . hcc_format_pkr( $q5['material'] ) . ' in materials before labour and shuttering.' ),
			array( 'q' => 'Lanter ka kharcha kitna aata hai?', 'a' => '5 inch lanter (1:2:4) ka material taqreeban ' . hcc_format_pkr( $q['per_sqft'] ) . ' per sq ft banta hai. Mistri ki mazdoori aur shuttering ka rate apne contractor se poochein aur upar wale calculator mein daalein.' ),
		);
	}
	return array();
}
function hcc_v110_faq_render( $key, $slug, $intro ) {
	$items = hcc_v110_faq_items( $key );
	if ( ! $items ) return '';
	$main = array();
	foreach ( $items as $i ) $main[] = array( '@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $i['a'] ) ) );
	$schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => home_url( '/' . $slug . '/' ) . '#faq', 'inLanguage' => 'en-US', 'mainEntity' => $main );
	return hcc_render_faq_custom( $items, $intro )
		. '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>';
}

/* --------------------------------------------------------------------------
 * Page templates.
 * ------------------------------------------------------------------------ */
function hcc_v110_page_definitions() {
	$u = function ( $k ) { return esc_url( home_url( '/' . trim( $k, '/' ) . ( $k ? '/' : '' ) ) ); };
	$img = get_template_directory_uri() . '/assets/img/';
	$hcc_ver = HCC_VERSION;
	$y = hcc_year();
	$slab = esc_url( HCC_SLAB_SITE );
	$f = function ( $plot, $st, $type = 'complete', $city = 'lahore' ) {
		$e = hcc_estimate( $plot, $st, $type, 'standard', $city );
		return array( 'total' => hcc_format_pkr( $e['grandTotal'] ), 'covered' => number_format( $e['covered'] ), 'psf' => hcc_format_pkr( $e['costPerSqft'] ), 'raw' => $e['grandTotal'] );
	};
	$e6 = $f( 1350, 1 ); $e6g = $f( 1350, 1, 'grey' ); $e6d = $f( 1350, 2 );
	$e8 = $f( 1800, 2 ); $e8g = $f( 1800, 2, 'grey' ); $e8s = $f( 1800, 1 );
	$e2k = $f( 9000, 2 ); $e2kg = $f( 9000, 2, 'grey' ); $e1k = $f( 4500, 2 );
	$e5s = $f( 1125, 1 ); $e5d = $f( 1125, 2 ); $e5dg = $f( 1125, 2, 'grey' ); $e10d = $f( 2250, 2 );
	$k1 = $f( 1080, 1, 'complete', 'karachi' ); $k2 = $f( 1080, 2, 'complete', 'karachi' ); $k2g = $f( 1080, 2, 'grey', 'karachi' ); $k3 = $f( 1080, 3, 'complete', 'karachi' );
	$diff6_7 = hcc_format_pkr( hcc_estimate( 1575, 1 )['grandTotal'] - $e6['raw'] );
	$save2k = hcc_format_pkr( abs( 2 * $e1k['raw'] - $e2k['raw'] ) );
	$cmp2k = 2 * $e1k['raw'] >= $e2k['raw'] ? 'less' : 'more';
	$lq = hcc_lanter_qty( 1000 );
	$l_psf = hcc_format_pkr( $lq['per_sqft'] );
	$l_cement = number_format( round( $lq['cement'] ) ); $l_steel = number_format( round( $lq['steel'] ) );
	$l_steel_share = round( $lq['cost']['steel'] / $lq['material'] * 100 );
	$l_cement_share = round( $lq['cost']['cement'] / $lq['material'] * 100 );
	$r = hcc_default_calc_rates();
	$cement_rate = number_format( $r['cement'] ); $steel_rate = number_format( $r['steel'] );

	$defs = array();

	$defs['6-marla-house-construction-cost'] = array(
		'title' => '6 Marla House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a 6 Marla plot is 1,350 sq ft. Built single storey with about {$e6['covered']} sq ft of covered area, a complete house (standard quality, Lahore rates) costs about <strong>{$e6['total']}</strong> and the grey structure alone about <strong>{$e6g['total']}</strong>. A double storey 6 Marla house, about {$e6d['covered']} sq ft, costs roughly <strong>{$e6d['total']}</strong>.</p>

[house_cost_calculator mode="calculator_only" variant="6-marla" default_type="complete" faq="no"]

<figure class="illo"><img src="{$img}hero-6-marla.png?ver={$hcc_ver}" alt="Infographic of a 6 Marla plot (1,350 sq ft) in Pakistan with an estimated {$e6['covered']} sq ft single storey covered area" width="1200" height="630" loading="lazy"><figcaption>6 Marla plot size, covered area and storeys at a glance.</figcaption></figure>

<h2>How much does a 6 Marla house cost in {$y}?</h2>
[hcc_plot_tables marla="6" storeys="1"]
<p class="hint">Single storey, {$e6['covered']} sq ft covered area, Lahore, typical design. Use the calculator above for your own city and finishing level.</p>

<h2>How big is a 6 Marla plot?</h2>
[hcc_size_box marla="6"]

<h2>Single or double storey on 6 Marla?</h2>
<p>A 6 Marla plot (often 30 × 45 ft) is wide enough for three bedrooms on one floor, which is why many families in newer Lahore, Faisalabad and Multan schemes build it single storey. A double storey adds a separate TV lounge and two more bedrooms, or a rentable upper portion:</p>
[hcc_plot_tables marla="6" storeys="1" part="storeys"]

<h2>Typical 6 Marla layouts</h2>
<ul>
<li><strong>Single storey:</strong> three bedrooms with attached baths, drawing room, lounge-cum-dining, kitchen and a car porch.</li>
<li><strong>Double storey:</strong> ground floor as above; first floor with two bedrooms, a TV lounge and a small kitchen if it will be rented.</li>
</ul>
<p>The 30 ft width lets you keep the staircase at the side and still fit a full-width porch, which is hard on a 25 ft wide 5 Marla plot.</p>

<h2>Material quantities for a 6 Marla house</h2>
[hcc_plot_tables marla="6" storeys="1" part="qty"]
<p>These are grey-structure quantities for the single storey option at standard quality. Check today's <a href="{$u('cement-rate-today-pakistan')}">cement rate</a> and <a href="{$u('steel-saria-rate-today')}">saria rate</a>, or price the roof alone with the <a href="{$u('lanter-roof-slab-cost-pakistan')}">lanter cost calculator</a>.</p>

<h2>6 Marla vs 5 Marla and 7 Marla</h2>
<p>Going up to 7 Marla adds 225 sq ft of land and about {$diff6_7} of single storey construction. Compare the <a href="{$u('5-marla-house-construction-cost')}">5 Marla house estimate</a> and <a href="{$u('7-marla-house-construction-cost')}">what a 7 Marla house costs</a>, or see every size in our <a href="{$u('construction-cost-guides')}">construction cost guides</a>.</p>

[hcc_faq variant="6-marla"]
HTML
	);

	$defs['8-marla-house-construction-cost'] = array(
		'title' => '8 Marla House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> an 8 Marla plot is 1,800 sq ft. Most 8 Marla houses are double storey: about {$e8['covered']} sq ft of covered area costs about <strong>{$e8['total']}</strong> complete (standard quality, Lahore rates), or <strong>{$e8g['total']}</strong> for the grey structure. Built single storey (about {$e8s['covered']} sq ft) it costs about {$e8s['total']}.</p>

[house_cost_calculator mode="calculator_only" variant="8-marla" default_type="complete" faq="no"]

<figure class="illo"><img src="{$img}hero-8-marla.png?ver={$hcc_ver}" alt="Infographic of an 8 Marla plot (1,800 sq ft) in Pakistan with an estimated {$e8['covered']} sq ft double storey covered area" width="1200" height="630" loading="lazy"><figcaption>8 Marla plot size, covered area and storeys at a glance.</figcaption></figure>

<h2>How much does an 8 Marla house cost in {$y}?</h2>
[hcc_plot_tables marla="8" storeys="2"]
<p class="hint">Double storey, {$e8['covered']} sq ft covered area, Lahore, typical design. Use the calculator above for your own city and finishing level.</p>

<h2>How big is an 8 Marla plot?</h2>
[hcc_size_box marla="8"]

<h2>Single or double storey on 8 Marla?</h2>
<p>An 8 Marla plot is usually 30 × 60 ft, the same footprint as a 30 × 60 Islamabad and Rawalpindi plot. It is popular in Bahria Town and DHA because it fits a proper double storey family home with four or five bedrooms:</p>
[hcc_plot_tables marla="8" storeys="2" part="storeys"]

<h2>Typical 8 Marla layouts</h2>
<ul>
<li><strong>Ground floor:</strong> drawing and dining, lounge, kitchen, two bedrooms with attached baths, car porch for one or two cars.</li>
<li><strong>First floor:</strong> two or three bedrooms, TV lounge and a terrace at the front.</li>
<li><strong>Roof:</strong> stair tower (mumty), store and water tanks.</li>
</ul>

<h2>Material quantities for an 8 Marla house</h2>
[hcc_plot_tables marla="8" storeys="2" part="qty"]
<p>Grey-structure quantities for the double storey option at standard quality. Price them against today's <a href="{$u('construction-material-rates')}">construction material rates</a>.</p>

<h2>8 Marla vs 10 Marla</h2>
<p>A 10 Marla double storey house costs about {$e10d['total']}, compared with {$e8['total']} for 8 Marla. See the <a href="{$u('10-marla-house-construction-cost')}">10 Marla double storey cost</a>, the smaller <a href="{$u('7-marla-house-construction-cost')}">7 Marla house cost</a>, or the <a href="{$u('double-storey-house-construction-cost')}">double storey cost for every plot size</a>.</p>

[hcc_faq variant="8-marla"]
HTML
	);

	$defs['2-kanal-house-construction-cost'] = array(
		'title' => '2 Kanal House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a 2 Kanal plot is 9,000 sq ft (40 Marla). A double storey 2 Kanal house with about {$e2k['covered']} sq ft of covered area costs about <strong>{$e2k['total']}</strong> complete (standard quality, Lahore rates), or <strong>{$e2kg['total']}</strong> for the grey structure. Premium finishing, a basement or a pool raise this a lot, so use the calculator below for your own design.</p>

[house_cost_calculator mode="calculator_only" variant="2-kanal" default_type="complete" faq="no"]

<figure class="illo"><img src="{$img}hero-2-kanal.png?ver={$hcc_ver}" alt="Infographic of a 2 Kanal plot (9,000 sq ft) in Pakistan with an estimated {$e2k['covered']} sq ft double storey covered area" width="1200" height="630" loading="lazy"><figcaption>2 Kanal plot size, covered area and storeys at a glance.</figcaption></figure>

<h2>How much does a 2 Kanal house cost in {$y}?</h2>
[hcc_plot_tables marla="40" storeys="2"]
<p class="hint">Double storey, {$e2k['covered']} sq ft covered area, Lahore, typical design. Large houses are rarely "typical", so set design complexity and quality in the calculator.</p>

<h2>How big is a 2 Kanal plot?</h2>
[hcc_size_box marla="40"]

<h2>What a 2 Kanal house usually includes</h2>
<p>Two Kanal plots are mostly in DHA, Bahria Town, Gulberg and the Islamabad sectors. Coverage rules leave large lawns, so the covered area is less than double a 1 Kanal house. A typical 2 Kanal home has six or seven bedrooms, separate formal and family lounges, a home office, servant quarters and parking for four or more cars, and many have a basement.</p>
[hcc_plot_tables marla="40" storeys="2" part="storeys"]

<h2>Material quantities for a 2 Kanal house</h2>
[hcc_plot_tables marla="40" storeys="2" part="qty"]
<p>Grey-structure quantities for the double storey option at standard quality. On a house this size, buying steel and cement in bulk through the contractor and fixing the rate in the contract protects you from price jumps.</p>

<h2>2 Kanal vs two 1 Kanal houses</h2>
<p>Because the allowed coverage falls on bigger plots, one 2 Kanal house ({$e2k['total']}) costs about {$save2k} {$cmp2k} than building two 1 Kanal houses ({$e1k['total']} each). See the <a href="{$u('1-kanal-house-construction-cost')}">1 Kanal build budget</a>, the <a href="{$u('marla-kanal-standards-pakistan')}">Marla and Kanal converter</a>, or every size in our <a href="{$u('construction-cost-guides')}">cost guides hub</a>.</p>

[hcc_faq variant="2-kanal"]
HTML
	);

	$defs['double-storey-house-construction-cost'] = array(
		'title' => 'Double Storey House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a double storey house costs about <strong>{$e5d['psf']} per sq ft</strong> of covered area complete (standard quality, Lahore). On 5 Marla that is about {$e5d['covered']} sq ft for <strong>{$e5d['total']}</strong> ({$e5dg['total']} grey structure), and on 10 Marla about {$e10d['covered']} sq ft for <strong>{$e10d['total']}</strong>. The table below gives every plot size.</p>

<h2>Double storey cost by plot size ({$y})</h2>
[hcc_storey_compare]

[house_cost_calculator mode="calculator_only" variant="double-storey" default_type="complete" faq="no" qa="no"]

<h2>Why the second floor costs a little more per sq ft</h2>
<p>The calculator adds about 3% per extra storey: bigger columns and footings, more steel, scaffolding and carrying material up. What you save is land, foundation area and the boundary wall, so a double storey house is cheaper per bedroom even though each sq ft costs slightly more.</p>

<h2>Single storey now, second floor later?</h2>
<ul>
<li>Have the structure designed for two storeys from day one: foundation, column sizes and steel.</li>
<li>Leave column starter bars above the roof and protect them from rust.</li>
<li>Plan the staircase, water tank position and electrical risers for both floors.</li>
<li>Budget the second lanter (roof slab) separately: for 1,000 sq ft it needs about {$l_cement} cement bags and {$l_steel} kg steel. Our <a href="{$u('lanter-roof-slab-cost-pakistan')}">lanter cost calculator</a> works it out, and our sister site <a href="{$slab}">SlabCostCalculator.com</a> has a detailed roof slab estimate.</li>
</ul>

<h2>Double storey vs single storey</h2>
<p>On 5 Marla, single storey gives about {$e5s['covered']} sq ft for {$e5s['total']}; double storey gives {$e5d['covered']} sq ft for {$e5d['total']}. See the <a href="{$u('5-marla-house-construction-cost')}">5 Marla house estimate</a>, the <a href="{$u('8-marla-house-construction-cost')}">8 Marla double storey estimate</a>, the <a href="{$u('10-marla-house-construction-cost')}">10 Marla double storey cost</a> or the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>.</p>

[hcc_faq variant="page-double-storey"]
HTML
	);

	$defs['120-sq-yard-house-construction-cost-karachi'] = array(
		'title' => '120 Sq Yard House Construction Cost in Karachi (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a 120 sq yd (gaz) plot is 1,080 sq ft. A ground + 1 house with about {$k2['covered']} sq ft of covered area costs about <strong>{$k2['total']}</strong> complete (standard quality, Karachi rates), or <strong>{$k2g['total']}</strong> for the grey structure. Ground floor only costs about {$k1['total']}.</p>

<h2>120 gaz house cost in Karachi ({$y})</h2>
[hcc_plot_tables sqyd="120" storeys="2" city="karachi" part="storeys" options="1,2,3"]
<p class="hint">Standard quality, typical design, Karachi rates. Ground + 2 ({$k3['covered']} sq ft) is shown for owners who build a rentable portion where the building rules allow it.</p>

[house_cost_calculator mode="calculator_only" variant="city-karachi" city="karachi" default_type="complete" faq="no" qa="no"]

<h2>Cost by quality for ground + 1</h2>
[hcc_plot_tables sqyd="120" storeys="2" city="karachi"]

<h2>How big is a 120 yard plot?</h2>
<p>120 sq yd = 1,080 sq ft = about 100 m², or 4.8 Marla under the 225 sq ft Marla. The usual Karachi 120 yard plot is 20 × 54 ft, found in Gulshan-e-Iqbal, North Karachi, Gulistan-e-Jauhar, Scheme 33 and Saadi Town. Our <a href="{$u('marla-kanal-standards-pakistan')}">gaz, Marla and sq ft converter</a> handles other sizes.</p>

<h2>Karachi-specific things that change the cost</h2>
<ul>
<li><strong>Approval:</strong> plans are approved by the Sindh Building Control Authority (SBCA); setbacks and the number of floors depend on the scheme and road width.</li>
<li><strong>Soil and water:</strong> salty soil and sub-soil water in many areas call for sulphate-resisting cement in the foundation and good waterproofing.</li>
<li><strong>Water tanks:</strong> an underground tank is standard because of supply timings, which adds to the grey structure.</li>
</ul>

<h2>Material quantities for a 120 yard ground + 1 house</h2>
[hcc_plot_tables sqyd="120" storeys="2" city="karachi" part="qty"]
<p>Compare other Karachi plot sizes (80, 200, 240 and 400 sq yd) in our <a href="{$u('construction-cost-karachi')}">Karachi construction cost guide</a>, or see the <a href="{$u('construction-cost-by-city')}">cost per sq ft in every city</a>.</p>

[hcc_faq variant="page-karachi-120"]
HTML
	);

	$defs['lanter-roof-slab-cost-pakistan'] = array(
		'title' => 'Lanter (Roof Slab) Cost in Pakistan (2026): Per Sq Ft Calculator',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> the materials for a 5 inch RCC lanter (roof slab) in 1:2:4 cost about <strong>{$l_psf} per sq ft</strong> at {$y} rates. A 1,000 sq ft lanter needs about <strong>{$l_cement} bags of cement</strong> and <strong>{$l_steel} kg of steel</strong>. Labour and shuttering are extra; ask your contractor for their per sq ft rate and add it in the calculator.</p>

[hcc_lanter_calc]

<h2>Lanter material for common house sizes</h2>
[hcc_lanter_table]

<h2>How this lanter estimate is worked out</h2>
<ul>
<li><strong>Concrete:</strong> slab area × thickness gives the wet volume; × 1.54 gives the dry volume of cement, sand and crush.</li>
<li><strong>Mix 1:2:4:</strong> one part cement, two parts sand, four parts crush. One 50 kg bag is 1.25 cft of cement.</li>
<li><strong>Steel:</strong> 3-sutar (3/8 inch) bars at 6 inch spacing both ways is about 0.68 kg per sq ft; with laps, bends and extra top bars at the supports we use 0.85 kg per sq ft. Your structural drawing may say more for long spans.</li>
<li><strong>Rates:</strong> cement PKR {$cement_rate} per bag and steel PKR {$steel_rate} per kg ({$y} planning rates). Steel is about {$l_steel_share}% and cement about {$l_cement_share}% of the material cost, so those two rates matter most.</li>
</ul>

<h2>Things that change your lanter cost</h2>
<ul>
<li><strong>Span and thickness:</strong> rooms wider than about 14 ft usually need a 6 inch slab or beams.</li>
<li><strong>Beams:</strong> drop beams add concrete and steel that this per sq ft figure does not include.</li>
<li><strong>Ready-mix or site mix:</strong> ready-mix concrete costs more per cft but saves labour and gives a more even slab.</li>
<li><strong>Curing:</strong> keep the slab wet for at least 7 to 10 days; poor curing causes cracks and leaks later.</li>
</ul>
<p>For a detailed roof slab estimate with beams, different thicknesses and mixes, use our sister site <a href="{$slab}">SlabCostCalculator.com</a>. For the whole house, see the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>, the <a href="{$u('double-storey-house-construction-cost')}">double storey house cost</a> and today's <a href="{$u('steel-saria-rate-today')}">saria rate</a>.</p>

[hcc_faq variant="page-lanter"]
HTML
	);

	return $defs;
}

/** Slug => FAQ key for the pages above that are not plot sizes. */
function hcc_v110_faq_pages() {
	return array( 'double-storey' => 'double-storey-house-construction-cost', 'karachi-120' => '120-sq-yard-house-construction-cost-karachi', 'lanter' => 'lanter-roof-slab-cost-pakistan' );
}

/**
 * Create the Phase 4 pages that don't exist yet, already as live
 * [hcc_page] content. Never touches an existing page.
 */
function hcc_ensure_v110_pages() {
	$owner = get_user_by( 'email', HCC_OWNER_EMAIL );
	$created = array();
	foreach ( hcc_v110_page_definitions() as $slug => $def ) {
		if ( get_page_by_path( $slug ) ) continue;
		$id = wp_insert_post( array(
			'post_title' => $def['title'], 'post_name' => $slug, 'post_content' => hcc_live_page_shortcode_text( $slug ),
			'post_status' => 'publish', 'post_type' => 'page', 'post_author' => $owner ? $owner->ID : 1,
			'comment_status' => 'closed', 'ping_status' => 'closed',
		) );
		if ( $id && ! is_wp_error( $id ) ) $created[] = $slug;
	}
	if ( $created ) hcc_log( 'v1.11.0: created ' . count( $created ) . ' new page(s): ' . implode( ', ', $created ) );
}
