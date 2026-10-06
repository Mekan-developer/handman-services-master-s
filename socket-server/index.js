require('dotenv').config();

const crypto = require('crypto');
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const PORT = process.env.PORT || 3000;
// 0.0.0.0 inside Docker, where nginx and php reach it over the network.
// On a bare server set 127.0.0.1 and expose only /socket.io/ through nginx.
const HOST = process.env.HOST || '0.0.0.0';
const OTP_SECRET = process.env.OTP_SECRET || '';
const OTP_EVENT_NAME = process.env.OTP_EVENT_NAME || 'otp';
const ENABLE_TEST_PAGE = process.env.ENABLE_TEST_PAGE === 'true';

// Every connected socket receives OTP codes, so running without a secret would
// hand them to anyone who can reach the port. Refuse to start instead.
if (!OTP_SECRET) {
  console.error('[gateway] OTP_SECRET is not set — refusing to start');
  process.exit(1);
}

function secretMatches(candidate) {
  const expected = Buffer.from(OTP_SECRET);
  const given = Buffer.from(String(candidate || ''));

  return given.length === expected.length && crypto.timingSafeEqual(given, expected);
}

const app = express();
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, { cors: { origin: '*' } });

// The SMS-gateway phone authenticates in the handshake:
// io(url, { auth: { token: OTP_SECRET } }). Anything else is turned away
// before it can join and see a single code.
io.use((socket, next) => {
  if (!secretMatches(socket.handshake.auth && socket.handshake.auth.token)) {
    console.warn(`[gateway] rejected unauthenticated socket from ${socket.handshake.address}`);

    return next(new Error('Unauthorized'));
  }

  return next();
});

io.on('connection', (socket) => {
  console.log(`[gateway] gateway connected: ${socket.id} (total: ${io.of('/').sockets.size})`);

  socket.on('disconnect', (reason) => {
    console.log(`[gateway] gateway disconnected: ${socket.id}, ${reason} (total: ${io.of('/').sockets.size})`);
  });
});

/**
 * The phone that should send the next SMS: the most recently connected one.
 * Emitting to every socket would make each connected phone send its own copy.
 */
function pickGateway() {
  const sockets = [...io.of('/').sockets.values()];

  return sockets.length > 0 ? sockets[sockets.length - 1] : null;
}

// Laravel calls this endpoint when an OTP is requested. It re-emits the
// payload as a Socket.IO event the Flutter SMS-gateway phone is listening to.
app.post('/emit-otp', (req, res) => {
  if (!secretMatches(req.get('X-Gateway-Secret'))) {
    return res.status(401).json({ message: 'Unauthorized' });
  }

  const { phone_number: phoneNumber, otp } = req.body || {};

  if (!phoneNumber || !otp) {
    return res.status(422).json({ message: 'phone_number and otp are required' });
  }

  const gateway = pickGateway();

  if (gateway === null) {
    console.warn('[gateway] no SMS-gateway phone connected, OTP event dropped');

    return res.status(503).json({ message: 'No gateway client connected' });
  }

  gateway.emit(OTP_EVENT_NAME, { phone_number: phoneNumber, otp });
  console.log(`[gateway] OTP emitted for ${phoneNumber} to ${gateway.id}`);

  return res.json({ message: 'OTP event emitted' });
});

app.get('/health', (req, res) => {
  res.json({ status: 'ok', clients: io.of('/').sockets.size });
});

// Manual tester: connects like the phone does and prints received events.
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
    const socket = io({ auth: { token: document.getElementById('secret').value } });
    socket.on('connect', () => log('connected ' + socket.id));
    socket.on('connect_error', (e) => log('connect_error: ' + e.message));
    socket.on(${JSON.stringify(OTP_EVENT_NAME)}, (data) => log('otp: ' + JSON.stringify(data)));
  };
</script>`);
  });
}

server.listen(PORT, HOST, () => {
  console.log(`[gateway] socket.io server listening on ${HOST}:${PORT}, event="${OTP_EVENT_NAME}", test page ${ENABLE_TEST_PAGE ? 'on' : 'off'}`);
});
