<?php
/**
 * Plugin Name:       ConstructionCostPK Fixes
 * Plugin URI:        https://constructioncostpk.com/
 * Description:       Site-specific bug fixes and tweaks for constructioncostpk.com. Deployed from GitHub via Hostinger Git.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ConstructionCostPK
 * License:           GPL-2.0-or-later
 * Text Domain:       constructioncostpk-fixes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCPK_FIXES_VERSION', '0.1.0' );
define( 'CCPK_FIXES_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCPK_FIXES_URL', plugin_dir_url( __FILE__ ) );

/*
 * Each fix lives in its own file under includes/ so it can be reviewed,
 * toggled or removed on its own. Files are loaded in alphabetical order.
 */
foreach ( glob( CCPK_FIXES_DIR . 'includes/*.php' ) as $ccpk_fix_file ) {
	require_once $ccpk_fix_file;
}
unset( $ccpk_fix_file );
