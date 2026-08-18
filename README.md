# Alo-kömek — Handyman Service (Admin Panel & Backend)

A platform for clients to search and book handyman services. Administrators manage masters, assign orders, sell access subscriptions and watch master locations in real time through a web admin panel. The service earns from master subscriptions only — the platform never pays masters and does not track their earnings. Masters and clients interact through dedicated Flutter mobile apps that talk to the versioned REST API of this same application.

---

## System Components

| Component | Technology |
|---|---|
| Admin Panel | Laravel 11 + Inertia.js v2 + Vue 3 |
| Backend API | Laravel 11 + Sanctum (`/api/v1/`) |
| Mobile Apps | Flutter (Android & iOS) — separate repositories |
| WebSocket | Laravel Reverb |
| OTP SMS Gateway | Node.js Socket.IO bridge (`socket-server/`) + Flutter gateway phone |
| Database | SQLite (default, dev) / MySQL 8 (production) |
| Maps | Self-hosted vector tiles served by Laravel from MBTiles + MapLibre GL |

---

## Tech Stack

### Backend

| Layer | Package / Version |
|---|---|
| PHP | `^8.2` required, developed on 8.3 |
| Framework | Laravel 11 |
| SPA Bridge | Inertia.js v2 (`inertiajs/inertia-laravel`) |
| API Auth | Laravel Sanctum v4 |
| WebSocket | Laravel Reverb v1 |
| Named Routes | Ziggy v2 |
| Testing | PHPUnit v10 (+ Mockery, Collision) |
| Code Style | Laravel Pint v1 |
| Static Analysis | Larastan v3 — level 6 (`phpstan.neon`) |
| API Docs | Scribe v5 — served at `/docs`, gated by `ProtectScribeDocs` |
| Dev Tooling | Laravel Boost v2, Breeze v2 (auth scaffolding), IDE Helper, Ignition |

### Frontend

| Layer | Package / Version |
|---|---|
| Framework | Vue 3 (Composition API, `<script setup>`) |
| Inertia Client | `@inertiajs/vue3` v2 |
| State Management | Pinia v3 |
| Styling | Tailwind CSS v3 (+ `@tailwindcss/forms`) |
| i18n | `vue-i18n` v11 — messages come from PHP lang files |
| Bundler | Vite 8 + `laravel-vite-plugin` |
| Realtime Client | `laravel-echo` v2 + `pusher-js` v8 (Reverb protocol) |
| Map Container | Leaflet v1.9 |
| Basemap Renderer | MapLibre GL v5 via `@maplibre/maplibre-gl-leaflet` bridge |
| Map Tiles | Served by the app itself — `TilesController` reads `storage/maps/tiles.mbtiles`; style, glyphs and sprites are static files under `public/maps/` |

### Companion Service

| Service | Purpose |
|---|---|
| `socket-server/` | Express + Socket.IO bridge. Laravel `POST`s an OTP to `/emit-otp`, the server re-emits it to the connected Flutter SMS-gateway phone. Exposes `/health` used by the system status endpoint. |

---

## Architecture & Patterns

This project enforces strict layered architecture. Every developer must follow these patterns without exception.

```
HTTP Request
    └── Controller (thin — HTTP only)
            └── Form Request (validation)
            └── Service / Action (business logic)
                    └── Repository (all DB queries)
                    └── Job (background tasks)
            └── Resource (response formatting)
```

| Pattern | Rule |
|---|---|
| **Thin Controllers** | Only handle HTTP: receive request, call action/service, return response |
| **Repository Pattern** | ALL database queries live in Repositories — never in Controllers or Services |
| **Services** | Complex multi-step business logic or external integrations (`OtpGatewayService`) |
| **Actions** | Single-purpose operations (e.g. `AssignMasterAction`, `CreditMasterBalanceAction`) |
| **Form Requests** | All validation — never `$request->validate()` in controllers |
| **API Resources** | All API responses — never return raw models or arrays |
| **Jobs** | All background/async processing (image conversion) |
| **Observers** | Model event handling (`MasterObserver`, registered in `AppServiceProvider`) |
| **Enums** | All statuses and fixed value sets — never raw strings |
| **Exceptions** | Domain failures throw `ApiException` subclasses instead of returning `false` |

### Hard Rules

```
❌ NEVER  $request->all()              ✅ USE  $request->validated()
❌ NEVER  Model::find($id)             ✅ USE  Model::findOrFail($id)
❌ NEVER  return true/false            ✅ THROW exceptions from Actions/Services
❌ NEVER  raw status strings           ✅ USE  Enums
❌ NEVER  session()->flash() directly  ✅ USE  WithNotification trait
❌ NEVER  hardcode UI text             ✅ USE  translation helpers __() / t()
```

### API Error Handling

`bootstrap/app.php` is the single source of truth for API errors: every exception raised on an `api/*` route is rendered as clean, localized JSON (validation `422`, auth `401`, forbidden `403`, not found `404`, throttle `429`, unexpected `500`). Web/Inertia routes keep default Laravel handling. Internal messages are never leaked — the raw exception text is added only when `APP_DEBUG=true`.

---

## Domain Model

```
Oblast ─┬─< Region
        └─< City ─┬─< Master ─┬─< MasterLocation      (GPS trail, one row per ping)
                  │           ├─< MasterPayout        (paid_by → User)
                  │           ├─< OrderReview
                  │           └─>< Category           (pivot: category_master)
                  ├─< Client
                  └─< Order

Category ─┬─< Category            (self-referencing parent/children)
          └─── CategoryContent ─< CategoryContentImage

Order ─┬─> City, Category, Master, Client
       ├─< OrderPhoto             (photos of the problem, client-supplied)
       ├─< OrderTask ─< OrderTaskPhoto   (before/after pair per performed task)
       ├─< MasterLocation         (the trip taken for this order)
       ├─< OrderMasterDecline     (masters who dismissed the auto-search offer)
       └─── OrderReview

Standalone: User (admin staff), Banner, Setting, PendingOtp
```

| Entity | Role |
|---|---|
| `Oblast` / `Region` / `City` | Geography. Masters, clients and orders are all scoped to a city |
| `Category` | Service catalog, self-nesting, bilingual, with an optional `CategoryContent` landing page |
| `Master` | The handyman — a profile **on top of a `Client` account** (`client_id`), never a standalone login. Carries the application (categories, `experience_years`, `about`), its review verdict (`App\Enums\MasterStatus`), access expiry derived from subscriptions, availability flag, live location |
| `Client` | Mobile app user, can be blocked by an administrator. Owns the account's avatar (`photo`) — the `Master` profile shows the same file. Optionally has one `Master` profile |
| `Order` | The job. Carries status (`App\Enums\OrderStatus`), photos, tasks, one review, and the auto-search state (`search_started_at`, `search_radius_km`, `search_expired_at`) |
| `OrderTask` | One discrete piece of work with before/after photos — e.g. "replaced hose" |
| `OrderMasterDecline` | A master dismissed an auto-search offer — hides it from that master's feed only |
| `SubscriptionPlan` | Tariff sold to masters: bilingual name, duration in days, price, soft deleted so sold subscriptions keep their link |
| `MasterSubscription` | A purchase: snapshot of plan name/price/duration, status (`App\Enums\SubscriptionStatus`), period, who issued it |
| `PendingOtp` | OTP parked for manual delivery when the SMS gateway is down |
| `Setting` | Key/value app settings exposed to both mobile apps |
| `User` | Admin panel staff — administrator / manager / operator (`App\Enums\UserRole`) |

