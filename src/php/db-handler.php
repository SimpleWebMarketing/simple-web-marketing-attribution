<?php
/**
 * Database handler for the attribution plugin.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create the custom database table for attribution links on plugin activation.
 */
function swma_create_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'swma_attr_links';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE `$table_name` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        campaign varchar(255) NOT NULL,
        source varchar(255) NOT NULL,
        medium varchar(255) NOT NULL,
        base_url text NOT NULL,
        final_url text NOT NULL,
        notes text DEFAULT NULL,
        date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        KEY campaign (campaign),
        KEY source (source),
        KEY medium (medium)
    ) $charset_collate;";

	$conversions_table_name = $wpdb->prefix . 'swma_conversions';
	$sql_conversions        = "CREATE TABLE `$conversions_table_name` (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		page_url text NOT NULL,
		event_type varchar(50) NOT NULL,
		utm_source varchar(255),
		utm_medium varchar(255),
		utm_campaign varchar(255),
		utm_term varchar(255),
		utm_content varchar(255),
		PRIMARY KEY  (id),
		KEY event_type (event_type),
		KEY timestamp (timestamp)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $sql_conversions );
}

/**
 * Get the full table name with prefix for the plugin's custom tables.
 *
 * @param string $table_key The key of the table (e.g., 'links', 'conversions').
 * @return string|null The full table name with prefix, or null if key is invalid.
 */
function swma_get_table_name( $table_key ) {
	global $wpdb;

	$tables = array(
		'links'       => $wpdb->prefix . 'swma_attr_links',
		'conversions' => $wpdb->prefix . 'swma_conversions',
	);

	return isset( $tables[ $table_key ] ) ? $tables[ $table_key ] : null;
}
