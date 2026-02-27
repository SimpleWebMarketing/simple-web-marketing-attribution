<?php
/**
 * Admin Dashboard functionality for the Simple Web Marketing Attribution.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enqueue scripts for the admin dashboard.
 *
 * @param string $hook The current admin page.
 */
function swma_dashboard_assets( $hook ) {
	// Check if we are on one of our plugin pages.
	$swma_pages = array(
		'toplevel_page_swma-dashboard',
		'simple-attribution_page_swma-dashboard',
		'simple-attribution_page_swma-link-builder',
		'simple-attribution_page_swma-link-library',
		'simple-attribution_page_swma-integrations',
		'simple-attribution_page_swma-settings',
	);

	if ( ! in_array( $hook, $swma_pages, true ) ) {
		return;
	}

	// Use the global constant for the plugin root URL.
	wp_enqueue_style(
		'swma-dashboard-css',
		SWMA_PLUGIN_URL . 'src/js/dashboard/dashboard.css',
		array(),
		SWMA_VERSION
	);

	wp_enqueue_script(
		'swma-dashboard-js',
		SWMA_PLUGIN_URL . 'src/js/dashboard/dashboard.js',
		array( 'jquery', 'wp-element' ),
		SWMA_VERSION,
		true
	);

	$options = function_exists( 'swma_get_attribution_settings' ) ? swma_get_attribution_settings() : array();
	$debug   = isset( $options['debug_mode'] ) ? (bool) $options['debug_mode'] : false;

	wp_localize_script(
		'swma-dashboard-js',
		'swmaDashboard',
		array(
			'root'    => esc_url_raw( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'siteUrl' => esc_url_raw( get_site_url() ), // Add site URL.
			'debug'   => $debug,
			'hook'    => $hook,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'swma_dashboard_assets' );

/**
 * Fix admin menu highlighting for submenus with query parameters.
 *
 * @param string $submenu_file The current submenu file.
 * @return string The modified submenu file.
 */
function swma_fix_admin_menu_highlighting( $submenu_file ) {
	global $parent_file;

	if ( 'swma-dashboard' === $parent_file ) {
		if ( isset( $_GET['view'] ) ) {
			$view = sanitize_text_field( wp_unslash( $_GET['view'] ) );
			return 'swma-dashboard&view=' . $view;
		}
	}

	return $submenu_file;
}
add_filter( 'submenu_file', 'swma_fix_admin_menu_highlighting' );

/**
 * Render the admin dashboard page with tabs.
 */
function swma_admin_dashboard_page() {
	?>
	<div class="wrap swma-admin-dashboard-wrapper">
		<div id="swma-dashboard-root">
			<!-- React App Mounts Here -->
			<p class="swma-loading-text"><?php esc_html_e( 'Loading dashboard...', 'simple-web-marketing-attribution' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Render the Getting Started tab content.
 */
function swma_getting_started_tab() {
	?>
	<h2><?php esc_html_e( 'Getting Started with Form Attribution', 'simple-web-marketing-attribution' ); ?></h2>
	<p><?php esc_html_e( 'This guide will help you configure your forms to capture attribution data.', 'simple-web-marketing-attribution' ); ?></p>

	<h3><?php esc_html_e( 'How to Add Hidden Fields', 'simple-web-marketing-attribution' ); ?></h3>
	<p>
		<?php echo wp_kses_post( __( 'To capture a specific UTM parameter, create a hidden text or textarea input field in your form with one of the following attributes matching the UTM parameter key (e.g., <code>utm_campaign</code>):', 'simple-web-marketing-attribution' ) ); ?>
	</p>
	<ul>
		<li><strong><?php esc_html_e( 'Name:', 'simple-web-marketing-attribution' ); ?></strong> <code>&lt;input type="hidden" name="utm_campaign"&gt;</code></li>
		<li><strong><?php esc_html_e( 'ID:', 'simple-web-marketing-attribution' ); ?></strong> <code>&lt;input type="hidden" id="utm_campaign"&gt;</code></li>
		<li><strong><?php esc_html_e( 'Class:', 'simple-web-marketing-attribution' ); ?></strong> <code>&lt;input type="hidden" class="utm_campaign"&gt;</code></li>
	</ul>
	<p>
		<?php esc_html_e( 'If you or your form platform are not able to hide the field by setting the type="hidden", it is acceptable to hide fields with CSS, "display:none;".', 'simple-web-marketing-attribution' ); ?>
	</p>
	<p>
		<?php echo wp_kses_post( __( 'To capture the complete attribution log as a JSON object, create a hidden textarea field with the name, ID, or class set to <code>all_detectable_attribution</code>.', 'simple-web-marketing-attribution' ) ); ?>
	</p>

	<h3><?php esc_html_e( 'Tested Forms', 'simple-web-marketing-attribution' ); ?></h3>
	<p><?php esc_html_e( 'This plugin has been successfully tested with the following WordPress form plugins:', 'simple-web-marketing-attribution' ); ?></p>
	<ul>
		<li><strong>HubSpot</strong></li>
		<li><strong>Contact Form 7</strong></li>
		<li><strong>WPForms</strong></li>
		<li><strong>Ninja Forms</strong></li>
	</ul>
	<?php
}

/**
 * Render the Settings tab content.
 */
function swma_settings_tab() {
	?>
	<h2><?php esc_html_e( 'HubSpot Integration (Recommended)', 'simple-web-marketing-attribution' ); ?></h2>
	<p>
		<?php esc_html_e( 'For the most reliable results with HubSpot, we recommend using our direct API integration. This method bypasses issues with iframes and ensures data is always captured.', 'simple-web-marketing-attribution' ); ?>
	</p>

	<h3><?php esc_html_e( 'Instructions for HubSpot Integration', 'simple-web-marketing-attribution' ); ?></h3>
	<h2><?php esc_html_e( 'Step 1: Create Custom Properties in HubSpot', 'simple-web-marketing-attribution' ); ?></h2>
	<p><?php echo wp_kses_post( __( 'You need to create fields in HubSpot to store the attribution data. The <strong>Internal name</strong> must be exactly as written below for the integration to work automatically.', 'simple-web-marketing-attribution' ) ); ?></p>
	<ul>
		<li><strong><?php esc_html_e( 'UTM Source:', 'simple-web-marketing-attribution' ); ?></strong> <code>utm_source</code> (Single-line text)</li>
		<li><strong><?php esc_html_e( 'UTM Medium:', 'simple-web-marketing-attribution' ); ?></strong> <code>utm_medium</code> (Single-line text)</li>
		<li><strong><?php esc_html_e( 'UTM Campaign:', 'simple-web-marketing-attribution' ); ?></strong> <code>utm_campaign</code> (Single-line text)</li>
		<li><strong><?php esc_html_e( 'UTM Term:', 'simple-web-marketing-attribution' ); ?></strong> <code>utm_term</code> (Single-line text)</li>
		<li><strong><?php esc_html_e( 'UTM Content:', 'simple-web-marketing-attribution' ); ?></strong> <code>utm_content</code> (Single-line text)</li>
		<li><strong><?php esc_html_e( 'All Detectable Attribution:', 'simple-web-marketing-attribution' ); ?></strong> <code>all_detectable_attribution</code> (Multi-line text)</li>
	</ul>

	<h2><?php esc_html_e( 'Step 2: Update Your HubSpot Embed Code', 'simple-web-marketing-attribution' ); ?></h2>
	<p><?php echo wp_kses_post( __( 'To enable the integration, you must use the HubSpot script embed method (not the HubSpot widget) and add a special callback to the <code>hbspt.forms.create</code> function. Find your form\'s embed code in HubSpot and add the <code>onFormReady</code> option as shown below:', 'simple-web-marketing-attribution' ) ); ?></p>
	<pre><code>
						<?php
						// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
						echo esc_html(
							'
<!-- Your HubSpot Embed Code -->
<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
<script>
  hbspt.forms.create({
    region: "na1",
    portalId: "YOUR_PORTAL_ID",
    formId: "YOUR_FORM_ID",
    // Add this onFormReady callback
    onFormReady: function($form) {
      // Ensure we work with the raw DOM element
      var form = ($form && $form.jquery) ? $form[0] : $form;
      
      var submitBtn = form.querySelector(\'input[type="submit"], button[type="submit"]\');
      if (submitBtn) {
        submitBtn.addEventListener(\'click\', function(e) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          swma_hubspot_form_submit(form);
        });
      }
    }
  });
</script>
'
						);
						// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
						?>
									</code></pre>

	<h2><?php esc_html_e( 'Step 3: Viewing Your Attribution Data', 'simple-web-marketing-attribution' ); ?></h2>
	<p><?php esc_html_e( 'When a visitor submits the configured form, the attribution data will be sent to HubSpot and appear in the contact\'s activity timeline and properties.', 'simple-web-marketing-attribution' ); ?></p>
	<?php
}
