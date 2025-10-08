<?php
/**
 * Widget handler for Elementor icon box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function sanitize_html_class;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon box widget.
 */
class Icon_Box_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor icon box widget.
	 *
	 * @param array $element Elementor widget data.
	 */
	public function handle( array $element ): string {
		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$child_blocks = array();

		$icon_block = $this->render_icon_block( $settings );
		if ( '' !== $icon_block ) {
			$child_blocks[] = $icon_block;
		}

		$heading_block = $this->render_heading_block( $settings );
		if ( '' !== $heading_block ) {
			$child_blocks[] = $heading_block;
		}

		$description_block = $this->render_description_block( $settings );
		if ( '' !== $description_block ) {
			$child_blocks[] = $description_block;
		}

		if ( empty( $child_blocks ) ) {
			return '';
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$attributes           = Style_Parser::parse_container_styles( $settings );
		$attributes['layout'] = array( 'type' => 'constrained' );

		return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
	}

	/**
	 * Render the icon portion of the widget.
	 *
	 * @param array $settings Widget settings.
	 */
	private function render_icon_block( array $settings ): string {
		$icon_data = $settings['selected_icon'] ?? null;
		if ( is_array( $icon_data ) && isset( $icon_data['value'] ) && '' !== $icon_data['value'] ) {
			return $this->render_icon_html_block( $icon_data['value'], $settings );
		}

		$fallback_icon = isset( $settings['icon'] ) ? (string) $settings['icon'] : '';
		if ( '' !== $fallback_icon ) {
			return $this->render_icon_html_block( $fallback_icon, $settings );
		}

		$icon_image = $settings['icon_image'] ?? null;
		if ( is_array( $icon_image ) && ! empty( $icon_image['url'] ) ) {
			$image_settings = array(
				'image' => $icon_image,
				'link'  => $settings['link'] ?? array(),
			);

			$image_handler = new Image_Widget_Handler();

			return $image_handler->handle( array( 'settings' => $image_settings ) );
		}

		return '';
	}

	/**
	 * Render the icon markup as an HTML block.
	 *
	 * @param string $icon_class Icon class string.
	 * @param array $settings Widget settings.
	 */
	private function render_icon_html_block( string $icon_class, array $settings ): string {
		$classes      = array( 'wp-block-group__icon', 'elementor-icon-box-icon' );
		$icon_classes = array( 'elementor-icon' );

		foreach ( preg_split( '/\s+/', $icon_class ) as $class ) {
			$class = trim( $class );
			if ( '' !== $class ) {
				$icon_classes[] = sanitize_html_class( $class );
			}
		}

		$style_rules = array();
		if ( isset( $settings['icon_size']['size'] ) && is_numeric( $settings['icon_size']['size'] ) ) {
			$unit          = $settings['icon_size']['unit'] ?? 'px';
			$style_rules[] = 'font-size:' . ( (float) $settings['icon_size']['size'] ) . $unit . ';';
		}

		if ( isset( $settings['icon_color'] ) ) {
			$color = strtolower( (string) $settings['icon_color'] );
			if ( '' !== $color ) {
				$style_rules[] = 'color:' . $color . ';';
			}
		}

		$icon_markup = sprintf(
			'<span class="%s"%s aria-hidden="true"></span>',
			esc_attr( implode( ' ', array_unique( $icon_classes ) ) ),
			empty( $style_rules ) ? '' : ' style="' . esc_attr( implode( '', $style_rules ) ) . '"'
		);

		$inner_html = sprintf(
			'<div class="%s">%s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$icon_markup
		);

		return Block_Builder::build( 'html', array(), $inner_html );
	}

	/**
	 * Render the title block.
	 *
	 * @param array $settings Widget settings.
	 */
	private function render_heading_block( array $settings ): string {
		$title = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : (string) ( $settings['title'] ?? '' );
		if ( '' === trim( $title ) ) {
			return '';
		}

		$heading_settings = array(
			'title'        => $title,
			'header_size'  => $settings['title_size'] ?? $settings['title_tag'] ?? 'h4',
			'title_color'  => $settings['title_color'] ?? '',
			'_css_classes' => $settings['title_css_classes'] ?? '',
			'_element_id'  => $settings['title_element_id'] ?? '',
		);

		$heading_settings += $this->remap_typography_settings( $settings, 'title_' );

		$handler = new Heading_Widget_Handler();

		return $handler->handle( array( 'settings' => $heading_settings ) );
	}

	/**
	 * Render the description block.
	 *
	 * @param array $settings Widget settings.
	 */
	private function render_description_block( array $settings ): string {
		$description = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : (string) ( $settings['description'] ?? '' );
		if ( '' === trim( $description ) ) {
			return '';
		}

		$text_settings = array(
			'editor'       => $description,
			'text_color'   => $settings['description_color'] ?? '',
			'_css_classes' => $settings['description_css_classes'] ?? '',
			'_element_id'  => $settings['description_element_id'] ?? '',
		);

		$text_settings += $this->remap_typography_settings( $settings, 'description_' );

		$handler = new Text_Editor_Widget_Handler();

		return $handler->handle( array( 'settings' => $text_settings ) );
	}

	/**
	 * Remap prefixed typography settings.
	 *
	 * @param array $settings Widget settings.
	 * @param string $prefix Prefix to strip.
	 */
	private function remap_typography_settings( array $settings, string $prefix ): array {
		$mapped      = array();
		$prefix_base = $prefix . 'typography_';
		$prefix_len  = strlen( $prefix_base );

		foreach ( $settings as $key => $value ) {
			if ( 0 !== strpos( $key, $prefix_base ) ) {
				continue;
			}

			$suffix = substr( $key, $prefix_len );
			if ( false === $suffix ) {
				continue;
			}

			$mapped[ 'typography_' . $suffix ] = $value;
		}

		return $mapped;
	}
}
