<?php
/**
 * Normalize Elementor column widths to Gutenberg percentages.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize Elementor widths for Gutenberg columns.
 */
class Columns_Widths {
	/**
	 * Normalize column widths to percentage strings that add up to ~100%.
	 *
	 * @param array $children Column children.
	 *
	 * @return array<string>
	 */
	public static function normalize( array $children ): array {
		$count = count( $children );
		if ( 0 === $count ) {
			return array();
		}

		$positive_indices = array();
		$zero_indices     = array();

		foreach ( $children as $index => $child ) {
			$hint = self::width_hint( $child );
			if ( $hint > 0 ) {
				$positive_indices[ $index ] = $hint;
			} else {
				$zero_indices[] = $index;
			}
		}

		if ( empty( $positive_indices ) ) {
			$equal = 100 / $count;
			return self::distribute_evenly( $count, $equal );
		}

		$positive_total = array_sum( $positive_indices );
		if ( $positive_total <= 0 ) {
			$equal = 100 / $count;
			return self::distribute_evenly( $count, $equal );
		}

		$percentages         = array_fill( 0, $count, 0.0 );
		$last_positive_index = array_key_last( $positive_indices );

		foreach ( $positive_indices as $index => $hint ) {
			$value = round( ( $hint / $positive_total ) * 100, 2 );
			$percentages[ $index ] = max( $value, 0.0 );
		}

		$total_assigned = array_sum( $percentages );
		if ( $total_assigned > 100 ) {
			$excess = $total_assigned - 100;
			if ( null !== $last_positive_index ) {
				$percentages[ $last_positive_index ] = max( $percentages[ $last_positive_index ] - $excess, 0.0 );
			}
			$remaining = 0.0;
		} else {
			$remaining = 100.0 - $total_assigned;
		}

		if ( ! empty( $zero_indices ) ) {
			$zero_count = count( $zero_indices );
			foreach ( $zero_indices as $i => $zero_index ) {
				if ( $i === $zero_count - 1 ) {
					$percentages[ $zero_index ] = max( $remaining, 0.0 );
					break;
				}
				$share                        = round( $remaining / ( $zero_count - $i ), 2 );
				$percentages[ $zero_index ]   = max( $share, 0.0 );
				$remaining                   -= $percentages[ $zero_index ];
			}
		} elseif ( null !== $last_positive_index ) {
			$percentages[ $last_positive_index ] += max( $remaining, 0.0 );
		}

		$result = array();
		foreach ( $percentages as $value ) {
			$result[] = self::format_percentage( $value );
		}

		return $result;
	}

	/**
	 * Extract width hint from a child element.
	 *
	 * @param array $child Elementor child element.
	 *
	 * @return float
	 */
	public static function width_hint( array $child ): float {
		$settings = $child['settings'] ?? array();

		if ( isset( $settings['_column_size'] ) ) {
			return (float) $settings['_column_size'];
		}

		if ( isset( $settings['width'] ) ) {
			$width_setting = $settings['width'];
			if ( is_array( $width_setting ) ) {
				if ( isset( $width_setting['size'] ) && '' !== $width_setting['size'] ) {
					return (float) $width_setting['size'];
				}
			} elseif ( '' !== $width_setting ) {
				return (float) $width_setting;
			}
		}

		if ( isset( $child['width'] ) && '' !== $child['width'] ) {
			return (float) $child['width'];
		}

		return 0.0;
	}

	/**
	 * Create evenly distributed widths.
	 *
	 * @param int   $count Number of columns.
	 * @param float $base  Base percentage.
	 *
	 * @return array<string>
	 */
	private static function distribute_evenly( int $count, float $base ): array {
		$widths    = array();
		$remaining = 100.0;

		for ( $index = 0; $index < $count; $index++ ) {
			if ( $index === $count - 1 ) {
				$widths[] = self::format_percentage( $remaining );
				break;
			}

			$value      = round( $base, 2 );
			$remaining -= $value;
			$widths[]   = self::format_percentage( $value );
		}

		return $widths;
	}

	/**
	 * Format a float as percentage string.
	 *
	 * @param float $value Float value.
	 *
	 * @return string
	 */
	private static function format_percentage( float $value ): string {
		if ( $value < 0 ) {
			$value = 0.0;
		}

		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}
}
