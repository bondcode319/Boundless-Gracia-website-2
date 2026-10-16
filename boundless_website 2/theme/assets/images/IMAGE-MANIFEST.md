# Image manifest

Every image slot in the theme: what it is for, what shape it must be, and what
the alt text has to do. Hand this to whoever is licensing or shooting the
photography — it is a shopping list, not documentation.

Licences and consent records live in `CREDITS.md`.

---

## How the sizes work

The theme registers six crops (`theme/inc/setup.php`). Upload at or above the
**upload size** and WordPress produces the rest; upload smaller and the crop is
upscaled and visibly soft.

| Crop | Ratio | Rendered at | Upload at least | Used by |
|---|---|---|---|---|
| `bgcf-hero` | 16:9 | full width | 2400×1350 | Hero, donation banner backgrounds |
| `bgcf-card` | 4:3 | ⅓–½ width | 1200×900 | All cards, gallery thumbnails, CTA split |
| `bgcf-story` | 3:2 | ⅔ width | 1800×1200 | Success story heroes |
| `bgcf-team` | 4:5 | portrait tile | 900×1125 | Team portraits, testimonial avatars |
| `bgcf-partner-logo` | free | ≤160×64 | SVG, or 720×360 PNG | Partner logos (never cropped) |
| `bgcf-report-cover` | A4 portrait | small tile | 900×1272 | Annual report covers |

## Slots

## A note on the photographic bands (Horizon)

Four slots below are **band backgrounds**: the hero, the photo CTA bands, the
page mastheads and the donation hub masthead. All four paint a deep-blue scrim
over the photograph at 55–85% opacity before any text lands on it.

That has two consequences for whoever supplies the photography:

1. **Exposure and contrast barely matter.** The scrim guarantees the text's
   contrast on its own, so a slightly dark or slightly flat frame is fine. Do
   not pre-darken anything — the scrim will do it, and a pre-darkened photo
   under a scrim is mud.
2. **Composition matters a great deal.** The scrim flattens the image, so a busy
   frame becomes visual noise rather than a photograph. Favour one clear subject,
   open space where the text sits, and depth over detail.

Every band also renders correctly with **no photograph at all**, as a flat
deep-blue band. That is a supported look, not a fallback, so a page can ship
before its photography is licensed.

---

### Homepage

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Hero background | `bgcf-hero` | Wide, bright, outdoors. Activity in the **right** half — the headline covers the left. Not a posed group shot. | Decorative — leave empty. The headline states the mission and the scrim makes the image a backdrop. | Yes, if faces are recognisable |
| Photo CTA bands (×2) | `bgcf-hero` | Cropped to a short band (~280px tall), so keep the subject near the vertical centre. One clear subject, not a crowd. | Decorative — leave empty. | Yes, if faces are recognisable |
| Split section images (×2) | `bgcf-story` | **Keeps its own aspect ratio** — portrait and landscape both work, and the choice is editorial. Shows the activity the text describes. | Name the activity and who is doing it. This image is content, not decoration. | Yes |
| Highlight card icons | — | Use the shipped SVG set, not images. | n/a | n/a |
| Programme cards (×3+) | `bgcf-card` | One image per programme, showing the activity rather than the building. | Name the activity and who is doing it. | Yes |
| Statistics band | — | No image. Colour band only. | n/a | n/a |
| Testimonial avatars | `bgcf-team` | Head and shoulders, neutral background, natural light. | The person's name is already beside it — describe nothing, leave alt empty (decorative). | Yes, from the adult quoted |
| Partner logos | `bgcf-partner-logo` | Supplied by each partner. SVG preferred. Never recolour, never crop. | The partner's name, exactly. Never the word "logo". | n/a |
| Donation banner background | `bgcf-hero` | Quieter than the hero; the giving form sits on top of it. | Decorative — leave empty. | Yes, if faces are recognisable |

### About

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Page hero | `bgcf-hero` | The team or the place, not a stock handshake. | Describe who and where. | Yes |
| Timeline milestones | `bgcf-card` | One per milestone where a photo exists. A milestone with no photo is fine. | State what the photo shows and roughly when. | Yes |
| Team portraits | `bgcf-team` | Consistent framing and background across the whole team — a mixed set looks careless. | Leave empty: the name and role are rendered beside the portrait. | Yes, from each person |

