# Boundless Gracia Horizon — WordPress Build

Shared context for **every** subagent. Read this before doing any work.

## Project

Build a production-ready, enterprise-quality **custom WordPress theme** for an
international NGO/charity. Inspired by the structure and UX quality of leading
foundation sites, but **100% original** code, design, copy, and assets. No
copyrighted content, branding, or layouts may be reproduced.

Goals: modern, trustworthy, emotionally engaging, donation-optimized, WCAG 2.2
accessible, 95+ Lighthouse, fully CMS-managed (no hardcoded content).

## This is the Horizon generation (2.x)

The back end is inherited from the first generation and is **unchanged**:
Flutterwave donations, lead and volunteer records, CPTs, security, SEO, privacy,
and the brand token contract. The presentation layer is new.

Read `inc/horizon.php` first. Three ideas run through everything:

1. **Bands.** Every section opens with `bgcf_section_open()` and takes its
   background from a **closed set of nine roles** (`page`, `soft`, `sand`,
   `bloom`, `leaf`, `ink`, `ink-deep`, `secondary`, `photo`). The set is closed
   so that *no two adjacent bands share a background* is enforceable. Three
   files must agree: `bgcf_section_backgrounds()`, `settings/_colors.scss`
   `$surfaces`, and `components/_section.scss`. Never add a tenth tint.
2. **One card dispatcher.** `bgcf_card( $type, $args )` renders all six card
   shapes (project, news, stat, service, action, person) from one argument
   bundle. Blocks and templates gather content and call it — **never write card
   markup in a block partial.**
3. **Dark surfaces flip tokens, not components.** A dark band includes
   `on-dark`; a light island inside it includes `on-light`. No component gets
   its own "on dark" variant.

Other invariants: every disclosure (mega panel, drawer sub-menu, promo dialog)
uses the `hidden` **attribute**, never `opacity: 0`; photographic bands enforce a
55% scrim floor in both the SCSS mixin and PHP; exactly one `h1` per page, owned
by `partials/global/sub-banner.php` or the hero block.

See `README.md` for the architecture, `ASSUMPTIONS.md` for every judgement call
and what is still unverified, `CONTENT-GUIDE.md` for the editor's view.

## Brand & Design System

- **Colors:** Deep Blue `#123B63` (primary), Emerald Green `#1E8A5A`
  (secondary), Orange `#F26A21` (CTA), Accent Yellow `#F5C518`, Light Gray
  `#F4F6F8` (backgrounds), White `#FFFFFF` (cards), Dark Text `#1A2430`.
- **Type:** Poppins (headings), Inter (body). Buttons semi-bold, generous
  spacing.
- **Feel:** warm, professional, hopeful, clean, accessible. Large imagery,
  rounded cards, soft shadows, minimal gradients, lots of white space.
- Store all tokens as SCSS variables + CSS custom properties. Never hardcode a
  hex value in a component.

## Imagery

Licensed stock or commissioned photography **only**. Never copy images from
another organisation's site, including any reference site. During the build,
every image slot uses the content-strategist's placeholder manifest (filename,
subject, aspect ratio, alt text); placeholders are swapped for final licensed
assets before launch and must never ship. Self-host everything (no hotlinking),
log source + licence + consent in `assets/images/CREDITS.md`, and never publish
an identifiable child without documented guardian consent. Full policy: PLAN.md §7.

## Tech Stack

WordPress (latest), custom theme, PHP 8+, HTML5, CSS3/SCSS, JS ES6, GSAP,
Swiper.js, Bootstrap 5 (grid/utilities only — do not let it fight the design
system), ACF Pro, Custom Post Types, custom Gutenberg blocks, Contact Form 7,
WP Mail SMTP, RankMath SEO, Wordfence, WP Rocket–compatible, GiveWP (donations),
Polylang (multilingual), Google Maps API, Cloudflare-compatible, lazy loading,
WebP, SVG icons.

## Theme File Structure

```
theme/
  assets/{css,scss,js,images,fonts}/
  inc/            # PHP modules: cpt, acf, blocks, enqueue, security, seo, setup
  templates/      # page templates
  partials/       # reusable template parts
  woocommerce/    # overrides (if WooCommerce used)
  acf-json/       # version-controlled ACF field groups
  languages/
  functions.php  style.css  index.php  header.php  footer.php
  single.php  archive.php  page.php  404.php  search.php
```

## Custom Post Types

Programs, Events, Campaigns, Stories (success stories), Team, Partners,
Testimonials, Reports.

## Custom Gutenberg Blocks

Hero, Statistics, CTA, Donation Banner, Program Cards, Gallery, Testimonials,
Timeline, Partners, FAQ, Newsletter — plus the Horizon bands: **Split Section,
Photo CTA, Service Cards, News Cards, Action Grid, Team Grid**.

All are ACF blocks (PHP-rendered). Add one by adding a row to
`bgcf_get_block_definitions()`, creating `partials/blocks/block-{slug}.php`, and
adding a field group to `build/acf-fields.mjs`. Field groups are **generated**
into `acf-json/` — but ACF also rewrites those files when an editor saves in
wp-admin, so pull before regenerating against a live site.

## Non-Negotiable Coding Standards

- Follow **WordPress Coding Standards (WPCS)**. Text domain: `bgcf`.
- **Security always:** escape on output (`esc_html`, `esc_attr`, `esc_url`,
  `wp_kses_post`), sanitize on input, verify nonces, use `$wpdb->prepare`,
  check capabilities. Never trust user input.
- Prefer reusable components over duplication. Modular `inc/` files, each with a
  single responsibility, loaded from `functions.php`.
- Enqueue assets properly (`wp_enqueue_*`), version them, no inline blobs.
- **Everything editable in wp-admin** via ACF — zero hardcoded content strings
  in templates.
- Accessible by default: semantic HTML, ARIA only where needed, keyboard
  operable, visible focus, alt text, skip links.
- Prefix all functions/handles/globals with `bgcf_`.

## Delivery status

Phases 1–7 are **built** (scaffold, tokens, layout, Horizon bands and cards,
inner templates, CMS, donations, performance). Phase 8 — **testing and
deployment — is open**, and nothing in this build has been executed against a
WordPress install: the build environment had no PHP runtime.

`README.md` §10 is the ordered list of what remains. The short version: activate
on a real install, run phpcs, run Lighthouse and axe, keyboard-test the mega
menu and the promo dialog, do a sandbox Flutterwave transaction, and replace
every placeholder image and every placeholder figure in the starter patterns.

**Do not report this as verified.** Say what was built and what was not run.
