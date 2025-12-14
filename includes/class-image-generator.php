<?php
/**
 * Clase para xerar e gardar imaxes das postais
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Postais_Nadal_Image_Generator
 */
class Postais_Nadal_Image_Generator {

	/**
	 * Gardar imaxe da postal
	 *
	 * @param string $image_data Base64 encoded image data.
	 * @param string $email Email do usuario.
	 * @param string $ip IP do usuario.
	 * @param array  $metadata Metadata da postal.
	 * @return array|WP_Error Array con URL e ID ou WP_Error en caso de erro.
	 */
	public function save_image( $image_data, $email, $ip, $metadata ) {
		// Validar email
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Email non válido' );
		}

		// Comprobar rate limit
		$database = new Postais_Nadal_Database();
		if ( $database->check_rate_limit( $ip ) ) {
			return new WP_Error( 'rate_limit', 'Superaches o límite de postais. Espera uns minutos.' );
		}

		// Decodificar imaxe base64
		if ( preg_match( '/^data:image\/(\w+);base64,/', $image_data, $type ) ) {
			$image_data = substr( $image_data, strpos( $image_data, ',' ) + 1 );
			$type = strtolower( $type[1] );

			// Validar tipo de imaxe
			if ( ! in_array( $type, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				return new WP_Error( 'invalid_type', 'Tipo de imaxe non válido' );
			}

			$image_data = base64_decode( $image_data );

			if ( false === $image_data ) {
				return new WP_Error( 'decode_error', 'Erro ao decodificar a imaxe' );
			}
		} else {
			return new WP_Error( 'invalid_data', 'Formato de imaxe non válido' );
		}

		// Crear directorio se non existe
		$upload_dir = wp_upload_dir();
		$year = wp_date( 'Y' );
		$month = wp_date( 'm' );
		$target_dir = $upload_dir['basedir'] . '/postais-nadal/' . $year . '/' . $month;

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Xerar nome único e seguro (non predecible)
		$filename = 'postal_' . bin2hex( random_bytes( 16 ) ) . '.jpg';
		$filepath = $target_dir . '/' . $filename;

		// Gardar imaxe
		$saved = file_put_contents( $filepath, $image_data );

		if ( false === $saved ) {
			error_log( 'Postais Nadal: Erro ao gardar imaxe en ' . $filepath );
			return new WP_Error( 'save_error', 'Erro ao gardar a imaxe' );
		}

		// Xerar URL
		$image_url = $upload_dir['baseurl'] . '/postais-nadal/' . $year . '/' . $month . '/' . $filename;

		// Gardar na base de datos
		$postal_data = array(
			'email' => $email,
			'ip' => $ip,
			'image_url' => $image_url,
			'metadata' => $metadata,
		);

		$postal_id = $database->insert_postal( $postal_data );

		if ( ! $postal_id ) {
			// Borrar imaxe se falla o gardado na BBDD
			if ( ! @unlink( $filepath ) ) {
				error_log( 'Postais Nadal: Erro ao borrar ficheiro orfan ' . $filepath );
			}
			error_log( 'Postais Nadal: Erro ao insertar na BBDD para email ' . $email );
			return new WP_Error( 'db_error', 'Erro ao gardar na base de datos' );
		}

		return array(
			'success' => true,
			'url' => $image_url,
			'id' => $postal_id,
		);
	}

	/**
	 * Comprobar rate limit
	 *
	 * @param string $ip IP do usuario.
	 * @return bool True se superou o límite.
	 */
	public function check_rate_limit( $ip ) {
		$database = new Postais_Nadal_Database();
		return $database->check_rate_limit( $ip );
	}
}
