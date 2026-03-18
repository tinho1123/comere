# CLAUDE.md - Comere AI Assistant Guide

## Project Overview

**Comere** is a multi-tenant B2B credit management SaaS (fiado/installment system) targeting small-to-medium Brazilian businesses. Companies use it to offer credit to their clients, track transactions, manage orders, and provide a self-service client portal.

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
| Web Push | `minishlink/web-push` + VAPID |
| PWA | `vite-plugin-pwa` + Service Worker |
| Storage/Auth | `bilalbaraz/supabase-laravel` |
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
│   │   ├── FavoredTransactionResource/
│   │   ├── OrderResource/
│   │   ├── ProductResource/
│   │   └── UserResource/       # Read-only: lists users per company
│   ├── Master/Resources/       # Super-admin panel (/master)
│   │   └── CompanyResource/    # + UsersRelationManager
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Api/Client/         # Sanctum-protected client portal API (5 controllers)
│   │   │   ├── CreditController
│   │   │   ├── NotificationController
│   │   │   ├── OrderController
│   │   │   ├── PaymentController
│   │   │   └── ProductController
│   │   ├── Auth/               # Admin login/logout
│   │   ├── Marketplace/        # Public marketplace + SSO controllers
│   │   ├── FavoredTransactionController.php  # Fiado CRUD + payDebt
│   │   ├── PushSubscriptionController.php    # Web push subscribe/unsubscribe
│   │   ├── DashboardController.php
│   │   └── UsersController.php
│   └── Middleware/             # 12 middleware classes
│       ├── ClientTenantResolver.php
│       ├── RemoveTenantScopes.php
│       └── ... (10 standard Laravel middleware)
├── Models/                     # 14 Eloquent models
└── Providers/
    ├── Filament/
    │   ├── AdminPanelProvider.php   # path: /admin, id: admin
    │   └── MasterPanelProvider.php  # path: /master, id: master
    └── ...

routes/
├── web.php                     # Marketplace, SSO, admin login, push routes
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
    ├── filament/
    │   └── push-notification-init.blade.php  # Injects SW + push subscription JS
    └── ...                     # Other Blade templates

public/
└── sw.js                       # Service Worker (push events, cache management)

config/
└── webpush.php                 # VAPID public/private key config

database/
├── migrations/                 # 24 migration files
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

1. `./vendor/bin/pint` — fix all PHP formatting issues
2. `./vendor/bin/phpunit` — all tests must pass
3. Verify no `.env` or secrets committed
4. Confirm all new DB tables have `company_id` for tenant isolation
5. Verify multi-tenant scoping for any new queries

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
- **Resources:** `ClientResource`, `ProductResource`, `OrderResource`, `FavoredTransactionResource`, `UserResource`
- **Push notifications:** Blade hook injects `push-notification-init` at `panels::body.end` — auto-registers SW and VAPID subscription for logged-in admins
- **Navigation groups:** Gestão (clients, users), Vendas (orders, fiado), Produtos (products)

### Master Panel (`/master`)
- **Provider:** `MasterPanelProvider` — id: `master`, path: `/master`
- **Access:** Only users with `is_master = true`
- **No tenant scope** — manages all companies globally
- **Resources:** `CompanyResource` (create/list companies, creates admin user on company creation, has `UsersRelationManager`)
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
    // ... columns — use string() not text() for indexed/unique columns
    $table->timestamps();
});
```

> **MySQL gotcha:** Never put a UNIQUE constraint on a `text()` column — MySQL requires a key length for TEXT indexes. Use `string(N)` instead.

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

- Account lockout: 5 failed attempts → 30-minute lock (`Client::incrementLoginAttempts()`)
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

# Credit
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
```

## API Endpoints (Fiado/Favored — `auth:sanctum`, `api_favored.php`)

```
GET  /api/favored-transactions                        # All transactions for company
POST /api/favored-transactions                        # Create transaction
GET  /api/favored-transactions/clients-with-transactions  # Summary per client
GET  /api/favored-transactions/{client:uuid}          # Transactions by client
PUT  /api/favored-transactions/{transaction}          # Update transaction
DELETE /api/favored-transactions/{transaction}        # Delete transaction
POST /api/favored-transactions/{transaction}/pay      # Record partial/full payment
```

