<?php
/**
 * Helper for parsing Elementor icon data structures.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize Elementor icon data for consistent rendering.
 */
class Icon_Parser {
	/**
	 * Parse Elementor selected icon data into a normalized structure.
	 *
	 * @param mixed $icon_setting Icon settings array from Elementor.
	 * @param string $default_library Default library to use when none provided.
	 *
	 * @return array<string,string> Normalized icon data.
	 */
	public static function parse_selected_icon( $icon_setting, string $default_library = 'fa-solid' ): array {
		$result = array(
			'type'        => 'font',
			'library'     => $default_library,
			'style_class' => self::resolve_icon_style_class( $default_library ),
			'class_name'  => '',
			'slug'        => '',
			'url'         => '',
		);

		if ( ! is_array( $icon_setting ) ) {
			return $result;
		}

		$library               = isset( $icon_setting['library'] ) ? (string) $icon_setting['library'] : $default_library;
		$result['library']     = $library;
		$result['style_class'] = self::resolve_icon_style_class( $library );

		if ( isset( $icon_setting['value'] ) ) {
			$value = $icon_setting['value'];

			if ( is_array( $value ) && isset( $value['url'] ) ) {
				$result['type'] = 'svg';
				$result['url']  = (string) $value['url'];

				$parsed_url = parse_url( $result['url'] );
				if ( is_array( $parsed_url ) && isset( $parsed_url['path'] ) ) {
					$result['slug'] = basename( $parsed_url['path'] );
				}

				return $result;
			}

			if ( is_string( $value ) ) {
				$value                = trim( $value );
				$result['slug']       = self::extract_icon_slug( $value );
				$result['class_name'] = self::build_class_name( $value, $result['style_class'], $result['slug'] );
			}
		}

		return $result;
	}

	/**
	 * Ensure the icon class name includes required style class and slug.
	 */
	private static function build_class_name( string $raw_value, string $style_class, string $slug ): string {
		$class_name   = trim( $raw_value );
		$has_style    = false;
		$style_tokens = array( $style_class, 'fas', 'far', 'fab', 'fa-solid', 'fa-regular', 'fa-brands' );

		foreach ( $style_tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( preg_match( '/\b' . preg_quote( $token, '/' ) . '\b/', $class_name ) ) {
				$has_style = true;
				break;
			}
		}

		$has_slug = '' !== $slug && false !== strpos( $class_name, $slug );

		if ( '' === $class_name ) {
			$class_name = trim( $style_class . ' ' . $slug );
		} else {
			$extra_parts = array();
			if ( ! $has_style && '' !== $style_class ) {
				$extra_parts[] = $style_class;
			}
			if ( ! $has_slug && '' !== $slug ) {
				$extra_parts[] = $slug;
			}

			if ( ! empty( $extra_parts ) ) {
				$class_name = trim( $class_name . ' ' . implode( ' ', $extra_parts ) );
			}
		}

		return $class_name;
	}

	/**
	 * Map Elementor library to Font Awesome style prefix.
	 */
	public static function resolve_icon_style_class( string $library ): string {
		switch ( $library ) {
			case 'fa-regular':
				return 'far';
			case 'fa-brands':
				return 'fab';
			default:
				return 'fas';
		}
	}

	/**
	 * Extract icon slug from a raw value.
	 */
	public static function extract_icon_slug( string $icon_value ): string {
		$parts = preg_split( '/\s+/', trim( $icon_value ) );
		if ( is_array( $parts ) && count( $parts ) > 0 ) {
			return (string) $parts[ count( $parts ) - 1 ];
		}

		return $icon_value;
	}
}
