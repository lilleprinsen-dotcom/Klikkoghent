# Lilleprinsen Click & Collect

Production-quality WordPress/WooCommerce plugin for Lilleprinsen's in-store click-and-collect terminal.

The plugin will provide a fast, calm, app-like staff terminal at `/butikkterminal` for handling pickup orders in a physical shop. It must feel like a modern in-store terminal, not like WordPress admin.

## Product Goal

Lilleprinsen sells through WooCommerce and has a physical store. Staff need a simple terminal for click-and-collect orders:

- log in with a staff profile and 4-digit PIN
- search by hentenummer, order number, customer name, phone, or email
- scan a QR code from the packing slip to open an order after login
- see payment state clearly
- start picking, mark ready, mark collected, or mark problem
- confirm in-store payment when required
- add internal notes
- see audit history

## What This Plugin Must Do

- Detect click-and-collect orders from configured shipping methods.
- Generate a unique pickup number, for example `H1001`.
- Store the pickup number on the WooCommerce order as `_lp_cc_pickup_number`.
- Store all order data through WooCommerce CRUD APIs and HPOS-compatible metadata APIs.
- Generate and store a secure QR token as `_lp_cc_qr_token`.
- Show pickup number and QR code on the WooCommerce admin order screen.
- Integrate with WP Overnight's "PDF Invoices & Packing Slips for WooCommerce" packing slips.
- Use the existing WooCommerce order status "Klar for henting" through admin mapping.
- Let admin classify payment methods as paid online or pay in store.
- Prevent collected state for pay-in-store orders until payment is confirmed, unless disabled in settings.
- Keep staff logged in for a configurable duration and support inactivity lock, logout, and profile switching.
- Log important actions with active staff profile and timestamp.

## What This Plugin Must Not Do

- No SMS integration. SMS is handled elsewhere in WooCommerce.
- No separate packing slip system. WP Overnight packing slips are the integration target.
- No duplicate "Klar for henting" WooCommerce status.
- No iOS app in v1.
- No inventory system, product barcode scanning, refunds, cash register integration, multi-store support, advanced stats, push notifications, offline mode, customer portal, or signature capture in v1.

## Intended Staff Workflow

1. Customer places a click-and-collect order.
2. The plugin detects pickup based on selected shipping methods.
3. The plugin generates a hentenummer, for example `H1001`.
4. The plugin stores hentenummer as WooCommerce order metadata.
5. The plugin generates a secure QR token.
6. WooCommerce admin order shows hentenummer and QR.
7. WP Overnight packing slip shows hentenummer and QR.
8. Staff opens `/butikkterminal`.
9. Staff selects profile and enters PIN.
10. Staff sees the order overview.
11. Staff searches or scans the order.
12. Staff starts picking.
13. Staff marks the order ready.
14. Existing WooCommerce/SMS systems may notify the customer outside this plugin.
15. Customer arrives and gives hentenummer.
16. Staff searches by hentenummer.
17. Staff checks payment state.
18. If pay in store, staff confirms payment received.
19. Staff marks the order collected.
20. The action is logged with staff profile and timestamp.

## Planned Technical Shape

- Plugin folder: `lilleprinsen-click-collect`
- PHP target: 8.1+
- WooCommerce compatible
- HPOS compatible
- Minimal dependencies
- Vanilla JavaScript terminal unless a build step becomes clearly necessary
- Norwegian UI text
- REST API namespace: `/wp-json/lp-cc/v1/`

## Local Installation

1. Copy or symlink `lilleprinsen-click-collect/` into `wp-content/plugins/`.
2. Make sure WooCommerce is installed and active.
3. In WordPress admin, activate `Lilleprinsen Click & Collect`.
4. Open WooCommerce -> Klikk og hent to confirm the settings page loads.

If WooCommerce is inactive, the plugin should show an admin notice and avoid loading plugin features.

## Current Implementation Status

The plugin currently includes the bootstrap, WooCommerce dependency guard, HPOS compatibility declaration, module structure, and the WooCommerce -> Klikk og hent settings page.

The settings page stores configuration for general behavior, pickup shipping methods, hentenummer, order status mapping, payment classification, terminal login, and WP Overnight packing slip options.

Click-and-collect order detection is implemented for configured shipping methods. When the plugin is enabled and automatic hentenummer generation is enabled, eligible orders receive HPOS-safe WooCommerce order metadata:

- `_lp_cc_is_pickup_order`
- `_lp_cc_pickup_number`
- `_lp_cc_qr_token`
- `_lp_cc_pickup_status`
- initial empty pickup/payment/note fields
- `_lp_cc_audit_log`

Existing hentenummer values are never overwritten. A WooCommerce admin order action can generate missing pickup metadata manually for an eligible order. Terminal UI and payment enforcement are planned future milestones.

WooCommerce admin order screens now include a compact Click & Collect panel for pickup orders and eligible pickup orders. The panel shows hentenummer, internal pickup status, QR token status without exposing the full token unless debug logging is enabled, a local QR code preview when enabled, payment classification, pickup timestamps, internal note, and audit history. The QR URL contains only pickup number and token: `{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}`. It also provides nonce-protected manual actions for generating missing hentedata, regenerating QR token, marking an eligible order as pickup, and clearing problem status. Order lists include a hentenummer column, and WooCommerce order search can search the pickup number metadata through WooCommerce's own search field filter.

WP Overnight packing slip integration is implemented through documented PDF template action hooks. When enabled, pickup orders can show a compact `KLIKK OG HENT` block with hentenummer and inline QR code on packing slips only. Invoices are unaffected.

## Documentation

- [Roadmap](docs/ROADMAP.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Metadata](docs/METADATA.md)
- [Settings](docs/SETTINGS.md)
- [REST API](docs/API.md)
- [Security](docs/SECURITY.md)
- [UI Guidelines](docs/UI-GUIDELINES.md)
- [WP Overnight Integration](docs/WPO-INTEGRATION.md)
- [QA](docs/QA.md)
- [Local Dev](docs/LOCAL-DEV.md)
- [Performance](docs/PERFORMANCE.md)
- [Release](docs/RELEASE.md)
