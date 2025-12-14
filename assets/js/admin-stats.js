/**
 * JavaScript para a páxina de estatísticas do admin
 *
 * @package Postais_Nadal
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initLightbox();
		initCheckboxes();
		initDeleteActions();
	});

	function initLightbox() {
		const lightbox = document.getElementById('postais-lightbox');
		if (!lightbox) return;

		const lightboxImg = lightbox.querySelector('img');
		const closeBtn = lightbox.querySelector('.postais-lightbox-close');
		const overlay = lightbox.querySelector('.postais-lightbox-overlay');

		document.querySelectorAll('.postais-thumbnail').forEach(function(thumb) {
			thumb.style.cursor = 'pointer';
			thumb.addEventListener('click', function() {
				lightboxImg.src = this.src;
				lightbox.style.display = 'block';
			});
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', function() {
				lightbox.style.display = 'none';
			});
		}

		if (overlay) {
			overlay.addEventListener('click', function() {
				lightbox.style.display = 'none';
			});
		}
	}

	function initCheckboxes() {
		$('#postais-select-all').on('change', function() {
			$('.postais-checkbox').prop('checked', this.checked);
			updateDeleteButton();
		});

		$('.postais-checkbox').on('change', function() {
			updateDeleteButton();
			const total = $('.postais-checkbox').length;
			const checked = $('.postais-checkbox:checked').length;
			$('#postais-select-all').prop('checked', total === checked);
		});
	}

	function updateDeleteButton() {
		const checked = $('.postais-checkbox:checked').length;
		$('#postais-delete-selected').prop('disabled', checked === 0);
	}

	function initDeleteActions() {
		$('#postais-delete-selected').on('click', function() {
			const ids = [];
			$('.postais-checkbox:checked').each(function() {
				ids.push($(this).val());
			});

			if (ids.length === 0) return;

			if (!confirm('Seguro que queres borrar ' + ids.length + ' postal(s)? Esta acción non se pode desfacer.')) {
				return;
			}

			deletePostals(ids);
		});

		$('.postais-delete-single').on('click', function() {
			const id = $(this).data('id');

			if (!confirm('Seguro que queres borrar esta postal? Esta acción non se pode desfacer.')) {
				return;
			}

			deletePostals([id]);
		});
	}

	function deletePostals(ids) {
		$.ajax({
			url: postaisNadalAdmin.ajax_url,
			type: 'POST',
			data: {
				action: 'postais_delete_postals',
				nonce: postaisNadalAdmin.nonce,
				ids: ids
			},
			beforeSend: function() {
				$('#postais-delete-selected').prop('disabled', true).text('Borrando...');
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('Erro: ' + response.data.message);
					$('#postais-delete-selected').prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Borrar seleccionadas');
				}
			},
			error: function() {
				alert('Erro ao conectar co servidor');
				$('#postais-delete-selected').prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Borrar seleccionadas');
			}
		});
	}

})(jQuery);
