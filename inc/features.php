<?php
/**
 * v1.8.0 — interactive features:
 *   E4  contact form (no plugin needed; mails the site owner)
 *   E6  "Was this estimate close?" feedback, stored privately in wp-admin
 *   T7.2 GA4 / Microsoft Clarity with calculator + PDF events
 *   T7.5 cookie consent (Google Consent Mode v2; denied by default in EEA/UK/CH)
 *   X1  embeddable calculator widget (/?hcc_embed=calculator)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rate-limit key for the visitor. Behind the Hostinger CDN every request
 * arrives from a few edge addresses, so REMOTE_ADDR alone put all visitors
 * in one shared bucket; the visitor's own address is the first entry of
 * X-Forwarded-For. Both are combined (and only hashed, never stored).
 */
function hcc_client_ip_key() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$fwd = '';
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $h ) {
		if ( empty( $_SERVER[ $h ] ) ) continue;
		$first = trim( explode( ',', (string) $_SERVER[ $h ] )[0] );
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) { $fwd = $first; break; }
	}
	return $remote . '|' . $fwd;
}

/* ==========================================================================
 * E4 — Contact form
 * ======================================================================== */
function hcc_contact_form_html() {
	$status = isset( $_GET['hcc_sent'] ) ? sanitize_key( $_GET['hcc_sent'] ) : '';
	$h = '';
	if ( 'ok' === $status ) $h .= '<div class="notice-box ok" role="status">Thank you — your message has been sent. We usually reply within a few days.</div>';
	if ( 'err' === $status ) $h .= '<div class="notice-box err" role="alert">Sorry, your message could not be sent. Please check the fields and try again.</div>';
	$h .= '<form class="hcc-contact-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. '<input type="hidden" name="action" value="hcc_contact">'
		. wp_nonce_field( 'hcc_contact', 'hcc_contact_nonce', true, false )
		. '<input type="hidden" name="hcc_ts" value="' . esc_attr( time() ) . '">'
		. '<div class="hp-field" aria-hidden="true"><label>Leave this empty <input type="text" name="hcc_website" tabindex="-1" autocomplete="off"></label></div>'
		. '<div class="field"><label for="hcc-c-name">Your name</label><input id="hcc-c-name" name="hcc_name" type="text" required maxlength="100" autocomplete="name"></div>'
		. '<div class="field"><label for="hcc-c-email">Your email</label><input id="hcc-c-email" name="hcc_email" type="email" required maxlength="150" autocomplete="email"></div>'
		. '<div class="field"><label for="hcc-c-topic">Topic</label><select id="hcc-c-topic" name="hcc_topic"><option>Outdated rate</option><option>Question about an estimate</option><option>Suggest a city or guide</option><option>Something is broken</option><option>Other</option></select></div>'
		. '<div class="field"><label for="hcc-c-msg">Message</label><textarea id="hcc-c-msg" name="hcc_message" rows="6" required maxlength="5000"></textarea></div>'
		. '<p class="hint">We only use these details to reply to you — see our <a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">privacy policy</a>.</p>'
		. '<button type="submit" class="btn btn-primary">Send message</button></form>';
	return $h;
}
add_shortcode( 'hcc_contact_form', 'hcc_contact_form_html' );

