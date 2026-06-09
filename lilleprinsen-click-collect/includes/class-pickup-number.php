<?php
/**
 * Pickup number generation.
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
 * Generates unique sequential hentenummer values.
 */
final class Pickup_Number {
	private const LOCK_OPTION = 'lp_cc_pickup_number_lock';
	private const LOCK_TTL    = 15;

	/**
	 * Generate and reserve the next unique pickup number.
	 *
	 * @param WC_Order $order Order receiving the pickup number.
	 * @return string|null
	 */
	public function generate_for_order( WC_Order $order ): ?string {
		if ( ! $this->acquire_lock() ) {
			return null;
		}

		try {
			$settings = Settings::get_all();
			$prefix   = (string) $settings['pickup_number_prefix'];
			$minimum  = max( 1, absint( $settings['min_number_length'] ) );
			$next     = max( 1, absint( $settings['next_pickup_number'] ) );

			for ( $attempt = 0; $attempt < 1000; $attempt++ ) {
				$candidate_number = $next + $attempt;
				$pickup_number    = $this->format_number( $prefix, $candidate_number, $minimum );

				if ( $this->is_unique( $pickup_number, $order->get_id() ) ) {
					$this->store_next_number( $candidate_number + 1 );

					return $pickup_number;
				}
			}
		} finally {
			$this->release_lock();
		}

		return null;
	}

	/**
	 * Build a formatted hentenummer from settings.
	 *
	 * @param string $prefix Prefix.
	 * @param int    $number Sequential number.
	 * @param int    $minimum Minimum numeric length.
	 */
	public function format_number( string $prefix, int $number, int $minimum ): string {
		return $prefix . str_pad( (string) max( 1, $number ), max( 1, $minimum ), '0', STR_PAD_LEFT );
	}

	/**
	 * Check whether a pickup number is unused by another order.
	 *
	 * @param string $pickup_number Pickup number.
	 * @param int    $current_order_id Current order ID.
	 */
	public function is_unique( string $pickup_number, int $current_order_id = 0 ): bool {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return true;
		}

		$order_ids = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'meta_key'   => '_lp_cc_pickup_number',
				'meta_value' => $pickup_number,
			)
		);

		if ( empty( $order_ids ) ) {
			return true;
		}

		return 1 === count( $order_ids ) && (int) reset( $order_ids ) === $current_order_id;
	}

	/**
	 * Store the next sequential number in plugin settings.
	 *
	 * @param int $next_number Next number.
	 */
	private function store_next_number( int $next_number ): void {
		$settings                         = Settings::get_all();
		$settings['next_pickup_number']   = max( 1, $next_number );
		$settings['pickup_number_prefix'] = (string) $settings['pickup_number_prefix'];

		update_option( Settings::OPTION_NAME, $settings );
	}

	/**
	 * Acquire a short-lived option lock so concurrent order events do not reserve the same number.
	 */
	private function acquire_lock(): bool {
		$expires_at = time() + self::LOCK_TTL;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			if ( add_option( self::LOCK_OPTION, (string) $expires_at, '', false ) ) {
				return true;
			}

			$current_expires_at = absint( get_option( self::LOCK_OPTION, 0 ) );
			if ( $current_expires_at > 0 && $current_expires_at < time() ) {
				delete_option( self::LOCK_OPTION );
				continue;
			}

			usleep( 100000 );
		}

		return false;
	}

	/**
	 * Release the pickup number lock.
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}
}
