require('dotenv').config();

const crypto = require('crypto');
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const PORT = process.env.PORT || 3000;
// 0.0.0.0 inside Docker and when the phone connects straight to the port.
// Behind an nginx /socket.io/ proxy on the same machine, 127.0.0.1 is enough.
const HOST = process.env.HOST || '0.0.0.0';
const OTP_SECRET = process.env.OTP_SECRET || '';
const OTP_EVENT_NAME = process.env.OTP_EVENT_NAME || 'otp';
const ENABLE_TEST_PAGE = process.env.ENABLE_TEST_PAGE === 'true';

// Every connected socket receives OTP codes, so running without a secret would
// hand them to anyone who can reach the port. Refuse to start instead.
if (!OTP_SECRET) {
  console.error('[gateway] OTP_SECRET is not set — refusing to start. Put it in socket-server/.env or the environment.');
  process.exit(1);
}

function secretMatches(candidate) {
  if (typeof candidate !== 'string' || candidate === '') {
    return false;
  }

  const expected = Buffer.from(OTP_SECRET);
  const given = Buffer.from(candidate);

  return given.length === expected.length && crypto.timingSafeEqual(given, expected);
}

const app = express();
app.use(express.json());

const server = http.createServer(app);

// The phone is a native client, not a browser page on another origin, so
// cross-origin access is switched off entirely.
const io = new Server(server, { cors: { origin: false } });

// Phone authentication, checked in this order:
//   1. io(url, { auth: { token: OTP_SECRET } })   — the main way
//   2. io(url, { auth: { secret: OTP_SECRET } })
//   3. X-Otp-Secret or X-Gateway-Secret header (setExtraHeaders on Flutter);
//      the second name keeps phones set up for the alo-komek gateway working
// The query string is deliberately not accepted: it ends up in access logs.
io.use((socket, next) => {
  const auth = socket.handshake.auth || {};
  const { headers } = socket.handshake;
  const candidates = [auth.token, auth.secret, headers['x-otp-secret'], headers['x-gateway-secret']];

  if (!candidates.some(secretMatches)) {
    console.warn(`[gateway] rejected unauthenticated socket from ${socket.handshake.address}`);

    return next(new Error('Unauthorized'));
  }

  return next();
});

io.on('connection', (socket) => {
  console.log(`[gateway] phone connected: ${socket.id} from ${socket.handshake.address} (total: ${io.of('/').sockets.size})`);

  socket.on('disconnect', (reason) => {
    console.log(`[gateway] phone disconnected: ${socket.id}, ${reason} (total: ${io.of('/').sockets.size})`);
  });
});

// Laravel calls this endpoint when an OTP is requested. The payload is
// re-emitted to every connected phone; the phone sends the SMS.
app.post('/emit-otp', (req, res) => {
  if (!secretMatches(req.get('X-Otp-Secret'))) {
    return res.status(401).json({ message: 'Unauthorized' });
  }

  const { phone_number: phoneNumber, otp } = req.body || {};

  if (!phoneNumber || !otp) {
    return res.status(422).json({ message: 'phone_number and otp are required' });
  }

  if (io.of('/').sockets.size === 0) {
    console.warn(`[gateway] no phone connected, OTP for ${phoneNumber} dropped`);

    return res.status(503).json({ message: 'No gateway client connected' });
  }

  // `token` lets a phone with Auth Token checking enabled accept the event.
  io.emit(OTP_EVENT_NAME, { phone_number: phoneNumber, otp, token: OTP_SECRET });
  console.log(`[gateway] OTP emitted for ${phoneNumber} to ${io.of('/').sockets.size} phone(s)`);

  return res.json({ message: 'OTP event emitted' });
});

app.get('/health', (req, res) => {
  res.json({ status: 'ok', clients: io.of('/').sockets.size });
});

// Manual tester: connects the way the phone does and prints received events.
// Keep it off in production — it is a ready-made OTP listener for anyone who
// knows the secret.
if (ENABLE_TEST_PAGE) {
  app.get('/test', (req, res) => {
    res.type('html').send(`<!doctype html>
<meta charset="utf-8">
<title>OTP gateway tester</title>
<input id="secret" placeholder="OTP_SECRET" type="password">
<button id="connect">Connect</button>
<pre id="log"></pre>
<script src="/socket.io/socket.io.js"></script>
<script>
  const log = (line) => { document.getElementById('log').textContent += line + '\\n'; };
  document.getElementById('connect').onclick = () => {
    const socket = io({ transports: ['websocket'], auth: { token: document.getElementById('secret').value } });
    socket.on('connect', () => log('connected ' + socket.id));
    socket.on('connect_error', (e) => log('connect_error: ' + e.message));
    socket.on('disconnect', (reason) => log('disconnected: ' + reason));
    socket.on(${JSON.stringify(OTP_EVENT_NAME)}, (data) => log(new Date().toLocaleTimeString() + ' otp: ' + JSON.stringify(data)));
  };
</script>`);
  });
}

server.listen(PORT, HOST, () => {
  console.log(`[gateway] listening on ${HOST}:${PORT}, event="${OTP_EVENT_NAME}", test page ${ENABLE_TEST_PAGE ? 'on' : 'off'}`);
});
