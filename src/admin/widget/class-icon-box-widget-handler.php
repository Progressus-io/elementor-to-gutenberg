<?php
/**
 * Widget handler for Elementor icon box widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Alignment_Helper;
use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Helper\External_Style_Collector;
use Progressus\BlockShift\Admin\Helper\Icon_Parser;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon box widget.
 */
class Icon_Box_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor icon box to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		return $this->handle_settings(
			is_array( $element['settings'] ?? null ) ? $element['settings'] : array()
		);
	}

	/**
	 * Build the block from a settings array.
	 *
	 * Split out so a widget with the same anatomy but different control names -
	 * Header Footer Elementor's info card - can reuse this without pretending to
	 * be an Elementor icon box.
	 *
	 * @param array $settings     Elementor widget settings.
	 * @param bool  $default_icon Whether a missing icon falls back to Elementor's
	 *                            own default star, which is what its icon box shows.
	 */
	public function handle_settings( array $settings, bool $default_icon = true ): string {
		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$alignment      = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment', 'text_align' ) );
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( trim( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' ) );

		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();

		$icon_data   = $this->resolve_icon_data( $settings );
		$icon_value  = trim( $icon_data['class_name'] );
		// 50px is Elementor's own default icon size for this widget.
		$size        = $this->sanitize_slider_value( $settings['size'] ?? null, 50 );
		$title       = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
		$description = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : '';

		// Normalize Elementor "start/end" to CSS text-align values.
		$alignment_value = is_string( $alignment ) ? trim( strtolower( $alignment ) ) : '';
		if ( 'start' === $alignment_value ) {
			$alignment_value = 'left';
		} elseif ( 'end' === $alignment_value ) {
			$alignment_value = 'right';
		}
		if ( '' === $alignment_value ) {
			/*
			 * Elementor aligns an icon box to match where the icon sits: stacked on top
			 * means centred, beside the text means aligned to that side. Defaulting
			 * everything to left pulled centred service cards out of line.
			 */
			$position = isset( $settings['position'] ) ? strtolower( trim( (string) $settings['position'] ) ) : '';

			switch ( $position ) {
				case 'left':
					$alignment_value = 'left';
					break;
				case 'right':
					$alignment_value = 'right';
					break;
				default:
					$alignment_value = 'center';
					break;
			}
		}

		$align_payload = Alignment_Helper::build_text_alignment_payload( $alignment_value );

		/*
		 * The icon has to be decided once and then used for both the markup and
		 * the block attributes. The block's save() draws the icon from
		 * `iconStyle` and `icon`, so anything written here that those two do not
		 * spell out - Elementor's full class list, or a star that only the
		 * markup knows about - is a difference the editor reports as invalid
		 * content.
		 */
		$svg_url    = ( 'svg' === $icon_data['type'] && '' !== $icon_data['url'] ) ? (string) $icon_data['url'] : '';
		$icon_style = $this->sanitize_class_tokens( isset( $icon_data['style_class'] ) ? (string) $icon_data['style_class'] : '' );
		$icon_slug  = $this->sanitize_class_tokens( isset( $icon_data['slug'] ) ? (string) $icon_data['slug'] : '' );

		if ( '' === $svg_url && '' === $icon_slug && '' === $icon_value && $default_icon ) {
			// Elementor's icon box shows a star when nothing has been picked.
			$icon_style = 'fas';
			$icon_slug  = 'fa-star';
		}

		if ( '' === $icon_style ) {
			$icon_style = 'fas';
		}

		$icon_html = '';

		if ( '' !== $svg_url ) {
			$icon_html = sprintf(
				'<img src="%1$s" alt="" style="width:%2$dpx;height:auto;" class="svg-icon"/>',
				esc_url( $svg_url ),
				$size
			);
		} elseif ( '' !== $icon_slug ) {
			$icon_html = sprintf(
				'<i class="%1$s" style="font-size:%2$dpx"></i>',
				esc_attr( $icon_style . ' ' . $icon_slug ),
				$size
			);
		}

		$segments = array();
		if ( '' !== $icon_html ) {
			$segments[] = '<div class="icon-box-icon">' . $icon_html . '</div>';
		}

		// Determine title/description typographic defaults (fall back to sensible values).
		$title_size        = isset( $typography_attr['fontSize'] ) ? (int) $typography_attr['fontSize'] : 20;
		$description_size  = isset( $typography_attr['descriptionSize'] ) ? (int) $typography_attr['descriptionSize'] : 14;

		/*
		 * Only pin a colour when Elementor actually carried one. Elementor leaves an
		 * unstyled icon box to the theme's heading and body colours; baking in black
		 * and grey here made every converted card ignore the site palette.
		 */
		$title_color       = isset( $typography_attr['color'] ) ? (string) $typography_attr['color'] : '';
		$description_color = isset( $typography_attr['descriptionColor'] ) ? (string) $typography_attr['descriptionColor'] : '';

		$title_style       = 'font-size:' . $title_size . 'px' . ( '' !== $title_color ? ';color:' . $title_color : '' );
		$description_style = 'font-size:' . $description_size . 'px' . ( '' !== $description_color ? ';color:' . $description_color : '' );

		// Elementor lets the card's heading sit at whatever level suits the page.
		$title_tag = $this->sanitize_title_tag( $settings['title_size'] ?? null );

		if ( '' !== trim( $title ) ) {
			$segments[] = sprintf(
				'<%1$s class="icon-box-title" style="%2$s">%3$s</%1$s>',
				$title_tag,
				esc_attr( $title_style ),
				wp_kses_post( $title )
			);
		}
		if ( '' !== trim( $description ) ) {
			$segments[] = '<div class="icon-box-description" style="' . esc_attr( $description_style ) . '">' . wp_kses_post( $description ) . '</div>';
		}

		/*
		 * Elementor applies the widget's own padding to the card wrapper, which is
		 * what keeps a card's text narrower than the image above it. The block's
		 * save() writes nothing but the text alignment there, so the padding goes
		 * to the conversion's stylesheet under a class of its own rather than
		 * inline, where it would leave the card unopenable in the editor.
		 */
		$wrapper_padding = $this->build_widget_padding( $settings['_padding'] ?? null );
		$collector       = External_Style_Collector::get_active();
		if ( '' !== $wrapper_padding && $collector instanceof External_Style_Collector ) {
			$padding_class = $collector->externalize_declarations(
				'icon-box',
				array( 'padding' => $wrapper_padding )
			);
			if ( '' !== $padding_class ) {
				$custom_classes[] = $padding_class;
			}
		}

		$wrapper_classes = array_merge( array( 'wp-block-icon-box' ), $align_payload['classes'], $custom_classes );
		$wrapper_attrs   = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		if ( '' !== $custom_id ) {
			$wrapper_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		// $alignment_value is already normalised above - Elementor's raw "start"/"end"
		// are not valid text-align keywords, and the default depends on icon position.
		$wrapper_style = 'text-align:' . $alignment_value;

		$wrapper_attrs[] = 'style="' . esc_attr( $wrapper_style ) . '"';

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . implode( '', $segments ) . '</div>';

		// Build block attributes for the new `blockshift/icon-box` block.
		//
		// These three come from the Elementor data and end up in the block's
		// markup as a class list and an image URL, so they are reduced to what
		// those positions can legitimately hold before they are stored, rather
		// than only when they are printed.
		$block_attributes = array(
			'icon'             => $icon_slug,
			'iconStyle'        => $icon_style,
			'svgUrl'           => '' !== $svg_url ? esc_url_raw( $svg_url ) : '',
			'svgStyle'         => '' !== $svg_url ? ( 'width:' . $size . 'px;height:auto;' ) : '',
			'size'             => $size,
			'title'            => wp_kses_post( $title ),
			'description'      => $description,
			'titleTag'         => $title_tag,
			'titleSize'        => $title_size,
			'titleColor'       => $title_color,
			'descriptionSize'  => $description_size,
			'descriptionColor' => $description_color,
			'alignment'        => $alignment_value,

		);
		if ( '' !== $custom_css ) {
			Style_Parser::note_skipped_custom_css( $custom_css );
		}

		return Block_Builder::build( 'blockshift/icon-box', $block_attributes, $content );
	}

	/**
	 * Reduce Elementor's heading tag control to a tag the block can render.
	 *
	 * @param mixed $value Raw control value.
	 *
	 * @return string The tag name, falling back to the block's own default.
	 */
	private function sanitize_title_tag( $value ): string {
		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );
		$tag     = is_string( $value ) ? strtolower( trim( $value ) ) : '';

		return in_array( $tag, $allowed, true ) ? $tag : 'h3';
	}

	/**
	 * Resolve the icon data from Elementor settings.
	 */
	private function resolve_icon_data( array $settings ): array {
		$icon_setting = isset( $settings['selected_icon'] ) && is_array( $settings['selected_icon'] ) ? $settings['selected_icon'] : null;
		$icon_data    = Icon_Parser::parse_selected_icon( $icon_setting );

		if ( '' === $icon_data['class_name'] && '' === $icon_data['url'] && isset( $settings['icon'] ) ) {
			$icon_data = Icon_Parser::parse_selected_icon(
				array(
					'value'   => $settings['icon'],
					'library' => 'fa-solid',
				)
			);
		}

		return $icon_data;
	}

	/**
	 * Reduce a value to a space-separated list of CSS class tokens.
	 *
	 * Icon class names arrive from Elementor and are written into a `class`
	 * attribute, so anything that is not a class token - quotes above all - is
	 * dropped here, before the value becomes a block attribute.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_class_tokens( string $value ): string {
		$tokens = preg_split( '/\s+/', trim( $value ) );
		if ( ! is_array( $tokens ) ) {
			return '';
		}

		$clean = array();
		foreach ( $tokens as $token ) {
			$token = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $token );
			if ( '' !== $token ) {
				$clean[] = $token;
			}
		}

		return implode( ' ', $clean );
	}

	/**
	 * Sanitize tooltip position value.
	 */
	private function sanitize_tooltip_position( $value ): string {
		$positions = array( 'top', 'bottom', 'left', 'right' );
		if ( ! is_string( $value ) ) {
			return 'top';
		}

		$parts = explode( ',', $value );
		$first = trim( strtolower( $parts[0] ?? '' ) );

		return in_array( $first, $positions, true ) ? $first : 'top';
	}

	/**
	 * Sanitize custom class string into individual classes.
	 */
	private function sanitize_custom_classes( string $class_string ): array {
		$classes = array();
		foreach ( preg_split( '/\s+/', $class_string ) as $class ) {
			$clean = Style_Parser::clean_class( $class );
			if ( '' === $clean ) {
				continue;
			}
			$classes[] = $clean;
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Sanitize slider or numeric values from Elementor settings.
	 */
	/**
	 * Turn Elementor's widget `_padding` control into a CSS padding shorthand.
	 *
	 * Elementor applies this padding to the widget wrapper, which is what keeps a card's
	 * text narrower than the image above it. Dropping it lets the copy run the full
	 * width of the column and re-wrap differently from the original.
	 *
	 * @param mixed $padding Raw `_padding` control value.
	 *
	 * @return string CSS shorthand, or an empty string when nothing usable is set.
	 */
	private function build_widget_padding( $padding ): string {
		if ( ! is_array( $padding ) ) {
			return '';
		}

		$unit = isset( $padding['unit'] ) && is_string( $padding['unit'] ) ? $padding['unit'] : 'px';
		if ( ! in_array( $unit, array( 'px', 'em', 'rem', '%' ), true ) ) {
			return '';
		}

		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( ! isset( $padding[ $side ] ) || ! is_numeric( $padding[ $side ] ) ) {
				return '';
			}
			$sides[] = ( (string) (float) $padding[ $side ] ) . $unit;
		}

		// Nothing to emit when every side is zero.
		if ( array( '0' . $unit, '0' . $unit, '0' . $unit, '0' . $unit ) === $sides ) {
			return '';
		}

		return implode( ' ', $sides );
	}

	private function sanitize_slider_value( $value, int $fallback ): int {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
				return (int) round( $value['size'] );
			}
			if ( isset( $value['value'] ) && is_numeric( $value['value'] ) ) {
				return (int) round( $value['value'] );
			}
		}
		if ( is_numeric( $value ) ) {
			return (int) round( $value );
		}

		return $fallback;
	}
}
