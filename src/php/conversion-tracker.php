<?php
/**
 * Conversion Tracker functionality for the Simple Web Marketing Attribution.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handle client-side conversion logging (e.g., phone clicks).
 */
function swma_handle_log_conversion_ajax() {
	// 1. Security Check.
	if ( ! check_ajax_referer( 'swma_hubspot_proxy_nonce', 'security', false ) ) {
		wp_send_json_error( 'Invalid security token.', 403 );
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : 'unknown';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$utm_source = isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$utm_medium = isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$utm_campaign = isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$utm_term = isset( $_POST['utm_term'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_term'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$utm_content = isset( $_POST['utm_content'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_content'] ) ) : '';

	swma_log_conversion_to_db(
		array(
			'event_type'   => $event_type,
			'page_url'     => $page_url,
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'utm_term'     => $utm_term,
			'utm_content'  => $utm_content,
		)
	);

	wp_send_json_success();
}
add_action( 'wp_ajax_swma_log_conversion', 'swma_handle_log_conversion_ajax' );
add_action( 'wp_ajax_nopriv_swma_log_conversion', 'swma_handle_log_conversion_ajax' );

/**
 * Helper function to insert conversion into DB.
 *
 * @param array $data Associative array of conversion data.
 */
function swma_log_conversion_to_db( $data ) {
	global $wpdb;
	$table_name = swma_get_table_name( 'conversions' );

	$defaults = array(
		'timestamp'    => current_time( 'mysql' ),
		'page_url'     => '',
		'event_type'   => 'general',
		'utm_source'   => '',
		'utm_medium'   => '',
		'utm_campaign' => '',
		'utm_term'     => '',
		'utm_content'  => '',
	);

	$args = wp_parse_args( $data, $defaults );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$table_name,
		array(
			'timestamp'    => $args['timestamp'],
			'page_url'     => $args['page_url'],
			'event_type'   => $args['event_type'],
			'utm_source'   => $args['utm_source'],
			'utm_medium'   => $args['utm_medium'],
			'utm_campaign' => $args['utm_campaign'],
			'utm_term'     => $args['utm_term'],
			'utm_content'  => $args['utm_content'],
		)
	);
}

/**
 * Register REST API Route for Dashboard Stats.
 */
function swma_attribution_register_stats_route() {
	register_rest_route(
		'swma/v1',
		'/stats',
		array(
			'methods'             => 'GET',
			'callback'            => 'swma_attribution_get_stats',
			'permission_callback' => function () {
				return current_user_can( 'read' );
			},
		)
	);
}
add_action( 'rest_api_init', 'swma_attribution_register_stats_route' );

/**
 * Get Stats for Dashboard.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_attribution_get_stats( $request ) {
	global $wpdb;
	$table_name = swma_get_table_name( 'conversions' );

	$start_date = sanitize_text_field( $request->get_param( 'start_date' ) ); // YYYY-MM-DD.
	$end_date   = sanitize_text_field( $request->get_param( 'end_date' ) );   // YYYY-MM-DD.

	if ( empty( $start_date ) || empty( $end_date ) ) {
		// Default to last 30 days.
		$end_date   = current_time( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	}

	// Ensure end date covers the full day.
	$start_query = $start_date . ' 00:00:00';
	$end_query   = $end_date . ' 23:59:59';

	$table_name_safe = esc_sql( $table_name );

	// 2. Count Conversions.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$total_conversions = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s', $start_query, $end_query ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$attributed_conversions = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s AND ((utm_source IS NOT NULL AND utm_source != \'\' AND utm_source != \'(direct)\' AND utm_source != \'(none)\') OR (utm_medium IS NOT NULL AND utm_medium != \'\' AND utm_medium != \'(none)\') OR (utm_campaign IS NOT NULL AND utm_campaign != \'\'))', $start_query, $end_query ) );

	// 3. Top Stats (Campaign, Source, Medium).
	$get_top_metric = function ( $column ) use ( $wpdb, $start_query, $end_query, $table_name_safe ) {
		switch ( $column ) {
			case 'utm_campaign':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				return $wpdb->get_row( $wpdb->prepare( 'SELECT `utm_campaign` as name, COUNT(*) as count FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s AND `utm_campaign` IS NOT NULL AND `utm_campaign` != \'\' AND `utm_campaign` != \'(direct)\' AND `utm_campaign` != \'(none)\' GROUP BY `utm_campaign` ORDER BY count DESC LIMIT 1', $start_query, $end_query ) );
			case 'utm_source':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				return $wpdb->get_row( $wpdb->prepare( 'SELECT `utm_source` as name, COUNT(*) as count FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s AND `utm_source` IS NOT NULL AND `utm_source` != \'\' AND `utm_source` != \'(direct)\' AND `utm_source` != \'(none)\' GROUP BY `utm_source` ORDER BY count DESC LIMIT 1', $start_query, $end_query ) );
			case 'utm_medium':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				return $wpdb->get_row( $wpdb->prepare( 'SELECT `utm_medium` as name, COUNT(*) as count FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s AND `utm_medium` IS NOT NULL AND `utm_medium` != \'\' AND `utm_medium` != \'(direct)\' AND `utm_medium` != \'(none)\' GROUP BY `utm_medium` ORDER BY count DESC LIMIT 1', $start_query, $end_query ) );
			default:
				return null;
		}
	};

	$top_campaign = $get_top_metric( 'utm_campaign' );
	$top_source   = $get_top_metric( 'utm_source' );
	$top_medium   = $get_top_metric( 'utm_medium' );

	// 4. Time Series Data (Daily).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$daily_data = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(timestamp) as date, COUNT(*) as total FROM `' . $table_name_safe . '` WHERE timestamp >= %s AND timestamp <= %s GROUP BY DATE(timestamp) ORDER BY date ASC', $start_query, $end_query ) );

	// 5. Recent Conversions (Last 50).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$recent_conversions = $wpdb->get_results( 'SELECT * FROM `' . $table_name_safe . '` ORDER BY timestamp DESC LIMIT 50' );

	foreach ( $recent_conversions as $conversion ) {
		$conversion->formatted_date = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $conversion->timestamp );
	}

	return rest_ensure_response(
		array(
			'total_conversions'      => (int) $total_conversions,
			'attributed_conversions' => (int) $attributed_conversions,
			'top_campaign'           => $top_campaign ? $top_campaign : array(
				'name'  => 'N/A',
				'count' => 0,
			),
			'top_source'             => $top_source ? $top_source : array(
				'name'  => 'N/A',
				'count' => 0,
			),
			'top_medium'             => $top_medium ? $top_medium : array(
				'name'  => 'N/A',
				'count' => 0,
			),
			'chart_data'             => $daily_data,
			'recent_conversions'     => $recent_conversions,
		)
	);
}
