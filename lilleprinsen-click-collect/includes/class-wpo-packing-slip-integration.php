<?php
/**
 * WP Overnight packing slip integration.
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
 * Adds pickup data to WP Overnight packing slips without custom templates.
 */
final class WPO_Packing_Slip_Integration {
	private const DOCUMENT_TYPE_PACKING_SLIP = 'packing-slip';

	/**
	 * Order helper.
	 *
	 * @var Order_Helper
	 */
	private Order_Helper $order_helper;

	/**
	 * QR code helper.
	 *
	 * @var QR_Code
	 */
	private QR_Code $qr_code;

	/**
	 * Constructor.
	 *
	 * @param Order_Helper $order_helper Order helper.
	 * @param QR_Code      $qr_code QR code helper.
	 */
	public function __construct( Order_Helper $order_helper, QR_Code $qr_code ) {
		$this->order_helper = $order_helper;
		$this->qr_code      = $qr_code;
	}

	/**
	 * Register WP Overnight hooks when available and enabled.
	 */
	public function register_hooks(): void {
		if ( ! $this->is_enabled() || ! $this->is_wpo_active() ) {
			return;
		}

		$placement = (string) Settings::get( 'wpo_placement' );

		if ( 'top' === $placement ) {
			add_action( 'wpo_wcpdf_before_document', array( $this, 'render_block' ), 20, 2 );
			return;
		}

		if ( 'before_order_items' === $placement ) {
			add_action( 'wpo_wcpdf_before_order_details', array( $this, 'render_block' ), 20, 2 );
			return;
		}

		add_action( 'wpo_wcpdf_after_order_data', array( $this, 'render_order_data_row' ), 20, 2 );
	}

	/**
	 * Render a compact pickup block in normal document flow.
	 *
	 * @param string $document_type WP Overnight document type.
	 * @param mixed  $order Candidate order object.
	 */
	public function render_block( string $document_type, $order ): void {
		$order = $this->resolve_order( $order );
		if ( ! $order || ! $this->should_render_for_order( $document_type, $order ) ) {
			return;
		}

		echo $this->build_block_markup( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render a compact pickup block inside WP Overnight's order-data table.
	 *
	 * @param string $document_type WP Overnight document type.
	 * @param mixed  $order Candidate order object.
	 */
	public function render_order_data_row( string $document_type, $order ): void {
		$order = $this->resolve_order( $order );
		if ( ! $order || ! $this->should_render_for_order( $document_type, $order ) ) {
			return;
		}

		printf(
			'<tr class="lp-cc-wpo-row"><td colspan="2">%s</td></tr>',
			$this->build_block_markup( $order ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Check whether the integration is enabled in settings.
	 */
	private function is_enabled(): bool {
		return (bool) Settings::get( 'wpo_enabled' )
			&& ( (bool) Settings::get( 'wpo_show_pickup_number' ) || (bool) Settings::get( 'wpo_show_qr' ) );
	}

	/**
	 * Detect WP Overnight defensively across plugin versions.
	 */
	private function is_wpo_active(): bool {
		return function_exists( 'wcpdf_get_document' )
			|| class_exists( 'WPO_WCPDF' )
			|| class_exists( 'WPO\WC\PDF_Invoices\Main' )
			|| defined( 'WPO_WCPDF_VERSION' );
	}

	/**
	 * Resolve the hook argument to a WooCommerce order.
	 *
	 * @param mixed $order Candidate order object.
	 */
	private function resolve_order( $order ): ?WC_Order {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		if ( is_numeric( $order ) ) {
			return $this->order_helper->get_order( absint( $order ) );
		}

		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return $this->order_helper->get_order( absint( $order->get_id() ) );
		}

		return null;
	}

	/**
	 * Check document type and pickup metadata before rendering.
	 *
	 * @param string   $document_type WP Overnight document type.
	 * @param WC_Order $order WooCommerce order.
	 */
	private function should_render_for_order( string $document_type, WC_Order $order ): bool {
		$pickup_number = (string) $order->get_meta( Order_Helper::META_PICKUP_NUMBER, true );

		return self::DOCUMENT_TYPE_PACKING_SLIP === sanitize_key( $document_type )
			&& '' !== $pickup_number
			&& $this->order_helper->is_pickup_order( $order );
	}

	/**
	 * Build print-friendly pickup markup.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function build_block_markup( WC_Order $order ): string {
		$pickup_number      = (string) $order->get_meta( Order_Helper::META_PICKUP_NUMBER, true );
		$qr_token           = (string) $order->get_meta( Order_Helper::META_QR_TOKEN, true );
		$show_pickup_number = (bool) Settings::get( 'wpo_show_pickup_number' );
		$show_qr            = (bool) Settings::get( 'wpo_show_qr' );
		$qr_markup          = '';
		$qr_failed          = false;

		if ( $show_qr && '' !== $qr_token ) {
			try {
				$qr_markup = $this->qr_code->render_svg( $this->qr_code->get_terminal_url( $order ) );
			} catch ( \Throwable $exception ) {
				$qr_failed = true;
			}
		} elseif ( $show_qr ) {
			$qr_failed = true;
		}

		ob_start();
		?>
		<div class="lp-cc-wpo-block" style="border:2px solid #111827; padding:8px 10px; margin:8px 0 12px; color:#111827; background:#ffffff; font-family:Arial, Helvetica, sans-serif; page-break-inside:avoid;">
			<div style="font-size:11px; line-height:1.2; font-weight:bold; letter-spacing:0.04em; text-transform:uppercase; margin:0 0 4px;">
				<?php echo esc_html__( 'KLIKK OG HENT', 'lilleprinsen-click-collect' ); ?>
			</div>
			<?php if ( $show_pickup_number || $qr_failed ) : ?>
				<div style="font-size:15px; line-height:1.25; font-weight:bold; margin:0 0 6px;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: pickup number. */
							__( 'Hentenummer: %s', 'lilleprinsen-click-collect' ),
							$pickup_number
						)
					);
					?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $qr_markup ) : ?>
				<div style="width:86px; height:86px; margin:4px 0;">
					<?php echo $this->prepare_svg_for_pdf( $qr_markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div style="font-size:10px; line-height:1.3; margin-top:4px;">
					<?php echo esc_html__( 'Scan for å åpne ordren i butikkterminalen', 'lilleprinsen-click-collect' ); ?>
				</div>
			<?php elseif ( $show_qr && $qr_failed ) : ?>
				<div style="font-size:10px; line-height:1.3; margin-top:4px;">
					<?php echo esc_html__( 'QR-kode kunne ikke vises. Bruk hentenummeret over.', 'lilleprinsen-click-collect' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Add inline dimensions so PDF engines do not depend on admin CSS.
	 *
	 * @param string $svg SVG markup from QR helper.
	 */
	private function prepare_svg_for_pdf( string $svg ): string {
		return str_replace(
			'<svg class="lp-cc-qr-svg"',
			'<svg class="lp-cc-qr-svg" width="86" height="86" style="display:block; width:86px; height:86px;"',
			$svg
		);
	}
}
