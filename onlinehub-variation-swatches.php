<?php
/**
 * Plugin Name: Onlinehub Variation Swatches
 * Description: Adds color swatches (with an admin color picker) and button-style variation selectors to WooCommerce variable products, automatically switching to a clean dropdown for attributes with many options.
 * Version: 0.4.1
 * Author: Muhammad Saqib
 * Author URI: https://github.com/prodevsaqib
 * Text Domain: onlinehub-variation-swatches
 * Requires Plugins: woocommerce
 * WC requires at least: 4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OHVS_VERSION', '0.4.1' );
define( 'OHVS_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHVS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bail out gracefully if WooCommerce isn't active.
 */
function ohvs_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Onlinehub Variation Swatches requires WooCommerce to be installed and active.', 'onlinehub-variation-swatches' ) . '</p></div>';
}

function ohvs_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ohvs_woocommerce_missing_notice' );
		return;
	}

	require_once OHVS_PATH . 'includes/class-ohvs-settings.php';
	require_once OHVS_PATH . 'includes/class-ohvs-swatches.php';

	OHVS_Swatches::instance();

	if ( is_admin() ) {
		require_once OHVS_PATH . 'includes/class-ohvs-admin.php';
		require_once OHVS_PATH . 'includes/class-ohvs-settings-page.php';

		OHVS_Admin::instance();
		OHVS_Settings_Page::instance();
	}
}
add_action( 'plugins_loaded', 'ohvs_init' );
