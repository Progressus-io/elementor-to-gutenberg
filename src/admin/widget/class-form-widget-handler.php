<?php
/**
 * Form Widget Handler
 *
 * @package Progressus\BlockShift\Admin\Widget
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Admin_Settings;
use Progressus\BlockShift\Admin\Helper\Core_Block_Markup;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function esc_url_raw;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function serialize_block_attributes;
use function wp_kses_post;

/**
 * Class Form_Widget_Handler
 */
class Form_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Post meta key holding the mail settings of the forms on a converted post, keyed by form ID.
	 */
	public const SETTINGS_META_KEY = '_blockshift_form_settings';

	/**
	 * Elementor field types the form block renders.
	 */
	private const FIELD_TYPES = array( 'text', 'email', 'textarea', 'url', 'tel', 'number', 'date', 'time', 'password', 'select', 'radio', 'checkbox', 'acceptance', 'hidden', 'html', 'honeypot', 'step' );

	/**
	 * Elementor field types that need a service the form block does not have.
	 */
	private const UNSUPPORTED_FIELD_TYPES = array( 'upload', 'recaptcha', 'recaptcha_v3' );

	/**
	 * Mail settings of the forms converted since the last take_pending_settings() call.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $pending_settings = array();

	/**
	 * Handle the widget conversion.
	 *
	 * @param array $widget The widget data.
	 * @return string The converted Gutenberg block HTML.
	 */
	public function handle( array $widget ): string {
		$settings = isset( $widget['settings'] ) && is_array( $widget['settings'] ) ? $widget['settings'] : array();
		$form_id  = sanitize_key( (string) ( $widget['id'] ?? '' ) );

		$attributes = array();

		if ( ! empty( $settings['form_name'] ) ) {
			$attributes['formName'] = sanitize_text_field( (string) $settings['form_name'] );
		}

		if ( '' !== $form_id ) {
			$attributes['formId'] = $form_id;
		}

		$attributes['formFields'] = $this->build_fields( $settings );

		if ( ! empty( $settings['input_size'] ) ) {
			$attributes['inputSize'] = $settings['input_size'];
		}

		if ( ! empty( $settings['button_text'] ) ) {
			$attributes['buttonText'] = $settings['button_text'];
		}

		if ( ! empty( $settings['button_align'] ) ) {
			$attributes['buttonAlign'] = $settings['button_align'];
		}

		if ( ! empty( $settings['custom_messages'] ) ) {
			foreach ( array(
				'success_message'        => 'successMessage',
				'error_message'          => 'errorMessage',
				'required_field_message' => 'requiredFieldMessage',
			) as $setting => $attribute ) {
				if ( ! empty( $settings[ $setting ] ) ) {
					$attributes[ $attribute ] = sanitize_text_field( (string) $settings[ $setting ] );
				}
			}
		}

		$actions = isset( $settings['submit_actions'] ) && is_array( $settings['submit_actions'] ) ? $settings['submit_actions'] : array( 'email' );

		if ( in_array( 'redirect', $actions, true ) ) {
			$redirect = $settings['redirect_to'] ?? '';
			$redirect = esc_url_raw( (string) ( is_array( $redirect ) ? ( $redirect['url'] ?? '' ) : $redirect ) );
			if ( '' !== $redirect ) {
				$attributes['redirectUrl'] = $redirect;
			}
		}

		if ( ! empty( $settings['step_next_label'] ) ) {
			$attributes['nextButtonText'] = sanitize_text_field( (string) $settings['step_next_label'] );
		}

		if ( ! empty( $settings['step_previous_label'] ) ) {
			$attributes['previousButtonText'] = sanitize_text_field( (string) $settings['step_previous_label'] );
		}

		if ( isset( $settings['column_gap']['size'] ) && '' !== $settings['column_gap']['size'] ) {
			$attributes['columnGap'] = (int) $settings['column_gap']['size'];
		}

		if ( isset( $settings['row_gap']['size'] ) && '' !== $settings['row_gap']['size'] ) {
			$attributes['rowGap'] = (int) $settings['row_gap']['size'];
		}

		if ( isset( $settings['label_spacing']['size'] ) && '' !== $settings['label_spacing']['size'] ) {
			$attributes['labelSpacing'] = (int) $settings['label_spacing']['size'];
		}

		$attributes['labelTypography'] = array(
			'fontFamily'    => $settings['label_typography_font_family'] ?? '',
			'fontWeight'    => $settings['label_typography_font_weight'] ?? 'normal',
			'letterSpacing' => (float) ( $settings['label_typography_letter_spacing']['size'] ?? 0 ),
			'wordSpacing'   => (float) ( $settings['label_typography_word_spacing']['size'] ?? 0 ),
		);

		if ( ! empty( $settings['button_background_color'] ) ) {
			$attributes['buttonBackgroundColor'] = $settings['button_background_color'];
		}

		if ( ! empty( $settings['button_text_color'] ) ) {
			$attributes['buttonTextColor'] = $settings['button_text_color'];
		}

		if ( ! empty( $settings['button_border_radius'] ) ) {
			$attributes['buttonBorderRadius'] = array(
				'top'    => (int) ( $settings['button_border_radius']['top'] ?? 0 ),
				'right'  => (int) ( $settings['button_border_radius']['right'] ?? 0 ),
				'bottom' => (int) ( $settings['button_border_radius']['bottom'] ?? 0 ),
				'left'   => (int) ( $settings['button_border_radius']['left'] ?? 0 ),
			);
		}

		if ( ! empty( $settings['button_text_padding'] ) ) {
			$attributes['buttonPadding'] = array(
				'top'    => (int) ( $settings['button_text_padding']['top'] ?? 12 ),
				'right'  => (int) ( $settings['button_text_padding']['right'] ?? 24 ),
				'bottom' => (int) ( $settings['button_text_padding']['bottom'] ?? 12 ),
				'left'   => (int) ( $settings['button_text_padding']['left'] ?? 24 ),
			);
		}

		if ( '' !== $form_id ) {
			self::$pending_settings[ $form_id ] = $this->build_mail_settings( $settings, $attributes['formFields'] );
		}

		$this->warn_about_skipped_actions( $actions );

		return '<!-- wp:blockshift/form ' . serialize_block_attributes( $attributes ) . ' -->' . $this->generate_form_html( $attributes ) . '<!-- /wp:blockshift/form -->';
	}

	/**
	 * Hand over the mail settings collected since the last call.
	 *
	 * @return array<string, array<string, mixed>> Mail settings keyed by form ID.
	 */
	public static function take_pending_settings(): array {
		$settings               = self::$pending_settings;
		self::$pending_settings = array();

		return $settings;
	}

	/**
	 * Build the block's field definitions from the Elementor form fields.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<int, array<string, mixed>> Field definitions.
	 */
	private function build_fields( array $settings ): array {
		$fields = array();

		foreach ( isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] ) ? $settings['form_fields'] : array() as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = isset( $field['field_type'] ) && '' !== $field['field_type'] ? (string) $field['field_type'] : 'text';
			if ( in_array( $type, self::UNSUPPORTED_FIELD_TYPES, true ) ) {
				/* translators: %s: Elementor form field type. */
				$this->log_warning( sprintf( __( 'The "%s" form field was skipped because the converted form cannot handle it.', 'migrate-off-elementor' ), $type ) );
				continue;
			}
			if ( ! in_array( $type, self::FIELD_TYPES, true ) ) {
				$type = 'text';
			}

			$custom_id = sanitize_key( (string) ( $field['custom_id'] ?? '' ) );
			if ( '' === $custom_id ) {
				$custom_id = sanitize_key( 'field_' . ( $field['_id'] ?? $index ) );
			}

			$entry = array(
				'customId'    => $custom_id,
				'fieldType'   => $type,
				'required'    => isset( $field['required'] ) && 'true' === $field['required'],
				'fieldLabel'  => sanitize_text_field( (string) ( $field['field_label'] ?? '' ) ),
				'placeholder' => sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
			);

			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$entry['options'] = $this->parse_options( (string) ( $field['field_options'] ?? '' ) );
				if ( 'select' === $type && ! empty( $field['allow_multiple'] ) ) {
					$entry['multiple'] = true;
				}
				if ( 'select' !== $type && ! empty( $field['inline_list'] ) ) {
					$entry['inline'] = true;
				}
			}

			if ( 'acceptance' === $type ) {
				$entry['acceptanceText'] = Core_Block_Markup::inline_html( (string) ( $field['acceptance_text'] ?? '' ) );
			}

			if ( 'html' === $type ) {
				$entry['html'] = trim( wp_kses_post( (string) ( $field['field_html'] ?? '' ) ) );
			}

			if ( 'textarea' === $type && ! empty( $field['rows'] ) ) {
				$entry['rows'] = (int) $field['rows'];
			}

			$value = sanitize_text_field( (string) ( $field['field_value'] ?? '' ) );
			if ( '' !== $value && ! in_array( $type, array( 'select', 'radio', 'checkbox', 'acceptance', 'html', 'step', 'honeypot', 'textarea' ), true ) ) {
				$entry['value'] = $value;
			}

			$width = isset( $field['width'] ) && is_numeric( $field['width'] ) ? (int) $field['width'] : 100;
			if ( $width > 0 && $width < 100 ) {
				$entry['width'] = $width;
			}

			$fields[] = $entry;
		}

		return $fields;
	}

	/**
	 * Parse Elementor's "Label|value" option lines.
	 *
	 * @param string $raw One option per line.
	 *
	 * @return array<int, array{label: string, value: string}> Options.
	 */
	private function parse_options( string $raw ): array {
		$options = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts     = explode( '|', $line, 2 );
			$label     = sanitize_text_field( $parts[0] );
			$options[] = array(
				'label' => $label,
				'value' => isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : $label,
			);
		}

		return $options;
	}

	/**
	 * Build the mail settings the submission handler reads for this form.
	 *
	 * @param array $settings Widget settings.
	 * @param array $fields   Field definitions of the converted form.
	 *
	 * @return array{email_to: array<int, string>, subject: string, reply_to_field: string}
	 */
	private function build_mail_settings( array $settings, array $fields ): array {
		$recipients = array();
		// Only a piece that is a complete address on its own is kept, so nothing can ride along as a header.
		foreach ( preg_split( '/[,;\r\n]+/', (string) ( $settings['email_to'] ?? '' ) ) as $recipient ) {
			$recipient = trim( $recipient );
			if ( '' !== $recipient && is_email( $recipient ) && sanitize_email( $recipient ) === $recipient ) {
				$recipients[] = $recipient;
			}
		}

		// A subject may pull in submitted values through shortcodes; those never reach the mail subject.
		$subject = (string) preg_replace( '/\[[^\]]*\]/', '', (string) ( $settings['email_subject'] ?? '' ) );
		$subject = trim( sanitize_text_field( $subject ) );

		$reply_to_field = '';
		$reply_to       = sanitize_key( (string) ( $settings['email_reply_to'] ?? '' ) );
		foreach ( $fields as $field ) {
			if ( 'email' === $field['fieldType'] && $field['customId'] === $reply_to ) {
				$reply_to_field = $reply_to;
			}
		}

		return array(
			'email_to'       => $recipients,
			'subject'        => $subject,
			'reply_to_field' => $reply_to_field,
		);
	}

	/**
	 * Warn about the submit actions the converted form does not perform.
	 *
	 * @param array $actions Elementor submit actions.
	 *
	 * @return void
	 */
	private function warn_about_skipped_actions( array $actions ): void {
		foreach ( $actions as $action ) {
			if ( in_array( $action, array( 'email', 'redirect' ), true ) || ! is_string( $action ) ) {
				continue;
			}

			if ( 'webhook' === $action ) {
				$this->log_warning( __( 'The form webhook was skipped. The converted form only sends email, so connect the webhook again with a form plugin if you need it.', 'migrate-off-elementor' ) );
				continue;
			}

			/* translators: %s: Elementor form action, such as mailchimp or email2. */
			$this->log_warning( sprintf( __( 'The "%s" form action was skipped. The converted form only sends email and redirects.', 'migrate-off-elementor' ), $action ) );
		}
	}

	/**
	 * Add a warning to the conversion log, when logging is on.
	 *
	 * @param string $message Warning text.
	 *
	 * @return void
	 */
	private function log_warning( string $message ): void {
		$log = Admin_Settings::instance()->get_conversion_log();
		if ( null !== $log ) {
			$log->record_warning( 'form', $message );
		}
	}

	/**
	 * Generate the form HTML the block's save() writes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML content.
	 */
	private function generate_form_html( array $attributes ): string {
		$form_fields      = $attributes['formFields'] ?? array();
		$input_size       = $attributes['inputSize'] ?? 'md';
		$label_typography = $attributes['labelTypography'] ?? array();
		$radius           = $attributes['buttonBorderRadius'] ?? array(
			'top'    => 0,
			'right'  => 0,
			'bottom' => 0,
			'left'   => 0,
		);
		$padding          = $attributes['buttonPadding'] ?? array(
			'top'    => 12,
			'right'  => 24,
			'bottom' => 12,
			'left'   => 24,
		);

		$label_style = $this->join_style(
			array(
				'font-family'    => (string) ( $label_typography['fontFamily'] ?? '' ),
				'font-weight'    => (string) ( $label_typography['fontWeight'] ?? 'normal' ),
				'letter-spacing' => ( $label_typography['letterSpacing'] ?? 0 ) . 'px',
				'word-spacing'   => ( $label_typography['wordSpacing'] ?? 0 ) . 'px',
				'margin-bottom'  => (int) ( $attributes['labelSpacing'] ?? 8 ) . 'px',
			)
		);

		$button_style = $this->join_style(
			array(
				'background-color' => (string) ( $attributes['buttonBackgroundColor'] ?? '' ),
				'color'            => (string) ( $attributes['buttonTextColor'] ?? '' ),
				'border-radius'    => sprintf( '%dpx %dpx %dpx %dpx', $radius['top'], $radius['right'], $radius['bottom'], $radius['left'] ),
				'padding'          => sprintf( '%dpx %dpx %dpx %dpx', $padding['top'], $padding['right'], $padding['bottom'], $padding['left'] ),
			)
		);

		$has_steps = false;
		foreach ( $form_fields as $field ) {
			$has_steps = $has_steps || 'step' === $field['fieldType'];
		}

		$form_attributes = array(
			'class'                => 'blockshift-form',
			'data-form-name'       => (string) ( $attributes['formName'] ?? 'contact-form' ),
			'data-form-id'         => (string) ( $attributes['formId'] ?? '' ),
			'data-success-message' => (string) ( $attributes['successMessage'] ?? 'Your submission was successful.' ),
			'data-error-message'   => (string) ( $attributes['errorMessage'] ?? 'Your submission failed because of an error.' ),
			'data-redirect'        => (string) ( $attributes['redirectUrl'] ?? '' ),
			'data-next-text'       => $has_steps ? (string) ( $attributes['nextButtonText'] ?? 'Next' ) : '',
			'data-previous-text'   => $has_steps ? (string) ( $attributes['previousButtonText'] ?? 'Previous' ) : '',
			'style'                => '--blockshift-form-gap:' . (int) ( $attributes['columnGap'] ?? 16 ) . 'px;row-gap:' . (int) ( $attributes['rowGap'] ?? 20 ) . 'px',
		);

		$html  = '<div class="wp-block-blockshift-form" id="" style="margin:0px 0px 0px 0px;padding:0px 0px 0px 0px">';
		$html .= '<form';
		foreach ( $form_attributes as $name => $value ) {
			if ( '' !== $value || in_array( $name, array( 'data-form-name', 'data-success-message', 'data-error-message' ), true ) ) {
				$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
		}
		$html .= '>';

		$html .= $has_steps ? $this->render_steps( $form_fields, $input_size, $label_style ) : $this->render_fields( $form_fields, $input_size, $label_style );

		$html .= '<div class="form-field form-field-honeypot" aria-hidden="true"><input type="text" name="blockshift_hp" tabindex="-1" autocomplete="off"/></div>';
		$html .= '<div class="form-button-wrapper" style="display:flex;justify-content:' . esc_attr( (string) ( $attributes['buttonAlign'] ?? 'start' ) ) . '">';
		$html .= '<button type="submit" class="form-submit-button" style="' . esc_attr( $button_style ) . '">' . esc_html( (string) ( $attributes['buttonText'] ?? 'Send' ) ) . '</button>';
		$html .= '</div>';
		$html .= '<div class="form-message" style="display:none"></div>';
		$html .= '</form></div>';

		return $html;
	}

	/**
	 * Join style declarations, leaving out the empty ones as the block's save() does.
	 *
	 * @param array<string, string> $declarations Property => value pairs.
	 *
	 * @return string Inline style value.
	 */
	private function join_style( array $declarations ): string {
		$parts = array();
		foreach ( $declarations as $property => $value ) {
			if ( '' !== $value ) {
				$parts[] = $property . ':' . $value;
			}
		}

		return implode( ';', $parts );
	}

	/**
	 * Render the fields of a multi-step form, one wrapper per step.
	 *
	 * @param array  $fields      Field definitions.
	 * @param string $input_size  Input size slug.
	 * @param string $label_style Inline label style.
	 *
	 * @return string Step markup.
	 */
	private function render_steps( array $fields, string $input_size, string $label_style ): string {
		$steps = array();
		foreach ( $fields as $field ) {
			if ( 'step' === $field['fieldType'] || empty( $steps ) ) {
				$steps[] = array(
					'label'  => 'step' === $field['fieldType'] ? $field['fieldLabel'] : '',
					'fields' => array(),
				);
			}
			if ( 'step' !== $field['fieldType'] ) {
				$steps[ count( $steps ) - 1 ]['fields'][] = $field;
			}
		}

		$html = '';
		foreach ( $steps as $step ) {
			$html .= '<div class="form-step" data-step-label="' . esc_attr( $step['label'] ) . '">' . $this->render_fields( $step['fields'], $input_size, $label_style ) . '</div>';
		}

		return $html;
	}

	/**
	 * Render a list of fields.
	 *
	 * @param array  $fields      Field definitions.
	 * @param string $input_size  Input size slug.
	 * @param string $label_style Inline label style.
	 *
	 * @return string Field markup.
	 */
	private function render_fields( array $fields, string $input_size, string $label_style ): string {
		$html = '';
		foreach ( $fields as $field ) {
			$html .= $this->render_field( $field, $input_size, $label_style );
		}

		return $html;
	}

	/**
	 * Render one field with its label and wrapper.
	 *
	 * @param array  $field       Field definition.
	 * @param string $input_size  Input size slug.
	 * @param string $label_style Inline label style.
	 *
	 * @return string Field markup.
	 */
	private function render_field( array $field, string $input_size, string $label_style ): string {
		$id   = esc_attr( $field['customId'] );
		$type = $field['fieldType'];

		if ( 'step' === $type ) {
			return '';
		}

		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . $id . '" value="' . esc_attr( (string) ( $field['value'] ?? '' ) ) . '"/>';
		}

		if ( 'honeypot' === $type ) {
			return '<div class="form-field form-field-honeypot" aria-hidden="true"><input type="text" name="' . $id . '" tabindex="-1" autocomplete="off"/></div>';
		}

		$style = '';
		if ( ! empty( $field['width'] ) ) {
			$widths = array(
				33 => '33.333%',
				66 => '66.666%',
			);
			$style  = ' style="--blockshift-field-width:' . esc_attr( $widths[ (int) $field['width'] ] ?? (int) $field['width'] . '%' ) . '"';
		}

		if ( 'html' === $type ) {
			return '<div class="form-field form-field-html"' . $style . '>' . ( $field['html'] ?? '' ) . '</div>';
		}

		$label_text = esc_html( $field['fieldLabel'] ) . ( $field['required'] ? ' *' : '' );
		$label      = '';
		if ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
			$label = '<span class="form-label" style="' . esc_attr( $label_style ) . '">' . $label_text . '</span>';
		} elseif ( 'acceptance' !== $type ) {
			$label = '<label for="' . $id . '" style="' . esc_attr( $label_style ) . '">' . $label_text . '</label>';
		}

		return '<div class="form-field form-field-' . esc_attr( $type ) . '"' . $style . '>' . $label . $this->render_control( $field, $input_size ) . '</div>';
	}

	/**
	 * Render the control of one field.
	 *
	 * @param array  $field      Field definition.
	 * @param string $input_size Input size slug.
	 *
	 * @return string Control markup.
	 */
	private function render_control( array $field, string $input_size ): string {
		$id       = esc_attr( $field['customId'] );
		$type     = $field['fieldType'];
		$class    = 'form-input size-' . esc_attr( $input_size );
		$required = $field['required'] ? ' required' : '';
		$options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

		if ( 'textarea' === $type ) {
			$rows = ! empty( $field['rows'] ) ? ' rows="' . (int) $field['rows'] . '"' : '';

			return '<textarea id="' . $id . '" name="' . $id . '" placeholder="' . esc_attr( $field['placeholder'] ) . '"' . $required . ' class="' . $class . '"' . $rows . '></textarea>';
		}

		if ( 'select' === $type ) {
			$multiple = ! empty( $field['multiple'] );
			$html     = '<select id="' . $id . '" name="' . $id . ( $multiple ? '[]' : '' ) . '"' . $required . ( $multiple ? ' multiple' : '' ) . ' class="' . $class . '">';
			foreach ( $options as $option ) {
				$html .= '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
			}

			return $html . '</select>';
		}

		if ( 'radio' === $type || 'checkbox' === $type ) {
			$is_radio = 'radio' === $type;
			$html     = '<div class="form-options' . ( ! empty( $field['inline'] ) ? ' is-inline' : '' ) . '">';
			foreach ( $options as $option ) {
				$html .= '<label class="form-option"><input type="' . $type . '" name="' . $id . ( $is_radio ? '' : '[]' ) . '" value="' . esc_attr( $option['value'] ) . '"' . ( $is_radio ? $required : '' ) . '/><span>' . esc_html( $option['label'] ) . '</span></label>';
			}

			return $html . '</div>';
		}

		if ( 'acceptance' === $type ) {
			return '<div class="form-options"><label class="form-option"><input type="checkbox" id="' . $id . '" name="' . $id . '" value="on"' . $required . '/><span>' . ( $field['acceptanceText'] ?? '' ) . '</span></label></div>';
		}

		$value = isset( $field['value'] ) && '' !== $field['value'] ? ' value="' . esc_attr( $field['value'] ) . '"' : '';

		return '<input type="' . esc_attr( $type ) . '" id="' . $id . '" name="' . $id . '" placeholder="' . esc_attr( $field['placeholder'] ) . '"' . $required . ' class="' . $class . '"' . $value . '/>';
	}
}
