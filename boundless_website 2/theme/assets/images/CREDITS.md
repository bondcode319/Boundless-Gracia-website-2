# Image credits and licences

Every image shipped in this theme is recorded here. **An image with no row in
this file must not be deployed.** That is not bureaucracy — it is the only way
to answer "where did this photo of a child come from, and who agreed to it?"
months after the person who uploaded it has left.

Policy: `PLAN.md` §7. Technical slots: `IMAGE-MANIFEST.md` in this directory.

---

## 1. Rules

1. **Licensed or commissioned only.** Licensed stock (with the licence
   certificate filed) or photography commissioned by the foundation. Never an
   image taken from another organisation's website, including any site used as a
   design reference.
2. **Self-hosted.** No hotlinking, ever. A third-party URL can change, rot, or
   be swapped for something the foundation would never publish.
3. **Guardian consent for identifiable children.** A child is identifiable if
   their face is recognisable, or if the image plus its caption would let a
   stranger find them. Consent must be written, must name the uses it covers,
   and must be filed with a reference recorded below.
   - The consent flag is enforced in code, not by memory: an image without
     `_bgcf_consent` set never appears in a public gallery. See
     `theme/inc/gallery.php`.
4. **Alt text is authored, never derived.** Written by the content team to
   describe what matters in the image. A filename is not alt text.
5. **Placeholders never ship.** Every row with `Status: placeholder` is a
   launch blocker.

## 2. Rows

| File | Subject | Source | Licence | Consent ref | Alt text author | Status |
|---|---|---|---|---|---|---|
| _(none yet)_ | — | — | — | — | — | — |

> The table is empty because no final imagery has been licensed yet. The theme
> ships with **no bitmap images at all** rather than with placeholders that could
> reach production by accident — the slots they would fill are specified in
> `IMAGE-MANIFEST.md`, and every block renders an editor-only empty state until
> a real image is set.

## 3. Icons

`assets/images/icons/*.svg` — 47 interface and brand icons, drawn for this
project on a 24×24 grid, 1.75px stroke, no embedded raster data or external
references. Original work; no licence obligation, no attribution required.

Brand marks (Facebook, X, Instagram, LinkedIn, YouTube, WhatsApp, TikTok) are
simplified glyphs drawn to the platforms' published shapes for the sole purpose
of linking to the foundation's own profiles — nominative use. They are not
modified logos and must not be recoloured away from `currentColor` or used to
imply endorsement.

## 4. Fonts

| File | Family | Licence |
|---|---|---|
| `assets/fonts/inter-var-latin*.woff2` | Inter (variable) | SIL Open Font License 1.1 |
| `assets/fonts/poppins-{500,600,700}-latin*.woff2` | Poppins | SIL Open Font License 1.1 |

Both are self-hosted, subset to Latin and Latin-Extended. The OFL requires the
licence text to travel with the fonts: keep `OFL.txt` alongside them if the
files are ever redistributed outside this theme.

## 5. On launch

- [ ] Every image slot in `IMAGE-MANIFEST.md` filled with a licensed asset.
- [ ] Every asset has a row in §2 above.
- [ ] Every licence certificate filed and referenced.
- [ ] Every identifiable child has a filed consent reference, and the matching
      attachment has publication consent ticked in the Media library.
- [ ] `npm run images:webp` run, and the WebP derivatives committed.
- [ ] No file in this directory is named `placeholder-*`.
