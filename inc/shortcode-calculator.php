<?php
/**
 * [house_cost_calculator] shortcode.
 *
 * Renders the interactive construction cost calculator plus, in "full" mode,
 * the complete SEO guide, FAQ section (with matching FAQPage JSON-LD built
 * from the SAME array as the visible markup, so the two can never drift out
 * of sync), and an E-E-A-T methodology block.
 *
 * Usage:
 *   [house_cost_calculator]                                  full guide + FAQ (Home page)
 *   [house_cost_calculator mode="calculator_only"]            calculator card only
 *   [house_cost_calculator default_type="grey"]                pre-selects a construction type
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================================
 * SEO AUDIT FIX (2026-09): server-side PHP port of the calculation engine in
 * assets/js/calculator.js. Formulas below MUST be kept identical to that
 * file — this is not a separate calculator, it exists purely so that the
 * "Quick Answer" strip and the 5/7/10 Marla & 1 Kanal examples table have
 * real numbers already present in the HTML that WordPress sends out, instead
 * of empty placeholders that only filled in after JavaScript ran. Search
 * engine and AI/answer-engine crawlers that don't execute JavaScript were
 * seeing blank values for exactly the numbers visitors search for most —
 * this was the single highest-priority finding in the September 2026 SEO
 * audit. The browser-side JS still runs as before and overwrites these with
 * the same numbers when the visitor changes city/quality, so nothing about
 * the interactive calculator changes for a human visitor.
 * ========================================================================== */

function hcc_base_qty_per_sqft() {
	return array( 'cementBags' => 0.42, 'steelKg' => 3.6, 'bricks' => 8.0, 'sandCft' => 1.1, 'crushCft' => 0.85 );
}
function hcc_quality_table() {
	return array(
		'economy'  => array( 'qtyFactor' => 0.88, 'greyLabour' => 520,  'finishRate' => 900,  'finishLabourShare' => 0.42, 'turnkeyRate' => 280,  'wallRatePerRft' => 2400 ),
		'standard' => array( 'qtyFactor' => 1.00, 'greyLabour' => 680,  'finishRate' => 1450, 'finishLabourShare' => 0.45, 'turnkeyRate' => 480,  'wallRatePerRft' => 3200 ),
		'premium'  => array( 'qtyFactor' => 1.15, 'greyLabour' => 900,  'finishRate' => 2300, 'finishLabourShare' => 0.47, 'turnkeyRate' => 900,  'wallRatePerRft' => 4200 ),
		'luxury'   => array( 'qtyFactor' => 1.35, 'greyLabour' => 1180, 'finishRate' => 3300, 'finishLabourShare' => 0.50, 'turnkeyRate' => 1500, 'wallRatePerRft' => 5600 ),
	);
}
function hcc_design_complexity_table() {
	return array(
		'efficient' => array( 'labourFactor' => 0.95, 'qtyBump' => 0.98 ),
		'typical'   => array( 'labourFactor' => 1.00, 'qtyBump' => 1.00 ),
		'complex'   => array( 'labourFactor' => 1.15, 'qtyBump' => 1.06 ),
	);
}
function hcc_city_factors() {
	return array(
		'lahore' => 1.00, 'karachi' => 0.98, 'islamabad' => 1.08, 'rawalpindi' => 0.95, 'faisalabad' => 0.90,
		'multan' => 0.88, 'peshawar' => 0.87, 'gujranwala' => 0.89, 'sialkot' => 0.90, 'hyderabad' => 0.88,
		'sargodha' => 0.87, 'other' => 0.90,
	);
}
function hcc_finish_material_split() {
	return array( 'tiles' => 0.30, 'plumbing' => 0.16, 'electrical' => 0.15, 'paint' => 0.12, 'woodwork' => 0.20, 'misc' => 0.07 );
}
function hcc_room_unit_cost() {
	return array(
		'economy'  => array( 'bathroom' => 70000,  'kitchen' => 250000 ),
		'standard' => array( 'bathroom' => 110000, 'kitchen' => 380000 ),
		'premium'  => array( 'bathroom' => 180000, 'kitchen' => 650000 ),
		'luxury'   => array( 'bathroom' => 280000, 'kitchen' => 1100000 ),
	);
}

function hcc_suggest_covered_area( $plot_sqft, $storeys ) {
	if ( $plot_sqft <= 1350 ) { $ratio = 0.85; }
	elseif ( $plot_sqft <= 2250 ) { $ratio = 0.75; }
	elseif ( $plot_sqft <= 4500 ) { $ratio = 0.65; }
	else { $ratio = 0.55; }
	return round( $plot_sqft * $ratio * $storeys );
}

/**
 * Mirrors computeEstimate() in assets/js/calculator.js line-for-line. Keep
 * both in sync if the pricing model ever changes.
 */
function hcc_compute_estimate( $in ) {
	$covered_area   = max( 0, $in['coveredArea'] );
	$storeys        = max( 1, $in['storeys'] );
	$has_basement   = ! empty( $in['hasBasement'] );
	$basement_area  = $has_basement ? max( 0, $in['basementArea'] ) : 0;
	$construction_type = $in['constructionType'];

	$qtable = hcc_quality_table();
	$q  = isset( $qtable[ $in['quality'] ] ) ? $qtable[ $in['quality'] ] : $qtable['standard'];
	$dctable = hcc_design_complexity_table();
	$dc = isset( $dctable[ $in['designComplexity'] ] ) ? $dctable[ $in['designComplexity'] ] : $dctable['typical'];
	$cftable = hcc_city_factors();
	$city_factor = isset( $cftable[ $in['city'] ] ) ? $cftable[ $in['city'] ] : $cftable['other'];

	$mat_city_factor = 1 + ( $city_factor - 1 ) * 0.35;
	$storey_factor   = 1 + max( 0, $storeys - 1 ) * 0.03;
	$complexity_labour_factor = $storey_factor * $dc['labourFactor'];
	$rates = $in['rates'];
	$base  = hcc_base_qty_per_sqft();

	$qty = array(
		'cementBags' => $covered_area * $base['cementBags'] * $q['qtyFactor'] * $dc['qtyBump'],
		'steelKg'    => $covered_area * $base['steelKg'] * $q['qtyFactor'] * $dc['qtyBump'],
		'bricks'     => $covered_area * $base['bricks'] * $q['qtyFactor'] * $dc['qtyBump'],
		'sandCft'    => $covered_area * $base['sandCft'] * $q['qtyFactor'] * $dc['qtyBump'],
		'crushCft'   => $covered_area * $base['crushCft'] * $q['qtyFactor'] * $dc['qtyBump'],
	);

	$item_cement      = $qty['cementBags'] * $rates['cement'] * $mat_city_factor * $storey_factor;
	$item_steel       = $qty['steelKg'] * $rates['steel'] * $mat_city_factor * $storey_factor;
	$item_bricks      = $qty['bricks'] * ( $rates['bricksPer1000'] / 1000 ) * $mat_city_factor * $storey_factor;
	$item_sand_crush  = ( $qty['sandCft'] * $rates['sand'] + $qty['crushCft'] * $rates['crush'] ) * $mat_city_factor * $storey_factor;
	$item_structure_labour = $covered_area * $q['greyLabour'] * $city_factor * $complexity_labour_factor;

	$grey_structure_cost = $item_cement + $item_steel + $item_bricks + $item_sand_crush + $item_structure_labour;
	$grey_rate_per_sqft   = $covered_area > 0 ? $grey_structure_cost / $covered_area : 0;
	$basement_cost = $has_basement ? $basement_area * $grey_rate_per_sqft * 1.45 : 0;
	$grey_structure_subtotal = $grey_structure_cost + $basement_cost;

	$item_tiles = $item_plumbing = $item_electrical = $item_paint = $item_woodwork = $item_misc_finish = $item_finish_labour = $item_room_extras = 0;
	$finishing_subtotal = 0;

	if ( 'complete' === $construction_type || 'turnkey' === $construction_type ) {
		$finish_material_total = $covered_area * $q['finishRate'] * ( 1 - $q['finishLabourShare'] ) * $mat_city_factor;
		$finish_labour_total   = $covered_area * $q['finishRate'] * $q['finishLabourShare'] * $city_factor * $dc['labourFactor'];
		$split = hcc_finish_material_split();

		$item_tiles       = $finish_material_total * $split['tiles'];
		$item_plumbing    = $finish_material_total * $split['plumbing'];
		$item_electrical  = $finish_material_total * $split['electrical'];
		$item_paint       = $finish_material_total * $split['paint'];
		$item_woodwork    = $finish_material_total * $split['woodwork'];
		$item_misc_finish = $finish_material_total * $split['misc'];
		$item_finish_labour = $finish_labour_total;

		$baseline_bathrooms = max( 1, (int) ceil( $in['bedrooms'] / 2 ) );
		$extra_bathrooms = max( 0, $in['bathrooms'] - $baseline_bathrooms );
		$extra_kitchens  = max( 0, $in['kitchens'] - 1 );
		$kitchen_factor  = 'turnkey' === $construction_type ? 1 : 0.5;
		$room_cost_table = hcc_room_unit_cost();
		$room_cost = isset( $room_cost_table[ $in['quality'] ] ) ? $room_cost_table[ $in['quality'] ] : $room_cost_table['standard'];
		$item_room_extras = $extra_bathrooms * $room_cost['bathroom'] + $extra_kitchens * $room_cost['kitchen'] * $kitchen_factor;

		$finishing_subtotal = $item_tiles + $item_plumbing + $item_electrical + $item_paint + $item_woodwork + $item_misc_finish + $item_finish_labour + $item_room_extras;
	}

	$item_turnkey = 'turnkey' === $construction_type ? $covered_area * $q['turnkeyRate'] * $city_factor * $dc['labourFactor'] : 0;

	$has_wall    = ! empty( $in['hasBoundaryWall'] );
	$wall_length = $has_wall ? max( 0, $in['wallLength'] ) : 0;
	$item_boundary_wall = $has_wall ? $wall_length * $q['wallRatePerRft'] * ( ( $mat_city_factor * 0.62 ) + ( $city_factor * 0.38 ) ) : 0;

	$has_approval = ! empty( $in['hasApprovalFee'] );
	$plot_marla   = $in['plotSqft'] > 0 ? $in['plotSqft'] / 225 : 0;
	$item_approval_fee = $has_approval ? $plot_marla * ( isset( $rates['approvalPerMarla'] ) ? $rates['approvalPerMarla'] : 0 ) : 0;

	$grand_total   = $grey_structure_subtotal + $finishing_subtotal + $item_turnkey + $item_boundary_wall + $item_approval_fee;
	$cost_per_sqft = $covered_area > 0 ? ( $grey_structure_subtotal + $finishing_subtotal + $item_turnkey ) / $covered_area : 0;

	return array(
		'coveredArea' => $covered_area,
		'grandTotal'  => $grand_total,
		'costPerSqft' => $cost_per_sqft,
	);
}

