<?php
/**
 * Staff profile and PIN management.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores staff terminal profiles in a structured option.
 */
final class Staff_Profiles {
	public const OPTION_NAME = 'lp_cc_staff_profiles';

	private const ACTION_CREATE  = 'lp_cc_staff_profile_create';
	private const ACTION_UPDATE  = 'lp_cc_staff_profile_update';
	private const MESSAGE_QUERY  = 'lp_cc_staff_message';
	private const MAX_ATTEMPTS   = 5;
	private const LOCK_SECONDS   = 600;
	private const PROFILE_ROLES  = array( 'staff', 'manager' );
	private const DEFAULT_COLOR  = '#6b7280';

	/**
	 * Register admin hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION_CREATE, array( $this, 'handle_create_action' ) );
		add_action( 'admin_post_' . self::ACTION_UPDATE, array( $this, 'handle_update_action' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Return all stored profile data.
	 *
	 * @return array{next_id:int, profiles:array<string, array<string, mixed>>}
	 */
	public function get_store(): array {
		$store = get_option( self::OPTION_NAME, array() );
		$store = is_array( $store ) ? $store : array();

		$next_id  = isset( $store['next_id'] ) ? max( 1, absint( $store['next_id'] ) ) : 1;
		$profiles = isset( $store['profiles'] ) && is_array( $store['profiles'] ) ? $store['profiles'] : array();

		return array(
			'next_id'  => $next_id,
			'profiles' => $this->normalize_profiles( $profiles ),
		);
	}

	/**
	 * Return all profiles, including inactive profiles.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_profiles(): array {
		$store = $this->get_store();

		return $store['profiles'];
	}

	/**
	 * Return one profile by ID.
	 *
	 * @param string $profile_id Profile ID.
	 * @return array<string, mixed>|null
	 */
	public function get_profile( string $profile_id ): ?array {
		$profile_id = sanitize_key( $profile_id );
		$profiles   = $this->get_profiles();

		return $profiles[ $profile_id ] ?? null;
	}

	/**
	 * Return active profiles for terminal login.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_active_profiles(): array {
		return array_filter(
			$this->get_profiles(),
			static function ( array $profile ): bool {
				return ! empty( $profile['active'] );
			}
		);
	}

	/**
	 * Return display name for a profile ID.
	 *
	 * @param string $profile_id Profile ID.
	 */
	public function get_profile_display_name( string $profile_id ): string {
		$profiles = $this->get_profiles();

		return isset( $profiles[ $profile_id ] ) ? (string) $profiles[ $profile_id ]['name'] : '';
	}

	/**
	 * Create a profile.
	 *
	 * @param array<string, mixed> $data Profile data.
	 * @return string|\WP_Error Created profile ID or error.
	 */
	public function create_profile( array $data ) {
		$pin = isset( $data['pin'] ) ? (string) $data['pin'] : '';
		if ( ! $this->is_valid_pin( $pin ) ) {
			return new \WP_Error( 'invalid_pin', __( 'PIN må være nøyaktig 4 siffer.', 'lilleprinsen-click-collect' ) );
		}

		$store      = $this->get_store();
		$profile_id = (string) $store['next_id'];
		$timestamp  = $this->now();
		$profile    = $this->sanitize_profile_data( $data );

		if ( '' === $profile['name'] ) {
			return new \WP_Error( 'missing_name', __( 'Navn er påkrevd.', 'lilleprinsen-click-collect' ) );
		}

		$profile['id']           = $profile_id;
		$profile['pin_hash']     = wp_hash_password( $pin );
		$profile['created_at']   = $timestamp;
		$profile['updated_at']   = $timestamp;

		$store['profiles'][ $profile_id ] = $profile;
		$store['next_id']                 = absint( $store['next_id'] ) + 1;

		update_option( self::OPTION_NAME, $store, false );

		return $profile_id;
	}

	/**
	 * Update a profile.
	 *
	 * @param string               $profile_id Profile ID.
	 * @param array<string, mixed> $data Profile data.
	 * @return bool|\WP_Error True on success or error.
	 */
	public function update_profile( string $profile_id, array $data ) {
		$store = $this->get_store();
		if ( ! isset( $store['profiles'][ $profile_id ] ) ) {
			return new \WP_Error( 'missing_profile', __( 'Ansattprofilen ble ikke funnet.', 'lilleprinsen-click-collect' ) );
		}

		$profile = array_merge( $store['profiles'][ $profile_id ], $this->sanitize_profile_data( $data ) );
		$pin     = isset( $data['pin'] ) ? trim( (string) $data['pin'] ) : '';

		if ( '' === $profile['name'] ) {
			return new \WP_Error( 'missing_name', __( 'Navn er påkrevd.', 'lilleprinsen-click-collect' ) );
		}

		if ( '' !== $pin ) {
			if ( ! $this->is_valid_pin( $pin ) ) {
				return new \WP_Error( 'invalid_pin', __( 'PIN må være nøyaktig 4 siffer.', 'lilleprinsen-click-collect' ) );
			}

			$profile['pin_hash'] = wp_hash_password( $pin );
		}

		$profile['id']         = $profile_id;
		$profile['created_at'] = (string) $store['profiles'][ $profile_id ]['created_at'];
		$profile['updated_at'] = $this->now();

		$store['profiles'][ $profile_id ] = $profile;
		update_option( self::OPTION_NAME, $store, false );

		return true;
	}

