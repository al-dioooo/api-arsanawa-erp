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

- Authentication has been started under `app/Modules/Authentication`.
- `routes/api.php` is enabled in `bootstrap/app.php`.
- A custom `auth:api` bearer-token guard is registered via `Auth::viaRequest`.
- `auth_access_tokens` stores hashed access tokens.
- `users.username` supports username-based login.
- Forgot/reset password uses Laravel's built-in password broker.
- SMTP will be configured later; current mail behavior depends on environment mail configuration.

## Open Decisions

- Whether to install and migrate to `nwidart/laravel-modules`.
- Whether to keep the custom bearer-token guard or replace it with Laravel Sanctum.
- Whether Authentication, Identity, and Organization should be separate modules immediately or split progressively.
- Exact module registry response schema for the Next.js frontend.
- Role/permission package choice, if any.
- SSO/OIDC/SAML strategy for future external identity providers.
