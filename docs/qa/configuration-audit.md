# Configuration and Environment Audit

## 1. What configuration exists

| File | Purpose | State |
|---|---|---|
| `package.json` | Build, lint, format scripts | Complete and correct; **nothing executes it** (DEF-001) |
| `phpcs.xml.dist` | WPCS ruleset, `bgcf`/`BGCF` prefixes, WP 6.5 / PHP 8.0 floors | Well written; currently failing (DEF-014) |
| `eslint.config.mjs` | ESLint v9 flat config | Passing |
| `.stylelintrc.json` | SCSS rules | Passing |
| `.editorconfig` | Whitespace | Fine |
| `.gitignore` | 1,103 bytes | **Governs nothing — no git repository exists** |
| `composer.json` / `.lock` | phpcs + WPCS + PHPCompatibility, dev-only | Correct; `vendor/` is committed |
| `docker-compose.yml` | **Written by this audit.** Local QA harness only | Not a deployment artifact |

## 2. Required runtime configuration

### Documented (`PAYMENTS.md`)

| Constant | Required? | Consequence if missing |
|---|---|---|
| `BGCF_FLW_PUBLIC_KEY` | Yes | `bgcf_flw_is_configured()` false -> donation endpoint returns **503** |
| `BGCF_FLW_SECRET_KEY` | Yes | As above; also every API call returns `bgcf_flw_not_configured` |
| `BGCF_FLW_SECRET_HASH` | Yes | As above; **and every webhook 401s silently** if set but wrong (R-06) |
| `BGCF_FLW_ENCRYPTION_KEY` | No | Mapped in `bgcf_flw_credential()` but never read |

Resolution order is `define()` then `getenv()`. Options and ACF are deliberately never consulted — correct, and the rationale is documented in `inc/payments.php`.

**Verified live:** with all three absent, `bgcf_donation_start` returns `503 unavailable` and no donation record is created. The unconfigured state fails safe.

### Undocumented but required

| Setting | Why it matters | Status |
|---|---|---|
| `DISALLOW_FILE_EDIT` in `wp-config.php` | The theme defines it as a backstop, but a theme loads late | **CFG-01** — the `DEPLOYMENT.md` that should mandate it does not exist |
| `DISABLE_WP_CRON` + a real system cron | The 15-minute reconciliation is the safety net for lost webhooks; `wp-cron.php` only fires on traffic, and a low-traffic NGO site at 3 a.m. has none | **CFG-04** — undocumented anywhere |
| Page-cache exclusion for the donation page | Nonce expiry (DEF-007) | Documented in `PAYMENTS.md:175-179` only; not in an operator-facing document |
| SMTP (WP Mail SMTP) | `wp_mail()` on a bare host silently drops receipts | Named in `CLAUDE.md`; no configuration guidance |
| `WP_DEBUG` off, `WP_DEBUG_LOG` on | Currently, `WP_DEBUG` off means **no payment logging at all** (DEF-005) | Contradictory as designed |
| Webhook URL registered in the Flutterwave dashboard | `bgcf_donation_webhook_url()` computes it; someone must paste it | Documented in `PAYMENTS.md` |
| Thank-you page ID + `[bgcf_donation_result]` shortcode placed | Without it donors land on the home page after paying | Settings default is `0` — **silently degrades** |

## 3. Findings

### CFG-01 — `DEPLOYMENT.md` is referenced three times and does not exist
`inc/security.php` defers directory-listing suppression, HSTS and CSP to `DEPLOYMENT.md`, explaining correctly why a theme cannot own them. The file is absent, so three security controls have **no owner**. Severity: **MEDIUM**. Confirmed live: `Strict-Transport-Security` and `Content-Security-Policy` are both absent from responses.

### CFG-02 — No CSP or HSTS in any layer
Five security headers are correctly emitted (verified live). The two that require per-environment tuning are deferred to a document that does not exist. Severity: **MEDIUM**. Fix with CFG-01.

### CFG-03 — No `.env.example` or consolidated constants checklist
An operator provisioning a new environment has no single list. `PAYMENTS.md` covers the three Flutterwave constants; nothing covers the rest of §2 above. Severity: **MEDIUM**.

### CFG-04 — WP-Cron dependency is undocumented and unmonitored
The reconciliation job is what makes the payment flow correct when webhooks are lost. It depends on `wp-cron.php` being triggered by page traffic. Nothing documents switching to a system cron, and nothing alerts if the job stops. Severity: **MEDIUM**. This is the highest-consequence configuration gap in the audit, because its failure mode is invisible (see `risk-register.md`, chaos table).

### CFG-05 — Production logging is disabled by the default configuration
`bgcf_flw_logging_enabled` defaults to `WP_DEBUG`. Correct production settings therefore guarantee zero payment observability. Severity: **HIGH** (tracked as DEF-005).

### CFG-06 — No environment separation
No staging config, no per-environment constants, no feature flags. Mitigated by one genuinely good decision: **gateway mode is derived from the secret key prefix**, never from a setting, so test keys cannot be mistaken for live ones. `inc/payments.php` states the reasoning explicitly — "a checkbox that disagrees with the key is how test money ends up being treated as real money." Severity: **LOW**, because of that design.

### CFG-07 — `vendor/` is committed while `node_modules/` is not
Defensible for a PHP project with no Composer step at deploy time. Noted for consistency only. Severity: **INFO**.

## 4. Configuration drift

Cannot be assessed: there is one environment, no VCS history, and no reference configuration to compare against. Recorded as an **UNVERIFIED ASSUMPTION** rather than a pass.

## 5. Dependency audit

### Node (dev-only; nothing ships to the browser from `node_modules`)

| Package | Declared | Note |
|---|---|---|
| `sass` ^1.77, `esbuild` ^0.25, `postcss` ^8.4, `autoprefixer` ^10.4 | current majors | Fine |
| `eslint` ^9, `stylelint` ^16 | current majors | Fine |
| `bootstrap` ^5.3, `gsap` ^3.13, `swiper` ^11 | current majors | Copied to `assets/*/vendor/` by `build/copy-vendor.mjs` and **self-hosted** — no CDN, no hotlinking. Correct. |
| `sharp` ^0.34 | native binary | Only used by `images:webp`; a native-build failure would break that script, not the site |

`npm run build` completed cleanly in this environment (Node 24.18.0): vendor copy, three SCSS bundles, a 4.1 KB minified JS bundle.

### PHP (dev-only)

`squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`, `phpcompatibility/*`, `dealerdirect/phpcodesniffer-composer-installer`. All dev dependencies; **nothing from `vendor/` is loaded at runtime** (verified — `functions.php` requires only `inc/` modules). No runtime dependency risk.

### Not assessed

No CVE scan was run (`npm audit` / `composer audit` need network access that was out of scope). Since every dependency is build-time only and none reaches the browser or the server runtime, the exposure is limited to the build host. Recorded as an **UNVERIFIED ASSUMPTION**, not a pass.

### Version compatibility

`phpcs.xml.dist` sets `testVersion 8.0-` and `minimum_wp_version 6.5`. The theme ran cleanly against **WordPress 7.0.2 / PHP 8.3** with zero deprecation notices across 24 routes — a good forward-compatibility signal for a codebase targeting a 6.5 floor.
