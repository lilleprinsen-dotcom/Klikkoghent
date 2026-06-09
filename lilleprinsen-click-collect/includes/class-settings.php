<?php
/**
 * Settings page and option helpers.
 *
 * @package Lilleprinsen_Click_Collect
 */

declare( strict_types=1 );

namespace Lilleprinsen\ClickCollect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the WooCommerce settings page.
 */
final class Settings {
	public const MENU_SLUG   = 'lp-cc-settings';
	public const OPTION_NAME = 'lp_cc_settings';

	/**
	 * Internal pickup states used for status mapping.
	 */
	private const PICKUP_STATES = array(
		'new'       => 'Ny klikk-og-hent-ordre',
		'picking'   => 'Plukkes',
		'ready'     => 'Klar for henting',
		'collected' => 'Hentet',
		'problem'   => 'Problem',
	);

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the settings option.
	 */
	public function register_settings(): void {
		register_setting(
			'lp_cc_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Register the settings page under WooCommerce.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Klikk og hent', 'lilleprinsen-click-collect' ),
			__( 'Klikk og hent', 'lilleprinsen-click-collect' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Return default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'                      => false,
			'terminal_slug'                => 'butikkterminal',
			'debug_logging'                => false,
			'pickup_shipping_methods'      => array(),
			'auto_pickup_number'           => true,
			'pickup_number_prefix'         => 'H',
			'next_pickup_number'           => 1001,
			'min_number_length'            => 4,
			'status_mapping'               => array(
				'new'       => '',
				'picking'   => '',
				'ready'     => '',
				'collected' => '',
				'problem'   => '',
			),
			'paid_online_methods'          => array(),
			'pay_in_store_methods'         => array(),
			'require_payment_confirmation' => true,
			'session_duration_hours'       => 4,
			'inactivity_lock_minutes'      => 30,
			'require_pin_on_switch'        => true,
			'wpo_enabled'                  => false,
			'wpo_show_pickup_number'       => true,
			'wpo_show_qr'                  => true,
			'wpo_placement'                => 'after_order_data',
		);
	}

