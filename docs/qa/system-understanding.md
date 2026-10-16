# System Understanding

**Audit date:** 2026-09-24 · **Method:** static inspection + live reproduction against a containerised WordPress 7.0.2 / PHP 8.3 / MariaDB 11 instance at `http://localhost:8088`.

## 1. What this repository is

A **single custom WordPress theme** (`theme/`, text domain `bgcf`) for an NGO in Uganda, plus its build toolchain. It is not an application with its own server, router or ORM — WordPress supplies all of that. The theme's own code is ~9,400 lines of PHP and ~6,000 lines of SCSS/JS.

It is **not** a brochure theme: it contains a complete **Flutterwave v3 payment integration** (~2,900 LOC across four modules) that takes real money. That subsystem carries essentially all of the project's risk and received the deepest scrutiny in this audit.

## 2. Technology stack (detected, not assumed)

| Layer | Detected | Evidence |
|---|---|---|
| Runtime | PHP 8.0+ (`testVersion 8.0-`), WordPress >= 6.5 | `phpcs.xml.dist` |
| CMS | WordPress (tested against 7.0.2) | container |
| Build | Node >= 20, npm, `sass`, `esbuild`, `postcss`/autoprefixer | `package.json` |
| Lint | `phpcs` (WordPress + PHPCompatibilityWP), `eslint` v9 flat config, `stylelint` + standard-scss | `phpcs.xml.dist`, `eslint.config.mjs`, `.stylelintrc.json` |
| Front-end libs | Bootstrap 5 (grid/utilities only), GSAP + ScrollTrigger, Swiper 11 — all self-hosted via `build/copy-vendor.mjs` | `theme/assets/{js,css}/vendor/` |
| Data store | MySQL/MariaDB via WordPress `$wpdb`; no custom tables | no `dbDelta` / `CREATE TABLE` anywhere |
| Payments | Flutterwave API **v3** | `BGCF_FLW_API_BASE` |
| Test framework | **none detected** | no PHPUnit, Playwright, Jest, wp-env |
| CI | **none detected** | no `.github/`, no CI config of any kind |
| VCS | **none detected** | `git rev-parse` returns *not a git repository* |

Declared-but-absent stack items from `CLAUDE.md`: ACF Pro, GiveWP, Contact Form 7, WP Mail SMTP, RankMath, Wordfence, Polylang, Google Maps. None are present or vendored; all are runtime plugin dependencies the theme guards for with `function_exists()` / `class_exists()` checks.

> `CLAUDE.md` names **GiveWP** as the donation system. The code implements a **bespoke Flutterwave integration instead**. The code is the more recent decision (`@since 1.1.0`, documented at length in `PAYMENTS.md`), but the two documents contradict each other — see GAP-D1.

## 3. Module map and load order

`functions.php` is a thin bootstrap that loads 14 modules from `inc/` in a documented dependency order:

```
setup -> template-tags -> security -> cpt -> acf -> blocks -> enqueue -> seo -> ajax
      -> payments -> donations -> payments-routes -> payments-admin -> admin
```

The payment subsystem is cleanly layered and the separation is genuinely enforced (verified by grep — nothing outside `payments.php` reads a secret key or calls the Flutterwave API):

| Module | LOC | Responsibility |
|---|---|---|
| `inc/payments.php` | 613 | Flutterwave HTTP client. Credentials, mode detection, charge/checkout/verify calls, webhook signature, MSISDN normalisation. Knows nothing about donations. |
| `inc/donations.php` | 842 | What a donation *is*. Settings, CPT, status state machine, the verification gate, fulfilment, receipts. The only writer of donation status. |
| `inc/payments-routes.php` | 844 | The public surface: 2 AJAX actions, 1 REST webhook, 1 return-URL handler, 1 cron job, 1 shortcode. |
| `inc/payments-admin.php` | 639 | wp-admin settings and donation list screens. |

## 4. Data model

Nine custom post types. Eight public content types in `inc/cpt.php`: `bgcf_program`, `bgcf_event`, `bgcf_campaign`, `bgcf_story`, `bgcf_team`, `bgcf_partner`, `bgcf_testimonial`, `bgcf_report`.

`bgcf_donation` — **private financial record**, one row per attempted donation. `public => false`, `show_in_rest => false`, `publicly_queryable => false`, `exclude_from_search => true`, `create_posts => do_not_allow`, all capabilities mapped to `manage_options` (filterable). *Verified publicly unreachable — see `defect-register.md`, "Verified correct".*

State is held entirely in `wp_postmeta` under a `_bgcf_` prefix. There are **no custom tables, no foreign keys, no unique constraints and no transactions** anywhere in the codebase. This is idiomatic WordPress but it is the direct root cause of DEF-004 and DEF-010.

### Donation state machine

```
          (record created BEFORE the gateway is called)
                        pending
             +------------+------------+--------------+
             v            v            v              v
        successful     failed      abandoned    pending+mismatch
         (TERMINAL)                             (parked; re-queried
                                                 every 15 min forever)
```

`bgcf_donation_set_status()` enforces terminal stickiness: once `successful`, no later signal can move it backwards. Verified by code reading — correct and important.

## 5. The money flow (the critical path)

