# Metadata

All click-and-collect order metadata must be stored on the WooCommerce order with HPOS-compatible CRUD APIs.

Do not use direct SQL or direct postmeta functions for WooCommerce order metadata.

## Required Order Meta Keys

| Key | Type | Purpose |
| --- | --- | --- |
| `_lp_cc_is_pickup_order` | boolean-like string | Marks order as a detected click-and-collect order. |
| `_lp_cc_pickup_number` | string | Human hentenummer, for example `H1001`. |
| `_lp_cc_qr_token` | string | Secure random token used in QR URL. |
| `_lp_cc_pickup_status` | string | Internal pickup state. |
| `_lp_cc_ready_at` | datetime string | Timestamp when order was marked ready. |
| `_lp_cc_collected_at` | datetime string | Timestamp when order was marked collected. |
| `_lp_cc_collected_by` | staff profile ID/name | Staff profile that marked order collected. |
| `_lp_cc_payment_confirmed_at` | datetime string | Timestamp when in-store payment was confirmed. |
| `_lp_cc_payment_confirmed_by` | staff profile ID/name | Staff profile that confirmed payment. |
| `_lp_cc_internal_note` | string | Internal staff note for pickup handling. |
| `_lp_cc_audit_log` | array/json | Structured audit log entries. |

## Internal Pickup States

| State | Norwegian UI | Meaning |
| --- | --- | --- |
| `new` | Nye | Pickup order exists and has not started picking. |
| `picking` | Plukkes | Staff has started picking. |
| `ready` | Klar | Order is ready for customer pickup. |
| `collected` | Hentet | Customer has collected the order. |
| `problem` | Problem | Order needs staff attention. |

## Hentenummer

- Stored as `_lp_cc_pickup_number`.
- Default format: `H1001`, `H1002`, `H1003`.
- Prefix default: `H`.
- Next number default: `1001`.
- Minimum number length configurable.
- Must be unique.
- Must never overwrite existing pickup number.
- Must only be generated for detected pickup orders.
- Must be usable by WooCommerce emails, SMS tools, and other integrations.

Generation behavior:

- Pickup orders are detected from the shipping methods selected in WooCommerce -> Klikk og hent.
- Automatic generation only runs when the plugin is enabled and `auto_pickup_number` is enabled.
- The next number is read from plugin settings, formatted with the configured prefix and minimum length, checked for uniqueness with WooCommerce order queries, then reserved by incrementing the stored next number.
- A short-lived WordPress option lock protects the sequential counter during concurrent order events. This lock is non-order data; order data still uses WooCommerce CRUD.
- If automatic generation is disabled, eligible orders may still be marked as pickup orders, but hentenummer and QR metadata are not generated automatically.
- Admins can use the WooCommerce order action `Generer manglende hentedata` to create missing pickup metadata for an eligible order.
- Existing `_lp_cc_pickup_number` values are preserved. Missing supporting metadata can be repaired without replacing the hentenummer.

## QR Token

- Stored as `_lp_cc_qr_token`.
- Must be secure random.
- QR URL format: `{site_url}/{terminal_slug}?pickup={pickup_number}&token={qr_token}`.
- Token must not expose private data.
- Token must not bypass staff login.
- The initial token is generated locally with secure random bytes when pickup metadata is created.

## Audit Log Entry Shape

Recommended structure:

```json
{
  "timestamp": "2026-06-09T10:30:00+02:00",
  "staff_profile_id": "123",
  "staff_profile_name": "Anna",
  "action": "marked_ready",
  "message": "Anna markerte ordren som klar for henting."
}
```

Log messages should be human-readable Norwegian.

The initial hentenummer generation audit event uses:

```json
{
  "timestamp": "2026-06-09T10:30:00+00:00",
  "action": "pickup_number_generated",
  "message": "Hentenummer H1001 ble generert"
}
```
