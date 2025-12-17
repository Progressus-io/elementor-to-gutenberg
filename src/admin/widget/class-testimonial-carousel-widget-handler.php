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
				$slides[] = array(
					'content'  => $slide['content'] ?? '',
					'name'     => $slide['name'] ?? '',
					'title'    => $slide['title'] ?? '',
					'imageUrl' => $slide['image']['url'] ?? '',
					'imageId'  => $slide['image']['id'] ?? 0,
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

		// Content typography.
		$content_typography = array(
			'fontSize'       => (int) ( $settings['content_typography_font_size']['size'] ?? 16 ),
			'fontWeight'     => $settings['content_typography_font_weight'] ?? 'normal',
			'fontStyle'      => $settings['content_typography_font_style'] ?? 'normal',
			'textDecoration' => $settings['content_typography_text_decoration'] ?? 'none',
			'lineHeight'     => (float) ( $settings['content_typography_line_height']['size'] ?? 1.5 ),
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
		$attributes_json = wp_json_encode( $attributes );

		// Generate the rendered HTML content.
		$rendered_html = $this->generate_testimonials_html( $attributes );

		// Generate the complete testimonials block markup.
		$block_content  = '<!-- wp:progressus/testimonials ' . $attributes_json . ' -->';
		$block_content .= $rendered_html;
		$block_content .= '<!-- /wp:progressus/testimonials -->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content . "\n";
	}

	/**
	 * Generate the rendered HTML content for the testimonials block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML content.
	 */
	private function generate_testimonials_html( array $attributes ): string {
		$slides                     = $attributes['slides'] ?? array();
		$layout                     = $attributes['layout'] ?? 'image_above';
		$alignment                  = $attributes['alignment'] ?? 'left';
		$slides_per_view            = $attributes['slidesPerView'] ?? 1;
		$space_between              = $attributes['spaceBetween'] ?? 20;
		$width                      = $attributes['width'] ?? 100;
		$slide_background_color     = $attributes['slideBackgroundColor'] ?? '';
		$slide_border_size          = $attributes['slideBorderSize'] ?? array( 'top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1 );
		$slide_border_radius        = $attributes['slideBorderRadius'] ?? 0;
		$slide_border_color         = $attributes['slideBorderColor'] ?? '';
		$slide_padding              = $attributes['slidePadding'] ?? array( 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20 );
		$content_gap                = $attributes['contentGap'] ?? 20;
		$content_color              = $attributes['contentColor'] ?? '';
		$content_typography         = $attributes['contentTypography'] ?? array();
		$name_color                 = $attributes['nameColor'] ?? '';
		$title_color                = $attributes['titleColor'] ?? '';
		$image_size                 = $attributes['imageSize'] ?? 80;
		$image_gap                  = $attributes['imageGap'] ?? 20;
		$image_border_radius        = $attributes['imageBorderRadius'] ?? 50;
		$arrows_size                = $attributes['arrowsSize'] ?? 20;
		$arrows_color               = $attributes['arrowsColor'] ?? '';
		$pagination_gap             = $attributes['paginationGap'] ?? 10;
		$pagination_size            = $attributes['paginationSize'] ?? 10;
		$pagination_color_inactive  = $attributes['paginationColorInactive'] ?? '';
		$_margin                    = $attributes['_margin'] ?? array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
		$_padding                   = $attributes['_padding'] ?? array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
		$custom_id                  = $attributes['customId'] ?? '';
		$custom_class               = $attributes['customClass'] ?? '';

		$container_style = sprintf(
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

		$html  = '<div class="wp-block-progressus-testimonials ' . esc_attr( $custom_class ) . '" id="' . esc_attr( $custom_id ) . '" style="' . esc_attr( $container_style ) . '">';
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

            $content_style = sprintf(
                'color:%s;font-size:%dpx;font-weight:%s;font-style:%s;text-decoration:%s;line-height:%dpx;letter-spacing:%spx;word-spacing:%spx;font-family:%s;margin-bottom:%dpx',
                $content_color,
                $content_typography['fontSize'] ?? 16,
                $content_typography['fontWeight'] ?? 'normal',
                $content_typography['fontStyle'] ?? 'normal',
                $content_typography['textDecoration'] ?? 'none',
                $content_typography['lineHeight'] ?? 1.5,
                $content_typography['letterSpacing'] ?? 0,
                $content_typography['wordSpacing'] ?? 0,
                $content_typography['fontFamily'] ?? '',
                $content_gap
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
			$content_html    = '<div class="testimonial-content" style="' . esc_attr( $content_style ) . '">' . esc_html( $slide['content'] ) . '</div>';
			$name_title_html = '<div class="testimonial-name" style="color:' . esc_attr( $name_color ) . ';font-weight:bold;margin-bottom:5px">' . esc_html( $slide['name'] ) . '</div><div class="testimonial-title" style="color:' . esc_attr( $title_color ) . ';font-size:14px">' . esc_html( $slide['title'] ) . '</div>';

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
}
