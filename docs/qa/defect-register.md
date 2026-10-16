# Defect Register

Sorted CRITICAL -> INFO. Every finding carries evidence obtained in this environment. Findings that could not be proven here are labelled **UNVERIFIED ASSUMPTION** and are not presented as defects.

**Summary:** 1 critical · 5 high · 9 medium · 6 low · 2 info.

---

# CRITICAL

## DEF-001 — No version control, no CI, no rollback path for money-handling code

| | |
|---|---|
| **Severity** | CRITICAL · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Deployment / CI-CD / process |
| **Component** | Repository root |

**Requirement.** `CLAUDE.md` defines 8 gated delivery phases and `ARCHITECTURE.md` §13 defines a build contract. Both presuppose a versioned, reviewable, revertible codebase.

**Observed.**
```
$ git rev-parse --is-inside-work-tree
fatal: not a git repository (or any of the parent directories): .git
$ ls -a .github
ls: cannot access '.github': No such file or directory
```
A `.gitignore` exists (1,103 bytes) but there is no repository for it to govern.

**Expected.** A git repository with CI that runs `npm run lint` (eslint + stylelint), `npm run lint:php` (phpcs), and `npm run build` on every change, gating merges.

**Technical explanation.** Every quality control this project defines is opt-in and local. `package.json` declares `lint`, `lint:php` and `prepare-release`, and nothing executes them. The proof: `phpcs` currently reports **50 errors and 151 warnings** against a standard `CLAUDE.md` labels "Non-Negotiable" (DEF-014). That drift is only possible because nothing enforces the gate.

**Impact.** For a codebase that charges cards and mobile money: no atomic deploy, no revert when a release breaks the donation form, no diff review on changes to verification logic, no audit trail of who changed a payment rule. A bad deploy is repaired by hand-editing files on a live server.

**Affected users.** Every donor during any incident; the finance team, who lose the ability to reconstruct what the code did on a given date.

**Root cause (5 whys).** Deploys are not revertible -> no repository -> project was authored as a filesystem deliverable, not a tracked codebase -> phase gates were defined as human review steps, not mechanical ones -> **process gap: the delivery model never required a VCS to exist.**

**Recommended fix.** `git init`; commit the current tree as a baseline; add a CI workflow running lint + build; make green CI the merge gate. No code change.

**Regression test.** CI itself is the regression test: a PR that reintroduces a phpcs error must fail.

**Verification criteria.** A CI run visible on a pull request, red on a deliberately introduced lint error, green after revert.

**Dependencies.** None. Do this before any other remediation so the rest is reviewable.

---

# HIGH

## DEF-002 — The content layer does not exist: 10 of 11 blocks are scaffolds and there are zero ACF field groups

| | |
|---|---|
| **Severity** | HIGH · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Missing functionality |
| **Component** | `theme/partials/blocks/*`, `theme/acf-json/`, `inc/blocks.php`, `front-page.php` |

**Requirement.** `CLAUDE.md`: eleven custom Gutenberg blocks; "Everything editable in wp-admin via ACF — zero hardcoded content strings in templates." `front-page.php` states the homepage is "composed entirely of `bgcf/*` blocks placed on the static front page in the editor."

**Observed.** Executable (non-comment, non-blank) line counts per block partial:

```
block-cta.php               13      block-partners.php          13
block-donation-banner.php   64      block-program-cards.php     13
block-faq.php               13      block-statistics.php        13
block-gallery.php           13      block-testimonials.php      13
block-hero.php              13      block-timeline.php          13
block-newsletter.php        13
```

Those 13 lines are identical in shape across all ten: open a section wrapper, call `bgcf_empty_state( 'Hero: add a background image, headline and calls to action...' )`, close it. `block-hero.php` read in full confirms it. Only the donation banner is implemented.

```
$ ls theme/acf-json | wc -l
0
```

And `inc/blocks.php:201`: `if ( ! function_exists( 'acf_register_block_type' ) ) { return; }` — without ACF **Pro**, no `bgcf/*` block registers at all. Confirmed live: with ACF absent, the editor offers none of them.

**Expected.** Eleven rendering blocks, each backed by a version-controlled ACF field group in `acf-json/`.

**Impact.** The CMS half of the product is absent. An editor cannot build the homepage, a program page, a campaign page or a story page. `CLAUDE.md` Phases 3 and 5 are unbuilt while the surrounding scaffolding (registration, render dispatch, template hierarchy, styleguide) is complete — which makes the gap easy to mistake for a configuration problem.

**Affected users.** Every content editor; every visitor, who sees placeholder empty-states.

