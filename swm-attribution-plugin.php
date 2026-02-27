<?php
/**
 * Plugin Name: Simple Web Marketing Attribution
 * Description: A plugin to capture and store attribution data including UTM parameters and referrer information.
 * Version: 1.2.3
 * Author: Simple Web Marketing
 * Author URI: https://www.simplewebmarketing.com
 * License: GPL2
 *
 * @package Simple_Web_Marketing_Attribution
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'SWMA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

define( 'SWMA_VERSION', '1.2.3' );



/**
 * Enqueue the attribution capture script on all frontend pages.
 */
function swma_enqueue_scripts() {
	wp_register_script(
		'swma-hubspot-forms',
		'https://js.hsforms.net/forms/embed/v2.js',
		array(),
		'2.0',
		true
	);

	// Enqueue Privacy Engine (must load before capture script).
	wp_enqueue_script(
		'swma-privacy',
		plugin_dir_url( __FILE__ ) . 'src/js/swma-privacy.js',
		array(),
		SWMA_VERSION,
		true
	);

	wp_enqueue_script(
		'swma-capture',
		plugin_dir_url( __FILE__ ) . 'src/js/swm-attribution-capture-script.js',
		array( 'jquery', 'swma-privacy' ),
		SWMA_VERSION,
		true
	);

	// Get the thank you message setting.
	$options                   = swma_get_attribution_settings();
	$thank_you_message         = $options['global']['thank_you_message'];
	$thank_you_url             = $options['global']['redirect_url'];
	$form_overrides            = $options['overrides'];
	$respect_marketing_consent = isset( $options['respect_marketing_consent'] ) ? (bool) $options['respect_marketing_consent'] : false;
	$respect_gpc               = isset( $options['respect_gpc'] ) ? (bool) $options['respect_gpc'] : true;
	$debug_mode                = isset( $options['debug_mode'] ) ? (bool) $options['debug_mode'] : false;

	// Localize the script with data for the HubSpot proxy.
	wp_localize_script(
		'swma-capture',
		'swma_ajax',
		array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'swma_hubspot_proxy_nonce' ),
			'thank_you_message' => $thank_you_message,
			'thank_you_url'     => $thank_you_url,
			'form_overrides'    => $form_overrides,
		)
	);

	// Localize settings for privacy.
	wp_add_inline_script(
		'swma-privacy',
		'window.swma_settings = ' . wp_json_encode(
			array(
				'respect_marketing_consent' => $respect_marketing_consent,
				'respect_gpc'               => $respect_gpc,
				'debug_mode'                => $debug_mode,
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'swma_enqueue_scripts' );

add_action( 'wp_head', 'swma_add_salesforce_meta_tag' );
/**
 * Add meta tag required by Salesforce Web-to-Lead.
 */
function swma_add_salesforce_meta_tag() {
	$options = swma_get_attribution_settings();
	if ( isset( $options['enable_salesforce'] ) && $options['enable_salesforce'] ) {
		echo '<meta http-equiv="Content-type" content="text/html; charset=UTF-8">' . "\n";
	}
}

// Include other PHP files.
require_once plugin_dir_path( __FILE__ ) . 'src/php/db-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'src/php/admin-dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'src/php/admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'src/php/hubspot-proxy.php';
require_once plugin_dir_path( __FILE__ ) . 'src/php/conversion-tracker.php';
require_once plugin_dir_path( __FILE__ ) . 'src/php/attribution-library.php';

// Register activation hook.
register_activation_hook( __FILE__, 'swma_create_table' );

/**
 * Add admin menu for Attribution dashboard.
 */
function swma_add_admin_menu() {
	add_menu_page(
		__( 'Simple Attribution', 'simple-web-marketing-attribution' ),
		__( 'Simple Attribution', 'simple-web-marketing-attribution' ),
		'read',
		'swma-dashboard',
		'swma_admin_dashboard_page',
		'dashicons-chart-area',
		80
	);

	// Add explicit Dashboard submenu.
	add_submenu_page(
		'swma-dashboard',
		__( 'Dashboard', 'simple-web-marketing-attribution' ),
		__( 'Dashboard', 'simple-web-marketing-attribution' ),
		'read',
		'swma-dashboard',
		'swma_admin_dashboard_page'
	);

	// Add Link Builder submenu.
	add_submenu_page(
		'swma-dashboard',
		__( 'Link Builder', 'simple-web-marketing-attribution' ),
		__( 'Link Builder', 'simple-web-marketing-attribution' ),
		'read',
		'swma-link-builder',
		'swma_admin_dashboard_page'
	);

	// Add Link Library submenu.
	add_submenu_page(
		'swma-dashboard',
		__( 'Link Library', 'simple-web-marketing-attribution' ),
		__( 'Link Library', 'simple-web-marketing-attribution' ),
		'read',
		'swma-link-library',
		'swma_admin_dashboard_page'
	);

	// Add Integrations submenu.
	add_submenu_page(
		'swma-dashboard',
		__( 'Integrations', 'simple-web-marketing-attribution' ),
		__( 'Integrations', 'simple-web-marketing-attribution' ),
		'read',
		'swma-integrations',
		'swma_admin_dashboard_page'
	);

	// Add Settings submenu.
	add_submenu_page(
		'swma-dashboard',
		__( 'Settings', 'simple-web-marketing-attribution' ),
		__( 'Settings', 'simple-web-marketing-attribution' ),
		'manage_options',
		'swma-settings',
		'swma_admin_dashboard_page'
	);
}
add_action( 'admin_menu', 'swma_add_admin_menu' );