```
 donor --POST--> admin-ajax.php?action=bgcf_donation_start
                   | guard: nonce -> honeypot -> rate limit -> gateway-configured
                   | sanitize -> validate (amount/email/method/phone/network)
                   | bgcf_donation_create()   <- DB row written BEFORE the charge
                   v
              Flutterwave  POST /v3/charges?type=mobile_money_uganda
                        or POST /v3/payments  (hosted checkout, card)
                   |
                   v  donor approves on handset / on Flutterwave's page
      +------------+------------------------+----------------------+
      |            |                        |                      |
  WEBHOOK     RETURN URL              STATUS POLL              CRON (15 min)
 verif-hash   ?bgcf-payment-return   ajax donation_status   bgcf_donations_reconcile
      +------------+------------------------+----------------------+
                   |  all four funnel into the SAME gate:
                   v
         GET /v3/transactions/{id}/verify    (server-side, authoritative)
                   v
         bgcf_donation_apply_verification()
           1. provider says "successful"
           2. tx_ref === the ref WE generated for THIS donation
           3. currency matches
           4. amount >= amount we asked for
                   v
         bgcf_donation_mark_successful()   <- receipt + admin notice, "once"
```

**This architecture is correct.** Value is never granted from a browser redirect or a webhook body; four independent signals all re-verify server-side; the tx_ref binding in check 2 defeats cross-donation replay. The defects found are in the *implementation* of the idempotency guarantee (DEF-004) and in operability (DEF-005) — not in the design.

## 6. Authentication and authorization

The theme adds **no authentication of its own**. It relies on:

- **WordPress sessions and capabilities** for wp-admin. Donation records gated by `bgcf_donations_capability()` -> `manage_options`.
- **WordPress nonces** for every AJAX endpoint, via the mandatory `bgcf_verify_request()` gate in `inc/security.php`.
- **Shared-secret header** (`verif-hash`, compared with `hash_equals`) for the Flutterwave webhook — the only authentication on that route, correctly so, since Flutterwave's egress IPs are dynamic.
- **Opaque 24-character tokens** (`_bgcf_public_token`) for donor-facing status lookups, so a transaction reference never becomes a lookup key.

There is **no user registration, login, password reset, MFA, OTP, JWT or refresh-token flow in this codebase.** Audit phases 5 and much of 6 therefore have almost no theme-owned surface; those findings are recorded as "not applicable — platform-owned" rather than as passes.

## 7. Main user journeys

| # | Journey | State |
|---|---|---|
| J1 | Browse site: page / post / CPT archive / single / search / 404 | **Works.** 24 routes verified 200/404, zero PHP errors. |
| J2 | Give by mobile money (MTN / Airtel) | **Code complete, UNVERIFIED end-to-end** — needs real sandbox credentials. Validation layer verified live. |
| J3 | Give by card (hosted checkout) | As J2. |
| J4 | See donation outcome on a thank-you page | Code complete; depends on an admin assigning `thank_you_page` and placing `[bgcf_donation_result]`. |
| J5 | Finance team reconciles in wp-admin | Code complete (`payments-admin.php`). |
| J6 | Editor builds the homepage from blocks | **BROKEN — does not exist.** DEF-002. |
| J7 | Visitor subscribes to the newsletter | **BROKEN — HTTP 501.** DEF-003. |
| J8 | Visitor applies to volunteer | **BROKEN — HTTP 501.** DEF-003. |
| J9 | Visitor loads more gallery items | **BROKEN — HTTP 501.** DEF-003. |

## 8. External integrations

| Integration | Direction | Auth | Failure handling |
|---|---|---|---|
| Flutterwave v3 | outbound HTTPS + inbound webhook | Bearer secret key / `verif-hash` | Every failure path returns `WP_Error`; 45 s timeout deliberately above the provider's ~28 s ceiling; timeouts trigger requery, never blind retry. **Good.** |
| `wp_mail` (receipts, admin notices) | outbound | host MTA / WP Mail SMTP | Return value discarded by the caller; a bounced receipt is invisible. DEF-005. |
| WordPress cron | internal | — | Relies on `wp-cron.php` being hit by traffic; no `DISABLE_WP_CRON` + system-cron guidance exists. CFG-04. |

## 9. Deployment architecture

**Undefined.** There is no Dockerfile, no project-authored compose file, no `.env.example`, no provisioning script, and no `DEPLOYMENT.md` — despite `inc/security.php` referencing `DEPLOYMENT.md` three times as the home of mandatory server-level hardening (directory listing, HSTS, CSP).

The `docker-compose.yml` at the repo root was written by this audit to obtain a running instance. It is a **local QA harness, not a deployment artifact**.

## 10. Known assumptions

1. Currency is UGX; the method matrix (`card` + `mobilemoneyuganda` only) follows from that. Hard-coded in several places by deliberate, documented design.
2. UGX has no minor units; amounts are whole integers throughout. Consistent everywhere checked — **no float-money defects found**.
3. Flutterwave API v3 will not be deprecated. Rationale documented in `inc/payments.php`.
4. ACF **Pro** will be installed in production. Without it no `bgcf/*` block registers at all (`inc/blocks.php:201`).

## 11. Unknowns (could not be resolved in this environment)

- Real Flutterwave sandbox behaviour: charge, webhook delivery and verify responses were never exercised against the live API (no credentials). J2 and J3 remain **UNVERIFIED** end-to-end.
- Production cache configuration (WP Rocket / Cloudflare TTLs) — determines whether DEF-007 fires.
- Whether a `DEPLOYMENT.md`, an ACF field-group export, or an image manifest exists outside this repository.

## 12. Risk areas, ranked

1. **Deployment process** — no VCS, no CI, no rollback, for code that moves money.
2. **Content layer** — the CMS half of the product is scaffolding.
3. **Concurrency on fulfilment** — the one place the code promises more than the platform delivers.
4. **Operability** — a payment failure in production is currently invisible.
5. **Data protection** — donor PII accumulates with no retention or erasure path.