**Root cause.** Work stopped after the architecture phase. The block registry, render dispatcher and field-reading helpers (`bgcf_field()`, `bgcf_option()`) are all finished; only the per-block markup and the field definitions are missing. This is *unstarted work*, not *broken work* — which is why the site runs cleanly with zero PHP errors.

**Recommended fix.** Phased, one block at a time: define the ACF group in the UI, export to `acf-json/`, then write the partial's markup against `bgcf_field()`. Start with Hero, Statistics, Program Cards, CTA (homepage-critical).

**Regression test.** Per block: register the field group, place the block on a draft page, assert the rendered HTML contains the field values and not the empty-state string.

**Verification criteria.** A homepage built entirely in the editor, with no `bgcf_empty_state()` output anywhere in the response body.

**Dependencies.** Requires an **ACF Pro licence** — currently not present in the repo or the environment.

---

## DEF-003 — Three advertised AJAX endpoints are permanent stubs returning HTTP 501

| | |
|---|---|
| **Severity** | HIGH · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Missing API / broken end-to-end journey |
| **Component** | `inc/ajax.php:124,147,170` |

**Requirement.** `CLAUDE.md` lists a Newsletter block and a Gallery block among the deliverables, and Phase 6 requires "volunteer forms, emails, admin workflow." `inc/ajax.php` documents a request/response contract for each and calls those contracts "a public contract."

**Observed** (live, with a valid logged-out nonce):
```
POST admin-ajax.php action=bgcf_newsletter_subscribe
  -> HTTP 501 {"success":false,"data":{"code":"not_implemented",...}}
```
All three behave identically. The handlers are two lines: `bgcf_ajax_guard(); bgcf_ajax_not_implemented( ... );`

**Expected.** Newsletter -> `{ message }`; volunteer -> `{ message, reference }` plus a stored `bgcf_volunteer` record and a résumé upload; gallery -> `{ html, hasMore, paged }`.

**Technical explanation.** The endpoints are registered on both `wp_ajax_` and `wp_ajax_nopriv_`, so they are live and publicly reachable on every install. The comments describe them as Phase 3/4/6 work that was never done. Note the volunteer endpoint is specified to accept a file upload; `grep` for `$_FILES`, `wp_handle_upload` and `move_uploaded_file` across `theme/` returns **nothing** — the entire upload-security surface described in the audit brief's Phase 13 has no implementation.

**Impact.** Three user journeys (J7, J8, J9) fail. A volunteer application form is a core NGO function and a stated Phase 6 deliverable.

**Root cause.** Same as DEF-002 — scaffolding completed, implementation not started. The stubs were a deliberate, documented choice so the front end could be error-handled against real response shapes; the follow-up never landed.

**Recommended fix.** Implement, or remove the registrations. Leaving a live public endpoint that answers 501 is worse than not registering it: it is attack surface with no function.

**Regression test.** Per endpoint: happy path asserting the response shape *and* the resulting DB state; honeypot rejected; invalid input rejected with the documented code; for volunteer, a `.php` file renamed `.pdf` must be rejected on real MIME, not extension.

**Verification criteria.** No endpoint in the theme returns 501.

---

## DEF-004 — Fulfilment idempotency is not atomic; concurrent signals can double-fulfil a donation

| | |
|---|---|
| **Severity** | HIGH · **Confidence** HIGH · **Status** CONFIRMED (race window proven; collision frequency load-dependent) |
| **Category** | Concurrency / data integrity |
| **Component** | `inc/donations.php` — `bgcf_donation_mark_successful()` |

**Requirement.** The function's own docblock: *"Idempotency is enforced with a unique meta row rather than a status check: the webhook, the return URL and the cron job can all arrive within the same second, and `add_post_meta( ..., true )` is the cheapest guard that survives that. A donor gets one receipt, and the total is counted once."*

**Observed.** `add_post_meta( $id, '_bgcf_fulfilled', time(), true )` resolves to `add_metadata()` in WordPress core, which is a **non-transactional read-then-write**:

```php
// wp-includes/meta.php:95-103
if ( $unique && $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE meta_key = %s AND $column = %d",
        $meta_key, $object_id
    )
) ) {
    return false;
}
```

There is no lock, no transaction, and no database constraint behind it:

```
$ SHOW INDEX FROM wp_postmeta
PRIMARY   meta_id
post_id   post_id
meta_key  meta_key
```

No unique index on `(post_id, meta_key)`. Proven by executing both inserts that two racing processes would perform after both passed the `SELECT COUNT(*)` guard:

```
donation_id=45
insert1=1 insert2=1 rows_now=2
RESULT: NO DB CONSTRAINT -- duplicate fulfilment rows accepted
```

