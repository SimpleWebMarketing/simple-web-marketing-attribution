<?php
/**
 * Admin Settings functionality for the Simple Web Marketing Attribution.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get the attribution settings, with normalization for the new structure.
 *
 * @return array
 */
function swma_get_attribution_settings() {
	$options = get_option( 'swma_settings', null );

	// Migration from old key if new key doesn't exist.
	if ( null === $options ) {
		$old_options = get_option( 'swm_attribution_settings', null );
		if ( null !== $old_options ) {
			$options = $old_options;
			update_option( 'swma_settings', $options );
		} else {
			$options = array();
		}
	}

	// Normalize to new structure if it's in the old structure or empty.
	if ( empty( $options ) || ! isset( $options['global'] ) ) {
		$normalized = array(
			'global'        => array(
				'thank_you_message' => isset( $options['thank_you_message'] ) ? $options['thank_you_message'] : 'Thanks for submitting the form.',
				'redirect_url'      => isset( $options['thank_you_url'] ) ? $options['thank_you_url'] : '',
			),
			'preserve_data' => true,
			'overrides'     => array(),
		);

		if ( isset( $options['form_overrides'] ) && is_array( $options['form_overrides'] ) ) {
			foreach ( $options['form_overrides'] as $override ) {
				$normalized['overrides'][] = array(
					'form_guid'         => isset( $override['form_id'] ) ? $override['form_id'] : '',
					'thank_you_message' => isset( $override['thank_you_message'] ) ? $override['thank_you_message'] : '',
					'redirect_url'      => isset( $override['thank_you_url'] ) ? $override['thank_you_url'] : '',
				);
			}
		}
		$options = $normalized;
	}

	// Ensure preserve_data exists.
	if ( ! isset( $options['preserve_data'] ) ) {
		$options['preserve_data'] = true;
	}

	// Ensure respect_marketing_consent exists.
	if ( ! isset( $options['respect_marketing_consent'] ) ) {
		// Migration from respect_analytics_consent or consent_mode.
		if ( isset( $options['respect_analytics_consent'] ) ) {
			$options['respect_marketing_consent'] = (bool) $options['respect_analytics_consent'];
		} elseif ( isset( $options['consent_mode'] ) ) {
			$options['respect_marketing_consent'] = ( 'strict' === $options['consent_mode'] );
		} else {
			$options['respect_marketing_consent'] = false; // Default to passive capture.
		}
	}

	// Ensure respect_gpc exists.
	if ( ! isset( $options['respect_gpc'] ) ) {
		$options['respect_gpc'] = true;
	}

	// Ensure debug_mode exists.
	if ( ! isset( $options['debug_mode'] ) ) {
		$options['debug_mode'] = false;
	}

	// Ensure enable_salesforce exists and is true by default.
	if ( ! isset( $options['enable_salesforce'] ) ) {
		$options['enable_salesforce'] = true;
	}

	// Add HubSpot plugin status.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$options['isHubSpotActive']    = is_plugin_active( 'leadin/leadin.php' );
	$options['isSalesforceActive'] = true; // Always active now.

	return $options;
}

/**
 * Register the settings for the HubSpot integration.
 */
function swma_register_settings() {
	// Register a new setting for 'swma_settings' page.
	register_setting(
		'swma_settings_group', // Option group.
		'swma_settings',       // Option name.
		'swma_settings_sanitize' // Sanitize callback.
	);
}
add_action( 'admin_init', 'swma_register_settings' );

/**
 * Register REST API routes for Settings.
 */
function swma_register_rest_routes() {
	register_rest_route(
		'swma/v1',
		'/settings',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'swma_rest_get_settings',
				'permission_callback' => 'swma_rest_settings_permission_check',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'swma_rest_update_settings',
				'permission_callback' => 'swma_rest_settings_permission_check',
			),
		)
	);

	register_rest_route(
		'swma/v1',
		'/reset',
		array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'swma_rest_reset_data',
				'permission_callback' => 'swma_rest_settings_permission_check',
			),
		)
	);
}
add_action( 'rest_api_init', 'swma_register_rest_routes' );

/**
 * Permission check for settings REST API.
 */
function swma_rest_settings_permission_check() {
	return current_user_can( 'manage_options' );
}

/**
 * GET settings callback.
 */
function swma_rest_get_settings() {
	return new WP_REST_Response( swma_get_attribution_settings(), 200 );
}

