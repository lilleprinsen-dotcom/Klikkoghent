# WP Overnight Packing Slip Integration

The store already uses "PDF Invoices & Packing Slips for WooCommerce" by WP Overnight.

This plugin must integrate with the existing packing slip system. Do not build a separate packing slip system and do not modify WP Overnight plugin files.

## Goals

- Show hentenummer on packing slips.
- Show QR code on packing slips when enabled.
- Keep output compact and print-friendly.
- Output on packing slips by default, not invoices.
- Fallback gracefully if QR cannot render.

## Planned Class

`class-wpo-packing-slip-integration.php`

Responsibilities:

- Detect if WP Overnight plugin is active.
- Register hooks only when integration is enabled.
- Use WP Overnight template/action hooks where possible.
- Read hentenummer and QR token from WooCommerce order metadata using CRUD APIs.
- Output compact markup for packing slips.

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

## Fallbacks

- If WP Overnight is inactive, show no packing slip output and avoid fatal errors.
- If QR rendering fails, still show hentenummer.
- If order is not a pickup order, show nothing.
- If hentenummer is missing, show nothing unless an explicit repair/admin action is used.
