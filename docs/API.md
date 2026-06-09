# REST API

Namespace:

```text
/wp-json/lp-cc/v1/
```

The API is for the staff terminal. Auth endpoints are implemented for staff profile sessions. Future order endpoints must require a valid, unlocked terminal session.

## Security Rules

- Validate terminal session on every terminal endpoint.
- Validate capabilities for admin-only endpoints.
- Sanitize all request input.
- Escape all output rendered into HTML.
- Return minimal data in list endpoints.
- Return only necessary customer/order data in detail endpoints.
- Never expose customer/order data publicly.
- QR token does not bypass login.
- Authenticated requests may use the HttpOnly `lp_cc_terminal_session` cookie, `Authorization: Bearer {token}`, or `X-LP-CC-Session: {token}`.
- Server-side session storage keeps only hashed tokens.
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

Response includes session state, active profile, and an opaque `session_token` for non-cookie test clients. The token is also set as an HttpOnly SameSite cookie. Response data never includes PIN hashes.

Session response:

```json
{
  "success": true,
  "message": "Du er logget inn.",
  "session": {
    "authenticated": true,
    "locked": false,
    "created_at": "2026-06-09T12:00:00+00:00",
    "expires_at": "2026-06-09T16:00:00+00:00",
    "last_activity_at": "2026-06-09T12:00:00+00:00",
    "profile": {
      "id": "1",
      "name": "Ola",
      "role": "staff",
      "initials": "O",
      "color": "#6b7280"
    },
    "session_token": "opaque-token"
  }
}
```

### `POST /auth/logout`

Revokes current terminal session and clears the terminal cookie.

### `POST /auth/switch-profile`

Switches active staff profile. Requires the target profile PIN when `require_pin_on_switch` is enabled.

Request:

```json
{
  "profile_id": "2",
  "pin": "1234"
}
```

### `POST /auth/unlock`

Unlocks inactive terminal session with PIN.

Request:

```json
{
  "pin": "1234"
}
```

### `GET /auth/me`

Returns current terminal session/profile state.

If the configured inactivity duration has passed, this endpoint returns the session with `locked: true`. If the configured session duration has expired, it returns `401` and the staff member must log in again.

Implemented auth event logging:

- `staff_logged_in`
- `staff_logged_out`
- `session_expired`
- `session_locked`
- `profile_switched`

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
