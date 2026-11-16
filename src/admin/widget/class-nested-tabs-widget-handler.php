<?php
/**
 * Widget handler for Elementor Nested tab widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Admin_Settings;
use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function serialize_block_attributes;
use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Converts the Elementor nested tabs widget into a blockshift/tabs block whose
 * tabs are blockshift/tab blocks holding the converted tab containers.
 */
class Nested_Tabs_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Header style the blockshift/tabs save() writes with its default attributes.
	 */
	private const HEADER_STYLE = 'padding:12px 16px 12px 16px;margin:0px 2px 0px 0px;cursor:pointer;border:1px solid #ddd;border-radius:4px;font-size:16px;font-weight:normal;line-height:1.5';

	/**
	 * Content style the blockshift/tabs save() writes with its default attributes, minus border-top.
	 */
	private const CONTENT_STYLE = 'padding:20px 20px 20px 20px;margin:0px 0px 0px 0px;border:1px solid #ddd;border-radius:4px;border-top:%s;font-size:14px;font-weight:normal;line-height:1.6';

	/**
	 * Convert an Elementor nested tabs widget.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$containers = isset( $element['elements'] ) && is_array( $element['elements'] ) ? array_values( $element['elements'] ) : array();
		$items      = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? array_values( $settings['tabs'] ) : array();

		$titles = array();
		$panels = '';
		foreach ( $containers as $index => $container ) {
			if ( ! is_array( $container ) ) {
				continue;
			}

			$title    = $this->get_tab_title( $items[ $index ] ?? array(), count( $titles ) );
			$titles[] = $title;
			$panels  .= $this->build_tab_block( $this->convert_tab_content( $container ) );
		}

		if ( empty( $titles ) ) {
			return '';
		}

		$active = 0;
		if ( isset( $settings['active_tab'] ) && is_numeric( $settings['active_tab'] ) ) {
			$active = (int) $settings['active_tab'];
		}
		$active = min( max( 0, $active ), count( $titles ) - 1 );

		$direction = isset( $settings['tabs_direction'] ) ? (string) $settings['tabs_direction'] : '';
		$tab_style = in_array( $direction, array( 'inline-start', 'inline-end' ), true ) ? 'vertical' : 'horizontal';

		$attrs = array(
			'tabs'      => array_map(
				static function ( string $title ): array {
					return array( 'title' => $title );
				},
				$titles
			),
			'innerTabs' => true,
			'activeTab' => $active,
		);
		if ( 'vertical' === $tab_style ) {
			$attrs['tabStyle'] = 'vertical';
		}

		return '<!-- wp:blockshift/tabs ' . serialize_block_attributes( $attrs ) . " -->\n"
			. $this->render_tabs_markup( $titles, $panels, $active, $tab_style )
			. "\n<!-- /wp:blockshift/tabs -->\n";
	}

	/**
	 * Get the title of a tab from its entry in `settings.tabs`.
	 *
	 * @param mixed $item  The matching entry of `settings.tabs`.
	 * @param int   $index The zero-based tab position.
	 *
	 * @return string Plain-text tab title.
	 */
	private function get_tab_title( $item, int $index ): string {
		$title = is_array( $item ) && isset( $item['tab_title'] ) ? (string) $item['tab_title'] : '';
		$title = trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $title ) {
			/* translators: %d: tab number. */
			$title = sprintf( __( 'Tab %d', 'migrate-off-elementor' ), $index + 1 );
		}

		return $title;
	}

	/**
	 * Convert the container of one tab through the normal element pipeline.
	 *
	 * @param array $container The tab container element.
	 *
	 * @return string Serialized inner blocks.
	 */
	private function convert_tab_content( array $container ): string {
		if ( ! empty( $container['elements'] ) && is_array( $container['elements'] ) ) {
			return trim( Admin_Settings::instance()->parse_nested_elements( array( $container ) ) );
		}

		if ( isset( $container['settings']['content'] ) ) {
			$content = trim( wp_kses_post( (string) $container['settings']['content'] ) );

			return '' === $content ? '' : trim( Block_Builder::build( 'html', array(), $content ) );
		}

		return '';
	}

	/**
	 * Build one blockshift/tab block.
	 *
	 * @param string $inner_blocks Serialized inner blocks.
	 *
	 * @return string Serialized block.
	 */
	private function build_tab_block( string $inner_blocks ): string {
		return "<!-- wp:blockshift/tab -->\n"
			. '<div class="wp-block-blockshift-tab blockshift-tab-content">' . $inner_blocks . '</div>'
			. "\n<!-- /wp:blockshift/tab -->";
	}

	/**
	 * Render the markup blockshift/tabs save() writes when it holds tab blocks.
	 *
	 * @param array  $titles    Tab titles.
	 * @param string $panels    Serialized blockshift/tab blocks.
	 * @param int    $active    Active tab index.
	 * @param string $tab_style Either horizontal or vertical.
	 *
	 * @return string Block HTML.
	 */
	private function render_tabs_markup( array $titles, string $panels, int $active, string $tab_style ): string {
		$is_vertical   = 'vertical' === $tab_style;
		$wrapper_style = $is_vertical ? 'display:flex;flex-direction:row' : 'display:block;flex-direction:column';
		$headers_style = 'display:flex;flex-direction:' . ( $is_vertical ? 'column' : 'row' );
		$content_style = sprintf( self::CONTENT_STYLE, $is_vertical ? '1px solid #ddd' : 'none' );

		$headers = '';
		foreach ( $titles as $index => $title ) {
			$class    = 'blockshift-tab-header ' . ( $index === $active ? 'active' : '' );
			$headers .= '<div class="' . esc_attr( trim( $class ) ) . '" style="' . esc_attr( self::HEADER_STYLE ) . '" data-tab-index="' . esc_attr( (string) $index ) . '">' . esc_html( $title ) . '</div>';
		}

		return '<div class="wp-block-blockshift-tabs">'
			. '<div class="blockshift-tabs" style="' . esc_attr( $wrapper_style ) . '" data-tab-style="' . esc_attr( $tab_style ) . '" data-active-tab="' . esc_attr( (string) $active ) . '">'
			. '<div class="blockshift-tabs-headers" style="' . esc_attr( $headers_style ) . '">' . $headers . '</div>'
			. '<div class="blockshift-tabs-content" style="' . esc_attr( $content_style ) . '">' . $panels . '</div>'
			. '</div></div>';
	}
}
