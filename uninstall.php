<?php
/**
 * Uninstall hook - Limpar datos ao desinstalar
 *
 * @package Postais_Nadal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * NOTA SOBRE PROTECCIÓN DE DATOS:
 *
 * Este script NON borra intencionalmente:
 * - A táboa da base de datos (postais_nadal) con rexistros de postais xeradas
 * - As imaxes xeradas polos usuarios en wp-content/uploads/postais-nadal/
 * - As imaxes base e stickers subidos polo admin
 *
 * Isto é por protección de datos: se o usuario desinstala o plugin por erro
 * ou quere reinstalalo, non perderá os seus datos.
 *
 * Para un borrado completo manual:
 * 1. Borrar a táboa: DROP TABLE wp_postais_nadal;
 * 2. Borrar a carpeta: wp-content/uploads/postais-nadal/
 */

// Borrar opcións do plugin
delete_option( 'postais_nadal_settings' );
delete_option( 'postais_nadal_db_version' );

// Borrar transients de rate limiting
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_postais_ratelimit_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_postais_ratelimit_%'" );