---

## Web Routes (Marketplace + Push)

```
GET  /                              # Marketplace index
GET  /store/{company:uuid}          # Company store
POST /marketplace/login             # Client CPF/CNPJ login
POST /marketplace/logout
POST /sso-callback                  # Clerk SSO
GET  /sso-callback                  # Redirect to marketplace.index
GET  /complete-profile              # Profile completion
POST /complete-profile
GET  /meus-pedidos                  # My orders [auth:client]

# Push Notifications [auth (admin users)]
POST /push/subscribe                # Register SW push subscription
POST /push/unsubscribe              # Remove SW push subscription

GET  /login                         # Admin login
POST /login
POST /logout
```

---

## Core Models (14)

| Model | Key Relationships | Notable Fields |
|---|---|---|
| `Company` | belongsToMany Users, hasMany Products/Orders/Transactions/Fees/FavoredTransactions | uuid, metadata (JSON), active (bool), is_promoted (bool) |
| `User` | belongsToMany Companies (`companies_users` pivot) | uuid, is_master (boolean) |
| `Client` | belongsToMany Companies (`client_company` pivot), hasMany Orders/Notifications | uuid, document_type, document_number, clerk_id, locked_until, preferences (array) |
| `Product` | belongsTo Company, belongsTo ProductsCategories, hasMany Transactions | uuid, is_for_favored, favored_price, isCool (bool), active (bool) |
| `ProductsCategories` | hasMany Products | Global — no company_id |
| `Order` | belongsTo Company, belongsTo Client, hasMany OrderItems | uuid, status, subtotal/discount_amount/fee_amount/total_amount, confirmed/shipped/delivered/cancelled_at |
| `OrderItem` | belongsTo Order, belongsTo Product | uuid, unit_price, discount_percent |
| `Transaction` | belongsTo Company, belongsTo Product, belongsTo Fee | uuid, type, payment_method |
| `FavoredTransaction` | belongsTo Company/Client/Order/Product/Category | uuid, due_date, favored_total, favored_paid_amount, `getRemainingBalance()`, `isFullyPaid()` |
| `FavoredDebt` | belongsTo Company | amount, due_date, status |
| `Fee` | belongsTo Company, hasMany Transactions | uuid, type, amount |
| `Notification` | belongsTo Company | uuid, client_user_id, type, read_at |
| `CompaniesUsers` | Pivot: belongsTo User, belongsTo Company | user_id, company_id |
| `PushSubscription` | belongsTo User, belongsTo Company | endpoint (string 500), public_key, auth_token |

### Order Status Flow
`pending → processing → shipped → delivered` (cancellation supported at any stage)

Model helpers: `canBeApproved()`, `canBeShipped()`, `canBeDelivered()`, `approve()`, `ship()`, `deliver()`, `recalculateTotal()`

### Notification Types
`order_update` · `payment_reminder` · `credit_warning` · `announcement`

---

## Database Tables (24 migrations)

| Table | Tenant-scoped |
|---|---|
| users | No |
| companies | No |
| companies_users | No (pivot) |
| clients | No |
| client_company | No (pivot) |
| products_categories | No (global) |
| products | Yes (`company_id`) |
| fees | Yes (`company_id`) |
| transactions | Yes (`company_id`) |
| favored_transactions | Yes (`company_id`) |
| favored_debts | Yes (`company_id`) |
| orders | Yes (`company_id`) |
| order_items | No (scoped via order) |
| notifications | Yes (`company_id`) |
| push_subscriptions | Yes (`company_id`) |

---

## PWA & Web Push Notifications

The admin panel supports web push notifications for new orders.

### Architecture
- **Service Worker:** `public/sw.js` — handles `push` events, displays notifications, handles `notificationclick`
- **Blade hook:** `resources/views/filament/push-notification-init.blade.php` — injected at `panels::body.end` via `AdminPanelProvider::boot()`
- **Config:** `config/webpush.php` — reads `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` from `.env`
- **Model:** `PushSubscription` — stores per-user/company endpoint + VAPID keys
- **Controller:** `PushSubscriptionController` — `subscribe()` / `unsubscribe()` (web middleware, `auth` guard)
- **Service:** `app/Services/PushNotificationService` — wraps `minishlink/web-push`

