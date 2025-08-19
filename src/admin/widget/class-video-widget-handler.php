<?php
/**
 * Widget handler for Elementor video widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor video widget.
 */
class Video_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor video to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings       = $element['settings'] ?? array();
		$video_url      = '';
		$embed_provider = '';
		$block_content  = '';

		// Determine video URL and provider
		if ( isset( $settings['video_type'] ) && $settings['video_type'] === 'hosted' && ! empty( $settings['hosted_url']['url'] ) ) {
			$hosted_video_url = $settings['hosted_url']['url'];
			$attachment_id    = attachment_url_to_postid( $hosted_video_url );

			if ( $attachment_id ) {
				$video_url = wp_get_attachment_url( $attachment_id );
			} else {
				$download_args = array(
					'timeout'      => 60,
					'redirection'  => 5,
					'stream'       => true,
					'headers'      => array(
						'User-Agent'      => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
						'Accept'          => 'video/mp4,video/*;q=0.9,*/*;q=0.8',
						'Accept-Language' => 'en-US,en;q=0.9',
						'Cache-Control'   => 'no-cache',
						'Pragma'          => 'no-cache',
					),
				);

				$tmp_file = download_url( $hosted_video_url, 60, false, $download_args );

				if ( ! is_wp_error( $tmp_file ) ) {
					$file_array    = array(
						'name'     => basename( $hosted_video_url ),
						'tmp_name' => $tmp_file,
					);
					$attachment_id = media_handle_sideload( $file_array, 0 );
					if ( ! is_wp_error( $attachment_id ) ) {
						$video_url = wp_get_attachment_url( $attachment_id );
					} else {
						$video_url = $hosted_video_url;
						add_settings_error(
							'gutenberg_json_data',
							'json_upload_error',
							esc_html__( 'Video Download Failed: Please manually download the video and upload it to your Media Library, or ensure the video URL is publicly accessible.', 'elementor-to-gutenberg' ),
							'error'
						);
					}
					if ( file_exists( $tmp_file ) ) {
						wp_delete_file( $tmp_file );
					}
				} else {
					$video_url = $hosted_video_url;
					add_settings_error(
						'gutenberg_json_data',
						'json_upload_error',
						esc_html__( 'Video Download Failed: Please manually download the video and upload it to your Media Library, or ensure the video URL is publicly accessible.', 'elementor-to-gutenberg' ),
						'error'
					);
				}
			}
		} elseif ( ! empty( $settings['youtube_url'] ) ) {
			$video_url      = $settings['youtube_url'];
			$embed_provider = 'youtube';
		} elseif ( ! empty( $settings['vimeo_url'] ) ) {
			$video_url      = $settings['vimeo_url'];
			$embed_provider = 'vimeo';
		} elseif ( ! empty( $settings['dailymotion_url'] ) ) {
			$video_url      = $settings['dailymotion_url'];
			$embed_provider = 'dailymotion';
		} elseif ( ! empty( $settings['videopress_url'] ) ) {
			$video_url      = $settings['videopress_url'];
			$embed_provider = 'videopress';
		}

		// Handle overlay image
		$poster_url = '';
		$poster_id  = 0;
		if ( $this->has_video_overlay( $settings ) ) {
			$overlay_url = $settings['image_overlay']['url'];
			$tmp_file    = download_url( $overlay_url );
			if ( ! is_wp_error( $tmp_file ) ) {
				$file_array    = array(
					'name'     => basename( $overlay_url ),
					'tmp_name' => $tmp_file,
				);
				$attachment_id = media_handle_sideload( $file_array, 0 );
				if ( ! is_wp_error( $attachment_id ) ) {
					$poster_url = wp_get_attachment_url( $attachment_id );
					$poster_id  = $attachment_id;
				}
				if ( file_exists( $tmp_file ) ) {
					wp_delete_file( $tmp_file );
				}
			}
		}

		// Apply spacing and extra attributes
		$attrs_array = array();
		$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );

		if ( isset( $settings['_css_classes'] ) ) {
			$attrs_array['className'] = $settings['_css_classes'];
		}
		if ( isset( $settings['premium_tooltip_text'] ) ) {
			$attrs_array['premiumTooltipText'] = $settings['premium_tooltip_text'];
		}
		if ( isset( $settings['premium_tooltip_position'] ) ) {
			$attrs_array['premiumTooltipPosition'] = $settings['premium_tooltip_position'];
		}

		// Hosted or direct video files
		if ( preg_match( '/\.(mp4|webm|ogg)$/i', $video_url ) ) {
			$attrs_array = array_merge(
				$attrs_array,
				array(
					'src'         => esc_url( $video_url ),
					'poster'      => $poster_url ? esc_url( $poster_url ) : '',
					'id'          => $poster_id,
					'autoplay'    => isset( $settings['autoplay'] ) && $settings['autoplay'] === 'yes',
					'loop'        => isset( $settings['loop'] ) && $settings['loop'] === 'yes',
					'muted'       => isset( $settings['mute'] ) && $settings['mute'] === 'yes',
					'controls'    => true,
					'playsInline' => isset( $settings['play_on_mobile'] ) && $settings['play_on_mobile'] === 'yes',
				)
			);

			$attrs = wp_json_encode( $attrs_array );

			$video_attrs = array();
			if ( $attrs_array['autoplay'] ) {
				$video_attrs[] = 'autoplay=""';
			}
			if ( $attrs_array['loop'] ) {
				$video_attrs[] = 'loop=""';
			}
			if ( $attrs_array['muted'] ) {
				$video_attrs[] = 'muted=""';
			}
			$video_attrs[] = 'controls="controls"';
			$video_attrs_str = implode( ' ', $video_attrs );
			$poster_attr = $attrs_array['poster'] ? ' poster="' . esc_url( $attrs_array['poster'] ) . '"' : '';

			$block_content .= sprintf(
				"<!-- wp:video %s -->\n<figure class=\"wp-block-video\"><video %s%s><source src=\"%s\"></video></figure>\n<!-- /wp:video -->\n",
				$attrs,
				$video_attrs_str,
				$poster_attr,
				esc_url( $video_url )
			);
		} else {
			// External embeds
			$attrs_array = array_merge(
				$attrs_array,
				array(
					'url'              => esc_url( $video_url ),
					'type'             => 'video',
					'providerNameSlug' => $embed_provider,
					'responsive'       => true,
				)
			);

			// Append start/end time for YouTube/Vimeo
			if ( in_array( $embed_provider, array( 'youtube', 'vimeo' ), true ) ) {
				if ( ! empty( $settings['start'] ) ) {
					$video_url = add_query_arg( 'start', intval( $settings['start'] ), $video_url );
				}
				if ( ! empty( $settings['end'] ) ) {
					$video_url = add_query_arg( 'end', intval( $settings['end'] ), $video_url );
				}
				$attrs_array['url'] = esc_url( $video_url );
			}

			$attrs = wp_json_encode( $attrs_array );

			$block_content .= sprintf(
				"<!-- wp:embed %s -->\n<figure class=\"wp-block-embed is-type-video is-provider-%s wp-block-embed-%s wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n%s\n</div></figure>\n<!-- /wp:embed -->\n",
				$attrs,
				$embed_provider,
				$embed_provider,
				esc_url( $video_url )
			);
		}

		return $block_content;
	}

	/**
	 * Check if video overlay is present.
	 *
	 * @param array $settings The Elementor settings.
	 * @return bool True if overlay is present, false otherwise.
	 */
	private function has_video_overlay( array $settings ): bool {
		return isset( $settings['image_overlay']['url'] ) && ! empty( $settings['image_overlay']['url'] );
	}
}