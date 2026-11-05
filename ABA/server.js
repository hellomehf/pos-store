'use strict';

const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

// --- tiny .env loader (no dependencies) -------------------------------------
function loadEnv(file) {
  if (!fs.existsSync(file)) return;
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq === -1) continue;
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (!(key in process.env)) process.env[key] = value;
  }
}

loadEnv(path.join(__dirname, '.env'));

const MERCHANT_ID = process.env.PAYWAY_MERCHANT_ID;
const API_KEY = process.env.PAYWAY_API_KEY;
const PURCHASE_URL =
  process.env.PAYWAY_PURCHASE_URL ||
  'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase';
const CHECK_URL = PURCHASE_URL.replace(/\/purchase$/, '/check-transaction-2');
const PORT = Number(process.env.PORT) || 3000;

if (!MERCHANT_ID || !API_KEY) {
  console.error('Missing PAYWAY_MERCHANT_ID or PAYWAY_API_KEY in .env');
  process.exit(1);
}

// --- signing ----------------------------------------------------------------
// Exact order documented by PayWay. Fields not sent contribute an empty string.
const HASH_ORDER = [
  'req_time',
  'merchant_id',
  'tran_id',
  'amount',
  'items',
  'shipping',
  'firstname',
  'lastname',
  'email',
  'phone',
  'type',
  'payment_option',
  'return_url',
  'cancel_url',
  'continue_success_url',
  'return_deeplink',
  'currency',
  'custom_fields',
  'return_params',
  'payout',
  'lifetime',
  'additional_params',
  'google_pay_token',
  'skip_success_page',
];

function reqTime() {
  // PayWay expects UTC in YYYYMMDDHHmmss.
  return new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
}

function sign(fields) {
  const raw = HASH_ORDER.map((key) => fields[key] ?? '').join('');
  return crypto.createHmac('sha512', API_KEY).update(raw).digest('base64');
}

function createPayment() {
  const fields = {
    req_time: reqTime(),
    merchant_id: MERCHANT_ID,
    tran_id: 'T' + Date.now(), // unique, <= 20 chars
    amount: '0.10',
    currency: 'USD',
    firstname: 'Sina',
    lastname: 'Chhum',
    phone: '093939399',
    payment_option: '', // empty = show every method enabled on the profile
    payment_gate: 0, // force the Checkout service (not the QR API)
  };
  fields.hash = sign(fields);
  return fields;
}

function signCheck(payload) {
  // Check Transaction hash covers only req_time + merchant_id + tran_id.
  const raw = payload.req_time + payload.merchant_id + payload.tran_id;
  return crypto.createHmac('sha512', API_KEY).update(raw).digest('base64');
}

// --- http helpers -----------------------------------------------------------
function sendJson(res, status, data) {
  const body = JSON.stringify(data);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

// --- server -----------------------------------------------------------------
const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host}`);

    if (req.method === 'GET' && url.pathname === '/') {
      const html = fs.readFileSync(path.join(__dirname, 'test.html'));
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(html);
      return;
    }

    if (req.method === 'POST' && url.pathname === '/api/create-payment') {
      sendJson(res, 200, createPayment());
      return;
    }

    if (req.method === 'POST' && url.pathname === '/api/check-transaction') {
      const raw = await readBody(req);
      const input = raw ? JSON.parse(raw) : {};
      if (!input.tran_id) {
        sendJson(res, 400, { error: 'tran_id is required' });
        return;
      }

      const payload = {
        req_time: reqTime(),
        merchant_id: MERCHANT_ID,
        tran_id: input.tran_id,
      };
      payload.hash = signCheck(payload);

      const upstream = await fetch(CHECK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const text = await upstream.text();
      res.writeHead(upstream.status, {
        'Content-Type': 'application/json; charset=utf-8',
      });
      res.end(text);
      return;
    }

    // Optional: point return_url here once its domain is whitelisted.
    if (req.method === 'POST' && url.pathname === '/payway/return') {
      const body = await readBody(req);
      console.log('PayWay callback:', body);
      sendJson(res, 200, { received: true });
      return;
    }

    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Not found');
  } catch (err) {
    console.error(err);
    sendJson(res, 500, { error: err.message });
  }
});

server.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
  console.log(`Merchant: ${MERCHANT_ID}`);
});
