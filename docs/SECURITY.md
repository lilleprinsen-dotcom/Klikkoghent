# Security Model

The plugin handles staff access to customer and order data. Security must be conservative from the first implementation.

## Principles

- Secure by default.
- Least privilege for every endpoint.
- No public order/customer data.
- No QR login bypass.
- Minimal data returned to the terminal.
- Escape output and sanitize input.
- Use nonces, capabilities, secure session tokens, and permission checks.

## Staff Authentication

- Staff log in with staff profile + 4-digit PIN when terminal auth is implemented.
- Staff profiles are managed by WooCommerce admins under WooCommerce -> Klikk og hent.
- PINs are hashed with WordPress password hashing.
- PINs must never be stored in plain text.
- Existing PINs must never be shown in admin UI or terminal UI.
- Failed PIN attempts are rate-limited by profile and remote address.
- Login errors should be generic.
- Active staff profile is attached to every important action.
- Browser storage must not contain sensitive profile data or PIN values. Later terminal sessions may store only an opaque session token.

## Terminal Sessions

- Configurable session duration, default 4 hours.
- Configurable inactivity lock, default 30 minutes.
- Logout must revoke/clear the session.
- Switch profile must require PIN when enabled.
- Locked screen must show current staff name but no order data.

## QR Tokens

- QR token must be secure random.
- QR URL includes pickup number and token only.
- QR token must not expose customer/order data.
- QR token must not bypass login.
- If scanned without a session, terminal asks for login and then opens the order.

## WooCommerce Data

- Use WooCommerce CRUD APIs.
- Maintain HPOS compatibility.
- Do not use direct SQL against order tables or postmeta.
- Do not use direct `get_post_meta`/`update_post_meta` for order data.

## REST API

- All terminal endpoints require valid terminal session.
- Admin endpoints require appropriate WordPress capabilities.
- Validate order is a pickup order before returning terminal data.
- Return minimal fields.
- Sanitize IDs, pickup numbers, tokens, filters, and notes.
- Use friendly Norwegian error messages without leaking sensitive details.

## Audit Log

Important actions must be logged with:

- timestamp
- staff profile ID/name if available
- action key
- Norwegian message

Audit log is stored on the order as `_lp_cc_audit_log`.

Terminal login, logout, switch-profile, and lock events should be added to audit logging when terminal sessions are implemented.

## External Services

- No unnecessary external services.
- No external QR code API.
- No SMS integration.