### Enums (`app/Enums/`)

| Enum | Values |
|---|---|
| `OrderStatus` | `pending`, `assigned`, `in_progress`, `completed`, `cancelled` (+ `label()`, `color()`, `isFinal()`) |
| `UserRole` | `administrator`, `manager`, `operator` (+ `assignable()`, `canManage()`) |
| `SubscriptionStatus` | `pending`, `active`, `expired`, `cancelled` (+ `label()`, `color()`, `isFinal()`, `canTransitionTo()`) |
| `MasterStatus` | `pending`, `approved`, `rejected` — where a master application stands (+ `label()`, `color()`, `grantsAccess()`, `canTransitionTo()`) |
| `OtpDeliveryChannel` | Delivery route of a generated OTP (SMS gateway vs. manual) |
| `OtpRecipientType` | Recipient a parked OTP belongs to |
| `CategoryIconType` | Icon source for a category: `preset` (SVG from the set), `image` (uploaded WebP ≤ 50 KB), `custom` (legacy SVG, read-only) |

### Roles & Access

Enforced by the `role` middleware alias (`App\Http\Middleware\CheckRole`) in `routes/web.php`.

| Section | administrator | manager | operator |
|---|:--:|:--:|:--:|
| Profile, `/system-status` | ✅ | ✅ | ✅ |
| Dashboard, geography, categories, masters, clients, orders, banners, settings, notifications, OTP codes | ✅ | ✅ | ❌ |
| Users (`/users`) | ✅ | ❌ | ❌ |
| Subscriptions & plans (`/subscriptions`) | ✅ | ❌ | ❌ |

---

## Project Structure

```
app/
├── Actions/                    # ~55 single-purpose operations (Create/Update/Delete/Assign/…)
├── Console/Commands/
│   ├── SimulateMasterMovement.php          # master:simulate-movement — demo GPS pings
│   ├── ExpandOrderSearchRadiusCommand.php  # orders:expand-search-radius — every minute
│   └── ExpireMasterSubscriptionsCommand.php # subscriptions:expire — hourly
├── Enums/                      # OrderStatus, UserRole, SubscriptionStatus, Otp*, CategoryIconType
├── Events/                     # MasterAssigned, MasterLocationUpdated, OrderCreated,
│                               # OrderStatusChanged, PendingOtpCreated
├── Exceptions/                 # ApiException + Master/Order/Otp/Subscription domain exceptions
├── Http/
│   ├── Controllers/            # Web controllers (thin, Inertia)
│   │   ├── Api/V1/             # Master API controllers
│   │   │   └── Client/         # Client API controllers
│   │   ├── Auth/               # Breeze auth controllers
│   │   ├── TilesController.php        # /tiles/{z}/{x}/{y}.pbf from MBTiles
│   │   └── SystemStatusController.php # /system-status health JSON
│   ├── Middleware/             # CheckRole, EnsureMaster, EnsureClient, SetLocale,
│   │                           # HandleInertiaRequests, ProtectScribeDocs
│   ├── Requests/               # Form Requests (web + Api/V1 + Api/V1/Client)
│   ├── Resources/              # Eloquent API Resources
│   └── Traits/
│       └── WithNotification.php
├── Jobs/                       # ConvertOrderPhotoJob, ConvertTaskPhotoJob
├── Listeners/                  # NotifyAdminsOnNewOrder
├── Models/                     # 19 Eloquent models
├── Notifications/              # NewOrderNotification (database channel)
├── Observers/                  # MasterObserver
├── Policies/                   # UserPolicy
├── Providers/                  # AppServiceProvider (observer + queue heartbeat + processed counter)
├── Repositories/               # 14 repositories — all database query logic
├── Services/                   # OtpGatewayService, SystemStatusService, ReverbMetricsService
└── Support/                    # Framework-agnostic helpers (PhotoConverter, CategoryIcon)

resources/js/
├── Components/                 # CategoryIcon, CategoryPicker, CityFilterSelect, ConfirmModal, IconPicker,
│                               # ImageLightbox, Modal, NotificationPanel, OblastCitySelect,
│                               # Pagination, PasswordInput, PendingOtpPanel, PhoneInput,
│                               # ServiceIcon, form primitives
├── Layouts/
│   ├── AdminLayout.vue         # Sidebar + topbar, notifications, OTP alerts
│   └── GuestLayout.vue         # Login / password reset shell
├── Pages/                      # Auth, Banners, Categories, Cities, Clients, Dashboard,
│                               # Masters (Index + Map), Oblasts, Orders (Index + Show),
│                               # PendingOtps, Profile, Regions, Settings, Subscriptions, Users
│                               # (each section has a Partials/ folder with its modals)
├── stores/                     # useThemeStore, useLocaleStore, useNotificationStore
├── utils/                      # loadMapStyle.js, formatPhone.js
├── app.js                      # Inertia + Pinia + Ziggy + vue-i18n bootstrap
├── bootstrap.js                # axios defaults
├── echo.js                     # Laravel Echo + Reverb client
└── i18n.js                     # vue-i18n instance (messages injected from PHP at runtime)

lang/
├── ru/                         # api, auth, banners, categories, cities, clients, dashboard,
│                               # layout, masters, notifications, oblasts, orders,
│                               # pending_otps, profile, regions, resources, settings,
│                               # subscriptions, users, validation
└── tk/                         # Turkmen — mirrors ru/ file-for-file, key-for-key

routes/
├── web.php                     # Admin panel (Inertia) + tiles + locale switch
├── auth.php                    # Breeze auth routes
├── channels.php                # Broadcast channel authorization
├── console.php
└── api/v1.php                  # Versioned mobile API

database/
├── migrations/                 # 39 migrations
├── factories/
└── seeders/                    # Oblast, City, Category, Master, MasterLocation, Client,
                                # Order, Setting

public/
├── maps/                       # MapLibre style.json, glyphs, sprites
├── icons/
└── sounds/alarm.mp3            # Admin panel new-order / new-OTP alert sound

storage/maps/tiles.mbtiles      # Vector tile archive — NOT in git (~118 MB), copy manually

socket-server/                  # Node.js Socket.IO OTP bridge (own package.json / .env)
bruno/                          # Ready-to-run Bruno API request collection (one app, both roles)
docs/                           # MASTER_APP_SPEC.md, MASTER_APP_MAP_INTEGRATION.md, tasks/

tests/
├── Feature/                    # Feature tests (primary), incl. Api/V1 and Api/V1/Client
└── Unit/
```

---

## Local Development Setup

