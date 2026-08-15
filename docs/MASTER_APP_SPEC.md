# Master Mobile App — Technical Specification (Flutter)

> Audience: Flutter developer building the master (handyman) side of the mobile application.
> Backend: Laravel 11 + Sanctum + Reverb (WebSockets).
> All endpoints are versioned under `/api/v1/`.

> **One app, one account.** There is no separate master application and no
> separate master login. The user signs in as a **client** (see the client auth
> flow), and the master side unlocks on that same token once the account carries
> an approved master profile. Section 2 covers how that works.

---

## 1. Overview

The master side lets a handyman:

1. Apply for the master role from inside the client app, and follow the review.
2. Receive assigned orders in real-time.
3. Send live GPS location to the backend so the admin can track the master on the map.
4. View order details (client name, phone, location, problem photos, description).
5. Mark order status: arrived, in progress, completed.
6. Upload Before/After photos for each individual task performed.
7. View own access subscription (plan, expiry, history) and the subscription price list.

UI reference: **JustLife app** — clean, minimalist, modern.
Languages: **Turkmen (primary)**, **Russian (secondary)**.

---

## 2. Auth & the "become a master" flow (implemented)

### 2.1 There is one token

Sign-in is the **client** OTP flow (`POST /api/v1/client/auth/request-otp` →
`verify-otp`), which returns a Sanctum token named `mobile-client`. Store it in
flutter_secure_storage and send it as `Authorization: Bearer <token>` on both the
client and the master endpoints. Logout is `POST /api/v1/client/auth/logout`.

**Handle `delivery: "manual"`** — it means the SMS gateway was unreachable. The code is still valid, but it was parked for an operator to dictate by phone. Show `delivery_message` to the user instead of a generic "SMS sent" screen; the OTP input flow stays exactly the same.

### 2.2 Which side of the app to show

`GET /api/v1/client/me` (and the `client` object returned by `verify-otp`) carries:

```json
{ "master_status": null, "master_id": null, "has_master_access": false }
```

- `master_status`: `null` (never applied) \| `pending` \| `approved` \| `rejected`
- `has_master_access`: `true` only when approved, active **and** subscribed — the
  one flag that says the master endpoints will answer

### 2.3 Applying

| Step | Endpoint | Body |
|------|----------|------|
| Read own application | `GET /api/v1/client/master-application` | — (`data: null` if never applied) |
| Apply / re-apply | `POST /api/v1/client/master-application` | `{ "city_id": 1, "category_ids": [4,7], "experience_years": 5, "about": "…" }` |

`category_ids` must be **leaf** categories. The client's profile name is required
first — apply after `complete-registration`.

Applying returns `201` with `status: "pending"` and grants nothing. An
administrator approves it and issues the subscription (paid in person). A
rejected applicant gets `rejection_reason` and may submit the form again; an
approved one gets `422` if they try.

### 2.4 Error contract on master endpoints (`ensure.master`)

| Code | `reason` | Meaning |
|------|----------|---------|
| `401` | — | Missing, malformed, or revoked token |
| `403` | `token_required` | Not a client token |
| `403` | `not_a_master` | Account has never applied — show "become a master" |
| `403` | `application_pending` | Still under review — show the waiting screen |
| `403` | `application_rejected` | Turned down — show `rejection_reason`, offer to re-apply |
| `403` | `disabled` | Profile deactivated by an administrator |
| `403` | `access_expired` | Subscription lapsed — show renewal, `GET /api/v1/master/subscription` still answers |

Every `403` carries a machine-readable `reason` alongside the localized
`message`; branch on `reason`, never on the text. None of them should sign the
user out — the client half of the app keeps working on the same token.

---

## 3. Currently Available Endpoints

### 3.1 Send live location

`POST /api/v1/master/{masterId}/location` — **Bearer token required**.

The mobile app should call this every **10–15 seconds** while the master is online (and has at least one active assignment). `{masterId}` must be the id of the master the token belongs to; posting for anyone else returns `403`.

