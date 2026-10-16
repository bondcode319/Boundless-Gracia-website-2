# Test Plan

## 1. Scope

**In scope:** the `bgcf` theme — payment subsystem, AJAX endpoints, CPT registration, template rendering, security helpers, SEO output, build pipeline, accessibility baseline.

**Out of scope:** WordPress core, third-party plugins, Flutterwave's own correctness, host/server configuration, and the design system's visual fidelity (a separate design review).

## 2. Strategy

The codebase has **zero** automated tests today. The strategy is not "achieve N% coverage" — it is **cover the invariants whose violation costs money or trust, and nothing else, first.** Coverage percentage is a poor proxy here because the highest-risk code is a handful of pure functions.

Four tiers, in build order:

1. **Unit (PHPUnit + wp-env)** — the verification gate, the status machine, MSISDN normalisation, payload hashing, amount bounds. Pure functions; no network.
2. **Integration (PHPUnit + WP test case)** — webhook route, return URL, cron job, AJAX handlers with a mocked HTTP layer (`pre_http_request` filter).
3. **Concurrency** — the DEF-004 race. Requires real parallel processes; cannot be faked with sequential calls.
4. **E2E (Playwright)** — donation form against the **Flutterwave sandbox**, plus accessibility (axe) and Lighthouse budgets.

Mocking rule: mock **only** `wp_remote_request` via `pre_http_request`. Never mock our own functions — the bugs found in this audit (DEF-004, DEF-009, DEF-010) all live in the seams between our code and the platform, exactly where over-mocking hides them.

## 3. Environments

| Env | Purpose | Data |
|---|---|---|
| Local (`docker-compose.yml`) | Unit + integration | Synthetic fixtures |
| CI | Every push | Fresh install per run |
| Sandbox | E2E money path | Flutterwave **test** keys (`FLWSECK_TEST-`) |
| Staging | Pre-release smoke | Sandbox keys, production-like cache |

**Never** run the suite against production. Flutterwave test keys only — `bgcf_flw_mode()` derives mode from the key prefix, so a test key cannot masquerade as live.

## 4. Test data

- Donations at boundaries: `min-1`, `min`, `min+1`, `max-1`, `max`, `max+1`, `0`, negative, decimal, non-numeric.
- Phones: `0772123456`, `+256 772 123 456`, `256-772-123456`, `772123456`, `254772123456` (Kenyan), `0999123456` (unknown prefix), empty, 40 digits.
- Emails: valid, no `@`, unicode local part, 320 chars, empty.
- Names/messages: empty, 10 KB, `<script>alert(1)</script>`, RTL text, emoji, null bytes.
- Webhook bodies: valid, missing `data`, missing `tx_ref`, unknown `tx_ref`, malformed JSON, 1 MB body, replayed identical body.

---

## 5. Test cases

### TC-001 — Cross-donation replay is rejected *(highest value in the suite)*

**Precondition:** donation A = UGX 1,000 (`tx_ref` A); donation B = UGX 1,000,000 (`tx_ref` B).
**Input:** verify payload for a genuinely successful UGX 1,000 payment carrying `tx_ref` **A**, applied to donation **B**.
**Steps:** `bgcf_donation_apply_verification( B_id, $payload_A )`.
**Expected:** returns `pending`; `_bgcf_mismatch` = 1; B is **not** `successful`; no receipt sent; a mismatch is logged.
**Related:** DEF-013 mutation #1. *This single test protects the system's core financial invariant.*

### TC-002 — Underpayment is rejected
Expected UGX 100,000; provider reports `successful` at 50,000. **Expected:** not fulfilled, mismatch flagged. *(Mutation: `<` -> `<=`.)*

### TC-003 — Currency mismatch is rejected
Expected UGX; provider reports `successful` in KES at the same numeric amount. **Expected:** not fulfilled, mismatch flagged.

### TC-004 — Overpayment is accepted
Expected 50,000; provider reports 60,000 successful. **Expected:** `successful`, one receipt. Documents the deliberate `>=` rule.

### TC-005 — `successful` is terminal
Donation is `successful`; apply a `failed` verification. **Expected:** stays `successful`; `bgcf_donation_set_status()` returns `false`.

### TC-006 — Webhook rejects a bad signature
Missing / wrong / empty / correct-but-truncated `verif-hash`. **Expected:** 401 for all but the exact match. *(Already verified manually — automate it.)*

### TC-007 — Webhook body is never trusted
Valid signature, body claims `status: successful` and `amount: 9999999`, while the verify endpoint returns `pending`. **Expected:** donation stays `pending`; no receipt. *Proves the body-is-a-trigger rule.*

### TC-008 — Fulfilment is idempotent under concurrency *(DEF-004)*
**Precondition:** one donation, verified successful.
**Steps:** N=10 OS processes call `bgcf_donation_mark_successful()` after a shared start barrier.
**Expected:** exactly one `bgcf_donation_completed` firing, exactly one `wp_mail` call, exactly one `_bgcf_fulfilled` row.
**Expected today:** **FAILS.** Write it first; it is the acceptance test for the DEF-004 fix.

### TC-009 — Sequential idempotency
Call `bgcf_donation_mark_successful()` three times in a row. **Expected:** one receipt. *(Passes today — the guard works without contention. Keep it, so a fix for TC-008 cannot regress the easy case.)*

### TC-010 — MSISDN normalisation table
All phone fixtures from §4 -> expected MSISDN or `''`. **Expected:** `0772…`, `+256 772…`, `256-772-…`, `772…` all normalise to `256772123456`; Kenyan and malformed return `''`.

