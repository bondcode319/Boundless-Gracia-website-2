

# ARCHITECTURE — Boundless Gracia Children Foundation theme

The conventions contract. `CLAUDE.md` says *what* to build and to what standard;
`PLAN.md` says *when*. This document says *how*, and it is binding on every
agent and every phase.

If you are about to invent a name, a folder, an escaping habit or a class
convention: it is probably already decided here. If it genuinely is not, add it
here in the same pull request that introduces it.

---

## 1. Directory map

```
Boundless_website/
├─ CLAUDE.md               Standards, brand, stack (source of truth)
├─ PLAN.md                 Phase plan and data model
├─ ARCHITECTURE.md         This file — conventions contract
├─ PAYMENTS.md             Flutterwave setup, sandbox testing, go-live gate
├─ package.json            Build scripts + JS/CSS toolchain (see §13)
├─ composer.json           PHPCS + WPCS + PHPCompatibilityWP (dev only)
├─ phpcs.xml.dist          WPCS ruleset: prefixes, text domain, PHP 8.0+
├─ .stylelintrc.json       SCSS lint rules (rationale in §13)
├─ eslint.config.mjs       Flat config: theme front-end + build scripts
├─ .editorconfig           Tabs, 4 wide, LF, UTF-8
├─ .gitignore              node_modules, secrets; build artefacts ARE committed
├─ build/                  Node build scripts (ESM, run via npm)
│  ├─ copy-vendor.mjs      node_modules → theme/assets/*/vendor/
│  ├─ webp.mjs             WebP derivatives + CREDITS.md audit
│  └─ clean.mjs            Remove build artefacts only
└─ theme/
   ├─ style.css            WordPress theme header ONLY. Never add styles here.
   ├─ functions.php        Constants + module loader. No business logic, ever.
   ├─ index.php            Fallback loop
   ├─ front-page.php       Homepage (renders the static front page's blocks)
   ├─ page.php             Single page
   ├─ single.php           Single post + default single for every CPT
   ├─ archive.php          Post type / taxonomy / date / author archives
   ├─ search.php           Search results
   ├─ searchform.php       get_search_form() override
   ├─ 404.php              Not found
   ├─ header.php           Opens <html>…<main>
   ├─ footer.php           Closes </main>…</html>
   ├─ sidebar.php          Blog widget area (self-guarding)
   ├─ comments.php         Comments + comment form
   ├─ inc/                 PHP modules, one responsibility each
   │  ├─ setup.php         Supports, menus, image sizes, widget areas, i18n
   │  ├─ template-tags.php Render helpers (loaded early, no side effects)
   │  ├─ security.php      Hardening + bgcf_verify_request()
   │  ├─ cpt.php           8 post types + 5 taxonomies
   │  ├─ acf.php           JSON sync, options pages, bgcf_field()/bgcf_option()
   │  ├─ blocks.php        bgcf/* block registry + shared render callback
   │  ├─ enqueue.php       All asset registration and enqueueing
   │  ├─ seo.php           Canonical, OG/Twitter, JSON-LD pipe
   │  ├─ ajax.php          admin-ajax endpoints (guard → sanitize → act → respond)
   │  ├─ payments.php      Flutterwave API v3 client (see PAYMENTS.md)
   │  ├─ donations.php     Donation records, settings, verification, fulfilment
   │  ├─ payments-routes.php  AJAX, webhook, return URL, reconciliation cron
   │  ├─ payments-admin.php   Donation settings screen, list table, detail
   │  └─ admin.php         Dashboard, menu order, login screen, editor tweaks
   ├─ templates/           Page templates (Template Name: …) — Phase 4
   │  └─ page-styleguide.php  Token/component proof. Dev only — remove at launch.
   ├─ partials/
   │  ├─ blocks/           block-{slug}.php — one per registered block
   │  ├─ content/          content.php, content-page.php, content-single.php,
   │  │                    content-search.php, content-none.php
   │  └─ global/           Cross-cutting parts (header/footer fragments) — Phase 2
   ├─ assets/
   │  ├─ scss/             Authored styles (uiux-designer)
   │  ├─ css/              Compiled output — build artefact, never hand-edited
   │  │                    main.css, editor.css, login.css, vendor/
   │  ├─ js/
   │  │  ├─ src/           Authored JS — the ONLY hand-edited JS in the theme
   │  │  ├─ main.js        esbuild bundle of src/main.js — build artefact
   │  │  └─ vendor/        Copied libraries — build artefact
   │  ├─ images/           Self-hosted imagery; icons/ holds inline SVG icons
   │  └─ fonts/            Self-hosted Poppins + Inter subsets
   ├─ acf-json/            Version-controlled field groups (source of truth)
   ├─ languages/           .pot / .po / .mo
   └─ woocommerce/         Reserved and empty — see its README.md
```

**Rules.**

- `assets/css/`, `assets/js/main.js` and `assets/js/vendor/` are build artefacts.
  Never edit them by hand; never commit a fix there that belongs in
  `assets/scss/` or `assets/js/src/`. They *are* committed (see §13) so the
  `theme/` folder stays deployable without a build step on the server.
- A file in `partials/` never queries the outside world for its own arguments —
  it receives `$args`. Templates decide, partials render.
- `templates/` is for files with a `Template Name:` header. Nothing else.

---

## 2. Module load order

`functions.php` loops over one array and requires each module with a
`file_exists()` guard. The order is a dependency order, not alphabetical:

