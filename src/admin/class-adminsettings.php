<?php

namespace Progressus\Gutenberg\Admin;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Class AdminSettings
 *
 * Handles the admin settings for the Mighty Kids plugin.
 *
 * @package Progressus\Gutenberg\Admin
 */
class AdminSettings {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			'manage_options',
			'gutenberg-settings',
			array( $this, 'settings_page_content' ),
			'dashicons-admin-generic',
			100
		);
	}

	public function settings_init() {
		register_setting(
			'gutenberg_settings_group',
			'gutenberg_json_data',
			array( 'sanitize_callback' => array( $this, 'handle_json_upload' ) )
		);

		add_settings_section(
			'gutenberg_settings_section',
			esc_html__( 'Upload JSON Data', 'elementor-to-gutenberg' ),
			null,
			'gutenberg-settings'
		);

		add_settings_field(
			'gutenberg_json_upload',
			esc_html__( 'JSON File', 'elementor-to-gutenberg' ),
			array( $this, 'json_upload_field_callback' ),
			'gutenberg-settings',
			'gutenberg_settings_section'
		);
	}

	public function json_upload_field_callback() {
		echo '<input type="file" name="json_upload" accept=".json">';
	}

	public function handle_json_upload( $option ) {
		if ( ! empty( $_FILES['json_upload']['tmp_name'] ) ) {
			$file         = $_FILES['json_upload'];
			$json_content = file_get_contents( $file['tmp_name'] );
			$data         = json_decode( $json_content, true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				// Convert JSON to Gutenberg content
				$gutenberg_content = $this->convert_json_to_gutenberg_content( $data );

				// Use title and type from JSON, fallback to defaults if missing
				$post_title = isset( $data['title'] ) ? $data['title'] : 'Untitled';
				$post_type  = isset( $data['type'] ) ? $data['type'] : 'page';

				// Create new post/page
				$new_post_id = wp_insert_post(
					array(
						'post_title'   => $post_title,
						'post_content' => $gutenberg_content,
						'post_type'    => $post_type,
						'post_status'  => 'publish',
					)
				);

				if ( is_wp_error( $new_post_id ) ) {
					add_settings_error(
						'gutenberg_json_data',
						'json_upload_error',
						esc_html__( 'Failed to create new page.', 'elementor-to-gutenberg' ),
						'error'
					);
					return get_option( 'gutenberg_json_data' );
				}

				// Add success message
				add_settings_error(
					'gutenberg_json_data',
					'json_upload_success',
					esc_html__( 'JSON file uploaded and page created successfully!', 'elementor-to-gutenberg' ),
					'updated'
				);

				return $gutenberg_content;
			} else {
				add_settings_error(
					'gutenberg_json_data',
					'json_upload_error',
					esc_html__( 'Invalid JSON file uploaded.', 'elementor-to-gutenberg' ),
					'error'
				);

				return get_option( 'gutenberg_json_data' ); // Return existing data on error
			}
		}
		return $option; // Return existing option if no new file is uploaded
	}

	public function settings_page_content() {
		echo '<div class="wrap">';
		echo '<h1>Gutenberg Settings</h1>';
		// Show success/error messages
		settings_errors( 'gutenberg_json_data' );
		?>
		<form method="post" action="options.php" enctype="multipart/form-data" id="json-upload-form">
			<?php settings_fields( 'gutenberg_settings_group' ); ?>
			<?php do_settings_sections( 'gutenberg-settings' ); ?>
			<button type="submit" class="button button-primary" id="json-upload-btn">Upload JSON File</button>
			<span id="json-upload-spinner" style="display:none;margin-left:10px;">
				<img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>" alt="Loading" /> Uploading...
			</span>
		</form>
		<script>
			document.getElementById( 'json-upload-form' ).addEventListener( 'submit', function() {
				document.getElementById( 'json-upload-btn' ).disabled = true;
				document.getElementById( 'json-upload-spinner' ).style.display = 'inline-block';
			} );
		</script>
		<?php
		echo '</div>';
	}

	/**
	 * Parse typography settings from the element.
	 *
	 * @param  array $settings The settings array.
	 * @return array [ 'attributes' => array, 'style' => string ]
	 */
	public function parse_typography_settings( $settings ) {
		$attrs = array();
		$style = '';

		if ( isset( $settings['typography_font_family'] ) ) {
			$attrs['fontFamily'] = $settings['typography_font_family'];
			$style              .= 'font-family:' . esc_attr( $settings['typography_font_family'] ) . ';';
		}
		if ( isset( $settings['typography_font_size'] ) && is_array( $settings['typography_font_size'] ) ) {
			$size = isset( $settings['typography_font_size']['size'] ) ? $settings['typography_font_size']['size'] : '';
			$unit = isset( $settings['typography_font_size']['unit'] ) ? $settings['typography_font_size']['unit'] : 'px';
			if ( $size !== '' ) {
				$attrs['fontSize'] = $size . $unit;
				$style            .= 'font-size:' . esc_attr( $size ) . esc_attr( $unit ) . ';';
			}
		}
		if ( isset( $settings['typography_font_weight'] ) ) {
			$attrs['fontWeight'] = $settings['typography_font_weight'];
			$style              .= 'font-weight:' . esc_attr( $settings['typography_font_weight'] ) . ';';
		}
		if ( isset( $settings['typography_line_height'] ) && is_array( $settings['typography_line_height'] ) ) {
			$size = isset( $settings['typography_line_height']['size'] ) ? $settings['typography_line_height']['size'] : '';
			$unit = isset( $settings['typography_line_height']['unit'] ) ? $settings['typography_line_height']['unit'] : '';
			if ( $size !== '' ) {
				$attrs['lineHeight'] = $size . $unit;
				$style              .= 'line-height:' . esc_attr( $size ) . ( $unit ? esc_attr( $unit ) : '' ) . ';';
			}
		}
		if ( isset( $settings['typography_font_style'] ) ) {
			$attrs['fontStyle'] = $settings['typography_font_style'];
			$style             .= 'font-style:' . esc_attr( $settings['typography_font_style'] ) . ';';
		}
		if ( isset( $settings['typography_text_decoration'] ) ) {
			$attrs['textDecoration'] = $settings['typography_text_decoration'];
			$style                  .= 'text-decoration:' . esc_attr( $settings['typography_text_decoration'] ) . ';';
		}
		if ( isset( $settings['typography_letter_spacing']['size'] ) ) {
			$val                    = $settings['typography_letter_spacing']['size'] . ( isset( $settings['typography_letter_spacing']['unit'] ) ? $settings['typography_letter_spacing']['unit'] : '' );
			$attrs['letterSpacing'] = $val;
			$style                 .= 'letter-spacing:' . esc_attr( $val ) . ';';
		}
		if ( isset( $settings['typography_word_spacing']['size'] ) ) {
			$val                  = $settings['typography_word_spacing']['size'] . ( isset( $settings['typography_word_spacing']['unit'] ) ? $settings['typography_word_spacing']['unit'] : '' );
			$attrs['wordSpacing'] = $val;
			$style               .= 'word-spacing:' . esc_attr( $val ) . ';';
		}

		return array(
			'attributes' => $attrs,
			'style'      => $style,
		);
	}

	/**
	 * Parse spacing settings from the element.
	 *
	 * @param  array $settings The settings array.
	 * @param  array $attrs    The attributes array to modify.
	 * @return array The modified attributes array with spacing styles.
	 */
	public function parse_spacing_settings( $settings, $attrs ) {
		foreach ( array( 'margin', 'padding' ) as $spacing ) {
			$spacing_key = '_' . $spacing;
			if ( isset( $settings[ $spacing_key ] ) && is_array( $settings[ $spacing_key ] ) ) {
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( isset( $settings[ $spacing_key ][ $side ] ) ) {
						$attrs['style'][ $spacing ][ $side ] = $settings[ $spacing_key ][ $side ] . ( isset( $settings[ $spacing_key ]['unit'] ) ? $settings[ $spacing_key ]['unit'] : 'px' );
					}
				}
			}
		}
		return $attrs;
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param  array $json_data The JSON data to convert.
	 * @return string The converted Gutenberg content.
	 */
	public function convert_json_to_gutenberg_content( $json_data ) {
		if ( ! isset( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
			return '';
		}

		$content = '';

		// Start parsing from top-level content
		$content .= $this->parse_elements( $json_data['content'] );

		return $content;
	}

	/**
	 * Check if the video element has an overlay enabled.
	 *
	 * @param  array $elements The elements to check.
	 * @return bool True if any video has overlay enabled.
	 */
	private function check_for_video_overlays( $settings ) {
		if ( isset( $settings['show_image_overlay'] ) && $settings['show_image_overlay'] === 'yes' ) {
			return true;
		}
		return false;
	}

	/**
	 * Download a file using cURL as a fallback method
	 *
	 * @param string $url The URL to download
	 * @return string|WP_Error The temporary file path or WP_Error on failure
	 */
	private function download_file_with_curl( $url ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return new WP_Error( 'curl_not_available', 'cURL is not available on this server' );
		}

		$tmp_file = wp_tempnam( basename( $url ) );
		if ( ! $tmp_file ) {
			return new WP_Error( 'temp_file_failed', 'Could not create temporary file' );
		}

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $url,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_FILE           => fopen( $tmp_file, 'w' ),
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
				CURLOPT_HTTPHEADER     => array(
					'Accept: video/mp4,video/*,*/*;q=0.9',
					'Accept-Language: en-US,en;q=0.9',
					'Cache-Control: no-cache',
					'Pragma: no-cache',
				),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
			)
		);

		$result    = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		curl_close( $ch );

		if ( $result === false || $http_code !== 200 ) {
			@unlink( $tmp_file );
			error_log( 'cURL download failed - HTTP Code: ' . $http_code . ', Error: ' . $error );
			return new WP_Error( 'curl_download_failed', 'cURL download failed: ' . $error );
		}

		return $tmp_file;
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param  array $elements The elements to convert.
	 * @return string The converted Gutenberg content.
	 */
	public function parse_elements( $elements ) {
		$block_content = '';
		foreach ( $elements as $element ) {
			// Handle containers
			if ( isset( $element['elType'] ) && $element['elType'] === 'container' ) {
				$inner = '';
				if ( ! empty( $element['elements'] ) ) {
					$inner = $this->parse_elements( $element['elements'] );
				}
				// Use group block for containers
				$block_content .= "<!-- wp:group --><div class=\"wp-block-group\">{$inner}</div><!-- /wp:group -->";
			}
			// Handle widgets
			elseif ( isset( $element['elType'] ) && $element['elType'] === 'widget' ) {
				$color = isset( $element['settings']['text_color'] ) ? $element['settings']['text_color'] : '';
				switch ( $element['widgetType'] ) {
					case 'heading':
						$title = isset( $element['settings']['title'] ) ? $element['settings']['title'] : '';
						$color = isset( $element['settings']['title_color'] ) ? $element['settings']['title_color'] : '';

						$class = $color ? 'has-text-color' : '';
						$style = '';
						// Add text-transform class if set.
						if ( isset( $element['settings']['typography_text_transform'] ) ) {
							$class .= ' has-text-transform-' . esc_attr( $element['settings']['typography_text_transform'] );
						}
						if ( $color ) {
							$style .= 'color:' . esc_attr( $color ) . ';';
						}
						$typography = $this->parse_typography_settings( $element['settings'] );
						if ( $typography ) {
							$style .= $typography['style'];
						}
						// Move styles to block attributes for Gutenberg compatibility.
						$attrs_array = array();

						// Prepare block style attributes.
						if ( $style ) {
							// Only set supported attributes: color, fontSize, fontFamily, fontWeight, lineHeight, margin, padding
							if ( $color ) {
								$attrs_array['style']['color']['text'] = $color;
							}
							// Typography
							$attrs_array['style']['typography'] = $typography['attributes'];
							// Margin & Padding
							$attrs_array = $this->parse_spacing_settings( $element['settings'], $attrs_array );
						}

						$attrs = wp_json_encode( $attrs_array );

						// Use heading level 2 by default, can be customized if needed
						$block_content .= "<!-- wp:heading {$attrs} -->";
						$block_content .= "<h2 class=\"wp-block-heading {$class}\"";
						if ( $style ) {
							$block_content .= ' style="' . esc_attr( $style ) . '"';
						}
						$block_content .= '>' . esc_html( $title ) . "</h2><!-- /wp:heading -->\n";
						break;
					case 'text-editor':
						$text  = isset( $element['settings']['editor'] ) ? $element['settings']['editor'] : '';
						$color = isset( $element['settings']['text_color'] ) ? $element['settings']['text_color'] : '';
						$class = $color ? 'has-text-color' : '';
						// Add text-transform class if set.
						if ( isset( $element['settings']['typography_text_transform'] ) ) {
							$class .= ' has-text-transform-' . esc_attr( $element['settings']['typography_text_transform'] );
						}
						$style = '';
						if ( $color ) {
							$style .= 'color:' . esc_attr( $color ) . ';';
						}
						$typography = $this->parse_typography_settings( $element['settings'] );
						if ( $typography ) {
							$style .= $typography['style'];
						}
						// Move styles to block attributes for Gutenberg compatibility
						$attrs_array = array();

						// Prepare block style attributes
						if ( $style ) {
							// Only set supported attributes: color, fontSize, fontFamily, fontWeight, lineHeight, margin, padding
							if ( $color ) {
								$attrs_array['style']['color']['text'] = $color;
							}
							// Typography
							$attrs_array['style']['typography'] = $typography['attributes'];

							// Margin & Padding
							$attrs_array = $this->parse_spacing_settings( $element['settings'], $attrs_array );
						}

						$attrs          = wp_json_encode( $attrs_array );
						$block_content .= "<!-- wp:html {$attrs} -->";
						$block_content .= "<div class=\"wp-block-paragraph {$class}\"";
						if ( $style ) {
							$block_content .= ' style="' . esc_attr( $style ) . '"';
						}
						$block_content .= '>' . wp_kses_post( $text ) . "</div><!-- /wp:html -->\n";
						break;
					case 'image':
						$url = isset( $element['settings']['image']['url'] ) ? $element['settings']['image']['url'] : '';
						$alt = isset( $element['settings']['image']['alt'] ) ? $element['settings']['image']['alt'] : '';

						$new_url = '';
						if ( $url ) {
							// Download image to temp file
							$tmp_file = download_url( $url );
							if ( ! is_wp_error( $tmp_file ) ) {
								$file_array = array(
									'name'     => basename( $url ),
									'tmp_name' => $tmp_file,
								);
								// Upload to media library
								$attachment_id = media_handle_sideload( $file_array, 0 );
								if ( ! is_wp_error( $attachment_id ) ) {
									$new_url = wp_get_attachment_url( $attachment_id );
								}
								// Clean up temp file if needed
								if ( file_exists( $tmp_file ) ) {
									@unlink( $tmp_file );
								}
							}
						}
						$image_url     = $new_url ? $new_url : '';
						$attachment_id = 0;
						if ( $new_url ) {
							// Try to get the attachment ID by URL
							$attachment_id = attachment_url_to_postid( $new_url );
						}
						$attrs_array = array(
							'id'              => $attachment_id,
							'sizeSlug'        => 'full',
							'linkDestination' => 'none',
						);

						$attrs_array = $this->parse_spacing_settings( $element['settings'], $attrs_array );
						$classes     = '';

						if ( isset( $element['settings']['align'] ) ) {
							$attrs_array['align'] = $element['settings']['align'];
							$classes             .= 'align' . $element['settings']['align'];
						}
						if ( isset( $element['settings']['width']['size'] ) && $element['settings']['width']['size'] !== '' ) {
							$attrs_array['width'] = $element['settings']['width']['size'] . ( isset( $element['settings']['width']['unit'] ) ? $element['settings']['width']['unit'] : '%' );
						}
						if ( isset( $element['settings']['height']['size'] ) && $element['settings']['height']['size'] !== '' ) {
							$attrs_array['height'] = $element['settings']['height']['size'] . ( isset( $element['settings']['height']['unit'] ) ? $element['settings']['height']['unit'] : '%' );
						}
						if ( isset( $element['settings']['space']['size'] ) && $element['settings']['space']['size'] !== '' ) {
							$attrs_array['space'] = $element['settings']['space']['size'] . ( isset( $element['settings']['space']['unit'] ) ? $element['settings']['space']['unit'] : '%' );
						}
						if ( isset( $element['settings']['premium_tooltip_text'] ) ) {
							$attrs_array['premiumTooltipText'] = $element['settings']['premium_tooltip_text'];
						}
						if ( isset( $element['settings']['premium_tooltip_position'] ) ) {
							$attrs_array['premiumTooltipPosition'] = $element['settings']['premium_tooltip_position'];
						}
						$attrs          = wp_json_encode( $attrs_array );
						$img_tag        = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . esc_attr( $attachment_id ) . '"/>';
						$block_content .= "<!-- wp:image {$attrs} -->\n<figure class=\"wp-block-image " . esc_attr( $classes ) . "\">{$img_tag}</figure>\n<!-- /wp:image -->\n";
						break;
					case 'button':
						$text        = isset( $element['settings']['text'] ) ? $element['settings']['text'] : '';
						$url         = isset( $element['settings']['link']['url'] ) ? $element['settings']['link']['url'] : '';
						$attrs_array = array();

						// Button URL
						if ( $url ) {
							$attrs_array['url'] = esc_url( $url );
						}

						// Alignment
						if ( isset( $element['settings']['align'] ) ) {
							$attrs_array['align'] = $element['settings']['align'];
						}

						// Size
						if ( isset( $element['settings']['size'] ) ) {
							$attrs_array['size'] = $element['settings']['size'];
						}

						// Text shadow
						if ( isset( $element['settings']['text_shadow_text_shadow_type'] ) && $element['settings']['text_shadow_text_shadow_type'] === 'yes' ) {
							$attrs_array['style']['textShadow'] = '1px 1px 2px #000'; // Example, customize as needed
						}

						// Background color
						if ( isset( $element['settings']['background_color'] ) ) {
							$attrs_array['style']['color']['background'] = $element['settings']['background_color'];
						}

						// Border
						if ( isset( $element['settings']['border_border'] ) ) {
							$attrs_array['style']['border']['style'] = $element['settings']['border_border'];
						}
						if ( isset( $element['settings']['border_width'] ) && is_array( $element['settings']['border_width'] ) ) {
							foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
								if ( isset( $element['settings']['border_width'][ $side ] ) ) {
									$attrs_array['style']['border']['width'][ $side ] = $element['settings']['border_width'][ $side ] . ( isset( $element['settings']['border_width']['unit'] ) ? $element['settings']['border_width']['unit'] : 'px' );
								}
							}
						}
						if ( isset( $element['settings']['border_radius'] ) && is_array( $element['settings']['border_radius'] ) ) {
							foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
								if ( isset( $element['settings']['border_radius'][ $side ] ) ) {
									$attrs_array['style']['border']['radius'][ $side ] = $element['settings']['border_radius'][ $side ] . ( isset( $element['settings']['border_radius']['unit'] ) ? $element['settings']['border_radius']['unit'] : 'px' );
								}
							}
						}

						// Margin & Padding
						$attrs_array = $this->parse_spacing_settings( $element['settings'], $attrs_array );

						$attrs = wp_json_encode( $attrs_array );

						// Compose style attribute for inline styles
						$inline_style = '';
						if ( isset( $element['settings']['background_color'] ) ) {
							$inline_style .= 'background-color:' . esc_attr( $element['settings']['background_color'] ) . ';';
						}
						if ( isset( $element['settings']['button_text_color'] ) ) {
							$inline_style .= 'color:' . esc_attr( $element['settings']['button_text_color'] ) . ';';
						}

						// Typography
						$typography    = $this->parse_typography_settings( $element['settings'] );
						$inline_style .= $typography['style'];
						if ( isset( $element['settings']['border_border'] ) ) {
							$inline_style .= 'border-style:' . esc_attr( $element['settings']['border_border'] ) . ';';
						}
						if ( isset( $element['settings']['border_width'] ) && is_array( $element['settings']['border_width'] ) ) {
							foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
								if ( isset( $element['settings']['border_width'][ $side ] ) ) {
									$inline_style .= 'border-' . $side . '-width:' . esc_attr( $element['settings']['border_width'][ $side ] ) . ( isset( $element['settings']['border_width']['unit'] ) ? esc_attr( $element['settings']['border_width']['unit'] ) : 'px' ) . ';';
								}
							}
						}
						if ( isset( $element['settings']['border_radius'] ) && is_array( $element['settings']['border_radius'] ) ) {
							$inline_style .= 'border-radius:';
							foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
								if ( isset( $element['settings']['border_radius'][ $side ] ) ) {
									$inline_style .= esc_attr( $element['settings']['border_radius'][ $side ] ) . ( isset( $element['settings']['border_radius']['unit'] ) ? esc_attr( $element['settings']['border_radius']['unit'] ) : 'px' ) . ' ';
								} else {
									$inline_style .= '0px '; // Default to 0 if not set
								}
							}
							$inline_style .= ';';
						}
						// Text shadow
						if ( isset( $element['settings']['text_shadow_text_shadow_type'] ) && $element['settings']['text_shadow_text_shadow_type'] === 'yes' ) {
							$inline_style .= 'text-shadow:1px 1px 2px #000;';
						}
						$block_content .= "<!-- wp:button {$attrs} --><p><a class=\"wp-block-button__link\"";
						if ( $inline_style ) {
							$block_content .= ' style="' . esc_attr( $inline_style ) . '"';
						}
						if ( $url ) {
							$block_content .= ' href="' . esc_url( $url ) . '"';
						}
						$block_content .= '>' . esc_html( $text ) . "</a></p><!-- /wp:button -->\n";
						break;
					case 'video':
						$video_url      = '';
						$embed_provider = '';

						if ( isset( $element['settings']['video_type'] ) && $element['settings']['video_type'] === 'hosted' ) {
							if ( ! empty( $element['settings']['hosted_url']['url'] ) ) {
								$hosted_video_url = $element['settings']['hosted_url']['url'];

								// Check if the file is already in the Media Library
								$attachment_id = attachment_url_to_postid( $hosted_video_url );

								if ( $attachment_id ) {
									// File already exists in Media Library
									$video_url = wp_get_attachment_url( $attachment_id );
								} else {
									$download_args = array(
										'timeout'    => 60,
										'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
										'headers'    => array(
											'Accept' => 'video/mp4,video/*,*/*;q=0.9',
											'Accept-Language' => 'en-US,en;q=0.9',
											'Cache-Control' => 'no-cache',
											'Pragma' => 'no-cache',
										),
									);

									$tmp_file = download_url( $hosted_video_url, 60, false, $download_args );

									if ( ! is_wp_error( $tmp_file ) ) {
										$file_array    = array(
											'name'     => basename( $hosted_video_url ),
											'tmp_name' => $tmp_file,
										);
										$attachment_id = media_handle_sideload( $file_array, 0 );
										error_log( 'attachment_id: ' . print_r( $attachment_id, true ) );
										if ( ! is_wp_error( $attachment_id ) ) {
											$video_url = wp_get_attachment_url( $attachment_id );
										} else {
											$video_url = $hosted_video_url;
										}
										if ( file_exists( $tmp_file ) ) {
											@unlink( $tmp_file );
										}
									} else {
										$curl_tmp_file = $this->download_file_with_curl( $hosted_video_url );
										if ( $curl_tmp_file && ! is_wp_error( $curl_tmp_file ) ) {
											$file_array    = array(
												'name'     => basename( $hosted_video_url ),
												'tmp_name' => $curl_tmp_file,
											);
											$attachment_id = media_handle_sideload( $file_array, 0 );
											if ( ! is_wp_error( $attachment_id ) ) {
												$video_url = wp_get_attachment_url( $attachment_id );
											} else {
												$video_url = $hosted_video_url;
											}
											if ( file_exists( $curl_tmp_file ) ) {
												@unlink( $curl_tmp_file );
											}
										} else {
											// If all methods fail, show admin notice and use original URL
											$video_url = $hosted_video_url;
											add_settings_error(
												'gutenberg_json_data',
												'json_upload_error',
												esc_html__( 'Video Download Failed:Please manually download the video and upload it to your Media Library, or ensure the video URL is publicly accessible.', 'elementor-to-gutenberg' ),
												'error'
											);
										}
									}
								}
							}
						} elseif ( ! empty( $element['settings']['youtube_url'] ) ) {
								$video_url      = $element['settings']['youtube_url'];
								$embed_provider = 'youtube';
						} elseif ( ! empty( $element['settings']['vimeo_url'] ) ) {
							$video_url      = $element['settings']['vimeo_url'];
							$embed_provider = 'vimeo';
						} elseif ( ! empty( $element['settings']['dailymotion_url'] ) ) {
							$video_url      = $element['settings']['dailymotion_url'];
							$embed_provider = 'dailymotion';
						} elseif ( ! empty( $element['settings']['videopress_url'] ) ) {
							$video_url      = $element['settings']['videopress_url'];
							$embed_provider = 'videopress';
						}

						$poster_url = '';
						$poster_id  = 0;

						// Handle overlay image
						if ( isset( $element['settings']['image_overlay']['url'] ) && ! empty( $element['settings']['image_overlay']['url'] ) ) {
							$overlay_url = $element['settings']['image_overlay']['url'];
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
									@unlink( $tmp_file );
								}
							}
						}

						// Apply spacing and extra attributes
						$attrs_array = array();
						$attrs_array = $this->parse_spacing_settings( $element['settings'], $attrs_array );

						if ( isset( $element['settings']['_css_classes'] ) ) {
							$attrs_array['className'] = $element['settings']['_css_classes'];
						}
						if ( isset( $element['settings']['premium_tooltip_text'] ) ) {
							$attrs_array['premiumTooltipText'] = $element['settings']['premium_tooltip_text'];
						}
						if ( isset( $element['settings']['premium_tooltip_position'] ) ) {
							$attrs_array['premiumTooltipPosition'] = $element['settings']['premium_tooltip_position'];
						}

						// Hosted or direct video files
						if ( preg_match( '/\.(mp4|webm|ogg)$/i', $video_url ) ) {
							$attrs_array = array_merge(
								$attrs_array,
								array(
									'src'         => esc_url( $video_url ),
									'poster'      => $poster_url ? esc_url( $poster_url ) : '',
									'id'          => $poster_id,
									'autoplay'    => ( isset( $element['settings']['autoplay'] ) && $element['settings']['autoplay'] === 'yes' ),
									'loop'        => ( isset( $element['settings']['loop'] ) && $element['settings']['loop'] === 'yes' ),
									'muted'       => ( isset( $element['settings']['mute'] ) && $element['settings']['mute'] === 'yes' ),
									'controls'    => true,
									'playsInline' => ( isset( $element['settings']['play_on_mobile'] ) && $element['settings']['play_on_mobile'] === 'yes' ),
								)
							);

							$attrs = wp_json_encode( $attrs_array );

							$block_content .= "<!-- wp:video {$attrs} -->\n";
							$block_content .= '<figure class="wp-block-video">';
							$video_attrs    = array();
							if ( $attrs_array['autoplay'] ) {
								$video_attrs[] = 'autoplay';
							}
							if ( $attrs_array['loop'] ) {
								$video_attrs[] = 'loop';
							}
							if ( $attrs_array['muted'] ) {
								$video_attrs[] = 'muted';
							}
							$video_attrs[] = 'controls="controls"';

							$video_attrs_str = implode( ' ', $video_attrs );
							$poster_attr     = $attrs_array['poster'] ? ' poster="' . esc_url( $attrs_array['poster'] ) . '"' : '';

							$block_content .= "<video {$video_attrs_str}{$poster_attr}>";
							$block_content .= '<source src="' . esc_url( $video_url ) . '" />';
							$block_content .= '</video>';
							$block_content .= '</figure>';
							$block_content .= "<!-- /wp:video -->\n";

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

							// Append start/end time if present for YouTube/Vimeo
							if ( in_array( $embed_provider, array( 'youtube', 'vimeo' ), true ) ) {
								if ( ! empty( $element['settings']['start'] ) ) {
									$video_url = add_query_arg( 'start', intval( $element['settings']['start'] ), $video_url );
								}
								if ( ! empty( $element['settings']['end'] ) ) {
									$video_url = add_query_arg( 'end', intval( $element['settings']['end'] ), $video_url );
								}
								$attrs_array['url'] = esc_url( $video_url );
							}

							$attrs = wp_json_encode( $attrs_array );

							$block_content .= "<!-- wp:embed {$attrs} -->\n";
							$block_content .= '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $embed_provider ) . ' wp-block-embed-' . esc_attr( $embed_provider ) . ' wp-embed-aspect-16-9 wp-has-aspect-ratio">';
							$block_content .= "<div class=\"wp-block-embed__wrapper\">\n";
							$block_content .= esc_url( $video_url ) . "\n";
							$block_content .= '</div>';
							$block_content .= "</figure>\n<!-- /wp:embed -->\n";
						}
						break;
					case 'icon-list':
						$icon_list = isset( $element['settings']['icon_list'] ) ? $element['settings']['icon_list'] : array();

						if ( ! empty( $icon_list ) ) {
							// Build icon list attributes
							$icon_list_attrs = array(
								'itemCount' => count( $icon_list ),
							);

							// Add tooltip if present
							if ( isset( $element['settings']['premium_tooltip_text'] ) && ! empty( $element['settings']['premium_tooltip_text'] ) ) {
								$icon_list_attrs['tooltip'] = $element['settings']['premium_tooltip_text'];
							}
							if ( isset( $element['settings']['premium_tooltip_position'] ) ) {
								$icon_list_attrs['tooltipPosition'] = $element['settings']['premium_tooltip_position'];
							}

							$attrs = wp_json_encode( $icon_list_attrs );

							// Generate icon list content
							$icon_list_content = '<ul class="icon-list">';

							foreach ( $icon_list as $list_item ) {
								$item_text         = isset( $list_item['text'] ) ? $list_item['text'] : '';
								$item_icon_value   = '';
								$item_icon_library = '';

								// Handle icon structure for each item
								if ( isset( $list_item['selected_icon']['value'] ) ) {
									$item_icon_value   = $list_item['selected_icon']['value'];
									$item_icon_library = isset( $list_item['selected_icon']['library'] ) ? $list_item['selected_icon']['library'] : 'fa-solid';
								}

								$icon_list_content .= '<li class="icon-list-item">';

								// Add icon if present
								if ( $item_icon_value ) {
									$icon_class = 'fas';
									if ( $item_icon_library === 'fa-solid' ) {
										$icon_class = 'fas';
									} elseif ( $item_icon_library === 'fa-regular' ) {
										$icon_class = 'far';
									} elseif ( $item_icon_library === 'fa-brands' ) {
										$icon_class = 'fab';
									}

									$item_icon_html = '<i class="' . esc_attr( $icon_class ) . ' ' . esc_attr( $item_icon_value ) . '"></i>';

									// Add tooltip wrapper if tooltip is present
									if ( isset( $icon_list_attrs['tooltip'] ) ) {
										$tooltip_position = isset( $icon_list_attrs['tooltipPosition'] ) ? $icon_list_attrs['tooltipPosition'] : 'top';
										$item_icon_html   = '<span class="tooltip-wrapper" data-tooltip="' . esc_attr( $icon_list_attrs['tooltip'] ) . '" data-tooltip-position="' . esc_attr( $tooltip_position ) . '">' . $item_icon_html . '</span>';
									}

									$icon_list_content .= '<span class="icon-list-icon">' . $item_icon_html . '</span>';
								}

								// Add text
								if ( $item_text ) {
									$icon_list_content .= '<span class="icon-list-text">' . esc_html( $item_text ) . '</span>';
								}

								$icon_list_content .= '</li>';
							}

							$icon_list_content .= '</ul>';

							$block_content .= "<!-- wp:html {$attrs} -->";
							$block_content .= '<div class="wp-block-icon-list">' . $icon_list_content . '</div>';
							$block_content .= "<!-- /wp:html -->\n";
						}
						break;
					case 'icon-box':
						$icon_value   = '';
						$icon_library = '';

						// Handle the icon structure
						if ( isset( $element['settings']['selected_icon']['value'] ) ) {
							$icon_value   = $element['settings']['selected_icon']['value'];
							$icon_library = isset( $element['settings']['selected_icon']['library'] ) ? $element['settings']['selected_icon']['library'] : 'fa-solid';
						} elseif ( isset( $element['settings']['icon'] ) ) {
							// Fallback to old structure
							$icon_value = $element['settings']['icon'];
						}

						$title       = isset( $element['settings']['title_text'] ) ? $element['settings']['title_text'] : '';
						$description = isset( $element['settings']['description_text'] ) ? $element['settings']['description_text'] : '';
						$size        = isset( $element['settings']['size'] ) ? intval( $element['settings']['size'] ) : 24;
						$shape       = isset( $element['settings']['shape'] ) ? $element['settings']['shape'] : 'square';

						// Build icon box attributes
						$icon_box_attrs = array(
							'icon'        => $icon_value,
							'size'        => $size,
							'shape'       => $shape,
							'title'       => $title,
							'description' => $description,
						);

						// Add tooltip if present
						if ( isset( $element['settings']['premium_tooltip_text'] ) && ! empty( $element['settings']['premium_tooltip_text'] ) ) {
							$icon_box_attrs['tooltip'] = $element['settings']['premium_tooltip_text'];
						}
						if ( isset( $element['settings']['premium_tooltip_position'] ) ) {
							$icon_box_attrs['tooltipPosition'] = $element['settings']['premium_tooltip_position'];
						}

						$attrs = wp_json_encode( $icon_box_attrs );

						// Generate icon HTML
						$icon_class = 'fas';
						if ( $icon_library === 'fa-solid' ) {
							$icon_class = 'fas';
						} elseif ( $icon_library === 'fa-regular' ) {
							$icon_class = 'far';
						} elseif ( $icon_library === 'fa-brands' ) {
							$icon_class = 'fab';
						}

						$icon_html = '';
						if ( $icon_value ) {
							$icon_html = '<i class="' . esc_attr( $icon_class ) . ' ' . esc_attr( $icon_value ) . '" style="font-size: ' . esc_attr( $size ) . 'px;"></i>';

							// Add tooltip wrapper if tooltip is present
							if ( isset( $icon_box_attrs['tooltip'] ) ) {
								$tooltip_position = isset( $icon_box_attrs['tooltipPosition'] ) ? $icon_box_attrs['tooltipPosition'] : 'top';
								$icon_html        = '<span class="tooltip-wrapper" data-tooltip="' . esc_attr( $icon_box_attrs['tooltip'] ) . '" data-tooltip-position="' . esc_attr( $tooltip_position ) . '">' . $icon_html . '</span>';
							}
						}

						// Generate the icon box content
						$icon_box_content = '';
						if ( $icon_html ) {
							$icon_box_content .= '<div class="icon-box-icon">' . $icon_html . '</div>';
						}
						if ( $title ) {
							$icon_box_content .= '<h3 class="icon-box-title">' . esc_html( $title ) . '</h3>';
						}
						if ( $description ) {
							$icon_box_content .= '<div class="icon-box-description">' . wp_kses_post( $description ) . '</div>';
						}

						$block_content .= "<!-- wp:html {$attrs} -->";
						$block_content .= '<div class="wp-block-icon-box">' . $icon_box_content . '</div>';
						$block_content .= "<!-- /wp:html -->\n";
						break;
					case 'spacer':
						$height         = isset( $element['settings']['height'] ) ? intval( $element['settings']['height'] ) : 20;
						$attrs          = '{"height":"' . $height . 'px"}';
						$block_content .= "<!-- wp:spacer {$attrs} -->\n";
						$block_content .= '<div style="height: ' . $height . 'px" aria-hidden="true" class="wp-block-spacer"></div>';
						$block_content .= "<!-- /wp:spacer -->\n";
						break;
					case 'icon':
						$icon_value   = '';
						$icon_library = '';

						// Handle the new icon structure
						if ( isset( $element['settings']['selected_icon']['value'] ) ) {
							$icon_value   = $element['settings']['selected_icon']['value'];
							$icon_library = isset( $element['settings']['selected_icon']['library'] ) ? $element['settings']['selected_icon']['library'] : 'fa-solid';
						} elseif ( isset( $element['settings']['icon'] ) ) {
							// Fallback to old structure
							$icon_value = $element['settings']['icon'];
						}

						$size  = isset( $element['settings']['size'] ) ? intval( $element['settings']['size'] ) : 24;
						$shape = isset( $element['settings']['shape'] ) ? $element['settings']['shape'] : 'square';

						if ( $icon_value ) {
							// Build icon attributes
							$icon_attrs = array(
								'icon'  => $icon_value,
								'size'  => $size,
								'shape' => $shape,
							);

							// Add tooltip if present
							if ( isset( $element['settings']['premium_tooltip_text'] ) && ! empty( $element['settings']['premium_tooltip_text'] ) ) {
								$icon_attrs['tooltip'] = $element['settings']['premium_tooltip_text'];
							}
							if ( isset( $element['settings']['premium_tooltip_position'] ) ) {
								$icon_attrs['tooltipPosition'] = $element['settings']['premium_tooltip_position'];
							}

							$attrs = wp_json_encode( $icon_attrs );

							// Generate icon HTML
							$icon_class = 'fas';
							if ( $icon_library === 'fa-solid' ) {
								$icon_class = 'fas';
							} elseif ( $icon_library === 'fa-regular' ) {
								$icon_class = 'far';
							} elseif ( $icon_library === 'fa-brands' ) {
								$icon_class = 'fab';
							}

							$icon_html = '<i class="' . esc_attr( $icon_class ) . ' ' . esc_attr( $icon_value ) . '" style="font-size: ' . esc_attr( $size ) . 'px;"></i>';

							// Add tooltip wrapper if tooltip is present
							if ( isset( $icon_attrs['tooltip'] ) ) {
								$tooltip_position = isset( $icon_attrs['tooltipPosition'] ) ? $icon_attrs['tooltipPosition'] : 'top';
								$icon_html        = '<span class="tooltip-wrapper" data-tooltip="' . esc_attr( $icon_attrs['tooltip'] ) . '" data-tooltip-position="' . esc_attr( $tooltip_position ) . '">' . $icon_html . '</span>';
							}

							$block_content .= "<!-- wp:html {$attrs} -->";
							$block_content .= '<div class="wp-block-icon" style="text-align: center;">' . $icon_html . '</div>';
							$block_content .= "<!-- /wp:html -->\n";
						}
						break;
					case 'social-icons':
						$social_icons = isset( $element['settings']['social_icons'] ) ? $element['settings']['social_icons'] : array();
						if ( ! empty( $social_icons ) ) {
							$icons_content = '';
							foreach ( $social_icons as $icon ) {
								$icon_name = isset( $icon['name'] ) ? $icon['name'] : '';
								$icon_url  = isset( $icon['url'] ) ? $icon['url'] : '';
								if ( $icon_name && $icon_url ) {
									$icons_content .= '<!-- wp:social-link --><a href="' . esc_url( $icon_url ) . '" class="wp-block-social-link wp-block-social-link--' . esc_attr( $icon_name ) . '">' . esc_html( $icon_name ) . "</a><!-- /wp:social-link -->\n";
								}
							}
							$block_content .= "<!-- wp:social-links -->{$icons_content}<!-- /wp:social-links -->\n";
						}
						break;
					case 'gallery':
						$gallery_images = isset( $element['settings']['gallery'] ) ? $element['settings']['gallery'] : array();
						if ( ! empty( $gallery_images ) ) {
							$gallery_content = '';
							foreach ( $gallery_images as $image ) {
								$url = isset( $image['url'] ) ? $image['url'] : '';
								$alt = isset( $image['alt'] ) ? $image['alt'] : '';
								if ( $url ) {
									$attrs            = '{"url":"' . esc_url( $url ) . '","alt":"' . esc_attr( $alt ) . '"}';
									$gallery_content .= "<!-- wp:image {$attrs} /-->\n";
								}
							}
							$block_content .= "<!-- wp:gallery -->{$gallery_content}<!-- /wp:gallery -->\n";
						}
						break;
					case 'list':
						$items = isset( $element['settings']['items'] ) ? $element['settings']['items'] : array();
						if ( ! empty( $items ) ) {
							$list_content = '<ul>';
							foreach ( $items as $item ) {
								$list_content .= '<li>' . esc_html( $item ) . '</li>';
							}
							$list_content  .= '</ul>';
							$block_content .= "<!-- wp:list -->{$list_content}<!-- /wp:list -->\n";
						}
						break;
					case 'tabs':
						$tabs = isset( $element['settings']['tabs'] ) ? $element['settings']['tabs'] : array();
						if ( ! empty( $tabs ) ) {
							foreach ( $tabs as $tab ) {
								$tab_title   = isset( $tab['title'] ) ? $tab['title'] : '';
								$tab_content = isset( $tab['content'] ) ? $tab['content'] : '';
								// Use heading for tab title and group for tab content
								$block_content .= "<!-- wp:heading -->{$tab_title}<!-- /wp:heading -->\n";
								$block_content .= "<!-- wp:group -->{$tab_content}<!-- /wp:group -->\n";
							}
						}
						break;
					case 'accordion':
						$accordions = isset( $element['settings']['accordions'] ) ? $element['settings']['accordions'] : array();
						if ( ! empty( $accordions ) ) {
							$accordion_content = '';
							foreach ( $accordions as $accordion ) {
								$accordion_title        = isset( $accordion['title'] ) ? $accordion['title'] : '';
								$accordion_content_text = isset( $accordion['content'] ) ? $accordion['content'] : '';
								$accordion_content     .= "<!-- wp:accordion-item -->\n<!-- wp:accordion-title -->{$accordion_title}<!-- /wp:accordion-title -->\n<!-- wp:accordion-content -->{$accordion_content_text}<!-- /wp:accordion-content -->\n<!-- /wp:accordion-item -->\n";
							}
							$block_content .= "<!-- wp:details --><summary>{$accordion_title}</summary>{$accordion_content_text}<!-- /wp:details -->\n";
						}
						break;
					default:
						// Unknown widget, fallback to paragraph
						$block_content .= "<!-- wp:paragraph -->{$element['widgetType']}<!-- /wp:paragraph -->\n";
						break;
				}
			} else {
				// Handle unknown elements
				$block_content .= '<!-- wp:paragraph -->' . esc_html__( 'Unknown element', 'elementor-to-gutenberg' ) . "<!-- /wp:paragraph -->\n";
			}
		}
		return $block_content;
	}
}
