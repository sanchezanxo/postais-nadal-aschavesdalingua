<?php
/**
 * Clase para o panel de administración
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Postais_Nadal_Admin
 */
class Postais_Nadal_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_postais_upload_image', array( $this, 'handle_image_upload' ) );
		add_action( 'wp_ajax_postais_delete_image', array( $this, 'handle_image_delete' ) );
		add_action( 'wp_ajax_postais_upload_sticker', array( $this, 'handle_sticker_upload' ) );
		add_action( 'wp_ajax_postais_delete_sticker', array( $this, 'handle_sticker_delete' ) );
		add_action( 'wp_ajax_postais_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_postais_delete_postals', array( $this, 'handle_delete_postals' ) );
	}

	/**
	 * Escanear imaxes do plugin nunha categoría
	 *
	 * @param string $categoria Categoría (postal, instagram, historia).
	 * @return array Array de imaxes con url, path e source.
	 */
	public function scan_plugin_images( $categoria = '' ) {
		$images = array();
		$base_path = POSTAIS_NADAL_PATH . 'assets/images/bases/';
		$base_url = POSTAIS_NADAL_URL . 'assets/images/bases/';

		if ( empty( $categoria ) ) {
			return $images;
		}

		$path = $base_path . $categoria . '/';
		$url = $base_url . $categoria . '/';

		if ( ! is_dir( $path ) ) {
			return $images;
		}

		$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'webp' );
		$files = glob( $path . '*.*' );

		if ( $files ) {
			foreach ( $files as $file ) {
				$filetype = wp_check_filetype( $file );
				if ( in_array( strtolower( $filetype['ext'] ), $allowed_extensions, true ) ) {
					$filename = basename( $file );
					$images[] = array(
						'url'    => $url . $filename,
						'path'   => $file,
						'source' => 'plugin',
					);
				}
			}
		}

		return $images;
	}

	/**
	 * Escanear stickers do plugin
	 *
	 * @return array Array de stickers con url, path e source.
	 */
	public function scan_plugin_stickers() {
		$stickers = array();
		$path = POSTAIS_NADAL_PATH . 'assets/images/stickers/';
		$url = POSTAIS_NADAL_URL . 'assets/images/stickers/';

		if ( ! is_dir( $path ) ) {
			return $stickers;
		}

		$files = glob( $path . '*.png' );

		if ( $files ) {
			foreach ( $files as $file ) {
				$filename = basename( $file );
				$stickers[] = array(
					'url'    => $url . $filename,
					'path'   => $file,
					'source' => 'plugin',
				);
			}
		}

		return $stickers;
	}

	/**
	 * Obter todas as imaxes dunha categoría (plugin + uploads)
	 *
	 * @param string $categoria Categoría (postal, instagram, historia).
	 * @return array Array de imaxes combinadas.
	 */
	public function get_all_images( $categoria ) {
		$images = array();

		// Primeiro, imaxes do plugin
		$plugin_images = $this->scan_plugin_images( $categoria );
		$images = array_merge( $images, $plugin_images );

		// Logo, imaxes subidas polo admin (gardadas en settings)
		$settings = get_option( 'postais_nadal_settings', array() );
		$key = 'imaxes_' . $categoria;

		if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
			foreach ( $settings[ $key ] as $url ) {
				$images[] = array(
					'url'    => $url,
					'path'   => '',
					'source' => 'upload',
				);
			}
		}

		return $images;
	}

	/**
	 * Obter todos os stickers (plugin + uploads)
	 *
	 * @return array Array de stickers combinados.
	 */
	public function get_all_stickers() {
		$stickers = array();

		// Primeiro, stickers do plugin
		$plugin_stickers = $this->scan_plugin_stickers();
		$stickers = array_merge( $stickers, $plugin_stickers );

		// Logo, stickers subidos polo admin
		$settings = get_option( 'postais_nadal_settings', array() );

		if ( isset( $settings['stickers'] ) && is_array( $settings['stickers'] ) ) {
			foreach ( $settings['stickers'] as $url ) {
				$stickers[] = array(
					'url'    => $url,
					'path'   => '',
					'source' => 'upload',
				);
			}
		}

		return $stickers;
	}

	/**
	 * Rexistrar menú de administración
	 */
	public function register_admin_menu() {
		add_menu_page(
			'Postais de Nadal',
			'Postais de Nadal',
			'manage_options',
			'postais-nadal-opcions',
			array( $this, 'render_options_page' ),
			'dashicons-images-alt2',
			30
		);

		add_submenu_page(
			'postais-nadal-opcions',
			'Opcións',
			'Opcións',
			'manage_options',
			'postais-nadal-opcions',
			array( $this, 'render_options_page' )
		);

		add_submenu_page(
			'postais-nadal-opcions',
			'Postais Xeradas',
			'Postais Xeradas',
			'manage_options',
			'postais-nadal-stats',
			array( $this, 'render_stats_page' )
		);

		add_submenu_page(
			'postais-nadal-opcions',
			'Exportar CSV',
			'Exportar CSV',
			'manage_options',
			'postais-nadal-export',
			array( $this, 'render_export_page' )
		);
	}

	/**
	 * Cargar scripts e estilos do admin
	 *
	 * @param string $hook Hook da páxina actual.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Só cargar nas nosas páxinas
		if ( strpos( $hook, 'postais-nadal' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'postais-nadal-admin',
			POSTAIS_NADAL_URL . 'assets/css/admin.css',
			array(),
			POSTAIS_NADAL_VERSION
		);

		wp_enqueue_script(
			'postais-nadal-admin',
			POSTAIS_NADAL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			POSTAIS_NADAL_VERSION,
			true
		);

		wp_localize_script(
			'postais-nadal-admin',
			'postaisNadalAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'postais_nadal_admin' ),
			)
		);

		// Script adicional para a páxina de estatísticas
		if ( strpos( $hook, 'postais-nadal-stats' ) !== false ) {
			wp_enqueue_script(
				'postais-nadal-admin-stats',
				POSTAIS_NADAL_URL . 'assets/js/admin-stats.js',
				array( 'jquery', 'postais-nadal-admin' ),
				POSTAIS_NADAL_VERSION,
				true
			);
		}
	}

	/**
	 * Renderizar páxina de opcións
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non tes permisos para acceder a esta páxina.', 'postais-nadal' ) );
		}

		$settings = get_option( 'postais_nadal_settings', array() );
		include POSTAIS_NADAL_PATH . 'templates/admin-page.php';
	}

	/**
	 * Renderizar páxina de estatísticas
	 */
	public function render_stats_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non tes permisos para acceder a esta páxina.', 'postais-nadal' ) );
		}

		$database = new Postais_Nadal_Database();
		$stats = $database->get_stats();

		// Paxinación
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		$per_page = in_array( $per_page, array( 20, 40 ), true ) ? $per_page : 20;
		$offset = ( $current_page - 1 ) * $per_page;

		$postals = $database->get_all_postals( $per_page, $offset );
		$total = $database->get_total_count();
		$total_pages = ceil( $total / $per_page );

		include POSTAIS_NADAL_PATH . 'templates/admin-stats.php';
	}

	/**
	 * Manexar subida de imaxes
	 */
	public function handle_image_upload() {
		// Verificar permisos e nonce
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		// Verificar se hai arquivo
		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( array( 'message' => 'Non se enviou ningún arquivo' ) );
		}

		$file = $_FILES['image'];

		// Validar tamaño (máximo 5MB)
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => 'O arquivo é demasiado grande. Máximo 5MB.' ) );
		}

		// Validar tipo MIME
		$allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp' );

		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => 'Tipo de arquivo non válido' ) );
		}

		// Obter categoría (postal, instagram, historia)
		$categoria = isset( $_POST['categoria'] ) ? sanitize_key( $_POST['categoria'] ) : 'postal';
		$categorias_validas = array( 'postal', 'instagram', 'historia' );
		if ( ! in_array( $categoria, $categorias_validas, true ) ) {
			$categoria = 'postal';
		}

		// Mover arquivo a subcarpeta por categoría
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/postais-nadal/bases/' . $categoria . '/';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$filename = sanitize_file_name( $file['name'] );
		$unique_filename = wp_unique_filename( $target_dir, $filename );
		$target_file = $target_dir . $unique_filename;

		if ( move_uploaded_file( $file['tmp_name'], $target_file ) ) {
			$image_url = $upload_dir['baseurl'] . '/postais-nadal/bases/' . $categoria . '/' . $unique_filename;

			// Engadir á lista de imaxes da categoría
			$settings = get_option( 'postais_nadal_settings', array() );
			$key = 'imaxes_' . $categoria;
			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = array();
			}
			$settings[ $key ][] = $image_url;
			update_option( 'postais_nadal_settings', $settings );

			wp_send_json_success( array(
				'url'    => $image_url,
				'source' => 'upload',
				'message' => 'Imaxe subida correctamente',
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Erro ao gardar o arquivo' ) );
		}
	}

	/**
	 * Manexar eliminación de imaxes
	 */
	public function handle_image_delete() {
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( $_POST['image_url'] ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : 'upload';

		if ( empty( $image_url ) ) {
			wp_send_json_error( array( 'message' => 'URL non válida' ) );
		}

		// Obter categoría
		$categoria = isset( $_POST['categoria'] ) ? sanitize_key( $_POST['categoria'] ) : 'postal';
		$categorias_validas = array( 'postal', 'instagram', 'historia' );
		if ( ! in_array( $categoria, $categorias_validas, true ) ) {
			$categoria = 'postal';
		}

		// Se é do plugin, borrar o arquivo físico
		if ( 'plugin' === $source ) {
			$plugin_base_url = POSTAIS_NADAL_URL . 'assets/images/bases/' . $categoria . '/';
			$plugin_base_path = POSTAIS_NADAL_PATH . 'assets/images/bases/' . $categoria . '/';

			if ( strpos( $image_url, $plugin_base_url ) === 0 ) {
				$filename = basename( $image_url );
				$file_path = $plugin_base_path . $filename;

				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
					wp_send_json_success( array( 'message' => 'Imaxe do plugin eliminada' ) );
				}
			}
			wp_send_json_error( array( 'message' => 'Non se puido eliminar a imaxe do plugin' ) );
		}

		// Se é upload, borrar de settings e do disco
		$settings = get_option( 'postais_nadal_settings', array() );
		$key_settings = 'imaxes_' . $categoria;

		if ( isset( $settings[ $key_settings ] ) ) {
			$key = array_search( $image_url, $settings[ $key_settings ], true );
			if ( false !== $key ) {
				unset( $settings[ $key_settings ][ $key ] );
				$settings[ $key_settings ] = array_values( $settings[ $key_settings ] );
				update_option( 'postais_nadal_settings', $settings );

				// Borrar arquivo físico
				$upload_dir = wp_upload_dir();
				$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );
				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}

				wp_send_json_success( array( 'message' => 'Imaxe eliminada' ) );
			}
		}

		wp_send_json_error( array( 'message' => 'Imaxe non atopada' ) );
	}

	/**
	 * Manexar subida de stickers
	 */
	public function handle_sticker_upload() {
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		if ( empty( $_FILES['sticker'] ) ) {
			wp_send_json_error( array( 'message' => 'Non se enviou ningún arquivo' ) );
		}

		$file = $_FILES['sticker'];

		// Validar tamaño (máximo 2MB para stickers)
		$max_size = 2 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => 'O arquivo é demasiado grande. Máximo 2MB.' ) );
		}

		// Só PNG para stickers (transparencia)
		if ( 'image/png' !== $file['type'] ) {
			wp_send_json_error( array( 'message' => 'Só se permiten arquivos PNG para stickers' ) );
		}

		// Mover arquivo
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/postais-nadal/stickers/';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$filename = sanitize_file_name( $file['name'] );
		$unique_filename = wp_unique_filename( $target_dir, $filename );
		$target_file = $target_dir . $unique_filename;

		if ( move_uploaded_file( $file['tmp_name'], $target_file ) ) {
			$sticker_url = $upload_dir['baseurl'] . '/postais-nadal/stickers/' . $unique_filename;

			// Engadir á lista de stickers
			$settings = get_option( 'postais_nadal_settings', array() );
			if ( ! isset( $settings['stickers'] ) ) {
				$settings['stickers'] = array();
			}
			$settings['stickers'][] = $sticker_url;
			update_option( 'postais_nadal_settings', $settings );

			wp_send_json_success( array(
				'url'     => $sticker_url,
				'source'  => 'upload',
				'message' => 'Sticker subido correctamente',
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Erro ao gardar o arquivo' ) );
		}
	}

	/**
	 * Manexar eliminación de stickers
	 */
	public function handle_sticker_delete() {
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		$sticker_url = isset( $_POST['sticker_url'] ) ? esc_url_raw( $_POST['sticker_url'] ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : 'upload';

		if ( empty( $sticker_url ) ) {
			wp_send_json_error( array( 'message' => 'URL non válida' ) );
		}

		// Se é do plugin, borrar o arquivo físico
		if ( 'plugin' === $source ) {
			$plugin_base_url = POSTAIS_NADAL_URL . 'assets/images/stickers/';
			$plugin_base_path = POSTAIS_NADAL_PATH . 'assets/images/stickers/';

			if ( strpos( $sticker_url, $plugin_base_url ) === 0 ) {
				$filename = basename( $sticker_url );
				$file_path = $plugin_base_path . $filename;

				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
					wp_send_json_success( array( 'message' => 'Sticker do plugin eliminado' ) );
				}
			}
			wp_send_json_error( array( 'message' => 'Non se puido eliminar o sticker do plugin' ) );
		}

		// Se é upload, borrar de settings e do disco
		$settings = get_option( 'postais_nadal_settings', array() );

		if ( isset( $settings['stickers'] ) ) {
			$key = array_search( $sticker_url, $settings['stickers'], true );
			if ( false !== $key ) {
				unset( $settings['stickers'][ $key ] );
				$settings['stickers'] = array_values( $settings['stickers'] );
				update_option( 'postais_nadal_settings', $settings );

				// Borrar arquivo físico
				$upload_dir = wp_upload_dir();
				$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $sticker_url );
				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}

				wp_send_json_success( array( 'message' => 'Sticker eliminado' ) );
			}
		}

		wp_send_json_error( array( 'message' => 'Sticker non atopado' ) );
	}

	/**
	 * Gardar configuración
	 */
	public function handle_save_settings() {
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		$settings = get_option( 'postais_nadal_settings', array() );

		// Fontes e límites
		$settings['fontes'] = isset( $_POST['fontes'] ) ? array_map( 'sanitize_text_field', $_POST['fontes'] ) : array();
		$settings['limite_caracteres'] = isset( $_POST['limite_caracteres'] ) ? absint( $_POST['limite_caracteres'] ) : 150;

		// Cores principais
		$settings['cor_primaria'] = isset( $_POST['cor_primaria'] ) ? sanitize_hex_color( $_POST['cor_primaria'] ) : '#2563eb';
		$settings['cor_primaria_hover'] = isset( $_POST['cor_primaria_hover'] ) ? sanitize_hex_color( $_POST['cor_primaria_hover'] ) : '#1d4ed8';

		// Fondos e textos
		$settings['modal_cor_fondo'] = isset( $_POST['modal_cor_fondo'] ) ? sanitize_hex_color( $_POST['modal_cor_fondo'] ) : '#ffffff';
		$settings['cor_fondo_sutil'] = isset( $_POST['cor_fondo_sutil'] ) ? sanitize_hex_color( $_POST['cor_fondo_sutil'] ) : '#f9fafb';
		$settings['modal_cor_texto'] = isset( $_POST['modal_cor_texto'] ) ? sanitize_hex_color( $_POST['modal_cor_texto'] ) : '#1f2937';
		$settings['modal_cor_texto_secundario'] = isset( $_POST['modal_cor_texto_secundario'] ) ? sanitize_hex_color( $_POST['modal_cor_texto_secundario'] ) : '#6b7280';
		$settings['cor_borde'] = isset( $_POST['cor_borde'] ) ? sanitize_hex_color( $_POST['cor_borde'] ) : '#e5e7eb';

		// Botóns de acción
		$settings['cor_descarga'] = isset( $_POST['cor_descarga'] ) ? sanitize_hex_color( $_POST['cor_descarga'] ) : '#10b981';
		$settings['cor_exito'] = isset( $_POST['cor_exito'] ) ? sanitize_hex_color( $_POST['cor_exito'] ) : '#10b981';
		$settings['cor_perigo'] = isset( $_POST['cor_perigo'] ) ? sanitize_hex_color( $_POST['cor_perigo'] ) : '#ef4444';
		$settings['cor_whatsapp'] = isset( $_POST['cor_whatsapp'] ) ? sanitize_hex_color( $_POST['cor_whatsapp'] ) : '#25d366';

		// Tabs de formato
		$settings['cor_tab_activa'] = isset( $_POST['cor_tab_activa'] ) ? sanitize_hex_color( $_POST['cor_tab_activa'] ) : '#2563eb';
		$settings['cor_tab_inactiva'] = isset( $_POST['cor_tab_inactiva'] ) ? sanitize_hex_color( $_POST['cor_tab_inactiva'] ) : '#6b7280';
		$settings['cor_tab_fondo_hover'] = isset( $_POST['cor_tab_fondo_hover'] ) ? sanitize_hex_color( $_POST['cor_tab_fondo_hover'] ) : '#f3f4f6';

		// Estilo
		$settings['radio_bordes'] = isset( $_POST['radio_bordes'] ) ? absint( $_POST['radio_bordes'] ) : 12;

		update_option( 'postais_nadal_settings', $settings );

		wp_send_json_success( array( 'message' => 'Configuración gardada correctamente' ) );
	}

	/**
	 * Renderizar páxina de exportación CSV
	 */
	public function render_export_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non tes permisos para acceder a esta páxina.', 'postais-nadal' ) );
		}

		$database = new Postais_Nadal_Database();
		$total = $database->get_total_count();
		$export_url = wp_nonce_url( admin_url( 'admin.php?page=postais-nadal-export&action=download_csv' ), 'postais_export_csv' );
		?>
		<div class="wrap postais-nadal-export">
			<h1>Exportar Estatísticas a CSV</h1>
			<div class="postais-export-box">
				<p>Exporta todas as postais xeradas a un ficheiro CSV.</p>
				<p><strong>Total de rexistros:</strong> <?php echo esc_html( $total ); ?></p>
				<?php if ( $total > 0 ) : ?>
					<p>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary button-large">
							<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
							Descargar CSV
						</a>
					</p>
				<?php else : ?>
					<p><em>Non hai datos para exportar.</em></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Manexar descarga de CSV
	 */
	public function handle_csv_export() {
		if ( ! isset( $_GET['page'] ) || 'postais-nadal-export' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'download_csv' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sen permisos' );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'postais_export_csv' ) ) {
			wp_die( 'Nonce inválido' );
		}

		$database = new Postais_Nadal_Database();
		$filename = 'postais-nadal-export-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// BOM para Excel
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Cabeceiras
		fputcsv( $output, array( 'ID', 'Email', 'IP', 'Data', 'URL Imaxe' ), ';' );

		// Exportar en lotes para evitar timeout/memoria
		$batch_size = 500;
		$offset = 0;

		do {
			$postals = $database->get_all_postals( $batch_size, $offset );

			foreach ( $postals as $postal ) {
				fputcsv( $output, array(
					$postal['id'],
					$postal['email'],
					$postal['ip'],
					$postal['timestamp'],
					$postal['image_url'],
				), ';' );
			}

			$offset += $batch_size;

			// Liberar memoria
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		} while ( count( $postals ) === $batch_size );

		fclose( $output );
		exit;
	}

	/**
	 * Manexar borrado de postais (individual ou en lote)
	 */
	public function handle_delete_postals() {
		check_ajax_referer( 'postais_nadal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sen permisos' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'Non se seleccionou ningunha postal' ) );
		}

		$database = new Postais_Nadal_Database();
		$upload_dir = wp_upload_dir();
		$deleted = 0;

		foreach ( $ids as $id ) {
			$postal = $database->get_postal_by_id( $id );

			if ( $postal ) {
				// Borrar ficheiro físico
				if ( ! empty( $postal['image_url'] ) ) {
					$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $postal['image_url'] );
					if ( file_exists( $file_path ) ) {
						@unlink( $file_path );
					}
				}

				// Borrar rexistro da BBDD
				if ( $database->delete_postal( $id ) ) {
					$deleted++;
				}
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( '%d postal(s) borrada(s) correctamente', $deleted ),
			'deleted' => $deleted,
		) );
	}
}
