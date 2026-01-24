<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Handles conversion of Elementor Call to Action widgets to Gutenberg blocks.
 */
class Call_To_Action_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Converts an Elementor Call to Action widget to a Gutenberg block.
	 *
	 * @param array $widget_data The widget data from Elementor.
	 *
	 * @return string The Gutenberg block markup.
	 */
	public function handle( array $widget_data ): string {
		$settings = isset( $widget_data['settings'] ) && is_array( $widget_data['settings'] ) ? $widget_data['settings'] : array();

		// Parse title typography.
		$title_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'title_typography_' ) ) {
				$new_key                               = substr( (string) $key, strlen( 'title_' ) );
				$title_typography_settings[ $new_key ] = $value;
			}
		}
		$title_typography      = Style_Parser::parse_typography( $title_typography_settings );
		$title_typography_attr = isset( $title_typography['attributes'] ) ? $title_typography['attributes'] : array();

		// Parse description typography.
		$description_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'description_typography_' ) ) {
				$new_key                                     = substr( (string) $key, strlen( 'description_' ) );
				$description_typography_settings[ $new_key ] = $value;
			}
		}
		$description_typography      = Style_Parser::parse_typography( $description_typography_settings );
		$description_typography_attr = isset( $description_typography['attributes'] ) ? $description_typography['attributes'] : array();

		// Parse button typography.
		$button_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'button_typography_' ) ) {
				$new_key                                = substr( (string) $key, strlen( 'button_' ) );
				$button_typography_settings[ $new_key ] = $value;
			}
		}
		$button_typography      = Style_Parser::parse_typography( $button_typography_settings );
		$button_typography_attr = isset( $button_typography['attributes'] ) ? $button_typography['attributes'] : array();

		// Parse ribbon typography.
		$ribbon_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'ribbon_typography_' ) ) {
				$new_key                                = substr( (string) $key, strlen( 'ribbon_' ) );
				$ribbon_typography_settings[ $new_key ] = $value;
			}
		}
		$ribbon_typography      = Style_Parser::parse_typography( $ribbon_typography_settings );
		$ribbon_typography_attr = isset( $ribbon_typography['attributes'] ) ? $ribbon_typography['attributes'] : array();

		// Extract basic data.
		$layout           = isset( $settings['layout'] ) ? (string) $settings['layout'] : 'left';
		$bg_image_data    = $this->resolve_bg_image_data( $settings );
		$bg_image_url     = isset( $bg_image_data['url'] ) ? (string) $bg_image_data['url'] : '';
		$bg_image_id      = isset( $bg_image_data['id'] ) ? (int) $bg_image_data['id'] : 0;
		$image_min_height = $this->sanitize_slider_value( $settings['image_min_height'] ?? null, 425 );

		// Extract title / description / button text safely.
		$title = $this->get_first_text_setting( $settings, array( 'title', 'title_text', 'cta_title', 'heading' ) );
		$title = $this->normalize_text_value( $title );

		$description = $this->get_first_text_setting( $settings, array(
			'description',
			'desc',
			'subtitle',
			'content',
			'text'
		) );
		$description = $this->normalize_text_value( $description );

		$button_text = $this->get_first_text_setting( $settings, array(
			'button',
			'button_text',
			'cta_text',
			'link_text'
		) );
		$button_text = $this->normalize_text_value( $button_text );

