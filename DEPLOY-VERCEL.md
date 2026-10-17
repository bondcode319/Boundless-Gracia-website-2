# Deploying to Vercel

Written for: whoever deploys this site. Assumes nothing beyond a GitHub account
and a Vercel account.

---

## What is actually being deployed

This repository holds **two** sites, and they are not interchangeable.

| | `theme/` | `public/` |
|---|---|---|
| What it is | The WordPress theme — the real product | A flat HTML mirror of it |
| Runs on | PHP hosting with MySQL | Any static host, including Vercel |
| CMS | Full wp-admin, ACF, GiveWP | None — edit `build/static-pages.mjs` |
| Donations | GiveWP + Flutterwave, on-site | A link out to a hosted payment page |
| Built by | `npm run build` | `npm run build:static` |

**Vercel cannot run the WordPress theme.** It has no PHP runtime, no MySQL and
no persistent filesystem for uploads. That is why `public/` exists.

The static mirror is not a reimplementation. It copies the theme's own compiled
stylesheet, its own behaviour bundle and its own self-hosted fonts, and marks
everything up with the same BEM classes, so the two look and behave the same.

---

## Vercel import settings

On Vercel's **New Project** screen, set exactly these:

| Field | Value |
|---|---|
| Root Directory | **`./`** — the repository root, *not* `boundless_website 2` |
| Framework Preset | `Other` |
| Build Command | `node build/static-site.mjs` |
| Output Directory | `public` |
| Install Command | leave as is, or `echo skip` |
| Environment Variables | none |

`vercel.json` at the repository root already declares all of these, so if you
leave the dashboard fields alone it will still build correctly. The file also
sets security headers, immutable caching for fonts, and clean URLs.

> `boundless_website 2/` is an older duplicate of the theme. Pointing Vercel at
> it produces a `404: NOT_FOUND`, because it contains no HTML.

---

## Commands

```bash
# Build the static site locally
npm run build:static

# Build it and serve it at http://localhost:3000
npm run dev:static

# Deploy from the terminal instead of GitHub
npm i -g vercel
vercel login
vercel link
vercel            # preview deployment
vercel --prod     # production deployment

# After a domain is bought
vercel domains add boundlessgracia.org
vercel alias set <deployment-url> boundlessgracia.org
```

`public/` is generated and git-ignored. Vercel rebuilds it on every push, so
there is nothing to commit.

---

## Editing the static site

There is no wp-admin. Content lives in two files:

- `build/static-lib.mjs` — organisation details: name, address, phone, email,
  registration number, social links, navigation.
- `build/static-pages.mjs` — the copy for every page, and the page manifest at
  the bottom that controls which pages exist.

Change a value, run `npm run build:static`, push. Vercel redeploys.

---

## Before this goes in front of the public

Everything below ships as a placeholder and must be replaced. This is the same
list as `ASSUMPTIONS.md` §12, narrowed to what affects the live static site.

### 1. Organisation details — `build/static-lib.mjs`

The address, phone number, email and NGO Bureau permit number in `ORG` are
invented. Replace all of them.

### 2. Every impact figure

`2,480 children supported`, `1,150 school places`, `94% sat their finals`,
`83% programme spend`, `12 partner schools`, the founding year `2014` and the
ten-year framing are all invented. So are the four donation tiers, the
UGX 180,000 monthly sponsorship cost, every date on the Stories and Events
pages, and every name quoted in a testimonial or listed under the team.

**Publishing invented impact figures as fact would be a fundraising
misrepresentation.** Replace them or delete the bands.

### 3. Photography

No photographs ship with this build, by policy (`PLAN.md` §7). Every image slot
renders a coloured panel naming the subject the final licensed photograph must
show — for example *"A community health worker weighing a young child"*.

Replace `photo()` in `build/static-lib.mjs` with a real `<img>`, source licensed
or commissioned images only, self-host them, log each one in
`assets/images/CREDITS.md`, and never publish an identifiable child without
documented guardian consent.

### 4. The forms do not submit yet

The contact, volunteer and newsletter forms validate, trap bots with a honeypot,
and then tell the visitor the form is not connected and to email instead. They
do not silently fail.

To connect them, create an endpoint (Formspree, Basin, or a Vercel Function) and
set it before `static-forms.js` loads:

```html
<script>window.bgcfFormEndpoint = 'https://formspree.io/f/xxxxxxxx';</script>
```

Add that line to `dataBridge()` in `build/static-site.mjs`.

### 5. Donations go nowhere

The **Donate** buttons currently point at `#`. Create a Flutterwave payment link
that accepts MTN and Airtel mobile money in UGX, then set `ORG.donateUrl` and
use it on the donate page's primary button.

See `PAYMENTS.md` for the Flutterwave specifics, including why UGX settlement
and mobile money shape the integration.

### 6. The canonical domain

`BASE_URL` in `build/static-lib.mjs` is `https://boundlessgracia.org`. It feeds
canonical tags, Open Graph URLs and the sitemap. Set it to the real domain
before launch or search engines will index the wrong hostname.

---

## What has not been verified

The build runs clean, every internal link resolves, every page has exactly one
`<h1>` and one `<main>`, every CSS class used is defined, and all eleven pages
plus every asset serve `200` from a local server.

Not yet done, because it needs a browser:

- Lighthouse, against the 95+ target in `CLAUDE.md`
- An axe pass and a keyboard-only walkthrough
- Cross-browser and real-device responsive checks
- Visual confirmation that the WordPress theme's stylesheet lays out correctly
  against this markup at every breakpoint

Run those against a preview deployment before pointing a domain at it.
