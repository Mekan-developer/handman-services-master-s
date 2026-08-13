# Alo-kömek — Handyman Service (Admin Panel & Backend)

A platform for clients to search and book handyman services. Administrators manage masters, assign orders, track payments and watch master locations in real time through a web admin panel. Masters and clients interact through dedicated Flutter mobile apps that talk to the versioned REST API of this same application.

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
       └─── OrderReview

Standalone: User (admin staff), Banner, Setting, PendingOtp
```

| Entity | Role |
|---|---|
| `Oblast` / `Region` / `City` | Geography. Masters, clients and orders are all scoped to a city |
| `Category` | Service catalog, self-nesting, bilingual, with an optional `CategoryContent` landing page |
| `Master` | The handyman: payment model, balance, access expiry, availability flag, live location |
| `Client` | Mobile app user, can be blocked by an administrator |
| `Order` | The job. Carries status (`App\Enums\OrderStatus`), photos, tasks and one review |
| `OrderTask` | One discrete piece of work with before/after photos — e.g. "replaced hose" |
| `MasterPayout` | Record of a balance payout, referencing the `User` who made it |
| `PendingOtp` | OTP parked for manual delivery when the SMS gateway is down |
| `Setting` | Key/value app settings exposed to both mobile apps |
| `User` | Admin panel staff — administrator / manager / operator (`App\Enums\UserRole`) |

### Enums (`app/Enums/`)

| Enum | Values |
|---|---|
| `OrderStatus` | `pending`, `assigned`, `in_progress`, `completed`, `cancelled` (+ `label()`, `color()`, `isFinal()`) |
| `UserRole` | `administrator`, `manager`, `operator` (+ `assignable()`, `canManage()`) |
| `PaymentModel` | `percentage`, `fixed_per_job`, `salary`, `salary_percentage` (+ `requiresFinalPrice()`) |
| `OtpDeliveryChannel` | Delivery route of a generated OTP (SMS gateway vs. manual) |
| `OtpRecipientType` | Whether the OTP belongs to a master or a client |
| `CategoryIconType` | Icon source for a category |

### Roles & Access

Enforced by the `role` middleware alias (`App\Http\Middleware\CheckRole`) in `routes/web.php`.

| Section | administrator | manager | operator |
|---|:--:|:--:|:--:|
| Profile, `/system-status` | ✅ | ✅ | ✅ |
| Dashboard, geography, categories, masters, clients, orders, banners, settings, notifications, OTP codes | ✅ | ✅ | ❌ |
| Users (`/users`) | ✅ | ❌ | ❌ |
| Payments & payouts (`/payments`) | ✅ | ❌ | ❌ |

---

## Project Structure

```
app/
├── Actions/                    # ~55 single-purpose operations (Create/Update/Delete/Assign/…)
├── Console/Commands/
│   └── SimulateMasterMovement.php   # master:simulate-movement — demo GPS pings
├── Enums/                      # OrderStatus, UserRole, PaymentModel, Otp*, CategoryIconType
├── Events/                     # MasterAssigned, MasterLocationUpdated, OrderCreated,
│                               # OrderStatusChanged, PendingOtpCreated
├── Exceptions/                 # ApiException + Master/Order/Otp/Payment domain exceptions
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
├── Providers/                  # AppServiceProvider (observer + queue heartbeat)
├── Repositories/               # 14 repositories — all database query logic
├── Services/                   # OtpGatewayService
└── Support/                    # Framework-agnostic helpers (PhotoConverter, CategoryIcon)

resources/js/
├── Components/                 # CategoryPicker, CityFilterSelect, ConfirmModal, IconPicker,
│                               # ImageLightbox, Modal, NotificationPanel, OblastCitySelect,
│                               # Pagination, PasswordInput, PendingOtpPanel, PhoneInput,
│                               # ServiceIcon, form primitives
├── Layouts/
│   ├── AdminLayout.vue         # Sidebar + topbar, notifications, OTP alerts
│   └── GuestLayout.vue         # Login / password reset shell
├── Pages/                      # Auth, Banners, Categories, Cities, Clients, Dashboard,
│                               # Masters (Index + Map), Oblasts, Orders (Index + Show),
│                               # Payments, PendingOtps, Profile, Regions, Settings, Users
│                               # (each section has a Partials/ folder with its modals)
├── stores/                     # useThemeStore, useLocaleStore, useNotificationStore
├── utils/                      # loadMapStyle.js, formatPhone.js
├── app.js                      # Inertia + Pinia + Ziggy + vue-i18n bootstrap
├── bootstrap.js                # axios defaults
├── echo.js                     # Laravel Echo + Reverb client
└── i18n.js                     # vue-i18n instance (messages injected from PHP at runtime)

