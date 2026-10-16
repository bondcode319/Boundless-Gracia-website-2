# Boundless Gracia Horizon

A production WordPress theme for the Boundless Gracia Children Foundation, built
on the **Horizon** layout system: a mega-menu header, a full-bleed photographic
hero, alternating coloured bands, and a family of six card shapes.

Written for: the developer who will install, extend and hand this over, and the
editor who will run the site afterwards. The editor-facing half is
[CONTENT-GUIDE.md](CONTENT-GUIDE.md); everything we had to assume is in
[ASSUMPTIONS.md](ASSUMPTIONS.md).

---

## 1. What this is

This is the second-generation theme for the same organisation. It keeps the
proven back end of the first — the Flutterwave donation pipeline, the lead and
volunteer records, the custom post types, the security and SEO modules, the
design tokens — and replaces the entire presentation layer with the Horizon band
architecture.

**What is new**

| Area | What changed |
|---|---|
| Header | Four-panel mega menu with a four-column grid of described destinations; transparent over a hero, solid after 40px of scroll; accordion in the mobile drawer |
| Page architecture | Pages are a run of full-bleed **bands** from a closed set of nine background roles, with no two adjacent bands sharing one |
| Components | A six-card family (project, news, stat, service, action, person) rendered by one dispatcher, plus split sections, photo CTA bands and page mastheads |
| Blocks | Six new editor blocks: Split Section, Photo CTA, Service Cards, News Cards, Action Grid, Team Grid |
| Templates | New article, listing, programme, donation hub, action hub and team directory templates |
| Starter content | Two block patterns that build a finished homepage and action hub in one insert |
| Announcement | An optional, session-dismissed, focus-trapped promo dialog |

**What is unchanged** — and therefore still documented by the first
generation's notes: payments (see [PAYMENTS.md](PAYMENTS.md)), the lead and
donation data model, the privacy exporters, the security headers, the schema
output, and the brand colour contract.

---

## 2. Requirements

- WordPress 6.4+
- PHP 8.0+
- Node 20+ (build only — not needed on the server)
- **ACF Pro** — required. Every block and every options page is an ACF
  structure; without it the theme renders its templates but the editor cannot
  fill them.

Recommended plugins, in the order they matter: WP Mail SMTP (transactional email
will not arrive without it), RankMath (the theme defers its own meta output when
RankMath is active), Wordfence, Polylang, WP Rocket.

---

## 3. Install

```bash
# 1. Dependencies and a first build.
npm install
npm run build          # ACF field groups, translations, CSS, JS

# 2. Put the theme where WordPress can see it.
#    `theme/` is the theme root — deploy that directory, not the repository.
cp -r theme /path/to/wp-content/themes/bgcf-horizon
```

Then, in wp-admin:

1. **Plugins** — activate ACF Pro first. The field groups in
   `theme/acf-json/` are picked up automatically; there is nothing to import.
2. **Appearance → Themes** — activate *Boundless Gracia Horizon*.
3. **Appearance → Menus** — create a menu and assign it to **Primary**. See
   §5 for the structure the mega menu expects.
4. **Settings → Reading** — set a static front page.
5. On that page, insert the **Homepage — the full page** pattern
   (Inserter → Patterns → Boundless Gracia). You now have the whole blueprint,
   with placeholder copy to replace.
6. **Site Options** — fill in Header, Footer, Global CTAs and Contact details.
7. **Site Options → Donations** — Flutterwave keys. Start in sandbox;
   [PAYMENTS.md](PAYMENTS.md) has the go-live checklist.

`docker-compose.yml` brings up WordPress, MySQL and MailHog for local work.

---

## 4. Build commands

| Command | What it does |
|---|---|
| `npm run build` | Everything: vendor copy, ACF groups, `.pot`, CSS, JS |
| `npm run dev` | Watches SCSS and JS |
| `npm run build:acf` | Regenerates `theme/acf-json/` from `build/acf-fields.mjs` |
| `npm run lint` | stylelint + eslint |
| `npm run lint:php` | phpcs against WordPress Coding Standards |
| `npm run images:webp` | Generates WebP derivatives of everything in `assets/images/` |