**Request body**:
```json
{
  "latitude": 37.952321,
  "longitude": 58.382345,
  "order_id": 42,
  "recorded_at": "2026-05-08T13:45:00+05:00"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `latitude` | float | yes | between -90 and 90 |
| `longitude` | float | yes | between -180 and 180 |
| `order_id` | int | no | Set when location is part of a specific order trip; null otherwise |
| `recorded_at` | ISO8601 | no | Defaults to server now() |

**Response 201**:
```json
{
  "data": {
    "id": 1234,
    "master_id": 1,
    "order_id": 42,
    "latitude": 37.952321,
    "longitude": 58.382345,
    "recorded_at": "2026-05-08T13:45:00+05:00"
  }
}
```

**Errors**:
- `401` — missing or invalid token
- `403` — master is inactive, access expired, or `{masterId}` is not the token owner
- `422` — validation error

### 3.2 The rest of the master endpoints

All of these are implemented and require the Bearer token.

| Endpoint | Purpose |
|----------|---------|
| `GET /api/v1/master/settings` | App rules/terms — **public**, shown before registration |
| `GET /api/v1/master/me` | Current master profile + access expiry + categories |
| `GET /api/v1/master/subscription-plans` | Subscription price list — **public**, readable without a token |
| `GET /api/v1/master/subscription` | Own subscription: current, access deadline, history — works with a lapsed subscription |
| `PATCH /api/v1/master/availability` | Toggle the "ready for work" flag |
| `GET /api/v1/master/orders` | Assigned orders — `?filter=active` or `?filter=history`, paginated 15/page |
| `GET /api/v1/master/orders/available` | Auto-search feed — unclaimed orders in range (Section 3.3) |
| `POST /api/v1/master/orders/{id}/respond` | Claim an offered order (Section 3.3) |
| `POST /api/v1/master/orders/{id}/decline` | Hide an offer from this master's feed (Section 3.3) |
| `GET /api/v1/master/orders/{id}` | Single order details (client info, photos, tasks) |
| `POST /api/v1/master/orders/{id}/start` | Mark order as `in_progress` when arriving |
| `POST /api/v1/master/orders/{id}/complete` | Mark order completed |
| `POST /api/v1/master/orders/{id}/tasks` | Create a task (e.g. "Replaced hose") |
| `POST /api/v1/master/orders/{id}/tasks/{taskId}/photo` | Upload before/after photo |
| `DELETE /api/v1/master/orders/{id}/tasks/{taskId}` | Remove a task |

Ready-to-run request examples for every one of these live in the `bruno/` collection at the repo root ([Bruno](https://www.usebruno.com/) — open the folder, pick the `local` environment). It is a single collection for the whole app: the master endpoints sit in `06_master_profile`, `07_master_orders` and `08_master_subscription`, and they run on the very same `{{token}}` that **Verify OTP** saved in `01_auth`.

### 3.3 Auto-search: available orders, respond, decline

New client orders are **not** routed by city. Each order starts a search at a radius that widens by one step every minute (20 → 40 → 60 → 80 km by default) until a master claims it or the maximum is passed.

**The master app must be sending location pings** (Section 3.1). A master with no recorded position gets an empty feed and cannot respond.

#### `GET /api/v1/master/orders/available`

Unclaimed orders that match the master's categories and currently sit inside their own search radius, **nearest first**. No parameters — the server uses the master's last GPS ping.

```json
{
  "data": [
    {
      "id": 42,
      "category": "Сантехника",
      "city": "Ашхабад",
      "description": "Протекает кран на кухне",
      "address": "ул. Огузхана 12",
      "latitude": 37.9601,
      "longitude": 58.3261,
      "distance_km": 4.7,
      "search_radius_km": 20,
      "search_started_at": "2026-08-14T10:15:00+00:00",
      "photos": [{ "id": 7, "url": "https://…/storage/…webp", "status": "ready" }],
      "created_at": "2026-08-14T10:15:00+00:00"
    }
  ]
}
```

> `client_name` and `client_phone` are **absent by design** — the order is not yours yet. They appear in the response of `respond` and in `GET /orders/{id}` afterwards.

An empty `data` array is normal: no orders in range, or no GPS ping yet. Do not treat it as an error.

#### `POST /api/v1/master/orders/{id}/respond`

Claims the order. **First responder wins** — expect to lose this race regularly and handle it gracefully in the UI.

- `200 OK` → the full `MasterOrderResource` (same shape as `GET /orders/{id}`); the order is now `assigned` to you.
- `422` → `{ "message": "…" }`, localized per the `X-Locale` header. Show the message and refresh the feed.

| Situation | `message` (ru) |
|---|---|
| Another master got there first | «Заявку уже разобрал другой мастер» |
| You moved out of the order's radius | «Заявка находится вне вашего радиуса поиска» |
| The search closed, an admin will assign it | «Авто-поиск по этой заявке завершён — её назначит администратор» |
| No GPS position on record | «Не удалось определить ваше местоположение — включите геолокацию» |
| Order category is not yours | «У мастера нет этой категории» |
| Availability toggle is off | «Мастер сейчас недоступен для принятия заявок» |

#### `POST /api/v1/master/orders/{id}/decline`

Hides the order from **your** feed permanently. Other masters keep seeing it and the search keeps running. Idempotent — repeat calls also return `204 No Content`. You can still claim a declined order later by calling `respond` with its id.

- `204 No Content` → dismissed.
- `422` «Заявку уже разобрал другой мастер» → someone claimed it while you were deciding.

---

## 4. Realtime — WebSockets (Reverb)

The backend broadcasts events via **Laravel Reverb** (a Pusher-compatible WebSocket server).

### Connection details

| Variable | Value (dev) |
|----------|-------------|
| `host` | `<your-machine-ip>` |
| `port` | `8080` |
| `key` | `handymanreverbappkey` |
| `forceTLS` | `false` (dev), `true` (prod) |
| `enabledTransports` | `["ws", "wss"]` |

Use the **`pusher_channels_flutter`** package (Pusher SDK is fully compatible with Reverb).

### Channels the master app subscribes to

| Channel | When | Event | Payload |
|---------|------|-------|---------|
| `available-orders` | While online and available | `.order.search.started` | `{ order_id, radius_km }` |
| `available-orders` | While online and available | `.order.search.radius.expanded` | `{ order_id, radius_km }` |
| `private-master.{masterId}` | After login | `.order.assigned` | `{ order_id, client_name, address, lat, lng }` *(planned)* |
| `private-order.{orderId}` | When viewing an active order | `.order.status.changed` | `{ status, by }` *(planned)* |

> **`available-orders` is a public channel and carries no usable order data — treat both events purely as a "your feed may have changed" signal and re-fetch `GET /api/v1/master/orders/available`.** The payload is not filtered for you: an event fires for every order in the system, including ones outside your radius or categories. Only the endpoint applies the matching rules. Debounce the refetch (≈1 s) so a burst of scheduler ticks does not turn into a burst of requests.

> The masters-map channel `masters-map.{cityId}` is for the **admin panel only**; the master app should NOT subscribe to it.

### Authorization for private channels

Reverb private channels require auth. The mobile app must point the auth endpoint to:
```
POST /api/v1/broadcasting/auth
Header: Authorization: Bearer <sanctum_token>
```

Because both roles live on one token, a single connection can subscribe to
`master.{masterId}` **and** `client.{clientId}` at the same time — use
`master_id` / `id` from `GET /api/v1/client/me`.

---

## 5. Photo Upload (Before / After)

**Important**: per spec, photos are uploaded asynchronously via backend Queue + Job. The mobile app simply POSTs the image — the conversion to WebP happens in the background.

The endpoint will accept `multipart/form-data` with field `photo` (max 8 MB, image/* mime). Response includes a status `pending` initially; the client app should not block UI on conversion.

```
POST /api/v1/master/orders/{orderId}/tasks/{taskId}/photo
Content-Type: multipart/form-data

