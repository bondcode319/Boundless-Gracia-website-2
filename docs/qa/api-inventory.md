# API Inventory

Every network-reachable endpoint the theme adds. Discovered by grepping `add_action( 'wp_ajax`, `register_rest_route`, `add_shortcode`, `template_redirect` and cron registrations across `theme/inc/`, then **probed live** against `http://localhost:8088`.

WordPress core endpoints (`/wp-json/wp/v2/*`, `wp-login.php`, `xmlrpc.php`) are platform surface, not theme surface; only theme-applied changes to them are recorded, in the last table.

---

## A1. `POST admin-ajax.php?action=bgcf_donation_start`

| | |
|---|---|
| Auth | Nonce `bgcf_ajax` (public — `wp_ajax_nopriv` registered) |
| Authorization | none required (donating is public, correct) |
| Handler | `bgcf_ajax_donation_start()` — `inc/payments-routes.php:109` |
| Consumers | `theme/assets/js/src/donation-form.js:132` (form POST), `partials/global/donation-form.php` |

**Request** (`multipart/form-data` or urlencoded): `nonce`, `amount` (int), `method` (`mobile_money`\|`card`), `network` (`MTN`\|`AIRTEL`, mobile only), `name`, `email`, `phone`, `campaign` (int), `message`, `anonymous` (0/1), `hp` (honeypot — must be empty).

**Success 200:** `{ success: true, data: { redirect, reference, token, method, mode, message } }`

**Observed error contract** — every row below was reproduced live:

| Condition | HTTP | `data.code` |
|---|---|---|
| Missing/expired nonce | 403 | `bgcf_invalid_nonce` |
| Honeypot filled | 400 | `rejected` |
| >12 attempts / 15 min / IP | 429 | `rate_limited` |
| Gateway credentials absent | 503 | `unavailable` |
| Unknown method | 422 | `invalid_method` |
| Malformed email | 422 | `invalid_email` |
| Amount outside min/max | 422 | `invalid_amount` |
| Non-UG phone | 422 | `invalid_phone` |
| Unrecognised network prefix | 422 | `invalid_network` |
| Record insert failed | 422 | `record_failed` |
| Provider rejected the call | 502 | `gateway_error` |
| Provider returned no payment URL | 502 | `no_redirect` |

**Status codes reachable:** 200, 400, 403, 422, 429, 502, 503. Not reachable by design: 401, 404, 409.

**Contract check:** the JS consumer reads `result.data.redirect`, `.token`, `.message`, `.field`. The server sends `field` only on `bgcf_donation_fail()` paths (422). `gateway_error` and `no_redirect` carry **no `field`** — the JS handles that (`if (error.field)`), so **no mismatch**. Verified by reading both sides.

**Defects:** DEF-008 (provider message relayed verbatim), DEF-009 (array-typed params), DEF-015 (rate limit counts validation failures).

---

## A2. `POST admin-ajax.php?action=bgcf_donation_status`

| | |
|---|---|
| Auth | Nonce `bgcf_ajax` (public) |
| Handler | `bgcf_ajax_donation_status()` — `inc/payments-routes.php:330` |
| Consumers | `donation-form.js:225` (`initDonationResult` poll loop, 5 s x 24) |

**Request:** `nonce`, `token` (24-char opaque public token).

**Success 200:** `{ success: true, data: { status, label, amount, url } }` — carries **no donor PII**. Confirmed by reading the response builder.

**Errors:** 403 `bgcf_invalid_nonce`; 404 `not_found` (reproduced live).

**Side effect:** when the record is `pending` and >= 10 s since the last requery, this endpoint makes an **outbound Flutterwave API call**. This is an unauthenticated endpoint with **no rate limit** — see DEF-012.

---

## A3. `POST /wp-json/bgcf/v1/flutterwave/webhook`

| | |
|---|---|
| Auth | `verif-hash` header vs `BGCF_FLW_SECRET_HASH`, `hash_equals` (constant time) |
| Handler | `bgcf_handle_flutterwave_webhook()`; permission `bgcf_flw_webhook_permission()` |
| Consumer | Flutterwave only |

**Probed live:**

