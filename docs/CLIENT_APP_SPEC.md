# Client Mobile App — Technical Specification (Flutter)

> Audience: Flutter developer building the client side of the mobile application.
> Backend: Laravel 11 + Sanctum + Reverb (WebSockets). All endpoints are versioned under `/api/v1/`.
> Companion docs: [`MASTER_APP_SPEC.md`](./MASTER_APP_SPEC.md) — what unlocks on the same
> account/token once a client becomes an approved master; [`MASTER_APP_MAP_INTEGRATION.md`](./MASTER_APP_MAP_INTEGRATION.md) — vector map screen.

> **One app, one account.** Everyone signs in as a client. "Become a master" (§7) adds a
> master profile on top of that same account — same login, same token, no separate app.
> This document covers the client half; §7 is the hand-off point into `MASTER_APP_SPEC.md`.

---

## How to use this document with Cursor

Feed this file to Cursor as project context and ask it to scaffold, in order:

1. `ApiClient` (Dio) — base URL from `--dart-define`/`.env`, interceptors for
   `Authorization`, `X-Locale`, `Accept: application/json`; a single error mapper that
   turns every response in §2.3 into a typed `ApiException`.
2. Models (freezed/json_serializable) for every JSON shape in §3–§6.
3. Repositories: `AuthRepository`, `CatalogRepository`, `ClientProfileRepository`,
   `ClientOrderRepository`, `MasterApplicationRepository` — one per section below.
4. Secure token storage (`flutter_secure_storage`) + an auth state notifier that exposes
   `master_status` / `has_master_access` from `client.me` so the UI can branch into the
   master flow at the right point.
5. `RealtimeService` (`pusher_channels_flutter`, Reverb) subscribed to `private-client.{id}`
   per §7.
6. Screens per §8, in that order.

Do not re-derive the master-side contract from this file — once `has_master_access` is
true, hand Cursor `MASTER_APP_SPEC.md` for that half of the app.

---

## 1. Overview

The client side lets a user:

1. Sign in with just a phone number (OTP, no password).
2. Browse the service catalog (categories, city-scoped) and promo banners.
3. Create an order (problem description, photos, location) and track it live.
4. See the assigned master's name, phone and live location once one is assigned.
5. Cancel a pending/assigned order, leave a rating + review after completion.
6. Apply to become a master and follow that application's status (§7).

Languages: **Turkmen (primary)**, **Russian (secondary)** — no hardcoded strings, drive
UI text off `X-Locale` the same way the backend does.

---

## 2. Conventions (shared with the master side)

### 2.1 Base URL & headers

- Base URL: `{APP_URL}/api/v1` (dev on Android emulator: `http://10.0.2.2:8000/api/v1`;
  iOS simulator/desktop: `http://localhost:8000/api/v1`)
- `Content-Type: application/json`, except order create/update which is `multipart/form-data`
  (they carry photos)
- `Accept: application/json`
- `X-Locale: ru` or `tk` — otherwise the backend falls back to `Accept-Language`, then `ru`.
  Only `ru`/`tk` are recognized.
- `Authorization: Bearer <token>` on every endpoint below marked **[auth]**.

### 2.2 Auth model

Sanctum personal access token (`mobile-client`), no refresh token, no expiry by default.
Obtained via OTP (§3), stored in `flutter_secure_storage`, sent as Bearer on every
subsequent request — including the master-side ones once unlocked.

### 2.3 Error contract (applies to the whole API, not just this doc)

| Status | Shape | Meaning |
|---|---|---|
| `401` | `{ "message" }` | Missing/invalid/revoked token |
| `403` | `{ "message" }` (client) or `{ "message", "reason" }` (master-side, see `MASTER_APP_SPEC.md` §2.4) | Forbidden |
| `404` | `{ "message" }` | Not found |
| `422` | `{ "message", "errors": { "field": ["…"] } }` | Validation — standard Laravel `ValidationException` shape |
| `429` | `{ "message" }` | Rate limited |
| `500` | `{ "message" }` | Unexpected — never leaks internals outside `APP_DEBUG` |

Every `message` is already localized per `X-Locale`. Map `422.errors` straight onto form
fields; don't try to parse `message` for anything but a toast/snackbar fallback.

### 2.4 Pagination

`GET /client/orders` is a standard Laravel paginator:
```json
{ "data": [ ... ], "links": { ... }, "meta": { "current_page": 1, "last_page": 3, ... } }
```
Every other list endpoint below (`oblasts`, `regions`, `cities`, `categories`, `banners`,
`categories/search`) returns a flat `{ "data": [...] }` — no pagination.

---

## 3. Auth