function hcc_format_pkr( $n ) {
	return 'PKR ' . number_format( round( $n ) );
}

function hcc_default_calc_rates() {
	return array( 'cement' => 1500, 'steel' => 280, 'bricksPer1000' => 20000, 'sand' => 90, 'crush' => 130, 'approvalPerMarla' => 18000 );
}

/**
 * Static HTML for the 5/7/10 Marla & 1 Kanal examples table — identical
 * markup and identical numbers to what assets/js/calculator.js's
 * renderExamplesTable() produces client-side, so there is no flash of
 * different content and no drift between the two.
 */
function hcc_render_examples_table_static() {
	$examples = array(
		array( 'label' => '5 Marla',  'plotSqft' => 5 * 225,  'storeys' => 1, 'url' => home_url( '/5-marla-house-construction-cost/' ) ),
		array( 'label' => '7 Marla',  'plotSqft' => 7 * 225,  'storeys' => 1, 'url' => home_url( '/7-marla-house-construction-cost/' ) ),
		array( 'label' => '10 Marla', 'plotSqft' => 10 * 225, 'storeys' => 2, 'url' => home_url( '/10-marla-house-construction-cost/' ) ),
		array( 'label' => '1 Kanal',  'plotSqft' => 4500,     'storeys' => 2, 'url' => home_url( '/1-kanal-house-construction-cost/' ) ),
	);
	$rates = hcc_default_calc_rates();
	$rows = '';
	foreach ( $examples as $ex ) {
		$covered_area = hcc_suggest_covered_area( $ex['plotSqft'], $ex['storeys'] );
		$r = hcc_compute_estimate( array(
			'coveredArea' => $covered_area, 'storeys' => $ex['storeys'], 'hasBasement' => false, 'basementArea' => 0,
			'hasBoundaryWall' => false, 'wallLength' => 0, 'hasApprovalFee' => false, 'plotSqft' => $ex['plotSqft'],
			'constructionType' => 'complete', 'quality' => 'standard', 'designComplexity' => 'typical', 'city' => 'lahore',
			'bedrooms' => 3, 'bathrooms' => 3, 'kitchens' => 1, 'rates' => $rates,
		) );
		$rows .= '<tr><td><a href="' . esc_url( $ex['url'] ) . '">' . esc_html( $ex['label'] ) . '</a></td><td>' . esc_html( number_format( round( $ex['plotSqft'] ) ) ) . ' sq ft</td>'
			. '<td>' . esc_html( $ex['storeys'] ) . '</td><td>' . esc_html( number_format( $covered_area ) ) . ' sq ft</td>'
			. '<td>' . esc_html( hcc_format_pkr( $r['costPerSqft'] ) ) . '</td><td>' . esc_html( hcc_format_pkr( $r['grandTotal'] ) ) . '</td></tr>';
	}
	return '<table class="mini-table"><thead><tr><th>Plot Size</th><th>Plot Area</th><th>Storeys</th>'
		. '<th>Est. Covered Area</th><th>Cost / Sq Ft</th><th>Est. Total Cost</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}

function hcc_faq_items() {
	return array(
		array(
			'q' => 'How much does it cost to build a house in Pakistan in 2026?',
			'a' => 'Most complete (structure plus finishing) houses in Pakistan\'s major cities cost roughly PKR 3,000 to PKR 5,500 per square foot in 2026, depending on city, quality level and design. A grey-structure-only build typically costs PKR 1,800 to PKR 3,000 per square foot. The exact figure depends on plot size, number of storeys, finishing standard and current material rates, which is why an itemized house construction cost calculator is more reliable than a single flat number.',
		),
		array(
			'q' => 'What is the construction cost per square foot in Pakistan?',
			'a' => 'Construction cost per square foot in Pakistan usually falls into three broad bands: economy (around PKR 2,600–3,300), standard (around PKR 3,300–4,300) and premium to luxury (PKR 4,500–8,500+), for complete house construction. Grey structure alone is lower, typically PKR 1,800–3,000 per square foot. Rates vary by city, with Islamabad and Lahore generally higher than smaller cities.',
		),
		array(
			'q' => 'What is the difference between grey structure and complete house construction?',
			'a' => 'Grey structure covers the structural shell only — foundation, columns, beams, slabs, roof and brickwork — without plaster finishing, flooring, paint, doors, sanitary fittings or electrical fixtures. Complete house construction adds all finishing work. Turnkey construction goes further and includes kitchen cabinets, wardrobes, light fixtures and other fit-outs so the house is ready to move into.',
		),
		array(
			'q' => 'How much does a 5 Marla house cost to construct?',
			'a' => 'A 5 Marla (1,125 sq ft plot) house with a single storey and around 950 sq ft of covered area typically costs roughly PKR 3 million at economy quality, around PKR 4 million at standard quality, and up to PKR 5.5 million or more at premium quality for complete house construction in 2026. A double-storey 5 Marla house with more covered area will cost proportionately more. Grey structure only would be considerably less. Use the calculator above for a figure based on your exact plot, city and quality preference — completely free and with no sign-up required — or see our <a href="' . esc_url( home_url( '/5-marla-house-construction-cost/' ) ) . '">full 5 Marla cost breakdown</a>.',
		),
		array(
			'q' => 'How much does a 10 Marla house cost to construct?',
			'a' => 'A 10 Marla (2,250 sq ft plot) house built as a double storey, with roughly 3,300–3,400 sq ft of covered area, generally costs between about PKR 11 million (economy quality) and PKR 19 million (premium quality) in 2026, with a standard-quality complete build costing around PKR 14 million. Exact cost depends on city, design and finishing choices — our <a href="' . esc_url( home_url( '/10-marla-house-construction-cost/' ) ) . '">10 Marla house cost guide</a> has the full breakdown.',
		),
		array(
			'q' => 'What is the grey structure cost per square foot in Lahore, Karachi and Islamabad right now?',
			'a' => 'Grey structure cost per square foot moves with cement, steel and local labour rates, so rather than quote one fixed number for every city, this calculator applies an editable city factor — Islamabad currently runs a little higher than Lahore, with Karachi close to Lahore and smaller cities somewhat lower. Select your city above and use the quick-answer strip or the calculator itself for a current, city-adjusted grey structure cost per square foot and complete house construction cost per square foot.',
		),
		array(
			'q' => 'How many cement bags are needed per square foot of construction?',
			'a' => 'As a general rule of thumb, residential construction in Pakistan uses approximately 0.35 to 0.50 bags of cement per square foot of covered area for the grey structure, depending on slab thickness, quality specification and structural design. This calculator uses an editable default of about 0.42 bags per square foot at standard quality.',
		),
		array(
			'q' => 'How much steel (saria) is required for a house in Pakistan?',
			'a' => 'Typical residential RCC construction in Pakistan uses around 3 to 4.5 kg of reinforcement steel (saria) per square foot of covered area, depending on the number of storeys, span sizes and structural design. Larger spans, cantilevers and additional storeys increase steel requirements.',
		),
		array(
			'q' => 'How many bricks are needed per square foot of house construction in Pakistan?',
			'a' => 'A typical residential structure in Pakistan uses roughly 7 to 9 bricks per square foot of covered area for walls and partitions, depending on wall thickness and design. This calculator uses an editable default of about 8 bricks per square foot, and you can adjust the brick price per 1,000 in the Advanced rates panel to match your local supplier\'s quotation.',
		),
		array(
			'q' => 'What is the average flooring, electrical, plumbing and paint cost per square foot in Pakistan?',
			'a' => 'For a standard-quality complete house, flooring/tiles typically account for roughly PKR 400–550 per sq ft, electrical wiring and fittings around PKR 200–280 per sq ft, plumbing pipework and fixtures around PKR 220–320 per sq ft, and paint/wall finishing around PKR 170–230 per sq ft — all scaled up at premium and luxury quality. This calculator itemizes each of these automatically inside your finishing cost breakdown, so you do not need to estimate them separately.',
		),
		array(
			'q' => 'How can I reduce my house construction cost without lowering quality too much?',
			'a' => 'Common ways to reduce construction cost include finalizing a complete design and Bill of Quantities before starting (to avoid costly mid-construction changes), buying bulk materials like cement and steel directly instead of through a contractor markup, choosing a simpler, more efficient layout with fewer curves and cantilevers, phasing finishing work, and getting at least three itemized quotations before hiring a contractor.',
		),
		array(
			'q' => 'How much does a boundary wall cost per running foot in Pakistan?',
			'a' => 'A plastered brick boundary wall (roughly 6–7 ft high, with pillars and coping) typically costs about PKR 2,200–2,600 per running foot at economy quality, PKR 2,900–3,500 at standard quality, and PKR 3,800–6,000+ per running foot at premium or luxury finish, depending on design and city. Tick "Include Boundary Wall" in the calculator above for an auto-suggested running length based on your plot size, which you can edit if you know your exact boundary measurements.',
		),
		array(
			'q' => 'What are typical government map or building approval (NOC) fees in Pakistan?',
			'a' => 'Map/building approval and no-objection-certificate (NOC) fees vary widely depending on the development authority or housing society (for example LDA, CDA, RDA or a private housing scheme) and on plot size and category, so there is no single fixed number. This calculator includes an optional, fully editable per-Marla allowance so you can budget for it — always confirm the exact current fee schedule with your relevant development authority or society before finalizing your budget.',
		),
		array(
			'q' => 'Is there a free construction cost calculator in Pakistan that does not require sign-up?',
			'a' => 'Yes — this house construction cost calculator is 100% free, requires no sign-up, account or contact details, and runs entirely in your browser. Unlike calculators built primarily to collect leads for a construction company, every rate assumption here is shown and editable, and you can download a full itemized PDF report without providing any personal information.',
		),
		array(
			'q' => 'Is this calculator accurate enough to use for a bank loan or tender document?',
			'a' => 'This calculator provides a planning-stage estimate based on typical Pakistani market rates and standard material-quantity assumptions, all of which are editable. It is a useful starting point for budgeting, but for bank loan applications, tender documents or contracts, you should get a certified estimate or quotation from a licensed structural engineer or registered contractor, since actual costs vary by site conditions, design and current market rates.',
		),
		array(
			'q' => 'How many square feet are in 1 Marla?',
			'a' => '1 Marla most commonly equals 225 square feet in Pakistan today — the "Society Marla" used by LDA, DHA, Bahria Town and most modern private housing societies, and the standard used throughout this calculator and its guides by default. However, at least three other standards are also used in practice: 250 sq ft in Karachi and some cantonment areas, 272 sq ft (the Land Revenue Marla used in many local records), and 272.25 sq ft (the older Patwari / revenue-record Marla still used in many rural areas and older land records). If your plot documents use a different standard, select it from the "Marla / Kanal Standard" dropdown in the calculator above so your plot size converts to the correct area in square feet before you calculate covered area and cost — see <a href="' . esc_url( home_url( '/marla-kanal-standards-pakistan/' ) ) . '">Pakistan’s 4 Marla standards</a> for a detailed comparison.',
		),
	);
}

function house_cost_calculator_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'mode'         => 'full',
			'default_type' => '',
			'variant'      => 'grey-structure',
			'city'         => '',
			'faq'          => 'yes',
		),
		$atts,
		'house_cost_calculator'
	);

	$img_url = get_template_directory_uri() . '/assets/img/';
	$hcc_cities_all = hcc_city_factors();
	$hcc_city = ( $atts['city'] && isset( $hcc_cities_all[ $atts['city'] ] ) ) ? $atts['city'] : 'lahore';
	$hcc_city_labels = hcc_city_labels();
	// Pre-set the plot size on plot-size guide pages so the calculator opens on
	// the size the visitor searched for (e.g. 10 Marla, double storey).
	$hcc_presets = array( '3-marla' => array( 3, 'marla', 1 ), '4-marla' => array( 4, 'marla', 1 ), '5-marla' => array( 5, 'marla', 1 ), '7-marla' => array( 7, 'marla', 1 ), '10-marla' => array( 10, 'marla', 2 ), '1-kanal' => array( 1, 'kanal', 2 ) );
	$hcc_preset = isset( $hcc_presets[ $atts['variant'] ] ) ? $hcc_presets[ $atts['variant'] ] : array( 5, 'marla', 1 );

	// SEO AUDIT FIX: pre-calculate the default (Lahore, standard quality,
	// 1,000 sq ft) quick-answer figures server-side so they are present in
	// the HTML WordPress sends out, not blank until JS runs. calculator.js's
	// updateQuickAnswer() still recalculates and overwrites these the moment
	// the page loads and whenever the visitor changes city — same formulas,
	// same numbers, so nothing visually changes for a human visitor.
	$hcc_qa_rates = hcc_default_calc_rates();
	$hcc_qa_common = array(
		'coveredArea' => 1000, 'storeys' => 1, 'hasBasement' => false, 'basementArea' => 0,
		'hasBoundaryWall' => false, 'wallLength' => 0, 'hasApprovalFee' => false, 'plotSqft' => 0,
		'quality' => 'standard', 'designComplexity' => 'typical', 'city' => $hcc_city,
		'bedrooms' => 3, 'bathrooms' => 3, 'kitchens' => 1, 'rates' => $hcc_qa_rates,
	);
	$hcc_qa_grey     = hcc_compute_estimate( array_merge( $hcc_qa_common, array( 'constructionType' => 'grey' ) ) );
	$hcc_qa_complete = hcc_compute_estimate( array_merge( $hcc_qa_common, array( 'constructionType' => 'complete' ) ) );

	ob_start();
	?>
	<div class="quick-answer" id="quick-answer">
		<strong>Quick answer:</strong>
		<span class="qa-item">Grey structure in <span id="qa-city-label"><?php echo esc_html( isset( $hcc_city_labels[ $hcc_city ] ) ? $hcc_city_labels[ $hcc_city ] : 'Lahore' ); ?></span>: <span class="qa-val" id="qa-grey-val"><?php echo esc_html( hcc_format_pkr( $hcc_qa_grey['costPerSqft'] ) ); ?> / sq ft</span></span>
		<span class="qa-item">Complete house: <span class="qa-val" id="qa-complete-val"><?php echo esc_html( hcc_format_pkr( $hcc_qa_complete['costPerSqft'] ) ); ?> / sq ft</span></span>
		<span class="qa-item" style="color:#b7c6dc">(1,000 sq ft, standard quality — change city below)</span>
	</div>

	<?php if ( 'full' !== $atts['mode'] ) echo hcc_variant_before_calculator( $atts['variant'] ); ?>

	<section class="calc-card" id="calculator" aria-labelledby="calc-heading">
		<h2 id="calc-heading" style="margin-top:0; font-size:20px;">Construction Cost Calculator</h2>
		<p style="color:var(--ink-soft); font-size:14px; margin-top:-6px;">Fill in your plot and building details below to get a free, instant building cost estimator result for Pakistan. All prices are shown in Pakistani Rupees (PKR) and every rate assumption can be customized to match your local market — no sign-up required.</p>

		<form id="calc-form" novalidate<?php echo $atts['default_type'] ? ' data-default-type="' . esc_attr( $atts['default_type'] ) . '"' : ''; ?>>

			<fieldset>
				<legend>Location &amp; Plot Size</legend>
				<div class="grid">
					<div class="field">
						<label for="city">City / Location</label>
						<select id="city" name="city">
							<option value="lahore"<?php selected( $hcc_city, 'lahore' ); ?>>Lahore</option>
							<option value="karachi"<?php selected( $hcc_city, 'karachi' ); ?>>Karachi</option>
							<option value="islamabad"<?php selected( $hcc_city, 'islamabad' ); ?>>Islamabad</option>
							<option value="rawalpindi"<?php selected( $hcc_city, 'rawalpindi' ); ?>>Rawalpindi</option>
							<option value="faisalabad"<?php selected( $hcc_city, 'faisalabad' ); ?>>Faisalabad</option>
							<option value="multan"<?php selected( $hcc_city, 'multan' ); ?>>Multan</option>
							<option value="peshawar"<?php selected( $hcc_city, 'peshawar' ); ?>>Peshawar</option>
							<option value="gujranwala"<?php selected( $hcc_city, 'gujranwala' ); ?>>Gujranwala</option>
							<option value="sialkot"<?php selected( $hcc_city, 'sialkot' ); ?>>Sialkot</option>
							<option value="hyderabad"<?php selected( $hcc_city, 'hyderabad' ); ?>>Hyderabad</option>
							<option value="sargodha"<?php selected( $hcc_city, 'sargodha' ); ?>>Sargodha</option>
							<option value="other"<?php selected( $hcc_city, 'other' ); ?>>Other City</option>
						</select>
						<span class="hint">Powers a construction cost calculator for Lahore, Karachi, Islamabad and 8 other cities — adjusts labour and finishing rates for your area.</span>
					</div>
					<div class="field">
						<label for="plot-size">Plot Size</label>
						<div class="row-inline">
							<input type="number" id="plot-size" name="plot-size" min="0.5" step="0.5" value="<?php echo esc_attr( $hcc_preset[0] ); ?>" inputmode="decimal">
							<select id="plot-unit" name="plot-unit">
								<option value="marla"<?php selected( $hcc_preset[1], 'marla' ); ?>>Marla</option>
								<option value="kanal"<?php selected( $hcc_preset[1], 'kanal' ); ?>>Kanal</option>
								<option value="sqft">Sq Ft</option>
							</select>
						</div>
						<span class="hint" id="plot-sqft-hint">= 1,125 sq ft (1 Marla = 225 sq ft)</span>
					</div>
					<div class="field" id="marla-standard-wrap">
						<label for="marla-standard">Marla / Kanal Standard</label>
						<select id="marla-standard" name="marla-standard">
							<option value="225" selected>225 sq ft — Society Marla (LDA, DHA, Bahria Town &amp; most modern societies)</option>
							<option value="250">250 sq ft — Karachi / Cantonment Marla</option>
							<option value="272">272 sq ft — Land Revenue Marla</option>
							<option value="272.25">272.25 sq ft — Patwari / Revenue-Record Marla (rural &amp; older records)</option>
						</select>
						<span class="hint">Pakistan has more than one Marla standard in everyday use. 225 sq ft ("Society Marla") is the most common today and is used throughout this calculator's guides — switch this if your plot's own record uses a different one. See our <a href="<?php echo esc_url( home_url( '/marla-kanal-standards-pakistan/' ) ); ?>">full guide to Pakistan's Marla &amp; Kanal standards</a> for details.</span>
					</div>
				</div>
				<div class="preset-row" role="group" aria-label="Quick plot size presets">
					<button type="button" class="preset-btn" data-marla="5">5 Marla</button>
					<button type="button" class="preset-btn" data-marla="7">7 Marla</button>
					<button type="button" class="preset-btn" data-marla="10">10 Marla</button>
					<button type="button" class="preset-btn" data-kanal="1">1 Kanal</button>
					<button type="button" class="preset-btn" data-kanal="2">2 Kanal</button>
					<button type="button" class="preset-btn" id="preset-custom-btn" data-custom="1">Custom Size</button>
				</div>
			</fieldset>

			<fieldset>
				<legend>Building Details</legend>
				<div class="grid">
					<div class="field">
						<label for="covered-area">Total Covered Area (sq ft, all floors)</label>
						<input type="number" id="covered-area" name="covered-area" min="100" step="10" value="1350" inputmode="decimal">
						<span class="hint" id="covered-area-hint">Auto-suggested from plot size &amp; storeys — edit if you have an exact drawing.</span>
					</div>
					<div class="field">
						<label for="storeys">Number of Storeys</label>
						<select id="storeys" name="storeys">
							<option value="1"<?php selected( $hcc_preset[2], 1 ); ?>>Single Storey (Ground Only)</option>
							<option value="2"<?php selected( $hcc_preset[2], 2 ); ?>>Double Storey (Ground + 1)</option>
							<option value="3">Triple Storey (Ground + 2)</option>
							<option value="4">Ground + 3</option>
						</select>
					</div>
					<div class="field">
						<label for="bedrooms">Bedrooms</label>
						<input type="number" id="bedrooms" name="bedrooms" min="0" max="12" step="1" value="3">
					</div>
					<div class="field">
						<label for="bathrooms">Bathrooms</label>
						<input type="number" id="bathrooms" name="bathrooms" min="0" max="12" step="1" value="3">
					</div>
					<div class="field">
						<label for="kitchens">Kitchens</label>
						<input type="number" id="kitchens" name="kitchens" min="0" max="4" step="1" value="1">
					</div>
					<div class="field">
						<label>&nbsp;</label>
						<div class="checkbox-row">
							<input type="checkbox" id="has-basement" name="has-basement">
							<label for="has-basement" style="font-weight:500">Include Basement</label>
						</div>
					</div>
				</div>
				<div class="grid" id="basement-area-wrap" hidden style="margin-top:14px">
					<div class="field">
						<label for="basement-area">Basement Area (sq ft)</label>
						<input type="number" id="basement-area" name="basement-area" min="0" step="10" value="0">
						<span class="hint">Basement construction typically costs about 45% more per sq ft (excavation, shoring, waterproofing, DPC).</span>
					</div>
				</div>
				<div class="grid" style="margin-top:14px">
					<div class="field">
						<div class="checkbox-row">
							<input type="checkbox" id="has-boundary-wall" name="has-boundary-wall">
							<label for="has-boundary-wall" style="font-weight:500">Include Boundary Wall</label>
						</div>
						<span class="hint">A cost head many simple calculators leave out entirely.</span>
					</div>
					<div class="field">
						<div class="checkbox-row">
							<input type="checkbox" id="has-approval-fee" name="has-approval-fee">
							<label for="has-approval-fee" style="font-weight:500">Include Approval / NOC Fee Allowance</label>
						</div>
						<span class="hint">Editable estimate for map/building approval fees.</span>
					</div>
				</div>
				<div class="grid" id="wall-length-wrap" hidden style="margin-top:14px">
					<div class="field">
						<label for="wall-length">Boundary Wall Length (running ft)</label>
						<input type="number" id="wall-length" name="wall-length" min="0" step="5" value="0">
						<span class="hint">Auto-suggested from plot perimeter — edit if you have an exact boundary survey.</span>
					</div>
				</div>
			</fieldset>

			<fieldset>
				<legend>Construction Type</legend>
				<div class="radio-grid" role="radiogroup" aria-label="Construction type">
					<label class="radio-card">
						<input type="radio" name="construction-type" value="grey">
						<strong>Grey Structure</strong>
						<span>Foundation, columns, beams, slab, roof &amp; brickwork only.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="construction-type" value="complete" checked>
						<strong>Complete House</strong>
						<span>Grey structure + plaster, flooring, paint, plumbing &amp; electrical.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="construction-type" value="turnkey">
						<strong>Turnkey</strong>
						<span>Complete house + kitchen cabinets, wardrobes &amp; fit-outs, move-in ready.</span>
					</label>
				</div>
			</fieldset>

			<fieldset>
				<legend>Quality / Finishing Standard</legend>
				<div class="radio-grid" role="radiogroup" aria-label="Quality standard">
					<label class="radio-card">
						<input type="radio" name="quality" value="economy">
						<strong>Economy</strong>
						<span>Budget-grade materials, basic fittings.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="quality" value="standard" checked>
						<strong>Standard</strong>
						<span>Good branded materials, mid-range fittings.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="quality" value="premium">
						<strong>Premium</strong>
						<span>High-end tiles, sanitary &amp; electrical fittings.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="quality" value="luxury">
						<strong>Luxury</strong>
						<span>Imported finishes, designer fittings &amp; woodwork.</span>
					</label>
				</div>
			</fieldset>

			<fieldset>
				<legend>Design Complexity</legend>
				<div class="radio-grid" role="radiogroup" aria-label="Design complexity">
					<label class="radio-card">
						<input type="radio" name="design-complexity" value="efficient">
						<strong>Efficient / Simple</strong>
						<span>Rectangular layout, minimal cantilevers.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="design-complexity" value="typical" checked>
						<strong>Typical</strong>
						<span>Standard residential layout &amp; spans.</span>
					</label>
					<label class="radio-card">
						<input type="radio" name="design-complexity" value="complex">
						<strong>Architecturally Complex</strong>
						<span>Curves, large cantilevers, double-height spaces.</span>
					</label>
				</div>
			</fieldset>

			<fieldset>
				<legend>Material, Labour &amp; Fee Rates (Advanced)</legend>
				<details class="advanced">
					<summary>Customize construction material rates Pakistan 2026 for your local market</summary>
					<p class="hint" style="margin:10px 0 0;">Defaults reflect typical Pakistani market rates as of 2026. Edit any field below to match your city's current supplier quotations — the calculator will use your numbers instead.</p>
					<div class="grid">
						<div class="field">
							<label for="rate-cement">Cement (PKR / 50kg bag)</label>
							<input type="number" id="rate-cement" min="0" step="10" value="1500">
						</div>
						<div class="field">
							<label for="rate-steel">Steel / Saria (PKR / kg)</label>
							<input type="number" id="rate-steel" min="0" step="1" value="280">
						</div>
						<div class="field">
							<label for="rate-bricks">Bricks (PKR / 1000 nos.)</label>
							<input type="number" id="rate-bricks" min="0" step="500" value="20000">
						</div>
						<div class="field">
							<label for="rate-sand">Sand (PKR / cft)</label>
							<input type="number" id="rate-sand" min="0" step="5" value="90">
						</div>
						<div class="field">
							<label for="rate-crush">Crush / Bajri (PKR / cft)</label>
							<input type="number" id="rate-crush" min="0" step="5" value="130">
						</div>
						<div class="field">
							<label for="rate-approval">Approval / NOC Fee (PKR per Marla)</label>
							<input type="number" id="rate-approval" min="0" step="500" value="18000">
						</div>
					</div>
				</details>
			</fieldset>

			<div class="actions">
				<button type="submit" class="btn btn-primary" id="calc-btn">Calculate Construction Cost</button>
				<button type="button" class="btn btn-secondary" id="reset-btn">Reset</button>
			</div>
			<div id="form-error" class="error-box" hidden role="alert"></div>
		</form>
	</section>

	<section id="results" hidden aria-labelledby="results-heading">
		<div class="results-card">
			<h2 id="results-heading" style="margin-top:0;">Your Estimated Construction Cost Breakdown</h2>
			<div class="summary-grid" id="summary-grid"></div>

			<div class="table-scroll">
				<table class="breakdown" id="cost-breakdown-table">
					<caption>Itemized House Construction Cost Breakdown</caption>
					<thead><tr><th>Item</th><th class="num">Estimated Cost (PKR)</th></tr></thead>
					<tbody id="cost-breakdown-body"></tbody>
				</table>
			</div>

			<div class="table-scroll">
				<table class="breakdown" id="qty-table">
					<caption>Estimated Material Quantities</caption>
					<thead><tr><th>Material</th><th class="num">Estimated Quantity</th></tr></thead>
					<tbody id="qty-body"></tbody>
				</table>
			</div>

			<div class="disclaimer">
				<strong>Please note:</strong> These figures are planning-stage estimates based on typical 2026 Pakistani market rates and standard material-quantity assumptions, all of which you can edit above. Actual construction cost can vary significantly by exact location, current market rates, site conditions, structural design, contractor and material brand. Always get a written quotation from a licensed contractor or structural engineer before finalizing your budget.
			</div>

			<div class="result-actions">
				<button type="button" class="btn btn-primary" id="download-pdf-btn">Download PDF Report</button>
				<button type="button" class="btn btn-secondary" id="edit-inputs-btn">Edit Inputs</button>
			</div>
			<?php if ( 'embed' !== $atts['variant'] ) echo hcc_feedback_widget_html(); ?>
		</div>
	</section>

	<?php if ( 'embed' === $atts['variant'] ) : ?>
		<?php /* Embed widget: calculator only — no FAQ or schema (the host page is not ours). */ ?>
	<?php elseif ( 'full' === $atts['mode'] ) : ?>
		<?php echo hcc_render_guide( $img_url ); ?>
		<?php echo hcc_render_faq(); ?>
		<?php echo hcc_render_eeat(); ?>
		<?php echo hcc_render_schema(); ?>
	<?php else : ?>
		<section class="static-page" style="margin-top:0">
			<div class="card">
				<p><?php echo hcc_variant_note( $atts['variant'] ); ?></p>
			</div>
		</section>
		<?php
		echo hcc_variant_after_calculator( $atts['variant'] );
		// SEO AUDIT FIX: this page previously stopped here (two intro
		// paragraphs + calculator, nothing else) with no FAQ and no schema.
		// GENERALIZED (blog/topical-authority phase): the "variant" attribute
		// lets any non-full-mode page (Grey Structure, and now the 5/7/10
		// Marla & 1 Kanal guides) get its own on-topic FAQ subset + matching
		// FAQPage/WebApplication schema, instead of this being hardcoded to
		// Grey Structure only.
		if ( 'no' !== $atts['faq'] ) {
			echo hcc_render_faq_custom(
				hcc_variant_faq_items( $atts['variant'] ),
				hcc_variant_faq_intro( $atts['variant'] )
			);
			echo hcc_variant_schema( $atts['variant'] );
		}
		?>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}
