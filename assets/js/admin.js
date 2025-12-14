/**
 * JavaScript para o panel de administración
 *
 * @package Postais_Nadal
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initTabs();
		initImageUploader();
		initImageDeleter();
		initStickerUploader();
		initStickerDeleter();
		initSettingsForm();
	});

	function initTabs() {
		$('.postais-tab').on('click', function() {
			const tab = $(this).data('tab');

			// Cambiar pestana activa
			$('.postais-tab').removeClass('active');
			$(this).addClass('active');

			// Mostrar contido
			$('.postais-tab-content').removeClass('active');
			$('.postais-tab-content[data-tab="' + tab + '"]').addClass('active');
		});
	}

	function initImageUploader() {
		$(document).on('click', '.upload-image-btn', function() {
			const categoria = $(this).data('categoria');
			$('.image-upload-input[data-categoria="' + categoria + '"]').click();
		});

		$(document).on('change', '.image-upload-input', function() {
			const file = this.files[0];
			const categoria = $(this).data('categoria');

			if (!file) {
				return;
			}

			// Validar tipo
			const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
			if (allowedTypes.indexOf(file.type) === -1) {
				showNotice('Tipo de arquivo non válido. Usa JPG, PNG ou WebP.', 'error');
				return;
			}

			// Validar tamaño (max 5MB)
			if (file.size > 5 * 1024 * 1024) {
				showNotice('O arquivo é demasiado grande. Máximo 5MB.', 'error');
				return;
			}

			const uploadBtn = $('.upload-image-btn[data-categoria="' + categoria + '"]');

			// Subir via AJAX
			const formData = new FormData();
			formData.append('action', 'postais_upload_image');
			formData.append('nonce', postaisNadalAdmin.nonce);
			formData.append('image', file);
			formData.append('categoria', categoria);

			$.ajax({
				url: postaisNadalAdmin.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				beforeSend: function() {
					uploadBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Subindo...');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
						addImageToGrid(response.data.url, categoria, response.data.source || 'upload');
						updateTabCount(categoria, 1);
					} else {
						showNotice(response.data.message, 'error');
					}
				},
				error: function() {
					showNotice('Erro ao subir a imaxe', 'error');
				},
				complete: function() {
					uploadBtn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Subir Imaxe');
					$('.image-upload-input[data-categoria="' + categoria + '"]').val('');
				}
			});
		});
	}

	function addImageToGrid(imageUrl, categoria, source) {
		const grid = $('.postais-image-grid[data-categoria="' + categoria + '"]');
		const noImages = grid.find('.no-images');

		if (noImages.length) {
			noImages.remove();
		}

		source = source || 'upload';
		const badgeText = (source === 'plugin') ? 'Por defecto' : 'Personalizada';
		const badgeClass = (source === 'plugin') ? 'badge-plugin' : 'badge-upload';

		const imageItem = $('<div class="postais-image-item">' +
			'<span class="postais-image-badge ' + badgeClass + '">' + badgeText + '</span>' +
			'<img src="' + imageUrl + '" alt="Imaxe base">' +
			'<button type="button" class="button postais-delete-image" data-url="' + imageUrl + '" data-categoria="' + categoria + '" data-source="' + source + '">' +
			'<span class="dashicons dashicons-trash"></span>' +
			'</button>' +
			'</div>');

		grid.append(imageItem);
	}

	function updateTabCount(categoria, delta) {
		const tab = $('.postais-tab[data-tab="' + categoria + '"]');
		const countSpan = tab.find('.postais-tab-count');
		const currentCount = parseInt(countSpan.text()) || 0;
		countSpan.text(currentCount + delta);
	}

	function initStickerUploader() {
		$(document).on('click', '.upload-sticker-btn', function() {
			$('.sticker-upload-input').click();
		});

		$(document).on('change', '.sticker-upload-input', function() {
			const file = this.files[0];

			if (!file) {
				return;
			}

			// Validar tipo - só PNG
			if (file.type !== 'image/png') {
				showNotice('Só se permiten arquivos PNG para stickers (con transparencia).', 'error');
				return;
			}

			// Validar tamaño (max 2MB)
			if (file.size > 2 * 1024 * 1024) {
				showNotice('O arquivo é demasiado grande. Máximo 2MB.', 'error');
				return;
			}

			const uploadBtn = $('.upload-sticker-btn');

			// Subir via AJAX
			const formData = new FormData();
			formData.append('action', 'postais_upload_sticker');
			formData.append('nonce', postaisNadalAdmin.nonce);
			formData.append('sticker', file);

			$.ajax({
				url: postaisNadalAdmin.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				beforeSend: function() {
					uploadBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Subindo...');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
						addStickerToGrid(response.data.url, response.data.source || 'upload');
					} else {
						showNotice(response.data.message, 'error');
					}
				},
				error: function() {
					showNotice('Erro ao subir o sticker', 'error');
				},
				complete: function() {
					uploadBtn.prop('disabled', false).html('<span class="dashicons dashicons-star-filled"></span> Subir Sticker (PNG)');
					$('.sticker-upload-input').val('');
				}
			});
		});
	}

	function addStickerToGrid(stickerUrl, source) {
		const grid = $('.postais-sticker-grid');
		const noStickers = grid.find('.no-stickers');

		if (noStickers.length) {
			noStickers.remove();
		}

		source = source || 'upload';
		const badgeText = (source === 'plugin') ? 'Por defecto' : 'Personalizado';
		const badgeClass = (source === 'plugin') ? 'badge-plugin' : 'badge-upload';

		const stickerItem = $('<div class="postais-sticker-item">' +
			'<span class="postais-sticker-badge ' + badgeClass + '">' + badgeText + '</span>' +
			'<img src="' + stickerUrl + '" alt="Sticker">' +
			'<button type="button" class="button postais-delete-sticker" data-url="' + stickerUrl + '" data-source="' + source + '">' +
			'<span class="dashicons dashicons-trash"></span>' +
			'</button>' +
			'</div>');

		grid.append(stickerItem);
	}

	function initStickerDeleter() {
		$(document).on('click', '.postais-delete-sticker', function() {
			const btn = $(this);
			const stickerUrl = btn.data('url');
			const source = btn.data('source') || 'upload';

			const confirmMsg = (source === 'plugin')
				? 'Este é un sticker por defecto do plugin. Estás seguro de que queres eliminalo permanentemente?'
				: 'Estás seguro de que queres eliminar este sticker?';

			if (!confirm(confirmMsg)) {
				return;
			}

			$.ajax({
				url: postaisNadalAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'postais_delete_sticker',
					nonce: postaisNadalAdmin.nonce,
					sticker_url: stickerUrl,
					source: source
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
						btn.closest('.postais-sticker-item').fadeOut(function() {
							$(this).remove();

							// Se non quedan stickers, mostrar mensaxe
							const grid = $('.postais-sticker-grid');
							if (grid.find('.postais-sticker-item').length === 0) {
								grid.html('<p class="no-stickers">Non hai stickers. Sube imaxes PNG con transparencia.</p>');
							}
						});
					} else {
						showNotice(response.data.message, 'error');
						btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
					}
				},
				error: function() {
					showNotice('Erro ao eliminar o sticker', 'error');
					btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
				}
			});
		});
	}

	function initImageDeleter() {
		$(document).on('click', '.postais-delete-image', function() {
			const btn = $(this);
			const imageUrl = btn.data('url');
			const categoria = btn.data('categoria');
			const source = btn.data('source') || 'upload';

			const confirmMsg = (source === 'plugin')
				? 'Esta é unha imaxe por defecto do plugin. Estás seguro de que queres eliminala permanentemente?'
				: 'Estás seguro de que queres eliminar esta imaxe?';

			if (!confirm(confirmMsg)) {
				return;
			}

			$.ajax({
				url: postaisNadalAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'postais_delete_image',
					nonce: postaisNadalAdmin.nonce,
					image_url: imageUrl,
					categoria: categoria,
					source: source
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
						btn.closest('.postais-image-item').fadeOut(function() {
							$(this).remove();
							updateTabCount(categoria, -1);

							// Se non quedan imaxes, mostrar mensaxe
							const grid = $('.postais-image-grid[data-categoria="' + categoria + '"]');
							if (grid.find('.postais-image-item').length === 0) {
								grid.html('<p class="no-images">Non hai imaxes nesta categoría.</p>');
							}
						});
					} else {
						showNotice(response.data.message, 'error');
						btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
					}
				},
				error: function() {
					showNotice('Erro ao eliminar a imaxe', 'error');
					btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
				}
			});
		});
	}

	function initSettingsForm() {
		const form = $('#postais-settings-form');
		const saveBtn = $('#save-settings-btn');

		form.on('submit', function(e) {
			e.preventDefault();

			// Recoller fontes (unha por liña)
			const fontesText = $('#fontes-textarea').val();
			const fontes = fontesText.split('\n').map(f => f.trim()).filter(f => f.length > 0);

			const data = {
				action: 'postais_save_settings',
				nonce: postaisNadalAdmin.nonce,
				fontes: fontes,
				limite_caracteres: $('#limite_caracteres').val(),
				// Cores principais
				cor_primaria: $('#cor_primaria').val(),
				cor_primaria_hover: $('#cor_primaria_hover').val(),
				// Fondos e textos
				modal_cor_fondo: $('#modal_cor_fondo').val(),
				cor_fondo_sutil: $('#cor_fondo_sutil').val(),
				modal_cor_texto: $('#modal_cor_texto').val(),
				modal_cor_texto_secundario: $('#modal_cor_texto_secundario').val(),
				cor_borde: $('#cor_borde').val(),
				// Botóns de acción
				cor_descarga: $('#cor_descarga').val(),
				cor_exito: $('#cor_exito').val(),
				cor_perigo: $('#cor_perigo').val(),
				cor_whatsapp: $('#cor_whatsapp').val(),
				// Tabs de formato
				cor_tab_activa: $('#cor_tab_activa').val(),
				cor_tab_inactiva: $('#cor_tab_inactiva').val(),
				cor_tab_fondo_hover: $('#cor_tab_fondo_hover').val(),
				// Estilo
				radio_bordes: $('#radio_bordes').val()
			};

			$.ajax({
				url: postaisNadalAdmin.ajax_url,
				type: 'POST',
				data: data,
				beforeSend: function() {
					saveBtn.prop('disabled', true).text('Gardando...');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
					} else {
						showNotice(response.data.message, 'error');
					}
				},
				error: function() {
					showNotice('Erro ao gardar a configuración', 'error');
				},
				complete: function() {
					saveBtn.prop('disabled', false).text('Gardar Cambios');
				}
			});
		});
	}

	function showNotice(message, type) {
		const notice = $('.postais-admin-notice');
		const className = type === 'success' ? 'notice-success' : 'notice-error';

		notice.removeClass('notice-success notice-error')
			.addClass('notice ' + className)
			.html('<p>' + message + '</p>')
			.slideDown();

		setTimeout(function() {
			notice.slideUp();
		}, 3000);
	}

})(jQuery);
