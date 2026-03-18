# CLAUDE.md - Comere AI Assistant Guide

## Project Overview

**Comere** is a multi-tenant B2B credit management SaaS (fiado/installment system) targeting small-to-medium Brazilian businesses. Companies use it to offer credit to their clients, track transactions, manage orders, and provide a self-service client portal. It also includes a **POS/Table management system** for dining/restaurant businesses.

**Phase 1 MVP status:** ~85% complete (as of Mar 2026)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 10, PHP 8.4 (Sail) |
| Admin Panel | Filament 3.3 |
| API Auth | Laravel Sanctum 3.3 |
| Frontend | React 19.x + Inertia.js 2.x |
| Styling | Tailwind CSS v4 + Vite 6.x |
| Animations | Framer Motion 12.x |
| Icons | Lucide React |
| Client Auth | Clerk React (SSO) |
| Database | MySQL 8.4 (Eloquent ORM) |
| Cache | Redis |
| Payments | Stripe (`stripe/stripe-php ^10.0`) |
| Push Notifications | Web Push API (`minishlink/web-push ^10.0`) |
| Dev Env | Laravel Sail (Docker) |
| Testing | PHPUnit 10.x |
| Formatter | Laravel Pint |

---

## Directory Structure

```
app/
├── Filament/
│   ├── Admin/Resources/        # Tenant-aware admin panel (/admin)
│   │   ├── ClientResource/
│   │   ├── DeliveryFeeRangeResource/
│   │   ├── FavoredTransactionResource/
│   │   ├── OrderResource/
│   │   ├── ProductResource/
│   │   ├── TableResource/          # Dining table management
│   │   ├── TableSessionResource/   # Active session management
│   │   └── UserResource/
│   ├── Master/Resources/       # Super-admin panel (/master)
│   │   ├── BillingCycleResource/
│   │   └── CompanyResource/
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # Admin-only controllers
│   │   │   └── TableQrController   # QR code PNG proxy for tables
│   │   ├── Api/Client/         # Sanctum-protected client portal API
│   │   │   ├── CreditController
│   │   │   ├── NotificationController
│   │   │   ├── OrderController
│   │   │   ├── PaymentController
│   │   │   └── ProductController
│   │   ├── Auth/               # Admin login/logout
│   │   └── Marketplace/        # Public marketplace + SSO + POS controllers
│   │       ├── ClientAddressController
│   │       ├── MarketplaceController
│   │       ├── MarketplaceLoginController
│   │       ├── SSOCallbackController
│   │       └── TableController     # POS table lifecycle
│   └── Middleware/             # 12 middleware classes
├── Models/                     # 22 Eloquent models
├── Services/                   # 3 service classes
│   ├── BillingService.php
│   ├── DistanceService.php     # Haversine geolocation + delivery fee lookup
│   └── PushNotificationService.php
└── Providers/
    ├── Filament/
    │   ├── AdminPanelProvider.php   # path: /admin, id: admin
    │   └── MasterPanelProvider.php  # path: /master, id: master
    └── ...

routes/
├── web.php                     # Marketplace, SSO, table, admin login routes
├── api.php                     # Base API routes
└── api_favored.php             # Fiado/credit transaction API routes

resources/
├── js/
│   ├── Pages/
│   │   └── Marketplace/        # Index, Show, Orders, CompleteProfile
│   ├── Layouts/
│   │   └── MarketplaceLayout.jsx
│   ├── app.jsx                 # Inertia.js entry point
│   └── bootstrap.js            # Axios setup
└── views/
    ├── filament/               # Filament customizations
    │   ├── push-notification-init.blade.php
    │   ├── forms/components/delivery-range-map.blade.php
    │   ├── infolists/components/order-location.blade.php
    │   └── resources/table/
    │       └── qr-modal.blade.php  # QR code modal with PNG export
    └── table/                  # POS table Blade views
        ├── show.blade.php
        └── occupied.blade.php

database/
├── migrations/                 # 37 migration files
├── seeders/
└── factories/

docs/                           # PRD documents
.agent/skills/                  # Extended AI skill guides
```

---

## Essential Commands