	/**
	 * Return all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_NAME, array() );

		return self::merge_with_defaults( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Return one setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public static function get( string $key ) {
		$settings = self::get_all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$defaults = self::get_defaults();

		$shipping_options = $this->get_shipping_method_options();
		$gateway_options  = $this->get_payment_gateway_options();
		$status_options   = $this->get_order_status_options();

		$terminal_slug = isset( $input['terminal_slug'] ) ? sanitize_title( (string) $input['terminal_slug'] ) : $defaults['terminal_slug'];
		if ( '' === $terminal_slug ) {
			$terminal_slug = $defaults['terminal_slug'];
		}

		$prefix = isset( $input['pickup_number_prefix'] ) ? sanitize_text_field( (string) $input['pickup_number_prefix'] ) : $defaults['pickup_number_prefix'];
		$prefix = preg_replace( '/[^A-Za-z0-9_-]/', '', $prefix ) ?? '';
		if ( '' === $prefix ) {
			$prefix = $defaults['pickup_number_prefix'];
		}

		$status_mapping = array();
		$raw_mapping    = isset( $input['status_mapping'] ) && is_array( $input['status_mapping'] ) ? $input['status_mapping'] : array();
		foreach ( array_keys( self::PICKUP_STATES ) as $state ) {
			$status = isset( $raw_mapping[ $state ] ) ? sanitize_key( (string) $raw_mapping[ $state ] ) : '';
			$status_mapping[ $state ] = isset( $status_options[ $status ] ) ? $status : '';
		}

		return array(
			'enabled'                      => ! empty( $input['enabled'] ),
			'terminal_slug'                => $terminal_slug,
			'debug_logging'                => ! empty( $input['debug_logging'] ),
			'pickup_shipping_methods'      => $this->sanitize_list( $input['pickup_shipping_methods'] ?? array(), array_keys( $shipping_options ) ),
			'auto_pickup_number'           => ! empty( $input['auto_pickup_number'] ),
			'pickup_number_prefix'         => $prefix,
			'next_pickup_number'           => max( 1, absint( $input['next_pickup_number'] ?? $defaults['next_pickup_number'] ) ),
			'min_number_length'            => max( 1, min( 12, absint( $input['min_number_length'] ?? $defaults['min_number_length'] ) ) ),
			'status_mapping'               => $status_mapping,
			'paid_online_methods'          => $this->sanitize_list( $input['paid_online_methods'] ?? array(), array_keys( $gateway_options ) ),
			'pay_in_store_methods'         => $this->sanitize_list( $input['pay_in_store_methods'] ?? array(), array_keys( $gateway_options ) ),
			'require_payment_confirmation' => ! empty( $input['require_payment_confirmation'] ),
			'session_duration_hours'       => max( 1, min( 24, absint( $input['session_duration_hours'] ?? $defaults['session_duration_hours'] ) ) ),
			'inactivity_lock_minutes'      => max( 1, min( 240, absint( $input['inactivity_lock_minutes'] ?? $defaults['inactivity_lock_minutes'] ) ) ),
			'require_pin_on_switch'        => ! empty( $input['require_pin_on_switch'] ),
			'wpo_enabled'                  => ! empty( $input['wpo_enabled'] ),
			'wpo_show_pickup_number'       => ! empty( $input['wpo_show_pickup_number'] ),
			'wpo_show_qr'                  => ! empty( $input['wpo_show_qr'] ),
			'wpo_placement'                => $this->sanitize_choice(
				$input['wpo_placement'] ?? $defaults['wpo_placement'],
				array_keys( $this->get_wpo_placement_options() ),
				$defaults['wpo_placement']
			),
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Du har ikke tilgang til denne siden.', 'lilleprinsen-click-collect' ) );
		}

		$settings         = self::get_all();
		$shipping_methods = $this->get_shipping_method_options();
		$order_statuses   = $this->get_order_status_options();
		$gateways         = $this->get_payment_gateway_options();
		$preview          = $this->get_pickup_number_preview( $settings );

		?>
		<div class="wrap lp-cc-admin-page">
			<h1><?php echo esc_html__( 'Klikk og hent', 'lilleprinsen-click-collect' ); ?></h1>
			<p class="lp-cc-page-intro">
				<?php echo esc_html__( 'Grunninnstillinger for butikkterminal, hentenummer, betaling og plukkliste.', 'lilleprinsen-click-collect' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'lp_cc_settings_group' ); ?>

				<div class="lp-cc-settings-grid">
					<?php
					$this->render_general_section( $settings );
					$this->render_detection_section( $settings, $shipping_methods );
					$this->render_pickup_number_section( $settings, $preview );
					$this->render_status_mapping_section( $settings, $order_statuses );
					$this->render_payment_section( $settings, $gateways );
					$this->render_login_section( $settings );
					$this->render_pdf_section( $settings );
					?>
				</div>

				<?php submit_button( __( 'Lagre innstillinger', 'lilleprinsen-click-collect' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_general_section( array $settings ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Generelt', 'lilleprinsen-click-collect' ); ?></h2>
			<?php $this->render_checkbox( 'enabled', __( 'Aktiver plugin', 'lilleprinsen-click-collect' ), (bool) $settings['enabled'] ); ?>
			<label class="lp-cc-field">
				<span><?php echo esc_html__( 'Terminal-URL', 'lilleprinsen-click-collect' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $this->field_name( 'terminal_slug' ) ); ?>" value="<?php echo esc_attr( (string) $settings['terminal_slug'] ); ?>" class="regular-text" />
				<small><?php echo esc_html__( 'Standard: butikkterminal. Terminalen kommer senere i egen milepæl.', 'lilleprinsen-click-collect' ); ?></small>
			</label>
			<?php $this->render_checkbox( 'debug_logging', __( 'Aktiver debug-logging', 'lilleprinsen-click-collect' ), (bool) $settings['debug_logging'] ); ?>
		</section>
		<?php
	}

	/**
	 * Render pickup detection section.
	 *
	 * @param array<string, mixed>  $settings Settings.
	 * @param array<string, string> $shipping_methods Shipping methods.
	 */
	private function render_detection_section( array $settings, array $shipping_methods ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Klikk-og-hent-deteksjon', 'lilleprinsen-click-collect' ); ?></h2>
			<p><?php echo esc_html__( 'Ordre som bruker valgte fraktmetoder får hentenummer når forretningslogikken aktiveres i en senere milepæl.', 'lilleprinsen-click-collect' ); ?></p>
			<?php $this->render_checkbox_group( 'pickup_shipping_methods', $shipping_methods, (array) $settings['pickup_shipping_methods'], __( 'Ingen fraktmetoder funnet.', 'lilleprinsen-click-collect' ) ); ?>
		</section>
		<?php
	}

