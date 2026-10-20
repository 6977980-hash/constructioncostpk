<?php
/**
 * The header for the House Cost Calculator theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta name="google-site-verification" content="rCL6rH0pkHQzfpvG-2LCHpqUVLPX_vlRhv0aA4TNFx4" />
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="top-accent-bar" aria-hidden="true"></div>
<a href="#main-content" class="skip-link">Skip to content</a>

<?php hcc_icon_sprite(); ?>

<div class="site-header">
	<div class="site-header-inner wrap">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo" aria-label="ConstructionCostPK — House Construction Cost Calculator Pakistan home">
			<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-crane"></use></svg>
			<span class="site-logo-text">ConstructionCost<span class="brand-pk">PK</span></span>
			<span class="site-logo-text-short">ConstructionCost<span class="brand-pk">PK</span></span>
		</a>
		<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu">
			<svg class="icon-open" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-menu"></use></svg>
			<svg class="icon-close" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-close"></use></svg>
		</button>
		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => 'nav',
				'container_id'   => 'primary-nav',
				'container_aria_label' => 'Site pages',
				'menu_class'     => '',
				'depth'          => 1,
				'fallback_cb'    => 'hcc_fallback_menu',
			) );
		} else {
			echo '<nav id="primary-nav" aria-label="Site pages">';
			hcc_fallback_menu();
			echo '</nav>';
		}
		?>
	</div>
</div>
