<?php
/**
 * Generic safe mappings for selected Elementor widgets.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Generic handler for small safe Elementor-to-core mappings.
 */
class Generic_Elementor_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Convert supported Elementor widgets.
	 *
	 * @param array $element Elementor element data.
	 */
	public function handle( array $element ): string {
		$widget_type = isset( $element['widgetType'] ) && \is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		if ( '' === $widget_type ) {
			return '';
		}

		$settings = isset( $element['settings'] ) && \is_array( $element['settings'] ) ? $element['settings'] : array();

		switch ( $widget_type ) {
			case 'soundcloud':
				return $this->handle_soundcloud( $settings );
			case 'testimonial':
				return $this->handle_testimonial( $settings );
			case 'alert':
				return $this->handle_alert( $settings );
			case 'rating':
			case 'star-rating':
				return $this->handle_rating( $settings );
			case 'sureforms_form':
				return $this->handle_sureforms_form( $settings );
			case 'suredonation-donation-form':
				return $this->handle_suredonation_form( $settings );
			case 'hfe-infocard':
				return $this->handle_infocard( $settings );
			case 'copyright':
				return $this->handle_copyright( $settings );
			case 'hfe-site-title':
				return $this->handle_site_block( 'core/site-title', $settings );
			case 'hfe-site-tagline':
				return $this->handle_site_block( 'core/site-tagline', $settings );
			default:
				return '';
		}
	}

	/**
	 * Build soundcloud -> core/embed.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_soundcloud( array $settings ): string {
		$url = $this->extract_url( $settings, array( 'link', 'url' ) );
		if ( '' === $url ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/embed',
				'attrs'        => array( 'url' => $url ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build testimonial -> core/quote with inner paragraph.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_testimonial( array $settings ): string {
		$content = $this->extract_text( $settings, array( 'content', 'testimonial_content' ) );
		if ( '' === $content ) {
			return '';
		}

		$paragraph = $this->build_paragraph_block( $content );
		if ( array() === $paragraph ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/quote',
				'attrs'        => array(),
				'innerBlocks'  => array( $paragraph ),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build alert -> core/group with inner paragraph.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_alert( array $settings ): string {
		$text = $this->extract_text( $settings, array( 'text', 'message' ) );
		if ( '' === $text ) {
			return '';
		}

		$paragraph = $this->build_paragraph_block( $text );
		if ( array() === $paragraph ) {
			return '';
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array( $paragraph ),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build rating -> paragraph stars.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_rating( array $settings ): string {
		$scale  = $this->extract_rating_scale( $settings );
		$rating = $this->extract_rating_value( $settings, array( 'rating', 'value' ), $scale );

		/*
		 * Elementor's star rating defaults to a full score, and a kit that never
		 * touches the control stores no `rating` at all - dropping the widget in
		 * that case lost stars the page was actually showing.
		 */
		if ( null === $rating ) {
			$rating = (float) $scale;
		}

		$filled = (int) \max( 0, \min( $scale, (int) \round( $rating ) ) );
		$stars  = \str_repeat( '★', $filled ) . \str_repeat( '☆', $scale - $filled );

		$title = $this->extract_text( $settings, array( 'title' ) );
		if ( '' !== $title ) {
			$stars = $title . ' ' . $stars;
		}

		return $this->serialize_parsed_block( $this->build_paragraph_block( $stars, $this->extract_rating_style( $settings ) ) );
	}


	/**
	 * Build a SureForms widget -> the plugin's own shortcode.
	 *
	 * SureForms ships a block, but its markup is an implementation detail of
	 * that plugin; the documented shortcode is the stable way to place a form
	 * and it keeps working when the block's internals change.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_sureforms_form( array $settings ): string {
		$form_id = 0;

		foreach ( array( 'srfm_form_block', 'form_id', 'sureforms_form' ) as $key ) {
			$value = $settings[ $key ] ?? null;
			if ( \is_array( $value ) ) {
				$value = $value['id'] ?? $value['value'] ?? null;
			}

			if ( \is_numeric( $value ) && (int) $value > 0 ) {
				$form_id = (int) $value;
				break;
			}
		}

		if ( 0 === $form_id ) {
			return '';
		}

		$show_title = ! isset( $settings['srfm_show_form_title'] )
			|| \filter_var( $settings['srfm_show_form_title'], FILTER_VALIDATE_BOOLEAN );

		$shortcode = \sprintf(
			'[sureforms id="%d" show_title="%s"]',
			$form_id,
			$show_title ? 'true' : 'false'
		);

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/shortcode',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $shortcode,
				'innerContent' => array( $shortcode ),
			)
		);
	}

	/**
	 * Build a copyright line.
	 *
	 * The widget stores its text under `shortcode` because it lets an author mix
	 * shortcodes into the line - `[hfe_current_year]` above all. A paragraph does
	 * not run shortcodes, so a line that contains one becomes core/shortcode and
	 * keeps working; a plain line stays an ordinary paragraph.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_copyright( array $settings ): string {
		$text = isset( $settings['shortcode'] ) ? \trim( (string) $settings['shortcode'] ) : '';
		if ( '' === $text ) {
			return '';
		}

		if ( \preg_match( '/\[[a-z0-9_-]+/i', $text ) ) {
			return $this->serialize_parsed_block(
				array(
					'blockName'    => 'core/shortcode',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => $text,
					'innerContent' => array( $text ),
				)
			);
		}

		$style = array();
		$color = Style_Parser::extract_text_color_css_value( $settings, 'title_color' );
		if ( ! empty( $color['color'] ) ) {
			$style['color'] = (string) $color['color'];
		}

		return $this->serialize_parsed_block( $this->build_paragraph_block( \wp_kses_post( $text ), $style ) );
	}

	/**
	 * Build one of the site-identity blocks from its Elementor equivalent.
	 *
	 * @param string $block_name Core block to emit.
	 * @param array  $settings   Widget settings.
	 */
	private function handle_site_block( string $block_name, array $settings ): string {
		$attrs = array();

		$align = isset( $settings['align'] ) ? \strtolower( \trim( (string) $settings['align'] ) ) : '';
		if ( \in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$attrs['textAlign'] = $align;
		}

		return $this->serialize_parsed_block(
			array(
				'blockName'    => $block_name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Build a SureDonation widget -> the plugin's own shortcode.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_suredonation_form( array $settings ): string {
		$campaign = $settings['campaign_id'] ?? null;
		if ( \is_array( $campaign ) ) {
			$campaign = $campaign['id'] ?? $campaign['value'] ?? null;
		}

		if ( ! \is_numeric( $campaign ) || (int) $campaign <= 0 ) {
			return '';
		}

		/*
		 * The widget points at a campaign, but the shortcode takes the ID of the
		 * donation form belonging to it - handing it the campaign's own ID matches
		 * no form, and SureDonation then renders nothing at all for a visitor.
		 */
		$shortcode = \sprintf( '[suredonation_form id="%d"]', $this->resolve_donation_form_id( (int) $campaign ) );

		return $this->serialize_parsed_block(
			array(
				'blockName'    => 'core/shortcode',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $shortcode,
				'innerContent' => array( $shortcode ),
			)
		);
	}


	/**
	 * Find the donation form that belongs to a campaign.
	 *
	 * @param int $campaign_id Campaign post ID.
	 *
	 * @return int Form ID, or the campaign ID when no form is linked to it.
	 */
	private function resolve_donation_form_id( int $campaign_id ): int {
		if ( $campaign_id <= 0 ) {
			return 0;
		}

		$forms = \get_posts(
			array(
				'post_type'      => 'suredonation_form',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_suredonation_campaign_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => (string) $campaign_id,
			)
		);

		return ! empty( $forms ) ? (int) $forms[0] : $campaign_id;
	}
	/**
	 * Build a Header Footer Elementor info card.
	 *
	 * It is an icon box under another name - icon, heading, description - so it
	 * converts through the same handler, with the control names translated and
	 * without the default star an Elementor icon box falls back to. Its optional
	 * call to action follows as a button.
	 *
	 * @param array $settings Widget settings.
	 */
	private function handle_infocard( array $settings ): string {
		$mapped = array(
			'title_text'       => $settings['infocard_title'] ?? '',
			'description_text' => $settings['infocard_description'] ?? '',
			'align'            => $settings['infocard_overall_align'] ?? '',
			'selected_icon'    => $settings['infocard_select_icon'] ?? null,
			'size'             => $settings['infocard_icon_size'] ?? null,
			'title_color'      => $settings['infocard_title_color'] ?? '',
			'description_color' => $settings['infocard_desc_color'] ?? '',
			'_css_classes'     => $settings['_css_classes'] ?? '',
			'_element_id'      => $settings['_element_id'] ?? '',
			'_padding'         => $settings['_padding'] ?? null,
			'__globals__'      => $settings['__globals__'] ?? array(),
		);

		$handler = new Icon_Box_Widget_Handler();
		$block   = $handler->handle_settings( $mapped, false );

		if ( '' === $block ) {
			return '';
		}

		return $block . $this->build_infocard_cta( $settings );
	}

	/**
	 * Build the info card's call to action, when it has one.
	 *
	 * @param array $settings Widget settings.
	 */
	private function build_infocard_cta( array $settings ): string {
		$type = \strtolower( \trim( (string) ( $settings['infocard_cta_type'] ?? 'none' ) ) );
		if ( 'button' !== $type && 'link' !== $type ) {
			return '';
		}

		$text = 'button' === $type
			? (string) ( $settings['infocard_button_text'] ?? '' )
			: (string) ( $settings['infocard_link_text'] ?? '' );
		$text = \trim( \wp_strip_all_tags( $text ) );

		if ( '' === $text ) {
			return '';
		}

		$url = '';
		if ( \is_array( $settings['infocard_text_link'] ?? null ) ) {
			$url = (string) ( $settings['infocard_text_link']['url'] ?? '' );
		}

		$attrs = array();
		if ( '' !== $url ) {
			$attrs['url'] = \esc_url_raw( $url );
		}

		$button = Block_Builder::build_prepared(
			'button',
			$attrs,
			static function ( array $prepared ) use ( $text ) {
				$href = isset( $prepared['url'] ) ? (string) $prepared['url'] : '';

				return \sprintf(
					'<a class="%1$s"%2$s>%3$s</a>',
					\esc_attr( Block_Builder::build_button_link_class( $prepared ) ),
					'' === $href ? '' : ' href="' . \esc_url( $href ) . '"',
					\esc_html( $text )
				);
			}
		);

		return Block_Builder::build( 'buttons', array(), $button );
	}
	/**
	 * Read the widget's rating scale (Elementor offers 5 or 10).
	 *
	 * @param array $settings Widget settings.
	 */
	private function extract_rating_scale( array $settings ): int {
		$scale = $settings['rating_scale'] ?? null;
		if ( \is_array( $scale ) ) {
			$scale = $scale['size'] ?? $scale['value'] ?? null;
		}

		$scale = \is_numeric( $scale ) ? (int) $scale : 5;

		return ( $scale >= 1 && $scale <= 10 ) ? $scale : 5;
	}

	/**
	 * Read the star colour and size so the converted rating keeps its styling.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<string, string>
	 */
	private function extract_rating_style( array $settings ): array {
		$style   = array();
		$globals = \is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array();

		foreach ( array( $settings['stars_color'] ?? '', $globals['stars_color'] ?? '' ) as $candidate ) {
			if ( '' === $candidate || null === $candidate ) {
				continue;
			}

			$resolved = Style_Parser::resolve_elementor_color_reference( $candidate );
			if ( ! empty( $resolved['color'] ) ) {
				$style['color'] = (string) $resolved['color'];
				break;
			}
		}

		$size = $settings['icon_size'] ?? null;
		if ( \is_array( $size ) && \is_numeric( $size['size'] ?? null ) ) {
			$style['fontSize'] = $size['size'] . ( $size['unit'] ?? 'px' );
		}

		return $style;
	}

	/**
	 * Build a core/paragraph parsed block from plain text.
	 *
	 * @param string $content Plain text content.
	 */
	private function build_paragraph_block( string $content, array $style = array() ): array {
		$content = \trim( $content );
		if ( '' === $content ) {
			return array();
		}

		$attrs   = array( 'content' => $content );
		$classes = array();
		$rules   = array();

		if ( ! empty( $style['color'] ) ) {
			$attrs['style']['color']['text'] = $style['color'];
			$classes[]                       = 'has-text-color';
			$rules[]                         = 'color:' . $style['color'];
		}

		if ( ! empty( $style['fontSize'] ) ) {
			$attrs['style']['typography']['fontSize'] = $style['fontSize'];
			$classes[]                                = 'has-custom-font-size';
			$rules[]                                  = 'font-size:' . $style['fontSize'];
		}

		$attributes = '';
		if ( array() !== $classes ) {
			$attributes .= ' class="' . \esc_attr( \implode( ' ', $classes ) ) . '"';
		}
		if ( array() !== $rules ) {
			$attributes .= ' style="' . \esc_attr( \implode( ';', $rules ) ) . '"';
		}

		/*
		 * core/paragraph is a static block: the markup saved with it is what the
		 * front end prints, so a `content` attribute on its own renders nothing.
		 */
		$markup = '<p' . $attributes . '>' . $content . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $markup,
			'innerContent' => array( $markup ),
		);
	}

	/**
	 * Serialize a parsed block via core serializer only.
	 *
	 * @param array $block Parsed block array.
	 */
	private function serialize_parsed_block( array $block ): string {
		if ( array() === $block || ! \function_exists( 'serialize_block' ) ) {
			return '';
		}

		return \serialize_block( $block ) . "\n";
	}

	/**
	 * Extract sanitized plain text by preferred keys.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_text( array $settings, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! \is_string( $settings[ $key ] ) ) {
				continue;
			}

			$text = \wp_strip_all_tags( $settings[ $key ] );
			$text = \trim( $text );
			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Extract sanitized URL from string or Elementor URL array.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_url( array $settings, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$candidate = '';
			if ( \is_string( $settings[ $key ] ) ) {
				$candidate = $settings[ $key ];
			} elseif ( \is_array( $settings[ $key ] ) && \is_string( $settings[ $key ]['url'] ?? null ) ) {
				$candidate = $settings[ $key ]['url'];
			}

			$candidate = \trim( $candidate );
			if ( '' === $candidate ) {
				continue;
			}

			$url = \esc_url_raw( $candidate );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Extract rating value 1-5.
	 *
	 * @param array $settings Source settings.
	 * @param array $keys Candidate keys.
	 */
	private function extract_rating_value( array $settings, array $keys, int $scale = 5 ): ?float {
		foreach ( $keys as $key ) {
			$value = $settings[ $key ] ?? null;
			if ( \is_array( $value ) ) {
				$value = $value['size'] ?? $value['value'] ?? null;
			}

			if ( ! \is_numeric( $value ) ) {
				continue;
			}

			$value = (float) $value;
			if ( $value >= 0 && $value <= $scale ) {
				return $value;
			}
		}

		return null;
	}

}