function hcc_handle_contact() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/contact-us/' );
	$back = remove_query_arg( 'hcc_sent', $back );
	$ok = isset( $_POST['hcc_contact_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hcc_contact_nonce'] ) ), 'hcc_contact' );
	$ts = isset( $_POST['hcc_ts'] ) ? (int) $_POST['hcc_ts'] : 0;
	$bot = ! empty( $_POST['hcc_website'] ) || ( time() - $ts ) < 3;
	$name = isset( $_POST['hcc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hcc_name'] ) ) : '';
	$email = isset( $_POST['hcc_email'] ) ? sanitize_email( wp_unslash( $_POST['hcc_email'] ) ) : '';
	$topic = isset( $_POST['hcc_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['hcc_topic'] ) ) : '';
	$msg = isset( $_POST['hcc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hcc_message'] ) ) : '';
	if ( $bot ) { wp_safe_redirect( add_query_arg( 'hcc_sent', 'ok', $back ) ); exit; } // silently drop bots
	if ( ! $ok || ! $name || ! is_email( $email ) || strlen( $msg ) < 5 ) { wp_safe_redirect( add_query_arg( 'hcc_sent', 'err', $back ) . '#hcc-c-name' ); exit; }
	// simple rate limit: 5 messages / hour / IP (IP is hashed, never stored raw)
	$rl = 'hcc_rl_' . md5( wp_salt() . hcc_client_ip_key() );
	$n = (int) get_transient( $rl );
	if ( $n >= 5 ) { wp_safe_redirect( add_query_arg( 'hcc_sent', 'err', $back ) ); exit; }
	set_transient( $rl, $n + 1, HOUR_IN_SECONDS );

	$to = hcc_opt( 'contact_email' );
	$to = ( $to && is_email( $to ) ) ? $to : get_option( 'admin_email' );
	$sent = wp_mail( $to, '[ConstructionCostPK] ' . $topic . ' — ' . $name, "Name: {$name}\nEmail: {$email}\nTopic: {$topic}\n\n{$msg}\n\n— sent from " . home_url( '/contact-us/' ), array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	wp_safe_redirect( add_query_arg( 'hcc_sent', $sent ? 'ok' : 'err', $back ) . '#hcc-c-name' );
	exit;
}
add_action( 'admin_post_nopriv_hcc_contact', 'hcc_handle_contact' );
add_action( 'admin_post_hcc_contact', 'hcc_handle_contact' );

/* ==========================================================================
 * E6 — estimate feedback
 * ======================================================================== */
add_action( 'init', function () {
	register_post_type( 'hcc_feedback', array(
		'label' => 'Estimate Feedback', 'public' => false, 'show_ui' => true, 'show_in_menu' => true,
		'menu_icon' => 'dashicons-feedback', 'supports' => array( 'title' ), 'capability_type' => 'post',
		'capabilities' => array( 'create_posts' => 'do_not_allow' ), 'map_meta_cap' => true, 'show_in_rest' => false,
	) );
} );

function hcc_feedback_widget_html() {
	return '<div class="feedback-widget" id="hcc-feedback" data-endpoint="' . esc_url( rest_url( 'hcc/v1/feedback' ) ) . '">'
		. '<p class="fb-q"><strong>Was this estimate close to your actual cost?</strong> <span class="hint">(optional — helps us publish real-world accuracy data)</span></p>'
		. '<div class="fb-btns"><button type="button" class="btn btn-secondary" data-fb="close">Yes, close</button><button type="button" class="btn btn-secondary" data-fb="lower">Actual was lower</button><button type="button" class="btn btn-secondary" data-fb="higher">Actual was higher</button><button type="button" class="btn btn-secondary" data-fb="not_built">Haven\'t built yet</button></div>'
		. '<div class="fb-actual" hidden><label for="fb-actual-input">Your actual total cost (PKR, optional)</label><div class="row-inline"><input type="number" id="fb-actual-input" min="0" step="1000" inputmode="numeric"><button type="button" class="btn btn-primary" data-fb-send>Send</button></div></div>'
		. '<p class="fb-thanks" hidden role="status">Thank you — your feedback was recorded anonymously.</p></div>';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'hcc/v1', '/feedback', array(
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => function ( WP_REST_Request $r ) {
			$rl = 'hcc_fb_' . md5( wp_salt() . hcc_client_ip_key() );
			$n = (int) get_transient( $rl );
			if ( $n >= 10 ) return new WP_REST_Response( array( 'ok' => false ), 429 );
			set_transient( $rl, $n + 1, HOUR_IN_SECONDS );
			$rating = sanitize_key( (string) $r->get_param( 'rating' ) );
			if ( ! in_array( $rating, array( 'close', 'lower', 'higher', 'not_built' ), true ) ) return new WP_REST_Response( array( 'ok' => false ), 400 );
			$fields = array(
				'page' => esc_url_raw( (string) $r->get_param( 'page' ) ),
				'city' => sanitize_key( (string) $r->get_param( 'city' ) ),
				'type' => sanitize_key( (string) $r->get_param( 'type' ) ),
				'quality' => sanitize_key( (string) $r->get_param( 'quality' ) ),
				'covered' => absint( $r->get_param( 'covered' ) ),
				'estimate' => absint( $r->get_param( 'estimate' ) ),
				'actual' => absint( $r->get_param( 'actual' ) ),
			);
			if ( $fields['page'] && 0 !== strpos( $fields['page'], home_url() ) ) $fields['page'] = '';
			$id = wp_insert_post( array(
				'post_type' => 'hcc_feedback', 'post_status' => 'private',
				'post_title' => sprintf( '%s — %s, %s, %s sq ft', $rating, $fields['city'], $fields['type'], number_format( $fields['covered'] ) ),
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_hcc_rating', $rating );
				foreach ( $fields as $k => $v ) update_post_meta( $id, '_hcc_' . $k, $v );
			}
			return new WP_REST_Response( array( 'ok' => (bool) $id ), 200 );
		},
	) );
} );
add_filter( 'manage_hcc_feedback_posts_columns', function ( $c ) {
	return array( 'cb' => $c['cb'], 'title' => 'Feedback', 'hcc_est' => 'Estimate (PKR)', 'hcc_act' => 'Actual (PKR)', 'hcc_page' => 'Page', 'date' => 'Date' );
} );
add_action( 'manage_hcc_feedback_posts_custom_column', function ( $col, $id ) {
	if ( 'hcc_est' === $col ) echo esc_html( number_format( (int) get_post_meta( $id, '_hcc_estimate', true ) ) );
	if ( 'hcc_act' === $col ) { $a = (int) get_post_meta( $id, '_hcc_actual', true ); echo $a ? esc_html( number_format( $a ) ) : '—'; }
	if ( 'hcc_page' === $col ) echo esc_html( wp_parse_url( (string) get_post_meta( $id, '_hcc_page', true ), PHP_URL_PATH ) );
}, 10, 2 );