**`theme/acf-json/` is generated, but it is also live.** ACF rewrites those
files whenever an editor saves a field group in wp-admin. If you change
`build/acf-fields.mjs` and regenerate, you overwrite any admin-side edits — so
pull the current files before regenerating on a site that has been live.

---

## 5. The navigation the mega menu expects

The header renders **two levels** of the Primary menu. A top-level item with
children becomes a panel; one without becomes a plain link.

```
About us                 ← becomes a panel trigger
  Our story              ← a cell in the panel
  Our team
  Governance and reports
  Press
Our work                 ← panel
  Education
  Health
  Child protection
  Family livelihoods
Get involved             ← panel
  Donate
  Sponsor a child
  Volunteer
  Fundraise for us
  Partner with us
Contact                  ← plain link, no children
```

Two things to set on each item, both in **Appearance → Menus**:

- **Description** — the one line shown under the title in the panel. If you
  cannot see the field, turn it on under **Screen Options** at the top of the
  menus screen.
- **Panel icon** — an ACF select on each menu item.

A third level is **not rendered**, and an admin notice on the menus screen says
so with a count if you have built one.

A parent that owns a panel is a `<button>`, not a link, so its own page is added
to the top of its panel as "About us overview". Give it a real URL and it
appears; set it to `#` and it does not.

---

## 6. Architecture

```
theme/
  functions.php          Loads inc/ modules in dependency order. No logic.
  header.php footer.php  The shell. header.php opens <main>, footer.php closes it.
  front-page.php         Renders the front page's blocks. No hardcoded order.
  single.php             Article: title band + 760px reading column + related.
  archive.php            Listing: masthead + card grid + pagination + CTA band.
  single-bgcf_program.php  Programme: masthead, outcomes, approach, campaign ask.
  404.php                Photographic band + search + three programmes.
  templates/             page-donate, page-take-action, page-team, page-contact,
                         page-volunteer, page-styleguide
  partials/
    cards/               One partial per card type. Only bgcf_card() calls these.
    blocks/              One partial per bgcf/* block.
    global/              Header, drawer, mega cell, bands, forms, promo modal.
    content/             content-page, content-single, content-none, …
  inc/
    horizon.php          THE CARD FAMILY AND THE BANDS. Start here.
    mega-menu.php        The two-level navigation renderer.
    patterns.php         The starter-page patterns.
    template-tags.php    Section wrappers, buttons, icons, headings.
    …                    cpt, acf, blocks, enqueue, seo, security, payments,
                         donations, campaigns, leads, privacy, admin
  assets/scss/           ITCSS: settings → tools → generic → elements →
                         components → utilities
  acf-json/              Field group definitions (generated, and live)
```

### The three ideas worth knowing

**1. Bands.** Every section is opened with `bgcf_section_open()` and takes its
background from a closed set of nine roles (`page`, `soft`, `sand`, `bloom`,
`leaf`, `ink`, `ink-deep`, `secondary`, `photo`). The set is closed so that "no
two adjacent bands share a background" is enforceable rather than aspirational.
Three files have to agree, and they say so in their own comments:
`bgcf_section_backgrounds()`, `settings/_colors.scss` `$surfaces`, and
`components/_section.scss`.

**2. One card dispatcher.** `bgcf_card( $type, $args )` renders all six card
shapes from one argument bundle. Block partials and templates gather content and
call it; they never write card markup. `bgcf_card_args_from_post()` maps a post
onto the bundle, so the excerpt fallback and the featured-image default exist in
one place.

