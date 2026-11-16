<?php
/**
 * Widget-level conversion log collector for a single page conversion.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Collects per-widget events during one conversion run.
 * Reset at the start of each convert_json_to_gutenberg_content() call.
 */
class Conversion_Log {

	private array $entries             = array();
	private int   $total               = 0;
	private int   $converted           = 0;
	private int   $unsupported         = 0;
	private int   $empty_output        = 0;
	private int   $content_lost        = 0;
	private int   $partial_output      = 0;
	private int   $warnings            = 0;
	private array $unsupported_by_type = array();

	public function record_unsupported( string $widget_type ): void {
		$this->total++;
		$this->unsupported++;
		$key                               = '' !== $widget_type ? $widget_type : 'unknown';
		$this->unsupported_by_type[ $key ] = ( $this->unsupported_by_type[ $key ] ?? 0 ) + 1;
		$this->entries[]                   = array(
			'level'  => 'warning',
			'type'   => 'unsupported',
			'widget' => $key,
		);
	}

	public function record_converted( string $widget_type ): void {
		$this->total++;
		$this->converted++;
	}

	public function record_empty_output( string $widget_type ): void {
		$this->total++;
		$this->empty_output++;
		$key             = '' !== $widget_type ? $widget_type : 'unknown';
		$this->entries[] = array(
			'level'  => 'info',
			'type'   => 'empty_output',
			'widget' => $key,
		);
	}

	/**
	 * Record a widget that held content but produced no output at all.
	 *
	 * @param string             $widget_type Elementor widget type.
	 * @param array<int, string> $missing     Text the widget held.
	 *
	 * @return void
	 */
	public function record_content_lost( string $widget_type, array $missing ): void {
		++$this->total;
		++$this->content_lost;
		$this->entries[] = array(
			'level'   => 'warning',
			'type'    => 'content_lost',
			'widget'  => '' !== $widget_type ? $widget_type : 'unknown',
			'missing' => array_slice( $missing, 0, 5 ),
		);
	}

	/**
	 * Record a widget whose output is missing some of the text it held.
	 *
	 * @param string             $widget_type Elementor widget type.
	 * @param array<int, string> $missing     Text that did not reach the output.
	 *
	 * @return void
	 */
	public function record_partial_output( string $widget_type, array $missing ): void {
		++$this->total;
		++$this->converted;
		++$this->partial_output;
		$this->entries[] = array(
			'level'   => 'warning',
			'type'    => 'partial_output',
			'widget'  => '' !== $widget_type ? $widget_type : 'unknown',
			'missing' => array_slice( $missing, 0, 5 ),
		);
	}

	/**
	 * Record a warning about something a converted widget could not carry over.
	 *
	 * @param string $widget_type Elementor widget type.
	 * @param string $message     What was left behind.
	 *
	 * @return void
	 */
	public function record_warning( string $widget_type, string $message ): void {
		++$this->warnings;
		$this->entries[] = array(
			'level'   => 'warning',
			'type'    => 'warning',
			'widget'  => '' !== $widget_type ? $widget_type : 'unknown',
			'message' => $message,
		);
	}

	public function get_stats(): array {
		return array(
			'total'          => $this->total,
			'converted'      => $this->converted,
			'unsupported'    => $this->unsupported,
			'empty_output'   => $this->empty_output,
			'content_lost'   => $this->content_lost,
			'partial_output' => $this->partial_output,
			'warnings'       => $this->warnings,
		);
	}

	public function get_entries(): array {
		return $this->entries;
	}

	public function get_unsupported_by_type(): array {
		$result = $this->unsupported_by_type;
		arsort( $result );
		return $result;
	}

	public function has_issues(): bool {
		return $this->unsupported > 0 || $this->empty_output > 0 || $this->content_lost > 0 || $this->partial_output > 0 || $this->warnings > 0;
	}

	/**
	 * Get the warnings worth showing next to a converted item, as readable lines.
	 *
	 * @return array<int, string> One line per lost, partial or skipped piece of content.
	 */
	public function get_warning_lines(): array {
		$lines = array();
		foreach ( $this->entries as $entry ) {
			$missing = isset( $entry['missing'] ) ? implode( ' | ', (array) $entry['missing'] ) : '';

			if ( 'content_lost' === $entry['type'] ) {
				/* translators: 1: Elementor widget type, 2: text the widget held. */
				$lines[] = sprintf( __( '%1$s: no output, content lost (%2$s)', 'migrate-off-elementor' ), $entry['widget'], $missing );
			} elseif ( 'partial_output' === $entry['type'] ) {
				/* translators: 1: Elementor widget type, 2: text missing from the converted output. */
				$lines[] = sprintf( __( '%1$s: partial output, missing (%2$s)', 'migrate-off-elementor' ), $entry['widget'], $missing );
			} elseif ( 'warning' === $entry['type'] ) {
				$lines[] = $entry['widget'] . ': ' . $entry['message'];
			}
		}

		return $lines;
	}

	public function to_array(): array {
		return array(
			'entries'             => $this->entries,
			'stats'               => $this->get_stats(),
			'unsupported_by_type' => $this->get_unsupported_by_type(),
			'warnings'            => $this->get_warning_lines(),
		);
	}
}