photo: <file>
type: "before" | "after"
```

Response `202 Accepted`:
```json
{ "id": 7, "status": "pending", "type": "before" }
```

---

## 6. UI / UX Screens

Build these screens in this order. Match JustLife visual style.

1. **Splash** — auto-redirect on token presence, then branch on `has_master_access` / `master_status` from `GET /api/v1/client/me`.
2. **Login** — phone input → OTP screen → home (shared with the client side).
3. **Become a master** — the application form (city, categories, years of experience, bio), plus the status screens for `pending` (waiting for review) and `rejected` (shows `rejection_reason`, lets them re-apply).
4. **Home** — list of active orders + tab "History".
5. **Order Details** — client info (call button), problem description, photos (gallery view), map (client location), action buttons.
6. **Order in Progress** — when started, sticky banner "Trip active" with elapsed time, big "Complete" button.
7. **Task Photo Capture** — camera flow, before → after pairs.
8. **Profile** — name, phone, subscription (plan, days left, history), access expiry, logout.
9. **Settings** — language switcher (tk/ru), notification toggles, theme.

---

## 7. Background Behavior

- **Location pings**: continue every 10–15s while the app has an active assignment, even backgrounded. Use platform-native location services with appropriate permissions.
- **Push notifications**: when offline, new order assignments must trigger an FCM push (FCM token registration endpoint planned).
- **Reconnection**: if WebSocket drops, retry with exponential backoff (1s, 2s, 4s, max 30s).

---

## 8. Local Testing

Sign in as a client, apply, then approve the application in the admin panel —
there are no seeded masters, and the approval is what opens the master endpoints.

```bash
# 1. Request the code for any phone number. A client account is created on the
#    fly. If the SMS gateway isn't running you'll get delivery: "manual" — read
#    the code from the admin panel's "OTP-коды" section.
curl -X POST http://localhost:8000/api/v1/client/auth/request-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222"}'

