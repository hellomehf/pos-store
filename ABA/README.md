# ABA PayWay sandbox test

Minimal PayWay Checkout 2.0 integration with **server-side** HMAC-SHA512 signing.

## Run

1. Put your current sandbox key in `.env`:

   ```dotenv
   PAYWAY_MERCHANT_ID=ec480150
   PAYWAY_API_KEY=<the 40-char Public Key from the email>
   ```

2. Start the server:

   ```powershell
   node server.js
   ```

3. Open **http://localhost:3000**

No `npm install` needed. Uses only Node built-ins (tested on Node 24).

## Files

| File | Purpose |
| --- | --- |
| `server.js` | Generates a fresh `tran_id` + `req_time`, signs the request, serves the page, proxies Check Transaction |
| `test.html` | Pay button, plugin fallback to hosted view, transaction status checker |
| `.env` | Merchant ID and API key (gitignored) |
| `.gitignore` | Keeps `.env` out of version control |

## Current status (2026-11-05)

Working against merchant `ec480150` with a Public Key valid until
**5 December 2026**.

The live smoke test returned **HTTP 200** with the PayWay Checkout HTML page.

`payment_gate: 0` is required for this profile. Without it, the Purchase
endpoint responds with the KHQR JSON payload (`qrString` / `qrImage`) instead
of the checkout page. This field is **not** part of the hash.

## Test cards

| Result | Card | Exp | CVV | 3DS |
| --- | --- | --- | --- | --- |
| Success | `5156 8399 3770 6777` | `01/30` | `993` | No |
| Success | `4286 0900 0000 0206` | `04/30` | `777` | Yes |
| Declined | `5156 8302 7256 1029` | `04/30` | `777` | Yes |
| Declined | `4156 8399 3770 6777` | `01/30` | `993` | No |

3DS OTP is sent to the email registered with PayWay.

## Troubleshooting

| Code | Meaning | Fix |
| --- | --- | --- |
| `1` | Wrong hash | Check field order and that posted values match signed values |
| `4` | Duplicated `tran_id` | Reload the page / press Pay again (a new ID is generated) |
| `6` | Domain/IP not whitelisted | Ask PayWay to whitelist your origin or public IP |
| `12` | Currency not allowed | Enable `USD` on the merchant profile |
| `21` | API lifetime ended | Renew the public key |
| `23` | Payment option not enabled | Leave `payment_option` empty |
| `81` | `return_url` domain not whitelisted | Whitelist it, or omit `return_url` |

## Security notes

- The API key is only read by `server.js`. Never place it in `test.html`.
- The RSA public/private key pair from the email is **not used** by Checkout.
  Do not commit it or load it in the browser.
- If the RSA private key was shared anywhere, regenerate it.
- `return_url` is intentionally omitted for now. Add it only after its domain is
  whitelisted, and verify the `X-PAYWAY-HMAC-SHA512` header on callbacks.

## Going live

Replace `PAYWAY_PURCHASE_URL` with the production endpoint
`https://checkout.payway.com.kh/api/payment-gateway/v1/payments/purchase`
and use production credentials.
