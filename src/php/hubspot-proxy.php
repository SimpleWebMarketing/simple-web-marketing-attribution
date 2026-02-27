<?php
/**
 * HubSpot Proxy functionality for the Simple Web Marketing Attribution.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles the AJAX request to proxy HubSpot form submissions.
 * This function gathers form data and attribution details,
 * then sends them to the HubSpot Forms API.
 */
function swma_handle_hubspot_proxy_submission() {
	// 1. Security Check.
	if ( ! check_ajax_referer( 'swma_hubspot_proxy_nonce', 'security', false ) ) {
		wp_send_json_error(
			array(
				'message' => 'Invalid security token.',
			),
			403
		);
		return;
	}

	// 2. Get Portal and Form ID from the AJAX request.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$portal_id = isset( $_POST['portal_id'] ) ? sanitize_text_field( wp_unslash( $_POST['portal_id'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$form_guid = isset( $_POST['form_guid'] ) ? sanitize_text_field( wp_unslash( $_POST['form_guid'] ) ) : '';

	if ( empty( $portal_id ) || empty( $form_guid ) ) {
		wp_send_json_error(
			array(
				'message' => 'HubSpot Portal ID or Form ID missing.',
			),
			400
		);
		return;
	}

	// 3. Get fields and context.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['fields'] ) || ! isset( $_POST['context'] ) ) {
		wp_send_json_error(
			array(
				'message' => 'Missing fields or context data.',
			),
			400
		);
		return;
	}

	// Parse fields.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	parse_str( wp_unslash( $_POST['fields'] ), $fields );

	// Robust JSON decoding for context.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$context_str = wp_unslash( $_POST['context'] );
	$context_raw = json_decode( $context_str, true );

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		$context_str = wp_unslash( $context_str );
		$context_raw = json_decode( $context_str, true );
	}

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $context_raw ) ) {
		wp_send_json_error(
			array(
				'message'    => 'Invalid JSON data in context',
				'json_error' => json_last_error_msg(),
			),
			400
		);
		return;
	}

	// Sanitize context - DO NOT use sanitize_key here as HubSpot API is case-sensitive (pageUri, pageName).
	$context = array();
	foreach ( $context_raw as $key => $value ) {
		// Use sanitize_text_field for keys to be safe but preserve case.
		$context[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
	}

	// Add hutk cookie if available.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_COOKIE['hubspotutk'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$context['hutk'] = sanitize_text_field( wp_unslash( $_COOKIE['hubspotutk'] ) );
	}

	// 4. Prepare HubSpot API payload.
	$hubspot_api_url = "https://api.hsforms.com/submissions/v3/integration/submit/{$portal_id}/{$form_guid}";

	$hubspot_fields = array();
	foreach ( $fields as $name => $value ) {
		// Skip HubSpot internal fields and metadata that shouldn't be in the fields array.
		if ( 0 === strpos( $name, 'hs_' ) || 0 === strpos( $name, '__hs' ) || 'hubspotutk' === $name || 'goog-gtm-vid' === $name ) {
			continue;
		}

		// Skip empty attribution fields to avoid 400 errors if they aren't on the form.
		if ( empty( $value ) && ( 0 === strpos( $name, 'utm_' ) || 'all_detectable_attribution' === $name ) ) {
			continue;
		}

		$hubspot_fields[] = array(
			'name'  => sanitize_text_field( $name ),
			'value' => sanitize_text_field( $value ),
		);
	}

	$hubspot_payload = array(
		'fields'  => $hubspot_fields,
		'context' => $context,
	);

	// 5. Send to HubSpot.
	$response = wp_remote_post(
		$hubspot_api_url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $hubspot_payload ),
			'timeout' => 20,
		)
	);

	// 6. Handle Response.
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'Failed to send data to HubSpot: ' . $response->get_error_message(), 500 );
	} else {
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 200 && $response_code < 300 ) {
			// Log conversion locally.
			if ( function_exists( 'swma_log_conversion_to_db' ) ) {
				$conversion_data = array(
					'event_type'   => 'hubspot_form',
					'page_url'     => isset( $context['pageUri'] ) ? esc_url_raw( $context['pageUri'] ) : '',
					'utm_source'   => '',
					'utm_medium'   => '',
					'utm_campaign' => '',
					'utm_term'     => '',
					'utm_content'  => '',
				);

				foreach ( $hubspot_fields as $field ) {
					if ( 0 === strpos( $field['name'], 'utm_' ) ) {
						$conversion_data[ $field['name'] ] = sanitize_text_field( $field['value'] );
					}
				}

				swma_log_conversion_to_db( $conversion_data );
			}

			$data = json_decode( $response_body, true );

			// Apply settings overrides.
			if ( empty( $data['redirectUri'] ) ) {
				$settings = function_exists( 'swma_get_attribution_settings' ) ? swma_get_attribution_settings() : array();

				if ( ! empty( $settings ) ) {
					$behavior = array(
						'thank_you_message' => $settings['global']['thank_you_message'],
						'redirect_url'      => $settings['global']['redirect_url'],
					);

					foreach ( $settings['overrides'] as $override ) {
						if ( $override['form_guid'] === $form_guid ) {
							if ( ! empty( $override['thank_you_message'] ) ) {
								$behavior['thank_you_message'] = $override['thank_you_message'];
							}
							if ( ! empty( $override['redirect_url'] ) ) {
								$behavior['redirect_url'] = $override['redirect_url'];
							}
							break;
						}
					}

					if ( empty( $data['inlineMessage'] ) ) {
						$data['inlineMessage'] = $behavior['thank_you_message'];
					}
					if ( ! empty( $behavior['redirect_url'] ) ) {
						$data['redirectUri'] = $behavior['redirect_url'];
					}
				}
			}

			wp_send_json_success( $data );
		} else {
			wp_send_json_error(
				array(
					'message'          => 'HubSpot API returned an error.',
					'hubspot_response' => json_decode( $response_body, true ),
				),
				$response_code
			);
		}
	}
}

add_action( 'wp_ajax_swma_hubspot_proxy', 'swma_handle_hubspot_proxy_submission' );
add_action( 'wp_ajax_nopriv_swma_hubspot_proxy', 'swma_handle_hubspot_proxy_submission' );
