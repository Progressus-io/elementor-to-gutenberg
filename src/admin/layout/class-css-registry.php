<?php
/**
 * Registry for collecting grid layout CSS rules.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Collects CSS rules for Elementor grid conversions and outputs them once.
 */
class Css_Registry {
	/**
	 * Stored CSS rules organized by media query.
	 *
	 * @var array
	 */
	private static $rules = array();

	/**
	 * Whether the CSS has already been printed.
	 *
	 * @var bool
	 */
	private static $did_output = false;

	/**
	 * Register a CSS rule for later output.
	 *
	 * @param string      $selector   CSS selector.
	 * @param array       $properties Associative array of CSS properties => values.
	 * @param string|null $media      Optional media query condition.
	 */
	public static function add_rule( string $selector, array $properties, ?string $media = null ): void {
		$selector = self::sanitize_selector( $selector );
		if ( '' === $selector || empty( $properties ) ) {
			return;
		}

		$media_key = self::sanitize_media( $media );
		if ( ! isset( self::$rules[ $media_key ] ) ) {
			self::$rules[ $media_key ] = array();
		}
		if ( ! isset( self::$rules[ $media_key ][ $selector ] ) ) {
			self::$rules[ $media_key ][ $selector ] = array();
		}

		foreach ( $properties as $property => $value ) {
			$property = self::sanitize_property( $property );
			$value    = self::sanitize_value( $value );
			if ( '' === $property || '' === $value ) {
				continue;
			}
			self::$rules[ $media_key ][ $selector ][ $property ] = $value;
		}
	}

	/**
	 * Output collected CSS rules in a single style tag.
	 */
	public static function output(): void {
		if ( self::$did_output ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$css  = self::build_stylesheet();
		$css .= self::get_persisted_css();

		if ( '' === trim( $css ) ) {
			return;
		}

		echo '<style id="etg-grid-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		self::$did_output = true;
	}

	/**
	 * Retrieve the generated stylesheet.
	 *
	 * @return string
	 */
	public static function get_stylesheet(): string {
		return self::build_stylesheet();
	}

	/**
	 * Reset stored rules and output flag.
	 */
	public static function reset(): void {
		self::$rules      = array();
		self::$did_output = false;
	}

	/**
	 * Build the full stylesheet string.
	 *
	 * @return string
	 */
	private static function build_stylesheet(): string {
		$styles = '';

		foreach ( self::$rules as $media => $selectors ) {
			if ( empty( $selectors ) ) {
				continue;
			}

			$block = '';
			foreach ( $selectors as $selector => $properties ) {
				$declaration = self::build_declaration_block( $properties );
				if ( '' === $declaration ) {
					continue;
				}
				$block .= $selector . '{' . $declaration . '}';
			}

			if ( '' === $block ) {
				continue;
			}

			if ( 'global' === $media ) {
				$styles .= $block;
			} else {
				$styles .= '@media ' . $media . '{' . $block . '}';
			}
		}

		return $styles;
	}

	/**
	 * Build a declaration block string from a property array.
	 *
	 * @param array $properties CSS properties mapped to values.
	 * @return string
	 */
	private static function build_declaration_block( array $properties ): string {
		if ( empty( $properties ) ) {
			return '';
		}

		$declarations = array();
		foreach ( $properties as $property => $value ) {
			if ( '' === $property || '' === $value ) {
				continue;
			}
			$declarations[] = $property . ':' . $value;
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return implode( ';', $declarations ) . ';';
	}

	/**
	 * Retrieve CSS stored with posts so converted grids work on the frontend.
	 *
	 * @return string
	 */
	private static function get_persisted_css(): string {
		$styles = '';

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$stored = get_post_meta( $post_id, '_etg_grid_css', true );
				if ( is_string( $stored ) && '' !== trim( $stored ) ) {
					$styles .= $stored;
				}
			}
			return $styles;
		}

		global $wp_query;
		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$seen = array();
			foreach ( $wp_query->posts as $post ) {
				if ( empty( $post->ID ) || isset( $seen[ $post->ID ] ) ) {
					continue;
				}
				$seen[ $post->ID ] = true;

				$stored = get_post_meta( $post->ID, '_etg_grid_css', true );
				if ( is_string( $stored ) && '' !== trim( $stored ) ) {
					$styles .= $stored;
				}
			}
		}

		return $styles;
	}

	/**
	 * Sanitize CSS selector input.
	 *
	 * @param string $selector Selector string.
	 * @return string
	 */
	private static function sanitize_selector( string $selector ): string {
		$selector = trim( $selector );
		$selector = preg_replace( '/[^A-Za-z0-9\-\_\.\#\s\:\>]/', '', $selector );
		return $selector;
	}

	/**
	 * Sanitize CSS media query string.
	 *
	 * @param string|null $media Media query condition.
	 * @return string
	 */
	private static function sanitize_media( ?string $media ): string {
		if ( null === $media || '' === trim( $media ) ) {
			return 'global';
		}

		$media = trim( $media );
		$media = preg_replace( '/[^A-Za-z0-9\(\)\:\-\s\.]/', '', $media );
		return $media;
	}

	/**
	 * Sanitize CSS property name.
	 *
	 * @param string $property Property name.
	 * @return string
	 */
	private static function sanitize_property( $property ): string {
		$property = is_string( $property ) ? $property : '';
		$property = strtolower( trim( $property ) );
		$property = preg_replace( '/[^a-z0-9\-]/', '', $property );
		return $property;
	}

	/**
	 * Sanitize CSS property value.
	 *
	 * @param mixed $value Property value.
	 * @return string
	 */
	private static function sanitize_value( $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		} elseif ( is_numeric( $value ) ) {
			$value = (string) $value;
		} elseif ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		$value = preg_replace( '/[\{\}<>]/', '', $value );
		return $value;
	}
}
