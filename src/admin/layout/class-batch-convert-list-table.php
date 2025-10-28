<?php
/**
 * List table for batch conversion wizard.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

use WP_List_Table;
use WP_Query;

use function absint;
use function date_i18n;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_option;
use function get_permalink;
use function get_post_meta;
use function get_the_title;
use function sanitize_text_field;
use function sprintf;
use function strtotime;
use function wp_reset_postdata;
use function wp_unslash;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Batch convert list table.
 */
class Batch_Convert_List_Table extends WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ele2gb-page',
				'plural'   => 'ele2gb-pages',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Retrieve columns.
	 */
	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'title'       => esc_html__( 'Title', 'elementor-to-gutenberg' ),
			'status'      => esc_html__( 'Status', 'elementor-to-gutenberg' ),
			'last_result' => esc_html__( 'Last result', 'elementor-to-gutenberg' ),
			'last_run'    => esc_html__( 'Last run', 'elementor-to-gutenberg' ),
		);
	}

	/**
	 * Prepare table items.
	 */
	public function prepare_items(): void {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = $this->get_items_per_page( 'ele2gb_pages_per_page', 20 );
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'post_type'      => array( 'page' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_query'     => array(
				array(
					'key'     => '_elementor_data',
					'value'   => '',
					'compare' => '!=',
				),
			),
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$query = new WP_Query( $query_args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'post'        => $post,
				'last_result' => get_post_meta( $post->ID, '_ele2gb_last_result', true ),
			);
		}

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => max( 1, $query->max_num_pages ),
			)
		);

		wp_reset_postdata();
	}

	/**
	 * Render checkbox column.
	 *
	 * @param array $item Item array.
	 */
	public function column_cb( $item ): string { // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
		$post = $item['post'];

		return '<input type="checkbox" name="post_ids[]" value="' . esc_attr( $post->ID ) . '" />';
	}

	/**
	 * Render title column.
	 *
	 * @param array $item Item array.
	 */
	public function column_title( $item ): string { // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
		$post      = $item['post'];
		$title     = get_the_title( $post );
		$edit_link = get_edit_post_link( $post );
		$view_link = get_permalink( $post );

		$title_html = '<strong>' . esc_html( $title ) . '</strong>';
		if ( $edit_link ) {
			$title_html = '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
		}

		$actions = array();
		if ( $edit_link ) {
			$actions['edit'] = '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'elementor-to-gutenberg' ) . '</a>';
		}
		if ( $view_link ) {
			$actions['view'] = '<a href="' . esc_url( $view_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'elementor-to-gutenberg' ) . '</a>';
		}

		return $title_html . $this->row_actions( $actions );
	}

	/**
	 * Render status column.
	 */
	public function column_status( $item ): string {
		$post = $item['post'];

		return esc_html( ucfirst( $post->post_status ) );
	}

	/**
	 * Render last result column.
	 */
	public function column_last_result( $item ): string {
		$last_result = is_array( $item['last_result'] ) ? $item['last_result'] : array();

		if ( empty( $last_result ) ) {
			return '&#8212;';
		}

		$status  = isset( $last_result['status'] ) ? $last_result['status'] : '';
		$message = isset( $last_result['message'] ) ? $last_result['message'] : '';

		if ( '' === $status && '' === $message ) {
			return '&#8212;';
		}

		if ( '' !== $status ) {
			$status = ucfirst( $status );
		}

		if ( '' !== $status && '' !== $message ) {
			return esc_html( sprintf( '%1$s — %2$s', $status, $message ) );
		}

		if ( '' !== $status ) {
			return esc_html( $status );
		}

		return esc_html( $message );
	}

	/**
	 * Render last run column.
	 */
	public function column_last_run( $item ): string {
		$last_result = is_array( $item['last_result'] ) ? $item['last_result'] : array();
		$time        = isset( $last_result['time'] ) ? $last_result['time'] : '';

		if ( empty( $time ) ) {
			return '&#8212;';
		}

		$timestamp = strtotime( $time );
		if ( false === $timestamp ) {
			return esc_html( $time );
		}

		$formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

		return esc_html( $formatted );
	}

	/**
	 * No items message.
	 */
	public function no_items(): void {
		esc_html_e( 'No Elementor pages found.', 'elementor-to-gutenberg' );
	}
}