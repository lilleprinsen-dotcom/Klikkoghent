# QA Checklist

Use this checklist before merging implementation PRs.

## Product Boundaries

- No SMS integration added.
- No duplicate "Klar for henting" status added.
- No custom packing slip system added.
- No unnecessary external services added.
- No external QR code API added.
- No v1 out-of-scope features added without explicit request.

## WooCommerce And HPOS

- Order data uses WooCommerce CRUD APIs.
- Order metadata uses HPOS-compatible APIs.
- No direct SQL against posts/postmeta for orders.
- No direct `get_post_meta`/`update_post_meta` for order data.
- Hentenummer is stored as `_lp_cc_pickup_number`.

## Hentenummer And QR

- Hentenummer generated only for configured pickup orders.
- Existing hentenummer is never overwritten.
- Hentenummer is unique.
- QR token is secure random.
- QR URL includes only pickup number and QR token.
- QR does not expose private data.
- QR does not bypass staff login.
- QR code rendering does not call an external API.

## Pickup Detection Smoke Test

Use this once WooCommerce is available locally:

- Enable the plugin in WooCommerce -> Klikk og hent.
- Select one click-and-collect shipping method.
- Confirm automatic hentenummer generation is enabled.
- Place or create an order with the selected shipping method.
- Confirm the order metadata contains `_lp_cc_is_pickup_order = yes`, `_lp_cc_pickup_number`, `_lp_cc_qr_token`, `_lp_cc_pickup_status = new`, and `_lp_cc_audit_log`.
- Confirm the audit log includes `Hentenummer H1001 ble generert` or the matching configured number.
- Confirm the stored next number has incremented by one.
- Create an order with a non-selected shipping method and confirm no pickup metadata is created.
- Disable automatic hentenummer generation, create another eligible order, and confirm no hentenummer or QR token is generated automatically.
- On an eligible order missing hentedata, run the WooCommerce order action `Generer manglende hentedata` and confirm metadata is created.
- Re-run order events or save the order again and confirm the existing hentenummer is not overwritten.

## WooCommerce Admin Order UI Smoke Test

Use this once WooCommerce is available locally:

- Open an eligible pickup order in WooCommerce admin.
- Confirm the `Klikk og hent` panel appears on the order screen.
- Confirm the panel shows hentenummer, internal pickup status, QR token status, payment classification, timestamps, internal note, and audit log.
- Confirm the panel shows a QR preview when `Vis QR-kode i WooCommerce ordrevisning` is enabled and pickup number/token exist.
- Scan or inspect the QR code and confirm the URL format is `{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}`.
- Confirm the QR URL contains no customer name, email, phone, address, order total, or item data.
- Disable `Vis QR-kode i WooCommerce ordrevisning` and confirm the QR preview is hidden while the QR token status remains visible.
- Confirm the full QR token is hidden when debug logging is disabled.
- Enable debug logging and confirm the full QR token is visible only in the admin panel.
- Use `Generer manglende hentenummer` on an eligible order missing hentedata and confirm metadata is saved through WooCommerce CRUD.
- Use `Regenerer QR-token` and confirm `_lp_cc_qr_token` changes and audit log records `qr_token_regenerated`.
- Force an overly long terminal URL or renderer exception in a development environment and confirm the admin panel shows a short fallback message with hentenummer, not a fatal error.
- Use `Marker som klikk og hent` on an eligible unmarked pickup order and confirm `_lp_cc_is_pickup_order = yes`.
- Set `_lp_cc_pickup_status = problem`, reload the order, use `Fjern problemstatus`, and confirm status returns to `new`.
- Confirm each manual action requires a valid nonce and a user with WooCommerce order-management capability.
- Confirm non-pickup orders without configured pickup shipping do not show the panel.
- Confirm WooCommerce order lists include a `Hentenummer` column.
- Search orders by an existing hentenummer and confirm WooCommerce returns matching orders.
- Dedicated dropdown filtering by pickup state/payment is deferred until a later admin-list milestone to avoid risky custom HPOS queries.

## Payment

- Payment gateways can be classified as paid online or pay in store.
- Pay-in-store orders show `Må betales i butikk`.
- Payment confirmation can be recorded.
- Mark collected is blocked when required confirmation is missing.
- Audit log records payment confirmation.

## Staff Profiles And PINs

