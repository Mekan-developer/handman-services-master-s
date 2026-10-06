# Mobile Map — task prompt for Cursor

> Audience: the Flutter developer. Paste everything below the horizontal rule into
> Cursor as a task; it is self-contained and needs no other file open.
>
> Companion prompt: [`MOBILE_REALTIME_PROMPT.md`](./MOBILE_REALTIME_PROMPT.md) — WebSocket
> layer and live tracking events. This prompt covers the map itself and the map-related
> REST endpoints.
> Backend verified against the codebase on 2026-10-01.

---

## Task

Add the map to this Flutter app: the base map widget, picking the order address on the
map, order pins for the master, and the "master is on his way" tracking screen for the
client. The backend is finished; this is client-side work only. Do not invent endpoints
or fields that are not listed here.

One app serves two roles on one account: **client** (orders a job) and **master** (does
the job). Both share the same Sanctum token.

## 0. Ask the backend team before writing code

1. **`API_BASE_URL`** for the target environment (production domain). Every URL below is
   relative to it. Keep it in one constant — never hardcode the domain twice.
2. **Reverb host / port / key** — only needed for the live tracking part (see the
   companion realtime prompt).

## 1. How the map works on the backend

The backend hosts its **own map** — no Google Maps, no Yandex, no Mapbox, no API key.

- It serves **vector tiles** (`.pbf`, OpenMapTiles schema) for Turkmenistan only, plus a
  **MapLibre style** (`style.json`), fonts (glyphs) and icons (sprite).
- There are **no raster PNG tiles**.

Therefore:

- Use the **`maplibre_gl`** package (latest version from pub.dev).
- **Do NOT use `flutter_map` with `TileLayer`** — it renders raster images only and
  will show a blank map with these `.pbf` tiles.
- Do not add `google_maps_flutter` or any map API key.

## 2. Map endpoints (public, no auth)

| Resource | URL |
|---|---|
| Style | `{API_BASE_URL}/maps/style.json` |
| Vector tiles | `{API_BASE_URL}/tiles/{z}/{x}/{y}.pbf` |
| Fonts | `{API_BASE_URL}/maps/fonts/{fontstack}/{range}.pbf` |
| Sprite | `{API_BASE_URL}/maps/sprite` (`sprite.json` / `sprite.png` / `@2x`) |

Facts:

- Tile data exists for zoom **0–14**. Above 14 MapLibre overzooms automatically; allow
  the camera up to ~18.
- A tile response of **`204 No Content` is normal** (sea / empty area), not an error.
- Data bounds: lon 51.83–66.72, lat 35.12–42.81. Restrict the camera to these bounds.
- **Default camera:** Ashgabat, lat `37.9415`, lng `58.3794`, zoom `11`. The style's own
  center is `(0,0)` — always set the initial camera yourself.
- **Attribution is required by the license**: keep
  `© OpenMapTiles © OpenStreetMap contributors` visible on every map.

## 3. Loading the style — mandatory step

`style.json` on the server contains **relative** paths (`/tiles/...`, `/maps/fonts/...`,
`/maps/sprite`). Native MapLibre requires **absolute** URLs, so passing the style URL
directly will not work. Download the style, rewrite the paths, pass the result as an
inline JSON string.

```dart
Future<String> loadMapStyle(String baseUrl) async {
  final res = await http.get(Uri.parse('$baseUrl/maps/style.json'));
  final style = jsonDecode(res.body) as Map<String, dynamic>;

  // Plain string concatenation on purpose: Uri would percent-encode the
  // {z}/{x}/{y}, {fontstack}, {range} tokens and break them.
  String abs(String url) => url.startsWith('http') ? url : '$baseUrl$url';

  final sources = style['sources'] as Map<String, dynamic>;
  // The style has a helper source "attribution" with no tiles/url. The web
  // renderer tolerates it, native MapLibre may refuse the whole style. Drop it.
  sources.remove('attribution');

  for (final source in sources.values.cast<Map<String, dynamic>>()) {
    if (source['tiles'] is List) {
      source['tiles'] = (source['tiles'] as List).map((t) => abs(t as String)).toList();
    }
    if (source['url'] is String) {
      source['url'] = abs(source['url'] as String);
    }
  }
  style['glyphs'] = abs(style['glyphs'] as String);
  style['sprite'] = abs(style['sprite'] as String);

  return jsonEncode(style);
}
```

```dart
MapLibreMap(
  styleString: cachedStyle, // result of loadMapStyle(), cached
  initialCameraPosition: const CameraPosition(
    target: LatLng(37.9415, 58.3794),
    zoom: 11,
  ),
  minMaxZoomPreference: const MinMaxZoomPreference(5, 18),
  cameraTargetBounds: CameraTargetBounds(
    LatLngBounds(
      southwest: LatLng(35.12, 51.83),
      northeast: LatLng(42.81, 66.72),
    ),
  ),
  attributionButtonPosition: AttributionButtonPosition.bottomRight,
  onMapCreated: (controller) => _controller = controller,
)
```

