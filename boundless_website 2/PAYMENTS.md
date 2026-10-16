# Payments — Flutterwave integration

How giving works on this site, how to run it in sandbox, and what has to be true
before it takes real money.

**Status: sandbox.** Nothing here has been run against live keys. The go-live
section at the end is the gate.

---

## 1. Why Flutterwave, and why not GiveWP

`CLAUDE.md` and `PLAN.md` name GiveWP for donations. This build takes payments
through a **native Flutterwave (API v3) integration in the theme** instead, for
one reason: the organisation collects in **UGX**, and the rail that matters in
Uganda is mobile money (MTN and Airtel). GiveWP has no first-party Ugandan
mobile money gateway, so it would need a third-party add-on and would still hand
off to Flutterwave underneath — with a plugin, a licence and an update treadmill
in between us and the money.

What we give up by not using GiveWP: recurring giving, donor accounts, the donor
wall, and the GiveWP reporting screens. Recurring giving is the notable one —
Flutterwave supports payment plans and card tokenization, so it is buildable, but
it is **not built here**. If the foundation needs monthly giving, that is a
scoped follow-up, not a switch to flip.

If GiveWP is later preferred, the pieces to remove are the five files in §3; the
block partial would then render a GiveWP form instead.

**Uganda constraint that shapes everything:** in UGX, Flutterwave settles
**cards and mobile money only**. There is no bank transfer, no USSD, no virtual
account and no bill payment in UGX. Do not design features around rails that
cannot settle.

---

## 2. Configure sandbox in 10 minutes

### 2.1 Get test keys

1. Create a Flutterwave account and stay in **test mode** (the dashboard toggle).
2. Settings → API Keys. Copy the **public key** (`FLWPUBK_TEST-…-X`) and the
   **secret key** (`FLWSECK_TEST-…-X`).
3. Invent a **secret hash** — any long random string. It is the webhook password;
   you choose it, then tell Flutterwave the same value.

### 2.2 Put them in `wp-config.php`

Above the `/* That's all, stop editing! */` line:

```php
define( 'BGCF_FLW_PUBLIC_KEY',  'FLWPUBK_TEST-xxxxxxxxxxxxxxxx-X' );
define( 'BGCF_FLW_SECRET_KEY',  'FLWSECK_TEST-xxxxxxxxxxxxxxxx-X' );
define( 'BGCF_FLW_SECRET_HASH', 'a-long-random-string-you-invent' );
```

**Never** in the database, never in ACF, never in the repo. The theme reads these
constants (or the same names as environment variables) and nothing else. The
settings screen shows a fingerprint so you can confirm which key is loaded
without exposing it.

The **mode is derived from the key prefix**, not from a setting: a
`FLWSECK_TEST-` key means sandbox, anything else means live. There is
deliberately no "test mode" checkbox to get out of sync with reality.

### 2.3 Register the webhook

Flutterwave → Settings → Webhooks:

| Field | Value |
|---|---|
| URL | `https://yoursite.test/wp-json/bgcf/v1/flutterwave/webhook` |
| Secret hash | the same string as `BGCF_FLW_SECRET_HASH` |
| Events | enable all |
| Retries | on |

The exact URL for this install is printed at **Donations → Settings**.

Do this **separately for test and live mode** — they are two different webhook
configurations, and forgetting the live one is the classic launch-day bug.

Local development: Flutterwave cannot reach `localhost`. Either tunnel
(`cloudflared tunnel --url http://localhost:8080`, ngrok) and register the tunnel
URL, or skip the webhook entirely — the reconciliation job (§5) settles every
donation within 15 minutes regardless.

### 2.4 Configure the form

**Donations → Settings** in wp-admin: currency, suggested amounts, min/max,
which methods to offer, the thank-you page, receipt and notification emails.

Then either:
- drop the **Donation Banner** block on a page (it renders the form), or
- render the form anywhere:
  `bgcf_render_partial( 'global/donation-form', array( 'campaign' => $id ) );`

