<?php
/**
 * WooCommerce order helper.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes HPOS-compatible order access.
 */
final class Order_Helper {
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
}