	/**
	 * Render pickup number section.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $preview Preview value.
	 */
	private function render_pickup_number_section( array $settings, string $preview ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Hentenummer', 'lilleprinsen-click-collect' ); ?></h2>
			<?php $this->render_checkbox( 'auto_pickup_number', __( 'Generer hentenummer automatisk', 'lilleprinsen-click-collect' ), (bool) $settings['auto_pickup_number'] ); ?>
			<div class="lp-cc-field-row">
				<label class="lp-cc-field">
					<span><?php echo esc_html__( 'Prefiks', 'lilleprinsen-click-collect' ); ?></span>
					<input type="text" name="<?php echo esc_attr( $this->field_name( 'pickup_number_prefix' ) ); ?>" value="<?php echo esc_attr( (string) $settings['pickup_number_prefix'] ); ?>" class="small-text" />
				</label>
				<label class="lp-cc-field">
					<span><?php echo esc_html__( 'Neste nummer', 'lilleprinsen-click-collect' ); ?></span>
					<input type="number" min="1" name="<?php echo esc_attr( $this->field_name( 'next_pickup_number' ) ); ?>" value="<?php echo esc_attr( (string) $settings['next_pickup_number'] ); ?>" class="small-text" />
				</label>
				<label class="lp-cc-field">
					<span><?php echo esc_html__( 'Minimum lengde', 'lilleprinsen-click-collect' ); ?></span>
					<input type="number" min="1" max="12" name="<?php echo esc_attr( $this->field_name( 'min_number_length' ) ); ?>" value="<?php echo esc_attr( (string) $settings['min_number_length'] ); ?>" class="small-text" />
				</label>
			</div>
			<div class="lp-cc-preview">
				<span><?php echo esc_html__( 'Forhåndsvisning', 'lilleprinsen-click-collect' ); ?></span>
				<strong><?php echo esc_html( $preview ); ?></strong>
			</div>
		</section>
		<?php
	}

