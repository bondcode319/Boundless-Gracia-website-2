# Boundless Gracia Children Foundation — WordPress Build

Shared context for **every** subagent. Read this before doing any work.

## Project

Build a production-ready, enterprise-quality **custom WordPress theme** for an
international NGO/charity. Inspired by the structure and UX quality of leading
foundation sites, but **100% original** code, design, copy, and assets. No
copyrighted content, branding, or layouts may be reproduced.

Goals: modern, trustworthy, emotionally engaging, donation-optimized, WCAG 2.2
accessible, 95+ Lighthouse, fully CMS-managed (no hardcoded content).

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
Timeline, Partners, FAQ, Newsletter.

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

## Delivery Phases (gate each one)

1. Project setup — theme scaffold, tooling, tokens, base components.
2. Core layout — header, nav (+mobile), footer, grid, homepage shell, a11y.
3. Homepage components — all sections as reusable blocks; animations/counters/carousels.
4. Inner pages — About, Programs, Projects, Blog, Events, Gallery, Stories, Contact, FAQ.
5. CMS & dynamic content — ACF, CPTs, blocks, dynamic templates.
6. Donations & volunteer — GiveWP, volunteer forms, emails, admin workflow.
7. Performance & SEO — assets, schema, lazy loading, caching, a11y polish.
8. Testing & deployment — cross-browser, responsive, Lighthouse, security, docs.

**After each phase, summarize completed work and wait for approval before the
next phase.**
