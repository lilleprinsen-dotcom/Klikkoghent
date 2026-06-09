# AGENTS.md

Persistent project instructions for Codex and future contributors.

## Non-Negotiable Workflow Rule

For every time you edit code or documentation in this repository, make a new pull request instead of updating an old one.

## Product Memory

Plugin name: Lilleprinsen Click & Collect

Plugin folder: `lilleprinsen-click-collect`

Primary goal: create a beautiful, fast, simple, and reliable click-and-collect staff terminal for a WooCommerce store with a physical shop.

The terminal must feel like a modern in-store app/kasse-terminal. It must not feel like WordPress admin.

Store context:

- Store: Lilleprinsen
- Platform: WordPress + WooCommerce
- Store has a physical shop and uses click and collect
- Staff need a simple terminal for handling pickup orders
- SMS is already handled elsewhere in WooCommerce
- Packing slips are already handled by "PDF Invoices & Packing Slips for WooCommerce" by WP Overnight
- The shop already has an order status named "Klar for henting"
- Payment is mainly online, with the exception "Betal med kort i butikk"

## Business Rules

- Do not build SMS integration.
- Do not build a separate packing slip system.
- Do not create a duplicate "Klar for henting" WooCommerce status.
- Admin must map existing WooCommerce statuses to internal pickup states.
- Admin must classify WooCommerce payment gateways as either paid online or pay in store.
- "Betal med kort i butikk" must be configurable as pay in store.
- Pay-in-store orders must clearly show "Må betales i butikk" in the terminal.
- Pay-in-store orders cannot be marked collected until payment is confirmed in the terminal, unless the setting is disabled.
- Staff must log into the terminal with a staff profile and 4-digit PIN.
- PINs must be stored hashed, never in plain text.
- Terminal sessions must support configurable stay-logged-in duration, inactivity lock, logout, and profile switching.
- All important actions must be logged with active staff profile and timestamp.

## Required Staff Flow

1. Customer places click-and-collect order.
2. Plugin detects pickup order based on selected shipping methods.
3. Plugin generates hentenummer, for example `H1001`.
4. Plugin stores hentenummer as order metadata.
5. Plugin generates secure QR token.
6. WooCommerce admin order shows hentenummer and QR.
7. WP Overnight packing slip shows hentenummer and QR.
8. Staff opens `/butikkterminal`.
9. Staff selects profile and enters PIN.
10. Staff sees order overview.
11. Staff searches or scans order.
12. Staff starts picking.
13. Staff marks order ready.
14. Existing WooCommerce/SMS system may handle customer SMS outside this plugin.
15. Customer comes to store and gives hentenummer.
16. Staff searches order by hentenummer.
17. Staff checks payment state.
18. If pay in store, staff confirms payment received.
19. Staff marks order collected.
20. Action is logged with staff profile and timestamp.

## Out Of Scope For V1

- SMS integration
- Own iOS app
- Separate packing slip system
- New duplicate "Klar for henting" WooCommerce status
- Advanced inventory system
- Barcode scanning of products
- Automatic refunds
- Cash register/kassasystem integration
- Multi-store support
- Advanced statistics/dashboard
- Push notifications
- Offline mode
- Customer-facing pickup portal
- Signature capture unless explicitly added later

## Technical Standards

- Build a WordPress plugin compatible with WooCommerce.
- Target PHP 8.1+.
- Keep dependencies minimal.
- Prefer vanilla JavaScript for the terminal unless a build step is clearly justified.
- Use Norwegian UI text.
- Be secure by default.
- Escape all output.
- Sanitize all input.
- Use nonces, capabilities, secure session tokens, and permission checks.
- Do not use unnecessary external services.
- Do not use an external QR code API.
- QR codes must be generated locally.
- Do not expose customer or order data publicly.
- QR tokens must never bypass staff login.
- QR tokens only help identify/open an order after valid staff session/PIN validation.

## WooCommerce And HPOS Rules

- Must be HPOS compatible.
- Use WooCommerce CRUD APIs for order data and order metadata.
- Do not use direct SQL against posts/postmeta for WooCommerce orders.
- Do not use `get_post_meta`, `update_post_meta`, or direct post meta writes for WooCommerce order data unless explicitly safe, documented, and not related to order storage.
- Hentenummer must be stored on the WooCommerce order as `_lp_cc_pickup_number`.
- Required order metadata must remain usable by WooCommerce tools, email templates, and external SMS tools.

## Required Order Metadata

- `_lp_cc_is_pickup_order`
- `_lp_cc_pickup_number`
- `_lp_cc_qr_token`
- `_lp_cc_pickup_status`
- `_lp_cc_ready_at`
- `_lp_cc_collected_at`
- `_lp_cc_collected_by`
- `_lp_cc_payment_confirmed_at`
- `_lp_cc_payment_confirmed_by`
- `_lp_cc_internal_note`
- `_lp_cc_audit_log`

## Internal Pickup States

- `new`
- `picking`
- `ready`
- `collected`
- `problem`

## Hentenummer Rules

- Default format: `H1001`, `H1002`, `H1003`.
- Admin can configure prefix.
- Admin can configure next number.
- Admin can configure minimum number length.
- Hentenummer must be unique.
- Never overwrite an existing pickup number.
- Generate only for detected click-and-collect orders.
- Store as WooCommerce order metadata under `_lp_cc_pickup_number`.

## QR Rules

- Generate a secure random QR token and store it as `_lp_cc_qr_token`.
- QR URL format: `{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}`.
- QR code must show in WooCommerce admin order panel.
- QR code must show on WP Overnight packing slip when enabled.
- QR code must not expose private customer/order data.
- QR scan should open the order in terminal only after staff session/PIN validation.
- If no staff session exists, terminal asks for login and then continues opening the scanned order.