**Expected.** Exactly one fulfilment per donation under any interleaving.

**Reproduction.** Interleave two requests as `T1:SELECT(0)` -> `T2:SELECT(0)` -> `T1:INSERT` -> `T2:INSERT`. Both `add_post_meta` calls return truthy; both proceed past the guard.

**Technical explanation.** The design deliberately arranges for **three or four signals to fire for the same payment** — that redundancy is what makes the flow correct when webhooks are lost. The same redundancy makes simultaneous arrival routine rather than exotic: a mobile-money webhook and the donor's browser return commonly land within the same second, and the 15-minute cron overlaps both. The guard chosen is the one WordPress primitive that *looks* atomic and is not.

**Impact.** Per collision: a duplicate receipt email to the donor; `do_action( 'bgcf_donation_completed' )` fires twice. That hook is explicitly documented as *"the hook for anything that must happen exactly once: campaign totals, CRM sync, thank-you automation."* Any consumer built on that promise will double-count. No money is double-charged — Flutterwave is the ledger — but **reported totals diverge from banked funds**, which for a charity is a reconciliation and donor-trust problem, not a cosmetic one.

**Affected users.** Donors (duplicate receipts); finance (totals that do not reconcile); any future CRM integration.

**Production risk.** Rises with donation volume and with webhook latency. Low-traffic launch: rare. A campaign push: likely.

**Root cause (5 whys).** Totals over-count -> `bgcf_donation_completed` fires twice -> both callers passed the unique-meta guard -> the guard is SELECT-then-INSERT with no constraint -> the platform offers no atomic upsert on `wp_postmeta` and **no test ever exercised concurrent fulfilment.**

**Recommended fix (ranked).**
1. **Add the missing constraint.** `ALTER TABLE wp_postmeta ADD UNIQUE KEY bgcf_fulfil (post_id, meta_key)` is *not* safe — WordPress relies on non-unique meta site-wide. Instead: keep fulfilment state in a **purpose-built row with a real unique index**, e.g. a small custom table `bgcf_donation_fulfilment (donation_id PK)`. `INSERT` succeeds or fails atomically; the failure is the idempotency signal.
2. **Cheaper interim:** wrap fulfilment in a named lock — `SELECT GET_LOCK('bgcf_fulfil_{id}', 0)` — and no-op when the lock is not acquired.
3. **Weakest acceptable:** `wp_cache_add()` as a pre-filter. Not durable, not correct without a persistent object cache; do not rely on it alone.

Whichever is chosen, **correct the docblock** — it currently asserts a guarantee the platform does not provide, which is how the gap survived review.

**Regression test.** Fork N=10 concurrent processes calling `bgcf_donation_mark_successful()` on one donation ID after a common barrier; assert exactly one `bgcf_donation_completed` firing and exactly one `wp_mail` call.

**Verification criteria.** The concurrency test is green, and a duplicate-fulfilment attempt is observable in logs as a rejected lock, not a silent second receipt.

**Dependencies.** DEF-013 (no test harness exists to hold this test).

---

## DEF-005 — Payment failures are invisible in production

| | |
|---|---|
| **Severity** | HIGH · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Observability |
| **Component** | `inc/payments.php` — `bgcf_flw_log()`; `inc/donations.php` |

**Requirement.** The audit standard: *"If this fails in production at 2 AM, can an engineer determine what happened?"*

**Observed.** The entire payment subsystem contains exactly one logging call site:
```
$ grep -c error_log theme/inc/*.php
theme/inc/payments.php: 1     (all others: 0)
```
and it is gated:
```php
if ( ! apply_filters( 'bgcf_flw_logging_enabled', defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
    return;
}
```
`WP_DEBUG` is off in production by definition. **Default production behaviour is: log nothing.**

Additionally, both mail calls discard their result — `bgcf_donation_send_receipt()` and `bgcf_donation_notify_admin()` return `(bool) wp_mail(...)`, and `bgcf_donation_mark_successful()` ignores both return values. A receipt that `wp_mail` refuses is silent.

**Expected.** Gateway errors, signature rejections, verification mismatches and failed receipt sends are recorded in production, at a level an on-call engineer can reach.

**Technical explanation.** The logging content is well-designed — the function deliberately carries only safe metadata (path, HTTP code, reference, provider message) and never keys, phone numbers or card fields. The defect is purely the **default**: correct data, never written. There are no metrics, no health check endpoint, no admin notice for stuck records, and no audit trail of who changed a donation in wp-admin.