> **Docker**: the repository also ships a Docker environment (nginx + php-fpm 8.3 + MySQL 8).
> See [docs/DOCKER.md](docs/DOCKER.md) for the full walkthrough — setup, migrations, seeders and troubleshooting.
> The steps below describe the native (non-Docker) setup.

### Requirements

- PHP 8.2+ (8.3 recommended) with `sqlite3`, `gd`, `pdo` extensions
- Composer
- Node.js 20+
- MySQL 8 — only if you deviate from the default SQLite connection

### Steps

```bash
# 1. Clone
git clone <repo-url>
cd project

# 2. Dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database — SQLite is the default and needs no configuration
touch database/database.sqlite
php artisan migrate --seed

# 5. Storage symlink (order/task photos, banners, client avatars)
php artisan storage:link

# 6. Basemap archive (optional — only for map screens)
#    Copy tiles.mbtiles into storage/maps/ manually; it is not in git.

# 7. Frontend + web server (two terminals — there is no `composer run dev` script here)
npm run dev
php artisan serve

# 8. Queue worker — REQUIRED for image conversion, admin notifications and OTP broadcasts
php artisan queue:work

# 9. WebSocket server — REQUIRED for realtime order alerts and the live map
php artisan reverb:start

# 10. Scheduler — REQUIRED for the master auto-search radius to grow
php artisan schedule:work

# 11. OTP SMS gateway bridge (optional in dev — without it OTPs fall back to manual delivery)
cd socket-server && cp .env.example .env && npm install && npm start
```

> **Vite over LAN**: set `VITE_DEV_SERVER_HOST` to your machine's LAN IP if you open the panel from another device (`vite.config.js` reads it for both `server.host` and HMR).

---

## Environment Variables

```dotenv
# ── Application ──────────────────────────────────────────────────────────────
APP_NAME="Alo-komek"         # Shown in browser title bar and Vite (VITE_APP_NAME)
APP_ENV=local                # local | staging | production
APP_KEY=                     # Run: php artisan key:generate
APP_DEBUG=true               # Set false in production
APP_URL=http://localhost     # Full public URL (used in emails, links, Scribe)
APP_TIMEZONE=Asia/Ashgabat

# ── Localization ─────────────────────────────────────────────────────────────
APP_LOCALE=ru                # Default locale: ru or tk
APP_FALLBACK_LOCALE=ru

# ── Database ─────────────────────────────────────────────────────────────────
DB_CONNECTION=sqlite         # sqlite (default) | mysql
# DB_HOST=127.0.0.1          # Uncomment the block below when using MySQL
# DB_PORT=3306
# DB_DATABASE=handyman
# DB_USERNAME=root
# DB_PASSWORD=

# ── Queue, Cache, Session ────────────────────────────────────────────────────
QUEUE_CONNECTION=database    # Use redis in production
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# ── Storage ──────────────────────────────────────────────────────────────────
FILESYSTEM_DISK=local        # Photos are written to the `public` disk regardless

# ── WebSocket — Laravel Reverb ───────────────────────────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=               # Generated by: php artisan reverb:install
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="127.0.0.1"
REVERB_PORT=8880
REVERB_SCHEME=http           # https in production

# Injected into Vite for the frontend Echo client
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# ── Maps ─────────────────────────────────────────────────────────────────────
TILES_STYLE_URL="/maps/style.json"   # Shared to the frontend as the `tilesStyleUrl` Inertia prop
MBTILES_PATH=maps/tiles.mbtiles      # Relative to storage/

# ── OTP SMS gateway (socket-server/) ─────────────────────────────────────────
SMS_GATEWAY_URL=http://127.0.0.1:3000   # Must match PORT in socket-server/.env
SMS_GATEWAY_SECRET=changeme             # Must match GATEWAY_SECRET in socket-server/.env
OTP_TTL_MINUTES=3

# ── Dev only ─────────────────────────────────────────────────────────────────
VITE_DEV_SERVER_HOST=127.0.0.1   # LAN IP when testing from a phone
```

All custom values are exposed through `config/services.php`: `services.tiles.style_url`, `services.mbtiles.path`, `services.sms_gateway.{url,secret}`, `services.otp.ttl_minutes`. Never read `env()` outside config files — it returns `null` once configs are cached.

> **Note**: `.env.example` currently omits the `REVERB_*` / `VITE_REVERB_*` block. Copy it from the snippet above (or from a working `.env`) after `cp .env.example .env`, otherwise broadcasting silently fails.

---

## Frontend Standards

All frontend code lives in `resources/js/`. Every component uses `<script setup>` — no Options API, no class components.

### Pinia Stores

| Store | File | Purpose |
|---|---|---|
| Theme | `useThemeStore.js` | Dark/light toggle. Persists to `localStorage`. Applies `dark` class to `<html>`. |
| Locale | `useLocaleStore.js` | Active locale (`ru`/`tk`). Persists to `localStorage`, syncs vue-i18n, and `POST`s to `/locale/{locale}` so the server session matches. |
| Notifications | `useNotificationStore.js` | Toast queue. Methods: `success()`, `error()`, `warning()`, `info()`. Auto-dismiss after 6s. |

### Component Template

```vue
<script setup>
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const { t } = useI18n()
</script>

<template>
    <AdminLayout :title="t('section.page_title')">
        <!-- Single root element inside the layout slot -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ t('section.heading') }}
            </h2>
        </div>
    </AdminLayout>
</template>
```

- **Tailwind only** — no `<style>` blocks except scoped transition animations
- **Dark mode** — every visible element must have `dark:` variants
- **No hardcoded text** — every string goes through `t('key')`
- **`@` alias** resolves to `resources/js/` (configured by `laravel-vite-plugin`, mirrored in `jsconfig.json`)

---

## Localization

Both `ru` and `tk` must always be complete. A key present in one language and missing in the other is a bug.

### PHP lang files are the single source of truth

There is **no separate frontend dictionary**. `HandleInertiaRequests::loadTranslations()` reads every file under `lang/ru/` and `lang/tk/` and shares them as the `translations` Inertia prop; `app.js` pushes them into vue-i18n on boot and after every Inertia visit. `resources/js/i18n.js` only creates the empty instance.

```
lang/ru/*.php  ─┐
                ├─→ HandleInertiaRequests (Inertia prop `translations`) ─→ app.js ─→ vue-i18n `t()`
lang/tk/*.php  ─┘
```

**Rule**: to add UI text, create the key in `lang/ru/xxx.php` **and** `lang/tk/xxx.php`. Nothing else. The same key then works on the server via `__('xxx.key')` and in Vue via `t('xxx.key')`.

Laravel's `:placeholder` syntax is normalized to vue-i18n's `{placeholder}` in `app.js`, so `t('notifications.created', { resource })` works with the exact same PHP string.

```php
// lang/ru/notifications.php
return [
    'created' => ':resource успешно создан',
    'updated' => ':resource успешно обновлён',
    'deleted' => ':resource успешно удалён',
];

// lang/ru/resources.php
return ['city' => 'Город', 'master' => 'Мастер', 'order' => 'Заказ'];

// Usage (PHP)
__('notifications.created', ['resource' => __('resources.city')])
```