| # | Module | Why here |
|---|---|---|
| 1 | `setup.php` | Declares what the theme is. Everything else assumes supports, menus and image sizes exist. |
| 2 | `template-tags.php` | Pure functions, zero side effects. Loaded early so every later module and every template can call them. |
| 3 | `security.php` | Hardening applies before any public surface is registered. Also defines `bgcf_verify_request()`, used by `ajax.php`. |
| 4 | `cpt.php` | Post types and taxonomies. ACF location rules and block queries bind to these. |
| 5 | `acf.php` | Options pages and `bgcf_field()` / `bgcf_option()`. |
| 6 | `blocks.php` | Needs ACF and the template tags. |
| 7 | `enqueue.php` | Asset registration; may reference block-specific handles. |
| 8 | `seo.php` | Reads CPT and ACF data, so it loads after both. |
| 9 | `ajax.php` | Uses the security helper and the CPTs. |
| 10 | `payments.php` | Flutterwave API v3 client. Pure functions over HTTP; the only module that reads a gateway secret. No WordPress state of its own. |
| 11 | `donations.php` | Donation records (CPT `bgcf_donation`), settings, status machine, verification rules, fulfilment. Needs `payments.php` for references and mode. |
| 12 | `payments-routes.php` | The public payment surface: AJAX start/status, REST webhook, return-URL handler, result shortcode, reconciliation cron. Needs `ajax.php` (guards), `payments.php` and `donations.php`. |
| 13 | `payments-admin.php` | Donation settings screen, list table, record detail. Needs `donations.php` and `payments-routes.php` (cron hook constant). |
| 14 | `admin.php` | Admin-only UX; loads last so it can reorder menus the earlier modules created. |

**To add a module:** create `inc/{name}.php`, add its basename to the
`$bgcf_modules` array in the correct dependency position, and add a row above.
Do not `require` a module from inside another module.

Everything is hooked, so load order only matters for function availability and
for hook registration order at equal priority. Keep it that way — no module may
*do* anything at require time except define functions and add hooks.

---

## 3. Naming conventions

### PHP

| Thing | Convention | Example |
|---|---|---|
| Function | `bgcf_` + snake_case, verb-led | `bgcf_register_post_types()` |
| Getter that returns | `bgcf_get_*` | `bgcf_get_button()` |
| Renderer that echoes | `bgcf_the_*` or a bare verb | `bgcf_the_post_thumbnail()`, `bgcf_button()` |
| Module-private helper | `bgcf_{module}_{verb}` | `bgcf_security_send_headers()` |
| Global / constant | `BGCF_` upper snake | `BGCF_ASSETS` |
| Template-scope variable | `$bgcf_` prefix | `$bgcf_section` |
| Text domain | `bgcf`, always literal | `__( 'Donate', 'bgcf' )` |

Template-scope variables **must** carry the `$bgcf_` prefix. Template files share
the global scope with WordPress core and every active plugin; an unprefixed
`$section` or `$image` is a live collision waiting to happen.

Every user-facing string is translated. No exceptions, including admin notices,
`aria-label` values and button text.

### Hooks

- Theme hooks are `bgcf_` snake_case: `bgcf_button_args`, `bgcf_schema_graph`.
- Template injection points read as positions: `bgcf_before_header`,
  `bgcf_after_header`, `bgcf_before_main_content`, `bgcf_after_main_content`,
  `bgcf_before_footer`, `bgcf_after_footer`, `bgcf_header_actions`,
  `bgcf_footer_contact`, `bgcf_section_start`, `bgcf_section_end`.
- Every filter this theme declares is documented with a `@since` and a
  `@param` block. If it is not documented, it is not a public hook.

### Asset handles

`bgcf-{name}`, in both the script and the style registry.

| Handle | File | Loaded |
|---|---|---|
| `bgcf-main` (style) | `assets/css/main.css` | Every front-end page |
| `bgcf-app` (script) | `assets/js/main.js` | Every front-end page, deferred |
| `bgcf-editor` (style) | `assets/css/editor.css` | Block editor |
| `bgcf-login` (style) | `assets/css/login.css` | Login screen |
| `bgcf-gsap` (script) | `assets/js/vendor/gsap.min.js` | On demand — `bgcf_use_gsap()` |
| `bgcf-gsap-scrolltrigger` | `assets/js/vendor/scroll-trigger.min.js` | On demand |
| `bgcf-swiper` (script + style) | `assets/js/vendor/swiper-bundle.min.js`, `assets/css/vendor/swiper-bundle.min.css` | On demand — `bgcf_use_swiper()` |

Vendor libraries keep the `bgcf-` prefix so a plugin shipping its own GSAP cannot
collide with ours. `bgcf-swiper` intentionally names both a script and a style;
WordPress keeps those registries separate.

`inc/enqueue.php` is the **only** file permitted to call `wp_enqueue_style()` or
`wp_enqueue_script()` with a URL. Templates and blocks opt in through
`bgcf_use_gsap()` / `bgcf_use_swiper()`. No `<script>` or `<style>` tags in
templates, ever.

### CSS classes — BEM with a namespace

`bgcf-block__element--modifier`.

- Every class the theme authors starts with `bgcf-`. No exceptions, no
  unprefixed utility classes of our own.
- Block (BEM) = component, not Gutenberg block. Gutenberg blocks get
  `.bgcf-block-{slug}` as their root, e.g. `.bgcf-block-hero`.
- Elements use `__`, modifiers use `--`. One `__` level maximum; if you need
  two, you have found a new component.
- State classes are modifiers, not bare words: `.bgcf-nav--open`, never `.open`.
- JavaScript hooks are **data attributes**, not classes: `data-bgcf-header`,
  `data-bgcf-carousel`. A class is for styling; renaming one must never break
  behaviour.
- Bootstrap 5 is imported for **grid and utilities only**. Bootstrap utility
  classes may be used for layout (`row`, `col-*`, spacing helpers). Bootstrap
  component classes (`.btn`, `.card`, `.navbar`) must never appear in our
  markup — we have `.bgcf-btn` and `.bgcf-card`.

**Structural classes emitted by PHP** (the design system must style all of
these; changing a name here is a coordinated change in both places):

