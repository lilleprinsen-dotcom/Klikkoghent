<?php
/**
 * Asset registration.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin assets.
 */
final class Assets {
	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
	}

	/**
	 * Register admin assets without enqueueing globally.
	 */
	public function register_admin_assets(): void {
		wp_register_style(
			'lp-cc-admin',
			LP_CC_PLUGIN_URL . 'assets/admin/admin.css',
			array(),
			LP_CC_VERSION
		);
	}
}