### Development Environment
```bash
./vendor/bin/sail up -d              # Start Docker containers (recommended)
./vendor/bin/sail down               # Stop containers
./vendor/bin/sail bash               # Shell into app container

composer install                     # PHP dependencies
npm install                          # Node dependencies
```

### Code Quality (MANDATORY before commits)
```bash
./vendor/bin/pint                    # Format all PHP code — ALWAYS run before committing
```

### Testing
```bash
./vendor/bin/phpunit                              # All tests
./vendor/bin/phpunit tests/Unit                  # Unit tests only
./vendor/bin/phpunit tests/Feature               # Feature tests only
./vendor/bin/phpunit --filter test_method_name   # Single test method
```

### Database
```bash
php artisan migrate                  # Run pending migrations
php artisan migrate:fresh --seed     # Full reset + seed (dev only)
```

### Frontend
```bash
npm run dev                          # Vite watch mode
npm run build                        # Production build
./vendor/bin/sail npm run dev        # Via Sail
```

### Cache Clearing
```bash
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
```

---

## Pre-commit Checklist

**MANDATORY: After every code change, always run in this order:**

1. `./vendor/bin/sail exec laravel.test ./vendor/bin/pint` — fix all PHP formatting issues (never skip)
2. `./vendor/bin/sail exec laravel.test ./vendor/bin/phpunit --stop-on-failure` — all tests must pass
3. Verify no `.env` or secrets committed
4. Confirm all new DB tables have `company_id` for tenant isolation
5. Verify multi-tenant scoping for any new queries

> If Sail is not running, use `./vendor/bin/pint` and `./vendor/bin/phpunit` directly.

---

## Architecture: Multi-Tenancy

**This is the most critical architectural constraint.** Every resource belongs to a `Company` (tenant).

- All tenant-scoped tables have a `company_id` foreign key with cascade delete
- All queries must be filtered by `company_id` — never return data across tenants
- Current company context: `auth()->user()->companies->first()->id`
- Models auto-set `company_id` in the `boot()` method
- Filament Admin routes include `{tenant}` (company UUID) parameter
- Client-company relationship uses the `client_company` pivot table (`client_id`, `company_id`, `is_active`)
- `products_categories` is **global** (not tenant-scoped) — no `company_id`

---

## Filament Panels

### Admin Panel (`/admin`)
- **Provider:** `AdminPanelProvider` — id: `admin`, path: `/admin`
- **Tenant-aware:** Company (slug: uuid), dark theme, Rose/Indigo colors
- **Resources:** `ClientResource`, `ProductResource`, `OrderResource`, `FavoredTransactionResource`, `UserResource`, `TableResource`, `TableSessionResource`, `DeliveryFeeRangeResource`

### Master Panel (`/master`)
- **Provider:** `MasterPanelProvider` — id: `master`, path: `/master`
- **Access:** Only users with `is_master = true`
- **No tenant scope** — manages all companies globally
- **Resources:** `CompanyResource` (create/list companies, creates admin user on company creation), `BillingCycleResource`
- **Colors:** Violet

---

## Code Conventions

### PHP / Laravel
- **Formatter:** Laravel Pint (enforced) — 4 spaces, UTF-8, LF line endings
- **Classes:** `PascalCase`
- **Methods/Variables:** `camelCase`
- **Constants:** `UPPER_SNAKE_CASE`
- **DB tables/columns:** `snake_case`
- **Route keys:** All models use UUID — implement `getRouteKeyName(): string { return 'uuid'; }`

### Import Order (strict)
```php
// 1. Framework imports
use Illuminate\Database\Eloquent\Model;

// 2. Package imports
use Laravel\Sanctum\HasApiTokens;

// 3. App imports
use App\Models\Company;
```

### Model Template
```php
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'company_id', 'amount', 'description'];

    protected $casts = ['amount' => 'decimal:2', 'created_at' => 'datetime'];

    public function getRouteKeyName(): string { return 'uuid'; }

    // Relationships, scopes, etc.
}
```

### Migration Template
```php
Schema::create('table_name', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('company_id')->constrained()->onDelete('cascade'); // REQUIRED
    // ... columns
    $table->timestamps();
});
```

### API Response Format
```json
{
  "success": true,
  "data": {},
  "message": "Description"
}
```

