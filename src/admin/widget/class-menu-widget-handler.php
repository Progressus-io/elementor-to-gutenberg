<?php
/**
 * Menu Widget Handler
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor nav-menu widget.
 */
class Menu_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor nav-menu widget to Gutenberg navigation block.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';
		$element_id = isset( $element['id'] ) ? (string) $element['id'] : '';

		$menu_name   = $settings['menu_name'] ?? $settings['menu'] ?? '';
		$menu_object = null;

		if ( ! empty( $menu_name ) ) {
			$menu_object = wp_get_nav_menu_object( $menu_name );
		}

		$navigation_post_id = $this->get_or_create_navigation_post( $menu_object, $menu_name, $element_id );

		if ( $menu_object ) {
			$this->sync_navigation_items( $navigation_post_id, $menu_object );
		}

		// Build block attributes for the navigation reference only.
		$attributes = array(
			'ref' => $navigation_post_id,
		);

		// Encode attributes for the block.
		$attributes_json = wp_json_encode( $attributes );

		// Generate the complete navigation block markup (self-closing).
		$block_content = '<!-- wp:navigation ' . $attributes_json . ' /-->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content . "\n";
	}

	/**
	 * Create or reuse a wp_navigation post for a given menu.
	 *
	 * @param \WP_Term|null $menu_object Menu term object when resolved.
	 * @param string $menu_name Menu name or slug from settings.
	 * @param string $element_id Elementor element id for fallback naming.
	 *
	 * @return int Navigation post ID.
	 */
	private function get_or_create_navigation_post( $menu_object, string $menu_name, string $element_id ): int {
		$post_id = 0;

		if ( $menu_object ) {
			$existing = get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'post_status'    => 'any',
					'meta_key'       => 'etg_source_menu_term_id',
					'meta_value'     => $menu_object->term_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$post_id = (int) $existing[0];
			}
		}

		if ( ! $post_id && ! empty( $menu_name ) ) {
			$slug     = sanitize_title( $menu_name );
			$existing = get_page_by_path( $slug, OBJECT, 'wp_navigation' );
			if ( $existing ) {
				$post_id = (int) $existing->ID;
			}
		}

		if ( ! $post_id && ! empty( $element_id ) ) {
			$slug     = sanitize_title( 'etg-menu-' . $element_id );
			$existing = get_page_by_path( $slug, OBJECT, 'wp_navigation' );
			if ( $existing ) {
				$post_id = (int) $existing->ID;
			}
		}

		if ( $post_id ) {
			return $post_id;
		}

		$post_title = $this->resolve_navigation_title( $menu_object, $menu_name, $element_id );
		$post_name  = sanitize_title( $post_title );
		$post_name  = wp_unique_post_slug( $post_name, 0, 'publish', 'wp_navigation', 0 );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'wp_navigation',
				'post_status' => 'publish',
				'post_title'  => $post_title,
				'post_name'   => $post_name,
			)
		);

		if ( $menu_object ) {
			update_post_meta( $post_id, 'etg_source_menu_term_id', $menu_object->term_id );
		}

		return (int) $post_id;
	}

	/**
	 * Resolve deterministic navigation title.
	 *
	 * @param \WP_Term|null $menu_object Menu term object when resolved.
	 * @param string $menu_name Menu name or slug from settings.
	 * @param string $element_id Elementor element id for fallback naming.
	 *
	 * @return string
	 */
	private function resolve_navigation_title( $menu_object, string $menu_name, string $element_id ): string {
		if ( $menu_object && ! empty( $menu_object->name ) ) {
			return $menu_object->name;
		}

		if ( ! empty( $menu_name ) ) {
			return $menu_name;
		}

		if ( ! empty( $element_id ) ) {
			return 'ETG Menu ' . $element_id;
		}

		return 'ETG Menu';
	}

	/**
	 * Sync navigation items to a wp_navigation post.
	 *
	 * @param int $navigation_post_id Navigation post ID.
	 * @param \WP_Term $menu_object Menu term object.
	 *
	 * @return void
	 */
	private function sync_navigation_items( int $navigation_post_id, $menu_object ): void {
		$menu_items = wp_get_nav_menu_items( $menu_object->term_id );
		if ( empty( $menu_items ) ) {
			wp_update_post(
				array(
					'ID'           => $navigation_post_id,
					'post_content' => '',
				)
			);

			return;
		}

		$items_by_id = array();
		foreach ( $menu_items as $menu_item ) {
			$items_by_id[ $menu_item->ID ] = array(
				'item'     => $menu_item,
				'children' => array(),
			);
		}

		$root_items = array();
		foreach ( $items_by_id as $item_id => $item_data ) {
			$parent_id = (int) $item_data['item']->menu_item_parent;
			if ( $parent_id && isset( $items_by_id[ $parent_id ] ) ) {
				$items_by_id[ $parent_id ]['children'][] = $item_id;
			} else {
				$root_items[] = $item_id;
			}
		}

		$blocks = array();
		foreach ( $root_items as $item_id ) {
			$blocks[] = $this->build_navigation_link_block( $items_by_id, $item_id );
		}

		$post_content = '';
		foreach ( $blocks as $block ) {
			$post_content .= serialize_block( $block );
		}

		wp_update_post(
			array(
				'ID'           => $navigation_post_id,
				'post_content' => $post_content,
			)
		);
	}

	/**
	 * Build a navigation-link block with nested items.
	 *
	 * @param array $items_by_id Items keyed by ID with children.
	 * @param int $item_id Menu item ID.
	 *
	 * @return array
	 */
	private function build_navigation_link_block( array $items_by_id, int $item_id ): array {
		$item         = $items_by_id[ $item_id ]['item'];
		$children_ids = $items_by_id[ $item_id ]['children'];

		$inner_blocks = array();
		foreach ( $children_ids as $child_id ) {
			$inner_blocks[] = $this->build_navigation_link_block( $items_by_id, $child_id );
		}

		$attributes = array(
			'label' => $item->title,
			'url'   => $item->url,
		);

		if ( '_blank' === $item->target ) {
			$attributes['opensInNewTab'] = true;
		}

		return array(
			'blockName'    => 'core/navigation-link',
			'attrs'        => $attributes,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

}
