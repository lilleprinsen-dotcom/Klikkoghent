# REST API

Planned namespace:

```text
/wp-json/lp-cc/v1/
```

The API is for the staff terminal. Terminal endpoints must require a valid terminal session.

## Security Rules

- Validate terminal session on every terminal endpoint.
- Validate capabilities for admin-only endpoints.
- Sanitize all request input.
- Escape all output rendered into HTML.
- Return minimal data in list endpoints.
- Return only necessary customer/order data in detail endpoints.
- Never expose customer/order data publicly.
- QR token does not bypass login.
- Use friendly Norwegian errors where appropriate.

## Auth Endpoints

### `POST /auth/login`

Login with staff profile and 4-digit PIN.

Request:

```json
{
  "profile_id": "123",
  "pin": "1234"
}
```

Response includes session state and active profile, not sensitive data.

### `POST /auth/logout`

Revokes current terminal session.

### `POST /auth/switch-profile`

Switches active staff profile. Requires PIN when enabled.

### `POST /auth/unlock`

Unlocks inactive terminal session with PIN.

### `GET /auth/me`

Returns current terminal session/profile state.

## Order Endpoints

### `GET /orders`

List pickup orders.

Filters:

- search
- internal pickup state
- payment filter
- pagination

List response must be minimal:

- order ID
- order number
- hentenummer
- customer display name
- item count
- payment badge/state
- internal pickup state
- WooCommerce status label

### `GET /orders/{id}`

Return order detail for terminal.

Include only what staff needs for pickup:

- hentenummer
- order number
- customer name
- phone
- email
- internal pickup status
- WooCommerce status
- payment classification and amount
- items needed for pickup
- internal note
- audit log timeline

### `GET /orders/by-pickup/{pickup_number}`

Find an order by hentenummer. Used for search and QR continuation after login.

### `POST /orders/{id}/start-picking`

Set internal pickup state to `picking`, update mapped WooCommerce status if configured, and append audit log.

### `POST /orders/{id}/mark-ready`

Set internal pickup state to `ready`, set `_lp_cc_ready_at`, update mapped WooCommerce status if configured, and append audit log.

### `POST /orders/{id}/confirm-payment`

For pay-in-store orders:

- set `_lp_cc_payment_confirmed_at`
- set `_lp_cc_payment_confirmed_by`
- append audit log

### `POST /orders/{id}/mark-collected`

Set internal pickup state to `collected`, set collected metadata, update mapped WooCommerce status if configured, and append audit log.

If payment method is pay in store and confirmation is required, reject unless payment has been confirmed.

### `POST /orders/{id}/mark-problem`

Set internal pickup state to `problem`, update mapped WooCommerce status if configured, and append audit log.

### `POST /orders/{id}/note`

Create or update internal note and append audit log.

## Error Language

Use Norwegian messages such as:

- `Ugyldig PIN-kode.`
- `Terminaløkten er utløpt. Logg inn på nytt.`
- `Betaling må bekreftes før ordren kan markeres som hentet.`
- `Ordren ble ikke funnet.`