### Programmes, projects, stories

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Programme featured | `bgcf-card` | The work happening. People, not empty rooms. | Name the activity. | Yes |
| Story featured | `bgcf-story` | The subject of the story, or their context if they cannot be shown. | Describe the image without naming a child beyond the first name used in the story. | **Yes — mandatory, and the story's consent reference field is required** |
| Before / after pairs | `bgcf-story` | Identical framing in both frames, or the comparison is dishonest. | Say which is which: "Before: …" / "After: …". | Yes |

### Events, gallery, reports

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Event featured | `bgcf-card` | The event itself, or last year's if it is recurring. | Name the event and what is happening. | Yes |
| Gallery items | `bgcf-card` | Uploaded to **Media**, tagged with an **Album**, consent ticked. | Describe each photo individually. A gallery of twenty identical alt texts is worse than none. | **Yes — enforced in the query** |
| Report covers | `bgcf-report-cover` | The PDF's own cover, exported at 900px wide. | "Cover of the 2026 annual report" is sufficient. | n/a |

### Horizon bands and templates

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Page masthead (every inner page) | `bgcf-hero` | The page's own subject. Set as the page's **Featured image**. Without one the band is flat deep blue, which is fine. | Decorative — leave empty. The `h1` is on top of it. | Yes, if faces are recognisable |
| Donation hub masthead | `bgcf-hero` | Quieter than the hero: the giving form sits on this band and needs a calm backdrop. Scrim runs at 72% here. | Decorative — leave empty. | Yes, if faces are recognisable |
| Action card images | `bgcf-card` | One per way-to-help. Show the action, not the outcome — a volunteer teaching, not a graduation. | Name what is happening. | Yes |
| Listing page banner (one, site-wide) | `bgcf-hero` | Generic and on-brand: it sits behind the title of **every** list page, so it must suit news, programmes, stories and search results alike. | Decorative — leave empty. | Yes, if faces are recognisable |
| Listing page closing band (one, site-wide) | `bgcf-hero` | As above — one image behind the call to action at the foot of every list page. | Decorative — leave empty. | Yes, if faces are recognisable |
| 404 page background | `bgcf-hero` | Optional. Warm and calm; this page is shown to someone who has just hit a dead end. | Decorative — leave empty. | Yes, if faces are recognisable |
| Announcement image | 16:9, ≥1200×675 | Optional. The seasonal pop-up. Legible at 420px wide. | Decorative — leave empty. | Yes, if faces are recognisable |
| Accreditation badges | `bgcf-partner-logo` | Supplied by each accrediting body. SVG preferred. **Never recoloured, never cropped** — most such bodies' brand rules forbid it. Rendered on a white plate. | The body's name, exactly. Never the word "logo". | n/a |

### Site-wide

| Slot | Crop | Subject brief | Alt text must | Consent |
|---|---|---|---|---|
| Logo | — | SVG, with a horizontal lock-up for the header and a stacked version for narrow screens. Must be legible in **white** as well as in colour: the header is transparent over the hero, so the logo sits on a photograph there. | The organisation's name. | n/a |
| Default share image | 1200×630 | Branded, with the logo and a single line of text. Legible at 300px wide. | n/a (Open Graph) | No |
| Fallback card image | `bgcf-card` | Neutral, on-brand, repeatable — it appears on every post with no image of its own. | Generic and honest: "Children at a Boundless Gracia programme". | Yes |
| 404 page | — | None. Search and links only. | n/a | n/a |

## Alt-text rules

1. Describe the **function** of the image in its context, not every visible
   detail.
2. Decorative images take `alt=""`. A hero whose headline says the same thing is
   decorative; a programme card photo is not.
3. Never start with "Image of" or "Photo of" — a screen reader already says
   "graphic".
4. Never name an identifiable child beyond the first name or pseudonym used in
   the surrounding copy.
5. Under 125 characters. Longer than that and the information belongs in the
   caption, where everyone can read it.

## Before launch

- [ ] Every slot above has a licensed asset.
- [ ] Every asset has a `CREDITS.md` row and, where needed, a consent reference.
- [ ] `npm run images:webp` has been run and the derivatives committed.
- [ ] Every attachment has author-written alt text in the Media library.
- [ ] Every gallery attachment has publication consent ticked.
- [ ] Lighthouse run on the homepage, a programme page and a story page.