- Open WooCommerce -> Klikk og hent as a user with `manage_woocommerce`.
- Confirm the `Ansattprofiler` section is visible.
- Create a staff profile with name, role, active state, initials/color, and a 4-digit PIN.
- Confirm the profile is saved with ID, created timestamp, and updated timestamp.
- Confirm the existing PIN is not displayed after save.
- Inspect the `lp_cc_staff_profiles` option in development and confirm `pin_hash` is hashed and the plain PIN is not stored.
- Try creating or updating with a non-4-digit PIN and confirm it is rejected.
- Edit name, role, active state, initials, and color without entering PIN and confirm the existing PIN hash is preserved.
- Enter a new 4-digit PIN while editing and confirm the hash changes.
- Deactivate a profile and confirm it is no longer returned by `Staff_Profiles::get_active_profiles()`.
- Call `Staff_Profiles::verify_pin()` in development and confirm valid PIN succeeds while invalid PIN fails.
- Confirm repeated failed PIN attempts are rate-limited and do not reveal whether profile or PIN was wrong.
- Confirm users without `manage_woocommerce` cannot create or edit profiles.

## Terminal Sessions

- Terminal session UI is not implemented yet.
- Create at least two active staff profiles with known 4-digit PINs.
- `POST /wp-json/lp-cc/v1/auth/login` with valid `profile_id` and `pin`; confirm success, profile data, `expires_at`, `last_activity_at`, and an opaque `session_token`.
- Confirm the response does not include `pin_hash` or the plain PIN.
- Confirm an HttpOnly `lp_cc_terminal_session` cookie is set where headers can be inspected.
- Inspect `lp_cc_terminal_sessions` in development and confirm only a hashed token key is stored, not the raw session token.
- Call `GET /wp-json/lp-cc/v1/auth/me` with the cookie, `Authorization: Bearer {token}`, or `X-LP-CC-Session: {token}` and confirm current profile state is returned.
- Call login/unlock with an invalid PIN repeatedly and confirm failed attempts are rate-limited with generic errors.
- Set the session `last_activity_at` older than the configured inactivity lock duration, call `auth/me`, and confirm the session returns `locked: true`.
- Call `POST /wp-json/lp-cc/v1/auth/unlock` with the current profile PIN and confirm `locked: false`.
- Call `POST /wp-json/lp-cc/v1/auth/switch-profile` with another active profile and PIN when PIN-on-switch is enabled; confirm the profile changes and the session remains unlocked.
- Disable PIN-on-switch in settings and confirm switch-profile can switch without a PIN while still requiring a valid session token.
- Set `expires_at` in the past, call `auth/me`, and confirm a `401` response requiring full login.
- Call `POST /wp-json/lp-cc/v1/auth/logout` and confirm the session is revoked/removed and the cookie is cleared.
- Confirm terminal session events are logged through WooCommerce logger: login, logout, session expired, inactivity lock, and profile switched.
- Future order/action endpoints must require a valid unlocked session before returning private order data.

## Terminal UI

- UI is Norwegian.
- UI is mobile-first and touch-friendly.
- Order overview tabs work:
  - Nye
  - Plukkes
  - Klar
  - Hentet
  - Problem
- Payment filter works:
  - Alle
  - Betalt på nett
  - Må betales i butikk
- Detail screen shows customer and pickup data needed by staff.
- Locked screen does not expose order data.

## WP Overnight

- Integration detects plugin safely.
- With WP Overnight inactive, the plugin should load without fatal errors and show no packing slip output.
- With WP Overnight active, enable `Aktiver WP Overnight-integrasjon`.
- Create or open a click-and-collect order with `_lp_cc_pickup_number` and `_lp_cc_qr_token`.
- Generate a WP Overnight packing slip for the order.
- Confirm the packing slip shows `KLIKK OG HENT`.
- Confirm hentenummer appears when `Vis hentenummer på pakkelapp` is enabled.
- Confirm QR appears when `Vis QR-kode på pakkelapp` is enabled.
- Scan or inspect the QR and confirm it contains only pickup number and QR token.
- Disable `Vis QR-kode på pakkelapp` and confirm the hentenummer remains visible without QR.
- Change placement to `top`, `after_order_data`, and `before_order_items`, then confirm the block appears in the expected area.
- Generate an invoice for the same order and confirm the Click & Collect block does not appear.
- Generate a packing slip for a non-pickup order and confirm the block does not appear.
- Temporarily force QR rendering failure in development and confirm hentenummer still appears with fallback text.
- Packing slip output is compact and print-friendly.
- Output appears on packing slips by default, not invoices.
- No WP Overnight plugin files modified.

## Review Gates

- Run available automated checks.
- Run Codex review if available.
- Fix P0/P1 issues before merge.
- Merge only when checks are green or when no checks exist and that is confirmed.

## Skeleton Activation Smoke Test

Use this for the initial plugin skeleton:

- With WooCommerce active, activate `Lilleprinsen Click & Collect` from WordPress admin.
- Confirm no fatal error occurs during activation.
- Confirm WooCommerce -> Klikk og hent loads and shows the placeholder settings page.
- Deactivate the plugin and confirm no fatal error occurs.
- With WooCommerce inactive, activate the plugin and confirm it shows a WooCommerce-required admin notice while loading no plugin features.
- Confirm no order data is read or written during activation/deactivation.
- Confirm no duplicate WooCommerce order statuses are created.
