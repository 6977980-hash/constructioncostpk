<?php
/**
 * The footer for the House Cost Calculator theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$hcc_home = home_url( '/' );
$hcc_grey = get_page_by_path( 'grey-structure-cost-calculator' );
$hcc_about = get_page_by_path( 'about-us' );
$hcc_privacy = get_page_by_path( 'privacy-policy' );
$hcc_disclaimer = get_page_by_path( 'disclaimer' );
$hcc_contact = get_page_by_path( 'contact-us' );
$hcc_5marla = get_page_by_path( '5-marla-house-construction-cost' );
$hcc_7marla = get_page_by_path( '7-marla-house-construction-cost' );
$hcc_10marla = get_page_by_path( '10-marla-house-construction-cost' );
$hcc_1kanal = get_page_by_path( '1-kanal-house-construction-cost' );
$hcc_marla_std = get_page_by_path( 'marla-kanal-standards-pakistan' );
$hcc_guides = get_page_by_path( 'construction-cost-guides' );
$hcc_fl = function ( $slug, $label ) { if ( hcc_page_exists( $slug ) ) echo '<li><a href="' . esc_url( hcc_page_url( $slug ) ) . '">' . esc_html( $label ) . '</a></li>'; };
?>
<button type="button" id="float-calc-btn" aria-label="Jump to the construction cost calculator">
	<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-calc"></use></svg>
	<span class="fab-label">Calculate</span>
</button>

<footer class="site-footer">
	<div class="footer-top">
		<div>
			<div class="footer-brand-row">
				<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use href="#icon-crane"></use></svg>
				<strong>ConstructionCostPK</strong>
			</div>
			<p>ConstructionCostPK is a free, independent house construction cost calculator and building cost estimator for Pakistan — covering grey structure, complete house and turnkey construction cost per sq ft for any plot from 3 Marla to 2 Kanal.</p>
			<p>All calculations run in your browser using editable, typical 2026 Pakistani market rate assumptions. Nothing you enter is sent to a server unless you choose to send optional feedback.</p>
		</div>
		<div>
			<h3>Quick Links</h3>
			<ul>
				<li><a href="<?php echo esc_url( $hcc_home ); ?>">Calculator (Home)</a></li>
				<?php if ( $hcc_grey ) : ?><li><a href="<?php echo esc_url( get_permalink( $hcc_grey ) ); ?>">Grey Structure Calculator</a></li><?php endif; ?>
				<li><a href="<?php echo esc_url( $hcc_home . '#guide' ); ?>">Construction Cost Guide</a></li>
				<li><a href="<?php echo esc_url( $hcc_home . '#faq-section' ); ?>">FAQs</a></li>
				<?php if ( $hcc_about ) : ?><li><a href="<?php echo esc_url( get_permalink( $hcc_about ) ); ?>">About Us</a></li><?php endif; ?>
				<?php if ( $hcc_contact ) : ?><li><a href="<?php echo esc_url( get_permalink( $hcc_contact ) ); ?>">Contact Us</a></li><?php endif; ?>
			</ul>
		</div>
		<div>
			<h3><?php if ( $hcc_guides ) : ?><a href="<?php echo esc_url( get_permalink( $hcc_guides ) ); ?>">Cost Guides</a><?php else : ?>Cost Guides<?php endif; ?></h3>
			<ul>
				<?php foreach ( hcc_plot_sizes() as $hcc_k => $hcc_s ) $hcc_fl( $hcc_k . '-house-construction-cost', $hcc_s[0] . ' House Cost' ); ?>
				<?php $hcc_fl( 'double-storey-house-construction-cost', 'Double Storey House Cost' ); $hcc_fl( 'marla-kanal-standards-pakistan', 'Marla & Kanal Standards' ); ?>
			</ul>
		</div>
		<div>
			<h3>Cities &amp; Materials</h3>
			<ul>
				<?php $hcc_fl( 'construction-cost-by-city', 'Cost by City' ); $hcc_fl( 'construction-cost-lahore', 'Lahore' ); $hcc_fl( 'construction-cost-karachi', 'Karachi' ); $hcc_fl( 'construction-cost-islamabad', 'Islamabad' ); $hcc_fl( '120-sq-yard-house-construction-cost-karachi', 'Karachi 120 Sq Yd' ); $hcc_fl( 'construction-material-rates', 'Material Rates' ); $hcc_fl( 'lanter-roof-slab-cost-pakistan', 'Lanter (Roof Slab) Cost' ); $hcc_fl( 'pakistan-construction-cost-index', 'Cost Index' ); ?>
			</ul>
			<h3 style="margin-top:18px">Policies</h3>
			<ul>
				<?php if ( $hcc_disclaimer ) : ?><li><a href="<?php echo esc_url( get_permalink( $hcc_disclaimer ) ); ?>">Disclaimer</a></li><?php endif; ?>
				<?php if ( $hcc_privacy ) : ?><li><a href="<?php echo esc_url( get_permalink( $hcc_privacy ) ); ?>">Privacy Policy</a></li><?php endif; ?>
				<?php $hcc_fl( 'methodology', 'Methodology' ); $hcc_fl( 'embed-construction-cost-calculator', 'Embed the Calculator' ); ?>
				<?php if ( hcc_analytics_ids()['ga4'] || hcc_analytics_ids()['clarity'] ) : ?><li><a href="#" data-cookie-settings>Cookie settings</a></li><?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="footer-bottom">
		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> ConstructionCostPK — House Construction Cost Calculator Pakistan. Independent informational tool — not a construction company, contractor or material supplier. Estimates only; always confirm with a licensed contractor before finalizing your budget.</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