> Translations are cached forever in production (`inertia.translations` cache key). Run `php artisan cache:clear` after editing lang files on a production server.

### Locale resolution

`SetLocale` middleware runs on both `web` and `api` stacks, before authentication, so even a `401` comes back translated. Web requests read the locale from the session (set via `POST /locale/{locale}`), mobile apps send an `X-Locale: tk` header.

---

## Notification System

### Backend — `WithNotification` Trait

```php
use App\Http\Traits\WithNotification;

class CityController extends Controller
{
    use WithNotification;

    public function store(StoreCityRequest $request, CreateCityAction $action): RedirectResponse
    {
        $action->handle($request->validated());
        $this->notifySuccess('notifications.created', ['resource' => __('resources.city')]);

        return redirect()->route('cities.index');
    }
}
```

Available methods: `notifySuccess()` · `notifyError()` · `notifyWarning()` · `notifyInfo()`

All methods accept a lang key + optional replace array. They flash to `session('notification')`, which `HandleInertiaRequests` shares as an Inertia prop.

### Frontend — Automatic Pickup

`AdminLayout.vue` watches `$page.props.notification` and passes it to `useNotificationStore`. No per-page setup needed. Toasts appear top-right and dismiss after 6 seconds.

### Notification Bell & Panel

The topbar bell shows the unread count and opens `NotificationPanel.vue` — a slide-in drawer listing all admin notifications (mark one/all as read, delete one/all), backed by `NotificationController` at `/notifications/*`. `unreadNotificationsCount` is a shared Inertia prop, so the badge stays in sync without extra API calls.

Database notifications use Laravel's built-in `notifications` table (UUID primary key, polymorphic `notifiable`) — migration `2026_05_22_162610_create_notifications_table.php`.

---

## Image Upload Convention

Synchronous conversion is **prohibited**. Photos are stored as-is, then converted to WebP by a queued job.

```
Upload (order photo / task photo)
    └── Action stores the original on the `public` disk, row status = pending
            └── ConvertOrderPhotoJob / ConvertTaskPhotoJob (queued, 3 tries, 30s backoff):
                    ├── status → converting
                    ├── PhotoConverter::convert()  → WebP
                    ├── delete the original file
                    └── status → done  (on failure: status → failed, job retries)
```

Row status values live as constants on the photo models: `pending` · `converting` · `done` · `failed`, so the UI can show progress and never blocks on processing.

Single **profile-sized** uploads are the exception — they convert inline because there is
no status column to poll and the payload is tiny:

| Upload | Helper | Result |
|---|---|---|
| Client avatar | `PhotoConverter::convertToWidth()` | WebP, width 512 |
| Category content image | `PhotoConverter::convertContent()` | WebP, width 700 when > 800 KB |
| Category icon | `PhotoConverter::convertToMaxBytes()` | WebP ≤ 50 KB, width ≤ 512 (`CategoryIcon`) |

**One person, one avatar.** A master profile always hangs off a client account, so the
photo lives on `clients.photo` and `MasterResource` reads it through the relation —
`masters` has no photo column. It is uploaded in three places, all landing in
`StoreClientPhotoAction`: the admin clients form, `POST /client/auth/complete-registration`
and `PATCH /client/me`. The last one is multipart-only via `POST` + `_method=PATCH`,
since PHP fills `$_FILES` on `POST` alone.

`convertToMaxBytes()` steps the WebP quality down (85 → 25) and, if the budget is still
missed, shrinks the canvas by 25 % and retries — transparency is preserved throughout.

---

## Realtime (Laravel Reverb)

### Broadcast channels (`routes/channels.php`)

| Channel | Type | Who may subscribe | Carries |
|---|---|---|---|
| `masters-map.{cityId}` | public | anyone (tighten in production) | `MasterLocationUpdated` |
| `available-orders` | public | master apps | `OrderSearchStarted`, `OrderSearchRadiusExpanded` — refresh signals carrying only an order id and a radius |
| `admin.pending-otps` | private | admin staff except operators | `PendingOtpCreated` |
| `client.{clientId}` | private | the Client that owns the Sanctum token | `MasterAssigned`, `OrderStatusChanged` |
| `master.{masterId}` | private | the Master that owns the Sanctum token | `MasterAssigned`, `OrderStatusChanged` |
| `App.Models.User.{id}` | private | the admin user | Laravel database notifications |

Mobile apps authorize private channels against `POST /api/v1/broadcasting/auth` (`auth:sanctum`).

### New-order alert flow

1. A client creates an order → `OrderCreated` is dispatched
2. `NotifyAdminsOnNewOrder` sends `NewOrderNotification` to admin staff (database channel)
3. The admin panel receives the broadcast, shows a toast via `useNotificationStore.info()`
4. `public/sounds/alarm.mp3` plays — **only when the browser tab is active** (Page Visibility API), so alerts do not stack up
5. The bell badge (`unreadNotificationsCount`) increments

---

## Master Auto-Search (expanding radius)

A client order is not routed by city — it is offered to masters by **geographic distance**, in a radius that widens every minute until somebody claims it.

### How the radius grows

`radius(n) = n × initial`, where `n` is the minute of the search the order is currently in:

| Minute | Radius (defaults) |
|---|---|
| 1 | 20 km |
| 2 | 40 km |
| 3 | 60 km |
| 4 | 80 km |
| 5 | would be 100 km → **over the maximum, the search closes** |

The radius is derived from `now() − search_started_at` on every tick rather than incremented, so a missed or duplicated scheduler run cannot drift it.

Both bounds are configurable at **Settings → Авто-поиск мастера** (`master_search_initial_radius_km`, `master_search_max_radius_km`; defaults live on `App\Models\Setting`). Validation enforces `initial ≤ max`, comparing a partial submit against the stored counterpart.

### Flow

1. `CreateClientOrderAction` stamps `search_started_at = now()` and `search_radius_km = initial`, then fires `OrderSearchStarted`.
2. `orders:expand-search-radius` runs **every minute** (`bootstrap/app.php` → `withSchedule`, `withoutOverlapping`) and hands each searching order to `ExpandOrderSearchRadiusAction`.
3. On each widening → `OrderSearchRadiusExpanded` broadcasts on the public `available-orders` channel. Master apps treat both events as a "reload your feed" signal and call `GET /api/v1/master/orders/available`.
4. A master claims the order with `POST /api/v1/master/orders/{order}/respond` → `RespondToOrderAction`.
5. Once `radius(n)` would exceed the maximum → `search_expired_at` is set, `OrderSearchExhausted` fires, `NotifyAdminsOnOrderSearchExhausted` sends `OrderSearchExhaustedNotification` to admin staff, and the order shows a **«Требует ручного назначения»** badge in `/orders`. From then on masters can neither see nor claim it — only `AssignMasterAction` (administrator) can.

> **Both `schedule:work` and `queue:work` must be running.** Without the scheduler the radius never grows; without the queue worker admins never receive the exhaustion notification.

