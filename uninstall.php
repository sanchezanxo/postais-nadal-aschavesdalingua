<?php
/**
 * Uninstall hook - Limpar datos ao desinstalar
 *
 * @package Postais_Nadal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Borrar opcións do plugin
delete_option( 'postais_nadal_settings' );
delete_option( 'postais_nadal_db_version' );

// Borrar transients de rate limiting
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_postais_ratelimit_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_postais_ratelimit_%'" );

// Borrar táboa da base de datos
$table_name = $wpdb->prefix . 'postais_nadal';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Borrar carpeta de uploads con todas as imaxes
$upload_dir = wp_get_upload_dir();
$postais_dir = $upload_dir['basedir'] . '/postais-nadal';

if ( is_dir( $postais_dir ) ) {
	postais_nadal_delete_directory( $postais_dir );
}

/**
 * Borrar directorio recursivamente
 *
 * @param string $dir Ruta do directorio.
 * @return bool
 */
function postais_nadal_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			postais_nadal_delete_directory( $path );
		} else {
			@unlink( $path );
		}
	}

	return @rmdir( $dir );
}
