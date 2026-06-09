<?php
/**
 * WooCommerce order helper.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

use WC_Order;
use WC_Order_Item_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes HPOS-compatible order access.
 */
final class Order_Helper {
	public const META_IS_PICKUP_ORDER        = '_lp_cc_is_pickup_order';
	public const META_PICKUP_NUMBER          = '_lp_cc_pickup_number';
	public const META_QR_TOKEN               = '_lp_cc_qr_token';
	public const META_PICKUP_STATUS          = '_lp_cc_pickup_status';
	public const META_READY_AT               = '_lp_cc_ready_at';
	public const META_COLLECTED_AT           = '_lp_cc_collected_at';
	public const META_COLLECTED_BY           = '_lp_cc_collected_by';
	public const META_PAYMENT_CONFIRMED_AT   = '_lp_cc_payment_confirmed_at';
	public const META_PAYMENT_CONFIRMED_BY   = '_lp_cc_payment_confirmed_by';
	public const META_INTERNAL_NOTE          = '_lp_cc_internal_note';
	public const META_AUDIT_LOG              = '_lp_cc_audit_log';

	private const PICKUP_STATUS_NEW = 'new';

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
	 * Constructor.
	 *
	 * @param Pickup_Number $pickup_number Pickup number generator.
	 * @param Audit_Log     $audit_log Audit log helper.
	 */
	public function __construct( Pickup_Number $pickup_number, Audit_Log $audit_log ) {
		$this->pickup_number = $pickup_number;
		$this->audit_log     = $audit_log;
	}