On the thank-you page, add the shortcode `[bgcf_donation_result]`. It shows the
donor the verified outcome and polls while a mobile money authorisation is still
in flight.

---

## 3. What is where

| File | Responsibility |
|---|---|
| `theme/inc/payments.php` | Flutterwave API client: credentials, mode, HTTP, MSISDN normalisation, `payload_hash`, webhook signature. The only file that reads a secret key. |
| `theme/inc/donations.php` | Donation records (CPT `bgcf_donation`), settings, status machine, verification rules, idempotent fulfilment, receipts. |
| `theme/inc/payments-routes.php` | Public surface: AJAX start/status, REST webhook, return-URL handler, result shortcode, reconciliation cron. |
| `theme/inc/payments-admin.php` | Settings screen, donations list table, read-only record detail. |
| `theme/partials/global/donation-form.php` | The form. |
| `theme/partials/global/donation-result.php` | The outcome panel. |
| `theme/partials/blocks/block-donation-banner.php` | Block wrapper around the form. |
| `theme/assets/js/src/donation-form.js` | Submit, redirect, poll. Decides nothing about money. |
| `theme/assets/scss/components/_donation.scss` | Styling. |

---

## 4. The flow

```
donor fills form
   │
   ▼
POST admin-ajax.php (bgcf_donation_start)
   │  nonce + honeypot + rate limit
   │  amount/method/email/phone validated SERVER-side against the settings
   ▼
donation record created (status: pending, tx_ref generated server-side)
   │
   ├── mobile money → POST /v3/charges?type=mobile_money_uganda
   │                  → meta.authorization.redirect
   └── card         → POST /v3/payments (with payload_hash) → data.link
   │
   ▼
donor approves on handset / pays on Flutterwave's page
   │
   ├── webhook         → verify → fulfil        (fast, may not arrive)
   ├── return URL      → verify → fulfil        (only if they come back)
   └── cron, 15 min    → verify → fulfil        (always)
```

**The rule:** no signal is trusted on its own. Each one triggers
`GET /v3/transactions/{id}/verify` and the answer must pass four checks before a
donation counts — status `successful`, **our** `tx_ref`, the expected currency,
and at least the expected amount. A successful payment that fails any of those is
parked as "needs review", never fulfilled and never discarded.

Fulfilment is idempotent (a unique `_bgcf_fulfilled` meta row), so all three
signals firing for one payment still produces one receipt.

Things the browser does **not** get to decide: the amount charged, the
transaction reference, the currency, or whether a payment succeeded.

---

## 5. Reconciliation

A cron job (`bgcf_donations_reconcile`, every 15 minutes) re-asks Flutterwave
about every donation still `pending`, skipping ones under two minutes old and
closing out ones over 24 hours as `abandoned`. This is why the integration is
correct even when webhooks are lost, delayed or never configured — but it needs
WP-Cron to actually run. On a low-traffic site, schedule a real cron hit:

```
*/5 * * * * curl -s https://yoursite.org/wp-cron.php?doing_wp_cron >/dev/null
```

(and set `define( 'DISABLE_WP_CRON', true );`).

The next run time is shown on the Donations → Settings screen.

### Page caching

The form carries a WordPress nonce, and a nonce for a logged-out visitor is only
valid for 12–24 hours. A full-page cache (WP Rocket, Cloudflare APO) that serves
day-old HTML will therefore serve dead forms, which fail with "Your session
expired". **Exclude any page containing the donation form from full-page
caching**, or set the cache lifespan below 12 hours. The honeypot and the rate
limit still stand on their own if you decide otherwise.

---

## 6. Testing in sandbox

### Mobile money (Uganda)

Any Ugandan-format number works: `0772123456`, `+256 772 123 456`, `256772123456`
all normalise to `256772123456`. **Test-mode Uganda mobile money auto-authorises
after a few seconds** — there is no handset prompt to approve.

