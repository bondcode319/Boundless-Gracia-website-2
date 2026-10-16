# Implementation Plan — Boundless Gracia Children Foundation Website

Custom WordPress theme build. Companion documents: `CLAUDE.md` (standards,
brand, stack — the source of truth) and `.claude/agents/README.md` (subagent
roster). This plan is executed phase by phase; **each phase ends with a
qa-verifier review and a summary, and waits for user approval before the next
phase starts.**

---

## 1. Guiding Constraints (apply to every task)

- 100% original code, design, copy, and assets. No copying of the reference site.
- WPCS-compliant PHP 8+, prefix `bgcf_`, text domain `bgcf`.
- Escape on output, sanitize on input, nonces + capability checks everywhere.
- Zero hardcoded content — every visible string/image comes from ACF, CPTs,
  menus, or the customizer. ACF field groups version-controlled in `acf-json/`.
- WCAG 2.2 AA, 95+ Lighthouse, mobile-first responsive.
- Design tokens (SCSS vars + CSS custom properties) only — never raw hex in
  components.

## 2. Architecture Summary

**Theme:** `theme/` per the structure in CLAUDE.md. `functions.php` only
requires modules from `inc/`:

| Module | Responsibility |
|---|---|
| `inc/setup.php` | Theme supports, menus, image sizes, i18n |
| `inc/enqueue.php` | Versioned assets, conditional loading (GSAP/Swiper only where used) |
| `inc/cpt.php` | 8 CPTs + taxonomies, registered in code |
| `inc/acf.php` | ACF options pages, JSON sync, field helpers |
| `inc/blocks.php` | 11 ACF-based Gutenberg blocks (`bgcf/*`) |
| `inc/template-tags.php` | Reusable render helpers used by templates + blocks |
| `inc/security.php` | Hardening, upload validation, rate limiting, reCAPTCHA |
| `inc/seo.php` | JSON-LD schema, OG/Twitter meta, breadcrumbs |
| `inc/ajax.php` | Volunteer form, newsletter, gallery infinite scroll |
| `inc/admin.php` | Admin dashboard widgets, editor tweaks, roles |

**Data model:**

| CPT | Taxonomies | Key ACF fields |
|---|---|---|
| `bgcf_program` | Program Category | hero, overview, objectives, gallery, stats, FAQ, CTAs |
| `bgcf_event` | Event Type | date/time, location + map, registration, countdown |
| `bgcf_campaign` | — | goal, raised, deadline, GiveWP form link |
| `bgcf_story` | Program (shared) | before/after, quote, video, related program |
| `bgcf_team` | Department | role, bio, photo, socials |
| `bgcf_partner` | Partner Type | logo, URL |
| `bgcf_testimonial` | — | quote, rating, name, org, photo |
| `bgcf_report` | Year | PDF file, cover, summary |

**Blocks (all ACF blocks, shared render partials with templates):** Hero,
Statistics, CTA, Donation Banner, Program Cards, Gallery, Testimonials,
Timeline, Partners, FAQ, Newsletter.

**Plugins integrated:** ACF Pro, GiveWP, Contact Form 7, WP Mail SMTP, RankMath,
Wordfence, Polylang, WP Rocket–compatible output. WooCommerce not used
(GiveWP covers donations); `woocommerce/` folder reserved.

---

## 3. Phase Plan

### Phase 1 — Project Setup
**Owners:** wordpress-architect, devops-engineer, uiux-designer

1. Scaffold `theme/` (all root templates as working stubs, `style.css` header,
   thin `functions.php` + empty `inc/` modules). *(architect)*
2. Tooling: `package.json` (SCSS compile+minify, ES6 bundling, WebP script),
   PHPCS/WPCS, stylelint, eslint, `.gitignore`, `.editorconfig`. *(devops)*
3. Design tokens: full SCSS architecture (settings/tools/generic/elements/
   components/utilities), CSS custom properties, Poppins/Inter loading with
   `font-display: swap`, Bootstrap 5 grid/utilities-only import. *(uiux)*
4. Base components: buttons (primary/secondary/ghost), card shell, section
   wrapper, container widths, focus styles, skip link. *(uiux)*
5. `ARCHITECTURE.md` recording conventions. *(architect)*

**Exit criteria:** theme activates cleanly; build pipeline compiles; lint passes;
tokens render a styleguide test page. → qa-verifier gate.

### Phase 2 — Core Layout
**Owners:** theme-developer, uiux-designer, accessibility-specialist

1. Sticky header: transparent-over-hero variant, logo, primary nav, Donate +
   Volunteer buttons, Polylang language switcher, accessible search toggle.
2. Mobile navigation: drawer with focus trap, ESC close, `aria-expanded`.
3. Footer: 4-column (quick links, programs, contact, newsletter + socials),
   fully ACF-driven via options page.
4. Homepage shell (`front-page.php`) rendering block content; 404 and search
   templates.
