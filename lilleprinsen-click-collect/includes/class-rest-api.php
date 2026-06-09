<?php
/**
 * REST API endpoints for the staff terminal.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers terminal REST endpoints.
 */
final class REST_API {
	private const NAMESPACE = 'lp-cc/v1';

	private Staff_Profiles $staff_profiles;
	private Terminal_Session $terminal_session;

	/**
	 * Constructor.
	 *
	 * @param Staff_Profiles   $staff_profiles Staff profile helper.
	 * @param Terminal_Session $terminal_session Terminal session helper.
	 */
	public function __construct( Staff_Profiles $staff_profiles, Terminal_Session $terminal_session ) {
		$this->staff_profiles   = $staff_profiles;
		$this->terminal_session = $terminal_session;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_login' ),
				'permission_callback' => '__return_true',
				'args'                => $this->pin_auth_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/logout',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_logout' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/switch-profile',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_switch_profile' ),
				'permission_callback' => '__return_true',
				'args'                => $this->pin_auth_args( false ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/unlock',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_unlock' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'pin' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/me',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_me' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle profile/PIN login.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_login( \WP_REST_Request $request ) {
		$profile_id = sanitize_key( (string) $request->get_param( 'profile_id' ) );
		$pin        = sanitize_text_field( (string) $request->get_param( 'pin' ) );

		if ( ! $this->staff_profiles->verify_pin( $profile_id, $pin ) ) {
			return new \WP_Error( 'lp_cc_invalid_login', __( 'Kunne ikke logge inn. Kontroller profil og PIN.', 'lilleprinsen-click-collect' ), array( 'status' => 401 ) );
		}

		$session = $this->terminal_session->create_session( $profile_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Du er logget inn.', 'lilleprinsen-click-collect' ),
				'session' => $this->terminal_session->prepare_session_response( $session, true ),
			)
		);
	}

	/**
	 * Handle logout/revocation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_logout( \WP_REST_Request $request ) {
		$result = $this->terminal_session->logout( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Du er logget ut.', 'lilleprinsen-click-collect' ),
			)
		);
	}

	/**
	 * Handle profile switching.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_switch_profile( \WP_REST_Request $request ) {
		$profile_id = sanitize_key( (string) $request->get_param( 'profile_id' ) );
		$pin        = sanitize_text_field( (string) $request->get_param( 'pin' ) );
		$session    = $this->terminal_session->switch_profile( $request, $profile_id, $pin );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Profilen er byttet.', 'lilleprinsen-click-collect' ),
				'session' => $this->terminal_session->prepare_session_response( $session ),
			)
		);
	}

	/**
	 * Handle inactivity unlock.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_unlock( \WP_REST_Request $request ) {
		$pin     = sanitize_text_field( (string) $request->get_param( 'pin' ) );
		$session = $this->terminal_session->unlock( $request, $pin );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Terminalen er låst opp.', 'lilleprinsen-click-collect' ),
				'session' => $this->terminal_session->prepare_session_response( $session ),
			)
		);
	}

	/**
	 * Return current auth state.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_me( \WP_REST_Request $request ) {
		$session = $this->terminal_session->get_current_session( $request, true, true );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'session' => $this->terminal_session->prepare_session_response( $session ),
			)
		);
	}

	/**
	 * Return shared profile/PIN args.
	 *
	 * @param bool $pin_required Whether PIN is always required.
	 * @return array<string, array<string, mixed>>
	 */
	private function pin_auth_args( bool $pin_required = true ): array {
		return array(
			'profile_id' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			),
			'pin'        => array(
				'type'              => 'string',
				'required'          => $pin_required,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