/**
 * UPDATE settings callback.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_rest_update_settings( $request ) {
	$params    = $request->get_params();
	$sanitized = swma_settings_sanitize( $params );
	update_option( 'swma_settings', $sanitized );
	return new WP_REST_Response( $sanitized, 200 );
}

/**
 * RESET data callback.
 */
function swma_rest_reset_data() {
	global $wpdb;

	$links_table       = swma_get_table_name( 'links' );
	$conversions_table = swma_get_table_name( 'conversions' );

	// Use DELETE instead of TRUNCATE for better compatibility across environments.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( 'DELETE FROM `' . esc_sql( $links_table ) . '`' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( 'DELETE FROM `' . esc_sql( $conversions_table ) . '`' );

	return new WP_REST_Response( array( 'success' => true ), 200 );
}

/**
 * Sanitize each setting field as needed.
 *
 * @param array $input Contains all settings fields as array keys.
 */
function swma_settings_sanitize( $input ) {
	$new_input = array();

	// If input has 'global', it's coming from the new React UI.
	if ( isset( $input['global'] ) ) {
		$new_input['global'] = array(
			'thank_you_message' => isset( $input['global']['thank_you_message'] ) ? wp_kses_post( $input['global']['thank_you_message'] ) : 'Thanks for submitting the form.',
			'redirect_url'      => isset( $input['global']['redirect_url'] ) ? esc_url_raw( $input['global']['redirect_url'] ) : '',
		);

		$new_input['preserve_data']             = isset( $input['preserve_data'] ) ? (bool) $input['preserve_data'] : true;
		$new_input['respect_gpc']               = isset( $input['respect_gpc'] ) ? (bool) $input['respect_gpc'] : true;
		$new_input['debug_mode']                = isset( $input['debug_mode'] ) ? (bool) $input['debug_mode'] : false;
		$new_input['enable_salesforce']         = true; // Always enabled.
		$new_input['respect_marketing_consent'] = isset( $input['respect_marketing_consent'] ) ? (bool) $input['respect_marketing_consent'] : false;

		// Map back to legacy fields for older JS.
		$new_input['respect_analytics_consent'] = $new_input['respect_marketing_consent'];
		$new_input['consent_mode']              = $new_input['respect_marketing_consent'] ? 'strict' : 'default';

		$new_input['overrides'] = array();
		if ( isset( $input['overrides'] ) && is_array( $input['overrides'] ) ) {
			foreach ( $input['overrides'] as $override ) {
				if ( empty( $override['form_guid'] ) ) {
					continue;
				}
				$new_input['overrides'][] = array(
					'form_guid'         => sanitize_text_field( $override['form_guid'] ),
					'thank_you_message' => wp_kses_post( $override['thank_you_message'] ),
					'redirect_url'      => esc_url_raw( $override['redirect_url'] ),
				);
			}
		}
	} else {
		// Legacy support for the old settings page.
		$new_input['global'] = array(
			'thank_you_message' => isset( $input['thank_you_message'] ) ? wp_kses_post( $input['thank_you_message'] ) : 'Thanks for submitting the form.',
			'redirect_url'      => isset( $input['thank_you_url'] ) ? esc_url_raw( $input['thank_you_url'] ) : '',
		);

		$new_input['preserve_data']             = isset( $input['preserve_data'] ) ? (bool) $input['preserve_data'] : true;
		$new_input['respect_gpc']               = isset( $input['respect_gpc'] ) ? (bool) $input['respect_gpc'] : true;
		$new_input['debug_mode']                = isset( $input['debug_mode'] ) ? (bool) $input['debug_mode'] : false;
		$new_input['enable_salesforce']         = true; // Always enabled.
		$new_input['respect_marketing_consent'] = isset( $input['respect_marketing_consent'] ) ? (bool) $input['respect_marketing_consent'] : false;

		// Map back to legacy fields for older JS.
		$new_input['respect_analytics_consent'] = $new_input['respect_marketing_consent'];
		$new_input['consent_mode']              = $new_input['respect_marketing_consent'] ? 'strict' : 'default';

		$new_input['overrides'] = array();
		if ( isset( $input['form_overrides'] ) && is_array( $input['form_overrides'] ) ) {
			foreach ( $input['form_overrides'] as $override ) {
				if ( empty( $override['form_id'] ) ) {
					continue;
				}
				$new_input['overrides'][] = array(
					'form_guid'         => sanitize_text_field( $override['form_id'] ),
					'thank_you_message' => wp_kses_post( $override['thank_you_message'] ),
					'redirect_url'      => esc_url_raw( $override['thank_you_url'] ),
				);
			}
		}
	}

	return $new_input;
}
