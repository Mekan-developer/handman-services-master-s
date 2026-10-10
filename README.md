# HANDYMAN — Admin Panel & Backend

A platform for clients to search and book handyman services. Administrators manage masters, review "become a master" applications, sell access subscriptions and watch master locations in real time through a web admin panel. The service earns from master subscriptions only — the platform never pays masters and does not track their earnings. Clients and masters use **one** Flutter mobile app that talks to the versioned REST API of this same application.

---

## System Components

| Component | Technology |
|---|---|
| Admin Panel | Laravel 11 + Inertia.js v2 + Vue 3 |
| Backend API | Laravel 11 + Sanctum (`/api/v1/`) |
| Mobile App | Flutter (Android & iOS), one app for both roles — separate repository |
| WebSocket | Laravel Reverb |
| OTP SMS Gateway | Node.js Socket.IO bridge (`socket-server/`) + Flutter gateway phone |
| Database | MySQL 8 (Docker, production) / SQLite (native dev default) |
| Cache / Queue | Redis (Docker, production) / database driver (native dev default) |
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
| Redis Client | Predis v3 |
| Named Routes | Ziggy v2 |
| Testing | PHPUnit v10 (+ Mockery, Collision) |
| Code Style | Laravel Pint v1 |
| Static Analysis | Larastan v3 — level 6 (`phpstan.neon`) |
| API Docs | Scramble — served at `/docs/api`, gated by `RestrictedDocsAccess` |
| Dev Tooling | Laravel Boost v2, Breeze v2 (auth scaffolding), IDE Helper, Debugbar, Ignition, Sail |

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
| `socket-server/` | Express + Socket.IO bridge. Laravel `POST`s an OTP to `/emit-otp`, the server re-emits it to every connected Flutter SMS phone. Exposes `/health` used by the system status endpoint. Full contract: [socket-server/README.md](socket-server/README.md) |

---

## Architecture & Patterns

This project enforces a strict layered architecture. Every developer must follow these patterns without exception.

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
| **Services** | Complex multi-step business logic or external integrations (`SystemStatusService`); swappable drivers behind an interface (`Services/Sms/SmsSender`) |
| **Actions** | Single-purpose operations (e.g. `RespondToOrderAction`, `ApproveOrderResponseAction`, `RestartOrderSearchAction`) |
| **Form Requests** | All validation — never `$request->validate()` in controllers |
| **API Resources** | All API responses — never return raw models or arrays |
| **Jobs** | All background/async processing (image conversion) |
| **Observers** | Model event handling (`ClientObserver`, `MasterObserver`, `OrderObserver`) |
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
        └─< City ─┬─< Client ──── Master ─┬─< MasterLocation      (GPS trail, one row per ping)
                  │   (one account,       ├─< MasterSubscription
                  │    optional role)     ├─< OrderReview
                  │                       └─>< Category           (pivot: category_master)
                  └─< Order

Category ─┬─< Category            (self-referencing parent/children)
          └─── CategoryContent ─< CategoryContentImage

Order ─┬─> City, Category, Client (required), Master (once a response is approved)
       ├─< OrderPhoto             (photos of the problem, client-supplied)
       ├─< OrderTask ─< OrderTaskPhoto   (before/after photos per performed task)
       ├─< OrderMasterResponse    (masters who offered themselves; the client picks one)
       ├─< OrderMasterDecline     (masters who dismissed the offer)
       ├─< MasterLocation         (the trip taken for this order)
       └─── OrderReview

