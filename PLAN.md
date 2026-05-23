# ERP Backend Plan

## Product Direction

- This project is an API-only Laravel backend for a Next.js frontend.
- The ERP will contain many business modules, including Inventory, Finance, POS, Accounting, and future modules.
- The frontend will have one module dashboard page that lists installed/enabled modules available to the current user.
- Authentication should work as centralized SSO for all ERP modules.

## Architecture

- Use a modular monolith first, not microservices.
- Keep one Laravel application and one deployment unit while separating code by module/domain.
- Use modules to organize business capabilities, not to create network boundaries.
- Preserve Laravel conventions unless a stronger project convention exists.
- Keep APIs versioned, using paths such as `/api/v1/...`.

## Module System

- `nwidart/laravel-modules` is the intended module library once dependency installation is explicitly approved.
- Treat `nwidart/laravel-modules` as structure/lifecycle tooling, not as a complete architecture solution.
- Do not install the package, scaffold modules, or change Composer dependencies without explicit approval.
- Modules should be organized by business capability.

Expected core/business modules:

- `Authentication`
- `Identity`
- `Organization`
- `Inventory`
- `Finance`
- `POS`
- `Accounting`

## Core Module Boundaries

- `Authentication` owns login, logout, access tokens, password reset, future MFA, and future external SSO/OIDC/SAML integrations.
- `Identity` owns users, user profile, user status, and identity mapping concerns.
- `Organization` owns companies, branches, memberships, and module entitlements.
- Business modules should not freely query another module's internal tables/models/services.
- Prefer explicit contracts, application services, actions, events, or API resources for cross-module interactions.

## Authentication Direction

- The current authentication module is an internal identity provider for ERP modules.
- Credentials are username/email plus password.
- One login should grant access across ERP modules according to permissions and entitlements.
- Do not implement separate login flows per module.
- Password reset must exist even before SMTP is configured.
- Password reset links should point to the Next.js frontend via `FRONTEND_URL`.
- The first implementation uses bearer tokens stored hashed in the database without adding external dependencies.
- Sanctum or a dedicated OIDC/JWT approach can be considered later if approved.

