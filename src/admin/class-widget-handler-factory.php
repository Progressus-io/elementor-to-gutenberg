<?php
/**
 * Widget Handler Factory
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use Progressus\BlockShift\Admin\Widget\WP_Widget_Handler;

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
		'counter'                   => 'Progressus\BlockShift\Admin\Widget\Counter_Widget_Handler',
		'progress'                  => 'Progressus\BlockShift\Admin\Widget\Progress_Widget_Handler',
		'heading'                   => 'Progressus\BlockShift\Admin\Widget\Heading_Widget_Handler',
		'text-editor'               => 'Progressus\BlockShift\Admin\Widget\Text_Editor_Widget_Handler',
		'image'                     => 'Progressus\BlockShift\Admin\Widget\Image_Widget_Handler',
		'gallery'                   => 'Progressus\BlockShift\Admin\Widget\Image_Widget_Handler',
		'google_maps'               => 'Progressus\BlockShift\Admin\Widget\Map_Widget_Handler',
		'button'                    => 'Progressus\BlockShift\Admin\Widget\Button_Widget_Handler',
		'video'                     => 'Progressus\BlockShift\Admin\Widget\Video_Widget_Handler',
		'accordion'                 => 'Progressus\BlockShift\Admin\Widget\Accordion_Widget_Handler',
		'toggle'                    => 'Progressus\BlockShift\Admin\Widget\Toggle_Widget_Handler',
		'nested-accordion'          => 'Progressus\BlockShift\Admin\Widget\Nested_Accordion_Widget_Handler',
		'nested-tabs'               => 'Progressus\BlockShift\Admin\Widget\Nested_Tabs_Widget_Handler',
		'icon'                      => 'Progressus\BlockShift\Admin\Widget\Icon_Widget_Handler',
		'icon-box'                  => 'Progressus\BlockShift\Admin\Widget\Icon_Box_Widget_Handler',
		'image-box'                 => 'Progressus\BlockShift\Admin\Widget\Image_Box_Widget_Handler',
		'call-to-action'            => 'Progressus\BlockShift\Admin\Widget\Call_To_Action_Widget_Handler',
		'icon-list'                 => 'Progressus\BlockShift\Admin\Widget\Icon_List_Widget_Handler',
		'social-icons'              => 'Progressus\BlockShift\Admin\Widget\Social_Icons_Widget_Handler',
		'spacer'                    => 'Progressus\BlockShift\Admin\Widget\Spacer_Widget_Handler',
		'image-gallery'             => 'Progressus\BlockShift\Admin\Widget\Gallery_Widget_Handler',
		'divider'                   => 'Progressus\BlockShift\Admin\Widget\Divider_Widget_Handler',
		'tabs'                      => 'Progressus\BlockShift\Admin\Widget\Tabs_Widget_Handler',
		'testimonial-carousel'      => 'Progressus\BlockShift\Admin\Widget\Testimonial_Carousel_Widget_Handler',
		'testimonial'               => 'Progressus\BlockShift\Admin\Widget\Testimonial_Widget_Handler',
		'form'                      => 'Progressus\BlockShift\Admin\Widget\Form_Widget_Handler',
		'nav-menu'                  => 'Progressus\BlockShift\Admin\Widget\Menu_Widget_Handler',
		'theme-site-logo'           => 'Progressus\BlockShift\Admin\Widget\Site_Logo_Widget_Handler',
		'woocommerce-products'      => 'Progressus\BlockShift\Admin\Widget\Woo_Products_Widget_Handler',
		'woocommerce-cart'          => 'Progressus\BlockShift\Admin\Widget\Woo_Cart_Widget_Handler',
		'woocommerce_cart'          => 'Progressus\BlockShift\Admin\Widget\Woo_Cart_Widget_Handler',
		'woocommerce-checkout-page' => 'Progressus\BlockShift\Admin\Widget\Woo_Checkout_Widget_Handler',
		'woocommerce-menu-cart'     => 'Progressus\BlockShift\Admin\Widget\Woo_Mini_Cart_Widget_Handler',
		'woocommerce-checkout'      => 'Progressus\BlockShift\Admin\Widget\Woo_Checkout_Widget_Handler',
		'woocommerce-mini-cart'     => 'Progressus\BlockShift\Admin\Widget\Woo_Mini_Cart_Widget_Handler',
		'shortcode'                 => 'Progressus\BlockShift\Admin\Widget\Shortcode_Widget_Handler',
		'wc-categories'             => 'Progressus\BlockShift\Admin\Widget\Woo_Categories_Widget_Handler',
		'woocommerce-notices'       => 'Progressus\BlockShift\Admin\Widget\Woo_Notices_Widget_Handler',
		'woocommerce-my-account'    => 'Progressus\BlockShift\Admin\Widget\Woo_My_Account_Widget_Handler',
		'wc-add-to-cart'            => 'Progressus\BlockShift\Admin\Widget\Woo_Add_To_Cart_Widget_Handler',
		'posts'                     => 'Progressus\BlockShift\Admin\Widget\Posts_Widget_Handler',
		'search-form'               => 'Progressus\BlockShift\Admin\Widget\Search_Form_Widget_Handler',
		'search'                    => 'Progressus\BlockShift\Admin\Widget\Search_Form_Widget_Handler',
		'soundcloud'                => 'Progressus\BlockShift\Admin\Widget\Generic_Elementor_Widget_Handler',
		'alert'                     => 'Progressus\BlockShift\Admin\Widget\Generic_Elementor_Widget_Handler',
		'rating'                    => 'Progressus\BlockShift\Admin\Widget\Generic_Elementor_Widget_Handler',
		'image-carousel'            => 'Progressus\BlockShift\Admin\Widget\Generic_Elementor_Widget_Handler',
		'image_carousel'            => 'Progressus\BlockShift\Admin\Widget\Generic_Elementor_Widget_Handler',
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

	/**
	 * Return all registered widget type slugs.
	 *
	 * @return array<int,string>
	 */
	public static function get_supported_types(): array {
		return array_keys( self::$handlers );
	}
}
