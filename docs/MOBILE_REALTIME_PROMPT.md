# Mobile Realtime & Live Tracking — task prompt for Cursor

> Audience: the Flutter developer. Paste everything below the horizontal rule into
> Cursor as a task; it is self-contained and needs no other file open.
>
> Companion specs in this repo: [`CLIENT_APP_SPEC.md`](./CLIENT_APP_SPEC.md) §7,
> [`MASTER_APP_SPEC.md`](./MASTER_APP_SPEC.md),
> [`MASTER_APP_MAP_INTEGRATION.md`](./MASTER_APP_MAP_INTEGRATION.md).
> Backend verified against the codebase on 2026-08-20.

---

## Task

Build the realtime layer of this Flutter app on top of **Laravel Reverb** (Pusher
protocol), and the live "master is on his way" map that rides on it. The backend
is finished: channels, events and endpoints exist and will not change to
accommodate the client. This is client-side work only.

One app serves two roles on one account: **client** (orders a job) and **master**
(does the job). A master profile is an add-on to a client account and shares the
same Sanctum token, so a single WebSocket connection can hold both private
channels at once.

## 0. Ask the backend team for these before writing code

Correct code against a half-running environment looks exactly like broken code.
Settle these first:

1. **The queue worker is running** (`php artisan queue:work`). `order.search.started`,
   `order.search.radius.expanded` and `order.response.created` are dispatched through
   the queue; with no worker they never arrive, while the other events keep working
   normally. That split is the symptom — if some events land and those three never
   do, the worker is down, not your code.
2. **Reverb is running** (`php artisan reverb:start`) and nginx proxies `/app` to it.
3. **A way to receive the OTP at login.** The code is sent by an SMS gateway to a real
   phone; when the gateway is down it is parked in the admin panel instead. There is
   no fixed test code in the project. Agree up front: admin panel access, a test
   number, or a dev bypass.
4. **`API_BASE_URL` and `REVERB_APP_KEY` for the environment you target.** A dev key
   against production fails silently — the socket just never connects.
5. **A test account with an approved master profile** (`has_master_access: true`) and a
   live subscription. Without approval the `master.*` channel returns 403 and every
   `/master/*` endpoint returns 403.

## 1. Stack