## WP Overnight Integration Rules

- Existing plugin: "PDF Invoices & Packing Slips for WooCommerce" by WP Overnight.
- Do not modify WP Overnight plugin files.
- Do not build a separate packing slip system.
- Add a dedicated integration class, for example `class-wpo-packing-slip-integration.php`.
- Detect if WP Overnight is active.
- Use WP Overnight template/action hooks where possible.
- Output only on packing slips by default, not invoices.
- Include settings for enabling integration, pickup number, QR code, and placement.
- Fallback gracefully if QR cannot render.

Packing slip output should be compact and print-friendly:

```text
KLIKK OG HENT
Hentenummer: H1001
[QR code]
Scan for å åpne ordren i butikkterminalen
```

## Payment Rules

- Admin can classify gateways as paid online or pay in store.
- Terminal states:
  - `Betalt på nett`
  - `Må betales i butikk`
  - `Betaling må sjekkes`
- If pay in store and payment is not confirmed, show amount clearly.
- Show button text `Bekreft betalt i butikk`.
- Prevent `Marker hentet` when payment confirmation is required and missing.
- Store payment confirmation metadata:
  - `_lp_cc_payment_confirmed_at`
  - `_lp_cc_payment_confirmed_by`
- Audit log must record payment confirmation.

## Status Rules

- Do not create duplicate WooCommerce statuses.
- Admin maps existing WooCommerce statuses to internal pickup states.
- Existing "Klar for henting" status should be selectable as the `ready` mapping.
- Terminal tabs use internal pickup states.
- Terminal actions update internal pickup state.
- Terminal actions update WooCommerce order status only if admin mapped a status.
- If no status mapping is set for an action, update only metadata and audit log.

## Staff Profile And Session Rules

- Admin can create staff profiles with name, 4-digit PIN, role, active state, and optional color/initials.
- Roles: `staff` or `manager`.
- Login uses profile + PIN.
- Failed PIN attempts must be rate-limited.
- Use a generic error message on failed login.
- Default session duration: 4 hours.
- Default inactivity lock duration: 30 minutes.
- Terminal header always shows active staff profile.
- Terminal must have `Logg ut` and `Bytt profil`.
- Logout clears/revokes session.
- Switch profile requires selecting another staff profile and entering PIN when enabled.
- Inactivity lock shows current staff name, requires PIN, and offers profile switching.

## Audit Log Rules

Store audit log on the order as `_lp_cc_audit_log`.

Log at minimum:

- pickup number generated
- QR token generated/regenerated
- staff logged in
- staff logged out
- switched profile
- session locked
- started picking
- marked ready
- payment confirmed
- marked collected
- marked problem
- internal note added/changed
- admin manual metadata repair

Entries include timestamp, staff profile ID/name if available, action key, and human-readable Norwegian message.

## Planned Admin Settings

Settings page: WooCommerce -> Klikk og hent

Sections:

- Generelt
- Klikk-og-hent-deteksjon
- Hentenummer
- Ordrestatus-mapping
- Betaling
- Ansattprofiler
- Terminalinnlogging
- PDF/plukkliste
- Terminalutseende

See `docs/SETTINGS.md`.

## Planned Terminal Screens

- Login/profile screen
- Locked screen
- Order overview
- Order detail

Terminal UI must be beautiful, premium, calm, fast, mobile-first, app-like, high contrast, uncluttered, and excellent on iPhone and iPad.

## Planned REST API

Namespace: `/wp-json/lp-cc/v1/`

Terminal endpoints require a valid terminal session and must return only the minimum needed order/customer data.

See `docs/API.md`.

## Planned Architecture

Use modular classes:

- `class-plugin.php`
- `class-activator.php`
- `class-deactivator.php`
- `class-compatibility.php`
- `class-settings.php`
- `class-order-helper.php`
- `class-pickup-number.php`
- `class-qr-code.php`
- `class-payment-helper.php`
- `class-status-mapper.php`
- `class-staff-profiles.php`
- `class-terminal-session.php`
- `class-rest-api.php`
- `class-terminal-route.php`
- `class-admin-order-ui.php`
- `class-wpo-packing-slip-integration.php`
- `class-audit-log.php`
- `class-assets.php`

See `docs/ARCHITECTURE.md`.

## PR And Review Expectations

- Every change must be made in a fresh PR.
- Keep PRs focused.
- Do not update old PRs for new tasks.
- Prefer draft PRs while work is in progress.
- Run the most relevant checks available before asking for review.
- If a Codex review is available, run it and fix P0/P1 issues before merge.
- Merge only when checks are green or when the absence of checks is explicitly confirmed.
- Do not merge if there are unresolved P0/P1 review comments.

## Definition Of Done

- Business rules from this file remain intact.
- No SMS integration is introduced.
- No duplicate "Klar for henting" status is introduced.
- No custom packing slip system is introduced.
- Hentenummer remains WooCommerce order metadata.
- WP Overnight remains the packing slip integration target.
- Staff sessions support stay-logged-in duration, logout, profile switching, and inactivity lock.
- Payment methods can be classified as paid online or pay in store.
- Pay-in-store collection blocking is enforced unless disabled.
- All output is escaped and all input is sanitized.
- WooCommerce order data uses HPOS-compatible CRUD APIs.
- REST endpoints require proper permission/session checks.
- UI remains Norwegian, mobile-first, touch-friendly, calm, and app-like.
- No unnecessary dependencies are added.
- Documentation is updated when product rules or architecture change.
