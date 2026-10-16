# Final QA Report

**System:** Boundless Gracia Children Foundation — custom WordPress theme (`bgcf`)
**Audit date:** 2026-09-24
**Method:** static inspection + live reproduction against WordPress 7.0.2 / PHP 8.3 / MariaDB 11
**Code changed by this audit:** none.

---

## 1. Executive summary

This is a **well-architected codebase that is roughly half built**, and the half that is missing is the half the client sees.

The payment subsystem — the part that handles money and carries the real risk — is genuinely good. The verification model is correct in the way that matters most: value is never granted from a browser redirect or a webhook body, four independent signals all re-verify server-side against the provider, and the transaction reference is bound to the specific donation record so a real small payment cannot be replayed against a large one. Secrets live in constants and never in the database. Gateway mode is derived from the key prefix rather than a checkbox that could disagree with it. Timeouts trigger a requery rather than a blind retry of a money-moving call. These are decisions that teams usually get wrong, and the reasoning is written down in the code.

Against that, **ten of eleven Gutenberg blocks are placeholders, there are zero ACF field groups, three advertised AJAX endpoints return HTTP 501, and no structured data ships.** An editor cannot build the homepage. That is not a bug list — it is unstarted work, which is why the site runs with zero PHP errors across every route tested.

Three findings are genuinely defects rather than absences:

- **DEF-004** — fulfilment idempotency relies on `add_post_meta(..., unique)`, which is a non-atomic SELECT-then-INSERT with no database constraint behind it. Proven in this environment. The code's own docblock asserts this guard "survives" simultaneous signals; it does not. Under concurrent webhook and browser-return arrival — which the architecture deliberately makes routine — a donor can receive two receipts and `bgcf_donation_completed` can fire twice, so any consumer counting totals over-counts.
- **DEF-005** — payment logging is gated on `WP_DEBUG`, so correct production settings guarantee **zero** payment observability. The system's characteristic failure mode is not a crash but silent degradation: a mistyped webhook secret makes every webhook 401, the cron quietly picks up the slack, and the only symptom is slower receipts. Nothing would report it.
- **DEF-001** — there is no git repository and no CI. For code that charges cards and mobile money, there is no revert, no review and no audit trail.

Two things I expected to find and did not: there are **no float-money defects** (integer shillings end to end) and **no dead code** (every meta key written is read somewhere). Two hypotheses I formed while reading were corrected by testing — the rate limiter works exactly as specified, and array-typed input produces a PHP warning rather than the fatal I predicted.

**The single largest unverified surface is the money path itself.** No Flutterwave sandbox credentials were available, so no donation was ever completed end to end. Everything said here about the payment flow is derived from reading the code and probing its edges — not from watching a shilling move.

## 2. Assessment by dimension

| # | Dimension | Score | Basis |
|---|---|---|---|
| 1 | Functional correctness | **2** | Payment logic sound; content layer absent; 3 endpoints return 501 |
| 2 | API correctness | **3** | Contracts consistent, error codes well designed, client/server agree. Three endpoints unimplemented; one disclosure issue |
| 3 | Integration | **2** | Flutterwave integration well built but **never verified end to end**; ACF Pro absent, so blocks cannot register |
| 4 | Security | **4** | Strongest dimension. Webhook auth, nonce discipline, capability mapping, secret handling, headers, validation all verified correct. Deductions for DEF-008 and DEF-012 |
| 5 | Data integrity | **2** | Correct state machine and terminal stickiness, undermined by a non-atomic fulfilment guard and no retention policy |
| 6 | Performance | **3** | Clean build, deferred JS, self-hosted vendors, no N+1 found. Unindexed meta lookup on a hot path; no measured Lighthouse |
| 7 | Reliability | **3** | Genuinely good failure design (requery-not-retry, cron safety net, fail-safe unconfigured state). Undercut by the cache/nonce trap and an unmonitored cron |
| 8 | Observability | **1** | One log call in 2,900 LOC of payment code, off by default. No metrics, health check, alerts or audit log |
| 9 | Maintainability | **4** | Excellent module boundaries, honest and specific documentation, consistent naming, no duplication. Two comments assert guarantees the code does not deliver |
| 10 | Test quality | **0** | No tests of any kind |
| 11 | Deployment readiness | **0** | No VCS, no CI, no deployment document |
| 12 | Documentation | **3** | `ARCHITECTURE.md` and `PAYMENTS.md` are unusually good. `DEPLOYMENT.md` is referenced three times and absent; `CLAUDE.md` still names GiveWP |

**Overall: 2.3 / 5 — needs major improvement before production.**

The spread is the story. Security and maintainability score 4; testing and deployment score 0. This is a codebase built by someone who understood the problem domain well and stopped before the delivery engineering.

## 3. Defect summary

| Severity | Count | IDs |
|---|---|---|
| CRITICAL | 1 | DEF-001 |
| HIGH | 5 | DEF-002, 003, 004, 005, 006 |
| MEDIUM | 9 | DEF-007..015 |
| LOW | 6 | DEF-016..021 |
| INFO | 2 | DEF-022, 023 |

Full detail, evidence and reproduction steps in `defect-register.md`.

## 4. What was verified as working

Recorded because a report listing only failures would misrepresent this codebase. Each was actively probed:

