# How Suppli connects to the shipment service

Suppli keeps stock and orders; the shipment service owns the shipping data (containers, seal numbers, shipping method,
line, destination, ETD/ETA, status and customer delivery dates). The two systems talk only through the REST API: the
service never reads Suppli's database and Suppli never writes to the service's database.

## 1. Authentication

- Each customer company in the service gets an **API client** for Suppli: a service account of that company that has no
  password and cannot sign in to the UI. A company admin creates it under *API clients* (or with
  `php artisan api:token "Suppli integration" --company=<id> --ability=write --ability=references:write`) and issues a
  token. The token is shown once and stored in Suppli's settings; the service only keeps a SHA-256 hash.
- Suppli sends it as `Authorization: Bearer <token>` over HTTPS. The token decides the company: every request is scoped to
  it, so Suppli cannot see or change another company's data, even with a wrong id.
- **Abilities** limit what a token may do: none = read-only, `write` = register shipments and change status/dates,
  `references:write` = update Suppli reference fields. People in the UI never get `references:write`.
- Tokens can have an expiry date; rotation = issue a new token, switch Suppli over, revoke the old one. Disabling the API
  client pauses it: its tokens are rejected until it is enabled again (revoke a token to remove it for good).
  Deactivating the company revokes all its tokens. Changes made with the token appear in the history as
  *Suppli integration* with source `api`.

## 2. How Suppli sends its data

**When a shipment is booked** (the commercial invoice / packing slip is final), Suppli registers it:

`POST /api/v1/shipments`

```json
{
  "external_reference": "SUPPLI-SHP-4711",
  "shipping_method": "sea", "shipping_line": "TRANSSEA AS", "destination_port": "SALTEN", "incoterm": "CIF",
  "etd": "2026-08-08", "eta": "2026-09-29",
  "containers": [{
    "container_no": "HAMU4766088", "seal_no": "SL-1001", "container_type": "45GP",
    "items": [
      {"package_no": "WENA192543", "tag_number": "GNP-2606018", "product_code": "25403000654",
       "product_description": "NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM", "product_category": "net_pen",
       "proforma_invoice_no": "862600578", "exporter_reference": "462600223",
       "customer_name": "Finnmark AS", "customer_reference": "C-1042", "storage_facility": null,
       "quantity": 1, "unit": "PC", "net_weight_kg": 3080, "gross_weight_kg": 3180,
       "customer_delivery_date": "2026-10-05"}
    ]
  }]
}
```

- `external_reference` is Suppli's id for the shipment. Sending the same one again returns `409` with the existing
  shipment id, so retries after a timeout are safe. Package numbers are unique per company, which protects the cargo too.
- Container numbers are validated (ISO 6346 check digit for sea, air waybill check digit for air) and ETD ≤ ETA; errors
  come back as `422` with one message per field.
- A shipment already under way can be registered with `initial_status` (e.g. `in_transit`).

| Suppli field (reference only) | Service field |
|---|---|
| Proforma invoice no | `proforma_invoice_no` |
| Exporter's reference | `exporter_reference` |
| Tag number | `tag_number` (stored as `GNP-…`, searchable with or without prefix) |
| NP (net pen) / DF (dead fish collect) | `product_category` `net_pen` / `dead_fish_collector` + product code/description |
| Storage facility | `storage_facility` |
| Customer | `customer_name`, `customer_reference` (Suppli id), `customer_po` |

**When reference data changes in Suppli** (e.g. goods moved to another warehouse, invoice corrected):
`PATCH /api/v1/cargo-items/references` with `{"items": [{"package_no": "WENA192543", "storage_facility": "EK Warehouse"}]}`
(token ability `references:write`). Items are matched by package number; the response lists updated and unknown packages.

**Status and date updates from Suppli** (optional, e.g. when the warehouse confirms a delivery in Suppli) use the same
audited endpoints as the UI: `POST /shipments/{id}/status` and `POST /shipments/{id}/dates` with a reason.

**Historical data** can be loaded once with the Excel import (`POST /imports`).

## 3. How the service notifies Suppli about status and date changes

**Webhooks (push).** A company admin registers Suppli's URL under *API clients → Webhooks* (or `POST /api/v1/webhooks`)
and picks the events. The signing secret is shown once and goes into Suppli's settings; it can be rotated at any time.

