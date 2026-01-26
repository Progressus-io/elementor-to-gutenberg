<?php
/**
 * Testimonial Carousel Widget Handler
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor testimonial-carousel widget.
 */
class Testimonial_Carousel_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor testimonial-carousel widget to Gutenberg testimonials block.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';

		// Build block attributes from Elementor settings.
		$attributes = array();

		// Slides - convert Elementor slides to Gutenberg format.
		$slides = array();
		if ( ! empty( $settings['slides'] ) && is_array( $settings['slides'] ) ) {
			foreach ( $settings['slides'] as $slide ) {
				$content = isset( $slide['content'] ) ? (string) $slide['content'] : '';

				$content = $this->normalize_testimonials_text_value( $content );

				$name     = isset( $slide['name'] ) ? (string) $slide['name'] : '';
				$title    = isset( $slide['title'] ) ? (string) $slide['title'] : '';
				$name     = $this->normalize_testimonials_text_value( $name );
				$title    = $this->normalize_testimonials_text_value( $title );
				$slides[] = array(
					'content'  => $content,
					'name'     => $name,
					'title'    => $title,
					'imageUrl' => isset( $slide['image']['url'] ) ? (string) $slide['image']['url'] : '',
					'imageId'  => isset( $slide['image']['id'] ) ? (int) $slide['image']['id'] : 0,
				);
			}
		}
		if ( ! empty( $slides ) ) {
			$attributes['slides'] = $slides;
		}

		// Layout.
		$attributes['layout'] = $settings['layout'] ?? 'image_above';

		// Alignment.
		$attributes['alignment'] = $settings['alignment'] ?? 'left';

		// Slides per view.
		if ( isset( $settings['slides_per_view'] ) ) {
			$attributes['slidesPerView'] = (int) $settings['slides_per_view'];
		}

		// Slides to scroll.
		if ( isset( $settings['slides_to_scroll'] ) ) {
			$attributes['slidesToScroll'] = (int) $settings['slides_to_scroll'];
		}

		// Width.
		if ( isset( $settings['width']['size'] ) ) {
			$attributes['width'] = (int) $settings['width']['size'];
		}

		// Space between.
		if ( isset( $settings['space_between']['size'] ) ) {
			$attributes['spaceBetween'] = (int) $settings['space_between']['size'];
		}

		// Slide background color.
		if ( ! empty( $settings['slide_background_color'] ) ) {
			$attributes['slideBackgroundColor'] = $settings['slide_background_color'];
		}

		// Slide border size.
		if ( ! empty( $settings['slide_border_size'] ) ) {
			$attributes['slideBorderSize'] = array(
				'top'    => (int) ( $settings['slide_border_size']['top'] ?? 1 ),
				'right'  => (int) ( $settings['slide_border_size']['right'] ?? 1 ),
				'bottom' => (int) ( $settings['slide_border_size']['bottom'] ?? 1 ),
				'left'   => (int) ( $settings['slide_border_size']['left'] ?? 1 ),
			);
		}

		// Slide border radius.
		if ( isset( $settings['slide_border_radius']['size'] ) ) {
			$attributes['slideBorderRadius'] = (int) $settings['slide_border_radius']['size'];
		}

		// Slide border color.
		if ( ! empty( $settings['slide_border_color'] ) ) {
			$attributes['slideBorderColor'] = $settings['slide_border_color'];
		}

		// Slide padding.
		if ( ! empty( $settings['slide_padding'] ) ) {
			$attributes['slidePadding'] = array(
				'top'    => (int) ( $settings['slide_padding']['top'] ?? 20 ),
				'right'  => (int) ( $settings['slide_padding']['right'] ?? 20 ),
				'bottom' => (int) ( $settings['slide_padding']['bottom'] ?? 20 ),
				'left'   => (int) ( $settings['slide_padding']['left'] ?? 20 ),
			);
		}

		// Content gap.
		if ( isset( $settings['content_gap']['size'] ) ) {
			$attributes['contentGap'] = (int) $settings['content_gap']['size'];
		}

		// Content color.
		if ( ! empty( $settings['content_color'] ) ) {
			$attributes['contentColor'] = $settings['content_color'];
		}

		$raw_line_height = 1.5;
		if ( isset( $settings['content_typography_line_height']['size'] ) ) {
			$raw_line_height = $settings['content_typography_line_height']['size'];
		}

		// Content typography.
		$content_typography              = array(
			'fontSize'       => (int) ( $settings['content_typography_font_size']['size'] ?? 16 ),
			'fontWeight'     => $settings['content_typography_font_weight'] ?? 'normal',
			'fontStyle'      => $settings['content_typography_font_style'] ?? 'normal',
			'textDecoration' => $settings['content_typography_text_decoration'] ?? 'none',
			'lineHeight'     => is_numeric( $raw_line_height ) ? (float) $raw_line_height : 1.5,
			'letterSpacing'  => (float) ( $settings['content_typography_letter_spacing']['size'] ?? 0 ),
			'wordSpacing'    => (float) ( $settings['content_typography_word_spacing']['size'] ?? 0 ),
			'fontFamily'     => $settings['content_typography_font_family'] ?? '',
		);
		$attributes['contentTypography'] = $content_typography;

		// Name color.
		if ( ! empty( $settings['name_color'] ) ) {
			$attributes['nameColor'] = $settings['name_color'];
		}

		// Title color.
		if ( ! empty( $settings['title_color'] ) ) {
			$attributes['titleColor'] = $settings['title_color'];
		}

		// Image size.
		if ( isset( $settings['image_size']['size'] ) ) {
			$attributes['imageSize'] = (int) $settings['image_size']['size'];
		}

		// Image gap.
		if ( isset( $settings['image_gap']['size'] ) ) {
			$attributes['imageGap'] = (int) $settings['image_gap']['size'];
		}

		// Image border radius.
		if ( isset( $settings['image_border_radius']['size'] ) ) {
			$attributes['imageBorderRadius'] = (int) $settings['image_border_radius']['size'];
		}

		// Arrows size.
		if ( isset( $settings['arrows_size']['size'] ) ) {
			$attributes['arrowsSize'] = (int) $settings['arrows_size']['size'];
		}

		// Arrows color.
		if ( ! empty( $settings['arrows_color'] ) ) {
			$attributes['arrowsColor'] = $settings['arrows_color'];
		}

		// Pagination gap.
		if ( isset( $settings['pagination_gap']['size'] ) ) {
			$attributes['paginationGap'] = (int) $settings['pagination_gap']['size'];
		}

		// Pagination size.
		if ( isset( $settings['pagination_size']['size'] ) ) {
			$attributes['paginationSize'] = (int) $settings['pagination_size']['size'];
		}

		// Pagination color inactive.
		if ( ! empty( $settings['pagination_color_inactive'] ) ) {
			$attributes['paginationColorInactive'] = $settings['pagination_color_inactive'];
		}

		// Spacing - use Style_Parser.
		$spacing_data = Style_Parser::parse_spacing( $settings );

		if ( ! empty( $spacing_data['attributes']['_margin'] ) ) {
			$attributes['_margin'] = array(
				'top'    => (int) ( $spacing_data['attributes']['_margin']['top'] ?? 0 ),
				'right'  => (int) ( $spacing_data['attributes']['_margin']['right'] ?? 0 ),
				'bottom' => (int) ( $spacing_data['attributes']['_margin']['bottom'] ?? 0 ),
				'left'   => (int) ( $spacing_data['attributes']['_margin']['left'] ?? 0 ),
			);
		}

		if ( ! empty( $spacing_data['attributes']['_padding'] ) ) {
			$attributes['_padding'] = array(
				'top'    => (int) ( $spacing_data['attributes']['_padding']['top'] ?? 0 ),
				'right'  => (int) ( $spacing_data['attributes']['_padding']['right'] ?? 0 ),
				'bottom' => (int) ( $spacing_data['attributes']['_padding']['bottom'] ?? 0 ),
				'left'   => (int) ( $spacing_data['attributes']['_padding']['left'] ?? 0 ),
			);
		}

		// Custom ID and class.
		if ( ! empty( $settings['_element_id'] ) ) {
			$attributes['customId'] = $settings['_element_id'];
		}
		if ( ! empty( $settings['_css_classes'] ) ) {
			$attributes['customClass'] = $settings['_css_classes'];
		}

		// Encode attributes for the block.
		$attributes = $this->sanitize_for_json( $attributes );

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$attributes = $this->prune_block_attrs_against_schema( 'progressus/testimonials', $attributes );

		$rendered_html = $this->generate_testimonials_html( $attributes );

		$parsed = array(
			'blockName'    => 'progressus/testimonials',
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => $rendered_html,
			'innerContent' => array( $rendered_html ),
		);

		if ( function_exists( 'serialize_block' ) ) {
			$block_content = serialize_block( $parsed );
		} else {

			$attributes_json = wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $attributes_json || '' === $attributes_json ) {
				$attributes_json = '{}';
			}

			$block_content = '<!-- wp:progressus/testimonials ' . $attributes_json . ' -->';
			$block_content .= $rendered_html;
			$block_content .= '<!-- /wp:progressus/testimonials -->';
		}

		return $block_content . "\n";

	}

	/**
	 * Generate the rendered HTML content for the testimonials block.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string Rendered HTML content.
	 */
	private function generate_testimonials_html( array $attributes ): string {
		$slides                    = $attributes['slides'] ?? array();
		$layout                    = $attributes['layout'] ?? 'image_above';
		$alignment                 = $attributes['alignment'] ?? 'left';
		$slides_per_view           = $attributes['slidesPerView'] ?? 1;
		$space_between             = $attributes['spaceBetween'] ?? 20;
		$width                     = $attributes['width'] ?? 100;
		$slide_background_color    = $attributes['slideBackgroundColor'] ?? '';
		$slide_border_size         = $attributes['slideBorderSize'] ?? array(
			'top'    => 1,
			'right'  => 1,
			'bottom' => 1,
			'left'   => 1
		);
		$slide_border_radius       = $attributes['slideBorderRadius'] ?? 0;
		$slide_border_color        = $attributes['slideBorderColor'] ?? '';
		$slide_padding             = $attributes['slidePadding'] ?? array(
			'top'    => 20,
			'right'  => 20,
			'bottom' => 20,
			'left'   => 20
		);
		$content_gap               = $attributes['contentGap'] ?? 20;
		$content_color             = $attributes['contentColor'] ?? '';
		$content_typography        = $attributes['contentTypography'] ?? array();
		$name_color                = $attributes['nameColor'] ?? '';
		$title_color               = $attributes['titleColor'] ?? '';
		$image_size                = $attributes['imageSize'] ?? 80;
		$image_gap                 = $attributes['imageGap'] ?? 20;
		$image_border_radius       = $attributes['imageBorderRadius'] ?? 50;
		$arrows_size               = $attributes['arrowsSize'] ?? 20;
		$arrows_color              = $attributes['arrowsColor'] ?? '';
		$pagination_gap            = $attributes['paginationGap'] ?? 10;
		$pagination_size           = $attributes['paginationSize'] ?? 10;
		$pagination_color_inactive = $attributes['paginationColorInactive'] ?? '';
		$_margin                   = $attributes['_margin'] ?? array(
			'top'    => 0,
			'right'  => 0,
			'bottom' => 0,
			'left'   => 0
		);
		$_padding                  = $attributes['_padding'] ?? array(
			'top'    => 0,
			'right'  => 0,
			'bottom' => 0,
			'left'   => 0
		);
		$custom_id                 = $attributes['customId'] ?? '';
		$custom_class              = $attributes['customClass'] ?? '';


		$line_height = (string) ( $content_typography['lineHeight'] ?? '1.5' );
		$line_height = trim( $line_height );
		if ( is_numeric( $line_height ) ) {
			$line_height = rtrim( rtrim( sprintf( '%.6F', (float) $line_height ), '0' ), '.' ) . 'px';
		} elseif ( '' !== $line_height && ! preg_match( '/px$/i', $line_height ) ) {
			$line_height = $line_height . 'px';
		}

		$content_style = sprintf(
			'color:%s;font-size:%dpx;font-weight:%s;font-style:%s;text-decoration:%s;line-height:%s;letter-spacing:%spx;word-spacing:%spx;font-family:%s;margin-bottom:%dpx',
			$content_color,
			(int) ( $content_typography['fontSize'] ?? 16 ),
			(string) ( $content_typography['fontWeight'] ?? 'normal' ),
			(string) ( $content_typography['fontStyle'] ?? 'normal' ),
			(string) ( $content_typography['textDecoration'] ?? 'none' ),
			$line_height,
			(string) ( $content_typography['letterSpacing'] ?? 0 ),
			(string) ( $content_typography['wordSpacing'] ?? 0 ),
			(string) ( $content_typography['fontFamily'] ?? '' ),
			(int) $content_gap
		);


		$custom_class = trim( (string) $custom_class );
		$class_attr   = trim( 'wp-block-progressus-testimonials ' . $custom_class );

		$wrapper_style = sprintf(
			'margin:%dpx %dpx %dpx %dpx;padding:%dpx %dpx %dpx %dpx',
			(int) ( $_margin['top'] ?? 0 ),
			(int) ( $_margin['right'] ?? 0 ),
			(int) ( $_margin['bottom'] ?? 0 ),
			(int) ( $_margin['left'] ?? 0 ),
			(int) ( $_padding['top'] ?? 0 ),
			(int) ( $_padding['right'] ?? 0 ),
			(int) ( $_padding['bottom'] ?? 0 ),
			(int) ( $_padding['left'] ?? 0 )
		);

		$html = '<div class="' . esc_attr( $class_attr ) . '" id="' . esc_attr( (string) $custom_id ) . '" style="' . esc_attr( $wrapper_style ) . '">';

		$html .= '<div class="progressus-testimonials-carousel" data-slides-per-view="' . esc_attr( $slides_per_view ) . '" data-space-between="' . esc_attr( $space_between ) . '" data-arrows-size="' . esc_attr( $arrows_size ) . '" data-arrows-color="' . esc_attr( $arrows_color ) . '" data-pagination-gap="' . esc_attr( $pagination_gap ) . '" data-pagination-size="' . esc_attr( $pagination_size ) . '" data-pagination-color="' . esc_attr( $pagination_color_inactive ) . '">';
		$html .= '<div class="swiper"><div class="swiper-wrapper">';

		foreach ( $slides as $slide ) {
			$slide_style = sprintf(
				'background-color:%s;border:%dpx solid %s;border-radius:%dpx;padding:%dpx %dpx %dpx %dpx;text-align:%s;max-width:%d%%;margin:0 auto',
				$slide_background_color,
				$slide_border_size['top'],
				$slide_border_color ?: '#ddd',
				$slide_border_radius,
				$slide_padding['top'],
				$slide_padding['right'],
				$slide_padding['bottom'],
				$slide_padding['left'],
				$alignment,
				$width
			);

			$line_height = (string) ( $content_typography['lineHeight'] ?? '1.5' );
			$line_height = trim( $line_height );
			if ( is_numeric( $line_height ) ) {
				$line_height = rtrim( rtrim( sprintf( '%.6F', (float) $line_height ), '0' ), '.' ) . 'px';
			} elseif ( '' !== $line_height && ! preg_match( '/px$/i', $line_height ) ) {
				$line_height = $line_height . 'px';
			}

			$content_style = sprintf(
				'color:%s;font-size:%dpx;font-weight:%s;font-style:%s;text-decoration:%s;line-height:%s;letter-spacing:%spx;word-spacing:%spx;font-family:%s;margin-bottom:%dpx',
				$content_color,
				(int) ( $content_typography['fontSize'] ?? 16 ),
				(string) ( $content_typography['fontWeight'] ?? 'normal' ),
				(string) ( $content_typography['fontStyle'] ?? 'normal' ),
				(string) ( $content_typography['textDecoration'] ?? 'none' ),
				$line_height,
				(string) ( $content_typography['letterSpacing'] ?? 0 ),
				(string) ( $content_typography['wordSpacing'] ?? 0 ),
				(string) ( $content_typography['fontFamily'] ?? '' ),
				(int) $content_gap
			);

			// Build image style based on layout.
			$image_style_parts = array(
				sprintf( 'width:%dpx', $image_size ),
				sprintf( 'height:%dpx', $image_size ),
				sprintf( 'border-radius:%d%%', $image_border_radius ),
				'object-fit:cover',
			);

			if ( 'image_above' === $layout ) {
				$image_style_parts[] = sprintf( 'margin-bottom:%dpx', $image_gap );
			} elseif ( 'image_inline' === $layout ) {
				$image_style_parts[] = sprintf( 'margin-right:%dpx', $image_gap );
			} elseif ( 'image_stacked' === $layout ) {
				$image_style_parts[] = sprintf( 'margin-top:%dpx', $content_gap );
				$image_style_parts[] = sprintf( 'margin-bottom:%dpx', $image_gap );
			} elseif ( 'image_left' === $layout ) {
				$image_style_parts[] = sprintf( 'margin-right:%dpx', $image_gap );
			} elseif ( 'image_right' === $layout ) {
				$image_style_parts[] = sprintf( 'margin-left:%dpx', $image_gap );
			}

			$image_style = implode( ';', $image_style_parts );

			$html .= '<div class="swiper-slide">';
			$html .= '<div class="testimonial-slide layout-' . esc_attr( $layout ) . '" style="' . esc_attr( $slide_style ) . '">';

			$image_html      = ! empty( $slide['imageUrl'] ) ? '<img src="' . esc_url( $slide['imageUrl'] ) . '" alt="' . esc_attr( $slide['name'] ) . '" style="' . esc_attr( $image_style ) . '">' : '';
			$content_html    = '<div class="testimonial-content" style="' . esc_attr( $content_style ) . '">' . $this->escape_text_node( (string) ( $slide['content'] ?? '' ) ) . '</div>';
			$name_title_html = '<div class="testimonial-name" style="color:' . esc_attr( $name_color ) . ';font-weight:bold;margin-bottom:5px">' . $this->escape_text_node( (string) ( $slide['name'] ?? '' ) ) . '</div><div class="testimonial-title" style="color:' . esc_attr( $title_color ) . ';font-size:14px">' . $this->escape_text_node( (string) ( $slide['title'] ?? '' ) ) . '</div>';

			if ( 'image_above' === $layout ) {
				$html .= $image_html . $content_html . $name_title_html;
			} elseif ( 'image_inline' === $layout ) {
				$html .= $content_html;
				$html .= '<div style="display:flex;align-items:center">' . $image_html . '<div>' . $name_title_html . '</div></div>';
			} elseif ( 'image_stacked' === $layout ) {
				$html .= $content_html . $image_html . $name_title_html;
			} elseif ( 'image_left' === $layout ) {
				$html .= '<div style="display:flex">' . $image_html . '<div style="flex:1">' . $content_html . $name_title_html . '</div></div>';
			} elseif ( 'image_right' === $layout ) {
				$html .= '<div style="display:flex"><div style="flex:1">' . $content_html . $name_title_html . '</div>' . $image_html . '</div>';
			}

			$html .= '</div></div>';
		}

		$html .= '</div>';
		$html .= '<div class="swiper-button-prev"></div>';
		$html .= '<div class="swiper-button-next"></div>';
		$html .= '<div class="swiper-pagination"></div>';
		$html .= '</div></div></div>';

		return $html;
	}

	/**
	 * Recursively sanitize strings to valid UTF-8 to avoid wp_json_encode failures.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return mixed
	 */
	private function sanitize_for_json( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->sanitize_for_json( $v );
			}

			return $value;
		}

		if ( is_string( $value ) ) {
			$value = wp_check_invalid_utf8( $value, true );

			// Strip ASCII control chars except \t \n \r
			$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );

			return $value;
		}

		return $value;
	}

	/**
	 * Escape plain text for HTML text nodes without encoding quotes.
	 *
	 * @param string $text Raw text.
	 *
	 * @return string
	 */
	private function escape_text_node( string $text ): string {
		$text = $this->normalize_testimonials_text_value( $text );

		return htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8' );
	}

	/**
	 * Normalize testimonials text to match the block's canonical save() output.
	 *
	 * Key rule: any straight quote (") must become the literal token u0022
	 * so Gutenberg does not "recover" the block by changing HTML content.
	 *
	 * @param string $text Raw input.
	 *
	 * @return string Normalized plain text (not HTML-escaped).
	 */
	private function normalize_testimonials_text_value( string $text ): string {
		$text = wp_check_invalid_utf8( $text, true );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		// Decode entities first (e.g. &quot;) to avoid producing real quotes later in the pipeline.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Ensure plain text only.
		$text = wp_strip_all_tags( $text, true );

		// Convert any escaped unicode quote sequences into the literal token expected by save().
		// Handles \u0022 and \\u0022 etc.
		$text = preg_replace( '/\\\\+u0022/i', 'u0022', $text );

		// Finally, force any remaining straight quotes to the literal token.
		$text = str_replace( '"', 'u0022', $text );

		return $text;
	}

	/**
	 * Keep only attrs that exist in the registered block schema, and drop defaults
	 * so the output matches Gutenberg serialization.
	 *
	 * @param string $block_name Block name.
	 * @param array $attrs Raw attrs.
	 *
	 * @return array
	 */
	private function prune_block_attrs_against_schema( string $block_name, array $attrs ): array {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return $attrs;
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( ! is_object( $block_type ) || empty( $block_type->attributes ) || ! is_array( $block_type->attributes ) ) {
			return $attrs;
		}

		$schema = $block_type->attributes;

		// Important: preserve schema order to match Gutenberg output order.
		$pruned = array();

		foreach ( $schema as $key => $definition ) {
			if ( ! array_key_exists( $key, $attrs ) ) {
				continue;
			}

			$value = $attrs[ $key ];

			if ( is_array( $definition ) && array_key_exists( 'default', $definition ) ) {
				$default_value = $definition['default'];

				if ( $this->values_equal( $value, $default_value ) ) {
					continue;
				}
			}

			$pruned[ $key ] = $value;
		}

		return $pruned;
	}

	/**
	 * Compare values in a Gutenberg-friendly way.
	 *
	 * @param mixed $a First.
	 * @param mixed $b Second.
	 *
	 * @return bool
	 */
	private function values_equal( $a, $b ): bool {
		if ( is_array( $a ) && is_array( $b ) ) {
			if ( count( $a ) !== count( $b ) ) {
				return false;
			}

			foreach ( $b as $k => $v ) {
				if ( ! array_key_exists( $k, $a ) ) {
					return false;
				}
				if ( ! $this->values_equal( $a[ $k ], $v ) ) {
					return false;
				}
			}

			foreach ( $a as $k => $v ) {
				if ( ! array_key_exists( $k, $b ) ) {
					return false;
				}
			}

			return true;
		}

		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return abs( (float) $a - (float) $b ) < 0.000001;
		}

		return $a === $b;
	}


}