SubscriptionPlan ─< MasterSubscription
Standalone: User (admin staff), Banner, Setting, PendingOtp
```

| Entity | Role |
|---|---|
| `Oblast` / `Region` / `City` | Geography. Clients and orders carry a city; the auto-search itself ignores it |
| `Category` | Service catalog, self-nesting, bilingual, with an optional `CategoryContent` landing page |
| `Client` | Mobile app account, can be blocked by an administrator. Owns the avatar (`photo`) — the `Master` profile shows the same file. Optionally has one `Master` profile |
| `Master` | The handyman — a profile **on top of a `Client` account** (`client_id`, unique), never a standalone login. Carries the application (categories, `experience_years`, `about`), its review verdict (`MasterStatus`), access expiry derived from subscriptions, availability flag, live location |
| `Order` | The job. Always belongs to a client (`client_id` is required). Carries status (`OrderStatus`), photos, tasks, one review, and the auto-search state (`search_started_at`, `search_radius_km`, `search_expired_at`) |
| `OrderMasterResponse` | A master offered themselves for an order (`OrderResponseStatus`: `pending` → `approved` / `rejected`) |
| `OrderMasterDecline` | A master dismissed an offer — hides it from that master's feeds only |
| `OrderTask` | One discrete piece of work with before/after photos — e.g. "replaced hose" |
| `SubscriptionPlan` | Tariff sold to masters: bilingual name, duration in days, price, soft deleted so sold subscriptions keep their link |
| `MasterSubscription` | A purchase: snapshot of plan name/price/duration, status (`SubscriptionStatus`), period, who issued it |
| `PendingOtp` | OTP parked for manual delivery when the SMS gateway is down |
| `Setting` | Key/value app settings (app rules, auto-search radii, auto-cancel deadline) |
| `User` | Admin panel staff — administrator / manager / operator (`UserRole`) |

### Enums (`app/Enums/`)

| Enum | Values |
|---|---|
| `OrderStatus` | `pending`, `assigned`, `in_progress`, `completed`, `cancelled` (+ `label()`, `color()`, `isFinal()`, `isTrackable()`) |
| `OrderResponseStatus` | `pending`, `approved`, `rejected` — a master's response to an order |
| `MasterStatus` | `pending`, `approved`, `rejected` — where a master application stands (+ `label()`, `color()`, `grantsAccess()`, `canTransitionTo()`) |
| `SubscriptionStatus` | `pending`, `active`, `expired`, `cancelled` (+ `label()`, `color()`, `isFinal()`, `canTransitionTo()`) |
| `UserRole` | `administrator`, `manager`, `operator` (+ `assignable()`, `canManage()`, `canAccessAdminSections()`) |
| `AnalyticsPeriod` | `daily`, `weekly`, `monthly`, `yearly` — dashboard chart grouping |
| `OtpDeliveryChannel` | Delivery route of a generated OTP (SMS gateway vs. manual) |
| `OtpRecipientType` | Recipient a parked OTP belongs to |
| `CategoryIconType` | Icon source for a category: `preset` (SVG from the set), `image` (uploaded WebP ≤ 50 KB), `custom` (legacy SVG, read-only) |

### Roles & Access

Enforced by the `role` middleware alias (`App\Http\Middleware\CheckRole`) in `routes/web.php`.

| Section | administrator | manager | operator |
|---|:--:|:--:|:--:|
| Profile, `/system-status` | ✅ | ✅ | ✅ |
| Dashboard, geography, categories, masters, master applications, clients, orders, banners, settings, notifications, OTP codes | ✅ | ✅ | ❌ |
| Users (`/users`) | ✅ | ❌ | ❌ |
| Subscriptions & plans (`/subscriptions`) | ✅ | ❌ | ❌ |

---

## Project Structure

```
app/
├── Actions/                    # ~66 single-purpose operations
│   └── Concerns/               # EnsuresMasterEligibility, SyncsMasterAccess
├── Console/Commands/
│   ├── ExpandOrderSearchRadiusCommand.php   # orders:expand-search-radius — every minute
│   ├── CancelStaleOrdersCommand.php         # orders:cancel-stale-orders — hourly
│   ├── ExpireMasterSubscriptionsCommand.php # subscriptions:expire — hourly
│   ├── PruneMasterLocations.php             # locations:prune — nightly 03:30
│   └── SimulateMasterMovement.php           # master:simulate-movement — demo GPS pings
├── Enums/                      # see the table above
├── Events/                     # ClientCreated, MasterAssigned, MasterLocationUpdated,
│                               # MasterRespondedToOrder, OrderCreated, OrderResponse{Rejected,
│                               # Superseded,Withdrawn}, OrderSearch{Started,RadiusExpanded,Exhausted},
│                               # OrderStatusChanged, PendingOtpCreated
├── Exceptions/                 # ApiException + Category/Client/Master/MasterApplication/
│                               # Order/Otp/Subscription domain exceptions
├── Http/
│   ├── Controllers/            # Web controllers (thin, Inertia)
│   │   ├── Api/V1/             # Master API controllers
│   │   │   └── Client/         # Client API controllers
│   │   ├── Auth/               # Breeze auth controllers
│   │   ├── TilesController.php        # /tiles/{z}/{x}/{y}.pbf from MBTiles
│   │   └── SystemStatusController.php # /system-status health JSON
│   ├── Middleware/             # CheckRole, EnsureClient, EnsureMaster, SetLocale,
│   │                           # HandleInertiaRequests
│   ├── Requests/               # Form Requests (web + Api/V1 + Api/V1/Client)
│   ├── Resources/              # Eloquent API Resources
│   └── Traits/WithNotification.php
├── Jobs/                       # ConvertOrderPhotoJob, ConvertTaskPhotoJob
├── Listeners/                  # NotifyAdminsOnNewClient, NotifyAdminsOnNewOrder,
│                               # NotifyAdminsOnOrderSearchExhausted
├── Models/                     # 22 Eloquent models
├── Notifications/              # NewClient, NewOrder, OrderSearchExhausted (database channel)
├── Observers/                  # ClientObserver, MasterObserver, OrderObserver
├── Policies/                   # UserPolicy
├── Providers/                  # AppServiceProvider (observers + queue heartbeat + processed counter)
├── Repositories/               # 16 repositories — all database query logic
├── Services/                   # SystemStatusService, ReverbMetricsService
│   └── Sms/                    # SmsSender interface + ModemSmsSender, LogSmsSender
└── Support/                    # Framework-agnostic helpers (PhotoConverter, CategoryIcon)

resources/js/
├── Components/                 # CategoryIcon, CategoryPicker, CityFilterSelect, ConfirmModal,
│                               # IconPicker, ImageLightbox, Modal, NotificationPanel,
│                               # OblastCitySelect, Pagination, PasswordInput, PendingOtpPanel,
│                               # PhoneInput, ServiceIcon, form primitives
├── Layouts/
│   ├── AdminLayout.vue         # Sidebar + topbar, notifications, OTP alerts
│   └── GuestLayout.vue         # Login / password reset shell
├── Pages/                      # Auth, Banners, Categories, Cities, Clients, Dashboard,
│                               # Masters (Index, Map, Applications), Oblasts, Orders (Index + Show),
│                               # PendingOtps, Profile, Regions, Settings, Subscriptions, Users
│                               # (each section keeps its modals in Partials/)
├── stores/                     # useThemeStore, useLocaleStore, useNotificationStore
├── utils/                      # loadMapStyle.js, formatPhone.js
├── app.js                      # Inertia + Pinia + Ziggy + vue-i18n bootstrap
├── bootstrap.js                # axios defaults
├── echo.js                     # Laravel Echo + Reverb client
└── i18n.js                     # vue-i18n instance (messages injected from PHP at runtime)

lang/
├── ru/                         # 20 files: api, auth, banners, categories, cities, clients,
│                               # dashboard, layout, masters, notifications, oblasts, orders,
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
├── migrations/                 # 51 migrations
├── factories/
└── seeders/                    # Oblast, City, Category, Setting

docker/                         # php (Dockerfile, entrypoint, php.ini), nginx, socket, db.env
public/
├── maps/                       # MapLibre style.json, glyphs, sprites
├── icons/
└── sounds/alarm.mp3            # Admin panel new-order / new-OTP alert sound

storage/maps/tiles.mbtiles      # Vector tile archive — NOT in git (~118 MB), copy manually

socket-server/                  # Node.js Socket.IO OTP bridge (own package.json / .env / README.md)
bruno/                          # Ready-to-run Bruno API collection (one app, both roles)
docs/tasks/                     # Task notes

