# Assumptions and deliberate deviations

Written for: whoever reviews this build and needs to know where a judgement call
was made rather than a specification followed.

The brief was a reference architecture document describing a Webflow-built site,
plus the existing Boundless Gracia theme as the source of branding, content model
and functionality. Where the two disagreed, or where neither said, this is what
was decided and why.

---

## 1. The brand contract won over the reference palette

The reference architecture specifies its own colours (a lime, a sand, a blush, a
fatigue green). The project brief specifies seven exact brand values and calls
them a contract.

**Decision:** the seven brand values ship exactly as specified. The reference's
colour *roles* were kept and re-mapped onto the brand ramps:

| Reference role | Horizon role | Resolves to |
|---|---|---|
| `ink` | `ink` | Deep Blue `#123B63` |
| `surface` | `soft` | Light Gray `#F4F6F8` |
| `sand` | `sand` | Accent Yellow 100 `#FEF7DF` |
| `blush` | `bloom` | Orange 50 `#FEF6F2` |
| `brand` (lime) | `leaf` | Emerald 50 `#F2F8F5` |
| `card-grey` | `card-muted` | Neutral 50 |

The reference uses its lime at full strength as a section background with dark
text on it. The brand emerald cannot do that job — `#1E8A5A` with white text is
4.34:1 and with Dark Text it is too close in value to read as a distinct band —
so the emerald band role resolves to a 50-level tint, and emerald at full
strength is used for rules and decoration only.

## 2. Shadows were kept; the reference has none

The reference is explicit that depth comes from flat colour blocks with no
shadows and no gradients. The brand brief asks for "rounded cards, soft shadows".

**Decision:** the brand brief wins, and the shadow scale is the existing
two-layer "lifted paper" ramp. Radii are the existing 12/16/24px scale rather
than the reference's uniform 10px, for the same reason.

## 3. Section padding is larger than the reference

The reference measures 50px of vertical padding per section. The brand brief asks
for generous spacing and a lot of white space.

**Decision:** the existing fluid section rhythm (56→96px at `md`, 80→144px at
`lg`). A 50px band at 1400px wide reads as cramped against this type scale.

## 4. The newsletter asks for one name, not two

The reference's subscribe card has first name and surname on one row.

**Decision:** one optional "Your name" field. The subscriber record stores a
single `name` string, so two inputs would be concatenated straight back into one;
and the field is optional, because an email address alone is a valid
subscription and every required field costs sign-ups.

## 5. The emphasised hero phrase is not italic

The reference's hero sets one phrase of the headline in italics.

**Decision:** the emphasis is carried by colour and weight, not slant. Poppins
has no true italic, so a browser would synthesise an oblique, and at 68px a
synthetic oblique looks like a rendering fault. The phrase is still marked up as
`<em>`, so assistive technology announces the emphasis either way, and the accent
yellow it is painted in clears 7:1 against the hero scrim.

It is also entered as a **separate field** naming the phrase, rather than as HTML
inside the heading — an editor typing angle brackets into a text input and
reloading to see whether they got it right is not a workflow worth shipping.

## 6. Mega-menu triggers are buttons, and the parent page moves into the panel

The reference's dropdown items are not links.

**Decision:** the same, implemented as `<button aria-expanded>`, which is the
correct disclosure pattern. The consequence is that a parent's own page becomes
unreachable from the bar, so it is re-inserted as the first cell of its own panel
("About us overview"). The alternative — a link that also toggles — navigates
away before a keyboard user can read the panel.

A parent whose URL is `#` or empty gets no overview cell, since it is a container
rather than a destination.

## 7. Three menu levels are dropped, loudly

The reference's information architecture is two levels deep in the header.

**Decision:** render two, discard the third, and show a counted admin notice on
the menus screen. Flattening a third level would put a grandchild beside its own
parent with no indication of the relationship; dropping it silently would leave
an editor with an item that exists in wp-admin and nowhere on the site.

## 8. The donation form is in the masthead

The reference's donation hub has an embedded form in its hero.

