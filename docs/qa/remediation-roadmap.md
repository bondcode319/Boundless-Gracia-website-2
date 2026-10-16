# Remediation Roadmap

Ordered by dependency, not by severity. Phase 0 must come first because nothing after it is reviewable or revertible without it.

**No code has been changed by this audit.** This roadmap is a proposal awaiting approval.

---

## PHASE 0 — Baseline and safety

**Objective.** Make every later change reviewable, revertible and mechanically gated.

**Problems addressed:** DEF-001, DEF-014, GAP-D1, CFG-01, CFG-03.

**Files affected:** repository root; new `.github/workflows/ci.yml`; new `DEPLOYMENT.md`, `.env.example`; formatting-only changes across `theme/inc/*`, `theme/templates/*`.

**Dependencies:** none.

**Tasks**
1. `git init`; commit the current tree verbatim as the baseline. **Commit before running phpcbf** so the formatting diff is separable from the baseline.
2. `npm run format:php` (phpcbf clears 183 of 201 violations); hand-fix the ~18 remainder; add justified `phpcs:ignore` for the three confirmed false positives documented in DEF-014.
3. CI workflow: `npm ci`, `npm run lint`, `npm run lint:php`, `npm run build` on push and PR.
4. Write `DEPLOYMENT.md`: the three Flutterwave constants, `DISALLOW_FILE_EDIT`, `DISABLE_WP_CRON` + system cron, SMTP, page-cache exclusion for the donation page, `Options -Indexes` / `autoindex off`, HSTS and CSP at the edge, webhook URL registration, thank-you page setup.
5. `.env.example` mirroring it.
6. Correct the two misleading comments: the atomicity claim in `donations.php` (DEF-004) and the "indexed meta lookup" claim (DEF-010). **Do this in Phase 0** — a comment that misleads a reviewer actively works against every later phase.

**Tests required:** TC-027 (clean build + all three linters).
**Acceptance:** CI red on a deliberately introduced lint error, green after revert.
**Risk:** phpcbf touches many files. Mitigated by committing the baseline first and reviewing the diff as formatting-only.
**Rollback:** `git revert`.
**Exit:** repository exists, CI green, `DEPLOYMENT.md` reviewed by whoever will run the deploy.

---

## PHASE 1 — Critical production blockers

**Objective.** Make the financial invariants testable, then provably correct under concurrency.

**Problems addressed:** DEF-013 (partially), DEF-004.

**Files affected:** new `tests/`; `theme/inc/donations.php`.

**Dependencies:** Phase 0.

**Tasks**
1. Stand up `wp-env` + PHPUnit; wire into CI.
2. Write test wave 1 (TC-001..007, TC-012, TC-017). **These must pass against the code as it is today** — if any fails, an undiscovered defect exists and this roadmap changes.
3. Write TC-008 (concurrency). Expect it to fail.
4. Implement atomic fulfilment. Preferred: a `bgcf_donation_fulfilment` table with `donation_id` as PRIMARY KEY, created on theme activation; `INSERT` succeeds or throws a duplicate-key error, and that error is the idempotency signal. Interim alternative: MySQL `GET_LOCK('bgcf_fulfil_{id}', 0)`.
5. Re-run TC-008 until green. Keep TC-009 green.

**Regression:** full wave 1 + wave 2.
**Acceptance:** TC-008 green with N=10 and N=50.
**Risk:** a new table needs an activation hook and an upgrade path for existing installs. Ship the table creation idempotently (`CREATE TABLE IF NOT EXISTS` via `dbDelta`) and backfill from existing `_bgcf_fulfilled` meta.
**Rollback:** revert; the meta guard remains as it is today (imperfect, not broken).
**Exit:** concurrency test green; no donation can be fulfilled twice under parallel load.

---

## PHASE 2 — Security and endpoint hardening

**Objective.** Close the disclosure, rate-limit and input-contract gaps.

**Problems addressed:** DEF-008, DEF-009, DEF-012, DEF-015, DEF-017, DEF-020, GAP-S1.

**Files affected:** `inc/ajax.php`, `inc/payments-routes.php`, `inc/security.php`.

**Dependencies:** Phase 1 (tests must exist first).

**Tasks**
1. Give `bgcf_ajax_param()` a scalar contract; update all call sites; drop redundant `(string)` casts.
2. Allowlist provider error messages; log the raw text, return a mapped donor-safe message.
3. Rate-limit `bgcf_donation_status` generously; skip the outbound call once the record is terminal.
4. Move the rate-limit increment to after validation; keep a separate high flood ceiling.
5. Reject non-integer amounts instead of coercing.
6. `301` -> `302` on the author-scan guard.

**Tests:** TC-011, TC-014, TC-015, TC-016, TC-018.
**Acceptance:** TC-014 produces zero PHP notices; TC-015 and TC-016 both green (the fix must not remove the brake).
**Risk:** loosening the rate limiter could weaken abuse protection — TC-016 exists precisely to prevent that.
**Exit:** wave 3 green.

---

## PHASE 3 — Data integrity, privacy and reconciliation

**Problems addressed:** DEF-010, DEF-011, DEF-018, DEF-019, GAP-S5.

**Tasks**
1. Indexed lookup for `tx_ref` and public token (reuse the Phase 1 table).
2. Register `wp_privacy_personal_data_exporters` and `..._erasers` for `bgcf_donation`.
3. Scheduled PII prune for `failed` / `abandoned` older than a configurable window (default 90 days). **Never** touch `successful` records — accounting retention.
4. Give mismatches their own handling: a distinct status or exclusion from the reconcile query, plus an admin notice.
5. Fall back to `post_date_gmt` when `_bgcf_created_gmt` is absent.

