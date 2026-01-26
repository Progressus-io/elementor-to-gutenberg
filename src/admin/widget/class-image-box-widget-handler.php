<?php
/**
 * Widget handler for Elementor image box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function esc_url;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor image box widget.
 */
class Image_Box_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor image box to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings       = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( trim( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' ) );

		// Parse title typography
		$title_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'title_typography_' ) === 0 ) {
				$new_key = substr( $key, strlen( 'title_' ) );
				$title_typography_settings[ $new_key ] = $value;
			}
		}
		$title_typography = Style_Parser::parse_typography( $title_typography_settings );
		$title_typography_attr = isset( $title_typography['attributes'] ) ? $title_typography['attributes'] : array();

		// Parse description typography
		$description_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'description_typography_' ) === 0 ) {
				$new_key = substr( $key, strlen( 'description_' ) );
				$description_typography_settings[ $new_key ] = $value;
			}
		}
		$description_typography = Style_Parser::parse_typography( $description_typography_settings );
		$description_typography_attr = isset( $description_typography['attributes'] ) ? $description_typography['attributes'] : array();

		$image_data   = $this->resolve_image_data( $settings );
		$image_url    = $image_data['url'] ?? '';
		$image_id     = $image_data['id'] ?? 0;
		$image_alt    = $image_data['alt'] ?? '';
		$image_width  = $this->sanitize_slider_value( $settings['image_width'] ?? null, 100 );
		$image_height = $this->sanitize_slider_value( $settings['image_height'] ?? null, 100 );
		$title        = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
		$description  = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : '';
		$text_align   = isset( $settings['text_align'] ) ? (string) $settings['text_align'] : 'left';

		$segments = array();

		if ( '' !== $image_url ) {
			$object_fit = isset( $settings['image_object_fit'] ) ? (string) $settings['image_object_fit'] : 'cover';
			$object_position = isset( $settings['image_object_position'] ) ? (string) $settings['image_object_position'] : 'center center';

			$image_html = sprintf(
				'<img src="%1$s" alt="%2$s" style="width:%3$dpx;height:%4$dpx;object-fit:%5$s;object-position:%6$s;display:block;"/>',
				esc_url( $image_url ),
				esc_attr( $image_alt ),
				$image_width,
				$image_height,
				esc_attr( $object_fit ),
				esc_attr( $object_position )
			);
			$segments[] = '<div class="image-box-image">' . $image_html . '</div>';
		}

		// Extract title typography attributes with fallback to Elementor color settings
		$title_size = isset( $title_typography_attr['fontSize'] ) ? (int) $title_typography_attr['fontSize'] : 20;
		$title_color = isset( $settings['title_color'] ) ? (string) $settings['title_color'] : ( isset( $title_typography_attr['color'] ) ? $title_typography_attr['color'] : '#000000' );
		$title_font_family = isset( $title_typography_attr['fontFamily'] ) ? $title_typography_attr['fontFamily'] : '';
		$title_font_weight = isset( $title_typography_attr['fontWeight'] ) ? $title_typography_attr['fontWeight'] : '';
		$title_text_transform = isset( $title_typography_attr['textTransform'] ) ? $title_typography_attr['textTransform'] : '';
		$title_font_style = isset( $title_typography_attr['fontStyle'] ) ? $title_typography_attr['fontStyle'] : '';
		$title_text_decoration = isset( $title_typography_attr['textDecoration'] ) ? $title_typography_attr['textDecoration'] : '';
		$title_line_height = isset( $title_typography_attr['lineHeight'] ) ? $title_typography_attr['lineHeight'] : '';
		$title_letter_spacing = isset( $title_typography_attr['letterSpacing'] ) ? $title_typography_attr['letterSpacing'] : '';
		$title_word_spacing = isset( $title_typography_attr['wordSpacing'] ) ? $title_typography_attr['wordSpacing'] : '';

		// Extract description typography attributes with fallback to Elementor color settings
		$description_size = isset( $description_typography_attr['fontSize'] ) ? (int) $description_typography_attr['fontSize'] : 14;
		$description_color = isset( $settings['description_color'] ) ? (string) $settings['description_color'] : ( isset( $description_typography_attr['color'] ) ? $description_typography_attr['color'] : '#666666' );
		$description_font_family = isset( $description_typography_attr['fontFamily'] ) ? $description_typography_attr['fontFamily'] : '';
		$description_font_weight = isset( $description_typography_attr['fontWeight'] ) ? $description_typography_attr['fontWeight'] : '';
		$description_text_transform = isset( $description_typography_attr['textTransform'] ) ? $description_typography_attr['textTransform'] : '';
		$description_font_style = isset( $description_typography_attr['fontStyle'] ) ? $description_typography_attr['fontStyle'] : '';
		$description_text_decoration = isset( $description_typography_attr['textDecoration'] ) ? $description_typography_attr['textDecoration'] : '';
		$description_line_height = isset( $description_typography_attr['lineHeight'] ) ? $description_typography_attr['lineHeight'] : '';
		$description_letter_spacing = isset( $description_typography_attr['letterSpacing'] ) ? $description_typography_attr['letterSpacing'] : '';
		$description_word_spacing = isset( $description_typography_attr['wordSpacing'] ) ? $description_typography_attr['wordSpacing'] : '';

		if ( '' !== trim( $title ) ) {
			$title_style_parts = array(
				'font-size:' . esc_attr( $title_size ) . 'px',
				'color:' . esc_attr( $title_color ),
			);
			if ( $title_font_family ) $title_style_parts[] = 'font-family:' . esc_attr( $title_font_family );
			if ( $title_font_weight ) $title_style_parts[] = 'font-weight:' . esc_attr( $title_font_weight );
			if ( $title_text_transform ) $title_style_parts[] = 'text-transform:' . esc_attr( $title_text_transform );
			if ( $title_font_style ) $title_style_parts[] = 'font-style:' . esc_attr( $title_font_style );
			if ( $title_text_decoration ) $title_style_parts[] = 'text-decoration:' . esc_attr( $title_text_decoration );
			if ( $title_line_height ) $title_style_parts[] = 'line-height:' . esc_attr( $title_line_height );
			if ( $title_letter_spacing ) $title_style_parts[] = 'letter-spacing:' . esc_attr( $title_letter_spacing );
			if ( $title_word_spacing ) $title_style_parts[] = 'word-spacing:' . esc_attr( $title_word_spacing );
			$segments[] = '<h3 class="image-box-title" style="' . implode( ';', $title_style_parts ) . '">' . esc_html( $title ) . '</h3>';
		}
		if ( '' !== trim( $description ) ) {
			$sanitized_description = wp_kses_post( $description );
			// Normalize newlines to avoid validation mismatches with Gutenberg save output.
			$sanitized_description_no_newlines = str_replace( array( "\r\n", "\r", "\n" ), '', $sanitized_description );
			$description_style_parts = array(
				'font-size:' . esc_attr( $description_size ) . 'px',
				'color:' . esc_attr( $description_color ),
			);
			if ( $description_font_family ) $description_style_parts[] = 'font-family:' . esc_attr( $description_font_family );
			if ( $description_font_weight ) $description_style_parts[] = 'font-weight:' . esc_attr( $description_font_weight );
			if ( $description_text_transform ) $description_style_parts[] = 'text-transform:' . esc_attr( $description_text_transform );
			if ( $description_font_style ) $description_style_parts[] = 'font-style:' . esc_attr( $description_font_style );
			if ( $description_text_decoration ) $description_style_parts[] = 'text-decoration:' . esc_attr( $description_text_decoration );
			if ( $description_line_height ) $description_style_parts[] = 'line-height:' . esc_attr( $description_line_height );
			if ( $description_letter_spacing ) $description_style_parts[] = 'letter-spacing:' . esc_attr( $description_letter_spacing );
			if ( $description_word_spacing ) $description_style_parts[] = 'word-spacing:' . esc_attr( $description_word_spacing );
			$segments[] = '<div class="image-box-description" style="' . implode( ';', $description_style_parts ) . '">' . $sanitized_description_no_newlines . '</div>';
		}

		$wrapper_classes = array_merge( 
			array( 'wp-block-image-box' ), 
			$text_align ? array( 'has-text-align-' . $text_align ) : array(),
			$custom_classes 
		);
		$wrapper_attrs   = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		if ( '' !== $custom_id ) {
			$wrapper_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		$alignment_value = $text_align ?: 'left';
		$wrapper_attrs[] = 'style="text-align:' . esc_attr( $alignment_value ) . '"';

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . implode( '', $segments ) . '</div>';

		// Build block attributes for the new `gutenberg/image-box` block.
		// Ensure attributes match the exact HTML inserted into the block content.
		// Use the sanitized output for `description` so save() and the post body match.
		$sanitized_description = '' !== trim( $description ) ? wp_kses_post( $description ) : '';
		// Store the same newline-normalized description in attributes so attributes JSON
		// matches the inner HTML used above.
		$sanitized_description_no_newlines = '' !== $sanitized_description ? str_replace( array( "\r\n", "\r", "\n" ), '', $sanitized_description ) : '';

		$block_attributes = array(
			'imageUrl'          => $image_url,
			'imageId'           => $image_id,
			'imageAlt'          => $image_alt,
			'imageWidth'        => $image_width,
			'imageHeight'       => $image_height,
			'objectFit'         => isset( $settings['image_object_fit'] ) ? (string) $settings['image_object_fit'] : 'cover',
			'objectPosition'    => isset( $settings['image_object_position'] ) ? (string) $settings['image_object_position'] : 'center center',
			'link'              => isset( $settings['link']['url'] ) ? (string) $settings['link']['url'] : '',
			'linkTarget'        => ! empty( $settings['link']['is_external'] ),
			'nofollow'          => ! empty( $settings['link']['nofollow'] ),
			'title'             => $title,
			'description'       => $sanitized_description_no_newlines,
			'titleSize'         => $title_size,
			'titleColor'        => $title_color,
			'titleFontFamily'   => $title_font_family,
			'titleFontWeight'   => $title_font_weight,
			'titleTextTransform'=> $title_text_transform,
			'titleFontStyle'    => $title_font_style,
			'titleTextDecoration'=> $title_text_decoration,
			'titleLineHeight'   => $title_line_height,
			'titleLetterSpacing'=> $title_letter_spacing,
			'titleWordSpacing'  => $title_word_spacing,
			'descriptionSize'   => $description_size,
			'descriptionColor'  => $description_color,
			'descriptionFontFamily' => $description_font_family,
			'descriptionFontWeight' => $description_font_weight,
			'descriptionTextTransform' => $description_text_transform,
			'descriptionFontStyle' => $description_font_style,
			'descriptionTextDecoration' => $description_text_decoration,
			'descriptionLineHeight' => $description_line_height,
			'descriptionLetterSpacing' => $description_letter_spacing,
			'descriptionWordSpacing' => $description_word_spacing,
			'align'             => $text_align ?: 'left',
			'className'         => implode( ' ', $custom_classes ),
			'anchor'            => $custom_id,
		);

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'gutenberg/image-box', $block_attributes, $content );
	}

	/**
	 * Resolve the image data from Elementor settings.
	 *
	 * @param array $settings The widget settings.
	 *
	 * @return array Image data with url, id, and alt.
	 */
	private function resolve_image_data( array $settings ): array {
		$image_data = array(
			'url' => '',
			'id'  => 0,
			'alt' => '',
		);

		// Check for image in settings.
		if ( isset( $settings['image'] ) && is_array( $settings['image'] ) ) {
			$image_data['url'] = isset( $settings['image']['url'] ) ? (string) $settings['image']['url'] : '';
			$image_data['id']  = isset( $settings['image']['id'] ) ? (int) $settings['image']['id'] : 0;
		}

		// Check for alt text.
		if ( isset( $settings['image_alt'] ) ) {
			$image_data['alt'] = (string) $settings['image_alt'];
		}

		return $image_data;
	}

	/**
	 * Sanitize custom class string into individual classes.
	 *
	 * @param string $class_string The class string to sanitize.
	 *
	 * @return array Array of sanitized classes.
	 */
	private function sanitize_custom_classes( string $class_string ): array {
		$classes = array();
		foreach ( preg_split( '/\s+/', $class_string ) as $class ) {
			$clean = Style_Parser::clean_class( $class );
			if ( '' === $clean ) {
				continue;
			}
			$classes[] = $clean;
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Sanitize slider or numeric values from Elementor settings.
	 *
	 * @param mixed $value   The value to sanitize.
	 * @param int   $default The default value.
	 *
	 * @return int The sanitized value.
	 */
	private function sanitize_slider_value( $value, int $default ): int {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
				return (int) round( $value['size'] );
			}
			if ( isset( $value['value'] ) && is_numeric( $value['value'] ) ) {
				return (int) round( $value['value'] );
			}
		}
		if ( is_numeric( $value ) ) {
			return (int) round( $value );
		}

		return $default;
	}
}