### React / JavaScript
- Functional components with hooks only (no class components)
- `useState`/`useReducer` for local state
- Axios with async/await for HTTP calls
- Tailwind CSS v4 utility classes for all styling
- Inertia.js for server-side routing integration
- Framer Motion for animations

---

## Authentication Architecture

| User Type | Method | Guard |
|---|---|---|
| Master Admin | Email/password + `is_master=true` | `auth` (web guard) |
| Company Admin | Email/password | `auth` (web guard) |
| Client (portal) | CPF/CNPJ | `auth:client` guard |
| Client (SSO) | Clerk JWT | SSO callback |
| API | Sanctum tokens | `auth:sanctum` |

- Account lockout: 5 failed attempts → 30-minute lock (Client model)
- Session-based tenant tracking via `selected_tenant_id`
- `is_master` boolean on `users` table gates access to `/master` panel

---

## API Endpoints (Client Portal — `auth:sanctum`)

```
# Products
GET  /api/client/companies/{company}/products
GET  /api/client/companies/{company}/products/{product}
GET  /api/client/companies/{company}/categories

# Orders
GET  /api/client/companies/{company}/orders
GET  /api/client/companies/{company}/orders/{order}
POST /api/client/companies/{company}/orders

# Credit (Fiado)
GET  /api/client/companies/{company}/client/credit-balance
GET  /api/client/companies/{company}/client/transaction-history
GET  /api/client/companies/{company}/client/upcoming-payments

# Payments (Stripe)
POST /api/client/companies/{company}/payments/create-intent
POST /api/client/companies/{company}/payments/confirm
GET  /api/client/companies/{company}/payments

# Notifications
GET  /api/client/notifications
GET  /api/client/notifications/{notification}
POST /api/client/notifications/{notification}/read
POST /api/client/notifications/mark-all-read
GET  /api/client/notifications/unread-count

# Fiado Transactions (auth:sanctum)
GET  /api/favored-transactions
POST /api/favored-transactions
GET  /api/favored-transactions/clients-with-transactions
GET  /api/favored-transactions/{client:uuid}
PUT  /api/favored-transactions/{transaction}
DELETE /api/favored-transactions/{transaction}
POST /api/favored-transactions/{transaction}/pay
```

---

## Web Routes (Marketplace + POS)

```
GET  /                              # Marketplace index
GET  /store/{company:uuid}          # Company store
POST /marketplace/login             # Client CPF/CNPJ login
POST /marketplace/logout
POST /sso-callback                  # Clerk SSO
GET  /complete-profile              # Profile completion
POST /complete-profile
GET  /meus-pedidos                  # My orders [auth:client]
POST /store/{company:uuid}/orders   # Create order [auth:client]

# Client Addresses [auth:client]
GET    /addresses
POST   /addresses
PUT    /addresses/{clientAddress:uuid}
PATCH  /addresses/{clientAddress:uuid}/default
DELETE /addresses/{clientAddress:uuid}

# POS / Table Management (public — device-locked via cookie)
GET  /table/{uuid}                  # View table (QR code entry point)
POST /table/{uuid}/open             # Open session with guest name
POST /table/{uuid}/name             # Register guest name
POST /table/{uuid}/item             # Add item to active session

# Admin [auth]
GET  /admin/table/{uuid}/qr-image   # Proxy QR image (used by PNG export canvas)
POST /push/subscribe
POST /push/unsubscribe

GET  /login                         # Admin login
POST /login
POST /logout
```

---

## POS / Table System

The table system lets restaurant/bar businesses manage dining tables via QR codes. Guests scan a QR code to open a session and order directly from their phone.

### Flow
1. Admin creates tables in Filament → `TableResource`
2. Admin opens "QR Code" modal → views the QR and downloads it as a PNG
3. Guest scans QR → arrives at `/table/{uuid}`
4. First guest entering name claims the session (cookie device lock)
5. Other devices see "table occupied" page
6. Guest adds items from the product catalog
7. Admin closes the session via `TableSessionResource` → an `Order` is created automatically
8. Payment method is recorded on close

### Device Locking
- UUID cookie `table_device_{uuid}` locks the session to one device
- Admin-opened sessions (no device token) are claimed by the first arriving guest