# 2. Exchange the code for a token.
curl -X POST http://localhost:8000/api/v1/client/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222", "code": "1234"}'

# 3. Fill in the profile (name is required before applying).
curl -X POST http://localhost:8000/api/v1/client/auth/complete-registration \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -d '{"name": "Мерген", "city_id": 1}'

# 4. Apply for the master role.
curl -X POST http://localhost:8000/api/v1/client/master-application \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -d '{"city_id": 1, "category_ids": [4], "experience_years": 5, "about": "…"}'

# 5. Approve it at http://localhost:8000/master-applications (pick a plan —
#    without one the master stays approved but without access).

# 6. Same token now reaches the master endpoints. The id in the path must be
#    the master profile's (read it from GET /api/v1/client/me → master_id).
curl -X POST http://localhost:8000/api/v1/master/1/location \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abc..." \
  -d '{"latitude": 37.95, "longitude": 58.38}'
```

For realtime testing on the admin map, the backend includes:

```bash
# Simulate one master moving for 2 minutes
php artisan master:simulate-movement 1 --interval=3 --steps=40
```

Watch the admin map (`/masters/map`) — the marker animates smoothly to each new position.

---

## 9. Open Questions for Flutter Developer

1. **Map provider on mobile** — resolved: use the backend's own vector tiles, see [`MASTER_APP_MAP_INTEGRATION.md`](./MASTER_APP_MAP_INTEGRATION.md).
2. **Push provider** — FCM (Android), APNs (iOS), or Huawei HMS? No FCM token registration endpoint exists yet on the backend.
3. **Min platform versions** — Android API and iOS minimum?
4. **Build profiles** — separate dev / staging / prod with different `.env`?

> **SMS provider** is resolved: OTP codes go to a Flutter phone acting as an SMS gateway, over a Socket.IO bridge (`socket-server/` in this repo). When that phone is offline the backend falls back to manual delivery — see §2.

Resolve the rest with the team before starting push integration.

---

## 10. Stable Contracts

The following contracts are **stable** as of this document; the Flutter app can rely on them:

- The OTP → Sanctum token auth flow (Section 2)
- `POST /api/v1/master/{masterId}/location` request and response shape (Section 3.1)
- The auto-search trio — `GET /orders/available`, `POST /orders/{id}/respond`, `POST /orders/{id}/decline` — and their response shapes (Section 3.3)
- `master.location.updated` event payload shape (used by admin only, but the Flutter side won't break it)
- Order status enum values: `pending`, `assigned`, `in_progress`, `completed`, `cancelled` (`App\Enums\OrderStatus`)
- Payment model values: `percentage`, `fixed_per_job`, `salary`, `salary_percentage` (`App\Enums\PaymentModel`)

Anything marked **planned** in this doc may change before implementation. The generated API reference at `/docs` is authoritative when it disagrees with this file.