tests/
├── Feature/                    # Feature tests (primary), incl. Api/V1 and Api/V1/Client
└── Unit/
```

---

## Local Development Setup

### Option A — Docker (recommended)

`docker-compose.yml` defines the stack; `docker-compose.override.yml` is picked up automatically in development and mounts the source for live edits.

| Service | Purpose | Dev port |
|---|---|---|
| `app` | php-fpm 8.3 | — |
| `nginx` | Web server | `8000` |
| `queue` | `queue:work` | — |
| `scheduler` | `schedule:work` | — |
| `reverb` | `reverb:start` | `8080` |
| `sms-gateway` | `socket-server/` OTP bridge | `3000` |
| `node` | Vite dev server (`npm run dev`) | `5173` |
| `db` | MySQL 8 | `3366` |
| `redis` | Cache / queue / session | `6379` |
| `artisan` | One-off Artisan runner | — |

```bash
cp .env.example .env                 # then fill DB_*, REDIS_*, REVERB_* (see Environment Variables)
docker compose up -d --build
docker compose run --rm artisan key:generate
docker compose run --rm artisan migrate --seed
docker compose run --rm artisan storage:link
```

Production uses `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`: the `.env` is mounted read-only, `storage/` lives in a named volume, and nginx serves a built frontend image.

### Option B — Native

Requirements: PHP 8.2+ (8.3 recommended) with `sqlite3`, `gd`, `pdo`; Composer; Node.js 20+.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite is the default and needs no configuration
touch database/database.sqlite
php artisan migrate --seed

php artisan storage:link             # order/task photos, banners, client avatars

# Basemap archive (optional — only for map screens):
# copy tiles.mbtiles into storage/maps/ manually; it is not in git.

# Each in its own terminal:
npm run dev
php artisan serve
php artisan queue:work               # REQUIRED — image conversion, notifications, broadcasts
php artisan reverb:start             # REQUIRED — realtime alerts and live maps
php artisan schedule:work            # REQUIRED — auto-search radius, stale orders, subscriptions
cd socket-server && cp .env.example .env && npm ci && npm start   # optional in dev (or SMS_DRIVER=log)
```

> **Vite over LAN**: set `VITE_DEV_SERVER_HOST` to your machine's LAN IP if you open the panel from another device (`vite.config.js` reads it for both `server.host` and HMR).

---

## Environment Variables

`.env.example` is the native-dev baseline; `.env.production.example` is the full production-shaped file (MySQL, Redis, Reverb). The values that matter for this project:

```dotenv
# ── Application ──────────────────────────────────────────────────────────────
APP_NAME="Handyman"
APP_URL=http://localhost          # Full public URL (links, API docs)
APP_TIMEZONE=Asia/Ashgabat
APP_LOCALE=ru                     # ru | tk
APP_FALLBACK_LOCALE=ru

# ── Database ─────────────────────────────────────────────────────────────────
DB_CONNECTION=sqlite              # mysql in Docker / production
# DB_HOST=db  DB_PORT=3306  DB_DATABASE=  DB_USERNAME=  DB_PASSWORD=

# ── Queue, Cache, Session ────────────────────────────────────────────────────
QUEUE_CONNECTION=database         # redis in Docker / production
CACHE_STORE=database              # redis in Docker / production
SESSION_DRIVER=database

# ── WebSocket — Laravel Reverb ───────────────────────────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=                    # Generated by: php artisan reverb:install
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=reverb                # Docker service name; 127.0.0.1 natively
REVERB_PORT=8080
REVERB_SCHEME=http                # https in production

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=                 # Leave empty in dev: Echo falls back to window.location.hostname.
                                  # Set the public WS domain in production. Never "${REVERB_HOST}" —
                                  # that is the server-side host, unreachable from the browser.
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# ── Maps ─────────────────────────────────────────────────────────────────────
TILES_STYLE_URL="/maps/style.json"   # Shared to the frontend as the `tilesStyleUrl` Inertia prop
MBTILES_PATH=maps/tiles.mbtiles      # Relative to storage/

# ── OTP by SMS (socket-server/) ──────────────────────────────────────────────
SMS_DRIVER=modem                        # modem (gateway) | log (dev only, refused in production)
SMS_GATEWAY_URL=http://127.0.0.1:3000   # http://sms-gateway:3000 inside Docker
OTP_SECRET=                             # Same value in socket-server and the phone's Auth Token
SMS_DEVICE_LABEL=                       # Sending phone's name on the Settings status card
OTP_TTL_MINUTES=3

# ── Dev only ─────────────────────────────────────────────────────────────────
VITE_DEV_SERVER_HOST=127.0.0.1    # LAN IP when testing from a phone
```

Custom values are exposed through `config/services.php`: `services.tiles.style_url`, `services.mbtiles.path`, `services.otp.ttl_minutes`; the SMS settings live in `config/sms.php` (`driver`, `gateway_url`, `otp_secret`, `device_label`). Never read `env()` outside config files — it returns `null` once configs are cached.

> **Note**: `.env.example` still ships Laravel's defaults (`APP_NAME=Laravel`, `APP_LOCALE=en`, `BROADCAST_CONNECTION=log`) and has no `REVERB_*` / `VITE_REVERB_*` block. Fix those after `cp .env.example .env`, otherwise broadcasting silently does nothing.

Business settings that admins change at runtime are **not** env variables — they live in the `settings` table and are edited at **Settings**: app rules (`client_app_rules`), auto-search radii (`master_search_initial_radius_km` = 20, `master_search_max_radius_km` = 80) the auto-cancel deadline (`order_auto_cancel_hours` = 48) and the window for a master to take back a declined order (`order_decline_restore_minutes` = 60, counted from the decline). Defaults are constants on `App\Models\Setting`.

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

There is **no separate frontend dictionary**. `HandleInertiaRequests::loadTranslations()` reads every file under `lang/ru/` and `lang/tk/` and shares them as the `translations` Inertia prop; `app.js` pushes them into vue-i18n on boot and after every Inertia visit.

```
lang/ru/*.php  ─┐
                ├─→ HandleInertiaRequests (Inertia prop `translations`) ─→ app.js ─→ vue-i18n `t()`
lang/tk/*.php  ─┘
```

**Rule**: to add UI text, create the key in `lang/ru/xxx.php` **and** `lang/tk/xxx.php`. Nothing else. The same key then works on the server via `__('xxx.key')` and in Vue via `t('xxx.key')`.

