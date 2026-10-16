# Gap Register

Things that are **absent**, as distinct from things that are **wrong** (those are in `defect-register.md`). A gap is a missing link between what the project says it delivers and what exists on disk.

---

## A. Missing features

| ID | Gap | Source of the requirement | Evidence | Defect |
|---|---|---|---|---|
| GAP-F1 | 10 of 11 Gutenberg blocks render a placeholder, not content | `CLAUDE.md` "Custom Gutenberg Blocks" | 13 executable lines each, all calling `bgcf_empty_state()` | DEF-002 |
| GAP-F2 | Zero ACF field groups | `CLAUDE.md` "Everything editable in wp-admin via ACF" | `theme/acf-json/` is empty | DEF-002 |
| GAP-F3 | Newsletter subscription | `CLAUDE.md` Newsletter block | live 501 | DEF-003 |
| GAP-F4 | Volunteer application + résumé upload + admin workflow | `CLAUDE.md` Phase 6 | live 501; no `$_FILES` handling anywhere | DEF-003 |
| GAP-F5 | Gallery pagination / load-more | `CLAUDE.md` Gallery block | live 501 | DEF-003 |
| GAP-F6 | schema.org / JSON-LD output | `CLAUDE.md` Phase 7; `inc/seo.php:225` | 0 `ld+json` blocks on a live page | DEF-006 |
| GAP-F7 | Page templates for About / Programs / Projects / Blog / Events / Gallery / Stories / Contact / FAQ | `CLAUDE.md` Phase 4 | `theme/templates/` contains only `page-styleguide.php` | — |
| GAP-F8 | Translation catalogue | `CLAUDE.md` (Polylang, text domain `bgcf`) | `theme/languages/` holds only `.gitkeep`; no `.pot` | DEF-021 |
| GAP-F9 | Image placeholder manifest and `CREDITS.md` | `CLAUDE.md` "Imagery"; `PLAN.md` §7 | `assets/images/` holds only `icons/` | DEF-021 |

> GAP-F7 is partially mitigated: WordPress's template hierarchy means these pages render through `page.php` / `archive.php` and work (verified live). What is missing is their *designed* layouts, which in this architecture would come from blocks — so GAP-F7 collapses into GAP-F1.

## B. Missing APIs

| ID | Gap | Evidence |
|---|---|---|
| GAP-A1 | `bgcf_newsletter_subscribe` — registered, contract documented, returns 501 | live probe |
| GAP-A2 | `bgcf_volunteer_submit` — as above; also no upload endpoint, storage or MIME validation | live probe + grep |
| GAP-A3 | `bgcf_gallery_load_more` — as above | live probe |
| GAP-A4 | No nonce-refresh endpoint, so a cached page cannot recover from an expired nonce | DEF-007 |
| GAP-A5 | No health/readiness endpoint — no way to assert "gateway configured, cron alive, webhook reachable" | DEF-005 |

**APIs with no consumer:** none found — every registered endpoint has a caller, including the three dead ones (the front end is wired to contracts that answer 501).
**Consumers with no API:** none found — the JS calls only endpoints that exist.

## C. Missing integrations

| ID | Gap | Note |
|---|---|---|
| GAP-I1 | ACF **Pro** | Not present. Without it `acf_register_block_type()` is undefined and **no** `bgcf/*` block registers (`inc/blocks.php:201`). Paid licence required. |
| GAP-I2 | GiveWP | Named in `CLAUDE.md`; superseded by the Flutterwave build. Documentation contradiction, not a gap in function — DEF-023. |
| GAP-I3 | Contact Form 7, WP Mail SMTP, RankMath, Wordfence, Polylang, Google Maps | All declared; none present or configured. The theme guards for each, so absence is graceful. |
| GAP-I4 | Real Flutterwave sandbox credentials | Blocks end-to-end verification of J2/J3. **This is the single largest untested surface in the product.** |

## D. Missing validation

| ID | Gap | Defect |
|---|---|---|
| GAP-V1 | No scalar guarantee on AJAX parameters; array input reaches `(string)` casts | DEF-009 |
| GAP-V2 | Non-integer amounts coerced rather than rejected | DEF-020 |
| GAP-V3 | No upload validation of any kind (no uploads implemented) | DEF-003 |
| GAP-V4 | No idempotency key on the charge endpoint — a double-submitted form creates two donation records and two charges | see note below |

