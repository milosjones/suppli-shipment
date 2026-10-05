# Suppli shipments tracking microservice

A standalone service for the shipping part of the business
- **Backend:** Laravel 13 (PHP 8.4), PostgreSQL 17, REST API with Sanctum bearer tokens, OpenAPI docs (Scramble)
- **Frontend:** React 19 + TypeScript + Tailwind CSS 4 (Vite), TanStack Query, shadcn/ui (Radix UI, cmdk, react-day-picker), Lucide icons
- **Runs with:** Docker Compose (`db` · `api` php-fpm · `worker` queue · `web` nginx)

| Requirement | Where |
|---|---|
| Shipments → containers → cargo items (tags), validated input (ISO 6346 / AWB check digits, ETD ≤ ETA) | `POST /shipments`, `app/Support/ContainerNumber.php` |
| Status flow with status history | `app/Enums/CargoStatus.php`, `app/Services/Shipments/StatusService.php` |
| Date changes with audit trail (old, new, who, when, reason) and days delayed | `app/Services/Shipments/DateChangeService.php` |
| Reports: upcoming deliveries, status overview, delayed shipments | `app/Services/Reports/*` |
| Lookup by container, proforma invoice, tag, product, customer | `app/Services/Lookup/LookupService.php` |
| Excel import with normalisation and a report of rows that cannot be imported | `app/Services/Import/*` |
| Token auth, every request belongs to a company, data separated per company | `app/Http/Middleware/EnsureCompanyContext.php`, composite tenant keys |
| Web UI: list with filters, shipment page (status + dates + history), lookup, reports | `frontend/` |
| Tests, Docker, README, OpenAPI, integration note | `backend/tests`, `docker-compose.yml`, `docs/` |
| Status and date changes pushed to Suppli: signed webhooks, retries, delivery log | `app/Webhooks/*`, UI *API clients → Webhooks* |
| Bonus: tracking-provider interface + fake implementation | `app/Tracking/*` |

## Quick start

Requirements: Docker Desktop (or Docker Engine with Compose v2). Nothing else is needed on the host.

```bash
docker compose up -d --build
```

Open **http://localhost:8080**. The first start runs the migrations, imports the attached delivery plan
(`backend/database/seeders/data/norway-delivery-plan.xlsx`) and creates demo accounts:

| Account | Email | Password | Sees |
|---|---|---|---|
| Super admin | `superadmin@suppli.test` | `password` | Companies, all users; any company via the company switcher |
| Company admin | `admin@garware.test` | `password` | Garware Technical Fibres AS (imported plan), users, API clients |
| Company user | `logistics@garware.test` | `password` | Garware Technical Fibres AS |

