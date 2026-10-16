# Risk Register

Probability is judged for the **first 90 days after a production launch at modest NGO traffic** (hundreds of donations per month, occasional campaign spikes). Severity combines impact with reversibility — a risk that destroys evidence scores higher than one that merely breaks a page.

| ID | Risk | Prob. | Impact | Severity | Evidence | Mitigation |
|---|---|---|---|---|---|---|
| R-01 | A bad release cannot be reverted; a broken donation form stays broken until someone hand-edits a live server | **High** | Critical | **CRITICAL** | `git rev-parse` -> not a repository; no CI | `git init` + CI gate before any deploy (DEF-001) |
| R-02 | The site launches unable to display its own content, because no block renders | **Certain if launched today** | Critical | **CRITICAL** | 10 of 11 block partials are `bgcf_empty_state()` stubs; `acf-json/` empty | Build blocks + field groups (DEF-002); do not launch before |
| R-03 | An ACF Pro licence is never purchased, so blocks cannot register at all | Medium | Critical | **HIGH** | `inc/blocks.php:201` guard | Confirm the licence **now** — it is a procurement lead-time item, not an engineering one (GAP-I1) |
| R-04 | Reported donation totals diverge from funds actually banked | Medium (rises with volume) | High | **HIGH** | `add_metadata` SELECT-then-INSERT; no unique index; duplicate rows proven accepted | Atomic fulfilment guard (DEF-004) |
| R-05 | A payment incident occurs and no one can determine what happened | **High** | High | **HIGH** | One `error_log` call in 2,900 LOC, gated on `WP_DEBUG` | Enable production logging + alerts (DEF-005) |
| R-06 | Webhook secret mistyped at provisioning; every webhook 401s and nobody notices | Medium | Medium | **HIGH** | No alerting on signature rejection; cron masks the symptom as "slow receipts" | GAP-M2 + a post-deploy webhook smoke test |
| R-07 | Donors hit an unrecoverable "session expired" loop on a cached donation page | Low-Medium (config-dependent) | High | **HIGH** | Nonce measured dead at >24 h; JS offers no recovery; reload re-serves the same cache | `DONOTCACHEPAGE` + nonce refresh + write `DEPLOYMENT.md` (DEF-007) |
| R-08 | Go-live with **zero** end-to-end verification of the money path | **Certain today** | Critical | **HIGH** | No sandbox credentials available; J2/J3 UNVERIFIED | Full sandbox run-through before launch — non-negotiable (GAP-I4) |
| R-09 | A data-protection request arrives and cannot be serviced | Low | High | **MEDIUM** | No privacy exporter/eraser; PII retained forever including from bots | Register WP privacy hooks + retention prune (DEF-011) |
| R-10 | A silent regression ships because nothing tests the verification gate | Medium | High | **MEDIUM** | No tests; removing the tx_ref check would be undetected | Five-test minimum suite (DEF-013) |
| R-11 | Server-level hardening is never applied because its document does not exist | Medium | Medium | **MEDIUM** | `inc/security.php` cites `DEPLOYMENT.md` 3x; file absent | Write `DEPLOYMENT.md` (GAP-D1) |
| R-12 | Donation lookups degrade exactly when a campaign succeeds | Low (near-term) | Medium | **MEDIUM** | `meta_value` unindexed; called per webhook and per 5 s poll | Indexed lookup (DEF-010) |
| R-13 | Error log filled by anonymous requests, drowning real signal | Medium | Low | **MEDIUM** | `Array to string conversion` warning reproduced from an unauthenticated request | Scalar contract on AJAX params (DEF-009) |
| R-14 | A genuine donor is locked out for 15 minutes by their own typos | Medium | Low | **MEDIUM** | Rate limiter increments before validation; verified firing at 12 | Count only gateway-reaching attempts (DEF-015) |
| R-15 | Configuration state leaks to anonymous probers via provider error text | Medium | Low | **LOW** | "Invalid authorization key" returned to an unauthenticated caller | Message allowlist (DEF-008) |
| R-16 | A child's image is published without documented guardian consent | Low | **Critical** (safeguarding + legal) | **MEDIUM** | `CREDITS.md` mandated by `CLAUDE.md`, does not exist; no manifest | Create the manifest and consent log **before** any photography lands (DEF-021) |
| R-17 | Site ships untranslatable despite Polylang being in scope | Medium | Low | **LOW** | `languages/` holds only `.gitkeep`; no `.pot` | Generate the POT in the build (DEF-021) |
| R-18 | Duplicate charges from a double-submitted form | Low | Medium | **LOW** | Fresh `tx_ref` per call; only a client-side busy guard | Server-side idempotency key (GAP-V4) |

## Single points of failure

| SPOF | Consequence if it disappears | Does the system recover? |
|---|---|---|
| **Flutterwave** | No donations can start | Yes, gracefully — `WP_Error` -> 502 with a donor-readable message. In-flight donations settle later via cron. **Good.** |
| **WordPress cron** | The reconciliation safety net stops; donations whose webhook was lost stay pending forever | **No automatic recovery, and no alert.** The most under-protected dependency in the system: it is the thing that makes webhook loss survivable, and nothing watches it. See GAP-M4. |
| **`wp_mail` / SMTP** | Receipts silently stop | No — failures are discarded (GAP-M5). Money is unaffected; donor trust is not. |
| **Database** | Total outage | Platform-level; out of theme scope |
| **Object cache** | Rate limiter degrades to per-request transients in the DB | Degrades safely |

## Chaos analysis — "what if this component disappears mid-transaction?"

| Scenario | What fails | What stays consistent | What is lost or duplicated | Can an admin repair it? |
|---|---|---|---|---|
| Flutterwave times out **after** charging | The AJAX response | The donation row exists and is `pending` | Nothing — the 45 s timeout and requery-never-retry rule are deliberate and correct | Yes — cron settles it within 15 min |
| Worker dies between `bgcf_donation_create()` and the charge call | Nothing visible | Row exists, `pending` | The donor sees an error and may retry, creating a second row | Yes — cron abandons the orphan after 24 h |
| Webhook and browser return arrive in the same second | Nothing visible | Status is correct either way | **Possible duplicate receipt and duplicate `bgcf_donation_completed`** (DEF-004) | Only by inspecting `_bgcf_fulfilled` row counts by hand |
| Flutterwave sends a malformed body | Nothing | Everything — the body is a trigger, not proof | Nothing | N/A. Handled correctly. |
| Donor submits 10 times | Rate limiter stops them at 12 | Each attempt is its own record | Up to 12 pending rows with PII | Partially — they abandon after 24 h but PII persists (DEF-011) |
| `wp-cron` never runs | Reconciliation | Webhook and return paths still work | Donations with lost webhooks stay pending indefinitely | Yes, if anyone notices — **nothing tells them** |
| Secret hash rotated without updating `wp-config.php` | All webhooks 401 | Cron still settles everything | Receipt latency degrades from seconds to ~15 min | Yes, if anyone notices — **nothing tells them** |

The last two rows are the same story twice and are the strongest argument for DEF-005: this system's failure mode is not a crash, it is **silent degradation that looks like slowness**.