**Tests:** TC-019, TC-020; new erasure and prune tests.
**Acceptance:** an erasure request clears PII and leaves the financial record intact; a mismatched record stops being re-queried and starts being visible.
**Risk:** the prune deletes data. Ship it **dry-run first**, logging what it would delete, for one full cycle.
**Exit:** wave 4 green; retention window documented.

---

## PHASE 4 — Observability

**Problems addressed:** DEF-005, GAP-M1..M6, CFG-04, CFG-05.

**Tasks**
1. Default `bgcf_flw_logging_enabled` to true for warning-and-above; keep verbose tracing on `WP_DEBUG`.
2. Check `wp_mail()` returns; flag `_bgcf_receipt_failed`; surface it in the donations list.
3. Admin notices for: webhook signature rejections in the last hour, records pending > 24 h, flagged mismatches, cron not having run in > 45 min.
4. Audit-log admin changes to donation records with the acting user ID.
5. A `bgcf_health` check (admin-only) asserting: credentials present, webhook URL reachable, cron scheduled, last successful verify timestamp.

**Acceptance:** a deliberately mistyped secret hash produces a visible operator signal within one cron cycle — the R-06 scenario.
**Exit:** the chaos table in `risk-register.md` has no remaining "nothing tells them" rows.

---

## PHASE 5 — Content layer (the largest phase)

**Problems addressed:** DEF-002, DEF-006, GAP-F1, F2, F6, F7.

**Dependencies:** **an ACF Pro licence.** Confirm procurement before this phase starts — it has lead time and blocks everything here.

**Tasks**
1. Per block, in homepage-priority order — Hero, Statistics, Program Cards, CTA, Donation Banner (done), Testimonials, Partners, Timeline, FAQ, Gallery, Newsletter: define the field group in the ACF UI against the field list already documented in each partial's docblock; export to `acf-json/`; write the markup against `bgcf_field()`.
2. Populate `bgcf_schema_graph` per context; honour `bgcf_seo_is_delegated()`.
3. Generate the `.pot` catalogue; add it to the build.

**Tests:** per block, assert field values render and the empty-state string does not. TC-028 for schema.
**Acceptance:** a homepage built entirely in the editor with no `bgcf_empty_state()` in the response.
**Risk:** the largest scope in the roadmap and the least specified. Gate each block individually rather than the phase as a whole.
**Exit:** all 11 blocks render real content; zero JSON-LD becomes correct JSON-LD.

---

## PHASE 6 — Remaining features

**Problems addressed:** DEF-003, GAP-F3/F4/F5, GAP-S6, GAP-V3.

**Tasks**
1. Newsletter: implement, or remove the registration.
2. Volunteer: `bgcf_volunteer` CPT, form, submission handler, **real MIME validation** (`finfo`, not extension), size cap, storage outside the web root or in a protected uploads subdirectory, admin workflow, notification emails.
3. Gallery load-more with clamped `paged` and `per_page`.

**Tests:** happy path + DB state; honeypot; a `.php` renamed `.pdf` rejected; oversize rejected; path-traversal filename neutralised.
**Acceptance:** no theme endpoint returns 501.
**Note:** this phase introduces the theme's **first** file-upload surface. Treat it as new attack surface, not as a form feature.

---

## PHASE 7 — Reliability and caching

**Problems addressed:** DEF-007, GAP-A4, GAP-V4, CFG-02.

**Tasks**
1. Define `DONOTCACHEPAGE` when the donation form renders.
2. Add an uncached nonce endpoint; on `bgcf_invalid_nonce` the JS refetches and retries once.
3. Server-side idempotency key on `bgcf_donation_start` so a double-submit reuses the pending donation rather than creating a second.
4. Publish a CSP (report-only first, then enforcing) and HSTS via `DEPLOYMENT.md`.

**Tests:** TC-023, TC-029.
**Acceptance:** a donation page cached for 48 h still accepts a submission.

---

## PHASE 8 — Performance, accessibility and launch readiness

**Problems addressed:** GAP-T6, GAP-T7, DEF-021, DEF-023, DEF-022.

**Tasks**
1. axe + Lighthouse in CI with budgets (TC-024, TC-026).
2. Keyboard-only donation walkthrough (TC-025).
3. Create `assets/images/CREDITS.md` and the placeholder manifest; swap every placeholder for licensed assets; verify guardian consent is documented for any identifiable child.
4. Update `CLAUDE.md` to name Flutterwave rather than GiveWP.
5. Full sandbox run-through on both rails with webhook delivery observed.

**Exit:** all of `test-plan.md` §7 satisfied.

---

## Phase gate template

Applied to every phase. A phase is not complete because the code compiles.

```
[ ] Implementation complete
[ ] Unit tests pass
[ ] Integration tests pass
[ ] Regression suite pass
[ ] phpcs / eslint / stylelint clean
[ ] Build passes from a clean tree
[ ] No new CRITICAL or HIGH defects introduced
[ ] Documentation updated (including any comment the change invalidates)
[ ] Evidence recorded in docs/qa/
```

## Fix discipline

For every individual fix: reproduce -> write the failing test -> smallest safe change -> targeted test -> component tests -> full regression -> static analysis -> clean build. One concern per commit.

**Continuous discovery rule:** if a fix reveals a new defect, stop, record it in `defect-register.md` with evidence, classify it against the phases above, and only then decide whether to address it now or schedule it. Do not fold an unrecorded fix into an unrelated commit.
