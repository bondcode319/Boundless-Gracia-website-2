# System Inventory

Feature-by-feature map through every layer. Endpoint detail lives in `api-inventory.md`; this document records the full vertical for each feature and where it breaks.

```
USER -> CLIENT -> API -> BUSINESS LOGIC -> DATABASE -> EXTERNAL -> JOBS -> NOTIFICATIONS -> ADMIN
```

---

## F1 — Donate by mobile money (MTN / Airtel)

| Layer | Detail |
|---|---|
| **UI entry** | `partials/global/donation-form.php` (299 LOC); rendered by the `bgcf/donation-banner` block or a direct include |
| **Client logic** | `assets/js/src/donation-form.js` — method toggle, `reportValidity()`, busy-lock, `FormData` POST, `sessionStorage` token stash |
| **API** | `POST admin-ajax.php?action=bgcf_donation_start` |
| **Auth / authz** | Nonce `bgcf_ajax`; public — no capability |
| **Handler** | `bgcf_ajax_donation_start()` |
| **Service layer** | `bgcf_donation_create()` -> `bgcf_flw_charge_mobile_money()` -> `bgcf_flw_request()` |
| **DB entities** | `bgcf_donation` post + 16 `_bgcf_*` meta keys |
| **Queries** | 1 insert + 16 meta writes; later 1 meta-query lookup per signal |
| **External** | `POST /v3/charges?type=mobile_money_uganda` |
| **Side effects** | Donation row written **before** the charge; `_bgcf_flw_id` stored from the response |
| **Notifications** | None at this stage (correct — nothing has been paid yet) |
| **Logging** | `bgcf_flw_log()` on transport/API error — **off in production** (DEF-005) |
| **Tests** | **None** |
| **State** | Validation layer verified live. End-to-end **UNVERIFIED** — no sandbox credentials (GAP-I4) |

## F2 — Donate by card (hosted checkout)

As F1, differing at: `bgcf_flw_create_checkout()` -> `POST /v3/payments`, with a `payload_hash` = `SHA256(amount + currency + email + tx_ref + SHA256(secret))` binding the amount so it cannot be edited in the browser. Redirect comes from `data.link` rather than `meta.authorization.redirect`; `bgcf_donation_extract_redirect()` normalises all three documented response shapes. Same test and verification state as F1.

## F3 — Confirm a payment (the critical path)

Four independent triggers, one gate.

| Trigger | Entry | Auth | Verified |
|---|---|---|---|
| Webhook | `POST /wp-json/bgcf/v1/flutterwave/webhook` | `verif-hash` + `hash_equals` | **Live: 401/401/200/200/404 all correct** |
| Return URL | `GET /?bgcf-payment-return=1&tx_ref=...` | none by design | Code read; correct |
| Status poll | `POST admin-ajax.php?action=bgcf_donation_status` | nonce + opaque token | **Live: 404 on unknown token** |
| Cron | `bgcf_donations_reconcile`, every 15 min | internal | Code read |

All four call `bgcf_donation_requery()` -> `bgcf_flw_verify_transaction()` or `..._by_reference()` -> `bgcf_donation_apply_verification()`.

**Gate:** status `successful` **and** `tx_ref` matches this record **and** currency matches **and** `amount >= expected`. Any mismatch on a `successful` payment sets `_bgcf_mismatch`, parks at `pending`, logs, and never fulfils.

**Fulfilment:** `bgcf_donation_mark_successful()` -> `add_post_meta(..., unique)` -> receipt + admin notice + `do_action('bgcf_donation_completed')`.

**Defects:** DEF-004 (the unique guard is not atomic), DEF-018 (mismatches loop and never alert).

## F4 — Donor sees the outcome

`bgcf_donation_result_shortcode()` -> `partials/global/donation-result.php`, keyed on `?donation=<opaque token>`. Status read from the record, never the query string. JS polls every 5 s, 24 times, then shows a timeout message. **Depends on an admin setting `thank_you_page` and placing the shortcode** — default `0` silently sends donors to the home page (CFG §2).

## F5 — Finance reconciliation (wp-admin)

`inc/payments-admin.php` (639 LOC): settings screen (currency, presets, min/max, methods, thank-you page, receipt/notify toggles) and a donation list with status, amount, reference and mode columns. Gated on `manage_options` via `bgcf_donations_capability()`. **No audit log of admin changes** (GAP-M6). Not exercised in this audit beyond confirming it loads without error.

## F6 — Browse content (8 public CPTs)

