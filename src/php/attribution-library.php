<?php
/**
 * Attribution Library functionality for the Simple Web Marketing Attribution.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register REST API Routes.
 */
function swma_register_routes() {
	register_rest_route(
		'swma/v1',
		'/links',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'swma_get_links',
				'permission_callback' => function () {
					return current_user_can( 'read' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'swma_create_link',
				'permission_callback' => function () {
					return current_user_can( 'read' );
				},
			),
		)
	);

	register_rest_route(
		'swma/v1',
		'/links/(?P<id>\d+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'swma_delete_link',
			'permission_callback' => function () {
				return current_user_can( 'read' );
			},
		)
	);

	register_rest_route(
		'swma/v1',
		'/autocomplete',
		array(
			'methods'             => 'GET',
			'callback'            => 'swma_autocomplete',
			'permission_callback' => function () {
				return current_user_can( 'read' );
			},
		)
	);
}
add_action( 'rest_api_init', 'swma_register_routes' );

/**
 * GET /links - Retrieve all saved links.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_get_links( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	global $wpdb;
	$table_name = swma_get_table_name( 'links' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$results = $wpdb->get_results( 'SELECT * FROM `' . esc_sql( $table_name ) . '` ORDER BY date_created DESC' );

	return rest_ensure_response( $results );
}

/**
 * POST /links - Create a new link.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_create_link( $request ) {
	global $wpdb;
	$table_name = swma_get_table_name( 'links' );

	$params = $request->get_json_params();

	$base_url  = esc_url_raw( $params['base_url'] );
	$campaign  = sanitize_text_field( $params['campaign'] );
	$source    = sanitize_text_field( $params['source'] );
	$medium    = sanitize_text_field( $params['medium'] );
	$notes     = sanitize_textarea_field( $params['notes'] );
	$final_url = esc_url_raw( $params['final_url'] );

	if ( empty( $base_url ) || ( empty( $campaign ) && empty( $source ) && empty( $medium ) ) ) {
		return new WP_Error( 'missing_fields', 'Required fields are missing. Provide Base URL and at least one UTM parameter.', array( 'status' => 400 ) );
	}

	// Check if a link with the same final_url already exists.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . esc_sql( $table_name ) . '` WHERE final_url = %s LIMIT 1', $final_url ) );

	if ( $existing ) {
		return new WP_Error( 'link_exists', 'This link already exists in your link library. Please find it in the link library along with any accompanying notes and copy it to your clipboard from there.', array( 'status' => 409 ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$inserted = $wpdb->insert(
		$table_name,
		array(
			'base_url'     => $base_url,
			'campaign'     => $campaign,
			'source'       => $source,
			'medium'       => $medium,
			'notes'        => $notes,
			'final_url'    => $final_url,
			'date_created' => current_time( 'mysql' ),
		)
	);

	if ( false === $inserted ) {
		return new WP_Error( 'db_insert_error', 'Could not save link to database', array( 'status' => 500 ) );
	}

	$new_id = $wpdb->insert_id;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$new_link = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE id = %d', $new_id ) );

	return rest_ensure_response( $new_link );
}

/**
 * DELETE /links/<id> - Delete a link.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_delete_link( $request ) {
	global $wpdb;
	$table_name = swma_get_table_name( 'links' );
	$id         = $request['id'];

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$deleted = $wpdb->delete( $table_name, array( 'id' => $id ) );

	if ( false === $deleted ) {
		return new WP_Error( 'db_delete_error', 'Could not delete link', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'deleted' => true,
			'id'      => $id,
		)
	);
}

/**
 * GET /autocomplete - Autocomplete suggestions.
 *
 * @param WP_REST_Request $request The request object.
 */
function swma_autocomplete( $request ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'swma_attr_links';

	$term  = sanitize_text_field( $request->get_param( 'term' ) );
	$field = sanitize_text_field( $request->get_param( 'field' ) );

	if ( empty( $term ) || strlen( $term ) < 2 ) {
		return rest_ensure_response( array() );
	}

	$allowed_fields = array( 'campaign', 'source', 'medium' );
	if ( ! in_array( $field, $allowed_fields, true ) ) {
		return rest_ensure_response( array() );
	}

	$table_name = swma_get_table_name( 'links' );

	// Use hardcoded SQL strings for each allowed field to satisfy strict PCP checks.
	switch ( $field ) {
		case 'campaign':
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `campaign` FROM `' . esc_sql( $table_name ) . '` WHERE `campaign` LIKE %s LIMIT 10', '%' . $wpdb->esc_like( $term ) . '%' ) );
			break;
		case 'source':
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `source` FROM `' . esc_sql( $table_name ) . '` WHERE `source` LIKE %s LIMIT 10', '%' . $wpdb->esc_like( $term ) . '%' ) );
			break;
		case 'medium':
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `medium` FROM `' . esc_sql( $table_name ) . '` WHERE `medium` LIKE %s LIMIT 10', '%' . $wpdb->esc_like( $term ) . '%' ) );
			break;
		default:
			$results = array();
	}

	return rest_ensure_response( $results );
}
