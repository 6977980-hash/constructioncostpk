<?php
/**
 * House Cost Calculator theme — functions & theme setup.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HCC_VERSION', '1.8.0' );

require get_template_directory() . '/inc/shortcode-calculator.php';
// v1.8.0 SEO-audit implementation (see docs in the delivery package).
require get_template_directory() . '/inc/hcc-data.php';
require get_template_directory() . '/inc/content-blocks.php';
require get_template_directory() . '/inc/content-enhance.php';
require get_template_directory() . '/inc/seo.php';
require get_template_directory() . '/inc/features.php';
require get_template_directory() . '/inc/pages-v18.php';
require get_template_directory() . '/inc/migrations.php';
require get_template_directory() . '/inc/admin.php';

/**
 * Theme setup.
 */
function hcc_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'house-cost-calculator' ),
		'footer'  => __( 'Footer Navigation', 'house-cost-calculator' ),
	) );
}
add_action( 'after_setup_theme', 'hcc_theme_setup' );

/**
 * Enqueue styles/scripts. The calculator's own JS is only enqueued on pages
 * that actually contain the [house_cost_calculator] shortcode, and jsPDF is
 * NOT enqueued here at all — it is lazy-loaded by calculator.js itself only
 * when the visitor clicks "Download PDF Report", to keep initial page weight
 * (and Largest Contentful Paint) as low as possible.
 */
function hcc_enqueue_assets() {
	wp_enqueue_style( 'hcc-style', get_stylesheet_uri(), array(), HCC_VERSION );

	// Tiny (dependency-free) sitewide script that only toggles the mobile nav
	// open/closed — needed on every page since the header/nav is sitewide.
	wp_enqueue_script( 'hcc-nav', get_template_directory_uri() . '/assets/js/nav-toggle.js', array(), HCC_VERSION, true );

	global $post;
	$has_calculator = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'house_cost_calculator' );

	if ( $has_calculator ) {
		wp_enqueue_script( 'hcc-calculator', get_template_directory_uri() . '/assets/js/calculator.js', array(), HCC_VERSION, true );
		// Self-hosted jsPDF URL (T6.8 — no third-party CDN) + branding for the PDF.
		wp_add_inline_script( 'hcc-calculator', 'window.hccCalc=' . wp_json_encode( hcc_calc_js_config() ) . ';', 'before' );
	}
}
add_action( 'wp_enqueue_scripts', 'hcc_enqueue_assets' );

/**
 * Register the primary nav menu as a real, crawlable set of internal links
 * (About Us / Grey Structure Calculator / Privacy / Disclaimer) — a
 * deliberate upgrade over a JS-only single-page layout, so search engines
 * can discover and rank each page independently.
 */
function hcc_fallback_menu() {
	echo '<ul class="fallback-menu">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>';
	$pages = array(
		'construction-cost-guides'       => 'Guides',
		'grey-structure-cost-calculator' => 'Grey Structure Calculator',
		'about-us'                       => 'About Us',
		'contact-us'                     => 'Contact Us',
		'privacy-policy'                 => 'Privacy Policy',
		'disclaimer'                     => 'Disclaimer',
	);
	foreach ( $pages as $slug => $label ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			echo '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
	}
	echo '</ul>';
}

/**
 * Inline SVG icon sprite — original, hand-drawn line icons used throughout
 * the site (no third-party icon packs, no copied artwork).
 */
function hcc_icon_sprite() {
	?>
	<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
		<symbol id="icon-ruler-compass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20 12 4l8 16"/><path d="M8 12h8"/><path d="M6.5 16h11"/></symbol>
		<symbol id="icon-formula" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h4M7 13h10M7 17h7"/><circle cx="17" cy="9" r="1.4"/></symbol>
		<symbol id="icon-layers" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5-10-5Z"/><path d="m2 13 10 5 10-5"/><path d="m2 18 10 5 10-5"/></symbol>
		<symbol id="icon-ruler" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="8" rx="1.5"/><path d="M6 8v3M9.5 8v3M13 8v3M16.5 8v3"/></symbol>
		<symbol id="icon-split" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l7 4.2"/></symbol>
		<symbol id="icon-plots" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
		<symbol id="icon-factors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v3M12 18.5v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2.5 12h3M18.5 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></symbol>
		<symbol id="icon-savings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12c0-3.3 3.1-6 7.5-6 3 0 5.3 1.2 6.5 3h2l-1 2.4 1 2.6h-2.2c-.6 1-1.6 1.8-2.8 2.4V19h-2.5v-1.3c-.7.1-1.4.2-2 .2C7.1 17.9 4 15.3 4 12Z"/><circle cx="15" cy="10.2" r=".9" fill="currentColor" stroke="none"/></symbol>
		<symbol id="icon-help" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.9.4-1.4 1-1.4 2"/><circle cx="12" cy="16.6" r=".25" fill="currentColor" stroke="none"/></symbol>
		<symbol id="icon-crane" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V6l9-3v3"/><path d="M13 6h8l-3 3"/><path d="M18 9v6"/><path d="M4 21h9"/><path d="M9 21v-6h4v6"/></symbol>
		<symbol id="icon-calc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8"/><path d="M7.5 11h1.5M11.25 11h1.5M15 11h1.5M7.5 14.5h1.5M11.25 14.5h1.5M15 14.5h1.5M7.5 18h1.5M11.25 18h1.5M15 15.5v4"/></symbol>
		<symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></symbol>
		<symbol id="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
	</svg>
	<?php
}

/**
 * Content for auto-created pages. Kept here (rather than as separate .html
 * files) so activation can create/update them with wp_insert_post() in one
 * pass, and so re-running activation is idempotent (see hcc_create_pages).
 */