	/**
	 * Register WooCommerce hooks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_new_order', array( $this, 'maybe_prepare_pickup_order_from_id' ), 20 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_prepare_pickup_order_from_id' ), 20 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_prepare_pickup_order_from_id' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_prepare_pickup_order_from_status_change' ), 20, 1 );
		add_filter( 'woocommerce_order_actions', array( $this, 'add_manual_order_action' ), 20, 2 );
		add_action( 'woocommerce_order_action_lp_cc_generate_pickup_metadata', array( $this, 'handle_manual_order_action' ) );
	}

	/**
	 * Fetch an order through WooCommerce CRUD.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return WC_Order|null
	 */
	public function get_order( int $order_id ): ?WC_Order {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Read order metadata through WooCommerce CRUD.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $key Meta key.
	 * @return mixed
	 */
	public function get_meta( WC_Order $order, string $key ) {
		return $order->get_meta( $key, true );
	}

	/**
	 * Update order metadata through WooCommerce CRUD.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $key Meta key.
	 * @param mixed    $value Meta value.
	 */
	public function update_meta( WC_Order $order, string $key, $value ): void {
		$order->update_meta_data( $key, $value );
		$order->save();
	}

	/**
	 * Prepare pickup metadata from a WooCommerce order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function maybe_prepare_pickup_order_from_id( int $order_id ): void {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$this->maybe_prepare_pickup_order( $order, false );
	}

	/**
	 * Prepare pickup metadata from an order status change.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function maybe_prepare_pickup_order_from_status_change( int $order_id ): void {
		$this->maybe_prepare_pickup_order_from_id( $order_id );
	}

	/**
	 * Add a manual action to generate missing pickup metadata on eligible orders.
	 *
	 * @param array<string, string> $actions Existing WooCommerce order actions.
	 * @param WC_Order|null         $order Current order when WooCommerce passes it.
	 * @return array<string, string>
	 */
	public function add_manual_order_action( array $actions, ?WC_Order $order = null ): array {
		if ( ! $order instanceof WC_Order ) {
			global $theorder;

			if ( $theorder instanceof WC_Order ) {
				$order = $theorder;
			}
		}

		if ( ! $order instanceof WC_Order || ! $this->can_generate_manual_metadata( $order ) ) {
			return $actions;
		}

		$actions['lp_cc_generate_pickup_metadata'] = __( 'Generer manglende hentedata', 'lilleprinsen-click-collect' );

		return $actions;
	}

	/**
	 * Handle the manual WooCommerce order action.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function handle_manual_order_action( WC_Order $order ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return;
		}

		$this->maybe_prepare_pickup_order( $order, true );
	}

	/**
	 * Detect and populate click-and-collect metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param bool     $manual Whether generation was requested manually by an admin.
	 */
	public function maybe_prepare_pickup_order( WC_Order $order, bool $manual = false ): bool {
		if ( ! (bool) Settings::get( 'enabled' ) ) {
			return false;
		}

		if ( ! $this->is_configured_pickup_order( $order ) ) {
			return false;
		}

		if ( ! $manual && ! (bool) Settings::get( 'auto_pickup_number' ) ) {
			$this->mark_as_pickup_order( $order );
			return false;
		}

		return $this->ensure_pickup_metadata( $order, $manual );
	}

	/**
	 * Check whether an order uses a configured pickup shipping method.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function is_configured_pickup_order( WC_Order $order ): bool {
		$configured_methods = Settings::get( 'pickup_shipping_methods' );
		$configured_methods = is_array( $configured_methods ) ? $configured_methods : array();

		if ( empty( $configured_methods ) ) {
			return false;
		}

		foreach ( $this->get_order_shipping_method_keys( $order ) as $method_key ) {
			if ( in_array( $method_key, $configured_methods, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return normalized shipping method keys for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<int, string>
	 */
	public function get_order_shipping_method_keys( WC_Order $order ): array {
		$keys = array();

		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			if ( ! $shipping_item instanceof WC_Order_Item_Shipping ) {
				continue;
			}

			$method_id   = sanitize_key( (string) $shipping_item->get_method_id() );
			$instance_id = absint( $shipping_item->get_instance_id() );

			if ( '' === $method_id ) {
				continue;
			}

			$keys[] = $method_id . ':' . $instance_id;
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Ensure all initial pickup metadata exists without overwriting hentenummer.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param bool     $manual Whether the request came from an admin action.
	 */
	public function ensure_pickup_metadata( WC_Order $order, bool $manual = false ): bool {
		$existing_pickup_number = (string) $order->get_meta( self::META_PICKUP_NUMBER, true );
		$pickup_number          = $existing_pickup_number;
		$generated_number       = false;

		if ( '' === $pickup_number ) {
			$pickup_number = $this->pickup_number->generate_for_order( $order );
			if ( null === $pickup_number ) {
				$this->audit_log->log(
					'warning',
					'Kunne ikke generere hentenummer for ordre.',
					array( 'order_id' => $order->get_id() )
				);

				return false;
			}

			$order->update_meta_data( self::META_PICKUP_NUMBER, $pickup_number );
			$generated_number = true;
		}

		$this->mark_as_pickup_order( $order, false );
		$this->update_meta_if_empty( $order, self::META_QR_TOKEN, $this->generate_qr_token() );
		$this->update_meta_if_empty( $order, self::META_PICKUP_STATUS, self::PICKUP_STATUS_NEW );
		$this->update_meta_if_empty( $order, self::META_READY_AT, '' );
		$this->update_meta_if_empty( $order, self::META_COLLECTED_AT, '' );
		$this->update_meta_if_empty( $order, self::META_COLLECTED_BY, '' );
		$this->update_meta_if_empty( $order, self::META_PAYMENT_CONFIRMED_AT, '' );
		$this->update_meta_if_empty( $order, self::META_PAYMENT_CONFIRMED_BY, '' );
		$this->update_meta_if_empty( $order, self::META_INTERNAL_NOTE, '' );

		if ( ! is_array( $order->get_meta( self::META_AUDIT_LOG, true ) ) ) {
			$order->update_meta_data( self::META_AUDIT_LOG, array() );
		}

		$order->save();

		if ( $generated_number ) {
			$this->audit_log->append_order_event(
				$order,
				'pickup_number_generated',
				sprintf(
					/* translators: %s: pickup number. */
					__( 'Hentenummer %s ble generert', 'lilleprinsen-click-collect' ),
					$pickup_number
				)
			);
		} elseif ( $manual ) {
			$this->audit_log->append_order_event(
				$order,
				'admin_metadata_repair',
				__( 'Manglende hentedata ble kontrollert manuelt av administrator.', 'lilleprinsen-click-collect' )
			);
		}

		return true;
	}

	/**
	 * Check whether the order already has pickup identity metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function is_pickup_order( WC_Order $order ): bool {
		return 'yes' === (string) $order->get_meta( self::META_IS_PICKUP_ORDER, true )
			|| '' !== (string) $order->get_meta( self::META_PICKUP_NUMBER, true );
	}

	/**
	 * Check whether an admin can generate or repair pickup metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function can_generate_manual_metadata( WC_Order $order ): bool {
		return ( $this->is_pickup_order( $order ) || $this->is_configured_pickup_order( $order ) )
			&& ! $this->has_initial_pickup_metadata( $order );
	}

	/**
	 * Mark an order as pickup from an admin action.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function mark_manually_as_pickup_order( WC_Order $order ): void {
		$this->mark_as_pickup_order( $order );

		$this->audit_log->append_order_event(
			$order,
			'admin_marked_pickup_order',
			__( 'Ordren ble markert som klikk og hent av administrator.', 'lilleprinsen-click-collect' )
		);
	}

	/**
	 * Regenerate the QR token for a pickup order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function regenerate_qr_token( WC_Order $order ): void {
		$order->update_meta_data( self::META_QR_TOKEN, $this->generate_qr_token() );
		$order->save();

		$this->audit_log->append_order_event(
			$order,
			'qr_token_regenerated',
			__( 'QR-token ble regenerert av administrator.', 'lilleprinsen-click-collect' )
		);
	}

	/**
	 * Clear a problem status by moving the internal pickup state back to new.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function clear_problem_status( WC_Order $order ): void {
		if ( 'problem' !== (string) $order->get_meta( self::META_PICKUP_STATUS, true ) ) {
			return;
		}

		$order->update_meta_data( self::META_PICKUP_STATUS, self::PICKUP_STATUS_NEW );
		$order->save();

		$this->audit_log->append_order_event(
			$order,
			'problem_status_cleared',
			__( 'Problemstatus ble fjernet av administrator.', 'lilleprinsen-click-collect' )
		);
	}

	/**
	 * Mark an order as pickup without overwriting other pickup data.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param bool     $save Whether to save immediately.
	 */
	public function mark_as_pickup_order( WC_Order $order, bool $save = true ): void {
		$order->update_meta_data( self::META_IS_PICKUP_ORDER, 'yes' );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Check whether initial pickup metadata is already present.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function has_initial_pickup_metadata( WC_Order $order ): bool {
		return 'yes' === (string) $order->get_meta( self::META_IS_PICKUP_ORDER, true )
			&& '' !== (string) $order->get_meta( self::META_PICKUP_NUMBER, true )
			&& '' !== (string) $order->get_meta( self::META_QR_TOKEN, true )
			&& '' !== (string) $order->get_meta( self::META_PICKUP_STATUS, true )
			&& is_array( $order->get_meta( self::META_AUDIT_LOG, true ) );
	}

	/**
	 * Update metadata only when a key is absent or empty.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $key Meta key.
	 * @param mixed    $value Meta value.
	 */
	private function update_meta_if_empty( WC_Order $order, string $key, $value ): void {
		$current_value = $order->get_meta( $key, true );

		if ( '' === $current_value || null === $current_value ) {
			$order->update_meta_data( $key, $value );
		}
	}

	/**
	 * Generate a secure random token for QR URLs.
	 */
	private function generate_qr_token(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $exception ) {
			return wp_generate_password( 64, false, false );
		}
	}
}