5. Accessibility baseline: landmarks, skip link wired, keyboard pass on
   header/nav/footer.

**Exit criteria:** header/footer responsive at all breakpoints; keyboard-only
navigation works; no hardcoded strings. → qa-verifier gate.

### Phase 3 — Homepage Components
**Owners:** uiux-designer, gutenberg-acf-developer, theme-developer, content-strategist

Build all 14 homepage sections as reusable blocks/partials:

1. Hero (emotional image, overlay, dual CTAs, animated stats, floating cards).
2. Three Core Pillars cards (icon, image, description, hover lift).
3. About split section with animated counters.
4. Impact Statistics band (6 counters, `aria-live` safe, IntersectionObserver
   trigger).
5. Featured Programs grid (pulls `bgcf_program`).
6. Success Stories Swiper slider (before/after, quotes).
7. Donation Campaigns cards (progress bars, target/raised/days, GiveWP links).
8. Volunteer CTA full-width banner.
9. Testimonials carousel (ratings, photos, orgs).
10. Latest News (3 newest posts: image, category, date, excerpt).
11. Partners logo marquee (pauses on hover/focus, reduced-motion safe).
12. Newsletter form (AJAX, honeypot).
13. GSAP motion pass: scroll reveal, fade/slide, parallax, counters — all behind
    `prefers-reduced-motion`.
14. Sample homepage content loaded. *(content-strategist)*

**Exit criteria:** homepage fully composed from blocks in the editor; every
section editable in wp-admin; animations performant and reduced-motion
compliant. → qa-verifier gate.

### Phase 4 — Inner Pages
**Owners:** theme-developer, gutenberg-acf-developer, content-strategist

1. About (history timeline block, mission/vision, core values, leadership grid
   from `bgcf_team`, annual reports from `bgcf_report`, awards).
2. Programs archive + single (hero, overview, objectives, gallery, testimonials,
   stats, donate + volunteer CTAs, FAQ) — 4 seeded programs: Medical Support,
   Education, Women Empowerment, Community Development.
3. Projects page (campaign-driven grid).
4. Blog: archive with categories/tags/search, single with author box, reading
   time, share buttons, related posts, comments.
5. Events: upcoming/past split, single with countdown, Google Map, registration
   (CF7), add-to-Google-Calendar link.
6. Gallery: albums, photo/video items, filters, lightbox, infinite scroll (AJAX).
7. Success Stories archive + single (before/after, video, quote, donation CTA).
8. Contact: Google Map, department cards, CF7 form, phone/WhatsApp/email links,
   office hours, socials.
9. FAQ, Partners, Resources, Privacy Policy, Terms pages.
10. Sample content + placeholder image manifest for all pages.
    *(content-strategist)*

**Exit criteria:** every page in the sitemap renders with sample content; all
templates responsive and keyboard-operable. → qa-verifier gate.

### Phase 5 — CMS & Dynamic Content
**Owners:** gutenberg-acf-developer, wordpress-architect, theme-developer

1. Finalize all 8 CPT registrations + taxonomies; archive/single routing check.
2. Complete ACF field groups (per-CPT, per-block, options pages for header/
   footer/socials/global CTAs/SEO defaults); export everything to `acf-json/`.
3. Harden all 11 blocks: editor previews, empty states, validation.
4. Admin experience: dashboard welcome widget, menu ordering, editor color
   palette locked to brand tokens, role-appropriate capabilities.
5. Polylang: register strings, verify switcher, translatable ACF fields.
6. Verification sweep: grep templates for any literal content string — must be
   zero.

**Exit criteria:** a non-technical editor can change every section of every page
from wp-admin; ACF JSON in version control. → qa-verifier gate.

### Phase 6 — Donations & Volunteer
**Owners:** theme-developer, security-engineer, gutenberg-acf-developer

1. GiveWP: styled donation forms (theme CSS layer), one-time + recurring,
   campaign linkage, progress bars fed by GiveWP totals, receipts via WP Mail
   SMTP, donor wall (anonymous supported), Donate page + Donation Banner block
   wired.
2. Volunteer system: application form (skills, availability, location, resume
   upload), custom `bgcf_volunteer` submission storage, admin approval workflow
   (pending/approved/rejected + email notifications), confirmation email,
   background-check consent checkbox.
3. Security pass on both flows: nonce + capability checks, upload validation
   (MIME/extension/size, protected directory), rate limiting, honeypot +
   reCAPTCHA on volunteer/contact/newsletter forms. *(security-engineer)*

**Exit criteria:** end-to-end test-mode donation succeeds; volunteer
application → admin approval → emails all work; security-engineer signs off.
→ qa-verifier gate.

### Phase 7 — Performance & SEO
**Owners:** performance-engineer, seo-specialist, accessibility-specialist

1. Performance: critical CSS, defer non-critical JS, conditional GSAP/Swiper
   loading, WebP + `srcset`/`sizes`, explicit image dimensions (no CLS), hero
   LCP preload, font subsetting/preload, preconnects, WP Rocket + Cloudflare
   compatibility check.
