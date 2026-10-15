<?php
/**
 * Front page template — the calculator's main landing page. Renders the
 * hero (with the page's title as the single H1 on the page, containing the
 * primary keyword) followed by the [house_cost_calculator] shortcode output
 * (calculator + full guide + FAQ + schema), which never emits its own H1.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
?>
<header class="hero" style="border:0">
	<span class="hero-badge"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><use href="#icon-crane"></use></svg> Pakistan Construction Cost Guide 2026</span>
	<h1><?php the_title(); ?></h1>
	<?php if ( function_exists( 'rank_math_the_breadcrumbs' ) ) rank_math_the_breadcrumbs(); ?>
	<?php
	// O3.1 — 40–60 word "Quick answer" directly under the H1 (figures from the calculator engine).
	$hcc_c = hcc_rate_per_sqft( 'complete' ); $hcc_g = hcc_rate_per_sqft( 'grey' ); $hcc_5 = hcc_estimate( 1125, 1 );
	?>
	<div class="quick-answer-box home-qa"><p><strong>Quick answer:</strong> in 2026 a complete house in Pakistan costs about PKR 3,000–5,500 per sq ft and grey structure about PKR 1,800–3,000 per sq ft. At standard quality in Lahore our calculator gives <?php echo esc_html( hcc_format_pkr( $hcc_c ) ); ?>/sq ft complete and <?php echo esc_html( hcc_format_pkr( $hcc_g ) ); ?>/sq ft grey — about PKR <?php echo esc_html( hcc_lakh_crore( $hcc_5['grandTotal'] ) ); ?> for a 5 Marla house.</p>
	<p class="page-meta"><span><strong>Rates last updated:</strong> <time datetime="<?php echo esc_attr( hcc_rates_date() ); ?>"><?php echo esc_html( hcc_rates_date_human() ); ?></time></span><span><strong>By</strong> <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php echo esc_html( hcc_author_name() ); ?></a></span></p></div>
	<p class="sub">Free house construction cost calculator and building cost estimator for Pakistan — estimate grey structure, complete house or turnkey construction cost per sq ft for 5 Marla, 7 Marla, 10 Marla, 1 Kanal or any custom plot size. Get an itemized material quantity and labour cost breakdown in seconds, with no sign-up.</p>
	<div class="trust-row">
		<span class="trust-pill">100% Free</span>
		<span class="trust-pill">Instant Results</span>
		<span class="trust-pill">No Sign-up</span>
		<span class="trust-pill">Downloadable PDF Report</span>
		<span class="trust-pill">Editable Local Rates</span>
	</div>
</header>

<main>
<?php the_content(); ?>
</main>
<?php
endwhile;
get_footer();
