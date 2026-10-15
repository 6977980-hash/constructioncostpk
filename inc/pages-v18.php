<?php
/**
 * v1.8.0 — new pages (C1 Priority 1, L4 hubs, G2 index, G5 methodology,
 * X1 embed). Prose lives in the page (editable in WordPress); every number
 * comes from shortcodes backed by the calculator engine, so figures update
 * automatically when rates change.
 *
 * Anti-doorway (C1.24): each city page carries its own plot units, approving
 * authority, local construction notes and its own computed table; each size
 * page carries size-specific layouts, quantities and storey comparisons.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hcc_v18_page_definitions() {
	$u = function ( $k ) { return esc_url( home_url( '/' . trim( $k, '/' ) . ( $k ? '/' : '' ) ) ); };
	$home = esc_url( home_url( '/' ) );
	$img = get_template_directory_uri() . '/assets/img/';
	$hcc_ver = HCC_VERSION;
	$e3 = hcc_estimate( 675, 1 ); $e3g = hcc_estimate( 675, 1, 'grey' ); $e3d = hcc_estimate( 675, 2 );
	$e4 = hcc_estimate( 900, 1 ); $e4g = hcc_estimate( 900, 1, 'grey' ); $e4d = hcc_estimate( 900, 2 );
	$r = hcc_default_calc_rates();
	$cityrate = function ( $c, $t = 'complete' ) { return hcc_format_pkr( hcc_rate_per_sqft( $t, $c ) ); };
	$b = hcc_base_qty_per_sqft();
	foreach ( array( 'e3', 'e3g', 'e3d', 'e4', 'e4g', 'e4d' ) as $v ) {
		${$v}['total_fmt'] = hcc_format_pkr( ${$v}['grandTotal'] );
		${$v}['covered'] = number_format( ${$v}['covered'] );
	}
	$e3d['ratio'] = number_format( $e3d['grandTotal'] / $e3['grandTotal'], 1 );
	$e4['diff5_fmt'] = hcc_format_pkr( hcc_estimate( 1125, 1 )['grandTotal'] - $e4['grandTotal'] );
	// misc figures used in the city / materials copy
	$e3['lahore5_fmt'] = hcc_format_pkr( hcc_estimate( 1125, 1, 'complete', 'standard', 'lahore' )['grandTotal'] );
	$e3['lahore1k_fmt'] = hcc_format_pkr( hcc_estimate( 4500, 2, 'complete', 'standard', 'lahore' )['grandTotal'] );
	$e3['k120_fmt'] = hcc_format_pkr( hcc_estimate( 1080, 2, 'complete', 'standard', 'karachi' )['grandTotal'] );
	$e3['i3060_fmt'] = hcc_format_pkr( hcc_estimate( 1800, 2, 'complete', 'standard', 'islamabad' )['grandTotal'] );
	$e3['bricks_fmt'] = number_format( $r['bricksPer1000'] );
	$e4['extra_covered'] = number_format( hcc_suggest_covered_area( 1125, 1 ) - hcc_suggest_covered_area( 900, 1 ) );
	$y = hcc_year();
	// How much cheaper smaller Punjab cities are than Lahore, from the engine.
	$lr = hcc_rate_per_sqft( 'complete', 'lahore' );
	$cheaper = array();
	foreach ( array( 'gujranwala', 'sargodha', 'multan' ) as $c ) $cheaper[] = ( 1 - hcc_rate_per_sqft( 'complete', $c ) / $lr ) * 100;
	$punjab_cheaper = round( min( $cheaper ) ) . '–' . round( max( $cheaper ) ) . '%';
	$g5 = hcc_estimate( 1125, 1, 'grey' ); $g5d = hcc_estimate( 1125, 2, 'grey' );

	$defs = array();

	/* ---------------- C1.1 — 3 Marla ---------------- */
	$defs['3-marla-house-construction-cost'] = array(
		'title' => '3 Marla House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a 3 Marla plot is 675 sq ft. Built single storey with about {$e3['covered']} sq ft of covered area, a complete house (standard quality, Lahore rates) costs about <strong>{$e3['total_fmt']}</strong> and the grey structure alone about <strong>{$e3g['total_fmt']}</strong>. Most 3 Marla owners build double storey to fit 3 bedrooms: about {$e3d['covered']} sq ft for roughly <strong>{$e3d['total_fmt']}</strong>.</p>

<figure class="illo"><img src="{$img}hero-3-marla.png?ver={$hcc_ver}" alt="Infographic of a 3 Marla plot (675 sq ft) in Pakistan with an estimated {$e3['covered']} sq ft single storey covered area" width="1200" height="630" loading="eager"><figcaption>3 Marla plot size, covered area and estimated complete-house cost at a glance.</figcaption></figure>

<h2>How much does a 3 Marla house cost in {$y}?</h2>
[hcc_plot_tables marla="3" storeys="1"]
<p class="hint">Single storey, {$e3['covered']} sq ft covered area, Lahore, typical design. Use the calculator below for your own city and finishing level.</p>

[house_cost_calculator mode="calculator_only" variant="3-marla" default_type="complete" faq="no"]

<h2>Single storey or double storey on 3 Marla?</h2>
<p>A 3 Marla plot is usually about 22.5 × 30 ft or 20 × 34 ft, which leaves room for only one bedroom, a small lounge, a kitchen and one bathroom on a single floor. That is why most 3 Marla houses in Lahore, Faisalabad and Gujranwala are built double storey (and many with a third "mumty" or roof room). The table below compares both options:</p>
[hcc_plot_tables marla="3" storeys="1" part="storeys"]
<p>The double storey option costs about {$e3d['total_fmt']} for roughly {$e3d['covered']} sq ft — almost double the living space for about {$e3d['ratio']}× the single storey cost. The cost per sq ft is a little higher (about 3% more for the second storey's columns, steel and scaffolding), but you get the extra rooms without buying more land.</p>

<h2>What layout fits on a 3 Marla plot?</h2>
<ul>
<li><strong>Ground floor:</strong> car porch or open front space, drawing/lounge, kitchen, one bedroom with attached bath.</li>
<li><strong>First floor:</strong> two bedrooms, one or two bathrooms, small TV lounge.</li>
<li><strong>Roof:</strong> stair tower (mumty) with a small store — tanks are placed here.</li>
</ul>
<p>On a narrow plot, light and ventilation decide the design: keep a skylight or small courtyard in the middle of the house and place the staircase on a side wall so it doesn't cut the lounge in two.</p>

<h2>Material quantities for a 3 Marla house</h2>
[hcc_plot_tables marla="3" storeys="1" part="qty"]
<p>These are grey-structure quantities for the single storey option at standard quality. A double storey house needs a little more than double (about 3% extra per added storey). Check current prices on our <a href="{$u('cement-rate-today-pakistan')}">cement rate</a> and <a href="{$u('steel-saria-rate-today')}">saria rate</a> pages.</p>

<h2>Ways to keep a 3 Marla budget under control</h2>
<ul>
<li>Share the party walls correctly with neighbours (get the boundary measured first) — every foot of wall you don't build saves bricks and labour.</li>
<li>Use standard door and window sizes; on a small house, custom sizes are a big share of the finishing bill.</li>
<li>Plan the second storey's columns and steel from day one even if you build it later — adding columns afterwards is expensive.</li>
</ul>
<p>Compare with the <a href="{$u('4-marla-house-construction-cost')}">4 Marla construction budget</a> or the <a href="{$u('5-marla-house-construction-cost')}">5 Marla house estimate</a>, or price the structure alone with the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>. All plot sizes are listed in our <a href="{$u('construction-cost-guides')}">construction cost guides</a>.</p>

[hcc_faq variant="3-marla"]
HTML
	);

	/* ---------------- C1.2 — 4 Marla ---------------- */
	$defs['4-marla-house-construction-cost'] = array(
		'title' => '4 Marla House Construction Cost in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> a 4 Marla plot is 900 sq ft. Built single storey with about {$e4['covered']} sq ft of covered area, a complete house (standard quality, Lahore rates) costs about <strong>{$e4['total_fmt']}</strong>, and grey structure only about <strong>{$e4g['total_fmt']}</strong>. A double storey 4 Marla house costs about {$e4d['total_fmt']}.</p>

<figure class="illo"><img src="{$img}hero-4-marla.png?ver={$hcc_ver}" alt="Infographic of a 4 Marla plot (900 sq ft) in Pakistan with an estimated {$e4['covered']} sq ft single storey covered area" width="1200" height="630" loading="eager"><figcaption>4 Marla plot size, covered area and estimated complete-house cost at a glance.</figcaption></figure>

<h2>How much does a 4 Marla house cost in {$y}?</h2>
[hcc_plot_tables marla="4" storeys="1"]
<p class="hint">Single storey, {$e4['covered']} sq ft covered area, Lahore, typical design.</p>

[house_cost_calculator mode="calculator_only" variant="4-marla" default_type="complete" faq="no"]

<h2>Is 4 Marla enough for a family house?</h2>
<p>A 4 Marla plot (commonly 25 × 36 ft) fits two bedrooms, a lounge, kitchen and two bathrooms on one floor. It is a popular size in newer Lahore and Rawalpindi schemes because it sits between the very narrow 3 Marla plot and the more expensive 5 Marla plot. Families who need three or four bedrooms usually build double storey:</p>
[hcc_plot_tables marla="4" storeys="1" part="storeys"]

<h2>Typical 4 Marla layouts</h2>
<ul>
<li><strong>Single storey:</strong> two bedrooms with attached baths, lounge-cum-dining, kitchen, small front lawn or porch.</li>
<li><strong>Double storey:</strong> ground floor as above; first floor with two more bedrooms and a TV lounge — often built as a separate portion for rent.</li>
</ul>
<p>If you plan a rentable upper portion, give it a separate staircase from the front and separate water and electricity meters — deciding this before the slab is poured avoids breaking concrete later.</p>

<h2>Material quantities for a 4 Marla house</h2>
[hcc_plot_tables marla="4" storeys="1" part="qty"]
<p>Price these against today's <a href="{$u('construction-material-rates')}">construction material rates</a>.</p>

<h2>4 Marla vs 5 Marla: is the extra Marla worth it?</h2>
<p>A 5 Marla plot adds 225 sq ft of land and about {$e4['extra_covered']} sq ft of single storey covered area. At standard quality that is roughly {$e4['diff5_fmt']} more construction cost — see the <a href="{$u('5-marla-house-construction-cost')}">5 Marla house estimate</a> for the full breakdown, or the smaller <a href="{$u('3-marla-house-construction-cost')}">cost of a 3 Marla home</a>. Every size is compared in our <a href="{$u('construction-cost-guides')}">cost guides hub</a>.</p>

[hcc_faq variant="4-marla"]
HTML
	);

	/* ---------------- C1.3 — Lahore ---------------- */
	$defs['construction-cost-lahore'] = array(
		'title' => 'Construction Cost in Lahore (2026): Per Sq Ft Rates',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> building a house in Lahore costs about <strong>{$cityrate('lahore')} per sq ft</strong> for a standard-quality complete house and about <strong>{$cityrate('lahore','grey')} per sq ft</strong> for grey structure in {$y}. A single storey 5 Marla house comes to roughly {$e3['lahore5_fmt']}; a double storey 1 Kanal house to about {$e3['lahore1k_fmt']}.</p>

<h2>Lahore construction rates per sq ft</h2>
[hcc_city_rates city="lahore"]

<h2>What do common Lahore plot sizes cost?</h2>
<p>Lahore plots are sold in Marla and Kanal. Most private schemes (DHA, Bahria Town, LDA Avenue and similar) use the 225 sq ft "society Marla", while older revenue records and some peripheral areas use 272.25 sq ft — confirm which one your plot uses on our <a href="{$u('marla-kanal-standards-pakistan')}">Marla standards guide</a>.</p>
[hcc_city_plots city="lahore"]

[house_cost_calculator mode="calculator_only" variant="city-lahore" city="lahore" default_type="complete" faq="no"]

<h2>Who approves building plans in Lahore?</h2>
<p>Inside areas controlled by the <strong>Lahore Development Authority (LDA)</strong>, building plans are approved by LDA under its building and zoning regulations. Large private schemes such as <strong>DHA Lahore</strong> and <strong>Bahria Town Lahore</strong> run their own building control departments with their own map fees, coverage limits and completion certificates. Areas under the Metropolitan Corporation or cantonment boards follow those bodies' rules. Because fee schedules are revised regularly, get the current schedule from your scheme office before budgeting — the calculator's approval-fee field is editable for exactly this reason.</p>

<h2>What makes Lahore construction cost different?</h2>
<ul>
<li><strong>Society rules add cost:</strong> DHA and Bahria require approved drawings, specific boundary wall designs and completion inspections, and some charge for water/sewer connections separately.</li>
<li><strong>Material supply is close by:</strong> cement from Punjab plants and steel from Lahore mills keep material transport lower than in many smaller cities — Lahore is our base city (factor 1.00).</li>
<li><strong>Labour demand is high:</strong> skilled masons and tile fixers in Lahore charge more than in Gujranwala, Sargodha or Multan, which is why smaller Punjab cities such as Gujranwala, Sargodha and Multan come out about {$punjab_cheaper} cheaper per sq ft for a complete house.</li>
<li><strong>Water table and soil:</strong> in low-lying parts of the city, plan damp-proofing and plinth height carefully — the cost is small compared with repairing rising damp later.</li>
</ul>
<p>Compare Lahore with other cities in the <a href="{$u('construction-cost-by-city')}">city-by-city cost table</a>, see <a href="{$u('construction-cost-karachi')}">Karachi building costs</a> and <a href="{$u('construction-cost-islamabad')}">Islamabad per sq ft rates</a>, or use the <a href="{$home}">main calculator</a>.</p>

[hcc_faq variant="city-lahore"]
HTML
	);

	/* ---------------- C1.4 — Karachi ---------------- */
	$defs['construction-cost-karachi'] = array(
		'title' => 'Construction Cost in Karachi (2026): Per Sq Ft & Per Sq Yd',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> in Karachi, a standard-quality complete house costs about <strong>{$cityrate('karachi')} per sq ft</strong> and grey structure about <strong>{$cityrate('karachi','grey')} per sq ft</strong> in {$y}. A double storey house on a 120 sq yd plot comes to roughly {$e3['k120_fmt']}.</p>

<h2>Karachi construction rates per sq ft</h2>
[hcc_city_rates city="karachi"]

<h2>What do Karachi plot sizes cost to build?</h2>
<p>Unlike Punjab, Karachi plots are sold in <strong>square yards</strong> (1 sq yd = 9 sq ft): 120, 240, 400, 500 and 1,000 sq yd are the common sizes. A 120 sq yd plot (1,080 sq ft) is slightly smaller than a 5 Marla (1,125 sq ft) plot, and most are built ground + 1. Where the Marla is used, Karachi and cantonment areas often use 250 sq ft per Marla.</p>
[hcc_city_plots city="karachi"]

[house_cost_calculator mode="calculator_only" variant="city-karachi" city="karachi" default_type="complete" faq="no"]

<h2>Who approves building plans in Karachi?</h2>
<p>Most of Karachi falls under the <strong>Sindh Building Control Authority (SBCA)</strong>, which approves plans under the Karachi Building &amp; Town Planning Regulations. <strong>Cantonment areas</strong> — including DHA Karachi, which falls under Clifton Cantonment Board — have their own approval process, and schemes developed by KDA or private developers (such as Bahria Town Karachi) have additional rules on coverage and elevations. Confirm current fees with the relevant office; enter them in the calculator's approval-fee field.</p>

<h2>What makes Karachi construction cost different?</h2>
<ul>
<li><strong>Salty groundwater and sea air:</strong> in coastal and low-lying areas engineers often recommend sulphate-resisting cement for foundations, a thicker concrete cover over steel and good damp-proofing — all of which add a little to the grey structure cost but prevent rusting and spalling later.</li>
<li><strong>Water supply:</strong> many Karachi homes budget for an underground tank and a larger overhead tank because of irregular line water — add it as an extra in your estimate.</li>
<li><strong>Labour and transport:</strong> the calculator's Karachi factor (0.98) puts labour and finishing slightly below Lahore; material prices are close to the national average.</li>
<li><strong>Heat and ventilation:</strong> roof insulation and cross-ventilation are worth budgeting for; they cut electricity bills in a city with long summers.</li>
</ul>
<p>Compare with <a href="{$u('construction-cost-lahore')}">Lahore construction rates</a> or <a href="{$u('construction-cost-islamabad')}">Islamabad costs</a>, see every city in the <a href="{$u('construction-cost-by-city')}">cost by city table</a>, or open the <a href="{$home}">main calculator</a>.</p>

[hcc_faq variant="city-karachi"]
HTML
	);

	/* ---------------- C1.5 — Islamabad ---------------- */
	$defs['construction-cost-islamabad'] = array(
		'title' => 'Construction Cost in Islamabad (2026): Per Sq Ft Rates',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> Islamabad is the most expensive major city to build in: about <strong>{$cityrate('islamabad')} per sq ft</strong> for a standard complete house and <strong>{$cityrate('islamabad','grey')} per sq ft</strong> for grey structure in {$y}. A double storey house on a 30×60 ft (8 Marla) plot costs roughly {$e3['i3060_fmt']}.</p>

<h2>Islamabad construction rates per sq ft</h2>
[hcc_city_rates city="islamabad"]

<h2>What do Islamabad plot sizes cost to build?</h2>
<p>CDA sectors are planned around plot dimensions rather than Marla: 25×50 ft (about 5.5 Marla), 30×60 ft (8 Marla), 35×70 ft (about 11 Marla), 40×80 ft (about 14 Marla) and 50×90 ft (1 Kanal). Most are built double storey.</p>
[hcc_city_plots city="islamabad"]

[house_cost_calculator mode="calculator_only" variant="city-islamabad" city="islamabad" default_type="complete" faq="no"]

<h2>Who approves building plans in Islamabad?</h2>
<p>In CDA sectors, plans are approved by the <strong>Capital Development Authority (CDA)</strong> building control section. Private housing societies in the capital territory — for example DHA Islamabad and Bahria Town — approve plans through their own offices. Rawalpindi areas fall under RDA or the cantonment boards. Approval fees and the time taken differ a lot between these bodies; check the current schedule before you budget.</p>

<h2>What makes Islamabad construction cost different?</h2>
<ul>
<li><strong>Higher seismic hazard:</strong> Islamabad and Rawalpindi sit in a higher seismic zone than Lahore under the Building Code of Pakistan, so structural engineers usually specify more steel and stronger column–beam joints. Never cut steel below the approved structural drawing.</li>
<li><strong>Sloping and rocky sites:</strong> plots in some sectors and societies need retaining walls or rock excavation, which the per-sq-ft rate does not include — add them separately.</li>
<li><strong>Labour costs more:</strong> the calculator's Islamabad factor (1.08) reflects higher labour and finishing rates than Lahore.</li>
<li><strong>Cold winters:</strong> concrete curing and plaster work slow down in winter, and many owners budget for better insulation and gas heating provisions.</li>
</ul>
<p>Compare with <a href="{$u('construction-cost-lahore')}">building costs in Lahore</a>, <a href="{$u('construction-cost-karachi')}">Karachi per sq yd plots</a>, or all cities in the <a href="{$u('construction-cost-by-city')}">construction cost by city</a> table. For a structure-only budget use the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>.</p>

[hcc_faq variant="city-islamabad"]
HTML
	);

	/* ---------------- C1.6 — cement ---------------- */
	$cement_bags5 = number_format( round( $g5['qty']['cementBags'] ) );
	$cement_rate = number_format( $r['cement'] );
	$cement_5m_cost = hcc_format_pkr( round( $g5['qty']['cementBags'] ) * $r['cement'] );
	$defs['cement-rate-today-pakistan'] = array(
		'title' => 'Cement Rate in Pakistan Today (2026): Price per Bag',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> ConstructionCostPK's current planning rate for cement is <strong>PKR {$cement_rate} per 50 kg bag</strong>. A single storey 5 Marla house needs about {$cement_bags5} bags for its grey structure — roughly {$cement_5m_cost} of cement. Brand prices vary by city and dealer, so always ask for today's quote.</p>

<h2>Cement rate used in our calculator</h2>
[hcc_material_rates]

<h2>Brand-wise cement prices</h2>
[hcc_market_prices material="cement"]
<p>Well-known brands in Pakistan include Lucky, DG Khan, Bestway, Maple Leaf, Fauji, Pioneer, Kohat, Cherat, Askari and Power cement. Prices differ between north and south because of transport from the plant, so a Karachi dealer and a Lahore dealer will quote different numbers for the same brand.</p>

<h2>How many cement bags does a house need?</h2>
[hcc_material_need material="cement"]

<h2>Which type of cement should you use?</h2>
<ul>
<li><strong>Ordinary Portland Cement (OPC):</strong> the standard grey cement for slabs, columns, beams, plaster and masonry.</li>
<li><strong>Sulphate-resisting cement (SRC):</strong> for foundations in salty soil or groundwater — common advice in coastal Karachi and some low-lying areas.</li>
<li><strong>White cement:</strong> for tile grout and decorative finishes only; not for structural work.</li>
</ul>

<h2>How to buy cement without losing money</h2>
<ul>
<li>Check the manufacturing date printed on the bag — cement slowly loses strength in storage, especially in humid weather.</li>
<li>Store bags off the floor on wooden planks, away from walls, and cover them with plastic.</li>
<li>Buy in batches matched to the work schedule rather than all at once.</li>
<li>Weigh a few bags at random — a full bag is 50 kg.</li>
</ul>
<p>See all rates on the <a href="{$u('construction-material-rates')}">construction material rates</a> page, compare with the <a href="{$u('steel-saria-rate-today')}">saria rate today</a>, or price a whole house with the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>.</p>

[hcc_faq variant="mat-cement"]
HTML
	);

	/* ---------------- C1.7 — steel ---------------- */
	$steel_rate = number_format( $r['steel'] );
	$steel_ton = number_format( $r['steel'] * 1000 );
	$steel_5m = number_format( round( $g5['qty']['steelKg'] ) );
	$defs['steel-saria-rate-today'] = array(
		'title' => 'Saria Rate Today in Pakistan (2026): Steel Price per Kg',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> ConstructionCostPK's planning rate for Grade 60 steel (saria) is <strong>PKR {$steel_rate} per kg</strong> — PKR {$steel_ton} per ton — including typical wastage and binding wire. A single storey 5 Marla house needs about {$steel_5m} kg. Mill prices change often, so confirm with your dealer on the day you buy.</p>

<h2>Steel rate used in our calculator</h2>
[hcc_material_rates]

<h2>Brand-wise saria prices</h2>
[hcc_market_prices material="steel"]
<p>Common re-bar brands include Amreli, Mughal, Ittefaq, Agha, FF Steel and Kamran Steel, among others. Compare prices for the same grade and the same bar size — a lower price for Grade 40 is not comparable with Grade 60.</p>

<h2>How much steel does a house need?</h2>
[hcc_material_need material="steel"]

<h2>Grade 40 vs Grade 60 steel</h2>
<p>Grade 60 bars (minimum yield strength 60,000 psi, about 420 MPa) are the norm for houses today; Grade 40 (40,000 psi, about 280 MPa) is weaker and should only be used if your structural drawing specifies it. The structural engineer's drawing decides the grade and bar sizes — never substitute a lower grade on site.</p>

<h2>Saria sizes in "sutar" and weight per foot</h2>
<p>In Pakistan bar diameters are often named in sutar (1 sutar = 1/8 inch). Weight per foot is D²/533 kg, where D is the diameter in mm:</p>
<div class="table-scroll"><table class="mini-table"><thead><tr><th>Sutar</th><th>Diameter</th><th>Approx. weight per ft</th><th>Typical use</th></tr></thead><tbody>
<tr><td>3 sutar</td><td>3/8 in (10 mm)</td><td>0.19 kg</td><td>Stirrups, slab bars</td></tr>
<tr><td>4 sutar</td><td>1/2 in (12 mm)</td><td>0.27 kg</td><td>Slabs, beams</td></tr>
<tr><td>5 sutar</td><td>5/8 in (16 mm)</td><td>0.48 kg</td><td>Beams, columns</td></tr>
<tr><td>6 sutar</td><td>3/4 in (20 mm)</td><td>0.75 kg</td><td>Columns, footings</td></tr>
</tbody></table></div>

<h2>How to check saria quality</h2>
<ul>
<li>Look for the mill's name and the grade rolled onto the bar.</li>
<li>Ask for the mill test certificate for your batch.</li>
<li>Weigh a measured length and compare it with the table above — underweight bars are a common trick.</li>
<li>Keep bars off the ground and use them before heavy rust forms; light surface rust is normal.</li>
</ul>
<p>See every material on the <a href="{$u('construction-material-rates')}">material rates hub</a>, check the <a href="{$u('cement-rate-today-pakistan')}">cement price per bag</a>, or estimate the full structure with the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a>.</p>

[hcc_faq variant="mat-steel"]
HTML
	);

	/* ---------------- L4.2 — city hub ---------------- */
	$defs['construction-cost-by-city'] = array(
		'title' => 'Construction Cost by City in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> in {$y}, standard-quality complete house construction costs about {$cityrate('islamabad')} per sq ft in Islamabad, {$cityrate('lahore')} in Lahore and {$cityrate('karachi')} in Karachi, and less in smaller cities such as Multan and Peshawar. Differences come mainly from labour and finishing rates.</p>
<h2>Cost per sq ft in every city</h2>
[hcc_city_table]
<h2>Detailed city guides</h2>
[hcc_hub_list cluster="city"]
<p>More city guides (Rawalpindi, Faisalabad, Multan, Peshawar, Gujranwala, Sialkot) are being prepared with local authority and plot-size details. Meanwhile, select your city in the <a href="{$home}">main construction cost calculator</a>.</p>
HTML
	);

	/* ---------------- L4.3 — materials hub ---------------- */
	$defs['construction-material-rates'] = array(
		'title' => 'Construction Material Rates in Pakistan (2026)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>Quick answer:</strong> our calculator currently uses PKR {$cement_rate} per cement bag, PKR {$steel_rate} per kg of steel, PKR {$e3['bricks_fmt']} per 1,000 bricks, PKR {$r['sand']} per cft of sand and PKR {$r['crush']} per cft of crush. Every rate is editable in the calculator.</p>
<h2>Current material rates</h2>
[hcc_material_rates]
<h2>Material guides</h2>
[hcc_hub_list cluster="material"]
<h2>How much material does a house need?</h2>
<p>At standard quality the calculator assumes, per sq ft of covered area, {$b['cementBags']} bags of cement, {$b['steelKg']} kg of steel, {$b['bricks']} bricks, {$b['sandCft']} cft of sand and {$b['crushCft']} cft of crush. The full method is on our <a href="{$u('methodology')}">methodology page</a>, and the <a href="{$u('grey-structure-cost-calculator')}">grey structure calculator</a> turns these into a priced bill of materials.</p>
HTML
	);

	/* ---------------- G5 — methodology ---------------- */
	$defs['methodology'] = array(
		'title' => 'Methodology: How ConstructionCostPK Calculates Construction Cost',
		'content' => <<<HTML
<p>This page documents exactly how the ConstructionCostPK calculator turns your inputs into an estimate, so you can check every assumption. The same formulas run in your browser and on the server (for the tables in our guides), so the numbers always match.</p>
<h2>1. Covered area</h2>
<p>If you don't enter covered area, the calculator suggests one from the plot size and number of storeys using typical ground-coverage ratios, multiplied by the number of storeys: 85% for plots up to 1,125 sq ft (5 Marla), 75% from 1,575 to 2,250 sq ft (7 to 10 Marla), 65% at 4,500 sq ft (1 Kanal) and 55% from 9,000 sq ft (2 Kanal). Between those sizes the ratio changes smoothly, so a bigger plot never gets less covered area than a smaller one.</p>
<h2>2. Grey structure</h2>
<p><strong>Quantity</strong> = covered area × quantity per sq ft × quality factor × design factor × storey factor. <strong>Materials</strong> = quantity × material rate × city material factor. <strong>Labour</strong> = covered area × structure labour rate × city factor × storey factor × design labour factor. The storey factor adds 3% per extra storey. The city material factor applies only 35% of the city factor, because materials vary less between cities than labour does, and it is applied only to our default rates: a rate you type in yourself is used exactly as entered. A basement costs 45% more per sq ft than the grey structure above it; its area is added to the material quantities, to the finishing area and to the area used for cost per sq ft.</p>
<h2>3. Finishing and turnkey</h2>
<p>Finishing = covered area (plus any basement) × finishing rate for the chosen quality, split into material (tiles 30%, woodwork 20%, plumbing 16%, electrical 15%, paint 12%, misc. 7%) and labour. Extra bathrooms beyond one per two bedrooms, and extra kitchens, are added at a fixed cost per room. Turnkey adds a per-sq-ft fit-out allowance (cabinets, wardrobes, fixtures).</p>
<h2>4. Optional extras</h2>
<p>Boundary wall = running feet × wall rate for the quality tier, city-adjusted. Approval/NOC fee = plot size in Marla × editable fee per Marla (default PKR {$r['approvalPerMarla']}).</p>
[hcc_methodology_tables]
<h2>Limitations</h2>
<ul>
<li>Estimates are planning figures, not quotations. Site conditions (soil, slope, water table), structural design and contractor margins can move the real cost by 10–20% or more.</li>
<li>Quantities are thumb rules; your structural engineer's bill of quantities always takes priority.</li>
<li>Default rates are reviewed periodically, not daily. Enter your own quotations in the "Advanced" panel for the best accuracy.</li>
<li>Land, utility connection charges, furniture and appliances are not included.</li>
</ul>
<h2>Changes to this method</h2>
<p>Any change to formulas or default rates is recorded in the update log on our <a href="{$u('about-us')}">About page</a>.</p>
HTML
	);

	/* ---------------- G2 — cost index ---------------- */
	$defs['pakistan-construction-cost-index'] = array(
		'title' => 'Pakistan Construction Cost Index (Monthly)',
		'content' => <<<HTML
<p class="quick-answer-box"><strong>What this is:</strong> ConstructionCostPK's monthly snapshot of house construction cost per sq ft in 11 Pakistani cities, with the cement, steel and brick rates behind it and the change from the previous month. It is recalculated with the same engine as our calculator every time the default rates are updated.</p>
[hcc_cost_index]
<h2>How the index is calculated</h2>
<p>Each figure is the cost per sq ft of a 1,000 sq ft single storey house at standard quality and typical design, using the default material rates of that month and each city's labour factor. See the full <a href="{$u('methodology')}">methodology</a>. You may quote these figures with a link to this page as the source.</p>
HTML
	);

	/* ---------------- X1 — embed page ---------------- */
	$defs['embed-construction-cost-calculator'] = array(
		'title' => 'Embed a Free Construction Cost Calculator on Your Website',
		'content' => <<<HTML
<p>Run a real estate blog, an architecture practice or a building-materials shop? Add the ConstructionCostPK house construction cost calculator to your own page for free. Your visitors get instant grey structure, complete and turnkey estimates for any plot size and city in Pakistan, with a downloadable PDF report — no sign-up, no ads inside the widget.</p>
<h2>Embed code</h2>
[hcc_embed_code]
<h2>Terms</h2>
<ul>
<li>Free for any website. Please keep the "Powered by ConstructionCostPK" credit link (included in the code above) visible under the widget.</li>
<li>The widget updates automatically whenever we update rates — you never need to change the code.</li>
<li>Figures are planning estimates; see our <a href="{$u('disclaimer')}">disclaimer</a>.</li>
</ul>
HTML
	);

	return $defs;
}


