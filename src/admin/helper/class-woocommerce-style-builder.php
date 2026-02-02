<?php
/**
 * Build scoped WooCommerce styles for converted widgets.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use Elementor\Plugin;
use Progressus\Gutenberg\Admin\Helper\Block_Output_Builder;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce style builder for Elementor widget conversion.
 */
class WooCommerce_Style_Builder {
	/**
	 * Register styles for the products widget.
	 *
	 * @param array $element Elementor widget data.
	 * @param string $widget_class Widget class name.
	 * @param string $page_class Page wrapper class.
	 *
	 * @return void
	 */
	public static function register_products_styles( array $element, string $widget_class, string $page_class ): void {
		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$settings = self::get_settings( $element );
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$column_gap = self::normalize_dimension(
			self::resolve_setting( $settings, 'woocommerce-products', 'column_gap', '30px' ),
			'px'
		);
		$row_gap    = self::normalize_dimension(
			self::resolve_setting( $settings, 'woocommerce-products', 'row_gap', '30px' ),
			'px'
		);

		$align = self::normalize_alignment(
			self::resolve_setting( $settings, 'woocommerce-products', 'align', 'left' )
		);

		$image_border_style  = self::sanitize_border_style(
			self::resolve_setting( $settings, 'woocommerce-products', 'image_border_border', '' )
		);
		$image_border_width  = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'image_border_width', array() ),
			'px'
		);
		$image_border_color  = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'image_border_color', '' )
		);
		$image_border_radius = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'image_border_radius', array() ),
			'px'
		);

		$title_color   = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'title_color', '' )
		);
		$title_spacing = self::normalize_dimension(
			self::resolve_setting( $settings, 'woocommerce-products', 'title_spacing', '' ),
			'px'
		);

		$price_color = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'price_color', '' )
		);

		$button_text_color       = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_text_color', '' )
		);
		$button_background_color = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_background_color', '' )
		);
		$button_border_color     = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_border_color', '' )
		);
		$button_border_style     = self::sanitize_border_style(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_border_border', '' )
		);
		$button_border_width     = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_border_width', array() ),
			'px'
		);
		$button_border_radius    = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_border_radius', array() ),
			'px'
		);
		$button_padding          = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'button_padding', array() ),
			'px'
		);

		$box_border_width  = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_border_width', array() ),
			'px'
		);
		$box_border_radius = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_border_radius', array() ),
			'px'
		);
		$box_padding       = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_padding', array() ),
			'px'
		);
		$box_background    = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_bg_color', '' )
		);
		$box_border_style  = self::sanitize_border_style(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_border_border', 'solid' )
		);
		$box_border_color  = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-products', 'box_border_color', '#000000' )
		);

		$prefix = self::build_prefix( $page_class, $widget_class );

		$gap_declarations = array();
		if ( null !== $column_gap ) {
			$gap_declarations['column-gap'] = $column_gap;
		}
		if ( null !== $row_gap ) {
			$gap_declarations['row-gap'] = $row_gap;
		}
		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product-template',
				'.wc-block-grid__products',
				'ul.wc-block-grid__products',
			),
			$gap_declarations,
			'woocommerce-products-gap'
		);

		$item_declarations = array();
		$border_width      = self::build_box_shorthand( $box_border_width );
		if ( '' !== $border_width ) {
			$item_declarations['border-width'] = $border_width;
		}
		if ( '' !== $box_border_style ) {
			$item_declarations['border-style'] = $box_border_style;
		}
		if ( '' !== $box_border_color ) {
			$item_declarations['border-color'] = $box_border_color;
		}
		$radius = self::build_box_shorthand( $box_border_radius );
		if ( '' !== $radius ) {
			$item_declarations['border-radius'] = $radius;
		}
		$padding = self::build_box_shorthand( $box_padding );
		if ( '' !== $padding ) {
			$item_declarations['padding'] = $padding;
		}
		if ( '' !== $box_background ) {
			$item_declarations['background-color'] = $box_background;
		}
		if ( '' !== $align ) {
			$item_declarations['text-align'] = $align;
		}
		$item_declarations['position'] = 'relative';

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-grid__product',
				'.wc-block-product',
				'.wc-block-product-template > li',
				'.wc-block-product-template .wc-block-product',
			),
			$item_declarations,
			'woocommerce-products-card'
		);

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product__onsale',
				'.wc-block-grid__product-onsale',
				'.onsale',
			),
			array(
				'position'      => 'absolute',
				'top'           => '12px',
				'right'         => '12px',
				'border-radius' => '999px',
			),
			'woocommerce-products-sale-badge'
		);

		$image_declarations = array();
		$image_border       = self::build_box_shorthand( $image_border_width );
		if ( '' !== $image_border ) {
			$image_declarations['border-width'] = $image_border;
		}
		if ( '' !== $image_border_style ) {
			$image_declarations['border-style'] = $image_border_style;
		}
		if ( '' !== $image_border_color ) {
			$image_declarations['border-color'] = $image_border_color;
		}
		$image_radius = self::build_box_shorthand( $image_border_radius );
		if ( '' !== $image_radius ) {
			$image_declarations['border-radius'] = $image_radius;
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product__image img',
				'.wc-block-grid__product-image img',
				'.wc-block-product-image img',
				'.woocommerce-loop-product__link img',
			),
			$image_declarations,
			'woocommerce-products-image'
		);

		$title_declarations = array();
		if ( '' !== $title_color ) {
			$title_declarations['color'] = $title_color;
		}
		if ( null !== $title_spacing ) {
			$title_declarations['margin-bottom'] = $title_spacing;
		} elseif ( '' === $title_color ) {
			$title_declarations['margin-bottom'] = '8px';
		}
		if ( empty( $settings['title_typography_font_size'] ) ) {
			$title_declarations['font-size'] = '16px';
		}
		if ( empty( $settings['title_typography_font_weight'] ) ) {
			$title_declarations['font-weight'] = '600';
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wp-block-post-title a',
				'.wc-block-product__title a',
				'.wc-block-grid__product-title a',
				'.woocommerce-loop-product__title a',
				'.woocommerce-loop-product__title a',
			),
			$title_declarations,
			'woocommerce-products-title-link'
		);

		$price_declarations = array();
		if ( '' !== $price_color ) {
			$price_declarations['color'] = $price_color;
		}
		if ( empty( $settings['price_typography_font_size'] ) ) {
			$price_declarations['font-size'] = '16px';
		}
		if ( empty( $settings['price_typography_font_weight'] ) ) {
			$price_declarations['font-weight'] = '600';
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product__price',
				'.wc-block-grid__product-price',
				'.wp-block-woocommerce-product-price',
				'.price',
			),
			$price_declarations,
			'woocommerce-products-price'
		);

		$button_declarations = array();
		if ( '' !== $button_text_color ) {
			$button_declarations['color'] = $button_text_color;
		}
		if ( '' !== $button_background_color ) {
			$button_declarations['background-color'] = $button_background_color;
		}
		if ( '' !== $button_border_style ) {
			$button_declarations['border-style'] = $button_border_style;
		}
		$button_border = self::build_box_shorthand( $button_border_width );
		if ( '' !== $button_border ) {
			$button_declarations['border-width'] = $button_border;
		}
		if ( '' !== $button_border_color ) {
			$button_declarations['border-color'] = $button_border_color;
		}
		$button_radius = self::build_box_shorthand( $button_border_radius );
		if ( '' !== $button_radius ) {
			$button_declarations['border-radius'] = $button_radius;
		}

		$button_padding_value = self::build_box_shorthand( $button_padding );
		if ( '' !== $button_padding_value ) {
			$button_declarations['padding'] = $button_padding_value;
		} elseif ( empty( $settings['button_padding'] ) ) {
			$button_declarations['padding'] = '12px 24px';
		}
		if ( empty( $settings['button_typography_font_size'] ) ) {
			$button_declarations['font-size'] = '14px';
		}
		if ( empty( $settings['button_typography_font_weight'] ) ) {
			$button_declarations['font-weight'] = '600';
		}
		if ( 'center' === $align ) {
			$button_declarations['margin-left']  = 'auto';
			$button_declarations['margin-right'] = 'auto';
			$button_declarations['display']      = 'inline-block';
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wp-block-woocommerce-product-button a',
				'.wc-block-grid__product-add-to-cart a',
				'.wc-block-grid__product-add-to-cart button',
				'.wp-block-button__link',
				'.add_to_cart_button',
			),
			$button_declarations,
			'woocommerce-products-button'
		);
	}

	/**
	 * Register styles for the product categories widget.
	 *
	 * @param array $element Elementor widget data.
	 * @param string $widget_class Widget class name.
	 * @param string $page_class Page wrapper class.
	 *
	 * @return void
	 */
	public static function register_categories_styles( array $element, string $widget_class, string $page_class ): void {
		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$settings = self::get_settings( $element );
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$column_gap = self::normalize_dimension(
			self::resolve_setting( $settings, 'wc-categories', 'column_gap', '30px' ),
			'px'
		);
		$row_gap    = self::normalize_dimension(
			self::resolve_setting( $settings, 'wc-categories', 'row_gap', '30px' ),
			'px'
		);
		$align      = self::normalize_alignment(
			self::resolve_setting( $settings, 'wc-categories', 'align', 'left' )
		);

		$image_border_style  = self::sanitize_border_style(
			self::resolve_setting( $settings, 'wc-categories', 'image_border_border', '' )
		);
		$image_border_width  = self::normalize_box_sides(
			self::resolve_setting( $settings, 'wc-categories', 'image_border_width', array() ),
			'px'
		);
		$image_border_radius = self::normalize_box_sides(
			self::resolve_setting( $settings, 'wc-categories', 'image_border_radius', array() ),
			'px'
		);
		$title_color         = self::resolve_color(
			self::resolve_setting( $settings, 'wc-categories', 'title_color', '' )
		);

		$prefix = self::build_prefix( $page_class, $widget_class );

		$gap_declarations = array();
		if ( null !== $column_gap ) {
			$gap_declarations['column-gap'] = $column_gap;
		}
		if ( null !== $row_gap ) {
			$gap_declarations['row-gap'] = $row_gap;
		}
		if ( '' !== $align ) {
			$gap_declarations['text-align'] = $align;
		}
		if ( ! empty( $gap_declarations ) ) {
			self::register_rule(
				$collector,
				$prefix,
				array(
					'.wc-block-product-categories-list',
					'.wc-block-product-categories__list',
					'ul.wc-block-product-categories-list',
				),
				$gap_declarations,
				'woocommerce-categories-gap'
			);
		}

		$image_declarations = array();
		$image_border       = self::build_box_shorthand( $image_border_width );
		if ( '' !== $image_border ) {
			$image_declarations['border-width'] = $image_border;
		}
		if ( '' !== $image_border_style ) {
			$image_declarations['border-style'] = $image_border_style;
		}
		$image_radius = self::build_box_shorthand( $image_border_radius );
		if ( '' !== $image_radius ) {
			$image_declarations['border-radius'] = $image_radius;
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product-categories__image img',
				'.wc-block-product-categories__image',
			),
			$image_declarations,
			'woocommerce-categories-image'
		);

		$title_declarations = array();
		if ( '' !== $title_color ) {
			$title_declarations['color'] = $title_color;
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.wc-block-product-categories__item-name',
				'.wc-block-product-categories-list a',
			),
			$title_declarations,
			'woocommerce-categories-title'
		);
	}

	/**
	 * Register styles for the add-to-cart widget.
	 *
	 * @param array $element Elementor widget data.
	 * @param string $widget_class Widget class name.
	 * @param string $page_class Page wrapper class.
	 *
	 * @return void
	 */
	public static function register_add_to_cart_styles( array $element, string $widget_class, string $page_class ): void {
		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$settings = self::get_settings( $element );
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$align = self::normalize_alignment(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'align', 'left' )
		);

		$text_color       = self::resolve_color(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'button_text_color', '' )
		);
		$background_color = self::resolve_color(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'background_color', '' )
		);
		$border_style     = self::sanitize_border_style(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'border_border', '' )
		);
		$border_width     = self::normalize_box_sides(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'border_width', array() ),
			'px'
		);
		$border_radius    = self::normalize_box_sides(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'border_radius', array() ),
			'px'
		);
		$padding          = self::normalize_box_sides(
			self::resolve_setting( $settings, 'wc-add-to-cart', 'text_padding', array() ),
			'px'
		);

		$prefix = self::build_prefix( $page_class, $widget_class );

		$button_declarations = array();
		if ( '' !== $text_color ) {
			$button_declarations['color'] = $text_color;
		}
		if ( '' !== $background_color ) {
			$button_declarations['background-color'] = $background_color;
		}
		if ( '' !== $border_style ) {
			$button_declarations['border-style'] = $border_style;
		}
		$border_width_value = self::build_box_shorthand( $border_width );
		if ( '' !== $border_width_value ) {
			$button_declarations['border-width'] = $border_width_value;
		}
		$border_radius_value = self::build_box_shorthand( $border_radius );
		if ( '' !== $border_radius_value ) {
			$button_declarations['border-radius'] = $border_radius_value;
		}
		$padding_value = self::build_box_shorthand( $padding );
		if ( '' !== $padding_value ) {
			$button_declarations['padding'] = $padding_value;
		}
		if ( 'center' === $align ) {
			$button_declarations['margin-left']  = 'auto';
			$button_declarations['margin-right'] = 'auto';
			$button_declarations['display']      = 'inline-block';
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.single_add_to_cart_button',
				'.wp-block-woocommerce-add-to-cart-form button',
				'.wp-block-woocommerce-add-to-cart-form a',
			),
			$button_declarations,
			'woocommerce-add-to-cart-button'
		);

		if ( '' !== $align ) {
			self::register_rule(
				$collector,
				$prefix,
				array(
					'.wp-block-woocommerce-add-to-cart-form',
					'.woocommerce div.product form.cart',
				),
				array( 'text-align' => $align ),
				'woocommerce-add-to-cart-align'
			);
		}
	}

	/**
	 * Register styles for the cart widget.
	 *
	 * @param array $element Elementor widget data.
	 * @param string $widget_class Widget class name.
	 * @param string $page_class Page wrapper class.
	 *
	 * @return void
	 */
	public static function register_cart_styles( array $element, string $widget_class, string $page_class ): void {
		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$settings = self::get_settings( $element );
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$background    = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_background_color', '' )
		);
		$border_style  = self::sanitize_border_style(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_border_type', '' )
		);
		$border_width  = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_border_width', array() ),
			'px'
		);
		$border_radius = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_border_radius', array() ),
			'px'
		);
		$padding       = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_padding', array() ),
			'px'
		);
		$margin        = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-cart', 'sections_margin', array() ),
			'px'
		);
		$fields_color  = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-cart', 'forms_fields_normal_color', '' )
		);

		$prefix = self::build_prefix( $page_class, $widget_class );

		$section_declarations = array();
		if ( '' !== $background ) {
			$section_declarations['background-color'] = $background;
		}
		if ( '' !== $border_style ) {
			$section_declarations['border-style'] = $border_style;
		}
		$border_width_value = self::build_box_shorthand( $border_width );
		if ( '' !== $border_width_value ) {
			$section_declarations['border-width'] = $border_width_value;
		}
		$border_radius_value = self::build_box_shorthand( $border_radius );
		if ( '' !== $border_radius_value ) {
			$section_declarations['border-radius'] = $border_radius_value;
		}
		$padding_value = self::build_box_shorthand( $padding );
		if ( '' !== $padding_value ) {
			$section_declarations['padding'] = $padding_value;
		}
		$margin_value = self::build_box_shorthand( $margin );
		if ( '' !== $margin_value ) {
			$section_declarations['margin'] = $margin_value;
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.woocommerce-cart-form',
				'.cart-collaterals',
				'.cart_totals',
				'.woocommerce-cart-form__contents',
			),
			$section_declarations,
			'woocommerce-cart-sections'
		);

		if ( '' !== $fields_color ) {
			self::register_rule(
				$collector,
				$prefix,
				array(
					'.woocommerce-cart-form label',
					'.woocommerce-cart-form input',
					'.woocommerce-cart-form select',
					'.woocommerce-cart-form textarea',
					'.cart-collaterals label',
					'.cart-collaterals input',
					'.cart-collaterals select',
					'.cart-collaterals textarea',
				),
				array( 'color' => $fields_color ),
				'woocommerce-cart-fields'
			);
		}
	}

	/**
	 * Register styles for the my account widget.
	 *
	 * @param array $element Elementor widget data.
	 * @param string $widget_class Widget class name.
	 * @param string $page_class Page wrapper class.
	 *
	 * @return void
	 */
	public static function register_my_account_styles( array $element, string $widget_class, string $page_class ): void {
		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$settings = self::get_settings( $element );
		Elementor_Fonts_Service::register_settings_fonts( $settings );

		$tabs_color          = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_normal_color', '' )
		);
		$tabs_border_style   = self::sanitize_border_style(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_border_type', '' )
		);
		$tabs_border_width   = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_border_width', array() ),
			'px'
		);
		$tabs_border_radius  = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_border_radius', array() ),
			'px'
		);
		$tabs_padding        = self::normalize_box_sides(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_padding', array() ),
			'px'
		);
		$tabs_divider        = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'tabs_divider_color', '' )
		);
		$sections_background = self::resolve_color(
			self::resolve_setting( $settings, 'woocommerce-my-account', 'my_account_sections_background_color', '' )
		);

		$prefix = self::build_prefix( $page_class, $widget_class );

		$nav_declarations = array();
		if ( '' !== $tabs_border_style ) {
			$nav_declarations['border-style'] = $tabs_border_style;
		}
		$tabs_border_value = self::build_box_shorthand( $tabs_border_width );
		if ( '' !== $tabs_border_value ) {
			$nav_declarations['border-width'] = $tabs_border_value;
		}
		$tabs_radius_value = self::build_box_shorthand( $tabs_border_radius );
		if ( '' !== $tabs_radius_value ) {
			$nav_declarations['border-radius'] = $tabs_radius_value;
		}

		self::register_rule(
			$collector,
			$prefix,
			array( '.woocommerce-MyAccount-navigation' ),
			$nav_declarations,
			'woocommerce-my-account-nav'
		);

		$nav_link_declarations = array();
		if ( '' !== $tabs_color ) {
			$nav_link_declarations['color'] = $tabs_color;
		}
		$tabs_padding_value = self::build_box_shorthand( $tabs_padding );
		if ( '' !== $tabs_padding_value ) {
			$nav_link_declarations['padding'] = $tabs_padding_value;
		}

		self::register_rule(
			$collector,
			$prefix,
			array(
				'.woocommerce-MyAccount-navigation-link a',
				'.woocommerce-MyAccount-navigation a',
			),
			$nav_link_declarations,
			'woocommerce-my-account-nav-links'
		);

		if ( '' !== $tabs_divider ) {
			self::register_rule(
				$collector,
				$prefix,
				array( '.woocommerce-MyAccount-navigation li' ),
				array( 'border-color' => $tabs_divider ),
				'woocommerce-my-account-nav-divider'
			);
		}

		if ( '' !== $sections_background ) {
			self::register_rule(
				$collector,
				$prefix,
				array( '.woocommerce-MyAccount-content' ),
				array( 'background-color' => $sections_background ),
				'woocommerce-my-account-content'
			);
		}
	}

	/**
	 * Fetch widget settings from Elementor element data.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return array
	 */
	private static function get_settings( array $element ): array {
		return is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
	}

	/**
	 * Build selector prefix with required specificity.
	 *
	 * @param string $page_class Page wrapper class.
	 * @param string $widget_class Widget class.
	 *
	 * @return string
	 */
	private static function build_prefix( string $page_class, string $widget_class ): string {
		$page_class   = trim( $page_class );
		$widget_class = trim( $widget_class );
		if ( '' === $page_class || '' === $widget_class ) {
			return '';
		}

		return 'body .' . $page_class . ' .' . $widget_class;
	}

	/**
	 * Register a rule with selector variants.
	 *
	 * @param External_Style_Collector $collector Collector instance.
	 * @param string $prefix Prefix selector.
	 * @param array $selectors Selector list.
	 * @param array $declarations Declarations list.
	 * @param string $reason Reason label.
	 *
	 * @return void
	 */
	private static function register_rule(
		External_Style_Collector $collector,
		string $prefix,
		array $selectors,
		array $declarations,
		string $reason
	): void {
		$selector = self::build_selector_list( $prefix, $selectors );
		if ( '' === $selector ) {
			return;
		}

		$collector->register_rule( $selector, $declarations, $reason );
	}

	/**
	 * Build selector list with prefix applied.
	 *
	 * @param string $prefix Selector prefix.
	 * @param array $selectors Selector list.
	 *
	 * @return string
	 */
	private static function build_selector_list( string $prefix, array $selectors ): string {
		if ( '' === $prefix ) {
			return '';
		}

		$output = array();
		foreach ( $selectors as $selector ) {
			$selector = trim( (string) $selector );
			if ( '' === $selector ) {
				continue;
			}
			$output[] = $prefix . ' ' . $selector;
		}

		return implode( ', ', $output );
	}

	/**
	 * Resolve a widget setting with Elementor defaults and fallbacks.
	 *
	 * @param array $settings Settings array.
	 * @param string $widget Widget name.
	 * @param string $key Setting key.
	 * @param mixed $fallback Fallback value.
	 *
	 * @return mixed
	 */
	private static function resolve_setting( array $settings, string $widget, string $key, $fallback ) {
		if ( array_key_exists( $key, $settings ) && null !== $settings[ $key ] && '' !== $settings[ $key ] ) {
			return $settings[ $key ];
		}

		$default = self::get_elementor_control_default( $widget, $key );
		if ( null !== $default && '' !== $default ) {
			return $default;
		}

		return $fallback;
	}

	/**
	 * Fetch a control default from Elementor when available.
	 *
	 * @param string $widget Widget name.
	 * @param string $key Control key.
	 *
	 * @return mixed|null
	 */
	private static function get_elementor_control_default( string $widget, string $key ) {
		static $defaults = array();

		if ( isset( $defaults[ $widget ] ) && array_key_exists( $key, $defaults[ $widget ] ) ) {
			return $defaults[ $widget ][ $key ];
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			$defaults[ $widget ][ $key ] = null;

			return null;
		}

		$plugin = Plugin::instance();
		if ( ! isset( $plugin->widgets_manager ) || ! method_exists( $plugin->widgets_manager, 'get_widget_types' ) ) {
			$defaults[ $widget ][ $key ] = null;

			return null;
		}

		$widget_obj = $plugin->widgets_manager->get_widget_types( $widget );
		if ( ! $widget_obj || ! method_exists( $widget_obj, 'get_controls' ) ) {
			$defaults[ $widget ][ $key ] = null;

			return null;
		}

		$controls = $widget_obj->get_controls();
		if ( ! is_array( $controls ) || ! isset( $controls[ $key ] ) ) {
			$defaults[ $widget ][ $key ] = null;

			return null;
		}

		$defaults[ $widget ][ $key ] = $controls[ $key ]['default'] ?? null;

		return $defaults[ $widget ][ $key ];
	}

	/**
	 * Normalize alignment values.
	 *
	 * @param mixed $value Raw alignment.
	 *
	 * @return string
	 */
	private static function normalize_alignment( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : '';
	}

	/**
	 * Normalize border style values.
	 *
	 * @param mixed $value Raw border style.
	 *
	 * @return string
	 */
	private static function sanitize_border_style( $value ): string {
		$value   = strtolower( trim( (string) $value ) );
		$allowed = array( 'solid', 'dashed', 'dotted', 'double', 'none' );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Resolve Elementor color values.
	 *
	 * @param mixed $value Raw color.
	 *
	 * @return string
	 */
	private static function resolve_color( $value ): string {
		$resolved = Style_Parser::resolve_elementor_color_reference( $value );

		return isset( $resolved['color'] ) ? (string) $resolved['color'] : '';
	}

	/**
	 * Normalize a CSS dimension value.
	 *
	 * @param mixed $value Raw value.
	 * @param string $default_unit Default unit.
	 *
	 * @return string|null
	 */
	private static function normalize_dimension( $value, string $default_unit ): ?string {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) ) {
				return self::normalize_dimension( $value['size'], $value['unit'] ?? $default_unit );
			}
			if ( isset( $value['value'] ) ) {
				return self::normalize_dimension( $value['value'], $value['unit'] ?? $default_unit );
			}
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return $value . ( '' === $default_unit ? '' : $default_unit );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/[a-z%]+$/i', $value ) ) {
			return $value;
		}

		return $value . ( '' === $default_unit ? '' : $default_unit );
	}

	/**
	 * Normalize a box model value into side map.
	 *
	 * @param mixed $value Raw box value.
	 * @param string $default_unit Default unit.
	 *
	 * @return array<string, string>
	 */
	private static function normalize_box_sides( $value, string $default_unit ): array {
		if ( ! is_array( $value ) ) {
			$normalized = self::normalize_dimension( $value, $default_unit );
			if ( null === $normalized ) {
				return array();
			}

			return array(
				'top'    => $normalized,
				'right'  => $normalized,
				'bottom' => $normalized,
				'left'   => $normalized,
			);
		}

		$unit  = isset( $value['unit'] ) && '' !== $value['unit'] ? (string) $value['unit'] : $default_unit;
		$sides = array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( ! array_key_exists( $side, $value ) ) {
				continue;
			}
			$side_value = self::normalize_dimension( $value[ $side ], $unit );
			if ( null !== $side_value ) {
				$sides[ $side ] = $side_value;
			}
		}

		return $sides;
	}

	/**
	 * Build CSS shorthand for box model values.
	 *
	 * @param array<string, string> $sides Side map.
	 *
	 * @return string
	 */
	private static function build_box_shorthand( array $sides ): string {
		if ( empty( $sides ) ) {
			return '';
		}

		$top    = $sides['top'] ?? '';
		$right  = $sides['right'] ?? $top;
		$bottom = $sides['bottom'] ?? $top;
		$left   = $sides['left'] ?? $right;

		if ( '' === $top && '' === $right && '' === $bottom && '' === $left ) {
			return '';
		}

		if ( $top === $right && $top === $bottom && $top === $left ) {
			return $top;
		}

		if ( $top === $bottom && $right === $left ) {
			return $top . ' ' . $right;
		}

		return trim( $top . ' ' . $right . ' ' . $bottom . ' ' . $left );
	}
}
