# Improvement report — v1.1.0 → v1.2.0

**Date:** 2026-10-08
**Scope:** the 23 functional and UI gaps found in the senior code scrape, plus
four defects from `docs/qa/` that the same work made it sensible to close.
**Verification:** `npm run lint` clean (stylelint + eslint), `npm run build`
clean, 65 PHP files brace-balanced, zero IDE diagnostics.
**Not verified:** nothing was run against a live WordPress install, and no
donation was completed end to end — see [§8](#8-what-is-still-not-verified).

---

## 1. Summary

The audit's own one-line verdict was that this was *"a well-architected
codebase that is roughly half built, and the half that is missing is the half
the client sees."* That is the half this work builds.

Before: 10 of 11 Gutenberg blocks rendered a grey placeholder, there were zero
ACF field groups, three advertised AJAX endpoints returned HTTP 501, the site
had **no navigation at all** below 1024px, and nothing structured shipped to
search engines. An editor could not build the homepage, and a phone user could
not find the donate button.

After: every block renders real, CMS-driven content; 23 field groups ship as
version-controlled JSON; the three endpoints are implemented, rate-limited and
consent-gated; the mobile drawer, submenu disclosures, search panel and language
switcher exist; and the JSON-LD extension point that had no producers now has
seven.

Three fixes matter more than the feature work, because each one was a live
failure rather than an absent feature:

| | What was wrong | Why it mattered |
|---|---|---|
| **A** | Every nonce was printed into HTML that a full-page cache serves for hours | A cached donate page silently stopped accepting donations ~12 h after it was cached. No error anywhere. Total conversion loss. |
| **B** | Fulfilment idempotency used `add_post_meta(…, $unique)` | Not atomic. Two simultaneous signals — which this architecture makes routine — could both "win", sending two receipts and double-counting every total. |
| **C** | Payment logging was gated on `WP_DEBUG` | A correctly configured production site had **zero** payment observability. A mistyped webhook secret would have failed silently for weeks. |

Net change: **+36 files, ~9,900 lines.** CSS 69 KB → 119 KB, JS 1 KB → 23 KB
(both minified, both still inside a 95+ Lighthouse budget).

---

## 2. UI gaps closed

### 2.1 Mobile navigation — was site-breaking

`_site-header.scss` hid the primary nav below `lg` and no drawer existed. On a
phone the site had two CTAs and nothing else.

- `inc/nav.php` — `Bgcf_Walker_Nav_Menu` emits a real
  `<button aria-expanded aria-controls>` **beside** each parent link, so the
  parent stays a navigable destination and the submenu becomes a standard
  disclosure. One markup shape serves the desktop dropdown and the mobile
  accordion; only the CSS differs.
- `partials/global/header-drawer.php` — off-canvas panel carrying the menu,
  search, both CTAs, contact details, the language switcher and social links.
- `assets/js/src/navigation.js` — focus trap, focus restoration, ESC and
  backdrop close, scroll lock, and a `matchMedia` listener that closes the
  drawer if the viewport grows past the desktop breakpoint.

**The decision worth knowing about:** the closed drawer is `hidden`, not
translated off-screen. A translated panel keeps every link in the tab order, so
a keyboard user tabbing the header falls into an invisible menu — the single
most common mobile-nav accessibility bug. The cost is that the entry transition
needs a frame between `hidden = false` and the open attribute, which
`requestAnimationFrame` provides.

### 2.2 Dropdown menus were dead on every viewport

`.sub-menu { display: none }` with `depth => 2` meant any second-level item an
editor added silently vanished. Now: the walker's button is the contract in both
the bar and the drawer; hover-opening is layered on top inside `@include t.hover`
so touch users never get a state they cannot leave; and with no JavaScript the
panel stays `hidden` while the parent link still works.

### 2.3 Ten placeholder blocks → ten real components

| Block | What it now does |
|---|---|
| Hero | Full-bleed, overlay strength clamped server-side to values that still pass contrast, LCP image eager + `fetchpriority="high"`, headline level is a field (h1 on the homepage, h2 on inner pages), stat row, floating highlight cards |
| Statistics | Counter band; **the final value is rendered server-side** and the count-up only overwrites it, restoring the exact server string on the last frame |
| CTA | Centred or split-with-image, three background options, inverse button treatment on dark surfaces |
| Program Cards | Three source modes (latest / curated / by category), `no_found_rows`, `publish`-only, hard-capped at 12 |
| Gallery | Album filters, AJAX load-more, accessible lightbox |
| Testimonials | Swiper carousel or grid, real prev/next buttons, opt-in autoplay with a pause control that never starts under reduced motion |
| Timeline | Ordered list, vertical or horizontal scroll-snap, decorative line in CSS only |
| Partners | Logo grid or CSS-only marquee, `object-fit: contain` so a trademark is never cropped |
| FAQ | APG disclosure pattern, deep-linkable, **and the data source for FAQPage JSON-LD** |
| Newsletter | Shares one form partial with the footer sign-up |

### 2.4 Icon system was an empty directory

`bgcf_get_svg_icon()` was well built — kses-allowlisted, cached,
`focusable="false"` — and `assets/images/icons/` contained only `.gitkeep`, so
every call returned `''`. Social links rendered as the plain words "Facebook",
"Twitter".

**47 icons** now ship, drawn for this project on a 24×24 grid: 40 stroke
interface icons and 7 brand marks. `bgcf_social_icon_for_url()` derives each
social icon from the link's host, so editors paste a URL into a normal WordPress
menu and never pick an icon — and a typo degrades to a visible text label rather
than a wrong logo. The text label stays in the DOM and is hidden only where an
icon actually rendered, so the accessible name is always the network name.

### 2.5 Promised header and footer furniture

`bgcf_header_actions` and `bgcf_footer_contact` were fired and nothing attached.
Now shipped: sticky header with a compact scrolled state, search disclosure
panel, Polylang/WPML language switcher, menu button, and a footer contact column
with address, tap-to-call phone, email, hours and the newsletter sign-up.

### 2.6 Animation layer

GSAP, ScrollTrigger and Swiper were vendored and registered, and nothing ever
called `bgcf_use_gsap()` or `bgcf_use_swiper()` — ~200 KB of unused JS in the
repo. Now: `motion.js` (IntersectionObserver reveals + counters),
`carousel.js` (Swiper, enqueued on demand by the testimonials block only).

Reveals arm only when `html[data-bgcf-motion="on"]`, which `main.js` sets from
live `matchMedia`. The SCSS keys its `opacity: 0` off the same attribute, so a
no-JS or reduced-motion visitor never meets an invisible page.

### 2.7 Donation form conversion work

Was functional but bare. Added, all server-driven so what the donor reads is
what gets charged:

- **Impact lines** — "UGX 50,000 feeds a child for a month". Every line is in
  the HTML and all but the matching one is `hidden`, so the copy is indexable and
  a no-JS donor still sees the line for the pre-selected amount.
- **Fee cover** — opt-in, with a live total. `amount` stays the gift (what the
  campaign raised, what the receipt calls the donation); `charged` is what leaves
  the account. Keeping them apart is what stops the fee being counted as income.
- **Campaign progress bar** — computed from the donation records, cached in a
  transient that completed gifts flush. **There is deliberately no field for a
  "raised" total**, and the ACF group says so: a typed total goes stale and
  overstates what the charity has received, which is a compliance problem.

### 2.8 Donation form collected PII with no consent

Name, email and phone were collected with no consent checkbox and no privacy
link — while the newsletter block's own docblock had mandated exactly that since
Phase 1. The form handling money was the laxer of the two. Now gated by
`consent_required` (default on) and validated server-side.

### 2.9 Inner-page templates

`templates/` held only `page-styleguide.php`. Added `page-contact.php` (details
beside a working form, plus a click-to-load map), `page-volunteer.php`, and
`archive-bgcf_event.php` with a genuine upcoming/past split.

**On the events archive:** the default archive ordered by publish date, which
for events is meaningless — an event published last week may be eight months
away. Worse, leading with a finished event tells every visitor the charity is
dormant. Two queries now run against `start_date` as `DATETIME`: upcoming
ascending, past descending and capped.

**On the map:** an always-embedded Google Map costs ~900 KB and sets third-party
cookies before anyone consents to anything. The placeholder shows the address
and a button; the iframe is created on click. Visitors who just want the phone
number never pay for a map they did not look at — and the page needs no cookie
banner on the map's account.

---

## 3. Function gaps closed

### 3.1 Three 501 endpoints, implemented

`bgcf_newsletter_subscribe`, `bgcf_volunteer_submit` and
`bgcf_gallery_load_more` were registered, documented and returned
`not_implemented`. All three now follow THE PATTERN from `inc/ajax.php`, and
`bgcf_contact_submit` was added alongside them.

New supporting modules: `inc/leads.php` (subscriber / volunteer / message
records), `inc/leads-admin.php` (columns, status workflow, CSV export),
`inc/gallery.php` (album taxonomy + the query).

**Gallery items are attachments grouped by a taxonomy, not ACF repeater rows.**
A repeater cannot be paginated, filtered or counted without loading the whole
field, which makes load-more and album filters impossible at any real size, and
it buries the photos in one page's post meta where nothing else can reuse them.

**Résumé uploads get three checks**, because any one alone is bypassable: size
before anything is read; `wp_check_filetype_and_ext()` against the file's actual
magic bytes (not the extension, not the browser-supplied Content-Type, both of
which the client controls); and an allow-list intersection. The file is stored
with a randomised name — a predictable one lets anyone who guesses the URL read
another applicant's CV.

### 3.2 (A) The nonce / page-cache failure

`request.js` now recovers from a stale nonce: on `bgcf_invalid_nonce` it fetches
a fresh one from `/wp-json/bgcf/v1/nonce`, replaces it in `bgcfData` **and in
every form's hidden field**, and replays the request exactly once. A second
failure is a real failure and is surfaced.

Only one refresh is ever in flight however many forms fail at once — the promise
is memoised, so ten simultaneous failures produce one network call. The endpoint
is a REST route rather than admin-ajax because hosts and CDNs are far more likely
to have `/wp-json/` already excluded from caching, and the response is
explicitly `no-store` regardless. It discloses nothing: a nonce is bound to the
session that produced it.

### 3.3 (B) Non-atomic fulfilment guard — `docs/qa/` DEF-004

`add_post_meta( …, $unique = true )` looks atomic and is not: WordPress
implements it as a SELECT followed by an INSERT, with nothing but PHP between
them and no unique constraint on `wp_postmeta` to catch a loser.

`bgcf_donation_claim_fulfilment()` uses `add_option()` instead, because
`wp_options.option_name` carries a UNIQUE index — the database itself rejects the
second INSERT. The `notoptions` cache is cleared first, or a concurrent process
would be served a cached miss and skip the write before it reached the database,
putting the race back where it started. The meta row survives as the audit trail.

### 3.4 (C) Payment observability — `docs/qa/` DEF-005

Logging was entirely behind `WP_DEBUG`. Seven money-at-risk event types are now
recorded unconditionally (rejected webhooks, verification mismatches,
chargebacks, missing redirects, request failures); everything else stays behind
the debug flag, because logging every successful API call fills a disk.

Critical events also land in a 20-entry ring buffer in an option, which the
dashboard widget prints and a new **Site Health test** evaluates. "The webhook
secret has been wrong since Tuesday" is now something a fundraiser can see,
rather than a line in a log on a server they cannot reach.

`inc/payments-health.php` also adds a readiness check (keys, webhook secret,
cron schedule, thank-you page, enabled methods, sandbox mode) shown as an admin
notice on the dashboard and donation screens only — a notice on every page is one
people learn to dismiss without reading.

### 3.5 Double-submit created two charges — `docs/qa/` GAP-V4

Guarded only by a client-side `data-bgcf-busy` attribute, which cannot see
another tab. Two tabs produced two donation records and two mobile-money prompts,
and Flutterwave's own duplicate protection did not apply because the references
genuinely differed.

The browser now generates an idempotency key per attempt and reuses it on
retries; the server replays the original response for a known key instead of
charging again. The key is scoped to the session as well as the key itself, so
one donor's key can never return another's redirect, and it is length-bounded.
A genuinely new attempt gets a new key.

### 3.6 Chargebacks and refunds

The `bgcf_flutterwave_webhook` hook existed and nothing listened. Disputes now
flag the donation, clear the campaign cache, email the team and fire
`bgcf_donation_disputed` — and disputed gifts are excluded from campaign totals
in SQL. The status is **not** rewritten automatically: a disputed gift is a
decision for a human with the bank's correspondence in front of them.

### 3.7 Structured data — `docs/qa/` DEF-006

`bgcf_seo_render_schema()` was a complete, correct extension point with zero
producers. `inc/seo-schema.php` adds: `NGO`, `WebSite` + `SearchAction`,
`BreadcrumbList`, and per-type nodes — `Event` (with dates, venue and a
`VirtualLocation` fallback, because Google requires a location), `Service`,
`Report`, `DonateAction`, `WebPage`, `Article`. Plus `FAQPage`.

**Two graphs, for one unavoidable reason.** Most nodes can be built in
`wp_head`. FAQ nodes cannot: their data lives in blocks that have not rendered
yet. Those are collected during the body render and emitted in `wp_footer`.
JSON-LD is valid in either position, and splitting it is honest about when the
data exists.

The breadcrumb node reads `bgcf_get_breadcrumb_items()` — the same list the
visible trail renders. Building it twice is how a page ends up with structured
data describing a path the page does not show.

Also added: `og:image:width/height/alt`, `twitter:image`, `twitter:site`,
`article:published_time`, `article:modified_time`. Image dimensions matter more
than they look — without them a crawler must fetch and measure the image before
laying out the card, which is the most common reason a correct `og:image`
"does not work" on first share.

### 3.8 Fonts were self-hosted but never preloaded

`bgcf_resource_hints()` was an empty passthrough. Two faces are now preloaded —
only the ones used above the fold, only the Latin subsets, and only if the file
actually exists on disk. `crossorigin` is mandatory even same-origin, or the
preload is fetched in a different mode than the CSS request and the file
downloads twice.

### 3.9 `window.bgcf.register()` was a no-op after boot

`main.js` exposed `register()` so later scripts could add modules, but
`ready(boot)` runs immediately under `defer`, so anything registered afterwards
never executed. Every future phase using the documented extension point would
have silently done nothing. Late registrations now run immediately.

### 3.10 `reducedMotion` was hardcoded `false` server-side

The server cannot know this, and a cached page would pin one visitor's answer
for everyone. Removed from `bgcfData` with a comment saying why; `main.js` is now
the single authority.

### 3.11 Donation admin tooling

Added: CSV export for subscribers, volunteers, messages and donations —
streamed in batches through `php://output` with the post cache flushed between
them, because a charity with 50,000 subscribers would otherwise exhaust the
memory limit and the failure mode is a blank page with no explanation. Plus a
volunteer status workflow with an audit log of who changed what.

**CSV formula injection is neutralised.** A donor name of
`=HYPERLINK("http://evil","Click")` is executed by Excel and Google Sheets when
the export is opened, and this data came from public forms. A UTF-8 BOM is
written too — without it Excel on Windows mangles every non-ASCII name, and
Ugandan names are full of characters it gets wrong.

### 3.12 Privacy — `docs/qa/` DEF-011

The theme stored personal data in four places WordPress knew nothing about, so
Tools → Export Personal Data returned nothing and the organisation would have
answered a lawful request with a false negative. Exporters and erasers are now
registered for all four, plus a retention cron.

**Erasure is not deletion, for donations.** A completed gift is a financial
record the charity must keep. An erasure request anonymises the donor on the
record — amount, date and reference survive; name, email, phone and message do
not — and the response says so explicitly rather than leaving anyone to guess.
Leads carry no such obligation and are deleted outright, including the uploaded
CV: an orphaned file in uploads is the most common way an "erased" application
stays readable by anyone with the URL.

### 3.13 ACF field groups — `docs/qa/` GAP-F2

`acf-json/` contained only `.gitkeep`, so even the one implemented block fell to
its empty state. **23 field groups** now ship: 11 block groups, 4 options pages,
8 post types.

They are generated by `build/acf-fields.mjs` from one readable definition. The
JSON is the artifact because ACF rewrites those files when an editor saves a
group — PHP `acf_add_local_field_group()` calls would render read-only in the
admin and make every field change a code deploy. Hand-writing them is the
problem the generator solves: one text field is ~20 lines of JSON with four keys
that must stay unique forever.

Block groups bind under **both** namespaces (`bgcf/hero` and `acf/hero`), since
ACF 6 preserves an explicit namespace and ACF 5 rewrites it — so the groups bind
correctly without anyone checking which version is installed. Conditional logic
is authored against field names and resolved to field keys at build time.

### 3.14 Translation catalogue — `docs/qa/` DEF-021

`languages/` held only `.gitkeep` despite a fully `__()`-wrapped codebase and a
multilingual requirement. **678 strings** now extracted to `bgcf.pot` with
translator comments, plurals, contexts and source references.

`build/makepot.mjs` is a small scanner, not a parser, and says so in its header.
The canonical tool is `wp i18n make-pot`, which needs WP-CLI and therefore PHP;
this toolchain is Node-only, so a translator would otherwise have had nothing at
all. The output format is deliberately identical, so swapping in `make-pot`
later changes nothing downstream.

### 3.15 Imagery policy

`CREDITS.md` was mandated by `CLAUDE.md` and absent. Added, along with
`IMAGE-MANIFEST.md` — every image slot with its crop, subject brief, alt-text
rule and consent requirement, written as a shopping list for whoever licenses
the photography.

**Safeguarding is enforced in the query, not on a checklist.** Gallery
attachments carry a `_bgcf_consent` flag, set through a media-library field, and
`bgcf_gallery_query()` excludes anything uncleared. An uncleared photo cannot
reach the markup even if an editor adds it to an album.

The theme still ships **zero bitmap images**, deliberately: placeholders that
could reach production by accident are worse than empty slots that render an
editor-only notice.

---

## 4. Files

**New PHP (10):** `inc/nav.php`, `inc/gallery.php`, `inc/leads.php`,
`inc/leads-admin.php`, `inc/campaigns.php`, `inc/privacy.php`,
`inc/seo-schema.php`, `inc/payments-health.php`, `archive-bgcf_event.php`,
`templates/page-contact.php`, `templates/page-volunteer.php`

**New partials (7):** `header-drawer`, `header-search`, `footer-contact`,
`newsletter-form`, `contact-form`, `volunteer-form`, `gallery-item`,
`content/content-event`

**New JS (7):** `request.js`, `navigation.js`, `motion.js`, `carousel.js`,
`accordion.js`, `forms.js`, `gallery.js`, `map.js`

**New SCSS (11):** `_grid`, `_drawer`, `_hero`, `_stats`, `_cta`, `_timeline`,
`_carousel`, `_partners`, `_faq`, `_gallery`, `_progress`, `_form`, `_contact`

**New build scripts (2):** `build/acf-fields.mjs`, `build/makepot.mjs`
(`npm run build:acf`, `npm run build:pot`, both wired into `npm run build`)

**Assets:** 47 SVG icons, 23 ACF field groups, 1 POT catalogue

---

## 5. Accessibility decisions

Collected here because several look like over-engineering until the reason is
stated:

| Decision | Why |
|---|---|
| Drawer closed with `hidden`, not `translate` | A translated panel keeps links in the tab order; a keyboard user falls into an invisible menu |
| Submenu button **beside** the parent link, not wrapping it | The parent stays a navigable destination and the control is a separate stop in one focus order |
| Counter's final value rendered server-side | Animating from zero with no value in the markup tells assistive tech the charity has helped 0 children |
| Stats DOM order is label-then-value, reversed in CSS | A screen reader hears "children reached: 12,400", not a number with no context |
| `aria-pressed` used as the CSS selector for chips | A separate `.is-active` class can drift from what is announced |
| Progress bar is `aria-hidden` with no `progressbar` role | Every number it encodes is in the text below; the role would announce the same fact three times |
| Marquee pauses on `:hover` **and** `:focus-within` | Without the second, a keyboard user tabbing the logos chases a moving target (WCAG 2.2.2) |
| Carousel autoplay stops permanently on `focusin` | Silently resuming moves content out from under a keyboard user |
| Per-card unique accessible names on repeated CTAs | Eight identical "Register free" links give a screen-reader user no way to tell events apart |
| Success panel takes focus | The element being interacted with was just removed; focus would otherwise fall to `<body>` and announce nothing |
| Rating rendered as text, not five star icons | A screen reader would read five identical graphics one by one |
| Honeypot off-screen, not `display: none` | A good number of form-filling bots skip `display: none` fields, which defeats the trap |

---

## 6. Performance notes

- Swiper (~140 KB) loads only on pages with a carousel block; GSAP is registered
  and still unenqueued until a block needs it.
- Two font faces preloaded; the extended subsets and unused weights are not.
- Map iframe is click-to-load: ~900 KB and all third-party cookies deferred.
- Hero image: `loading="eager"`, `fetchpriority="high"`, `sizes="100vw"`.
- Campaign totals are one aggregate SQL query behind a transient that completed
  gifts flush, rather than loading every donation record to produce one integer.
- `aspect-ratio` on the map container and gallery images, so neither causes
  layout shift.
- CSS 119 KB / JS 23 KB minified, both pre-gzip.

---

## 7. Known trade-offs

1. **The nav menu is rendered twice** (desktop bar + drawer). One list cannot be
   both a horizontal bar and an off-canvas accordion without CSS that fights
   itself. Exactly one copy is `display: none` at any breakpoint, so assistive
   technology never meets the same menu twice — but it is two renders, and
   `header.php` says so.
2. **`build/makepot.mjs` is a lexer, not a parser.** It handles the gettext
   calls this codebase actually uses. Replace it with `wp i18n make-pot` the
   moment WP-CLI is available.
3. **Fee-cover percentages are configuration, not an API call.** If Flutterwave
   changes its Uganda pricing, the number under Donations → Settings has to be
   updated or the "total today" shown to donors drifts from what is charged.
4. **Disputed donations are flagged, not reversed.** Deliberate; it needs a human.
5. **The gallery needs editors to tag albums and tick consent.** That is the cost
   of making safeguarding enforceable in the query rather than in a checklist.

---

## 8. What is still not verified

Stated plainly, because the audit's sharpest finding was that the money path had
never been watched working:

- **No live WordPress run.** No PHP runtime was available in this environment
  (no CLI, Docker daemon down). Verification was: all linters, a full build,
  brace-balance across 65 PHP files, JSON validity across 23 field groups, and
  zero IDE diagnostics. That catches syntax and style. It does not catch a
  wrong hook name or a wrong meta key.
- **No donation completed end to end.** Still the largest untested surface in
  the product, now with fee cover and idempotency layered on top of it.
- **No tests.** `docs/qa/` GAP-T1–T7 are untouched. The highest-value targets
  are unchanged and are now more valuable: the verification gate, the status
  machine, MSISDN normalisation, the payload hash — and now
  `bgcf_donation_claim_fulfilment()` under concurrency and
  `bgcf_fee_for_amount()` at the cap boundary.
- **No Lighthouse measurement.** The budget is respected by construction, not by
  measurement.
- **ACF Pro is still absent**, so no block has been rendered by ACF. The field
  groups are schema-valid JSON; they have not been imported.

### Recommended next steps

1. Stand up a local WordPress with ACF Pro. Import the field groups, place every
   block on a page, click through the drawer and the forms. **This is the gate.**
2. Flutterwave sandbox credentials, then complete one mobile-money and one card
   donation — with fee cover on and off, and with a deliberate double-submit.
3. `git init`. `docs/qa/` DEF-001 is still open and is still the critical
   finding: this code charges cards with no revert, no review and no audit trail.
4. Write `DEPLOYMENT.md`. `inc/security.php` refers to it three times as the home
   of mandatory server hardening and it does not exist, so those controls have
   no owner.
5. The six unit tests listed above, then axe and Lighthouse in CI.

### One documentation contradiction left alone

`CLAUDE.md` still names GiveWP for donations; the build uses Flutterwave
(`PAYMENTS.md`). This is `docs/qa/` DEF-023. `CLAUDE.md` is the project's own
instruction file, so it was not edited here — but it should be corrected, because
it currently tells any new contributor to integrate a plugin this theme
deliberately does not use.