**Impact.** Concretely: if the `verif-hash` is mistyped during provisioning, every webhook returns 401 and **nothing anywhere reports it**. Donations still settle via the 15-minute cron, so the symptom is only "receipts are slow" — a silent degradation that can persist for weeks. A verification mismatch (the fraud signal in `bgcf_donation_apply_verification`) sets `_bgcf_mismatch` and logs — to a log that is off.

**Root cause.** Logging was scoped as a developer-debugging aid rather than an operational requirement, so it inherited `WP_DEBUG`.

**Recommended fix.**
1. Default `bgcf_flw_logging_enabled` to **true** for warning-and-above; keep verbose tracing behind `WP_DEBUG`.
2. Check the `wp_mail()` return; on false, store `_bgcf_receipt_failed` and surface it in the donations list.
3. Surface `_bgcf_mismatch` and records pending > 24 h as a wp-admin notice for `manage_options`.
4. Log every admin-initiated donation status change with the acting user ID.

**Regression test.** With `WP_DEBUG` off, force a gateway error and assert a log line is written; force `wp_mail` to return false and assert the flag is set and shown.

**Verification criteria.** A mistyped secret hash produces a visible operator signal within one cron cycle.

---

## DEF-006 — No structured data is emitted; the schema graph has no producers

| | |
|---|---|
| **Severity** | HIGH · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Missing functionality / SEO |
| **Component** | `inc/seo.php:233` — `bgcf_seo_render_schema()` |

**Requirement.** `CLAUDE.md` Phase 7: "schema". `inc/seo.php:225` states Phase 7 adds "the Organization, WebSite, BreadcrumbList, Article, Event, Person, FAQPage" nodes.

**Observed.** Live fetch of `/about/`:
```
JSON-LD schema : 0 block(s)
canonical      : true      og:title  : true
twitter:card   : true      meta descr: true
```
`bgcf_seo_render_schema()` reads `apply_filters( 'bgcf_schema_graph', array() )` and returns early when empty. A grep for `add_filter( 'bgcf_schema_graph'` across the theme returns **no producers**. The renderer is correct — including `JSON_HEX_TAG|JSON_HEX_AMP` escaping — and has nothing to render.

**Expected.** At minimum Organization + WebSite sitewide, BreadcrumbList on inner pages, Event on `bgcf_event`, Article on posts and stories.

**Impact.** No rich results, no knowledge-panel eligibility, no event listings in search — all of which materially affect discovery and donation traffic for an NGO. Meta/OG/Twitter/canonical **are** correct, so the loss is confined to structured data.

**Root cause.** The extension point was built in Phase 1; Phase 7 never filled it. Same pattern as DEF-002.

**Recommended fix.** Add a `bgcf_schema_graph` producer per context. Guard against RankMath: `bgcf_seo_is_delegated()` already exists at `inc/seo.php:36` — honour it so the two do not emit duplicate graphs.

**Regression test.** Per template type, assert exactly one `application/ld+json` block, that it parses, and that `@type` matches expectations.

**Verification criteria.** Google Rich Results Test passes for a program, an event and a post.

---

# MEDIUM

## DEF-007 — Cached-page nonce expiry can put donors in an unrecoverable retry loop

| | |
|---|---|
| **Severity** | MEDIUM (HIGH if page cache TTL > 12 h) · **Confidence** HIGH · **Status** CONFIRMED (mechanism); **DESIGN RISK** (occurrence is config-dependent) |
| **Category** | Configuration / reliability |
| **Component** | `inc/enqueue.php:165`, `inc/security.php` `bgcf_verify_request()`, `assets/js/src/donation-form.js` |

**Requirement.** `CLAUDE.md` requires the theme to be "WP Rocket–compatible".

**Observed.** The nonce is baked into the HTML at render time (`wp_localize_script`, `bgcfData.nonce`) and into hidden form inputs. Nonce lifetime measured in this environment:

```
nonce_life = 86400s (tick = 43200s)
fresh (0-12h old)   verify=1      -> ACCEPTED
12-24h old          verify=2      -> ACCEPTED
24-36h old          verify=false  -> REJECTED (403)
36-48h old          verify=false  -> REJECTED (403)
```

For a logged-out donor the nonce is bound to uid `0` and an empty session token, so **every visitor served the same cached page receives the same nonce**, and it dies 24 h after the page was cached.

**Failure scenario.** Page cache TTL exceeds 24 h (or Cloudflare holds an edge copy). Donor submits -> `bgcf_invalid_nonce` -> 403 -> the JS displays the server message *"Your session expired. Please reload the page and try again."* -> the donor reloads -> **the same cached page with the same dead nonce is served** -> loop. The donation is lost and the site looks broken.