// Ribbon.
		$ribbon_title = isset( $settings['ribbon_title'] ) ? $this->normalize_text_value( (string) $settings['ribbon_title'] ) : '';


		$button_url      = isset( $settings['link']['url'] ) ? (string) $settings['link']['url'] : '';
		$button_target   = ! empty( $settings['link']['is_external'] );
		$button_nofollow = ! empty( $settings['link']['nofollow'] );

		$alignment = isset( $settings['text_align'] ) ? (string) $settings['text_align'] : ( isset( $settings['alignment'] ) ? (string) $settings['alignment'] : 'left' );

		// Colors.
		$content_bg_color  = isset( $settings['content_bg_color'] ) ? (string) $settings['content_bg_color'] : '';
		$title_color       = isset( $settings['title_color'] ) ? (string) $settings['title_color'] : ( isset( $title_typography_attr['color'] ) ? (string) $title_typography_attr['color'] : '#000000' );
		$description_color = isset( $settings['description_color'] ) ? (string) $settings['description_color'] : ( isset( $description_typography_attr['color'] ) ? (string) $description_typography_attr['color'] : '#666666' );
		$button_bg_color   = isset( $settings['button_background_color'] ) ? (string) $settings['button_background_color'] : '#007cba';
		$button_text_color = isset( $settings['button_text_color'] ) ? (string) $settings['button_text_color'] : '#ffffff';

		// Spacing.
		$description_spacing = $this->sanitize_slider_value( $settings['description_spacing'] ?? null, 0 );

		$content_padding = $this->extract_padding(
			isset( $settings['padding'] ) && is_array( $settings['padding'] ) ? $settings['padding'] : array(),
			array( 'top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50 )
		);

		$content_margin = $this->extract_padding(
			isset( $settings['_margin'] ) && is_array( $settings['_margin'] ) ? $settings['_margin'] : array(),
			array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 )
		);

		$button_padding = $this->extract_padding(
			isset( $settings['button_padding'] ) && is_array( $settings['button_padding'] ) ? $settings['button_padding'] : array(),
			array( 'top' => 12, 'right' => 24, 'bottom' => 12, 'left' => 24 )
		);

		// Ribbon.
		$ribbon_title               = isset( $settings['ribbon_title'] ) ? $this->normalize_text_value( (string) $settings['ribbon_title'] ) : '';
		$ribbon_bg_color            = isset( $settings['ribbon_bg_color'] ) ? (string) $settings['ribbon_bg_color'] : '#007cba';
		$ribbon_text_color          = isset( $settings['ribbon_text_color'] ) ? (string) $settings['ribbon_text_color'] : '#ffffff';
		$ribbon_horizontal_position = isset( $settings['ribbon_horizontal_position'] ) ? (string) $settings['ribbon_horizontal_position'] : 'left';
		$ribbon_distance            = $this->sanitize_slider_value( $settings['ribbon_distance'] ?? null, 42 );

		// Typography attributes.
		$title_size            = isset( $title_typography_attr['fontSize'] ) ? (int) $title_typography_attr['fontSize'] : 28;
		$title_font_family     = isset( $title_typography_attr['fontFamily'] ) ? (string) $title_typography_attr['fontFamily'] : '';
		$title_font_weight     = isset( $title_typography_attr['fontWeight'] ) ? (string) $title_typography_attr['fontWeight'] : '';
		$title_text_transform  = isset( $title_typography_attr['textTransform'] ) ? (string) $title_typography_attr['textTransform'] : '';
		$title_font_style      = isset( $title_typography_attr['fontStyle'] ) ? (string) $title_typography_attr['fontStyle'] : '';
		$title_text_decoration = isset( $title_typography_attr['textDecoration'] ) ? (string) $title_typography_attr['textDecoration'] : '';
		$title_line_height     = isset( $title_typography_attr['lineHeight'] ) ? (string) $title_typography_attr['lineHeight'] : '';
		$title_letter_spacing  = isset( $title_typography_attr['letterSpacing'] ) ? (string) $title_typography_attr['letterSpacing'] : '';
		$title_word_spacing    = isset( $title_typography_attr['wordSpacing'] ) ? (string) $title_typography_attr['wordSpacing'] : '';

		$description_size            = isset( $description_typography_attr['fontSize'] ) ? (int) $description_typography_attr['fontSize'] : 16;
		$description_font_family     = isset( $description_typography_attr['fontFamily'] ) ? (string) $description_typography_attr['fontFamily'] : '';
		$description_font_weight     = isset( $description_typography_attr['fontWeight'] ) ? (string) $description_typography_attr['fontWeight'] : '';
		$description_text_transform  = isset( $description_typography_attr['textTransform'] ) ? (string) $description_typography_attr['textTransform'] : '';
		$description_font_style      = isset( $description_typography_attr['fontStyle'] ) ? (string) $description_typography_attr['fontStyle'] : '';
		$description_text_decoration = isset( $description_typography_attr['textDecoration'] ) ? (string) $description_typography_attr['textDecoration'] : '';
		$description_line_height     = isset( $description_typography_attr['lineHeight'] ) ? (string) $description_typography_attr['lineHeight'] : '';
		$description_letter_spacing  = isset( $description_typography_attr['letterSpacing'] ) ? (string) $description_typography_attr['letterSpacing'] : '';
		$description_word_spacing    = isset( $description_typography_attr['wordSpacing'] ) ? (string) $description_typography_attr['wordSpacing'] : '';

		$button_size            = isset( $button_typography_attr['fontSize'] ) ? (int) $button_typography_attr['fontSize'] : 16;
		$button_font_family     = isset( $button_typography_attr['fontFamily'] ) ? (string) $button_typography_attr['fontFamily'] : '';
		$button_font_weight     = isset( $button_typography_attr['fontWeight'] ) ? (string) $button_typography_attr['fontWeight'] : '';
		$button_text_transform  = isset( $button_typography_attr['textTransform'] ) ? (string) $button_typography_attr['textTransform'] : '';
		$button_font_style      = isset( $button_typography_attr['fontStyle'] ) ? (string) $button_typography_attr['fontStyle'] : '';
		$button_text_decoration = isset( $button_typography_attr['textDecoration'] ) ? (string) $button_typography_attr['textDecoration'] : '';
		$button_line_height     = isset( $button_typography_attr['lineHeight'] ) ? (string) $button_typography_attr['lineHeight'] : '';
		$button_letter_spacing  = isset( $button_typography_attr['letterSpacing'] ) ? (string) $button_typography_attr['letterSpacing'] : '';
		$button_word_spacing    = isset( $button_typography_attr['wordSpacing'] ) ? (string) $button_typography_attr['wordSpacing'] : '';

		$ribbon_size            = isset( $ribbon_typography_attr['fontSize'] ) ? (int) $ribbon_typography_attr['fontSize'] : 16;
		$ribbon_font_family     = isset( $ribbon_typography_attr['fontFamily'] ) ? (string) $ribbon_typography_attr['fontFamily'] : '';
		$ribbon_font_weight     = isset( $ribbon_typography_attr['fontWeight'] ) ? (string) $ribbon_typography_attr['fontWeight'] : '';
		$ribbon_text_transform  = isset( $ribbon_typography_attr['textTransform'] ) ? (string) $ribbon_typography_attr['textTransform'] : '';
		$ribbon_font_style      = isset( $ribbon_typography_attr['fontStyle'] ) ? (string) $ribbon_typography_attr['fontStyle'] : '';
		$ribbon_text_decoration = isset( $ribbon_typography_attr['textDecoration'] ) ? (string) $ribbon_typography_attr['textDecoration'] : '';
		$ribbon_line_height     = isset( $ribbon_typography_attr['lineHeight'] ) ? (string) $ribbon_typography_attr['lineHeight'] : '';
		$ribbon_letter_spacing  = isset( $ribbon_typography_attr['letterSpacing'] ) ? (string) $ribbon_typography_attr['letterSpacing'] : '';
		$ribbon_word_spacing    = isset( $ribbon_typography_attr['wordSpacing'] ) ? (string) $ribbon_typography_attr['wordSpacing'] : '';

		// Sanitize description for both HTML and attributes (remove newlines).
		$sanitized_description             = wp_kses_post( $description );
		$sanitized_description_no_newlines = str_replace( array( "\r\n", "\r", "\n" ), '', $sanitized_description );
		$sanitized_description_no_newlines = $this->normalize_text_value( (string) $sanitized_description_no_newlines );

		// Build HTML segments.
		$segments = array();

		if ( '' !== trim( $title ) ) {
			$title_style_parts = array(
				'font-size:' . (int) $title_size . 'px',
				'color:' . esc_attr( $title_color ),
				'margin-bottom:16px',
			);

			if ( '' !== trim( $title_font_family ) ) {
				$title_style_parts[] = 'font-family:' . esc_attr( $title_font_family );
			}
			if ( '' !== trim( $title_font_weight ) ) {
				$title_style_parts[] = 'font-weight:' . esc_attr( $title_font_weight );
			}
			if ( '' !== trim( $title_text_transform ) ) {
				$title_style_parts[] = 'text-transform:' . esc_attr( $title_text_transform );
			}
			if ( '' !== trim( $title_font_style ) ) {
				$title_style_parts[] = 'font-style:' . esc_attr( $title_font_style );
			}
			if ( '' !== trim( $title_text_decoration ) ) {
				$title_style_parts[] = 'text-decoration:' . esc_attr( $title_text_decoration );
			}
			if ( '' !== trim( $title_line_height ) ) {
				$title_style_parts[] = 'line-height:' . esc_attr( $title_line_height );
			}
			if ( '' !== trim( $title_letter_spacing ) ) {
				$title_style_parts[] = 'letter-spacing:' . esc_attr( $title_letter_spacing );
			}
			if ( '' !== trim( $title_word_spacing ) ) {
				$title_style_parts[] = 'word-spacing:' . esc_attr( $title_word_spacing );
			}

			$segments[] = '<h2 class="call-to-action-title" style="' . implode( ';', $title_style_parts ) . '">' . esc_html( $title ) . '</h2>';
		}

		if ( '' !== trim( $sanitized_description_no_newlines ) ) {
			$description_style_parts = array(
				'font-size:' . (int) $description_size . 'px',
				'color:' . esc_attr( $description_color ),
				'margin-bottom:' . (int) $description_spacing . 'px',
			);

			if ( '' !== trim( $description_font_family ) ) {
				$description_style_parts[] = 'font-family:' . esc_attr( $description_font_family );
			}
			if ( '' !== trim( $description_font_weight ) ) {
				$description_style_parts[] = 'font-weight:' . esc_attr( $description_font_weight );
			}
			if ( '' !== trim( $description_text_transform ) ) {
				$description_style_parts[] = 'text-transform:' . esc_attr( $description_text_transform );
			}
			if ( '' !== trim( $description_font_style ) ) {
				$description_style_parts[] = 'font-style:' . esc_attr( $description_font_style );
			}
			if ( '' !== trim( $description_text_decoration ) ) {
				$description_style_parts[] = 'text-decoration:' . esc_attr( $description_text_decoration );
			}
			if ( '' !== trim( $description_line_height ) ) {
				$description_style_parts[] = 'line-height:' . esc_attr( $description_line_height );
			}
			if ( '' !== trim( $description_letter_spacing ) ) {
				$description_style_parts[] = 'letter-spacing:' . esc_attr( $description_letter_spacing );
			}
			if ( '' !== trim( $description_word_spacing ) ) {
				$description_style_parts[] = 'word-spacing:' . esc_attr( $description_word_spacing );
			}

			$segments[] = '<p class="call-to-action-description" style="' . implode( ';', $description_style_parts ) . '">' . $sanitized_description_no_newlines . '</p>';
		}

		if ( '' !== trim( $button_text ) ) {
			$button_style_parts = array(
				'display:inline-block',
				'font-size:' . (int) $button_size . 'px',
				'color:' . esc_attr( $button_text_color ),
				'background-color:' . esc_attr( $button_bg_color ),
				'padding:' . (int) $button_padding['top'] . 'px ' . (int) $button_padding['right'] . 'px ' . (int) $button_padding['bottom'] . 'px ' . (int) $button_padding['left'] . 'px',
				'border-radius:4px',
				'text-decoration:none',
				'cursor:pointer',
				'border:none',
			);

			if ( '' !== trim( $button_font_family ) ) {
				$button_style_parts[] = 'font-family:' . esc_attr( $button_font_family );
			}
			if ( '' !== trim( $button_font_weight ) ) {
				$button_style_parts[] = 'font-weight:' . esc_attr( $button_font_weight );
			}
			if ( '' !== trim( $button_text_transform ) ) {
				$button_style_parts[] = 'text-transform:' . esc_attr( $button_text_transform );
			}
			if ( '' !== trim( $button_font_style ) ) {
				$button_style_parts[] = 'font-style:' . esc_attr( $button_font_style );
			}
			if ( '' !== trim( $button_text_decoration ) && 'none' !== $button_text_decoration ) {
				$button_style_parts[] = 'text-decoration:' . esc_attr( $button_text_decoration );
			}
			if ( '' !== trim( $button_line_height ) ) {
				$button_style_parts[] = 'line-height:' . esc_attr( $button_line_height );
			}
			if ( '' !== trim( $button_letter_spacing ) ) {
				$button_style_parts[] = 'letter-spacing:' . esc_attr( $button_letter_spacing );
			}
			if ( '' !== trim( $button_word_spacing ) ) {
				$button_style_parts[] = 'word-spacing:' . esc_attr( $button_word_spacing );
			}

			if ( '' !== trim( $button_url ) ) {
				$target_attr = $button_target ? ' target="_blank"' : '';
				$rel_attr    = $button_nofollow ? ' rel="nofollow"' : '';
				$segments[]  = '<a href="' . esc_url( $button_url ) . '" class="call-to-action-button" style="' . implode( ';', $button_style_parts ) . '"' . $target_attr . $rel_attr . '><span>' . esc_html( $button_text ) . '</span></a>';
			} else {
				$segments[] = '<span class="call-to-action-button" style="' . implode( ';', $button_style_parts ) . '">' . esc_html( $button_text ) . '</span>';
			}
		}

		// Ribbon HTML.
		$ribbon_html = '';
		if ( '' !== trim( $ribbon_title ) ) {
			$ribbon_style_parts = array(
				'position:absolute',
				'top:' . (int) $ribbon_distance . 'px',
				( 'right' === $ribbon_horizontal_position ? 'right:' . (int) $ribbon_distance . 'px' : 'left:' . (int) $ribbon_distance . 'px' ),
				'background-color:' . esc_attr( $ribbon_bg_color ),
				'color:' . esc_attr( $ribbon_text_color ),
				'font-size:' . (int) $ribbon_size . 'px',
				'padding:8px 16px',
				'border-radius:4px',
				'z-index:10',
				'transform:' . ( 'right' === $ribbon_horizontal_position ? 'rotate(15deg)' : 'rotate(-15deg)' ),
			);

			if ( '' !== trim( $ribbon_font_family ) ) {
				$ribbon_style_parts[] = 'font-family:' . esc_attr( $ribbon_font_family );
			}
			if ( '' !== trim( $ribbon_font_weight ) ) {
				$ribbon_style_parts[] = 'font-weight:' . esc_attr( $ribbon_font_weight );
			}
			if ( '' !== trim( $ribbon_text_transform ) ) {
				$ribbon_style_parts[] = 'text-transform:' . esc_attr( $ribbon_text_transform );
			}
			if ( '' !== trim( $ribbon_font_style ) ) {
				$ribbon_style_parts[] = 'font-style:' . esc_attr( $ribbon_font_style );
			}
			if ( '' !== trim( $ribbon_text_decoration ) ) {
				$ribbon_style_parts[] = 'text-decoration:' . esc_attr( $ribbon_text_decoration );
			}
			if ( '' !== trim( $ribbon_line_height ) ) {
				$ribbon_style_parts[] = 'line-height:' . esc_attr( $ribbon_line_height );
			}
			if ( '' !== trim( $ribbon_letter_spacing ) ) {
				$ribbon_style_parts[] = 'letter-spacing:' . esc_attr( $ribbon_letter_spacing );
			}
			if ( '' !== trim( $ribbon_word_spacing ) ) {
				$ribbon_style_parts[] = 'word-spacing:' . esc_attr( $ribbon_word_spacing );
			}

			$ribbon_html = '<div class="call-to-action-ribbon" style="' . implode( ';', $ribbon_style_parts ) . '">' . esc_html( $ribbon_title ) . '</div>';
		}

		// Wrapper classes and styles.
		$wrapper_classes = array(
			'wp-block-call-to-action',
			$alignment ? 'has-text-align-' . $alignment : '',
			'call-to-action-layout-' . $layout,
		);

		$wrapper_attrs   = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		$wrapper_attrs[] = 'style="text-align:' . esc_attr( $alignment ) . '"';

		// Container styles.
		$container_style_parts = array(
			'min-height:' . (int) $image_min_height . 'px',
			'display:flex',
			'position:relative',
		);

		if ( in_array( $layout, array( 'left', 'right' ), true ) ) {
			$container_style_parts[] = 'align-items:stretch';
		} else {
			$container_style_parts[] = 'align-items:flex-start';
		}

		if ( 'center' === $layout ) {
			$container_style_parts[] = 'justify-content:center';
		} elseif ( 'right' === $layout ) {
			$container_style_parts[] = 'justify-content:flex-end';
		} else {
			$container_style_parts[] = 'justify-content:flex-start';
		}

		switch ( $layout ) {
			case 'above':
				$container_style_parts[] = 'flex-direction:column';
				break;
			case 'below':
				$container_style_parts[] = 'flex-direction:column-reverse';
				break;
			case 'right':
				$container_style_parts[] = 'flex-direction:row-reverse';
				break;
			case 'left':
				$container_style_parts[] = 'flex-direction:row';
				break;
		}

		if ( $bg_image_url && 'center' === $layout ) {
			$container_style_parts[] = 'background-image:url(' . esc_url( $bg_image_url ) . ')';
			$container_style_parts[] = 'background-size:cover';
			$container_style_parts[] = 'background-position:center';
		}

		$image_html = '';
		if ( $bg_image_url && in_array( $layout, array( 'above', 'below', 'left', 'right' ), true ) ) {
			$image_style_parts = array(
				'background-image:url(' . esc_url( $bg_image_url ) . ')',
				'background-size:cover',
				'background-position:center',
				'min-height:' . (int) $image_min_height . 'px',
			);

			if ( in_array( $layout, array( 'left', 'right' ), true ) ) {
				$image_style_parts[] = 'flex-basis:50%';
			}

			$aria_label = '';
			$path       = parse_url( $bg_image_url, PHP_URL_PATH );
			if ( $path ) {
				$aria_label = basename( $path );
			}

			$image_html = '<div class="call-to-action-image" role="img" aria-label="' . esc_attr( $aria_label ) . '" style="' . implode( ';', $image_style_parts ) . '"></div>';
			$image_html .= '<div class="call-to-action-image-overlay"></div>';
		}

		$content_style_parts   = array();
		$content_style_parts[] = 'background-color:' . esc_attr( $content_bg_color ? $content_bg_color : 'rgba(255,255,255,0.9)' );
		$content_style_parts[] = 'padding:' . (int) $content_padding['top'] . 'px ' . (int) $content_padding['right'] . 'px ' . (int) $content_padding['bottom'] . 'px ' . (int) $content_padding['left'] . 'px';
		$content_style_parts[] = 'margin:' . (int) $content_margin['top'] . 'px ' . (int) $content_margin['right'] . 'px ' . (int) $content_margin['bottom'] . 'px ' . (int) $content_margin['left'] . 'px';

		if ( 'center' === $layout ) {
			$content_style_parts[] = 'max-width:600px';
		} elseif ( in_array( $layout, array( 'above', 'below' ), true ) ) {
			$content_style_parts[] = 'max-width:100%';
		} else {
			$content_style_parts[] = 'max-width:50%';
			$content_style_parts[] = 'flex-basis:50%';
			$content_style_parts[] = 'display:flex';
			$content_style_parts[] = 'flex-direction:column';
			$content_style_parts[] = 'justify-content:flex-start';
		}

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' .
		           '<div class="call-to-action-container" style="' . implode( ';', $container_style_parts ) . '">' .
		           $ribbon_html .
		           $image_html .
		           '<div class="call-to-action-content" style="' . implode( ';', $content_style_parts ) . '">' .
		           implode( '', $segments ) .
		           '</div></div></div>';

		// Build full attributes then strip defaults (this is what Gutenberg does on save).
		$block_attributes = array(
			'layout'                    => $layout,
			'bgImageUrl'                => $bg_image_url,
			'bgImageId'                 => $bg_image_id,
			'title'                     => $title,
			'description'               => $sanitized_description_no_newlines,
			'buttonText'                => $button_text,
			'buttonUrl'                 => $button_url,
			'buttonTarget'              => $button_target,
			'buttonNofollow'            => $button_nofollow,
			'alignment'                 => $alignment,
			'imageMinHeight'            => $image_min_height,
			'contentBgColor'            => $content_bg_color,
			'titleColor'                => $title_color,
			'titleSize'                 => $title_size,
			'titleFontFamily'           => $title_font_family,
			'titleFontWeight'           => $title_font_weight,
			'titleTextTransform'        => $title_text_transform,
			'titleFontStyle'            => $title_font_style,
			'titleTextDecoration'       => $title_text_decoration,
			'titleLineHeight'           => $title_line_height,
			'titleLetterSpacing'        => $title_letter_spacing,
			'titleWordSpacing'          => $title_word_spacing,
			'descriptionColor'          => $description_color,
			'descriptionSize'           => $description_size,
			'descriptionFontFamily'     => $description_font_family,
			'descriptionFontWeight'     => $description_font_weight,
			'descriptionTextTransform'  => $description_text_transform,
			'descriptionFontStyle'      => $description_font_style,
			'descriptionTextDecoration' => $description_text_decoration,
			'descriptionLineHeight'     => $description_line_height,
			'descriptionLetterSpacing'  => $description_letter_spacing,
			'descriptionWordSpacing'    => $description_word_spacing,
			'descriptionSpacing'        => $description_spacing,
			'buttonBgColor'             => $button_bg_color,
			'buttonTextColor'           => $button_text_color,
			'buttonSize'                => $button_size,
			'buttonFontFamily'          => $button_font_family,
			'buttonFontWeight'          => $button_font_weight,
			'buttonTextTransform'       => $button_text_transform,
			'buttonFontStyle'           => $button_font_style,
			'buttonTextDecoration'      => $button_text_decoration,
			'buttonLineHeight'          => $button_line_height,
			'buttonLetterSpacing'       => $button_letter_spacing,
			'buttonWordSpacing'         => $button_word_spacing,
			'buttonBorderRadius'        => 4,
			'buttonPadding'             => $button_padding,
			'contentPadding'            => $content_padding,
			'contentMargin'             => $content_margin,
			'ribbonTitle'               => $ribbon_title,
			'ribbonBgColor'             => $ribbon_bg_color,
			'ribbonTextColor'           => $ribbon_text_color,
			'ribbonSize'                => $ribbon_size,
			'ribbonFontFamily'          => $ribbon_font_family,
			'ribbonFontWeight'          => $ribbon_font_weight,
			'ribbonTextTransform'       => $ribbon_text_transform,
			'ribbonFontStyle'           => $ribbon_font_style,
			'ribbonTextDecoration'      => $ribbon_text_decoration,
			'ribbonLineHeight'          => $ribbon_line_height,
			'ribbonLetterSpacing'       => $ribbon_letter_spacing,
			'ribbonWordSpacing'         => $ribbon_word_spacing,
			'ribbonHorizontalPosition'  => $ribbon_horizontal_position,
			'ribbonDistance'            => $ribbon_distance,
		);

		$block_attributes = $this->filter_default_block_attributes( $block_attributes );

		return Block_Builder::build( 'gutenberg/call-to-action', $block_attributes, $content );
	}

	/**
	 * Resolve the background image data from Elementor settings.
	 *
	 * @param array $settings The widget settings.
	 *
	 * @return array Image data with url and id.
	 */
	private function resolve_bg_image_data( array $settings ): array {
		$image_data = array(
			'url' => '',
			'id'  => 0,
		);

		if ( isset( $settings['bg_image'] ) && is_array( $settings['bg_image'] ) ) {
			$image_data['url'] = isset( $settings['bg_image']['url'] ) ? (string) $settings['bg_image']['url'] : '';
			$image_data['id']  = isset( $settings['bg_image']['id'] ) ? (int) $settings['bg_image']['id'] : 0;
		}

		return $image_data;
	}

	/**
	 * Sanitize slider/range value from Elementor data.
	 *
	 * @param mixed $value Raw value from Elementor.
	 * @param int $default Default value if parsing fails.
	 *
	 * @return int Sanitized integer value.
	 */
	private function sanitize_slider_value( $value, int $default ): int {
		if ( is_array( $value ) && isset( $value['size'] ) ) {
			return (int) $value['size'];
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		return $default;
	}

	/**
	 * Extract padding values from Elementor padding object.
	 *
	 * @param array $padding Padding array from Elementor.
	 * @param array $defaults Default padding values.
	 *
	 * @return array Normalized padding with top, right, bottom, left keys.
	 */
	private function extract_padding( array $padding, array $defaults = array() ): array {
		$defaults = wp_parse_args(
			$defaults,
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			)
		);

		return array(
			'top'    => isset( $padding['top'] ) ? (int) $padding['top'] : (int) $defaults['top'],
			'right'  => isset( $padding['right'] ) ? (int) $padding['right'] : (int) $defaults['right'],
			'bottom' => isset( $padding['bottom'] ) ? (int) $padding['bottom'] : (int) $defaults['bottom'],
			'left'   => isset( $padding['left'] ) ? (int) $padding['left'] : (int) $defaults['left'],
		);
	}

	/**
	 * Get first non-empty textual setting.
	 *
	 * @param array $settings Settings array.
	 * @param array $keys Keys to check.
	 *
	 * @return string
	 */
	private function get_first_text_setting( array $settings, array $keys ): string {
		foreach ( $keys as $k ) {
			if ( isset( $settings[ $k ] ) ) {
				$val = $settings[ $k ];
				if ( is_string( $val ) && '' !== trim( $val ) ) {
					return (string) $val;
				}
				if ( is_array( $val ) ) {
					$raw = isset( $val['raw'] ) ? (string) $val['raw'] : ( isset( $val['text'] ) ? (string) $val['text'] : '' );
					if ( '' !== trim( $raw ) ) {
						return $raw;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Normalize Elementor text values (fix u201c/u201d and similar sequences).
	 *
	 * @param string $text Raw text.
	 *
	 * @return string
	 */
	private function normalize_text_value( string $text ): string {
		$text = (string) $text;

		if ( '' === $text ) {
			return $text;
		}

		// Unslash first (Elementor/WP can store slashed strings).
		if ( function_exists( 'wp_unslash' ) ) {
			$text = wp_unslash( $text );
		}

		// Decode HTML entities first (e.g. &#8220; or &#x201c;).
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		$text = preg_replace_callback(
			'/%u([0-9a-fA-F]{4,6})/i',
			static function ( array $m ): string {
				$hex = isset( $m[1] ) ? (string) $m[1] : '';
				if ( '' === $hex ) {
					return (string) $m[0];
				}

				return html_entity_decode( '&#x' . $hex . ';', ENT_QUOTES, 'UTF-8' );
			},
			$text
		);

		$text = preg_replace_callback(
			'/\\\\*[uU]([0-9a-fA-F]{4,6})/',
			static function ( array $m ): string {
				$hex = isset( $m[1] ) ? (string) $m[1] : '';
				if ( '' === $hex ) {
					return (string) $m[0];
				}

				return html_entity_decode( '&#x' . $hex . ';', ENT_QUOTES, 'UTF-8' );
			},
			$text
		);

		return $text;
	}

	/**
	 * Remove attributes that match the block.json defaults (Gutenberg does this on save).
	 *
	 * @param array $attrs Raw attrs.
	 *
	 * @return array Filtered attrs.
	 */
	private function filter_default_block_attributes( array $attrs ): array {
		$defaults = array(
			'layout'                    => 'left',
			'bgImageUrl'                => '',
			'bgImageId'                 => 0,
			'bgImageSize'               => 'full',
			'title'                     => '',
			'description'               => '',
			'buttonText'                => '',
			'buttonUrl'                 => '',
			'buttonTarget'              => false,
			'buttonNofollow'            => false,
			'alignment'                 => 'left',
			'imageMinHeight'            => 425,
			'contentBgColor'            => '',
			'titleColor'                => '#000000',
			'titleSize'                 => 28,
			'titleFontFamily'           => '',
			'titleFontWeight'           => '',
			'titleTextTransform'        => '',
			'titleFontStyle'            => '',
			'titleTextDecoration'       => '',
			'titleLineHeight'           => '',
			'titleLetterSpacing'        => '',
			'titleWordSpacing'          => '',
			'descriptionColor'          => '#666666',
			'descriptionSize'           => 16,
			'descriptionFontFamily'     => '',
			'descriptionFontWeight'     => '',
			'descriptionTextTransform'  => '',
			'descriptionFontStyle'      => '',
			'descriptionTextDecoration' => '',
			'descriptionLineHeight'     => '',
			'descriptionLetterSpacing'  => '',
			'descriptionWordSpacing'    => '',
			'descriptionSpacing'        => 0,
			'buttonBgColor'             => '#007cba',
			'buttonTextColor'           => '#ffffff',
			'buttonSize'                => 16,
			'buttonFontFamily'          => '',
			'buttonFontWeight'          => '',
			'buttonTextTransform'       => '',
			'buttonFontStyle'           => '',
			'buttonTextDecoration'      => '',
			'buttonLineHeight'          => '',
			'buttonLetterSpacing'       => '',
			'buttonWordSpacing'         => '',
			'buttonBorderRadius'        => 4,
			'buttonPadding'             => array( 'top' => 12, 'right' => 24, 'bottom' => 12, 'left' => 24 ),
			'contentPadding'            => array( 'top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50 ),
			'contentMargin'             => array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ),
			'ribbonTitle'               => '',
			'ribbonBgColor'             => '#007cba',
			'ribbonTextColor'           => '#ffffff',
			'ribbonSize'                => 16,
			'ribbonFontFamily'          => '',
			'ribbonFontWeight'          => '',
			'ribbonTextTransform'       => '',
			'ribbonFontStyle'           => '',
			'ribbonTextDecoration'      => '',
			'ribbonLineHeight'          => '',
			'ribbonLetterSpacing'       => '',
			'ribbonWordSpacing'         => '',
			'ribbonHorizontalPosition'  => 'left',
			'ribbonDistance'            => 42,
		);

		foreach ( $attrs as $key => $value ) {
			// Drop empty strings early.
			if ( is_string( $value ) && '' === trim( $value ) ) {
				unset( $attrs[ $key ] );
				continue;
			}

			if ( array_key_exists( $key, $defaults ) ) {
				$def = $defaults[ $key ];

				if ( is_array( $value ) && is_array( $def ) ) {
					if ( wp_json_encode( $value ) === wp_json_encode( $def ) ) {
						unset( $attrs[ $key ] );
					}
					continue;
				}

				if ( $value === $def ) {
					unset( $attrs[ $key ] );
				}
			}
		}

		return $attrs;
	}
}