/* ==========================================================================
 * T7.2 / T7.5 — analytics behind a cookie-consent notice.
 * GA4 uses the site's own ID unless another is entered in the Customizer.
 * Consent Mode v2 defaults to "denied" in the EEA, UK and Switzerland and to
 * "granted" elsewhere; the consent bar lets any visitor decline.
 * ======================================================================== */
define( 'HCC_GA4_DEFAULT', 'G-SZFCLBZJ9S' );
function hcc_analytics_ids() {
	$ga = strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', (string) ( hcc_opt( 'ga4_id' ) ?: HCC_GA4_DEFAULT ) ) );
	$cl = preg_replace( '/[^a-z0-9]/i', '', (string) hcc_opt( 'clarity_id' ) );
	return array( 'ga4' => ( 0 === strpos( $ga, 'G-' ) ) ? $ga : '', 'clarity' => $cl );
}
add_action( 'wp_head', function () {
	if ( is_admin() || get_query_var( 'hcc_embed' ) || current_user_can( 'edit_posts' ) ) return;
	$ids = hcc_analytics_ids();
	if ( ! $ids['ga4'] && ! $ids['clarity'] ) return;
	$eea = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH' );
	echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}";
	echo "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',region:" . wp_json_encode( $eea ) . ",wait_for_update:500});";
	echo "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'granted'});";
	echo "try{var c=localStorage.getItem('hcc_consent');if(c==='granted'||c==='denied'){gtag('consent','update',{analytics_storage:c});}}catch(e){}";
	if ( $ids['ga4'] ) echo "gtag('js',new Date());gtag('config'," . wp_json_encode( $ids['ga4'] ) . ");";
	echo "</script>\n";
	if ( $ids['ga4'] ) echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ids['ga4'] ) . '"></script>' . "\n";
}, 3 );