These passwords are for local demos only (see [Production notes](#production-notes)). To show that companies are kept
apart, sign in as the super admin, add a company and its admin under *Companies*, and import a plan for it.

- API: `http://localhost:8080/api/v1` · interactive docs: **http://localhost:8080/docs/api** · spec: [`docs/openapi.json`](docs/openapi.json)
- Health check: `http://localhost:8080/up`
- How Suppli would connect: [`docs/suppli-integration.md`](docs/suppli-integration.md)

To start again from an empty database: `docker compose down -v && docker compose up -d --build`.

## Tests

The suite (132 tests) runs on PostgreSQL in a separate `shipments_test` database, so it never touches the app data:

```bash
docker compose exec api php artisan test
```

It covers: authentication and token abilities, company isolation (lists, detail, changes, lookup, reports, payload ids),
shipment validation, the status flow (fan-out, corrections, mixed cargo, actual dates), the date audit trail and delay
days, reports, lookup, the Excel import (the real file, a dirty generated file, re-import, dry run, upload), Suppli
references, user/company/API-client administration, the tracking-provider sync and the webhooks (events per change,
signatures, retries, pause/resume, URL rules). A guard in `tests/TestCase.php`
refuses to run against any database that is not `*_test`.

## Using the API

```bash
# Log in (web UI tokens are valid 12 hours)
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"email":"admin@garware.test","password":"password"}' | jq -r .token)

# Shipments still at sea, most delayed first
curl -s "http://localhost:8080/api/v1/shipments?status=in_transit&sort=-eta_delay_days" -H "Authorization: Bearer $TOKEN"

# Record the arrival of a whole shipment (also sets the ETA to that day)
curl -s -X POST http://localhost:8080/api/v1/shipments/35/status -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"status":"arrived","occurred_at":"2026-08-24","reason":"Vessel berthed"}'

# Move the ETA of a shipment (reason required, written to the audit trail)
curl -s -X POST http://localhost:8080/api/v1/shipments/36/dates -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"eta":"2026-09-10","reason":"Transhipment delay in Colombo"}'

# Look up a tag (with or without the GNP- prefix)
curl -s "http://localhost:8080/api/v1/lookup?q=2606018" -H "Authorization: Bearer $TOKEN"

# Check an Excel file without saving anything
curl -s -X POST http://localhost:8080/api/v1/imports -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -F 'file=@backend/database/seeders/data/norway-delivery-plan.xlsx' -F dry_run=1

# Push status and date changes to Suppli (the response shows the signing secret once)
curl -s -X POST http://localhost:8080/api/v1/webhooks -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"url":"https://suppli.example.com/webhooks/shipments","events":["shipment.status_changed","shipment.dates_changed"]}'
```

Integrations (Suppli) use **API-client tokens** instead of a password: create them under *API clients* in the UI or with
`docker compose exec api php artisan api:token "Suppli integration" --company=1 --ability=write --ability=references:write`.
Super admins add `X-Company-Id: <id>` to choose the company they work in.

All endpoints, parameters and response shapes are in the OpenAPI document. Errors use Laravel's format:
`422 {"message": "...", "errors": {"field": ["..."]}}`, `401` (no/expired token), `403` (missing ability or role),
`404` (not found or another company's data), `409` (duplicate `external_reference`).

## Architecture

```
 Browser ──► web (nginx :8080) ──┬── /            React SPA (static build)
                                 └── /api /docs /up ──► api (php-fpm, Laravel) ──► db (PostgreSQL 17)
 Suppli ──► /api/v1 (bearer token of an API client)
 worker (queue:work) ──► Suppli's webhook URL (signed POST per change)
```

```
backend/
  app/Enums/                 CargoStatus (flow), ShippingMethod, ProductCategory, ChangeSource, DateField, UserRole
  app/Models/                Company, User, Shipment, Container, CargoItem, ShipmentStatusChange, ShipmentDateChange, Import
  app/Services/Shipments/    ShipmentService, StatusService, DateChangeService, ShipmentHistory, ShipmentSummaries
  app/Services/Import/       SheetReader → RowNormalizer → DeliveryPlanImporter (+ ImportReport)
  app/Services/Reports/      UpcomingDeliveriesReport, StatusOverviewReport, DelayedShipmentsReport
  app/Services/Lookup/       LookupService
  app/Tracking/              TrackingProvider interface, TrackingSyncService, Providers/FakeTrackingProvider
  app/Webhooks/              WebhookOutbox, WebhookPayloads, WebhookSender (+ Jobs/DeliverWebhook)
  app/Http/                  Controllers/Api/V1, Requests, Resources, Middleware/EnsureCompanyContext
  database/migrations/       schema with CHECK constraints, composite tenant keys and trigram indexes
  tests/                     Unit + Feature (PostgreSQL)
frontend/src/
  features/                  shipments (list, detail, dialogs, history), lookup, reports, imports, admin, auth
  components/                layout (sidebar, page header), ui (shadcn/ui: select, combobox, date pickers, dialog …)
  lib/                       api client, typed models, React Query hooks, formatting, status styles
docs/                        openapi.json, suppli-integration.md
```

### Data model

```
companies 1─n users 1─n personal_access_tokens            (Sanctum; integrations are service-account users)
companies 1─n shipments 1─n containers 1─n cargo_items
shipments 1─n shipment_status_changes   (one row per cargo item and change)
shipments 1─n shipment_date_changes     (ETD/ETA per shipment, customer delivery date per item)
companies 1─n imports                   (each Excel run with its report and the stored file)
companies 1─n webhook_endpoints 1─n webhook_deliveries   (outbox and delivery log, one row per event and URL)
```

- **Shipment** = one voyage (same vessel, same ETD/ETA). Owns shipping method, line, ports, incoterm, ETD/ETA with their
  original (planned) values and cached `*_delay_days`, and a cached status (least advanced cargo status).
- **Container** = a container (sea), an air waybill (air) or a trailer (road). Numbers are unique per shipment, because
  shipping lines reuse them. Its status is derived from its cargo.
- **Cargo item** = one package (one Excel row), identified by its package/batch number. It holds the **Suppli
  references** (proforma invoice, exporter's reference, tag number, product incl. NP/DF category, customer, storage
  facility, quantities and weights: read-only here) and the fields this service owns: status and customer delivery date.
- Every business table carries `company_id`; children reference parents with composite keys `(parent_id, company_id)`,
  so the database itself refuses to link data across companies. Enum-like columns are checked with `CHECK` constraints;
  history tables are append-only.

### Status flow

```
planned ──► in_transit ──► arrived ──► in_storage ──► delivered
                              └──────────────────────────▲  (delivery straight from the port)
```

- Departure (`in_transit`) and arrival apply to whole containers or the shipment; `in_storage` and `delivered` can also be
  set for single packages (e.g. one net delivered to a fish farm while the rest stays in the warehouse).
- Only the next step is allowed. Going back is a **correction**: it needs a reason and is flagged in the history.
- Recording departure, arrival or delivery on a day also sets ETD, ETA or the customer delivery date to that day,
  written to the date audit trail in the same change group.
- A shipment/container shows the least advanced status of its cargo, plus progress such as "3/5 delivered".

### Dates, delays and history

- The first known date is the baseline. `etd_delay_days`, `eta_delay_days` and `delivery_delay_days` show how far the
  current date moved (positive = later). Each audit row also stores the shift of that single change.
- "Overdue" is evaluated against today in Norway (`Europe/Oslo`): ETA passed but not arrived, or customer delivery date
  passed but not delivered. Reports accept `as_of` to look at another day.
- The shipment page merges both history tables into one timeline, grouped by change group (one user action, one import
  step or one provider sync).

### Reports

- **Upcoming deliveries** (`from`/`to`, default the next 30 days): arrivals at port by ETA, packages due at customers,
  and what is already overdue.
- **Status overview:** packages, containers and shipments per status, and package counts per destination and customer.
- **Delayed:** shipments whose ETD/ETA slipped at least `min_days` or that are overdue at port; packages whose delivery
  date slipped or passed; and packages **at risk** (still on the way while the ETA is after the promised delivery date).

## Excel import

`POST /imports` (UI: *Excel import*, or `php artisan shipments:import <file> --company=<id>`). Columns are found by their
header text, so reordered or extra columns are fine. Required: *Batch No.*, *Container/ AWB No*, *Destination Port*,
*ETD Mumbai*, *Planned ETA*, *Current Status*; otherwise the run fails with a clear message and nothing is saved.

- Rows on the same **voyage** (shipping method, line, destination, ETD, planned ETA) become one shipment, rows with the
  same container number on that voyage one container, and every row one cargo item.
- **Planned ETA** is the baseline; *Arrival date/Actual ETA Port* becomes the current ETA (logged as a change, so the
  delay is visible). *Customer approved del date* is the delivery baseline, *Cust Delivery date* the current date.
- The **status history** is rebuilt from the sheet's dates: in transit at ETD, arrived at the actual ETA, in storage at
  the storage date, delivered at the delivery date.
- Packages that already exist are skipped (warning if the stored data differs), so the same file can be imported again.
  A dry run goes through every step and rolls back. Each run is kept with its report and the uploaded file.

Normalisation:

| Data in the sheet | Result |
|---|---|
| `In Stock`, `In stock`, `In Storage`, `In storage` | `in_storage` (original text kept as `raw_status`) physically the same; unsold stock is Suppli's concern |
| `Sold` | `in_storage` with a warning (order status belongs to Suppli) |
| `Yet to Sail`, `In Transit`, `Arrived`, `Delivered` | `planned`, `in_transit`, `arrived`, `delivered` |
| Tag `2606018` / `GLS - 2511017` / ` -` | `GNP-2606018` / `GLS-2511017` / empty (searchable with or without prefix) |
| `Net cage`, `Repair panel`, `Lice shield`, `Bird net` | `Net Cage`, `Repair Panel`, `Lice Shield`, `Bird Nets` |
| Customer `Garware Technical Fibres AS` vs `GARWARE TECHNICAL FIBRES AS` | most frequent spelling |
| Container type `AIR` | empty (air waybill, no container) |
| Numbers like `862600578.0`, NBSP/extra spaces, `-`, `?`, empty cells | clean strings / empty |
| Dates as Excel serials or text (`27.06.2026`, `2026-06-27`, `27/06/2026`) | dates; unreadable optional dates become empty with a warning |

**Rejected rows** (listed with row number and reason): missing package number, missing or invalid container/AWB number
(check digit, or a number that does not fit the shipping method), missing destination, missing/invalid ETD or planned
ETA, ETD after ETA, arrival before ETD, unknown status, duplicate package in the file.
**Warnings** (row imported): unreadable optional date, missing product code/customer/invoice, conflicting actual ETA
within a voyage (the date most rows agree on wins), delivery date before ETD, package already imported with other data.

Result for the attached *Norway delivery plan Template.xlsx*: **180 rows → 178 packages, 40 shipments, 66 containers,
0 rejected, 2 blank rows, 7 warnings** - row 59 `?` as delivery date; row 101 actual ETA 18.05 vs 19.05 on the rest of
its voyage; rows 103, 105, 113, 118 and 121 without material code.

## Companies, users and roles

| Role | Can |
|---|---|
| `super_admin` | Manage companies and the users of every company (`/admin/users`); work in any company by choosing it (header `X-Company-Id`) |
| `company_admin` | Everything a company user can, plus users, API clients and webhooks of the own company |
| `company_user` | Shipments, status and dates, import, lookup, reports of the own company |
| API client | Service account of a company (no password); token abilities `write` and `references:write` |

Users are deactivated, never deleted, so the history always shows who made a change. Deactivating a user or a company
revokes its tokens immediately. A company always keeps at least one active admin.

## Webhooks

Status and date changes are pushed to the URLs registered under *API clients → Webhooks* (`/api/v1/webhooks`): one
signed event per change and kind (`shipment.status_changed`, `shipment.dates_changed`). The delivery is written in the
same transaction as the change and sent by the `worker` container. Failures are retried after 1 min, 5 min, 30 min, 2 h,
6 h, 12 h and 24 h, then marked failed. The delivery log shows the receiver's answer for each one and can send it again;
*Send test* checks a URL. Excel imports send no webhooks. Payload, headers and how Suppli checks the signature:
[`docs/suppli-integration.md`](docs/suppli-integration.md#3-how-the-service-notifies-suppli-about-status-and-date-changes).

Webhook URLs must be public HTTPS addresses. With `APP_ENV=local` (as in the compose file) plain HTTP and local hosts
are allowed too (`WEBHOOKS_ALLOW_INSECURE_URLS`), so a receiver on this machine works at
`http://host.docker.internal:<port>/…`; a request-inspection service such as webhook.site shows real deliveries.

## Tracking providers (bonus)

`App\Tracking\TrackingProvider` is the seam for status and dates that come from outside: a container-tracking API for
the sea leg, or Shipmondo for the last mile from storage to the customer. `TrackingSyncService` applies provider updates
through the same status and date services as manual changes (source `provider`): never backwards, idempotent, and steps a
provider did not report (e.g. departure when only the arrival is reported) are recorded as implied without moving dates.

`FakeTrackingProvider` is used in the tests (scripted updates) and in the demo (it reports overdue in-transit shipments
as arrived on their ETA). Try *Sync tracking* on a shipment page, or `php artisan tracking:sync` (scheduled hourly).
A real provider is one class implementing the interface plus an entry in `config/tracking.php`.

## Development

Live-mount the backend (vendor stays in the container):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec api php artisan test
```

PostgreSQL is published on `localhost:5432` (this machine only) for a database client such as DBeaver, pgAdmin or
TablePlus. The database name, user and password are in `docker-compose.yml`; the tests use the `shipments_test`
database on the same server. If port 5432 is taken, set another one, e.g. `DB_HOST_PORT=5433` in a `.env` file next to
`docker-compose.yml`.

The queue worker keeps the code it started with: after backend changes run `docker compose restart worker`.

Frontend with hot reload (Node 22+), proxying `/api` to the Docker stack on :8080:

```bash
cd frontend && npm install && npm run dev
```

## Assumptions

- One row of the delivery plan is one package (`Batch No.` = packing-slip package number, unique per company). A net pen
  and its dead fish collector share a tag number; the tag is a reference, not the package identity.
- Status and customer delivery date are kept per package (the sheet mixes *Delivered* and *In storage* inside one
  container); containers and shipments show the least advanced status.
- ETD/ETA belong to the voyage (shipment). A container that misses its vessel would be moved to another shipment.
- Calendar dates are timezone-free; "today" is evaluated in Europe/Oslo; timestamps are stored in UTC.
- Columns of the sheet that are not shipping data (certificates, customs, transport prices, NOFI service, SAP/Tripletex
  invoicing) are not imported; *Comments/ site* becomes the package note.
- The service never reads Suppli's database; Suppli pushes references and reads changes through the API.

## Production notes

The compose file is a ready-to-run demo. For production: set `APP_ENV=production`, `APP_DEBUG=false`, your own
`APP_KEY` (it also encrypts the webhook secrets; the compose file ships a demo key) and real database credentials;
disable `SEED_ON_START` and create the first super admin with `php artisan users:create-super-admin <email>`; terminate
HTTPS in front of nginx; set `opcache.validate_timestamps=0`; keep the queue worker running (`php artisan queue:work`,
the `worker` service) and run the scheduler (`php artisan schedule:work`: tracking sync hourly, overdue webhook
deliveries every five minutes); back up the database and the `import-files` volume.
