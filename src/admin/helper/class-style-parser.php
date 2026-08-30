<?php
/**
 * Utility class for parsing styles and attributes.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use Elementor\Plugin;
use function sanitize_html_class;
use function sanitize_hex_color;
use function wp_get_global_settings;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for parsing styles and attributes.
 */
class Style_Parser {

	public const BREAKPOINT_TABLET_MAX = 1024;
	public const BREAKPOINT_MOBILE_MAX = 767;

	/**
	 * Cached theme palette colors.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private static ?array $theme_palette = null;

	/**
	 * Cached theme font-size presets.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private static ?array $font_sizes = null;

	/**
	 * Cached theme font-family presets.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private static ?array $font_families = null;

	/**
	 * Cached Elementor kit settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $elementor_kit_settings = null;

	/**
	 * Cached Elementor global typography definitions.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $elementor_global_typography = null;

	/**
	 * Parse typography settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 *
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_typography( array $settings ): array {
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$settings    = self::apply_global_typography( $settings );
		$attributes  = array();
		$style_parts = array();
		$fields      = array(
			'typography_font_family'     => array(
				'attr' => 'fontFamily',
				'css'  => 'font-family',
			),
			'typography_text_transform'  => array(
				'attr' => 'textTransform',
				'css'  => 'text-transform',
			),
			'typography_font_style'      => array(
				'attr' => 'fontStyle',
				'css'  => 'font-style',
			),
			'typography_font_weight'     => array(
				'attr' => 'fontWeight',
				'css'  => 'font-weight',
			),
			'typography_text_decoration' => array(
				'attr' => 'textDecoration',
				'css'  => 'text-decoration',
			),
		);

		foreach ( $fields as $key => $map ) {
			$value = self::sanitize_scalar( $settings[ $key ] ?? null );
			if ( '' === $value ) {
				continue;
			}

			$attributes[ $map['attr'] ] = $value;
			$style_parts[]              = sprintf( '%s:%s;', $map['css'], $value );
		}

		$dimensions = array(
			'typography_font_size'      => array(
				'attr'         => 'fontSize',
				'css'          => 'font-size',
				'default_unit' => 'px',
			),
			'typography_line_height'    => array(
				'attr'         => 'lineHeight',
				'css'          => 'line-height',
				'default_unit' => '',
			),
			'typography_letter_spacing' => array(
				'attr'         => 'letterSpacing',
				'css'          => 'letter-spacing',
				'default_unit' => 'px',
			),
			'typography_word_spacing'   => array(
				'attr'         => 'wordSpacing',
				'css'          => 'word-spacing',
				'default_unit' => 'px',
			),
		);

		foreach ( $dimensions as $key => $map ) {
			$value = self::normalize_dimension( $settings[ $key ] ?? null, $map['default_unit'] );
			if ( null === $value ) {
				continue;
			}

			$attributes[ $map['attr'] ] = $value;
			$style_parts[]              = sprintf( '%s:%s;', $map['css'], $value );
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Build deterministic per-element class from Elementor element id.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return string
	 */
	public static function get_element_unique_class( array $element ): string {
		$id = '';

		if ( isset( $element['id'] ) ) {
			$id = self::clean_class( (string) $element['id'] );
		}

		if ( '' === $id && isset( $element['settings'] ) && is_array( $element['settings'] ) && isset( $element['settings']['_element_id'] ) ) {
			$id = self::clean_class( (string) $element['settings']['_element_id'] );
		}

		if ( '' === $id ) {
			return '';
		}

		return 'blockshift-el-' . $id;
	}

