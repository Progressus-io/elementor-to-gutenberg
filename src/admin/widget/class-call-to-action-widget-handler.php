<?php
namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

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
		$settings = $widget_data['settings'] ?? array();

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

		// Parse button typography
		$button_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'button_typography_' ) === 0 ) {
				$new_key = substr( $key, strlen( 'button_' ) );
				$button_typography_settings[ $new_key ] = $value;
			}
		}
		$button_typography = Style_Parser::parse_typography( $button_typography_settings );
		$button_typography_attr = isset( $button_typography['attributes'] ) ? $button_typography['attributes'] : array();

		// Parse ribbon typography
		$ribbon_typography_settings = array();
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'ribbon_typography_' ) === 0 ) {
				$new_key = substr( $key, strlen( 'ribbon_' ) );
				$ribbon_typography_settings[ $new_key ] = $value;
			}
		}
		$ribbon_typography = Style_Parser::parse_typography( $ribbon_typography_settings );
		$ribbon_typography_attr = isset( $ribbon_typography['attributes'] ) ? $ribbon_typography['attributes'] : array();

		// Extract basic data
		$layout = isset( $settings['layout'] ) ? (string) $settings['layout'] : 'left';
		$bg_image_data = $this->resolve_bg_image_data( $settings );
		$bg_image_url = $bg_image_data['url'] ?? '';
		$bg_image_id = $bg_image_data['id'] ?? 0;
		// Extract title, description and button text with fallbacks for different Elementor field names.
		$title = '';
		foreach ( array( 'title', 'title_text', 'cta_title', 'heading' ) as $k ) {
			if ( isset( $settings[ $k ] ) && '' !== trim( (string) $settings[ $k ] ) ) {
				$title = (string) $settings[ $k ];
				break;
			}
		}

		$description = '';
		foreach ( array( 'description', 'desc', 'subtitle', 'content', 'text' ) as $k ) {
			if ( isset( $settings[ $k ] ) && '' !== trim( (string) $settings[ $k ] ) ) {
				$description = (string) $settings[ $k ];
				break;
			}
		}

		$button_text = '';
		foreach ( array( 'button', 'button_text', 'cta_text', 'link_text' ) as $k ) {
			if ( isset( $settings[ $k ] ) && '' !== trim( (string) $settings[ $k ] ) ) {
				$button_text = (string) $settings[ $k ];
				break;
			}
		}

		$button_url  = isset( $settings['link']['url'] ) ? (string) $settings['link']['url'] : '';
		$button_target = ! empty( $settings['link']['is_external'] );
		$button_nofollow = ! empty( $settings['link']['nofollow'] );
		// Prefer Elementor's text_align if present, fall back to alignment
		$alignment = isset( $settings['text_align'] ) ? (string) $settings['text_align'] : ( isset( $settings['alignment'] ) ? (string) $settings['alignment'] : 'left' );
		$image_min_height = $this->sanitize_slider_value( $settings['image_min_height'] ?? null, 425 );
		
		// Extract colors
		$content_bg_color = isset( $settings['content_bg_color'] ) ? (string) $settings['content_bg_color'] : '';
		$title_color = isset( $settings['title_color'] ) ? (string) $settings['title_color'] : ( isset( $title_typography_attr['color'] ) ? $title_typography_attr['color'] : '#000000' );
		$description_color = isset( $settings['description_color'] ) ? (string) $settings['description_color'] : ( isset( $description_typography_attr['color'] ) ? $description_typography_attr['color'] : '#666666' );
		$button_bg_color = isset( $settings['button_background_color'] ) ? (string) $settings['button_background_color'] : '#007cba';
		$button_text_color = isset( $settings['button_text_color'] ) ? (string) $settings['button_text_color'] : '#ffffff';

		// Extract spacing values
		$description_spacing = $this->sanitize_slider_value( $settings['description_spacing'] ?? null, 0 );
		$content_padding = $this->extract_padding( $settings['padding'] ?? array() );
		$content_margin = $this->extract_padding( $settings['_margin'] ?? array() );
		$button_padding = $this->extract_padding( $settings['button_padding'] ?? array() );

		// Extract ribbon data
		$ribbon_title = isset( $settings['ribbon_title'] ) ? (string) $settings['ribbon_title'] : '';
		$ribbon_bg_color = isset( $settings['ribbon_bg_color'] ) ? (string) $settings['ribbon_bg_color'] : '#007cba';
		$ribbon_text_color = isset( $settings['ribbon_text_color'] ) ? (string) $settings['ribbon_text_color'] : '#ffffff';
		$ribbon_horizontal_position = isset( $settings['ribbon_horizontal_position'] ) ? (string) $settings['ribbon_horizontal_position'] : 'left';
		$ribbon_distance = $this->sanitize_slider_value( $settings['ribbon_distance'] ?? null, 42 );

		// Extract typography attributes with fallback to Elementor settings
		$title_size = isset( $title_typography_attr['fontSize'] ) ? (int) $title_typography_attr['fontSize'] : 28;
		$title_font_family = isset( $title_typography_attr['fontFamily'] ) ? $title_typography_attr['fontFamily'] : '';
		$title_font_weight = isset( $title_typography_attr['fontWeight'] ) ? $title_typography_attr['fontWeight'] : '';
		$title_text_transform = isset( $title_typography_attr['textTransform'] ) ? $title_typography_attr['textTransform'] : '';
		$title_font_style = isset( $title_typography_attr['fontStyle'] ) ? $title_typography_attr['fontStyle'] : '';
		$title_text_decoration = isset( $title_typography_attr['textDecoration'] ) ? $title_typography_attr['textDecoration'] : '';
		$title_line_height = isset( $title_typography_attr['lineHeight'] ) ? $title_typography_attr['lineHeight'] : '';
		$title_letter_spacing = isset( $title_typography_attr['letterSpacing'] ) ? $title_typography_attr['letterSpacing'] : '';
		$title_word_spacing = isset( $title_typography_attr['wordSpacing'] ) ? $title_typography_attr['wordSpacing'] : '';

		$description_size = isset( $description_typography_attr['fontSize'] ) ? (int) $description_typography_attr['fontSize'] : 16;
		$description_font_family = isset( $description_typography_attr['fontFamily'] ) ? $description_typography_attr['fontFamily'] : '';
		$description_font_weight = isset( $description_typography_attr['fontWeight'] ) ? $description_typography_attr['fontWeight'] : '';
		$description_text_transform = isset( $description_typography_attr['textTransform'] ) ? $description_typography_attr['textTransform'] : '';
		$description_font_style = isset( $description_typography_attr['fontStyle'] ) ? $description_typography_attr['fontStyle'] : '';
		$description_text_decoration = isset( $description_typography_attr['textDecoration'] ) ? $description_typography_attr['textDecoration'] : '';
		$description_line_height = isset( $description_typography_attr['lineHeight'] ) ? $description_typography_attr['lineHeight'] : '';
		$description_letter_spacing = isset( $description_typography_attr['letterSpacing'] ) ? $description_typography_attr['letterSpacing'] : '';
		$description_word_spacing = isset( $description_typography_attr['wordSpacing'] ) ? $description_typography_attr['wordSpacing'] : '';

		$button_size = isset( $button_typography_attr['fontSize'] ) ? (int) $button_typography_attr['fontSize'] : 16;
		$button_font_family = isset( $button_typography_attr['fontFamily'] ) ? $button_typography_attr['fontFamily'] : '';
		$button_font_weight = isset( $button_typography_attr['fontWeight'] ) ? $button_typography_attr['fontWeight'] : '';
		$button_text_transform = isset( $button_typography_attr['textTransform'] ) ? $button_typography_attr['textTransform'] : '';
		$button_font_style = isset( $button_typography_attr['fontStyle'] ) ? $button_typography_attr['fontStyle'] : '';
		$button_text_decoration = isset( $button_typography_attr['textDecoration'] ) ? $button_typography_attr['textDecoration'] : '';
		$button_line_height = isset( $button_typography_attr['lineHeight'] ) ? $button_typography_attr['lineHeight'] : '';
		$button_letter_spacing = isset( $button_typography_attr['letterSpacing'] ) ? $button_typography_attr['letterSpacing'] : '';
		$button_word_spacing = isset( $button_typography_attr['wordSpacing'] ) ? $button_typography_attr['wordSpacing'] : '';

		$ribbon_size = isset( $ribbon_typography_attr['fontSize'] ) ? (int) $ribbon_typography_attr['fontSize'] : 16;
		$ribbon_font_family = isset( $ribbon_typography_attr['fontFamily'] ) ? $ribbon_typography_attr['fontFamily'] : '';
		$ribbon_font_weight = isset( $ribbon_typography_attr['fontWeight'] ) ? $ribbon_typography_attr['fontWeight'] : '';
		$ribbon_text_transform = isset( $ribbon_typography_attr['textTransform'] ) ? $ribbon_typography_attr['textTransform'] : '';
		$ribbon_font_style = isset( $ribbon_typography_attr['fontStyle'] ) ? $ribbon_typography_attr['fontStyle'] : '';
		$ribbon_text_decoration = isset( $ribbon_typography_attr['textDecoration'] ) ? $ribbon_typography_attr['textDecoration'] : '';
		$ribbon_line_height = isset( $ribbon_typography_attr['lineHeight'] ) ? $ribbon_typography_attr['lineHeight'] : '';
		$ribbon_letter_spacing = isset( $ribbon_typography_attr['letterSpacing'] ) ? $ribbon_typography_attr['letterSpacing'] : '';
		$ribbon_word_spacing = isset( $ribbon_typography_attr['wordSpacing'] ) ? $ribbon_typography_attr['wordSpacing'] : '';

		// Build HTML segments
		$segments = array();

		// Build title HTML with styles
		if ( '' !== trim( $title ) ) {
			$title_style_parts = array( 'font-size:' . $title_size . 'px', 'color:' . esc_attr( $title_color ), 'margin-bottom:16px' );
			if ( $title_font_family ) $title_style_parts[] = 'font-family:' . esc_attr( $title_font_family );
			if ( $title_font_weight ) $title_style_parts[] = 'font-weight:' . esc_attr( $title_font_weight );
			if ( $title_text_transform ) $title_style_parts[] = 'text-transform:' . esc_attr( $title_text_transform );
			if ( $title_font_style ) $title_style_parts[] = 'font-style:' . esc_attr( $title_font_style );
			if ( $title_text_decoration ) $title_style_parts[] = 'text-decoration:' . esc_attr( $title_text_decoration );
			if ( $title_line_height ) $title_style_parts[] = 'line-height:' . esc_attr( $title_line_height );
			if ( $title_letter_spacing ) $title_style_parts[] = 'letter-spacing:' . esc_attr( $title_letter_spacing );
			if ( $title_word_spacing ) $title_style_parts[] = 'word-spacing:' . esc_attr( $title_word_spacing );
			$segments[] = '<h2 class="call-to-action-title" style="' . implode( ';', $title_style_parts ) . '">' . esc_html( $title ) . '</h2>';
		}

		// Build description HTML with styles
		if ( '' !== trim( $description ) ) {
			$sanitized_description = wp_kses_post( $description );
			$sanitized_description_no_newlines = str_replace( array( "\r\n", "\r", "\n" ), '', $sanitized_description );
			
			$description_style_parts = array( 'font-size:' . $description_size . 'px', 'color:' . esc_attr( $description_color ), 'margin-bottom:' . $description_spacing . 'px' );
			if ( $description_font_family ) $description_style_parts[] = 'font-family:' . esc_attr( $description_font_family );
			if ( $description_font_weight ) $description_style_parts[] = 'font-weight:' . esc_attr( $description_font_weight );
			if ( $description_text_transform ) $description_style_parts[] = 'text-transform:' . esc_attr( $description_text_transform );
			if ( $description_font_style ) $description_style_parts[] = 'font-style:' . esc_attr( $description_font_style );
			if ( $description_text_decoration ) $description_style_parts[] = 'text-decoration:' . esc_attr( $description_text_decoration );
			if ( $description_line_height ) $description_style_parts[] = 'line-height:' . esc_attr( $description_line_height );
			if ( $description_letter_spacing ) $description_style_parts[] = 'letter-spacing:' . esc_attr( $description_letter_spacing );
			if ( $description_word_spacing ) $description_style_parts[] = 'word-spacing:' . esc_attr( $description_word_spacing );
			$segments[] = '<p class="call-to-action-description" style="' . implode( ';', $description_style_parts ) . '">' . $sanitized_description_no_newlines . '</p>';
		}

		// Build button HTML with styles
		if ( '' !== trim( $button_text ) ) {
			$button_style_parts = array( 
				'display:inline-block',
				'font-size:' . $button_size . 'px', 
				'color:' . esc_attr( $button_text_color ),
				'background-color:' . esc_attr( $button_bg_color ),
				'padding:' . $button_padding['top'] . 'px ' . $button_padding['right'] . 'px ' . $button_padding['bottom'] . 'px ' . $button_padding['left'] . 'px',
				'border-radius:4px',
				'text-decoration:none',
				'cursor:pointer',
				'border:none'
			);
			if ( $button_font_family ) $button_style_parts[] = 'font-family:' . esc_attr( $button_font_family );
			if ( $button_font_weight ) $button_style_parts[] = 'font-weight:' . esc_attr( $button_font_weight );
			if ( $button_text_transform ) $button_style_parts[] = 'text-transform:' . esc_attr( $button_text_transform );
			if ( $button_font_style ) $button_style_parts[] = 'font-style:' . esc_attr( $button_font_style );
			if ( $button_text_decoration && $button_text_decoration !== 'none' ) $button_style_parts[] = 'text-decoration:' . esc_attr( $button_text_decoration );
			if ( $button_line_height ) $button_style_parts[] = 'line-height:' . esc_attr( $button_line_height );
			if ( $button_letter_spacing ) $button_style_parts[] = 'letter-spacing:' . esc_attr( $button_letter_spacing );
			if ( $button_word_spacing ) $button_style_parts[] = 'word-spacing:' . esc_attr( $button_word_spacing );
			
			if ( '' !== trim( $button_url ) ) {
				$target_attr = $button_target ? ' target="_blank"' : '';
				$rel_attr = $button_nofollow ? ' rel="nofollow"' : '';
				$segments[] = '<a href="' . esc_url( $button_url ) . '" class="call-to-action-button" style="' . implode( ';', $button_style_parts ) . '"' . $target_attr . $rel_attr . '><span>' . esc_html( $button_text ) . '</span></a>';
			} else {
				$segments[] = '<span class="call-to-action-button" style="' . implode( ';', $button_style_parts ) . '">' . esc_html( $button_text ) . '</span>';
			}
		}

		// Build ribbon HTML with styles
		$ribbon_html = '';
		if ( '' !== trim( $ribbon_title ) ) {
			$ribbon_style_parts = array( 
				'position:absolute',
				'top:' . $ribbon_distance . 'px',
				( $ribbon_horizontal_position === 'right' ? 'right:' . $ribbon_distance . 'px' : 'left:' . $ribbon_distance . 'px' ),
				'background-color:' . esc_attr( $ribbon_bg_color ),
				'color:' . esc_attr( $ribbon_text_color ),
				'font-size:' . $ribbon_size . 'px',
				'padding:8px 16px',
				'border-radius:4px',
				'z-index:10',
				'transform:' . ( $ribbon_horizontal_position === 'right' ? 'rotate(15deg)' : 'rotate(-15deg)' )
			);
			if ( $ribbon_font_family ) $ribbon_style_parts[] = 'font-family:' . esc_attr( $ribbon_font_family );
			if ( $ribbon_font_weight ) $ribbon_style_parts[] = 'font-weight:' . esc_attr( $ribbon_font_weight );
			if ( $ribbon_text_transform ) $ribbon_style_parts[] = 'text-transform:' . esc_attr( $ribbon_text_transform );
			if ( $ribbon_font_style ) $ribbon_style_parts[] = 'font-style:' . esc_attr( $ribbon_font_style );
			if ( $ribbon_text_decoration ) $ribbon_style_parts[] = 'text-decoration:' . esc_attr( $ribbon_text_decoration );
			if ( $ribbon_line_height ) $ribbon_style_parts[] = 'line-height:' . esc_attr( $ribbon_line_height );
			if ( $ribbon_letter_spacing ) $ribbon_style_parts[] = 'letter-spacing:' . esc_attr( $ribbon_letter_spacing );
			if ( $ribbon_word_spacing ) $ribbon_style_parts[] = 'word-spacing:' . esc_attr( $ribbon_word_spacing );
			
			$ribbon_html = '<div class="call-to-action-ribbon" style="' . implode( ';', $ribbon_style_parts ) . '">' . esc_html( $ribbon_title ) . '</div>';
		}

		// Build wrapper classes and styles
		$wrapper_classes = array( 
			'wp-block-call-to-action', 
			$alignment ? 'has-text-align-' . $alignment : '',
			'call-to-action-layout-' . $layout
		);
		$wrapper_attrs = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		$wrapper_attrs[] = 'style="text-align:' . esc_attr( $alignment ) . '"';

		// Build container styles
		$container_style_parts = array(
			'min-height:' . $image_min_height . 'px',
			'display:flex',
			'position:relative'
		);
		
		if ( $bg_image_url ) {
			if ( $layout === 'center' ) {
				$container_style_parts[] = 'background-image:url(' . esc_url( $bg_image_url ) . ')';
				$container_style_parts[] = 'background-size:cover';
				$container_style_parts[] = 'background-position:center';
			}
		}

		if ( in_array( $layout, array( 'left', 'right' ), true ) ) {
			$container_style_parts[] = 'align-items:stretch';
		} else {
			$container_style_parts[] = 'align-items:flex-start';
		}

		// Set horizontal justification
		if ( $layout === 'center' ) {
			$container_style_parts[] = 'justify-content:center';
		} elseif ( $layout === 'right' ) {
			$container_style_parts[] = 'justify-content:flex-end';
		} else {
			$container_style_parts[] = 'justify-content:flex-start';
		}

		// Set flex direction for image/content ordering
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
			default:
				break;
		}

		$image_html = '';
		if ( $bg_image_url && in_array( $layout, array( 'above', 'below', 'left', 'right' ), true ) ) {
			$image_style_parts = array(
				'background-image:url(' . esc_url( $bg_image_url ) . ')',
				'background-size:cover',
				'background-position:center',
				'min-height:' . $image_min_height . 'px',
			);

			if ( in_array( $layout, array( 'left', 'right' ), true ) ) {
				$image_style_parts[] = 'flex-basis:50%';
			}
			$aria_label = '';
			$path = parse_url( $bg_image_url, PHP_URL_PATH );
			if ( $path ) {
				$aria_label = basename( $path );
			}
			$aria_label = esc_attr( $aria_label );

			$image_html = '<div class="call-to-action-image" role="img" aria-label="' . $aria_label . '" style="' . implode( ';', $image_style_parts ) . '"></div>';
			$image_html .= '<div class="call-to-action-image-overlay"></div>';
		}

		// Build content styles
		$content_style_parts = array();
		if ( $content_bg_color ) {
			$content_style_parts[] = 'background-color:' . esc_attr( $content_bg_color );
		} else {
			$content_style_parts[] = 'background-color:rgba(255,255,255,0.9)';
		}
		
		$content_style_parts[] = 'padding:' . $content_padding['top'] . 'px ' . $content_padding['right'] . 'px ' . $content_padding['bottom'] . 'px ' . $content_padding['left'] . 'px';
		$content_style_parts[] = 'margin:' . $content_margin['top'] . 'px ' . $content_margin['right'] . 'px ' . $content_margin['bottom'] . 'px ' . $content_margin['left'] . 'px';
		
		if ( $layout === 'center' ) {
			$content_style_parts[] = 'max-width:600px';
		} elseif ( in_array( $layout, array( 'above', 'below' ), true ) ) {
			$content_style_parts[] = 'max-width:100%';
		} else {
			$content_style_parts[] = 'max-width:50%';
			if ( in_array( $layout, array( 'left', 'right' ), true ) ) {
				$content_style_parts[] = 'flex-basis:50%';
				$content_style_parts[] = 'display:flex';
				$content_style_parts[] = 'flex-direction:column';
				$content_style_parts[] = 'justify-content:flex-start';
			}
		}

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . 
		           '<div class="call-to-action-container" style="' . implode( ';', $container_style_parts ) . '">' .
		           $ribbon_html .
		           $image_html .
		           '<div class="call-to-action-content" style="' . implode( ';', $content_style_parts ) . '">' . 
		           implode( '', $segments ) . 
		           '</div></div></div>';

		if ( '' === trim( $content ) ) {
			$rebuild_segments = array();
			if ( '' !== trim( $block_attributes['title'] ?? '' ) ) {
				$rebuild_segments[] = '<h2 class="call-to-action-title" style="font-size:' . (int) ( $block_attributes['titleSize'] ?? 28 ) . 'px;color:' . esc_attr( $block_attributes['titleColor'] ?? '#000000' ) . ';margin-bottom:16px">' . esc_html( $block_attributes['title'] ) . '</h2>';
			}
			if ( '' !== trim( $block_attributes['description'] ?? '' ) ) {
				$san = wp_kses_post( $block_attributes['description'] );
				$san_no_nl = str_replace( array( "\r\n", "\r", "\n" ), '', $san );
				$rebuild_segments[] = '<p class="call-to-action-description" style="font-size:' . (int) ( $block_attributes['descriptionSize'] ?? 16 ) . 'px;color:' . esc_attr( $block_attributes['descriptionColor'] ?? '#666666' ) . ';margin-bottom:0px">' . $san_no_nl . '</p>';
			}
			if ( '' !== trim( $block_attributes['buttonText'] ?? '' ) ) {
				$bp = isset( $block_attributes['buttonPadding'] ) && is_array( $block_attributes['buttonPadding'] ) ? $block_attributes['buttonPadding'] : array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
				$rebuild_btn_style = implode( ';', array(
					'display:inline-block',
					'font-size:' . (int) ( $block_attributes['buttonSize'] ?? 16 ) . 'px',
					'color:' . esc_attr( $block_attributes['buttonTextColor'] ?? '#ffffff' ),
					'background-color:' . esc_attr( $block_attributes['buttonBgColor'] ?? '#007cba' ),
					'padding:' . (int) $bp['top'] . 'px ' . (int) $bp['right'] . 'px ' . (int) $bp['bottom'] . 'px ' . (int) $bp['left'] . 'px',
					'border-radius:4px',
					'text-decoration:none',
					'cursor:pointer',
					'border:none'
				) );
				$rebuild_segments[] = '<span class="call-to-action-button" style="' . $rebuild_btn_style . '">' . esc_html( $block_attributes['buttonText'] ) . '</span>';
			}

			$rebuild_content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . '<div class="call-to-action-container" style="' . implode( ';', $container_style_parts ) . '">' . $ribbon_html . $image_html . '<div class="call-to-action-content" style="' . implode( ';', $content_style_parts ) . '">' . implode( '', $rebuild_segments ) . '</div></div></div>';
			$content = $rebuild_content;
		}

		// Build block attributes
		$block_attributes = array(
			'layout'                     => $layout,
			'bgImageUrl'                 => $bg_image_url,
			'bgImageId'                  => $bg_image_id,
			'title'                      => $title,
			'description'                => isset( $sanitized_description_no_newlines ) ? $sanitized_description_no_newlines : $description,
			'buttonText'                 => $button_text,
			'buttonUrl'                  => $button_url,
			'buttonTarget'               => $button_target,
			'buttonNofollow'             => $button_nofollow,
			'alignment'                  => $alignment,
			'imageMinHeight'             => $image_min_height,
			'contentBgColor'             => $content_bg_color,
			'titleColor'                 => $title_color,
			'titleSize'                  => $title_size,
			'titleFontFamily'            => $title_font_family,
			'titleFontWeight'            => $title_font_weight,
			'titleTextTransform'         => $title_text_transform,
			'titleFontStyle'             => $title_font_style,
			'titleTextDecoration'        => $title_text_decoration,
			'titleLineHeight'            => $title_line_height,
			'titleLetterSpacing'         => $title_letter_spacing,
			'titleWordSpacing'           => $title_word_spacing,
			'descriptionColor'           => $description_color,
			'descriptionSize'            => $description_size,
			'descriptionFontFamily'      => $description_font_family,
			'descriptionFontWeight'      => $description_font_weight,
			'descriptionTextTransform'   => $description_text_transform,
			'descriptionFontStyle'       => $description_font_style,
			'descriptionTextDecoration'  => $description_text_decoration,
			'descriptionLineHeight'      => $description_line_height,
			'descriptionLetterSpacing'   => $description_letter_spacing,
			'descriptionWordSpacing'     => $description_word_spacing,
			'descriptionSpacing'         => $description_spacing,
			'buttonBgColor'              => $button_bg_color,
			'buttonTextColor'            => $button_text_color,
			'buttonSize'                 => $button_size,
			'buttonFontFamily'           => $button_font_family,
			'buttonFontWeight'           => $button_font_weight,
			'buttonTextTransform'        => $button_text_transform,
			'buttonFontStyle'            => $button_font_style,
			'buttonTextDecoration'       => $button_text_decoration,
			'buttonLineHeight'           => $button_line_height,
			'buttonLetterSpacing'        => $button_letter_spacing,
			'buttonWordSpacing'          => $button_word_spacing,
			'buttonBorderRadius'         => 4,
			'buttonPadding'              => $button_padding,
			'contentPadding'             => $content_padding,
			'contentMargin'              => $content_margin,
			'ribbonTitle'                => $ribbon_title,
			'ribbonBgColor'              => $ribbon_bg_color,
			'ribbonTextColor'            => $ribbon_text_color,
			'ribbonSize'                 => $ribbon_size,
			'ribbonFontFamily'           => $ribbon_font_family,
			'ribbonFontWeight'           => $ribbon_font_weight,
			'ribbonTextTransform'        => $ribbon_text_transform,
			'ribbonFontStyle'            => $ribbon_font_style,
			'ribbonTextDecoration'       => $ribbon_text_decoration,
			'ribbonLineHeight'           => $ribbon_line_height,
			'ribbonLetterSpacing'        => $ribbon_letter_spacing,
			'ribbonWordSpacing'          => $ribbon_word_spacing,
			'ribbonHorizontalPosition'   => $ribbon_horizontal_position,
			'ribbonDistance'             => $ribbon_distance,
		);

		// Ensure plain scalar values for title/description/buttonText — handle cases where Elementor stores them as arrays/objects.
		if ( '' === trim( $block_attributes['title'] ?? '' ) && isset( $settings['title'] ) ) {
			$raw = $settings['title'];
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$block_attributes['title'] = (string) $raw;
			} elseif ( is_array( $raw ) ) {
				$block_attributes['title'] = isset( $raw['raw'] ) ? (string) $raw['raw'] : ( isset( $raw['text'] ) ? (string) $raw['text'] : '' );
			}
		}

		if ( '' === trim( $block_attributes['description'] ?? '' ) && isset( $settings['description'] ) ) {
			$raw = $settings['description'];
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$block_attributes['description'] = (string) $raw;
			} elseif ( is_array( $raw ) ) {
				$block_attributes['description'] = isset( $raw['raw'] ) ? (string) $raw['raw'] : ( isset( $raw['text'] ) ? (string) $raw['text'] : '' );
			}
		}

		if ( '' === trim( $block_attributes['buttonText'] ?? '' ) && ( isset( $settings['button'] ) || isset( $settings['button_text'] ) ) ) {
			$raw = isset( $settings['button'] ) ? $settings['button'] : $settings['button_text'];
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$block_attributes['buttonText'] = (string) $raw;
			} elseif ( is_array( $raw ) ) {
				$block_attributes['buttonText'] = isset( $raw['raw'] ) ? (string) $raw['raw'] : ( isset( $raw['text'] ) ? (string) $raw['text'] : '' );
			}
		}

		$built = Block_Builder::build( 'gutenberg/call-to-action', $block_attributes, $content );

		return $built;
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
	 * @param int   $default Default value if parsing fails.
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
	 *
	 * @return array Normalized padding with top, right, bottom, left keys.
	 */
	private function extract_padding( array $padding ): array {
		return array(
			'top'    => isset( $padding['top'] ) ? (int) $padding['top'] : 0,
			'right'  => isset( $padding['right'] ) ? (int) $padding['right'] : 0,
			'bottom' => isset( $padding['bottom'] ) ? (int) $padding['bottom'] : 0,
			'left'   => isset( $padding['left'] ) ? (int) $padding['left'] : 0,
		);
	}
}