Laravel's `:placeholder` syntax is normalized to vue-i18n's `{placeholder}` in `app.js`, so `t('notifications.created', { resource })` works with the exact same PHP string.

> Translations are cached forever in production (`inertia.translations` cache key). Run `php artisan cache:clear` after editing lang files on a production server.

### Locale resolution

`SetLocale` middleware runs on both `web` and `api` stacks, before authentication, so even a `401` comes back translated. Web requests read the locale from the session (set via `POST /locale/{locale}`), the mobile app sends an `X-Locale: tk` header.

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

Available methods: `notifySuccess()` · `notifyError()` · `notifyWarning()` · `notifyInfo()`. All accept a lang key + optional replace array and flash to `session('notification')`, which `HandleInertiaRequests` shares as an Inertia prop.

Key convention: `notifications.created|updated|deleted` with a `:resource` replace; resource names live in `lang/{ru,tk}/resources.php`.

### Frontend — Automatic Pickup

`AdminLayout.vue` watches `$page.props.notification` and passes it to `useNotificationStore`. No per-page setup needed. Toasts appear top-right and dismiss after 6 seconds.

### Notification Bell & Panel

The topbar bell shows the unread count and opens `NotificationPanel.vue` — a slide-in drawer listing all admin notifications (mark one/all as read, delete one/all), backed by `NotificationController` at `/notifications/*`. `unreadNotificationsCount` is a shared Inertia prop. Admin staff receive three database notifications: new client, new order, and auto-search exhausted.

---

## Image Upload Convention

Synchronous conversion is **prohibited** for order and task photos. They are stored as-is, then converted to WebP by a queued job.

```
Upload (order photo / task photo)
    └── Action stores the original on the `public` disk, row status = pending
            └── ConvertOrderPhotoJob / ConvertTaskPhotoJob (queued, 3 tries, 30s backoff):
                    ├── status → converting
                    ├── PhotoConverter::convert()  → WebP
                    ├── delete the original file
                    └── status → done  (on failure: status → failed, job retries)
```

Row status values: `pending` · `converting` · `done` · `failed`, so the UI can show progress and never blocks on processing.

Single **profile-sized** uploads are the exception — they convert inline because there is no status column to poll and the payload is tiny:

| Upload | Helper | Result |
|---|---|---|
| Client avatar | `PhotoConverter::convertToWidth()` | WebP, width 512 |
| Category content image | `PhotoConverter::convertContent()` | WebP, width 700 when > 800 KB |
| Category icon | `PhotoConverter::convertToMaxBytes()` | WebP ≤ 50 KB, width ≤ 512 (`CategoryIcon`) |

**One person, one avatar.** A master profile always hangs off a client account, so the photo lives on `clients.photo` — `masters` has no photo column. It is uploaded in three places, all landing in `StoreClientPhotoAction`: the admin clients form, `POST /client/auth/complete-registration` and `PATCH /client/me`. The last one is multipart-only via `POST` + `_method=PATCH`, since PHP fills `$_FILES` on `POST` alone.

---

## Realtime (Laravel Reverb)

### Broadcast channels

| Channel | Type | Who may subscribe | Carries |
|---|---|---|---|
| `orders` | public | admin panel | `OrderCreated`, `MasterAssigned`, `OrderStatusChanged` |
| `clients` | public | admin panel | `ClientCreated` |
| `available-orders` | public | the mobile app (master side) | `OrderSearchStarted`, `OrderSearchRadiusExpanded` — refresh signals carrying only an order id and a radius |
| `masters-map.{cityId}` | private | admin staff except operators | `MasterLocationUpdated` |
| `admin.pending-otps` | private | admin staff except operators | `PendingOtpCreated` |
| `client.{clientId}` | private | the Client that owns the Sanctum token | `MasterRespondedToOrder`, `MasterAssigned`, `OrderStatusChanged`, `MasterLocationUpdated` (pings tagged with their order) |
| `master.{masterId}` | private | the Client whose master profile has that id | `MasterAssigned`, `OrderStatusChanged`, `OrderResponseRejected`, `OrderResponseSuperseded`, `OrderResponseWithdrawn` |
| `App.Models.User.{id}` | private | the admin user | Laravel database notifications |

The mobile app authorizes private channels against `POST /api/v1/broadcasting/auth` (`auth:sanctum`). One token and one socket cover both `client.{id}` and `master.{id}`.

### New-order alert flow

1. A client creates an order → `OrderCreated` is dispatched
2. `NotifyAdminsOnNewOrder` sends `NewOrderNotification` to admin staff (database channel)
3. The admin panel receives the broadcast, shows a toast via `useNotificationStore.info()`
4. `public/sounds/alarm.mp3` plays — **only when the browser tab is active** (Page Visibility API)
5. The bell badge (`unreadNotificationsCount`) increments

---

## Orders: Auto-Search, Responses, Assignment

A client order is not routed by city and not assigned by an administrator. It is offered to masters by **geographic distance**, masters **respond**, and the **client picks** one of them.

```
order created (app, or admin "on behalf of a client")
   └── auto-search starts, radius grows every minute
          └── masters see it in their feed → POST respond     (order stays pending)
                 └── client sees the responses → approve one   → order assigned to that master
                                                                 other pending responses superseded
```

### Creating an order

- **Mobile app**: `POST /api/v1/client/orders` → `CreateClientOrderAction`. `client_name` is taken from the account, not from the request.
- **Admin panel**: `POST /orders` → `CreateOrderForClientAction` picks an existing client (`client_id`) or finds/creates one by phone, then calls the same `CreateClientOrderAction`, so the auto-search starts exactly as from the app. Every order has a client — `orders.client_id` is `NOT NULL`.

### How the radius grows

`radius(n) = n × initial`, where `n` is the minute of the search the order is currently in:

| Minute | Radius (defaults) |
|---|---|
| 1 | 20 km |
| 2 | 40 km |
| 3 | 60 km |
| 4 | 80 km |
| 5 | would be 100 km → **over the maximum, the search is marked exhausted** |

The radius is derived from `now() − search_started_at` on every tick rather than incremented, so a missed or duplicated scheduler run cannot drift it.