```
bgcf-skip-link  bgcf-site  bgcf-main  bgcf-container  bgcf-container--{default|wide|narrow|full}
bgcf-section  bgcf-section--bg-{none|base|surface|primary|secondary}  bgcf-section--space-{none|sm|md|lg}
bgcf-section-heading  bgcf-section-heading__{eyebrow|title|lead}  bgcf-section-heading--{start|center}
bgcf-btn  bgcf-btn--{primary|secondary|ghost|link}  bgcf-btn--{sm|md|lg}  bgcf-btn--block
bgcf-btn--icon  bgcf-btn__label  bgcf-btn__icon
bgcf-icon  bgcf-icon--{name}   bgcf-image  bgcf-image-link
bgcf-site-header  bgcf-site-header__{inner|branding|title|home|nav|actions|cta}
bgcf-site-footer  bgcf-site-footer__{inner|brand|title|home|intro|column|heading|bar|bar-inner|copyright|legal|social}
bgcf-nav  bgcf-nav--{primary|footer|legal|social}  bgcf-nav__label
bgcf-card  bgcf-card--{archive|related|search}  bgcf-card__{media|image|body|title|link|meta|excerpt}
bgcf-entry  bgcf-entry__{header|title|meta|media|image|content|footer}
bgcf-entry-meta  bgcf-entry-meta__item  bgcf-entry-meta__item--{date|reading-time|type}
bgcf-archive  bgcf-archive__{header|title|description|list}
bgcf-search  bgcf-search__{header|title|form|list}   bgcf-result  bgcf-result__{title|link|meta|excerpt}
bgcf-empty  bgcf-empty--{archive|search}  bgcf-empty__{title|text|search}   bgcf-empty-state
bgcf-page  bgcf-page__{article|header|title|media|image|content}  bgcf-prose
bgcf-breadcrumbs  bgcf-breadcrumbs__{list|item|link|current}
bgcf-pagination  bgcf-page-links  bgcf-page-links__item  bgcf-post-nav  bgcf-post-nav__{direction|title}
bgcf-comments  bgcf-comments__{title|list|nav|closed}  bgcf-comment-form
bgcf-search-form  bgcf-search-form__{label|input|submit}
bgcf-sidebar  bgcf-widget  bgcf-widget__title   bgcf-term-list  bgcf-term-list__{item|link}
bgcf-block  bgcf-block-{slug}   bgcf-dashboard-links
```

### Design tokens

Never write a raw hex, rgb, px font size or box-shadow literal in a component,
a template, or a PHP file. Tokens are declared once in `assets/scss/settings/`
and exposed as CSS custom properties on `:root`.

`inc/setup.php` references these by name for the locked editor palette and type
scale, so they **must** exist and must resolve in both `assets/css/main.css` and
`assets/css/editor.css`:

```
--bgcf-color-primary      Deep Blue     (palette slug: primary)
--bgcf-color-secondary    Emerald Green (palette slug: secondary)
--bgcf-color-cta          Orange        (palette slug: cta)
--bgcf-color-accent       Yellow        (palette slug: accent)
--bgcf-color-surface      Light Gray    (palette slug: surface)
--bgcf-color-base         White         (palette slug: base)
--bgcf-color-ink          Dark Text     (palette slug: ink)

--bgcf-font-size-sm       (editor slug: small)
--bgcf-font-size-base     (editor slug: body)
--bgcf-font-size-lg       (editor slug: lead)
--bgcf-font-size-xl       (editor slug: heading)
--bgcf-font-size-2xl      (editor slug: display)

--bgcf-login-logo         Set inline by inc/admin.php; consumed by login.css
```

Palette **slugs** are written into post content as `.has-primary-color` and
`.has-surface-background-color`. Renaming a slug orphans every post already
using it. Renaming the custom property is safe (it is CSS-only); renaming the
slug is a content migration.

Button variants map to the brand as follows and the design system owns the
mapping: `--primary` is the orange CTA, `--secondary` is deep blue,
`--ghost` is an outline on either, `--link` is text-only.

---

## 4. Escaping and sanitization policy

**Escape late, at the point of output. Sanitize early, at the point of input.**
There is no third option and no "it's already safe" exemption.

| Context | Function |
|---|---|
| Text node | `esc_html()` |
| Attribute value | `esc_attr()` |
| URL in `href`/`src` | `esc_url()` |
| URL stored or passed to a helper that will `esc_attr()` it | `esc_url_raw()` |
| Rich text from the editor / a WYSIWYG field | `wp_kses_post()` |
| Inline SVG | `wp_kses( $svg, bgcf_get_kses_svg() )` |
| Translated string with placeholders | `printf()` + `esc_html__()` / `esc_attr__()` |
| Element/tag name | `tag_escape()` |
| CSS class from data | `sanitize_html_class()` |
| JSON-LD | `wp_json_encode( …, JSON_HEX_TAG \| JSON_HEX_AMP )` — never `esc_html()` |

```php
// Correct.
printf(
	'<a class="bgcf-card__link" href="%1$s">%2$s</a>',
	esc_url( get_permalink() ),
	esc_html( get_the_title() )
);

// Correct — rich text keeps its markup, safely.
printf( '<div class="bgcf-prose">%s</div>', wp_kses_post( bgcf_field( 'body' ) ) );

// Wrong — double encoding. esc_url() then esc_attr() turns & into &amp;#038;.
$attrs['href'] = esc_url( $url ); // then esc_attr()'d downstream

// Wrong — concatenating unescaped data.
echo '<h2>' . get_the_title() . '</h2>';
```

**Template tags escape internally.** `bgcf_get_button()`,
`bgcf_get_post_thumbnail()`, `bgcf_get_svg_icon()` and `bgcf_section_open()`
return or emit already-escaped markup. Callers must **not** re-escape their
return value — `echo` it with a `phpcs:ignore` and a reason, exactly as the
existing templates do.

**Input.** Never touch `$_POST` / `$_GET` / `$_REQUEST` directly. Read through
`bgcf_ajax_param( $key, $sanitizer, $default )`, or sanitize explicitly with
`sanitize_text_field()`, `sanitize_email()`, `absint()`, `wp_kses_post()`.
Database access uses the WordPress data APIs, or `$wpdb->prepare()` — never
string interpolation.

