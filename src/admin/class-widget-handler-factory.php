<?php
/**
 * Widget Handler Factory
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin;

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
		'heading'          => 'Progressus\Gutenberg\Admin\Widget\Heading_Widget_Handler',
		'text-editor'      => 'Progressus\Gutenberg\Admin\Widget\Text_Editor_Widget_Handler',
		'image'            => 'Progressus\Gutenberg\Admin\Widget\Image_Widget_Handler',
		'button'           => 'Progressus\Gutenberg\Admin\Widget\Button_Widget_Handler',
		'video'            => 'Progressus\Gutenberg\Admin\Widget\Video_Widget_Handler',
		'accordion'        => 'Progressus\Gutenberg\Admin\Widget\Accordion_Widget_Handler',
		'nested-accordion' => 'Progressus\Gutenberg\Admin\Widget\Nested_Accordion_Widget_Handler',
		'nested-tabs'      => 'Progressus\Gutenberg\Admin\Widget\Nested_Tabs_Widget_Handler',
		'icon'             => 'Progressus\Gutenberg\Admin\Widget\Icon_Widget_Handler',
		'icon-box'         => 'Progressus\Gutenberg\Admin\Widget\Icon_Box_Widget_Handler',
		'icon-list'        => 'Progressus\Gutenberg\Admin\Widget\Icon_List_Widget_Handler',
		'image-box'        => 'Progressus\Gutenberg\Admin\Widget\Image_Box_Widget_Handler',
		'testimonial'      => 'Progressus\Gutenberg\Admin\Widget\Testimonial_Widget_Handler',
		'social-icons'     => 'Progressus\Gutenberg\Admin\Widget\Social_Icons_Widget_Handler',
		'social'           => 'Progressus\Gutenberg\Admin\Widget\Social_Icons_Widget_Handler',
		'spacer'           => 'Progressus\Gutenberg\Admin\Widget\Spacer_Widget_Handler',
		'image-gallery'    => 'Progressus\Gutenberg\Admin\Widget\Gallery_Widget_Handler',
		'divider'          => 'Progressus\Gutenberg\Admin\Widget\Divider_Widget_Handler',
		'tabs'             => 'Progressus\Gutenberg\Admin\Widget\Tabs_Widget_Handler',
	);

	/**
     * Get a widget handler instance.
     *
     * @param string $widget_type The Elementor widget type.
     * @return Widget_Handler_Interface|null The widget handler or null if not found.
     */
    public static function get_handler( string $widget_type ): ?Widget_Handler_Interface {
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