**Decision:** kept, and the form is a **light card on the photograph** rather
than an inverted form. White-on-blue inputs read as disabled and their
placeholder text fails contrast.

On phones the form is ordered **above** the copy, which is the opposite of the
split section's stacking rule. A visitor on the donate page has already decided;
scrolling past four paragraphs of reassurance to reach the amount buttons is the
one place where leading with the photograph is wrong.

## 9. Photo bands carry an enforced scrim floor

The reference describes a "dark overlay" without a value.

**Decision:** 62% by default, editor-adjustable between 55% and 85%, with the
55% floor enforced in both the SCSS mixin and `bgcf_section_open()`. At 55% the
composite is never lighter than the scrim over white, which measures 4.9:1
against white text — so the band is safe whatever photograph is uploaded, which
is the only way to let an editor choose it.

## 10. The promo modal is off by default and expires

The reference has a seasonal pop-up.

**Decision:** shipped, disabled, and dismissed per **session** rather than
permanently — a modal that never returns is invisible to a donor who dismissed it
in March and came back in December. It also takes an optional end date, so an
appeal that has closed stops announcing itself without anyone remembering to
switch it off. `sessionStorage` rather than a cookie, so a purely local UI
preference does not drag the site into consent-banner territory.

## 11. The sitemap is the organisation's, not the reference's

The reference's routes (`/our-tree-library`, `/reforest-fest`, `/come-gardening`)
are specific to a different organisation and a different sector.

**Decision:** the existing post types and taxonomies are the information
architecture — Programs, Events, Campaigns, Stories, Team, Partners,
Testimonials, Reports — and the templates are built for those. The navigation
structure suggested in README §5 is a proposal, not a constraint; it is
menu-driven, so changing it needs no code.

## 12. Organisation details are placeholders

Nothing in the brief stated the organisation's registration number, address,
founding year, programme names, staff, partners or actual impact figures.

**Decision:** the starter patterns carry **specific, plausible, clearly
replaceable** placeholder copy rather than lorem ipsum — "UGX 50,000 keeps one
child in school for a term" tells an editor what the field is for, where "Lorem
ipsum dolor" tells them nothing. Everything in it is invented and **all of it
must be replaced before launch**, in particular:

- Founding year (2014), location (Nansana, Kampala) and the ten-year framing.
- Every impact figure in the statistics band, and the claim that they are
  independently audited.
- The 83% programme-spend ratio and the UGX 180,000 monthly sponsorship cost.
- The 2025 annual report reference.
- The five components of a sponsored year.

Uganda and UGX were inferred from the existing Flutterwave mobile-money
integration and the payments documentation, not stated in the brief.

## 13. No images, anywhere

**Decision:** no pattern and no template references an attachment ID. A pattern
cannot know the IDs on the install it is inserted into, and one that points at ID
42 renders a broken image on every site but the one it was authored on. Every
band renders correctly with no image.

The brand logo files in `brand/` were carried over from the existing project and
are the organisation's own. They are **not** wired into the theme — the logo is
set through **Appearance → Customise → Site Identity**, so that an editor can
change it without a deploy.

---

## 14. What has not been verified

The build compiles, bundles and lints clean. It has not been executed, because
the build environment had no PHP runtime and no WordPress install. Specifically
unverified:

- **`npm run lint:php`** (phpcs) never ran. The PHP in this build follows the
  existing codebase's conventions and escaping contract by inspection, not by
  tooling.
- **The ACF field keys serialised into the patterns** are derived from the
  generator's documented `field_bgcf_{group}_{name}` scheme and were checked
  against the generated JSON by hand. They have not been round-tripped through
  ACF.
- **The mega menu against real menu data** — the tree builder, the third-level
  notice and the overview-cell insertion are untested against a real
  `wp_get_nav_menu_items()` result.
- **Lighthouse, axe and a keyboard pass.** The accessibility decisions above are
  reasoned and the contrast ratios are calculated, but nothing has been measured
  in a browser.

README §10 is the ordered list of what to do about it.