**State changes.** Every write path calls
`bgcf_verify_request( $action, $nonce_key, $capability )` first. It checks the
nonce, the capability, and the rate limit / honeypot filters that Phase 6 hangs
off `bgcf_verify_request`. Do not hand-roll `wp_verify_nonce()` elsewhere.

---

## 5. Content model registry

Post type keys, taxonomy keys and rewrite slugs are written into the database
and into every permalink. **Changing one after content exists is a migration,
not a rename.** This table is the source of truth.

### Post types (`inc/cpt.php`)

| Key | Labels | Public | Archive | Rewrite slug | REST base | Supports | Taxonomies |
|---|---|---|---|---|---|---|---|
| `bgcf_program` | Programs / Program | yes | `/programs/` | `programs` | `programs` | title, editor, excerpt, thumbnail, revisions, page-attributes, custom-fields | `bgcf_program_category` |
| `bgcf_event` | Events / Event | yes | `/events/` | `events` | `events` | title, editor, excerpt, thumbnail, revisions, custom-fields | `bgcf_event_type` |
| `bgcf_campaign` | Campaigns / Campaign | yes | `/campaigns/` | `campaigns` | `campaigns` | title, editor, excerpt, thumbnail, revisions, custom-fields | — |
| `bgcf_story` | Success Stories / Success Story | yes | `/stories/` | `stories` | `stories` | title, editor, excerpt, thumbnail, revisions, custom-fields | `bgcf_program_category` (shared) |
| `bgcf_team` | Team / Team Member | yes | none | `team` | `team` | title, editor, thumbnail, page-attributes, custom-fields | `bgcf_department` |
| `bgcf_partner` | Partners / Partner | **no** | none | — | `partners` | title, thumbnail, page-attributes, custom-fields | `bgcf_partner_type` |
| `bgcf_testimonial` | Testimonials / Testimonial | **no** | none | — | `testimonials` | title, thumbnail, page-attributes, custom-fields | — |
| `bgcf_report` | Reports / Report | **no** | none | — | `reports` | title, editor, thumbnail, custom-fields | `bgcf_report_year` |

All eight are `show_in_rest => true` (required for the block editor and for ACF
fields to save reliably), `capability_type => post`, `map_meta_cap => true`.

Partners, testimonials and reports are **admin-only by design**: they are
reference data surfaced through blocks and page templates, not destinations.
Promoting a type to public later is free; demoting one after Google has indexed
its URLs is not. That asymmetry is why the default is closed.

Use `bgcf_get_post_types()` and `bgcf_get_taxonomies()` instead of hardcoding
these strings in queries.

### Taxonomies (`inc/cpt.php`)

| Key | Attached to | Public | Hierarchical | Rewrite slug | REST base |
|---|---|---|---|---|---|
| `bgcf_program_category` | `bgcf_program`, `bgcf_story` | yes | yes | `program-category` | `program-categories` |
| `bgcf_event_type` | `bgcf_event` | yes | no | `event-type` | `event-types` |
| `bgcf_department` | `bgcf_team` | no (UI only) | yes | — | `departments` |
| `bgcf_partner_type` | `bgcf_partner` | no (UI only) | yes | — | `partner-types` |
| `bgcf_report_year` | `bgcf_report` | no (UI only) | no | — (`query_var => false`) | `report-years` |

`bgcf_report_year` is deliberately **not** called `year`. `year` is a reserved
WordPress query var used by date archives; registering it as a public taxonomy
query var breaks archive routing site-wide.

### Reserved public paths

These URL segments belong to post type archives and taxonomy rewrites. **Never
create a Page with one of these slugs** — WordPress will resolve the page and
the archive will 404, silently, and only in production:

`programs`, `events`, `campaigns`, `stories`, `team`, `program-category`,
`event-type`

The public "Projects" page is a **page template** that queries `bgcf_campaign`.
It is intentionally decoupled from `/campaigns/`, so the page slug `projects` is
free to use.

### Image sizes (`inc/setup.php`)

| Size | Dimensions | Crop | Used by |
|---|---|---|---|
| `bgcf-hero` | 1920×1080 | yes | Hero, donation banner, page hero, OG image |
| `bgcf-card` | 800×600 | yes | Program/campaign/event/post cards |
| `bgcf-story` | 1200×800 | yes | Story hero, before/after, single entry |
| `bgcf-team` | 600×750 | yes | Team portraits |
| `bgcf-partner-logo` | 360×180 | no | Partner logos (letterboxed, never clipped) |
| `bgcf-report-cover` | 600×848 | yes | Report covers (A4 ratio) |

Adding a size later means regenerating thumbnails across the whole media
library. Add deliberately, and keep the aspect ratios in sync with the SCSS
components and the placeholder-image manifest.

---

## 6. Blocks

**Strategy: every custom block is an ACF block (PHP-rendered).** No native JS
blocks. Reasons: one rendering path shared with templates, no React build step
for content components, and markup fixes ship without invalidating saved block
content.

| Registered name | Slug | Render partial | Root class |
|---|---|---|---|
| `bgcf/hero` | `hero` | `partials/blocks/block-hero.php` | `.bgcf-block-hero` |
| `bgcf/statistics` | `statistics` | `partials/blocks/block-statistics.php` | `.bgcf-block-statistics` |
| `bgcf/cta` | `cta` | `partials/blocks/block-cta.php` | `.bgcf-block-cta` |
| `bgcf/donation-banner` | `donation-banner` | `partials/blocks/block-donation-banner.php` | `.bgcf-block-donation-banner` |
| `bgcf/program-cards` | `program-cards` | `partials/blocks/block-program-cards.php` | `.bgcf-block-program-cards` |
| `bgcf/gallery` | `gallery` | `partials/blocks/block-gallery.php` | `.bgcf-block-gallery` |
| `bgcf/testimonials` | `testimonials` | `partials/blocks/block-testimonials.php` | `.bgcf-block-testimonials` |
| `bgcf/timeline` | `timeline` | `partials/blocks/block-timeline.php` | `.bgcf-block-timeline` |
| `bgcf/partners` | `partners` | `partials/blocks/block-partners.php` | `.bgcf-block-partners` |
| `bgcf/faq` | `faq` | `partials/blocks/block-faq.php` | `.bgcf-block-faq` |
| `bgcf/newsletter` | `newsletter` | `partials/blocks/block-newsletter.php` | `.bgcf-block-newsletter` |

