<?php
/**
 * Fallback template (blog listing / search / archives). This theme is built
 * around static pages rather than posts, so this stays minimal.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main>
<div class="static-page">
	<div class="card">
		<?php if ( is_404() ) : ?>
			<h1><?php esc_html_e( '404 — Page Not Found', 'house-cost-calculator' ); ?></h1>
			<p><?php esc_html_e( 'Sorry, the page you were looking for could not be found. It may have been moved or no longer exists.', 'house-cost-calculator' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the House Construction Cost Calculator', 'house-cost-calculator' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'house-cost-calculator' ); ?></a>
			</p>
		<?php else : ?>
			<h1><?php echo is_search() ? 'Search Results' : esc_html__( 'House Construction Cost Calculator', 'house-cost-calculator' ); ?></h1>
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				<?php endwhile; ?>
			<?php else : ?>
				<p>Nothing found. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Return to the House Construction Cost Calculator.</a></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
</main>
<?php get_footer(); ?>