Test that:
- [ ] MTN prefix (077/078/076/039) and Airtel prefix (070/074/075/020) both work
- [ ] leaving the network on "Detect from my number" picks the right one
- [ ] an unrecognised number is rejected with a useful message, not a 500
- [ ] the donation record reaches `successful` and a receipt arrives
- [ ] a second webhook delivery does **not** send a second receipt

### Cards

Hosted checkout. Flutterwave's v3 test cards:

| Scenario | Number | Exp | CVV | OTP | PIN |
|---|---|---|---|---|---|
| 3DS success (Visa) | 4187427415564246 | 09/32 | 828 | 12345 | 3310 |
| PIN success (Mastercard) | 5531886652142950 | 09/32 | 564 | 12345 | 3310 |
| Insufficient funds | 5258585922666506 | 09/31 | 883 | 12345 | 3310 |
| Do not honour | 5143010522339965 | 08/32 | 276 | 12345 | 3310 |

Check the failure cards leave the record `failed` and tell the donor they were
not charged.

### Edge cases worth forcing

- [ ] **Tampered amount:** submit the form, then replay the request with a larger
      amount — the server clamps to the settings and the `payload_hash` on hosted
      checkout makes the checkout page reject an edited amount.
- [ ] **Forged webhook:** POST to the webhook URL with a wrong or missing
      `verif-hash` → 401, nothing changes.
- [ ] **No webhook at all:** disable it, donate, wait for the cron run → record
      still settles.
- [ ] **Donor closes the tab:** never returns → cron settles it.
- [ ] **Rate limit:** 12 attempts in 15 minutes from one IP → 429.

Sandbox data is archived by Flutterwave after 30 days.

---

## 7. Before going live

Engineering:

- [ ] Live keys (`FLWPUBK-…`, `FLWSECK-…`) swapped into `wp-config.php`; the
      settings screen shows **Live mode**
- [ ] Webhook re-registered for **live** mode with the same secret hash
- [ ] HTTPS enforced site-wide (TLS 1.2+)
- [ ] WP-Cron verified to be running
- [ ] `WP_DEBUG` off in production (gateway logging follows it)
- [ ] One real, low-value donation taken end-to-end and refunded
- [ ] Receipt and notification emails confirmed deliverable (WP Mail SMTP)

Flutterwave account — **an unapproved KYC file cannot be fixed with code**:

- [ ] NGO onboarding documents submitted and approved: certificate of
      registration, **NGO Bureau permit**, certified MemArts, board resolution
      signed by the directors, URA PIN certificate, directors' colour IDs,
      cancelled cheque or bank reference letter, verifiable website URL
- [ ] Settlement bank account configured (UG local settlement is next-day)
- [ ] Pricing confirmed in the dashboard — roughly 3.0% on mobile money and
      3.2–4.8% on local cards, excluding VAT. Model it into the fundraising
      targets; the donor's UGX 50,000 is not UGX 50,000 in the bank.

Not implemented, by decision, and worth a conversation before launch: recurring
giving, payouts/disbursements (which additionally need IP whitelisting and the
API transfers toggle), refunds from wp-admin (do them in the Flutterwave
dashboard), and split payments to subaccounts.

---

## 8. Donor data

Donation records hold names, emails and phone numbers. They are a private post
type, not creatable through the UI, gated on `manage_options` (filterable via
`bgcf_donations_capability`) and excluded from search and REST. Gateway logs
carry references and provider messages only — never keys, never donor contact
details, never card data. The site never sees a card number or a mobile money
PIN: those are entered on Flutterwave's own surfaces.

---

## 9. Reference

- Uganda mobile money: https://developer.flutterwave.com/docs/uganda
- Full doc index: https://developer.flutterwave.com/llms.txt (append `.md` to any
  doc URL for the markdown version)
- Status page: https://status.flutterwave.com
- Uganda support: +256 31 5100400