	/**
	 * Render status mapping section.
	 *
	 * @param array<string, mixed>  $settings Settings.
	 * @param array<string, string> $order_statuses WooCommerce statuses.
	 */
	private function render_status_mapping_section( array $settings, array $order_statuses ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Ordrestatus-mapping', 'lilleprinsen-click-collect' ); ?></h2>
			<p><?php echo esc_html__( 'Velg eksisterende WooCommerce-statuser. Ikke opprett en ny status for Klar for henting her.', 'lilleprinsen-click-collect' ); ?></p>
			<?php foreach ( self::PICKUP_STATES as $state => $label ) : ?>
				<label class="lp-cc-field">
					<span><?php echo esc_html( $label ); ?></span>
					<select name="<?php echo esc_attr( $this->field_name( 'status_mapping' ) . '[' . $state . ']' ); ?>">
						<option value=""><?php echo esc_html__( 'Ikke endre WooCommerce-status', 'lilleprinsen-click-collect' ); ?></option>
						<?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $settings['status_mapping'][ $state ] ?? '', $status_key ); ?>>
								<?php echo esc_html( $status_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * Render payment section.
	 *
	 * @param array<string, mixed>  $settings Settings.
	 * @param array<string, string> $gateways Payment gateways.
	 */
	private function render_payment_section( array $settings, array $gateways ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Betaling', 'lilleprinsen-click-collect' ); ?></h2>
			<div class="lp-cc-two-column">
				<div>
					<h3><?php echo esc_html__( 'Betalt på nett', 'lilleprinsen-click-collect' ); ?></h3>
					<?php $this->render_checkbox_group( 'paid_online_methods', $gateways, (array) $settings['paid_online_methods'], __( 'Ingen betalingsmetoder funnet.', 'lilleprinsen-click-collect' ) ); ?>
				</div>
				<div>
					<h3><?php echo esc_html__( 'Må betales i butikk', 'lilleprinsen-click-collect' ); ?></h3>
					<?php $this->render_checkbox_group( 'pay_in_store_methods', $gateways, (array) $settings['pay_in_store_methods'], __( 'Ingen betalingsmetoder funnet.', 'lilleprinsen-click-collect' ) ); ?>
				</div>
			</div>
			<?php $this->render_checkbox( 'require_payment_confirmation', __( 'Krev betalingsbekreftelse før ordre kan markeres som hentet', 'lilleprinsen-click-collect' ), (bool) $settings['require_payment_confirmation'] ); ?>
		</section>
		<?php
	}

	/**
	 * Render terminal login section.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_login_section( array $settings ): void {
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'Terminalinnlogging', 'lilleprinsen-click-collect' ); ?></h2>
			<div class="lp-cc-field-row">
				<label class="lp-cc-field">
					<span><?php echo esc_html__( 'Øktvarighet i timer', 'lilleprinsen-click-collect' ); ?></span>
					<input type="number" min="1" max="24" name="<?php echo esc_attr( $this->field_name( 'session_duration_hours' ) ); ?>" value="<?php echo esc_attr( (string) $settings['session_duration_hours'] ); ?>" class="small-text" />
				</label>
				<label class="lp-cc-field">
					<span><?php echo esc_html__( 'Inaktivitetslås i minutter', 'lilleprinsen-click-collect' ); ?></span>
					<input type="number" min="1" max="240" name="<?php echo esc_attr( $this->field_name( 'inactivity_lock_minutes' ) ); ?>" value="<?php echo esc_attr( (string) $settings['inactivity_lock_minutes'] ); ?>" class="small-text" />
				</label>
			</div>
			<?php $this->render_checkbox( 'require_pin_on_switch', __( 'Krev PIN ved bytte av profil', 'lilleprinsen-click-collect' ), (bool) $settings['require_pin_on_switch'] ); ?>
		</section>
		<?php
	}

	/**
	 * Render PDF/packing slip section.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_pdf_section( array $settings ): void {
		$placements = $this->get_wpo_placement_options();
		?>
		<section class="lp-cc-settings-card">
			<h2><?php echo esc_html__( 'PDF/plukkliste', 'lilleprinsen-click-collect' ); ?></h2>
			<?php $this->render_checkbox( 'wpo_enabled', __( 'Aktiver WP Overnight-integrasjon', 'lilleprinsen-click-collect' ), (bool) $settings['wpo_enabled'] ); ?>
			<?php $this->render_checkbox( 'wpo_show_pickup_number', __( 'Vis hentenummer på pakkelapp', 'lilleprinsen-click-collect' ), (bool) $settings['wpo_show_pickup_number'] ); ?>
			<?php $this->render_checkbox( 'wpo_show_qr', __( 'Vis QR-kode på pakkelapp', 'lilleprinsen-click-collect' ), (bool) $settings['wpo_show_qr'] ); ?>
			<label class="lp-cc-field">
				<span><?php echo esc_html__( 'Plassering', 'lilleprinsen-click-collect' ); ?></span>
				<select name="<?php echo esc_attr( $this->field_name( 'wpo_placement' ) ); ?>">
					<?php foreach ( $placements as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['wpo_placement'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</section>
		<?php
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param string $key Setting key.
	 * @param string $label Label.
	 * @param bool   $checked Checked state.
	 */
	private function render_checkbox( string $key, string $label, bool $checked ): void {
		?>
		<label class="lp-cc-checkbox">
			<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( $checked ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render a checkbox group.
	 *
	 * @param string                $key Setting key.
	 * @param array<string, string> $options Options.
	 * @param array<int, string>    $selected Selected values.
	 * @param string                $empty_message Message for empty options.
	 */
	private function render_checkbox_group( string $key, array $options, array $selected, string $empty_message ): void {
		if ( empty( $options ) ) {
			echo '<p class="description">' . esc_html( $empty_message ) . '</p>';
			return;
		}

		echo '<div class="lp-cc-checkbox-list">';
		foreach ( $options as $value => $label ) {
			?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $selected, true ) ); ?> />
				<span><?php echo esc_html( $label ); ?></span>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Return an option field name.
	 *
	 * @param string $key Setting key.
	 */
	private function field_name( string $key ): string {
		return self::OPTION_NAME . '[' . $key . ']';
	}

	/**
	 * Sanitize a list against allowed values.
	 *
	 * @param mixed              $raw Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @return array<int, string>
	 */
	private function sanitize_list( $raw, array $allowed ): array {
		$raw = is_array( $raw ) ? $raw : array();

		$values = array_map(
			static function ( $value ): string {
				return sanitize_text_field( (string) $value );
			},
			$raw
		);

		return array_values( array_intersect( array_unique( $values ), $allowed ) );
	}

	/**
	 * Sanitize a single choice.
	 *
	 * @param mixed              $raw Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string             $default Default value.
	 */
	private function sanitize_choice( $raw, array $allowed, string $default ): string {
		$value = sanitize_key( (string) $raw );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Merge saved settings with defaults.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @return array<string, mixed>
	 */
	private static function merge_with_defaults( array $settings ): array {
		$defaults = self::get_defaults();
		$settings = array_merge( $defaults, $settings );

		$settings['status_mapping'] = array_merge(
			$defaults['status_mapping'],
			is_array( $settings['status_mapping'] ) ? $settings['status_mapping'] : array()
		);

		return $settings;
	}

	/**
	 * Return available shipping method options.
	 *
	 * @return array<string, string>
	 */
	private function get_shipping_method_options(): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$options = array();
		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array(
			'id'        => 0,
			'zone_name' => __( 'Resten av verden', 'lilleprinsen-click-collect' ),
		);

		foreach ( $zones as $zone_data ) {
			$zone = \WC_Shipping_Zones::get_zone( (int) $zone_data['id'] );
			if ( ! $zone ) {
				continue;
			}

			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( ! is_object( $method ) || empty( $method->id ) ) {
					continue;
				}

				$instance_id = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
				$value       = sanitize_key( (string) $method->id ) . ':' . $instance_id;
				$title       = method_exists( $method, 'get_title' ) ? $method->get_title() : $method->title;
				$title       = $title ? $title : ( method_exists( $method, 'get_method_title' ) ? $method->get_method_title() : $method->id );

				$options[ $value ] = sprintf(
					'%1$s - %2$s',
					(string) $zone_data['zone_name'],
					(string) $title
				);
			}
		}

		return $options;
	}

	/**
	 * Return WooCommerce order status options.
	 *
	 * @return array<string, string>
	 */
	private function get_order_status_options(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		return wc_get_order_statuses();
	}

	/**
	 * Return payment gateway options.
	 *
	 * @return array<string, string>
	 */
	private function get_payment_gateway_options(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$options  = array();
		$gateways = WC()->payment_gateways()->payment_gateways();

		foreach ( $gateways as $gateway_id => $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}

			$title = method_exists( $gateway, 'get_title' ) ? $gateway->get_title() : ( $gateway->title ?? $gateway_id );
			$state = ! empty( $gateway->enabled ) && 'yes' === $gateway->enabled
				? __( 'aktiv', 'lilleprinsen-click-collect' )
				: __( 'inaktiv', 'lilleprinsen-click-collect' );

			$options[ sanitize_key( (string) $gateway_id ) ] = sprintf(
				'%1$s (%2$s)',
				(string) $title,
				$state
			);
		}

		return $options;
	}

	/**
	 * Return WP Overnight placement choices.
	 *
	 * @return array<string, string>
	 */
	private function get_wpo_placement_options(): array {
		return array(
			'top'                => __( 'Øverst', 'lilleprinsen-click-collect' ),
			'after_order_data'   => __( 'Etter ordredata', 'lilleprinsen-click-collect' ),
			'before_order_items' => __( 'Før varelinjer', 'lilleprinsen-click-collect' ),
		);
	}

	/**
	 * Build pickup number preview.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function get_pickup_number_preview( array $settings ): string {
		$number = str_pad(
			(string) absint( $settings['next_pickup_number'] ),
			absint( $settings['min_number_length'] ),
			'0',
			STR_PAD_LEFT
		);

		return (string) $settings['pickup_number_prefix'] . $number;
	}
}
