<?php
/**
 * Plugin Name: Lilleprinsen Click & Collect
 * Plugin URI: https://github.com/lilleprinsen-dotcom/Klikkoghent
 * Description: Staff terminal foundation for Lilleprinsen click-and-collect order handling.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Lilleprinsen
 * Text Domain: lilleprinsen-click-collect
 * Domain Path: /languages
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

use Lilleprinsen\ClickCollect\Activator;
use Lilleprinsen\ClickCollect\Compatibility;
use Lilleprinsen\ClickCollect\Deactivator;
use Lilleprinsen\ClickCollect\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LP_CC_VERSION', '0.1.0' );
define( 'LP_CC_PLUGIN_FILE', __FILE__ );
define( 'LP_CC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'LP_CC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LP_CC_TEXT_DOMAIN', 'lilleprinsen-click-collect' );

require_once LP_CC_PLUGIN_PATH . 'includes/class-compatibility.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-activator.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-deactivator.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-plugin.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-settings.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-pickup-number.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-qr-code.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-order-helper.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-audit-log.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-assets.php';
require_once LP_CC_PLUGIN_PATH . 'includes/class-admin-order-ui.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

add_action( 'before_woocommerce_init', array( Compatibility::class, 'declare_hpos_compatibility' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! Compatibility::meets_php_requirement() ) {
			add_action( 'admin_notices', array( Compatibility::class, 'php_version_notice' ) );
			return;
		}

		if ( ! Compatibility::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( Compatibility::class, 'missing_woocommerce_notice' ) );
			return;
		}

		Plugin::instance()->run();
	},
	20
);
