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
	 * Pickup number generator.
	 *
	 * @var Pickup_Number
	 */
	private Pickup_Number $pickup_number;

	/**
	 * Audit log helper.
	 *
	 * @var Audit_Log
	 */
	private Audit_Log $audit_log;

	/**
	 * QR code helper.
	 *
	 * @var QR_Code
	 */
	private QR_Code $qr_code;

	/**
	 * WooCommerce admin order UI.
	 *
	 * @var Admin_Order_UI
	 */
	private Admin_Order_UI $admin_order_ui;

	/**
	 * WP Overnight packing slip integration.
	 *
	 * @var WPO_Packing_Slip_Integration
	 */
	private WPO_Packing_Slip_Integration $wpo_packing_slip_integration;

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
		$this->settings      = new Settings();
		$this->assets        = new Assets();
		$this->audit_log     = new Audit_Log();
		$this->pickup_number = new Pickup_Number();
		$this->qr_code       = new QR_Code();
		$this->order_helper   = new Order_Helper( $this->pickup_number, $this->audit_log, $this->qr_code );
		$this->admin_order_ui = new Admin_Order_UI( $this->order_helper, $this->qr_code );
		$this->wpo_packing_slip_integration = new WPO_Packing_Slip_Integration( $this->order_helper, $this->qr_code );
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
		$this->order_helper->register_hooks();
		$this->admin_order_ui->register_hooks();
		$this->wpo_packing_slip_integration->register_hooks();
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
	 * Get pickup number module.
	 */
	public function pickup_number(): Pickup_Number {
		return $this->pickup_number;
	}

	/**
	 * Get audit log module.
	 */
	public function audit_log(): Audit_Log {
		return $this->audit_log;
	}

	/**
	 * Get QR code module.
	 */
	public function qr_code(): QR_Code {
		return $this->qr_code;
	}
}
