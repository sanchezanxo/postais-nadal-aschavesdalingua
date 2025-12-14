/**
 * JavaScript minimalista con edición directa
 *
 * @package Postais_Nadal
 */

(function() {
	'use strict';

	// Dimensións por formato (baseadas en 1080px de largo)
	const FORMATOS = {
		postal: { width: 1080, height: 720, ratio: '3:2' },      // Horizontal
		instagram: { width: 1080, height: 1350, ratio: '4:5' },  // Vertical feed
		historia: { width: 1080, height: 1920, ratio: '9:16' }   // Vertical stories
	};

	const state = {
		selectedImage: null,
		selectedImageIndex: null,
		selectedFormato: 'postal',
		textElements: [],
		selectedTextIndex: null,
		stickerElements: [],
		selectedStickerIndex: null,
		loadedStickerImages: {}, // Cache de imaxes de stickers cargadas
		canvas: null,
		ctx: null,
		previewCanvas: null,
		previewCtx: null,
		canvasWidth: 800,
		canvasHeight: 600,
		isDragging: false,
		isResizing: false,
		dragType: null, // 'text' ou 'sticker'
		mouseDownPos: null, // Para detectar clics vs arrastres
		resizeStartFontSize: 0,
		resizeStartSize: 0,
		resizeStartY: 0,
		dragOffset: { x: 0, y: 0 },
		backgroundImage: null,
		generatedBlob: null,
		hintShown: false,
		hoverOnResize: false,
		userEmail: null
	};

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		const triggers = document.querySelectorAll('[data-modal-trigger]');
		triggers.forEach(trigger => {
			trigger.addEventListener('click', openModal);
		});

		const modal = document.getElementById('postal-modal');
		if (!modal) return;

		const closeBtn = modal.querySelector('.postal-close');
		const overlay = modal.querySelector('.postal-overlay');

		closeBtn.addEventListener('click', closeModal);
		overlay.addEventListener('click', closeModal);

		setupFormatSelector();
		setupImageSelection();
		setupEditor();
		setupPreviewAndForm();
		setupAccordion();
	}

	function openModal() {
		const modal = document.getElementById('postal-modal');
		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';
		resetModal();
	}

	function closeModal() {
		const modal = document.getElementById('postal-modal');
		modal.style.display = 'none';
		document.body.style.overflow = '';
		resetModal();
	}

	function resetModal() {
		document.querySelectorAll('.postal-step').forEach(step => {
			step.classList.remove('active');
		});
		document.querySelector('.postal-step-1').classList.add('active');

		state.selectedImage = null;
		state.selectedImageIndex = null;
		state.selectedFormato = 'postal';
		state.textElements = [];
		state.selectedTextIndex = null;
		state.stickerElements = [];
		state.selectedStickerIndex = null;
		state.isDragging = false;
		state.dragType = null;
		state.backgroundImage = null;
		state.generatedBlob = null;
		state.hintShown = false;

		if (state.ctx) {
			state.ctx.clearRect(0, 0, state.canvasWidth, state.canvasHeight);
		}

		const textOptions = document.getElementById('text-options');
		if (textOptions) {
			textOptions.style.display = 'none';
		}

		const stickerOptions = document.getElementById('sticker-options');
		if (stickerOptions) {
			stickerOptions.style.display = 'none';
		}

		document.getElementById('postal-share').style.display = 'none';
		document.getElementById('postal-form').style.display = 'block';

		const hint = document.querySelector('.postal-canvas-hint');
		if (hint) hint.classList.remove('show');

		// Resetear selector de formato
		document.querySelectorAll('.postal-format-btn').forEach((btn, i) => {
			btn.classList.toggle('active', i === 0);
		});
		document.querySelectorAll('.postal-gallery').forEach((gallery, i) => {
			gallery.classList.toggle('active', i === 0);
		});
	}

	function setupFormatSelector() {
		const formatBtns = document.querySelectorAll('.postal-format-btn');

		formatBtns.forEach(btn => {
			btn.addEventListener('click', function() {
				const formato = this.dataset.formato;

				// Actualizar botóns
				formatBtns.forEach(b => b.classList.remove('active'));
				this.classList.add('active');

				// Actualizar galerías
				document.querySelectorAll('.postal-gallery').forEach(gallery => {
					gallery.classList.toggle('active', gallery.dataset.formato === formato);
				});

				state.selectedFormato = formato;
			});
		});
	}

	function setupImageSelection() {
		const galleryItems = document.querySelectorAll('.postal-gallery-item');

		galleryItems.forEach(item => {
			item.addEventListener('click', function() {
				const imageUrl = this.dataset.image;
				const imageIndex = this.dataset.index;

				state.selectedImage = imageUrl;
				state.selectedImageIndex = imageIndex;

				goToStep(2);
				loadImageToCanvas(imageUrl);
			});
		});
	}

	function setupEditor() {
		state.canvas = document.getElementById('postal-canvas');
		state.ctx = state.canvas.getContext('2d');

		const deleteTextBtn = document.getElementById('delete-text');
		const deleteStickerBtn = document.getElementById('delete-sticker');
		const nextBtn = document.querySelector('.postal-next');
		const backBtn = document.querySelector('.postal-back-step1');

		deleteTextBtn.addEventListener('click', deleteText);
		if (deleteStickerBtn) {
			deleteStickerBtn.addEventListener('click', deleteSticker);
		}
		nextBtn.addEventListener('click', () => goToStep(3));
		backBtn.addEventListener('click', () => goToStep(1));

		// Setup sticker buttons
		setupStickerButtons();

		const textInput = document.getElementById('text-input');
		const charCount = document.querySelector('.postal-char-count');
		const fontSelect = document.getElementById('font-select');
		const colorPicker = document.getElementById('color-picker');

		// Aplicar cambios en tempo real
		textInput.addEventListener('input', function() {
			const current = this.value.length;
			const max = this.maxLength;
			charCount.textContent = current + ' / ' + max;

			if (state.selectedTextIndex !== null) {
				state.textElements[state.selectedTextIndex].content = this.value;
				redrawCanvas(true);
			}
		});

		// Cambio de fonte - esperar a que a fonte estea cargada
		fontSelect.addEventListener('change', function() {
			if (state.selectedTextIndex !== null && state.textElements[state.selectedTextIndex]) {
				const newFont = this.value;
				const text = state.textElements[state.selectedTextIndex];
				const fontSize = text.fontSize || 40;

				// Actualizar a fonte no estado
				text.font = newFont;

				// Usar document.fonts.load() para asegurar que a fonte está lista
				if (document.fonts && document.fonts.load) {
					document.fonts.load(fontSize + 'px "' + newFont + '"').then(function() {
						redrawCanvas(true);
					}).catch(function() {
						// Se falla, debuxar igualmente
						redrawCanvas(true);
					});
				} else {
					// Fallback para navegadores sen soporte
					setTimeout(function() {
						redrawCanvas(true);
					}, 50);
				}
			}
		});

		colorPicker.addEventListener('input', function() {
			if (state.selectedTextIndex !== null) {
				state.textElements[state.selectedTextIndex].color = this.value;
				redrawCanvas(true);
			}
		});

		// Eventos canvas - EDICIÓN DIRECTA
		// Mouse events
		state.canvas.addEventListener('mousedown', handleCanvasMouseDown);
		state.canvas.addEventListener('mousemove', handleCanvasMouseMove);
		state.canvas.addEventListener('mouseup', handleCanvasMouseUp);

		// Touch events para móbil
		state.canvas.addEventListener('touchstart', handleCanvasTouchStart, { passive: false });
		state.canvas.addEventListener('touchmove', handleCanvasTouchMove, { passive: false });
		state.canvas.addEventListener('touchend', handleCanvasTouchEnd);

		// Eventos globais para que o resize/drag funcione cando o rato sae do canvas
		document.addEventListener('mousemove', handleDocumentMouseMove);
		document.addEventListener('mouseup', handleDocumentMouseUp);
		document.addEventListener('touchmove', handleDocumentTouchMove, { passive: false });
		document.addEventListener('touchend', handleDocumentTouchEnd);

		// Tecla SUPR/DELETE para eliminar texto ou sticker seleccionado
		document.addEventListener('keydown', function(e) {
			// Comprobar tecla Delete (código 46) ou Backspace (código 8) cando non está nun input
			var isDeleteKey = e.key === 'Delete' || e.key === 'Supr' || e.key === 'Del' || e.keyCode === 46;

			if (!isDeleteKey) {
				return;
			}

			// Non eliminar se está escribindo nun input ou textarea
			var activeTag = document.activeElement.tagName.toLowerCase();
			if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
				return;
			}

			// Comprobar se o modal está visible
			var modal = document.getElementById('postal-modal');
			if (!modal || modal.style.display === 'none') {
				return;
			}

			if (state.selectedTextIndex !== null) {
				e.preventDefault();
				deleteText();
			} else if (state.selectedStickerIndex !== null) {
				e.preventDefault();
				deleteSticker();
			}
		});
	}

	function loadImageToCanvas(imageUrl) {
		const img = new Image();
		img.crossOrigin = 'anonymous';

		img.onload = function() {
			state.backgroundImage = img;

			// Usar dimensións do formato seleccionado
			const formato = FORMATOS[state.selectedFormato] || FORMATOS.postal;

			// Dimensións para visualización (escalado para caber na pantalla)
			const maxDisplayWidth = 600;
			const maxDisplayHeight = 500;

			// Calcular escala para visualización
			let displayWidth = formato.width;
			let displayHeight = formato.height;

			if (displayWidth > maxDisplayWidth) {
				const scale = maxDisplayWidth / displayWidth;
				displayWidth = maxDisplayWidth;
				displayHeight = formato.height * scale;
			}

			if (displayHeight > maxDisplayHeight) {
				const scale = maxDisplayHeight / displayHeight;
				displayHeight = maxDisplayHeight;
				displayWidth = displayWidth * scale;
			}

			state.canvasWidth = displayWidth;
			state.canvasHeight = displayHeight;

			state.canvas.width = state.canvasWidth;
			state.canvas.height = state.canvasHeight;

			redrawCanvas();

			// Mostrar hint despois dun segundo
			setTimeout(() => {
				if (!state.hintShown && state.textElements.length === 0) {
					const hint = document.querySelector('.postal-canvas-hint');
					if (hint) {
						hint.classList.add('show');
						setTimeout(() => hint.classList.remove('show'), 5000);
						state.hintShown = true;
					}
				}
			}, 1000);
		};

		img.onerror = function() {
			alert('Erro ao cargar a imaxe');
		};

		img.src = imageUrl;
	}

	function isClickOnText(x, y) {
		// Buscar se clicou sobre un texto existente
		for (let i = state.textElements.length - 1; i >= 0; i--) {
			const text = state.textElements[i];

			state.ctx.font = text.fontSize + 'px ' + text.font;
			const displayText = text.content || '|';
			const metrics = state.ctx.measureText(displayText);
			const width = Math.max(metrics.width, 20);
			const height = text.fontSize;

			if (
				x >= text.x - width / 2 - 10 &&
				x <= text.x + width / 2 + 10 &&
				y >= text.y - height / 2 - 10 &&
				y <= text.y + height / 2 + 10
			) {
				return i;
			}
		}
		return -1;
	}

	function createNewTextAt(x, y) {
		const newText = {
			content: '', // Comezar baleiro
			x: x,
			y: y,
			font: postaisNadal.fontes[0] || 'Arial',
			color: '#ffffff',
			fontSize: 40
		};

		state.textElements.push(newText);
		state.selectedTextIndex = state.textElements.length - 1;

		// Mostrar controis e sincronizar valores
		showTextControls(newText, true);
	}

	function selectText(index) {
		state.selectedTextIndex = index;
		const text = state.textElements[index];

		// Mostrar controis e sincronizar valores
		showTextControls(text, true);
	}

	function showTextControls(text, focusInput) {
		const textOptions = document.getElementById('text-options');
		const textInput = document.getElementById('text-input');

		// Ocultar opcións de sticker
		const stickerOptions = document.getElementById('sticker-options');
		if (stickerOptions) {
			stickerOptions.style.display = 'none';
		}

		textOptions.style.display = 'block';

		textInput.value = text.content;
		document.getElementById('font-select').value = text.font;
		document.getElementById('color-picker').value = text.color;

		const charCount = document.querySelector('.postal-char-count');
		charCount.textContent = text.content.length + ' / ' + postaisNadal.limite_caracteres;

		redrawCanvas(true);

		// Facer foco no input inmediatamente
		if (focusInput) {
			// Usar requestAnimationFrame para asegurar que o DOM está listo
			requestAnimationFrame(() => {
				textInput.focus();
				// Poñer o cursor ao final do texto
				textInput.setSelectionRange(textInput.value.length, textInput.value.length);
			});
		}
	}

	function deleteText() {
		if (state.selectedTextIndex !== null && state.textElements.length > 0) {
			state.textElements.splice(state.selectedTextIndex, 1);
			state.selectedTextIndex = null;
			document.getElementById('text-options').style.display = 'none';
			document.getElementById('text-input').value = '';
			redrawCanvas();
		}
	}

	// =============================================
	// STICKERS
	// =============================================

	function setupStickerButtons() {
		const stickerBtns = document.querySelectorAll('.postal-sticker-btn');
		stickerBtns.forEach(btn => {
			btn.addEventListener('click', function() {
				const stickerUrl = this.dataset.sticker;
				addSticker(stickerUrl);
			});
		});
	}

	function setupAccordion() {
		const accordionToggles = document.querySelectorAll('.postal-accordion-toggle');
		accordionToggles.forEach(toggle => {
			toggle.addEventListener('click', function() {
				const isExpanded = this.getAttribute('aria-expanded') === 'true';
				this.setAttribute('aria-expanded', !isExpanded);
			});
		});
	}

	function addSticker(stickerUrl) {
		// Cargar imaxe se non está en cache
		if (!state.loadedStickerImages[stickerUrl]) {
			const img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function() {
				state.loadedStickerImages[stickerUrl] = img;
				createStickerElement(stickerUrl, img);
			};
			img.onerror = function() {
				// Erro silencioso ao cargar sticker
			};
			img.src = stickerUrl;
		} else {
			createStickerElement(stickerUrl, state.loadedStickerImages[stickerUrl]);
		}
	}

	function createStickerElement(stickerUrl, img) {
		// Calcular tamaño inicial (máx 100px, mantendo proporción)
		const maxSize = 100;
		let width = img.naturalWidth;
		let height = img.naturalHeight;

		if (width > maxSize || height > maxSize) {
			const scale = maxSize / Math.max(width, height);
			width *= scale;
			height *= scale;
		}

		const newSticker = {
			url: stickerUrl,
			image: img,
			x: state.canvasWidth / 2,
			y: state.canvasHeight / 2,
			width: width,
			height: height,
			originalWidth: img.naturalWidth,
			originalHeight: img.naturalHeight
		};

		state.stickerElements.push(newSticker);
		state.selectedStickerIndex = state.stickerElements.length - 1;
		state.selectedTextIndex = null; // Deseleccionar texto

		// Mostrar opcións de sticker
		document.getElementById('text-options').style.display = 'none';
		const stickerOptions = document.getElementById('sticker-options');
		if (stickerOptions) {
			stickerOptions.style.display = 'block';
		}

		redrawCanvas(true);
	}

	function selectSticker(index) {
		state.selectedStickerIndex = index;
		state.selectedTextIndex = null; // Deseleccionar texto

		document.getElementById('text-options').style.display = 'none';
		const stickerOptions = document.getElementById('sticker-options');
		if (stickerOptions) {
			stickerOptions.style.display = 'block';
		}

		redrawCanvas(true);
	}

	function deleteSticker() {
		if (state.selectedStickerIndex !== null && state.stickerElements.length > 0) {
			state.stickerElements.splice(state.selectedStickerIndex, 1);
			state.selectedStickerIndex = null;
			const stickerOptions = document.getElementById('sticker-options');
			if (stickerOptions) {
				stickerOptions.style.display = 'none';
			}
			redrawCanvas();
		}
	}

	function isClickOnSticker(x, y) {
		// Buscar se clicou sobre un sticker existente (de arriba a abaixo)
		for (let i = state.stickerElements.length - 1; i >= 0; i--) {
			const sticker = state.stickerElements[i];
			const halfW = sticker.width / 2;
			const halfH = sticker.height / 2;

			if (
				x >= sticker.x - halfW &&
				x <= sticker.x + halfW &&
				y >= sticker.y - halfH &&
				y <= sticker.y + halfH
			) {
				return i;
			}
		}
		return -1;
	}

	function redrawCanvas(showEditBorder = false) {
		state.ctx.clearRect(0, 0, state.canvasWidth, state.canvasHeight);

		if (state.backgroundImage) {
			state.ctx.drawImage(state.backgroundImage, 0, 0, state.canvasWidth, state.canvasHeight);
		}

		// Debuxar stickers (antes dos textos para que o texto quede enriba)
		state.stickerElements.forEach((sticker, index) => {
			const isSelected = showEditBorder && index === state.selectedStickerIndex;

			// Debuxar imaxe do sticker
			state.ctx.drawImage(
				sticker.image,
				sticker.x - sticker.width / 2,
				sticker.y - sticker.height / 2,
				sticker.width,
				sticker.height
			);

			// Borde de selección
			if (isSelected) {
				const boxX = sticker.x - sticker.width / 2 - 4;
				const boxY = sticker.y - sticker.height / 2 - 4;
				const boxW = sticker.width + 8;
				const boxH = sticker.height + 8;

				// Borde punteado
				state.ctx.strokeStyle = '#2563eb';
				state.ctx.lineWidth = 2;
				state.ctx.setLineDash([5, 3]);
				state.ctx.strokeRect(boxX, boxY, boxW, boxH);
				state.ctx.setLineDash([]);

				// Handle de redimensionado (esquina inferior dereita)
				const handleSize = 12;
				const handleX = boxX + boxW - handleSize / 2;
				const handleY = boxY + boxH - handleSize / 2;

				// Gardar coordenadas do handle para detección
				sticker._resizeHandle = {
					x: handleX,
					y: handleY,
					size: handleSize
				};

				// Debuxar handle
				state.ctx.fillStyle = state.hoverOnResize ? '#1d4ed8' : '#2563eb';
				state.ctx.strokeStyle = '#ffffff';
				state.ctx.lineWidth = 2;
				state.ctx.beginPath();
				state.ctx.arc(handleX + handleSize / 2, handleY + handleSize / 2, handleSize / 2, 0, Math.PI * 2);
				state.ctx.fill();
				state.ctx.stroke();

				// Icono de redimensionado dentro do handle
				state.ctx.strokeStyle = '#ffffff';
				state.ctx.lineWidth = 1.5;
				const iconCenter = { x: handleX + handleSize / 2, y: handleY + handleSize / 2 };
				const iconOffset = 3;

				state.ctx.beginPath();
				state.ctx.moveTo(iconCenter.x - iconOffset, iconCenter.y + iconOffset);
				state.ctx.lineTo(iconCenter.x + iconOffset, iconCenter.y - iconOffset);
				state.ctx.stroke();
			}
		});

		// Debuxar textos
		state.textElements.forEach((text, index) => {
			const isSelected = showEditBorder && index === state.selectedTextIndex;
			const displayText = text.content || (isSelected ? '|' : '');

			if (displayText || isSelected) {
				state.ctx.font = text.fontSize + 'px ' + text.font;
				state.ctx.fillStyle = text.color;
				state.ctx.textAlign = 'center';
				state.ctx.textBaseline = 'middle';

				// Sombra tenue e moderna
				state.ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
				state.ctx.shadowBlur = 6;
				state.ctx.shadowOffsetX = 1;
				state.ctx.shadowOffsetY = 1;

				state.ctx.fillText(displayText, text.x, text.y);

				state.ctx.shadowColor = 'transparent';
				state.ctx.shadowBlur = 0;
				state.ctx.shadowOffsetX = 0;
				state.ctx.shadowOffsetY = 0;

				// Borde de edición con handle de redimensionado
				if (isSelected) {
					const metrics = state.ctx.measureText(displayText);
					const width = Math.max(metrics.width, 20); // Mínimo 20px de ancho
					const height = text.fontSize;

					const boxX = text.x - width / 2 - 8;
					const boxY = text.y - height / 2 - 8;
					const boxW = width + 16;
					const boxH = height + 16;

					// Borde punteado
					state.ctx.strokeStyle = '#2563eb';
					state.ctx.lineWidth = 2;
					state.ctx.setLineDash([5, 3]);
					state.ctx.strokeRect(boxX, boxY, boxW, boxH);
					state.ctx.setLineDash([]);

					// Handle de redimensionado (esquina inferior dereita)
					const handleSize = 12;
					const handleX = boxX + boxW - handleSize / 2;
					const handleY = boxY + boxH - handleSize / 2;

					// Gardar coordenadas do handle para detección de hover/click
					text._resizeHandle = {
						x: handleX,
						y: handleY,
						size: handleSize
					};

					// Debuxar handle
					state.ctx.fillStyle = state.hoverOnResize ? '#1d4ed8' : '#2563eb';
					state.ctx.strokeStyle = '#ffffff';
					state.ctx.lineWidth = 2;
					state.ctx.beginPath();
					state.ctx.arc(handleX + handleSize / 2, handleY + handleSize / 2, handleSize / 2, 0, Math.PI * 2);
					state.ctx.fill();
					state.ctx.stroke();

					// Icono de redimensionado dentro do handle
					state.ctx.strokeStyle = '#ffffff';
					state.ctx.lineWidth = 1.5;
					const iconCenter = { x: handleX + handleSize / 2, y: handleY + handleSize / 2 };
					const iconOffset = 3;

					// Liña diagonal
					state.ctx.beginPath();
					state.ctx.moveTo(iconCenter.x - iconOffset, iconCenter.y + iconOffset);
					state.ctx.lineTo(iconCenter.x + iconOffset, iconCenter.y - iconOffset);
					state.ctx.stroke();
				}
			}
		});
	}

	function handleCanvasMouseDown(e) {
		const rect = state.canvas.getBoundingClientRect();
		const x = (e.clientX - rect.left) * (state.canvasWidth / rect.width);
		const y = (e.clientY - rect.top) * (state.canvasHeight / rect.height);

		// Gardar posición inicial para detectar se é clic ou arrastre
		state.mouseDownPos = { x, y };

		// Primeiro comprobar se está no handle de redimensionado do texto
		if (state.selectedTextIndex !== null) {
			const text = state.textElements[state.selectedTextIndex];
			if (text && text._resizeHandle) {
				const handle = text._resizeHandle;
				const dist = Math.sqrt(
					Math.pow(x - (handle.x + handle.size / 2), 2) +
					Math.pow(y - (handle.y + handle.size / 2), 2)
				);
				if (dist <= handle.size) {
					state.isResizing = true;
					state.dragType = 'text';
					state.resizeStartFontSize = text.fontSize;
					state.resizeStartY = y;
					state.canvas.style.cursor = 'nwse-resize';
					e.preventDefault();
					return;
				}
			}
		}

		// Comprobar se está no handle de redimensionado do sticker
		if (state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			if (sticker && sticker._resizeHandle) {
				const handle = sticker._resizeHandle;
				const dist = Math.sqrt(
					Math.pow(x - (handle.x + handle.size / 2), 2) +
					Math.pow(y - (handle.y + handle.size / 2), 2)
				);
				if (dist <= handle.size) {
					state.isResizing = true;
					state.dragType = 'sticker';
					state.resizeStartSize = sticker.width;
					state.resizeStartY = y;
					state.canvas.style.cursor = 'nwse-resize';
					e.preventDefault();
					return;
				}
			}
		}

		// Comprobar se clicou sobre un sticker existente (prioridade sobre texto)
		const clickedStickerIndex = isClickOnSticker(x, y);
		if (clickedStickerIndex >= 0) {
			const sticker = state.stickerElements[clickedStickerIndex];
			state.selectedStickerIndex = clickedStickerIndex;
			state.selectedTextIndex = null;
			state.dragType = 'sticker';
			state.dragOffset = {
				x: x - sticker.x,
				y: y - sticker.y
			};
			e.preventDefault();
			return;
		}

		// Comprobar se clicou sobre un texto existente
		const clickedTextIndex = isClickOnText(x, y);
		if (clickedTextIndex >= 0) {
			const text = state.textElements[clickedTextIndex];
			state.selectedTextIndex = clickedTextIndex;
			state.selectedStickerIndex = null;
			state.dragType = 'text';
			state.dragOffset = {
				x: x - text.x,
				y: y - text.y
			};
			// Non activar isDragging aínda - só se move o rato
			e.preventDefault();
		}
	}

	function handleCanvasMouseMove(e) {
		const rect = state.canvas.getBoundingClientRect();
		const x = (e.clientX - rect.left) * (state.canvasWidth / rect.width);
		const y = (e.clientY - rect.top) * (state.canvasHeight / rect.height);

		// Xestionar redimensionado de texto
		if (state.isResizing && state.dragType === 'text' && state.selectedTextIndex !== null) {
			const deltaY = y - state.resizeStartY;
			const scaleFactor = 0.5;
			let newFontSize = Math.round(state.resizeStartFontSize + deltaY * scaleFactor);
			newFontSize = Math.max(12, Math.min(150, newFontSize));

			state.textElements[state.selectedTextIndex].fontSize = newFontSize;
			redrawCanvas(true);
			return;
		}

		// Xestionar redimensionado de sticker
		if (state.isResizing && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			const deltaY = y - state.resizeStartY;
			const scaleFactor = 0.5;
			let newWidth = Math.round(state.resizeStartSize + deltaY * scaleFactor);
			newWidth = Math.max(20, Math.min(400, newWidth));

			// Manter proporción
			const aspectRatio = sticker.originalHeight / sticker.originalWidth;
			sticker.width = newWidth;
			sticker.height = newWidth * aspectRatio;

			redrawCanvas(true);
			return;
		}

		// Se hai mouseDownPos e estamos sobre un sticker, activar arrastre
		if (state.mouseDownPos && state.selectedStickerIndex !== null && state.dragType === 'sticker' && !state.isDragging) {
			const dist = Math.sqrt(
				Math.pow(x - state.mouseDownPos.x, 2) +
				Math.pow(y - state.mouseDownPos.y, 2)
			);
			if (dist > 3) {
				state.isDragging = true;
				state.canvas.style.cursor = 'move';
			}
		}

		// Se hai mouseDownPos e estamos sobre un texto, activar arrastre
		if (state.mouseDownPos && state.selectedTextIndex !== null && state.dragType === 'text' && !state.isDragging) {
			const dist = Math.sqrt(
				Math.pow(x - state.mouseDownPos.x, 2) +
				Math.pow(y - state.mouseDownPos.y, 2)
			);
			if (dist > 3) {
				state.isDragging = true;
				state.canvas.style.cursor = 'move';
			}
		}

		// Xestionar arrastre de sticker
		if (state.isDragging && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			state.stickerElements[state.selectedStickerIndex].x = x - state.dragOffset.x;
			state.stickerElements[state.selectedStickerIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
			return;
		}

		// Xestionar arrastre de texto
		if (state.isDragging && state.dragType === 'text' && state.selectedTextIndex !== null) {
			state.textElements[state.selectedTextIndex].x = x - state.dragOffset.x;
			state.textElements[state.selectedTextIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
			return;
		}

		// Detectar hover sobre o handle de redimensionado (texto)
		if (state.selectedTextIndex !== null && !state.mouseDownPos) {
			const text = state.textElements[state.selectedTextIndex];
			if (text && text._resizeHandle) {
				const handle = text._resizeHandle;
				const dist = Math.sqrt(
					Math.pow(x - (handle.x + handle.size / 2), 2) +
					Math.pow(y - (handle.y + handle.size / 2), 2)
				);
				const wasHovering = state.hoverOnResize;
				state.hoverOnResize = dist <= handle.size + 4;

				if (state.hoverOnResize !== wasHovering) {
					state.canvas.style.cursor = state.hoverOnResize ? 'nwse-resize' : 'crosshair';
					redrawCanvas(true);
				}
				return;
			}
		}

		// Detectar hover sobre o handle de redimensionado (sticker)
		if (state.selectedStickerIndex !== null && !state.mouseDownPos) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			if (sticker && sticker._resizeHandle) {
				const handle = sticker._resizeHandle;
				const dist = Math.sqrt(
					Math.pow(x - (handle.x + handle.size / 2), 2) +
					Math.pow(y - (handle.y + handle.size / 2), 2)
				);
				const wasHovering = state.hoverOnResize;
				state.hoverOnResize = dist <= handle.size + 4;

				if (state.hoverOnResize !== wasHovering) {
					state.canvas.style.cursor = state.hoverOnResize ? 'nwse-resize' : 'crosshair';
					redrawCanvas(true);
				}
			}
		}
	}

	function handleCanvasMouseUp(e) {
		const rect = state.canvas.getBoundingClientRect();
		const x = (e.clientX - rect.left) * (state.canvasWidth / rect.width);
		const y = (e.clientY - rect.top) * (state.canvasHeight / rect.height);

		// Se foi un clic (non arrastre nin resize)
		if (state.mouseDownPos && !state.isDragging && !state.isResizing) {
			const dist = Math.sqrt(
				Math.pow(x - state.mouseDownPos.x, 2) +
				Math.pow(y - state.mouseDownPos.y, 2)
			);

			// É un clic se moveu menos de 3 píxeles
			if (dist <= 3) {
				// Primeiro comprobar stickers
				const clickedStickerIndex = isClickOnSticker(x, y);
				if (clickedStickerIndex >= 0) {
					selectSticker(clickedStickerIndex);
					resetMouseState();
					return;
				}

				// Despois comprobar textos
				const clickedTextIndex = isClickOnText(x, y);
				if (clickedTextIndex >= 0) {
					// Seleccionar texto existente
					selectText(clickedTextIndex);
				} else {
					// Deseleccionar sticker se había un seleccionado
					if (state.selectedStickerIndex !== null) {
						state.selectedStickerIndex = null;
						const stickerOptions = document.getElementById('sticker-options');
						if (stickerOptions) {
							stickerOptions.style.display = 'none';
						}
					}
					// Crear novo texto
					createNewTextAt(x, y);
				}
			}
		}

		resetMouseState();
	}

	// Eventos globais para cando o rato sae do canvas
	function handleDocumentMouseMove(e) {
		if (!state.canvas) return;
		if (!state.isResizing && !state.isDragging) return;

		const rect = state.canvas.getBoundingClientRect();
		const x = (e.clientX - rect.left) * (state.canvasWidth / rect.width);
		const y = (e.clientY - rect.top) * (state.canvasHeight / rect.height);

		// Xestionar redimensionado de texto mesmo fóra do canvas
		if (state.isResizing && state.dragType === 'text' && state.selectedTextIndex !== null) {
			const deltaY = y - state.resizeStartY;
			const scaleFactor = 0.5;
			let newFontSize = Math.round(state.resizeStartFontSize + deltaY * scaleFactor);
			newFontSize = Math.max(12, Math.min(150, newFontSize));

			state.textElements[state.selectedTextIndex].fontSize = newFontSize;
			redrawCanvas(true);
			return;
		}

		// Xestionar redimensionado de sticker mesmo fóra do canvas
		if (state.isResizing && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			const deltaY = y - state.resizeStartY;
			const scaleFactor = 0.5;
			let newWidth = Math.round(state.resizeStartSize + deltaY * scaleFactor);
			newWidth = Math.max(20, Math.min(400, newWidth));

			const aspectRatio = sticker.originalHeight / sticker.originalWidth;
			sticker.width = newWidth;
			sticker.height = newWidth * aspectRatio;

			redrawCanvas(true);
			return;
		}

		// Xestionar arrastre de sticker mesmo fóra do canvas
		if (state.isDragging && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			state.stickerElements[state.selectedStickerIndex].x = x - state.dragOffset.x;
			state.stickerElements[state.selectedStickerIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
			return;
		}

		// Xestionar arrastre de texto mesmo fóra do canvas
		if (state.isDragging && state.dragType === 'text' && state.selectedTextIndex !== null) {
			state.textElements[state.selectedTextIndex].x = x - state.dragOffset.x;
			state.textElements[state.selectedTextIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
		}
	}

	function handleDocumentMouseUp() {
		if (state.isResizing || state.isDragging) {
			resetMouseState();
		}
	}

	function resetMouseState() {
		state.isDragging = false;
		state.isResizing = false;
		state.dragType = null;
		state.mouseDownPos = null;
		state.hoverOnResize = false;
		if (state.canvas) {
			state.canvas.style.cursor = 'crosshair';
		}
	}

	// =============================================
	// TOUCH EVENTS - Para dispositivos móbiles
	// =============================================

	function getTouchPos(e) {
		const rect = state.canvas.getBoundingClientRect();
		const touch = e.touches[0] || e.changedTouches[0];
		return {
			x: (touch.clientX - rect.left) * (state.canvasWidth / rect.width),
			y: (touch.clientY - rect.top) * (state.canvasHeight / rect.height)
		};
	}

	function handleCanvasTouchStart(e) {
		e.preventDefault();
		const pos = getTouchPos(e);

		// Gardar posición inicial
		state.mouseDownPos = { x: pos.x, y: pos.y };

		// Comprobar se está no handle de redimensionado do texto
		if (state.selectedTextIndex !== null) {
			const text = state.textElements[state.selectedTextIndex];
			if (text && text._resizeHandle && isOnHandle(pos, text._resizeHandle)) {
				state.isResizing = true;
				state.dragType = 'text';
				state.resizeStartFontSize = text.fontSize;
				state.resizeStartY = pos.y;
				return;
			}
		}

		// Comprobar se está no handle de redimensionado do sticker
		if (state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			if (sticker && sticker._resizeHandle && isOnHandle(pos, sticker._resizeHandle)) {
				state.isResizing = true;
				state.dragType = 'sticker';
				state.resizeStartSize = sticker.width;
				state.resizeStartY = pos.y;
				return;
			}
		}

		// Comprobar se tocou sobre un sticker (prioridade sobre texto)
		const clickedStickerIndex = isClickOnSticker(pos.x, pos.y);
		if (clickedStickerIndex >= 0) {
			const sticker = state.stickerElements[clickedStickerIndex];
			state.selectedStickerIndex = clickedStickerIndex;
			state.selectedTextIndex = null;
			state.dragType = 'sticker';
			state.dragOffset = { x: pos.x - sticker.x, y: pos.y - sticker.y };
			return;
		}

		// Comprobar se tocou sobre un texto existente
		const clickedTextIndex = isClickOnText(pos.x, pos.y);
		if (clickedTextIndex >= 0) {
			const text = state.textElements[clickedTextIndex];
			state.selectedTextIndex = clickedTextIndex;
			state.selectedStickerIndex = null;
			state.dragType = 'text';
			state.dragOffset = { x: pos.x - text.x, y: pos.y - text.y };
		}
	}

	function isOnHandle(pos, handle) {
		const dist = Math.sqrt(
			Math.pow(pos.x - (handle.x + handle.size / 2), 2) +
			Math.pow(pos.y - (handle.y + handle.size / 2), 2)
		);
		return dist <= handle.size + 10; // Marxe extra para touch
	}

	function handleCanvasTouchMove(e) {
		e.preventDefault();
		const pos = getTouchPos(e);

		// Xestionar redimensionado de texto
		if (state.isResizing && state.dragType === 'text' && state.selectedTextIndex !== null) {
			const deltaY = pos.y - state.resizeStartY;
			let newFontSize = Math.round(state.resizeStartFontSize + deltaY * 0.5);
			state.textElements[state.selectedTextIndex].fontSize = Math.max(12, Math.min(150, newFontSize));
			redrawCanvas(true);
			return;
		}

		// Xestionar redimensionado de sticker
		if (state.isResizing && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			const deltaY = pos.y - state.resizeStartY;
			const newWidth = Math.max(20, Math.min(400, Math.round(state.resizeStartSize + deltaY * 0.5)));
			sticker.width = newWidth;
			sticker.height = newWidth * (sticker.originalHeight / sticker.originalWidth);
			redrawCanvas(true);
			return;
		}

		// Activar arrastre se moveu o suficiente
		if (state.mouseDownPos && !state.isDragging) {
			const dist = Math.sqrt(
				Math.pow(pos.x - state.mouseDownPos.x, 2) +
				Math.pow(pos.y - state.mouseDownPos.y, 2)
			);
			if (dist > 5) {
				state.isDragging = true;
			}
		}

		// Xestionar arrastre de sticker
		if (state.isDragging && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			state.stickerElements[state.selectedStickerIndex].x = pos.x - state.dragOffset.x;
			state.stickerElements[state.selectedStickerIndex].y = pos.y - state.dragOffset.y;
			redrawCanvas(true);
			return;
		}

		// Xestionar arrastre de texto
		if (state.isDragging && state.dragType === 'text' && state.selectedTextIndex !== null) {
			state.textElements[state.selectedTextIndex].x = pos.x - state.dragOffset.x;
			state.textElements[state.selectedTextIndex].y = pos.y - state.dragOffset.y;
			redrawCanvas(true);
		}
	}

	function handleCanvasTouchEnd(e) {
		const pos = getTouchPos(e);

		// Se foi un tap (non arrastre nin resize)
		if (state.mouseDownPos && !state.isDragging && !state.isResizing) {
			const dist = Math.sqrt(
				Math.pow(pos.x - state.mouseDownPos.x, 2) +
				Math.pow(pos.y - state.mouseDownPos.y, 2)
			);

			if (dist <= 10) {
				// Primeiro comprobar stickers
				const clickedStickerIndex = isClickOnSticker(pos.x, pos.y);
				if (clickedStickerIndex >= 0) {
					selectSticker(clickedStickerIndex);
					resetMouseState();
					return;
				}

				// Despois comprobar textos
				const clickedTextIndex = isClickOnText(pos.x, pos.y);
				if (clickedTextIndex >= 0) {
					selectText(clickedTextIndex);
				} else {
					// Deseleccionar sticker se había un seleccionado
					if (state.selectedStickerIndex !== null) {
						state.selectedStickerIndex = null;
						const stickerOptions = document.getElementById('sticker-options');
						if (stickerOptions) {
							stickerOptions.style.display = 'none';
						}
					}
					createNewTextAt(pos.x, pos.y);
				}
			}
		}

		resetMouseState();
	}

	function handleDocumentTouchMove(e) {
		if (!state.canvas) return;
		if (!state.isResizing && !state.isDragging) return;

		e.preventDefault();

		const rect = state.canvas.getBoundingClientRect();
		const touch = e.touches[0];
		const x = (touch.clientX - rect.left) * (state.canvasWidth / rect.width);
		const y = (touch.clientY - rect.top) * (state.canvasHeight / rect.height);

		// Redimensionado de texto
		if (state.isResizing && state.dragType === 'text' && state.selectedTextIndex !== null) {
			const deltaY = y - state.resizeStartY;
			state.textElements[state.selectedTextIndex].fontSize = Math.max(12, Math.min(150, Math.round(state.resizeStartFontSize + deltaY * 0.5)));
			redrawCanvas(true);
			return;
		}

		// Redimensionado de sticker
		if (state.isResizing && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			const sticker = state.stickerElements[state.selectedStickerIndex];
			const deltaY = y - state.resizeStartY;
			const newWidth = Math.max(20, Math.min(400, Math.round(state.resizeStartSize + deltaY * 0.5)));
			sticker.width = newWidth;
			sticker.height = newWidth * (sticker.originalHeight / sticker.originalWidth);
			redrawCanvas(true);
			return;
		}

		// Arrastre de sticker
		if (state.isDragging && state.dragType === 'sticker' && state.selectedStickerIndex !== null) {
			state.stickerElements[state.selectedStickerIndex].x = x - state.dragOffset.x;
			state.stickerElements[state.selectedStickerIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
			return;
		}

		// Arrastre de texto
		if (state.isDragging && state.dragType === 'text' && state.selectedTextIndex !== null) {
			state.textElements[state.selectedTextIndex].x = x - state.dragOffset.x;
			state.textElements[state.selectedTextIndex].y = y - state.dragOffset.y;
			redrawCanvas(true);
		}
	}

	function handleDocumentTouchEnd() {
		if (state.isResizing || state.isDragging) {
			resetMouseState();
		}
	}

	function setupPreviewAndForm() {
		state.previewCanvas = document.getElementById('postal-preview');
		state.previewCtx = state.previewCanvas.getContext('2d');

		const backBtn = document.querySelector('.postal-back');
		const form = document.getElementById('postal-form');
		const whatsappBtn = document.getElementById('share-whatsapp-file');
		const sendEmailBtn = document.getElementById('send-email-btn');

		backBtn.addEventListener('click', () => goToStep(2));
		form.addEventListener('submit', handleFormSubmit);

		if (whatsappBtn) {
			whatsappBtn.addEventListener('click', shareWhatsAppFile);
		}

		if (sendEmailBtn) {
			sendEmailBtn.addEventListener('click', sendPostalByEmail);
		}
	}

	function goToStep(stepNumber) {
		document.querySelectorAll('.postal-step').forEach(step => {
			step.classList.remove('active');
		});

		document.querySelector('.postal-step-' + stepNumber).classList.add('active');

		if (stepNumber === 3) {
			copyToPreview();
		}

		if (stepNumber === 1) {
			state.textElements = [];
			state.selectedTextIndex = null;
			state.stickerElements = [];
			state.selectedStickerIndex = null;
			document.getElementById('text-options').style.display = 'none';
			const stickerOptions = document.getElementById('sticker-options');
			if (stickerOptions) {
				stickerOptions.style.display = 'none';
			}
		}
	}

	function copyToPreview() {
		// Obter dimensións finais do formato
		const formato = FORMATOS[state.selectedFormato] || FORMATOS.postal;
		const finalWidth = formato.width;
		const finalHeight = formato.height;

		// Escala entre canvas de edición e canvas final
		const scaleX = finalWidth / state.canvasWidth;
		const scaleY = finalHeight / state.canvasHeight;

		// Preview a tamaño reducido para visualización
		const previewScale = Math.min(600 / finalWidth, 500 / finalHeight);
		state.previewCanvas.width = finalWidth * previewScale;
		state.previewCanvas.height = finalHeight * previewScale;

		// Debuxar fondo
		if (state.backgroundImage) {
			state.previewCtx.drawImage(state.backgroundImage, 0, 0, state.previewCanvas.width, state.previewCanvas.height);
		}

		// Debuxar stickers escalados
		state.stickerElements.forEach((sticker) => {
			const scaledWidth = sticker.width * previewScale * scaleX;
			const scaledHeight = sticker.height * previewScale * scaleY;
			const scaledX = sticker.x * previewScale * scaleX - scaledWidth / 2;
			const scaledY = sticker.y * previewScale * scaleY - scaledHeight / 2;

			state.previewCtx.drawImage(sticker.image, scaledX, scaledY, scaledWidth, scaledHeight);
		});

		// Debuxar textos escalados
		state.textElements.forEach((text) => {
			if (text.content) {
				const scaledFontSize = text.fontSize * previewScale * scaleX;
				state.previewCtx.font = scaledFontSize + 'px ' + text.font;
				state.previewCtx.fillStyle = text.color;
				state.previewCtx.textAlign = 'center';
				state.previewCtx.textBaseline = 'middle';

				state.previewCtx.shadowColor = 'rgba(0, 0, 0, 0.3)';
				state.previewCtx.shadowBlur = 6 * previewScale;
				state.previewCtx.shadowOffsetX = 1 * previewScale;
				state.previewCtx.shadowOffsetY = 1 * previewScale;

				const scaledX = text.x * previewScale * scaleX;
				const scaledY = text.y * previewScale * scaleY;
				state.previewCtx.fillText(text.content, scaledX, scaledY);

				state.previewCtx.shadowColor = 'transparent';
				state.previewCtx.shadowBlur = 0;
				state.previewCtx.shadowOffsetX = 0;
				state.previewCtx.shadowOffsetY = 0;
			}
		});
	}

	function generateFullResImage(callback) {
		// Xerar imaxe a resolución completa
		const formato = FORMATOS[state.selectedFormato] || FORMATOS.postal;
		const finalWidth = formato.width;
		const finalHeight = formato.height;

		// Escala entre canvas de edición e canvas final
		const scaleX = finalWidth / state.canvasWidth;
		const scaleY = finalHeight / state.canvasHeight;

		// Crear canvas temporal a resolución completa
		const fullResCanvas = document.createElement('canvas');
		fullResCanvas.width = finalWidth;
		fullResCanvas.height = finalHeight;
		const fullResCtx = fullResCanvas.getContext('2d');

		// Debuxar fondo
		if (state.backgroundImage) {
			fullResCtx.drawImage(state.backgroundImage, 0, 0, finalWidth, finalHeight);
		}

		// Debuxar stickers escalados
		state.stickerElements.forEach((sticker) => {
			const scaledWidth = sticker.width * scaleX;
			const scaledHeight = sticker.height * scaleY;
			const scaledX = sticker.x * scaleX - scaledWidth / 2;
			const scaledY = sticker.y * scaleY - scaledHeight / 2;

			fullResCtx.drawImage(sticker.image, scaledX, scaledY, scaledWidth, scaledHeight);
		});

		// Debuxar textos escalados
		state.textElements.forEach((text) => {
			if (text.content) {
				const scaledFontSize = text.fontSize * scaleX;
				fullResCtx.font = scaledFontSize + 'px ' + text.font;
				fullResCtx.fillStyle = text.color;
				fullResCtx.textAlign = 'center';
				fullResCtx.textBaseline = 'middle';

				fullResCtx.shadowColor = 'rgba(0, 0, 0, 0.3)';
				fullResCtx.shadowBlur = 6 * scaleX;
				fullResCtx.shadowOffsetX = 1 * scaleX;
				fullResCtx.shadowOffsetY = 1 * scaleX;

				const scaledX = text.x * scaleX;
				const scaledY = text.y * scaleY;
				fullResCtx.fillText(text.content, scaledX, scaledY);

				fullResCtx.shadowColor = 'transparent';
				fullResCtx.shadowBlur = 0;
				fullResCtx.shadowOffsetX = 0;
				fullResCtx.shadowOffsetY = 0;
			}
		});

		fullResCanvas.toBlob(callback, 'image/jpeg', 0.92);
	}

	function handleFormSubmit(e) {
		e.preventDefault();

		const email = document.getElementById('postal-email').value;

		if (!email || !validateEmail(email)) {
			alert('Email non válido');
			return;
		}

		document.querySelector('.postal-loading').style.display = 'flex';

		// Xerar imaxe a resolución completa
		generateFullResImage(function(blob) {
			state.generatedBlob = blob;

			const reader = new FileReader();
			reader.onloadend = function() {
				const base64data = reader.result;

				const metadata = {
					imaxe_base_id: state.selectedImageIndex,
					formato: state.selectedFormato,
					textos: state.textElements
				};

				// Gardar email para uso posterior no botón de envío
				state.userEmail = email;

				const formData = new FormData();
				formData.append('action', 'postais_generate_image');
				formData.append('nonce', postaisNadal.nonce);
				formData.append('email', email);
				formData.append('image_data', base64data);
				formData.append('metadata', JSON.stringify(metadata));
				formData.append('send_email', '0');

				fetch(postaisNadal.ajax_url, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					document.querySelector('.postal-loading').style.display = 'none';

					if (data.success) {
						document.getElementById('postal-form').style.display = 'none';
						document.getElementById('postal-share').style.display = 'block';

						const downloadBtn = document.getElementById('download-btn');
						const blobUrl = URL.createObjectURL(state.generatedBlob);
						downloadBtn.href = blobUrl;

						window.postalGeneratedUrl = data.data.url;

						// Mostrar WhatsApp só en móbil
						const whatsappBtn = document.getElementById('share-whatsapp-file');
						if (whatsappBtn && isMobile()) {
							whatsappBtn.style.display = 'flex';
						}
					} else {
						alert('Erro: ' + data.data.message);
					}
				})
				.catch(() => {
					document.querySelector('.postal-loading').style.display = 'none';
					alert('Erro ao xerar a postal');
				});
			};
			reader.readAsDataURL(blob);
		});
	}

	function shareWhatsAppFile() {
		if (!state.generatedBlob) {
			alert('Primeiro xera a postal');
			return;
		}

		const file = new File([state.generatedBlob], 'postal-nadal.jpg', { type: 'image/jpeg' });

		if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
			navigator.share({
				files: [file],
				title: 'Postal de Nadal',
				text: 'Deséxoche bo Nadal! - crea a túa postal en aschavesdalingua.gal'
			}).catch(() => {
				shareWhatsAppUrl();
			});
		} else {
			shareWhatsAppUrl();
		}
	}

	function shareWhatsAppUrl() {
		const shareText = 'Deséxoche bo Nadal! - crea a túa postal en aschavesdalingua.gal';
		const encodedUrl = encodeURIComponent(window.postalGeneratedUrl);
		const encodedText = encodeURIComponent(shareText);
		window.open(`https://wa.me/?text=${encodedText}%20${encodedUrl}`, '_blank');
	}

	function sendPostalByEmail() {
		if (!state.generatedBlob) {
			alert('Primeiro xera a postal');
			return;
		}

		if (!state.userEmail) {
			alert('Non se atopou o email');
			return;
		}

		const sendBtn = document.getElementById('send-email-btn');
		const originalText = sendBtn.innerHTML;
		sendBtn.disabled = true;
		sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

		const reader = new FileReader();
		reader.onloadend = function() {
			const base64data = reader.result;

			const formData = new FormData();
			formData.append('action', 'postais_send_email');
			formData.append('nonce', postaisNadal.nonce);
			formData.append('email', state.userEmail);
			formData.append('image_data', base64data);

			fetch(postaisNadal.ajax_url, {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				sendBtn.disabled = false;

				if (data.success) {
					sendBtn.innerHTML = '<i class="fas fa-check"></i> Enviado!';
					setTimeout(() => {
						sendBtn.innerHTML = originalText;
					}, 3000);
				} else {
					sendBtn.innerHTML = originalText;
					alert('Erro: ' + (data.data.message || 'Non se puido enviar o email'));
				}
			})
			.catch(() => {
				sendBtn.disabled = false;
				sendBtn.innerHTML = originalText;
				alert('Erro ao enviar o email');
			});
		};
		reader.readAsDataURL(state.generatedBlob);
	}

	function isMobile() {
		return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
	}

	function validateEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

})();
