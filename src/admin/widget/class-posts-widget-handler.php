<?php
/**
 * Widget handler for Elementor posts widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor posts widget.
 */
class Posts_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor posts widget to Gutenberg Query Loop block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		// Determine skin from settings (classic, cards, full_content)
		$skin = 'classic';
		if ( isset( $settings['_skin'] ) ) {
			$skin = (string) $settings['_skin'];
		} elseif ( isset( $settings['cards_meta_separator'] ) && '' !== $settings['cards_meta_separator'] ) {
			$skin = 'cards';
		} elseif ( isset( $settings['full_content_meta_separator'] ) && '' !== $settings['full_content_meta_separator'] ) {
			$skin = 'full_content';
		}

		// Get posts per page (default 10)
		$posts_per_page = isset( $settings['posts_per_page'] ) ? (int) $settings['posts_per_page'] : 10;
		if ( isset( $settings[$skin . '_posts_per_page'] ) ) {
			$posts_per_page = (int) $settings[$skin . '_posts_per_page'];
		}
		if ( $posts_per_page <= 0 ) {
			$posts_per_page = 10;
		}

		// Get order and orderby
		$order_by = isset( $settings['orderby'] ) ? (string) $settings['orderby'] : 'date';
		if ( isset( $settings[$skin . '_orderby'] ) ) {
			$order_by = (string) $settings[$skin . '_orderby'];
		}
		
		$order = isset( $settings['order'] ) ? strtoupper( (string) $settings['order'] ) : 'DESC';
		if ( isset( $settings[$skin . '_order'] ) ) {
			$order = strtoupper( (string) $settings[$skin . '_order'] );
		}

		// Get columns (default 3)
		$columns = 3;
		if ( isset( $settings['columns'] ) ) {
			$columns = (int) $settings['columns'];
		} elseif ( isset( $settings[$skin . '_columns'] ) ) {
			$columns = (int) $settings[$skin . '_columns'];
		}
		if ( $columns < 1 ) {
			$columns = 3;
		}
		if ( $columns > 6 ) {
			$columns = 6;
		}

		// Build query attributes
		$offset = 0;
		if ( isset( $settings['offset'] ) ) {
			$offset = (int) $settings['offset'];
		}

		$query_attrs = array(
			'perPage'  => $posts_per_page,
			'pages'    => 0,
			'offset'   => $offset,
			'postType' => 'post',
			'order'    => strtolower( $order ),
			'orderBy'  => $order_by,
			'author'   => '',
			'search'   => '',
			'exclude'  => array(),
			'sticky'   => '',
			'inherit'  => false,
		);

		// Handle categories
		if ( ! empty( $settings['category'] ) ) {
			if ( is_array( $settings['category'] ) ) {
				$query_attrs['categoryIds'] = array_map( 'intval', $settings['category'] );
			}
		}

		// Handle excluded categories
		if ( ! empty( $settings['exclude_category'] ) ) {
			if ( is_array( $settings['exclude_category'] ) ) {
				$query_attrs['excludeCategoryIds'] = array_map( 'intval', $settings['exclude_category'] );
			}
		}

		// Handle tags
		if ( ! empty( $settings['tags'] ) ) {
			if ( is_array( $settings['tags'] ) ) {
				$query_attrs['tagIds'] = array_map( 'intval', $settings['tags'] );
			}
		}

		// Handle excluded tags
		if ( ! empty( $settings['exclude_tags'] ) ) {
			if ( is_array( $settings['exclude_tags'] ) ) {
				$query_attrs['excludeTagIds'] = array_map( 'intval', $settings['exclude_tags'] );
			}
		}

		// Build query block attributes
		$query_block_attrs = array(
			'queryId' => 0,
			'query'   => $query_attrs,
		);

		// Add class name based on skin
		$class_names = array( 'wp-block-query' );
		if ( 'cards' === $skin ) {
			$class_names[] = 'is-style-cards';
		} elseif ( 'full_content' === $skin ) {
			$class_names[] = 'is-style-full-content';
		}

		$query_block_attrs['className'] = implode( ' ', $class_names );

		// Build inner blocks for post template
		$inner_blocks = $this->build_post_template_blocks( $settings, $columns );

		// Build the Query Loop block with nested blocks
		return Block_Builder::build(
			'query',
			$query_block_attrs,
			$inner_blocks,
			array( 'raw' => true )
		);
	}

	/**
	 * Build inner blocks for the post template based on settings.
	 *
	 * @param array $settings Element settings.
	 * @param int   $columns Number of columns.
	 * @return string Inner blocks markup.
	 */
	private function build_post_template_blocks( array $settings, int $columns ): string {
		$template_blocks = array();

		// Determine skin
		$skin = 'classic';
		if ( isset( $settings['_skin'] ) ) {
			$skin = (string) $settings['_skin'];
		} elseif ( isset( $settings['cards_meta_separator'] ) ) {
			$skin = 'cards';
		} elseif ( isset( $settings['full_content_meta_separator'] ) ) {
			$skin = 'full_content';
		}

		// Show featured image (default true)
		$show_image_key = $skin . '_show_image';
		$show_image = ! isset( $settings[$show_image_key] ) || 'yes' === $settings[$show_image_key];
		
		// Show title (default true)
		$show_title_key = $skin . '_show_title';
		$show_title = ! isset( $settings[$show_title_key] ) || 'yes' === $settings[$show_title_key];
		
		// Show excerpt (default true)
		$show_excerpt_key = $skin . '_show_excerpt';
		$show_excerpt = ! isset( $settings[$show_excerpt_key] ) || 'yes' === $settings[$show_excerpt_key];
		
		// Show date (default true)
		$show_date_key = $skin . '_show_date';
		$show_date = ! isset( $settings[$show_date_key] ) || 'yes' === $settings[$show_date_key];
		
		// Show author (default true)
		$show_author_key = $skin . '_show_author';
		$show_author = ! isset( $settings[$show_author_key] ) || 'yes' === $settings[$show_author_key];
		
		// Show read more (default true)
		$show_read_more_key = $skin . '_show_read_more';
		$show_read_more = ! isset( $settings[$show_read_more_key] ) || 'yes' === $settings[$show_read_more_key];

		// Build Post Template block
		$post_template_blocks = array();

		// Featured Image
		if ( $show_image ) {
			$post_template_blocks[] = Block_Builder::build(
				'post-featured-image',
				array(
					'isLink'      => true,
					'aspectRatio' => 'auto',
				),
				''
			);
		}

		// Post Title
		if ( $show_title ) {
			$post_template_blocks[] = Block_Builder::build(
				'post-title',
				array(
					'isLink'   => true,
					'level'    => 2,
				),
				''
			);
		}

		// Post Meta (Date and Author)
		if ( $show_date || $show_author ) {
			$post_date_blocks = array();
			
			if ( $show_date ) {
				$post_date_blocks[] = Block_Builder::build(
					'post-date',
					array(),
					''
				);
			}

			if ( $show_author ) {
				$post_date_blocks[] = Block_Builder::build(
					'post-author',
					array(
						'showAvatar' => false,
					),
					''
				);
			}

			// Wrap meta items in a group
			if ( ! empty( $post_date_blocks ) ) {
				$post_template_blocks[] = Block_Builder::build(
					'group',
					array(
						'className' => 'post-meta',
						'layout'    => array( 'type' => 'flex' ),
					),
					implode( "\n", $post_date_blocks )
				);
			}
		}

		// Post Excerpt
		if ( $show_excerpt ) {
			$excerpt_attrs = array();
			
			// Get excerpt length
			$excerpt_length_key = $skin . '_excerpt_length';
			if ( isset( $settings[$excerpt_length_key] ) ) {
				$excerpt_length = (int) $settings[$excerpt_length_key];
				if ( $excerpt_length > 0 ) {
					$excerpt_attrs['excerptLength'] = $excerpt_length;
				}
			}

			$post_template_blocks[] = Block_Builder::build(
				'post-excerpt',
				$excerpt_attrs,
				''
			);
		}

		// Read More button
		if ( $show_read_more ) {
			$read_more_text_key = $skin . '_read_more_text';
			$read_more_text = isset( $settings[$read_more_text_key] ) 
				? (string) $settings[$read_more_text_key] 
				: 'Read More »';
			
			$button_html = '<a class="wp-block-button__link wp-element-button"></a>';
			$button_inner = Block_Builder::build(
				'button',
				array(),
				$button_html
			);
			$post_template_blocks[] = Block_Builder::build(
				'buttons',
				array(),
				$button_inner
			);
		}

		// Post Template wrapper
		$post_template = Block_Builder::build(
			'post-template',
			array(
				'layout' => array(
					'type'        => 'grid',
					'columnCount' => $columns,
				),
			),
			implode( "\n", $post_template_blocks ),
			array( 'raw' => true )
		);

		$template_blocks[] = $post_template;

		// Handle pagination
		$pagination_type = isset( $settings['pagination_type'] ) ? (string) $settings['pagination_type'] : '';
		
		if ( '' !== $pagination_type && 'none' !== $pagination_type ) {
			$pagination_attrs = array();
			
			// Map pagination types
			if ( 'numbers' === $pagination_type ) {
				$pagination_attrs['paginationArrow'] = 'none';
			} elseif ( 'prev_next' === $pagination_type ) {
				$pagination_attrs['paginationArrow'] = 'arrow';
			} elseif ( 'numbers_and_prev_next' === $pagination_type ) {
				$pagination_attrs['paginationArrow'] = 'arrow';
			}

			$template_blocks[] = Block_Builder::build(
				'query-pagination',
				$pagination_attrs,
				$this->build_pagination_blocks( $settings, $pagination_type )
			);
		}

		// No results block
		$no_posts_msg = isset( $settings['nothing_found_message'] ) 
			? (string) $settings['nothing_found_message'] 
			: 'No posts found.';
		$paragraph_inner = Block_Builder::build(
			'paragraph',
			array(),
			'<p>' . esc_html( $no_posts_msg ) . '</p>'
		);
		$template_blocks[] = Block_Builder::build(
			'query-no-results',
			array(),
			$paragraph_inner
		);

		return implode( "\n", $template_blocks );
	}

	/**
	 * Build pagination blocks based on settings.
	 *
	 * @param array  $settings Element settings.
	 * @param string $pagination_type Type of pagination.
	 * @return string Pagination blocks markup.
	 */
	private function build_pagination_blocks( array $settings, string $pagination_type ): string {
		$pagination_blocks = array();

		if ( 'prev_next' === $pagination_type || 'numbers_and_prev_next' === $pagination_type ) {
			$prev_label = isset( $settings['pagination_prev_label'] ) 
				? (string) $settings['pagination_prev_label'] 
				: '« Previous';

			$pagination_blocks[] = Block_Builder::build(
				'query-pagination-previous',
				array(
					'label' => $prev_label,
				),
				''
			);
		}

		if ( 'numbers' === $pagination_type || 'numbers_and_prev_next' === $pagination_type ) {
			$pagination_blocks[] = Block_Builder::build(
				'query-pagination-numbers',
				array(),
				''
			);
		}

		if ( 'prev_next' === $pagination_type || 'numbers_and_prev_next' === $pagination_type ) {
			$next_label = isset( $settings['pagination_next_label'] ) 
				? (string) $settings['pagination_next_label'] 
				: 'Next »';

			$pagination_blocks[] = Block_Builder::build(
				'query-pagination-next',
				array(
					'label' => $next_label,
				),
				''
			);
		}

		return implode( "\n", $pagination_blocks );
	}

	/**
	 * Check if value is a preset color slug.
	 *
	 * @param string $value The value to check.
	 * @return bool
	 */
	private function is_preset_color_slug( string $value ): bool {
		return preg_match( '/^[a-z][a-z0-9-]*$/', $value ) && ! preg_match( '/^#[0-9a-f]{3,8}$/i', $value );
	}
}
