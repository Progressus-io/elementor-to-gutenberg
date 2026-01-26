<?php
/**
 * Widget handler for Elementor video widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_url;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor video widget.
 */
class Video_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor video to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$youtube  = isset( $settings['youtube_url'] ) ? trim( (string) $settings['youtube_url'] ) : '';
		$custom   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

		if ( '' === $youtube ) {
			return '';
		}

		$watch_url = $this->normalize_youtube_url( $youtube );
		if ( '' === $watch_url ) {
			return '';
		}

		if ( '' !== $custom ) {
			Style_Parser::save_custom_css( $custom );
		}

		$attrs = array(
			'url'              => $watch_url,
			'type'             => 'video',
			'providerNameSlug' => 'youtube',
			'responsive'       => true,
		);

		$figure = sprintf(
			'<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube"><div class="wp-block-embed__wrapper">%s</div></figure>',
			esc_url( $watch_url )
		);

		return Block_Builder::build( 'embed', $attrs, $figure, array( 'raw' => true ) );
	}

	/**
	 * Normalize various YouTube URLs to canonical watch form.
	 */
	private function normalize_youtube_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$video_id = '';

		if ( preg_match( '#youtu\.be/([^?&]+)#i', $url, $matches ) ) {
			$video_id = $matches[1];
		} elseif ( preg_match( '#youtube\.com/embed/([^?&]+)#i', $url, $matches ) ) {
			$video_id = $matches[1];
		} else {
			$parts = parse_url( $url );
			if ( isset( $parts['query'] ) ) {
				parse_str( $parts['query'], $query_vars );
				if ( ! empty( $query_vars['v'] ) ) {
					$video_id = (string) $query_vars['v'];
				}
			}
		}

		$video_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $video_id );
		if ( '' === $video_id ) {
			return '';
		}

		return 'https://www.youtube.com/watch?v=' . $video_id;
	}
}
