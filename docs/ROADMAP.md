# Roadmap

This roadmap describes planned implementation phases. It is intentionally documentation-first so future Codex tasks can build the plugin in small, reviewable PRs.

## Phase 0: Repository Memory And Product Specification

- Create `README.md`, `AGENTS.md`, `CHANGELOG.md`, and docs.
- Capture business rules, out-of-scope boundaries, architecture, metadata, API, security, UI, and QA expectations.
- Do not implement plugin functionality in this phase.

## Phase 1: Plugin Skeleton

- Create `lilleprinsen-click-collect` plugin folder.
- Add main plugin file and modular class loader.
- Add activation/deactivation classes.
- Add compatibility checks for PHP, WordPress, WooCommerce, and HPOS.
- Add text domain and basic plugin headers.
- Add empty settings page shell under WooCommerce -> Klikk og hent.
- Add initial test/check scripts if appropriate.

## Phase 2: Settings Foundation

- Implement settings storage and admin page sections:
  - Generelt
  - Klikk-og-hent-deteksjon
  - Hentenummer
  - Ordrestatus-mapping
  - Betaling
  - Ansattprofiler
  - Terminalinnlogging
  - PDF/plukkliste
  - Terminalutseende
- List existing WooCommerce shipping methods, payment gateways, and order statuses.
- Avoid creating duplicate statuses.

## Phase 3: Pickup Detection And Metadata

- Detect click-and-collect orders from configured shipping methods.
- Generate unique hentenummer only for pickup orders.
- Store required metadata with WooCommerce CRUD APIs.
- Generate secure QR token.
- Add audit log helper.
- Add admin repair actions for missing metadata where safe.

## Phase 4: Admin Order UI

- Add Click & Collect panel to WooCommerce order admin.
- Show hentenummer, QR code, internal pickup status, payment classification, payment confirmation, timestamps, internal note, and audit log.
- Add manual actions:
  - generate missing hentenummer
  - regenerate QR token
  - mark as pickup order
  - repair missing metadata

## Phase 5: Staff Profiles And Terminal Sessions

- Implement staff profile storage.
- Store hashed 4-digit PINs.
- Add reset PIN flow.
- Add login, logout, switch profile, and unlock flows.
- Add session duration and inactivity lock.
- Rate-limit failed PIN attempts.

## Phase 6: REST API

- Implement `/wp-json/lp-cc/v1/`.
- Add auth endpoints.
- Add order list/detail endpoints.
- Add order action endpoints.
- Ensure terminal endpoints require valid terminal session.
- Return minimal order/customer data.
- Return friendly Norwegian errors.

## Phase 7: Terminal UI

- Build `/butikkterminal`.
- Implement login/profile screen.
- Implement locked screen.
- Implement order overview with tabs and payment filter.
- Implement order detail with payment, items, note, audit timeline, and action buttons.
- Use mobile-first, app-like, Norwegian UI.

## Phase 8: WP Overnight Packing Slip Integration

- Detect WP Overnight plugin.
- Add compact pickup block to packing slips when enabled.
- Show hentenummer and QR code based on settings.
- Support placement setting.
- Do not modify WP Overnight files.
- Do not output on invoices by default.

## Phase 9: Hardening And QA

- Run security review.
- Verify HPOS compatibility.
- Verify payment blocking rules.
- Verify session expiration and inactivity lock.
- Verify no public customer/order data exposure.
- Verify print-friendly packing slip output.
- Add automated tests where practical.

## Phase 10: Release Preparation

- Finalize README and release notes.
- Add upgrade notes if metadata/settings change.
- Package plugin.
- Confirm production checklist from `docs/RELEASE.md`.
