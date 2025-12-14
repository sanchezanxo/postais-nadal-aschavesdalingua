<?php
/**
 * Template do modal minimalista
 *
 * @package Postais_Nadal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'postais_nadal_settings', array() );

// Instanciar frontend para usar as funcións de escaneo combinadas
$frontend_instance = new Postais_Nadal_Frontend();

// Imaxes por categoría (plugin + uploads)
$imaxes_postal = $frontend_instance->get_images_for_frontend( 'postal' );
$imaxes_instagram = $frontend_instance->get_images_for_frontend( 'instagram' );
$imaxes_historia = $frontend_instance->get_images_for_frontend( 'historia' );

// Stickers (plugin + uploads)
$stickers = $frontend_instance->get_stickers_for_frontend();

$fontes = isset( $settings['fontes'] ) ? $settings['fontes'] : array();
?>

<div id="postal-modal" class="postal-modal" style="display:none">
	<div class="postal-overlay"></div>
	<div class="postal-container">
		<button class="postal-close" aria-label="Pechar"><i class="fas fa-times"></i></button>

		<!-- Paso 1: Escoller formato e imaxe -->
		<div class="postal-step postal-step-1 active">
			<h2>Escolle a imaxe</h2>

			<!-- Tabs de formato -->
			<nav class="postal-format-selector" role="tablist">
				<button type="button" class="postal-format-btn active" data-formato="postal" role="tab">
					<i class="fas fa-image"></i>
					<span class="postal-format-name">Postal</span>
				</button>
				<button type="button" class="postal-format-btn" data-formato="instagram" role="tab">
					<i class="fas fa-th-large"></i>
					<span class="postal-format-name">Instagram</span>
				</button>
				<button type="button" class="postal-format-btn" data-formato="historia" role="tab">
					<i class="fas fa-mobile-alt"></i>
					<span class="postal-format-name">Historia</span>
				</button>
			</nav>

			<!-- Galería: Postal -->
			<div class="postal-gallery active" data-formato="postal" role="tabpanel">
				<?php if ( ! empty( $imaxes_postal ) ) : ?>
					<?php foreach ( $imaxes_postal as $index => $imaxe ) : ?>
						<div class="postal-gallery-item" data-image="<?php echo esc_url( $imaxe ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
							<img src="<?php echo esc_url( $imaxe ); ?>" alt="Imaxe de Nadal <?php echo esc_attr( $index + 1 ); ?>" loading="lazy">
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="postal-no-images">Non hai imaxes</p>
				<?php endif; ?>
			</div>

			<!-- Galería: Instagram -->
			<div class="postal-gallery" data-formato="instagram" role="tabpanel">
				<?php if ( ! empty( $imaxes_instagram ) ) : ?>
					<?php foreach ( $imaxes_instagram as $index => $imaxe ) : ?>
						<div class="postal-gallery-item" data-image="<?php echo esc_url( $imaxe ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
							<img src="<?php echo esc_url( $imaxe ); ?>" alt="Imaxe de Nadal <?php echo esc_attr( $index + 1 ); ?>" loading="lazy">
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="postal-no-images">Non hai imaxes</p>
				<?php endif; ?>
			</div>

			<!-- Galería: Historia -->
			<div class="postal-gallery" data-formato="historia" role="tabpanel">
				<?php if ( ! empty( $imaxes_historia ) ) : ?>
					<?php foreach ( $imaxes_historia as $index => $imaxe ) : ?>
						<div class="postal-gallery-item" data-image="<?php echo esc_url( $imaxe ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
							<img src="<?php echo esc_url( $imaxe ); ?>" alt="Imaxe de Nadal <?php echo esc_attr( $index + 1 ); ?>" loading="lazy">
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="postal-no-images">Non hai imaxes</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Paso 2: Deseñar postal -->
		<div class="postal-step postal-step-2">
			<h2>Deseña a túa postal</h2>
			<div class="postal-editor">
				<div class="postal-canvas-wrapper">
					<div class="postal-canvas-hint"><i class="fas fa-hand-pointer"></i> Toca para engadir texto</div>
					<canvas id="postal-canvas"></canvas>
				</div>
				<div class="postal-controls">
					<!-- Panel de Stickers (Acordeón) -->
					<?php if ( ! empty( $stickers ) ) : ?>
					<div class="postal-accordion">
						<button type="button" class="postal-accordion-toggle" aria-expanded="true">
							<span class="postal-accordion-icon"><i class="fas fa-star"></i></span>
							<span class="postal-accordion-title">Stickers</span>
							<span class="postal-accordion-count"><?php echo count( $stickers ); ?></span>
							<span class="postal-accordion-arrow"><i class="fas fa-chevron-down"></i></span>
						</button>
						<div class="postal-accordion-content">
							<div class="postal-stickers-grid">
								<?php foreach ( $stickers as $index => $sticker ) : ?>
									<div class="postal-sticker-btn" data-sticker="<?php echo esc_url( $sticker ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
										<img src="<?php echo esc_url( $sticker ); ?>" alt="Sticker decorativo <?php echo esc_attr( $index + 1 ); ?>" loading="lazy">
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<?php endif; ?>

					<!-- Opcións de texto (aparece ao seleccionar texto) -->
					<div class="postal-control-section" id="text-options" style="display:none">
						<div class="postal-control-group">
							<label for="text-input">Texto</label>
							<input type="text" id="text-input" maxlength="<?php echo esc_attr( isset( $settings['limite_caracteres'] ) ? $settings['limite_caracteres'] : 150 ); ?>" placeholder="Escribe aquí...">
							<span class="postal-char-count">0 / <?php echo esc_html( isset( $settings['limite_caracteres'] ) ? $settings['limite_caracteres'] : 150 ); ?></span>
						</div>
						<div class="postal-control-group">
							<label for="font-select">Fonte</label>
							<select id="font-select">
								<?php foreach ( $fontes as $fonte ) : ?>
									<option value="<?php echo esc_attr( $fonte ); ?>"><?php echo esc_html( $fonte ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="postal-control-group">
							<label for="color-picker">Cor</label>
							<input type="color" id="color-picker" value="#ffffff">
						</div>
						<p class="postal-resize-hint">
							<i class="fas fa-arrows-alt"></i> Arrastra a esquina para redimensionar
						</p>
						<div class="postal-control-buttons">
							<button type="button" id="delete-text" class="postal-delete-link">
								<i class="fas fa-trash"></i> Eliminar
							</button>
						</div>
					</div>

					<!-- Opcións de sticker (aparece ao seleccionar sticker) -->
					<div class="postal-control-section" id="sticker-options" style="display:none">
						<label class="postal-section-label"><i class="fas fa-star"></i> Sticker seleccionado</label>
						<p class="postal-resize-hint">
							<i class="fas fa-arrows-alt"></i> Arrastra para mover, esquina para redimensionar
						</p>
						<div class="postal-control-buttons">
							<button type="button" id="delete-sticker" class="postal-delete-link">
								<i class="fas fa-trash"></i> Eliminar sticker
							</button>
						</div>
					</div>
				</div>
			</div>
			<nav class="postal-nav">
				<button type="button" class="postal-btn postal-btn-sm postal-nav-left postal-back-step1">
					<i class="fas fa-arrow-left"></i><span>Volver</span>
				</button>
				<button type="button" class="postal-next postal-btn postal-btn-primary">
					<span>Seguinte</span><i class="fas fa-arrow-right"></i>
				</button>
			</nav>
		</div>

		<!-- Paso 3: Revisar e compartir -->
		<div class="postal-step postal-step-3">
			<h2>Comparte</h2>
			<div class="postal-editor">
				<div class="postal-preview-wrapper">
					<canvas id="postal-preview"></canvas>
				</div>
				<div class="postal-controls">
					<div class="postal-control-section">
						<form id="postal-form">
							<div class="postal-control-group">
								<label for="postal-email">O teu email</label>
								<input type="email" name="email" id="postal-email" required placeholder="email@exemplo.com">
							</div>
							<button type="submit" class="postal-btn postal-btn-primary postal-btn-lg">
								<i class="fas fa-check"></i> Xerar
							</button>
						</form>
					</div>
					<div id="postal-share" style="display:none">
						<div class="postal-success-message">
							<i class="fas fa-check-circle"></i> Listo!
						</div>
						<div class="postal-share-buttons">
							<a id="download-btn" download="postal-nadal.jpg" class="postal-social-btn postal-social-btn-download">
								<i class="fas fa-download"></i> Descargar
							</a>
							<button type="button" id="send-email-btn" class="postal-social-btn postal-social-btn-email">
								<i class="fas fa-envelope"></i> Enviar por email
							</button>
							<button type="button" id="share-whatsapp-file" class="postal-social-btn postal-social-btn-whatsapp" style="display:none">
								<i class="fab fa-whatsapp"></i> WhatsApp
							</button>
						</div>
					</div>
				</div>
			</div>
			<nav class="postal-nav">
				<button type="button" class="postal-back postal-btn postal-btn-sm postal-nav-left">
					<i class="fas fa-arrow-left"></i><span>Editar</span>
				</button>
			</nav>
		</div>

		<!-- Loading spinner -->
		<div class="postal-loading" style="display:none">
			<div class="postal-spinner"></div>
			<p>Xerando postal...</p>
		</div>
	</div>
</div>