### Matching rules

A master sees an order only when **all** of these hold:

- order is `pending` with no `master_id`, and its search has not expired
- the order's category is one of the master's categories
- distance(master's last GPS ping → order) ≤ the order's current `search_radius_km`
- the master has not declined that order

**City is deliberately not part of the match** — an 80 km radius crosses city borders by design. `city_id` stays a reporting/filtering dimension in the admin panel, and manual assignment via `AssignMasterAction` still enforces `cityMismatch()`.

A master with no row in `master_locations` is excluded: `GET .../available` returns an empty list (a normal state right after login), and `respond` fails with `master_location_unknown`.

### Distance is computed in two passes

`OrderRepository::availableForMaster()` pre-filters in SQL with a **plain-arithmetic bounding box** (no `acos`/`radians`), then settles the exact circle in PHP via `Order::distanceKmTo()` (haversine) and sorts nearest-first.

This is deliberate: MySQL, PostgreSQL and the SQLite build used by the test suite disagree on which trigonometric functions exist, so a `selectRaw` haversine would tie the feature to one driver and break the tests. The candidate set the bounding box lets through is small, so the PHP pass costs nothing.

### Claiming is a race

`OrderRepository::claimForMaster()` puts the guard in the `WHERE` clause:

```php
Order::where('id', $order->id)
    ->whereNull('master_id')
    ->where('status', OrderStatus::Pending)
    ->whereNull('search_expired_at')
    ->update([...]);   // affected rows === 1 → this master won
```

Two simultaneous responders resolve to one `UPDATE` touching a row and one touching none; the loser gets `orders.errors.already_claimed`. No locks, no transaction needed.

### Declining

`POST /api/v1/master/orders/{order}/decline` writes to `order_master_declines` (unique on `order_id + master_id`, idempotent). It **only** hides the order from that master's own feed — the search keeps running and other masters still see it. A master who changes their mind can still claim it directly by id.

### Privacy

`AvailableOrderResource` is intentionally narrower than `MasterOrderResource`: an unclaimed order exposes category, description, address, coordinates and distance, but **not** `client_name` or `client_phone`. Those appear only after the claim succeeds.

The public `available-orders` channel carries nothing but an order id and a radius. `OrderCreated` — whose payload includes the client's name — stays on the admin-only `orders` channel; the master-facing signal is the separate, contentless `OrderSearchStarted`.

---

## Becoming a Master

One mobile app, one account. Everyone registers as a **client**; the master role
is applied for from inside the app and granted by an administrator.

```
client signs up (OTP)
   └── POST /api/v1/client/master-application   city, categories, experience, bio
          └── masters row, status = pending          ← grants nothing
                 └── admin reviews at /master-applications
                        ├── approve (+ subscription)  → status = approved, access opens
                        └── reject  (+ reason)        → applicant sees why, may re-apply
```

| Piece | Where |
|---|---|
| Submit / read own application | `Api/V1/Client/MasterApplicationController` → `SubmitMasterApplicationAction` |
| Admin review queue | `MasterApplicationController` → `ReviewMasterApplicationAction` |
| Gate | `App\Http\Middleware\EnsureMaster` |
| Status | `App\Enums\MasterStatus` |

Design decisions worth keeping:

- **Approval and access are separate.** Approving only flips the status; access
  comes from a subscription, which the same screen can issue in one go because
  the master pays the owner in person. An approved master with no subscription
  is a valid state — the app shows "renew", not "apply".
- **Re-applying reuses the row.** Only a `rejected` applicant may submit again;
  the verdict fields are cleared and the same `masters` row goes back to
  `pending`, so one client never accumulates several master profiles
  (`masters.client_id` is unique).
- **Losing the master role never signs anyone out.** Deactivation and rejection
  leave Sanctum tokens alone — the token belongs to the client account.
  `MasterObserver` only drops `is_available`.
- **A master entered by hand in the admin panel** still gets a client account:
  `CreateMasterAction` reuses the one matching the phone number or creates it,
  and marks the profile `approved` — an administrator entering it *is* the review.

### Deleting accounts

Because a master is a role on a client account, the two deletes are not symmetric.

| Action | Effect |
|---|---|
| Delete **master** | Removes the role only. The client account, its orders, its login and its avatar all stay |
| Delete **client** | Takes the master profile, the orders this person *placed*, their tasks, photos and reviews, and the avatar file |

Both are blocked while the master has work on the books — `DeleteMasterAction` and
`DeleteClientAction` throw `MasterException` / `ClientException`, the controller turns it
into a `notifyError`:

- **completed orders** — the client who ordered the job keeps seeing it, its review and its
  before/after photos; erasing the master would gut that history;
- **assigned / in-progress orders** — `orders.master_id` is `ON DELETE SET NULL`, so
  deleting mid-job would leave an order sitting in `assigned` with nobody assigned to it.

Cancelled orders never block anything.

**Why the children are deleted through Eloquent, not the foreign keys.** `masters.client_id`
and `orders.client_id` are both `ON DELETE CASCADE`, and a database-level cascade **never
fires model events** — the rows would vanish while their uploaded files stayed on disk
forever. `ClientObserver::deleting` therefore deletes the master profile and the client's
orders through the models, which lets `OrderObserver::deleted` drop `orders/{id}` from the
public disk in one call. The foreign keys stay as the backstop for anything that bypasses
the model (raw SQL, `->where(...)->delete()`).

## Master Subscriptions

The service owner sells masters timed access to the platform. **This is the only revenue stream** — the platform does not pay masters, does not hold a balance for them and does not track their per-order earnings. The client pays the master directly; `orders.final_price` is bookkeeping for reporting, nothing more.

### Two tables

| Table | Purpose |
|---|---|
| `subscription_plans` | The owner's tariffs: bilingual name/description, `duration_days`, `price`, `is_active`, `sort_order`. **Soft deleted** so already sold subscriptions never lose their link |
| `master_subscriptions` | A purchase. Carries a **snapshot** of `plan_name`, `price_paid` and `duration_days` — later edits to the plan must never rewrite history (same trick the old payout ledger used) |

There is no seeder for plans — the owner creates them in the admin panel.

### `access_expires_at` has exactly one writer

`Master::hasActiveAccess()` and every consumer of it (`EnsureMaster`, `EnsuresMasterEligibility`, `MasterRepository` filters) are unchanged. What changed is **who writes the column**:

- it is no longer editable by hand — `Store/UpdateMasterRequest` do not accept it;
- every subscription action funnels through `App\Actions\Concerns\SyncsMasterAccess`, which sets it to `MAX(expires_at)` across the master's `active` + `pending` subscriptions;
- with nothing left the deadline is set to `now()` — **never `null`**, because `null` means *unlimited* to `hasActiveAccess()` (legacy masters created before subscriptions keep that meaning);
- a new master created without a plan starts with access closed.

Free access is granted the same way as paid: issue a subscription with `price_paid = 0` and a note. That keeps it auditable — who granted it, when and why.

### Renewal is a queue, not an overwrite

At most one subscription per master is `active`. Selling to a master who already has a running one does **not** start from `now()` and does not error out — the new purchase is created `pending`, starting the moment the current one ends:

```
├─ active   01.01 → 31.01   ← running
└─ pending  31.01 → 02.03   ← paid for, waiting its turn
access_expires_at = 02.03   (MAX over active + pending)
```

Paid-for days are never burned, and the "one active" invariant holds.

### Status transitions

Allowed moves live in `SubscriptionStatus::canTransitionTo()` — never in scattered `if`s.

```
pending  ──▶ active      (its start date arrived, or an admin starts it early)
pending  ──▶ cancelled
active   ──▶ expired     (end date passed, or an admin closes it)
active   ──▶ cancelled
expired / cancelled      terminal
```

Manually activating a queued subscription while another is still running is rejected (`SubscriptionException::alreadyActive()`).

### The clock: `subscriptions:expire`

Registered in `bootstrap/app.php` → `withSchedule()`, hourly, `withoutOverlapping()`. One pass does three things in order:

1. `active` rows past `expires_at` → `expired`;
2. `pending` rows whose `starts_at` has arrived → `active` (only when nothing else is running for that master);
3. re-derive `access_expires_at` for every touched master.

It is idempotent — a skipped run just catches up on the next tick.

### Who buys

The administrator issues subscriptions manually after taking payment, exactly like the rest of the money flow in this project — there is no payment gateway. The mobile API is **read-only** for masters.

Two endpoints, and their auth is deliberately asymmetric:

- `GET /api/v1/master/subscription-plans` is **public**. Someone weighing whether to apply has no master profile yet, so the price list has to be reachable without one. It is a price list: no PII, nothing to protect.
- `GET /api/v1/master/subscription` runs under `ensure.master:allow-expired`. The middleware parameter skips only the `hasActiveAccess()` check; "is a master" and `is_active` still apply.

### Web routes (administrator only)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/subscriptions` | The page: plans + purchase history + stats |
| `POST` | `/subscription-plans` | Create a plan |
| `PUT` | `/subscription-plans/{plan}` | Edit a plan (future purchases only) |
| `POST` | `/subscription-plans/{plan}/toggle` | Enable / disable for new purchases |
| `DELETE` | `/subscription-plans/{plan}` | Soft delete |
| `POST` | `/masters/{master}/subscriptions` | Issue or renew |
| `PUT` | `/subscriptions/{subscription}` | Correct `price_paid` / `note` only — dates and duration stay as sold |
| `POST` | `/subscriptions/{subscription}/status` | Change status |
| `DELETE` | `/subscriptions/{subscription}` | Delete and recompute access |

A master can also be given their first subscription right in the create-master form: pick a plan, optionally type a different price.

---

## Live Master Tracking on the Admin Map

The admin map (`/masters/map`) shows masters in real time. When a master's mobile app pings its location:

1. Master `POST`s to `/api/v1/master/{master}/location` with its Sanctum token (`{master}` must be the token owner)
2. `UpdateMasterLocationAction` stores the ping and dispatches `MasterLocationUpdated`
3. The event broadcasts on the public channel `masters-map.{cityId}`
4. Any open admin map subscribed to that city animates the marker smoothly

### Basemap Rendering (`Pages/Masters/Map.vue`)

The base layer is rendered by **MapLibre GL** (GPU vector rendering — sharp at any zoom and on HiDPI), mounted into Leaflet via the `L.maplibreGL` bridge, so all markers, popups, trajectories and Reverb channel code stay pure Leaflet.

- **Source**: the whole MapLibre stack is **self-hosted by this application** — no external tile service, no separate tileserver process. This is deliberate: public OSM tile servers are blocked in Turkmenistan, so the basemap must come from our own origin.
  - **Vector tiles**: `GET /tiles/{z}/{x}/{y}.pbf` → `TilesController::vectorTile()` reads them straight out of the MBTiles SQLite archive at `storage/maps/tiles.mbtiles` (`MBTILES_PATH`). Tiles are stored gzipped in the TMS row scheme, so the controller flips Y and sets `Content-Encoding: gzip`. A missing tile returns `204`, which is normal for sea and unmapped areas.
  - **Style, glyphs, sprites**: static files under `public/maps/` (`style.json`, `fonts/`, `sprite.*`).
  - The archive itself is **not in git** (~118 MB) — copy it onto each machine manually.
- **Style URL is not hardcoded**: `TILES_STYLE_URL` → `config('services.tiles.style_url')` → shared by `HandleInertiaRequests` as the `tilesStyleUrl` prop → read via `usePage().props.tilesStyleUrl`.
- `maplibre-gl` is a lazy chunk — `@maplibre/maplibre-gl-leaflet` is dynamically imported in `onMounted`, so it loads only on map screens.
- `utils/loadMapStyle.js` rewrites the style's `tiles`/`glyphs`/`sprite` URLs to absolute ones, because MapLibre resolves them inside a Web Worker that has no page origin. All three map screens (`Masters/Map.vue`, `Orders/Show.vue`, `Orders/Partials/CreateOrderModal.vue`) go through it and therefore share one basemap stack.

> **Production**: serve the whole app over **HTTPS** — an HTTP tile origin is blocked as mixed content on an HTTPS panel. Since tiles come from the same origin, no CORS setup is needed.

### Per-Order Live Tracking (`/orders/{order}`)

When a master is assigned and the order is `assigned` or `in_progress`:

- On mount, loads the master's trajectory polyline from `GET /orders/{order}/master-trajectory`
- Subscribes to `masters-map.{cityId}` and moves the marker in real time
- Extends the polyline as new location events arrive
- Shows a live distance (Haversine) and ETA chip (assuming 60 km/h) with a pulsing dot
- Unsubscribes and cleans up on `onBeforeUnmount`

**Testing without the Flutter app**:

```bash
php artisan reverb:start                                    # terminal 1
php artisan master:simulate-movement 1 --interval=3 --steps=60   # terminal 2
```

Master 1 broadcasts a new location every 3 seconds for 3 minutes — open `/masters/map` and watch the marker move.

---

## API (Mobile Apps)

| Rule | Detail |
|---|---|
| Base path | `/api/v1/` (registered in `bootstrap/app.php` → `routes/api/v1.php`) |
| Controllers | `app/Http/Controllers/Api/V1/` (master) and `Api/V1/Client/` (client) |
| Auth | Laravel Sanctum, token-based, no sessions. **One token for both roles**: sign-in issues a `mobile-client` token gated by `ensure.client`, and the same token opens the master endpoints through `ensure.master` |
| Responses | Always via Eloquent API Resources |
| Errors | Localized JSON from the global handler in `bootstrap/app.php` |
| Locale | Send `X-Locale: ru|tk` |

### One account, two roles

A single mobile app serves both sides. Everyone signs up as a **client**; "become
a master" adds a master profile to that same account (`masters.client_id`), and
an administrator reviews it. There is no separate master login.

`EnsureMaster` therefore resolves the master profile from the authenticated
client and swaps it into the request, so master controllers keep reading
`$request->user()` as a `Master`. It answers `403` with a machine-readable
`reason` so the app can route to the right screen:

| `reason` | Meaning |
|---|---|
| `token_required` | Not a client token |
| `not_a_master` | Never applied |
| `application_pending` | Application still in review |
| `application_rejected` | Application turned down (`rejection_reason` explains) |
| `disabled` | Profile deactivated by an administrator |
| `access_expired` | Subscription lapsed |

`GET /api/v1/client/me` carries `master_status`, `master_id` and
`has_master_access` so the app knows which half to show without a second call.

Web (Inertia) and API controllers are **strictly separate**. Never reuse or share a controller between both.

**Flutter references**: [docs/MASTER_APP_SPEC.md](docs/MASTER_APP_SPEC.md) (full master app spec — endpoints, WebSocket contracts, screen flow) and [docs/MASTER_APP_MAP_INTEGRATION.md](docs/MASTER_APP_MAP_INTEGRATION.md) (map/tiles integration).

### Master API — `ensure.master` unless marked public

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/master/settings` | public | App rules/terms shown before registration |
| `GET` | `/api/v1/master/subscription-plans` | public | Subscription price list — reachable without a token on purpose (see below) |
| `GET` | `/api/v1/master/subscription` | Sanctum, `ensure.master:allow-expired` | Own subscription: current one, access deadline, history |
| `GET` | `/api/v1/master/me` | Sanctum | Profile, access expiry, categories |
| `PATCH` | `/api/v1/master/availability` | Sanctum | Toggle "ready for work" |
| `POST` | `/api/v1/master/{master}/location` | Sanctum | GPS ping; `{master}` **must** match the token owner |
| `GET` | `/api/v1/master/orders` | Sanctum | Assigned orders (`filter=active` / `history`) |
| `GET` | `/api/v1/master/orders/available` | Sanctum | Auto-search feed: unclaimed orders inside the current radius, nearest first |
| `POST` | `/api/v1/master/orders/{order}/respond` | Sanctum | Claim an offered order (first responder wins) |
| `POST` | `/api/v1/master/orders/{order}/decline` | Sanctum | Hide an offer from this master's feed only |
| `GET` | `/api/v1/master/orders/{order}` | Sanctum | Order details |
| `POST` | `/api/v1/master/orders/{order}/start` | Sanctum | Mark as `in_progress` on arrival |
| `POST` | `/api/v1/master/orders/{order}/complete` | Sanctum | Mark the job as done |
| `POST` | `/api/v1/master/orders/{order}/tasks` | Sanctum | Add a performed task |
| `POST` | `/api/v1/master/orders/{order}/tasks/{task}/photo` | Sanctum | Upload a before/after photo |
| `DELETE` | `/api/v1/master/orders/{order}/tasks/{task}` | Sanctum | Remove a task |

### Client API — `ensure.client` unless marked public

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/client/settings` | public | App rules/terms |
| `GET` | `/api/v1/client/{oblasts,regions,cities}` | public | Geography catalog |
| `GET` | `/api/v1/client/categories` | public | Service categories |
| `GET` | `/api/v1/client/categories/search` | public | Category search |
| `GET` | `/api/v1/client/categories/{category}/content` | public | Category landing content |
| `GET` | `/api/v1/client/banners` | public | Promo banners |
| `POST` | `/api/v1/client/auth/request-otp` | public | Send OTP to phone number |
| `POST` | `/api/v1/client/auth/verify-otp` | public | Verify OTP, returns a Sanctum token |
| `POST` | `/api/v1/client/auth/complete-registration` | Sanctum | Save name + city after first login |
| `POST` | `/api/v1/client/auth/logout` | Sanctum | Revoke the current token |
| `GET` `PATCH` | `/api/v1/client/me` | Sanctum | Read / update profile (includes the master role on this account) |
| `GET` | `/api/v1/client/master-application` | Sanctum | Own master application and its verdict, `null` if never applied |
| `POST` | `/api/v1/client/master-application` | Sanctum | "Become a master": city, categories, years of experience, short bio |
| `GET` `POST` | `/api/v1/client/orders` | Sanctum | List / create orders |
| `GET` `PATCH` | `/api/v1/client/orders/{order}` | Sanctum | Read / update an order |
| `POST` | `/api/v1/client/orders/{order}/cancel` | Sanctum | Cancel an order |
| `POST` | `/api/v1/client/orders/{order}/review` | Sanctum | Leave a review after completion |

**Broadcast auth**: `POST /api/v1/broadcasting/auth` (`auth:sanctum`) — mobile apps point their Reverb/Pusher `authEndpoint` here.

Run `php artisan route:list --path=api/v1` for the authoritative list.

### API documentation

Scribe generates the docs (`php artisan scribe:generate`) and serves them at `/docs`, with `/docs.openapi` and `/docs.postman` alongside. Access is gated by `ProtectScribeDocs` (wired in `config/scribe.php` → `laravel.middleware`): open in local/dev, **administrators only in production**, everyone else gets a `404` so the endpoint is not discoverable.

Ready-to-run requests live in the single `bruno/` collection ([Bruno](https://www.usebruno.com/) — open the folder, pick the `local` environment). It mirrors the mobile app: one collection, one `{{token}}` saved by **Verify OTP**, folders ordered the way a real session runs — sign in → catalog → order → become a master → master work. `{{locale}}` flips every request between `tk` and `ru`.

---

## Infrastructure Monitoring

`GET /system-status` (auth only) powers the status dots in `AdminLayout.vue` and the monitoring cards on `/settings`. Every number is measured — nothing is simulated on the frontend.

| Source | What it measures | How |
| --- | --- | --- |
| **Queue** | worker alive, pending jobs, jobs finished today | `Queue::looping` writes `queue:worker_heartbeat` (stale after 120 s); `Queue::size()`; `Queue::after` increments `queue:processed:{Y-m-d}` |
| **Reverb** | reachability, open channels, connections, response time | `ReverbMetricsService` calls the signed Pusher-compatible endpoints `/apps/{id}/channels` and `/apps/{id}/connections` on `REVERB_HOST:REVERB_PORT` |
| **OTP gateway** | bridge alive, connected phones, last OTP | `GET {SMS_GATEWAY_URL}/health` + `otp_gateway:last_sent` cache key |
| **WebSocket card** | this browser's own socket | read directly from `window.Echo.connector.pusher` (state + subscribed channels) |

`SystemStatusService` caches the whole snapshot for 10 s so open admin tabs don't storm Reverb and the SMS gateway. The **Переподключить** buttons request `?fresh=1` to bypass that cache; the WebSocket button reconnects the Echo client itself.

When a source is unreachable its card shows `—` instead of a number — a missing metric never renders as `0`.

---

## OTP Delivery & Manual Fallback

OTP codes are generated by `DispatchOtpAction` and pushed to the Flutter SMS-gateway phone through the Socket.IO bridge (`OtpGatewayService` → `POST {SMS_GATEWAY_URL}/emit-otp` with the `X-Gateway-Secret` header → `socket-server/` re-emits the `otp` event).

**When the gateway is unreachable, login is not blocked.** Instead:

1. The code is still written to cache, so `verify-otp` accepts it as usual.
2. A row is parked in `pending_otps` (`PendingOtpRepository::replaceForPhone()` — only the latest code per phone survives).
3. `request-otp` answers `200` with `delivery: "manual"` and a localized `delivery_message` telling the caller to phone support.
4. The **OTP-коды** sidebar section (`Pages/PendingOtps/Index.vue`) and the dashboard both render `Components/PendingOtpPanel.vue`, which polls `GET /pending-otps/data` every 30 s and shows the code, phone, recipient and a live countdown, so an administrator or manager can dictate it. `DELETE /pending-otps/{id}` dismisses a delivered code; expired rows are purged on every poll.
5. Parking a code fires `PendingOtpCreated` — a **queued** broadcast (`ShouldBroadcast`, not `ShouldBroadcastNow`) on the private `admin.pending-otps` channel, so the caller's login request never waits on Reverb. Open panels prepend the code instantly; the 30 s poll is the fallback if the worker or Reverb is down.
6. The sidebar item carries an amber badge fed by the `pendingOtpCount` shared prop, refreshed on broadcast via `router.reload({ only: ['pendingOtpCount'] })`, plus a toast and alarm sound from `AdminLayout`.

Codes live only as long as `OTP_TTL_MINUTES`. The routes sit behind `auth` + `role:administrator,manager` — operators never see them.

---

## System Status

`GET /system-status` (any authenticated user) returns the health of the three moving parts the panel depends on:

```json
{
  "queue":       "ok | error",
  "websocket":   "ok | error",
  "otp_gateway": { "status": "ok | error", "clients": 0, "last_sent": null }
}
```

- **queue** — `AppServiceProvider` writes a `queue:worker_heartbeat` cache key on every worker loop; the status is `error` if the heartbeat is older than 120 s (i.e. no `queue:work` running).
- **websocket** — HTTP probe against the configured Reverb host/port.
- **otp_gateway** — `GET {SMS_GATEWAY_URL}/health`, reporting how many gateway phones are connected and when the last OTP was emitted.

---

## Adding a New Feature

Follow this order every time — no skipping steps.

```
Step 1 — Database
    php artisan make:migration create_xxx_table
    php artisan make:model Xxx -f              # -f creates factory

Step 2 — Repository
    Create app/Repositories/XxxRepository.php  # all queries live here

Step 3 — Business Logic
    php artisan make:class Actions/CreateXxxAction
    (or app/Services/XxxService.php for multi-step logic)

Step 4 — Controller
    php artisan make:controller XxxController  # thin — HTTP only

Step 5 — Validation & Response
    php artisan make:request StoreXxxRequest
    php artisan make:resource XxxResource

Step 6 — Vue Component
    Create resources/js/Pages/Xxx/Index.vue (+ Partials/XxxFormModal.vue)
    Dark mode + i18n, uses AdminLayout

Step 7 — Translations
    Add keys to lang/ru/xxx.php AND lang/tk/xxx.php — that is all
    (the frontend picks them up automatically through the Inertia prop)

Step 8 — Tests
    php artisan make:test --phpunit XxxTest
    Cover: happy path + validation failure + authorization + edge cases
```

---

## Adding a New Package or Service

1. **Update this README** — Tech Stack table + a usage section
2. **Update `CLAUDE.md`** — add rules under the relevant section
3. **Add lang keys** to both `lang/ru/` and `lang/tk/` if the package introduces UI text
4. **Update `.env.example`** with any new required environment variables
5. After pulling: run `composer install` and/or `npm install`

---

## Code Style, Static Analysis & Tests

```bash
vendor/bin/pint --dirty            # Format changed PHP files — run before every commit
vendor/bin/pint                    # Format the whole codebase

vendor/bin/phpstan analyse         # Larastan level 6 over app/ (middleware excluded)

php artisan test --compact                              # All tests
php artisan test --compact tests/Feature/CityTest.php   # Single file
php artisan test --compact --filter=it_creates_a_city   # Single test
```

Every feature, action and model must have PHPUnit tests covering the happy path, validation failure and edge cases. Tests are never deleted without approval.

---

## Useful Commands

```bash
# ── Development ──────────────────────────────────────────────────────────────
npm run dev                       # Vite dev server
npm run build                     # Production asset build
php artisan serve                 # Laravel dev server

# ── Workers & services ───────────────────────────────────────────────────────
php artisan queue:work            # Process queued jobs (image conversion, broadcasts)
php artisan reverb:start          # WebSocket server
php artisan schedule:work         # Scheduler (auto-search radius + subscription expiry)
cd socket-server && npm start     # OTP Socket.IO bridge

php artisan orders:expand-search-radius   # One-off sweep of the auto-search, for debugging
php artisan subscriptions:expire          # One-off subscription clock tick, for debugging
php artisan schedule:list                 # Verify both scheduled commands are registered

# ── Database ─────────────────────────────────────────────────────────────────
php artisan migrate               # Run pending migrations
php artisan migrate --seed        # Migrate + seed
php artisan migrate:fresh --seed  # Drop all tables, migrate, seed
php artisan storage:link          # Symlink public/storage

# ── Demo / debugging ─────────────────────────────────────────────────────────
php artisan master:simulate-movement 1 --interval=3 --steps=60
php artisan route:list --except-vendor          # All application routes
php artisan route:list --path=api/v1            # Mobile API only
php artisan config:show database                # Show config values
php artisan cache:clear                         # Also flushes the translations cache

# ── Docs & quality ───────────────────────────────────────────────────────────
php artisan scribe:generate       # Regenerate API docs at /docs
vendor/bin/pint --dirty           # Format changed PHP files
vendor/bin/phpstan analyse        # Static analysis
php artisan test --compact        # Full test suite
```

---

## Git Conventions

| Prefix | When to use |
|---|---|
| `feat:` | New feature |
| `fix:` | Bug fix |
| `refactor:` | Code change with no behavior change |
| `docs:` | Documentation updates |
| `test:` | Adding or fixing tests |

Example: `feat: add city management with repository and PHPUnit tests`

---

## Deployment

The recommended target is [Laravel Cloud](https://cloud.laravel.com/), which handles scaling, zero-downtime deploys, queue workers and WebSocket servers. See `.env.production.example` for a production-shaped environment file.

Before going live:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Checklist:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real HTTPS URL
- Serve over **HTTPS** (required for the self-hosted map tiles)
- `queue:work` running under a supervisor — image conversion, admin notifications and OTP broadcasts all depend on it
- `reverb:start` running, `REVERB_SCHEME=https` and the WebSocket port proxied
- `socket-server/` running with a real `GATEWAY_SECRET`
- `storage/maps/tiles.mbtiles` copied onto the server (not in git)
- Switch `masters-map.{cityId}` to a private channel with an admin gate before exposing the panel publicly
