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
	 * Register admin assets and enqueue them only on this plugin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function register_admin_assets( string $hook_suffix ): void {
		wp_register_style(
			'lp-cc-admin',
			LP_CC_PLUGIN_URL . 'assets/admin/admin.css',
			array(),
			LP_CC_VERSION
		);

		if ( $this->should_enqueue_admin_styles( $hook_suffix ) ) {
			wp_enqueue_style( 'lp-cc-admin' );
		}
	}

	/**
	 * Check whether admin CSS should load on the current screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	private function should_enqueue_admin_styles( string $hook_suffix ): bool {
		if ( 'woocommerce_page_' . Settings::MENU_SLUG === $hook_suffix ) {
			return true;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return 'shop_order' === $screen->post_type || 'woocommerce_page_wc-orders' === $screen->id;
	}
}