### QR Code PNG Export
- The `qr_code` action in `TableResource` opens a Filament modal
- Modal content is `resources/views/filament/resources/table/qr-modal.blade.php`
- Alpine.js canvas renders: table name (bold 34px) + "Escaneie para pedir" + QR image (400×400)
- Image is proxied via `/admin/table/{uuid}/qr-image` (same-origin → no CORS issues)
- Download filename: `qr-{slug}.png`

---

## Core Models (22 Total)

| Model | Key Relationships | Notable Fields |
|---|---|---|
| `Company` | belongsToMany Users, hasMany Products/Orders/Transactions/Tables | uuid, metadata (JSON), active, address fields, latitude/longitude |
| `User` | belongsToMany Companies (`companies_users` pivot) | uuid, is_master (boolean) |
| `Client` | belongsToMany Companies (`client_company` pivot), hasMany Orders/Notifications/Addresses | uuid, document_type, document_number, clerk_id, locked_until |
| `ClientAddress` | belongsTo Client | uuid, zip, street, city, state, latitude, longitude, is_default |
| `Product` | belongsTo Company, belongsTo ProductsCategories, hasMany Transactions | uuid, is_for_favored, favored_price, isCool, active |
| `ProductsCategories` | hasMany Products | Global — no company_id |
| `Order` | belongsTo Company, belongsTo Client (nullable), hasMany OrderItems | uuid, status, subtotal/discount/fee/total, payment_method, channel |
| `OrderItem` | belongsTo Order, belongsTo Product | uuid, unit_price, discount_percent |
| `Transaction` | belongsTo Company, belongsTo Product, belongsTo Fee | uuid, type, payment_method |
| `FavoredTransaction` | belongsTo Company/Client/Order/Product | uuid, due_date, favored_total, favored_paid_amount |
| `FavoredDebt` | belongsTo Company | amount, due_date, status |
| `Fee` | belongsTo Company, hasMany Transactions | uuid, type, amount |
| `Notification` | belongsTo Company | uuid, client_user_id, type, read_at |
| `CompaniesUsers` | Pivot: belongsTo User, belongsTo Company | user_id, company_id |
| `Table` | belongsTo Company, hasMany Sessions | uuid, name, seats, is_active |
| `TableSession` | belongsTo Company/Table/Client, hasMany Items | uuid, status, guest_name, device_token, opened_at, payment_method |
| `TableSessionItem` | belongsTo TableSession, belongsTo Product | product_name, quantity, unit_price, total_amount |
| `DeliveryFeeRange` | belongsTo Company | max_km, fee, is_active |
| `BillingCycle` | (Master-scoped) | Billing period management |
| `BillingSetting` | (Master-scoped) | Billing configuration |
| `PushSubscription` | belongsTo User | endpoint, keys (JSON) |

### Order Status Flow
`pending → processing → shipped → delivered` (cancellation supported at any stage)

### Notification Types
`order_update` · `payment_reminder` · `credit_warning` · `announcement`

### Order Channels
`online` · `presential`

### Payment Methods
`cash` · `debit` · `credit` · `pix`

---

## Database Tables (37 Migrations)

| Table | Tenant-scoped |
|---|---|
| users | No |
| companies | No |
| companies_users | No (pivot) |
| clients | No |
| client_company | No (pivot) |
| client_addresses | No (scoped via client) |
| push_subscriptions | No (scoped via user) |
| products_categories | No (global) |
| products | Yes (`company_id`) |
| fees | Yes (`company_id`) |
| transactions | Yes (`company_id`) |
| favored_transactions | Yes (`company_id`) |
| favored_debts | Yes (`company_id`) |
| orders | Yes (`company_id`) |
| order_items | No (scoped via order) |
| notifications | Yes (`company_id`) |
| tables | Yes (`company_id`) |
| table_sessions | Yes (`company_id`) |
| table_session_items | No (scoped via session) |
| delivery_fee_ranges | Yes (`company_id`) |
| billing_settings | No (master-scoped) |
| billing_cycles | No (master-scoped) |

---

## Testing Patterns

