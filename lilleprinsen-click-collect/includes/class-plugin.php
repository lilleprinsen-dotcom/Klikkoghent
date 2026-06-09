<?php
/**
 * Central plugin coordinator.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and coordinates plugin modules.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $started = false;

	/**
	 * Settings module.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Asset module.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Order helper.
	 *
	 * @var Order_Helper
	 */
	private Order_Helper $order_helper;

	/**
	 * Audit log helper.
	 *
	 * @var Audit_Log
	 */
	private Audit_Log $audit_log;

	/**
	 * Return the shared plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		$this->settings     = new Settings();
		$this->assets       = new Assets();
		$this->order_helper = new Order_Helper();
		$this->audit_log    = new Audit_Log();
	}

	/**
	 * Register plugin hooks.
	 */
	public function run(): void {
		if ( $this->started ) {
			return;
		}

		$this->started = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->settings->register_hooks();
		$this->assets->register_hooks();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			LP_CC_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( LP_CC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get settings module.
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Get order helper module.
	 */
	public function order_helper(): Order_Helper {
		return $this->order_helper;
	}

	/**
	 * Get audit log module.
	 */
	public function audit_log(): Audit_Log {
		return $this->audit_log;
	}
}
