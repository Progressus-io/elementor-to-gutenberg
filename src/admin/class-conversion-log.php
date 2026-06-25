<?php
// phpcs:ignoreFile

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

	public function get_stats(): array {
		return array(
			'total'        => $this->total,
			'converted'    => $this->converted,
			'unsupported'  => $this->unsupported,
			'empty_output' => $this->empty_output,
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
		return $this->unsupported > 0 || $this->empty_output > 0;
	}

	public function to_array(): array {
		return array(
			'entries'             => $this->entries,
			'stats'               => $this->get_stats(),
			'unsupported_by_type' => $this->get_unsupported_by_type(),
		);
	}
}