- **24 routes** — pages, CPT archives and singles, search, feed, REST, 404 — correct status codes, **zero PHP errors**, empty `debug.log`, no browser console errors.
- **Webhook authentication** — no signature 401, wrong signature 401, correct 200, wrong method 404. Constant-time comparison.
- **Donation records unreachable publicly** — REST 404, direct `?p=` 404, excluded from search.
- **Validation matrix** — 11 hostile inputs, 11 correct rejections with correct codes and HTTP statuses.
- **Rate limiting** — fires at exactly 12 attempts per 15 minutes per IP.
- **Security headers** — 5 of 5 present; `X-Pingback` removed; XML-RPC disabled.
- **Accessibility baseline** — one `h1`, skip link, `<main>`, `lang`, five aria-labelled navs, zero `alt`-less images.
- **Build** — `npm run build` clean; eslint and stylelint both clean.
- **Nonce discipline** — no handler hand-rolls a nonce check; every one routes through the mandatory gate.

## 5. The twenty questions

| # | Question | Answer |
|---|---|---|
| 1 | What does it do? | Renders an NGO site from 8 CPTs and takes UGX donations via Flutterwave v3 |
| 2 | What should it do? | That, plus 11 editable blocks, newsletter, volunteer applications with uploads, gallery pagination and structured data |
| 3 | What is implemented? | The payment subsystem, CPTs, templates, security baseline, meta/OG SEO, build toolchain |
| 4 | What is missing? | The content layer, 3 endpoints, schema, all tests, all deployment infrastructure |
| 5 | Which APIs exist? | 2 AJAX + 1 REST webhook + 1 return handler + 1 shortcode + 1 cron |
| 6 | Which APIs are missing? | Newsletter, volunteer (+upload), gallery — registered, answering 501 |
| 7 | Which integrations are broken? | ACF Pro absent (blocks cannot register). Flutterwave unverified end to end |
| 8 | Which workflows are broken? | J6 (build homepage), J7, J8, J9. J2/J3 unverified |
| 9 | Which security vulnerabilities exist? | No exploitable vulnerability found. Two weaknesses: provider-message disclosure, unrate-limited status endpoint |
| 10 | Which data-integrity risks exist? | Non-atomic fulfilment (DEF-004); unbounded PII retention (DEF-011) |
| 11 | Which tests are missing? | All of them |
| 12 | Which tests are unreliable? | N/A — none exist |
| 13 | Which production failures are likely? | Silent webhook failure; duplicate receipts under load; an unrevertible bad deploy |
| 14 | What is the root cause? | Delivery stopped after architecture; nothing mechanical enforced the phase gates |
| 15 | How should each be fixed? | `remediation-roadmap.md` |
| 16 | In what order? | Phase 0 (VCS/CI) -> Phase 1 (tests + atomicity) -> then by dependency |
| 17 | How is each verified? | Per-case acceptance criteria in `test-plan.md` |
| 18 | What regression tests are needed? | 30 cases, prioritised into 5 waves |
| 19 | What stays risky after remediation? | The ACF Pro licence dependency; Flutterwave as a single point of failure; WP-Cron reliability |
| 20 | Is it production ready? | **No.** See below |

## 6. Release decision

# NO-GO

**One unresolved CRITICAL defect exists (DEF-001), which alone forces this result.** But the deeper reason is simpler than any single finding:

**The money path has never been observed working.** No Flutterwave sandbox credentials were available, so no donation was completed end to end in this audit. The code reads as correct and its edges probe as correct — but a payment system that has never taken a payment is not production ready, however good the code looks.

Add to that: the site cannot display its own content (DEF-002), three advertised features return 501 (DEF-003), fulfilment can double-fire under exactly the concurrency the architecture creates on purpose (DEF-004), and a production failure would be invisible (DEF-005).

### Minimum bar for CONDITIONAL GO

1. DEF-001 closed — repository and CI exist, gating merges.
2. DEF-004 closed — atomic fulfilment, proven by the concurrency test.
3. DEF-005 closed — production logging on, with alerts for webhook rejections, stuck records and cron failure.
4. Test waves 1–2 green.
5. **A full sandbox donation completed on both rails**, webhook delivery observed, exactly one receipt received.
6. `DEPLOYMENT.md` written and followed for the target environment.

Items 1–6 make the **payment surface** releasable. They do **not** make the site releasable — DEF-002 and DEF-003 still stand between this and a launchable product. If the goal is to launch the donation capability early on an otherwise simple site, that is a legitimate path, but it should be a deliberate decision rather than a discovery made at launch.

### What must not be misread

Nothing in this report says the payment code is bad. It is the strongest part of the codebase and, with the three defects above closed and one sandbox transaction observed, it would be genuinely trustworthy. The problem is not the engineering. It is that the engineering was never finished, never tested, and has nowhere to deploy from.

---

## Index of audit documents

| Document | Contents |
|---|---|
| `system-understanding.md` | Architecture, stack detection, data model, money flow, assumptions, unknowns |
| `system-inventory.md` | Feature-by-feature vertical map, authorization matrix, business invariants |
| `api-inventory.md` | Every endpoint with probed request/response/error contracts |
| `defect-register.md` | 23 findings with evidence, root cause, fix and regression test |
| `gap-register.md` | What is absent, by category, including dead-code recommendations |
| `risk-register.md` | 18 risks, SPOFs, chaos/failure-mode analysis |
| `traceability-matrix.md` | Requirement -> UI -> API -> backend -> DB -> test -> deploy |
| `configuration-audit.md` | Required constants, config gaps, dependency audit |
| `test-plan.md` | Strategy, environments, 30 prioritised test cases, exit criteria |
| `remediation-roadmap.md` | 9 phases with gates, rollback and acceptance criteria |
