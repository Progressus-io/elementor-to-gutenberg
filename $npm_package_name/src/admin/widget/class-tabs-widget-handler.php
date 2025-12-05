<?php
namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor tabs widget.
 */
class Tabs_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor tabs widget to Gutenberg custom tabs block.
	 *
	 * @param array $element Elementor widget data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		if ( empty( $element['settings']['tabs'] ) || ! is_array( $element['settings']['tabs'] ) ) {
			return '';
		}

		$settings   = $element['settings'];
		$tabs       = $settings['tabs'];
		$custom_css = $settings['custom_css'] ?? '';

		// Convert Elementor tabs to Gutenberg tabs format.
		$gutenberg_tabs = array();
		foreach ( $tabs as $index => $tab ) {
			$title   = isset( $tab['tab_title'] ) ? $tab['tab_title'] : 'Tab ' . ( $index + 1 );
			$content = isset( $tab['tab_content'] ) ? wp_strip_all_tags( $tab['tab_content'] ) : '';

			$gutenberg_tabs[] = array(
				'title'   => $title,
				'content' => $content,
			);
		}

		// Build block attributes from Elementor settings.
		$attributes = array(
			'tabs'      => $gutenberg_tabs,
			'activeTab' => 0,
		);

		$attributes['tabColor']               = $settings['tab_background_color'] ?? '';
		$attributes['activeTabColor']         = $settings['background_color'] ?? ( $settings['tab_active_color'] ?? '' );
		$attributes['contentBackgroundColor'] = $settings['background_color'] ?? ( $settings['content_color'] ?? '' );
		$attributes['borderColor']            = $settings['border_color'] ?? '';
		$attributes['borderWidth']            = (int) ( $settings['border_width']['size'] ?? 1 );
		$attributes['borderStyle']            = $settings['border_style'] ?? 'solid';
		$attributes['borderRadius']           = (int) ( $settings['border_radius']['size'] ?? 4 );

		// Margin and padding settings - always provide defaults.
		$margin_data              = $settings['margin'] ?? $settings['tab_margin'] ?? $settings['_margin'] ?? array();
		$attributes['tabsMargin'] = array(
			'top'    => (int) ( $margin_data['top'] ?? 0 ),
			'right'  => (int) ( $margin_data['right'] ?? 2 ),
			'bottom' => (int) ( $margin_data['bottom'] ?? 0 ),
			'left'   => (int) ( $margin_data['left'] ?? 0 ),
		);

		$padding_data              = $settings['padding'] ?? $settings['_padding'] ?? array();
		$attributes['tabsPadding'] = array(
			'top'    => (int) ( $padding_data['top'] ?? 12 ),
			'right'  => (int) ( $padding_data['right'] ?? 16 ),
			'bottom' => (int) ( $padding_data['bottom'] ?? 12 ),
			'left'   => (int) ( $padding_data['left'] ?? 16 ),
		);

		// Typography settings - always provide defaults.
		$tab_font_size   = isset( $settings['tab_typography_font_size']['size'] ) ? $settings['tab_typography_font_size']['size'] : ( $settings['title_typography_font_size']['size'] ?? 16 );
		$tab_font_weight = $settings['tab_typography_font_weight'] ?? ( $settings['title_typography_font_weight'] ?? 'normal' );
		$tab_line_height = isset( $settings['tab_typography_line_height']['size'] ) ? $settings['tab_typography_line_height']['size'] : ( $settings['title_typography_line_height']['size'] ?? 1.5 );
		$tab_font_family = $settings['tab_typography_font_family'] ?? ( $settings['title_typography_font_family'] ?? '' );

		$attributes['tabTypography'] = array(
			'fontSize'   => (int) $tab_font_size,
			'fontWeight' => $tab_font_weight,
			'lineHeight' => $tab_line_height,
			'fontFamily' => $tab_font_family,
		);

		$attributes['contentTypography'] = array(
			'fontSize'   => (int) ( $settings['content_typography_font_size']['size'] ?? 14 ),
			'fontWeight' => $settings['content_typography_font_weight'] ?? 'normal',
			'lineHeight' => $settings['content_typography_line_height']['size'] ?? 1.6,
			'fontFamily' => $settings['content_typography_font_family'] ?? '',
		);

		// Text colors.
		$attributes['tabTextColor']       = $settings['tab_color'] ?? ( $settings['title_color'] ?? '' );
		$attributes['activeTabTextColor'] = $settings['tab_active_color'] ?? ( $settings['title_active_color'] ?? '' );
		$attributes['contentTextColor']   = $settings['content_text_color'] ?? '';

		// Additional attributes for complete block structure.
		$attributes['tabStyle'] = $settings['tab_style'] ?? 'horizontal';

		// Encode attributes for the block.
		$attributes_json = wp_json_encode( $attributes );

		// Generate the rendered HTML content.
		$rendered_html = $this->generate_tabs_html( $attributes );

		// Generate the complete custom tabs block markup.
		$block_content  = '<!-- wp:progressus/tabs ' . $attributes_json . ' -->';
		$block_content .= $rendered_html;
		$block_content .= '<!-- /wp:progressus/tabs -->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}

	/**
	 * Generate the rendered HTML content for the tabs block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML content.
	 */
	private function generate_tabs_html( array $attributes ): string {
		$tabs       = $attributes['tabs'] ?? array();
		$active_tab = $attributes['activeTab'] ?? 0;
		$tab_style  = $attributes['tabStyle'] ?? 'horizontal';

		// Extract styling attributes with defaults.
		$border_color  = $attributes['borderColor'] ?? '';
		$border_width  = $attributes['borderWidth'] ?? 1;
		$border_style  = $attributes['borderStyle'] ?? 'solid';
		$border_radius = $attributes['borderRadius'] ?? 4;

		// Padding and margin.
		$tabs_padding = $attributes['tabsPadding'] ?? array(
			'top'    => 12,
			'right'  => 16,
			'bottom' => 12,
			'left'   => 16,
		);
		$tabs_margin  = $attributes['tabsMargin'] ?? array(
			'top'    => 0,
			'right'  => 2,
			'bottom' => 0,
			'left'   => 0,
		);

		// Typography.
		$tab_typography     = $attributes['tabTypography'] ?? array(
			'fontSize'   => 16,
			'fontWeight' => 'normal',
			'lineHeight' => 1.5,
			'fontFamily' => '',
		);
		$content_typography = $attributes['contentTypography'] ?? array(
			'fontSize'   => 14,
			'fontWeight' => 'normal',
			'lineHeight' => 1.6,
			'fontFamily' => '',
		);

		// Main container styles.
		$tabs_style = sprintf(
			'display:%s;flex-direction:%s',
			'vertical' === $tab_style ? 'flex' : 'block',
			'vertical' === $tab_style ? 'row' : 'column'
		);

		// Background and text colors (emit even if empty string).
		$content_style_parts[] = 'background-color:' . ( $attributes['contentBackgroundColor'] ?? '' );
		$content_style_parts[] = 'color:' . ( $attributes['contentTextColor'] ?? '' );

		// Layout properties - always include.
		$content_style_parts[] = sprintf(
			'padding:%dpx %dpx %dpx %dpx',
			$tabs_padding['top'],
			$tabs_padding['right'],
			$tabs_padding['bottom'],
			$tabs_padding['left']
		);
		$content_style_parts[] = sprintf(
			'margin:%dpx %dpx %dpx %dpx',
			$tabs_margin['top'],
			$tabs_margin['right'],
			$tabs_margin['bottom'],
			$tabs_margin['left']
		);
		$content_style_parts[] = sprintf(
			'border:%dpx %s %s',
			$border_width,
			$border_style,
			$border_color ? $border_color : '#ddddddff'
		);
		$content_style_parts[] = sprintf( 'border-radius:%dpx', $border_radius );

		// Border top.
		if ( 'horizontal' === $tab_style ) {
			$content_style_parts[] = 'border-top:none';
		} else {
			$content_style_parts[] = sprintf( 'border-top:%dpx %s %s', $border_width, $border_style, $border_color ? $border_color : '#ddd' );
		}

		// Typography.
		$content_style_parts[] = sprintf( 'font-size:%dpx', $content_typography['fontSize'] );
		$content_style_parts[] = 'font-weight:' . $content_typography['fontWeight'];
		$content_style_parts[] = 'line-height:' . $content_typography['lineHeight'];
		if ( ! empty( $content_typography['fontFamily'] ) ) {
			$content_style_parts[] = 'font-family:' . $content_typography['fontFamily'];
		}

		// Finalize content style string.
		$content_style = implode( ';', $content_style_parts );

		$html  = '<div class="wp-block-progressus-tabs">';
		$html .= sprintf(
			'<div class="progressus-tabs" style="%s" data-tab-style="%s" data-active-tab="%d">',
			$tabs_style,
			$tab_style,
			$active_tab
		);

		// Tab headers.
		$html .= sprintf(
			'<div class="progressus-tabs-headers" style="display:flex;flex-direction:%s">',
			'vertical' === $tab_style ? 'column' : 'row'
		);

		foreach ( $tabs as $index => $tab ) {
			$is_active = $index === $active_tab;

			$style_parts = array();

			// 1. Background color (may be empty string).
			$bg            = $is_active ? ( $attributes['activeTabColor'] ?? '' ) : ( $attributes['tabColor'] ?? '' );
			$style_parts[] = 'background-color:' . $bg;

			// 2. Border color (emit even if empty).
			$style_parts[] = 'border-color:' . ( $attributes['borderColor'] ?? '' );

			// 3. Text color (may fall back to tabTextColor).
			$txt           = $is_active ? ( $attributes['activeTabTextColor'] ?? ( $attributes['tabTextColor'] ?? '' ) ) : ( $attributes['tabTextColor'] ?? '' );
			$style_parts[] = 'color:' . $txt;

			// 4. Common styles in exact order.
			$style_parts[] = sprintf(
				'padding:%dpx %dpx %dpx %dpx',
				$tabs_padding['top'],
				$tabs_padding['right'],
				$tabs_padding['bottom'],
				$tabs_padding['left']
			);
			$style_parts[] = sprintf(
				'margin:%dpx %dpx %dpx %dpx',
				$tabs_margin['top'],
				$tabs_margin['right'],
				$tabs_margin['bottom'],
				$tabs_margin['left']
			);
			$style_parts[] = 'cursor:pointer';
			$style_parts[] = sprintf(
				'border:%dpx %s %s',
				$border_width,
				$border_style,
				$border_color ? $border_color : '#ddddddff'
			);
			$style_parts[] = sprintf( 'border-radius:%dpx', $border_radius );

			// 5. Typography.
			$style_parts[] = sprintf( 'font-size:%dpx', $tab_typography['fontSize'] );
			$style_parts[] = 'font-weight:' . $tab_typography['fontWeight'];
			$style_parts[] = 'line-height:' . $tab_typography['lineHeight'];
			if ( ! empty( $tab_typography['fontFamily'] ) ) {
				$style_parts[] = 'font-family:' . $tab_typography['fontFamily'];
			}

			$header_style = implode( ';', $style_parts );

			$html .= sprintf(
				'<div class="progressus-tab-header %s" style="%s" data-tab-index="%d">%s</div>',
				$is_active ? 'active' : '',
				$header_style,
				$index,
				$tab['title']
			);
		}

		$html .= '</div>'; // Close headers.

		// Tab contents.
		$html .= sprintf( '<div class="progressus-tabs-content" style="%s">', $content_style );

		foreach ( $tabs as $index => $tab ) {
			$is_active = $index === $active_tab;
			// Remove HTML tags from tab content to render plain text.
			$content_plain = wp_strip_all_tags( $tab['content'] );
			$html         .= sprintf(
				'<div class="progressus-tab-content %s" style="display:%s">%s</div>',
				$is_active ? 'active' : '',
				$is_active ? 'block' : 'none',
				$content_plain
			);
		}

		$html .= '</div>'; // Close content.
		$html .= '</div>'; // Close progressus-tabs.
		$html .= '</div>'; // Close wp-block-progressus-tabs.

		return $html;
	}
}