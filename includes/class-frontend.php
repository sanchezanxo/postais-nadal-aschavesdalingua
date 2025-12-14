<?php
/**
 * Clase para o frontend (bloque Gutenberg e modal)
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Postais_Nadal_Frontend
 */
class Postais_Nadal_Frontend {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_action( 'wp_ajax_postais_generate_image', array( $this, 'handle_generate_image' ) );
		add_action( 'wp_ajax_nopriv_postais_generate_image', array( $this, 'handle_generate_image' ) );
		add_action( 'wp_ajax_postais_send_email', array( $this, 'handle_send_email' ) );
		add_action( 'wp_ajax_nopriv_postais_send_email', array( $this, 'handle_send_email' ) );
	}

	/**
	 * Obter imaxes dunha categoría para o frontend (plugin + uploads)
	 *
	 * @param string $categoria Categoría (postal, instagram, historia).
	 * @return array Array de URLs de imaxes.
	 */
	public function get_images_for_frontend( $categoria ) {
		$images = array();

		// Escanear imaxes do plugin
		$plugin_path = POSTAIS_NADAL_PATH . 'assets/images/bases/' . $categoria . '/';
		$plugin_url = POSTAIS_NADAL_URL . 'assets/images/bases/' . $categoria . '/';

		if ( is_dir( $plugin_path ) ) {
			$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'webp' );
			$files = glob( $plugin_path . '*.*' );

			if ( $files ) {
				foreach ( $files as $file ) {
					$filetype = wp_check_filetype( $file );
					if ( in_array( strtolower( $filetype['ext'] ), $allowed_extensions, true ) ) {
						$images[] = $plugin_url . basename( $file );
					}
				}
			}
		}

		// Engadir imaxes subidas polo admin
		$settings = get_option( 'postais_nadal_settings', array() );
		$key = 'imaxes_' . $categoria;

		if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
			$images = array_merge( $images, $settings[ $key ] );
		}

		return $images;
	}

	/**
	 * Obter stickers para o frontend (plugin + uploads)
	 *
	 * @return array Array de URLs de stickers.
	 */
	public function get_stickers_for_frontend() {
		$stickers = array();

		// Escanear stickers do plugin
		$plugin_path = POSTAIS_NADAL_PATH . 'assets/images/stickers/';
		$plugin_url = POSTAIS_NADAL_URL . 'assets/images/stickers/';

		if ( is_dir( $plugin_path ) ) {
			$files = glob( $plugin_path . '*.png' );

			if ( $files ) {
				foreach ( $files as $file ) {
					$stickers[] = $plugin_url . basename( $file );
				}
			}
		}

		// Engadir stickers subidos polo admin
		$settings = get_option( 'postais_nadal_settings', array() );

		if ( isset( $settings['stickers'] ) && is_array( $settings['stickers'] ) ) {
			$stickers = array_merge( $stickers, $settings['stickers'] );
		}

		return $stickers;
	}

	/**
	 * Rexistrar bloque Gutenberg
	 */
	public function register_block() {
		// Rexistrar script do editor
		wp_register_script(
			'postais-nadal-block-editor',
			POSTAIS_NADAL_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
			POSTAIS_NADAL_VERSION,
			false
		);

		// Rexistrar bloque
		register_block_type( 'postal-nadal/boton-xerador', array(
			'editor_script' => 'postais-nadal-block-editor',
			'attributes' => array(
				'buttonText' => array(
					'type' => 'string',
					'default' => 'Crear Postal',
				),
				'buttonColor' => array(
					'type' => 'string',
					'default' => '#0073aa',
				),
				'buttonPadding' => array(
					'type' => 'string',
					'default' => '12px 24px',
				),
			),
			'render_callback' => array( $this, 'render_block' ),
		) );
	}

	/**
	 * Renderizar bloque
	 *
	 * @param array $attributes Atributos do bloque.
	 * @return string HTML do bloque.
	 */
	public function render_block( $attributes ) {
		$button_text = isset( $attributes['buttonText'] ) ? esc_html( $attributes['buttonText'] ) : 'Crear Postal';
		$button_color = isset( $attributes['buttonColor'] ) ? esc_attr( $attributes['buttonColor'] ) : '#0073aa';
		$button_padding = isset( $attributes['buttonPadding'] ) ? esc_attr( $attributes['buttonPadding'] ) : '12px 24px';

		$style = sprintf(
			'background-color: %s; padding: %s; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;',
			$button_color,
			$button_padding
		);

		return sprintf(
			'<button class="postais-nadal-trigger" data-modal-trigger style="%s">%s</button>',
			$style,
			$button_text
		);
	}

	/**
	 * Cargar scripts e estilos do frontend
	 */
	public function enqueue_frontend_scripts() {
		// Só cargar se hai bloque na páxina
		if ( ! has_block( 'postal-nadal/boton-xerador' ) && ! is_singular() ) {
			return;
		}

		// Preconnect a Google Fonts para carga rápida
		add_action( 'wp_head', function() {
			echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
			echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
		}, 1 );

		// Font Awesome
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		wp_enqueue_style(
			'postais-nadal-frontend',
			POSTAIS_NADAL_URL . 'assets/css/frontend.css',
			array(),
			POSTAIS_NADAL_VERSION
		);

		// Obter configuración
		$settings = get_option( 'postais_nadal_settings', array() );

		// Inxectar CSS personalizado con variables
		$custom_css = $this->generate_custom_css( $settings );
		wp_add_inline_style( 'postais-nadal-frontend', $custom_css );

		wp_enqueue_script(
			'postais-nadal-modal',
			POSTAIS_NADAL_URL . 'assets/js/modal-generator.js',
			array(),
			POSTAIS_NADAL_VERSION,
			true
		);

		// Cargar Google Fonts
		$fontes = isset( $settings['fontes'] ) ? $settings['fontes'] : array();

		if ( ! empty( $fontes ) ) {
			$fonts_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_map( function( $font ) {
				return str_replace( ' ', '+', $font ) . ':wght@400;700';
			}, $fontes ) ) . '&display=swap';

			wp_enqueue_style( 'postais-nadal-fonts', $fonts_url, array(), null );
		}

		// Localizar script con imaxes combinadas (plugin + uploads)
		wp_localize_script(
			'postais-nadal-modal',
			'postaisNadal',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'postais_nadal_frontend' ),
				'imaxes_postal'     => $this->get_images_for_frontend( 'postal' ),
				'imaxes_instagram'  => $this->get_images_for_frontend( 'instagram' ),
				'imaxes_historia'   => $this->get_images_for_frontend( 'historia' ),
				'fontes'            => $fontes,
				'stickers'          => $this->get_stickers_for_frontend(),
				'limite_caracteres' => isset( $settings['limite_caracteres'] ) ? $settings['limite_caracteres'] : 150,
			)
		);
	}

	/**
	 * Xerar CSS personalizado baseado na configuración
	 *
	 * @param array $settings Configuración do plugin.
	 * @return string CSS personalizado.
	 */
	private function generate_custom_css( $settings ) {
		// Valores por defecto
		$defaults = array(
			'cor_primaria'              => '#2563eb',
			'cor_primaria_hover'        => '#1d4ed8',
			'modal_cor_fondo'           => '#ffffff',
			'cor_fondo_sutil'           => '#f9fafb',
			'modal_cor_texto'           => '#1f2937',
			'modal_cor_texto_secundario' => '#6b7280',
			'cor_borde'                 => '#e5e7eb',
			'cor_descarga'              => '#10b981',
			'cor_exito'                 => '#10b981',
			'cor_perigo'                => '#ef4444',
			'cor_whatsapp'              => '#25d366',
			'radio_bordes'              => '12',
			'cor_tab_activa'            => '#2563eb',
			'cor_tab_inactiva'          => '#6b7280',
			'cor_tab_fondo_hover'       => '#f3f4f6',
		);

		// Merge con valores gardados
		$vars = array();
		foreach ( $defaults as $key => $default ) {
			$vars[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
		}

		// Calcular cor de hover para descarga (escurecer 10%)
		$cor_descarga_hover = $this->darken_color( $vars['cor_descarga'], 10 );
		$cor_whatsapp_hover = $this->darken_color( $vars['cor_whatsapp'], 10 );
		$cor_perigo_hover = $this->darken_color( $vars['cor_perigo'], 15 );

		// Xerar CSS
		$css = ":root {
	--primary: {$vars['cor_primaria']};
	--primary-hover: {$vars['cor_primaria_hover']};
	--text: {$vars['modal_cor_texto']};
	--text-light: {$vars['modal_cor_texto_secundario']};
	--border: {$vars['cor_borde']};
	--bg: {$vars['modal_cor_fondo']};
	--bg-subtle: {$vars['cor_fondo_sutil']};
	--success: {$vars['cor_exito']};
	--danger: {$vars['cor_perigo']};
	--radius: {$vars['radio_bordes']}px;
}

/* Cores personalizadas para botóns */
.postal-social-btn-download {
	background: {$vars['cor_descarga']};
}
.postal-social-btn-download:hover {
	background: {$cor_descarga_hover};
}

.postal-social-btn-whatsapp {
	background: {$vars['cor_whatsapp']};
}
.postal-social-btn-whatsapp:hover {
	background: {$cor_whatsapp_hover};
}

.postal-delete-link {
	color: {$vars['cor_perigo']};
}
.postal-delete-link:hover {
	color: {$cor_perigo_hover};
}

/* Mensaxe de éxito */
.postal-success-message {
	background: {$this->hex_to_rgba( $vars['cor_exito'], 0.15 )};
	border-color: {$this->hex_to_rgba( $vars['cor_exito'], 0.4 )};
	color: {$this->darken_color( $vars['cor_exito'], 40 )};
}

/* Tabs de formato */
.postal-format-btn {
	color: {$vars['cor_tab_inactiva']};
}
.postal-format-btn:hover {
	color: {$vars['cor_tab_activa']};
	background: {$vars['cor_tab_fondo_hover']};
}
.postal-format-btn.active {
	color: {$vars['cor_tab_activa']};
	border-bottom-color: {$vars['cor_tab_activa']};
}
.postal-format-btn.active:hover {
	color: {$vars['cor_tab_activa']};
}";

		return $css;
	}

	/**
	 * Escurecer unha cor hexadecimal
	 *
	 * @param string $hex Cor en formato hexadecimal.
	 * @param int    $percent Porcentaxe a escurecer.
	 * @return string Cor escurecida.
	 */
	private function darken_color( $hex, $percent ) {
		$hex = ltrim( $hex, '#' );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$r = max( 0, min( 255, $r - ( $r * $percent / 100 ) ) );
		$g = max( 0, min( 255, $g - ( $g * $percent / 100 ) ) );
		$b = max( 0, min( 255, $b - ( $b * $percent / 100 ) ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Converter cor hex a rgba
	 *
	 * @param string $hex   Cor en formato hexadecimal.
	 * @param float  $alpha Valor alpha (0-1).
	 * @return string Cor en formato rgba.
	 */
	private function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( $hex, '#' );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return "rgba({$r}, {$g}, {$b}, {$alpha})";
	}

	/**
	 * Renderizar modal no footer
	 */
	public function render_modal() {
		// Só renderizar se hai bloque na páxina
		if ( ! has_block( 'postal-nadal/boton-xerador' ) && ! is_singular() ) {
			return;
		}

		include POSTAIS_NADAL_PATH . 'templates/modal-template.php';
	}

	/**
	 * Manexar xeración de imaxe via AJAX
	 */
	public function handle_generate_image() {
		// Verificar nonce
		check_ajax_referer( 'postais_nadal_frontend', 'nonce' );

		// Obter datos
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$image_data = isset( $_POST['image_data'] ) ? $this->sanitize_base64_image( $_POST['image_data'] ) : '';
		$metadata = isset( $_POST['metadata'] ) ? json_decode( stripslashes( $_POST['metadata'] ), true ) : array();

		// Validar email
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email non válido' ) );
		}

		// Validar imaxe
		if ( empty( $image_data ) ) {
			wp_send_json_error( array( 'message' => 'Imaxe non válida ou demasiado grande' ) );
		}

		// Obter IP
		$ip = $this->get_client_ip();

		// Xerar e gardar imaxe
		$image_generator = new Postais_Nadal_Image_Generator();
		$result = $image_generator->save_image( $image_data, $email, $ip, $metadata );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Enviar por email se o usuario o solicitou
		$send_email = isset( $_POST['send_email'] ) && '1' === $_POST['send_email'];
		if ( $send_email && ! empty( $result['url'] ) ) {
			$this->send_postal_email( $email, $result['url'] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Manexar envío de email via AJAX (botón separado)
	 */
	public function handle_send_email() {
		// Verificar nonce
		check_ajax_referer( 'postais_nadal_frontend', 'nonce' );

		// Obter datos
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$image_data = isset( $_POST['image_data'] ) ? $this->sanitize_base64_image( $_POST['image_data'] ) : '';

		// Validar email
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email non válido' ) );
		}

		// Validar imaxe
		if ( empty( $image_data ) ) {
			wp_send_json_error( array( 'message' => 'Imaxe non válida ou demasiado grande' ) );
		}

		// Crear arquivo temporal da imaxe
		$upload_dir = wp_upload_dir();
		$temp_file = $upload_dir['basedir'] . '/postais-nadal/temp-email-' . uniqid() . '.jpg';

		// Asegurar que existe o directorio
		$dir = dirname( $temp_file );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Decodificar e gardar imaxe temporal
		$image_data = str_replace( 'data:image/jpeg;base64,', '', $image_data );
		$image_data = str_replace( ' ', '+', $image_data );
		$decoded = base64_decode( $image_data );

		if ( false === $decoded ) {
			wp_send_json_error( array( 'message' => 'Erro ao procesar a imaxe' ) );
		}

		// Gardar arquivo temporal
		$saved = file_put_contents( $temp_file, $decoded );
		if ( false === $saved ) {
			wp_send_json_error( array( 'message' => 'Erro ao gardar a imaxe' ) );
		}

		// Enviar email
		$to = $email;
		$subject = __( 'A túa postal de Nadal - As Chaves da Lingua', 'postais-nadal' );
		$message = __( "Ola!\n\nAquí tes a túa postal de Nadal.\n\nBoas festas!\n\nAs Chaves da Lingua\nhttps://aschavesdalingua.gal", 'postais-nadal' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$attachments = array( $temp_file );

		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		// Eliminar arquivo temporal
		@unlink( $temp_file );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => 'Erro ao enviar o email' ) );
		}

		wp_send_json_success( array( 'message' => 'Email enviado correctamente' ) );
	}

	/**
	 * Obter IP do cliente
	 *
	 * @return string IP do cliente.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Enviar postal por email co JPG adxunto
	 *
	 * @param string $email Email do destinatario.
	 * @param string $image_url URL da imaxe.
	 */
	private function send_postal_email( $email, $image_url ) {
		// Converter URL a path local
		$upload_dir = wp_upload_dir();
		$image_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );

		if ( ! file_exists( $image_path ) ) {
			error_log( 'Postais Nadal: Non se atopou a imaxe para enviar: ' . $image_path );
			return;
		}

		$to = $email;
		$subject = __( 'A túa postal de Nadal - As Chaves da Lingua', 'postais-nadal' );
		$message = __( "Ola!\n\nAquí tes a túa postal de Nadal.\n\nBoas festas!\n\nAs Chaves da Lingua\nhttps://aschavesdalingua.gal", 'postais-nadal' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$attachments = array( $image_path );

		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! $sent ) {
			error_log( 'Postais Nadal: Erro ao enviar email a ' . $email );
		}
	}

	/**
	 * Sanitizar e validar datos de imaxe base64
	 *
	 * @param string $image_data Datos da imaxe en base64.
	 * @return string|false Datos sanitizados ou false se non é válido.
	 */
	private function sanitize_base64_image( $image_data ) {
		// Límite de 10MB en base64 (aprox 7.5MB en imaxe real)
		$max_size = 10 * 1024 * 1024;
		if ( strlen( $image_data ) > $max_size ) {
			return false;
		}

		// Verificar formato válido de data URI para JPEG
		if ( strpos( $image_data, 'data:image/jpeg;base64,' ) !== 0 ) {
			return false;
		}

		// Extraer e validar base64
		$base64_part = substr( $image_data, 23 ); // Despois de 'data:image/jpeg;base64,'
		$decoded = base64_decode( $base64_part, true );

		if ( false === $decoded ) {
			return false;
		}

		// Verificar que é unha imaxe JPEG real (magic bytes)
		if ( substr( $decoded, 0, 2 ) !== "\xFF\xD8" ) {
			return false;
		}

		return $image_data;
	}
}
