<?php
/**
 * Secure terminal session storage for staff profiles.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages server-side terminal sessions backed by hashed opaque tokens.
 */
final class Terminal_Session {
	public const OPTION_NAME = 'lp_cc_terminal_sessions';
	public const COOKIE_NAME = 'lp_cc_terminal_session';

	private Staff_Profiles $staff_profiles;
	private Audit_Log $audit_log;

	/**
	 * Constructor.
	 *
	 * @param Staff_Profiles $staff_profiles Staff profile helper.
	 * @param Audit_Log      $audit_log Audit/log helper.
	 */
	public function __construct( Staff_Profiles $staff_profiles, Audit_Log $audit_log ) {
		$this->staff_profiles = $staff_profiles;
		$this->audit_log      = $audit_log;
	}

	/**
	 * Create and persist a new terminal session.
	 *
	 * @param string $profile_id Staff profile ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_session( string $profile_id ) {
		$profile = $this->staff_profiles->get_profile( $profile_id );
		if ( ! $profile || empty( $profile['active'] ) ) {
			return new \WP_Error( 'lp_cc_invalid_profile', __( 'Kunne ikke logge inn. Kontroller profil og PIN.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$token      = $this->generate_token();
		$token_hash = $this->hash_token( $token );
		$created_at = time();
		$expires_at = $created_at + $this->get_session_duration_seconds();
		$sessions   = $this->get_sessions();

		$sessions[ $token_hash ] = array(
			'token_hash'       => $token_hash,
			'profile_id'       => $profile_id,
			'created_at'       => $created_at,
			'expires_at'       => $expires_at,
			'last_activity_at' => $created_at,
			'locked'           => false,
			'revoked'          => false,
		);

		$this->save_sessions( $sessions );
		$this->set_cookie( $token, $expires_at );
		$this->log_event( 'staff_logged_in', sprintf( 'Ansatt %s logget inn i butikkterminalen.', $this->staff_profiles->get_profile_display_name( $profile_id ) ), $profile_id );

		$session                  = $sessions[ $token_hash ];
		$session['session_token'] = $token;

		return $session;
	}

	/**
	 * Return the current session from a REST request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param bool             $allow_locked Whether locked sessions are accepted.
	 * @param bool             $touch Whether to update activity for unlocked sessions.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_current_session( \WP_REST_Request $request, bool $allow_locked = false, bool $touch = true ) {
		$token = $this->get_token_from_request( $request );
		if ( '' === $token ) {
			return new \WP_Error( 'lp_cc_missing_session', __( 'Du må logge inn på nytt.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		return $this->get_session_by_token( $token, $allow_locked, $touch );
	}

	/**
	 * Revoke the current session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public function logout( \WP_REST_Request $request ) {
		$token = $this->get_token_from_request( $request );
		if ( '' === $token ) {
			$this->clear_cookie();
			return true;
		}

		$token_hash = $this->hash_token( $token );
		$sessions   = $this->get_sessions();

		if ( isset( $sessions[ $token_hash ] ) ) {
			$profile_id = (string) $sessions[ $token_hash ]['profile_id'];
			unset( $sessions[ $token_hash ] );
			$this->save_sessions( $sessions );
			$this->log_event( 'staff_logged_out', sprintf( 'Ansatt %s logget ut av butikkterminalen.', $this->staff_profiles->get_profile_display_name( $profile_id ) ), $profile_id );
		}

		$this->clear_cookie();

		return true;
	}

	/**
	 * Unlock the current session using the current profile PIN.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $pin PIN candidate.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function unlock( \WP_REST_Request $request, string $pin ) {
		$session = $this->get_current_session( $request, true, false );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$profile_id = (string) $session['profile_id'];
		if ( ! $this->staff_profiles->verify_pin( $profile_id, $pin ) ) {
			return new \WP_Error( 'lp_cc_invalid_pin', __( 'Kunne ikke låse opp. Kontroller PIN.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$token_hash = (string) $session['token_hash'];
		$sessions   = $this->get_sessions();
		if ( ! isset( $sessions[ $token_hash ] ) ) {
			return new \WP_Error( 'lp_cc_missing_session', __( 'Du må logge inn på nytt.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$sessions[ $token_hash ]['locked']           = false;
		$sessions[ $token_hash ]['last_activity_at'] = time();
		$this->save_sessions( $sessions );

		return $sessions[ $token_hash ];
	}

	/**
	 * Switch the active staff profile for the current session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $profile_id New profile ID.
	 * @param string           $pin PIN candidate.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function switch_profile( \WP_REST_Request $request, string $profile_id, string $pin = '' ) {
		$session = $this->get_current_session( $request, true, false );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$profile = $this->staff_profiles->get_profile( $profile_id );
		if ( ! $profile || empty( $profile['active'] ) ) {
			return new \WP_Error( 'lp_cc_invalid_profile', __( 'Kunne ikke bytte profil. Kontroller profil og PIN.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		if ( (bool) Settings::get( 'require_pin_on_switch' ) && ! $this->staff_profiles->verify_pin( $profile_id, $pin ) ) {
			return new \WP_Error( 'lp_cc_invalid_pin', __( 'Kunne ikke bytte profil. Kontroller profil og PIN.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$token_hash  = (string) $session['token_hash'];
		$previous_id = (string) $session['profile_id'];
		$sessions    = $this->get_sessions();
		if ( ! isset( $sessions[ $token_hash ] ) ) {
			return new \WP_Error( 'lp_cc_missing_session', __( 'Du må logge inn på nytt.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$sessions[ $token_hash ]['profile_id']       = $profile_id;
		$sessions[ $token_hash ]['last_activity_at'] = time();
		$sessions[ $token_hash ]['locked']           = false;
		$sessions[ $token_hash ]['switched_at']      = time();
		$sessions[ $token_hash ]['previous_profile_id'] = $previous_id;
		$this->save_sessions( $sessions );

		$this->log_event(
			'profile_switched',
			sprintf(
				'Butikkterminalen byttet profil fra %1$s til %2$s.',
				$this->staff_profiles->get_profile_display_name( $previous_id ),
				$this->staff_profiles->get_profile_display_name( $profile_id )
			),
			$profile_id
		);

		return $sessions[ $token_hash ];
	}

	/**
	 * Format a session for REST responses without exposing token hashes.
	 *
	 * @param array<string, mixed> $session Session data.
	 * @param bool                 $include_token Whether to include the raw token returned at login.
	 * @return array<string, mixed>
	 */
	public function prepare_session_response( array $session, bool $include_token = false ): array {
		$profile = $this->staff_profiles->get_profile( (string) $session['profile_id'] );
		$data    = array(
			'authenticated'    => true,
			'locked'           => ! empty( $session['locked'] ),
			'created_at'       => $this->format_timestamp( (int) $session['created_at'] ),
			'expires_at'       => $this->format_timestamp( (int) $session['expires_at'] ),
			'last_activity_at' => $this->format_timestamp( (int) $session['last_activity_at'] ),
			'profile'          => $profile ? $this->prepare_profile_response( $profile ) : null,
		);

		if ( $include_token && isset( $session['session_token'] ) ) {
			$data['session_token'] = (string) $session['session_token'];
		}

		return $data;
	}

