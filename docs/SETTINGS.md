# Settings

Settings page location: WooCommerce -> Klikk og hent

All settings must be sanitized on save and escaped on output.

## 1. Generelt

- Enable plugin
- Terminal URL slug, default `butikkterminal`
- Debug logging

## 2. Klikk-og-hent-deteksjon

- Select shipping methods that count as click and collect
- Only detected click-and-collect orders receive hentenummer and QR token

## 3. Hentenummer

- Enable automatic hentenummer generation
- Prefix, default `H`
- Next number, default `1001`
- Minimum number length
- Preview

Rules:

- Must be unique.
- Must not overwrite existing pickup numbers.
- Must be stored as `_lp_cc_pickup_number`.

## 4. Ordrestatus-mapping

Map internal pickup states to existing WooCommerce statuses:

- `new`
- `picking`
- `ready`
- `collected`
- `problem`

Rules:

- Do not create duplicate statuses.
- Existing "Klar for henting" status should be selectable as `ready`.
- If no status is mapped for an action, update only metadata and audit log.

## 5. Betaling

- Payment methods classified as paid online
- Payment methods classified as pay in store
- Require payment confirmation before collected

Terminal labels:

- `Betalt på nett`
- `Må betales i butikk`
- `Betaling må sjekkes`

## 6. Ansattprofiler

Each staff profile includes:

- name
- 4-digit PIN stored hashed
- role: `staff` or `manager`
- active yes/no
- optional color/initials for UI

Admin actions:

- create profile
- edit profile
- deactivate profile
- reset PIN

## 7. Terminalinnlogging

- Session duration, default 4 hours
- Inactivity lock duration, default 30 minutes
- Require PIN when switching profile

Terminal must support:

- stay logged in
- `Logg ut`
- `Bytt profil`
- inactivity lock

## 8. PDF/plukkliste

- Enable WP Overnight integration
- Show hentenummer on packing slip
- Show QR on packing slip
- QR placement:
  - top
  - after order data
  - before order items

Rules:

- Use WP Overnight hooks.
- Do not modify WP Overnight files.
- Output only on packing slips by default.
- Do not build a separate packing slip system.

## 9. Terminalutseende

- Show product images
- Show SKU
- Compact or comfortable layout

UI must remain fast, calm, touch-friendly, and mobile-first.
