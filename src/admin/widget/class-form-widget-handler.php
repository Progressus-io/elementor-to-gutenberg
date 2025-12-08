<?php
/**
 * Form Widget Handler
 *
 * @package Progressus\Gutenberg\Admin\Widget
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

/**
 * Class Form_Widget_Handler
 */
class Form_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle the widget conversion.
	 *
	 * @param array $widget The widget data.
	 * @return string The converted Gutenberg block HTML.
	 */
	public function handle( array $widget ): string {
		$settings = $widget['settings'] ?? array();

		// Build attributes.
		$attributes = array();

		// Form name.
		if ( ! empty( $settings['form_name'] ) ) {
			$attributes['formName'] = $settings['form_name'];
		}

		// Form fields.
		if ( ! empty( $settings['form_fields'] ) && is_array( $settings['form_fields'] ) ) {
			$form_fields = array();
			foreach ( $settings['form_fields'] as $field ) {
				$form_fields[] = array(
					'customId'    => $field['custom_id'] ?? '',
					'fieldType'   => $field['field_type'] ?? 'text',
					'required'    => isset( $field['required'] ) && 'true' === $field['required'],
					'fieldLabel'  => $field['field_label'] ?? '',
					'placeholder' => $field['placeholder'] ?? '',
				);
			}
			$attributes['formFields'] = $form_fields;
		}

		// Input size.
		if ( ! empty( $settings['input_size'] ) ) {
			$attributes['inputSize'] = $settings['input_size'];
		}

		// Button text.
		if ( ! empty( $settings['button_text'] ) ) {
			$attributes['buttonText'] = $settings['button_text'];
		}

		// Button align.
		if ( ! empty( $settings['button_align'] ) ) {
			$attributes['buttonAlign'] = $settings['button_align'];
		}

		// Success message.
		if ( ! empty( $settings['success_message'] ) ) {
			$attributes['successMessage'] = $settings['success_message'];
		}

		// Error message.
		if ( ! empty( $settings['error_message'] ) ) {
			$attributes['errorMessage'] = $settings['error_message'];
		}

		// Required field message.
		if ( ! empty( $settings['required_field_message'] ) ) {
			$attributes['requiredFieldMessage'] = $settings['required_field_message'];
		}

		// Column gap.
		if ( isset( $settings['column_gap']['size'] ) ) {
			$attributes['columnGap'] = (int) $settings['column_gap']['size'];
		}

		// Row gap.
		if ( isset( $settings['row_gap']['size'] ) ) {
			$attributes['rowGap'] = (int) $settings['row_gap']['size'];
		}

		// Label spacing.
		if ( isset( $settings['label_spacing']['size'] ) ) {
			$attributes['labelSpacing'] = (int) $settings['label_spacing']['size'];
		}

		// Label typography.
		$label_typography = array(
			'fontFamily'     => $settings['label_typography_font_family'] ?? '',
			'fontWeight'     => $settings['label_typography_font_weight'] ?? 'normal',
			'letterSpacing'  => (float) ( $settings['label_typography_letter_spacing']['size'] ?? 0 ),
			'wordSpacing'    => (float) ( $settings['label_typography_word_spacing']['size'] ?? 0 ),
		);
		$attributes['labelTypography'] = $label_typography;

		// Button background color.
		if ( ! empty( $settings['button_background_color'] ) ) {
			$attributes['buttonBackgroundColor'] = $settings['button_background_color'];
		}

		// Button text color.
		if ( ! empty( $settings['button_text_color'] ) ) {
			$attributes['buttonTextColor'] = $settings['button_text_color'];
		}

		// Button border radius.
		if ( ! empty( $settings['button_border_radius'] ) ) {
			$attributes['buttonBorderRadius'] = array(
				'top'    => (int) ( $settings['button_border_radius']['top'] ?? 0 ),
				'right'  => (int) ( $settings['button_border_radius']['right'] ?? 0 ),
				'bottom' => (int) ( $settings['button_border_radius']['bottom'] ?? 0 ),
				'left'   => (int) ( $settings['button_border_radius']['left'] ?? 0 ),
			);
		}

		// Button padding.
		if ( ! empty( $settings['button_text_padding'] ) ) {
			$attributes['buttonPadding'] = array(
				'top'    => (int) ( $settings['button_text_padding']['top'] ?? 12 ),
				'right'  => (int) ( $settings['button_text_padding']['right'] ?? 24 ),
				'bottom' => (int) ( $settings['button_text_padding']['bottom'] ?? 12 ),
				'left'   => (int) ( $settings['button_text_padding']['left'] ?? 24 ),
			);
		}

		// Generate the block HTML.
		$block_content = $this->generate_form_html( $attributes );

		// Create the Gutenberg block.
		$block = '<!-- wp:progressus/form ' . wp_json_encode( $attributes ) . ' -->' . $block_content . '<!-- /wp:progressus/form -->';

		return $block;
	}

	/**
	 * Generate form HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML content.
	 */
	private function generate_form_html( array $attributes ): string {
		$form_name              = $attributes['formName'] ?? 'contact-form';
		$form_fields            = $attributes['formFields'] ?? array();
		$input_size             = $attributes['inputSize'] ?? 'md';
		$button_text            = $attributes['buttonText'] ?? 'Send';
		$button_align           = $attributes['buttonAlign'] ?? 'start';
		$success_message        = $attributes['successMessage'] ?? 'Your submission was successful.';
		$error_message          = $attributes['errorMessage'] ?? 'Your submission failed because of an error.';
		$row_gap                = $attributes['rowGap'] ?? 20;
		$label_spacing          = $attributes['labelSpacing'] ?? 8;
		$label_typography       = $attributes['labelTypography'] ?? array();
		$button_background_color = $attributes['buttonBackgroundColor'] ?? '';
		$button_text_color      = $attributes['buttonTextColor'] ?? '';
		$button_border_radius   = $attributes['buttonBorderRadius'] ?? array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
		$button_padding         = $attributes['buttonPadding'] ?? array( 'top' => 12, 'right' => 24, 'bottom' => 12, 'left' => 24 );
		$_margin                = $attributes['_margin'] ?? array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
		$_padding               = $attributes['_padding'] ?? array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
		$custom_id              = $attributes['customId'] ?? '';
		$custom_class           = $attributes['customClass'] ?? '';

		$form_style = sprintf(
			'margin:%dpx %dpx %dpx %dpx;padding:%dpx %dpx %dpx %dpx',
			$_margin['top'],
			$_margin['right'],
			$_margin['bottom'],
			$_margin['left'],
			$_padding['top'],
			$_padding['right'],
			$_padding['bottom'],
			$_padding['left']
		);

		$label_style = sprintf(
			'font-family:%s;font-weight:%s;letter-spacing:%spx;word-spacing:%spx;margin-bottom:%dpx',
			$label_typography['fontFamily'] ?? '',
			$label_typography['fontWeight'] ?? 'normal',
			$label_typography['letterSpacing'] ?? 0,
			$label_typography['wordSpacing'] ?? 0,
			$label_spacing
		);

		$button_style = sprintf(
			'background-color:%s;color:%s;border-radius:%dpx %dpx %dpx %dpx;padding:%dpx %dpx %dpx %dpx',
			$button_background_color,
			$button_text_color,
			$button_border_radius['top'],
			$button_border_radius['right'],
			$button_border_radius['bottom'],
			$button_border_radius['left'],
			$button_padding['top'],
			$button_padding['right'],
			$button_padding['bottom'],
			$button_padding['left']
		);

		$html  = '<div class="wp-block-progressus-form ' . esc_attr( $custom_class ) . '" id="' . esc_attr( $custom_id ) . '" style="' . esc_attr( $form_style ) . '">';
		$html .= '<form class="progressus-form" data-form-name="' . esc_attr( $form_name ) . '" data-success-message="' . esc_attr( $success_message ) . '" data-error-message="' . esc_attr( $error_message ) . '" style="display:grid;gap:' . esc_attr( $row_gap ) . 'px">';

		foreach ( $form_fields as $field ) {
			$html .= '<div class="form-field">';
			$html .= '<label for="' . esc_attr( $field['customId'] ) . '" style="' . esc_attr( $label_style ) . '">';
			$html .= esc_html( $field['fieldLabel'] );
			if ( $field['required'] ) {
				$html .= ' *';
			}
			$html .= '</label>';

			if ( 'textarea' === $field['fieldType'] ) {
				$html .= '<textarea id="' . esc_attr( $field['customId'] ) . '" name="' . esc_attr( $field['customId'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" class="form-input size-' . esc_attr( $input_size ) . '"';
				if ( $field['required'] ) {
					$html .= ' required';
				}
				$html .= '></textarea>';
			} else {
				$html .= '<input type="' . esc_attr( $field['fieldType'] ) . '" id="' . esc_attr( $field['customId'] ) . '" name="' . esc_attr( $field['customId'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" class="form-input size-' . esc_attr( $input_size ) . '"';
				if ( $field['required'] ) {
					$html .= ' required';
				}
				$html .= '>';
			}

			$html .= '</div>';
		}

		$html .= '<div class="form-button-wrapper" style="display:flex;justify-content:' . esc_attr( $button_align ) . '">';
		$html .= '<button type="submit" class="form-submit-button" style="' . esc_attr( $button_style ) . '">' . esc_html( $button_text ) . '</button>';
		$html .= '</div>';
		$html .= '<div class="form-message" style="display:none"></div>';
		$html .= '</form></div>';

		return $html;
	}
}
