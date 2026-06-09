# Architecture

The plugin should be modular, conservative, and HPOS-compatible. Keep dependencies minimal and prefer native WordPress/WooCommerce APIs.

## Proposed Folder Shape

```text
lilleprinsen-click-collect/
  lilleprinsen-click-collect.php
  includes/
    class-plugin.php
    class-activator.php
    class-deactivator.php
    class-compatibility.php
    class-settings.php
    class-order-helper.php
    class-pickup-number.php
    class-qr-code.php
    class-payment-helper.php
    class-status-mapper.php
    class-staff-profiles.php
    class-terminal-session.php
    class-rest-api.php
    class-terminal-route.php
    class-admin-order-ui.php
    class-wpo-packing-slip-integration.php
    class-audit-log.php
    class-assets.php
  assets/
    css/
    js/
  templates/
  languages/
```

## Class Responsibilities

### `class-plugin.php`

Bootstraps the plugin, registers hooks, and wires services together.

### `class-activator.php`

Handles activation tasks such as default settings and capability setup. Must not create duplicate WooCommerce statuses.

### `class-deactivator.php`

Handles deactivation cleanup that is safe and reversible. Must not delete order metadata by default.

### `class-compatibility.php`

Checks PHP, WordPress, WooCommerce, and HPOS compatibility. Handles graceful admin notices.

### `class-settings.php`

Owns settings registration, sanitization, settings UI, and option reads.

### `class-order-helper.php`

Central wrapper for WooCommerce order access. Use `wc_get_order()` and order CRUD methods. Do not query order postmeta directly.

### `class-pickup-number.php`

Generates unique hentenummer values, respects prefix/next number/minimum length, and never overwrites an existing number.

### `class-qr-code.php`

Generates secure QR tokens, builds terminal QR URLs, and renders local SVG QR markup. No external QR code API.

Current implementation uses a small dependency-free renderer behind the `QR_Code` service. This keeps the first plugin package lightweight while preserving a single class boundary that can later be swapped for a responsibly vendored PHP QR library if packing slip/PDF rendering needs a broader feature set.

### `class-payment-helper.php`

Classifies orders as paid online, pay in store, or needs checking based on gateway settings and order payment state.

### `class-status-mapper.php`

Maps internal pickup states to existing WooCommerce statuses. Never creates duplicate statuses.

### `class-staff-profiles.php`

Creates, edits, deactivates, and validates staff profiles stored in the `lp_cc_staff_profiles` option. Hashes 4-digit PINs with WordPress password hashing, never returns or renders existing PINs, and rate-limits failed PIN verification attempts.

### `class-terminal-session.php`

Creates and validates terminal sessions, handles logout, profile switch, unlock, expiry, and inactivity lock.

### `class-rest-api.php`

Registers `/wp-json/lp-cc/v1/` endpoints and validates terminal sessions/permissions.

### `class-terminal-route.php`

Registers `/butikkterminal` or configured slug and renders the app shell.

### `class-admin-order-ui.php`

Adds Click & Collect panel to WooCommerce order admin with hentenummer, QR, metadata, audit log, and safe manual actions.

### `class-wpo-packing-slip-integration.php`

Integrates with WP Overnight packing slips through hooks. Does not modify external plugin files.

### `class-audit-log.php`

Appends structured audit log entries to `_lp_cc_audit_log`.

### `class-assets.php`

Registers/enqueues terminal and admin assets only where needed.

## Data Flow

1. WooCommerce order is created or updated.
2. Order helper determines whether the configured shipping method is click-and-collect.
3. Pickup number helper generates hentenummer if missing.
4. QR helper generates QR token if missing.
5. Metadata is stored through WooCommerce CRUD APIs.
6. Admin order UI asks the QR helper for `{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}` and renders a local SVG preview when enabled.
7. Admin creates active staff profiles with hashed PINs for future terminal login.
8. Staff terminal reads minimal order lists through REST API.
9. Staff actions update internal pickup state, mapped WooCommerce status when configured, timestamps, and audit log.
10. Packing slip integration outputs pickup block using order metadata.

## Architecture Rules

- Keep order access centralized.
- Keep terminal session logic separate from WordPress user auth.
- Keep UI data minimal and purpose-built.
- Keep WP Overnight integration isolated.
- Keep QR generation local.
- Keep all user-facing strings in Norwegian.
- Prefer small PRs and focused classes.
