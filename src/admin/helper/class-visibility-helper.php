<?php
/**
 * Carries Elementor's "hide on device" settings over to converted blocks.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function esc_attr;
use function get_option;
use function get_post_meta;
use function parse_blocks;
use function serialize_block;
use function serialize_block_attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a class per hidden device to a converted element and registers the media queries that hide it.
 */
class Visibility_Helper {

	/**
	 * Prefix of the class added for each device an element is hidden on.
	 */
	public const CLASS_PREFIX = 'blockshift-hide-';

	/**
	 * Elementor devices that end at a max-width breakpoint, smallest first, with Elementor's default widths.
	 */
	private const MAX_WIDTH_DEVICES = array(
		'mobile'       => 767,
		'mobile_extra' => 880,
		'tablet'       => 1024,
		'tablet_extra' => 1200,
		'laptop'       => 1366,
	);

	/**
	 * Elementor's default min-width of the widescreen device.
	 */
	private const WIDESCREEN_MIN_WIDTH = 2400;

	/**
	 * Blocks whose saved markup has no element that takes a custom class.
	 */
	private const CLASSLESS_BLOCKS = array( 'core/html', 'core/shortcode', 'core/freeform', 'core/block', 'core/more', 'core/nextpage' );

	/**
	 * Read the devices an Elementor element is hidden on.
	 *
	 * @param array $settings Elementor element settings.
	 *
	 * @return array<int, string> Device slugs such as desktop, tablet and mobile.
	 */
	public static function get_hidden_devices( array $settings ): array {
		$devices = array();
		foreach ( array_merge( array( 'desktop', 'widescreen' ), array_keys( self::MAX_WIDTH_DEVICES ) ) as $device ) {
			if ( ! empty( $settings[ 'hide_' . $device ] ) ) {
				$devices[] = $device;
			}
		}

		return $devices;
	}

	/**
	 * Get the hide classes of an Elementor element and register the CSS they need.
	 *
	 * @param array $settings Elementor element settings.
	 *
	 * @return string Space-separated classes, or an empty string when the element is never hidden.
	 */
	public static function get_class_name( array $settings ): string {
		$classes = array();
		foreach ( self::get_hidden_devices( $settings ) as $device ) {
			$classes[] = self::CLASS_PREFIX . str_replace( '_', '-', $device );
		}

		if ( empty( $classes ) ) {
			return '';
		}

		self::register_css();

		return implode( ' ', $classes );
	}

	/**
	 * Add the hide classes of an Elementor element to its converted markup.
	 *
	 * A single block takes the classes itself. Several blocks, or a block whose
	 * markup cannot carry a class, are wrapped in a group that takes them.
	 *
	 * @param string $content  Serialized blocks produced for the element.
	 * @param array  $settings Elementor element settings.
	 *
	 * @return string Serialized blocks carrying the hide classes.
	 */
	public static function apply( string $content, array $settings ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		$class_name = self::get_class_name( $settings );
		if ( '' === $class_name ) {
			return $content;
		}

		$classes = explode( ' ', $class_name );
		$blocks  = array();
		foreach ( parse_blocks( $content ) as $block ) {
			if ( null !== $block['blockName'] || '' !== trim( (string) $block['innerHTML'] ) ) {
				$blocks[] = $block;
			}
		}

		if ( 1 === count( $blocks ) ) {
			$block = self::add_classes_to_block( $blocks[0], $classes );
			if ( null !== $block ) {
				return serialize_block( $block ) . "\n";
			}
		}

		return '<!-- wp:group ' . serialize_block_attributes( array( 'className' => $class_name ) ) . " -->\n"
			. '<div class="wp-block-group ' . esc_attr( $class_name ) . '">' . trim( $content ) . '</div>'
			. "\n<!-- /wp:group -->\n";
	}

	/**
	 * Add classes to a parsed block and to the root element of its markup.
	 *
	 * @param array              $block   Parsed block.
	 * @param array<int, string> $classes Classes to add.
	 *
	 * @return array|null The block with the classes, or null when its markup cannot carry them.
	 */
	private static function add_classes_to_block( array $block, array $classes ): ?array {
		if ( null === $block['blockName'] || in_array( $block['blockName'], self::CLASSLESS_BLOCKS, true ) ) {
			return null;
		}

		$has_markup = '' !== trim( (string) $block['innerHTML'] );
		if ( $has_markup ) {
			if ( ! is_string( $block['innerContent'][0] ?? null ) ) {
				return null;
			}

			$processor = new \WP_HTML_Tag_Processor( $block['innerContent'][0] );
			if ( ! $processor->next_tag() ) {
				return null;
			}

			foreach ( $classes as $class ) {
				$processor->add_class( $class );
			}
			$block['innerContent'][0] = $processor->get_updated_html();
		}

		$existing                    = isset( $block['attrs']['className'] ) ? trim( (string) $block['attrs']['className'] ) : '';
		$block['attrs']['className'] = trim( $existing . ' ' . implode( ' ', $classes ) );

		return $block;
	}