Implemented authentication endpoints:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`

## Authorization

- Use centralized module-aware permissions.
- Permission names should be granular and scoped by module.

Examples:

- `inventory.view`
- `inventory.create`
- `finance.approve-payment`
- `accounting.post-journal`

- Frontend module visibility should be based on the authenticated user's permissions and module entitlements.
- The module registry endpoint must be permission-aware and cacheable.

## Multi-Company ERP Requirements

- Design business data for multi-company usage from the start.
- Business tables should consider `company_id`.
- Add `branch_id` when data is branch-specific.
- Add `created_by` and `updated_by` where auditability matters.
- Avoid adding company/branch scoping later as an afterthought.

## Performance Rules

- Avoid filesystem scanning during normal requests.
- Keep module metadata cacheable.
- Support `route:cache`, `config:cache`, and optimized Composer autoloading.
- Use pagination for list endpoints.
- Add indexes for columns used in filters, joins, ordering, and tenant/company scoping.
- Use eager loading to avoid N+1 queries.
- Use API Resources for response shape.
- Use queues for heavy work such as posting journals, stock reconciliation, closing periods, reports, and exports.
- Avoid writing token audit fields on every request; throttle `last_used_at` updates or defer audit logging.

## API Response Convention

- Every API response must follow a uniform envelope structure.
- All controllers must use the `ApiResponse` trait (inherited from the base `Controller`).
- The response shape is:

```json
{
  "message": "Human-readable status message",
  "data": { ... } | null
}
```

- `message` is always present and describes the outcome.
- `data` contains the response payload. When there is no payload, `data` is `null`.
- Use `$this->success($data, $message)` for 2xx responses.
- Use `$this->error($message, $status)` for error responses.
- Validation errors from `FormRequest` follow Laravel's default `{ message, errors }` shape.

## Current Implementation Notes

- Authentication, Identity, and Organization are implemented under `app/Modules`.
- `routes/api.php` is enabled in `bootstrap/app.php`.
- A custom `auth:api` bearer-token guard is registered via `Auth::extend` and
  `Authentication\Guards\AccessTokenGuard`.
- `auth_access_tokens` stores hashed access tokens.
- `users.username` supports username-based login.
- Forgot/reset password uses Laravel's built-in password broker.
- `GET /api/v1/modules` is Organization-backed and reads company module entitlements.
- SMTP will be configured later; current mail behavior depends on environment mail configuration.
- Phase 0 (Foundation Hardening) is complete: a `Platform` module owns `currencies` and a
  company/branch-scoped `settings` store (`SettingsManager`); `config/permissions.php` +
  `App\Support\PermissionCatalog` + `php artisan permission:sync` drive a permission
  registry; companies manage custom roles via `/organization/companies/{company}/roles`;
  branch-level role scoping uses the `branch_user` table, the `BranchPermission` service,
  and the `X-Branch-Id` header resolved by `SetCurrentCompany`.
- Default data is seeded by `CurrencySeeder`, `PermissionSeeder`, and `DemoCompanySeeder`.
- Phase 1 (Partners) is complete: the shared `Partners` module owns `partners`,
  `partner_contacts`, and `partner_addresses` (company-scoped customers/suppliers with
  contacts and addresses), exposed under `/api/v1/partners` and gated by `partners.*`
  permissions. Inventory and Finance reference partners through this module.
- Phase 4 (POS) is complete: `app/Modules/Pos` owns registers, cashier shifts, sales,
  sale lines, split-tender payments, applied promotions, catering confirmation,
  completion/void integration, and sales/shift reports. Completion consumes Inventory
  FIFO stock through `StockService` and posts balanced revenue and COGS journals through
  Finance `PostingService`. `PosDemoSeeder` seeds the SEKALORI demo register and the
  `cogs` / `inventory_asset` account mappings.

## Resolved Decisions

- `spatie/laravel-permission ^7.4` chosen for roles/permissions. Guard name is always `api`.
- Authentication, Identity, and Organization are separate modules (implemented separately).
- Custom bearer-token guard kept (Sanctum migration deferred).
- Product categories are a user-defined free-depth tree, company-scoped — one
  self-referencing `categories` table, not a hardcoded taxonomy.
- Discount & Reward are company/branch-scoped and toggled via the Organization
  entitlement feature flag, not built as standalone modules.
- A shared `Partners` module owns customers, suppliers, and contacts; Inventory and
  Finance reference it via contracts.
- Role assignments will be scoped per branch (not only per company).
- A Configurations/settings table (company + module scoped) will be added in Phase 0.
- Build order is the five-phase roadmap in `walkthrough.md` (Foundation → Partners →
  Inventory → Finance → POS → Bank VA).

## Open Decisions

- Whether to install and migrate to `nwidart/laravel-modules`.
- Exact module registry response schema for the Next.js frontend.
- SSO/OIDC/SAML strategy for future external identity providers.
- Whether Purchasing (PO) folds into Inventory or becomes a separate Procurement module.

---

## Codex Update — 2026-05-19

**Agent:** Codex

- Organization is now implemented under `app/Modules/Organization` using the existing
  custom module convention, not `nwidart/laravel-modules`.
- Organization owns `companies`, `branches`, `memberships`, and `module_entitlements`.
- Spatie teams are enabled; `team_id` is the active company ID and maps to
  `Organization\Models\Company`.
- Authenticated routes now run the Organization company-context middleware so permissions
  are evaluated against the active company membership.
- `GET /api/v1/modules` is no longer the config stub; it reads company entitlements and
  filters visibility by permissions.
- The module-registry entitlement gate decision is resolved for the current backend:
  module visibility is Organization-backed and company-scoped.
- Verification by Codex: `vendor/bin/pint --dirty --format agent`; `php artisan test`
  passed with 44 tests and 188 assertions.