	/**
	 * Verify a PIN for an active profile with rate limiting.
	 *
	 * @param string $profile_id Profile ID.
	 * @param string $pin PIN candidate.
	 */
	public function verify_pin( string $profile_id, string $pin ): bool {
		if ( $this->is_rate_limited( $profile_id ) ) {
			return false;
		}

		if ( ! $this->is_valid_pin( $pin ) ) {
			$this->record_failed_attempt( $profile_id );
			return false;
		}

		$profiles = $this->get_profiles();
		$profile  = $profiles[ $profile_id ] ?? null;

		if ( ! is_array( $profile ) || empty( $profile['active'] ) || empty( $profile['pin_hash'] ) ) {
			$this->record_failed_attempt( $profile_id );
			return false;
		}

		if ( ! wp_check_password( $pin, (string) $profile['pin_hash'] ) ) {
			$this->record_failed_attempt( $profile_id );
			return false;
		}

		delete_transient( $this->get_rate_limit_key( $profile_id ) );

		return true;
	}

	/**
	 * Handle create form.
	 */
	public function handle_create_action(): void {
		$this->assert_admin_access();
		check_admin_referer( self::ACTION_CREATE );

		$result = $this->create_profile( $this->get_posted_profile_data() );
		$this->redirect_with_message( is_wp_error( $result ) ? 'error' : 'created' );
	}

	/**
	 * Handle update form.
	 */
	public function handle_update_action(): void {
		$this->assert_admin_access();
		check_admin_referer( self::ACTION_UPDATE );

		$profile_id = sanitize_key( (string) filter_input( INPUT_POST, 'profile_id', FILTER_UNSAFE_RAW ) );
		$result     = $this->update_profile( $profile_id, $this->get_posted_profile_data() );

		$this->redirect_with_message( is_wp_error( $result ) ? 'error' : 'updated' );
	}

	/**
	 * Return create action URL.
	 */
	public function get_create_action_url(): string {
		return admin_url( 'admin-post.php' );
	}

	/**
	 * Return update action URL.
	 */
	public function get_update_action_url(): string {
		return admin_url( 'admin-post.php' );
	}

	/**
	 * Return create action key.
	 */
	public function get_create_action(): string {
		return self::ACTION_CREATE;
	}

	/**
	 * Return update action key.
	 */
	public function get_update_action(): string {
		return self::ACTION_UPDATE;
	}

	/**
	 * Render profile action notices.
	 */
	public function render_admin_notice(): void {
		$page = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );
		if ( Settings::MENU_SLUG !== $page ) {
			return;
		}

		$message_key = sanitize_key( (string) filter_input( INPUT_GET, self::MESSAGE_QUERY, FILTER_UNSAFE_RAW ) );
		if ( '' === $message_key ) {
			return;
		}

		$messages = array(
			'created' => __( 'Ansattprofilen ble opprettet.', 'lilleprinsen-click-collect' ),
			'updated' => __( 'Ansattprofilen ble oppdatert.', 'lilleprinsen-click-collect' ),
			'error'   => __( 'Ansattprofilen kunne ikke lagres. Kontroller navn og at PIN er nøyaktig 4 siffer.', 'lilleprinsen-click-collect' ),
		);

		if ( ! isset( $messages[ $message_key ] ) ) {
			return;
		}