	/**
	 * Return all stored sessions, removing revoked or malformed records.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_sessions(): array {
		$sessions = get_option( self::OPTION_NAME, array() );
		$sessions = is_array( $sessions ) ? $sessions : array();
		$clean    = array();

		foreach ( $sessions as $token_hash => $session ) {
			if ( ! is_array( $session ) ) {
				continue;
			}

			$token_hash = sanitize_text_field( (string) $token_hash );
			if ( '' === $token_hash || ! empty( $session['revoked'] ) ) {
				continue;
			}

			$clean[ $token_hash ] = array(
				'token_hash'          => $token_hash,
				'profile_id'          => sanitize_key( (string) ( $session['profile_id'] ?? '' ) ),
				'created_at'          => absint( $session['created_at'] ?? 0 ),
				'expires_at'          => absint( $session['expires_at'] ?? 0 ),
				'last_activity_at'    => absint( $session['last_activity_at'] ?? 0 ),
				'locked'              => ! empty( $session['locked'] ),
				'revoked'             => false,
				'switched_at'         => absint( $session['switched_at'] ?? 0 ),
				'previous_profile_id' => sanitize_key( (string) ( $session['previous_profile_id'] ?? '' ) ),
			);
		}

		if ( count( $clean ) !== count( $sessions ) ) {
			$this->save_sessions( $clean );
		}

		return $clean;
	}

	/**
	 * Save sessions without autoloading the option.
	 *
	 * @param array<string, array<string, mixed>> $sessions Sessions.
	 */
	private function save_sessions( array $sessions ): void {
		update_option( self::OPTION_NAME, $sessions, false );
	}