### TC-011 — Validation matrix
Every fixture from §4 against `bgcf_donation_start`; assert HTTP status **and** `data.code`. *(The manual run of this in the audit passed 11/11 — automate it to lock it down.)*

### TC-012 — Nonce is mandatory
Every AJAX action with no / wrong / expired nonce. **Expected:** 403 `bgcf_invalid_nonce`, and **no side effect** (no donation row, no outbound call). *(Mutation: remove `bgcf_ajax_guard()`.)*

### TC-013 — Donation records are not publicly reachable
`/wp-json/wp/v2/bgcf_donation`, `/?p=<id>`, `/?post_type=bgcf_donation`, site search, and an unauthenticated `admin-ajax` attempt. **Expected:** 404 / absent in all cases.

### TC-014 — Array-typed parameters *(DEF-009)*
`token[]=x` and `token[a][b]=1` on every public endpoint. **Expected:** documented error code and **zero** PHP notices in the error log.

### TC-015 — Rate limiter counts only gateway-reaching attempts *(DEF-015)*
11 submissions failing validation, then 1 valid. **Expected:** the valid one succeeds. **Expected today:** FAILS.

### TC-016 — Rate limiter still stops abuse
13 valid-shaped submissions from one IP. **Expected:** 429 on the 13th. *(Passes today — keep it so the TC-015 fix cannot remove the brake.)*

### TC-017 — Unconfigured gateway fails safe
No Flutterwave constants. **Expected:** 503 `unavailable`, and **no donation record created**. *(Verified manually; automate.)*

### TC-018 — Gateway errors do not leak configuration *(DEF-008)*
Force an authorization-class provider error. **Expected:** generic donor-safe message in the response; raw provider text present only in the log.

### TC-019 — Reconciliation abandons stale records
Pending donation aged 25 h, provider still `pending`. **Expected:** `abandoned`. Also: aged 1 min -> skipped; `_bgcf_created_gmt` = 0 -> **currently loops forever** (DEF-019).

### TC-020 — Cron never resurrects a terminal record
`successful` and `failed` donations are not returned by the reconcile query and are not mutated.

### TC-021 — Return URL cannot be forged
`?bgcf-payment-return=1&tx_ref=<valid>&status=successful` while the provider reports `pending`. **Expected:** donation stays `pending`; the query string changes nothing.

### TC-022 — Receipt contents
Successful sandbox donation. **Expected:** one email containing the reference and formatted amount; in test mode it carries the `*** TEST MODE ***` banner; no card or phone data anywhere in the body.

### TC-023 — Nonce recovery on a cached page *(DEF-007)*
Serve the form with a dead nonce. **Expected (after fix):** the client fetches a fresh nonce and retries once, transparently. **Expected today:** FAILS — unrecoverable loop.

### TC-024 — Accessibility regression
axe-core against home, a page, a post, a CPT archive, a CPT single, search, 404 and the donation form. **Expected:** zero serious/critical violations; the baseline verified in this audit (one `h1`, skip link, `<main>`, `lang`, labelled navs, no `alt`-less images) holds.

### TC-025 — Keyboard-only donation
Complete the form with keyboard alone. **Expected:** visible focus throughout, logical order, errors announced to screen readers (`announce()` exists in the JS — assert its live region).

### TC-026 — Performance budget
Lighthouse on home and a CPT single. **Expected:** performance >= 95, a11y >= 95, best-practices >= 95, SEO >= 95, per `CLAUDE.md`.

### TC-027 — Build integrity
`npm ci && npm run lint && npm run build` from a clean tree. **Expected:** zero errors from eslint, stylelint **and phpcs**. **Expected today:** phpcs FAILS with 50 errors (DEF-014).

### TC-028 — Structured data *(DEF-006)*
Per template type. **Expected (after fix):** exactly one parseable `ld+json` block with the right `@type`, and **none** when RankMath is active (`bgcf_seo_is_delegated()`).

### TC-029 — Network failure modes
Via `pre_http_request`: timeout, DNS failure, connection reset, HTTP 500/502/503/429, truncated body, non-JSON body. **Expected:** every case returns `WP_Error`, the donor sees a readable message, the record stays `pending`, and **no money-moving call is blindly retried.**

### TC-030 — Provider returns malformed success
HTTP 200 with `status: "success"` but no `data`, or `data` missing `tx_ref` / `amount`. **Expected:** treated as not-successful; never fulfilled.

---

## 6. Prioritisation

| Wave | Cases | Why |
|---|---|---|
| **1 — before any further feature work** | TC-001..007, TC-012, TC-017 | The financial invariants and the auth boundary. Cheap, pure, high value. |
| **2 — with the DEF-004 fix** | TC-008, TC-009 | TC-008 is the fix's acceptance criterion |
| **3 — with the endpoint hardening** | TC-011, TC-014, TC-015, TC-016, TC-018 | Locks in behaviour that is already good |
| **4 — with reconciliation work** | TC-019..021, TC-029, TC-030 | Failure modes |
| **5 — pre-launch** | TC-022..028 | E2E, a11y, performance, build |

## 7. Exit criteria

- Waves 1–4 green in CI.
- TC-008 green (proves DEF-004 fixed).
- TC-027 green (proves DEF-014 fixed and enforced).
- One full sandbox donation completed end to end on **both** rails, with webhook delivery observed and exactly one receipt received.