		$type = 'error' === $message_key ? 'notice-error' : 'notice-success';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $messages[ $message_key ] )
		);
	}

	/**
	 * Assert current user can manage profiles.
	 */
	private function assert_admin_access(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Du har ikke tilgang til å administrere ansattprofiler.', 'lilleprinsen-click-collect' ) );
		}
	}

	/**
	 * Return posted profile data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_posted_profile_data(): array {
		return array(
			'name'     => filter_input( INPUT_POST, 'name', FILTER_UNSAFE_RAW ),
			'pin'      => filter_input( INPUT_POST, 'pin', FILTER_UNSAFE_RAW ),
			'role'     => filter_input( INPUT_POST, 'role', FILTER_UNSAFE_RAW ),
			'active'   => filter_input( INPUT_POST, 'active', FILTER_UNSAFE_RAW ),
			'initials' => filter_input( INPUT_POST, 'initials', FILTER_UNSAFE_RAW ),
			'color'    => filter_input( INPUT_POST, 'color', FILTER_UNSAFE_RAW ),
		);
	}

	/**
	 * Sanitize profile data without touching PIN hash.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitize_profile_data( array $data ): array {
		$name     = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$role     = isset( $data['role'] ) ? sanitize_key( (string) $data['role'] ) : 'staff';
		$initials = isset( $data['initials'] ) ? sanitize_text_field( (string) $data['initials'] ) : '';
		$initials = strtr( $initials, array( 'æ' => 'Æ', 'ø' => 'Ø', 'å' => 'Å' ) );
		$initials = strtoupper( $initials );
		$initials = preg_replace( '/[^A-ZÆØÅ0-9]/u', '', $initials ) ?? '';
		$initials = substr( $initials, 0, 3 );
		$color    = isset( $data['color'] ) ? sanitize_hex_color( (string) $data['color'] ) : self::DEFAULT_COLOR;

		return array(
			'name'     => $name,
			'role'     => in_array( $role, self::PROFILE_ROLES, true ) ? $role : 'staff',
			'active'   => ! empty( $data['active'] ),
			'initials' => $initials,
			'color'    => $color ?: self::DEFAULT_COLOR,
		);
	}

	/**
	 * Normalize profiles loaded from the option.
	 *
	 * @param array<string, mixed> $profiles Raw profiles.
	 * @return array<string, array<string, mixed>>
	 */
	private function normalize_profiles( array $profiles ): array {
		$normalized = array();

		foreach ( $profiles as $profile_id => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$profile_id = sanitize_key( (string) $profile_id );
			if ( '' === $profile_id ) {
				continue;
			}

			$normalized[ $profile_id ] = array(
				'id'         => $profile_id,
				'name'       => sanitize_text_field( (string) ( $profile['name'] ?? '' ) ),
				'pin_hash'   => (string) ( $profile['pin_hash'] ?? '' ),
				'role'       => in_array( (string) ( $profile['role'] ?? '' ), self::PROFILE_ROLES, true ) ? (string) $profile['role'] : 'staff',
				'active'     => ! empty( $profile['active'] ),
				'initials'   => sanitize_text_field( (string) ( $profile['initials'] ?? '' ) ),
				'color'      => sanitize_hex_color( (string) ( $profile['color'] ?? self::DEFAULT_COLOR ) ) ?: self::DEFAULT_COLOR,
				'created_at' => sanitize_text_field( (string) ( $profile['created_at'] ?? '' ) ),
				'updated_at' => sanitize_text_field( (string) ( $profile['updated_at'] ?? '' ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Check for exactly four digits.
	 */
	private function is_valid_pin( string $pin ): bool {
		return 1 === preg_match( '/^\d{4}$/', $pin );
	}

	/**
	 * Return current UTC timestamp.
	 */
	private function now(): string {
		return gmdate( 'c' );
	}

	/**
	 * Redirect back to settings with a message.
	 */
	private function redirect_with_message( string $message_key ): void {
		wp_safe_redirect(
			add_query_arg(
				self::MESSAGE_QUERY,
				sanitize_key( $message_key ),
				admin_url( 'admin.php?page=' . Settings::MENU_SLUG )
			)
		);
		exit;
	}

	/**
	 * Check rate limit state.
	 */
	private function is_rate_limited( string $profile_id ): bool {
		$attempts = get_transient( $this->get_rate_limit_key( $profile_id ) );

		return is_array( $attempts ) && (int) ( $attempts['count'] ?? 0 ) >= self::MAX_ATTEMPTS;
	}

	/**
	 * Record a failed PIN attempt.
	 */
	private function record_failed_attempt( string $profile_id ): void {
		$key      = $this->get_rate_limit_key( $profile_id );
		$attempts = get_transient( $key );
		$attempts = is_array( $attempts ) ? $attempts : array( 'count' => 0 );

		$attempts['count'] = (int) $attempts['count'] + 1;
		$attempts['last']  = $this->now();

		set_transient( $key, $attempts, self::LOCK_SECONDS );
	}

	/**
	 * Return rate-limit key scoped to profile and remote address.
	 */
	private function get_rate_limit_key( string $profile_id ): string {
		$remote_addr = sanitize_text_field( (string) filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_UNSAFE_RAW ) );

		return 'lp_cc_pin_' . md5( $profile_id . '|' . $remote_addr );
	}
}