**Mitigating facts, stated fairly.** `PAYMENTS.md:175-179` **already documents this** and prescribes excluding the donation page from caching or keeping the lifespan under 12 hours. This is a known hazard, not an oversight.

**Why it is still a defect.** The mitigation is manual, external, and undocumented where an operator would look: there is **no `DEPLOYMENT.md`** (CFG-01), so the instruction lives only in a developer document. There is no defence in depth — no nonce-refresh endpoint, no cache-exclusion hint emitted by the theme (e.g. a `DONOTCACHEPAGE` constant on pages containing the donation form), and no client-side recovery: the JS treats `bgcf_invalid_nonce` like any other error rather than re-fetching a fresh nonce and retrying once.

**Recommended fix.** (a) Define `DONOTCACHEPAGE` when the donation form renders — respected by WP Rocket, W3TC and WP Super Cache. (b) Add a tiny uncached `bgcf_nonce` endpoint; on `bgcf_invalid_nonce` the JS fetches a fresh nonce and retries once. (c) Write `DEPLOYMENT.md` and put the cache rule in it.

**Regression test.** Force `wp_verify_nonce` to fail once; assert the JS refetches and the second attempt succeeds.

---

## DEF-008 — Raw provider error messages are relayed to anonymous callers

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Information disclosure / error handling |
| **Component** | `inc/payments-routes.php` — `gateway_error` branch |

**Observed.** With deliberately invalid sandbox keys, an unauthenticated request returned:
```
HTTP 502 {"success":false,"data":{"code":"gateway_error",
          "message":"Invalid authorization key..."}}
```
The message originates from Flutterwave and is passed through verbatim by design — the code comments it as *"safe to surface... the difference between a donor retrying and a donor giving up."*

**Technical explanation.** The intent is sound for donor-facing messages like "amount below minimum". But the channel is unfiltered, so **server configuration state leaks through the same pipe**. "Invalid authorization key" tells an anonymous prober that the site's gateway credentials are wrong — useful reconnaissance, and embarrassing on a live charity site.

**Impact.** Low-grade information disclosure; unprofessional error text shown to donors during a misconfiguration.

**Recommended fix.** Map provider error codes to an **allowlist** of donor-safe messages; fall back to a generic "We could not start your donation" for anything unmapped, and log the raw message (once DEF-005 is fixed).

**Regression test.** Assert that an authorization-class provider error yields the generic message and that the raw text appears only in the log.

---

## DEF-009 — `bgcf_ajax_param()` never guarantees a scalar; array input floods the error log

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Input validation / error handling |
| **Component** | `inc/ajax.php:63-85`; all `(string) bgcf_ajax_param(...)` call sites |

**Observed.** Live, with a valid logged-out nonce:
```
POST admin-ajax.php action=bgcf_donation_status&nonce=...&token[x][y]=1
  -> HTTP 404 {"code":"not_found"}
```
and in `wp-content/debug.log`:
```
PHP Warning: Array to string conversion in .../inc/payments-routes.php on line 346
```

**Technical explanation.** `bgcf_ajax_param()` returns an array when `$_REQUEST[$key]` is an array. `sanitize_text_field()` on a nested array returns `''` (WordPress guards for it), so there is **no fatal** — my initial hypothesis of a 500 was wrong, and the evidence corrects it. What remains is the `(string)` cast at every call site emitting a PHP Warning. Every one of the ~14 `(string) bgcf_ajax_param(...)` sites in `payments-routes.php` shares the shape.

**Impact.** An unauthenticated caller (nonces are public) can write a warning line per request to the server error log. Sustained, this is log-volume amplification and disk pressure, and it drowns real signals — which matters more given DEF-005 leaves so little else in the log.

**Recommended fix.** Give `bgcf_ajax_param()` an explicit scalar contract: add a `$single = true` parameter (or a sibling `bgcf_ajax_param_scalar()`) that returns `''` for array input instead of the array. Update the call sites and drop the redundant casts.

**Regression test.** Send `token[]=x` and `token[a][b]=1` to every public endpoint; assert the documented error code and **zero** entries in the PHP error log.

---

## DEF-010 — Donation lookups scan `wp_postmeta`; the code comment claims an index that does not exist

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Performance / scalability / documentation accuracy |
| **Component** | `inc/donations.php` — `bgcf_donation_find_by_meta()` |

**Observed.** The function comments its `meta_query` as *"Indexed meta lookup on a unique value; the alternative is a raw `$wpdb` query, which buys nothing here."* The actual indexes:
```
PRIMARY   meta_id
post_id   post_id
meta_key  meta_key      <- prefix index, meta_key(191)
```
**`meta_value` is not indexed** — it is `LONGTEXT` and WordPress never indexes it. The query filters on `meta_key = '_bgcf_tx_ref' AND meta_value = '<ref>'`: MySQL can use the `meta_key` index to narrow to *all rows of that key*, then filters `meta_value` row by row.

