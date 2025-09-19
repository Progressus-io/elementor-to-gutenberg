<?php
/**
 * Maps Elementor grid containers to Gutenberg blocks.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

use Progressus\Gutenberg\Admin\Layout\Css_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Mapper responsible for rendering grid containers.
 */
class Grid_Mapper {
	/**
	 * Render a grid container as a Gutenberg group block.
	 *
	 * @param array    $element          Elementor element data.
	 * @param callable $render_children Callback to render child elements.
	 * @return string
	 */
	public static function render_grid_container( array $element, callable $render_children ): string {
		if ( ! is_callable( $render_children ) ) {
			return '';
		}

		$settings = $element['settings'] ?? array();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$container_id = isset( $element['id'] ) ? sanitize_html_class( (string) $element['id'] ) : '';
		if ( '' === $container_id ) {
			$container_id = sanitize_html_class( uniqid( 'grid-' ) );
		}

		$columns = self::get_responsive_int( $settings, 'grid_columns_grid', 1 );
		if ( empty( $columns['desktop'] ) ) {
			$columns['desktop'] = 1;
		}
		$rows = self::get_responsive_int( $settings, 'grid_rows_grid', null );
		$gaps = self::get_gap( $settings );

		$selector        = '.etg-' . $container_id;
		$desktop_columns = max( 1, absint( $columns['desktop'] ) );

		$base_props = array(
			'display'               => 'grid',
			'grid-template-columns' => self::build_repeat_value( $desktop_columns ),
		);

		if ( ! empty( $rows['desktop'] ) ) {
			$base_props['grid-template-rows'] = self::build_repeat_value( absint( $rows['desktop'] ) );
		}

		$gap_desktop = self::build_gap_properties( $gaps['desktop'], true );
		$base_props  = array_merge( $base_props, $gap_desktop['properties'] );

		Css_Registry::add_rule( $selector, $base_props );

		$tablet_gap_result = self::build_gap_properties( $gaps['tablet'], false );
		$tablet_props      = array();
		if ( ! empty( $columns['tablet'] ) && absint( $columns['tablet'] ) !== $desktop_columns ) {
			$tablet_props['grid-template-columns'] = self::build_repeat_value( absint( $columns['tablet'] ) );
		}
		$desktop_rows_value = ! empty( $rows['desktop'] ) ? absint( $rows['desktop'] ) : 0;
		if ( ! empty( $rows['tablet'] ) && absint( $rows['tablet'] ) !== $desktop_rows_value ) {
			$tablet_props['grid-template-rows'] = self::build_repeat_value( absint( $rows['tablet'] ) );
		}
		foreach ( $tablet_gap_result['properties'] as $property => $value ) {
			$reference = $gap_desktop['properties'][ $property ] ?? null;
			if ( $reference === $value ) {
				continue;
			}
			$tablet_props[ $property ] = $value;
		}
		if ( ! empty( $tablet_props ) ) {
			Css_Registry::add_rule( $selector, $tablet_props, '(max-width: 960px)' );
		}

		$mobile_gap_result = self::build_gap_properties( $gaps['mobile'], false );
		$mobile_props      = array();
		$tablet_columns    = ! empty( $columns['tablet'] ) ? absint( $columns['tablet'] ) : $desktop_columns;
		if ( ! empty( $columns['mobile'] ) && absint( $columns['mobile'] ) !== $tablet_columns ) {
			$mobile_props['grid-template-columns'] = self::build_repeat_value( absint( $columns['mobile'] ) );
		}
		$tablet_rows_value = ! empty( $rows['tablet'] ) ? absint( $rows['tablet'] ) : $desktop_rows_value;
		if ( ! empty( $rows['mobile'] ) && absint( $rows['mobile'] ) !== $tablet_rows_value ) {
			$mobile_props['grid-template-rows'] = self::build_repeat_value( absint( $rows['mobile'] ) );
		}
		$gap_reference_mobile = ! empty( $tablet_gap_result['properties'] ) ? $tablet_gap_result['properties'] : $gap_desktop['properties'];
		foreach ( $mobile_gap_result['properties'] as $property => $value ) {
			$reference = $gap_reference_mobile[ $property ] ?? null;
			if ( $reference === $value ) {
				continue;
			}
			$mobile_props[ $property ] = $value;
		}
		if ( ! empty( $mobile_props ) ) {
			Css_Registry::add_rule( $selector, $mobile_props, '(max-width: 600px)' );
		}

		$block_gap_value = $gap_desktop['block_gap'];

		$attributes = array(
			'className' => 'etg-grid etg-' . $container_id,
			'layout'    => array(
				'type'        => 'grid',
				'columnCount' => $desktop_columns,
			),
		);
		if ( null !== $block_gap_value ) {
			$attributes['style'] = array(
				'spacing' => array(
					'blockGap' => $block_gap_value,
				),
			);
		}

		$attributes_json = wp_json_encode( $attributes );
		$style_attribute = '';
		if ( null !== $block_gap_value ) {
			$style_attribute = ' style="gap:' . esc_attr( $block_gap_value ) . ';"';
		}

		$children_output = '';
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				$child_markup = call_user_func( $render_children, array( $child ) );
				$child_markup = trim( (string) $child_markup );
				if ( '' === $child_markup ) {
					continue;
				}
				$child_attributes_json = wp_json_encode( array( 'className' => 'etg-grid-item' ) );
				$children_output      .= '<!-- wp:group ' . $child_attributes_json . ' --><div class="wp-block-group etg-grid-item">' . $child_markup . '</div><!-- /wp:group -->';
			}
		}

		$comment_open = '<!-- wp:group';
		if ( ! empty( $attributes_json ) ) {
			$comment_open .= ' ' . $attributes_json;
		}
		$comment_open .= ' -->';
		$comment_close = '<!-- /wp:group -->';

		$block_content  = $comment_open;
		$block_content .= '<div class="wp-block-group etg-grid etg-' . esc_attr( $container_id ) . '"' . $style_attribute . '>';
		$block_content .= $children_output;
		$block_content .= '</div>';
		$block_content .= $comment_close;
		$block_content .= '\n';

		return $block_content;
	}

	/**
	 * Get responsive integer settings for desktop/tablet/mobile.
	 *
	 * @param array   $settings       Elementor settings array.
	 * @param string  $base_key       Base key for the setting.
	 * @param integer $default_value  Default desktop value.
	 * @return array
	 */
	private static function get_responsive_int( array $settings, string $base_key, ?int $default_value ): array {
		return array(
			'desktop' => self::get_int( $settings, $base_key . '.size', $default_value ),
			'tablet'  => self::get_int( $settings, $base_key . '_tablet.size', null ),
			'mobile'  => self::get_int( $settings, $base_key . '_mobile.size', null ),
		);
	}

	/**
	 * Safely extract an integer from nested settings.
	 *
	 * @param array   $settings       Settings array.
	 * @param string  $path           Dot separated path.
	 * @param integer $default_value  Default value.
	 * @return int|null
	 */
	private static function get_int( array $settings, string $path, ?int $default_value ): ?int {
		$value = self::get_value_by_path( $settings, $path );
		if ( null === $value || '' === $value ) {
			return $default_value;
		}
		if ( is_array( $value ) ) {
			return $default_value;
		}
		if ( ! is_numeric( $value ) ) {
			return $default_value;
		}

		$int_value = absint( $value );
		if ( $int_value < 1 ) {
			return $default_value;
		}

		return $int_value;
	}

	/**
	 * Retrieve a value from an array using dot notation.
	 *
	 * @param array  $settings Settings array.
	 * @param string $path     Dot separated path.
	 * @return mixed
	 */
	private static function get_value_by_path( array $settings, string $path ) {
		$segments = explode( '.', $path );
		$value    = $settings;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Extract gap values for desktop, tablet, and mobile.
	 *
	 * @param array $settings Elementor settings.
	 * @return array
	 */
	private static function get_gap( array $settings ): array {
		return array(
			'desktop' => array(
				'gap'        => self::get_dimension_value( $settings, 'gap' ),
				'column_gap' => self::get_dimension_value( $settings, 'grid_column_gap' ),
				'row_gap'    => self::get_dimension_value( $settings, 'grid_row_gap' ),
			),
			'tablet'  => array(
				'gap'        => self::get_dimension_value( $settings, 'gap_tablet' ),
				'column_gap' => self::get_dimension_value( $settings, 'grid_column_gap_tablet' ),
				'row_gap'    => self::get_dimension_value( $settings, 'grid_row_gap_tablet' ),
			),
			'mobile'  => array(
				'gap'        => self::get_dimension_value( $settings, 'gap_mobile' ),
				'column_gap' => self::get_dimension_value( $settings, 'grid_column_gap_mobile' ),
				'row_gap'    => self::get_dimension_value( $settings, 'grid_row_gap_mobile' ),
			),
		);
	}

	/**
	 * Retrieve a dimension value with unit.
	 *
	 * @param array  $settings Settings array.
	 * @param string $key      Base key.
	 * @return string|null
	 */
	private static function get_dimension_value( array $settings, string $key ): ?string {
		$raw  = self::get_value_by_path( $settings, $key );
		$size = null;
		$unit = '';

		if ( is_array( $raw ) ) {
			$size = $raw['size'] ?? null;
			$unit = isset( $raw['unit'] ) ? (string) $raw['unit'] : '';
		} elseif ( null !== $raw ) {
			$size = $raw;
		}

		if ( null === $size ) {
			$size = self::get_value_by_path( $settings, $key . '.size' );
			$unit = (string) self::get_value_by_path( $settings, $key . '.unit' );
		}

		if ( null === $size || '' === $size || is_array( $size ) ) {
			return null;
		}
		if ( ! is_numeric( $size ) ) {
			return null;
		}

		$numeric = (float) $size;
		if ( 0.0 === $numeric ) {
			return '0';
		}

		if ( '' === $unit ) {
			$unit = 'px';
		}

		if ( abs( $numeric - round( $numeric ) ) < 0.0001 ) {
			$size_value = (string) (int) round( $numeric );
		} else {
			$size_value = rtrim( rtrim( sprintf( '%.4F', $numeric ), '0' ), '.' );
		}

		return $size_value . $unit;
	}

	/**
	 * Build repeat value used by CSS grid templates.
	 *
	 * @param int $count Column or row count.
	 * @return string
	 */
	private static function build_repeat_value( int $count ): string {
		$count = max( 1, $count );
		return 'repeat(' . $count . ',minmax(0,1fr))';
	}

	/**
	 * Build gap properties array for CSS output.
	 *
	 * @param array $gap_values    Gap values for a breakpoint.
	 * @param bool  $apply_default Whether to apply default gap when missing.
	 * @return array
	 */
	private static function build_gap_properties( array $gap_values, bool $apply_default ): array {
		$properties = array();
		$block_gap  = null;

		if ( null !== $gap_values['gap'] && '' !== $gap_values['gap'] ) {
			$properties['gap'] = $gap_values['gap'];
			$block_gap         = $gap_values['gap'];
			return array(
				'properties' => $properties,
				'block_gap'  => $block_gap,
			);
		}

		if ( null !== $gap_values['column_gap'] && '' !== $gap_values['column_gap'] ) {
			$properties['column-gap'] = $gap_values['column_gap'];
		}
		if ( null !== $gap_values['row_gap'] && '' !== $gap_values['row_gap'] ) {
			$properties['row-gap'] = $gap_values['row_gap'];
		}

		if ( empty( $properties ) && $apply_default ) {
			$properties['gap'] = 'var(--wp--style--block-gap, .75rem)';
			$block_gap         = 'var(--wp--style--block-gap, .75rem)';
		}

		return array(
			'properties' => $properties,
			'block_gap'  => $block_gap,
		);
	}
}
