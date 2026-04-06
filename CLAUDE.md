# Timr API — Laravel 12 REST API

Multi-tenant SaaS backend for shift scheduling and employee management. Managers create shifts and assign employees; employees view schedules and clock in/out with geofence validation.

## Quick Reference

```bash
composer run dev          # Start all dev services (server, queue, pail, vite)
composer run test         # Run test suite (clears config cache first)
php artisan test --compact --filter=TestName  # Run specific tests
vendor/bin/pint --dirty --format agent        # Format modified PHP files
```

- **Server**: http://localhost:8000
- **Database**: MySQL (SQLite `:memory:` for tests)
- **Auth**: Laravel Sanctum (token-based)
- **Testing**: Pest 4 (feature tests preferred over unit tests)
- **Formatting**: Laravel Pint (always run after modifying PHP files)
- **Monitoring**: Laravel Nightwatch

## Architecture

- **Routes**: `routes/api.php` — four groups: public, auth, manager (`/api/manager`), employee (`/api/employee`)
- **Controllers**: `app/Http/Controllers/{Auth,Manager,Employee}/` — thin controllers that delegate to services
- **Services**: `app/Services/` — business logic (ShiftService, ClockService, GeoFenceService, ShiftTemplateService, IcelandicHolidayService, SocialAuthService)
- **Models**: `app/Models/` — User, Company, Employee, Shift, EmployeeShift, ClockEntry, Location, ShiftTemplate, NotificationPreference, SocialAccount
- **Resources**: `app/Http/Resources/` — API response transformers (one per model)
- **Requests**: `app/Http/Requests/` — form request validation classes (always use these, never inline validation)
- **Policies**: `app/Policies/` — authorization (ShiftPolicy, ShiftAssignmentPolicy, ClockEntryPolicy, ShiftTemplatePolicy)
- **Middleware**: `app/Http/Middleware/` — EnsureCompanyRole, EnsureEmployee (registered in `bootstrap/app.php`)
- **Migrations**: `database/migrations/` — prefixed with date, sequential

### Route Groups

| Group | Prefix | Middleware | Purpose |
|-------|--------|-----------|---------|
| Public | `/api` | none | Calendar view, auth (login, register, forgot/reset password, OAuth) |
| Authenticated | `/api/auth` | `auth:sanctum` | Logout, get user, create company, update onboarding, social accounts |
| Manager | `/api/manager` | `auth:sanctum`, `company.role:owner,admin` | CRUD employees, shifts, assignments, clock entries, locations, shift templates |
| Employee | `/api/employee` | `auth:sanctum`, `employee` | View shifts, clock in/out, calendar subscription, notification preferences |

### Key Domain Concepts

- **Multi-company**: Users belong to many companies via pivot with role (Owner, Admin, Accountant)
- **Company scoping**: Models use global scopes to filter by authenticated user's `company_id`
- **Shift publishing**: Shifts are assigned to employees (EmployeeShift) with a `date` and `published` flag. Managers publish in bulk by date range.
- **Geofencing**: ClockService validates clock-in location against Location's `geo_fence_radius`
- **Roles**: `CompanyRole` enum — Owner, Admin, Manager, Employee. Manager routes require Owner or Admin.
- **Soft deletes**: Shifts use soft deletes; clock entries have nullable `shift_id`

## Conventions

- Use `php artisan make:*` commands with `--no-interaction` to create new files
- Use constructor property promotion (`public function __construct(public GitHub $github) {}`)
- Always add explicit return types and parameter type hints
- Use Eloquent relationships over raw queries; avoid `DB::` facade, prefer `Model::query()`
- Prevent N+1 queries with eager loading
- Use factories in tests; check existing factory states before manual setup
- Every code change must have a corresponding test
- Keep controllers thin — business logic goes in services
- Always validate via form request classes, not inline in controllers

## Development Workflow (TDD)

Follow test-driven development:

1. **Write failing tests first** — Pest feature tests covering the API endpoints or behavior being added/changed
2. **Implement API changes** — migrations, models, form requests, resources, services, controllers, routes — until tests pass
3. **Implement frontend changes** — after the backend is verified by passing tests

---

## Frontend (Next.js 16) — Cross-Reference

The frontend lives in `../timr-frontend/` and is a Next.js 16 App Router application (React 19, TypeScript). Understanding how it consumes this API is important for backend work.

### How the Frontend Calls the API

The frontend does **not** call the Laravel API directly from the browser. Instead:

1. Browser → Next.js API route (`/api/forms/*`, `/api/auth/*`, `/api/manager/*`, `/api/employee/*`)
2. Next.js API route → proxies to Laravel at `http://localhost:8000` with the `Authorization: Bearer <token>` header
3. Laravel responds → Next.js forwards the response to the browser

This proxy pattern exists so the frontend can manage httpOnly cookies for auth tokens.

### Auth Flow

1. User submits login/register form → Next.js API route validates with Zod → proxies to Laravel `/api/auth/login` or `/api/auth/register`
2. Laravel returns a Sanctum token → Next.js stores it in an httpOnly cookie AND returns it to the client (stored in localStorage)
3. Subsequent requests include the token via `Authorization: Bearer` header
4. Frontend `UserProvider` context fetches `/api/auth/user` on mount to get user profile

### What the Frontend Expects from API Responses

- JSON wrapped in `{ "data": ... }` using Laravel Resources
- Validation errors in standard Laravel format: `{ "message": "...", "errors": { "field": ["error"] } }`
- HTTP status codes: 200 (success), 201 (created), 422 (validation), 401 (unauthenticated), 403 (forbidden)

### Frontend Key Pages

| Route | Consumes API Endpoints |
|-------|----------------------|
| `/login`, `/register` | `POST /api/auth/login`, `POST /api/auth/register` |
| `/dashboard` | `GET /api/auth/user`, onboarding endpoints |
| `/dashboard/shifts` | Manager shift CRUD, assignments, publish/unpublish |
| `/dashboard/employees` | Manager employee CRUD |
| `/dashboard/punch-clock` | Employee clock in/out |
| `/dashboard/time-entry` | Manager clock entry list, summary, export |

### Frontend Conventions That Affect API Design

- **Icelandic locale**: The app is localized for Icelandic users. Duration format is `Xklst Ymin`.
- **Zod validation**: Frontend validates forms before sending — but backend must still validate independently
- **Shift templates**: Support patterns like "2-2-3", "5-5-4", "5-2", "4-3", and custom
