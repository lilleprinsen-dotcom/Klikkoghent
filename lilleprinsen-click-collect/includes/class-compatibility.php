<?php
/**
 * Compatibility checks and declarations.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles environment checks.
 */
final class Compatibility {
	private const MIN_PHP_VERSION = '8.1';

	/**
	 * Declare HPOS compatibility when WooCommerce exposes the feature utility.
	 */
	public static function declare_hpos_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', LP_CC_PLUGIN_FILE, true );
		}
	}

	/**
	 * Check the active PHP version.
	 */
	public static function meets_php_requirement(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Check whether WooCommerce is available.
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice for missing WooCommerce.
	 */
	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Lilleprinsen Click & Collect krever at WooCommerce er installert og aktivert.', 'lilleprinsen-click-collect' )
		);
	}

	/**
	 * Admin notice for unsupported PHP versions.
	 */
	public static function php_version_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'Lilleprinsen Click & Collect krever PHP %1$s eller nyere. Serveren kjører PHP %2$s.', 'lilleprinsen-click-collect' ),
					self::MIN_PHP_VERSION,
					PHP_VERSION
				)
			)
		);
	}
}
