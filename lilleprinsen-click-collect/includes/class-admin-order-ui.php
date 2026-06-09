<?php
/**
 * WooCommerce admin order UI.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

use WC_Order;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows click-and-collect information on WooCommerce admin order screens.
 */
final class Admin_Order_UI {
	private const ACTION_HOOK       = 'lp_cc_order_admin_action';
	private const MESSAGE_QUERY_VAR = 'lp_cc_message';

	/**
	 * Order helper.
	 *
	 * @var Order_Helper
	 */
	private Order_Helper $order_helper;

	/**
	 * Constructor.
	 *
	 * @param Order_Helper $order_helper Order helper.
	 */
	public function __construct( Order_Helper $order_helper ) {
		$this->order_helper = $order_helper;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 20, 2 );
		add_action( 'admin_post_' . self::ACTION_HOOK, array( $this, 'handle_admin_action' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_list_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_classic_order_list_column' ), 20, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_list_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_order_list_column' ), 20, 2 );
		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'add_search_field' ) );
	}

	/**
	 * Register the Click & Collect order panel.
	 *
	 * @param string $post_type Current post type/screen context.
	 * @param mixed  $post_or_order Post or order object.
	 */
	public function register_meta_box( string $post_type, $post_or_order = null ): void {
		$order = $this->resolve_order( $post_or_order );
		if ( ! $order || ! $this->should_show_panel( $order ) ) {
			return;
		}

		foreach ( $this->get_order_screen_ids() as $screen_id ) {
			add_meta_box(
				'lp-cc-order-panel',
				__( 'Klikk og hent', 'lilleprinsen-click-collect' ),
				array( $this, 'render_meta_box' ),
				$screen_id,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the admin order panel.
	 *
	 * @param mixed $post_or_order Post or order object.
	 */
	public function render_meta_box( $post_or_order ): void {
		$order = $this->resolve_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$pickup_number = (string) $order->get_meta( Order_Helper::META_PICKUP_NUMBER, true );
		$status        = (string) $order->get_meta( Order_Helper::META_PICKUP_STATUS, true );
		$qr_token      = (string) $order->get_meta( Order_Helper::META_QR_TOKEN, true );
		$audit_log     = $order->get_meta( Order_Helper::META_AUDIT_LOG, true );
		$debug_enabled = (bool) Settings::get( 'debug_logging' );

		?>
		<div class="lp-cc-order-panel">
			<div class="lp-cc-order-hero">
				<span><?php echo esc_html__( 'Hentenummer', 'lilleprinsen-click-collect' ); ?></span>
				<strong><?php echo esc_html( '' !== $pickup_number ? $pickup_number : __( 'Mangler', 'lilleprinsen-click-collect' ) ); ?></strong>
			</div>

			<dl class="lp-cc-order-facts">
				<?php $this->render_fact( __( 'Intern status', 'lilleprinsen-click-collect' ), $this->get_pickup_status_label( $status ) ); ?>
				<?php $this->render_fact( __( 'QR-token', 'lilleprinsen-click-collect' ), $this->get_qr_token_status( $qr_token, $debug_enabled ), $debug_enabled && '' !== $qr_token ); ?>
				<?php $this->render_fact( __( 'Betaling', 'lilleprinsen-click-collect' ), $this->get_payment_classification_label( $order ) ); ?>
				<?php $this->render_fact( __( 'Betaling bekreftet', 'lilleprinsen-click-collect' ), $this->format_meta_datetime( $order, Order_Helper::META_PAYMENT_CONFIRMED_AT ) ); ?>
				<?php $this->render_fact( __( 'Klar', 'lilleprinsen-click-collect' ), $this->format_meta_datetime( $order, Order_Helper::META_READY_AT ) ); ?>
				<?php $this->render_fact( __( 'Hentet', 'lilleprinsen-click-collect' ), $this->format_meta_datetime( $order, Order_Helper::META_COLLECTED_AT ) ); ?>
			</dl>

			<div class="lp-cc-order-note">
				<h4><?php echo esc_html__( 'Intern note', 'lilleprinsen-click-collect' ); ?></h4>
				<p><?php echo esc_html( $this->get_internal_note( $order ) ); ?></p>
			</div>

			<div class="lp-cc-order-actions">
				<?php $this->render_actions( $order, $pickup_number, $qr_token, $status ); ?>
			</div>

			<div class="lp-cc-audit-log">
				<h4><?php echo esc_html__( 'Historikk', 'lilleprinsen-click-collect' ); ?></h4>
				<?php $this->render_audit_log( is_array( $audit_log ) ? $audit_log : array() ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle nonce-protected admin order actions.
	 */
	public function handle_admin_action(): void {
		$order_id = absint( filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT ) );
		$action   = sanitize_key( (string) filter_input( INPUT_GET, 'lp_cc_action', FILTER_UNSAFE_RAW ) );
		$order    = $this->order_helper->get_order( $order_id );

		if ( ! $order ) {
			wp_die( esc_html__( 'Ordren ble ikke funnet.', 'lilleprinsen-click-collect' ) );
		}

		$this->check_order_permission( $order );
		check_admin_referer( $this->get_nonce_action( $order ) );

		$message = 'updated';

		if ( 'generate_pickup_number' === $action ) {
			$this->order_helper->ensure_pickup_metadata( $order, true );
			$message = 'generated';
		} elseif ( 'regenerate_qr_token' === $action ) {
			$this->order_helper->regenerate_qr_token( $order );
			$message = 'qr_regenerated';
		} elseif ( 'mark_pickup_order' === $action ) {
			$this->order_helper->mark_manually_as_pickup_order( $order );
			$message = 'marked_pickup';
		} elseif ( 'clear_problem_status' === $action ) {
			$this->order_helper->clear_problem_status( $order );
			$message = 'problem_cleared';
		}

		wp_safe_redirect(
			add_query_arg(
				self::MESSAGE_QUERY_VAR,
				$message,
				$this->get_order_edit_url( $order )
			)
		);
		exit;
	}

	/**
	 * Render admin notice after an action.
	 */
	public function render_admin_notice(): void {
		$message_key = sanitize_key( (string) filter_input( INPUT_GET, self::MESSAGE_QUERY_VAR, FILTER_UNSAFE_RAW ) );
		if ( '' === $message_key ) {
			return;
		}

		$messages = array(
			'generated'       => __( 'Klikk og hent-data ble generert.', 'lilleprinsen-click-collect' ),
			'qr_regenerated'  => __( 'QR-token ble regenerert.', 'lilleprinsen-click-collect' ),
			'marked_pickup'   => __( 'Ordren ble markert som klikk og hent.', 'lilleprinsen-click-collect' ),
			'problem_cleared' => __( 'Problemstatus ble fjernet.', 'lilleprinsen-click-collect' ),
			'updated'         => __( 'Klikk og hent-data ble oppdatert.', 'lilleprinsen-click-collect' ),
		);

		if ( ! isset( $messages[ $message_key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $messages[ $message_key ] )
		);
	}

	/**
	 * Add pickup number column to order lists.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_order_list_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'order_number' === $key ) {
				$new_columns['lp_cc_pickup_number'] = __( 'Hentenummer', 'lilleprinsen-click-collect' );
			}
		}

		if ( ! isset( $new_columns['lp_cc_pickup_number'] ) ) {
			$new_columns['lp_cc_pickup_number'] = __( 'Hentenummer', 'lilleprinsen-click-collect' );
		}

		return $new_columns;
	}

	/**
	 * Render pickup number in classic order list.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Order post ID.
	 */
	public function render_classic_order_list_column( string $column, int $post_id ): void {
		if ( 'lp_cc_pickup_number' !== $column ) {
			return;
		}

		$order = $this->order_helper->get_order( $post_id );
		$this->render_pickup_number_column_value( $order );
	}

	/**
	 * Render pickup number in HPOS order list.
	 *
	 * @param string       $column Column key.
	 * @param WC_Order|int $order_or_id Order object or ID.
	 */
	public function render_hpos_order_list_column( string $column, $order_or_id ): void {
		if ( 'lp_cc_pickup_number' !== $column ) {
			return;
		}

		$order = $order_or_id instanceof WC_Order ? $order_or_id : $this->order_helper->get_order( absint( $order_or_id ) );
		$this->render_pickup_number_column_value( $order );
	}

	/**
	 * Include hentenummer in WooCommerce order search fields.
	 *
	 * @param array<int, string> $fields Search fields.
	 * @return array<int, string>
	 */
	public function add_search_field( array $fields ): array {
		$fields[] = Order_Helper::META_PICKUP_NUMBER;

		return array_values( array_unique( $fields ) );
	}

	/**
	 * Resolve the current admin order.
	 *
	 * @param mixed $post_or_order Post or order object.
	 */
	private function resolve_order( $post_or_order = null ): ?WC_Order {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( $post_or_order instanceof WP_Post ) {
			return $this->order_helper->get_order( (int) $post_or_order->ID );
		}

		$order_id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );
		if ( 0 === $order_id ) {
			$order_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );
		}

		return $order_id > 0 ? $this->order_helper->get_order( $order_id ) : null;
	}

	/**
	 * Check whether the panel should be shown.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function should_show_panel( WC_Order $order ): bool {
		return $this->order_helper->is_pickup_order( $order )
			|| $this->order_helper->is_configured_pickup_order( $order )
			|| $this->order_helper->can_generate_manual_metadata( $order );
	}

	/**
	 * Return compatible order screen IDs.
	 *
	 * @return array<int, string>
	 */
	private function get_order_screen_ids(): array {
		$screen_ids = array( 'shop_order' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_ids[] = wc_get_page_screen_id( 'shop-order' );
		}

		$screen_ids[] = 'woocommerce_page_wc-orders';

		return array_values( array_unique( array_filter( $screen_ids ) ) );
	}

	/**
	 * Render a label/value fact.
	 *
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @param bool   $code Whether value should render as code.
	 */
	private function render_fact( string $label, string $value, bool $code = false ): void {
		?>
		<div>
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd>
				<?php if ( $code ) : ?>
					<code><?php echo esc_html( $value ); ?></code>
				<?php else : ?>
					<?php echo esc_html( $value ); ?>
				<?php endif; ?>
			</dd>
		</div>
		<?php
	}

	/**
	 * Render available action buttons.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $pickup_number Pickup number.
	 * @param string   $qr_token QR token.
	 * @param string   $status Pickup status.
	 */
	private function render_actions( WC_Order $order, string $pickup_number, string $qr_token, string $status ): void {
		if ( ! $this->can_manage_order( $order ) ) {
			return;
		}

		if ( ! $this->order_helper->is_pickup_order( $order ) ) {
			$this->render_action_button( $order, 'mark_pickup_order', __( 'Marker som klikk og hent', 'lilleprinsen-click-collect' ) );
		}

		if ( '' === $pickup_number || $this->order_helper->can_generate_manual_metadata( $order ) ) {
			$label = '' === $pickup_number
				? __( 'Generer manglende hentenummer', 'lilleprinsen-click-collect' )
				: __( 'Reparer manglende hentedata', 'lilleprinsen-click-collect' );

			$this->render_action_button( $order, 'generate_pickup_number', $label, 'button-primary' );
		}

		if ( $this->order_helper->is_pickup_order( $order ) && '' !== $qr_token ) {
			$this->render_action_button( $order, 'regenerate_qr_token', __( 'Regenerer QR-token', 'lilleprinsen-click-collect' ) );
		}

		if ( 'problem' === $status ) {
			$this->render_action_button( $order, 'clear_problem_status', __( 'Fjern problemstatus', 'lilleprinsen-click-collect' ) );
		}
	}

	/**
	 * Render one action link.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $action Action key.
	 * @param string   $label Link label.
	 * @param string   $class Button class.
	 */
	private function render_action_button( WC_Order $order, string $action, string $label, string $class = 'button-secondary' ): void {
		$url = add_query_arg(
			array(
				'action'       => self::ACTION_HOOK,
				'order_id'     => $order->get_id(),
				'lp_cc_action' => $action,
			),
			admin_url( 'admin-post.php' )
		);

		printf(
			'<a class="button %1$s" href="%2$s">%3$s</a>',
			esc_attr( $class ),
			esc_url( wp_nonce_url( $url, $this->get_nonce_action( $order ) ) ),
			esc_html( $label )
		);
	}

	/**
	 * Render audit log entries.
	 *
	 * @param array<int, mixed> $events Audit events.
	 */
	private function render_audit_log( array $events ): void {
		if ( empty( $events ) ) {
			echo '<p class="lp-cc-muted">' . esc_html__( 'Ingen historikk ennå.', 'lilleprinsen-click-collect' ) . '</p>';
			return;
		}

		echo '<ol>';
		foreach ( array_reverse( $events ) as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$timestamp = isset( $event['timestamp'] ) ? (string) $event['timestamp'] : '';
			$message   = isset( $event['message'] ) ? (string) $event['message'] : '';
			$action    = isset( $event['action'] ) ? (string) $event['action'] : '';

			echo '<li>';
			echo '<strong>' . esc_html( $this->format_datetime_string( $timestamp ) ) . '</strong>';
			echo '<span>' . esc_html( $message ) . '</span>';
			if ( '' !== $action ) {
				echo '<small>' . esc_html( $action ) . '</small>';
			}
			echo '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Render order list pickup number value.
	 *
	 * @param WC_Order|null $order WooCommerce order.
	 */
	private function render_pickup_number_column_value( ?WC_Order $order ): void {
		if ( ! $order ) {
			echo esc_html( '-' );
			return;
		}

		$pickup_number = (string) $order->get_meta( Order_Helper::META_PICKUP_NUMBER, true );
		echo esc_html( '' !== $pickup_number ? $pickup_number : '-' );
	}

	/**
	 * Return internal pickup status label.
	 *
	 * @param string $status Status key.
	 */
	private function get_pickup_status_label( string $status ): string {
		$labels = array(
			'new'       => __( 'Ny', 'lilleprinsen-click-collect' ),
			'picking'   => __( 'Plukkes', 'lilleprinsen-click-collect' ),
			'ready'     => __( 'Klar', 'lilleprinsen-click-collect' ),
			'collected' => __( 'Hentet', 'lilleprinsen-click-collect' ),
			'problem'   => __( 'Problem', 'lilleprinsen-click-collect' ),
		);

		return $labels[ $status ] ?? __( 'Ikke satt', 'lilleprinsen-click-collect' );
	}

	/**
	 * Return QR token status.
	 *
	 * @param string $qr_token QR token.
	 * @param bool   $debug_enabled Whether debug is enabled.
	 */
	private function get_qr_token_status( string $qr_token, bool $debug_enabled ): string {
		if ( '' === $qr_token ) {
			return __( 'Mangler', 'lilleprinsen-click-collect' );
		}

		if ( $debug_enabled ) {
			return $qr_token;
		}

		return sprintf(
			/* translators: %s: token suffix. */
			__( 'Lagret, slutter på %s', 'lilleprinsen-click-collect' ),
			substr( $qr_token, -6 )
		);
	}

	/**
	 * Return payment classification label.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_payment_classification_label( WC_Order $order ): string {
		$payment_method = sanitize_key( (string) $order->get_payment_method() );
		$paid_online    = Settings::get( 'paid_online_methods' );
		$pay_in_store   = Settings::get( 'pay_in_store_methods' );
		$paid_online    = is_array( $paid_online ) ? $paid_online : array();
		$pay_in_store   = is_array( $pay_in_store ) ? $pay_in_store : array();

		if ( in_array( $payment_method, $paid_online, true ) ) {
			return __( 'Betalt på nett', 'lilleprinsen-click-collect' );
		}

		if ( in_array( $payment_method, $pay_in_store, true ) ) {
			return __( 'Må betales i butikk', 'lilleprinsen-click-collect' );
		}

		return __( 'Ukjent', 'lilleprinsen-click-collect' );
	}

	/**
	 * Return formatted datetime from order metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $meta_key Meta key.
	 */
	private function format_meta_datetime( WC_Order $order, string $meta_key ): string {
		return $this->format_datetime_string( (string) $order->get_meta( $meta_key, true ) );
	}

	/**
	 * Return formatted datetime or dash.
	 *
	 * @param string $datetime Datetime string.
	 */
	private function format_datetime_string( string $datetime ): string {
		if ( '' === $datetime ) {
			return '-';
		}

		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return $datetime;
		}

		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Return internal note fallback.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_internal_note( WC_Order $order ): string {
		$note = (string) $order->get_meta( Order_Helper::META_INTERNAL_NOTE, true );

		return '' !== $note ? $note : __( 'Ingen intern note.', 'lilleprinsen-click-collect' );
	}

	/**
	 * Check order permissions or stop request.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function check_order_permission( WC_Order $order ): void {
		if ( ! $this->can_manage_order( $order ) ) {
			wp_die( esc_html__( 'Du har ikke tilgang til å endre denne ordren.', 'lilleprinsen-click-collect' ) );
		}
	}

	/**
	 * Check whether the current user can manage an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function can_manage_order( WC_Order $order ): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_order', $order->get_id() );
	}

	/**
	 * Return nonce action for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_nonce_action( WC_Order $order ): string {
		return 'lp_cc_order_action_' . $order->get_id();
	}

	/**
	 * Return edit URL for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_order_edit_url( WC_Order $order ): string {
		if ( method_exists( $order, 'get_edit_order_url' ) ) {
			return $order->get_edit_order_url();
		}

		return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
	}
}
