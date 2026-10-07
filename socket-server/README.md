# OTP gateway (socket-server)

Bridge between Laravel and the phone that sends OTP codes by SMS.

```
Laravel ──HTTP POST /emit-otp──▶ socket-server ──Socket.IO event "otp"──▶ phone (Flutter OTP Listener) ──▶ SMS
```

The gateway sends no SMS itself and does not answer the phone. It only relays `{phone_number, otp}` to every connected phone.

One shared secret, `OTP_SECRET`, on all three sides: the Laravel `.env`, the gateway environment, and the phone's Auth Token.

## Run

### Without Docker

```bash
cd socket-server
cp .env.example .env        # set OTP_SECRET (openssl rand -hex 32)
npm ci --omit=dev
npm start                   # = node index.js
```

Laravel (`.env` in the project root):

```dotenv
SMS_DRIVER=modem
SMS_GATEWAY_URL=http://127.0.0.1:3000
OTP_SECRET=<same value>
```

Then `php artisan config:cache`. Keep the process alive with systemd or pm2.

The phone connects either straight to `http://<server-ip>:3000` (`HOST=0.0.0.0`, port 3000 firewalled to the phone's IP) or to `https://<domain>` through the nginx location below (`HOST=127.0.0.1`). See [Security](#security).

### With Docker

The `sms-gateway` service in `docker-compose.yml` takes `OTP_SECRET`, `OTP_EVENT_NAME` and `ENABLE_TEST_PAGE` from the root `.env` (`socket-server/.env` is not used) and refuses to start without `OTP_SECRET`. Laravel reaches it at `SMS_GATEWAY_URL=http://sms-gateway:3000`; the phone reaches it through nginx at `/socket.io/`.

## Environment

| Variable | Default | Meaning |
|---|---|---|
| `PORT` | `3000` | Listening port |
| `HOST` | `0.0.0.0` | Listening address. `127.0.0.1` when only a local nginx proxies `/socket.io/` |
| `OTP_SECRET` | — | **Required.** Without it the process exits with code 1 |
| `OTP_EVENT_NAME` | `otp` | Event name the phone listens to |
| `ENABLE_TEST_PAGE` | `false` | Serve the tester at `GET /test` |

## Phone connection

Socket.IO v4, namespace `/`. Example with `socket_io_client`:

```dart
final socket = IO.io('http://<server>:3000', <String, dynamic>{
  'transports': ['websocket'],
  'auth': {'token': '<OTP_SECRET>'},
});
```

The secret is accepted in this order:

1. `auth: {'token': ...}` — the main way
2. `auth: {'secret': ...}`
3. `X-Otp-Secret` or `X-Gateway-Secret` header (`setExtraHeaders` on Flutter). The second name is the one the alo-komek gateway uses, so a phone configured for it connects unchanged

The query string (`?token=`) is **not** accepted: it would end up in access logs.

| Server reply to CONNECT | Meaning |
|---|---|
| `40{"sid":"..."}` | Connected; `/health` → `clients` grows by 1 |
| `44{"message":"Unauthorized"}` | Wrong or missing secret; logged as a warning with the client IP |

Every phone receives:

```
42["otp",{"phone_number":"61234567","otp":"123456","token":"<OTP_SECRET>"}]
```

- `phone_number` — 8 digits, without `+993`. The phone sends the SMS to `+993` + `phone_number`.
- `token` — lets a client with Auth Token checking accept the event.

The code is sent to **every** connected phone: two connected phones send two SMS.

Keep-alive: ping every 25 s, disconnect after 20 s without a pong. The client library handles it; after a disconnect `/emit-otp` answers `503` until the phone is back.

## HTTP API

### `POST /emit-otp` — called by Laravel

Header `X-Otp-Secret: <OTP_SECRET>`, JSON body `{"phone_number": "61234567", "otp": "123456"}`.

| Status | Body | When |
|---|---|---|
| `200` | `{"message":"OTP event emitted"}` | Sent to all connected phones |
| `401` | `{"message":"Unauthorized"}` | Wrong or missing `X-Otp-Secret` |
| `422` | `{"message":"phone_number and otp are required"}` | A field is missing |
| `503` | `{"message":"No gateway client connected"}` | No phone connected |

Only the phone number is logged, never the code.

### `GET /health` — no secret

```json
{"status":"ok","clients":1}
```

`clients` counts authenticated phones (`io.of('/').sockets.size`). The admin panel's system status card reads it.

### `GET /test`

Registered only when `ENABLE_TEST_PAGE=true`. A browser page served by the gateway itself (Socket.IO client from `/socket.io/socket.io.js`): enter the secret, connect, and watch `otp` events arrive. Keep it off in production — it is a ready-made OTP listener for anyone who knows the secret.

## Security

- Traffic on port 3000 is **plain HTTP/WS, without TLS**: the secret and every code travel in clear text. Either expose the gateway only through an HTTPS nginx location `/socket.io/`, or close port 3000 with a firewall and allow only the phone's IP.
- `/emit-otp` and `/health` must never be public: proxy `/socket.io/` only.
- Use a long random `OTP_SECRET` (`openssl rand -hex 32`) and rotate it on all three sides together.

nginx location for the HTTPS variant:

```nginx
location /socket.io/ {
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_read_timeout 3600s;
}
```

The phone then connects to `https://<domain>` instead of `http://<server>:3000`.