2. SEO: JSON-LD (NGO Organization, WebSite, BreadcrumbList, Article, Event,
   Person, FAQPage, DonateAction), OG/Twitter meta with ACF fallbacks,
   canonical URLs, breadcrumbs partial, RankMath config doc, image SEO audit.
3. Accessibility polish: full WCAG 2.2 AA audit (contrast, focus order, ARIA,
   forms, carousels, reduced motion) with fixes applied.

**Exit criteria:** Lighthouse ≥95 (performance/SEO/best practices/accessibility)
on home, program single, blog single; schema validates; axe clean.
→ qa-verifier gate.

### Phase 8 — Testing & Deployment
**Owners:** qa-verifier, devops-engineer, security-engineer

1. Cross-browser (Chrome, Firefox, Safari, Edge) and responsive matrix (large
   desktop → mobile → foldable) pass.
2. Functional regression: all forms, donation flow, volunteer flow, search,
   comments, language switch, gallery infinite scroll.
3. Final security review: Wordfence config, headers, roles, file-edit disabled,
   secrets out of repo.
4. Documentation: `README.md` (theme), `DEPLOYMENT.md` (staging→production,
   required plugins + versions, SMTP/Maps/reCAPTCHA keys, Cloudflare + WP Rocket
   settings, backup/rollback, go-live checklist), editor handbook (how to edit
   each section).
5. Final Lighthouse + axe re-run; ship.

**Exit criteria:** all checklists green; documentation complete; final
qa-verifier PASS report. → project sign-off.

---

## 4. Dependencies & Sequencing

```
P1 Setup ──► P2 Layout ──► P3 Homepage ──► P4 Inner pages ──► P5 CMS ──► P6 Donate/Volunteer ──► P7 Perf/SEO ──► P8 Test/Deploy
                              ▲ CPT/ACF skeletons from P1 architect feed P3–P5
                              ▲ content-strategist runs in parallel from P3 onward
```

- CPTs/ACF are *skeletoned* early (Phase 1 architecture) so Phases 3–4 can bind
  real data, then *finalized* in Phase 5.
- Security review is continuous (every form as it's built) with a formal pass in
  Phases 6 and 8.
- Sample content is written in parallel from Phase 3 so pages are never
  demoed empty.

## 5. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| ACF Pro / GiveWP license availability in dev | Stub field data + GiveWP test mode; document license needs in DEPLOYMENT.md |
| Bootstrap fighting the design system | Import grid/utilities only; tokens override; stylelint rule against raw hex |
| Animation hurting Lighthouse/CLS | Conditional loading, transform/opacity only, reduced-motion, measure in P7 |
| Content editability regressions | Phase 5 grep sweep + qa-verifier gate check every phase |
| Scope creep on inner pages | Sitemap in this plan is the contract; changes go through orchestrator |

## 6. Definition of Done (project)

Complete original theme with 8 CPTs, 11 blocks, all pages from the sitemap,
working donation + volunteer systems, sample content and placeholder manifest,
WCAG 2.2 AA, ≥95 Lighthouse, hardened per the security checklist, and full
documentation (architecture, deployment, editor handbook) — every phase gate
approved by the user.

## 7. Image & Photography Sourcing Policy

Photography is **licensed stock or commissioned original work only**. No image,
photo, illustration, or icon may be copied from another organisation's website,
including any reference site used for structural inspiration. This protects the
foundation from copyright exposure and — because the subjects are children —
from safeguarding and consent liability.

**Interim (build phase):** every image slot is filled by the
content-strategist's placeholder-image manifest — filename, subject, aspect
ratio, and descriptive alt text per slot. Placeholders are neutral, correctly
sized, and clearly marked so the layout, `srcset`, and CLS behaviour can be
built and measured against real dimensions before final assets exist.

**Final (pre-launch):** placeholders are swapped for licensed or commissioned
photography. Accepted sources:

| Source | Notes |
|---|---|
| Commissioned photography | Preferred. Requires signed model/guardian consent for every identifiable child. |
| Paid stock (Getty, Adobe Stock, Stocksy) | Check the licence permits charity/fundraising use. |
| Free stock with commercial licence (Unsplash, Pexels) | Verify licence per asset; avoid recognisable minors. |
| Foundation's own archive | Only where consent is documented. |

**Rules for every agent:**

- Never hotlink. All assets are self-hosted and run through the WebP pipeline.
- Record source, licence type, licence URL, and consent status for every final
  asset in `assets/images/CREDITS.md`.
- No identifiable child appears without documented guardian consent.
- Alt text is written by content-strategist, not auto-generated from filenames.
- Placeholders must never reach production — Phase 8 go-live checklist includes
  a "zero placeholder assets remaining" gate.

**Owner:** content-strategist (manifest + alt text), devops-engineer (pipeline +
CREDITS.md enforcement), qa-verifier (Phase 8 gate).