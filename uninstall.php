<?php
/**
 * Uninstall file for the Simple Web Marketing Attribution.
 *
 * This file is run when the plugin is uninstalled from the WordPress dashboard.
 * It checks the user preference for data preservation before removing plugin data.
 *
 * @package Simple_Web_Marketing_Attribution
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Get the data preservation setting.
$swma_uninst_settings = get_option( 'swma_settings', null );
if ( null === $swma_uninst_settings ) {
	$swma_uninst_settings = get_option( 'swm_attribution_settings', array() );
}
$swma_uninst_preserve_data = isset( $swma_uninst_settings['preserve_data'] ) ? (bool) $swma_uninst_settings['preserve_data'] : true;

// 2. If user chose NOT to preserve data, clean up the database.
if ( ! $swma_uninst_preserve_data ) {
	global $wpdb;

	// Define table names.
	$swma_uninst_links_table       = $wpdb->prefix . 'swma_attr_links';
	$swma_uninst_conversions_table = $wpdb->prefix . 'swma_conversions';

	// Drop tables.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $swma_uninst_links_table ) . '`' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $swma_uninst_conversions_table ) . '`' );

	// Delete options.
	delete_option( 'swma_settings' );
	delete_option( 'swm_attribution_settings' );
}
