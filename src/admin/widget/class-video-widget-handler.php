<?php
/**
 * Widget handler for Elementor video widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function add_query_arg;
use function esc_attr;
use function esc_url;
use function esc_url_raw;
use function serialize_block;
use function wp_parse_url;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor video widget.
 */
class Video_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Class that marks an embed whose URL carries player options for the front end.
	 */
	public const EMBED_CLASS = 'blockshift-video';

	/**
	 * Class of the group that stacks an overlay image above an embed.
	 */
	public const OVERLAY_CLASS = 'blockshift-video-overlay';

	/**
	 * Elementor aspect ratio values mapped to the core embed aspect classes.
	 */
	private const ASPECT_CLASSES = array(
		'169' => 'wp-embed-aspect-16-9',
		'219' => 'wp-embed-aspect-21-9',
		'43'  => 'wp-embed-aspect-4-3',
		'32'  => 'wp-embed-aspect-3-2',
		'11'  => 'wp-embed-aspect-1-1',
		'916' => 'wp-embed-aspect-9-16',
	);

	/**
	 * Handle conversion of Elementor video to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$type     = isset( $settings['video_type'] ) && '' !== $settings['video_type'] ? (string) $settings['video_type'] : 'youtube';

		if ( 'hosted' === $type ) {
			$output = $this->build_hosted_video( $settings );
		} else {
			$output = $this->build_embed( $type, $settings );
		}

		if ( '' !== $output && ! empty( $settings['custom_css'] ) ) {
			Style_Parser::note_skipped_custom_css( (string) $settings['custom_css'] );
		}

		return $output;
	}

	/**
	 * Build a core/video block for a self-hosted video.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return string Serialized block, or an empty string without a source.
	 */
	private function build_hosted_video( array $settings ): string {
		$source = ! empty( $settings['insert_url'] ) ? ( $settings['external_url'] ?? array() ) : ( $settings['hosted_url'] ?? array() );
		$url    = is_array( $source ) && ! empty( $source['url'] ) ? esc_url_raw( (string) $source['url'] ) : '';
		if ( '' === $url ) {
			return '';
		}

		$start = $this->read_seconds( $settings, 'start' );
		$end   = $this->read_seconds( $settings, 'end' );
		if ( $start > 0 || $end > 0 ) {
			$url .= '#t=' . $start . ( $end > 0 ? ',' . $end : '' );
		}

		$html_attributes = array();
		if ( $this->is_on( $settings, 'autoplay' ) ) {
			$html_attributes[] = 'autoplay';
		}
		if ( $this->is_on( $settings, 'controls', true ) ) {
			$html_attributes[] = 'controls';
		}
		if ( $this->is_on( $settings, 'loop' ) ) {
			$html_attributes[] = 'loop';
		}
		if ( $this->is_on( $settings, 'mute' ) ) {
			$html_attributes[] = 'muted';
		}

		$poster = $this->read_image_url( $settings, 'poster' );
		if ( '' === $poster && $this->is_on( $settings, 'show_image_overlay' ) ) {
			$poster = $this->read_image_url( $settings, 'image_overlay' );
		}
		if ( '' !== $poster ) {
			$html_attributes[] = 'poster="' . esc_url( $poster ) . '"';
		}

		$html_attributes[] = 'src="' . esc_url( $url ) . '"';
		if ( $this->is_on( $settings, 'play_on_mobile' ) ) {
			$html_attributes[] = 'playsinline';
		}

		$attrs = array();
		if ( empty( $settings['insert_url'] ) && ! empty( $source['id'] ) ) {
			$attrs['id'] = (int) $source['id'];
		}

		$html = '<figure class="wp-block-video"><video ' . implode( ' ', $html_attributes ) . '></video></figure>';

		return $this->serialize( 'core/video', $attrs, "\n" . $html . "\n" );
	}

	/**
	 * Build a core/embed block for a hosted-elsewhere video.
	 *
	 * @param string $type     Elementor video type.
	 * @param array  $settings Widget settings.
	 *
	 * @return string Serialized block, or an empty string without a usable URL.
	 */
	private function build_embed( string $type, array $settings ): string {
		$raw_url = isset( $settings[ $type . '_url' ] ) ? trim( (string) $settings[ $type . '_url' ] ) : '';
		if ( '' === $raw_url ) {
			return '';
		}

		// With an overlay the video starts on click, so the embed must not autoplay underneath it.
		$overlay = $this->is_on( $settings, 'show_image_overlay' ) ? $this->read_image_url( $settings, 'image_overlay' ) : '';
		if ( '' !== $overlay ) {
			unset( $settings['autoplay'] );
		}

		switch ( $type ) {
			case 'youtube':
				$url = $this->build_youtube_url( $raw_url, $settings );
				break;
			case 'vimeo':
				$url = $this->build_vimeo_url( $raw_url, $settings );
				break;
			case 'dailymotion':
				$url = $this->build_dailymotion_url( $raw_url, $settings );
				break;
			default:
				$url = esc_url_raw( $raw_url );
		}

		if ( '' === $url ) {
			return '';
		}

		$aspect  = isset( $settings['aspect_ratio'] ) ? (string) $settings['aspect_ratio'] : '169';
		$classes = array( self::EMBED_CLASS, self::ASPECT_CLASSES[ $aspect ] ?? self::ASPECT_CLASSES['169'], 'wp-has-aspect-ratio' );

		$attrs = array(
			'url'              => $url,
			'type'             => 'video',
			'providerNameSlug' => $type,
			'responsive'       => true,
			'className'        => implode( ' ', $classes ),
		);

		$figure = sprintf(
			'<figure class="wp-block-embed is-type-video is-provider-%1$s wp-block-embed-%1$s %2$s"><div class="wp-block-embed__wrapper">' . "\n" . '%3$s' . "\n" . '</div></figure>',
			esc_attr( $type ),
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $url )
		);

		$embed = $this->serialize( 'core/embed', $attrs, "\n" . $figure . "\n" );
		if ( '' === $overlay ) {
			return $embed;
		}

		return $this->wrap_with_overlay( $embed, $overlay, $settings );
	}

	/**
	 * Build a YouTube watch URL that keeps the player options as query arguments.
	 *
	 * @param string $url      Stored YouTube URL.
	 * @param array  $settings Widget settings.
	 *
	 * @return string Watch URL, or an empty string when no video ID is found.
	 */
	private function build_youtube_url( string $url, array $settings ): string {
		$video_id = '';

		if ( preg_match( '#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/)([^?&/]+)#i', $url, $matches ) ) {
			$video_id = $matches[1];
		} else {
			$parts = wp_parse_url( $url );
			if ( isset( $parts['query'] ) ) {
				parse_str( $parts['query'], $query_vars );
				if ( ! empty( $query_vars['v'] ) ) {
					$video_id = (string) $query_vars['v'];
				}
			}
		}

		$video_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $video_id );
		if ( '' === $video_id ) {
			return '';
		}

		$args = array( 'v' => $video_id );
		if ( $this->read_seconds( $settings, 'start' ) > 0 ) {
			$args['start'] = $this->read_seconds( $settings, 'start' );
		}
		if ( $this->read_seconds( $settings, 'end' ) > 0 ) {
			$args['end'] = $this->read_seconds( $settings, 'end' );
		}

		$args += $this->read_player_flags( $settings, 'mute' );

		return add_query_arg( $args, 'https://www.youtube.com/watch' );
	}

	/**
	 * Build a Vimeo URL that keeps the player options as query arguments.
	 *
	 * @param string $url      Stored Vimeo URL.
	 * @param array  $settings Widget settings.
	 *
	 * @return string Vimeo URL.
	 */
	private function build_vimeo_url( string $url, array $settings ): string {
		$url   = esc_url_raw( strtok( $url, '#' ) );
		$url   = add_query_arg( $this->read_player_flags( $settings, 'muted' ), $url );
		$start = $this->read_seconds( $settings, 'start' );

		return $start > 0 ? $url . '#t=' . $start . 's' : $url;
	}

	/**
	 * Build a Dailymotion URL that keeps the player options as query arguments.
	 *
	 * @param string $url      Stored Dailymotion URL.
	 * @param array  $settings Widget settings.
	 *
	 * @return string Dailymotion URL.
	 */
	private function build_dailymotion_url( string $url, array $settings ): string {
		$args  = $this->read_player_flags( $settings, 'mute' );
		$start = $this->read_seconds( $settings, 'start' );
		if ( $start > 0 ) {
			$args['start'] = $start;
		}

		return add_query_arg( $args, esc_url_raw( $url ) );
	}

	/**
	 * Read the autoplay, mute, loop and controls switches as player query arguments.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $mute_key Name the provider uses for the mute argument.
	 *
	 * @return array<string, int> Query arguments that differ from the player defaults.
	 */
	private function read_player_flags( array $settings, string $mute_key ): array {
		$args = array();
		if ( $this->is_on( $settings, 'autoplay' ) ) {
			$args['autoplay'] = 1;
		}
		if ( $this->is_on( $settings, 'mute' ) ) {
			$args[ $mute_key ] = 1;
		}
		if ( $this->is_on( $settings, 'loop' ) ) {
			$args['loop'] = 1;
		}
		if ( ! $this->is_on( $settings, 'controls', true ) ) {
			$args['controls'] = 0;
		}

		return $args;
	}

	/**
	 * Stack the overlay image above the embed inside a group.
	 *
	 * @param string $embed    Serialized embed block.
	 * @param string $overlay  Overlay image URL.
	 * @param array  $settings Widget settings.
	 *
	 * @return string Serialized group block.
	 */
	private function wrap_with_overlay( string $embed, string $overlay, array $settings ): string {
		$image_id    = isset( $settings['image_overlay']['id'] ) ? (int) $settings['image_overlay']['id'] : 0;
		$image_attrs = array( 'sizeSlug' => 'full' );
		$image_class = 'wp-block-image size-full';
		$img_class   = '';
		if ( $image_id > 0 ) {
			$image_attrs = array( 'id' => $image_id ) + $image_attrs;
			$img_class   = ' class="wp-image-' . $image_id . '"';
		}

		$image = $this->serialize(
			'core/image',
			$image_attrs,
			"\n" . '<figure class="' . $image_class . '"><img src="' . esc_url( $overlay ) . '" alt=""' . $img_class . '/></figure>' . "\n"
		);

		$inner = "\n" . '<div class="wp-block-group ' . self::OVERLAY_CLASS . '">' . $image . $embed . '</div>' . "\n";

		return serialize_block(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( 'className' => self::OVERLAY_CLASS ),
				'innerBlocks'  => array(),
				'innerHTML'    => $inner,
				'innerContent' => array( $inner ),
			)
		) . "\n";
	}

	/**
	 * Serialize a block that has no inner blocks.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @param string $html  Block HTML.
	 *
	 * @return string Serialized block.
	 */
	private function serialize( string $name, array $attrs, string $html ): string {
		return serialize_block(
			array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			)
		) . "\n";
	}

	/**
	 * Whether an Elementor switcher setting is on.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 * @param bool   $fallback Value used when the setting was never stored.
	 *
	 * @return bool
	 */
	private function is_on( array $settings, string $key, bool $fallback = false ): bool {
		if ( ! array_key_exists( $key, $settings ) ) {
			return $fallback;
		}

		return 'yes' === $settings[ $key ] || true === $settings[ $key ];
	}

	/**
	 * Read a start or end time in whole seconds.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 *
	 * @return int Seconds, or zero when unset.
	 */
	private function read_seconds( array $settings, string $key ): int {
		return isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ? max( 0, (int) $settings[ $key ] ) : 0;
	}

	/**
	 * Read the URL of an Elementor media setting.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 *
	 * @return string Image URL, or an empty string.
	 */
	private function read_image_url( array $settings, string $key ): string {
		$value = $settings[ $key ] ?? null;

		return is_array( $value ) && ! empty( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '';
	}
}