All eleven sit in the `bgcf` inserter category ("Boundless Gracia"), pinned above
the core categories.

**To add a block:** add a row to `bgcf_get_block_definitions()` in
`inc/blocks.php` and create `partials/blocks/block-{slug}.php`. Nothing else.
The shared `bgcf_render_block()` callback resolves the partial and passes a
normalised `$args` bundle:

```php
$args = array(
	'block'      => array,   // Raw ACF block settings
	'slug'       => string,  // 'hero'
	'content'    => string,  // InnerBlocks content
	'is_preview' => bool,    // Rendering inside the editor
	'post_id'    => int,     // Post that holds the block
	'anchor'     => string,  // Editor-set HTML anchor
	'class'      => string,  // Root class list, already composed
	'is_example' => bool,    // Inserter preview render
);
```

A block partial must: open with `bgcf_section_open()` using `$args['anchor']`
and `$args['class']`, render an editor-only `bgcf_empty_state()` when required
fields are missing, and render **nothing visible to the public** when it has no
content. Never let an unconfigured block leak scaffolding onto the front end.

**Namespace caveat.** ACF 6 preserves the explicit `bgcf/` namespace passed to
`acf_register_block_type()`; ACF 5 rewrites it to `acf/{slug}`. Every consumer
in the theme is namespace-agnostic (`bgcf_render_block()` strips either prefix;
`bgcf_get_block_editor_names()` allows both). ACF **field group location rules**
must target whichever name the install actually registers — verify this once, in
Phase 5, against `WP_Block_Type_Registry::get_instance()->get_all_registered()`,
and record the result here.

**Inserter is restricted.** Non-administrators see the eleven theme blocks plus
a vetted core subset (paragraph, heading, list, image, quote, buttons, columns,
group, separator, spacer, embed, table, html). Administrators keep the full
inserter. Extend via the `bgcf_allowed_block_types` filter, not by editing the
array.

---

## 7. Template hierarchy

| Request | Template | Notes |
|---|---|---|
| Homepage (static front page) | `front-page.php` | Renders the page's blocks. The Hero block owns the page's single `<h1>`. |
| Homepage (latest posts) | `front-page.php` → `index.php` | Graceful fallback before Settings → Reading is configured. |
| Page | `page.php` → `partials/content/content-page.php` | |
| Page with a template | `templates/page-{name}.php` | Phase 4 |
| Single post / any CPT | `single.php` → `partials/content/content-single.php` | Phase 4 adds `single-bgcf_program.php`, `single-bgcf_event.php`, `single-bgcf_story.php`. |
| Any archive | `archive.php` → `partials/content/content.php` | Phase 4 adds `archive-bgcf_program.php`, `archive-bgcf_event.php`, `archive-bgcf_story.php`. |
| Search | `search.php` → `partials/content/content-search.php` | |
| Empty loop | `partials/content/content-none.php` | Shared by archive, index and search. |
| 404 | `404.php` | |

**The `<main>` contract.** `header.php` opens
`<main class="bgcf-main" id="bgcf-main" tabindex="-1">` and `footer.php` closes
it. No template opens or closes the main landmark. This guarantees exactly one
`<main>` per document and a skip link that always lands somewhere focusable.

**Heading order.** One `<h1>` per page. Archives put it in
`.bgcf-archive__title`; pages and single entries in the content partial; the
homepage in the Hero block. The site title in the header is a `<p>`, never a
heading. Section headings start at `<h2>`.

---

## 8. Template tag contract (`inc/template-tags.php`)

`bgcf_get_*` returns an escaped string. `bgcf_the_*` and bare verbs echo. No
template tag ever queries for its own arguments.

| Function | Purpose |
|---|---|
| `bgcf_render_partial( $slug, $args, $echo )` | The only way to include a partial. Path is relative to `partials/`, no extension. Child-theme overridable. |
| `bgcf_get_text( $key, $default )` / `bgcf_the_text()` | Editable interface copy. Resolves ACF option `text_{$key}` → `$default` → `bgcf_text` filter. **This is how templates stay free of hardcoded copy before the options pages have content.** |
| `bgcf_get_post_thumbnail( $args )` / `bgcf_the_post_thumbnail()` | Responsive image with `srcset`, `sizes`, explicit width/height (no CLS), lazy by default. Pass `loading => 'eager', fetchpriority => 'high'` above the fold. Falls back to the global ACF placeholder image. |
| `bgcf_get_button( $args )` / `bgcf_button()` | The only sanctioned CTA renderer. Renders `<a>` with a URL, `<button>` without — semantics always match behaviour. Variants: `primary`, `secondary`, `ghost`, `link`. Sizes: `sm`, `md`, `lg`. |
| `bgcf_get_link_button( $acf_link, $overrides )` | Normalises an ACF Link field (array or string) into a button. Returns `''` for an empty link, so no template needs to guard. |
| `bgcf_section_open( $args )` / `bgcf_section_close( $args )` | Consistent landmark, background, spacing and container width. Pass the **same** `$args` array to both. |
| `bgcf_section_heading( $args )` | Eyebrow / title / lead lock-up with a controllable heading level and an `id` for `aria-labelledby`. |
| `bgcf_breadcrumbs()` | Defers to RankMath when present. Otherwise builds a minimal trail; extend via the `bgcf_breadcrumb_items` filter (Phase 7). |
| `bgcf_get_reading_time( $post )` / `bgcf_reading_time()` | Whole minutes, minimum 1, `_n()` pluralised. |
| `bgcf_posted_on()`, `bgcf_entry_terms( $taxonomy )`, `bgcf_pagination( $args )` | Entry meta and archive pagination. |
| `bgcf_get_svg_icon( $name, $args )` / `bgcf_svg_icon()` | Inlines `assets/images/icons/{name}.svg`, cached per request, `currentColor`-friendly, decorative unless given a `title`. |
| `bgcf_classnames( $classes )` | Conditional class joiner; accepts strings and `class => bool` pairs. |
| `bgcf_get_attrs( $attrs )` | Escaped attribute string. `true` → boolean attribute, `false`/`null` → omitted. |
| `bgcf_empty_state( $message )` | Editor-only instruction. Renders for users who can `edit_posts`, never for the public. |
| `bgcf_get_kses_svg()` | `wp_kses` allowlist for SVG. |

