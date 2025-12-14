<?php
/**
 * Plugin Name: Postais de Nadal - As Chaves da Lingua
 * Plugin URI: https://aschavesdalingua.gal
 * Description: Sistema de xeración de felicitacións de Nadal con editor visual, base de datos propia e panel admin completo.
 * Version: 1.0.0
 * Author: Anxo Sánchez
 * Author URI: https://www.anxosanchez.com
 * Text Domain: postais-nadal
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes do plugin
define( 'POSTAIS_NADAL_VERSION', '1.0.0' );
define( 'POSTAIS_NADAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'POSTAIS_NADAL_URL', plugin_dir_url( __FILE__ ) );

// Cargar clases principais
require_once POSTAIS_NADAL_PATH . 'includes/class-database.php';
require_once POSTAIS_NADAL_PATH . 'includes/class-image-generator.php';
require_once POSTAIS_NADAL_PATH . 'includes/class-admin.php';
require_once POSTAIS_NADAL_PATH . 'includes/class-frontend.php';

/**
 * Hook de activación do plugin
 * Crea táboa BBDD e carpetas necesarias
 */
function postais_nadal_activate() {
	// Crear táboa na base de datos
	$database = new Postais_Nadal_Database();
	$database->create_table();

	// Crear carpetas de uploads
	$upload_dir = wp_upload_dir();
	$postais_dir = $upload_dir['basedir'] . '/postais-nadal';
	$bases_dir = $postais_dir . '/bases';
	$stickers_dir = $postais_dir . '/stickers';

	if ( ! file_exists( $postais_dir ) ) {
		wp_mkdir_p( $postais_dir );
	}

	if ( ! file_exists( $bases_dir ) ) {
		wp_mkdir_p( $bases_dir );
	}

	if ( ! file_exists( $stickers_dir ) ) {
		wp_mkdir_p( $stickers_dir );
	}

	// Opcións por defecto
	if ( ! get_option( 'postais_nadal_settings' ) ) {
		update_option( 'postais_nadal_settings', array(
			'imaxes_base' => array(),
			'fontes' => array( 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Raleway' ),
			'modal_cor_fondo' => '#ffffff',
			'modal_cor_texto' => '#333333',
			'modal_tipografia' => 'Roboto',
			'limite_caracteres' => 150,
		) );
	}
}
register_activation_hook( __FILE__, 'postais_nadal_activate' );

/**
 * Hook de desactivación do plugin
 * Limpeza de opcións temporais (non borra datos)
 */
function postais_nadal_deactivate() {
	// Borrar transients de rate limiting
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_postais_ratelimit_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_postais_ratelimit_%'" );
}
register_deactivation_hook( __FILE__, 'postais_nadal_deactivate' );

/**
 * Inicializar o plugin
 */
function postais_nadal_init() {
	// Cargar traducións
	load_plugin_textdomain(
		'postais-nadal',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Inicializar clases
	new Postais_Nadal_Admin();
	new Postais_Nadal_Frontend();
}
add_action( 'plugins_loaded', 'postais_nadal_init' );
