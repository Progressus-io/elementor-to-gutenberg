<?php
/**
 * Generic safe mappings for selected Elementor widgets.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Generic handler for small safe Elementor-to-core mappings.
 */
class Generic_Elementor_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Convert supported Elementor widgets.
	 *
	 * @param array $element Elementor element data.
	 */
	public function handle( array $element ): string {
		$widget_type = isset( $element['widgetType'] ) && \is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		if ( '' === $widget_type ) {
			return '';
		}

		$settings = isset( $element['settings'] ) && \is_array( $element['settings'] ) ? $element['settings'] : array();

		switch ( $widget_type ) {
			case 'soundcloud':
				return $this->handle_soundcloud( $settings );
			case 'testimonial':
				return $this->handle_testimonial( $settings );
			case 'alert':
				return $this->handle_alert( $settings );
			case 'rating':
				return $this->handle_rating( $settings );
			case 'image-carousel':
			case 'image_carousel':
				return $this->handle_image_carousel( $settings );
			default:
				return '';
		}
	}

	/**
	 * Build soundcloud -> core/embed.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_soundcloud( array $settings ): string {
		$url = $this->extract_url( $settings, array( 'link', 'url' ) );
		if ( '' === $url ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/embed',
				'attrs'        => array( 'url' => $url ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build testimonial -> core/quote with inner paragraph.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_testimonial( array $settings ): string {
		$content = $this->extract_text( $settings, array( 'content', 'testimonial_content' ) );
		if ( '' === $content ) {
			return '';
		}

		$paragraph = $this->build_paragraph_block( $content );
		if ( array() === $paragraph ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/quote',
				'attrs'        => array(),
				'innerBlocks'  => array( $paragraph ),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build alert -> core/group with inner paragraph.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_alert( array $settings ): string {
		$text = $this->extract_text( $settings, array( 'text', 'message' ) );
		if ( '' === $text ) {
			return '';
		}

		$paragraph = $this->build_paragraph_block( $text );
		if ( array() === $paragraph ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array( $paragraph ),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build rating -> paragraph stars.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_rating( array $settings ): string {
		$rating = $this->extract_rating_value( $settings, array( 'rating', 'value' ) );
		if ( null === $rating ) {
			return '';
		}

		$stars = \str_repeat( '★', $rating ) . \str_repeat( '☆', 5 - $rating );

		return $this->serialize_parsed_block( $this->build_paragraph_block( $stars ) );
	}

	/**
	 * Build image carousel -> core/gallery with inner core/image blocks.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_image_carousel( array $settings ): string {
		$ids = $this->extract_image_ids( $settings );
		if ( array() === $ids ) {
			return '';
		}

		$inner_blocks = array();
		foreach ( $ids as $id ) {
			$inner_blocks[] = array(
				'blockName'    => 'core/image',
				'attrs'        => array( 'id' => $id ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/gallery',
				'attrs'        => array(),
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build a core/paragraph parsed block from plain text.
	 *
	 * @param string $content Plain text content.
	 */
	private function build_paragraph_block( string $content ): array {
		$content = \trim( $content );
		if ( '' === $content ) {
			return array();
		}

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array( 'content' => $content ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Serialize a parsed block via core serializer only.
	 *
	 * @param array $block Parsed block array.
	 */
	private function serialize_parsed_block( array $block ): string {
		if ( array() === $block || ! \function_exists( 'serialize_block' ) ) {
			return '';
		}

		return \serialize_block( $block ) . "\n";
	}

	/**
	 * Extract sanitized plain text by preferred keys.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_text( array $settings, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! \is_string( $settings[ $key ] ) ) {
				continue;
			}

			$text = \wp_strip_all_tags( $settings[ $key ] );
			$text = \trim( $text );
			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Extract sanitized URL from string or Elementor URL array.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_url( array $settings, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$candidate = '';
			if ( \is_string( $settings[ $key ] ) ) {
				$candidate = $settings[ $key ];
			} elseif ( \is_array( $settings[ $key ] ) && \is_string( $settings[ $key ]['url'] ?? null ) ) {
				$candidate = $settings[ $key ]['url'];
			}

			$candidate = \trim( $candidate );
			if ( '' === $candidate ) {
				continue;
			}

			$url = \esc_url_raw( $candidate );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Extract rating value 1-5.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_rating_value( array $settings, array $keys ): ?int {
		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! \is_numeric( $settings[ $key ] ) ) {
				continue;
			}

			$value = (int) $settings[ $key ];
			if ( $value >= 1 && $value <= 5 ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Extract unique positive media IDs from carousel/slides/images arrays.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<int>
	 */
	private function extract_image_ids( array $settings ): array {
		$keys = array( 'carousel', 'slides', 'images' );
		$ids  = array();

		foreach ( $keys as $key ) {
			$items = $settings[ $key ] ?? null;
			if ( ! \is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( ! \is_array( $item ) ) {
					continue;
				}

				$id = \absint( $item['id'] ?? 0 );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return \array_values( \array_unique( $ids ) );
	}
}
