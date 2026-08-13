# Master Mobile App — Technical Specification (Flutter)

> Audience: Flutter developer building the **Master** (handyman) mobile application.
> Backend: Laravel 11 + Sanctum + Reverb (WebSockets).
> All endpoints are versioned under `/api/v1/`.

---

## 1. Overview

The Master app lets a handyman:

1. Log in by phone (OTP-confirmed).
2. Receive assigned orders in real-time.
3. Send live GPS location to the backend so the admin can track the master on the map.
4. View order details (client name, phone, location, problem photos, description).
5. Mark order status: arrived, in progress, completed.
6. Upload Before/After photos for each individual task performed.
7. View personal balance, payment model, work history.

UI reference: **JustLife app** — clean, minimalist, modern.
Languages: **Turkmen (primary)**, **Russian (secondary)**.

---

## 2. Auth Flow (implemented)

Every master endpoint except `GET /api/v1/master/settings` and the two OTP endpoints requires a Bearer token.

| Step | Endpoint | Body | Response |
|------|----------|------|----------|
| 1. Request OTP | `POST /api/v1/master/auth/request-otp` | `{ "phone": "+99362111222" }` | `{ "message": "OTP sent.", "delivery": "sms\|manual", "delivery_message": null }` |
| 2. Verify OTP | `POST /api/v1/master/auth/verify-otp` | `{ "phone": "+99362111222", "code": "1234" }` | `{ "token": "1\|abc...", "master": { ... } }` |
| 3. Authenticated requests | any | header: `Authorization: Bearer <token>` | — |
| 4. Logout | `POST /api/v1/master/auth/logout` | — | `204 No Content` |

Token is a Laravel Sanctum personal access token, issued with the name `mobile`. Store it in flutter_secure_storage.

**Handle `delivery: "manual"`** — it means the SMS gateway was unreachable. The code is still valid, but it was parked for an operator to dictate by phone. Show `delivery_message` to the user instead of a generic "SMS sent" screen; the OTP input flow stays exactly the same.

**Error contract on protected endpoints** (`ensure.master`):

| Code | Meaning |
|------|---------|
| `401` | Missing, malformed, or revoked token |
| `403` | Token belongs to a non-master, the master is deactivated, or their access period expired |

A `403` on access expiry is terminal for the session — send the master back to the login screen with an explanatory message rather than retrying.

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
| `GET /api/v1/master/me` | Current master profile + balance + payment model + categories |
| `PATCH /api/v1/master/availability` | Toggle the "ready for work" flag |
| `GET /api/v1/master/orders` | Assigned orders — `?filter=active` or `?filter=history`, paginated 15/page |
| `GET /api/v1/master/orders/{id}` | Single order details (client info, photos, tasks) |
| `POST /api/v1/master/orders/{id}/start` | Mark order as `in_progress` when arriving |
| `POST /api/v1/master/orders/{id}/complete` | Mark order completed; credits the master's balance |
| `POST /api/v1/master/orders/{id}/tasks` | Create a task (e.g. "Replaced hose") |
| `POST /api/v1/master/orders/{id}/tasks/{taskId}/photo` | Upload before/after photo |
| `DELETE /api/v1/master/orders/{id}/tasks/{taskId}` | Remove a task |

Ready-to-run request examples for every one of these live in the `bruno-master/` collection at the repo root ([Bruno](https://www.usebruno.com/) — open the folder, pick the `local` environment).

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
| `private-master.{masterId}` | After login | `.order.assigned` | `{ order_id, client_name, address, lat, lng }` *(planned)* |
| `private-order.{orderId}` | When viewing an active order | `.order.status.changed` | `{ status, by }` *(planned)* |

> The masters-map channel `masters-map.{cityId}` is for the **admin panel only**; the master app should NOT subscribe to it.

### Authorization for private channels

Reverb private channels require auth. The mobile app must point the auth endpoint to:
```
POST /broadcasting/auth
Header: Authorization: Bearer <sanctum_token>
```

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

1. **Splash** — auto-redirect to login or home based on token presence.
2. **Login** — phone input → OTP screen → home.
3. **Home** — list of active orders + tab "History".
4. **Order Details** — client info (call button), problem description, photos (gallery view), map (client location), action buttons.
5. **Order in Progress** — when started, sticky banner "Trip active" with elapsed time, big "Complete" button.
6. **Task Photo Capture** — camera flow, before → after pairs.
7. **Profile** — name, phone, balance, payment model, access expiry, logout.
8. **Settings** — language switcher (tk/ru), notification toggles, theme.

---

## 7. Background Behavior

- **Location pings**: continue every 10–15s while the app has an active assignment, even backgrounded. Use platform-native location services with appropriate permissions.
- **Push notifications**: when offline, new order assignments must trigger an FCM push (FCM token registration endpoint planned).
- **Reconnection**: if WebSocket drops, retry with exponential backoff (1s, 2s, 4s, max 30s).

---

## 8. Local Testing

Log in first, then use the token for everything else. Seeded masters (`php artisan db:seed`) have real phone numbers — pick one from the admin panel.

```bash
# 1. Request the code. If the SMS gateway isn't running you'll get
#    delivery: "manual" — read the code from the admin panel's "OTP-коды" section.
curl -X POST http://localhost:8000/api/v1/master/auth/request-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222"}'

# 2. Exchange the code for a token.
curl -X POST http://localhost:8000/api/v1/master/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "+99362111222", "code": "1234"}'

# 3. Use it. The id in the path must be the token owner's.
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
- `master.location.updated` event payload shape (used by admin only, but the Flutter side won't break it)
- Order status enum values: `pending`, `assigned`, `in_progress`, `completed`, `cancelled` (`App\Enums\OrderStatus`)
- Payment model values: `percentage`, `fixed_per_job`, `salary`, `salary_percentage` (`App\Enums\PaymentModel`)

Anything marked **planned** in this doc may change before implementation. The generated API reference at `/docs` is authoritative when it disagrees with this file.
