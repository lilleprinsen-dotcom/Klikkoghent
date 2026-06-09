<?php
/**
 * Activation handling.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
final class Activator {
	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		if ( ! get_option( 'lp_cc_version' ) ) {
			add_option( 'lp_cc_version', LP_CC_VERSION );
		} else {
			update_option( 'lp_cc_version', LP_CC_VERSION );
		}
	}
}
