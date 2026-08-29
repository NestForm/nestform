<?php
/**
 * Uninstall cleanup (hooked via Freemius after_uninstall).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wipe Nestform data when the user opted in via settings.
 *
 * @return void
 */
function nestform_fs_uninstall_cleanup() {
	$settings = get_option( 'nestform_settings', array() );
	$wipe     = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) && '1' === (string) $settings['delete_data_on_uninstall'];

	if ( ! $wipe ) {
		return;
	}

	global $wpdb;

	$form_ids = get_posts(
		array(
			'post_type'              => 'nestform',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $form_ids as $form_id ) {
		wp_delete_post( (int) $form_id, true );
	}

	$entry_ids = get_posts(
		array(
			'post_type'              => 'nestform_entry',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $entry_ids as $entry_id ) {
		wp_delete_post( (int) $entry_id, true );
	}

	delete_option( 'nestform_settings' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'nestform\\_%'" );

	$table = $wpdb->prefix . 'nestform_form_views';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
