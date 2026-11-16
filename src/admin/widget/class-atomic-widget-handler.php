<?php
/**
 * Widget handler for Elementor 4 atomic widgets.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Core_Block_Markup;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_url;
use function get_permalink;
use function get_post_meta;
use function wp_get_attachment_url;

defined( 'ABSPATH' ) || exit;

/**
 * Converts the atomic heading, paragraph, button, image, divider and video widgets into core blocks.
 */
class Atomic_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of an atomic widget.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = self::unwrap( isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array() );
		$settings = is_array( $settings ) ? $settings : array();
		$link     = $this->read_link( $settings );

		switch ( $element['widgetType'] ?? '' ) {
			case 'e-heading':
				$level = isset( $settings['tag'] ) && 1 === preg_match( '/^h([1-6])$/', (string) $settings['tag'], $matches ) ? (int) $matches[1] : 2;

				return Core_Block_Markup::heading( $this->link_text( $this->read_text( $settings, 'title' ), $link ), $level );
			case 'e-paragraph':
				return Core_Block_Markup::paragraph( $this->link_text( $this->read_text( $settings, 'paragraph' ), $link ) );
			case 'e-button':
				return Core_Block_Markup::button( $this->read_text( $settings, 'text' ), $link['url'], $link['new_tab'] );
			case 'e-image':
				$source = isset( $settings['image']['src'] ) && is_array( $settings['image']['src'] ) ? $settings['image']['src'] : array();

				return $this->build_image( $source, $link );
			case 'e-divider':
				return Core_Block_Markup::separator();
			case 'e-youtube':
				return ( new Video_Widget_Handler() )->handle( array( 'settings' => $this->map_youtube_settings( $settings ) ) );
			case 'e-self-hosted-video':
				return ( new Video_Widget_Handler() )->handle( array( 'settings' => $this->map_hosted_video_settings( $settings ) ) );
			default:
				return '';
		}
	}

	/**
	 * Strip the type wrappers atomic widgets store around every value.
	 *
	 * @param mixed $value A stored atomic value.
	 *
	 * @return mixed The plain value, with nested values unwrapped too.
	 */
	public static function unwrap( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( isset( $value['$$type'] ) && array_key_exists( 'value', $value ) ) {
			return self::unwrap( $value['value'] );
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::unwrap( $item );
		}

		return $value;
	}

	/**
	 * Read the desktop flex direction of an atomic layout element from its local styles.
	 *
	 * @param array $element Atomic element.
	 *
	 * @return string Either row or column.
	 */
	public static function read_flex_direction( array $element ): string {
		$direction = 'e-flexbox' === ( $element['elType'] ?? '' ) ? 'row' : 'column';
		$styles    = self::unwrap( isset( $element['styles'] ) && is_array( $element['styles'] ) ? $element['styles'] : array() );

		foreach ( is_array( $styles ) ? $styles : array() as $style ) {
			foreach ( is_array( $style['variants'] ?? null ) ? $style['variants'] : array() as $variant ) {
				$is_base = empty( $variant['meta']['state'] ) && in_array( $variant['meta']['breakpoint'] ?? null, array( null, 'desktop' ), true );
				if ( $is_base && isset( $variant['props']['flex-direction'] ) && is_string( $variant['props']['flex-direction'] ) ) {
					$direction = $variant['props']['flex-direction'];
				}
			}
		}

		return 0 === strpos( $direction, 'row' ) ? 'row' : 'column';
	}

	/**
	 * Read a text setting, which is either a string or an object holding `content`.
	 *
	 * @param array  $settings Unwrapped widget settings.
	 * @param string $key      Setting key.
	 *
	 * @return string Text or inline HTML.
	 */
	private function read_text( array $settings, string $key ): string {
		$value = $settings[ $key ] ?? '';
		if ( is_array( $value ) && isset( $value['content'] ) ) {
			$value = $value['content'];
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read the link of an atomic widget.
	 *
	 * A destination that is a post ID is resolved to its permalink.
	 *
	 * @param array $settings Unwrapped widget settings.
	 *
	 * @return array{url: string, new_tab: bool}
	 */
	private function read_link( array $settings ): array {
		$link        = isset( $settings['link'] ) && is_array( $settings['link'] ) ? $settings['link'] : array();
		$destination = $link['destination'] ?? '';

		if ( is_array( $destination ) ) {
			$destination = $destination['id'] ?? ( $destination['url'] ?? '' );
		}
		if ( is_numeric( $destination ) && (int) $destination > 0 ) {
			$destination = (string) get_permalink( (int) $destination );
		}

		return array(
			'url'     => is_string( $destination ) ? trim( $destination ) : '',
			'new_tab' => ! empty( $link['isTargetBlank'] ),
		);
	}

	/**
	 * Wrap text in the widget's link.
	 *
	 * @param string $text Text or inline HTML.
	 * @param array  $link Link from read_link().
	 *
	 * @return string Text, linked when the widget has a link.
	 */
	private function link_text( string $text, array $link ): string {
		if ( '' === $link['url'] || '' === trim( $text ) ) {
			return $text;
		}

		return '<a href="' . esc_url( $link['url'] ) . '"' . ( $link['new_tab'] ? ' target="_blank" rel="noreferrer noopener"' : '' ) . '>' . Core_Block_Markup::inline_html( $text ) . '</a>';
	}

	/**
	 * Build a core/image block from an atomic image source.
	 *
	 * @param array $source Unwrapped image source holding an `id` or a `url`.
	 * @param array $link   Link from read_link().
	 *
	 * @return string Serialized block, or an empty string without an image.
	 */
	private function build_image( array $source, array $link ): string {
		$id  = isset( $source['id'] ) && is_numeric( $source['id'] ) ? (int) $source['id'] : 0;
		$url = isset( $source['url'] ) && is_string( $source['url'] ) ? $source['url'] : '';
		$alt = isset( $source['alt'] ) && is_string( $source['alt'] ) ? $source['alt'] : '';

		if ( $id > 0 ) {
			$url = (string) wp_get_attachment_url( $id );
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		}

		return Core_Block_Markup::image( $url, $id, $alt, $link['url'], $link['new_tab'] );
	}

	/**
	 * Map atomic YouTube settings onto the classic video widget settings.
	 *
	 * @param array $settings Unwrapped widget settings.
	 *
	 * @return array Classic video widget settings.
	 */
	private function map_youtube_settings( array $settings ): array {
		return array(
			'video_type'  => 'youtube',
			'youtube_url' => isset( $settings['source'] ) && is_string( $settings['source'] ) ? $settings['source'] : '',
			'start'       => $settings['start'] ?? '',
			'end'         => $settings['end'] ?? '',
			'autoplay'    => ! empty( $settings['autoplay'] ) ? 'yes' : '',
			'mute'        => ! empty( $settings['mute'] ) ? 'yes' : '',
			'loop'        => ! empty( $settings['loop'] ) ? 'yes' : '',
			'controls'    => isset( $settings['player_controls'] ) && ! $settings['player_controls'] ? '' : 'yes',
		);
	}

	/**
	 * Map atomic self-hosted video settings onto the classic video widget settings.
	 *
	 * @param array $settings Unwrapped widget settings.
	 *
	 * @return array Classic video widget settings.
	 */
	private function map_hosted_video_settings( array $settings ): array {
		$source = isset( $settings['source'] ) && is_array( $settings['source'] ) ? $settings['source'] : array();
		$id     = isset( $source['id'] ) && is_numeric( $source['id'] ) ? (int) $source['id'] : 0;
		$url    = isset( $source['url'] ) && is_string( $source['url'] ) ? $source['url'] : '';
		if ( $id > 0 ) {
			$url = (string) wp_get_attachment_url( $id );
		}

		$poster = isset( $settings['poster']['src'] ) && is_array( $settings['poster']['src'] ) ? $settings['poster']['src'] : array();
		if ( ! empty( $poster['id'] ) && is_numeric( $poster['id'] ) ) {
			$poster['url'] = (string) wp_get_attachment_url( (int) $poster['id'] );
		}

		return array(
			'video_type'     => 'hosted',
			'hosted_url'     => array(
				'url' => $url,
				'id'  => $id,
			),
			'start'          => $settings['start_time'] ?? '',
			'end'            => $settings['end_time'] ?? '',
			'autoplay'       => ! empty( $settings['autoplay'] ) ? 'yes' : '',
			'mute'           => ! empty( $settings['mute'] ) ? 'yes' : '',
			'loop'           => ! empty( $settings['loop'] ) ? 'yes' : '',
			'controls'       => isset( $settings['controls'] ) && ! $settings['controls'] ? '' : 'yes',
			'play_on_mobile' => ! empty( $settings['playsinline'] ) ? 'yes' : '',
			'poster'         => ! empty( $settings['poster_enabled'] ) && ! empty( $poster['url'] ) ? array( 'url' => $poster['url'] ) : array(),
		);
	}
}