| Layer | Detail |
|---|---|
| UI | `archive.php`, `single.php`, `page.php`, `search.php`, `404.php`, `index.php`, `front-page.php` + `partials/content/*` |
| API | None — server-rendered |
| Backend | `inc/cpt.php` (682 LOC) registers all 8 with labels, rewrites, taxonomies |
| DB | `wp_posts` / `wp_postmeta` / `wp_term_*` |
| Tests | None |
| **State** | **Works.** Verified live: all 8 CPT archives and 4 singles return 200 with zero PHP errors |

Per-CPT detail templates (`single-bgcf_program.php` etc.) do not exist; everything falls through to `single.php`. Acceptable given the block-composition model — but that model is itself unbuilt (DEF-002).

## F7 — Newsletter · F8 — Volunteer · F9 — Gallery load-more

| Layer | F7 | F8 | F9 |
|---|---|---|---|
| UI | block is a stub | **none** | block is a stub |
| API | registered | registered | registered |
| Handler | `bgcf_ajax_not_implemented()` | same | same |
| Service / DB / external | **none** | **none** | **none** |
| Live result | **501** | **501** | **501** |

F8 additionally specifies a résumé upload. No upload handling exists anywhere in the theme. **DEF-003.**

## F10 — SEO output

`inc/seo.php` (307 LOC). Meta description, canonical, OG, Twitter cards, sitemap post-type filtering, title separator — **all working, verified live**. `bgcf_seo_is_delegated()` correctly steps aside for RankMath. Schema renderer is complete and correct and **has no producers**, so zero JSON-LD ships (DEF-006).

## F11 — Security baseline

`inc/security.php` (292 LOC). Head cleaning, generator removal, XML-RPC off, pingback header removed, five security headers, REST user-endpoint restriction, `?author=` block, generic login errors, filename normalisation, SVG upload block, and `bgcf_verify_request()` — the mandatory gate used by every handler. **All verified live** except the upload paths, which have nothing to exercise them. Defects: DEF-016, DEF-017; gaps CFG-01, CFG-02.

---

## Cross-cutting inventory

### Every state-changing entry point

| Entry | Nonce | Capability | Rate limit | Honeypot |
|---|---|---|---|---|
| `bgcf_donation_start` | yes | public | **yes** | **yes** |
| `bgcf_donation_status` | yes | public | **no** (DEF-012) | n/a |
| `bgcf_newsletter_subscribe` | yes | public | no | not implemented |
| `bgcf_volunteer_submit` | yes | public | no | not implemented |
| `bgcf_gallery_load_more` | yes | public | no | n/a |
| Flutterwave webhook | n/a | n/a — shared secret | **no** | n/a |
| Return URL | n/a by design | n/a | no | n/a |

Every AJAX handler routes through `bgcf_ajax_guard()` -> `bgcf_verify_request()`. **No handler hand-rolls its own nonce check** — verified by grepping for `wp_verify_nonce` outside `security.php`: zero hits. The mandatory-gate rule is actually honoured.

### Authorization matrix

| Resource | Guest | Subscriber | Editor | Admin |
|---|---|---|---|---|
| Public content | read | read | read+edit | full |
| Start a donation | **yes** (by design) | yes | yes | yes |
| Poll donation status (with token) | **yes** (by design) | yes | yes | yes |
| Read donation records | no | no | **no** | **yes** |
| Edit donation records | no | no | **no** | **yes** |
| Create donation records | **no one** — `create_posts => do_not_allow` | | | |
| Donation settings | no | no | **no** | yes |
| Theme/plugin file editor | — | — | — | **disabled** |

`map_meta_cap => false` with every capability explicitly mapped to `manage_options`. Enforcement is server-side in `register_post_type()`, not UI hiding. **Verified:** REST 404, direct `?p=` 404, excluded from search. No IDOR surface found — the only donor-facing identifier is a 24-character random token, and knowing a transaction reference reveals nothing.

### Business invariants

| Invariant | Enforced | Where | Tested |
|---|---|---|---|
| A charge cannot exist without a donation row | **yes** | row written before the gateway call | no |
| `tx_ref` is always server-generated | **yes** | `bgcf_flw_generate_tx_ref()` | no |
| A payment can only satisfy the record whose ref it carries | **yes** | check 2 of the gate | **no — and this is the highest-value missing test** |
| `successful` is terminal | **yes** | `bgcf_donation_set_status()` | no |
| A donor receives exactly one receipt | **partially** | non-atomic guard | no (DEF-004) |
| Amount received >= amount requested | **yes** | check 4 | no |
| Currency must match | **yes** | check 3 | no |
| Amount is a positive integer within min/max | **yes** (coerces rather than rejects) | `absint` + bounds | no (DEF-020) |
| Donor PII never appears in logs | **yes** | `bgcf_flw_log()` contract | no |
| Donor PII never appears in API responses | **yes** | status response carries status/label/amount/url only | no |
