<?php
/**
 * Helper for building block markup with wrapper elements.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function esc_attr;
use function wp_kses_post;
use Progressus\Gutenberg\Admin\Helper\Block_Output_Builder;
use Progressus\Gutenberg\Admin\Helper\Html_Attribute_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Normalizer;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for building block markup with wrapper elements.
 */
class Block_Builder {

	/**
	 * Bootstraps the hardening pipeline.
	 *
	 * @param External_Style_Collector|null $collector Style collector instance.
	 *
	 * @return void
	 */
	public static function bootstrap( ?External_Style_Collector $collector ): void {
		Block_Output_Builder::bootstrap( $collector );
	}

	/**
	 * Blocks that require wrapper div output.
	 *
	 * @var array<string>
	 */
	private static $wrapper_blocks = array( 'group', 'columns', 'column', 'buttons', 'button' );

	/**
	 * Core blocks that should prefer strict serialization via serialize_block().
	 *
	 * @var array<string>
	 */
	private static $strict_blocks = array( 'image' );

	/**
	 * Blocks that should bypass the hardening pipeline (prepare/sanitize).
	 *
	 * These blocks often require their inner markup and attributes to remain
	 * in the exact shape Gutenberg expects (e.g. core/embed figure  wrapper URL).
	 *
	 * @var array<string>
	 */
	private static $raw_blocks = array( 'embed' );

	/**
	 * Decide whether to bypass hardening for a given block.
	 *
	 * @param string $block_slug Block slug (e.g. embed, group).
	 * @param array  $options    Build options.
	 *
	 * @return bool True when hardening must be bypassed.
	 */
	private static function should_bypass_hardening( string $block_slug, array $options ): bool {
		if ( array_key_exists( 'raw', $options ) ) {
			return true === $options['raw'];
		}

		return in_array( $block_slug, self::$raw_blocks, true );
	}

	/**
	 * Build the serialized markup for a block including wrapper markup when required.
	 *
	 * @param string $block Block name without `core/` prefix (e.g. `group`).
	 * @param array $attrs Attributes array to encode in block comment.
	 * @param string $inner_html Inner HTML for the block.
	 *
	 * @return string
	 */
	public static function build( string $block, array $attrs, string $inner_html, array $options = array() ): string {
		if ( 'html' === $block ) {
			$attrs = array();
		}

		$block_slug = self::get_block_slug( $block );

		$bypass     = self::should_bypass_hardening( $block_slug, $options );

		if ( ! $bypass ) {
			$attrs      = Block_Output_Builder::prepare_attributes( $block_slug, self::normalize_attributes( $attrs ) );
			$inner_html = Block_Output_Builder::sanitize_inner_html( $block_slug, $inner_html );
		} else {
			$attrs                 = self::normalize_attributes( $attrs );
			$inner_html            = wp_kses_post( (string) $inner_html );
			$options['strict']     = false;
		}

		if ( 'image' === $block_slug ) {
			$normalized = self::normalize_core_image( $attrs, $inner_html );
			$attrs      = $normalized['attrs'];
			$inner_html = $normalized['inner_html'];
		}

		if ( 'button' === $block && '' === trim( $inner_html ) ) {
			$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

			return sprintf( '<!-- wp:%s%s /-->%s', $block, $attr_json, "\n" );
		}

		$is_wrapper = in_array( $block_slug, self::$wrapper_blocks, true );

		if ( self::should_use_strict_serialization( $block_slug, $options ) && ! $is_wrapper ) {
			return self::build_strict_serialized( $block, $attrs, $inner_html );
		}

		$attr_json    = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );
		$opening      = sprintf( '<!-- wp:%s%s -->', $block, $attr_json );
		$closing      = sprintf( '<!-- /wp:%s -->', $block );
		$wrapper_html = $inner_html;

		if ( $is_wrapper ) {
			$wrapper_class     = self::build_wrapper_class( $block_slug, $attrs );
			$style_attr        = self::build_style_attribute( $attrs, $block_slug );
			$attrs_for_wrapper = array(
				'class' => $wrapper_class,
			);

			if ( '' !== $style_attr ) {
				$attrs_for_wrapper['style'] = trim( $style_attr );
			}

			$wrapper_html = sprintf(
				'<div %s>%s</div>',
				Html_Attribute_Builder::build( $attrs_for_wrapper ),
				$inner_html
			);
		}

		$wrapper_html = rtrim( (string) $wrapper_html, "\n" );

