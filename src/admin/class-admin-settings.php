<?php
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Layout\Container_Classifier;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

use function esc_html;
use function esc_html__;
use function get_option;
use function sanitize_key;
use function current_time;
use function update_option;
use function sanitize_text_field;
use function wp_strip_all_tags;
use function wp_unslash;
use function esc_attr;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 */
class Admin_Settings {
	/**
	 * Singleton instance.
	 *
	 * @var Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Settings
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_filter( 'page_row_actions', array( $this, 'myplugin_add_convert_button' ), 10, 2 );
		add_action( 'admin_post_myplugin_convert_page', array( $this, 'myplugin_handle_convert_page' ) );
	}

	public function myplugin_add_convert_button( $actions, $post ) {
		if ( $post->post_type === 'page' ) {
			$json_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( empty( $json_data ) ) {
				return $actions;
			}
			$url                             = wp_nonce_url(
				admin_url( 'admin-post.php?action=myplugin_convert_page&page_id=' . $post->ID ),
				'myplugin_convert_page_' . $post->ID
			);
			$actions['convert_to_gutenberg'] = '<a href="' . esc_url( $url ) . '">Convert to Gutenberg</a>';
		}

		return $actions;
	}


	public function myplugin_handle_convert_page() {
		if ( ! isset( $_GET['page_id'] ) ) {
			wp_die( 'Page ID missing.' );
		}

		$page_id = absint( $_GET['page_id'] );

		// Verify nonce
		check_admin_referer( 'myplugin_convert_page_' . $page_id );

		// Get JSON template stored in post meta
		$json_data = get_post_meta( $page_id, '_elementor_data', true ); // Example for Elementor
		if ( empty( $json_data ) ) {
			wp_die( 'No template JSON found for this page.' );
		}

		$data['content'] = json_decode( $json_data, true );
		// Convert JSON → Gutenberg blocks
		$blocks = $this->convert_json_to_gutenberg_content( $data );

		// Create new page with blocks
		$new_page_id = $this->insert_new_page( $page_id, $blocks );

		if ( $new_page_id ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_page_id . '&action=edit' ) );
			exit;
		}

		wp_die( 'Failed to create Gutenberg page.' );
	}

	/**
	 * Insert new page with Gutenberg blocks.
	 *
	 * @param int $page_id Page ID.
	 * @param array $blocks Gutenberg blocks.
	 *
	 * @return int New page ID.
	 */
	public function insert_new_page( $page_id, $blocks ): int {
		$new_page_id = wp_insert_post( array(
				'post_title'   => get_the_title( $page_id ) . ' (Gutenberg)',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $blocks,
			)
		);

		return $new_page_id;
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu(): void {
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

	/**
	 * Initialize settings.
	 */
	public function settings_init(): void {
		register_setting(
			'gutenberg_settings_group',
			'gutenberg_json_data',
			array(
				'sanitize_callback' => array( $this, 'handle_json_upload' ),
			)
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

	/**
	 * Render JSON upload field.
	 */
	public function json_upload_field_callback(): void {
		?>
        <input type="file" name="json_upload" accept=".json"/>
		<?php
	}

	/**
	 * Handle JSON file upload and conversion.
	 *
	 * @param mixed $option The option value.
	 *
	 * @return string The processed Gutenberg content or existing option.
	 */
	public function handle_json_upload( $option ): string {
		if ( empty( $_FILES['json_upload']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $option;
		}

		$json_content = File_Upload_Service::upload_file( $_FILES['json_upload'], 'json' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $json_content ) {
			return get_option( 'gutenberg_json_data', '' );
		}

		$data              = json_decode( $json_content, true );
		$gutenberg_content = $this->convert_json_to_gutenberg_content( $data );

		$post_title = $data['title'] ?? 'Untitled';
		$post_type  = $data['type'] ?? 'page';

		// Check if a post with the same title and type exists
		$existing_post = get_page_by_title( $post_title, OBJECT, $post_type );

		if ( $existing_post ) {
			// Update existing post
			$new_post_id = wp_update_post(
				array(
					'ID'           => $existing_post->ID,
					'post_content' => $gutenberg_content,
					'post_status'  => 'publish',
				)
			);
		} else {
			// Create new post
			$new_post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $post_title ),
					'post_content' => $gutenberg_content,
					'post_type'    => sanitize_key( $post_type ),
					'post_status'  => 'publish',
				)
			);
		}

		if ( is_wp_error( $new_post_id ) ) {
			add_settings_error(
				'gutenberg_json_data',
				'json_upload_error',
				esc_html__( 'Failed to create new page.', 'elementor-to-gutenberg' ),
				'error'
			);

			return get_option( 'gutenberg_json_data', '' );
		}

		add_settings_error(
			'gutenberg_json_data',
			'json_upload_success',
			esc_html__( 'JSON file uploaded and page created successfully!', 'elementor-to-gutenberg' ),
			'updated'
		);

		return $gutenberg_content;
	}

	/**
	 * Render settings page content.
	 */
	public function settings_page_content(): void {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gutenberg Settings', 'elementor-to-gutenberg' ); ?></h1>
			<?php settings_errors( 'gutenberg_json_data' ); ?>
            <form method="post" action="options.php" enctype="multipart/form-data" id="json-upload-form">
				<?php
				settings_fields( 'gutenberg_settings_group' );
				do_settings_sections( 'gutenberg-settings' );
				submit_button( esc_html__( 'Upload JSON File', 'elementor-to-gutenberg' ), 'primary', 'json-upload-btn' );
				?>
                <span id="json-upload-spinner" style="display:none;margin-left:10px;">
					<img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>"
                         alt="<?php esc_attr_e( 'Loading', 'elementor-to-gutenberg' ); ?>"/> <?php esc_html_e( 'Uploading...', 'elementor-to-gutenberg' ); ?>
				</span>
            </form>
            <script>
                (function () {
                    'use strict';
                    var form = document.getElementById('json-upload-form');
                    var button = document.getElementById('json-upload-btn');
                    var spinner = document.getElementById('json-upload-spinner');
                    if (form && button && spinner) {
                        form.addEventListener('submit', function () {
                            button.disabled = true;
                            spinner.style.display = 'inline-block';
                        });
                    }
                })();
            </script>
        </div>
		<?php
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param array $json_data The JSON data to convert.
	 *
	 * @return string The converted Gutenberg content.
	 */
	public function convert_json_to_gutenberg_content( array $json_data ): string {
		if ( empty( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
			return '';
		}

		return $this->parse_elementor_elements( $json_data['content'] );
	}

	/**
	 * Parse Elementor elements to Gutenberg blocks.
	 *
	 * @param array $elements The Elementor elements array.
	 *
	 * @return string The converted Gutenberg block content.
	 */
	public function parse_elementor_elements( array $elements ): string {
		$blocks = '';
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$blocks .= $this->render_element( $element );
		}

		return $blocks;
	}

	/**
	 * Render an Elementor element into block markup.
	 *
	 * @param array $element Elementor element.
	 */
	private function render_element( array $element ): string {
		$el_type = $element['elType'] ?? '';
		if ( 'container' === $el_type ) {
			return $this->render_container( $element );
		}

		if ( 'widget' === $el_type ) {
			$widget_type = $element['widgetType'] ?? '';
			$handler     = Widget_Handler_Factory::get_handler( $widget_type );
			if ( null !== $handler ) {
				return $handler->handle( $element );
			}

			return $this->render_unknown_widget( $widget_type );
		}

		return $this->render_unknown_widget( $el_type ?: 'unknown' );
	}

	/**
	 * Render a container element based on layout classification.
	 *
	 * @param array $element Elementor container element.
	 */
	private function render_container( array $element ): string {
		$children     = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$child_blocks = array();
		$child_data   = array();
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$child_data[] = array(
				'element' => $child,
				'content' => $this->render_element( $child ),
			);
		}

		$child_count        = count( $children );
		$container_settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$container_attr     = Style_Parser::parse_container_styles( $container_settings );
		$container_classes  = Container_Classifier::get_element_classes( $element );
		$container_attr     = $this->apply_container_class_adjustments( $container_attr, $container_classes );

		$child_blocks = ! empty( $child_data )
			? array_map(
				static function ( array $data ): string {
					return $data['content'] ?? '';
				},
				$child_data
			)
			: array();

		if ( Container_Classifier::is_grid( $element ) ) {
			$columns = Container_Classifier::get_grid_column_count( $element, $child_count );

			return $this->render_grid_group( $container_attr, $child_data, $columns );
		}

		if ( Container_Classifier::should_use_columns( $element ) ) {
			return $this->render_columns_group( $container_attr, $child_data );
		}

		if ( Container_Classifier::is_row( $element, $child_count ) ) {
			return $this->render_row_group( $container_attr, $child_blocks );
		}

		$layout_type = in_array( 'e-con-full', $container_classes, true ) ? 'default' : 'constrained';

		return $this->render_group( $container_attr, $child_blocks, $layout_type );
	}

	/**
	 * Render a Gutenberg group with constrained layout.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_group( array $attributes, array $child_blocks, string $layout_type = 'constrained' ): string {
		$attributes['layout'] = array( 'type' => $layout_type );
		$inner_html           = implode( '', $child_blocks );

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a Gutenberg group with flex layout for row containers.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_row_group( array $attributes, array $child_blocks ): string {
		$attributes['layout'] = array(
			'type'           => 'flex',
			'justifyContent' => 'space-between',
			'flexWrap'       => 'wrap',
		);

		return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
	}

	/**
	 * Render a Gutenberg grid layout group.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 * @param int $columns Number of columns.
	 */
	private function render_grid_group( array $attributes, array $child_data, int $columns ): string {
		$attributes['layout'] = array(
			'type'        => 'grid',
			'columnCount' => max( 1, $columns ),
		);

		$inner_html = '';
		foreach ( $child_data as $child ) {
			$content = $child['content'] ?? '';
			if ( '' === $content ) {
				continue;
			}

			$inner_html .= Block_Builder::build(
				'group',
				array( 'layout' => array( 'type' => 'constrained' ) ),
				$content
			);
		}

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a Gutenberg columns block for typical three/four card rows.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_data Child element data with rendered content.
	 */
	private function render_columns_group( array $attributes, array $child_data ): string {
		$inner_html          = '';
		$columns_alignments  = array();

		foreach ( $child_data as $child ) {
			$content = isset( $child['content'] ) ? $child['content'] : '';
			if ( '' === $content ) {
				continue;
			}

			$element = isset( $child['element'] ) && is_array( $child['element'] ) ? $child['element'] : array();
			$width   = $this->get_column_width( $element );

			// اقرأ computed styles من Elementor
			$computed_styles = Style_Parser::get_computed_styles( $element );
			$vertical_align  = null;

			if ( isset( $computed_styles['align-self'] ) ) {
				$vertical_align = $this->map_align_self_to_vertical_alignment( $computed_styles['align-self'] );
			}

			$column_attrs = array();
			if ( null !== $width ) {
				$column_attrs['width'] = $width;
			}
			if ( null !== $vertical_align ) {
				$column_attrs['verticalAlignment'] = $vertical_align;
				$columns_alignments[]              = $vertical_align;
			}

			$attrs_json = '';
			if ( ! empty( $column_attrs ) ) {
				$attrs_json = ' ' . wp_json_encode( $column_attrs );
			}

			$style_attr  = '';
			$class_names = array( 'wp-block-column' );

			if ( null !== $width ) {
				$style_attr = ' style="flex-basis:' . esc_attr( $width ) . '"';
			}

			if ( null !== $vertical_align ) {
				$class_names[] = 'is-vertically-aligned-' . sanitize_html_class( $vertical_align );
			}

			$inner_html .= sprintf(
				'<!-- wp:column%s --><div class="%s"%s>%s</div><!-- /wp:column -->',
				$attrs_json,
				esc_attr( implode( ' ', $class_names ) ),
				$style_attr,
				$content
			);
		}

		if ( '' === $inner_html ) {
			return '';
		}

		if ( ! empty( $columns_alignments ) ) {
			$unique = array_unique( $columns_alignments );
			if ( 1 === count( $unique ) ) {
				$alignment                   = reset( $unique ); // top/center/bottom
				$attributes['verticalAlignment'] = $alignment;

				$class = 'are-vertically-aligned-' . sanitize_html_class( $alignment );
				if ( empty( $attributes['className'] ) ) {
					$attributes['className'] = $class;
				} else {
					$attributes['className'] .= ' ' . $class;
				}
			}
		}

		return Block_Builder::build( 'columns', $attributes, $inner_html );
	}


	/**
	 * Infer a core/column width attribute from an Elementor container element.
	 *
	 * Returns values like "33.33%" when possible.
	 *
	 * @param array $element Elementor container element.
	 *
	 * @return string|null
	 */
	private function get_column_width( array $element ): ?string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		if ( empty( $settings ) ) {
			return null;
		}

		$candidates = array( 'width', 'column_width', 'container_width' );

		foreach ( $candidates as $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$value = $settings[ $key ];

			if ( is_array( $value ) ) {
				$size = isset( $value['size'] ) ? $value['size'] : ( isset( $value['value'] ) ? $value['value'] : null );
				$unit = isset( $value['unit'] ) ? (string) $value['unit'] : '%';

				if ( null === $size || '' === $size ) {
					continue;
				}

				$size = trim( (string) $size );
				if ( '' === $size || ! is_numeric( $size ) ) {
					continue;
				}

				if ( '' === $unit ) {
					$unit = '%';
				}

				// For now we only trust percentage widths. Other units are ignored.
				if ( '%' !== $unit ) {
					continue;
				}

				return $size . $unit;
			}

			$string_value = trim( (string) $value );
			if ( '' === $string_value ) {
				continue;
			}

			if ( false !== strpos( $string_value, '%' ) ) {
				return $string_value;
			}

			if ( is_numeric( $string_value ) ) {
				return $string_value . '%';
			}
		}

		return null;
	}

	/**
	 * Apply Elementor container class adjustments (full/boxed) to block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $classes Elementor class list.
	 */
	private function apply_container_class_adjustments( array $attributes, array $classes ): array {
		if ( in_array( 'e-con-boxed', $classes, true ) ) {
			$attributes = $this->add_class_to_attributes( $attributes, 'has-global-padding' );
		}

		if ( in_array( 'e-con-full', $classes, true ) ) {
			$attributes = $this->remove_class_from_attributes( $attributes, 'has-global-padding' );
		}

		return $attributes;
	}

	/**
	 * Add a className entry to block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param string $class Class to add.
	 */
	private function add_class_to_attributes( array $attributes, string $class ): array {
		$sanitized = Style_Parser::clean_class( $class );
		if ( '' === $sanitized ) {
			return $attributes;
		}

		$existing   = isset( $attributes['className'] ) ? preg_split( '/\s+/', $attributes['className'] ) : array();
		$existing   = is_array( $existing ) ? array_filter( $existing ) : array();
		$existing[] = $sanitized;

		$unique = array();
		foreach ( $existing as $item ) {
			$item = Style_Parser::clean_class( $item );
			if ( '' === $item ) {
				continue;
			}
			$unique[ $item ] = true;
		}

		if ( empty( $unique ) ) {
			unset( $attributes['className'] );

			return $attributes;
		}

		$attributes['className'] = implode( ' ', array_keys( $unique ) );

		return $attributes;
	}

	/**
	 * Map CSS align-self to Gutenberg verticalAlignment value.
	 *
	 * @param string $align_self
	 * @return string|null
	 */
	private function map_align_self_to_vertical_alignment( string $align_self ): ?string {
		$align_self = strtolower( trim( $align_self ) );

		switch ( $align_self ) {
			case 'flex-start':
			case 'start':
				return 'top';
			case 'center':
				return 'center';
			case 'flex-end':
			case 'end':
				return 'bottom';
			default:
				return null;
		}
	}

	/**
	 * Remove a class from block attributes if present.
	 *
	 * @param array $attributes Block attributes.
	 * @param string $class Class to remove.
	 */
	private function remove_class_from_attributes( array $attributes, string $class ): array {
		if ( empty( $attributes['className'] ) ) {
			return $attributes;
		}

		$target    = Style_Parser::clean_class( $class );
		$classlist = preg_split( '/\s+/', (string) $attributes['className'] );
		$classlist = is_array( $classlist ) ? array_filter( $classlist ) : array();

		$filtered = array();
		foreach ( $classlist as $item ) {
			$item = Style_Parser::clean_class( $item );
			if ( '' === $item || $item === $target ) {
				continue;
			}
			$filtered[ $item ] = true;
		}

		if ( empty( $filtered ) ) {
			unset( $attributes['className'] );
		} else {
			$attributes['className'] = implode( ' ', array_keys( $filtered ) );
		}

		return $attributes;
	}

	/**
	 * Render a placeholder for unknown widgets.
	 *
	 * @param string $type Widget type.
	 */
	private function render_unknown_widget( string $type ): string {
		$message   = sprintf( /* translators: %s widget type */ esc_html__( 'Unknown widget: %s', 'elementor-to-gutenberg' ), esc_html( $type ) );
		$paragraph = sprintf( '<p>%s</p>', $message );

		return Block_Builder::build(
			'group',
			array( 'layout' => array( 'type' => 'constrained' ) ),
			'<!-- wp:paragraph -->' . $paragraph . '<!-- /wp:paragraph -->' . "\n"
		);
	}
}