	/**
	 * Register the media queries that hide the classes, once per conversion.
	 *
	 * The rules skip the block editor canvas so a hidden block stays editable.
	 *
	 * @return void
	 */
	private static function register_css(): void {
		$collector = External_Style_Collector::get_active();
		if ( null === $collector ) {
			return;
		}

		$css = '';
		foreach ( self::get_device_ranges() as $device => $range ) {
			$conditions = array();
			if ( null !== $range['min'] ) {
				$conditions[] = '(min-width:' . $range['min'] . 'px)';
			}
			if ( null !== $range['max'] ) {
				$conditions[] = '(max-width:' . $range['max'] . 'px)';
			}
			if ( empty( $conditions ) ) {
				continue;
			}

			$css .= '@media ' . implode( ' and ', $conditions ) . '{body:not(.editor-styles-wrapper) .' . self::CLASS_PREFIX . str_replace( '_', '-', $device ) . "{display:none!important}}\n";
		}

		$collector->add_raw_css( $css );
	}

	/**
	 * Work out the viewport range of every device from the kit's breakpoints.
	 *
	 * @return array<string, array{min: int|null, max: int|null}> Ranges keyed by device slug.
	 */
	public static function get_device_ranges(): array {
		$kit      = self::get_kit_settings();
		$active   = isset( $kit['active_breakpoints'] ) && is_array( $kit['active_breakpoints'] ) ? $kit['active_breakpoints'] : array();
		$ranges   = array();
		$previous = null;

		foreach ( self::MAX_WIDTH_DEVICES as $device => $default ) {
			$is_core = in_array( $device, array( 'mobile', 'tablet' ), true );
			if ( ! $is_core && ! in_array( 'viewport_' . $device, $active, true ) ) {
				continue;
			}

			$max = self::read_breakpoint( $kit, $device, $default );

			$ranges[ $device ] = array(
				'min' => null === $previous ? null : $previous + 1,
				'max' => $max,
			);
			$previous          = $max;
		}

		$widescreen = in_array( 'viewport_widescreen', $active, true )
			? self::read_breakpoint( $kit, 'widescreen', self::WIDESCREEN_MIN_WIDTH )
			: null;

		$ranges['desktop'] = array(
			'min' => $previous + 1,
			'max' => null === $widescreen ? null : $widescreen - 1,
		);

		if ( null !== $widescreen ) {
			$ranges['widescreen'] = array(
				'min' => $widescreen,
				'max' => null,
			);
		}

		return $ranges;
	}

	/**
	 * Read one breakpoint from the kit, falling back to Elementor's default.
	 *
	 * Kits saved before Elementor 3.2 store the mobile and tablet breakpoints as
	 * the first width of the next device (`viewport_md`, `viewport_lg`).
	 *
	 * @param array  $kit      Kit settings.
	 * @param string $device   Device slug.
	 * @param int    $fallback Elementor's default width for the device.
	 *
	 * @return int Breakpoint width in pixels.
	 */
	private static function read_breakpoint( array $kit, string $device, int $fallback ): int {
		if ( isset( $kit[ 'viewport_' . $device ] ) && is_numeric( $kit[ 'viewport_' . $device ] ) && (int) $kit[ 'viewport_' . $device ] > 0 ) {
			return (int) $kit[ 'viewport_' . $device ];
		}

		$legacy_keys = array(
			'mobile' => 'viewport_md',
			'tablet' => 'viewport_lg',
		);
		if ( isset( $legacy_keys[ $device ], $kit[ $legacy_keys[ $device ] ] ) && is_numeric( $kit[ $legacy_keys[ $device ] ] ) && (int) $kit[ $legacy_keys[ $device ] ] > 1 ) {
			return (int) $kit[ $legacy_keys[ $device ] ] - 1;
		}

		return $fallback;
	}

	/**
	 * Get the settings of the active Elementor kit.
	 *
	 * @return array Kit settings, or an empty array when there is no kit.
	 */
	private static function get_kit_settings(): array {
		$kit_id = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id <= 0 ) {
			return array();
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

		return is_array( $settings ) ? $settings : array();
	}
}
