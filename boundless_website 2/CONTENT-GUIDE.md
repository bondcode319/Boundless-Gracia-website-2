# Running the site

Written for: whoever edits this website. No code, and nothing here needs a
developer.

---

## Where everything lives

| What you want to change | Where it is |
|---|---|
| The header's Donate and Volunteer buttons | Site Options → Header |
| The footer text, badges and registration number | Site Options → Footer |
| Address, phone, email, opening hours | Site Options → Global CTAs |
| The announcement pop-up | Site Options → Announcement |
| The navigation menus | Appearance → Menus |
| The logo | Appearance → Customise → Site Identity |
| Programmes, Events, Campaigns, Stories, Team, Partners, Reports | Their own menus in the sidebar |
| Everything on a page | Edit that page |

---

## Building a page

Pages are built from **bands** — full-width horizontal stripes. You add them in
the page editor like any other block; they are grouped under **Boundless
Gracia** in the block inserter.

### The bands you have

| Band | Use it for |
|---|---|
| **Hero** | The top of the homepage. One big photograph, a headline, two buttons. |
| **Split Section** | A photograph on one side, words on the other. The workhorse — most pages use two or three. |
| **Photo CTA Band** | A short photographic stripe with one heading and one button. Use it to break up a long page. |
| **Program Cards** | Your programmes, pulled automatically. |
| **News Cards** | Recent posts, stories or events, with dates. |
| **Statistics** | Four figures that count up as the visitor scrolls. |
| **Service Cards** | A grid of text-only cards. Designed for a dark band. |
| **Action Grid** | Ways to get involved, each with its own button. |
| **Team Grid** | Your people, grouped by department. |
| **Donation Banner** | A giving form, anywhere on any page. |
| **Gallery, Testimonials, Timeline, Partners, FAQ, Newsletter** | As named. |

### The one rule

**No two bands next to each other should be the same colour.** Every band has a
*Band colour* setting with seven options, labelled by what each is for:

- White — the quiet band
- Light grey — the default band
- Warm yellow — the impact band
- Warm orange — the news band
- Pale emerald — the programmes band
- Deep blue — a dark band
- Emerald — a dark band

A page that alternates light, dark, photograph, light reads as a journey. A page
of six light-grey bands reads as one long column, however good the writing is.
The starter homepage is already alternating correctly — if you reorder it, keep
checking that no two neighbours match.

### Starting from a finished page

**Inserter → Patterns → Boundless Gracia** has two complete pages:

- **Homepage — the full page.** All eleven bands, in order, with placeholder
  text. Insert it on your front page and replace the words band by band.
- **Take action — ways to get involved.** Six cards and a closing band.

Once inserted it is yours — edit, reorder and delete freely.

---

## Page templates

Some pages need a particular shape. Set it under **Page Attributes → Template**
in the right-hand sidebar:

| Template | What it does |
|---|---|
| **Donation hub** | Puts the giving form at the very top, on the photograph. Use it for your Donate page. |
| **Action hub** | A masthead above whatever ways-to-help bands you add. |
| **Team directory** | Adds your team automatically, grouped by department. |
| **Contact** | Contact details beside a working form, with an opt-in map. |
| **Volunteer** | The volunteer application form. |
| *(Default)* | A masthead, then whatever bands you add. |

---

## The top of every page

Every page opens with a band carrying the page title. You control three things:

1. **The photograph** — set the page's **Featured image**. Landscape, at least
   1920px across. Without one the band is deep blue, which is a proper look and
   not a fallback.
2. **The label above the title** — *Page masthead → Label above the title*. Two
   or three words.
3. **The sentence below it** — *Page masthead → Sentence below the title*. One or
   two sentences. This is also what Google and social networks show if you have
   not written a separate SEO description.

You never add your own big heading at the top of a page. The band is the
heading — adding a second one confuses screen readers and search engines.

---

## The header menu

**Appearance → Menus**, assigned to **Primary**.

A top-level item **with** items under it becomes a drop-down panel. A top-level
item **without** any becomes a plain link.

For each item inside a panel, set two things:

- **Description** — the one line under its title in the panel. If you cannot see
  the field, open **Screen Options** at the top of the page and tick
  *Description*.
- **Panel icon** — the small round icon beside it.

Two things to know:

- **A parent item's own page still works.** It appears at the top of its own
  panel as "About us overview". If you do not want that, set the parent's URL to
  `#`.
- **Three levels deep will not show.** The header renders two. If you build a
  third, a yellow notice appears on this screen telling you how many items are
  affected.

---

## The announcement pop-up

**Site Options → Announcement.** Off by default.

It appears a few seconds after the page loads, once per visit, and is dismissed
for the rest of that visit. Set a **Stop showing after** date and it switches
itself off — so an appeal that has closed does not keep announcing itself.

Clear the heading, or untick *Show the announcement*, and nothing appears at all.

---

## Photographs

### Where to get them

Licensed stock, or photographs you commissioned. **Never** an image taken from
another organisation's website, including one you admire. Everything is stored
on this site; nothing is hot-linked.

### Sizes

`theme/assets/images/IMAGE-MANIFEST.md` lists every slot on the site, what it is
for and what size to upload. The short version:

| Slot | Upload at least |
|---|---|
| Hero, photo bands, page mastheads | 2400 × 1350 |
| Cards, galleries | 1200 × 900 |
| Story and split-section images | 1800 × 1200 |
| Team portraits | 900 × 1125 |
| Partner and badge logos | SVG, or 720 × 360 |

Upload smaller than this and the image is stretched and visibly soft.

### Alt text

Every photograph needs it, written in the **Media** library. Describe what the
image is *doing there*, in one clause, under 125 characters. Do not start with
"Image of" — a screen reader already says "graphic". Never name an identifiable
child beyond the first name used in the surrounding story.

Leave alt text **empty** when the image is decorative — a team portrait with the
person's name printed next to it, or a background photograph behind a heading
that already says the same thing.

### Consent

**No identifiable child is published without documented guardian consent.**

- Gallery images will not appear until the consent box on the attachment is
  ticked. This is enforced, not advisory.
- A success story cannot be published without a consent reference.
- Record where every consent form is filed in
  `theme/assets/images/CREDITS.md`, along with each image's source and licence.

---

## Writing for the site

**The headline.** Say what happens, not what you believe. "1,840 children
supported through school" lands; "Committed to transforming lives" does not.

**The emphasised phrase in the hero.** Type one phrase from your headline into
*Phrase to emphasise*, exactly as it appears above, and it is set apart in the
brand yellow. Leave it empty for a plain headline.

**Impact figures.** Give the source and the date. A figure without them reads as
a guess, and a supporter who thinks you are guessing does not give twice.

**Costs.** Always say what an amount buys — "UGX 50,000 keeps one child in school
for a term", not "every donation helps". An amount with no outcome attached is a
number a visitor has no way to judge.

**Card text.** Two or three lines. Cards clamp longer text, so the fourth line is
not lost — it is never shown.

**Buttons.** Say what happens next: "Sponsor a child", not "Click here" or
"Learn more". The text you type into a link field becomes the button's label.

---

## Before you publish a new page

- The band colours alternate; no two neighbours match.
- The page has a Featured image, or you chose the deep-blue band deliberately.
- Every photograph has alt text, or is deliberately decorative with it empty.
- Every photograph of an identifiable child has a consent record.
- Every button says what happens when you press it.
- Every figure has a source and a date.
- You have read it on a phone.
