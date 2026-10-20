<?php
/**
 * Generic page template — About Us / Privacy Policy / Disclaimer / Grey
 * Structure Cost Calculator and any other plain page. The page title
 * renders as the single H1 on the page (SEO requirement: exactly one H1
 * containing the primary keyword for that page); shortcode output never
 * emits its own H1, so this stays true even for the calculator pages.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main-content">
<div class="static-page">
	<div class="card">
		<?php while ( have_posts() ) : the_post(); ?>
			<h1><?php the_title(); ?></h1>
			<?php if ( function_exists( 'rank_math_the_breadcrumbs' ) ) rank_math_the_breadcrumbs(); ?>
			<?php the_content(); ?>
		<?php endwhile; ?>
	</div>
</div>
</main>
<?php get_footer(); ?>
