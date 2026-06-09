<?php
/**
 * Deactivation handling.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
final class Deactivator {
	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {
		// Keep options and order metadata intact. Deactivation should be reversible.
	}
}