		return $opening . "\n" . $wrapper_html . "\n" . $closing . "\n";
	}

	/**
	 * Normalize core/image payload to match Gutenberg serialization expectations.
	 *
	 * @param array $attrs Block attrs.
	 * @param string $inner_html Inner HTML.
	 *
	 * @return array{attrs:array,inner_html:string}
	 */
	private static function normalize_core_image( array $attrs, string $inner_html ): array {
		$align = isset( $attrs['align'] ) ? strtolower( trim( (string) $attrs['align'] ) ) : '';
		if ( '' !== $align && ! in_array( $align, array( 'left', 'right', 'center', 'wide', 'full' ), true ) ) {
			$align = '';
			unset( $attrs['align'] );
		}

		if ( isset( $attrs['className'] ) && is_string( $attrs['className'] ) && '' !== $attrs['className'] ) {
			$attrs['className'] = preg_replace( '/\bwp-block-image\b/', '', $attrs['className'] );
			$attrs['className'] = preg_replace( '/\bsize-[^\s"]+\b/', '', $attrs['className'] );
			$attrs['className'] = preg_replace( '/\balign(left|right|center|wide|full)\b/', '', $attrs['className'] );
			$attrs['className'] = preg_replace( '/\s+/', ' ', trim( $attrs['className'] ) );
			if ( '' === $attrs['className'] ) {
				unset( $attrs['className'] );
			}
		}

		$size_slug = isset( $attrs['sizeSlug'] ) ? trim( (string) $attrs['sizeSlug'] ) : '';
		if ( '' === $size_slug ) {
			$size_slug         = 'full';
			$attrs['sizeSlug'] = 'full';
		}

		$is_resized = false;
		if ( isset( $attrs['width'] ) ) {
			$width = trim( (string) $attrs['width'] );
			if ( '' !== $width && '100%' !== $width ) {
				$is_resized = true;
			}
		}

		$custom_classes = array();
		if ( isset( $attrs['className'] ) && is_string( $attrs['className'] ) && '' !== $attrs['className'] ) {
			$custom_classes = preg_split( '/\s+/', trim( $attrs['className'] ) );
			if ( ! is_array( $custom_classes ) ) {
				$custom_classes = array();
			}
		}

		$figure_classes = array( 'wp-block-image' );
		if ( '' !== $align ) {
			$figure_classes[] = 'align' . $align;
		}
		$figure_classes[] = 'size-' . $size_slug;
		if ( $is_resized ) {
			$figure_classes[] = 'is-resized';
		}
		if ( ! empty( $custom_classes ) ) {
			$figure_classes = array_merge( $figure_classes, $custom_classes );
		}

		$figure_classes = array_values( array_unique( array_filter( $figure_classes ) ) );

		$has_id    = isset( $attrs['id'] ) && is_numeric( $attrs['id'] ) && (int) $attrs['id'] > 0;
		$img_class = $has_id ? ( 'wp-image-' . (string) (int) $attrs['id'] ) : '';

		$inner_html = preg_replace_callback(
			'/<img\b[^>]*\/?>/i',
			function ( $m ) use ( $img_class ) {
				$tag   = (string) $m[0];
				$src   = '';
				$alt   = '';
				$width = '';

				if ( preg_match( '/\ssrc="([^"]*)"/i', $tag, $mm ) ) {
					$src = (string) $mm[1];
				}
				if ( preg_match( '/\salt="([^"]*)"/i', $tag, $mm ) ) {
					$alt = (string) $mm[1];
				}
				if ( preg_match( '/\swidth="([^"]*)"/i', $tag, $mm ) ) {
					$width = (string) $mm[1];
				}

				$parts   = array();
				$parts[] = 'src="' . esc_url( $src ) . '"';
				$parts[] = 'alt="' . esc_attr( $alt ) . '"';
				if ( '' !== $img_class ) {
					$parts[] = 'class="' . esc_attr( $img_class ) . '"';
				}
				if ( '' !== $width ) {
					$parts[] = 'width="' . esc_attr( $width ) . '"';
				}

				return '<img ' . implode( ' ', $parts ) . '/>';
			},
			$inner_html,
			1
		);

		if ( false === strpos( $inner_html, '<figure' ) ) {
			return array(
				'attrs'      => $attrs,
				'inner_html' => $inner_html,
			);
		}

		$class_attr = ' class="' . esc_attr( implode( ' ', $figure_classes ) ) . '"';

		$inner_html = preg_replace_callback(
			'/<figure\b([^>]*)>/',
			function ( $m ) use ( $class_attr ) {
				$attrs_raw = (string) $m[1];
				$attrs_raw = preg_replace( '/(?:^|\s)class=("|\')[^"\']*\1/', '', $attrs_raw );
				$attrs_raw = trim( (string) $attrs_raw );
				if ( '' !== $attrs_raw ) {
					$attrs_raw = ' ' . $attrs_raw;
				} else {
					$attrs_raw = '';
				}

				return '<figure' . $attrs_raw . $class_attr . '>';
			},
			$inner_html,
			1
		);

		return array(
			'attrs'      => $attrs,
			'inner_html' => $inner_html,
		);
	}

	/**
	 * Decide whether to use strict serialization for a given block slug.
	 *
	 * @param string $block_slug Block slug (e.g. image, group).
	 * @param array $options Options.
	 *
	 * @return bool
	 */
	private static function should_use_strict_serialization( string $block_slug, array $options ): bool {
		$force = $options['strict'] ?? null;
		if ( true === $force ) {
			return true;
		}
		if ( false === $force ) {
			return false;
		}

		return in_array( $block_slug, self::$strict_blocks, true );
	}

	/**
	 * Build markup using WordPress core serialize_block() (strict serialization).
	 *
	 * @param string $block Block name as passed to build() (often without namespace).
	 * @param array $attrs Comment attributes (already prepared).
	 * @param string $inner_html Sanitized inner HTML.
	 *
	 * @return string
	 */
	private static function build_strict_serialized( string $block, array $attrs, string $inner_html ): string {
		$full_name = self::to_full_block_name( $block );

		$parsed = array(
			'blockName'    => $full_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);

		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block( $parsed ) . "\n";
		}

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return sprintf(
			"<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->\n",
			$block,
			$attr_json,
			$inner_html,
			$block
		);
	}

	/**
	 * Build a block where the inner HTML is generated after attributes have been prepared.
	 *
	 * This is useful when the inner markup must match the final prepared attributes exactly
	 *
	 * @param string $block Block name without `core/` prefix (e.g. `heading`).
	 * @param array $attrs Raw block attributes (will be normalized and prepared).
	 * @param callable $inner_builder Callback that receives prepared attributes and returns inner HTML.
	 *                               Signature: function( array $prepared_attrs ): string
	 * @param array $options Optional build options.
	 *                          - strict (bool|null): Force enable/disable strict serialization. Null uses defaults.
	 *
	 * @return string Serialized block markup (including trailing newline).
	 */
	public static function build_prepared( string $block, array $attrs, callable $inner_builder, array $options = array() ): string {
		if ( 'html' === $block ) {
			$attrs = array();
		}

		$block_slug = self::get_block_slug( $block );

		$bypass     = self::should_bypass_hardening( $block_slug, $options );

		if ( ! $bypass ) {
			$attrs      = Block_Output_Builder::prepare_attributes( $block_slug, self::normalize_attributes( $attrs ) );
			$inner_html = (string) call_user_func( $inner_builder, $attrs );
			$inner_html = Block_Output_Builder::sanitize_inner_html( $block_slug, $inner_html );
		} else {
			$attrs                 = self::normalize_attributes( $attrs );
			$inner_html            = (string) call_user_func( $inner_builder, $attrs );
			$inner_html            = wp_kses_post( (string) $inner_html );
			$options['strict']     = false;
		}

		if ( 'button' === $block && '' === trim( $inner_html ) ) {
			$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

			return sprintf( '<!-- wp:%s%s /-->%s', $block, $attr_json, "\n" );
		}

		$is_wrapper = in_array( $block_slug, self::$wrapper_blocks, true );

		$attr_json    = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );
		$opening      = sprintf( '<!-- wp:%s%s -->', $block, $attr_json );
		$closing      = sprintf( '<!-- /wp:%s -->', $block );
		$wrapper_html = $inner_html;

		if ( $is_wrapper ) {
			$wrapper_class     = self::build_wrapper_class( $block_slug, $attrs );
			$style_attr        = self::build_style_attribute( $attrs, $block_slug );
			$attrs_for_wrapper = array(
				'class' => $wrapper_class,
			);

			if ( '' !== $style_attr ) {
				$attrs_for_wrapper['style'] = trim( $style_attr );
			}

			$wrapper_html = sprintf(
				'<div %s>%s</div>',
				Html_Attribute_Builder::build( $attrs_for_wrapper ),
				$inner_html
			);
		}

		$wrapper_html = rtrim( (string) $wrapper_html, "\n" );

		return $opening . "\n" . $wrapper_html . "\n" . $closing . "\n";
	}

	/**
	 * Normalize a block name to a full namespaced form for serialize_block().
	 *
	 * @param string $block Block name.
	 *
	 * @return string
	 */
	private static function to_full_block_name( string $block ): string {
		if ( false !== strpos( $block, '/' ) ) {
			return $block;
		}

		return 'core/' . $block;
	}

	/**
	 * Build the wrapper class attribute from block attributes.
	 *
	 * @param string $block Block name.
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function build_wrapper_class( string $block_slug, array $attrs ): string {
		$classes   = array( 'wp-block-' . $block_slug );
		$align     = isset( $attrs['align'] ) ? trim( (string) $attrs['align'] ) : '';
		$class_raw = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';

		if ( '' !== $align ) {
			$clean_align = Style_Parser::clean_class( 'align' . $align );
			if ( '' !== $clean_align ) {
				$classes[] = $clean_align;
			}
		}

		if ( '' !== $class_raw ) {
			$class_parts = preg_split( '/\s+/', $class_raw );
			if ( is_array( $class_parts ) ) {
				foreach ( $class_parts as $class_part ) {
					$sanitized = Style_Parser::clean_class( $class_part );
					if ( '' !== $sanitized ) {
						$classes[] = $sanitized;
					}
				}
			}
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Build inline style attribute for wrapper blocks.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	public static function build_style_attribute( array $attrs, string $block_slug = '' ): string {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return '';
		}

		$style       = $attrs['style'];
		$style_rules = array();

		// Keep margin only (matches Gutenberg serialization).
		if ( 'button' === $block_slug ) {
			if ( isset( $style['spacing']['margin'] ) && is_array( $style['spacing']['margin'] ) ) {
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( ! array_key_exists( $side, $style['spacing']['margin'] ) ) {
						continue;
					}
					$val = $style['spacing']['margin'][ $side ];
					if ( null === $val || '' === (string) $val ) {
						continue;
					}
					$style_rules[] = 'margin-' . $side . ':' . self::normalize_style_value( $val );
				}
			}

			if ( empty( $style_rules ) ) {
				return '';
			}

			return esc_attr( implode( ';', $style_rules ) );
		}

		if ( isset( $style['spacing'] ) && is_array( $style['spacing'] ) && array_key_exists( 'blockGap', $style['spacing'] ) ) {
			$gap = $style['spacing']['blockGap'];
			if ( null !== $gap && '' !== (string) $gap ) {
				$style_rules[] = 'gap:' . self::normalize_style_value( $gap );
			}
		}


		foreach ( array( 'margin', 'padding' ) as $type ) {
			if ( empty( $style['spacing'][ $type ] ) || ! is_array( $style['spacing'][ $type ] ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! array_key_exists( $side, $style['spacing'][ $type ] ) ) {
					continue;
				}

				$val = $style['spacing'][ $type ][ $side ];

				if ( null === $val || '' === (string) $val ) {
					continue;
				}

				$style_rules[] = sprintf(
					'%s-%s:%s',
					$type,
					$side,
					self::normalize_style_value( $val )
				);
			}
		}

		if ( ! empty( $style['typography'] ) && is_array( $style['typography'] ) ) {
			foreach ( $style['typography'] as $property => $value ) {
				if ( null === $value || '' === (string) $value ) {
					continue;
				}
				$style_rules[] = sprintf(
					'%s:%s',
					self::camel_to_kebab( $property ),
					self::normalize_style_value( $value )
				);
			}
		}

		if ( isset( $style['color']['background'] ) && null !== $style['color']['background'] && '' !== (string) $style['color']['background'] ) {
			$style_rules[] = 'background-color:' . self::normalize_style_value( $style['color']['background'] );
		}


		if ( ! empty( $style['background'] ) && is_array( $style['background'] ) ) {
			if ( ! empty( $style['background']['image'] ) ) {
				$style_rules[] = 'background-image:url(' . self::normalize_style_value( $style['background']['image'] ) . ')';
			}

			if ( isset( $style['background']['position'] ) ) {
				$style_rules[] = 'background-position:' . self::normalize_style_value( $style['background']['position'] );
			}

			if ( isset( $style['background']['size'] ) ) {
				$style_rules[] = 'background-size:' . self::normalize_style_value( $style['background']['size'] );
			}

			if ( isset( $style['background']['repeat'] ) ) {
				$style_rules[] = 'background-repeat:' . self::normalize_style_value( $style['background']['repeat'] );
			}
		}

		if ( isset( $style['dimensions']['minHeight'] ) ) {
			$style_rules[] = 'min-height:' . self::normalize_style_value( $style['dimensions']['minHeight'] );
		}
		if ( isset( $style['boxShadow'] ) && '' !== trim( (string) $style['boxShadow'] ) ) {
			$style_rules[] = 'box-shadow:' . trim( (string) $style['boxShadow'] );
		}
		if ( ! empty( $style['border'] ) && is_array( $style['border'] ) ) {
			$style_rules = array_merge( $style_rules, self::build_border_rules( $style['border'] ) );
		}

		if ( empty( $style_rules ) ) {
			return '';
		}

		return esc_attr( implode( ';', $style_rules ) );
	}

	/**
	 * Build anchor class attribute for core/button inner <a>.
	 *
	 * @param array $attrs Prepared block attrs.
	 *
	 * @return string
	 */
	public static function build_button_link_class( array $attrs ): string {
		$classes = array( 'wp-block-button__link' );

		$has_text_color = false;
		if ( ! empty( $attrs['textColor'] ) ) {
			$slug = Style_Parser::clean_class( (string) $attrs['textColor'] );
			if ( '' !== $slug ) {
				$classes[]      = 'has-text-color';
				$classes[]      = 'has-' . $slug . '-color';
				$has_text_color = true;
			}
		} elseif ( ! empty( $attrs['style']['color']['text'] ) ) {
			$classes[]      = 'has-text-color';
			$has_text_color = true;
		}

		if ( ! empty( $attrs['backgroundColor'] ) ) {
			$slug = Style_Parser::clean_class( (string) $attrs['backgroundColor'] );
			if ( '' !== $slug ) {
				$classes[] = 'has-background';
				$classes[] = 'has-' . $slug . '-background-color';
			}
		} elseif ( ! empty( $attrs['style']['color']['background'] ) ) {
			$classes[] = 'has-background';
		}

		if ( ! empty( $attrs['fontSize'] ) ) {
			$slug = Style_Parser::clean_class( (string) $attrs['fontSize'] );
			if ( '' !== $slug ) {
				$classes[] = 'has-' . $slug . '-font-size';
			}
		} elseif ( ! empty( $attrs['style']['typography']['fontSize'] ) ) {
			$classes[] = 'has-custom-font-size';
		}

		$classes[] = 'wp-element-button';

		return implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) );
	}

	/**
	 * Build anchor inline style attribute for core/button inner <a>.
	 *
	 * Order is intentional to better match Gutenberg serialization.
	 *
	 * @param array $attrs Prepared block attrs.
	 *
	 * @return string
	 */
	public static function build_button_link_style( array $attrs ): string {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return '';
		}

		$style = $attrs['style'];
		$rules = array();

		if ( isset( $style['color']['background'] ) && null !== $style['color']['background'] && '' !== (string) $style['color']['background'] ) {
			$rules[] = 'background-color:' . self::normalize_style_value( $style['color']['background'] );
		}

		if ( isset( $style['color']['text'] ) && null !== $style['color']['text'] && '' !== (string) $style['color']['text'] ) {
			$rules[] = 'color:' . self::normalize_style_value( $style['color']['text'] );
		}

		$typo = isset( $style['typography'] ) && is_array( $style['typography'] ) ? $style['typography'] : array();

		if ( isset( $typo['fontSize'] ) && '' !== (string) $typo['fontSize'] ) {
			$rules[] = 'font-size:' . self::normalize_style_value( $typo['fontSize'] );
		}
		if ( isset( $typo['fontStyle'] ) && '' !== (string) $typo['fontStyle'] ) {
			$rules[] = 'font-style:' . self::normalize_style_value( $typo['fontStyle'] );
		}
		if ( isset( $typo['fontWeight'] ) && '' !== (string) $typo['fontWeight'] ) {
			$rules[] = 'font-weight:' . self::normalize_style_value( $typo['fontWeight'] );
		}
		if ( isset( $typo['lineHeight'] ) && '' !== (string) $typo['lineHeight'] ) {
			$rules[] = 'line-height:' . self::normalize_style_value( $typo['lineHeight'] );
		}
		if ( isset( $typo['textTransform'] ) && '' !== (string) $typo['textTransform'] ) {
			$rules[] = 'text-transform:' . self::normalize_style_value( $typo['textTransform'] );
		}
		if ( isset( $typo['textDecoration'] ) && '' !== (string) $typo['textDecoration'] ) {
			$rules[] = 'text-decoration:' . self::normalize_style_value( $typo['textDecoration'] );
		}
		if ( isset( $typo['letterSpacing'] ) && '' !== (string) $typo['letterSpacing'] ) {
			$rules[] = 'letter-spacing:' . self::normalize_style_value( $typo['letterSpacing'] );
		}
		if ( isset( $typo['wordSpacing'] ) && '' !== (string) $typo['wordSpacing'] ) {
			$rules[] = 'word-spacing:' . self::normalize_style_value( $typo['wordSpacing'] );
		}

		if ( isset( $style['spacing']['padding'] ) && is_array( $style['spacing']['padding'] ) ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! array_key_exists( $side, $style['spacing']['padding'] ) ) {
					continue;
				}
				$val = $style['spacing']['padding'][ $side ];
				if ( null === $val || '' === (string) $val ) {
					continue;
				}
				$rules[] = 'padding-' . $side . ':' . self::normalize_style_value( $val );
			}
		}

		if ( empty( $rules ) ) {
			return '';
		}

		return esc_attr( implode( ';', $rules ) );
	}

	/**
	 * Normalize style values for inline usage.
	 *
	 * @param mixed $value Raw value from attributes.
	 *
	 * @return string Normalized CSS value.
	 */
	private static function normalize_style_value( $value ): string {
		$value = (string) $value;
		if ( 0 === strpos( $value, 'var:' ) ) {
			$without_prefix = substr( $value, 4 );
			$parts          = explode( '|', $without_prefix );
			if ( ! empty( $parts ) ) {
				$value = 'var(--wp--' . implode( '--', array_map( 'sanitize_html_class', $parts ) ) . ')';
			}
		}

		return $value;
	}

	/**
	 * Convert camelCase properties to kebab-case.
	 *
	 * @param string $property Property name.
	 */
	private static function camel_to_kebab( string $property ): string {
		return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $property ) );
	}

	/**
	 * Normalise attributes by removing empty values recursively.
	 *
	 * @param array $attrs Raw attributes.
	 */
	private static function normalize_attributes( array $attrs ): array {
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$attrs[ $key ] = self::normalize_attributes( $value );
				if ( empty( $attrs[ $key ] ) ) {
					unset( $attrs[ $key ] );
				}
			} elseif ( null === $value || '' === $value ) {
				unset( $attrs[ $key ] );
			}
		}

		return $attrs;
	}

	/**
	 * Extract the slug part from a block name.
	 *
	 * @param string $block Block name (e.g. core/group).
	 */
	private static function get_block_slug( string $block ): string {
		if ( false === strpos( $block, '/' ) ) {
			return $block;
		}

		$parts = explode( '/', $block );

		return end( $parts ) ?: $block;
	}

	/**
	 * Build inline CSS rules for border data.
	 *
	 * @param array $border Border attribute data.
	 */
	private static function build_border_rules( array $border ): array {
		$rules = array();

		if ( ! empty( $border['style'] ) ) {
			$rules[] = 'border-style:' . self::normalize_style_value( $border['style'] );
		}

		if ( ! empty( $border['radius'] ) && is_array( $border['radius'] ) ) {
			$map = array(
				'topLeft'     => 'border-top-left-radius',
				'topRight'    => 'border-top-right-radius',
				'bottomRight' => 'border-bottom-right-radius',
				'bottomLeft'  => 'border-bottom-left-radius',
			);

			foreach ( $map as $key => $property ) {
				if ( empty( $border['radius'][ $key ] ) ) {
					continue;
				}

				$rules[] = sprintf( '%s:%s', $property, self::normalize_style_value( $border['radius'][ $key ] ) );
			}
		}

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( empty( $border[ $side ] ) || ! is_array( $border[ $side ] ) ) {
				continue;
			}

			if ( ! empty( $border[ $side ]['width'] ) ) {
				$rules[] = sprintf( 'border-%s-width:%s', $side, self::normalize_style_value( $border[ $side ]['width'] ) );
			}

			if ( ! empty( $border[ $side ]['color'] ) ) {
				$rules[] = sprintf( 'border-%s-color:%s', $side, self::normalize_style_value( $border[ $side ]['color'] ) );
			}
		}

		return $rules;
	}
}
