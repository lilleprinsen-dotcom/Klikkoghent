# Settings

Settings page location: WooCommerce -> Klikk og hent

The settings page stores one sanitized option: `lp_cc_settings`.

Current scope: settings storage, pickup detection, hentenummer generation, secure QR token generation, WooCommerce admin order display, and WP Overnight packing slip output. Terminal sessions and payment enforcement are later milestones.

All settings must be sanitized on save and escaped on output.

## Helper Methods

Settings are managed by `Lilleprinsen\ClickCollect\Settings`.

- `Settings::get_defaults()` returns the complete default setting array.
- `Settings::get_all()` returns saved settings merged with defaults.
- `Settings::get( $key )` returns a single setting value.

## 1. Generelt

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `enabled` | boolean | `false` | Enables the plugin's future business logic. |
| `terminal_slug` | string | `butikkterminal` | Terminal URL slug. Sanitized with `sanitize_title`. |
| `debug_logging` | boolean | `false` | Enables future debug logging. |

## 2. Klikk-og-hent-deteksjon

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `pickup_shipping_methods` | string array | `[]` | Selected WooCommerce shipping method instance IDs in `method_id:instance_id` format. |

The UI explains in Norwegian that orders using these shipping methods receive hentenummer when business logic is implemented.

Rules:

- Store selected method IDs safely.
- Do not detect orders yet in this settings-only phase.
- Only selected shipping methods should count as click and collect once detection is implemented.

## 3. Hentenummer

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `auto_pickup_number` | boolean | `true` | Enables future automatic hentenummer generation. |
| `pickup_number_prefix` | string | `H` | Prefix for hentenummer. Sanitized to letters, numbers, underscore, and hyphen. |
| `next_pickup_number` | integer | `1001` | Next number to use. Minimum `1`. |
| `min_number_length` | integer | `4` | Minimum numeric length. Range `1-12`. |
| `admin_show_qr` | boolean | `true` | Show a locally rendered QR code preview on the WooCommerce admin order screen. |

The admin UI shows a preview such as `H1001`.

Rules:

- Hentenummer must be unique.
- Hentenummer must never overwrite existing pickup numbers.
- Hentenummer must be stored as `_lp_cc_pickup_number` on the WooCommerce order.
- QR previews must be generated locally and must encode only pickup number and QR token.

## 4. Ordrestatus-Mapping

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `status_mapping` | object/array | all empty | Maps internal pickup states to existing WooCommerce statuses. |

Internal keys:

- `new`
- `picking`
- `ready`
- `collected`
- `problem`

Rules:

- Admin chooses from existing WooCommerce statuses only.
- Do not create duplicate statuses.
- The store's existing "Klar for henting" status must be selectable for the `ready` mapping.
- Empty mapping means future actions update metadata/audit log only and do not change WooCommerce order status.

## 5. Betaling

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `paid_online_methods` | string array | `[]` | Payment gateway IDs classified as paid online. |
| `pay_in_store_methods` | string array | `[]` | Payment gateway IDs classified as pay in store. |
| `require_payment_confirmation` | boolean | `true` | Future enforcement: require confirmation before marking collected. |

Terminal labels for future implementation:

- Paid online methods: `Betalt på nett`
- Pay in store methods: `Må betales i butikk`
- Unclassified methods: `Betaling må sjekkes`

## 6. Terminalinnlogging

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `session_duration_hours` | integer | `4` | Staff terminal session duration. Range `1-24`. |
| `inactivity_lock_minutes` | integer | `30` | Inactivity lock duration. Range `1-240`. |
| `require_pin_on_switch` | boolean | `true` | Require PIN when switching staff profile. |

Rules for future implementation:

- Terminal must support stay-logged-in duration.
- Terminal must support inactivity lock.
- Terminal must support `Logg ut` and `Bytt profil`.

## 7. PDF/Plukkliste

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `wpo_enabled` | boolean | `false` | Enables WP Overnight packing slip integration. |
| `wpo_show_pickup_number` | boolean | `true` | Show hentenummer on packing slip. |
| `wpo_show_qr` | boolean | `true` | Show QR code on packing slip. |
| `wpo_placement` | string | `after_order_data` | Placement: `top`, `after_order_data`, or `before_order_items`. |

Rules:

- Use WP Overnight hooks when implementation is added.
- Do not modify WP Overnight files.
- Output only on packing slips by default, not invoices.
- Do not build a separate packing slip system.
