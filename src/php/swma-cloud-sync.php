<?php
/**
 * SWM Cloud Platform opt-in sync for the WP attribution plugin.
 *
 * When the site owner enables cloud sync from the Settings screen, this module:
 * 1. Registers the site with the SWM Cloud Platform (or retrieves an existing key
 *    when the domain was previously added via the standalone script).
 * 2. After every local conversion insert, fires a non-blocking HTTP POST to the
 *    SWM ingest endpoint so the data also appears in the cloud dashboard.
 *
 * API key and account metadata are stored in the separate `swma_cloud_account`
 * WP option, keeping them distinct from the user-controlled `swma_settings`.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** URL of the SWM Cloud ingest endpoint. */
define( 'SWMA_CLOUD_INGEST_URL', 'https://www.simplewebmarketing.com/swma-ingest/' );

/** URL of the SWM Cloud registration endpoint. */
define( 'SWMA_CLOUD_REGISTER_URL', 'https://www.simplewebmarketing.com/wp-json/swmcp/v1/register-site' );

/** WP option key for the stored cloud account data. */
define( 'SWMA_CLOUD_ACCOUNT_OPTION', 'swma_cloud_account' );

/**
 * Register (or retrieve) this site's account with the SWM Cloud Platform.
 *
 * Makes a blocking HTTP POST to the registration endpoint. On success, stores
 * the returned API key in the `swma_cloud_account` option and returns ok: true.
 * On failure (network error, 409, 500, etc.) returns ok: false with an error
 * string suitable for display in the admin UI.
 *
 * For domains previously registered via the standalone script, the server
 * recognises the wp_plugin source_type, upgrades source_type to wp_plugin, and
 * returns the existing API key (200) instead of 409. The plugin treats this
 * identically to a fresh registration.
 *
 * @return array{ok: bool, api_key?: string, error?: string}
 */
function swma_cloud_register_site() {
	$admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
	$site_url    = get_site_url();
	$site_name   = sanitize_text_field( (string) get_bloginfo( 'name' ) );

	$response = wp_remote_post(
		SWMA_CLOUD_REGISTER_URL,
		array(
			'body'    => wp_json_encode(
				array(
					'site_url'    => $site_url,
					'admin_email' => $admin_email,
					'site_name'   => $site_name,
					'source_type' => 'wp_plugin',
				)
			),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'ok'    => false,
			'error' => $response->get_error_message(),
		);
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 === $status && ! empty( $body['api_key'] ) ) {
		$account = array(
			'api_key'       => sanitize_text_field( $body['api_key'] ),
			'domain'        => isset( $body['domain'] ) ? sanitize_text_field( $body['domain'] ) : '',
			'dashboard_url' => isset( $body['dashboard_url'] ) ? esc_url_raw( $body['dashboard_url'] ) : '',
			'registered_at' => current_time( 'mysql' ),
		);
		update_option( SWMA_CLOUD_ACCOUNT_OPTION, $account );
		return array(
			'ok'      => true,
			'api_key' => $account['api_key'],
		);
	}

	// Extract the server-provided message when available.
	$error = isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';

	if ( '' === $error ) {
		/* translators: %d: HTTP status code returned by the registration server. */
		$error = sprintf( __( 'Registration failed (HTTP %d). Please try again.', 'simple-web-marketing-attribution' ), $status );
	}

	return array(
		'ok'    => false,
		'error' => $error,
	);
}

/**
 * Return the stored cloud account data, or null if not yet registered.
 *
 * @return array{api_key: string, domain: string, dashboard_url: string, registered_at: string}|null
 */
function swma_cloud_get_account() {
	$account = get_option( SWMA_CLOUD_ACCOUNT_OPTION, null );
	return ( is_array( $account ) && ! empty( $account['api_key'] ) ) ? $account : null;
}

/**
 * Fire a non-blocking duplicate conversion POST to the SWM Cloud ingest endpoint.
 *
 * Silently skips if:
 * - Cloud sync is not enabled in settings.
 * - No API key is stored (registration has not completed).
 *
 * Uses the WP HTTP API with blocking => false so the outbound request does not
 * add latency to the conversion-recording response seen by the visitor.
 *
 * @param array $args Conversion data — same shape as passed to swma_log_conversion_to_db().
 */
function swma_cloud_sync_conversion( $args ) {
	$settings = swma_get_attribution_settings();
	if ( empty( $settings['swma_cloud_sync_enabled'] ) ) {
		return;
	}

	$account = swma_cloud_get_account();
	if ( null === $account ) {
		return;
	}

	wp_remote_post(
		SWMA_CLOUD_INGEST_URL,
		array(
			'body'     => wp_json_encode(
				array(
					'page_url'     => isset( $args['page_url'] ) ? $args['page_url'] : '',
					'event_type'   => isset( $args['event_type'] ) ? $args['event_type'] : 'general',
					'utm_source'   => isset( $args['utm_source'] ) && '' !== $args['utm_source'] ? $args['utm_source'] : null,
					'utm_medium'   => isset( $args['utm_medium'] ) && '' !== $args['utm_medium'] ? $args['utm_medium'] : null,
					'utm_campaign' => isset( $args['utm_campaign'] ) && '' !== $args['utm_campaign'] ? $args['utm_campaign'] : null,
					'utm_term'     => isset( $args['utm_term'] ) && '' !== $args['utm_term'] ? $args['utm_term'] : null,
					'utm_content'  => isset( $args['utm_content'] ) && '' !== $args['utm_content'] ? $args['utm_content'] : null,
					'timestamp'    => isset( $args['timestamp'] ) && '' !== $args['timestamp'] ? $args['timestamp'] : null,
				)
			),
			'headers'  => array(
				'Content-Type' => 'application/json',
				'X-SWMA-Key'   => $account['api_key'],
				'Origin'       => get_site_url(),
			),
			'blocking' => false,
			'timeout'  => 5,
		)
	);
}
