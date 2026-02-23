<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use function absint;
use function esc_attr;
use function esc_html;
use function esc_url;
use function is_array;
use function is_numeric;
use function is_string;
use function parse_blocks;
use function sanitize_key;
use function sanitize_title;
use function serialize_block;
use function strpos;
use function trim;
use function wp_get_attachment_image_url;
use function wp_get_attachment_url;

defined( 'ABSPATH' ) || exit;

class WP_Widget_Handler implements Widget_Handler_Interface {

	public function handle( array $element ): string {
		$widget_type = is_string( $element['widgetType'] ?? null ) ? (string) $element['widgetType'] : '';
		if ( '' === $widget_type || 0 !== strpos( $widget_type, 'wp-widget-' ) ) {
			return '';
		}

		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$wp       = is_array( $settings['wp'] ?? null ) ? $settings['wp'] : array();
		$id_base  = $this->normalize_id_base( (string) substr( $widget_type, 10 ) );

		switch ( $id_base ) {
			case 'recent-comments':
				return $this->dynamic_block( 'latest-comments' );
			case 'archives':
				return $this->dynamic_block( 'archives' );
			case 'search':
				return $this->dynamic_block( 'search' );
			case 'tag-cloud':
				return $this->dynamic_block( 'tag-cloud' );
			case 'pages':
				return $this->dynamic_block( 'page-list' );
			case 'calendar':
				return $this->dynamic_block( 'calendar' );
			case 'rss':
				return $this->rss_block( $wp );
			case 'image':
				return $this->image_block( $wp );
			case 'gallery':
				return $this->gallery_block( $wp );
			case 'custom-html':
				return $this->html_block( is_string( $wp['content'] ?? null ) ? (string) $wp['content'] : '' );
			case 'text':
				return $this->text_block( $wp );
			case 'block':
				return $this->block_widget_block( $wp );
			case 'audio':
				return $this->media_block( 'audio', $wp );
			case 'video':
				return $this->media_block( 'video', $wp );
			default:
				return '';
		}
	}

	private function normalize_id_base( string $raw ): string {
		$raw     = sanitize_title( str_replace( array( '_', ' ' ), '-', strtolower( trim( $raw ) ) ) );
		$aliases = array(
			'media-image'    => 'image',
			'media-gallery'  => 'gallery',
			'media-audio'    => 'audio',
			'media-video'    => 'video',
			'tagcloud'       => 'tag-cloud',
			'customhtml'     => 'custom-html',
			'recentcomments' => 'recent-comments',
		);

		return $aliases[ $raw ] ?? $raw;
	}