add_action( 'wp_footer', function () {
	if ( is_admin() || get_query_var( 'hcc_embed' ) || current_user_can( 'edit_posts' ) ) return;
	$ids = hcc_analytics_ids();
	if ( ! $ids['ga4'] && ! $ids['clarity'] ) return;
	?>
	<div id="hcc-consent" class="consent-bar" role="dialog" aria-live="polite" aria-label="Cookie consent" hidden>
		<p>We use optional analytics cookies to see which calculators help people most. Your calculator inputs are never collected. <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy policy</a></p>
		<div class="consent-btns"><button type="button" class="btn btn-secondary" data-consent="denied">Decline</button><button type="button" class="btn btn-primary" data-consent="granted">Accept</button></div>
	</div>
	<script>
	(function(){var bar=document.getElementById('hcc-consent');if(!bar)return;var clarity=<?php echo wp_json_encode( $ids['clarity'] ); ?>;
	function get(){try{return localStorage.getItem('hcc_consent');}catch(e){return null;}}
	function set(v){try{localStorage.setItem('hcc_consent',v);}catch(e){}}
	function loadClarity(){if(!clarity||window.clarity)return;(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",clarity);}
	function apply(v){if(window.gtag)gtag('consent','update',{analytics_storage:v==='granted'?'granted':'denied'});if(v==='granted')loadClarity();}
	var cur=get();if(cur){apply(cur);}else{bar.hidden=false;}
	bar.addEventListener('click',function(e){var b=e.target.closest('[data-consent]');if(!b)return;set(b.dataset.consent);apply(b.dataset.consent);bar.hidden=true;});
	document.addEventListener('click',function(e){if(e.target.closest('[data-cookie-settings]')){e.preventDefault();bar.hidden=false;}});
	})();
	</script>
	<?php
}, 50 );

/* ==========================================================================
 * X1 — embeddable widget: /?hcc_embed=calculator renders the calculator on a
 * bare page (noindex) with a "Powered by ConstructionCostPK" credit link.
 * ======================================================================== */
add_action( 'template_redirect', function () {
	if ( 'calculator' !== get_query_var( 'hcc_embed' ) ) return;
	header( 'X-Robots-Tag: noindex, follow' );
	header_remove( 'X-Frame-Options' );
	$css = get_stylesheet_uri() . '?ver=' . HCC_VERSION;
	$js = get_template_directory_uri() . '/assets/js/calculator.js?ver=' . HCC_VERSION;
	?><!DOCTYPE html><html lang="en-PK"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow"><title>House Construction Cost Calculator — ConstructionCostPK</title><link rel="canonical" href="<?php echo esc_url( home_url( '/' ) ); ?>"><link rel="stylesheet" href="<?php echo esc_url( $css ); ?>"><style>body{background:#f4f6fa}.embed-wrap{max-width:960px;margin:0 auto;padding:12px}.embed-credit{text-align:center;font-size:13px;margin:14px 0 6px}</style></head><body class="hcc-embed"><div class="embed-wrap">
	<?php echo do_shortcode( '[house_cost_calculator mode="calculator_only" variant="embed"]' ); // phpcs:ignore ?>
	<p class="embed-credit">Powered by <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">ConstructionCostPK — House Construction Cost Calculator Pakistan</a></p>
	</div><script>window.hccCalc=<?php echo wp_json_encode( hcc_calc_js_config() ); ?>;</script><script src="<?php echo esc_url( $js ); ?>"></script></body></html><?php
	exit;
} );

function hcc_calc_js_config() {
	return array(
		'jspdf' => get_template_directory_uri() . '/assets/js/vendor/jspdf.umd.min.js?ver=2.5.1',
		'brand' => HCC_BRAND,
		'site' => home_url( '/' ),
		'rates' => hcc_default_calc_rates(),
	);
}