Use [`pusher_channels_flutter`](https://pub.dev/packages/pusher_channels_flutter) — the
official Pusher client, which supports a custom host and an `onAuthorizer` callback.

Do **not** use `laravel_echo` + `pusher_client`: both are unmaintained and break on
current Android/iOS toolchains.

## 2. Connection

Put these in `--dart-define`, never in source:

| Setting | Dev | Production |
|---|---|---|
| `API_BASE_URL` | `http://localhost/api/v1` | `https://Handyman.com.tm/api/v1` (confirm) |
| `REVERB_APP_KEY` | `4liesygc2gk2bijxales` | ask backend |
| `REVERB_HOST` | same host as the API | same host as the API |
| `REVERB_PORT` | `80` | `443` |
| `REVERB_USE_TLS` | `false` | `true` |

The WebSocket is **not** on a separate port. nginx proxies the `/app` path to Reverb
on the same host and port that serves the REST API, and the Pusher client appends
`/app/{key}` itself. Point it at the API host and nothing else.

`cluster` is meaningless here — Reverb is self-hosted, not Pusher's cloud. If the
package demands a non-empty value, pass `'mt1'` as a placeholder together with an
explicit `host`, which overrides it.

```dart
await pusher.init(
  apiKey: reverbAppKey,
  cluster: 'mt1',            // placeholder; the real endpoint is `host` below
  useTLS: reverbUseTls,
  host: reverbHost,          // API host only — no scheme, no path
  wsPort: 80,
  wssPort: 443,
  onAuthorizer: _authorizePrivateChannel,
  onEvent: _handleEvent,
  onConnectionStateChange: _handleConnectionState,
  onError: _handleError,
);
await pusher.connect();
```

## 3. Authorizing private channels

`available-orders` is public and needs no authorization. `client.{id}` and
`master.{id}` are private.

Use `onAuthorizer`, not `authEndpoint` — the request needs an `Authorization` header,
which `authEndpoint` cannot set on every platform.

```
POST {API_BASE_URL}/broadcasting/auth
Headers:
  Authorization: Bearer <sanctum_token>
  Accept: application/json
Body (form-urlencoded):
  socket_id=<socketId>
  channel_name=<channelName>     // arrives already prefixed: private-client.42
200:
  { "auth": "<app_key>:<hmac_signature>" }
```

Return the decoded JSON as `Map<String, dynamic>` — `{'auth': '...'}`, unchanged and
unrenamed.

`403` means the token is fine but this user has no right to that channel (a foreign
`client.{id}`, or `master.{id}` without an approved master profile). Log it and stop;
do not retry. `401` means the token expired — run the normal logout flow.

## 4. Which id goes in which channel name

From `POST /client/auth/verify-otp` or `GET /client/me`:

```json
{
  "token": "3|xxxxx",
  "client": {
    "id": 42,
    "master_id": 7,
    "master_status": "approved",
    "has_master_access": true
  }
}
```

**`master_id` is not `client.id`.** The master channel is built from `master_id`, the
client channel from `id`. Swapping them yields a 403 and a long debugging session.
The same split runs through the whole API: `GET /master/me` and `GET /client/me`
describe different records with different ids, because the backend swaps the
authenticated user for the Master model on `/master/*` routes.

Subscription rules:

- `client.{client.id}` — always, right after login.
- `master.{client.master_id}` — only when `has_master_access == true`.
- `available-orders` — only when `has_master_access == true` and the master is on duty
  (`PATCH /master/availability`).

## 5. Channels and events

**Event names carry no leading dot.** `order.status.changed`, not
`.order.status.changed`. The dot is Laravel Echo's JavaScript convention for
cancelling the `App\Events\` namespace and means nothing to a raw Pusher client —
ignore every internet example that shows one.

### 5.1 `available-orders` — public, master side

A signal that the pool of claimable orders changed. The payload is deliberately
contentless: the channel is public, so everything identifying a client stays behind
the authenticated endpoint.

| Event | Payload |
|---|---|
| `order.search.started` | `{ order_id, radius_km }` |
| `order.search.radius.expanded` | `{ order_id, radius_km }` |

React identically to both: re-fetch `GET /master/orders/available`, which filters by
this master's position, categories and the order's current search radius. **Debounce
1–2 seconds** — these arrive in bursts and must collapse into one request.

### 5.2 `client.{clientId}` — private, client side

| Event | Payload | Meaning |
|---|---|---|
| `order.response.created` | `{ order_id, response_id, master_id, master_name, master_phone }` | A master offered to take the job — refresh the responses list |
| `master.assigned` | `{ order_id, client_name, master_id, master_name, master_phone }` | A master is assigned — open the tracking screen (§6) |
| `order.status.changed` | `{ order_id, client_name, from, to, to_label }` | Patch the order status locally |
| `master.location.updated` | `{ master_id, order_id, latitude, longitude, distance_km, recorded_at }` | The assigned master moved (§6) |

### 5.3 `master.{masterId}` — private, master side

| Event | Payload | Meaning |
|---|---|---|
| `master.assigned` | `{ order_id, client_name, master_id, master_name, master_phone }` | The offer was approved — the job is yours |
| `order.status.changed` | `{ order_id, client_name, from, to, to_label }` | Status of the current job changed |
| `order.response.rejected` | `{ order_id, reason }` | The client turned the offer down; `reason` may be `null` |
| `order.response.superseded` | `{ order_id }` | The client picked another master |
| `order.response.withdrawn` | `{ order_id }` | The order was cancelled while the offer was pending |

`from` / `to` values: `pending`, `assigned`, `in_progress`, `completed`, `cancelled`.
`to_label` is already translated server-side per `X-Locale` — display it as is.

## 6. Live tracking on the map

Runs from the moment the client approves an offer until the job closes.

### Master side — sending position

```
POST {API_BASE_URL}/master/{masterId}/location
{
  "latitude": 37.9521,
  "longitude": 58.3812,
  "order_id": 42,
  "recorded_at": "2026-08-20T14:03:11+05:00"   // optional
}
```

- Send only while a job is active: from `master.assigned` until `order.status.changed`
  reports `completed`/`cancelled`. Outside that window, shut the background location
  stream down — nothing consumes idle pings and they cost battery.
- **`order_id` decides who sees the ping.** With it, the position also reaches that
  order's client. Without it, only the staff map gets it and the client sees nothing.
- The tag is verified, not trusted: someone else's order → `404`, a finished order →
  `422`. Both mean *stop tagging pings with this id* — they are permanent, not transient.
- Interval: 10–15 seconds while moving. Every ping is a stored row.

### Client side — drawing the trail

Backfill, and recovery after a dropped connection:

```
GET {API_BASE_URL}/client/orders/{orderId}/track
GET {API_BASE_URL}/client/orders/{orderId}/track?since=2026-08-20T14:03:11+05:00

{
  "data": {
    "order_id": 42,
    "status": "assigned",
    "is_active": true,
    "destination": { "latitude": 38.0, "longitude": 58.3, "address": "..." },
    "master": { "id": 7, "name": "...", "phone": "..." },
    "last_location": { "latitude": 37.93, "longitude": 58.3, "recorded_at": "...", "distance_km": 7.8 },
    "points": [ { "latitude": 37.91, "longitude": 58.3, "recorded_at": "..." } ]
  }
}
```

`points` is chronological, capped at the newest 500 pings. `since` returns only what
came after that moment.

Assembly:

1. `master.assigned` arrives → open the map screen.
2. Call `/track` once → draw the polyline from `points`, the master marker from
   `last_location`, the destination marker from `destination`, and a "~`distance_km` km"
   label.
3. On every `master.location.updated` with the matching `order_id`: **append the point
   to the polyline** and move the marker. Never re-call `/track` per ping.
4. **Filter on `order_id`.** The channel carries pings for all of this client's active
   orders; one screen shows one order.
5. `distance_km` is straight-line distance, not road distance, and the backend
   computes no ETA. Present it as an approximation and do not invent an arrival time.

Stopping — three equivalent signals, handle whichever arrives:

- `order.status.changed` with `to: completed` or `cancelled`;
- `/track` answering `is_active: false` (its `points` are empty);
- the pings simply stop — the backend drops the client channel from the broadcast the
  moment the order stops being trackable.

There is no unsubscribe call. `client.{id}` stays subscribed for the session and the
stream for a closed order dries up by itself.

## 7. API contract that applies to every request

- **`X-Locale: tk|ru` on every call.** Without it the backend answers in Russian.
  Error messages and `to_label` come back already translated — do not localize them
  on the client.
- **Errors are uniform.** Always `{"message": "..."}`; validation failures add
  `{"errors": {"field": ["..."]}}`. One parser covers the whole API.
- **403 from the master guard carries a machine-readable `reason`:** `token_required`,
  `not_a_master`, `application_pending`, `application_rejected`, `disabled`,
  `access_expired`. Route screens off that code — never off the translated `message`,
  which changes with the language.
- **State changes without the user doing anything.** Scheduled jobs widen the search
  radius every minute, cancel stale orders hourly, and expire subscriptions hourly. A
  master can hit `403 access_expired` mid-session and an open order can cancel itself
  while the screen is up. Handle 403-with-`reason` in one global interceptor rather
  than per call site.

## 8. Implementation requirements

1. **One `RealtimeService` singleton.** No connections from widgets.
2. Expose **typed Dart streams**, not raw maps: `Stream<OrderStatusChanged>`,
   `Stream<MasterLocationUpdated>` and so on, with `fromJson` models built from the
   payloads above.
3. **Lifecycle:** connect after a successful login; unsubscribe and `disconnect()` on
   logout *before* clearing the token; on `resume` check the connection state rather
   than tearing it down on every background transition.
4. **Reconnect implies re-fetch.** Events sent while the app was offline are gone —
   the backend keeps no history. On reconnect, re-fetch whatever the current screen
   shows.
5. **The socket is a signal, not the source of truth.** Use payloads for instant UI
   response, but keep screen state synchronized over REST. Never assemble a whole
   order out of event fields.
6. **Log** connection state changes and subscription errors in dev builds. Debugging
   private-channel auth without logs is guesswork.
7. **Roles can appear at runtime:** a client applies to become a master and an admin
   approves. When `has_master_access` flips to `true`, subscribe to `master.{id}` and
   `available-orders` on the **existing** socket, without reconnecting.

## 9. Acceptance checklist

- [ ] Client creates an order → a master in range gets `order.search.started` and the
      available list refreshes with no pull-to-refresh.
- [ ] Master offers → the client sees the offer appear instantly.
- [ ] Client approves → the winning master gets `master.assigned`, the others get
      `order.response.superseded`.
- [ ] The client's map draws the trail, the marker moves on each ping, and the distance
      label counts down.
- [ ] Master completes the job → the client's map stops and closes; the master stops
      sending pings.
- [ ] Client cancels → a master with a pending offer gets `order.response.withdrawn`.
- [ ] Airplane mode for 30 seconds, then off → the connection recovers and the screen
      re-fetches.
- [ ] Logout → socket closed, no further events; login as another account → channels
      bound to the new ids.
- [ ] An account with no master role subscribes to neither `master.*` nor
      `available-orders`.

## 10. Do not

- Do not subscribe to staff channels — `orders`, `clients`, `masters-map.*`,
  `admin.pending-otps`. They belong to the web panel, they are private, and a mobile
  token is rejected there.
- Do not hand-prefix `private-` when a high-level helper already does it; check your
  package version and avoid double-prefixing.
- Do not poll. Reaching for `Timer.periodic` on an order list means the realtime layer
  is wired wrong.