function hcc_page_definitions() {
	$year          = date_i18n( 'Y' );
	$hcc_home      = home_url( '/' );
	$hcc_grey_url  = home_url( '/grey-structure-cost-calculator/' );
	$hcc_5m_url    = home_url( '/5-marla-house-construction-cost/' );
	$hcc_7m_url    = home_url( '/7-marla-house-construction-cost/' );
	$hcc_10m_url   = home_url( '/10-marla-house-construction-cost/' );
	$hcc_1k_url    = home_url( '/1-kanal-house-construction-cost/' );
	$hcc_marla_url = home_url( '/marla-kanal-standards-pakistan/' );
	$hcc_img_base  = get_template_directory_uri() . '/assets/img/';

	$hcc_5marla_content = <<<HTML
<p><strong>Quick answer:</strong> a 5 Marla plot is 1,125 sq ft. Built as a single storey home with roughly 956 sq ft of covered area, a <strong>complete house</strong> (structure + finishing, standard quality, Lahore rates) costs approximately <strong>PKR 4,065,450</strong> — about PKR 4,253 per sq ft. <strong>Grey structure only</strong> costs approximately <strong>PKR 2,569,250</strong>, about PKR 2,688 per sq ft. These figures come from the same calculator engine used below, so you can recalculate them instantly for your own city, quality level and design.</p>

<figure class="illo">
<img src="{$hcc_img_base}hero-5-marla.png" alt="Infographic showing a 5 Marla plot (1,125 sq ft) in Pakistan with an estimated 956 sq ft single storey covered area and an estimated PKR 4,065,450 complete house construction cost" width="1200" height="630" loading="eager">
<figcaption>5 Marla plot size, covered area and estimated complete-house construction cost at a glance.</figcaption>
</figure>

<h2>How Big Is a 5 Marla Plot?</h2>
<p>In Pakistan, 1 Marla equals 225 sq ft, so a 5 Marla plot works out to 1,125 sq ft of land. It is one of the most common plot sizes for a first home, especially in housing societies around Lahore, Karachi, Faisalabad and Islamabad's satellite towns. Because the plot itself is compact, most 5 Marla houses are built as a single storey, though a growing number of owners choose a double storey design to fit more bedrooms onto the same piece of land.</p>
<p>Local building bylaws typically allow roughly 80–85% ground coverage on a plot this size once front and side setbacks are accounted for, which is why this calculator suggests around 956 sq ft of covered area for a single storey 5 Marla house. If your own approved drawing shows a different figure, simply edit the "Covered Area" field in the calculator below — every other number recalculates instantly.</p>
<p class="hint">Note: this guide uses 1 Marla = 225 sq ft ("Society Marla"), the most common standard across Pakistan today. Some plots use 250, 272 or 272.25 sq ft per Marla instead — if yours does, select it from the "Marla / Kanal Standard" dropdown in the calculator below (see our <a href="{$hcc_marla_url}">full guide to Pakistan's Marla &amp; Kanal standards</a> for a detailed comparison). Only the plot-size-to-sq-ft conversion changes; once your covered area is set correctly, every cost figure on this page still applies.</p>

<h2>5 Marla House Construction Cost: Grey Structure vs Complete House</h2>
<p>The table below shows how total cost changes with construction type and finishing quality, using standard 2026 Lahore market rates and 956 sq ft of covered area. All figures are editable — change city, quality or design complexity in the calculator below and this same breakdown recalculates for your exact project.</p>
<table class="mini-table">
<thead><tr><th>Construction Type</th><th>Cost / Sq Ft</th><th>Estimated Total Cost</th></tr></thead>
<tbody>
<tr><td>Grey structure only</td><td>PKR 2,688</td><td>PKR 2,569,250</td></tr>
<tr><td>Complete house — economy quality</td><td>PKR 3,260</td><td>PKR 3,116,390</td></tr>
<tr><td>Complete house — standard quality</td><td>PKR 4,253</td><td>PKR 4,065,450</td></tr>
<tr><td>Complete house — premium quality</td><td>PKR 5,697</td><td>PKR 5,446,246</td></tr>
</tbody>
</table>
<p class="hint">Based on a 956 sq ft, single storey covered area in Lahore. Add a boundary wall or approval fee allowance in the calculator for a more complete budget.</p>

<h2>Material Quantities for a 5 Marla House</h2>
<p>For the same 956 sq ft covered area at standard quality and typical design complexity, a 5 Marla house needs approximately the following material quantities for the structural (grey) portion of the work:</p>
<table class="mini-table">
<thead><tr><th>Material</th><th>Approx. Quantity</th></tr></thead>
<tbody>
<tr><td>Cement</td><td>402 bags</td></tr>
<tr><td>Steel (saria)</td><td>3,442 kg</td></tr>
<tr><td>Bricks</td><td>7,648</td></tr>
<tr><td>Sand</td><td>1,052 cft</td></tr>
<tr><td>Crush (aggregate)</td><td>813 cft</td></tr>
</tbody>
</table>
<p>These quantities scale with covered area, quality tier and design complexity — the <a href="{$hcc_grey_url}">Grey Structure Cost Calculator</a> shows the exact material cost breakdown priced against your own supplier rates.</p>

<h2>Single Storey or Double Storey on a 5 Marla Plot?</h2>
<p>Most 5 Marla houses are built single storey with 2–3 bedrooms, a lounge and a small kitchen — this keeps foundation and steel requirements modest and construction cost more predictable, since a single storey structure needs a lighter foundation than a multi-storey one on the same plot. A double storey 5 Marla house roughly doubles the usable covered area for more bedrooms or a separate portion for rental income, but costs meaningfully more in total because of extra steel, columns, stairs and finishing area. Use the "Number of Storeys" field in the calculator below to compare both options side by side for your own budget.</p>

<h2>Ways to Save on a 5 Marla House Budget</h2>
<p>On a smaller plot, every rupee of wastage matters proportionally more than on a large plot. A few practical ways to keep a 5 Marla construction budget under control: finalize your layout and Bill of Quantities before starting rather than changing the design mid-construction; stick to standard door, window and tile sizes to avoid custom fabrication and cutting waste; buy cement and steel directly rather than through a contractor markup where possible; and consider building the grey structure first and phasing finishing work (flooring, paint, fixtures) as your budget allows. The <a href="{$hcc_home}">full construction cost guide</a> on our home page covers these strategies in more detail.</p>

<h2>Calculate Your Own 5 Marla House Cost</h2>
<p>The calculator below is pre-set to complete house construction — simply set your plot size to 5 Marla (or leave the suggested covered area), choose your city and quality level, and get an instant, itemized estimate with a downloadable PDF report. No sign-up required.</p>

[house_cost_calculator mode="calculator_only" variant="5-marla" default_type="complete"]
HTML;

	$hcc_7marla_content = <<<HTML
<p><strong>Quick answer:</strong> a 7 Marla plot is 1,575 sq ft. Built as a single storey home with roughly 1,181 sq ft of covered area, a <strong>complete house</strong> (structure + finishing, standard quality, Lahore rates) costs approximately <strong>PKR 4,996,388</strong> — about PKR 4,231 per sq ft. <strong>Grey structure only</strong> costs approximately <strong>PKR 3,173,938</strong>, about PKR 2,688 per sq ft. These figures are produced by the same calculator engine used below, so you can recalculate them instantly for your own city, quality level and design.</p>

<figure class="illo">
<img src="{$hcc_img_base}hero-7-marla.png" alt="Infographic showing a 7 Marla plot (1,575 sq ft) in Pakistan with an estimated 1,181 sq ft single storey covered area and an estimated PKR 4,996,388 complete house construction cost" width="1200" height="630" loading="eager">
<figcaption>7 Marla plot size, covered area and estimated complete-house construction cost at a glance.</figcaption>
</figure>

<h2>How Big Is a 7 Marla Plot?</h2>
<p>A 7 Marla plot works out to 1,575 sq ft of land (1 Marla = 225 sq ft) — a popular "step-up" size for families who want noticeably more room than a 5 Marla plot without moving all the way to a 10 Marla budget. Most 7 Marla houses in Pakistan are still built single storey, with a comfortable 3-bedroom layout, though a double storey option is common where more bedrooms or a home office/rental portion is needed.</p>
<p>Applying typical building-bylaw coverage ratios for this plot size (around 80–85% ground coverage), this calculator suggests approximately 1,181 sq ft of covered area for a single storey 7 Marla house. As always, this is editable — enter your own approved drawing's covered area in the calculator below for an exact match.</p>
<p class="hint">Note: this guide uses 1 Marla = 225 sq ft ("Society Marla"), the most common standard across Pakistan today. Some plots use 250, 272 or 272.25 sq ft per Marla instead — if yours does, select it from the "Marla / Kanal Standard" dropdown in the calculator below (see our <a href="{$hcc_marla_url}">full guide to Pakistan's Marla &amp; Kanal standards</a> for a detailed comparison). Only the plot-size-to-sq-ft conversion changes; once your covered area is set correctly, every cost figure on this page still applies.</p>

<h2>7 Marla House Construction Cost: Grey Structure vs Complete House</h2>
<p>Using standard 2026 Lahore market rates and 1,181 sq ft of covered area, here is how total cost shifts by construction type and quality tier:</p>
<table class="mini-table">
<thead><tr><th>Construction Type</th><th>Cost / Sq Ft</th><th>Estimated Total Cost</th></tr></thead>
<tbody>
<tr><td>Grey structure only</td><td>PKR 2,688</td><td>PKR 3,173,938</td></tr>
<tr><td>Complete house — economy quality</td><td>PKR 3,246</td><td>PKR 3,833,375</td></tr>
<tr><td>Complete house — standard quality</td><td>PKR 4,231</td><td>PKR 4,996,388</td></tr>
<tr><td>Complete house — premium quality</td><td>PKR 5,661</td><td>PKR 6,685,686</td></tr>
</tbody>
</table>
<p class="hint">Based on a 1,181 sq ft, single storey covered area in Lahore. Karachi and Islamabad rates differ slightly — recalculate for your own city in the tool below.</p>

<h2>Material Quantities for a 7 Marla House</h2>
<p>For 1,181 sq ft of covered area at standard quality and typical design complexity, a 7 Marla house needs approximately:</p>
<table class="mini-table">
<thead><tr><th>Material</th><th>Approx. Quantity</th></tr></thead>
<tbody>
<tr><td>Cement</td><td>496 bags</td></tr>
<tr><td>Steel (saria)</td><td>4,252 kg</td></tr>
<tr><td>Bricks</td><td>9,448</td></tr>
<tr><td>Sand</td><td>1,299 cft</td></tr>
<tr><td>Crush (aggregate)</td><td>1,004 cft</td></tr>
</tbody>
</table>
<p>Want a dedicated breakdown of just the structural cost, priced against your own supplier quotations? Try the <a href="{$hcc_grey_url}">Grey Structure Cost Calculator</a>.</p>

<h2>Single Storey or Double Storey on a 7 Marla Plot?</h2>
<p>A 7 Marla plot comfortably fits a single storey 3-bedroom house with room for a reasonably sized lounge and courtyard/lawn — a common choice for families who want a garden without the extra steel and foundation cost of a second floor. Going double storey on the same plot roughly doubles covered area, useful for a 4th and 5th bedroom, a separate guest portion, or a small home office, but increases total cost due to added structure, staircase and finishing area. Compare both using the storeys field in the calculator below.</p>

<h2>Ways to Save on a 7 Marla House Budget</h2>
<p>Because a 7 Marla plot sits between the most common "starter" and "family" sizes, a frequent budgeting mistake is under-costing finishing work while planning generously for structure. Get quotations for flooring, sanitary fixtures and electrical fittings early, not just for cement and steel, since finishing typically makes up 35–45% of a complete house's cost. Other savings strategies — simplifying the design, buying materials in bulk, phasing finishing work — are covered in the <a href="{$hcc_home}">full construction cost guide</a> on our home page.</p>

<h2>Calculate Your Own 7 Marla House Cost</h2>
<p>The calculator below is pre-set to complete house construction — set your plot size to 7 Marla, choose your city and quality level, and get an instant, itemized estimate with a downloadable PDF report. No sign-up required.</p>

[house_cost_calculator mode="calculator_only" variant="7-marla" default_type="complete"]
HTML;

	$hcc_10marla_content = <<<HTML
<p><strong>Quick answer:</strong> a 10 Marla plot is 2,250 sq ft. Built as a double storey home with roughly 3,375 sq ft of total covered area, a <strong>complete house</strong> (structure + finishing, standard quality, Lahore rates) costs approximately <strong>PKR 14,346,172</strong> — about PKR 4,251 per sq ft. <strong>Grey structure only</strong> costs approximately <strong>PKR 9,342,422</strong>, about PKR 2,768 per sq ft. These figures come from the same calculator engine used below, so you can recalculate them instantly for your own city, quality level and design.</p>

<figure class="illo">
<img src="{$hcc_img_base}hero-10-marla.png" alt="Infographic showing a 10 Marla plot (2,250 sq ft) in Pakistan with an estimated 3,375 sq ft double storey covered area and an estimated PKR 14,346,172 complete house construction cost" width="1200" height="630" loading="eager">
<figcaption>10 Marla plot size, covered area and estimated complete-house construction cost at a glance.</figcaption>
</figure>

<h2>How Big Is a 10 Marla Plot?</h2>
<p>A 10 Marla plot is 2,250 sq ft of land (1 Marla = 225 sq ft) — a popular size for established families who want a proper double storey home with multiple bedrooms, a separate drawing and dining area, and often a small lawn or driveway. Almost all 10 Marla houses in Pakistan are built double storey to make full use of the plot; a single storey build on this size plot is comparatively rare.</p>
<p>Using typical building-bylaw coverage ratios for this plot size (around 75% ground coverage, doubled across two floors), this calculator suggests approximately 3,375 sq ft of total covered area for a double storey 10 Marla house. Edit the "Covered Area" and "Number of Storeys" fields below to match your own approved drawing exactly.</p>
<p class="hint">Note: this guide uses 1 Marla = 225 sq ft ("Society Marla"), the most common standard across Pakistan today. Some plots use 250, 272 or 272.25 sq ft per Marla instead — if yours does, select it from the "Marla / Kanal Standard" dropdown in the calculator below (see our <a href="{$hcc_marla_url}">full guide to Pakistan's Marla &amp; Kanal standards</a> for a detailed comparison). Only the plot-size-to-sq-ft conversion changes; once your covered area is set correctly, every cost figure on this page still applies.</p>

<h2>10 Marla House Construction Cost: Grey Structure vs Complete House</h2>
<p>Using standard 2026 Lahore market rates and 3,375 sq ft of total covered area across two storeys, here is the cost breakdown by construction type and quality tier:</p>
<table class="mini-table">
<thead><tr><th>Construction Type</th><th>Cost / Sq Ft</th><th>Estimated Total Cost</th></tr></thead>
<tbody>
<tr><td>Grey structure only</td><td>PKR 2,768</td><td>PKR 9,342,422</td></tr>
<tr><td>Complete house — economy quality</td><td>PKR 3,276</td><td>PKR 11,056,293</td></tr>
<tr><td>Complete house — standard quality</td><td>PKR 4,251</td><td>PKR 14,346,172</td></tr>
<tr><td>Complete house — premium quality</td><td>PKR 5,658</td><td>PKR 19,096,483</td></tr>
</tbody>
</table>
<p class="hint">Based on a 3,375 sq ft, double storey covered area in Lahore, including the small steel and labour premium double storey construction adds over single storey. Recalculate for your own city and design below.</p>

<h2>Material Quantities for a 10 Marla House</h2>
<p>For 3,375 sq ft of total covered area at standard quality and typical design complexity, a double storey 10 Marla house needs approximately:</p>
<table class="mini-table">
<thead><tr><th>Material</th><th>Approx. Quantity</th></tr></thead>
<tbody>
<tr><td>Cement</td><td>1,418 bags</td></tr>
<tr><td>Steel (saria)</td><td>12,150 kg</td></tr>
<tr><td>Bricks</td><td>27,000</td></tr>
<tr><td>Sand</td><td>3,713 cft</td></tr>
<tr><td>Crush (aggregate)</td><td>2,869 cft</td></tr>
</tbody>
</table>
<p>For a structure-only budget priced against your own supplier quotations, use the <a href="{$hcc_grey_url}">Grey Structure Cost Calculator</a>.</p>

<h2>Planning a Double Storey 10 Marla House</h2>
<p>With around 3,375 sq ft split across two floors, a 10 Marla house typically comfortably fits 4–5 bedrooms, 3–4 bathrooms, a separate drawing and dining area on the ground floor, and a family lounge upstairs — often with a small store room or servant quarter if the design allows. Because the second floor adds finishing area (tiling, paint, plumbing, electrical) without needing a second kitchen or additional structural footprint, the cost-per-square-foot of the upper floor is often slightly lower than the ground floor once you exclude the driveway and boundary wall allowance. Model this precisely by entering your exact bedroom and bathroom count in the calculator below.</p>

<h2>Ways to Save on a 10 Marla House Budget</h2>
<p>At this size, structural decisions have an outsized effect on the final bill — an efficient, mostly-rectangular layout with fewer cantilevers and curved walls can save several percent on both steel and formwork labour compared with an architecturally complex design at the same quality tier (try switching "Design Complexity" in the calculator below to see the difference). Getting at least three itemized quotations, buying cement and steel directly, and budgeting separately for boundary wall and government approval fees — which are easy to forget on a plot this size — are covered in more detail in the <a href="{$hcc_home}">full construction cost guide</a> on our home page.</p>

<h2>Calculate Your Own 10 Marla House Cost</h2>
<p>The calculator below is pre-set to complete house construction — set your plot size to 10 Marla, choose your city and quality level, and get an instant, itemized estimate with a downloadable PDF report. No sign-up required.</p>

[house_cost_calculator mode="calculator_only" variant="10-marla" default_type="complete"]
HTML;

	$hcc_1kanal_content = <<<HTML
<p><strong>Quick answer:</strong> 1 Kanal is 4,500 sq ft of land. Built as a double storey home with roughly 5,850 sq ft of total covered area, a <strong>complete house</strong> (structure + finishing, standard quality, Lahore rates) costs approximately <strong>PKR 24,786,031</strong> — about PKR 4,237 per sq ft. <strong>Grey structure only</strong> costs approximately <strong>PKR 16,193,531</strong>, about PKR 2,768 per sq ft. These figures come from the same calculator engine used below, so you can recalculate them instantly for your own city, quality level and design.</p>

<figure class="illo">
<img src="{$hcc_img_base}hero-1-kanal.png" alt="Infographic showing a 1 Kanal plot (4,500 sq ft) in Pakistan with an estimated 5,850 sq ft double storey covered area and an estimated PKR 24,786,031 complete house construction cost" width="1200" height="630" loading="eager">
<figcaption>1 Kanal plot size, covered area and estimated complete-house construction cost at a glance.</figcaption>
</figure>

<h2>How Big Is a 1 Kanal Plot?</h2>
<p>1 Kanal equals 20 Marla, or 4,500 sq ft of land (1 Marla = 225 sq ft) — the largest of the commonly built residential plot sizes in Pakistan, typical of premium housing societies and upscale suburbs in Lahore, Islamabad and Karachi. A 1 Kanal house is almost always built double storey (sometimes with a basement) to make full use of the plot for larger bedrooms, multiple lounges, a study, and often a servant portion and a proper driveway with landscaping.</p>
<p>Using typical building-bylaw coverage ratios for a plot this size (around 55% ground coverage, doubled across two floors), this calculator suggests approximately 5,850 sq ft of total covered area for a double storey 1 Kanal house — noticeably less than a simple linear scale-up from smaller plots, because larger plots are required to keep proportionally more open lawn, driveway and setback area under most housing-society bylaws.</p>
<p class="hint">Note: this guide uses 1 Kanal = 20 Marla = 4,500 sq ft, based on the most common "Society Marla" standard of 225 sq ft. Under the older Patwari / revenue-record standard (272.25 sq ft per Marla), 1 Kanal is actually 5,445 sq ft instead — a meaningful difference. If your plot's own record uses 250, 272 or 272.25 sq ft per Marla, select it from the "Marla / Kanal Standard" dropdown in the calculator below (see our <a href="{$hcc_marla_url}">full guide to Pakistan's Marla &amp; Kanal standards</a> for a detailed comparison). Only the plot-size-to-sq-ft conversion changes; once your covered area is set correctly, every cost figure on this page still applies.</p>

<h2>1 Kanal House Construction Cost: Grey Structure vs Complete House</h2>
<p>Using standard 2026 Lahore market rates and 5,850 sq ft of total covered area, the table below shows the full range from grey structure to premium finishing:</p>
<table class="mini-table">
<thead><tr><th>Construction Type</th><th>Cost / Sq Ft</th><th>Estimated Total Cost</th></tr></thead>
<tbody>
<tr><td>Grey structure only</td><td>PKR 2,768</td><td>PKR 16,193,531</td></tr>
<tr><td>Complete house — economy quality</td><td>PKR 3,267</td><td>PKR 19,112,908</td></tr>
<tr><td>Complete house — standard quality</td><td>PKR 4,237</td><td>PKR 24,786,031</td></tr>
<tr><td>Complete house — premium quality</td><td>PKR 5,636</td><td>PKR 32,968,570</td></tr>
</tbody>
</table>
<p class="hint">Based on a 5,850 sq ft, double storey covered area in Lahore. A turnkey (fully fitted-out, move-in-ready) 1 Kanal build typically runs higher still — select "Turnkey" in the calculator below to see that figure.</p>

<h2>Material Quantities for a 1 Kanal House</h2>
<p>For 5,850 sq ft of total covered area at standard quality and typical design complexity, a double storey 1 Kanal house needs approximately:</p>
<table class="mini-table">
<thead><tr><th>Material</th><th>Approx. Quantity</th></tr></thead>
<tbody>
<tr><td>Cement</td><td>2,457 bags</td></tr>
<tr><td>Steel (saria)</td><td>21,060 kg</td></tr>
<tr><td>Bricks</td><td>46,800</td></tr>
<tr><td>Sand</td><td>6,435 cft</td></tr>
<tr><td>Crush (aggregate)</td><td>4,973 cft</td></tr>
</tbody>
</table>
<p>For a structure-only budget priced against your own supplier quotations, use the <a href="{$hcc_grey_url}">Grey Structure Cost Calculator</a>.</p>

<h2>What a 1 Kanal House Budget Should Include</h2>
<p>At this scale, several cost heads that are optional or minor on a smaller plot become significant: a boundary wall and gate long enough to enclose a 4,500 sq ft plot, a driveway and hard landscaping, higher government approval/NOC fees (typically charged per Marla, so 20 Marla adds up quickly), and often a basement or larger structural spans that increase steel requirements beyond the simple per-square-foot average. Tick "Include Boundary Wall" and "Include Approval Fee" in the calculator below to fold these into your total budget rather than discovering them mid-project.</p>

<h2>Ways to Save on a 1 Kanal House Budget</h2>
<p>On a project this size, even a small percentage saving is a large absolute amount — which is exactly why finalizing drawings and a full Bill of Quantities before breaking ground matters most here. Buying cement and steel in bulk directly from suppliers (rather than through a contractor markup), getting at least three detailed, itemized quotations, and keeping the structural design efficient rather than architecturally complex can each save several percent. These strategies, along with a full grey-vs-complete-vs-turnkey comparison, are covered in the <a href="{$hcc_home}">full construction cost guide</a> on our home page.</p>

<h2>Calculate Your Own 1 Kanal House Cost</h2>
<p>The calculator below is pre-set to complete house construction — set your plot size to 1 Kanal, choose your city, quality level and number of storeys, and get an instant, itemized estimate with a downloadable PDF report. No sign-up required.</p>

[house_cost_calculator mode="calculator_only" variant="1-kanal" default_type="complete"]
HTML;

	$hcc_marla_standards_content = <<<HTML
<p>There is no single "sq ft per Marla" figure in Pakistan — <strong>four different standards are in everyday use</strong>: 225 sq ft (the most common, used by LDA, DHA, Bahria Town and most modern housing societies), 250 sq ft (Karachi and Cantonment Board areas), 272 sq ft (the Land Revenue Marla used in many local records) and 272.25 sq ft (the older Patwari / government revenue-record standard, still used in rural areas and older records, including much of Faisalabad). Because 1 Kanal always equals 20 Marla, the size of a Kanal shifts too — from 4,500 sq ft under the 225 sq ft standard up to 5,445 sq ft under the 272.25 sq ft standard.</p>

<figure class="illo">
<img src="{$hcc_img_base}hero-marla-standards.png" alt="Comparison infographic of Pakistan's four Marla standards — 225, 250, 272 and 272.25 square feet — showing the resulting size of 1 Kanal under each standard" width="1200" height="630" loading="eager">
<figcaption>Pakistan's four Marla standards compared — the same "1 Marla" label can mean four different plot sizes.</figcaption>
</figure>

<h2>The 4 Marla Standards Used in Pakistan</h2>
<p>"Marla" is not a fixed, single unit — its size in square feet depends on which land-record system or housing authority issued the plot. The table below lists all four standards in common use:</p>
<table class="mini-table">
<thead><tr><th>Marla Type</th><th>Square Feet</th><th>Square Yards</th><th>Where It's Typically Used</th></tr></thead>
<tbody>
<tr><td>Society Marla (small Marla)</td><td>225 sq ft</td><td>25 sq yd</td><td>Lahore (LDA), DHA, Bahria Town and most modern private housing societies</td></tr>
<tr><td>Karachi / Cantonment Marla (middle Marla)</td><td>250 sq ft</td><td>27.78 sq yd</td><td>Karachi, some Sindh areas, older parts of Rawalpindi and Cantonment Board areas</td></tr>
<tr><td>Land Revenue Marla</td><td>272 sq ft</td><td>30.22 sq yd</td><td>Land revenue records and some local documentation in Punjab</td></tr>
<tr><td>Patwari / Revenue-Record Marla</td><td>272.25 sq ft</td><td>30.25 sq yd</td><td>Government revenue records (patwar khana), rural areas, Faisalabad and older land records</td></tr>
</tbody>
</table>
<p class="hint">These are the standards in general market use, not a legal ruling — always confirm the exact sq ft per Marla with the relevant housing authority or land record for your specific plot before relying on it.</p>

<h2>1 Kanal Changes Too — It's Always 20 Marla</h2>
<p>Whichever Marla standard applies, 1 Kanal is always exactly 20 Marla — but because the Marla itself varies, so does the resulting size of a Kanal:</p>
<table class="mini-table">
<thead><tr><th>Marla Standard</th><th>1 Kanal (20 Marla)</th></tr></thead>
<tbody>
<tr><td>225 sq ft (Society Marla)</td><td>4,500 sq ft</td></tr>
<tr><td>250 sq ft (Karachi / Cantonment Marla)</td><td>5,000 sq ft</td></tr>
<tr><td>272 sq ft (Land Revenue Marla)</td><td>5,440 sq ft</td></tr>
<tr><td>272.25 sq ft (Patwari / Revenue-Record Marla)</td><td>5,445 sq ft</td></tr>
</tbody>
</table>

<h2>A Real Example: How Much Difference Does This Actually Make?</h2>
<p>Take a plot everyone calls "10 Marla." Depending on which standard applies, its actual size ranges from:</p>
<table class="mini-table">
<thead><tr><th>Marla Standard</th><th>"10 Marla" Plot Size</th></tr></thead>
<tbody>
<tr><td>225 sq ft (Society Marla)</td><td>2,250 sq ft</td></tr>
<tr><td>250 sq ft (Karachi / Cantonment Marla)</td><td>2,500 sq ft</td></tr>
<tr><td>272 sq ft (Land Revenue Marla)</td><td>2,720 sq ft</td></tr>
<tr><td>272.25 sq ft (Patwari / Revenue-Record Marla)</td><td>2,722.5 sq ft</td></tr>
</tbody>
</table>
<p>That's a difference of 472.5 sq ft between the smallest and largest reading of the same "10 Marla" label — about <strong>17% of the total plot size</strong>. On a bigger plot, or at typical per-square-foot land and construction rates, that gap can be worth millions of rupees, so it is never a difference to assume away.</p>

<h2>How to Find Out Which Standard Your Plot Uses</h2>
<ol>
<li>Ask the housing authority or developer directly (LDA, DHA, Bahria Town and most modern societies use 225 sq ft per Marla, but always confirm for your specific scheme).</li>
<li>Check your allotment letter, possession letter or registry/intiqal documents — these usually state the plot area in sq ft or sq yd alongside the Marla figure, letting you back-calculate the standard used.</li>
<li>For rural land, older properties, or areas like Faisalabad, ask specifically whether the Patwari / revenue-record standard (272.25 sq ft) applies — this is common outside planned urban societies.</li>
<li>When in doubt, get the plot professionally surveyed and measured directly rather than relying on any Marla conversion.</li>
</ol>

<h2>Why This Matters When Buying a Plot</h2>
<p>Never rely on the word "Marla" alone when buying, selling or budgeting for a plot in Pakistan. Always confirm with the housing authority or land record exactly how many square feet their Marla represents — the difference between standards can be as much as 17% of the total plot size, which directly affects both the price you should pay for the land and the true covered area you can build on. It also matters for construction: cost-per-Marla rates quoted by contractors are meaningless unless you know which Marla standard they used to calculate them, so always convert to square feet before comparing quotations.</p>

<h2>Calculate Construction Cost For Your Exact Plot Size</h2>
<p>Once you know your plot's exact standard, select it from the "Marla / Kanal Standard" dropdown in the calculator below, enter your plot size, and get an instant, itemized construction cost estimate that matches your plot's real area — not just its label. See our dedicated guides for <a href="{$hcc_5m_url}">5 Marla</a>, <a href="{$hcc_10m_url}">10 Marla</a> and <a href="{$hcc_1k_url}">1 Kanal</a> house construction costs for worked examples.</p>

[house_cost_calculator mode="calculator_only" variant="marla-standards" default_type="complete"]
HTML;

	$hcc_guides_content = <<<HTML
<p>Every construction-cost guide on this site in one place, organized by category. Each guide uses the same calculation engine as the main <a href="{$hcc_home}">House Construction Cost Calculator</a>, so figures never contradict each other — pick the one closest to your own plot and refine it with the calculator embedded on that page.</p>

<h2>By Plot Size</h2>
<ul>
<li><strong><a href="{$hcc_5m_url}">5 Marla House Construction Cost</a></strong> — grey structure, complete house cost per sq ft and material quantities for a 1,125 sq ft plot.</li>
<li><strong><a href="{$hcc_7m_url}">7 Marla House Construction Cost</a></strong> — the same breakdown for a 1,575 sq ft plot, a common single-storey family home size.</li>
<li><strong><a href="{$hcc_10m_url}">10 Marla House Construction Cost</a></strong> — a 2,250 sq ft plot, usually built double storey with 4–5 bedrooms.</li>
<li><strong><a href="{$hcc_1k_url}">1 Kanal House Construction Cost</a></strong> — a 4,500 sq ft plot, the largest of the commonly built residential sizes.</li>
</ul>

<h2>Land Measurement Reference</h2>
<ul>
<li><strong><a href="{$hcc_marla_url}">Marla &amp; Kanal Standards Explained</a></strong> — Pakistan has four different sq-ft-per-Marla standards (225, 250, 272 and 272.25 sq ft). Confirm which one applies to your plot before trusting any Marla-based figure.</li>
</ul>

<h2>Structural Budgeting</h2>
<ul>
<li><strong><a href="{$hcc_grey_url}">Grey Structure Cost Calculator</a></strong> — a dedicated tool for pricing the structural shell only, for anyone building in phases.</li>
</ul>

<h2>Calculate Your Own Cost</h2>
<p>None of these match your exact plot, city or quality level? Use the full <a href="{$hcc_home}">House Construction Cost Calculator</a> for any custom plot size, city and finishing level, with an itemized, downloadable PDF report.</p>
HTML;

	return array(
		'home' => array(
			'title'   => 'House Construction Cost Calculator – Pakistan (2026)',
			'slug'    => 'home',
			'content' => '[house_cost_calculator mode="full"]',
			'excerpt' => 'Free house construction cost calculator for Pakistan — grey structure, complete house and turnkey construction cost per square foot, with itemized PDF report.',
			'front'   => true,
		),
		'grey-structure' => array(
			'title'   => 'Grey Structure Cost Calculator Pakistan',
			'slug'    => 'grey-structure-cost-calculator',
			'content' => "<p>Use this dedicated grey structure cost calculator to estimate the cost of the structural shell only — foundation, columns, beams, roof slab and brickwork — without any finishing work. Grey structure cost per square foot in Pakistan typically runs from about PKR 1,800 to PKR 3,000 depending on city, quality and design complexity, and this tool breaks that figure down into cement, steel, bricks, sand and crush quantities plus structure labour, so you can plan a phased build with confidence.</p>\n<p>Many homeowners in Pakistan build the grey structure first and complete finishing work in stages as budget allows. This calculator uses the exact same engine as our main <a href=\"" . home_url( '/' ) . "\">house construction cost calculator</a>, pre-set to Grey Structure, so the numbers always match.</p>\n\n[house_cost_calculator mode=\"calculator_only\" default_type=\"grey\"]",
			'excerpt' => 'Free grey structure cost calculator for Pakistan — estimate structural shell cost per square foot with a full material quantity breakdown.',
			'front'   => false,
		),
		'guides' => array(
			'title'   => 'House Construction Cost Guides – Pakistan (2026)',
			'slug'    => 'construction-cost-guides',
			'content' => $hcc_guides_content,
			'excerpt' => 'Every house construction cost guide on this site in one place — by plot size (5/7/10 Marla, 1 Kanal), Marla & Kanal standards, and grey structure budgeting.',
			'front'   => false,
		),
		'plot-5-marla' => array(
			'title'   => '5 Marla House Construction Cost in Pakistan (2026)',
			'slug'    => '5-marla-house-construction-cost',
			'content' => $hcc_5marla_content,
			'excerpt' => 'How much does it cost to build a 5 Marla house in Pakistan in 2026? Grey structure, complete house cost per sq ft and material quantities — calculator-based figures.',
			'front'   => false,
		),
		'plot-7-marla' => array(
			'title'   => '7 Marla House Construction Cost in Pakistan (2026)',
			'slug'    => '7-marla-house-construction-cost',
			'content' => $hcc_7marla_content,
			'excerpt' => 'How much does it cost to build a 7 Marla house in Pakistan in 2026? Grey structure, complete house cost per sq ft and material quantities — calculator-based figures.',
			'front'   => false,
		),
		'plot-10-marla' => array(
			'title'   => '10 Marla House Construction Cost in Pakistan (2026)',
			'slug'    => '10-marla-house-construction-cost',
			'content' => $hcc_10marla_content,
			'excerpt' => 'How much does it cost to build a 10 Marla double storey house in Pakistan in 2026? Grey structure, complete house cost per sq ft and material quantities — calculator-based figures.',
			'front'   => false,
		),
		'plot-1-kanal' => array(
			'title'   => '1 Kanal House Construction Cost in Pakistan (2026)',
			'slug'    => '1-kanal-house-construction-cost',
			'content' => $hcc_1kanal_content,
			'excerpt' => 'How much does it cost to build a 1 Kanal house in Pakistan in 2026? Grey structure, complete house cost per sq ft and material quantities — calculator-based figures.',
			'front'   => false,
		),
		'marla-standards' => array(
			'title'   => 'How Many Square Feet Are in a Marla? Pakistan\'s Marla & Kanal Standards Explained (2026)',
			'slug'    => 'marla-kanal-standards-pakistan',
			'content' => $hcc_marla_standards_content,
			'excerpt' => 'Pakistan has 4 different Marla standards — 225, 250, 272 and 272.25 sq ft. See how each one changes your plot size, 1 Kanal, and construction cost.',
			'front'   => false,
		),
		'about' => array(
			'title'   => 'About Us',
			'slug'    => 'about-us',
			'content' => "<p>This House Construction Cost Calculator was built for anyone planning a residential construction project in Pakistan — from a first-time 5 Marla homeowner to someone budgeting a 1 Kanal turnkey build — who needs a fast, transparent, itemized estimate without calling multiple contractors just to get a ballpark figure.</p>\n<p>Our goal is simple: take the scattered rules of thumb that experienced contractors and engineers use every day — cost per square foot, cement bags per square foot, steel per square foot — and put them into one editable, easy-to-use tool. Every default rate used in this calculator (cement, steel, bricks, sand, crush, boundary wall, approval fees, labour and finishing rates) is shown clearly and can be edited, because we believe an estimate is only useful if you understand exactly what assumptions went into it.</p>\n<p>This is an independent, self-funded informational tool, founded and maintained by Ali Ahmad (ConstructionCostPK). We are not a construction company, contractor, real estate agency or material supplier, and we do not receive commission for recommending any contractor, brand or supplier.</p>\n<p>We update our default rate assumptions periodically based on publicly available market information, but construction material and labour prices in Pakistan can change quickly. If you notice that our default rates are significantly out of date for your area, please treat the \"Advanced\" rate fields as your primary tool — simply enter your own local quotations and the calculator will use them instead.</p>\n<p>This tool is built entirely to run in your browser — nothing you type into the calculator is sent to any server unless you choose to send optional feedback, which you can read more about in our <a href=\"" . home_url( '/privacy-policy/' ) . "\">Privacy Policy</a>. Have a question or want to reach Ali Ahmad directly? Visit our <a href=\"" . home_url( '/contact-us/' ) . "\">Contact Us</a> page.</p>",
			'excerpt' => 'Who runs the House Construction Cost Calculator and why we built it.',
			'front'   => false,
		),
		'contact' => array(
			'title'   => 'Contact Us',
			'slug'    => 'contact-us',
			'content' => "<p>Have a question about the House Construction Cost Calculator, spotted a default rate that looks out of date for your city, or found something on the site that needs fixing? We would like to hear from you.</p>\n<p>We read every message, though as a small, independent team we may take a little while to reply. Please note that this calculator is an independent informational tool — we are not a construction company or contractor, so we are not able to provide quotations, site visits or project management services. For general information about who runs this site, see our <a href=\"" . home_url( '/about-us/' ) . "\">About Us</a> page.</p>",
			'excerpt' => 'How to get in touch with the team behind the House Construction Cost Calculator.',
			'front'   => false,
		),
		'disclaimer' => array(
			'title'   => 'Disclaimer',
			'slug'    => 'disclaimer',
			'content' => "<p>The House Construction Cost Calculator and all associated content, articles and FAQs on this website are provided for general informational and educational purposes only. They do not constitute professional engineering, architectural, financial or legal advice, and should not be relied upon as a substitute for a formal quotation, structural design or cost certification from a licensed professional.</p>\n<p>All cost estimates, material quantities and rate assumptions produced by this calculator are approximate and based on generalized, editable market-rate assumptions for Pakistan as of 2026. Actual construction costs are affected by many factors this calculator cannot fully account for, including but not limited to: exact site conditions and soil bearing capacity, architectural and structural drawings, local municipal and building-code requirements, contractor pricing and markup, seasonal material price fluctuations, currency and import-cost changes for items such as sanitary ware or electrical fittings, government approval/NOC fee schedules, and regional labour availability.</p>\n<p>We make no warranty, express or implied, regarding the accuracy, completeness, reliability or suitability of the figures produced by this calculator for any particular purpose, including budgeting for a bank loan, tender submission, contract negotiation or investment decision. Before starting construction or signing any contract, you should obtain a detailed, written quotation from a licensed contractor and, where appropriate, a structural design and cost certification from a qualified structural engineer.</p>\n<p>We do not guarantee that using this website will result in any particular search engine ranking, placement, or approval for any advertising program, and we make no representation about the future performance of this website.</p>\n<p>Use of this calculator and website is entirely at your own risk. To the fullest extent permitted by law, we disclaim all liability for any loss or damage arising from reliance on information provided on this website.</p>",
			'excerpt' => 'Important limitations on the estimates produced by this calculator.',
			'front'   => false,
		),
		'privacy' => array(
			'title'   => 'Privacy Policy',
			'slug'    => 'privacy-policy',
			'content' => "<p>We respect your privacy. This page explains what information this website collects and how it is used.</p>\n<h2>Calculator Data</h2>\n<p>All calculations performed by the House Construction Cost Calculator — including your plot size, covered area, city, quality selection, room counts and any custom material, labour, boundary wall or approval-fee rates you enter — are processed entirely within your own web browser using client-side JavaScript. This data is not transmitted to, stored on, or processed by any server unless you choose to send the optional estimate feedback described below. When you close or refresh this page, your inputs are gone. The \"Download PDF Report\" feature likewise generates your PDF file locally in your browser; no data is uploaded anywhere in the process.</p>\n<h2>Cookies &amp; Local Storage</h2>\n<p>This website does not use cookies or browser storage to track or identify individual users for the purpose of the calculator itself. If this site is displayed with third-party advertising, those advertising providers may independently set cookies or use similar technologies in your browser, as described below.</p>\n<h2>Analytics &amp; Advertising</h2>\n<p>This website may use third-party analytics services to understand aggregate, anonymized traffic patterns (such as number of visitors and popular pages), and may display third-party advertising, including through Google AdSense or similar networks. These third parties may use cookies, web beacons or similar technologies to serve relevant ads and measure ad performance, in accordance with their own privacy policies. You can control or delete cookies through your browser settings, and you can learn more about how Google uses data from sites that use its services at Google's own privacy and terms pages.</p>\n<h2>Third-Party Links</h2>\n<p>This website may contain links to third-party websites. We are not responsible for the privacy practices or content of those external sites.</p>\n<h2>Children's Privacy</h2>\n<p>This website is a general-audience informational and utility tool and is not directed at children. We do not knowingly collect personal information from children.</p>\n<h2>Changes to This Policy</h2>\n<p>We may update this Privacy Policy from time to time to reflect changes in our practices or for legal, operational or regulatory reasons. Any changes will be posted on this page.</p>\n<h2>Contact</h2>\n<p>If you have questions about this Privacy Policy, please use the form on our <a href=\"" . home_url( '/contact-us/' ) . "\">Contact Us</a> page.</p>",
			'excerpt' => 'What this website collects and how your data is used.',
			'front'   => false,
		),
	);
}

/**
 * Activation: create the site's core pages if they don't already exist,
 * set a static front page, register the primary menu with real links, and
 * switch on clean "/post-name/" permalinks. Idempotent — safe to run again
 * (e.g. after a theme update) without creating duplicate pages.
 */
function hcc_create_pages_and_menu() {
	$defs = hcc_page_definitions();
	$ids  = array();

	foreach ( $defs as $key => $def ) {
		$existing = get_page_by_path( $def['slug'] );
		if ( $existing ) {
			// Fresh installs: WordPress creates a *draft* Privacy Policy page itself —
			// publish the theme's policy text into it instead of leaving a 404.
			if ( 'privacy' === $key && 'draft' === $existing->post_status && false !== strpos( $existing->post_content, 'Suggested text' ) ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish', 'post_title' => $def['title'], 'post_content' => $def['content'], 'post_excerpt' => $def['excerpt'] ) );
			}
			$ids[ $key ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post( array(
			'post_title'   => $def['title'],
			'post_name'    => $def['slug'],
			'post_content' => $def['content'],
			'post_excerpt' => $def['excerpt'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( ! is_wp_error( $id ) && $id ) {
			$ids[ $key ] = $id;
		}
	}

	if ( ! empty( $ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
	}

	// SEO-friendly permalinks: /page-name/ instead of ?p=123.
	global $wp_rewrite;
	if ( get_option( 'permalink_structure' ) !== '/%postname%/' ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// Real, crawlable primary navigation menu for internal linking.
	$menu_name = 'Primary Navigation';
	$menu_id   = 0;
	$existing_menu = wp_get_nav_menu_object( $menu_name );
	if ( $existing_menu ) {
		$menu_id = $existing_menu->term_id;
	} else {
		$created = wp_create_nav_menu( $menu_name );
		if ( ! is_wp_error( $created ) ) $menu_id = $created;
	}
	if ( $menu_id ) {
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( empty( $menu_items ) ) {
			$order = array( 'home', 'guides', 'grey-structure', 'about', 'contact', 'privacy', 'disclaimer' );
			$i = 1;
			foreach ( $order as $key ) {
				if ( empty( $ids[ $key ] ) ) continue;
				wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-title'     => ( 'home' === $key ) ? 'Home' : $defs[ $key ]['title'],
					'menu-item-object-id' => $ids[ $key ],
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $i++,
				) );
			}
		}
		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( ! is_array( $locations ) ) $locations = array();
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'hcc_create_pages_and_menu' );
add_action( 'after_switch_theme', function () {
	hcc_ensure_plot_thumbnails();
	hcc_run_v18_migration();
	update_option( 'hcc_pages_synced_version', HCC_VERSION );
}, 20 );

/**
 * SEO AUDIT FIX (2026-09, blog/topical-authority phase): re-uploading an
 * updated theme zip while this theme is ALREADY the active theme does NOT
 * fire 'after_switch_theme' — that action only fires when switching FROM a
 * different theme. Without this, any new page added to
 * hcc_page_definitions() in a theme update (like the 5/7/10 Marla & 1 Kanal
 * guides added in this version) would never actually get created after a
 * routine "Upload Theme" update, silently. This runs the same idempotent
 * page/menu sync on an ordinary wp-admin page load, but only does anything
 * when the stored "pages synced up to version" option is behind
 * HCC_VERSION, so it is a no-op on every admin page load except the one
 * right after an update that changed the page list.
 */
function hcc_maybe_sync_pages() {
	if ( get_option( 'hcc_pages_synced_version' ) === HCC_VERSION ) return;
	hcc_create_pages_and_menu();
	hcc_ensure_plot_thumbnails();
	hcc_ensure_guides_nav_item();
	hcc_run_v18_migration();
	update_option( 'hcc_pages_synced_version', HCC_VERSION );
}
add_action( 'admin_init', 'hcc_maybe_sync_pages' );

/**
 * NAV FIX (2026-09, discoverability phase): hcc_create_pages_and_menu()'s
 * menu-building block only ever runs `if ( empty( $menu_items ) )` — i.e.
 * only the very first time the "Primary Navigation" menu is created. On a
 * site that is already live (menu already has 6 items from an earlier
 * version), simply adding 'guides' to that function's $order array does
 * NOT add a "Guides" link to the existing menu. This runs once per version
 * bump (called from hcc_maybe_sync_pages()) and, if the menu exists but has
 * no item yet pointing at the Guides hub page, inserts one right after
 * "Home" — shifting every later item down by one position — without
 * touching or recreating any existing menu item. Safe to call repeatedly:
 * it is a no-op once the Guides item exists.
 */
function hcc_ensure_guides_nav_item() {
	if ( ! function_exists( 'wp_get_nav_menu_object' ) ) return;
	$menu = wp_get_nav_menu_object( 'Primary Navigation' );
	if ( ! $menu ) return;
	$guides_page = get_page_by_path( 'construction-cost-guides' );
	if ( ! $guides_page ) return;

	$items = wp_get_nav_menu_items( $menu->term_id );
	if ( ! is_array( $items ) ) $items = array();

	$home_position = 0;
	foreach ( $items as $item ) {
		if ( (int) $item->object_id === (int) $guides_page->ID ) return; // already linked
		if ( 'page' === $item->object && (int) $item->object_id === (int) get_option( 'page_on_front' ) ) {
			$home_position = (int) $item->menu_order;
		}
	}

	$max_position = 0;
	foreach ( $items as $item ) {
		$max_position = max( $max_position, (int) $item->menu_order );
	}

	if ( $home_position ) {
		foreach ( $items as $item ) {
			if ( (int) $item->menu_order > $home_position ) {
				wp_update_nav_menu_item( $menu->term_id, $item->ID, array(
					'menu-item-position' => (int) $item->menu_order + 1,
				) );
			}
		}
		$new_position = $home_position + 1;
	} else {
		$new_position = $max_position + 1;
	}

	wp_update_nav_menu_item( $menu->term_id, 0, array(
		'menu-item-title'     => 'Guides',
		'menu-item-object-id' => $guides_page->ID,
		'menu-item-object'    => 'page',
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $new_position,
	) );
}

/**
 * Unique featured image (WordPress "thumbnail") per plot-size guide page —
 * the SAME infographic that is also embedded inline near the top of that
 * page's article content (see hcc_page_definitions()), so the post
 * thumbnail shown in menus/search/social previews always matches what the
 * visitor sees on the page itself. Each image already exists as a bundled
 * theme asset (assets/img/hero-*.png, 1200×630, never cropped since it is
 * only ever displayed at its own aspect ratio); this copies it into the
 * uploads folder once, registers it as a real attachment with unique alt
 * text, and sets it as that page's featured image. Idempotent: a page that
 * already has a featured image (including one an admin replaced by hand)
 * is left alone.
 */
function hcc_plot_thumbnail_defs() {
	return array(
		'3-marla-house-construction-cost' => array(
			'file' => 'hero-3-marla.png',
			'alt'  => '3 Marla plot size and house construction cost infographic for Pakistan: a 675 sq ft plot with an estimated 574 sq ft single storey covered area and an estimated PKR 2,484,925 complete house construction cost',
		),
		'4-marla-house-construction-cost' => array(
			'file' => 'hero-4-marla.png',
			'alt'  => '4 Marla plot size and house construction cost infographic for Pakistan: a 900 sq ft plot with an estimated 765 sq ft single storey covered area and an estimated PKR 3,275,188 complete house construction cost',
		),
		'5-marla-house-construction-cost' => array(
			'file' => 'hero-5-marla.png',
			'alt'  => '5 Marla plot size and house construction cost infographic for Pakistan: a 1,125 sq ft plot with an estimated 956 sq ft single storey covered area and an estimated PKR 4,065,450 complete house construction cost',
		),
		'7-marla-house-construction-cost' => array(
			'file' => 'hero-7-marla.png',
			'alt'  => '7 Marla plot size and house construction cost infographic for Pakistan: a 1,575 sq ft plot with an estimated 1,181 sq ft single storey covered area and an estimated PKR 4,996,388 complete house construction cost',
		),
		'10-marla-house-construction-cost' => array(
			'file' => 'hero-10-marla.png',
			'alt'  => '10 Marla plot size and house construction cost infographic for Pakistan: a 2,250 sq ft plot with an estimated 3,375 sq ft double storey covered area and an estimated PKR 14,346,172 complete house construction cost',
		),
		'1-kanal-house-construction-cost' => array(
			'file' => 'hero-1-kanal.png',
			'alt'  => '1 Kanal plot size and house construction cost infographic for Pakistan: a 4,500 sq ft plot with an estimated 5,850 sq ft double storey covered area and an estimated PKR 24,786,031 complete house construction cost',
		),
		'marla-kanal-standards-pakistan' => array(
			'file' => 'hero-marla-standards.png',
			'alt'  => 'Comparison infographic of Pakistan\'s four Marla standards — 225, 250, 272 and 272.25 square feet — showing the resulting size of 1 Kanal under each standard',
		),
	);
}

function hcc_ensure_plot_thumbnails() {
	if ( ! function_exists( 'wp_insert_attachment' ) || ! function_exists( 'wp_upload_dir' ) ) return;
	foreach ( hcc_plot_thumbnail_defs() as $slug => $def ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) continue;
		if ( get_post_thumbnail_id( $page->ID ) ) continue; // already set — never overwrite a manual change

		$src_path = get_template_directory() . '/assets/img/' . $def['file'];
		if ( ! file_exists( $src_path ) ) continue;

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['path'] ) || empty( $upload_dir['url'] ) ) continue;

		$dest_filename = wp_unique_filename( $upload_dir['path'], $def['file'] );
		$dest_path     = trailingslashit( $upload_dir['path'] ) . $dest_filename;
		if ( ! @copy( $src_path, $dest_path ) ) continue;

		$filetype      = wp_check_filetype( $dest_filename, null );
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( $def['file'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $upload_dir['url'] ) . $dest_filename,
		), $dest_path, $page->ID );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) continue;

		if ( file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $dest_path );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $def['alt'] );
		set_post_thumbnail( $page->ID, $attachment_id );
	}
}

/**
 * SEO AUDIT FIX (2026-09, round 2): this site has Rank Math active, and while
 * Rank Math is active it remains the ONLY thing printing meta description /
 * canonical / robots / OG / Twitter tags (an earlier fix disabled this
 * function entirely to stop it double-printing those tags alongside Rank
 * Math — confirmed live: view-source showed two <meta name="robots"> tags,
 * two canonicals, two descriptions).
 *
 * The gap that fix left: if Rank Math is ever deactivated, breaks, or hits a
 * licensing problem, the site would have ZERO meta descriptions, canonical
 * tags, robots directives or OG/Twitter tags anywhere — a silent, sitewide
 * crawl/CTR regression with no warning. So this function is re-enabled as a
 * SAFETY NET ONLY: it checks for an active SEO plugin first and does nothing
 * at all while one is present, exactly preserving today's no-duplication
 * behaviour. It only starts outputting tags the moment no SEO plugin is
 * active — i.e. exactly when the site would otherwise have nothing.
 */
function hcc_seo_meta_tags() {
	if ( is_admin() ) return;
	// An SEO plugin is active — let it handle all of this, do nothing here.
	if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) return;

	$title = wp_get_document_title();
	$canonical = is_front_page() ? home_url( '/' ) : ( is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ) ) );

	$hcc_key = hcc_page_key();
	$hcc_map = hcc_seo_meta_map();
	if ( $hcc_key && isset( $hcc_map[ $hcc_key ] ) ) {
		$description = $hcc_map[ $hcc_key ]['desc'];
	} elseif ( is_front_page() ) {
		$description = 'Free house construction cost calculator for Pakistan. Estimate grey structure, complete & turnkey cost per sq ft for 5, 7, 10 Marla and 1 Kanal homes, with a PDF report — no sign-up required.';
	} elseif ( is_singular() ) {
		global $post;
		$description = has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
	} else {
		$description = get_bloginfo( 'description' );
	}
	$description = trim( preg_replace( '/\s+/', ' ', (string) $description ) );

	echo "\n" . '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta name="robots" content="' . ( ( is_author() || is_search() || is_category() || is_tag() || is_date() ) ? 'noindex, follow' : 'index, follow' ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( HCC_BRAND ) . '">' . "\n";
	echo '<meta property="og:locale" content="en_PK">' . "\n";
	$og_img = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'full' ) : get_template_directory_uri() . '/assets/img/hero-5-marla.png';
	echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
	echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $og_img ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
}
add_action( 'wp_head', 'hcc_seo_meta_tags', 1 );