lang/
├── ru/                         # api, auth, banners, categories, cities, clients, dashboard,
│                               # layout, masters, notifications, oblasts, orders, payments,
│                               # pending_otps, profile, regions, resources, settings, users,
│                               # validation
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
bruno-master/, bruno-client/    # Ready-to-run Bruno API request collections
docs/                           # MASTER_APP_SPEC.md, MASTER_APP_MAP_INTEGRATION.md, tasks/

tests/
├── Feature/                    # Feature tests (primary), incl. Api/V1 and Api/V1/Client
└── Unit/
```

---

## Local Development Setup

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

# 5. Storage symlink (order/task photos, banners, master avatars)
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

# 10. OTP SMS gateway bridge (optional in dev — without it OTPs fall back to manual delivery)
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

---

## Realtime (Laravel Reverb)

### Broadcast channels (`routes/channels.php`)

| Channel | Type | Who may subscribe | Carries |
|---|---|---|---|
| `masters-map.{cityId}` | public | anyone (tighten in production) | `MasterLocationUpdated` |
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
| Auth | Laravel Sanctum, token-based, no sessions. Master tokens are named `mobile` and gated by `ensure.master`; client tokens are named `mobile-client` and gated by `ensure.client` |
| Responses | Always via Eloquent API Resources |
| Errors | Localized JSON from the global handler in `bootstrap/app.php` |
| Locale | Send `X-Locale: ru|tk` |

`ensure.master` additionally rejects masters that are inactive (`403 api.master.disabled`) or whose paid access has expired (`403 api.master.access_expired`).

Web (Inertia) and API controllers are **strictly separate**. Never reuse or share a controller between both.

**Flutter references**: [docs/MASTER_APP_SPEC.md](docs/MASTER_APP_SPEC.md) (full master app spec — endpoints, WebSocket contracts, screen flow) and [docs/MASTER_APP_MAP_INTEGRATION.md](docs/MASTER_APP_MAP_INTEGRATION.md) (map/tiles integration).

### Master API — `ensure.master` unless marked public

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/master/settings` | public | App rules/terms shown before registration |
| `POST` | `/api/v1/master/auth/request-otp` | public | Send OTP to the master's phone |
| `POST` | `/api/v1/master/auth/verify-otp` | public | Verify OTP, returns a Sanctum token |
| `POST` | `/api/v1/master/auth/logout` | Sanctum | Revoke the current token |
| `GET` | `/api/v1/master/me` | Sanctum | Profile, balance, payment model, categories |
| `PATCH` | `/api/v1/master/availability` | Sanctum | Toggle "ready for work" |
| `POST` | `/api/v1/master/{master}/location` | Sanctum | GPS ping; `{master}` **must** match the token owner |
| `GET` | `/api/v1/master/orders` | Sanctum | Assigned orders (`filter=active` / `history`) |
| `GET` | `/api/v1/master/orders/{order}` | Sanctum | Order details |
| `POST` | `/api/v1/master/orders/{order}/start` | Sanctum | Mark as `in_progress` on arrival |
| `POST` | `/api/v1/master/orders/{order}/complete` | Sanctum | Complete and credit the balance |
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
| `GET` `PATCH` | `/api/v1/client/me` | Sanctum | Read / update profile |
| `GET` `POST` | `/api/v1/client/orders` | Sanctum | List / create orders |
| `GET` `PATCH` | `/api/v1/client/orders/{order}` | Sanctum | Read / update an order |
| `POST` | `/api/v1/client/orders/{order}/cancel` | Sanctum | Cancel an order |
| `POST` | `/api/v1/client/orders/{order}/review` | Sanctum | Leave a review after completion |

**Broadcast auth**: `POST /api/v1/broadcasting/auth` (`auth:sanctum`) — mobile apps point their Reverb/Pusher `authEndpoint` here.

Run `php artisan route:list --path=api/v1` for the authoritative list.

### API documentation

Scribe generates the docs (`php artisan scribe:generate`) and serves them at `/docs`, with `/docs.openapi` and `/docs.postman` alongside. Access is gated by `ProtectScribeDocs` (wired in `config/scribe.php` → `laravel.middleware`): open in local/dev, **administrators only in production**, everyone else gets a `404` so the endpoint is not discoverable.

Ready-to-run request collections live in `bruno-master/` and `bruno-client/` (Bruno, with `environments/`).

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
cd socket-server && npm start     # OTP Socket.IO bridge

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
