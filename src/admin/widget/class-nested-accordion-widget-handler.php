<?php
/**
 * Widget handler for Elementor nested accordion widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Admin_Settings;
use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor nested accordion widget.
 */
class Nested_Accordion_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor nested accordion to Gutenberg block.
	 *
	 * Each item becomes a core/details block whose summary is the item title from
	 * `settings.items` and whose inner blocks are the converted item container.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$elements = is_array( $element['elements'] ?? null ) ? array_values( $element['elements'] ) : array();
		$items    = is_array( $settings['items'] ?? null ) ? array_values( $settings['items'] ) : array();

		$details_blocks = '';
		foreach ( $elements as $index => $item_container ) {
			if ( ! is_array( $item_container ) ) {
				continue;
			}

			$details_blocks .= $this->build_details_block(
				$this->get_item_title( $items[ $index ] ?? array(), $item_container, $index ),
				$this->convert_item_content( $item_container )
			);
		}

		if ( '' === $details_blocks ) {
			return '';
		}

		return Block_Builder::build( 'group', $this->get_wrapper_attributes( $settings ), $details_blocks );
	}

	/**
	 * Get the title of an accordion item.
	 *
	 * @param mixed $item           The matching entry of `settings.items`.
	 * @param array $item_container The item container element.
	 * @param int   $index          The zero-based item position.
	 *
	 * @return string The item title.
	 */
	private function get_item_title( $item, array $item_container, int $index ): string {
		$title = is_array( $item ) && isset( $item['item_title'] ) ? trim( (string) $item['item_title'] ) : '';

		if ( '' === $title && isset( $item_container['settings']['_title'] ) ) {
			$title = trim( (string) $item_container['settings']['_title'] );
		}

		if ( '' === $title ) {
			/* translators: %d: accordion item number. */
			$title = sprintf( __( 'Item #%d', 'migrate-off-elementor' ), $index + 1 );
		}

		return $title;
	}

	/**
	 * Convert the content of an accordion item container into blocks.
	 *
	 * @param array $item_container The item container element.
	 *
	 * @return string The converted block markup.
	 */
	private function convert_item_content( array $item_container ): string {
		if ( ! empty( $item_container['elements'] ) && is_array( $item_container['elements'] ) ) {
			return trim( Admin_Settings::instance()->parse_nested_elements( array( $item_container ) ) );
		}

		if ( isset( $item_container['settings']['content'] ) ) {
			$content = trim( wp_kses_post( (string) $item_container['settings']['content'] ) );

			return '' === $content ? '' : Block_Builder::build( 'html', array(), $content );
		}

		return '';
	}

	/**
	 * Build a core/details block that matches the block's save() output.
	 *
	 * @param string $title         The summary text.
	 * @param string $inner_blocks  The serialized inner blocks.
	 *
	 * @return string The serialized details block.
	 */
	private function build_details_block( string $title, string $inner_blocks ): string {
		return sprintf(
			"<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>%s</summary>%s</details>\n<!-- /wp:details -->\n",
			esc_html( $title ),
			$inner_blocks
		);
	}

	/**
	 * Map the accordion's box settings onto the wrapper group attributes.
	 *
	 * @param array $settings The widget settings.
	 *
	 * @return array The group block attributes.
	 */
	private function get_wrapper_attributes( array $settings ): array {
		$box_settings = array(
			'padding'       => $settings['accordion_padding'] ?? null,
			'_margin'       => $settings['_margin'] ?? null,
			'border_radius' => $settings['accordion_border_radius'] ?? null,
			'border_width'  => $settings['accordion_border_normal_width'] ?? null,
			'border_color'  => $settings['accordion_border_normal_color'] ?? '',
			'_css_classes'  => $settings['_css_classes'] ?? '',
			'_element_id'   => $settings['_element_id'] ?? '',
		);

		if ( ! empty( $settings['accordion_border_normal_border'] ) ) {
			$box_settings['_border_border'] = $settings['accordion_border_normal_border'];
		}

		$attributes = Style_Parser::parse_container_styles( array_filter( $box_settings, static fn( $value ) => null !== $value && '' !== $value ) );

		return array_intersect_key( $attributes, array_flip( array( 'style', 'className', 'anchor' ) ) );
	}
}