1. `CreateClientOrderAction` stamps `search_started_at = now()` and `search_radius_km = initial`, then fires `OrderSearchStarted`.
2. `orders:expand-search-radius` runs **every minute** and hands each searching order to `ExpandOrderSearchRadiusAction` → `OrderSearchRadiusExpanded` on `available-orders`. The app treats both events as a "reload your feed" signal.
3. Once `radius(n)` would exceed the maximum, `search_expired_at` is set, the radius stays at the maximum, `OrderSearchExhausted` fires and `NotifyAdminsOnOrderSearchExhausted` notifies admin staff; the order gets a **«Поиск мастера не дал результата»** badge in `/orders`. The radius stops growing, but the order **stays visible and open to responses** within the maximum radius. An administrator can restart the search (`POST /orders/{order}/restart-search` → `RestartOrderSearchAction`).
4. `orders:cancel-stale-orders` runs **hourly** and cancels orders still `pending` and unassigned `order_auto_cancel_hours` (default 48) after creation.

> **Both `schedule:work` and `queue:work` must be running.** Without the scheduler the radius never grows and stale orders never close; without the queue worker admins never receive notifications.

### Two master feeds

| Endpoint | Shows | Order |
|---|---|---|
| `GET /api/v1/master/orders/available` | Orders inside their own current search radius from the master's last GPS ping | nearest first, not paginated |
| `GET /api/v1/master/orders/by-category` | Every open order in the master's categories, regardless of distance; optional `category_id` filter. Each item carries `distance_km` and `is_within_radius` | newest first, paginated by 15 |

Both feeds share the base rules — an order is listed only when:

- it is `pending`, has no `master_id`, and its search has started;
- its category is one of the master's categories;
- it was not placed from the master's own client account;
- the master has neither declined it nor already responded to it.

**City is deliberately not part of the match** — an 80 km radius crosses city borders by design.

A master with no row in `master_locations` gets an empty `available` feed (a normal state right after login); `by-category` still lists orders, with `distance_km: null` and `is_within_radius: false`.

Both feeds expose the client's name and phone (`AvailableOrderResource` / `CategoryOrderResource`) so the master can call before responding.

### Distance is computed in two passes

`OrderRepository::availableForMaster()` pre-filters in SQL with a **plain-arithmetic bounding box** (no `acos`/`radians`), then settles the exact circle in PHP via `Order::distanceKmTo()` (haversine) and sorts nearest-first. MySQL, PostgreSQL and the SQLite build used by the test suite disagree on which trigonometric functions exist, so a `selectRaw` haversine would tie the feature to one driver.

### Responding

`POST /api/v1/master/orders/{order}/respond` → `RespondToOrderAction` creates an `OrderMasterResponse` (`pending`) and broadcasts `MasterRespondedToOrder` to the client. **The order is not assigned** — several masters may respond to the same order. Everything is re-checked server-side, because nothing stops an app from posting an id it never saw in a feed:

All failures are `422` with a localized message from `orders.errors.*`:

| Check | Lang key |
|---|---|
| order is `pending` and has no master | `already_claimed` |
| not the master's own order | `own_order` |
| master is available, active and subscribed | `master_unavailable` / `master_inactive` |
| category is one of the master's | `category_mismatch` |
| master has a GPS ping | `master_location_unknown` |
| distance ≤ the order's current `search_radius_km` | `out_of_search_radius` |
| no earlier response from this master | `already_responded` |

So an order from the `by-category` feed with `is_within_radius: false` is listed but cannot be responded to yet.

### The client decides

| Endpoint | Action | Effect |
|---|---|---|
| `GET /client/orders/{order}/responses` | — | Pending responses, nearest master first, with avatar and average rating |
| `POST …/responses/{id}/approve` | `ApproveOrderResponseAction` | In one transaction: order → `assigned` to that master, response → `approved`, every other pending response → `rejected` (`OrderResponseSuperseded` to those masters), `MasterAssigned` to both sides |
| `POST …/responses/{id}/reject` | `RejectOrderResponseAction` | Only that response → `rejected` (`OrderResponseRejected`); the order stays open |

When an order is cancelled (by the client, an admin or the stale sweep), `UpdateOrderStatusAction` withdraws all pending responses and notifies those masters with `OrderResponseWithdrawn`.

A master follows their open responses with `GET /api/v1/master/orders?filter=awaiting_response`.

### Declining

`POST /api/v1/master/orders/{order}/decline` writes to `order_master_declines` (unique on `order_id + master_id`, idempotent). It only hides the order from that master's own feeds — the search keeps running and other masters still see it.

---

## Becoming a Master

One mobile app, one account. Everyone registers as a **client**; the master role is applied for from inside the app and granted by an administrator.

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

- **Approval and access are separate.** Approving only flips the status; access comes from a subscription, which the same screen can issue in one go because the master pays the owner in person. An approved master with no subscription is a valid state — the app shows "renew", not "apply".
- **Re-applying reuses the row.** Only a `rejected` applicant may submit again; the verdict fields are cleared and the same `masters` row goes back to `pending`, so one client never accumulates several master profiles (`masters.client_id` is unique).
- **Losing the master role never signs anyone out.** Deactivation and rejection leave Sanctum tokens alone — the token belongs to the client account. `MasterObserver` only drops `is_available`.
- **Name, phone and city live on the client.** `ClientObserver` mirrors them onto the master profile; `PATCH /master/me` edits only trade details (`category_ids`, `experience_years`, `about`).

### Deleting accounts

Because a master is a role on a client account, the two deletes are not symmetric.

| Action | Effect |
|---|---|
| Delete **master** | Removes the role only. The client account, its orders, its login and its avatar all stay |
| Delete **client** | Takes the master profile, the orders this person *placed*, their tasks, photos and reviews, and the avatar file |