add_shortcode( 'house_cost_calculator', 'house_cost_calculator_shortcode' );

function hcc_render_guide( $img_url ) {
	ob_start();
	?>
	<section id="guide" aria-labelledby="guide-heading">
		<div class="guide-title-row">
			<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><use href="#icon-ruler-compass"></use></svg>
			<h2 id="guide-heading">House Construction Cost in Pakistan (2026): The Complete Guide</h2>
		</div>
		<div class="title-underline" aria-hidden="true"></div>
		<p style="font-size:12.5px; color:var(--ink-soft); margin:0 0 14px; font-weight:600;">Rates last updated: <?php echo esc_html( hcc_rates_date_human() ); ?> &nbsp;·&nbsp; Covers Lahore, Karachi, Islamabad &amp; 8 other cities</p>
		<p class="wrap-intro">Whether you are budgeting for a 5 Marla house, planning a 10 Marla double-storey home, or pricing out a 1 Kanal turnkey project, understanding how construction cost is actually calculated helps you avoid surprises. This free house construction cost calculator guide explains grey structure vs complete vs turnkey construction, typical building cost per square foot, material quantity and labour costs, and how to plan a realistic budget as a construction cost estimator for your own project — including the cost of building a house in Pakistan per Marla. Tap any section below to read the full explanation.</p>

		<nav class="toc" aria-labelledby="toc-heading">
			<p class="toc-title" id="toc-heading">Table of Contents</p>
			<ol>
				<li><a href="#sec-how-calculated">How House Construction Cost Is Calculated</a></li>
				<li><a href="#sec-grey-vs-complete">Grey Structure vs Complete vs Turnkey</a></li>
				<li><a href="#sec-cost-per-sqft">Construction Cost Per Square Foot</a></li>
				<li><a href="#sec-material-vs-labour">Material Cost vs Labour Cost</a></li>
				<li><a href="#sec-examples">5, 7, 10 Marla &amp; 1 Kanal Cost Examples</a></li>
				<li><a href="#sec-factors">Factors That Affect Construction Cost</a></li>
				<li><a href="#sec-reduce-cost">How to Reduce Construction Cost</a></li>
			</ol>
		</nav>

		<details class="accordion" id="sec-how-calculated">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-formula"></use></svg></span>
				<span class="sum-text">
					<h3>How House Construction Cost Is Calculated in Pakistan</h3>
					<span class="preview">Covered area, quality level, city, design complexity and construction type are the biggest drivers of your total cost.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<p>House construction cost estimation in Pakistan generally works by multiplying your total covered area (in square feet) by a construction rate per square foot, then adjusting for city, quality standard, design complexity and the scope of work you actually want done. Covered area is not the same as plot size — a 5 Marla plot (1,125 sq ft) built as a double storey home could have roughly 1,900–2,100 sq ft of total covered area once you add both floors and typical coverage ratios allowed by local building bylaws.</p>
				<p>A detailed estimate, like the one this house construction cost calculator produces, goes a step further by breaking the rate per square foot into its underlying components: material quantities (cement, steel, bricks, sand, crush) multiplied by current market unit prices, plus a separate labour cost for structure work and finishing work. This "bottom-up" approach is more transparent than a single flat rate because you can see exactly which assumption to change if your local prices differ — for example, if cement is more expensive in your city, you only need to update that one number rather than re-estimating the whole project.</p>
				<p>Two houses of the same covered area can have very different costs depending on the number of storeys (which affects foundation and steel requirements), the presence of a basement or boundary wall, an architecturally complex design, the number of bathrooms and kitchens (which affects plumbing, tiling and fixture costs), and of course the quality tier chosen for finishing materials.</p>
			</div>
		</details>

		<details class="accordion" id="sec-grey-vs-complete">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-layers"></use></svg></span>
				<span class="sum-text">
					<h3>Grey Structure vs Complete House vs Turnkey Construction</h3>
					<span class="preview">Grey structure is the shell only; complete house adds finishing; turnkey adds fit-outs so you can move straight in.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<figure class="illo">
					<img src="<?php echo esc_url( $img_url . 'grey-complete-turnkey-stages.svg' ); ?>" alt="Illustration comparing three house construction stages: grey structure with only the bare frame and brickwork, complete house adding plaster, paint and windows, and turnkey adding a finished roof and doors" width="560" height="150" loading="lazy">
					<figcaption>Grey structure, complete house and turnkey construction compared side by side.</figcaption>
				</figure>
				<p><strong>Grey structure cost</strong> covers only the structural shell of the house: excavation, foundation, DPC, columns, beams, roof slab, brickwork for walls, and basic staircase. It does not include plaster, flooring, paint, doors, windows, plumbing fixtures, electrical fittings or any finishing work. Many buyers in Pakistan build the grey structure first and finish the house in phases as budget allows — which is exactly why a <a href="<?php echo esc_url( home_url( '/grey-structure-cost-calculator/' ) ); ?>">dedicated grey structure cost calculator</a> is useful on its own.</p>
				<p><strong>Complete house construction</strong> includes everything in grey structure plus internal and external plaster, flooring and tiling, paint, doors and windows, plumbing pipework and sanitary fixtures, electrical wiring and fittings, and a functional kitchen and bathrooms. This is what most people mean by a "ready to live in, but simply finished" house.</p>
				<p><strong>Turnkey construction</strong> goes a step further and includes built-in wardrobes, kitchen cabinets, false ceiling where applicable, light fixtures, and other fit-outs — the idea being that you can move in with just your furniture. Turnkey projects cost more per square foot but remove the hassle (and hidden costs) of managing finishing contractors separately.</p>
				<h4>Typical scope comparison</h4>
				<table class="mini-table">
					<thead><tr><th>Included Work</th><th>Grey Structure</th><th>Complete House</th><th>Turnkey</th></tr></thead>
					<tbody>
						<tr><td>Foundation, columns, slab, roof</td><td>✔</td><td>✔</td><td>✔</td></tr>
						<tr><td>Brickwork &amp; DPC</td><td>✔</td><td>✔</td><td>✔</td></tr>
						<tr><td>Plaster, flooring &amp; paint</td><td>✘</td><td>✔</td><td>✔</td></tr>
						<tr><td>Plumbing &amp; electrical fittings</td><td>✘</td><td>✔</td><td>✔</td></tr>
						<tr><td>Boundary wall (optional add-on)</td><td>Optional</td><td>Optional</td><td>Optional</td></tr>
						<tr><td>Kitchen cabinets &amp; wardrobes</td><td>✘</td><td>Partial</td><td>✔</td></tr>
						<tr><td>Move-in ready</td><td>✘</td><td>Mostly</td><td>✔</td></tr>
					</tbody>
				</table>
			</div>
		</details>

		<details class="accordion" id="sec-cost-per-sqft">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-ruler"></use></svg></span>
				<span class="sum-text">
					<h3>Construction Cost Per Square Foot Explained</h3>
					<span class="preview">Per-sq-ft rates in Pakistan typically range from around PKR 1,800 (grey) to PKR 7,000+ (luxury complete).</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<figure class="illo">
					<img src="<?php echo esc_url( $img_url . 'cost-per-sqft-chart.svg' ); ?>" alt="Bar chart of construction cost per square foot in Pakistan across grey structure, economy, standard, premium and turnkey quality tiers" width="520" height="170" loading="lazy">
					<figcaption>Indicative construction cost per square foot in Pakistan by quality tier (2026).</figcaption>
				</figure>
				<p>Construction cost per square foot is simply your total estimated cost divided by total covered area, and it's the most common way contractors and homeowners in Pakistan compare projects — and the fastest way to compare a construction cost calculator Lahore result against a construction cost calculator Karachi or construction cost calculator Islamabad result. Based on typical 2026 market data across major cities, indicative ranges are:</p>
				<ul>
					<li><strong>Grey structure only:</strong> roughly PKR 1,800 – 3,000 per sq ft</li>
					<li><strong>Complete house — economy quality:</strong> roughly PKR 2,600 – 3,300 per sq ft</li>
					<li><strong>Complete house — standard quality:</strong> roughly PKR 3,300 – 4,300 per sq ft</li>
					<li><strong>Complete house — premium quality:</strong> roughly PKR 4,500 – 6,000 per sq ft</li>
					<li><strong>Turnkey / luxury finishing:</strong> roughly PKR 6,000 – 8,500+ per sq ft</li>
				</ul>
				<p>These ranges shift with cement, steel and labour prices, and with city — Islamabad and Lahore tend to run slightly higher than smaller cities due to labour and transport costs. Because prices move throughout the year, this construction cost calculator uses editable rate fields so you can plug in your own supplier's current quotation instead of relying on a fixed number.</p>
			</div>
		</details>

		<details class="accordion" id="sec-material-vs-labour">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-split"></use></svg></span>
				<span class="sum-text">
					<h3>Material Cost vs Labour Cost: Where Your Money Goes</h3>
					<span class="preview">Materials (cement, steel, bricks, tiles) typically account for 55–65% of total cost, labour for the rest.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<figure class="illo">
					<img src="<?php echo esc_url( $img_url . 'material-vs-labour-chart.svg' ); ?>" alt="Donut chart showing material cost is about 65 percent of grey structure spending and 55 percent of finishing spending, with labour making up the rest" width="400" height="150" loading="lazy">
					<figcaption>Material vs labour cost split for grey structure and finishing work.</figcaption>
				</figure>
				<p>For grey structure work, material cost (cement, steel/saria, bricks, sand and crush) usually makes up 65–75% of the total, with skilled and unskilled labour (mason, steel-fixer, helper) accounting for the rest. For finishing work, the split shifts — tiling, paint, plumbing and electrical fittings are still material-heavy, but skilled labour (tile-layer, electrician, plumber, painter, carpenter) plays a bigger role, often 40–50% of finishing cost.</p>
				<p>Common finishing cost categories include: floor and wall tiles, plumbing pipework and fixtures, electrical wiring and switches/sockets, paint and wall finishing, and woodwork such as doors, window frames and, for turnkey projects, cabinets and wardrobes. Each of these is estimated separately in the house construction cost breakdown above so you can see exactly where your budget is going, rather than just one lump "finishing" figure.</p>
			</div>
		</details>

		<details class="accordion" id="sec-examples">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-plots"></use></svg></span>
				<span class="sum-text">
					<h3>5 Marla, 7 Marla, 10 Marla &amp; 1 Kanal House Construction Cost Examples</h3>
					<span class="preview">Illustrative standard-quality, complete-house estimates for the most commonly searched plot sizes.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<p>The table below is generated live by the same calculation engine as the calculator above, using standard quality, complete house construction, in Lahore, with a typical number of storeys for each plot size — a quick reference for anyone comparing a 5 Marla house construction cost against a 10 Marla house construction cost or a 1 Kanal house construction cost in Pakistan. Use the calculator itself for numbers matched to your exact city, quality and design.</p>
				<div id="examples-table-wrap"><?php echo hcc_render_examples_table_static(); ?></div>
				<h4>Current material rates used in these examples</h4>
				<?php echo hcc_material_rates_table(); ?>
				<p class="hint">Figures update automatically and reflect the default material and labour rates shown in the "Advanced" section of the calculator. Plot sizes above use the most common Pakistani standard of 1 Marla = 225 sq ft ("Society Marla") — if your plot's own record uses 250, 272 or 272.25 sq ft per Marla instead, switch the "Marla / Kanal Standard" dropdown in the calculator above and re-enter your plot size. See our <a href="<?php echo esc_url( home_url( '/marla-kanal-standards-pakistan/' ) ); ?>">guide to Pakistan's different Marla &amp; Kanal standards</a> for the full comparison.</p>
			</div>
		</details>

		<details class="accordion" id="sec-factors">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-factors"></use></svg></span>
				<span class="sum-text">
					<h3>Factors That Affect House Construction Cost</h3>
					<span class="preview">Design complexity, structural spans, site access and material brand choices can shift cost by 20% or more.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<ul>
					<li><strong>Design complexity:</strong> curved walls, large cantilevers, double-height spaces and irregular layouts increase both material and labour cost — use the "Design Complexity" selector above to see the difference.</li>
					<li><strong>Number of storeys and basement:</strong> additional floors require stronger foundations, more steel, and add scaffolding and vertical transport costs. Basements add excavation, shoring and waterproofing costs.</li>
					<li><strong>Boundary wall and site works:</strong> a boundary wall, gate and external paving are easy to forget when budgeting but can add a meaningful amount to your total project cost.</li>
					<li><strong>Site access and soil conditions:</strong> a narrow street or poor soil bearing capacity can increase foundation and labour costs.</li>
					<li><strong>Material brand and quality:</strong> branded cement, tested-grade steel and premium tiles/sanitary ware cost noticeably more than generic alternatives.</li>
					<li><strong>City and location:</strong> labour rates, transport distance from suppliers, and local demand all shift the rate per square foot.</li>
					<li><strong>Number of bathrooms and kitchens:</strong> each additional bathroom or kitchen adds plumbing points, tiling, waterproofing and fixtures.</li>
					<li><strong>Government approval / NOC fees:</strong> map approval and building-plan fees vary by development authority and society, and are often overlooked in early budgets.</li>
					<li><strong>Market timing:</strong> cement and steel prices in Pakistan can move noticeably within a single year, which is why editable rate fields matter more than a fixed number.</li>
					<li><strong>Contractor type:</strong> a labour-only contractor, a material-supply contractor, and a full turnkey contractor all price and mark up differently.</li>
				</ul>
			</div>
		</details>

		<details class="accordion" id="sec-reduce-cost">
			<summary>
				<span class="acc-icon"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-savings"></use></svg></span>
				<span class="sum-text">
					<h3>How to Reduce House Construction Cost Without Compromising Quality</h3>
					<span class="preview">Good planning, bulk buying and a simpler design usually save more than cutting corners on materials.</span>
				</span>
				<span class="plus" aria-hidden="true">+</span>
			</summary>
			<div class="accordion-body">
				<ol>
					<li><strong>Finalize your drawings and Bill of Quantities before starting.</strong> Mid-construction design changes are one of the most common causes of budget overruns.</li>
					<li><strong>Keep the structural layout simple and efficient.</strong> Fewer cantilevers, curves and unusual spans mean less steel and formwork labour — an "Efficient" design in the calculator above can be several percent cheaper than a complex one at the same quality tier.</li>
					<li><strong>Buy major materials (cement, steel) directly where possible</strong> instead of through a contractor markup, and buy in bulk when prices are favorable.</li>
					<li><strong>Get at least three itemized quotations</strong> from different contractors so you can compare like-for-like scope, not just a single headline number.</li>
					<li><strong>Phase your finishing work</strong> — complete the grey structure and essential finishing first, and upgrade fittings, tiles or wardrobes later as budget allows.</li>
					<li><strong>Choose standard sizes for tiles, doors and windows</strong> to reduce cutting waste and custom fabrication charges.</li>
					<li><strong>Budget for boundary wall and approval fees up front.</strong> These "hidden cost heads" are easy to forget and can strain a budget that only accounted for the house itself.</li>
					<li><strong>Supervise or hire a supervisor.</strong> Material pilferage and rework due to poor supervision can add several percent to total cost.</li>
					<li><strong>Avoid over-specifying structural elements.</strong> A licensed structural engineer can right-size your foundation, beams and steel instead of blanket over-design.</li>
				</ol>
			</div>
		</details>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Reusable FAQ renderer — takes an explicit $faqs array so a page that only
 * needs a subset (see hcc_grey_structure_faq_items() below) still gets a
 * real, visible FAQ section that its own FAQPage schema can match exactly.
 */
function hcc_render_faq_custom( $faqs, $intro = 'Quick, direct answers about house construction cost, material quantities and this calculator — separate from the full guide above so you can jump straight to the answer you need.' ) {
	ob_start();
	?>
	<section id="faq-section" aria-labelledby="faq-heading">
		<div class="wrap">
			<div class="guide-title-row">
				<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><use href="#icon-help"></use></svg>
				<h2 id="faq-heading">Frequently Asked Questions</h2>
			</div>
			<div class="title-underline" aria-hidden="true"></div>
			<p class="wrap-intro"><?php echo esc_html( $intro ); ?></p>
			<?php foreach ( $faqs as $item ) : ?>
				<details class="faq">
					<summary><span class="faq-badge" aria-hidden="true">Q</span><span class="sum-text"><h3><?php echo esc_html( $item['q'] ); ?></h3></span><span class="chev" aria-hidden="true">▾</span></summary>
					<div class="accordion-body"><p><?php echo wp_kses_post( $item['a'] ); ?></p></div>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

function hcc_render_faq() {
	return hcc_render_faq_custom( hcc_faq_items() );
}

/**
 * SEO AUDIT FIX: five questions from the main FAQ pool that are directly
 * about grey structure (reused, not duplicated content authored twice) so
 * the Grey Structure Cost Calculator page — previously two short paragraphs
 * and nothing else — has real, relevant, already-vetted FAQ content and can
 * carry its own FAQPage schema that matches what is actually on the page.
 */
function hcc_grey_structure_faq_items() {
	$wanted = array(
		'What is the difference between grey structure and complete house construction?',
		'What is the grey structure cost per square foot in Lahore, Karachi and Islamabad right now?',
		'How many cement bags are needed per square foot of construction?',
		'How many bricks are needed per square foot of house construction in Pakistan?',
		'How much steel (saria) is required for a house in Pakistan?',
	);
	$all = hcc_faq_items();
	$out = array();
	foreach ( $all as $item ) {
		if ( in_array( $item['q'], $wanted, true ) ) {
			$out[] = $item;
		}
	}
	return $out;
}

function hcc_render_eeat() {
	ob_start();
	?>
	<section id="eeat" aria-labelledby="eeat-heading">
		<div class="eeat-card">
			<h2 id="eeat-heading">How We Calculate These Estimates</h2>
			<p>This calculator is built and maintained by Ali Ahmad (ConstructionCostPK). Every default rate — cement, steel, bricks, sand, crush, labour and finishing rates, boundary wall and approval-fee allowances — is built from publicly available Pakistani supplier and marketplace pricing and standard quantity-estimation thumb rules used across the industry, then shown openly in the "Advanced" panel so you can verify or override it with your own local quotation.</p>
			<p>We review and update these default rates periodically as material and labour prices move. If you notice a default that looks out of date for your city, please use the editable rate fields as your primary source of truth, or get in touch via our <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact Us</a> page to flag it. The full formulas and quantity assumptions are published on our <a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>">methodology page</a>.</p>
			<div class="eeat-meta">
				<span><strong>Rates last updated:</strong> <?php echo esc_html( hcc_rates_date_human() ); ?></span>
				<span><strong>Coverage:</strong> Lahore, Karachi, Islamabad, Rawalpindi, Faisalabad, Multan, Peshawar, Gujranwala, Sialkot, Hyderabad, Sargodha &amp; other cities</span>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Reusable schema renderer. $faqs must be exactly what is visibly rendered
 * on the same page (Google's structured-data guidelines require FAQPage
 * markup to match on-page content) — see hcc_render_schema() for the Home
 * page's full FAQ set and hcc_render_grey_structure_schema() below for the
 * Grey Structure page's smaller, page-specific set.
 */
function hcc_render_schema_custom( $faqs, $app_url, $app_name, $app_description, $app_keywords, $image_url = '' ) {
	$main_entity = array();
	foreach ( $faqs as $item ) {
		$main_entity[] = array(
			'@type'          => 'Question',
			'name'           => $item['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['a'],
			),
		);
	}
	$faq_schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'@id'        => $app_url . '#faq',
		'inLanguage' => 'en-US',
		'mainEntity' => $main_entity,
	);
	$app_schema = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'WebApplication',
		'@id'              => $app_url . '#calculator',
		'name'             => $app_name,
		'url'              => $app_url,
		'description'      => $app_description,
		'applicationCategory' => 'UtilitiesApplication',
		'operatingSystem'  => 'Any (Web browser)',
		'offers'           => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PKR' ),
		'inLanguage'       => 'en-US',
		'isPartOf'         => array( '@id' => home_url( '/' ) . '#website' ),
		'publisher'        => array( '@id' => home_url( '/' ) . '#organization' ),
		'dateModified'     => hcc_rates_date(),
		'keywords'         => $app_keywords,
	);
	if ( $image_url ) {
		$app_schema['image'] = $image_url;
	}
	$out  = '<script type="application/ld+json">' . wp_json_encode( $app_schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	$out .= '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES ) . '</script>';
	return $out;
}

function hcc_render_schema() {
	return hcc_render_schema_custom(
		hcc_faq_items(),
		home_url( '/' ),
		'House Construction Cost Calculator',
		'Free online calculator to estimate house construction cost in Pakistan, including grey structure, complete house and turnkey construction cost per square foot, material quantities and an itemized PDF report.',
		'house construction cost calculator, construction cost calculator Pakistan, house building cost calculator, grey structure cost calculator, 5 marla house construction cost, 10 marla house construction cost, 1 kanal house construction cost, construction material quantity calculator, building cost estimator Pakistan'
	);
}

/**
 * SEO AUDIT FIX: the Grey Structure Cost Calculator page previously had no
 * schema at all, because hcc_render_schema() only ran in mode="full" (Home
 * page only). This gives it its own WebApplication + FAQPage schema, scoped
 * to that page's URL and matching the FAQ subset actually shown on it.
 */
function hcc_render_grey_structure_schema() {
	return hcc_render_schema_custom(
		hcc_variant_faq_items( 'grey-structure' ),
		home_url( '/grey-structure-cost-calculator/' ),
		'Grey Structure Cost Calculator',
		'Free online calculator to estimate grey structure (structural shell) construction cost in Pakistan per square foot, with a full cement, steel, brick, sand and crush material-quantity breakdown.',
		'grey structure cost calculator pakistan, grey structure cost per square foot, grey structure rate lahore karachi islamabad, cement bags per square foot pakistan, steel per square foot pakistan'
	);
}

/* ============================================================================
 * BLOG / TOPICAL AUTHORITY PHASE (2026-09): the 5/7/10 Marla & 1 Kanal plot
 * size guide pages reuse the same [house_cost_calculator mode="calculator_only"
 * variant="..."] mechanism as the Grey Structure page above, instead of a
 * hardcoded single case. Each variant gets its own "read more" note, its own
 * on-page FAQ subset, and its own scoped WebApplication + FAQPage schema.
 * All numeric FAQ answers below are produced by the SAME hcc_compute_estimate()
 * engine as the live calculator (Lahore, standard quality unless stated),
 * so an article's figures can never drift from what the tool itself shows.
 * ========================================================================== */

function hcc_plot_size_meta() {
	return array(
		'3-marla' => array(
			'label' => '3 Marla', 'url' => home_url( '/3-marla-house-construction-cost/' ),
			'app_name' => '3 Marla House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-3-marla.png',
		),
		'4-marla' => array(
			'label' => '4 Marla', 'url' => home_url( '/4-marla-house-construction-cost/' ),
			'app_name' => '4 Marla House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-4-marla.png',
		),
		'5-marla' => array(
			'label' => '5 Marla', 'url' => home_url( '/5-marla-house-construction-cost/' ),
			'app_name' => '5 Marla House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-5-marla.png',
		),
		'7-marla' => array(
			'label' => '7 Marla', 'url' => home_url( '/7-marla-house-construction-cost/' ),
			'app_name' => '7 Marla House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-7-marla.png',
		),
		'10-marla' => array(
			'label' => '10 Marla', 'url' => home_url( '/10-marla-house-construction-cost/' ),
			'app_name' => '10 Marla House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-10-marla.png',
		),
		'1-kanal' => array(
			'label' => '1 Kanal', 'url' => home_url( '/1-kanal-house-construction-cost/' ),
			'app_name' => '1 Kanal House Construction Cost Calculator',
			'image' => get_template_directory_uri() . '/assets/img/hero-1-kanal.png',
		),
	);
}

/**
 * "Read more" note shown under the bare calculator card in non-full mode,
 * per variant — cross-links every plot-size guide to its siblings and to
 * the full home-page guide, so each new page both sends and receives
 * internal links rather than being an isolated leaf.
 */
function hcc_variant_note( $variant ) {
	$plots = hcc_plot_size_meta();
	if ( 'marla-standards' === $variant ) {
		return 'Want to see these standards applied to a real plot? Check the <a href="' . esc_url( home_url( '/7-marla-house-construction-cost/' ) ) . '">7 Marla plot size and cost</a>, our <a href="' . esc_url( home_url( '/5-marla-house-construction-cost/' ) ) . '">5 Marla</a>, <a href="' . esc_url( home_url( '/10-marla-house-construction-cost/' ) ) . '">10 Marla</a> and <a href="' . esc_url( home_url( '/1-kanal-house-construction-cost/' ) ) . '">1 Kanal</a> guides, or price just the structure with the <a href="' . esc_url( home_url( '/grey-structure-cost-calculator/' ) ) . '">grey structure calculator</a>. The full <a href="' . esc_url( home_url( '/' ) ) . '">House Construction Cost Calculator</a> handles any plot size and city.';
	}
	if ( 'grey-structure' === $variant || ! isset( $plots[ $variant ] ) ) {
		return 'Want the full construction cost guide — grey vs complete vs turnkey, material vs labour split, 5/7/10 Marla &amp; 1 Kanal examples, and the complete FAQ? Read the <a href="' . esc_url( home_url( '/' ) ) . '">House Construction Cost Calculator &amp; Guide</a> on our home page.';
	}
	if ( 0 === strpos( $variant, 'city-' ) ) {
		return 'Building somewhere else? Compare rates in our <a href="' . esc_url( home_url( '/construction-cost-by-city/' ) ) . '">city-by-city cost table</a>, or price the structure alone with the <a href="' . esc_url( home_url( '/grey-structure-cost-calculator/' ) ) . '">grey structure calculator</a>.';
	}
	// Anchor text rotates per page so the same phrase isn't repeated site-wide (L3).
	$anchors = array(
		'3-marla'  => 'cost of a 3 Marla home',
		'4-marla'  => '4 Marla construction budget',
		'5-marla'  => '5 Marla house estimate',
		'7-marla'  => 'what a 7 Marla house costs',
		'10-marla' => '10 Marla double storey cost',
		'1-kanal'  => '1 Kanal build budget',
	);
	$keys = array_keys( $plots );
	$i = array_search( $variant, $keys, true );
	$pick = array();
	foreach ( array( -1, 1, 2 ) as $off ) {
		$k = isset( $keys[ $i + $off ] ) ? $keys[ $i + $off ] : null;
		if ( $k && $k !== $variant && ! in_array( $k, $pick, true ) ) $pick[] = $k;
	}
	$links = array();
	foreach ( $pick as $k ) {
		$links[] = '<a href="' . esc_url( $plots[ $k ]['url'] ) . '">' . esc_html( $anchors[ $k ] ) . '</a>';
	}
	$last = array_pop( $links );
	return 'Comparing plot sizes? See the ' . ( $links ? implode( ', ', $links ) . ' and the ' : '' ) . $last . ', or run any custom size through the <a href="' . esc_url( home_url( '/' ) ) . '">main construction cost calculator</a>.';
}

function hcc_variant_faq_intro( $variant ) {
	$plots = hcc_plot_size_meta();
	if ( 0 === strpos( $variant, 'city-' ) ) {
		$labels = hcc_city_labels();
		$c = substr( $variant, 5 );
		return 'Quick, direct answers about house construction cost in ' . ( isset( $labels[ $c ] ) ? $labels[ $c ] : 'your city' ) . ' — including a few questions in Roman Urdu.';
	}
	if ( 'marla-standards' === $variant ) {
		return 'Quick, direct answers about Pakistan\'s different Marla and Kanal standards.';
	}
	if ( isset( $plots[ $variant ] ) ) {
		return 'Quick, direct answers about ' . $plots[ $variant ]['label'] . ' house construction cost and material quantities in Pakistan.';
	}
	return 'Quick, direct answers about grey structure construction cost and material quantities in Pakistan.';
}

function hcc_variant_faq_items( $variant ) {
	$plots = hcc_plot_size_meta();
	if ( 0 === strpos( $variant, 'city-' ) ) {
		$items = hcc_city_faq_items( substr( $variant, 5 ) );
	} elseif ( 'marla-standards' === $variant ) {
		$items = hcc_marla_standards_faq_items();
	} elseif ( isset( $plots[ $variant ] ) ) {
		$items = hcc_plot_size_faq_items( $variant );
	} else {
		$items = array_merge( hcc_grey_structure_faq_items(), hcc_grey_structure_extra_faq_items() );
	}
	return array_merge( $items, hcc_roman_urdu_faq_items( $variant ) );
}

function hcc_variant_schema( $variant ) {
	$plots = hcc_plot_size_meta();
	if ( 0 === strpos( $variant, 'city-' ) ) {
		$c = substr( $variant, 5 );
		$labels = hcc_city_labels();
		$label = isset( $labels[ $c ] ) ? $labels[ $c ] : 'Pakistan';
		return hcc_render_schema_custom(
			hcc_variant_faq_items( $variant ),
			home_url( '/construction-cost-' . $c . '/' ),
			'Construction Cost Calculator ' . $label,
			'Free online calculator to estimate house construction cost in ' . $label . ' — grey structure, complete and turnkey cost per square foot, material quantities and an itemized PDF report.',
			'construction cost ' . strtolower( $label ) . ', construction cost per sq ft ' . strtolower( $label ) . ', house construction cost ' . strtolower( $label ) . ' 2026, grey structure rate ' . strtolower( $label )
		);
	}
	if ( 'marla-standards' === $variant ) {
		return hcc_render_schema_custom(
			hcc_variant_faq_items( $variant ),
			home_url( '/marla-kanal-standards-pakistan/' ),
			'Marla & Kanal Standards Guide',
			'A comparison of the different Marla and Kanal land-measurement standards used across Pakistan (225, 250, 272 and 272.25 sq ft per Marla) and how to convert your plot size correctly.',
			'marla to square feet pakistan, 1 marla kitne square feet, kanal to square feet pakistan, patwari marla, land revenue marla, society marla, marla standards pakistan',
			get_template_directory_uri() . '/assets/img/hero-marla-standards.png'
		);
	}
	if ( ! isset( $plots[ $variant ] ) ) {
		return hcc_render_grey_structure_schema();
	}
	$meta = $plots[ $variant ];
	return hcc_render_schema_custom(
		hcc_variant_faq_items( $variant ),
		$meta['url'],
		$meta['app_name'],
		'Free online calculator to estimate ' . $meta['label'] . ' house construction cost in Pakistan — grey structure and complete house cost per square foot, total budget and material quantities.',
		strtolower( $meta['label'] ) . ' house construction cost, ' . strtolower( $meta['label'] ) . ' house cost pakistan, ' . strtolower( $meta['label'] ) . ' construction cost calculator, ' . strtolower( $meta['label'] ) . ' house cost per square foot',
		$meta['image']
	);
}

/**
 * FAQ subset for each plot-size guide page. Figures are computed once via
 * hcc_compute_estimate() (Lahore, typical design) right here, so the FAQ
 * text and the on-page cost tables in functions.php's page content can
 * never contradict each other or the live calculator.
 */
function hcc_plot_size_faq_items( $variant ) {
	$sizes = array(
		'3-marla'  => array( 'label' => '3 Marla',  'plotSqft' => 3 * 225,  'storeys' => 1 ),
		'4-marla'  => array( 'label' => '4 Marla',  'plotSqft' => 4 * 225,  'storeys' => 1 ),
		'5-marla'  => array( 'label' => '5 Marla',  'plotSqft' => 5 * 225,  'storeys' => 1 ),
		'7-marla'  => array( 'label' => '7 Marla',  'plotSqft' => 7 * 225,  'storeys' => 1 ),
		'10-marla' => array( 'label' => '10 Marla', 'plotSqft' => 10 * 225, 'storeys' => 2 ),
		'1-kanal'  => array( 'label' => '1 Kanal',  'plotSqft' => 4500,     'storeys' => 2 ),
	);
	if ( ! isset( $sizes[ $variant ] ) ) return array();
	$s = $sizes[ $variant ];
	$covered = hcc_suggest_covered_area( $s['plotSqft'], $s['storeys'] );
	$rates   = hcc_default_calc_rates();
	$common  = array(
		'coveredArea' => $covered, 'storeys' => $s['storeys'], 'hasBasement' => false, 'basementArea' => 0,
		'hasBoundaryWall' => false, 'wallLength' => 0, 'hasApprovalFee' => false, 'plotSqft' => $s['plotSqft'],
		'designComplexity' => 'typical', 'city' => 'lahore', 'bedrooms' => 3, 'bathrooms' => 3, 'kitchens' => 1, 'rates' => $rates,
	);
	$grey     = hcc_compute_estimate( array_merge( $common, array( 'constructionType' => 'grey', 'quality' => 'standard' ) ) );
	$standard = hcc_compute_estimate( array_merge( $common, array( 'constructionType' => 'complete', 'quality' => 'standard' ) ) );
	$economy  = hcc_compute_estimate( array_merge( $common, array( 'constructionType' => 'complete', 'quality' => 'economy' ) ) );
	$premium  = hcc_compute_estimate( array_merge( $common, array( 'constructionType' => 'complete', 'quality' => 'premium' ) ) );

	$base = hcc_base_qty_per_sqft();
	$cement = round( $covered * $base['cementBags'] );
	$steel  = round( $covered * $base['steelKg'] );
	$bricks = round( $covered * $base['bricks'] );

	$plot_desc = ( '1-kanal' === $variant ) ? '1 Kanal (4,500 sq ft)' : $s['label'] . ' (' . number_format( $s['plotSqft'] ) . ' sq ft)';
	$storey_word = 1 === $s['storeys'] ? 'single storey' : ( $s['storeys'] . '-storey' );

	return array(
		array(
			'q' => 'How much does a ' . $s['label'] . ' house cost to construct in Pakistan in 2026?',
			'a' => 'For a ' . $plot_desc . ' plot built ' . $storey_word . ' with an estimated ' . number_format( $covered ) . ' sq ft of covered area, complete house construction (standard quality, Lahore) costs roughly ' . hcc_format_pkr( $standard['grandTotal'] ) . ' — about ' . hcc_format_pkr( $standard['costPerSqft'] ) . ' per sq ft. Economy quality brings this down to around ' . hcc_format_pkr( $economy['grandTotal'] ) . ', while premium finishing raises it to around ' . hcc_format_pkr( $premium['grandTotal'] ) . '. Grey structure only (no finishing) costs around ' . hcc_format_pkr( $grey['grandTotal'] ) . '. These are calculator-based planning estimates for Lahore — use the calculator on this page to adjust for your own city, quality and design.',
		),
		array(
			'q' => 'How much covered area does a ' . $s['label'] . ' house have?',
			'a' => 'A ' . $plot_desc . ' plot built ' . $storey_word . ' typically yields around ' . number_format( $covered ) . ' sq ft of covered area, based on typical Pakistani building-bylaw coverage ratios for this plot size. Covered area (not plot area) is what construction cost per square foot is actually multiplied by, which is why two houses on the same size plot can have very different total costs depending on how many storeys are built.',
		),
		array(
			'q' => 'How much cement, steel and bricks are needed for a ' . $s['label'] . ' house?',
			'a' => 'For roughly ' . number_format( $covered ) . ' sq ft of covered area at standard quality, a ' . $s['label'] . ' house typically needs approximately ' . number_format( $cement ) . ' bags of cement, ' . number_format( $steel ) . ' kg of steel (saria), and ' . number_format( $bricks ) . ' bricks, alongside sand and crush for concrete work. These figures use standard per-square-foot thumb-rule quantities and will shift with design complexity and quality tier — the calculator on this page breaks all of this down automatically for your exact inputs.',
		),
		array(
			'q' => 1 === $s['storeys']
				? 'Should a ' . $s['label'] . ' house be built single storey or double storey?'
				: 'Is a ' . $s['label'] . ' plot big enough to build a comfortable double storey house?',
			'a' => 1 === $s['storeys']
				? 'A ' . $s['label'] . ' plot (' . number_format( $s['plotSqft'] ) . ' sq ft) is commonly built as a single storey home with 2–3 bedrooms, which keeps foundation and steel requirements lower and construction cost more predictable. Some owners add a second storey later, or build double storey from the start to get more covered area (more bedrooms, a separate lounge, or a rentable upper portion) for a higher total cost but a lower land cost per bedroom — try both storey counts in the calculator above to compare.'
				: 'Yes — a ' . $s['label'] . ' plot (' . number_format( $s['plotSqft'] ) . ' sq ft) comfortably supports a double storey house with an estimated ' . number_format( $covered ) . ' sq ft of combined covered area, typically enough for 4–5 bedrooms, multiple bathrooms, a drawing/lounge area and a kitchen on each level. A double storey build costs more in total than a single storey house on the same plot (more steel, more finishing area) but often works out cheaper per bedroom.',
		),
	);
}

/**
 * FAQ subset for the dedicated Marla & Kanal standards guide page.
 */
function hcc_marla_standards_faq_items() {
	return array(
		array(
			'q' => 'How many square feet are in 1 Marla in Pakistan?',
			'a' => 'It depends which standard your plot documents use. The most common today is the "Society Marla" of 225 sq ft, used by LDA, DHA, Bahria Town and most modern private housing societies. Three other standards are also in real-world use: 250 sq ft (Karachi and some cantonment areas), 272 sq ft (the Land Revenue Marla used in many local records), and 272.25 sq ft (the older Patwari / revenue-record Marla, still common in rural areas and older land records). Always confirm which one applies to your specific plot with the housing authority or land registry before relying on it for a purchase decision.',
		),
		array(
			'q' => 'How many square feet are in 1 Kanal?',
			'a' => '1 Kanal is always exactly 20 Marla — that part never changes — but because Marla itself has more than one standard, Kanal does too: 4,500 sq ft under the 225 sq ft Society Marla, 5,000 sq ft under the 250 sq ft Karachi/Cantonment Marla, 5,440 sq ft under the 272 sq ft Land Revenue Marla, and 5,445 sq ft under the 272.25 sq ft Patwari / revenue-record Marla.',
		),
		array(
			'q' => 'How much difference does the Marla standard actually make when buying a plot?',
			'a' => 'It can be significant. For example, a plot recorded as "10 Marla" is 2,250 sq ft under the 225 sq ft Society standard, but 2,722.5 sq ft under the 272.25 sq ft Patwari standard — a difference of about 472.5 sq ft, or roughly 17% less land under the smaller standard for the same "10 Marla" label. This is exactly why you should never assume a Marla figure without confirming the underlying square-foot standard with the housing authority or land registry.',
		),
		array(
			'q' => 'How do I find out which Marla standard my plot uses?',
			'a' => 'Ask the housing authority, developer or land registry directly what 1 Marla equals in square feet for that specific scheme or revenue record — do not assume 225 sq ft. Most modern private housing societies (DHA, Bahria Town and similar) use the 225 sq ft Society Marla and will confirm this on request; older government revenue records and many rural areas use the 272.25 sq ft Patwari Marla instead. The exact square footage should also be checked against your allotment letter, registry documents (fard/intiqal) or approved site plan where possible.',
		),
		array(
			'q' => 'Which Marla / Kanal standard does this calculator use by default?',
			'a' => 'This calculator defaults to the 225 sq ft "Society Marla," the most common standard in use today and the one all of this site\'s plot-size guides (5 Marla, 7 Marla, 10 Marla, 1 Kanal) are written against. If your own plot uses a different standard, switch it from the "Marla / Kanal Standard" dropdown in the calculator — every plot-size-to-square-foot conversion updates immediately, and your cost estimate will still be accurate once your covered area is set correctly.',
		),
	);
}


/**
 * [hcc_faq variant="..."] — the page's FAQ + FAQPage/WebApplication schema,
 * for pages that place the FAQ after their own H2 sections (AEO template, G10).
 */
function hcc_faq_shortcode( $atts ) {
	$a = shortcode_atts( array( 'variant' => 'grey-structure' ), $atts );
	if ( 0 === strpos( $a['variant'], 'mat-' ) ) return hcc_material_faq_render( substr( $a['variant'], 4 ) );
	return hcc_render_faq_custom( hcc_variant_faq_items( $a['variant'] ), hcc_variant_faq_intro( $a['variant'] ) ) . hcc_variant_schema( $a['variant'] );
}
add_shortcode( 'hcc_faq', 'hcc_faq_shortcode' );
