<?php
/**
 * Widget handler for Elementor divider widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Alignment_Helper;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;
use Progressus\BlockShift\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor divider widget.
 */
class Divider_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor divider to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

		$text_raw    = isset( $settings['text'] ) ? (string) $settings['text'] : '';
		$text_normal = strtolower( trim( $text_raw ) );

		if ( '' === $text_normal || 'divider' === $text_normal ) {
			$text = '';
		} else {
			$text = $text_raw;
		}

		$block_content = '';
		$inline_style  = '';
		$custom_class  = $settings['_css_classes'] ?? '';
		$custom_id     = $settings['_element_id'] ?? '';
		// Only plugin-generated rules are enqueued below; the widget's raw
		// user-authored Custom CSS is recorded for the user but never enqueued.
		Style_Parser::note_skipped_custom_css( (string) ( $settings['custom_css'] ?? '' ) );
		$custom_css    = '';
		$unique_class  = 'divider-' . uniqid();
		$custom_class .= ' ' . $unique_class;

		// Alignment → group wrapper.
		$group_attrs = array();
		$alignment   = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment' ) );
		if ( '' !== $alignment ) {
			$group_attrs['align'] = $alignment;
		}

		// Separator attributes.
		$separator_attrs = array(
			'className' => trim( $custom_class ),
		);

		$has_width = false;
		// Width.
		if ( isset( $settings['width'] ) && is_array( $settings['width'] ) && isset( $settings['width']['size'] ) ) {
			$size = trim( (string) $settings['width']['size'] );
			$unit = isset( $settings['width']['unit'] ) ? (string) $settings['width']['unit'] : '%';

			if ( '' !== $size && is_numeric( $size ) ) {
				if ( '' === $unit ) {
					$unit = '%';
				}

				$inline_style .= 'width:' . esc_attr( $size . $unit ) . ';';
				$has_width     = true;
			}
		}

		// Color.
		$color_sources = array();

		if ( isset( $settings['color'] ) ) {
			$color_sources[] = $settings['color'];
		}

		if (
			isset( $settings['__globals__'] ) &&
			is_array( $settings['__globals__'] ) &&
			isset( $settings['__globals__']['color'] )
		) {
			$color_sources[] = $settings['__globals__']['color'];
		}

		$resolved_color   = null;
		$has_custom_color = false;

		foreach ( $color_sources as $source ) {
			$data = Style_Parser::resolve_elementor_color_reference( $source );
			if ( '' !== $data['slug'] || '' !== $data['color'] ) {
				$resolved_color = $data;
				break;
			}
		}

		if ( null !== $resolved_color ) {
			if ( '' !== $resolved_color['slug'] ) {
				$separator_attrs['className'] = trim(
					$separator_attrs['className'] .
					' has-background has-' . Style_Parser::clean_class( $resolved_color['slug'] ) . '-background-color'
				);
			}

			if ( '' !== $resolved_color['color'] ) {
				$has_custom_color                                = true;
				$separator_attrs['style']['color']['background'] = $resolved_color['color'];
				$inline_style                                   .= 'background-color:' . esc_attr( $resolved_color['color'] ) . ';color:' . esc_attr( $resolved_color['color'] ) . ';';
			}
		}

		// Style (solid/dashed/dotted).
		if ( isset( $settings['style'] ) ) {
			$separator_attrs['className'] .= 'dotted' === esc_attr( $settings['style'] ) ? ' is-style-dots' : ' is-style-' . esc_attr( $settings['style'] );
		}

		// Margin / Spacing.
		$spacing = Style_Parser::parse_spacing( $settings );

		if ( ! empty( $spacing['attributes']['margin'] ) ) {
			$separator_attrs['style']['spacing']['margin'] = $spacing['attributes']['margin'];

			if ( ! empty( $spacing['style'] ) ) {
				$inline_style .= preg_replace( '/gap:[^;]+;?/', '', $spacing['style'] );
			}
		}

		if ( isset( $settings['gap'] ) && is_array( $settings['gap'] ) && ! empty( $settings['gap']['size'] ) ) {
			$size = trim( (string) $settings['gap']['size'] );
			$unit = ! empty( $settings['gap']['unit'] ) ? (string) $settings['gap']['unit'] : 'px';

			if ( is_numeric( $size ) ) {
				$value = $size . $unit;

				$separator_attrs['style']['spacing']['margin']['top']    = $value;
				$separator_attrs['style']['spacing']['margin']['bottom'] = $value;

				$inline_style .= 'margin-top:' . $value . ';margin-bottom:' . $value . ';';
			}
		}

		// Border thickness etc.
		$border = Style_Parser::parse_border( $settings );
		if ( ! empty( $border['style'] ) ) {
			$inline_style .= $border['style'];
		}

		// Build block content.
		if ( $text ) {
			$group_attrs['layout'] = array(
				'type'           => 'flex',
				'justifyContent' => 'center',
				'flexWrap'       => 'nowrap',
			);

			$block_content .= sprintf(
				'<!-- wp:group %s --><div class="wp-block-group">',
				$group_attrs ? wp_json_encode( $group_attrs ) : ''
			);

			// core/separator's save() prints has-text-color has-background whenever a
			// colour style is set, so the first separator has to carry them too or the
			// editor sees markup its own save() would not write and flags it invalid.
			if ( true === $has_custom_color ) {
				$separator_attrs['className'] = trim( 'has-text-color has-background ' . $separator_attrs['className'] );
			}

			$separator_attrs['className'] = self::normalize_separator_classes(
				$separator_attrs['className'] . ' has-alpha-channel-opacity'
			);

			// First separator.
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr class="wp-block-separator %s" style="%s"/><!-- /wp:separator -->' . "\n",
				$separator_attrs ? wp_json_encode( $separator_attrs ) : '',
				esc_attr( $separator_attrs['className'] ),
				esc_attr( self::build_separator_style( $separator_attrs ) )
			);

			// Text.
			$block_content .= sprintf(
				'<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->' . "\n",
				esc_html( $text )
			);

			// Second separator (with css opacity).
			$second_attrs   = array(
				'opacity'   => 'css',
				'className' => trim( $unique_class ),
			);
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr class="wp-block-separator has-css-opacity %s"/><!-- /wp:separator -->' . "\n",
				wp_json_encode( $second_attrs ),
				esc_attr( $unique_class )
			);

			$block_content .= '</div><!-- /wp:group -->' . "\n";
		} else {
			if ( false === strpos( $separator_attrs['className'], 'is-style-wide' ) ) {
				$separator_attrs['className'] .= ' is-style-wide';
			}

			// Gutenberg adds these classes when color style exists.
			if ( true === $has_custom_color ) {
				$separator_attrs['className'] = trim( 'has-text-color has-background ' . $separator_attrs['className'] );
			}

			/*
			 * A separator's `opacity` attribute defaults to `alpha-channel`, and
			 * its save() turns that into this class whether or not a colour was
			 * set. Adding it only alongside a colour left the rest looking like
			 * markup from an older version of the block, which the editor either
			 * migrates behind the user's back or rejects outright.
			 */
			$separator_attrs['className'] = self::normalize_separator_classes(
				$separator_attrs['className'] . ' has-alpha-channel-opacity'
			);

			$inline_style_attr = self::build_separator_style( $separator_attrs );
			$id_attr           = '' !== trim( (string) $custom_id ) ? ' id="' . esc_attr( $custom_id ) . '"' : '';

			$block_content .= sprintf(
				"<!-- wp:separator %s -->\n<hr%s class=\"wp-block-separator %s\" style=\"%s\"/>\n<!-- /wp:separator -->\n",
				$separator_attrs ? wp_json_encode( $separator_attrs ) : '',
				$id_attr,
				esc_attr( $separator_attrs['className'] ),
				esc_attr( $inline_style_attr )
			);
		}

		// Save inline CSS.
		if ( $inline_style ) {
			$element_selector = '.' . $unique_class;
			$custom_css      .= sprintf( '%s{ %s }', $element_selector, $inline_style );
		}
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_generated_css( $custom_css );
		}

		return $block_content;
	}

	/**
	 * Build the `style` attribute core/separator's save() would write.
	 *
	 * Everything else Elementor asks for - width, border thickness, padding -
	 * reaches the separator through the generated `divider-…` class instead.
	 * Printing it inline as well made the stored markup differ from save().
	 *
	 * @param array $separator_attrs Block attributes for the separator.
	 *
	 * @return string CSS declarations, without a trailing semicolon.
	 */
	private static function build_separator_style( array $separator_attrs ): string {
		$rules      = array();
		$background = $separator_attrs['style']['color']['background'] ?? '';

		if ( '' !== trim( (string) $background ) ) {
			$rules[] = 'background-color:' . $background;
			$rules[] = 'color:' . $background;
		}

		$margin = $separator_attrs['style']['spacing']['margin'] ?? array();
		if ( is_array( $margin ) ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! isset( $margin[ $side ] ) || '' === trim( (string) $margin[ $side ] ) ) {
					continue;
				}
				$rules[] = 'margin-' . $side . ':' . $margin[ $side ];
			}
		}

		return implode( ';', $rules );
	}

	/**
	 * Drop duplicate and empty class names from a separator's class list.
	 *
	 * @param string $classes Space-separated class names.
	 *
	 * @return string The cleaned list.
	 */
	private static function normalize_separator_classes( string $classes ): string {
		$parts = preg_split( '/\s+/', trim( $classes ) );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		return implode( ' ', array_values( array_unique( array_filter( $parts ) ) ) );
	}
}
