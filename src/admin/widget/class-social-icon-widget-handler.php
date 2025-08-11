<?php
/**
 * Widget handler for Elementor social icons widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor social icons widget.
 */
class Social_Icons_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor social icons to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$social_icons  = $settings['social_icon_list'] ?? array();
		$block_content = '';

		if ( ! empty( $social_icons ) ) {
			$icons_content = '';

			// Generate social links
			foreach ( $social_icons as $icon ) {
				$url     = $icon['link']['url'] ?? '';
				$service = '';

				// Match common services from icon value
				if ( ! empty( $icon['social_icon']['value'] ) ) {
					$icon_value = strtolower( $icon['social_icon']['value'] );
					if ( strpos( $icon_value, 'facebook' ) !== false ) {
						$service = 'facebook';
					} elseif ( strpos( $icon_value, 'twitter' ) !== false || strpos( $icon_value, 'xcorp' ) !== false ) {
						$service = 'twitter';
					} elseif ( strpos( $icon_value, 'youtube' ) !== false ) {
						$service = 'youtube';
					} elseif ( strpos( $icon_value, 'linkedin' ) !== false ) {
						$service = 'linkedin';
					}
				}

				if ( $url && $service ) {
					$icons_content .= sprintf(
						"<!-- wp:social-link {\"url\":\"%s\",\"service\":\"%s\"} /-->\n",
						esc_url( $url ),
						esc_attr( $service )
					);
				}
			}

			// Extract styles
			$style_json = array(
				'layout' => array(
					'type' => 'flex',
				),
				'style' => array(),
			);

			if ( isset( $settings['_flex_align_self'] ) ) {
				$style_json['layout']['justifyContent'] = $settings['_flex_align_self'];
			}

			if ( isset( $settings['icon_size']['size'] ) ) {
				$style_json['style']['elements']['link'] = array(
					'size' => $settings['icon_size']['size'] . 'px',
				);
			}

			if ( isset( $settings['icon_spacing']['size'] ) ) {
				$style_json['style']['spacing']['blockGap'] = $settings['icon_spacing']['size'] . 'px';
			}

			if ( isset( $settings['icon_padding']['size'] ) ) {
				$style_json['style']['spacing']['padding'] = array(
					'top' => $settings['icon_padding']['size'] . 'px',
					'right' => $settings['icon_padding']['size'] . 'px',
					'bottom' => $settings['icon_padding']['size'] . 'px',
					'left' => $settings['icon_padding']['size'] . 'px',
				);
			}

			if ( isset( $settings['border_radius'] ) ) {
				$r = $settings['border_radius'];
				$style_json['style']['border']['radius'] = is_array( $r ) ? array(
					'top' => $r['top'] . 'px',
					'right' => $r['right'] . 'px',
					'bottom' => $r['bottom'] . 'px',
					'left' => $r['left'] . 'px',
				) : $r . 'px';
			}

			if ( isset( $settings['image_border_width'] ) ) {
				$w = $settings['image_border_width'];
				$style_json['style']['border']['width'] = is_array( $w ) ? array(
					'top' => $w['top'] . 'px',
					'right' => $w['right'] . 'px',
					'bottom' => $w['bottom'] . 'px',
					'left' => $w['left'] . 'px',
				) : $w . 'px';
			}

			if ( isset( $settings['image_border_border'] ) ) {
				$style_json['style']['border']['style'] = $settings['image_border_border'];
			}

			if ( isset( $settings['_padding'] ) ) {
				$p = $settings['_padding'];
				$style_json['style']['spacing']['padding'] = array(
					'top' => $p['top'] . 'px',
					'right' => $p['right'] . 'px',
					'bottom' => $p['bottom'] . 'px',
					'left' => $p['left'] . 'px',
				);
			}

			if ( isset( $settings['_margin'] ) ) {
				$m = $settings['_margin'];
				$style_json['style']['spacing']['margin'] = array(
					'top' => $m['top'] . 'px',
					'right' => $m['right'] . 'px',
					'bottom' => $m['bottom'] . 'px',
					'left' => $m['left'] . 'px',
				);
			}

			if ( isset( $settings['icon_color'] ) ) {
				$style_json['style']['elements']['link']['color'] = $settings['icon_color'];
			}

			if ( isset( $settings['icon_color_value'] ) ) {
				$style_json['style']['color'] = array(
					'text' => $settings['icon_color_value'],
				);
			}

			$attrs = wp_json_encode( $style_json );

			$block_content .= sprintf(
				"<!-- wp:group %s --><div class=\"wp-block-group\"><!-- wp:social-links -->\n<ul class=\"wp-block-social-links\">\n%s</ul>\n<!-- /wp:social-links -->\n</div><!-- /wp:group -->\n",
				$attrs,
				$icons_content
			);
		}

		return $block_content;
	}
}