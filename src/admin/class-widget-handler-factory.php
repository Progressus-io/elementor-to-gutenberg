<?php
/**
 * Widget Handler Factory
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Widget\WP_Widget_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Factory for creating widget handlers.
 */
class Widget_Handler_Factory {
	/**
	 * Registered widget handlers.
	 *
	 * @var array
	 */
	private static $handlers = array(
		'counter'                   => 'Progressus\Gutenberg\Admin\Widget\Counter_Widget_Handler',
		'progress'                  => 'Progressus\Gutenberg\Admin\Widget\Progress_Widget_Handler',
		'heading'                   => 'Progressus\Gutenberg\Admin\Widget\Heading_Widget_Handler',
		'text-editor'               => 'Progressus\Gutenberg\Admin\Widget\Text_Editor_Widget_Handler',
		'image'                     => 'Progressus\Gutenberg\Admin\Widget\Image_Widget_Handler',
		'gallery'                   => 'Progressus\Gutenberg\Admin\Widget\Image_Widget_Handler',
		'google_maps'               => 'Progressus\Gutenberg\Admin\Widget\Map_Widget_Handler',
		'button'                    => 'Progressus\Gutenberg\Admin\Widget\Button_Widget_Handler',
		'video'                     => 'Progressus\Gutenberg\Admin\Widget\Video_Widget_Handler',
		'accordion'                 => 'Progressus\Gutenberg\Admin\Widget\Accordion_Widget_Handler',
		'toggle'                    => 'Progressus\Gutenberg\Admin\Widget\Toggle_Widget_Handler',
		'nested-accordion'          => 'Progressus\Gutenberg\Admin\Widget\Nested_Accordion_Widget_Handler',
		'nested-tabs'               => 'Progressus\Gutenberg\Admin\Widget\Nested_Tabs_Widget_Handler',
		'icon'                      => 'Progressus\Gutenberg\Admin\Widget\Icon_Widget_Handler',
		'icon-box'                  => 'Progressus\Gutenberg\Admin\Widget\Icon_Box_Widget_Handler',
		'image-box'                 => 'Progressus\Gutenberg\Admin\Widget\Image_Box_Widget_Handler',
		'call-to-action'            => 'Progressus\Gutenberg\Admin\Widget\Call_To_Action_Widget_Handler',
		'icon-list'                 => 'Progressus\Gutenberg\Admin\Widget\Icon_List_Widget_Handler',
		'social-icons'              => 'Progressus\Gutenberg\Admin\Widget\Social_Icons_Widget_Handler',
		'spacer'                    => 'Progressus\Gutenberg\Admin\Widget\Spacer_Widget_Handler',
		'image-gallery'             => 'Progressus\Gutenberg\Admin\Widget\Gallery_Widget_Handler',
		'divider'                   => 'Progressus\Gutenberg\Admin\Widget\Divider_Widget_Handler',
		'tabs'                      => 'Progressus\Gutenberg\Admin\Widget\Tabs_Widget_Handler',
		'testimonial-carousel'      => 'Progressus\Gutenberg\Admin\Widget\Testimonial_Carousel_Widget_Handler',
		'testimonial'               => 'Progressus\Gutenberg\Admin\Widget\Testimonial_Widget_Handler',
		'form'                      => 'Progressus\Gutenberg\Admin\Widget\Form_Widget_Handler',
		'nav-menu'                  => 'Progressus\Gutenberg\Admin\Widget\Menu_Widget_Handler',
		'theme-site-logo'           => 'Progressus\Gutenberg\Admin\Widget\Site_Logo_Widget_Handler',
		'woocommerce-products'      => 'Progressus\Gutenberg\Admin\Widget\Woo_Products_Widget_Handler',
		'woocommerce-cart'          => 'Progressus\Gutenberg\Admin\Widget\Woo_Cart_Widget_Handler',
		'woocommerce_cart'          => 'Progressus\Gutenberg\Admin\Widget\Woo_Cart_Widget_Handler',
		'woocommerce-checkout-page' => 'Progressus\Gutenberg\Admin\Widget\Woo_Checkout_Widget_Handler',
		'woocommerce-menu-cart'     => 'Progressus\Gutenberg\Admin\Widget\Woo_Mini_Cart_Widget_Handler',
		'woocommerce-checkout'      => 'Progressus\Gutenberg\Admin\Widget\Woo_Checkout_Widget_Handler',
		'woocommerce-mini-cart'     => 'Progressus\Gutenberg\Admin\Widget\Woo_Mini_Cart_Widget_Handler',
		'shortcode'                 => 'Progressus\Gutenberg\Admin\Widget\Shortcode_Widget_Handler',
		'wc-categories'             => 'Progressus\Gutenberg\Admin\Widget\Woo_Categories_Widget_Handler',
		'woocommerce-notices'       => 'Progressus\Gutenberg\Admin\Widget\Woo_Notices_Widget_Handler',
		'woocommerce-my-account'    => 'Progressus\Gutenberg\Admin\Widget\Woo_My_Account_Widget_Handler',
		'wc-add-to-cart'            => 'Progressus\Gutenberg\Admin\Widget\Woo_Add_To_Cart_Widget_Handler',
		'posts'                     => 'Progressus\Gutenberg\Admin\Widget\Posts_Widget_Handler',
		'search-form'               => 'Progressus\Gutenberg\Admin\Widget\Search_Form_Widget_Handler',
		'search'                    => 'Progressus\Gutenberg\Admin\Widget\Search_Form_Widget_Handler',
		'soundcloud'                => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
		'testimonial'               => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
		'alert'                     => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
		'rating'                    => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
		'image-carousel'            => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
		'image_carousel'            => 'Progressus\Gutenberg\Admin\Widget\Generic_Elementor_Widget_Handler',
	);

	/**
	 * Get a widget handler instance.
	 *
	 * @param string $widget_type The Elementor widget type.
	 *
	 * @return Widget_Handler_Interface|null The widget handler or null if not found.
	 */
	public static function get_handler( string $widget_type ): ?Widget_Handler_Interface {
		if ( 0 === strpos( $widget_type, 'wp-widget-' ) ) {
			return new WP_Widget_Handler();
		}
		$handler_class = self::$handlers[ $widget_type ] ?? null;
		if ( null === $handler_class ) {
			return null;
		}

		return new $handler_class();
	}

	/**
	 * Register a new widget handler.
	 *
	 * @param string $widget_type The Elementor widget type.
	 * @param string $handler_class The handler class name.
	 */
	public static function register_handler( string $widget_type, string $handler_class ): void {
		self::$handlers[ $widget_type ] = $handler_class;
	}
}
