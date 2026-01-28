<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function absint;
use function esc_attr;
use function sanitize_title;
use function sanitize_key;

defined( 'ABSPATH' ) || exit;

class Woo_Products_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		$columns = absint( $settings['columns'] ?? 0 );
		$rows    = absint( $settings['rows'] ?? 0 );
		$order   = $this->normalize_order( (string) ( $settings['order'] ?? '' ) );
		$orderby = $this->normalize_orderby( (string) ( $settings['orderby'] ?? '' ) );

		$category = $this->normalize_terms_csv( $settings['categories'] ?? null );
		$tag      = $this->normalize_terms_csv( $settings['tags'] ?? null );

		$attrs = array();

		if ( $columns > 0 ) {
			$attrs['columns'] = (string) $columns;
		}

		if ( $columns > 0 && $rows > 0 ) {
			$attrs['limit'] = (string) ( $columns * $rows );
		}

		if ( '' !== $order ) {
			$attrs['order'] = $order;
		}

		if ( '' !== $orderby ) {
			$attrs['orderby'] = $orderby;
		}

		if ( '' !== $category ) {
			$attrs['category'] = $category;
		}

		if ( '' !== $tag ) {
			$attrs['tag'] = $tag;
		}

		$shortcode = $this->build_shortcode( 'products', $attrs );

		// Prefer WooCommerce Product Collection block when available.
		if ( $this->is_block_registered( 'woocommerce/product-collection' ) ) {
			$limit = 0;
			if ( $columns > 0 && $rows > 0 ) {
				$limit = absint( $columns * $rows );
			}

			$block_attrs = $this->build_product_collection_attrs(
				$element,
				$columns,
				$limit,
				'' === $order ? 'desc' : $order,
				'' === $orderby ? 'date' : $orderby,
				$category,
				$tag
			);

			$template = $this->get_product_collection_template();

			return $this->serialize_block( 'woocommerce/product-collection', $block_attrs, $template );
		}

		// Fallback to shortcode for older sites without Woo blocks.
		return $this->serialize_block( 'core/shortcode', array(), $shortcode );
	}

	private function normalize_order( string $value ): string {
		$value = strtolower( trim( $value ) );

		return in_array( $value, array( 'asc', 'desc' ), true ) ? $value : '';
	}

	private function normalize_orderby( string $value ): string {
		$value   = sanitize_key( $value );
		$allowed = array( 'date', 'title', 'id', 'menu_order', 'rand', 'price', 'popularity', 'rating' );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	private function normalize_terms_csv( $value ): string {
		$terms = array();

		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					if ( isset( $item['slug'] ) ) {
						$item = $item['slug'];
					} elseif ( isset( $item['value'] ) ) {
						$item = $item['value'];
					}
				}

				$slug = sanitize_title( (string) $item );
				if ( '' !== $slug ) {
					$terms[] = $slug;
				}
			}
		}

		$terms = array_values( array_unique( $terms ) );

		return empty( $terms ) ? '' : implode( ',', $terms );
	}

	private function build_shortcode( string $tag, array $attrs ): string {
		$shortcode = '[' . sanitize_key( $tag );

		foreach ( $attrs as $key => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$shortcode .= sprintf( ' %s="%s"', sanitize_key( (string) $key ), esc_attr( (string) $value ) );
		}

		$shortcode .= ']';

		return $shortcode;
	}

	private function build_product_collection_attrs(
		array $element,
		int $columns,
		int $per_page,
		string $order,
		string $orderby,
		string $category_csv,
		string $tag_csv
	): array {
		$query_id = $this->build_query_id_from_element( $element );

		$tax_query = array();

		$categories = '' !== $category_csv ? array_filter( array_map( 'trim', explode( ',', $category_csv ) ) ) : array();
		$tags       = '' !== $tag_csv ? array_filter( array_map( 'trim', explode( ',', $tag_csv ) ) ) : array();

		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_values( $categories ),
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tags ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => array_values( $tags ),
				'operator' => 'IN',
			);
		}

		$cols = $columns > 0 ? $columns : 3;
		$pp   = $per_page > 0 ? $per_page : ( $cols * 1 );

		return array(
			'queryId'       => $query_id,
			'query'         => array(
				'perPage'                       => $pp,
				'pages'                         => 0,
				'offset'                        => 0,
				'postType'                      => 'product',
				'order'                         => $order,
				'orderBy'                       => $orderby,
				'search'                        => '',
				'exclude'                       => array(),
				'inherit'                       => false,
				'taxQuery'                      => $tax_query,
				'isProductCollectionBlock'      => true,
				'featured'                      => false,
				'woocommerceOnSale'             => false,
				'woocommerceStockStatus'        => array( 'instock', 'outofstock', 'onbackorder' ),
				'woocommerceAttributes'         => array(),
				'woocommerceHandPickedProducts' => array(),
			),
			'tagName'       => 'div',
			'displayLayout' => array(
				'type'          => 'flex',
				'columns'       => $cols,
				'shrinkColumns' => true,
			),
			'dimensions'    => array(
				'widthType' => 'fill',
			),
		);
	}

	private function build_query_id_from_element( array $element ): int {
		$raw_id = isset( $element['id'] ) ? (string) $element['id'] : '';

		if ( '' !== $raw_id && ctype_xdigit( $raw_id ) ) {
			return absint( hexdec( substr( $raw_id, 0, 8 ) ) );
		}

		if ( '' === $raw_id ) {
			return 1;
		}

		return absint( sprintf( '%u', crc32( $raw_id ) ) );
	}

	private function get_product_collection_template(): string {
		return
			"<div class=\"wp-block-woocommerce-product-collection\">\n" .
			"<!-- wp:woocommerce/product-template -->\n" .

			"<!-- wp:woocommerce/product-image {\"showSaleBadge\":false,\"isDescendentOfQueryLoop\":true} -->\n" .
			"<!-- wp:woocommerce/product-sale-badge {\"align\":\"right\"} /-->\n" .
			"<!-- /wp:woocommerce/product-image -->\n" .

			"<!-- wp:post-title {\"level\":3,\"isLink\":true,\"__woocommerceNamespace\":\"woocommerce/product-collection/product-title\"} /-->\n" .

			"<!-- wp:woocommerce/product-price {\"isDescendentOfQueryLoop\":true} /-->\n" .
			"<!-- wp:woocommerce/product-button {\"isDescendentOfQueryLoop\":true} /-->\n" .

			"<!-- /wp:woocommerce/product-template -->\n" .
			"</div>\n";
	}

}
