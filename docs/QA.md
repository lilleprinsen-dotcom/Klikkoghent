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
- QR does not expose private data.
- QR does not bypass staff login.

## Payment

- Payment gateways can be classified as paid online or pay in store.
- Pay-in-store orders show `Må betales i butikk`.
- Payment confirmation can be recorded.
- Mark collected is blocked when required confirmation is missing.
- Audit log records payment confirmation.

## Staff Sessions

- Staff can log in with profile + PIN.
- PINs are hashed.
- Failed PIN attempts are rate-limited.
- Session duration works.
- Inactivity lock works.
- Logout clears session.
- Switch profile works.
- Important actions include active staff profile.

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
- Packing slip output appears only when enabled.
- Packing slip output is compact and print-friendly.
- Output appears on packing slips by default, not invoices.
- No WP Overnight plugin files modified.

## Review Gates

- Run available automated checks.
- Run Codex review if available.
- Fix P0/P1 issues before merge.
- Merge only when checks are green or when no checks exist and that is confirmed.
