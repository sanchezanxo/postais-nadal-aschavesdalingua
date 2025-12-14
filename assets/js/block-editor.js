/**
 * Bloque Gutenberg para o xerador de postais
 *
 * @package Postais_Nadal
 */

(function(wp) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { PanelBody, TextControl, ColorPicker } = wp.components;
	const { createElement: el, Fragment } = wp.element;

	registerBlockType('postal-nadal/boton-xerador', {
		title: 'Xerador de Postais de Nadal',
		icon: 'images-alt2',
		category: 'widgets',
		description: 'Botón para abrir o xerador de postais de Nadal',

		attributes: {
			buttonText: {
				type: 'string',
				default: 'Crear Postal'
			},
			buttonColor: {
				type: 'string',
				default: '#0073aa'
			},
			buttonPadding: {
				type: 'string',
				default: '12px 24px'
			}
		},

		edit: function(props) {
			const { attributes, setAttributes } = props;
			const { buttonText, buttonColor, buttonPadding } = attributes;

			return el(
				Fragment,
				{},
				// Panel lateral de configuración
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Configuración do Botón', initialOpen: true },
						el(TextControl, {
							label: 'Texto do Botón',
							value: buttonText,
							onChange: function(value) {
								setAttributes({ buttonText: value });
							}
						}),
						el('div', { style: { marginBottom: '12px' } },
							el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: 'bold' } }, 'Cor do Botón'),
							el(ColorPicker, {
								color: buttonColor,
								onChangeComplete: function(value) {
									setAttributes({ buttonColor: value.hex });
								}
							})
						),
						el(TextControl, {
							label: 'Padding do Botón',
							value: buttonPadding,
							onChange: function(value) {
								setAttributes({ buttonPadding: value });
							},
							help: 'Exemplo: 12px 24px'
						})
					)
				),
				// Preview do botón no editor
				el(
					'div',
					{
						style: {
							padding: '20px',
							background: '#f5f5f5',
							borderRadius: '4px',
							textAlign: 'center'
						}
					},
					el('button', {
						style: {
							backgroundColor: buttonColor,
							padding: buttonPadding,
							color: '#fff',
							border: 'none',
							borderRadius: '4px',
							cursor: 'pointer',
							fontSize: '16px',
							fontWeight: 'bold'
						},
						disabled: true
					}, buttonText)
				)
			);
		},

		save: function() {
			// Rendering dinámico desde PHP
			return null;
		}
	});

})(window.wp);
