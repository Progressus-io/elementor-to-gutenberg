<?php
/**
 * Widget handler for Elementor search-form widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor search-form widget.
 */
class Search_Form_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor search-form widget to Gutenberg search block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_raw = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$custom_id  = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';

		$label       = $this->normalize_text( $settings['label'] ?? $settings['search_label'] ?? $settings['form_label'] ?? '' );
		$placeholder = $this->normalize_text( $settings['placeholder'] ?? $settings['search_placeholder'] ?? '' );
		$button_text = $this->normalize_text( $settings['button_text'] ?? $settings['buttonText'] ?? $settings['submit_text'] ?? '' );

		if ( '' === $label ) {
			$label = 'Search';
		}

		if ( '' === $placeholder ) {
			$placeholder = 'Search…';
		}

		if ( '' === $button_text ) {
			$button_text = 'Search';
		}

		$show_label = true;
		if ( array_key_exists( 'show_label', $settings ) ) {
			$show_label = $this->is_toggle_on( $settings['show_label'] );
		} elseif ( array_key_exists( 'label_display', $settings ) ) {
			$show_label = $this->is_toggle_on( $settings['label_display'] );
		}

		$button_position_setting = $settings['button_position'] ?? $settings['buttonPosition'] ?? '';
		$button_position         = $this->normalize_button_position( $button_position_setting );

		$button_type     = $settings['button_type'] ?? $settings['buttonType'] ?? '';
		$button_use_icon = $this->is_button_icon_enabled( $button_type, $settings['button_icon'] ?? null );

		$show_button = true;
		if ( array_key_exists( 'show_button', $settings ) ) {
			$show_button = $this->is_toggle_on( $settings['show_button'] );
		}

		if ( $this->is_button_hidden( $button_type, $show_button ) ) {
			$button_position = 'no-button';
		}

		$attributes = array(
			'label'         => $label,
			'showLabel'     => $show_label,
			'placeholder'   => $placeholder,
			'buttonText'    => $button_text,
			'buttonUseIcon' => $button_use_icon,
			'buttonPosition'=> $button_position,
		);

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$custom_classes = array();
		if ( '' !== trim( $custom_raw ) ) {
			foreach ( preg_split( '/\s+/', trim( $custom_raw ) ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		if ( ! empty( $custom_classes ) ) {
			$attributes['className'] = implode( ' ', array_unique( $custom_classes ) );
		}

		$alignment = $this->detect_block_alignment( $settings );
		if ( '' !== $alignment ) {
			$attributes['align'] = $alignment;
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'search', $attributes, '' );
	}

	/**
	 * Normalize scalar text values.
	 *
	 * @param mixed $value Input value.
	 */
	private function normalize_text( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['value'] ) ) {
				$value = $value['value'];
			} else {
				$value = '';
			}
		}

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Determine whether a toggle-like value is enabled.
	 *
	 * @param mixed $value Toggle value.
	 */
	private function is_toggle_on( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}

		if ( is_string( $value ) ) {
			$clean = strtolower( trim( $value ) );
			return in_array( $clean, array( 'yes', 'true', '1', 'on' ), true );
		}

		return false;
	}

	/**
	 * Normalize Elementor button position values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalize_button_position( $value ): string {
		if ( ! is_string( $value ) ) {
			return 'button-outside';
		}

		$clean = strtolower( trim( $value ) );
		switch ( $clean ) {
			case 'inside':
			case 'inner':
			case 'button-inside':
				return 'button-inside';
			case 'outside':
			case 'outer':
			case 'button-outside':
				return 'button-outside';
			case 'none':
			case 'no-button':
				return 'no-button';
			default:
				return 'button-outside';
		}
	}

	/**
	 * Determine whether to use an icon button.
	 *
	 * @param mixed $button_type Button type setting.
	 * @param mixed $button_icon Button icon setting.
	 */
	private function is_button_icon_enabled( $button_type, $button_icon ): bool {
		if ( is_string( $button_type ) ) {
			$clean = strtolower( trim( $button_type ) );
			if ( 'icon' === $clean ) {
				return true;
			}
		}

		return ! empty( $button_icon );
	}

	/**
	 * Determine when the button should be hidden.
	 *
	 * @param mixed $button_type Button type setting.
	 * @param bool $show_button Toggle value.
	 */
	private function is_button_hidden( $button_type, bool $show_button ): bool {
		if ( ! $show_button ) {
			return true;
		}

		if ( is_string( $button_type ) ) {
			$clean = strtolower( trim( $button_type ) );
			if ( 'none' === $clean ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect a valid block alignment value.
	 *
	 * @param array $settings Elementor widget settings.
	 */
	private function detect_block_alignment( array $settings ): string {
		$raw_align = $settings['align'] ?? $settings['alignment'] ?? '';
		if ( is_string( $raw_align ) ) {
			$raw_align = strtolower( trim( $raw_align ) );
			if ( in_array( $raw_align, array( 'left', 'center', 'right', 'wide', 'full' ), true ) ) {
				return $raw_align;
			}
		}

		$alignment = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment', 'text_align', 'button_align' ) );
		if ( in_array( $alignment, array( 'left', 'center', 'right', 'wide', 'full' ), true ) ) {
			return $alignment;
		}

		return '';
	}
}