	private function dynamic_block( string $slug, array $attrs = array() ): string {
		$block = array(
			'blockName'    => 'core/' . $slug,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		return serialize_block( $block ) . "\n";
	}

	private function rss_block( array $wp ): string {
		$feed = is_string( $wp['url'] ?? null ) ? (string) $wp['url'] : '';
		$feed = trim( $feed );
		if ( '' === $feed ) {
			return $this->dynamic_block( 'rss' );
		}

		return $this->dynamic_block( 'rss', array( 'feedURL' => esc_url( $feed ) ) );
	}

	private function image_block( array $wp ): string {
		$id   = absint( $wp['attachment_id'] ?? 0 );
		$size = is_string( $wp['size'] ?? null ) ? sanitize_key( (string) $wp['size'] ) : '';
		$size = '' !== $size ? $size : 'full';

		$url = is_string( $wp['url'] ?? null ) ? (string) $wp['url'] : '';
		$url = trim( $url );
		if ( '' === $url && $id > 0 ) {
			$resolved = wp_get_attachment_image_url( $id, $size );
			if ( is_string( $resolved ) && '' !== $resolved ) {
				$url = $resolved;
			}
		}

		if ( '' === $url && $id > 0 ) {
			$resolved = wp_get_attachment_url( $id );
			if ( is_string( $resolved ) && '' !== $resolved ) {
				$url = $resolved;
			}
		}

		if ( '' === $url ) {
			return '';
		}

		$alt     = is_string( $wp['alt'] ?? null ) ? (string) $wp['alt'] : '';
		$caption = is_string( $wp['caption'] ?? null ) ? (string) $wp['caption'] : '';

		$img_class = $id > 0 ? ' class="wp-image-' . esc_attr( (string) $id ) . '"' : '';
		$figure    = '<figure class="wp-block-image size-' . esc_attr( $size ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $img_class . '/>';
		if ( '' !== trim( $caption ) ) {
			$figure .= '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>';
		}
		$figure .= '</figure>';

		$attrs = array( 'sizeSlug' => $size );
		if ( $id > 0 ) {
			$attrs['id'] = $id;
		}

		$block = array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $figure,
			'innerContent' => array( $figure ),
		);

		return serialize_block( $block ) . "\n";
	}

	private function gallery_block( array $wp ): string {
		$ids_raw = $wp['ids'] ?? '';
		$ids     = array();

		if ( is_string( $ids_raw ) ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $ids_raw ) ) );
			foreach ( $parts as $p ) {
				if ( is_numeric( $p ) && (int) $p > 0 ) {
					$ids[] = (int) $p;
				}
			}
		} elseif ( is_array( $ids_raw ) ) {
			foreach ( $ids_raw as $p ) {
				if ( is_numeric( $p ) && (int) $p > 0 ) {
					$ids[] = (int) $p;
				}
			}
		}

		if ( empty( $ids ) ) {
			return '';
		}

		$size    = is_string( $wp['size'] ?? null ) ? sanitize_key( (string) $wp['size'] ) : '';
		$size    = '' !== $size ? $size : 'full';
		$columns = absint( $wp['columns'] ?? 0 );

		$class_columns = $columns > 0 ? 'columns-' . (string) $columns : 'columns-default';
		$out           = '<!-- wp:gallery {"linkTo":"none"} -->' . "\n";
		$out           .= '<figure class="wp-block-gallery has-nested-images ' . esc_attr( $class_columns ) . ' is-cropped">';

		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, $size );
			if ( ! is_string( $url ) || '' === $url ) {
				$url = wp_get_attachment_url( $id );
			}
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$out .= '<!-- wp:image {"id":' . (string) $id . ',"className":"size-' . esc_attr( $size ) . '"} -->';
			$out .= '<figure class="wp-block-image size-' . esc_attr( $size ) . '"><img src="' . esc_url( $url ) . '" alt="" class="wp-image-' . esc_attr( (string) $id ) . '"/></figure>';
			$out .= '<!-- /wp:image -->';
		}

		$out .= '</figure>' . "\n";
		$out .= '<!-- /wp:gallery -->' . "\n";

		return $out;
	}

	private function html_block( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		$block = array(
			'blockName'    => 'core/html',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);

		return serialize_block( $block ) . "\n";
	}

	private function text_block( array $wp ): string {
		$text = '';
		if ( is_string( $wp['text'] ?? null ) ) {
			$text = (string) $wp['text'];
		}
		if ( '' === trim( $text ) && is_string( $wp['content'] ?? null ) ) {
			$text = (string) $wp['content'];
		}

		return $this->html_block( $text );
	}

	private function block_widget_block( array $wp ): string {
		$content = is_string( $wp['content'] ?? null ) ? (string) $wp['content'] : '';
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}
		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return '';
		}

		return $content . "\n";
	}

	private function media_block( string $type, array $wp ): string {
		$id  = absint( $wp['attachment_id'] ?? 0 );
		$url = is_string( $wp['url'] ?? null ) ? (string) $wp['url'] : '';
		$url = trim( $url );
		if ( '' === $url && $id > 0 ) {
			$resolved = wp_get_attachment_url( $id );
			if ( is_string( $resolved ) && '' !== $resolved ) {
				$url = $resolved;
			}
		}
		if ( '' === $url ) {
			return '';
		}

		if ( 'audio' === $type ) {
			$figure = '<figure class="wp-block-audio"><audio controls src="' . esc_url( $url ) . '"></audio></figure>';
			$name   = 'core/audio';
		} else {
			$figure = '<figure class="wp-block-video"><video controls src="' . esc_url( $url ) . '"></video></figure>';
			$name   = 'core/video';
		}

		$block = array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $figure,
			'innerContent' => array( $figure ),
		);

		return serialize_block( $block ) . "\n";
	}
}
