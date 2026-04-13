<?php
/**
 * Storage for manual AI improvement workspace.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function current_time;
use function get_post_meta;
use function update_post_meta;

defined( 'ABSPATH' ) || exit;

class AI_Workspace_Repository {

	const META_KEY = '_ele2gb_ai_workspace';

	/**
	 * Load workspace data by target page id.
	 *
	 * @param int $target_id Target page ID.
	 */
	public static function get( int $target_id ): array {
		$data = get_post_meta( $target_id, self::META_KEY, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	/**
	 * Save workspace data by target page id.
	 *
	 * @param int $target_id Target page ID.
	 * @param array $workspace Workspace payload.
	 */
	public static function save( int $target_id, array $workspace ): void {
		$workspace['updated_at'] = current_time( 'mysql' );
		update_post_meta( $target_id, self::META_KEY, $workspace );
	}
}
