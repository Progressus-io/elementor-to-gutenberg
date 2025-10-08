<?php
/**
 * Decide which Gutenberg layout to use when rendering Elementor containers.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Determine which layout a container should use.
 */
class Container_Classifier {
	/**
	 * Determine if a container should render as a grid.
	 *
	 * @param array $element Elementor container element.
	 *
	 * @return bool
	 */
        public static function is_grid( array $element ): bool {
                $settings    = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
                $child_count = isset( $element['elements'] ) && is_array( $element['elements'] ) ? count( $element['elements'] ) : 0;

                if ( isset( $settings['container_type'] ) && 'grid' === $settings['container_type'] ) {
                        return true;
                }

$grid_hints = array(
'grid_columns',
'grid_template_columns',
'grid_auto_flow',
'grid_columns_grid',
'grid_rows_grid',
);

                foreach ( $grid_hints as $hint ) {
                        if ( isset( $settings[ $hint ] ) && '' !== $settings[ $hint ] ) {
                                return true;
                        }
                }

                if ( self::is_repeating_card_layout( $element ) ) {
                        return true;
                }

                return $child_count > 4;
        }

	/**
	 * Infer a grid column count from Elementor settings.
	 *
	 * @param array $element     Elementor container element.
	 * @param int   $child_count Number of children.
	 *
	 * @return int
	 */
        public static function get_grid_column_count( array $element, int $child_count ): int {
                $child_count = max( 1, $child_count );
                $settings    = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

                if ( isset( $settings['grid_columns_grid'] ) && is_array( $settings['grid_columns_grid'] ) ) {
$size = $settings['grid_columns_grid']['size'] ?? null;
if ( is_numeric( $size ) ) {
return self::clamp_columns( (int) $size, $child_count );
}
}

$numeric_keys = array( 'grid_columns', 'columns', 'grid_columns_number' );
foreach ( $numeric_keys as $key ) {
$value = $settings[ $key ] ?? null;
if ( null === $value || '' === $value ) {
continue;
}

if ( is_array( $value ) ) {
$value = $value['size'] ?? $value['value'] ?? null;
}

if ( is_numeric( $value ) ) {
return self::clamp_columns( (int) $value, $child_count );
}
}

$template = $settings['grid_template_columns'] ?? '';
                if ( is_string( $template ) && '' !== trim( $template ) ) {
                        $template = strtolower( $template );
                        if ( preg_match( '/repeat\((\d+)/', $template, $matches ) ) {
                                return self::clamp_columns( (int) $matches[1], $child_count );
                        }
                        $columns = preg_split( '/\s+/', trim( $template ) );
                        if ( is_array( $columns ) && count( $columns ) > 1 ) {
                                return self::clamp_columns( count( $columns ), $child_count );
                        }
                }

                if ( self::is_repeating_card_layout( $element ) ) {
                        return self::clamp_columns( min( 3, $child_count ), $child_count );
                }

                return min( 4, $child_count );
        }

        /**
         * Determine if the container is a repeating card layout such as testimonials or image boxes.
         *
         * @param array $element Elementor container element.
         */
        public static function is_repeating_card_layout( array $element ): bool {
                $children = is_array( $element['elements'] ?? null ) ? array_filter( $element['elements'], 'is_array' ) : array();
                $child_count = count( $children );

                if ( $child_count < 3 ) {
                        return false;
                }

                $card_widgets = array( 'image-box', 'icon-box', 'testimonial' );
                $card_like    = 0;

                foreach ( $children as $child ) {
                        $el_type = $child['elType'] ?? '';
                        if ( 'widget' === $el_type && in_array( $child['widgetType'] ?? '', $card_widgets, true ) ) {
                                $card_like++;
                                continue;
                        }

                        if ( 'container' === $el_type ) {
                                $grandchildren = is_array( $child['elements'] ?? null ) ? $child['elements'] : array();
                                foreach ( $grandchildren as $grandchild ) {
                                        if ( ! is_array( $grandchild ) ) {
                                                continue;
                                        }
                                        if ( 'widget' === ( $grandchild['elType'] ?? '' ) && in_array( $grandchild['widgetType'] ?? '', $card_widgets, true ) ) {
                                                $card_like++;
                                                break;
                                        }
                                }
                        }
                }

                if ( 0 === $card_like ) {
                        return false;
                }

                $threshold = max( 2, (int) ceil( $child_count * 0.6 ) );

                return $card_like >= $threshold;
        }

/**
 * Determine if a container should be rendered as a flex row.
 *
 * @param array $element     Elementor container element.
 * @param int   $child_count Number of child elements.
 */
public static function is_row( array $element, int $child_count ): bool {
if ( $child_count < 2 || $child_count > 4 ) {
return false;
}

if ( self::is_grid( $element ) ) {
return false;
}

$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
$direction  = self::get_flex_direction( $settings );
$has_row    = in_array( $direction, array( 'row', 'row-reverse' ), true );
$wrap_value = strtolower( (string) ( $settings['flex_wrap'] ?? $settings['flex_wrap_tablet'] ?? $settings['flex_wrap_mobile'] ?? '' ) );

if ( $has_row ) {
return true;
}

return '' === $direction && ( '' === $wrap_value || 'nowrap' !== $wrap_value );
}

	/**
	 * Get the flex direction configured for a container.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return string
	 */
public static function get_flex_direction( array $settings ): string {
$direction = $settings['flex_direction'] ?? $settings['direction'] ?? '';
$direction = is_string( $direction ) ? strtolower( $direction ) : '';

$valid = array( 'row', 'row-reverse', 'column', 'column-reverse' );
if ( in_array( $direction, $valid, true ) ) {
return $direction;
}

return '';
}

/**
 * Clamp inferred column counts to sane limits.
 *
 * @param int $columns     Desired column count.
 * @param int $child_count Total children.
 */
private static function clamp_columns( int $columns, int $child_count ): int {
$columns = max( 1, $columns );
$columns = min( $columns, $child_count );
return max( 1, $columns );
}
}