| Step | Endpoint | Auth | Body | Response |
|---|---|---|---|---|
| Request code | `POST /client/auth/request-otp` | public | `{ "phone": "+993..." }` | `{ "message", "delivery": "sms"\|"manual", "delivery_message": string\|null }` |
| Verify code | `POST /client/auth/verify-otp` | public | `{ "phone", "code": "123456" }` (code = 6 chars) | `{ "token", "is_new": bool, "client": ClientProfile }` |
| Finish profile | `POST /client/auth/complete-registration` | **[auth]** | `{ "name", "city_id": int, "photo"? }` — multipart when `photo` is sent | `{ "client": ClientProfile }` |
| Logout | `POST /client/auth/logout` | **[auth]** | — | `204` |

**Handle `delivery: "manual"`** — the SMS gateway was unreachable; the code is still valid
but was parked for an operator to read out over the phone. Show `delivery_message`
instead of a generic "SMS sent" screen. The OTP input step is otherwise unchanged.

Save `token` from `verify-otp` immediately. If `is_new` is `true` (or `ClientProfile.name`
is empty), route to the "finish profile" screen before anything else — `city_id` is
required by several endpoints below and by the master application form.

`POST /client/auth/logout` invalidates the token **server-side** — always clear local
storage in the same call site, don't rely on a 401 to catch a stale token later.

**Avatar** — send `photo` on `complete-registration` as `multipart/form-data`
(`jpeg|png|jpg|webp`, max 5 MB). The server downscales it to **512 px wide**
(height proportional, smaller images untouched) and re-encodes it as WebP inline, so the
response already carries the final `photo_url` — no polling, no conversion status. Upload
the original straight from the camera roll; client-side resizing is wasted work.
Re-posting the endpoint with a new `photo` replaces the file; omitting it keeps the
current one. See §5 for changing the avatar later.

`photo_url` is **absolute** — scheme, host and port of the server that answered the request,
so it can go straight into an `Image.network(...)` with no base-URL concatenation.
`photo` next to it is the raw storage path, kept for clients that build their own URLs.

There is exactly **one** avatar per person: if the account also carries a master profile,
the master half of the app shows this same file. There is no separate master photo upload.

### ClientProfile

```json
{
  "id": 1,
  "name": "string",
  "phone": "string",
  "photo": "clients/abc123.webp" | null,
  "photo_url": "https://.../storage/clients/abc123.webp" | null,
  "city_id": 1,
  "city": { "id": 1, "name": "string" } | null,
  "master_status": "pending" | "approved" | "rejected" | null,
  "master_id": 1 | null,
  "has_master_access": false,
  "created_at": "2026-08-15 10:00:00"
}
```

`has_master_access` is the single flag to gate showing the master half of the UI — it's
`true` only once approved **and** active **and** carrying a live subscription. `master_status`
alone is not enough (an approved master can still be waiting on a subscription).

---

## 4. Public catalog (no token)

| Endpoint | Response |
|---|---|
| `GET /client/settings` | `{ "data": { "content": "string" } }` — app rules/terms shown pre-registration; the only rules endpoint, the master screens read it too |
| `GET /client/oblasts` | `{ "data": [{ "id", "name" }] }` |
| `GET /client/regions` | `{ "data": [{ "id", "name", "oblast_id", "oblast": { "id", "name" } }] }` |
| `GET /client/cities` | `{ "data": [{ "id", "name", "oblast_id" }] }` — use for the city picker in profile/order forms |
| `GET /client/categories` | `{ "data": [Category] }` — full tree, parents carry `children[]` (see below) |
| `GET /client/categories/search?q=` | `{ "data": [CategorySearchResult] }` — flat list, `q` min 2 chars |
| `GET /client/categories/{id}/content` | `{ "id", "title", "description", "price", "image_url" }` — service detail card, `404` if the category or its content is inactive |
| `GET /client/banners` | `{ "data": [{ "id", "image_url", "url", "sort_order" }] }` — home screen carousel |

**`categories` and `categories/search` return different shapes** — don't share one model:

```json
// Category (from GET /client/categories — tree)
{
  "id": 1, "name_ru": "string", "name_tk": "string", "parent_id": null,
  "icon_type": "preset" | "image" | "custom", "icon": "string",
  "children": [ /* same shape, one level deep */ ]
}

// CategorySearchResult (from GET /client/categories/search)
{
  "id": 4, "name": "string", "parent_id": 1,
  "icon_type": "preset" | "image" | "custom", "icon": "string", "icon_url": "https://…",
  "parent": { "id": 1, "name": "string" } | null
}
```
`icon_type` tells you how to draw `icon_url`: `preset` and legacy `custom` are monochrome
SVGs meant to be tinted with the current text color, `image` is a full-color WebP (≤ 50 KB)
that should be rendered as a plain image.
`categories` gives you raw `name_ru`/`name_tk` (pick by current locale yourself);
`categories/search` is already localized server-side into `name`. Only **leaf** categories
(non-null `parent_id`) are valid picks for creating an order or applying as a master.