From `inc/acf.php`: `bgcf_field( $selector, $post_id, $default )`,
`bgcf_option( $selector, $default )`, `bgcf_has_field()`, `bgcf_image_id()`.
From `inc/enqueue.php`: `bgcf_use_gsap()`, `bgcf_use_swiper()`,
`bgcf_asset_uri()`, `bgcf_asset_version()`.
From `inc/security.php`: `bgcf_verify_request()`.
From `inc/cpt.php`: `bgcf_get_post_types()`, `bgcf_get_taxonomies()`.

---

## 9. ACF

**Reading values.** Only ever through `bgcf_field()` / `bgcf_option()`. They
return `$default` when ACF is absent or the field is empty, and pass through the
`bgcf_field_value` filter (which is how Polylang overrides and licence-free dev
environments work). Never call `get_field()` from a template or a partial.

**JSON sync.** `acf/settings/save_json` points at `theme/acf-json/`;
`acf/settings/load_json` replaces the default path with it. Saving a field group
in wp-admin writes `acf-json/group_xxxxxxxx.json`, which is committed. **That
file is the source of truth; the database copy is a cache.**

Workflow:

1. Edit the field group in wp-admin. ACF writes the JSON on save.
2. Commit the JSON file in the same change as the code that reads the fields.
3. Another developer pulls, opens **Custom Fields → Field Groups**, and clicks
   **Sync** on anything flagged as available.
4. Never edit `acf-json/*.json` by hand. Never resolve a merge conflict inside
   one — take one side wholesale, re-sync, re-save.

**Options pages** (all under the `bgcf-site-options` parent, capability
`edit_theme_options`, filterable via `bgcf_options_capability`):

| Menu slug | Purpose |
|---|---|
| `bgcf-site-options` | Parent. Organisation identity, global fallbacks, default featured image. |
| `bgcf-header` | Logo variants, header CTAs (`header_primary_cta`, `header_secondary_cta`), utility bar. |
| `bgcf-footer` | Footer intro (`footer_intro`), contact block, column headings, socials. |
| `bgcf-global-ctas` | Reusable donate / volunteer / contact CTAs, `error_404_cta`. |
| `bgcf-seo-defaults` | `seo_default_description`, `seo_default_share_image`, organisation schema data. |

**Field naming.** Lower snake_case, no `bgcf_` prefix (the group already scopes
them): `hero_heading`, `primary_cta`, `stats`, `milestones`. Interface strings
that templates read through `bgcf_get_text()` are named `text_{key}` on the
options pages, matching the key passed to the helper.

**Field group naming.** `Block — Hero`, `Program — Details`,
`Options — Footer`. Location rules bind to the CPT keys and block names in
§5 and §6.

---

## 10. Accessibility baseline

Non-negotiable, enforced at every phase gate:

- Semantic HTML first. ARIA only where semantics genuinely run out.
- One `<main>`, one `<h1>`, no skipped heading levels.
- Skip link is the first focusable element and targets `#bgcf-main`.
- Every landmark that repeats (`<nav>`, `<aside>`) carries a distinct
  `aria-label`; the label text comes from `bgcf_get_text()`, so it translates.
- Visible focus on everything focusable. Never remove an outline without
  replacing it with something at least as visible.
- Interactive things are `<button>` or `<a>`. A `div` with a click handler is a
  defect, not a style choice.
- Images: meaningful ones get real alt text authored by the content strategist;
  decorative ones get `alt=""` and `aria-hidden`.
- All motion sits behind `prefers-reduced-motion`. Carousels never autoplay
  without a pause control. Counters expose their final value in the DOM at
  render time.
- Forms: real `<label>` elements, errors associated with their field, status
  messages in a `role="status"` region.

---

## 11. Coupling and migration risks

Read this before proposing a "quick rename".

### Expensive to change later (treat as one-way doors)

1. **CPT keys and rewrite slugs.** `bgcf_program` is stored in
   `wp_posts.post_type` on every row, and `programs` is in every permalink,
   every internal link editors have pasted, and every external inbound link.
   Changing either = SQL migration + a 301 map + re-saving permalinks. **Cost:
   high. Decide now, not in Phase 5.**
2. **Taxonomy keys.** Stored in `wp_term_taxonomy.taxonomy`. Same class of
   problem, plus ACF taxonomy fields store term IDs scoped to the taxonomy.
3. **Block names.** `bgcf/hero` is written into `post_content` as an HTML
   comment the moment an editor saves. Renaming a block orphans every page
   already using it and shows an "unexpected content" error in the editor.
   Migration means a content rewrite across `wp_posts`.
4. **Editor palette slugs.** `primary`, `secondary`, `cta`, `accent`, `surface`,
   `base`, `ink` generate `.has-{slug}-color` classes saved into post content.
   Renaming a slug leaves orphaned classes on published pages. The underlying
   custom property name is safe to change; the slug is not.
5. **ACF field names.** Stored as `wp_postmeta` keys. Renaming a field silently
   loses its data unless you migrate the meta rows. Field *keys* (`field_xxx`)
   are what location rules and `get_field()` resolve against — never edit them
   by hand in the JSON.