Requirements:

- Load the style **once** (app start or first map open) and cache it in memory and on
  disk — it is a few hundred KB. Never refetch it on every `build`.
- Build **one reusable map widget** (style loading, default camera, bounds,
  attribution) and use it on every map screen below.
- Show a loader while the style is loading and an error state with retry if it fails.

## 4. Map-related API (WGS84 decimal degrees everywhere)

All requests: `{API_BASE_URL}/api/v1/...`, header `Authorization: Bearer <token>`,
`Accept: application/json`.

### 4.1 Client: pick the order address on the map

User drops a pin (or uses current GPS) when creating or editing an order.

`POST /api/v1/client/orders` and `PATCH /api/v1/client/orders/{id}`:

| Field | Required | Rule |
|---|---|---|
| `client_lat` | yes | number, -90..90 |
| `client_lng` | yes | number, -180..180 |
| `client_address` | no | string, max 255 |

Note the field names: `client_lat` / `client_lng` (not `latitude` / `longitude`).
There is no geocoding endpoint — the address is a text field the user types.

### 4.2 Client: "master is on his way" screen

`GET /api/v1/client/orders/{id}/track` (optional `?since=<ISO8601>`)

```json
{
  "order_id": 1,
  "status": "assigned",
  "is_active": true,
  "destination": { "latitude": 37.95, "longitude": 58.38, "address": "..." },
  "master": { "id": 5, "name": "...", "phone": "..." },
  "last_location": {
    "latitude": 37.94, "longitude": 58.37,
    "recorded_at": "2026-10-01T10:00:00+05:00",
    "distance_km": 1.42
  },
  "points": [
    { "latitude": 37.93, "longitude": 58.36, "recorded_at": "..." }
  ]
}
```

- On open: draw the trail as a line (`points`), the master marker (`last_location`),
  and the destination marker. `master` and `last_location` can be `null`.
- Live points arrive over Reverb: private channel `client.{clientId}`, event
  `master.location.updated` (no leading dot). Payload:
  `master_id, order_id, latitude, longitude, distance_km, recorded_at`.
  Append the point to the line and move the marker.
- After a socket reconnect, fetch the gap: `track?since=<recorded_at of the last point>`.
- **`is_active: false`** (order completed/cancelled) — close tracking, stop listening.
  This is a normal state, not an error.
- `distance_km` is straight-line, not road distance. There is no routing on the backend
  — do not draw a road route.

### 4.3 Master: order pins

`GET /api/v1/master/orders?filter=active` — orders in status `assigned` / `in_progress`.

Each order has `latitude`, `longitude`, `address`.

- If `latitude` or `longitude` is `null` — **skip the pin**. Never plot `(0,0)`.
- The response is paginated (15 per page) — read `meta` / `links`.
- `GET /api/v1/master/orders/available` (open orders the master can respond to) has the
  same `latitude` / `longitude` fields.

### 4.4 Master: sending own location

`POST /api/v1/master/{masterId}/location`

```json
{
  "latitude": 37.952321,
  "longitude": 58.382345,
  "order_id": 42,
  "recorded_at": "2026-10-01T13:45:00+05:00"
}
```

- `latitude`, `longitude` required; `recorded_at` optional (server time by default).
- `{masterId}` must be the id of the master that owns the token, otherwise `403`.
- Send every **10–15 seconds** only while the master has an active job.
- **`order_id` is what makes the client see the master.** Send it from the
  `master.assigned` event until the order becomes `completed` / `cancelled`. Outside a
  job, stop the location stream entirely.
- Errors: `401` bad token; `403` master inactive / not the token owner; `404` order is
  not this master's; `422` invalid coordinates **or** the order is already finished.
  On `404` / `422` for an order — stop sending that `order_id`, do not retry.

## 5. Acceptance checklist

- [ ] `maplibre_gl` is used; no `flutter_map` `TileLayer`, no Google Maps
- [ ] Style downloaded, paths made absolute, `attribution` source removed, style cached
- [ ] One reusable map widget: Ashgabat zoom 11, Turkmenistan bounds, attribution visible
- [ ] Map renders streets and labels in Ashgabat (if labels are missing — glyphs URL is wrong; if the map is blank — tiles URL is wrong)
- [ ] Client: address picker sends `client_lat` / `client_lng` / `client_address`
- [ ] Client: tracking screen = `/track` + socket + `since` after reconnect; closes on `is_active: false`
- [ ] Master: pins from `filter=active`, null coordinates skipped
- [ ] Master: location sent with `order_id` only during an active job; stops on `404` / `422`