```php
/** @test */
public function it_can_create_order_for_authenticated_client()
{
    // Arrange
    $company = Company::factory()->create();
    $client = Client::factory()->create();

    // Act
    $response = $this->actingAs($client, 'client')
        ->postJson("/api/client/companies/{$company->uuid}/orders", [...]);

    // Assert
    $response->assertStatus(201);
    $this->assertDatabaseHas('orders', ['company_id' => $company->id]);
}
```

- Always test with proper multi-tenant company context
- Test both success and failure scenarios
- Use `assertDatabaseHas` to confirm persistence
- SQLite in-memory used for test isolation (`phpunit.xml`)
- Run `./vendor/bin/pint` before committing test files

**Test files (8):**
- `tests/Feature/FavoredTransaction/FavoredTransactionApiTest.php` (335 lines)
- `tests/Feature/FavoredTransaction/FavoredTransactionResourceTest.php`
- `tests/Feature/LoginTest.php` (148 lines)
- `tests/Feature/LoginE2ETest.php`
- `tests/Unit/FavoredTransaction/FavoredTransactionTest.php` (180 lines)
- `tests/Unit/UserLoginTest.php`

---

## Error Handling

```php
try {
    $response = Http::timeout(30)->post($url, $data);
    if (!$response->successful()) {
        Log::error('API request failed', [
            'url' => $url,
            'status' => $response->status(),
            'company_id' => $company->id,  // Always include company_id in logs
        ]);
        return response()->json(['success' => false, 'message' => 'External API error'], 502);
    }
    return response()->json(['success' => true, 'data' => $response->json()]);
} catch (\Exception $e) {
    Log::error('Exception', ['message' => $e->getMessage(), 'company_id' => $company->id]);
    return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
}
```

HTTP status codes: `200`, `201`, `400`, `401`, `403`, `404`, `422`, `500`, `502`

---

## Docker Compose Services

| Service | Purpose |
|---|---|
| `laravel.test` | App container (PHP 8.4), ports 80 + 5173 |
| `mysql:8.4` | Primary database |
| `redis:alpine` | Cache / sessions |
| `meilisearch` | Full-text search |
| `mailpit` | Email testing (UI on port 8025) |
| `selenium` | Browser automation (Chromium) |

---

## Environment Variables (Key ones)

```env
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_DATABASE=comere

STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=

VITE_CLERK_PUBLISHABLE_KEY=   # Client SSO

CACHE_DRIVER=file             # Use redis in production
SESSION_DRIVER=file           # Use redis in production
QUEUE_CONNECTION=sync
```

---

## Services

### DistanceService
Haversine formula distance calculations between company and client address coordinates. Returns the applicable `DeliveryFeeRange` for a given distance, used by `MarketplaceController` when displaying delivery fees.

### BillingService
Billing cycle calculation and billing settings management for the master panel.

### PushNotificationService
Wraps `minishlink/web-push` to deliver Web Push API notifications to subscribed admin users (`PushSubscription` model).

---

## Frontend Build (Vite 6)

Code-splitting into vendor chunks:
- `react-vendor` — React + ReactDOM
- `inertia-vendor` — Inertia.js
- `motion-vendor` — Framer Motion
- `icons-vendor` — Lucide React

PWA caching (via `vite-plugin-pwa`):
- API responses: 5-minute cache
- Static assets: 30-day cache

---

## Extended Documentation

For deeper context on specific areas, see:

- `AGENTS.md` — Core development guidelines and code style reference
- `IMPLEMENTATION.md` — Phase 1 implementation summary and architecture decisions
- `docs/PRD-System-Overview.md` — Full business context and system design
- `docs/ANALISE-CODIGO.md` — Code analysis and architecture review
- `.agent/skills/laravel-development.md` — Laravel patterns
- `.agent/skills/api-development.md` — API design conventions
- `.agent/skills/filament-resources.md` — Filament admin resource patterns
- `.agent/skills/multi-tenant-development.md` — Multi-tenancy patterns
- `.agent/skills/testing-strategy.md` — Testing approaches

---

## Phase 2 Roadmap

- Client dashboard (React/Inertia)
- Payment recording & reconciliation
- Stripe webhooks
- Email notification queue
- Advanced product filtering & cart persistence
- PDF invoice generation
- Mobile PWA optimization
- WhatsApp / SMS notifications