6. **Menu location slugs** (`primary`, `footer_quick`, `footer_programs`,
   `legal`, `social`). Renaming one silently empties that menu on the front end
   with no error anywhere. Cheap to fix, easy to miss.
7. **Image size names.** Changing dimensions requires regenerating thumbnails
   across the entire media library, and old images keep the old crop until
   regenerated.

### Deliberate coupling — know where it is

| Coupling | Between | Breaks how |
|---|---|---|
| Design tokens ↔ editor palette | `assets/scss/settings/` ↔ `inc/setup.php` | The palette uses `var(--bgcf-color-*)`. If the custom properties are not declared on `:root` in **both** `main.css` and `editor.css`, editor swatches render transparent. Front end is unaffected, so this fails silently in review. |
| Structural CSS classes ↔ PHP | `inc/template-tags.php`, partials ↔ SCSS components | The class list in §3 is emitted by PHP. Renaming on either side alone produces unstyled markup with no error. |
| AJAX nonce ↔ script data | `inc/enqueue.php` (`bgcfData.nonce`, action `bgcf_ajax`) ↔ `inc/ajax.php` | Change the nonce action in one place and every form fails with a 403 that looks like a session bug. |
| Vendor asset paths ↔ build | `inc/enqueue.php` ↔ devops build config | `bgcf-gsap`, `bgcf-gsap-scrolltrigger` and `bgcf-swiper` point at `assets/js/vendor/` and `assets/css/vendor/`. If the build does not copy them there, the handles register fine and 404 only when a block enqueues them — i.e. in Phase 3, not Phase 1. |
| Compiled CSS filenames ↔ enqueue | `assets/css/{main,editor,login}.css` ↔ `inc/enqueue.php`, `inc/admin.php` | These three exact filenames are hardcoded. The build must produce them; content-hashed filenames would require a manifest reader. |
| JS source path ↔ bundle path | `assets/js/src/main.js` ↔ `assets/js/main.js` | `bgcf-app` enqueues `js/main.js`. esbuild's input and output must never be the same file, hence `src/`. Editing `js/main.js` directly appears to work until the next build silently reverts it. |
| CPTs in the theme ↔ portability | `inc/cpt.php` | Registering CPTs in the theme (rather than a plugin) is a deliberate trade: the deliverable is one portable artefact, but deactivating the theme hides all program/event/story content from the front end. Acceptable because this theme *is* the site. If the client ever wants theme independence, move `inc/cpt.php` into a `bgcf-core` must-use plugin — the file is written so it can be moved verbatim. |
| Gateway credentials ↔ `wp-config.php` | `inc/payments.php` ↔ hosting | `BGCF_FLW_PUBLIC_KEY`, `BGCF_FLW_SECRET_KEY` and `BGCF_FLW_SECRET_HASH` are read from constants or environment variables only — never the database. A clone of production with no constants defined silently shows "giving unavailable" rather than charging real cards with someone else's key, which is the intended failure direction. The mode (sandbox vs live) is derived from the secret key prefix, not from a setting. See PAYMENTS.md. |
| Webhook secret hash ↔ Flutterwave dashboard | `inc/payments-routes.php` ↔ Flutterwave settings | The hash is registered per mode. Change it on one side and every webhook 401s: donations then settle only on the 15-minute reconciliation cron, so the symptom is "donations are slow", not "donations are broken". |
| ACF Pro ↔ all content | Everything | ACF is a hard dependency for content, not for booting. Every accessor degrades gracefully and an admin notice fires when ACF is missing. Licence renewal is a launch-blocking item — track it in `DEPLOYMENT.md`. |
| RankMath ↔ `inc/seo.php` | SEO output | The theme suppresses its own canonical/OG output when RankMath is active (`bgcf_seo_is_delegated()`). Deactivating RankMath silently switches the theme's fallbacks back on — verify meta output after any SEO plugin change. |
| ACF block namespace ↔ field group location rules | ACF version | See §6. Verify once in Phase 5 and record the answer. |
| Polylang ↔ options pages | Phase 5 | ACF options pages are **not** translated per language by default. Polylang integration requires either per-language option pages or registering the strings. Decide in Phase 5 before content is authored, or the footer will be monolingual on every translation. |

### Known deferrals

- `assets/images/icons/` holds only the placeholder set.
  `bgcf_get_svg_icon()` returns `''` for a missing icon, so buttons with icons
  simply render without one until the full icon set lands.
- `theme/templates/` holds only `page-styleguide.php` (the Phase 1 token proof).
  `theme/partials/global/` is an empty placeholder for Phase 2.
- The styleguide page is a **development artefact**. It must be removed, or
  gated behind a capability check, before launch — it is currently reachable by
  anyone who assigns the template. Tracked as a Phase 8 checklist item.
- The `bgcf_volunteer` storage type (Phase 6) is **not** in §5 yet. It is a
  private submission store, not editorial content; register it in `inc/cpt.php`
  alongside the others when Phase 6 starts, and add it to the table then.
- **`npm run lint:php` has never been executed.** PHP and Composer are not
  installed on the build machine, so `phpcs.xml.dist` and `composer.json` are
  written and shipped but unverified by an actual run. First environment with
  PHP available must run `composer install && npm run lint:php` and fix the
  fallout before the Phase 8 gate. Treat the PHP in the theme as *reviewed
  against* WPCS, not *proven against* it.
- **Bootstrap 5 is installed but not imported.** `assets/scss/vendor/_bootstrap.scss`
  is commented out in both `main.scss` and `editor.scss`. The `--load-path=node_modules`
  flag in `css:compile` exists so uncommenting is a one-line change. Until then
  the grid/utility classes in the stylelint allowlist (`row`, `col-*`, `g-*`)
  are reserved, not live.
- **`build/webp.mjs` has not been exercised** — `theme/assets/images/` holds no
  raster source images yet, and npm blocked sharp's install script, so its
  binary has never run. Verify when the first licensed photography lands
  (Phase 7), not before.

