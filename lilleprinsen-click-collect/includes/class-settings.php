<?php
/**
 * Settings page shell.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the initial WooCommerce settings page shell.
 */
final class Settings {
	public const MENU_SLUG = 'lp-cc-settings';

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the settings page under WooCommerce.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Klikk og hent', 'lilleprinsen-click-collect' ),
			__( 'Klikk og hent', 'lilleprinsen-click-collect' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render placeholder settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Du har ikke tilgang til denne siden.', 'lilleprinsen-click-collect' ) );
		}

		?>
		<div class="wrap lp-cc-admin-page">
			<h1><?php echo esc_html__( 'Klikk og hent', 'lilleprinsen-click-collect' ); ?></h1>
			<p>
				<?php echo esc_html__( 'Lilleprinsen Click & Collect er aktivert. Innstillinger bygges ut i kommende milepæler.', 'lilleprinsen-click-collect' ); ?>
			</p>
		</div>
		<?php
	}
}