For every change (one user action, API call or provider sync, identified by its `change_group`) the service sends one
event per kind of change:

- `shipment.status_changed`: the packages that moved, with `from`, `to`, when, and whether it was a correction.
- `shipment.dates_changed`: ETD/ETA of the shipment or customer delivery dates of packages (old, new, shift in days).

Marking a container arrived on another day than planned sends both events, with the same `change_group`:

```json
{
  "id": "0d6c5a52-9a3e-5f0b-8e61-3c2f4b7a9d10",
  "type": "shipment.status_changed",
  "created_at": "2026-10-02T09:15:00Z",
  "change_group": "6b1e0c9a-4f2d-4e7a-9c1b-2d3e4f5a6b7c",
  "company": {"id": 1, "name": "Garware Technical Fibres AS"},
  "shipment": {"id": 41, "reference": "SHP-00041", "external_reference": "SUPPLI-SHP-4711",
               "status": "arrived", "etd": "2026-08-08", "eta": "2026-10-02"},
  "source": "manual", "changed_by": "Ops User", "reason": "Vessel berthed",
  "changes": [{"package_no": "WENA192543", "container_no": "HAMU4766088", "proforma_invoice_no": "862600578",
               "tag_number": "GNP-2606018", "customer_reference": "C-1042",
               "from": "in_transit", "to": "arrived", "is_correction": false, "occurred_at": "2026-10-02T08:30:00Z"}]
}
```

Payloads carry Suppli's own keys (`external_reference`, package number, proforma invoice, tag, customer reference),
so Suppli does not need the service's internal ids.

- **Request:** `POST` with a JSON body and the headers `X-Webhook-Id` (the event id, the same on every retry),
  `X-Webhook-Event`, `X-Webhook-Timestamp` (Unix seconds), `X-Webhook-Attempt` and
  `X-Webhook-Signature: sha256=<hex>`.
- **Checking it in Suppli:** compute HMAC-SHA256 of `<timestamp>.<raw body>` with the secret, compare it with the
  signature in constant time, reject timestamps older than five minutes, answer `2xx` within 10 seconds, then process
  the event in the background. Delivery is at-least-once, so Suppli ignores event ids it has already handled.
- **Retries:** any other answer, a timeout or a connection error is retried after 1 min, 5 min, 30 min, 2 h, 6 h, 12 h
  and 24 h (8 attempts in about two days). After that the delivery is marked failed. The delivery log in the UI shows
  every delivery with the receiver's answer, and failed ones can be sent again. A webhook can be paused; changes made
  while it is paused are not sent to it.
- **Reliability:** the delivery is written in the same database transaction as the change itself (an outbox), so a
  saved change never misses its notification. A queue worker sends it; a scheduled job (`webhooks:deliver-due`) picks up
  anything left behind by a crash.
- Retries can make events of one shipment arrive out of order: compare `created_at`, or re-read the shipment.
- Changes made with Suppli's own token are sent back with `source: "api"`, so Suppli can skip its own changes.
  Excel imports (past data) send no webhooks.

**Polling (fallback and reconciliation).** Every change bumps the shipment's `updated_at`.
`GET /api/v1/shipments?updated_since=<time of the last check>` lists what changed; `GET /shipments/{id}` returns the
current status per package, ETD/ETA and delivery dates, and `GET /shipments/{id}/history` the full audit trail.
Suppli can use this after a long outage or as a nightly check.

## 4. Conventions

- Versioned base path `/api/v1`; JSON only; dates as `YYYY-MM-DD`, timestamps ISO 8601 in UTC.
- Errors: `401` invalid or expired token, `403` missing ability, `404` not found (also for another company's ids),
  `409` duplicate `external_reference`, `422` validation with field messages.
- The full contract is in [`openapi.json`](openapi.json) and at `/docs/api`.

## Assumptions

- A shipment is one voyage; Suppli knows the containers and packages when the packing slip is final.
- The package number from the packing slip identifies a cargo item; Suppli's own ids can be sent as references.
- Suppli is the master for customers, products, invoices and storage facilities; the service never changes them on its
  own and never pushes them back.
- Status and customer delivery date are tracked per package; Suppli may aggregate them per order line.
- One Suppli installation can serve several companies; each company gets its own API client and token.
- Suppli's webhook endpoint is reachable from the service over HTTPS and answers within 10 seconds.