/* --------------------------------------------------------------------------
 * Material FAQs (FAQPage only — no WebApplication on these pages).
 * ------------------------------------------------------------------------ */
function hcc_material_faq_items( $m ) {
	$r = hcc_default_calc_rates();
	$b = hcc_base_qty_per_sqft();
	$g5 = hcc_estimate( 1125, 1, 'grey' ); $g5d = hcc_estimate( 1125, 2, 'grey' );
	if ( 'steel' === $m ) {
		return array(
			array( 'q' => 'What is the saria rate per kg in Pakistan today?', 'a' => 'ConstructionCostPK uses a planning rate of PKR ' . number_format( $r['steel'] ) . ' per kg for Grade 60 steel (rates last updated ' . hcc_rates_date_human() . '). Mill and dealer prices change often and differ by brand and city, so confirm on the day you buy.' ),
			array( 'q' => 'How much steel is needed for a 5 Marla house?', 'a' => 'About ' . number_format( round( $g5['qty']['steelKg'] ) ) . ' kg for a single storey 5 Marla house of ' . number_format( $g5['covered'] ) . ' sq ft at standard quality (' . $b['steelKg'] . ' kg per sq ft). A double storey 5 Marla house (' . number_format( $g5d['covered'] ) . ' sq ft) needs about ' . number_format( round( $g5d['qty']['steelKg'] ) ) . ' kg.' ),
			array( 'q' => 'Grade 40 or Grade 60 steel — which is better for a house?', 'a' => 'Grade 60 (about 420 MPa yield strength) is the standard for houses today and is what most structural drawings specify. Use the grade your structural engineer specifies.' ),
			array( 'q' => 'Saria ka rate aaj kal kya hai?', 'a' => 'Hamara planning rate PKR ' . number_format( $r['steel'] ) . ' per kg (Grade 60) hai. Asal rate brand aur sheher ke hisaab se badalta hai — khareedne se pehle dealer se aaj ka rate confirm karein.' ),
		);
	}
	return array(
		array( 'q' => 'What is the cement rate per bag in Pakistan today?', 'a' => 'ConstructionCostPK uses a planning rate of PKR ' . number_format( $r['cement'] ) . ' per 50 kg bag (rates last updated ' . hcc_rates_date_human() . '). Actual prices vary by brand, city and dealer, so ask two or three dealers for today\'s quote.' ),
		array( 'q' => 'How many cement bags are needed for a 5 Marla house?', 'a' => 'About ' . number_format( round( $g5['qty']['cementBags'] ) ) . ' bags for the grey structure of a single storey 5 Marla house (' . $b['cementBags'] . ' bags per sq ft of covered area at standard quality), or about ' . number_format( round( $g5d['qty']['cementBags'] ) ) . ' bags for a double storey one.' ),
		array( 'q' => 'Which cement is best for foundations in salty soil?', 'a' => 'Sulphate-resisting cement (SRC) is normally recommended for foundations in soil or groundwater with high sulphate content, such as in parts of coastal Karachi. Follow your structural engineer\'s specification.' ),
		array( 'q' => 'Cement ki bori ka rate kya hai?', 'a' => 'Hamara planning rate PKR ' . number_format( $r['cement'] ) . ' per 50 kg bori hai. Brand aur sheher ke hisaab se asal qeemat alag hoti hai — dealer se aaj ka rate zaroor poochein.' ),
	);
}
function hcc_material_faq_render( $m ) {
	$items = hcc_material_faq_items( $m );
	$slug = 'steel' === $m ? 'steel-saria-rate-today' : 'cement-rate-today-pakistan';
	$main = array();
	foreach ( $items as $i ) $main[] = array( '@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $i['a'] ) );
	$schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => home_url( '/' . $slug . '/' ) . '#faq', 'inLanguage' => 'en-US', 'mainEntity' => $main );
	return hcc_render_faq_custom( $items, 'Quick answers about ' . ( 'steel' === $m ? 'steel (saria)' : 'cement' ) . ' prices and quantities in Pakistan.' )
		. '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>';
}
