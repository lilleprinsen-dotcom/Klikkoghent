# WP Overnight Packing Slip Integration

The store already uses "PDF Invoices & Packing Slips for WooCommerce" by WP Overnight.

This plugin must integrate with the existing packing slip system. Do not build a separate packing slip system and do not modify WP Overnight plugin files.

## Goals

- Show hentenummer on packing slips.
- Show QR code on packing slips when enabled.
- Keep output compact and print-friendly.
- Output on packing slips by default, not invoices.
- Fallback gracefully if QR cannot render.

## Integration Class

`class-wpo-packing-slip-integration.php`

Responsibilities:

- Detect if WP Overnight plugin is active.
- Register hooks only when integration is enabled.
- Use WP Overnight template/action hooks where possible.
- Read hentenummer and QR token from WooCommerce order metadata using CRUD APIs.
- Output compact markup for packing slips.
- Avoid invoices by default.

The current implementation is defensive for WP Overnight 3.4.0 and newer. It detects the integration with `wcpdf_get_document`, `WPO_WCPDF`, `WPO\WC\PDF_Invoices\Main`, or `WPO_WCPDF_VERSION`, then registers only documented template action hooks.

## Hooks Used

| Setting placement | WP Overnight hook | Markup shape |
| --- | --- | --- |
| `top` | `wpo_wcpdf_before_document` | Compact block before document content |
| `after_order_data` | `wpo_wcpdf_after_order_data` | `<tr><td colspan="2">...</td></tr>` because the hook is inside the order data table |
| `before_order_items` | `wpo_wcpdf_before_order_details` | Compact block before the item table |

No WP Overnight plugin files are modified, and no custom template is required. The documented hooks are sufficient for the current pickup block.

## Settings

- Enable WP Overnight integration
- Show hentenummer on packing slip
- Show QR on packing slip
- Placement:
  - top
  - after order data
  - before order items

## Output

```text
KLIKK OG HENT
Hentenummer: H1001
[QR code]
Scan for å åpne ordren i butikkterminalen
```

## QR Behavior

QR URL format:

```text
{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}
```

The QR token identifies the order for the terminal but does not grant access. Staff must still be logged in with a valid terminal session.

QR output is rendered as inline SVG with fixed dimensions and inline styles so the PDF renderer does not depend on admin CSS or external image assets.

## Fallbacks

- If WP Overnight is inactive, show no packing slip output and avoid fatal errors.
- If QR rendering fails, still show hentenummer.
- If order is not a pickup order, show nothing.
- If hentenummer is missing, show nothing unless an explicit repair/admin action is used.
- If the document type is not `packing-slip`, show nothing.
