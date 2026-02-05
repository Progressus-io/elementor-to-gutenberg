<?php
/**
 * Hardened Gutenberg block output builder.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Hardened Gutenberg block output builder.
 */
class Block_Output_Builder {

	/**
	 * @var External_Style_Collector|null
	 */
	private static ?External_Style_Collector $collector = null;

	/**
	 * @var Gutenberg_Supports_Mapper|null
	 */
	private static ?Gutenberg_Supports_Mapper $mapper = null;

	/**
	 * Bootstrap dependencies.
	 *
	 * @param External_Style_Collector|null $collector Collector instance.
	 *
	 * @return void
	 */
	public static function bootstrap( ?External_Style_Collector $collector ): void {
		self::$collector = $collector;
		if ( null === self::$mapper ) {
			self::$mapper = new Gutenberg_Supports_Mapper();
		}
	}

	/**
	 * Normalize and split attributes into supported and external CSS buckets.
	 *
	 * @param string $block_slug Block slug.
	 * @param array $attrs Raw attributes.
	 *
	 * @return array Supported attributes.
	 */
	public static function prepare_attributes( string $block_slug, array $attrs ): array {
		$attrs = Style_Normalizer::normalize_attributes( $block_slug, $attrs );

		if ( null === self::$mapper ) {
			self::$mapper = new Gutenberg_Supports_Mapper();
		}

		$split = self::$mapper->split_attributes( $block_slug, $attrs );

		if ( ! empty( $split['external'] ) && null !== self::$collector ) {
			$class = self::$collector->externalize_declarations( $block_slug, $split['external'] );
			if ( '' !== $class ) {
				$split['supported']['className'] = Html_Attribute_Builder::merge_classes(
					$split['supported']['className'] ?? '',
					$class
				);
			}
		}

		if ( ! empty( $split['dropped'] ) && null !== self::$collector ) {
			self::$collector->record_dropped( $block_slug, 'attrs', $split['dropped'] );
		}

		return $split['supported'];
	}

	/**
	 * Expose the active style collector instance.
	 *
	 * @return External_Style_Collector|null
	 */
	public static function get_collector(): ?External_Style_Collector {
		return self::$collector;
	}

	/**
	 * Strip unsafe fragments from inner HTML.
	 *
	 * @param string $block_slug Block slug.
	 * @param string $inner_html Raw inner HTML.
	 *
	 * @return string
	 */
	public static function sanitize_inner_html( string $block_slug, string $inner_html ): string {
		$inner_html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $inner_html );

		if ( null !== self::$collector ) {
			self::$collector->record_inner_sanitization( $block_slug, $inner_html );
		}

		return (string) $inner_html;
	}
}
