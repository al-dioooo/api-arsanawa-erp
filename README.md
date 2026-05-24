# Arsanawa ERP API

API-only Laravel backend for Arsanawa ERP, a modular multi-company ERP platform. This
service owns authentication, company context, module entitlements, permissions, and the
business APIs consumed by the web console and future module frontends.

## What is included

- Custom bearer-token authentication through `auth:api`
- Company and branch context on authenticated requests
- Spatie permissions using the `api` guard
- Module entitlement registry per company
- Current backend modules:
  - Authentication
  - Identity
  - Organization
  - Platform
  - Partners
  - Inventory
  - Finance
  - POS

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm, for Laravel asset tooling only
- PostgreSQL

The exact framework and package versions are pinned in `composer.json` and
`package.json`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

Configure `.env` for your local database before running migrations:

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=arsanawa_erp
DB_USERNAME=postgres
DB_PASSWORD=
```

## Running locally

```bash
php artisan serve
```

The API is served at:

```text
http://localhost:8000/api/v1
```

The combined Laravel development script is also available:

```bash
composer run dev
```

That starts the HTTP server, queue listener, log tailing, and Vite process defined in
`composer.json`.

## Tests

Run the full backend test suite before finishing any API change:

```bash
php artisan test
```

Useful focused commands:

```bash
php artisan test tests/Feature/AuthenticationApiTest.php
php artisan test --filter="token expiry"
```

This project uses Pest feature tests. New behavior should be covered by a failing test
before implementation.

## Authentication

Login accepts either email or username in the `login` field:

```http
POST /api/v1/auth/login
Accept: application/json
Content-Type: application/json
```

```json
{
  "login": "admin@example.com",
  "password": "password",
  "device_name": "Local browser"
}
```

Authenticated requests use:

```http
Authorization: Bearer {token}
X-Company-Id: {company_id}
X-Branch-Id: {branch_id}
Accept: application/json
```

`X-Company-Id` is required once the user belongs to more than one company or when a
company-scoped endpoint needs an explicit context. `X-Branch-Id` is used by branch-scoped
operations.

## Response shape

Successful API responses use the shared envelope:

```json
{
  "message": "Human-readable message.",
  "data": {}
}
```

Validation errors use Laravel's default validation shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {}
}
```

## Route groups

All API routes are prefixed with `/api/v1`.

| Area | Prefix | Notes |
| --- | --- | --- |
| Authentication | `/auth` | Login, password reset, current user, logout, refresh |
| Identity | `/identity` | Profile and user identity reads |
| Organization | `/organization` | Companies, branches, memberships, roles, entitlements |
| Platform | `/platform` | Settings and currencies |
| Partners | `/partners` | Customers, suppliers, contacts, addresses |
| Inventory | `/inventory` | Catalogue, stock, pricing, discounts, rewards |
| Finance | `/finance` | Accounts, periods, tax, journals, invoices, bills, payments, reports |
| POS | `/pos` | Registers, shifts, sales, payments, promotions, reports |

Use `php artisan route:list --path=api/v1` for the source-of-truth route list.

## Architecture

Modules live under `app/Modules/{ModuleName}` and follow this structure:

```text
app/Modules/{Module}/
|-- Actions/
|-- Http/
|   |-- Controllers/
|   |-- Requests/
|   `-- Resources/
|-- Models/
|-- Providers/
`-- database/
    `-- migrations/
```

Controllers stay thin. Business operations belong in action classes with one public
`execute(...)` method. Request validation belongs in FormRequest classes. API response
transforms belong in JsonResource classes.

Modules must not import another module's models directly. Use actions, events, or API
resources for cross-module contracts. `app/Models/User.php` is the only shared model
exception.

## Permissions

Permission names use the `module.action` format, for example:

- `identity.view`
- `organization.manage-members`
- `inventory.manage-products`
- `finance.post-journal`
- `pos.operate`

All permissions must use `guard_name = api`. Company scoping is implemented through
Spatie teams with the active team set from request company context.

## Documentation

- `walkthrough.md` in the workspace root tracks architecture, module status, API surface,
  and recent changes.
- `routes/api.php` is the authoritative route registration file.
- Feature tests under `tests/Feature` are the best executable examples of expected API
  behavior.