---

## 12. Ownership

| Area | Owner |
|---|---|
| `functions.php`, `inc/` module boundaries, CPT/taxonomy registry, block registry, this document | wordpress-architect |
| `assets/scss/`, design tokens, base components, `screenshot.png` | uiux-designer |
| `package.json`, build pipeline, lint configs, `.gitignore`, `.editorconfig`, CREDITS enforcement | devops-engineer |
| Root templates, `templates/`, `partials/content/`, header/footer/nav implementation | theme-developer |
| ACF field groups, `acf-json/`, `partials/blocks/` implementations | gutenberg-acf-developer |
| `inc/security.php` hardening beyond Phase 1, form/upload/rate-limit work | security-engineer |
| `inc/seo.php` schema builders, RankMath config | seo-specialist |
| Copy, alt text, placeholder manifest | content-strategist |

Changing something outside your area? Raise it with the orchestrator and update
this document in the same change.

**Cross-area changes on record.** Phase 1 build-up required devops-engineer to
edit uiux-designer's SCSS. Logged here rather than silently absorbed:

| File | Change | Why it could not stay in the build layer |
|---|---|---|
| `tools/_layout.scss` | `ratio()` takes `$width, $height` separately | Real output bug: `ratio(4/3)` emitted `aspect-ratio: 1.33333`, because Sass evaluated the division before CSS ever saw it. Call sites in `components/_card.scss`, `components/_entry.scss` and `utilities/_layout.scss` updated to match. |
| `tools/_functions.scss` | `luminance()` uses `@if`, not `if()` | Dart Sass 1.100+ deprecates the Sass `if()` syntax. Statement form is unambiguous in both. |
| `tools/_a11y.scss`, `_elevation.scss`, `_typography.scss` | `== null` guards → `@if not $x` | Same deprecation sweep. Behaviour identical. |
| `generic/_reset.scss` | `-webkit-text-size-adjust` dropped from source | Autoprefixer re-adds it at build time from the browserslist. Authoring it by hand produced a duplicate declaration. |
| `utilities/_visibility.scss` | `.bgcf-u-aria-hidden` deleted | It set `speak: none`, which no browser implements — a comforting no-op. Hiding from assistive tech is `aria-hidden="true"` in markup, and nothing else. |

---

## 13. Build contract

### Commands

| Command | Does |
|---|---|
| `npm run build` | vendor copy → SCSS compile → autoprefix → JS bundle. The full artefact set. |
| `npm run dev` | Vendor copy once, then Sass and esbuild in watch mode. |
| `npm run lint` | stylelint + ESLint. **Does not include PHP** — see `lint:php`. |
| `npm run lint:php` | PHPCS against `phpcs.xml.dist`. Requires `composer install` first. |
| `npm run images:webp` | WebP derivatives + a warning for any image missing from `CREDITS.md`. |
| `npm run clean` | Deletes only the three artefact paths. Never touches sources. |
| `npm run prepare-release` | clean → build → lint. Run before any handover. |

Node 20+ (`engines` enforces it). Browser targets live in one place: the
`browserslist` key in `package.json`. Autoprefixer and esbuild's `--target`
must be kept consistent by hand — esbuild does not read browserslist.

### Why build artefacts are committed

`.gitignore` deliberately does **not** ignore `assets/css/`, `assets/js/main.js`
or `assets/js/vendor/`. A WordPress theme is deployed by copying a folder;
shared hosting has no Node. Committing the artefacts means `theme/` is always
installable as-is. The cost is diff noise on every style change and the standing
risk that someone edits an artefact — which is why the rule appears in §1, §11
and here.

### stylelint rules that are switched off, and why

`.stylelintrc.json` cannot carry comments, so the reasoning is here. Each of
these was set to `null` after reviewing what `--fix` actually wanted to do:

| Rule | Reason |
|---|---|
| `selector-not-notation` | It rewrites `:not(a):not(b)` into `:not(a, b)`. These are **not** equivalent — chained `:not()` sums specificity, the list form takes only the highest. Auto-fixing this would silently change the cascade. |
| `no-descending-specificity` | Fires across the ITCSS layer boundary by design: `elements/` legitimately sets a low-specificity default that `components/` overrides later. The layering *is* the specificity strategy. |
| `scss/operator-no-newline-after` | Fights the multi-line `clamp()` and `calc()` formatting used throughout the token files, which is more readable than a single long line. |
| `*-empty-line-before` (4 rules) | Purely cosmetic vertical-rhythm opinions that disagree with the house comment style. |
| `scss/comment-no-empty` | The `// ===` section banners contain intentionally empty comment lines. |

And two settings that exist to stop `--fix` corrupting real values:

- **`number-max-precision: 5`** — the default of 4 truncates `0.04045`, the
  WCAG relative-luminance linearization constant in `tools/_functions.scss`,
  to `0.0404`. That is a wrong contrast calculation, not a formatting nit.
- **`font-family-name-quotes: "always-unless-keyword"`** — keeps `"Poppins"`
  quoted so a future rename containing a space or digit cannot break parsing.

`settings/**` is exempt from `color-no-hex` — that is the one layer where raw
hex is correct, because it is where tokens are *defined*. Everywhere else the
rule enforces CLAUDE.md's "never hardcode a hex value in a component."
`vendor/**` is exempt from the naming rules because Bootstrap's variable names
are not ours to rename.

### selector-class-pattern allowlist

The pattern enforces `.bgcf-block__element--modifier`, plus an explicit
allowlist for classes WordPress core or a plugin emits (`wp-*`, `page-numbers`,
`menu-item-*`, `swiper*`, `give*`, `wpcf7*`, Bootstrap's `row`/`col-*`/`g-*`,
…). **When a new plugin lands, add its prefix to that allowlist** rather than
weakening the pattern — the allowlist is the audit trail of every non-theme
class the stylesheet is permitted to touch.