	/**
	 * Extract author-supplied CSS classes from a widget element.
	 *
	 * Widgets carry custom classes under `_css_classes` (e.g. `bw-title` on a
	 * heading widget, `bw-desc` on a text-editor widget, `part-logo` on an
	 * image widget). Some plugins use the unprefixed `css_classes` form too,
	 * so we read both for safety.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return string Sanitized space-separated class string, or empty.
	 */
	public static function get_element_author_classes( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] )
			? $element['settings']
			: array();

		$collected = array();

		foreach ( array( '_css_classes', 'css_classes' ) as $key ) {
			if ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) {
				continue;
			}

			$sanitized = self::sanitize_class_string( $settings[ $key ] );
			if ( '' === $sanitized ) {
				continue;
			}

			$collected[] = $sanitized;
		}

		return '' === implode( ' ', $collected ) ? '' : trim( implode( ' ', $collected ) );
	}

	/**
	 * Build a combined widget className string of "blockshift-el-{id} {author_classes}".
	 *
	 * Widget handlers that build their own className string can call this
	 * instead of `get_element_unique_class()` to also include the author's
	 * Elementor `_css_classes` / `css_classes` values.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return string Class string (may be empty).
	 */
	public static function get_element_widget_class_string( array $element ): string {
		$parts = array();

		$unique = self::get_element_unique_class( $element );
		if ( '' !== $unique ) {
			$parts[] = $unique;
		}

		$author = self::get_element_author_classes( $element );
		if ( '' !== $author ) {
			$parts[] = $author;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Extract typography declarations for base/tablet/mobile from Elementor settings.
	 *
	 * @param array $settings Elementor settings.
	 *
	 * @return array{base:array,tablet:array,mobile:array,font_family:string}
	 */
	public static function extract_typography_css_rules( array $settings ): array {
		$settings = self::apply_global_typography( $settings );

		$base   = array();
		$tablet = array();
		$mobile = array();

		$font_family = self::sanitize_font_family_value( $settings['typography_font_family'] ?? '' );
		if ( '' !== $font_family ) {
			$base['font-family'] = $font_family;
		}

		$font_weight = self::sanitize_font_weight_value( $settings['typography_font_weight'] ?? '' );
		if ( '' !== $font_weight ) {
			$base['font-weight'] = $font_weight;
		}

		$text_transform = self::sanitize_text_transform_value( $settings['typography_text_transform'] ?? '' );
		if ( '' !== $text_transform ) {
			$base['text-transform'] = $text_transform;
		}

		$font_style = self::sanitize_font_style_value( $settings['typography_font_style'] ?? '' );
		if ( '' !== $font_style ) {
			$base['font-style'] = $font_style;
		}

		$text_decoration = self::sanitize_text_decoration_value( $settings['typography_text_decoration'] ?? '' );
		if ( '' !== $text_decoration ) {
			$base['text-decoration'] = $text_decoration;
		}

		$letter_spacing = self::normalize_dimension( $settings['typography_letter_spacing'] ?? null, 'px' );
		if ( null !== $letter_spacing ) {
			$letter_spacing = self::sanitize_letter_spacing_value( $letter_spacing );
			if ( '' !== $letter_spacing && ! self::is_zero_dimension( $letter_spacing ) ) {
				$base['letter-spacing'] = $letter_spacing;
			}
		}

		$word_spacing = self::normalize_dimension( $settings['typography_word_spacing'] ?? null, 'px' );
		if ( null !== $word_spacing ) {
			$word_spacing = self::sanitize_word_spacing_value( $word_spacing );
			if ( '' !== $word_spacing && ! self::is_zero_dimension( $word_spacing ) ) {
				$base['word-spacing'] = $word_spacing;
			}
		}

		$font_size_desktop = self::normalize_dimension( $settings['typography_font_size'] ?? null, 'px' );
		$font_size_tablet  = self::normalize_dimension( $settings['typography_font_size_tablet'] ?? null, 'px' );
		$font_size_mobile  = self::normalize_dimension( $settings['typography_font_size_mobile'] ?? null, 'px' );

		$line_height_desktop = self::normalize_dimension( $settings['typography_line_height'] ?? null, '' );
		$line_height_tablet  = self::normalize_dimension( $settings['typography_line_height_tablet'] ?? null, '' );
		$line_height_mobile  = self::normalize_dimension( $settings['typography_line_height_mobile'] ?? null, '' );

		self::append_dimension_rule( $base, 'font-size', $font_size_desktop, 'sanitize_css_dimension_value' );
		self::append_dimension_rule( $tablet, 'font-size', $font_size_tablet, 'sanitize_css_dimension_value' );
		self::append_dimension_rule( $mobile, 'font-size', $font_size_mobile, 'sanitize_css_dimension_value' );

		self::append_dimension_rule( $base, 'line-height', $line_height_desktop, 'sanitize_line_height_value' );
		self::append_dimension_rule( $tablet, 'line-height', $line_height_tablet, 'sanitize_line_height_value' );
		self::append_dimension_rule( $mobile, 'line-height', $line_height_mobile, 'sanitize_line_height_value' );

		return array(
			'base'        => $base,
			'tablet'      => $tablet,
			'mobile'      => $mobile,
			'font_family' => $font_family,
		);
	}

	/**
	 * Extract safe text color for specific widget setting key.
	 *
	 * @param array  $settings Elementor settings.
	 * @param string $key Color setting key.
	 *
	 * @return array{color:string,safe:bool}
	 */
	public static function extract_text_color_css_value( array $settings, string $key ): array {
		$raw = isset( $settings[ $key ] ) ? self::sanitize_scalar( $settings[ $key ] ) : '';

		/*
		 * A widget that picks its colour from the site palette stores nothing under
		 * the control name and puts a "globals/colors?id=..." reference under
		 * `__globals__` instead. Reading only the literal key lost those colours -
		 * a hero heading set to the palette white came out in the theme dark.
		 */
		if ( '' === $raw && isset( $settings['__globals__'][ $key ] ) ) {
			$raw = self::sanitize_scalar( $settings['__globals__'][ $key ] );
		}

		if ( '' === $raw ) {
			return array(
				'color' => '',
				'safe'  => true,
			);
		}

		if ( preg_match( '/^var\(\s*--[a-z0-9\-_]+\s*\)$/i', $raw ) ) {
			return array(
				'color' => $raw,
				'safe'  => true,
			);
		}

		$resolved = self::resolve_elementor_color_reference( $raw );
		if ( '' !== $resolved['color'] ) {
			return array(
				'color' => $resolved['color'],
				'safe'  => true,
			);
		}

		$normalized = self::normalize_color_value( $raw );
		if ( '' !== $normalized ) {
			return array(
				'color' => $normalized,
				'safe'  => true,
			);
		}

		return array(
			'color' => '',
			'safe'  => false,
		);
	}

	private static function append_dimension_rule( array &$rules, string $property, ?string $value, string $sanitizer ): void {
		if ( null === $value ) {
			return;
		}

		$clean = '';
		if ( 'sanitize_line_height_value' === $sanitizer ) {
			$clean = self::sanitize_line_height_value( $value );
		} else {
			$clean = self::sanitize_css_dimension_value( $value );
		}

		if ( '' !== $clean ) {
			$rules[ $property ] = $clean;
		}
	}

	private static function is_zero_dimension( string $value ): bool {
		$normalized = trim( strtolower( $value ) );
		$normalized = preg_replace( '/\s+/', '', $normalized );
		if ( null === $normalized ) {
			return false;
		}

		return in_array( $normalized, array( '0', '0px', '0em', '0rem', '0%' ), true );
	}

	/**
	 * Retrieve Elementor kit typography settings for a context (body/headings).
	 *
	 * @param string $context Context key (body or headings).
	 *
	 * @return array
	 */
	public static function get_elementor_kit_typography( string $context ): array {
		$context = strtolower( trim( $context ) );
		if ( '' === $context ) {
			return array();
		}

		$kit_settings = self::get_elementor_kit_settings();
		if ( empty( $kit_settings ) || ! is_array( $kit_settings ) ) {
			return array();
		}

		if ( 'body' === $context ) {
			$prefixes = array( 'body_typography', 'body' );
		} elseif ( 'headings' === $context || 'heading' === $context ) {
			$prefixes = array( 'heading_typography', 'headings_typography', 'heading', 'headings' );
		} else {
			return array();
		}

		/*
		 * Deliberately no fallback to the kit's `system_typography` entries here.
		 * Those (primary, secondary, text, accent) are a palette that individual widgets
		 * opt into through `__globals__` - Elementor does not apply them to every heading
		 * on the site. Treating "primary" as a site-wide heading style stamped Elementor's
		 * stock Roboto 600 over whatever fonts the active theme had been rendering.
		 */
		return self::collect_typography_from_prefixes( $kit_settings, $prefixes );
	}

	/**
	 * Build CSS declarations from Elementor typography settings.
	 *
	 * @param array $settings Elementor typography settings.
	 *
	 * @return array<string, string>
	 */
	public static function build_typography_declarations( array $settings ): array {
		$settings = self::apply_global_typography( $settings );
		$rules    = array();

		$font_family = self::sanitize_font_family_value( $settings['typography_font_family'] ?? '' );
		if ( '' !== $font_family ) {
			$rules['font-family'] = $font_family;
		}

		$font_size = self::normalize_dimension( $settings['typography_font_size'] ?? null, 'px' );
		if ( null !== $font_size ) {
			$font_size = self::sanitize_css_dimension_value( $font_size );
			if ( '' !== $font_size ) {
				$rules['font-size'] = $font_size;
			}
		}

		$font_weight = self::sanitize_font_weight_value( $settings['typography_font_weight'] ?? '' );
		if ( '' !== $font_weight ) {
			$rules['font-weight'] = $font_weight;
		}

		$text_transform = self::sanitize_text_transform_value( $settings['typography_text_transform'] ?? '' );
		if ( '' !== $text_transform ) {
			$rules['text-transform'] = $text_transform;
		}

		$letter_spacing = self::normalize_dimension( $settings['typography_letter_spacing'] ?? null, 'px' );
		if ( null !== $letter_spacing ) {
			$letter_spacing = self::sanitize_letter_spacing_value( $letter_spacing );
			if ( '' !== $letter_spacing ) {
				$rules['letter-spacing'] = $letter_spacing;
			}
		}

		$line_height = self::normalize_dimension( $settings['typography_line_height'] ?? null, '' );
		if ( null !== $line_height ) {
			$line_height = self::sanitize_line_height_value( $line_height );
			if ( '' !== $line_height ) {
				$rules['line-height'] = $line_height;
			}
		}

		$font_style = self::sanitize_font_style_value( $settings['typography_font_style'] ?? '' );
		if ( '' !== $font_style ) {
			$rules['font-style'] = $font_style;
		}

		return $rules;
	}

	/**
	 * Match a font-family string to a theme preset slug when possible.
	 *
	 * @param string $font_family Raw font-family value.
	 *
	 * @return string|null
	 */
	public static function match_font_family_slug( string $font_family ): ?string {
		$font_family = trim( $font_family );
		if ( '' === $font_family ) {
			return null;
		}

		$normalized = self::normalize_font_family_string( $font_family );
		if ( '' === $normalized ) {
			return null;
		}

		foreach ( self::get_font_family_presets() as $preset ) {
			$preset_family = isset( $preset['fontFamily'] ) ? (string) $preset['fontFamily'] : '';
			$preset_slug   = isset( $preset['slug'] ) ? (string) $preset['slug'] : '';
			if ( '' === $preset_family || '' === $preset_slug ) {
				continue;
			}

			if ( self::normalize_font_family_string( $preset_family ) === $normalized ) {
				return $preset_slug;
			}
		}

		return null;
	}

	/**
	 * Build a CSS var reference for a font-family preset slug.
	 *
	 * @param string $slug Preset slug.
	 *
	 * @return string
	 */
	public static function build_font_family_preset_value( string $slug ): string {
		$slug = self::sanitize_scalar( $slug );
		if ( '' === $slug ) {
			return '';
		}

		return 'var(--wp--preset--font-family--' . $slug . ')';
	}

	/**
	 * Parse button-specific colors from widget and kit settings.
	 *
	 * @param array $settings Elementor widget settings.
	 *
	 * @return array{
	 *     attributes: array,
	 *     anchor_classes: array,
	 *     anchor_styles: array
	 * }
	 */
	public static function parse_button_styles( array $settings ): array {
		$attributes     = array();
		$anchor_classes = array();
		$anchor_styles  = array();

		$bg_mode = self::sanitize_scalar(
			$settings['button_background_background']
			?? $settings['_button_background_background']
				?? $settings['background_background']
					?? $settings['_background_background']
					?? ''
		);

		$bg_mode = strtolower( trim( $bg_mode ) );

		$allow_kit_background_fallback = ( '' === $bg_mode || 'classic' === $bg_mode );
		if ( 'none' === $bg_mode ) {
			$allow_kit_background_fallback = false;
		}

		$text_color = self::extract_color_from_sources(
			array(
				$settings['button_text_color'] ?? '',
				$settings['text_color'] ?? '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['button_text_color'] ?? '' ) : '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['text_color'] ?? '' ) : '',
			)
		);

		$background_color = self::extract_color_from_sources(
			array(
				$settings['button_background_color'] ?? '',
				$settings['background_color'] ?? '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['button_background_color'] ?? '' ) : '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['background_color'] ?? '' ) : '',
			)
		);

		if ( '' === $text_color['color'] && '' === $text_color['slug'] ) {
			$kit        = self::get_elementor_kit_settings();
			$text_color = self::extract_color_from_sources(
				array(
					$kit['button_text_color'] ?? '',
					$kit['button_color'] ?? '',
					$kit['buttons_text_color'] ?? '',
					$kit['global_button_text_color'] ?? '',
				)
			);
		}

		if ( $allow_kit_background_fallback && '' === $background_color['color'] && '' === $background_color['slug'] ) {
			$kit              = isset( $kit ) ? $kit : self::get_elementor_kit_settings();
			$background_color = self::extract_color_from_sources(
				array(
					$kit['button_background_color'] ?? '',
					$kit['button_color_background'] ?? '',
					$kit['buttons_background_color'] ?? '',
					$kit['global_button_background_color'] ?? '',
				)
			);
		}

		$text_value = $text_color['color'];
		if ( '' === $text_value && '' !== $text_color['slug'] ) {
			$text_value = self::resolve_theme_color_value( $text_color['slug'] );
		}

		$background_value = $background_color['color'];
		if ( '' === $background_value && '' !== $background_color['slug'] ) {
			$background_value = self::resolve_theme_color_value( $background_color['slug'] );
		}

		if ( '' === $text_value && '' !== $background_value ) {
			$text_value = '#ffffff';
		}

		if ( '' !== $text_value ) {
			$attributes['style']['color']['text'] = $text_value;
			$anchor_classes[]                     = 'has-text-color';
			$anchor_styles[]                      = 'color:' . $text_value;
		}

		if ( '' !== $background_value ) {
			$attributes['style']['color']['background'] = $background_value;
			$anchor_classes[]                           = 'has-background';
			$anchor_styles[]                            = 'background-color:' . $background_value;
		}

		return array(
			'attributes'     => $attributes,
			'anchor_classes' => $anchor_classes,
			'anchor_styles'  => $anchor_styles,
		);
	}

	/**
	 * Extract a normalized color from a list of potential sources.
	 *
	 * @param array<int, mixed> $sources Potential color values.
	 *
	 * @return array{slug: string, color: string}
	 */
	private static function extract_color_from_sources( array $sources ): array {
		foreach ( $sources as $source ) {
			$raw = self::sanitize_color( $source );
			if ( '' === $raw ) {
				continue;
			}

			if ( self::is_elementor_global_color_reference( $raw ) ) {
				$resolved = self::resolve_elementor_color_reference( $raw );

				if ( '' !== $resolved['color'] ) {
					$matched_slug = self::match_theme_color_slug( $resolved['color'] );

					return array(
						'slug'  => null === $matched_slug ? '' : self::clean_class( $matched_slug ),
						'color' => $resolved['color'],
					);
				}

				continue;
			}

			$normalized = self::normalize_color_value( $raw );
			if ( '' !== $normalized ) {
				$matched_slug = self::match_theme_color_slug( $normalized );
				if ( null !== $matched_slug ) {
					$resolved_color = self::resolve_theme_color_value( $matched_slug );
					return array(
						'slug'  => self::clean_class( $matched_slug ),
						'color' => $resolved_color ? $resolved_color : $normalized,
					);
				}

				return array(
					'slug'  => '',
					'color' => $normalized,
				);
			}

			if ( self::is_preset_slug( $raw ) ) {
				$slug = self::clean_class( $raw );

				return array(
					'slug'  => $slug,
					'color' => self::resolve_theme_color_value( $slug ),
				);
			}
		}

		return array(
			'slug'  => '',
			'color' => '',
		);
	}

	/**
	 * Resolve an Elementor color reference (raw hex or globals/colors?id=xxx)
	 * into a normalized hex color and an optional matching theme slug.
	 *
	 * @param mixed $value Raw color value or Elementor global reference.
	 *
	 * @return array{slug:string,color:string}
	 */
	public static function resolve_elementor_color_reference( $value ): array {
		if ( is_array( $value ) ) {
			if ( isset( $value['value'] ) ) {
				$value = $value['value'];
			} elseif ( isset( $value['color'] ) ) {
				$value = $value['color'];
			}
		}

		$value = self::sanitize_scalar( $value );
		if ( '' === $value ) {
			return array(
				'slug'  => '',
				'color' => '',
			);
		}

		$normalized = self::normalize_color_value( $value );
		if ( '' !== $normalized ) {
			$slug = self::match_theme_color_slug( $normalized );

			return array(
				'slug'  => null === $slug ? '' : $slug,
				'color' => $normalized,
			);
		}

		$handle = '';
		if ( 0 === strpos( $value, 'globals/colors?id=' ) ) {
			$handle = substr( $value, strlen( 'globals/colors?id=' ) );
		} else {
			$handle = $value;
		}

		$handle = trim( $handle );
		if ( '' === $handle ) {
			return array(
				'slug'  => '',
				'color' => '',
			);
		}

		// Try to read the color from the active Elementor kit.
		$kit = self::get_elementor_kit_settings();
		if ( ! empty( $kit ) && is_array( $kit ) ) {
			$groups = array( 'system_colors', 'custom_colors' );

			foreach ( $groups as $group ) {
				if ( empty( $kit[ $group ] ) || ! is_array( $kit[ $group ] ) ) {
					continue;
				}

				foreach ( $kit[ $group ] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					if ( empty( $item['_id'] ) || (string) $item['_id'] !== $handle ) {
						continue;
					}

					$color = self::normalize_color_value( $item['color'] ?? '' );
					if ( '' === $color ) {
						continue;
					}

					$slug = self::match_theme_color_slug( $color );
					if ( null === $slug ) {
						$slug = '';
					}

					return array(
						'slug'  => $slug,
						'color' => $color,
					);
				}
			}
		}

		$preset_color = self::resolve_theme_color_value( $handle );
		if ( '' !== $preset_color ) {
			return array(
				'slug'  => $handle,
				'color' => $preset_color,
			);
		}

		$theme_global = self::resolve_theme_global_color( $handle );
		if ( '' !== $theme_global ) {
			$slug = self::match_theme_color_slug( $theme_global );

			return array(
				'slug'  => null === $slug ? '' : $slug,
				'color' => $theme_global,
			);
		}

		return array(
			'slug'  => '',
			'color' => '',
		);
	}

	/**
	 * Resolve a global colour that the active theme - not Elementor - owns.
	 *
	 * Astra registers its palette with Elementor as `astglobalcolor0`..`astglobalcolor8`
	 * at runtime, so those handles never appear in the Elementor kit's own
	 * `system_colors` / `custom_colors`. Without this the colour is simply dropped and
	 * the converted section loses its background. The palette itself lives in the
	 * `astra-settings` option.
	 *
	 * @param string $handle Global colour handle taken from `__globals__`.
	 *
	 * @return string Normalised colour, or an empty string when it cannot be resolved.
	 */
	private static function resolve_theme_global_color( string $handle ): string {
		if ( 1 !== preg_match( '/^astglobalcolor(\d+)$/i', $handle, $matches ) ) {
			return '';
		}

		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		$settings = get_option( 'astra-settings' );
		if ( ! is_array( $settings ) ) {
			return '';
		}

		$palette = $settings['global-color-palette']['palette'] ?? null;
		if ( ! is_array( $palette ) ) {
			return '';
		}

		$index = (int) $matches[1];
		if ( ! isset( $palette[ $index ] ) ) {
			return '';
		}

		return self::normalize_color_value( $palette[ $index ] );
	}

	/**
	 * Read a four-sided dimension (padding, margin, radius) from the Elementor kit.
	 *
	 * Kit-level defaults are what Elementor falls back to whenever a widget leaves a
	 * control untouched, so a converter that ignores them reproduces the plugin's own
	 * defaults rather than the site's.
	 *
	 * @param string $key Kit setting key, e.g. `button_padding`.
	 *
	 * @return array<string, string> Map of top/right/bottom/left, or an empty array.
	 */
	public static function get_elementor_kit_dimensions( string $key ): array {
		$kit = self::get_elementor_kit_settings();

		return self::parse_dimensions( $kit[ $key ] ?? null );
	}

	/**
	 * Combine a colour and an opacity into a single CSS `rgba()` value.
	 *
	 * Lets a translucent layer be expressed as one ordinary colour value, which core
	 * blocks accept, rather than needing a separate opacity declaration.
	 *
	 * @param string $color   Colour value (hex, #RRGGBBAA, rgb()/rgba(), or a named colour).
	 * @param float  $opacity Opacity between 0 and 1. Multiplied with any alpha the colour already carries.
	 *
	 * @return string `rgba(...)` string, or an empty string when the colour cannot be parsed.
	 */
	public static function to_rgba_string( string $color, float $opacity ): string {
		$color = self::normalize_color_value( $color );
		if ( '' === $color ) {
			return '';
		}

		$alpha = max( 0.0, min( 1.0, $opacity ) );

		// #RRGGBBAA - fold the colour's own alpha into the requested opacity.
		if ( 1 === preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})$/i', $color, $m ) ) {
			$alpha *= hexdec( $m[2] ) / 255;
			$color  = '#' . $m[1];
		}

		$rgb = self::parse_color_to_rgb( $color );
		if ( ! is_array( $rgb ) || count( $rgb ) < 3 ) {
			return '';
		}

		$alpha = rtrim( rtrim( number_format( $alpha, 3, '.', '' ), '0' ), '.' );
		if ( '' === $alpha ) {
			$alpha = '0';
		}

		return sprintf( 'rgba(%d,%d,%d,%s)', (int) $rgb[0], (int) $rgb[1], (int) $rgb[2], $alpha );
	}

	/**
	 * Read a single size control (unit + size) from the Elementor kit.
	 *
	 * @param string $key Kit setting key, e.g. `button_typography_font_size`.
	 *
	 * @return string CSS length, or an empty string when it cannot be resolved.
	 */
	public static function get_elementor_kit_size( string $key ): string {
		$kit   = self::get_elementor_kit_settings();
		$value = $kit[ $key ] ?? null;

		if ( ! is_array( $value ) || ! isset( $value['size'] ) || ! is_numeric( $value['size'] ) ) {
			return '';
		}

		$unit = isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : 'px';
		if ( ! in_array( $unit, array( 'px', 'em', 'rem', '%' ), true ) ) {
			return '';
		}

		return ( (string) (float) $value['size'] ) . $unit;
	}

	/**
	 * Convert an Elementor four-sided dimensions control into CSS values.
	 *
	 * Elementor leaves a side as an empty string when it is not set, which is not the
	 * same as zero - an unset control means "inherit the kit default". Anything not
	 * fully specified therefore yields an empty array so callers can fall back.
	 *
	 * @param mixed $value Raw dimensions control value.
	 *
	 * @return array<string, string> Map of top/right/bottom/left, or an empty array.
	 */
	public static function parse_dimensions( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$unit = isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : 'px';
		if ( ! in_array( $unit, array( 'px', 'em', 'rem', '%' ), true ) ) {
			return array();
		}

		$out = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( ! isset( $value[ $side ] ) || ! is_numeric( $value[ $side ] ) ) {
				return array();
			}
			$out[ $side ] = ( (string) (float) $value[ $side ] ) . $unit;
		}

		return $out;
	}

	/**
	 * Fetch Elementor kit settings.
	 */
	private static function get_elementor_kit_settings(): array {
		if ( null !== self::$elementor_kit_settings ) {
			return self::$elementor_kit_settings;
		}

		self::$elementor_kit_settings = array();

		// Prefer Elementor's Kit API when available.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = Plugin::instance();

			// Newer Elementor: get_active_kit_for_frontend().
			if ( isset( $plugin->kits_manager ) && method_exists( $plugin->kits_manager, 'get_active_kit_for_frontend' ) ) {
				$kit = $plugin->kits_manager->get_active_kit_for_frontend();

				if ( $kit && method_exists( $kit, 'get_settings_for_display' ) ) {
					self::$elementor_kit_settings = (array) $kit->get_settings_for_display();

					return self::$elementor_kit_settings;
				}
			}

			// Fallback: older API.
			if ( isset( $plugin->kits_manager ) && method_exists( $plugin->kits_manager, 'get_current_settings' ) ) {
				self::$elementor_kit_settings = (array) $plugin->kits_manager->get_current_settings();

				return self::$elementor_kit_settings;
			}
		}

		if ( function_exists( 'get_option' ) && function_exists( 'get_post_meta' ) ) {
			$kit_id = (int) get_option( 'elementor_active_kit' );
			if ( $kit_id > 0 ) {
				$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
				if ( is_array( $settings ) ) {
					self::$elementor_kit_settings = $settings;
				}
			}
		}

		return self::$elementor_kit_settings;
	}

	/**
	 * Build a map of Elementor global typography definitions keyed by handle (_id).
	 *
	 * @return array<string, array> Map: handle => typography settings array.
	 */
	private static function get_elementor_global_typography_map(): array {
		if ( null !== self::$elementor_global_typography ) {
			return self::$elementor_global_typography;
		}

		self::$elementor_global_typography = array();

		$kit_settings = self::get_elementor_kit_settings();
		if ( empty( $kit_settings ) || ! is_array( $kit_settings ) ) {
			return self::$elementor_global_typography;
		}

		$groups = array( 'system_typography', 'custom_typography' );

		foreach ( $groups as $group ) {
			if ( empty( $kit_settings[ $group ] ) || ! is_array( $kit_settings[ $group ] ) ) {
				continue;
			}

			foreach ( $kit_settings[ $group ] as $item ) {
				if ( ! is_array( $item ) || empty( $item['_id'] ) ) {
					continue;
				}

				$handle = (string) $item['_id'];

				self::$elementor_global_typography[ $handle ] = $item;
			}
		}

		return self::$elementor_global_typography;
	}

	/**
	 * Collect typography settings using known kit key prefixes.
	 *
	 * @param array $kit_settings Elementor kit settings.
	 * @param array $prefixes Key prefixes to scan.
	 *
	 * @return array
	 */
	private static function collect_typography_from_prefixes( array $kit_settings, array $prefixes ): array {
		if ( empty( $kit_settings ) || empty( $prefixes ) ) {
			return array();
		}

		$suffix_map = array(
			'font_family'       => 'typography_font_family',
			'text_transform'    => 'typography_text_transform',
			'font_style'        => 'typography_font_style',
			'font_weight'       => 'typography_font_weight',
			'text_decoration'   => 'typography_text_decoration',
			'font_size'         => 'typography_font_size',
			'line_height'       => 'typography_line_height',
			'letter_spacing'    => 'typography_letter_spacing',
			'word_spacing'      => 'typography_word_spacing',
			'typography'        => 'typography_typography',
			'global_typography' => 'typography_global_typography',
		);

		$settings = array();

		foreach ( $prefixes as $prefix ) {
			$prefix = trim( (string) $prefix );
			if ( '' === $prefix ) {
				continue;
			}

			foreach ( $suffix_map as $suffix => $target ) {
				$key = $prefix . '_' . $suffix;
				if ( isset( $kit_settings[ $key ] ) ) {
					$settings[ $target ] = $kit_settings[ $key ];
				}
			}

			if ( isset( $kit_settings[ $prefix ] ) && is_array( $kit_settings[ $prefix ] ) ) {
				foreach ( $suffix_map as $suffix => $target ) {
					if ( isset( $kit_settings[ $prefix ][ $suffix ] ) ) {
						$settings[ $target ] = $kit_settings[ $prefix ][ $suffix ];
					}
				}
			}

			if ( ! empty( $settings ) ) {
				break;
			}
		}

		return $settings;
	}

	/**
	 * Extract global typography handle from Elementor __globals__ reference.
	 *
	 * Example input:  "globals/typography?id=primary"
	 * Example output: "primary"
	 *
	 * @param string $reference Raw global reference string.
	 *
	 * @return string Handle ID or empty string when it cannot be parsed.
	 */
	private static function extract_global_typography_handle( string $reference ): string {
		$reference = trim( $reference );
		if ( '' === $reference ) {
			return '';
		}

		$pos = strpos( $reference, 'id=' );
		if ( false === $pos ) {
			return '';
		}

		$handle = substr( $reference, $pos + 3 );

		$amp_pos = strpos( $handle, '&' );
		if ( false !== $amp_pos ) {
			$handle = substr( $handle, 0, $amp_pos );
		}

		return trim( $handle );
	}

	/**
	 * Resolve global typography reference (typography_typography = global) into concrete values.
	 *
	 * Expects the usual "typography_*" keys in $settings.
	 *
	 * @param array $settings Settings passed from widget handler.
	 *
	 * @return array Settings with global values merged in when applicable.
	 */
	private static function apply_global_typography( array $settings ): array {
		if (
			( ! isset( $settings['typography_typography'] ) || '' === self::sanitize_scalar( $settings['typography_typography'] ) )
			&& isset( $settings['__globals__'] )
			&& is_array( $settings['__globals__'] )
			&& ! empty( $settings['__globals__']['typography_typography'] )
		) {
			$global_handle = self::extract_global_typography_handle(
				self::sanitize_scalar( $settings['__globals__']['typography_typography'] )
			);

			if ( '' !== $global_handle ) {
				$settings['typography_typography']        = 'global';
				$settings['typography_global_typography'] = $global_handle;
			}
		}

		$mode = isset( $settings['typography_typography'] )
			? self::sanitize_scalar( $settings['typography_typography'] )
			: '';

		if ( 'global' !== $mode ) {
			return $settings;
		}

		$global_key = isset( $settings['typography_global_typography'] )
			? self::sanitize_scalar( $settings['typography_global_typography'] )
			: '';

		if ( '' === $global_key ) {
			return $settings;
		}

		$map    = self::get_elementor_global_typography_map();
		$global = isset( $map[ $global_key ] ) ? $map[ $global_key ] : null;

		if ( ! is_array( $global ) ) {
			return $settings;
		}

		// Scalar fields we want to inherit from the global definition.
		$scalar_keys = array(
			'typography_font_family',
			'typography_text_transform',
			'typography_font_style',
			'typography_font_weight',
			'typography_text_decoration',
		);

		foreach ( $scalar_keys as $key ) {
			if ( '' === self::sanitize_scalar( $settings[ $key ] ?? '' ) && isset( $global[ $key ] ) ) {
				$settings[ $key ] = $global[ $key ];
			}
		}

		// Dimension-like values (arrays with size/unit) we also want to inherit.
		$dimension_keys = array(
			'typography_font_size',
			'typography_line_height',
			'typography_letter_spacing',
			'typography_word_spacing',
		);

		foreach ( $dimension_keys as $key ) {
			if ( ! isset( $settings[ $key ] ) && isset( $global[ $key ] ) ) {
				$settings[ $key ] = $global[ $key ];
			}
		}

		return $settings;
	}

	/**
	 * Retrieve computed styles from an Elementor element.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return array<string, string> Normalized property => value map.
	 */
	public static function get_computed_styles( array $element ): array {
		$candidates = array();

		foreach ( array( 'computed_styles', 'computedStyles', 'computed' ) as $key ) {
			if ( isset( $element[ $key ] ) && is_array( $element[ $key ] ) ) {
				$candidates[] = $element[ $key ];
			}
		}

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			foreach ( array( 'computed_styles', 'computedStyles', 'computed' ) as $key ) {
				if ( isset( $element['settings'][ $key ] ) && is_array( $element['settings'][ $key ] ) ) {
					$candidates[] = $element['settings'][ $key ];
				}
			}
		}

		$styles = array();

		foreach ( $candidates as $candidate ) {
			foreach ( self::flatten_style_candidate( $candidate ) as $property => $value ) {
				if ( '' === $property || '' === $value ) {
					continue;
				}

				$styles[ $property ] = $value;
			}
		}

		return $styles;
	}

	/**
	 * Attempt to match a color to a theme preset slug within a 3% delta.
	 *
	 * @param string $color Color string.
	 *
	 * @return string|null Preset slug or null when no close match exists.
	 */
	public static function match_theme_color_slug( string $color ): ?string {
		$rgb = self::parse_color_to_rgb( $color );
		if ( null === $rgb ) {
			return null;
		}

		$palette = self::get_theme_palette();
		if ( empty( $palette ) ) {
			return null;
		}

		$closest_slug   = null;
		$closest_delta  = null;
		$max_difference = sqrt( 3 * ( 255 ** 2 ) );

		foreach ( $palette as $preset ) {
			$preset_color = self::parse_color_to_rgb( $preset['color'] ?? '' );
			if ( null === $preset_color ) {
				continue;
			}

			$distance = sqrt(
				( $rgb[0] - $preset_color[0] ) ** 2 +
				( $rgb[1] - $preset_color[1] ) ** 2 +
				( $rgb[2] - $preset_color[2] ) ** 2
			);

			$delta = $distance / $max_difference;

			if ( $delta > 0.03 ) {
				continue;
			}

			if ( null === $closest_delta || $delta < $closest_delta ) {
				$closest_delta = $delta;
				$closest_slug  = $preset['slug'] ?? null;
			}
		}

		return $closest_slug;
	}

	/**
	 * Attempt to match a font-size to a theme preset slug.
	 *
	 * @param string $font_size Font-size value (e.g. 18px, 1.125rem).
	 *
	 * @return string|null Preset slug when the value is within a 3% tolerance of a preset.
	 */
	public static function match_font_size_slug( string $font_size ): ?string {
		$target = self::font_size_to_pixels( $font_size );
		if ( null === $target ) {
			return null;
		}

		$presets = self::get_font_size_presets();
		if ( empty( $presets ) ) {
			return null;
		}

		foreach ( $presets as $preset ) {
			$preset_value = self::font_size_to_pixels( $preset['size'] ?? '' );
			if ( null === $preset_value ) {
				continue;
			}

			$tolerance = max( 0.25, $preset_value * 0.03 );

			if ( abs( $preset_value - $target ) <= $tolerance ) {
				return $preset['slug'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Resolve a theme color value from a preset slug.
	 *
	 * @param string $slug Theme color slug.
	 *
	 * @return string Normalized hexadecimal color or empty string when unavailable.
	 */
	public static function resolve_theme_color_value( string $slug ): string {
		$slug = self::sanitize_scalar( $slug );
		if ( '' === $slug ) {
			return '';
		}

		foreach ( self::get_theme_palette() as $preset ) {
			if ( strtolower( $preset['slug'] ?? '' ) !== strtolower( $slug ) ) {
				continue;
			}

			$normalized = self::normalize_color_value( $preset['color'] ?? '' );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}

		return '';
	}

	/**
	 * Resolve a font-size value from a preset slug.
	 *
	 * @param string $slug Theme font size slug.
	 *
	 * @return string Sanitized font-size string or empty string when unavailable.
	 */
	public static function resolve_font_size_value( string $slug ): string {
		$slug = self::sanitize_scalar( $slug );
		if ( '' === $slug ) {
			return '';
		}

		foreach ( self::get_font_size_presets() as $preset ) {
			if ( strtolower( $preset['slug'] ?? '' ) !== strtolower( $slug ) ) {
				continue;
			}

			$value = self::sanitize_css_dimension_value( $preset['size'] ?? '' );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Sanitize a CSS dimension value allowing typical units.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_css_dimension_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( '0' === $value || '0px' === strtolower( $value ) ) {
			return '0';
		}

		if ( preg_match( '/^-?[0-9]*\.?[0-9]+(px|em|rem|vh|vw|%)?$/i', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a font-family value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_family_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Sanitize a font-style value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_style_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value    = strtolower( trim( (string) $value ) );
		$allowed  = array( 'normal', 'italic', 'oblique', 'inherit', 'initial', 'unset' );
		$fallback = '';

		foreach ( $allowed as $option ) {
			if ( $value === $option ) {
				return $option;
			}
		}

		return $fallback;
	}

	/**
	 * Sanitize a text-decoration value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_text_decoration_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value   = strtolower( trim( (string) $value ) );
		$allowed = array(
			'none',
			'underline',
			'overline',
			'line-through',
			'inherit',
			'initial',
			'unset',
		);

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Sanitize a letter-spacing value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_letter_spacing_value( $value ): string {
		$sanitized = self::sanitize_css_dimension_value( $value );

		return $sanitized;
	}

	/**
	 * Sanitize a word-spacing value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_word_spacing_value( $value ): string {
		$sanitized = self::sanitize_css_dimension_value( $value );

		return $sanitized;
	}

	/**
	 * Sanitize a line-height value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_line_height_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'normal', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		if ( preg_match( '/^-?[0-9]*\.?[0-9]+(px|em|rem|%)?$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a font-weight value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font_weight_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'normal', 'bold', 'lighter', 'bolder', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		if ( preg_match( '/^(100|200|300|400|500|600|700|800|900)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a text-transform value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_text_transform_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$keywords = array( 'uppercase', 'lowercase', 'capitalize', 'none', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Normalize a color string to hexadecimal when possible.
	 *
	 * @param mixed $color Raw color value.
	 */
	public static function normalize_color_value( $color ): string {
		if ( is_array( $color ) || is_object( $color ) ) {
			return '';
		}

		$color = trim( strtolower( (string) $color ) );
		if ( '' === $color || 0 === strpos( $color, 'var(' ) || 'transparent' === $color ) {
			return '';
		}

		if ( preg_match( '/^#([0-9a-f]{8})$/i', $color, $m ) ) {
			return '#' . strtolower( $m[1] );
		}
		if ( preg_match( '/^#([0-9a-f]{4})$/i', $color, $m ) ) {
			$h = strtolower( $m[1] );

			// expand #RGBA -> #RRGGBBAA
			return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3];
		}

		$hex = sanitize_hex_color( $color );
		if ( false !== $hex && null !== $hex ) {
			return strtolower( $hex );
		}

		$rgb = self::parse_color_to_rgb( $color );
		if ( null === $rgb ) {
			return '';
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Convert raw palette data into a flat list.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_theme_palette(): array {
		if ( null !== self::$theme_palette ) {
			return self::$theme_palette;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			self::$theme_palette = array();

			return self::$theme_palette;
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$output  = array();

		foreach ( array( 'theme', 'custom', 'default' ) as $group ) {
			if ( empty( $palette[ $group ] ) || ! is_array( $palette[ $group ] ) ) {
				continue;
			}

			foreach ( $palette[ $group ] as $preset ) {
				if ( empty( $preset['slug'] ) || empty( $preset['color'] ) ) {
					continue;
				}

				$output[] = array(
					'slug'  => (string) $preset['slug'],
					'color' => (string) $preset['color'],
				);
			}
		}

		self::$theme_palette = $output;

		return self::$theme_palette;
	}

	/**
	 * Fetch font size presets defined by the active theme.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_font_size_presets(): array {
		if ( null !== self::$font_sizes ) {
			return self::$font_sizes;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			self::$font_sizes = array();

			return self::$font_sizes;
		}

		$settings = wp_get_global_settings( array( 'typography', 'fontSizes' ) );
		$output   = array();

		foreach ( array( 'theme', 'custom', 'default' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}

			foreach ( $settings[ $group ] as $preset ) {
				if ( empty( $preset['slug'] ) || empty( $preset['size'] ) ) {
					continue;
				}

				$output[] = array(
					'slug' => (string) $preset['slug'],
					'size' => (string) $preset['size'],
				);
			}
		}

		self::$font_sizes = $output;

		return self::$font_sizes;
	}

	/**
	 * Fetch font-family presets defined by the active theme.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_font_family_presets(): array {
		if ( null !== self::$font_families ) {
			return self::$font_families;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			self::$font_families = array();

			return self::$font_families;
		}

		$settings = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$output   = array();

		foreach ( array( 'theme', 'custom', 'default' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}

			foreach ( $settings[ $group ] as $preset ) {
				if ( empty( $preset['slug'] ) || empty( $preset['fontFamily'] ) ) {
					continue;
				}

				$output[] = array(
					'slug'       => (string) $preset['slug'],
					'fontFamily' => (string) $preset['fontFamily'],
				);
			}
		}

		self::$font_families = $output;

		return self::$font_families;
	}

	/**
	 * Convert font-size values to an approximate pixel value.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function font_size_to_pixels( $value ): ?float {
		if ( is_array( $value ) || is_object( $value ) ) {
			return null;
		}

		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)px$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)rem$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)em$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		return null;
	}

	/**
	 * Normalize font-family strings for comparison.
	 *
	 * @param string $value Raw font-family string.
	 *
	 * @return string
	 */
	private static function normalize_font_family_string( string $value ): string {
		$value = trim( strtolower( $value ) );
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( array( '"', "'" ), '', $value );
		$value = preg_replace( '/\s*,\s*/', ',', $value );
		$value = preg_replace( '/\s+/', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Convert mixed style data into a property => value map.
	 *
	 * @param mixed $candidate Potential style structure.
	 *
	 * @return array<string, string>
	 */
	private static function flatten_style_candidate( $candidate ): array {
		$output = array();

		if ( ! is_array( $candidate ) ) {
			return $output;
		}

		foreach ( $candidate as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['property'], $value['value'] ) ) {
					$property = self::normalize_property_name( $value['property'] );
					$val      = trim( (string) $value['value'] );
					if ( '' !== $property && '' !== $val ) {
						$output[ $property ] = $val;
					}
				}

				continue;
			}

			$property = self::normalize_property_name( (string) $key );
			$val      = trim( (string) $value );
			if ( '' === $property || '' === $val ) {
				continue;
			}

			$output[ $property ] = $val;
		}

		return $output;
	}

	/**
	 * Normalize property names to kebab-case.
	 *
	 * @param string $property Property name.
	 */
	private static function normalize_property_name( string $property ): string {
		$property = trim( (string) $property );
		if ( '' === $property ) {
			return '';
		}

		$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $property ) );
		$property = str_replace( array( '_', ' ' ), '-', $property );

		return trim( $property );
	}

	/**
	 * Convert color strings (hex/rgb/rgba) to RGB array.
	 *
	 * @param mixed $color Raw color value.
	 *
	 * @return array{0:int,1:int,2:int}|null
	 */
	private static function parse_color_to_rgb( $color ): ?array {
		if ( is_array( $color ) || is_object( $color ) ) {
			return null;
		}

		$color = trim( strtolower( (string) $color ) );
		if ( '' === $color ) {
			return null;
		}

		$hex = sanitize_hex_color( $color );
		if ( $hex ) {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			if ( 6 === strlen( $hex ) ) {
				return array(
					hexdec( substr( $hex, 0, 2 ) ),
					hexdec( substr( $hex, 2, 2 ) ),
					hexdec( substr( $hex, 4, 2 ) ),
				);
			}
		}

		if ( preg_match( '/^rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)$/', $color, $matches ) ) {
			return array( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		}

		if ( preg_match( '/^rgba\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]*\.?[0-9]+)\s*\)$/', $color, $matches ) ) {
			$alpha = (float) $matches[4];
			if ( $alpha <= 0 ) {
				return null;
			}

			return array( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		}

		return null;
	}

	/**
	 * Parse spacing settings from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array{attributes:array, style:string}
	 */
	public static function parse_spacing( array $settings ): array {
		$attributes  = array();
		$style_parts = array();
		$maps        = array(
			'_padding'        => 'padding',
			'_margin'         => 'margin',
			'padding'         => 'padding',
			'margin'          => 'margin',
			'button_padding'  => 'padding',
			'_button_padding' => 'padding',
		);

		foreach ( $maps as $key => $type ) {
			$data = $settings[ $key ] ?? null;
			if ( ! is_array( $data ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$value = self::extract_box_value( $data, $side );
				if ( null === $value ) {
					continue;
				}

				$attributes[ $type ][ $side ] = $value;
				$style_parts[]                = sprintf( '%s-%s:%s;', $type, $side, $value );
			}
		}

		$gap_keys = array( 'gap', 'column_gap', 'gap_columns' );
		foreach ( $gap_keys as $gap_key ) {
			$gap_value = self::normalize_dimension( $settings[ $gap_key ] ?? null, 'px' );
			if ( null === $gap_value ) {
				continue;
			}

			$attributes['blockGap'] = $gap_value;
			$style_parts[]          = sprintf( 'gap:%s;', $gap_value );
			break;
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Parse border settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 *
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_border( array $settings ): array {
		$attributes  = array();
		$style_parts = array();

		$radius_sources = array( '_border_radius', 'border_radius', 'button_border_radius', '_button_border_radius' );
		foreach ( $radius_sources as $radius_key ) {
			$radius_data = $settings[ $radius_key ] ?? null;
			if ( ! is_array( $radius_data ) ) {
				continue;
			}

			$unit = self::sanitize_scalar( $radius_data['unit'] ?? 'px' );
			foreach (
				array(
					'topLeft'     => 'top',
					'topRight'    => 'right',
					'bottomRight' => 'bottom',
					'bottomLeft'  => 'left',
				) as $attr_key => $side
			) {
				$value = self::normalize_dimension( $radius_data[ $side ] ?? null, $unit );
				if ( null === $value ) {
					continue;
				}

				$attributes['radius'][ $attr_key ] = $value;
				$style_parts[]                     = sprintf(
					'border-%s-radius:%s;',
					str_replace(
						array(
							'Left',
							'Right',
						),
						array( 'left', 'right' ),
						strtolower( preg_replace( '/([A-Z])/', '-$1', $attr_key ) )
					),
					$value
				);
			}
		}

		$width_sources = array( '_border_width', 'border_width', 'button_border_width', '_button_border_width' );
		$color_info    = self::extract_color_from_sources(
			array(
				$settings['border_color'] ?? '',
				$settings['_border_color'] ?? '',
				$settings['button_border_color'] ?? '',
				$settings['_button_border_color'] ?? '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['border_color'] ?? '' ) : '',
				( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) ? ( $settings['__globals__']['button_border_color'] ?? '' ) : '',
			)
		);

		$color = $color_info['color'];
		if ( '' === $color && '' !== $color_info['slug'] ) {
			$color = self::resolve_theme_color_value( $color_info['slug'] );
		}

		foreach ( $width_sources as $width_key ) {
			$width_data = $settings[ $width_key ] ?? null;
			if ( ! is_array( $width_data ) ) {
				continue;
			}

			$unit = self::sanitize_scalar( $width_data['unit'] ?? 'px' );
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$value = self::normalize_dimension( $width_data[ $side ] ?? null, $unit );
				if ( null === $value ) {
					continue;
				}

				$attributes[ $side ]['width'] = $value;
				$style_parts[]                = sprintf( 'border-%s-width:%s;', $side, $value );

				if ( '' !== $color ) {
					$attributes[ $side ]['color'] = $color;
					$style_parts[]                = sprintf( 'border-%s-color:%s;', $side, $color );
				}
			}
		}

		$style_value = self::sanitize_scalar( $settings['_border_border'] ?? $settings['border_style'] ?? '' );
		if ( '' !== $style_value ) {
			$attributes['style'] = $style_value;
			$style_parts[]       = sprintf( 'border-style:%s;', $style_value );
		}

		return array(
			'attributes' => $attributes,
			'style'      => implode( '', $style_parts ),
		);
	}

	/**
	 * Parse container specific styles into block attributes.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array
	 */
	public static function parse_container_styles( array $settings ): array {
		$attributes = array();
		$style      = array();
		$spacing    = self::parse_spacing( $settings );
		if ( ! empty( $spacing['attributes']['padding'] ) ) {
			$attributes['style']['spacing']['padding'] = $spacing['attributes']['padding'];
		}

		if ( ! empty( $spacing['attributes']['margin'] ) ) {
			$attributes['style']['spacing']['margin'] = $spacing['attributes']['margin'];
		}

		if ( ! empty( $spacing['attributes']['blockGap'] ) ) {
			$attributes['style']['spacing']['blockGap'] = $spacing['attributes']['blockGap'];
		}

		$border = self::parse_border( $settings );
		if ( ! empty( $border['attributes'] ) ) {
			$attributes['style']['border'] = $border['attributes'];
		}

		$background_color = self::extract_color_from_sources(
			array(
				isset( $settings['background_color'] ) ? $settings['background_color'] : '',
				isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] )
					? ( isset( $settings['__globals__']['background_color'] ) ? $settings['__globals__']['background_color'] : '' )
					: '',
			)
		);

		$background_type = self::sanitize_scalar( $settings['background_background'] ?? $settings['_background_background'] ?? '' );
		$image_data      = $settings['background_image'] ?? $settings['_background_image'] ?? null;
		$image_url       = self::extract_image_url( $image_data );

		if ( '' !== $background_color['color'] && ( '' === $background_type || 'classic' === $background_type ) ) {
			if ( ! isset( $style['color'] ) || ! is_array( $style['color'] ) ) {
				$style['color'] = array();
			}
			$style['color']['background'] = $background_color['color'];
		} elseif ( '' !== $background_color['slug'] && ( '' === $background_type || 'classic' === $background_type ) ) {
			$resolved = self::resolve_theme_color_value( $background_color['slug'] );
			if ( '' !== $resolved ) {
				if ( ! isset( $style['color'] ) || ! is_array( $style['color'] ) ) {
					$style['color'] = array();
				}
				$style['color']['background'] = $resolved;
			}
		}

		if ( 'classic' === $background_type && '' !== $image_url ) {
			$attributes['style']['background']['image'] = $image_url;

			$position = self::sanitize_scalar( $settings['background_position'] ?? $settings['_background_position'] ?? '' );
			if ( '' !== $position ) {
				$attributes['style']['background']['position'] = $position;
			}

			$size = self::sanitize_scalar( $settings['background_size'] ?? $settings['_background_size'] ?? '' );
			if ( '' !== $size ) {
				$attributes['style']['background']['size'] = $size;
			}

			$repeat = self::sanitize_scalar( $settings['background_repeat'] ?? $settings['_background_repeat'] ?? '' );
			if ( '' !== $repeat ) {
				$attributes['style']['background']['repeat'] = $repeat;
			}
		}

		$min_height = self::parse_min_height( $settings );
		if ( null !== $min_height ) {
			$attributes['style']['dimensions']['minHeight'] = $min_height;
		}

		foreach ( array( '_css_classes', 'css_classes' ) as $css_classes_key ) {
			$custom_classes = self::sanitize_class_string( $settings[ $css_classes_key ] ?? '' );
			if ( '' !== $custom_classes ) {
				$attributes['className'] = self::append_class( $attributes['className'] ?? '', $custom_classes );
			}
		}

		if ( ! empty( $settings['_element_id'] ) ) {
			$anchor_id = self::clean_class( (string) $settings['_element_id'] );
			if ( '' !== $anchor_id ) {
				$attributes['anchor'] = $anchor_id;
			}
		}
		if (
			isset( $settings['box_shadow_box_shadow_type'], $settings['box_shadow_box_shadow'] )
			&& 'yes' === $settings['box_shadow_box_shadow_type']
			&& is_array( $settings['box_shadow_box_shadow'] )
		) {
			$shadow = $settings['box_shadow_box_shadow'];

			$horizontal = isset( $shadow['horizontal'] ) ? (float) $shadow['horizontal'] : 0.0;
			$vertical   = isset( $shadow['vertical'] ) ? (float) $shadow['vertical'] : 0.0;
			$blur       = isset( $shadow['blur'] ) ? (float) $shadow['blur'] : 0.0;
			$spread     = isset( $shadow['spread'] ) ? (float) $shadow['spread'] : 0.0;
			$color_raw  = isset( $shadow['color'] ) ? $shadow['color'] : '';
			$color      = self::normalize_color_value( $color_raw );

			$parts = array(
				$horizontal . 'px',
				$vertical . 'px',
				$blur . 'px',
				$spread . 'px',
			);

			if ( '' !== $color ) {
				$parts[] = $color;
			}

			$box_shadow = trim( implode( ' ', $parts ) );
			if ( '' !== $box_shadow ) {
				$style['boxShadow'] = $box_shadow;
			}
		}

		if ( ! empty( $style ) ) {
			$attributes['style'] = isset( $attributes['style'] ) && is_array( $attributes['style'] )
				? array_replace_recursive( $attributes['style'], $style )
				: $style;
		}

		return $attributes;
	}

	/**
	 * Determine if the supplied color is a preset slug.
	 *
	 * @param string $color Color string.
	 */
	private static function is_preset_slug( string $color ): bool {
		if ( '' === $color ) {
			return false;
		}

		if ( false !== strpos( $color, '#' ) || 0 === strpos( $color, 'rgb' ) ) {
			return false;
		}

		if ( self::is_elementor_global_color_reference( $color ) ) {
			return false;
		}

		if ( false !== strpos( $color, '/' ) ) {
			return false;
		}

		return true;
	}

	private static function is_elementor_global_color_reference( string $value ): bool {
		$value = trim( $value );

		return ( '' !== $value && 0 === strpos( $value, 'globals/colors?id=' ) );
	}

	/**
	 * Sanitize a scalar value from Elementor settings.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_scalar( $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;

		return trim( $value );
	}

	/**
	 * Normalize color strings.
	 *
	 * @param mixed $value Potential color value.
	 */
	private static function sanitize_color( $value ): string {
		if ( is_array( $value ) ) {
			$value = $value['value'] ?? $value['color'] ?? '';
		}

		return strtolower( self::sanitize_scalar( $value ) );
	}

	/**
	 * Normalize Elementor dimension value.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $default_unit Default unit when missing.
	 */
	private static function normalize_dimension( $value, string $default_unit ): ?string {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) ) {
				return self::normalize_dimension( $value['size'], $value['unit'] ?? $default_unit );
			}
			if ( isset( $value['value'] ) ) {
				return self::normalize_dimension( $value['value'], $value['unit'] ?? $default_unit );
			}
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return $value . ( '' === $default_unit ? '' : $default_unit );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/[a-z%]+$/i', $value ) ) {
			return $value;
		}

		return $value . ( '' === $default_unit ? '' : $default_unit );
	}

	/**
	 * Extract padding/margin side values.
	 *
	 * @param array  $data Elementor box model array.
	 * @param string $side Side to extract.
	 */
	private static function extract_box_value( array $data, string $side ): ?string {
		if ( array_key_exists( $side, $data ) ) {
			return self::normalize_dimension( $data[ $side ], $data['unit'] ?? 'px' );
		}

		return null;
	}

	/**
	 * Parse min-height values from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 */
	/**
	 * Parse min-height values from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 */
	/**
	 * Parse min-height values from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return string|null CSS value like "88vh" or "600px" or null when not set.
	 */
	private static function parse_min_height( $settings ) {
		if ( ! is_array( $settings ) ) {
			return null;
		}

		// Elementor may store min-height separately for desktop / tablet / mobile.
		$candidate = $settings['min_height'] ?? null;

		if ( is_array( $candidate ) ) {
			if ( ! isset( $candidate['size'] ) || '' === $candidate['size'] || null === $candidate['size'] ) {
				return null;
			}

			$unit = isset( $candidate['unit'] ) && '' !== $candidate['unit'] ? $candidate['unit'] : 'px';

			return self::normalize_dimension(
				array(
					'size' => $candidate['size'],
					'unit' => $unit,
				),
				$unit
			);
		}

		if ( null !== $candidate && '' !== $candidate ) {
			$value = self::normalize_dimension( $candidate, 'px' );
			if ( null !== $value ) {
				return $value;
			}
		}

		return null;
	}


	/**
	 * Extract image URL from Elementor image data.
	 *
	 * @param mixed $value Raw image value.
	 */
	private static function extract_image_url( $value ): string {
		if ( is_array( $value ) ) {
			/*
			 * Prefer the attachment ID. Elementor resolves media through the ID, so on a
			 * site whose content was imported or migrated the stored `url` is routinely
			 * stale and still points at the source domain - which would leave the
			 * converted page hot-linking someone else's server.
			 */
			$attachment_url = self::resolve_attachment_url( $value['id'] ?? null );
			if ( '' !== $attachment_url ) {
				return $attachment_url;
			}

			if ( ! empty( $value['url'] ) ) {
				return esc_url_raw( (string) $value['url'] );
			}

			if ( ! empty( $value['value'] ) ) {
				return esc_url_raw( (string) $value['value'] );
			}
		}

		if ( is_string( $value ) ) {
			return esc_url_raw( $value );
		}

		return '';
	}

	/**
	 * Resolve an Elementor media control to a URL on this site.
	 *
	 * Elementor keeps both an attachment `id` and the `url` the media had when
	 * the widget was saved. After an import or a domain change only the ID is
	 * still meaningful, so callers outside this class resolve media through
	 * here rather than reading `url` directly and hot-linking the old site.
	 *
	 * @param mixed $media Elementor media setting, or a plain URL string.
	 */
	public static function resolve_media_url( $media ): string {
		return self::extract_image_url( $media );
	}

	/**
	 * Resolve a media attachment ID to its URL on this site.
	 *
	 * @param mixed $id Raw attachment ID from an Elementor media control.
	 *
	 * @return string Attachment URL, or an empty string when it cannot be resolved.
	 */
	private static function resolve_attachment_url( $id ): string {
		if ( ! is_scalar( $id ) ) {
			return '';
		}

		$id = (int) $id;
		if ( $id <= 0 || ! function_exists( 'wp_get_attachment_url' ) ) {
			return '';
		}

		$url = wp_get_attachment_url( $id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Append classes safely, ensuring no duplicates.
	 *
	 * @param string $existing Existing class list.
	 * @param string $new New class or classes.
	 */
	private static function append_class( string $existing, string $additional ): string {
		$existing_list = '' === $existing ? array() : preg_split( '/\s+/', $existing );
		$new_list      = preg_split( '/\s+/', $additional );
		$combined      = array();

		foreach ( array_merge( (array) $existing_list, (array) $new_list ) as $class ) {
			$class = trim( (string) $class );
			if ( '' === $class ) {
				continue;
			}
			$combined[ $class ] = true;
		}

		return implode( ' ', array_keys( $combined ) );
	}

	/**
	 * Sanitize custom class string from Elementor.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_class_string( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$classes = array();
		foreach ( preg_split( '/\s+/', $value ) as $class ) {
			$sanitized = self::clean_class( $class );
			if ( '' === $sanitized ) {
				continue;
			}
			$classes[] = $sanitized;
		}

		return implode( ' ', $classes );
	}

	/**
	 * Sanitize a single class name while dropping Elementor-generated classes.
	 *
	 * @param string $class Raw class name.
	 *
	 * @return string Sanitized class name or empty string if disallowed.
	 */
	public static function clean_class( string $class_name ): string {
		$class_name = trim( $class_name );
		if ( '' === $class_name ) {
			return '';
		}

		$sanitized = sanitize_html_class( $class_name );
		if ( '' === $sanitized ) {
			return '';
		}

		if ( self::is_disallowed_elementor_class( $sanitized ) ) {
			return '';
		}

		return $sanitized;
	}

	/**
	 * Determine if a class should be stripped because it's Elementor-specific.
	 *
	 * @param string $class Sanitized class name.
	 */
	private static function is_disallowed_elementor_class( string $class_name ): bool {
		$blocked_exact = array(
			'e-con',
			'e-con-full',
			'e-con-boxed',
			'e-con-child',
			'e-grid',
		);

		if ( in_array( $class_name, $blocked_exact, true ) ) {
			return true;
		}

		$blocked_prefixes = array(
			'elementor',
			'elementor-',
			'elementor_',
			'e-con-',
			'e-grid-',
			'wp-elements-',
		);

		foreach ( $blocked_prefixes as $prefix ) {
			if ( 0 === strpos( $class_name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save custom CSS to the Customizer's Additional CSS store when available.
	 *
	 * @param string $css CSS string to append.
	 */
	public static function save_custom_css( string $css ): void {
		// Migrated custom CSS is page-specific author input (e.g. a rule the user
		// wrote for one page). Route it into the active per-page stylesheet so it
		// loads ONLY on the converted page and never leaks site-wide. It is
		// sanitized when that page's file is written (External_CSS_Service::save_post_css).
		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}

		$collector = External_Style_Collector::get_active();
		if ( null !== $collector ) {
			$collector->add_raw_css( $css );
		}
	}
}