---

## 5. Client profile

| Endpoint | Body | Response |
|---|---|---|
| `GET /client/me` **[auth]** | — | `ClientProfile` (§3) |
| `PATCH /client/me` **[auth]** | `{ "name"?, "city_id"?, "photo"? }` (all optional) | `ClientProfile` |

**Changing the avatar** — `photo` is a file, so the request has to be
`multipart/form-data`, and PHP only fills `$_FILES` on `POST`. Send it as
`POST /client/me` with a `_method=PATCH` field (same trick as updating order photos, §6);
a genuine `PATCH` arrives with the file silently dropped. Same rules as on registration:
`jpeg|png|jpg|webp`, max 5 MB, converted inline to a 512 px-wide WebP, previous file
deleted. Leaving `photo` out of the request never blanks the stored avatar.

---

## 6. Orders

| Endpoint | Body | Response |
|---|---|---|
| `GET /client/orders?status=` **[auth]** | query: `pending`\|`assigned`\|`in_progress`\|`completed`\|`cancelled`, omit for all | paginated `ClientOrder[]` (§2.4) |
| `GET /client/orders/{id}` **[auth]** | — | `ClientOrder` |
| `POST /client/orders` **[auth]** | multipart, see below | `201 ClientOrder` |
| `PATCH /client/orders/{id}` **[auth]** | multipart, all fields `sometimes` | `ClientOrder` |
| `POST /client/orders/{id}/cancel` **[auth]** | `{ "reason"?: string ≤500 }` | `ClientOrder` |
| `POST /client/orders/{id}/review` **[auth]** | `{ "rating": 1..5, "comment"?: string ≤1000 }` | `201 { "id", "rating", "comment", "created_at" }` |

### Create / update body (multipart — always, even with no photos)

```
city_id: int, required on create — exists in cities
category_id: int, required on create — exists in categories (must be a leaf)
description: string, 5..2000
client_phone: string, 6..20
client_address: string|null, ≤255
client_lat: float, -90..90
client_lng: float, -180..180
photos[]: up to 4 files, jpeg/jpg/png/webp, ≤8MB each
remove_photo_ids[]: int[] — update only, drops existing photos by id
```
On update every field is `sometimes` (send only what changed); on create the first six
are required. `client_lat`/`client_lng` should come from the device's GPS or a map pin —
they're what makes the order visible to nearby masters' auto-search.

### ClientOrder

```json
{
  "id": 1,
  "status": "pending",
  "status_label": "localized string",
  "description": "string",
  "client_address": "string|null",
  "client_lat": "37.950000",
  "client_lng": "58.380000",
  "final_price": null,
  "city": { "id", "name" },
  "category": { "id", "name" },
  "master": {
    "id", "name", "phone",
    "location": { "lat": 0.0, "lng": 0.0, "recorded_at": "datetime" } | null
  } | null,
  "photos": [{ "id", "url", "status" }],
  "tasks": [{
    "id", "title", "description",
    "before_photo_url": "url|null", "after_photo_url": "url|null",
    "before_status": "string", "after_status": "string"
  }],
  "review": { "id", "rating", "comment", "created_at" } | null,
  "assigned_at": "datetime|null", "started_at": "datetime|null",
  "completed_at": "datetime|null", "cancelled_at": "datetime|null",
  "created_at": "datetime"
}
```

`master.location` is only populated while `status` is `assigned` or `in_progress` — that's
what backs the live-tracking map on the order-detail screen; pair it with the
`master.location.updated`-adjacent `order.status.changed`/`master.assigned` events in §7
so the pin moves without polling.

**Lifecycle**: `pending → assigned → in_progress → completed`, or `cancelled` at any point
before `completed`. `completed`/`cancelled` are final — no further transitions.

`tasks` mirror what the master logs on their side (`MASTER_APP_SPEC.md` §3.2) — read-only
here, useful for an itemized "what was done" view.

---

## 7. Realtime (Laravel Reverb)

- Auth endpoint for private channels: `POST {APP_URL}/api/v1/broadcasting/auth`, header
  `Authorization: Bearer <token>`.
- Flutter package: `pusher_channels_flutter` (Reverb speaks the Pusher protocol). Get
  `host`/`port`/`key`/`scheme` for the target environment from the backend team — they
  live in `.env`, not the repo (dev values are in `MASTER_APP_SPEC.md` §4 if you're testing
  against the same local backend).
- Subscribe to `private-client.{clientId}` right after login (`clientId` = `ClientProfile.id`).
  If `has_master_access` is also true, the same connection can additionally subscribe to
  `private-master.{masterId}` — see `MASTER_APP_SPEC.md` §4.

