<?php
/**
 * Audit log helper.
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
 * Provides basic logging helpers for early skeleton work.
 */
final class Audit_Log {
	private const LOG_SOURCE = 'lilleprinsen-click-collect';

	/**
	 * Write a diagnostic log entry through WooCommerce when available.
	 *
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array  $context Extra log context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		if ( is_object( $logger ) && method_exists( $logger, 'log' ) ) {
			$logger->log(
				$level,
				$message,
				array_merge(
					array(
						'source' => self::LOG_SOURCE,
					),
					$context
				)
			);
		}
	}

	/**
	 * Placeholder for future per-order audit events.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $action Action key.
	 * @param string   $message Human-readable Norwegian message.
	 */
	public function append_order_event( WC_Order $order, string $action, string $message ): void {
		$events = $order->get_meta( '_lp_cc_audit_log', true );

		if ( ! is_array( $events ) ) {
			$events = array();
		}

		$events[] = array(
			'timestamp' => gmdate( 'c' ),
			'action'    => sanitize_key( $action ),
			'message'   => sanitize_text_field( $message ),
		);

		$order->update_meta_data( '_lp_cc_audit_log', $events );
		$order->save();
	}
}