> **GAP-V4 detail.** `bgcf_donation_start` generates a fresh `tx_ref` per call. The browser guards against double-submit with a `data-bgcf-busy` attribute, which is client-side only. A donor who submits twice from two tabs creates two pending donations and potentially two mobile-money prompts. Flutterwave's own duplicate-reference protection does not apply, because the references genuinely differ. Not a defect in the sense of violating a stated contract — no idempotency was ever promised — but it is the kind of gap that produces angry donor emails. Recorded here rather than in the defect register for that reason.

## E. Missing tests

Everything. Detail and a ranked minimum suite are in `defect-register.md` DEF-013 and `test-plan.md`.

| ID | Gap |
|---|---|
| GAP-T1 | No unit tests — verification gate, status machine, MSISDN normalisation, payload hash are all untested pure functions |
| GAP-T2 | No integration tests — webhook, return URL, cron |
| GAP-T3 | No concurrency test — the DEF-004 race is undetectable today |
| GAP-T4 | No E2E / browser tests |
| GAP-T5 | No negative or boundary suite, despite a validation layer that is genuinely good and worth locking down |
| GAP-T6 | No accessibility regression (axe) despite WCAG 2.2 AA being a stated goal |
| GAP-T7 | No performance budget / Lighthouse gate despite a stated 95+ target |

## F. Missing documentation

| ID | Gap | Impact |
|---|---|---|
| GAP-D1 | `DEPLOYMENT.md` does not exist, but `inc/security.php` refers to it **three times** as the home of mandatory server hardening (directory listing, HSTS, CSP) | Those controls have no owner. CFG-01. |
| GAP-D2 | No `.env.example` or consolidated required-constants checklist | `PAYMENTS.md` documents the three Flutterwave constants; nothing lists everything an environment needs. CFG-03. |
| GAP-D3 | `CLAUDE.md` still names GiveWP | DEF-023 |
| GAP-D4 | `donations.php` docblock asserts atomic idempotency that the platform does not provide | DEF-004 — a doc that actively misleads a reviewer is worse than a missing one |
| GAP-D5 | `donations.php` comments a meta lookup as "indexed" when `meta_value` is not indexed | DEF-010 |
| GAP-D6 | No runbook: what to do when webhooks stop, when a donation is stuck pending, when a mismatch is flagged | DEF-005 |

## G. Missing monitoring

| ID | Gap |
|---|---|
| GAP-M1 | Production logging is off by default (`WP_DEBUG`-gated) — DEF-005 |
| GAP-M2 | No alert on webhook signature rejections — a mistyped secret hash is silent |
| GAP-M3 | No alert on verification mismatch, the system's own fraud signal |
| GAP-M4 | No alert on donations stuck pending, or on cron not running |
| GAP-M5 | `wp_mail()` failures discarded — a bounced receipt is invisible |
| GAP-M6 | No audit log of admin changes to donation records |

## H. Missing security controls

| ID | Gap | Note |
|---|---|---|
| GAP-S1 | No rate limit on the status endpoint | DEF-012 |
| GAP-S2 | No rate limit on the authenticated webhook path | Lower risk — requires the secret — but each call costs an outbound verify |
| GAP-S3 | No CSP, no HSTS | Documented as edge-owned; the document that would own them does not exist (GAP-D1) |
| GAP-S4 | No reCAPTCHA | `inc/security.php` declares the `bgcf_verify_request` hook point for it; nothing attaches. Honeypot + rate limit are in place, so this is defence-in-depth, not a hole. |
| GAP-S5 | No privacy exporter/eraser registration | DEF-011 |
| GAP-S6 | No upload MIME/size validation | Nothing uploads yet — becomes blocking the moment GAP-F4 is built |

## I. Dead code and unused surface

Assessed per the brief; **recommendations only, nothing deleted.**

| Item | Finding | Recommendation |
|---|---|---|
| `theme/woocommerce/` | Contains only `README.md`; WooCommerce is not in the stack | Keep — documented as a deliberate override slot |
| `bgcf_ajax_not_implemented()` | Used by exactly the three dead endpoints | Remove when DEF-003 is resolved |
| `bgcf_seo_render_schema()` | Correct, complete, and has no producers | **Not** dead — it is a finished extension point awaiting DEF-006 |
| `bgcf_flw_credential('encryption_key')` | Mapped but never read; v3 encryption is unused in this flow | Harmless. Leave, or drop with the v3 rationale noted |
| Block partials | Not dead — scaffolds awaiting DEF-002 | Keep |
| `.stylelintrc.json`, `eslint.config.mjs`, `phpcs.xml.dist` | All present, all correct, **none enforced** | DEF-001 |

No unreachable branches, duplicate implementations or dead feature flags were found. For a 9,400-line codebase that is unusual and worth stating.