| Case | HTTP | Body |
|---|---|---|
| No signature | **401** | `{"code":"bgcf_invalid_signature",...}` |
| Wrong signature | **401** | `{"code":"bgcf_invalid_signature",...}` |
| Correct signature, unknown tx_ref | **200** | `{"received":true}` |
| Correct signature, empty body | **200** | `{"received":true}` |
| `GET` with correct signature | **404** | `rest_no_route` (method not allowed) |

**Correct by design:** always 200 once authenticated, including for events and references we do not recognise — a non-2xx would push "not mine" into Flutterwave's retry queue. The body is treated as a trigger only; amount and status come from the server's own verify call.

**Gaps:** no rate limit on the authenticated path (each call triggers an outbound verify); no replay/event-id dedup (mitigated by the requery being idempotent-in-intent — see DEF-004 for where that breaks down).

---

## A4. `GET /?bgcf-payment-return=1&tx_ref=...`

| | |
|---|---|
| Auth | **none, by design** — third-party redirect, cannot carry a nonce |
| Handler | `bgcf_donation_handle_return()` on `template_redirect` priority 1 |

Query string is attacker-controlled and is used for exactly one thing: choosing which donation to ask the API about. The outcome comes from `bgcf_donation_requery()`, which reads only stored values. `?status=successful` in the URL changes nothing. **Correct.**

Unknown `tx_ref` -> redirect to home. Known -> requery, then redirect to the result URL carrying the opaque token.

---

## A5. Shortcode `[bgcf_donation_result]`

Read-only render keyed on `?donation=<token>`. Status is read from the record, never the query string. Renders `partials/global/donation-result.php`.

---

## A6. Cron `bgcf_donations_reconcile` (every 15 minutes)

Not network-reachable, but it is the **safety net that makes the whole flow correct when webhooks are lost**, so it is inventoried here.

Batch of 20 `pending` records ordered by ID ASC. Skips records younger than 2 minutes. Requeries each. Marks `abandoned` after 24 h. Defects: DEF-018, DEF-019.

---

## A7. Unimplemented endpoints — registered, advertised, dead

All three are live on every install, pass the nonce guard, and then return **HTTP 501**. Reproduced live.

| Action | Handler | Documented contract | Reality |
|---|---|---|---|
| `bgcf_newsletter_subscribe` | `inc/ajax.php:124` | `{ message }` | `501 {"code":"not_implemented"}` |
| `bgcf_volunteer_submit` | `inc/ajax.php:147` | `{ message, reference }` | `501 {"code":"not_implemented"}` |
| `bgcf_gallery_load_more` | `inc/ajax.php:170` | `{ html, hasMore, paged }` | `501 {"code":"not_implemented"}` |

See DEF-003. Note `bgcf_volunteer_submit` is documented as accepting a **file upload** (`resume`); no upload handling exists anywhere in the theme (`grep` for `$_FILES` / `wp_handle_upload` returns nothing), so Phase 13 of the audit brief has **no implementation to test**.

---

## A8. Theme-applied changes to WordPress core surface

| Endpoint | Change | Verified |
|---|---|---|
| `/wp-json/wp/v2/users` | removed for logged-out callers | code; `rest_endpoints` filter |
| `/?author=N` | 301 redirect to home for logged-out callers | code — see DEF-017 (301 vs 302) |
| `xmlrpc.php` | `xmlrpc_enabled` -> false, `pings_open` -> false | code |
| `X-Pingback` header | removed | **live: absent** |
| `/wp-json/wp/v2/bgcf_donation` | never registered (`show_in_rest => false`) | **live: 404** |

### Security response headers (live)

| Header | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), payment=(), interest-cohort=()` |
| `Cross-Origin-Resource-Policy` | `same-origin` |
| `Strict-Transport-Security` | **absent** (documented as edge-owned; no doc exists to own it — CFG-02) |
| `Content-Security-Policy` | **absent** (same) |

> `Permissions-Policy: payment=()` disables the browser Payment Request API site-wide. Harmless for the current redirect-based flow; a latent conflict if an inline card widget is ever embedded. Logged as DEF-022 (INFO).