/**
 * The only bit of the old function worth keeping standalone — theme-color is
 * not something Rank Math outputs, so there is no duplication risk.
 */
function hcc_misc_meta_tags() {
	if ( is_admin() ) return;
	echo '<meta name="theme-color" content="#1558d6">' . "\n";
}
add_action( 'wp_head', 'hcc_misc_meta_tags', 1 );

/**
 * SITE FAVICON (2026-09, blog/topical-authority phase): bundled directly in
 * the theme's assets/img folder (generated from the same crane/house icon
 * used in the site header logo, on the site's navy brand colour) and output
 * via plain <link> tags in wp_head — this works immediately on theme
 * upload/activation with no manual "upload a Site Icon in Customizer" step,
 * and does not depend on or conflict with Rank Math or the WordPress core
 * Site Icon feature (both can coexist; if a Site Icon is ever also set via
 * Appearance > Customize > Site Identity, these tags simply provide a
 * reliable fallback / are superseded in browsers that prefer the later tag).
 */
function hcc_favicon_tags() {
	if ( is_admin() ) return;
	$img = get_template_directory_uri() . '/assets/img/';
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon.ico' ) . '" sizes="any">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url( $img . 'favicon-16x16.png' ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $img . 'favicon-32x32.png' ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $img . 'favicon-192x192.png' ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $img . 'apple-touch-icon.png' ) . '">' . "\n";
}
add_action( 'wp_head', 'hcc_favicon_tags', 1 );

/**
 * Demote heading levels inside the calculator markup automatically isn't
 * needed — the shortcode only ever emits H2/H3/H4, and every template
 * below renders exactly one H1 (the WordPress page title), keeping each
 * page compliant with the single-H1-per-page SEO requirement.
 */