	/**
	 * Return a session by raw token and enforce expiry/inactivity rules.
	 *
	 * @param string $token Raw session token.
	 * @param bool   $allow_locked Whether locked sessions are accepted.
	 * @param bool   $touch Whether to update activity.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function get_session_by_token( string $token, bool $allow_locked, bool $touch ) {
		$token_hash = $this->hash_token( $token );
		$sessions   = $this->get_sessions();
		$session    = $sessions[ $token_hash ] ?? null;

		if ( ! is_array( $session ) ) {
			$this->clear_cookie();
			return new \WP_Error( 'lp_cc_missing_session', __( 'Du må logge inn på nytt.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$now = time();
		if ( (int) $session['expires_at'] <= $now ) {
			unset( $sessions[ $token_hash ] );
			$this->save_sessions( $sessions );
			$this->clear_cookie();
			$this->log_event( 'session_expired', 'Butikkterminal-økten utløp.', (string) $session['profile_id'] );
			return new \WP_Error( 'lp_cc_session_expired', __( 'Økten er utløpt. Logg inn på nytt.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		if ( ! empty( $session['locked'] ) ) {
			if ( $allow_locked ) {
				return $session;
			}

			return new \WP_Error( 'lp_cc_session_locked', __( 'Terminalen er låst. Tast PIN for å fortsette.', 'lilleprinsen-click-collect' ), array( 'status' => 423 ) );
		}

		if ( (int) $session['last_activity_at'] + $this->get_inactivity_seconds() <= $now ) {
			$sessions[ $token_hash ]['locked'] = true;
			$this->save_sessions( $sessions );
			$this->log_event( 'session_locked', 'Butikkterminalen ble låst på grunn av inaktivitet.', (string) $session['profile_id'] );

			if ( $allow_locked ) {
				return $sessions[ $token_hash ];
			}

			return new \WP_Error( 'lp_cc_session_locked', __( 'Terminalen er låst. Tast PIN for å fortsette.', 'lilleprinsen-click-collect' ), array( 'status' => 423 ) );
		}

		if ( $touch ) {
			$sessions[ $token_hash ]['last_activity_at'] = $now;
			$this->save_sessions( $sessions );
			$session = $sessions[ $token_hash ];
		}

		return $session;
	}

	/**
	 * Extract token from header, bearer auth, or HttpOnly cookie.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	private function get_token_from_request( \WP_REST_Request $request ): string {
		$token = (string) $request->get_header( 'x_lp_cc_session' );

		if ( '' === $token ) {
			$authorization = (string) $request->get_header( 'authorization' );
			if ( 1 === preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
				$token = (string) $matches[1];
			}
		}

		if ( '' === $token ) {
			$token = (string) filter_input( INPUT_COOKIE, self::COOKIE_NAME, FILTER_UNSAFE_RAW );
		}

		return $this->sanitize_token( $token );
	}

	/**
	 * Generate a secure opaque token.
	 */
	private function generate_token(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $exception ) {
			return wp_generate_password( 64, false, false );
		}
	}

	/**
	 * Return a deterministic server-side token hash.
	 */
	private function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Sanitize a token value.
	 */
	private function sanitize_token( string $token ): string {
		$token = sanitize_text_field( $token );

		return 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ? $token : '';
	}

	/**
	 * Set the secure terminal session cookie.
	 */
	private function set_cookie( string $token, int $expires_at ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie( self::COOKIE_NAME, $token, $this->get_cookie_options( $expires_at ) );
	}

	/**
	 * Clear the terminal session cookie.
	 */
	private function clear_cookie(): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie( self::COOKIE_NAME, '', $this->get_cookie_options( time() - HOUR_IN_SECONDS ) );
	}

	/**
	 * Build secure cookie options.
	 *
	 * @return array<string, mixed>
	 */
	private function get_cookie_options( int $expires_at ): array {
		$options = array(
			'expires'  => $expires_at,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
			$options['domain'] = COOKIE_DOMAIN;
		}

		return $options;
	}

	/**
	 * Return configured session duration.
	 */
	private function get_session_duration_seconds(): int {
		return max( 1, absint( Settings::get( 'session_duration_hours' ) ) ) * HOUR_IN_SECONDS;
	}

	/**
	 * Return configured inactivity duration.
	 */
	private function get_inactivity_seconds(): int {
		return max( 1, absint( Settings::get( 'inactivity_lock_minutes' ) ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Format a Unix timestamp as UTC ISO-8601.
	 */
	private function format_timestamp( int $timestamp ): string {
		return gmdate( 'c', $timestamp );
	}

	/**
	 * Prepare public profile data for terminal responses.
	 *
	 * @param array<string, mixed> $profile Staff profile.
	 * @return array<string, mixed>
	 */
	private function prepare_profile_response( array $profile ): array {
		return array(
			'id'       => (string) $profile['id'],
			'name'     => (string) $profile['name'],
			'role'     => (string) $profile['role'],
			'initials' => (string) $profile['initials'],
			'color'    => (string) $profile['color'],
		);
	}

	/**
	 * Log a terminal session event.
	 */
	private function log_event( string $action, string $message, string $profile_id = '' ): void {
		$this->audit_log->log(
			'info',
			$message,
			array(
				'action'     => sanitize_key( $action ),
				'profile_id' => sanitize_key( $profile_id ),
			)
		);
	}
}
