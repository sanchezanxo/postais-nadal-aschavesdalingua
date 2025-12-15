<?php
/**
 * Template para a páxina de opcións do admin
 *
 * @package Postais_Nadal
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $settings ) ? $settings : get_option( 'postais_nadal_settings', array() );

// Instanciar admin para usar as funcións de escaneo
$admin_instance = new Postais_Nadal_Admin();

// Imaxes por categoría (plugin + uploads)
$imaxes_postal = $admin_instance->get_all_images( 'postal' );
$imaxes_instagram = $admin_instance->get_all_images( 'instagram' );
$imaxes_historia = $admin_instance->get_all_images( 'historia' );

// Stickers (plugin + uploads)
$stickers = $admin_instance->get_all_stickers();

$fontes = isset( $settings['fontes'] ) ? $settings['fontes'] : array();
$limite_caracteres = isset( $settings['limite_caracteres'] ) ? $settings['limite_caracteres'] : 150;
// Cores do modal
$modal_cor_fondo = isset( $settings['modal_cor_fondo'] ) ? $settings['modal_cor_fondo'] : '#ffffff';
$modal_cor_texto = isset( $settings['modal_cor_texto'] ) ? $settings['modal_cor_texto'] : '#1f2937';
$modal_cor_texto_secundario = isset( $settings['modal_cor_texto_secundario'] ) ? $settings['modal_cor_texto_secundario'] : '#6b7280';

// Cores principais (botóns primarios, acentos)
$cor_primaria = isset( $settings['cor_primaria'] ) ? $settings['cor_primaria'] : '#2563eb';
$cor_primaria_hover = isset( $settings['cor_primaria_hover'] ) ? $settings['cor_primaria_hover'] : '#1d4ed8';

// Cores secundarias (bordes, fondos sutís)
$cor_borde = isset( $settings['cor_borde'] ) ? $settings['cor_borde'] : '#e5e7eb';
$cor_fondo_sutil = isset( $settings['cor_fondo_sutil'] ) ? $settings['cor_fondo_sutil'] : '#f9fafb';

// Cores de estado
$cor_exito = isset( $settings['cor_exito'] ) ? $settings['cor_exito'] : '#10b981';
$cor_perigo = isset( $settings['cor_perigo'] ) ? $settings['cor_perigo'] : '#ef4444';

// Botón de descarga (pode ser diferente do éxito)
$cor_descarga = isset( $settings['cor_descarga'] ) ? $settings['cor_descarga'] : '#10b981';

// WhatsApp
$cor_whatsapp = isset( $settings['cor_whatsapp'] ) ? $settings['cor_whatsapp'] : '#25d366';

// Overlay do modal
$cor_overlay = isset( $settings['cor_overlay'] ) ? $settings['cor_overlay'] : 'rgba(0, 0, 0, 0.6)';

// Radio dos bordes
$radio_bordes = isset( $settings['radio_bordes'] ) ? $settings['radio_bordes'] : '12';

// Tamaños de fonte
$tamanho_fonte_titulo = isset( $settings['tamanho_fonte_titulo'] ) ? $settings['tamanho_fonte_titulo'] : '1.25';
$tamanho_fonte_botons = isset( $settings['tamanho_fonte_botons'] ) ? $settings['tamanho_fonte_botons'] : '0.875';

// Cores das tabs
$cor_tab_activa = isset( $settings['cor_tab_activa'] ) ? $settings['cor_tab_activa'] : '#2563eb';
$cor_tab_inactiva = isset( $settings['cor_tab_inactiva'] ) ? $settings['cor_tab_inactiva'] : '#6b7280';
$cor_tab_fondo_hover = isset( $settings['cor_tab_fondo_hover'] ) ? $settings['cor_tab_fondo_hover'] : '#f3f4f6';

// Categorías de imaxes
$categorias = array(
	'postal' => array(
		'nome'   => 'Postal',
		'desc'   => 'Formato horizontal clásico (3:2)',
		'icona'  => 'dashicons-format-image',
		'imaxes' => $imaxes_postal,
	),
	'instagram' => array(
		'nome'   => 'Instagram',
		'desc'   => 'Formato vertical para feed (4:5)',
		'icona'  => 'dashicons-instagram',
		'imaxes' => $imaxes_instagram,
	),
	'historia' => array(
		'nome'   => 'Historia',
		'desc'   => 'Formato vertical para Stories (9:16)',
		'icona'  => 'dashicons-smartphone',
		'imaxes' => $imaxes_historia,
	),
);
?>

<div class="wrap postais-nadal-admin">
	<h1>Postais de Nadal - Opcións</h1>

	<div class="postais-admin-notice" style="display:none;"></div>

	<form id="postais-settings-form" method="post">
		<?php wp_nonce_field( 'postais_nadal_admin', 'postais_nonce' ); ?>

		<!-- Sección: Imaxes Base con Pestanas -->
		<div class="postais-section">
			<h2>Imaxes Base das Postais</h2>
			<p class="description">Sube as imaxes que os usuarios poderán escoller como fondo. Organiza por formato.</p>

			<!-- Pestanas -->
			<div class="postais-tabs">
				<?php $first = true; ?>
				<?php foreach ( $categorias as $key => $cat ) : ?>
					<button type="button" class="postais-tab <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
						<span class="dashicons <?php echo esc_attr( $cat['icona'] ); ?>"></span>
						<?php echo esc_html( $cat['nome'] ); ?>
						<span class="postais-tab-count"><?php echo count( $cat['imaxes'] ); ?></span>
					</button>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</div>

			<!-- Contido das pestanas -->
			<?php $first = true; ?>
			<?php foreach ( $categorias as $key => $cat ) : ?>
				<div class="postais-tab-content <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
					<p class="postais-tab-desc"><?php echo esc_html( $cat['desc'] ); ?></p>

					<div class="postais-image-uploader">
						<input type="file" class="image-upload-input" data-categoria="<?php echo esc_attr( $key ); ?>" accept="image/*" style="display:none;">
						<button type="button" class="button button-primary upload-image-btn" data-categoria="<?php echo esc_attr( $key ); ?>">
							<span class="dashicons dashicons-upload"></span>
							Subir Imaxe
						</button>
					</div>

					<div class="postais-image-grid" data-categoria="<?php echo esc_attr( $key ); ?>">
						<?php if ( ! empty( $cat['imaxes'] ) ) : ?>
							<?php foreach ( $cat['imaxes'] as $imaxe_data ) : ?>
								<?php
								$imaxe_url = is_array( $imaxe_data ) ? $imaxe_data['url'] : $imaxe_data;
								$imaxe_source = is_array( $imaxe_data ) ? $imaxe_data['source'] : 'upload';
								$badge_text = ( 'plugin' === $imaxe_source ) ? 'Por defecto' : 'Personalizada';
								$badge_class = ( 'plugin' === $imaxe_source ) ? 'badge-plugin' : 'badge-upload';
								?>
								<div class="postais-image-item">
									<span class="postais-image-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
									<img src="<?php echo esc_url( $imaxe_url ); ?>" alt="Imaxe base">
									<button type="button" class="button postais-delete-image" data-url="<?php echo esc_url( $imaxe_url ); ?>" data-categoria="<?php echo esc_attr( $key ); ?>" data-source="<?php echo esc_attr( $imaxe_source ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="no-images">Non hai imaxes nesta categoría.</p>
						<?php endif; ?>
					</div>
				</div>
				<?php $first = false; ?>
			<?php endforeach; ?>
		</div>

		<!-- Sección: Stickers/Formas -->
		<div class="postais-section">
			<h2>Stickers e Formas</h2>
			<p class="description">Sube imaxes PNG con transparencia que os usuarios poderán engadir ás súas postais (decoracións, debuxos, iconas...).</p>

			<div class="postais-image-uploader">
				<input type="file" class="sticker-upload-input" accept="image/png" style="display:none;">
				<button type="button" class="button button-primary upload-sticker-btn">
					<span class="dashicons dashicons-star-filled"></span>
					Subir Sticker (PNG)
				</button>
			</div>

			<div class="postais-sticker-grid">
				<?php if ( ! empty( $stickers ) ) : ?>
					<?php foreach ( $stickers as $sticker_data ) : ?>
						<?php
						$sticker_url = is_array( $sticker_data ) ? $sticker_data['url'] : $sticker_data;
						$sticker_source = is_array( $sticker_data ) ? $sticker_data['source'] : 'upload';
						$badge_text = ( 'plugin' === $sticker_source ) ? 'Por defecto' : 'Personalizado';
						$badge_class = ( 'plugin' === $sticker_source ) ? 'badge-plugin' : 'badge-upload';
						?>
						<div class="postais-sticker-item">
							<span class="postais-sticker-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
							<img src="<?php echo esc_url( $sticker_url ); ?>" alt="Sticker">
							<button type="button" class="button postais-delete-sticker" data-url="<?php echo esc_url( $sticker_url ); ?>" data-source="<?php echo esc_attr( $sticker_source ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="no-stickers">Non hai stickers. Sube imaxes PNG con transparencia.</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Sección: Fontes -->
		<div class="postais-section">
			<h2>Fontes Dispoñibles</h2>
			<p class="description">Lista de fontes de Google Fonts que os usuarios poderán usar (unha por liña).</p>

			<textarea name="fontes" id="fontes-textarea" rows="10" class="large-text"><?php
				echo esc_textarea( implode( "\n", $fontes ) );
			?></textarea>

			<p class="description">
				<em>Exemplo: Roboto, Open Sans, Lato, Montserrat, Raleway</em><br>
				<a href="https://fonts.google.com/" target="_blank">Ver fontes dispoñibles en Google Fonts</a>
			</p>
		</div>

		<!-- Sección: Aparencia do Modal -->
		<div class="postais-section">
			<h2>Aparencia do Modal</h2>
			<p class="description">Personaliza as cores e estilos do xerador de postais.</p>

			<!-- Subsección: Cores Principais -->
			<h3 class="postais-subsection-title">Cores Principais</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cor_primaria">Cor Primaria</label>
					</th>
					<td>
						<input type="color" id="cor_primaria" name="cor_primaria" value="<?php echo esc_attr( $cor_primaria ); ?>">
						<span class="description">Botóns principais, tabs activas, acentos</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_primaria_hover">Cor Primaria (Hover)</label>
					</th>
					<td>
						<input type="color" id="cor_primaria_hover" name="cor_primaria_hover" value="<?php echo esc_attr( $cor_primaria_hover ); ?>">
						<span class="description">Cor ao pasar o rato por riba</span>
					</td>
				</tr>
			</table>

			<!-- Subsección: Fondos e Textos -->
			<h3 class="postais-subsection-title">Fondos e Textos</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="modal_cor_fondo">Fondo do Modal</label>
					</th>
					<td>
						<input type="color" id="modal_cor_fondo" name="modal_cor_fondo" value="<?php echo esc_attr( $modal_cor_fondo ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_fondo_sutil">Fondo Sutil</label>
					</th>
					<td>
						<input type="color" id="cor_fondo_sutil" name="cor_fondo_sutil" value="<?php echo esc_attr( $cor_fondo_sutil ); ?>">
						<span class="description">Controis, áreas de canvas</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="modal_cor_texto">Texto Principal</label>
					</th>
					<td>
						<input type="color" id="modal_cor_texto" name="modal_cor_texto" value="<?php echo esc_attr( $modal_cor_texto ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="modal_cor_texto_secundario">Texto Secundario</label>
					</th>
					<td>
						<input type="color" id="modal_cor_texto_secundario" name="modal_cor_texto_secundario" value="<?php echo esc_attr( $modal_cor_texto_secundario ); ?>">
						<span class="description">Labels, descripcións, placeholders</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_borde">Cor dos Bordes</label>
					</th>
					<td>
						<input type="color" id="cor_borde" name="cor_borde" value="<?php echo esc_attr( $cor_borde ); ?>">
					</td>
				</tr>
			</table>

			<!-- Subsección: Botóns de Acción -->
			<h3 class="postais-subsection-title">Botóns de Acción</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cor_descarga">Botón Descargar</label>
					</th>
					<td>
						<input type="color" id="cor_descarga" name="cor_descarga" value="<?php echo esc_attr( $cor_descarga ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_exito">Cor de Éxito</label>
					</th>
					<td>
						<input type="color" id="cor_exito" name="cor_exito" value="<?php echo esc_attr( $cor_exito ); ?>">
						<span class="description">Mensaxes de éxito, confirmacións</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_perigo">Cor de Perigo</label>
					</th>
					<td>
						<input type="color" id="cor_perigo" name="cor_perigo" value="<?php echo esc_attr( $cor_perigo ); ?>">
						<span class="description">Botón eliminar, erros</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_whatsapp">Botón WhatsApp</label>
					</th>
					<td>
						<input type="color" id="cor_whatsapp" name="cor_whatsapp" value="<?php echo esc_attr( $cor_whatsapp ); ?>">
					</td>
				</tr>
			</table>

			<!-- Subsección: Tabs de Formato -->
			<h3 class="postais-subsection-title">Tabs de Formato</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cor_tab_activa">Tab Activa</label>
					</th>
					<td>
						<input type="color" id="cor_tab_activa" name="cor_tab_activa" value="<?php echo esc_attr( $cor_tab_activa ); ?>">
						<span class="description">Cor do texto e borde inferior</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_tab_inactiva">Tab Inactiva</label>
					</th>
					<td>
						<input type="color" id="cor_tab_inactiva" name="cor_tab_inactiva" value="<?php echo esc_attr( $cor_tab_inactiva ); ?>">
						<span class="description">Cor do texto das tabs non seleccionadas</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cor_tab_fondo_hover">Tab Hover</label>
					</th>
					<td>
						<input type="color" id="cor_tab_fondo_hover" name="cor_tab_fondo_hover" value="<?php echo esc_attr( $cor_tab_fondo_hover ); ?>">
						<span class="description">Fondo ao pasar o rato</span>
					</td>
				</tr>
			</table>

			<!-- Subsección: Estilo -->
			<h3 class="postais-subsection-title">Estilo</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="radio_bordes">Radio dos Bordes</label>
					</th>
					<td>
						<input type="number" id="radio_bordes" name="radio_bordes" value="<?php echo esc_attr( $radio_bordes ); ?>" min="0" max="24" class="small-text"> px
						<span class="description">Redondeo das esquinas (0 = cadrado, 12 = suave)</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tamanho_fonte_titulo">Tamaño Títulos</label>
					</th>
					<td>
						<input type="number" id="tamanho_fonte_titulo" name="tamanho_fonte_titulo" value="<?php echo esc_attr( $tamanho_fonte_titulo ); ?>" min="0.75" max="3" step="0.125" class="small-text"> rem
						<span class="description">Tamaño dos títulos dos pasos (1.25 por defecto)</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tamanho_fonte_botons">Tamaño Botóns</label>
					</th>
					<td>
						<input type="number" id="tamanho_fonte_botons" name="tamanho_fonte_botons" value="<?php echo esc_attr( $tamanho_fonte_botons ); ?>" min="0.5" max="2" step="0.125" class="small-text"> rem
						<span class="description">Tamaño do texto dos botóns (0.875 por defecto)</span>
					</td>
				</tr>
			</table>
		</div>

		<!-- Sección: Límites -->
		<div class="postais-section">
			<h2>Límites</h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="limite_caracteres">Límite de Caracteres</label>
					</th>
					<td>
						<input type="number" id="limite_caracteres" name="limite_caracteres" value="<?php echo esc_attr( $limite_caracteres ); ?>" min="10" max="500" class="small-text">
						<p class="description">Número máximo de caracteres permitidos no texto da postal.</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary" id="save-settings-btn">
				Gardar Cambios
			</button>
		</p>
	</form>
</div>