Both are blocked while the master has work on the books — completed orders (the client's history would be gutted) and assigned / in-progress orders (`orders.master_id` is `ON DELETE SET NULL`, so the job would be left orphaned). `DeleteMasterAction` / `DeleteClientAction` throw `MasterException` / `ClientException`, and the controller turns it into a `notifyError`. Cancelled orders never block anything.

**Children are deleted through Eloquent, not the foreign keys.** A database-level cascade never fires model events, so the rows would vanish while their files stayed on disk. `ClientObserver::deleting` deletes the master profile and the client's orders through the models, which lets `OrderObserver::deleted` drop `orders/{id}` from the public disk. The `ON DELETE CASCADE` keys stay as the backstop for anything that bypasses the model.

---

## Master Subscriptions

The service owner sells masters timed access to the platform. **This is the only revenue stream** — the platform does not pay masters and does not track their per-order earnings. The client pays the master directly; `orders.final_price` is bookkeeping set by an administrator, nothing more.

### Two tables

| Table | Purpose |
|---|---|
| `subscription_plans` | The owner's tariffs: bilingual name/description, `duration_days`, `price`, `is_active`, `sort_order`. **Soft deleted** so already sold subscriptions never lose their link |
| `master_subscriptions` | A purchase. Carries a **snapshot** of `plan_name`, `price_paid` and `duration_days` — later edits to the plan never rewrite history |

There is no seeder for plans — the owner creates them in the admin panel.

### `access_expires_at` has exactly one writer

- it is not editable by hand — `Store/UpdateMasterRequest` do not accept it;
- every subscription action funnels through `App\Actions\Concerns\SyncsMasterAccess`, which sets it to `MAX(expires_at)` across the master's `active` + `pending` subscriptions;
- with nothing left the deadline is set to `now()` — **never `null`**, because `null` means *unlimited* to `Master::hasActiveAccess()` (legacy masters created before subscriptions keep that meaning);
- a new master created without a plan starts with access closed.

Free access is granted the same way as paid: issue a subscription with `price_paid = 0` and a note, which keeps it auditable.

### Renewal is a queue, not an overwrite

At most one subscription per master is `active`. Selling to a master who already has a running one creates the new purchase as `pending`, starting the moment the current one ends:

```
├─ active   01.01 → 31.01   ← running
└─ pending  31.01 → 02.03   ← paid for, waiting its turn
access_expires_at = 02.03   (MAX over active + pending)
```

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

Hourly, `withoutOverlapping()`. One pass: `active` rows past `expires_at` → `expired`; `pending` rows whose `starts_at` has arrived → `active` (only when nothing else is running for that master); re-derive `access_expires_at` for every touched master. Idempotent — a skipped run catches up on the next tick.

### Who buys

The administrator issues subscriptions manually after taking payment — there is no payment gateway. The mobile API is **read-only** for masters:

- `GET /api/v1/master/subscription-plans` is **public**: someone weighing whether to apply has no master profile yet.
- `GET /api/v1/master/subscription` runs under `ensure.master:allow-expired`, so a master with a lapsed subscription can still see "expired on …, renew".

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

---

## Live Master Tracking

When the mobile app pings its location:

1. The master `POST`s to `/api/v1/master/{master}/location` with the Sanctum token (`{master}` must be the token owner)
2. `UpdateMasterLocationAction` stores the ping and dispatches `MasterLocationUpdated`
3. The event goes to the private `masters-map.{cityId}` channel (admin map) and, when the ping is tagged with an order, to that order's `client.{clientId}`

**Every ping carries an `order_id` whenever one can be determined.** A tag sent by the app is verified (`OrderRepository::findForMasterOrFail` + `OrderStatus::isTrackable()`) — never trusted, since a foreign id would stream the master into a stranger's map. An untagged ping is bound server-side to the master's single open (`assigned`/`in_progress`) job; with several open jobs it stays untagged rather than guessing.

The client app replays the trail through `GET /api/v1/client/orders/{order}/track` (500 newest pings, optional `since`) and follows new points over the socket.

Pings accumulate quickly, so `locations:prune` runs nightly: untagged pings are kept 7 days, order-tagged pings 30 days after the order closed (`--ping-days`, `--order-days`).

### Basemap Rendering

The base layer is rendered by **MapLibre GL**, mounted into Leaflet via the `L.maplibreGL` bridge, so markers, popups, trajectories and channel code stay pure Leaflet.

- **Everything is self-hosted** — public OSM tile servers are blocked in Turkmenistan.
  - **Vector tiles**: `GET /tiles/{z}/{x}/{y}.pbf` → `TilesController::vectorTile()` reads the MBTiles archive at `storage/maps/tiles.mbtiles` (`MBTILES_PATH`). Tiles are gzipped in the TMS row scheme, so the controller flips Y and sets `Content-Encoding: gzip`. A missing tile returns `204` — normal for sea and unmapped areas.
  - **Style, glyphs, sprites**: static files under `public/maps/`.
  - The archive is **not in git** (~118 MB) — copy it onto each machine manually.
- **Style URL is not hardcoded**: `TILES_STYLE_URL` → `config('services.tiles.style_url')` → `tilesStyleUrl` Inertia prop.
- `maplibre-gl` is a lazy chunk — loaded only on map screens.
- `utils/loadMapStyle.js` rewrites the style's `tiles`/`glyphs`/`sprite` URLs to absolute ones (MapLibre resolves them inside a Web Worker with no page origin). `Masters/Map.vue`, `Orders/Show.vue` and `Orders/Partials/CreateOrderModal.vue` all go through it.

> **Production**: serve the whole app over **HTTPS** — an HTTP tile origin is blocked as mixed content on an HTTPS panel.

### Per-Order Tracking (`/orders/{order}`)

When a master is assigned and the order is trackable (`is_trackable` on `OrderResource`):

- loads the trail from `GET /orders/{order}/master-trajectory` → `MasterLocationRepository::trackForOrder()` (only pings tagged with this order, oldest first);
- subscribes to `masters-map.{cityId}` and extends the polyline only on events whose `order_id` matches this order;
- shows a live distance and ETA chip (assuming 60 km/h);
- unsubscribes on `onBeforeUnmount`.

**Testing without the Flutter app**:

```bash
php artisan reverb:start                                          # terminal 1
php artisan master:simulate-movement 1 --interval=3 --steps=60    # terminal 2
```

---

## API (Mobile App)

| Rule | Detail |
|---|---|
| Base path | `/api/v1/` (`routes/api/v1.php`) |
| Controllers | `app/Http/Controllers/Api/V1/` (master) and `Api/V1/Client/` (client) — never shared with web controllers |
| Auth | Sanctum, token-based. **One token for both roles**: sign-in issues a `mobile-client` token gated by `ensure.client`, and the same token opens the master endpoints through `ensure.master` |
| Responses | Always via Eloquent API Resources |
| Errors | Localized JSON from the global handler in `bootstrap/app.php` |
| Locale | Send `X-Locale: ru|tk` |

### One account, two roles

`EnsureMaster` resolves the master profile from the authenticated client and swaps it into the request, so master controllers read `$request->user()` as a `Master`. It answers `403` with a machine-readable `reason`:

| `reason` | Meaning |
|---|---|
| `token_required` | Not a client token |
| `not_a_master` | Never applied |
| `application_pending` | Application still in review |
| `application_rejected` | Application turned down (`rejection_reason` explains) |
| `disabled` | Profile deactivated by an administrator |
| `access_expired` | Subscription lapsed |

`GET /api/v1/client/me` carries `master_status`, `master_id` and `has_master_access` so the app knows which half to show without a second call.

### Master API — `ensure.master` unless marked otherwise

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/master/subscription-plans` | public | Subscription price list |
| `GET` | `/master/subscription` | `ensure.master:allow-expired` | Own subscription: current one, access deadline, history |
| `GET` `PATCH` | `/master/me` | Sanctum | Profile; edit `category_ids`, `experience_years`, `about` |
| `PATCH` | `/master/availability` | Sanctum | Toggle "ready for work" |
| `POST` | `/master/{master}/location` | Sanctum | GPS ping; `{master}` **must** match the token owner |
| `GET` | `/master/orders` | Sanctum | Own orders: `filter=active` / `history` / `awaiting_response` |
| `GET` | `/master/orders/available` | Sanctum | Feed: open orders inside their radius, nearest first |
| `GET` | `/master/orders/by-category` | Sanctum | Feed: every open order in the master's categories, paginated, `category_id` filter |
| `POST` | `/master/orders/{order}/respond` | Sanctum | Offer myself for an order (the client decides) |
| `POST` | `/master/orders/{order}/decline` | Sanctum | Hide an order from this master's feeds |
| `GET` | `/master/orders/{order}` | Sanctum | Order details (assigned to this master) |
| `POST` | `/master/orders/{order}/start` | Sanctum | `assigned` → `in_progress` |
| `POST` | `/master/orders/{order}/complete` | Sanctum | `in_progress` → `completed` |
| `POST` | `/master/orders/{order}/tasks` | Sanctum | Add a performed task |
| `POST` | `/master/orders/{order}/tasks/{task}/photo` | Sanctum | Upload a before/after photo (max 2 per type) |
| `DELETE` | `/master/orders/{order}/tasks/{task}` | Sanctum | Remove a task |

### Client API — `ensure.client` unless marked public

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/client/settings` | public | App rules/terms — both roles read it |
| `GET` | `/client/{oblasts,regions,cities}` | public | Geography catalog |
| `GET` | `/client/categories`, `/client/categories/search` | public | Category tree / search |
| `GET` | `/client/categories/{category}/content` | public | Category landing content |
| `GET` | `/client/banners` | public | Promo banners |
| `POST` | `/client/auth/request-otp` | public | Send OTP to phone number |
| `POST` | `/client/auth/verify-otp` | public | Verify OTP, returns the Sanctum token |
| `POST` | `/client/auth/complete-registration` | Sanctum | Save name + city (+ avatar) after first login |
| `POST` | `/client/auth/logout` | Sanctum | Revoke the current token |
| `GET` `PATCH` | `/client/me` | Sanctum | Read / update profile (includes the master role on this account) |
| `GET` `POST` | `/client/master-application` | Sanctum | Own application / "become a master" |
| `GET` `POST` | `/client/orders` | Sanctum | List / create orders |
| `GET` `PATCH` | `/client/orders/{order}` | Sanctum | Read / update an order (only while `pending`) |
| `POST` | `/client/orders/{order}/cancel` | Sanctum | Cancel while no master is assigned |
| `POST` | `/client/orders/{order}/review` | Sanctum | Review after completion, once |
| `GET` | `/client/orders/{order}/responses` | Sanctum | Pending master responses |
| `POST` | `/client/orders/{order}/responses/{id}/approve` | Sanctum | Pick the master |
| `POST` | `/client/orders/{order}/responses/{id}/reject` | Sanctum | Turn one master down |
| `GET` | `/client/orders/{order}/track` | Sanctum | Master's trail for the client map |

**Broadcast auth**: `POST /api/v1/broadcasting/auth` (`auth:sanctum`).

Run `php artisan route:list --path=api/v1` for the authoritative list.

### API documentation

- **Scramble** generates OpenAPI docs from routes, Form Requests and Resources at `/docs/api` (spec at `/docs/api.json`). Access is gated by `RestrictedDocsAccess`: open in local, closed elsewhere unless the `viewApiDocs` gate allows it.
- **Bruno** — `bruno/` is a ready-to-run collection with request bodies, example responses and error tables in each request's Docs tab. Open the folder, pick the `local` environment; **Verify OTP** saves `{{token}}`, and the folders follow a real session: sign in → catalog → order → become a master → master work → realtime.

---

## OTP Delivery & Manual Fallback

OTP codes are generated by `DispatchOtpAction`, kept in the cache for `OTP_TTL_MINUTES`, and sent through an event:

```
DispatchOtpAction ─▶ SmsCodeRequested ─▶ SendSmsCode (sync listener) ─▶ SmsSender (sms.driver)
                                                                        ├── modem: POST {SMS_GATEWAY_URL}/emit-otp, X-Otp-Secret ─▶ socket-server ─▶ phone
                                                                        └── log:   code written to the log (development; refused in production)
```

The listener is synchronous on purpose: a failed send must reach `DispatchOtpAction` in the same request. `ModemSmsSender` throws `OtpException` on `503` (no phone connected), any other non-2xx or a timeout, and `RuntimeException` when `SMS_GATEWAY_URL` or `OTP_SECRET` is empty. The gateway contract is documented in [socket-server/README.md](socket-server/README.md).

**When the gateway is unreachable, login is not blocked:**

1. The code is still written to cache, so `verify-otp` accepts it as usual.
2. A row is parked in `pending_otps` (only the latest code per phone survives).
3. `request-otp` answers `200` with `delivery: "manual"` and a localized `delivery_message`.
4. The **OTP-коды** section and the dashboard render `PendingOtpPanel.vue`, which polls `GET /pending-otps/data` every 30 s and shows the code, phone and a live countdown, so staff can dictate it.
5. Parking a code fires `PendingOtpCreated` — a queued broadcast on the private `admin.pending-otps` channel, so the login request never waits on Reverb.
6. The sidebar item carries an amber badge fed by the `pendingOtpCount` shared prop, plus a toast and alarm sound.

Codes live only as long as `OTP_TTL_MINUTES`. Operators never see them.

---

## System Status & Monitoring

`GET /system-status` (any authenticated user) powers the status dots in `AdminLayout.vue` and the monitoring cards on `/settings`. Every number is measured — nothing is simulated on the frontend.

| Source | What it measures | How |
| --- | --- | --- |
| **Queue** | worker alive, pending jobs, jobs finished today | `Queue::looping` writes `queue:worker_heartbeat` (stale after 120 s); `Queue::size()`; `Queue::after` increments `queue:processed:{Y-m-d}` |
| **Reverb** | reachability, open channels, connections, response time | `ReverbMetricsService` calls the signed Pusher-compatible `/apps/{id}/channels` and `/apps/{id}/connections` endpoints |
| **OTP gateway** | bridge alive, connected phones, last OTP `SmsSender::status()` → `GET {SMS_GATEWAY_URL}/health` (2 s timeout) + `otp_gateway:last_sent` cache key; the card also shows `SMS_DEVICE_LABEL` and the driver |
| **WebSocket card** | this browser's own socket | read from `window.Echo.connector.pusher` |

`SystemStatusService` caches the snapshot for 10 s; the **Переподключить** buttons request `?fresh=1` to bypass it. An unreachable source shows `—`, never `0`.

---

## Scheduled Commands

All registered in `bootstrap/app.php` → `withSchedule()`, each `withoutOverlapping()`.

| Command | Frequency | Purpose |
|---|---|---|
| `orders:expand-search-radius` | every minute | Grow the auto-search radius, mark exhausted searches |
| `orders:cancel-stale-orders` | hourly | Cancel orders unassigned past `order_auto_cancel_hours` |
| `subscriptions:expire` | hourly | Expire / start subscriptions, re-derive master access |
| `locations:prune` | daily 03:30 | Delete GPS pings past their retention window |

---

## Adding a New Feature

```
Step 1 — Database        php artisan make:migration … / make:model Xxx -f
Step 2 — Repository      app/Repositories/XxxRepository.php — all queries live here
Step 3 — Business logic  php artisan make:class Actions/CreateXxxAction (or a Service)
Step 4 — Controller      php artisan make:controller XxxController — thin, HTTP only
Step 5 — Validation      php artisan make:request StoreXxxRequest / make:resource XxxResource
Step 6 — Vue             resources/js/Pages/Xxx/Index.vue (+ Partials/), dark mode + i18n, AdminLayout
Step 7 — Translations    keys in lang/ru/xxx.php AND lang/tk/xxx.php
Step 8 — Tests           php artisan make:test --phpunit XxxTest — happy path, validation,
                         authorization, edge cases
Step 9 — API docs        for API endpoints, add or update the request in bruno/
```

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
vendor/bin/phpstan analyse         # Larastan level 6

php artisan test --compact                                    # All tests
php artisan test --compact tests/Feature/OrderTest.php        # Single file
php artisan test --compact --filter=test_master_sees          # By name
```

Every feature, action and model must have PHPUnit tests covering the happy path, validation failure and edge cases. Tests are never deleted without approval.

**Test environment**: `phpunit.xml` is the single source of truth (sqlite `:memory:`, `array` cache, `sync` queue). `tests/bootstrap.php` copies those `<env>` entries into `$_SERVER` before Laravel boots — required because `docker-compose` injects the real `.env` into the container, and Laravel's env repository reads `$_SERVER` ahead of `putenv()`. Without it the suite would run against the live Redis and MySQL. Add new test-only variables to `phpunit.xml`.

---

## Useful Commands

```bash
# ── Development ──────────────────────────────────────────────────────────────
npm run dev / npm run build
php artisan serve

# ── Workers & services ───────────────────────────────────────────────────────
php artisan queue:work
php artisan reverb:start
php artisan schedule:work
php artisan schedule:list                 # Verify the four scheduled commands

# ── One-off runs for debugging ───────────────────────────────────────────────
php artisan orders:expand-search-radius
php artisan orders:cancel-stale-orders
php artisan subscriptions:expire
php artisan locations:prune --ping-days=7 --order-days=30
php artisan master:simulate-movement 1 --interval=3 --steps=60

# ── Database ─────────────────────────────────────────────────────────────────
php artisan migrate
php artisan migrate:fresh --seed
php artisan storage:link

# ── Inspection ───────────────────────────────────────────────────────────────
php artisan route:list --path=api/v1
php artisan config:show database
php artisan cache:clear                   # Also flushes the translations cache
php artisan scramble:export               # Export the OpenAPI spec to api.json
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

---

## Deployment

Production runs on Docker (`docker-compose.yml` + `docker-compose.prod.yml`), configured from `.env.production.example`. [Laravel Cloud](https://cloud.laravel.com/) is an alternative target.

Outside Docker, before going live:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

Checklist:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real HTTPS URL
- Serve over **HTTPS** (required for the self-hosted map tiles)
- `queue:work` and `schedule:work` running under a supervisor
- `reverb:start` running, `REVERB_SCHEME=https`, the WebSocket port proxied
- `socket-server/` running with a long random `OTP_SECRET` (the same value as in the Laravel `.env` and on the phone), `ENABLE_TEST_PAGE=false`; the phone connects through HTTPS `/socket.io/`, or port 3000 is firewalled to the phone's IP — `/emit-otp` and `/health` are never public
- `storage/maps/tiles.mbtiles` copied onto the server (not in git)
- The public `orders` channel carries `OrderCreated` with the client's name — move it to a private, staff-gated channel before exposing the panel publicly