### PushNotificationService
```php
// Usage: inject or resolve from container
$service->notifyCompany(int $companyId, string $title, string $body, string $url = '/admin');
```
- Sends push to **all** subscriptions for a given `company_id`
- Auto-deletes invalid subscriptions (HTTP 404/410 from push endpoint)
- Uses VAPID from `config/webpush.php`

### Flow
1. Admin logs in → blade hook registers `sw.js` and requests notification permission
2. On grant → subscribes via VAPID and POSTs to `/push/subscribe`
3. Server stores subscription in `push_subscriptions` table
4. When an order is created → call `PushNotificationService::notifyCompany()` to push to all admins

### Required `.env` variables
```env
VAPID_PUBLIC_KEY=      # Generate with: php artisan webpush:vapid
VAPID_PRIVATE_KEY=
```

### Vite PWA config (`vite.config.js`)
- `VitePWA` with `registerType: 'autoUpdate'`, offline asset caching via Workbox
- API calls (`/api/*`): NetworkFirst, 5-min cache, 10s timeout
- Storage files (`/storage/*`): CacheFirst, 30-day cache
- PWA manifest: name "Comere", theme `#e11d48`, standalone display
- **Required assets (create if missing):** `public/icons/icon-192x192.png`, `public/icons/icon-512x512.png`

---

## Known Issues & Incomplete Areas

> AI assistants: be aware of these gaps before modifying related code.

### 1. `CreditController` — Missing fields on `FavoredDebt`
`CreditController::balance()` references `$debt->credit_limit` and `$debt->available_credit`, but these columns **do not exist** in the `favored_debts` table or model. The migration only created `amount`, `due_date`, `status`. Fix requires adding these columns to `favored_debts` or rewriting the balance calculation from `favored_transactions`.

### 2. `CreditController` — Non-existent `payment_status` field
`CreditController::history()` and `balance()` filter `FavoredTransaction` by `payment_status`. This column **does not exist** — `FavoredTransaction` uses `favored_paid_amount` vs `favored_total` to determine payment status. Use `isFullyPaid()` or compute it manually.

### 3. Client API routes not registered
Controllers in `app/Http/Controllers/Api/Client/` exist and are complete, but their routes (`/api/client/companies/{company}/...`) are **not registered** in any route file. They need to be added to `routes/api.php` or a dedicated `routes/api_client.php`.

### 4. `MarketplaceController` — Missing `Order` import
`MarketplaceController::orders()` references the `Order` model without importing it (`use App\Models\Order;`). Will cause a runtime error.

### 5. `FavoredDebt` — Not fully integrated
The model and table exist but credit limit management is incomplete. `FavoredDebt` is intended to track per-client credit limits per company but the columns (`credit_limit`, `available_credit`) are missing from the schema.

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
- Run `./vendor/bin/pint` before committing test files

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
| `laravel.test` | App container (PHP 8.4) |
| `mysql:8.4` | Primary database |
| `redis:alpine` | Cache / sessions |
| `meilisearch` | Full-text search |
| `mailpit` | Email testing |
| `selenium` | Browser automation |

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

VAPID_PUBLIC_KEY=             # Web push notifications (admin panel)
VAPID_PRIVATE_KEY=            # Generate: php artisan webpush:vapid

CACHE_DRIVER=file             # Use redis in production
SESSION_DRIVER=file           # Use redis in production
QUEUE_CONNECTION=sync
```

---

## Extended Documentation

For deeper context on specific areas, see:

- `AGENTS.md` — Core development guidelines and code style reference
- `IMPLEMENTATION.md` — Phase 1 implementation summary and architecture decisions
- `docs/PRD-System-Overview.md` — Full business context and system design
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
- WhatsApp / SMS notifications

### Recently Completed (Phase 1 additions)
- ✅ PWA support (`vite-plugin-pwa`, service worker, offline caching)
- ✅ Web push notifications for admin panel (VAPID + `minishlink/web-push`)
- ✅ `UserResource` in admin panel (read-only per-company user listing)
- ✅ `is_master` flag on users (Master panel access control)
- ✅ Boolean migration for `active`/`isCool` columns
- ✅ `push_subscriptions` table (per-company, per-user SW subscriptions)