**Technical explanation.** Cost is O(number of donations), not O(1). Called on **every** webhook, every return-URL hit, and every status poll (5-second cadence x 24 per donor). It also runs through `WP_Query`, adding a `wp_posts` join and post-type/status filtering on top.

**Impact.** Invisible at hundreds of donations; a real hot path at tens of thousands, precisely when a campaign is succeeding. No production incident expected at launch volume — this is a scalability ceiling, recorded so it is not discovered during a campaign.

**Recommended fix.** Short term: correct the misleading comment. Medium term: store the tx_ref and public token in an indexed column of a small custom table (which DEF-004's fix should introduce anyway), or add a targeted index. Do not paper over it with caching — a lookup that must be correct on a webhook should not depend on cache warmth.

**Regression test.** Seed 50,000 donations; assert `bgcf_donation_find_by_tx_ref()` stays under a fixed time budget.

---

## DEF-011 — Donor PII accumulates indefinitely with no retention policy or erasure path

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Data protection / compliance |
| **Component** | `inc/donations.php` |

**Observed.** A failed test donation, dumped from the database:
```
_bgcf_donor_email   a@b.com
_bgcf_donor_phone   256772123456
_bgcf_donor_name    (as supplied)
_bgcf_message       (free text)
```
A record is created **before** the gateway call, so every attempt persists — including failures, abandonments and bot probes. Nothing prunes them. `grep` for `wp_privacy_personal_data_exporters`, `wp_privacy_personal_data_erasers` and `personal_data` across `theme/` returns **nothing**.

**Technical explanation.** Creating the record first is the correct safety decision (a charge can never exist without a row). The consequence — unbounded PII from unauthenticated input — was not addressed. WordPress ships privacy exporter/eraser hooks; the theme registers neither, so a subject-access or erasure request cannot be serviced through the standard tooling and would require manual SQL.

**Impact.** GDPR exposure for EU donors and exposure under Uganda's Data Protection and Privacy Act. Growing breach blast radius. Note `bgcf_flw_log()` is careful never to log PII — the design intent is clearly present, which makes the storage-side omission the outlier.

**Recommended fix.** (a) Register a `wp_privacy_personal_data_exporters` and `..._erasers` pair for `bgcf_donation`. (b) Add a scheduled prune that clears PII from `failed` and `abandoned` records older than a configurable window (default 90 days), keeping the non-identifying financial record. (c) Document the retention period. Never auto-delete `successful` records — those have accounting and audit obligations.

**Regression test.** Run the eraser for a known donor email; assert PII meta is cleared and the financial record survives.

---

## DEF-012 — Unauthenticated, unrate-limited status endpoint triggers outbound provider calls

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Rate limiting / resource exhaustion |
| **Component** | `inc/payments-routes.php` — `bgcf_ajax_donation_status()` |

**Observed.** `bgcf_donation_start` calls `bgcf_donation_rate_limited()`; `bgcf_donation_status` does **not**. It is registered `nopriv`, and when the record is `pending` and 10 s have elapsed it performs an outbound Flutterwave verify.

**Technical explanation.** Two amplification paths. Unauthenticated, no token needed: each request costs a `WP_Query` meta scan (DEF-010) — cheap per request, not free at volume. With a valid token (a donor's own, or one from a shared URL): a request every 10 s forces a provider API call, consuming Flutterwave rate-limit budget that the **real** payment flow needs.

**Mitigating fact.** The 10-second per-donation floor is the main brake and was a deliberate design choice. The token is 24 characters of `wp_generate_password` entropy — brute-forcing one is not feasible.

**Recommended fix.** Apply the existing `bgcf_donation_rate_limited()` to the status endpoint with a more generous budget (polling is legitimate), and return the cached status without an outbound call once the record is terminal.

**Regression test.** 200 requests in 60 s from one IP; assert 429 after the budget and no more than 6 outbound provider calls.

---

## DEF-013 — Zero automated tests

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED (TEST COVERAGE GAP) |
| **Category** | Test coverage |

**Observed.** No PHPUnit, no `wp-env`, no Playwright/Cypress/Jest, no fixtures, no test directory. `package.json` has no `test` script.

**Mutation reasoning.** Because no tests exist, every mutation is undetected by definition. The ones that matter, ranked by blast radius:

| Mutation | Detected today? | Consequence if shipped |
|---|---|---|
| Remove check 2 (tx_ref binding) in `bgcf_donation_apply_verification()` | **No** | A real UGX 1,000 payment can be replayed against a UGX 1,000,000 record. **Financial.** |
| Change `$amount < $expected_amount` to `<=` | **No** | Underpayment accepted as full payment. |
| Drop `'successful' === $current` from `bgcf_donation_set_status()` | **No** | A late webhook reverts a paid donation to pending. |
| Return `true` from `bgcf_flw_verify_webhook_signature()` | **No** | Anyone can post a webhook. **Auth bypass.** |
| Remove `bgcf_ajax_guard()` from any handler | **No** | CSRF on a public endpoint. |

These five are the minimum viable test suite. They are all pure functions of their inputs and are cheap to test.

**Recommended fix.** Add `wp-env` + PHPUnit. Write the five tests above first, then the concurrency test from DEF-004.

---

## DEF-014 — WPCS non-compliance contradicts a "Non-Negotiable" standard

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | Static analysis / documentation consistency |

**Observed.**
```
A TOTAL OF 50 ERRORS AND 151 WARNINGS WERE FOUND IN 14 FILES
PHPCBF CAN FIX 183 OF THESE SNIFF VIOLATIONS AUTOMATICALLY
```
By sniff: 130 array-alignment, 25 block-comment, 17 array-spacing, 11 statement-alignment — **all auto-fixable formatting**. eslint and stylelint are both **clean**.

**Investigated, not assumed.** Four violations looked security-relevant and were each traced to source:

- `WordPress.Security.EscapeOutput.OutputNotEscaped` x3 — `inc/payments-admin.php:182-185`. Inspected: these are the `wp_dropdown_pages()` argument array (`name`, `id`, `selected`), where `$name`/`$id` are internally generated field identifiers and `$value` passes through `absint()`. **False positive — not an XSS.**
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` x1 — `inc/ajax.php:76`. This is `$raw = wp_unslash( $_REQUEST[$key] )`, sanitized on the following lines by the caller-supplied callback. **False positive** as an injection risk — but it is the same line implicated in the genuine DEF-009.
- `PrefixAllGlobals.NonPrefixedConstantFound` x1 — `inc/security.php:35`, `DISALLOW_FILE_EDIT`. A WordPress core constant; the sniff is right that a theme defining it is unusual (see DEF-016), wrong that it should be prefixed.

**Impact.** No security impact. The impact is on the standard's credibility: `CLAUDE.md` calls WPCS non-negotiable and nothing enforces it, which is DEF-001's symptom.

**Recommended fix.** Run `npm run format:php` (phpcbf clears 183), hand-fix the ~18 remainder, add `-q` suppressions with justifications for the confirmed false positives, then enforce in CI.

---

## DEF-015 — Rate limiter counts validation failures against the donor's budget

| | |
|---|---|
| **Severity** | MEDIUM · **Confidence** HIGH · **Status** CONFIRMED |
| **Category** | UX / business logic |
| **Component** | `inc/payments-routes.php` — `bgcf_donation_rate_limited()` |

**Observed.** The limiter works correctly — verified live, 429 on attempt 13 of 20, transient `bgcf_don_rl_<md5>` = 12:
```
429 429 429 429 429 429 429 429 429 429 429 429 429 429 429 429 429 429 429 429
```
But it is called **before** validation and increments on every call. A donor who mistypes their phone number, fixes it, mistypes their email, fixes it... consumes the same 12-attempt budget as an attacker, then is locked out for 15 minutes with no path forward.

Secondary: `get_transient()` then `set_transient( $key, $count + 1 )` is a read-modify-write with no atomicity, so concurrent requests can under-count. Minor here — the limit is a coarse brake, not a security control.

**Technical explanation.** The limiter is protecting the **gateway call**, which is the expensive, abusable operation. Validation failures never reach it.

**Recommended fix.** Increment only on attempts that reach `bgcf_donation_create()`. Keep a separate, much higher ceiling on total requests to blunt raw flooding. Use `wp_cache_incr()` where a persistent object cache is available.

**Regression test.** 11 invalid submissions followed by 1 valid one -> the valid one must succeed.

---

# LOW

## DEF-016 — `DISALLOW_FILE_EDIT` defined in the theme
`inc/security.php:35`. A theme loads after plugins and after much of core, so this is a late and partial backstop; it also trips `PrefixAllGlobals`. **Fix:** keep it, but document `wp-config.php` as the required location in `DEPLOYMENT.md` and suppress the sniff with a justification. The code already says this — the doc it points to does not exist (CFG-01).

## DEF-017 — Author-scan guard uses a 301
`bgcf_security_block_author_scan()` returns `wp_safe_redirect( home_url('/'), 301 )`. A 301 is cached permanently by browsers and CDNs, so a legitimate future `?author=` URL stays poisoned in caches. **Fix:** use 302.

## DEF-018 — Verification mismatches are re-queried forever and never alerted
On a `successful`-but-mismatched verification, `bgcf_donation_apply_verification()` sets `_bgcf_mismatch` and parks the record at `pending`. The cron then re-queries and re-logs it every 15 minutes for 24 hours. Parking is the right call — the comment is correct that this is "a red flag, not a donation" — but nothing surfaces it. **Fix:** add a `mismatch` status or exclude flagged records from the reconcile query, and raise an admin notice. Ties to DEF-005.

## DEF-019 — A donation with a missing `_bgcf_created_gmt` can never be abandoned
`bgcf_donation_reconcile()` guards both the min-age skip and the abandon branch with `$created > 0`. If the meta is ever 0 or absent, the record is re-queried every 15 minutes forever. Not reachable through the normal creation path (the meta is always written) — so **POTENTIAL BUG**, reachable only via partial writes or manual edits. **Fix:** fall back to `post_date_gmt`.

## DEF-020 — Negative and decimal amounts are silently coerced
`absint('-50000')` -> `50000`; `absint('50000.99')` -> `50000`. Both reached the gateway in live probes rather than being rejected. The coercion direction is safe (no negative charge is possible) and the result stays inside min/max, but a donor who typed something odd is charged a number they did not type. **Fix:** reject non-integer input with `invalid_amount` rather than coercing.

## DEF-021 — Image governance artefacts are missing
`CLAUDE.md` mandates `assets/images/CREDITS.md` logging source, licence and consent, and a placeholder manifest. `theme/assets/images/` contains only `icons/`; `CREDITS.md` does not exist. `theme/languages/` contains only `.gitkeep` — no `.pot`, so the `bgcf` text domain is not actually translatable despite full `__()` coverage. Both are launch-blocking for the stated policy (child-safeguarding consent, Polylang) but neither is a code defect.

# INFO

## DEF-022 — `Permissions-Policy: payment=()` disables the Payment Request API
Harmless for the current redirect-based flow. A latent conflict if an inline card widget or Apple/Google Pay is ever embedded. Recorded so the header is not mistaken for a browser bug later.

## DEF-023 — `GiveWP` vs Flutterwave documentation contradiction
`CLAUDE.md` names GiveWP as the donation system; the implementation is a bespoke Flutterwave v3 integration documented in `PAYMENTS.md`. The code is clearly the newer decision. **Fix:** update `CLAUDE.md` so the canonical brief does not contradict the build.

---

# Verified correct — tested and found sound

Recorded because a QA report that lists only failures misrepresents the codebase. Each was actively probed, not assumed.

| Area | Evidence |
|---|---|
| **Webhook authentication** | No signature -> 401; wrong signature -> 401; correct -> 200. `hash_equals`, constant-time. Correctly refuses to substitute an IP allowlist. |
| **Donation records are private** | `/wp-json/wp/v2/bgcf_donation` -> 404; `/?p=<id>` -> 404; excluded from site search. |
| **Server-side verification gate** | All four checks present, including the tx_ref binding that defeats cross-donation replay. Single funnel for all four signals. |
| **Terminal status stickiness** | `successful` cannot be reverted by any later signal. |
| **Secrets handling** | Constants/env only, never the database, with an explicit rationale. Mode derived from the key prefix, not a checkbox that can disagree with it. |
| **Input validation matrix** | 11 hostile inputs probed live; min/max/zero/email/method/foreign-phone/unknown-prefix/honeypot all returned the correct code and status. |
| **Rate limiting on the charge path** | Fires at exactly 12 attempts / 15 min / IP. |
| **Runtime cleanliness** | 24 routes (pages, CPT archives and singles, search, feed, REST, 404) — all correct status, **zero** PHP errors, empty `debug.log`, no browser console errors. |
| **Money precision** | Integer shillings end to end. No float arithmetic on amounts anywhere. |
| **Timeout discipline** | 45 s client timeout deliberately above the provider's ~28 s ceiling; timeouts trigger requery, never blind retry of a money-moving call. |
| **Accessibility baseline** | Live `/about/`: one `h1`, skip link, `<main>`, `lang` attribute, 5 aria-labelled `<nav>`s, zero `<img>` without `alt`. |
| **JS/CSS static analysis** | eslint and stylelint both clean. |
| **Security headers** | 5 of 5 intended headers present; `X-Pingback` removed; XML-RPC disabled. |
