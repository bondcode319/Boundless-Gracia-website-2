# Traceability Matrix

Requirement -> UI -> API -> Backend -> Database -> Integration -> Test -> Deployment.

Legend: **OK** implemented and verified · **PART** partially implemented · **NO** absent · **N/A** not applicable · **UNVERIF** implemented but not verifiable in this environment.

## Core requirements (`CLAUDE.md`)

| Requirement | UI | API | Backend | DB | Integration | Test | Deploy | Gap |
|---|---|---|---|---|---|---|---|---|
| Custom theme, PHP 8, WPCS, `bgcf_` prefix | OK | N/A | OK | N/A | N/A | **NO** | **NO** | DEF-014, DEF-001 |
| Modular `inc/`, single responsibility | N/A | N/A | **OK** | N/A | N/A | NO | NO | — |
| Escape on output, sanitize on input, nonces, capability checks | OK | OK | OK | N/A | N/A | **NO** | NO | DEF-013 |
| 8 public CPTs | OK | N/A | OK | OK | N/A | NO | NO | — |
| 11 Gutenberg blocks | **NO** | N/A | PART | N/A | **NO** (ACF Pro) | NO | NO | DEF-002 |
| ACF field groups in `acf-json/` | **NO** | N/A | PART | **NO** | **NO** | NO | NO | DEF-002 |
| Everything editable in wp-admin, zero hardcoded content | **NO** | N/A | PART | **NO** | NO | NO | NO | DEF-002 |
| Donations | OK | OK | **OK** | OK | **UNVERIF** | **NO** | **NO** | GAP-I4, DEF-004 |
| Volunteer forms + emails + admin workflow | **NO** | **501** | **NO** | **NO** | NO | NO | NO | DEF-003 |
| Newsletter | PART | **501** | **NO** | NO | NO | NO | NO | DEF-003 |
| Gallery pagination | PART | **501** | **NO** | NO | NO | NO | NO | DEF-003 |
| Accessibility (WCAG 2.2 AA) | **OK** (baseline verified) | N/A | OK | N/A | N/A | **NO** | NO | GAP-T6 |
| SEO: meta, OG, Twitter, canonical | **OK** | N/A | OK | N/A | N/A | NO | NO | — |
| SEO: schema.org / JSON-LD | **NO** | N/A | PART | N/A | NO | NO | NO | DEF-006 |
| Performance: 95+ Lighthouse | PART | N/A | OK | N/A | N/A | **NO** | NO | GAP-T7 |
| i18n, text domain `bgcf` | OK (calls) | N/A | OK | N/A | **NO** (no POT) | NO | NO | DEF-021 |
| Licensed imagery + `CREDITS.md` + consent log | **NO** | N/A | N/A | N/A | N/A | N/A | **NO** | DEF-021 |

## Payment requirements (`PAYMENTS.md`)

| Requirement | UI | API | Backend | DB | Integration | Test | Deploy |
|---|---|---|---|---|---|---|---|
| Server-generated `tx_ref` | N/A | N/A | **OK** | OK | N/A | **NO** | N/A |
| Value granted only after server-side verify | N/A | OK | **OK** | OK | UNVERIF | **NO** | N/A |
| Four-check verification gate | N/A | N/A | **OK** | OK | UNVERIF | **NO** | N/A |
| Fulfilment exactly once | N/A | N/A | **PART** | **NO** (no constraint) | N/A | **NO** | N/A |
| Webhook signature auth | N/A | **OK** | OK | N/A | **OK** (probed) | **NO** | PART (secret must be set) |
| Return-URL handling not trusted | N/A | OK | **OK** | OK | UNVERIF | NO | N/A |
| Reconciliation cron | N/A | N/A | OK | OK | UNVERIF | **NO** | **NO** (no real-cron guidance) |
| Secrets in constants, never the DB | N/A | N/A | **OK** | **OK** | OK | NO | PART |
| Rate limit + honeypot | OK | **OK** (probed) | OK | OK | N/A | NO | N/A |
| Mode derived from key prefix | N/A | N/A | **OK** | OK | OK | NO | N/A |
| UGX integer amounts, no minor units | OK | OK | **OK** | OK | UNVERIF | NO | N/A |
| Donor PII protected | N/A | **OK** (no PII in responses) | OK | **PART** (no retention/erasure) | N/A | NO | NO |

## Broken links — where the chain snaps

Reading each row left to right, the first **NO** is where the requirement dies:

1. **Blocks / ACF -> UI.** Backend dispatch exists; markup and field definitions do not. *Everything downstream of the CMS is blocked on this.*
2. **Newsletter / volunteer / gallery -> API.** Endpoints exist and answer 501. Front end is wired to contracts that will never be honoured.
3. **Schema -> UI.** Renderer complete, graph empty.
4. **Everything -> Test.** Not one requirement in either table has a test. This is the only column that is **NO** across the entire matrix.
5. **Everything -> Deploy.** Second-worst column. No VCS, no CI, no deployment document.

## Inverse check — implementation without a requirement

Searched for code that exists but nothing asks for. Findings:

| Item | Verdict |
|---|---|
| `theme/templates/page-styleguide.php` (257 LOC) | Legitimate developer tooling, not dead code. Verified rendering live at `/styleguide/` (200, 42 KB). |
| `theme/woocommerce/` | README only, documented as a deliberate override slot. Keep. |
| `bgcf_flw_credential('encryption_key')` | Mapped, never read. Harmless residue of the v3 API surface. |
| `bgcf_donation_setting('checkout_title')` and similar | All consumed. |

No orphaned services, unused models, unreferenced database fields or dead feature flags were found. Every meta key written is read somewhere. For a codebase of this size that is a genuinely good result and reflects the disciplined module boundaries noted in `system-understanding.md` §3.
