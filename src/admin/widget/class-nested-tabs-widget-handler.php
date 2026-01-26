<?php
/**
 * Widget handler for Elementor Nested tab widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Elementor_Elements_Parser;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function wp_json_encode;
use function wp_strip_all_tags;
use function do_blocks;

defined( 'ABSPATH' ) || exit;

class Nested_Tabs_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {

		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$tabs     = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();

		if ( empty( $tabs ) ) {
			return '';
		}

		$tab_items = array();
		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$title = '';
			if ( isset( $tab['settings'] ) && is_array( $tab['settings'] ) && isset( $tab['settings']['_title'] ) ) {
				$title = (string) $tab['settings']['_title'];
			}
			$title = trim( (string) $title );
			if ( '' === $title ) {
				$title = 'Tab ' . ( count( $tab_items ) + 1 );
			}

			$content_text = $this->extract_tab_content_text( $tab );

			$tab_items[] = array(
				'title'   => wp_strip_all_tags( $title ),
				'content' => $content_text,
			);
		}
		if ( empty( $tab_items ) ) {
			return '';
		}

		$attrs         = $this->get_progressus_tabs_defaults();
		$attrs['tabs'] = $tab_items;

		$active = 0;
		if ( isset( $settings['active_tab'] ) && is_numeric( $settings['active_tab'] ) ) {
			$active = max( 0, (int) $settings['active_tab'] );
		} elseif ( isset( $settings['activeTab'] ) && is_numeric( $settings['activeTab'] ) ) {
			$active = max( 0, (int) $settings['activeTab'] );
		}
		$attrs['activeTab'] = $active;

		$position = '';
		if ( isset( $settings['tabs_position'] ) ) {
			$position = strtolower( trim( (string) $settings['tabs_position'] ) );
		} elseif ( isset( $settings['tab_position'] ) ) {
			$position = strtolower( trim( (string) $settings['tab_position'] ) );
		}
		if ( in_array( $position, array( 'left', 'right' ), true ) ) {
			$attrs['tabStyle'] = 'vertical';
		}

		return $this->serialize_progressus_tabs_block( $attrs );
	}

	/**
	 * Extract nested-tab content as plain text to keep Gutenberg from extracting inner blocks
	 * out of HTML wrappers (the exact issue you saw in Before/After).
	 *
	 * @param array $tab Tab element data.
	 *
	 * @return string
	 */
	private function extract_tab_content_text( array $tab ): string {
		$text = '';

		if ( isset( $tab['elements'] ) && is_array( $tab['elements'] ) && ! empty( $tab['elements'] ) ) {
			$blocks = Elementor_Elements_Parser::parse( $tab['elements'] );
			$blocks = trim( (string) $blocks );

			if ( '' !== $blocks && function_exists( 'do_blocks' ) ) {
				$html = do_blocks( $blocks );
				$text = $this->html_to_text( (string) $html );
			} else {
				$text = $this->html_to_text( (string) $blocks );
			}
		} elseif ( isset( $tab['settings'] ) && is_array( $tab['settings'] ) && isset( $tab['settings']['content'] ) ) {
			$text = $this->html_to_text( (string) $tab['settings']['content'] );
		}

		return $text;
	}

	/**
	 * Convert HTML-ish input into readable plain text with basic line breaks.
	 *
	 * @param string $html Input.
	 *
	 * @return string
	 */
	private function html_to_text( string $html ): string {
		$html = preg_replace( '/<\s*br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<\/\s*(p|div|h[1-6]|li|ul|ol|section|article)\s*>/i', "\n", $html );
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( "/[ \t]/", ' ', (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Defaults aligned to the progressus/tabs block save() output.
	 *
	 * @return array
	 */
	private function get_progressus_tabs_defaults(): array {
		return array(
			'tabs'                      => array(),
			'activeTab'                 => 0,
			'tabStyle'                  => 'horizontal',
			'tabColor'                  => '',
			'activeTabColor'            => '',
			'tabBorderColor'            => '',
			'activeTabBorderColor'      => '',
			'tabTextColor'              => '',
			'activeTabTextColor'        => '',
			'tabPadding'                => array( 'top' => 12, 'right' => 16, 'bottom' => 12, 'left' => 16 ),
			'tabMargin'                 => array( 'top' => 0, 'right' => 2, 'bottom' => 0, 'left' => 0 ),
			'tabBorderRadius'           => 4,
			'tabBorderWidth'            => 1,
			'tabBorderStyle'            => 'solid',
			'tabFontSize'               => 16,
			'tabFontWeight'             => 'normal',
			'tabLineHeight'             => 1.5,
			'tabFontFamily'             => '',
			'tabContentBackgroundColor' => '',
			'tabContentTextColor'       => '',
			'tabContentPadding'         => array( 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20 ),
			'tabContentMargin'          => array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ),
			'tabContentBorderColor'     => '',
			'tabContentBorderRadius'    => 4,
			'tabContentFontSize'        => 14,
			'tabContentFontWeight'      => 'normal',
			'tabContentLineHeight'      => 1.6,
			'tabContentFontFamily'      => '',
		);
	}

	/**
	 * Serialize a progressus/tabs block with inner HTML matching the block's save() output.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private function serialize_progressus_tabs_block( array $attrs ): string {
		$inner = $this->render_progressus_tabs_inner_html( $attrs );
		$json  = ' ' . wp_json_encode( $attrs );

		return '<!-- wp:progressus/tabs' . $json . " -->\n" . $inner . "\n<!-- /wp:progressus/tabs -->\n";
	}

	/**
	 * Render the exact DOM structure used by the progressus/tabs block.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private function render_progressus_tabs_inner_html( array $attrs ): string {
		$tabs      = isset( $attrs['tabs'] ) && is_array( $attrs['tabs'] ) ? $attrs['tabs'] : array();
		$active    = isset( $attrs['activeTab'] ) ? (int) $attrs['activeTab'] : 0;
		$tab_style = isset( $attrs['tabStyle'] ) ? (string) $attrs['tabStyle'] : 'horizontal';

		$wrapper_style = 'display:' . ( 'vertical' === $tab_style ? 'flex' : 'block' ) . ';' .
		                 'flex-direction:' . ( 'vertical' === $tab_style ? 'row' : 'column' );

		$headers_style = 'display:flex;' .
		                 'flex-direction:' . ( 'vertical' === $tab_style ? 'column' : 'row' );

		$tab_color           = isset( $attrs['tabColor'] ) ? (string) $attrs['tabColor'] : '';
		$active_tab_color    = isset( $attrs['activeTabColor'] ) ? (string) $attrs['activeTabColor'] : '';
		$tab_border_color    = isset( $attrs['tabBorderColor'] ) ? (string) $attrs['tabBorderColor'] : '';
		$active_border_color = isset( $attrs['activeTabBorderColor'] ) ? (string) $attrs['activeTabBorderColor'] : '';
		$tab_text_color      = isset( $attrs['tabTextColor'] ) ? (string) $attrs['tabTextColor'] : '';
		$active_text_color   = isset( $attrs['activeTabTextColor'] ) ? (string) $attrs['activeTabTextColor'] : '';

		$pad = isset( $attrs['tabPadding'] ) && is_array( $attrs['tabPadding'] ) ? $attrs['tabPadding'] : array();
		$mar = isset( $attrs['tabMargin'] ) && is_array( $attrs['tabMargin'] ) ? $attrs['tabMargin'] : array();

		$pad_top    = isset( $pad['top'] ) ? (int) $pad['top'] : 12;
		$pad_right  = isset( $pad['right'] ) ? (int) $pad['right'] : 16;
		$pad_bottom = isset( $pad['bottom'] ) ? (int) $pad['bottom'] : 12;
		$pad_left   = isset( $pad['left'] ) ? (int) $pad['left'] : 16;

		$mar_top    = isset( $mar['top'] ) ? (int) $mar['top'] : 0;
		$mar_right  = isset( $mar['right'] ) ? (int) $mar['right'] : 2;
		$mar_bottom = isset( $mar['bottom'] ) ? (int) $mar['bottom'] : 0;
		$mar_left   = isset( $mar['left'] ) ? (int) $mar['left'] : 0;

		$radius      = isset( $attrs['tabBorderRadius'] ) ? (int) $attrs['tabBorderRadius'] : 4;
		$border_w    = isset( $attrs['tabBorderWidth'] ) ? (int) $attrs['tabBorderWidth'] : 1;
		$border_s    = isset( $attrs['tabBorderStyle'] ) ? (string) $attrs['tabBorderStyle'] : 'solid';
		$font_size   = isset( $attrs['tabFontSize'] ) ? (int) $attrs['tabFontSize'] : 16;
		$font_weight = isset( $attrs['tabFontWeight'] ) ? (string) $attrs['tabFontWeight'] : 'normal';
		$line_height = isset( $attrs['tabLineHeight'] ) ? (string) $attrs['tabLineHeight'] : '1.5';
		$font_family = isset( $attrs['tabFontFamily'] ) ? trim( (string) $attrs['tabFontFamily'] ) : '';

		$border_color_for_shorthand = '' !== $tab_border_color ? $tab_border_color : '#ddd';

		$base_header_style = array(
			'background-color:' . $tab_color,
			'border-color:' . $tab_border_color,
			'color:' . $tab_text_color,
			'padding:' . $pad_top . 'px ' . $pad_right . 'px ' . $pad_bottom . 'px ' . $pad_left . 'px',
			'margin:' . $mar_top . 'px ' . $mar_right . 'px ' . $mar_bottom . 'px ' . $mar_left . 'px',
			'cursor:pointer',
			'border:' . $border_w . 'px ' . ( '' !== $border_s ? $border_s : 'solid' ) . ' ' . $border_color_for_shorthand,
			'border-radius:' . $radius . 'px',
			'font-size:' . $font_size . 'px',
			'font-weight:' . ( '' !== $font_weight ? $font_weight : 'normal' ),
			'line-height:' . $line_height,
		);
		if ( '' !== $font_family ) {
			$base_header_style[] = 'font-family:' . $font_family;
		}

		$content_bg    = isset( $attrs['tabContentBackgroundColor'] ) ? (string) $attrs['tabContentBackgroundColor'] : '';
		$content_color = isset( $attrs['tabContentTextColor'] ) ? (string) $attrs['tabContentTextColor'] : '';
		$c_pad         = isset( $attrs['tabContentPadding'] ) && is_array( $attrs['tabContentPadding'] ) ? $attrs['tabContentPadding'] : array();
		$c_mar         = isset( $attrs['tabContentMargin'] ) && is_array( $attrs['tabContentMargin'] ) ? $attrs['tabContentMargin'] : array();
		$c_border_c    = isset( $attrs['tabContentBorderColor'] ) ? (string) $attrs['tabContentBorderColor'] : '';
		$c_radius      = isset( $attrs['tabContentBorderRadius'] ) ? (int) $attrs['tabContentBorderRadius'] : 4;
		$c_font_size   = isset( $attrs['tabContentFontSize'] ) ? (int) $attrs['tabContentFontSize'] : 14;
		$c_font_weight = isset( $attrs['tabContentFontWeight'] ) ? (string) $attrs['tabContentFontWeight'] : 'normal';
		$c_line_height = isset( $attrs['tabContentLineHeight'] ) ? (string) $attrs['tabContentLineHeight'] : '1.6';
		$c_font_family = isset( $attrs['tabContentFontFamily'] ) ? trim( (string) $attrs['tabContentFontFamily'] ) : '';

		$c_pad_top    = isset( $c_pad['top'] ) ? (int) $c_pad['top'] : 20;
		$c_pad_right  = isset( $c_pad['right'] ) ? (int) $c_pad['right'] : 20;
		$c_pad_bottom = isset( $c_pad['bottom'] ) ? (int) $c_pad['bottom'] : 20;
		$c_pad_left   = isset( $c_pad['left'] ) ? (int) $c_pad['left'] : 20;

		$c_mar_top    = isset( $c_mar['top'] ) ? (int) $c_mar['top'] : 0;
		$c_mar_right  = isset( $c_mar['right'] ) ? (int) $c_mar['right'] : 0;
		$c_mar_bottom = isset( $c_mar['bottom'] ) ? (int) $c_mar['bottom'] : 0;
		$c_mar_left   = isset( $c_mar['left'] ) ? (int) $c_mar['left'] : 0;

		$c_border_color_for_shorthand = '' !== $c_border_c ? $c_border_c : '#ddd';

		$content_style_parts = array(
			'background-color:' . $content_bg,
			'color:' . $content_color,
			'padding:' . $c_pad_top . 'px ' . $c_pad_right . 'px ' . $c_pad_bottom . 'px ' . $c_pad_left . 'px',
			'margin:' . $c_mar_top . 'px ' . $c_mar_right . 'px ' . $c_mar_bottom . 'px ' . $c_mar_left . 'px',
			'border:' . $border_w . 'px ' . ( '' !== $border_s ? $border_s : 'solid' ) . ' ' . $c_border_color_for_shorthand,
			'border-radius:' . $c_radius . 'px',
			'border-top:' . ( 'horizontal' === $tab_style ? 'none' : ( $border_w . 'px ' . ( '' !== $border_s ? $border_s : 'solid' ) . ' ' . $border_color_for_shorthand ) ),
			'font-size:' . $c_font_size . 'px',
			'font-weight:' . ( '' !== $c_font_weight ? $c_font_weight : 'normal' ),
			'line-height:' . $c_line_height,
		);
		if ( '' !== $c_font_family ) {
			$content_style_parts[] = 'font-family:' . $c_font_family;
		}

		$headers_html = '';
		foreach ( $tabs as $i => $tab ) {
			$is_active = ( $i === $active );

			$header_class = 'progressus-tab-header' . ( $is_active ? ' active' : ' ' );

			$header_style    = $base_header_style;
			$header_style[0] = 'background-color:' . ( $is_active ? $active_tab_color : $tab_color );
			$header_style[1] = 'border-color:' . ( $is_active ? $active_border_color : $tab_border_color );
			$header_style[2] = 'color:' . ( $is_active ? $active_text_color : $tab_text_color );

			$title = isset( $tab['title'] ) ? (string) $tab['title'] : '';

			$headers_html .= '<div class="' . esc_attr( $header_class ) . '" style="' . esc_attr( implode( ';', $header_style ) ) . '" data-tab-index="' . esc_attr( (string) $i ) . '">' . wp_strip_all_tags( $title ) . '</div>';
		}

		$contents_html = '';
		foreach ( $tabs as $i => $tab ) {
			$is_active     = ( $i === $active );
			$class         = 'progressus-tab-content' . ( $is_active ? ' active' : ' ' );
			$style         = 'display:' . ( $is_active ? 'block' : 'none' );
			$content       = isset( $tab['content'] ) ? (string) $tab['content'] : '';
			$contents_html .= '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">' . esc_attr( $content ) . '</div>';
		}

		return '<div class="wp-block-progressus-tabs">' .
		       '<div class="progressus-tabs" style="' . esc_attr( $wrapper_style ) . '" data-tab-style="' . esc_attr( $tab_style ) . '" data-active-tab="' . esc_attr( (string) $active ) . '">' .
		       '<div class="progressus-tabs-headers" style="' . esc_attr( $headers_style ) . '">' . $headers_html . '</div>' .
		       '<div class="progressus-tabs-content" style="' . esc_attr( implode( ';', $content_style_parts ) ) . '">' . $contents_html . '</div>' .
		       '</div></div>';
	}
}