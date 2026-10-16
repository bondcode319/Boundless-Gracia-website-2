# `woocommerce/` — reserved, intentionally empty

**WooCommerce is not used on this project.** Donations are handled by the
theme's own **Flutterwave integration** (`inc/payments*.php`, `inc/donations.php`
— see PAYMENTS.md). This directory exists only so the theme structure matches the one
in `CLAUDE.md` and so a future e-commerce requirement (merchandise, ticketed
events, a shop) has an obvious, conventional home.

## Do not

- Do not install or activate WooCommerce to "make donations work". The
  Flutterwave integration covers UGX mobile money and card giving, receipts and
  donation records.
- Do not add `add_theme_support( 'woocommerce' )` in `inc/setup.php`. Declaring
  support loads WooCommerce template hooks and image sizing that this design
  system does not account for.
- Do not put unrelated template overrides here. Only WooCommerce template
  overrides belong in this directory.

## If WooCommerce is ever adopted

1. Add `add_theme_support( 'woocommerce' )` plus the gallery supports to
   `inc/setup.php`.
2. Create `inc/woocommerce.php` as an eleventh module and require it from
   `functions.php` — do not scatter WooCommerce logic through existing modules.
3. Copy the templates you need from `woocommerce/templates/` in the plugin into
   this directory, preserving the plugin's path structure.
4. Add a `assets/scss/vendor/_woocommerce.scss` layer that maps WooCommerce
   classes onto the design tokens. Do not restyle WooCommerce inline.
5. Re-run the accessibility and Lighthouse gates — WooCommerce adds significant
   CSS and JS on every page unless explicitly dequeued.
