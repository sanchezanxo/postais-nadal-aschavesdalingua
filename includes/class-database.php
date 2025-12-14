<?php
/**
 * Clase para manexar a base de datos das postais
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Postais_Nadal_Database
 */
class Postais_Nadal_Database {

	/**
	 * Nome da táboa
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Versión actual da base de datos
	 *
	 * @var string
	 */
	private $db_version = '1.1';

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'postais_nadal';
	}

	/**
	 * Crear ou actualizar táboa na base de datos
	 */
	public function create_table() {
		$installed_version = get_option( 'postais_nadal_db_version' );

		// Só executar dbDelta se a versión cambiou ou non existe
		if ( $installed_version !== $this->db_version ) {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				email VARCHAR(255) NOT NULL,
				ip VARCHAR(45) DEFAULT NULL,
				timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				image_url VARCHAR(500) DEFAULT NULL,
				metadata TEXT DEFAULT NULL,
				PRIMARY KEY (id),
				KEY email (email),
				KEY timestamp (timestamp),
				KEY ip (ip)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( 'postais_nadal_db_version', $this->db_version );
		}
	}

	/**
	 * Inserir nova postal na base de datos
	 *
	 * @param array $data Datos da postal.
	 * @return int|false ID da postal insertada ou false en caso de erro.
	 */
	public function insert_postal( $data ) {
		global $wpdb;

		// Sanitizar e limitar metadata (só campos esenciais, máx 2KB)
		$metadata = array();
		if ( ! empty( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$metadata['num_textos'] = isset( $data['metadata']['textElements'] ) ? count( $data['metadata']['textElements'] ) : 0;
			$metadata['imaxe_base'] = isset( $data['metadata']['selectedImage'] ) ? absint( $data['metadata']['selectedImage'] ) : 0;
		}
		$metadata_json = wp_json_encode( $metadata );
		if ( strlen( $metadata_json ) > 2048 ) {
			$metadata_json = '{}';
		}

		// Sanitizar datos
		$clean_data = array(
			'email' => sanitize_email( $data['email'] ),
			'ip' => sanitize_text_field( $data['ip'] ),
			'image_url' => esc_url_raw( $data['image_url'] ),
			'metadata' => $metadata_json,
			'timestamp' => current_time( 'mysql' ),
		);

		// Inserir na base de datos
		$result = $wpdb->insert(
			$this->table_name,
			$clean_data,
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Obter tódas as postais con paxinación
	 *
	 * @param int $limit Límite de resultados.
	 * @param int $offset Offset para paxinación.
	 * @return array Array de postais.
	 */
	public function get_all_postals( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$limit = absint( $limit );
		$offset = absint( $offset );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Obter o número total de postais
	 *
	 * @return int Total de postais.
	 */
	public function get_total_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	/**
	 * Obter estatísticas das postais
	 *
	 * @return array Array con estatísticas.
	 */
	public function get_stats() {
		global $wpdb;

		// Total de postais
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		// Emails únicos
		$unique_emails = $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$this->table_name}" );

		// Postais de hoxe (usando a hora de WordPress)
		$today = current_time( 'Y-m-d' );
		$today_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(timestamp) = %s",
				$today
			)
		);

		// Postais nos últimos 7 días
		$last_week = $wpdb->get_results(
			"SELECT DATE(timestamp) as date, COUNT(*) as count
			FROM {$this->table_name}
			WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY DATE(timestamp)
			ORDER BY date DESC",
			ARRAY_A
		);

		return array(
			'total' => (int) $total,
			'unique_emails' => (int) $unique_emails,
			'today' => (int) $today_count,
			'last_week' => $last_week ? $last_week : array(),
		);
	}

	/**
	 * Obter unha postal por ID
	 *
	 * @param int $id ID da postal.
	 * @return array|null Datos da postal ou null.
	 */
	public function get_postal_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Borrar unha postal por ID
	 *
	 * @param int $id ID da postal.
	 * @return bool True se se borrou correctamente.
	 */
	public function delete_postal( $id ) {
		global $wpdb;
		$result = $wpdb->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
		return false !== $result;
	}

	/**
	 * Comprobar rate limit por IP
	 *
	 * @param string $ip Enderezo IP.
	 * @return bool True se superou o límite, false en caso contrario.
	 */
	public function check_rate_limit( $ip ) {
		$transient_key = 'postais_ratelimit_' . md5( $ip );
		$count = get_transient( $transient_key );

		if ( false === $count ) {
			// Primeira petición, crear transient
			set_transient( $transient_key, 1, 10 * MINUTE_IN_SECONDS );
			return false;
		}

		// Incrementar contador
		$count++;
		set_transient( $transient_key, $count, 10 * MINUTE_IN_SECONDS );

		// Límite: 20 postais cada 10 minutos
		return $count > 20;
	}
}