| Event | Payload |
|---|---|
| `.master.assigned` | `{ order_id, client_name, master_id, master_name, master_phone }` — a master just claimed the client's order; refresh the order and start listening for location updates |
| `.order.status.changed` | `{ order_id, client_name, from, to, to_label }` — patch the order's `status` locally instead of re-fetching |

Both are verified against `App\Events\MasterAssigned` / `App\Events\OrderStatusChanged`
and `routes/channels.php` (`client.{clientId}` channel) directly in the backend source.

---

## 8. "Become a master" (hand-off into `MASTER_APP_SPEC.md`)

| Endpoint | Body | Response |
|---|---|---|
| `GET /client/master-application` **[auth]** | — | `{ "data": MasterApplication \| null }` (`null` = never applied) |
| `POST /client/master-application` **[auth]** | `{ "city_id", "category_ids": int[1..10], "experience_years": 0..70, "about"?: ≤1000 }` | `201 { "data": MasterApplication }` |

`category_ids` must all be **leaf** categories. Requires a completed profile
(`name`/`city_id` set) first.

```json
// MasterApplication
{
  "id": 1, "status": "pending" | "approved" | "rejected",
  "experience_years": 5, "about": "string|null",
  "rejection_reason": "string|null", "reviewed_at": "datetime|null",
  "has_access": false, "access_expires_at": "date|null",
  "city": { "id", "name" }, "categories": [{ "id", "name" }],
  "created_at": "datetime"
}
```

A `rejected` application can be resubmitted with the same `POST` — it goes back to
`pending`. An `approved` one returns `422` on resubmission.

Once `GET /client/me` reports `has_master_access: true`, everything under `/master/*`
opens on this same token — stop reading this document and switch to
[`MASTER_APP_SPEC.md`](./MASTER_APP_SPEC.md) §3 onward for that side of the app.

---

## 9. UI / UX screens

Build in this order:

1. **Splash** — branch on stored token: none → Login; present → `GET /client/me`, then
   route home (and remember `master_status`/`has_master_access` for the profile tab).
2. **Login** — phone input → OTP input (handle `delivery: "manual"`) → profile completion
   if `is_new`.
3. **Home** — category grid/list (`GET /client/categories`), banner carousel, search bar
   (`categories/search`).
4. **Category / service detail** — `categories/{id}/content`, CTA "Order this".
5. **Create order** — description, photo picker (≤4), address/map pin (feeds
   `client_lat`/`client_lng`), phone (prefilled from profile, editable).
6. **My orders** — tabs by status, pull to refresh, badge for `assigned`/`in_progress`.
7. **Order detail** — status timeline, master card (name/phone/call button) once assigned,
   live map once `master.location` is present, cancel button while cancellable, review
   form once `completed`.
8. **Become a master** — application form + status screens for `pending`/`rejected`
   (mirrors `MASTER_APP_SPEC.md` §6.3, same account).
9. **Profile** — name/city edit, language switch (tk/ru), "become a master" entry point,
   logout.

---

## 10. Local testing

```bash
# 1. Request a code — a client account is created on the fly if the phone is new.
curl -X POST http://localhost:8000/api/v1/client/auth/request-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222"}'

# 2. Exchange it for a token (delivery: "manual" → read the code from the admin
#    panel's "OTP-коды" section instead of an SMS).
curl -X POST http://localhost:8000/api/v1/client/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222", "code": "1234"}'

# 3. Finish the profile (multipart — drop -F photo to register without an avatar).
curl -X POST http://localhost:8000/api/v1/client/auth/complete-registration \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -F "name=Aman" -F "city_id=1" -F "photo=@avatar.jpg"

# 3b. Change the avatar later (POST + _method=PATCH — see §5).
curl -X POST http://localhost:8000/api/v1/client/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -F "_method=PATCH" -F "photo=@new-avatar.jpg"

# 4. Browse the catalog (no token needed).
curl http://localhost:8000/api/v1/client/categories

# 5. Create an order (multipart — omit -F photos[] entries if testing without images).
curl -X POST http://localhost:8000/api/v1/client/orders \
  -H "Authorization: Bearer 1|abc..." \
  -F "city_id=1" -F "category_id=4" \
  -F "description=Протекает кран на кухне" \
  -F "client_phone=+99362111222" \
  -F "client_lat=37.95" -F "client_lng=58.38"
```

---

## 11. Stable contracts

- The OTP → Sanctum token auth flow (§3) — identical to the master doc's §2, one login for
  the whole app.
- `ClientOrder` response shape (§6), including the `master.location` gating rule.
- `master.assigned` / `order.status.changed` payloads on `private-client.{clientId}` (§7).
- Order status enum values: `pending`, `assigned`, `in_progress`, `completed`, `cancelled`.
- Master application status enum values: `pending`, `approved`, `rejected`.

The generated API reference at `/docs` (Scribe) is authoritative if it ever disagrees with
this file.