**3. Dark surfaces flip tokens, not components.** A dark band includes
`on-dark`, which re-declares every semantic colour role at once; an island of
light inside it (the footer's subscribe card, a service card) includes
`on-light`. No component carries its own "on dark" variant.

---

## 7. Accessibility

Targeting WCAG 2.2 AA. The decisions that are not obvious from the code:

- **Every disclosure uses the `hidden` attribute**, not `opacity: 0`. Mega
  panels, drawer sub-menus and the promo dialog are all genuinely absent from
  the tab order and the accessibility tree while closed.
- **Panel triggers are `<button aria-expanded>`**, so the parent's own page is
  re-inserted as the first cell of its panel. A link that also toggles strands
  keyboard users.
- **Photographic bands never trust the photograph.** A deep-blue scrim is
  painted over every one at a minimum of 55%, so white text clears AA against
  the scrim alone whatever the editor uploads. The floor is enforced in both the
  SCSS mixin and `bgcf_section_open()`.
- **Card links carry their context.** "Read more" is followed by a visually
  hidden card title, so nine cards are nine distinguishable links (2.4.4).
- **Counters render their real value server-side.** The animation overwrites and
  restores it, so a screen reader, a no-JS visitor and a reduced-motion visitor
  all get the final figure.
- **One `h1` per page**, owned by `partials/global/sub-banner.php` or by the hero
  block. No template emits its own.
- **The brand palette has three failing pairings** and the ramps exist to solve
  them. The rules are at the top of `settings/_colors.scss` — in short: never
  white text on the CTA orange, never yellow text at all, and emerald fills with
  white text resolve to the 700 shade.

Not yet done: a real audit run. See §10.

---

## 8. Performance

- GSAP and Swiper are registered but enqueued only by the blocks that use them
  (`bgcf_use_gsap()`, `bgcf_use_swiper()`), so the baseline is one 26 KB script.
- Fonts are self-hosted; the two above-the-fold faces are preloaded and nothing
  else is.
- Every image goes through `bgcf_get_post_thumbnail()`, which always emits
  width, height and `srcset`. Split sections and cards pass explicit `sizes`.
- Above-the-fold images opt in with `priority`, which resolves to
  `loading="eager"` + `fetchpriority="high"`. Everything else stays lazy.
- Listing queries are capped at twelve and always `no_found_rows`.

---

## 9. Content and images

**No image ships with this theme, and none of the patterns references one.** A
pattern cannot know the attachment IDs of the site it lands on, so every band is
built to render correctly without a photograph — photographic bands fall back to
flat deep blue, cards fall back to the global default image.

`theme/assets/images/IMAGE-MANIFEST.md` is the shopping list: every slot, its
crop, its subject brief and what its alt text has to do. Licence, source and
consent records go in `theme/assets/images/CREDITS.md`.

Two rules that are not negotiable, both from the project brief: licensed or
commissioned photography only, never an image taken from another organisation's
site; and no identifiable child is published without documented guardian
consent — the gallery query enforces the consent flag, and a success story's
consent reference field is required.

---

## 10. Before you call this done

This build has been compiled, bundled and linted. It has **not** been run
against a WordPress install, because none was available in the build
environment. The following are open, in priority order:

1. **Activate on a real install** and walk the eight pages. The riskiest areas
   are the mega menu against real menu data, the ACF field keys in the patterns,
   and the donation form inside its new card.
2. **`npm run lint:php`** — phpcs is configured and vendored, but there was no
   PHP runtime here to execute it.
3. **Lighthouse**, mobile, on the homepage, a programme page and an article.
4. **axe plus a keyboard pass**: tab the mega menu, the drawer, the promo dialog
   and the donation form; confirm focus returns correctly from each.
5. **Contrast spot-check** the three light bands introduced here (`sand`,
   `bloom`, `leaf`) against body, muted and eyebrow text.
6. **Flutterwave sandbox transaction**, then the go-live checklist in
   PAYMENTS.md.
7. Swap every placeholder image for a licensed asset and delete the
   placeholders. They must never ship.
