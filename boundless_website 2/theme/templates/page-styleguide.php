<?php
/**
 * Template Name: Styleguide (Dev)
 *
 * The Phase 1 verification surface: renders every design token and base
 * component so the system can be checked at a glance. Assign it to a private,
 * noindex page during development; it is a developer tool, not editorial
 * content, which is why it carries literal labels rather than ACF copy.
 *
 * Colour chips reference the token custom properties inline (e.g.
 * `var(--bgcf-color-primary)`), never a raw hex — so the page proves the tokens
 * resolve, which is the whole point. Contrast ratios shown are the measured
 * values from assets/scss/settings/_colors.scss.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

/*
 * Data tables driving the specimens. Kept inline: this is a dev template, not a
 * content page, so there is nothing to make editable in wp-admin.
 */
$bgcf_brand_colors = array(
	'primary'   => array( 'Deep Blue', '#123B63', '11.47:1 on white', true ),
	'secondary' => array( 'Emerald Green', '#1E8A5A', '4.34:1 — fills w/ ink only', false ),
	'cta'       => array( 'Action Orange', '#F26A21', '5.12:1 w/ ink text', true ),
	'accent'    => array( 'Accent Yellow', '#F5C518', '1.63:1 — background only', false ),
	'surface'   => array( 'Light Gray', '#F4F6F8', 'Background', true ),
	'base'      => array( 'White', '#FFFFFF', 'Card surface', true ),
	'ink'       => array( 'Dark Text', '#1A2430', '15.68:1 on white', true ),
);

$bgcf_type_scale = array(
	'5xl' => 'Statistic counter',
	'4xl' => 'Hero heading',
	'3xl' => 'Page h1',
	'2xl' => 'Display / h2',
	'xl'  => 'Heading / h3',
	'lg'  => 'Lead paragraph',
	'base' => 'Body copy',
	'sm'  => 'Small / meta',
	'xs'  => 'Caption',
);

$bgcf_space_scale  = array( '2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl' );
$bgcf_radius_scale = array( 'sm', 'md', 'lg', 'xl', '2xl', 'pill' );
$bgcf_shadow_scale = array( 'xs', 'sm', 'md', 'lg', 'xl', '2xl' );
?>

<article class="bgcf-styleguide">

	<?php
	bgcf_section_open(
		array(
			'background' => 'surface',
			'spacing'    => 'md',
			'width'      => 'wide',
		)
	);

	bgcf_section_heading(
		array(
			'eyebrow' => 'Design system',
			'title'   => 'Boundless Gracia — Styleguide',
			'lead'    => 'Every token and base component, rendered from the compiled stylesheet. If a swatch is transparent or a component is unstyled, the build is broken.',
			'tag'     => 'h1',
		)
	);
	?>

	<!-- ================================================================= -->
	<!-- Colour -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Brand colours">
		<h2 class="bgcf-styleguide__group-title">Brand palette</h2>
		<div class="bgcf-styleguide__grid">
			<?php foreach ( $bgcf_brand_colors as $bgcf_slug => $bgcf_meta ) : ?>
				<div class="bgcf-swatch">
					<div
						class="bgcf-swatch__chip"
						style="<?php echo esc_attr( 'background-color:var(--bgcf-color-' . sanitize_html_class( $bgcf_slug ) . ');' ); ?>"
					></div>
					<div class="bgcf-swatch__meta">
						<span class="bgcf-swatch__name"><?php echo esc_html( $bgcf_meta[0] ); ?></span>
						<span class="bgcf-swatch__value"><?php echo esc_html( $bgcf_meta[1] ); ?></span>
						<span class="bgcf-swatch__ratio bgcf-swatch__ratio--<?php echo $bgcf_meta[3] ? 'pass' : 'fail'; ?>">
							<?php echo esc_html( $bgcf_meta[2] ); ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- ================================================================= -->
	<!-- Typography -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Type scale">
		<h2 class="bgcf-styleguide__group-title">Type scale</h2>
		<?php foreach ( $bgcf_type_scale as $bgcf_size => $bgcf_use ) : ?>
			<p
				style="<?php echo esc_attr( 'font-size:var(--bgcf-font-size-' . sanitize_html_class( $bgcf_size ) . ');font-family:var(--bgcf-font-heading);margin-block:var(--bgcf-space-3);' ); ?>"
			>
				<?php echo esc_html( $bgcf_use ); ?>
				<span class="bgcf-swatch__value"><?php echo esc_html( '— ' . $bgcf_size ); ?></span>
			</p>
		<?php endforeach; ?>

		<h3 class="bgcf-styleguide__subtitle">Families</h3>
		<p style="font-family:var(--bgcf-font-heading);font-size:var(--bgcf-font-size-xl);">Poppins — headings. The quick brown fox jumps over the lazy dog.</p>
		<p style="font-family:var(--bgcf-font-body);font-size:var(--bgcf-font-size-base);">Inter — body. The quick brown fox jumps over the lazy dog. 0123456789.</p>
	</section>

	<!-- ================================================================= -->
	<!-- Spacing -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Spacing scale">
		<h2 class="bgcf-styleguide__group-title">Spacing scale</h2>
		<div class="bgcf-styleguide__grid">
			<?php foreach ( $bgcf_space_scale as $bgcf_step ) : ?>
				<div class="bgcf-specimen">
					<span
						class="bgcf-specimen__box"
						style="<?php echo esc_attr( 'inline-size:var(--bgcf-space-' . sanitize_html_class( $bgcf_step ) . ');block-size:var(--bgcf-space-' . sanitize_html_class( $bgcf_step ) . ');' ); ?>"
					></span>
					<span><?php echo esc_html( 'space-' . $bgcf_step ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- ================================================================= -->
	<!-- Radius + Shadow -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Radius and elevation">
		<h2 class="bgcf-styleguide__group-title">Radius</h2>
		<div class="bgcf-styleguide__grid">
			<?php foreach ( $bgcf_radius_scale as $bgcf_r ) : ?>
				<div class="bgcf-specimen">
					<span
						class="bgcf-specimen__surface"
						style="<?php echo esc_attr( 'border-radius:var(--bgcf-radius-' . sanitize_html_class( $bgcf_r ) . ');' ); ?>"
					></span>
					<span><?php echo esc_html( 'radius-' . $bgcf_r ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<h2 class="bgcf-styleguide__group-title" style="margin-block-start:var(--bgcf-space-xl);">Elevation</h2>
		<div class="bgcf-styleguide__grid">
			<?php foreach ( $bgcf_shadow_scale as $bgcf_s ) : ?>
				<div class="bgcf-specimen">
					<span
						class="bgcf-specimen__surface"
						style="<?php echo esc_attr( 'box-shadow:var(--bgcf-shadow-' . sanitize_html_class( $bgcf_s ) . ');border-color:transparent;' ); ?>"
					></span>
					<span><?php echo esc_html( 'shadow-' . $bgcf_s ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- ================================================================= -->
	<!-- Buttons -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Buttons">
		<h2 class="bgcf-styleguide__group-title">Buttons</h2>

		<h3 class="bgcf-styleguide__subtitle">Variants</h3>
		<div class="bgcf-styleguide__row">
			<?php
			bgcf_button( array( 'text' => 'Donate now', 'url' => '#', 'variant' => 'primary' ) );
			bgcf_button( array( 'text' => 'Volunteer', 'url' => '#', 'variant' => 'secondary' ) );
			bgcf_button( array( 'text' => 'Learn more', 'url' => '#', 'variant' => 'ghost' ) );
			bgcf_button( array( 'text' => 'Read the story', 'url' => '#', 'variant' => 'link' ) );
			?>
		</div>

		<h3 class="bgcf-styleguide__subtitle">Sizes</h3>
		<div class="bgcf-styleguide__row">
			<?php
			bgcf_button( array( 'text' => 'Small', 'url' => '#', 'variant' => 'primary', 'size' => 'sm' ) );
			bgcf_button( array( 'text' => 'Medium', 'url' => '#', 'variant' => 'primary', 'size' => 'md' ) );
			bgcf_button( array( 'text' => 'Large', 'url' => '#', 'variant' => 'primary', 'size' => 'lg' ) );
			?>
		</div>

		<h3 class="bgcf-styleguide__subtitle">States</h3>
		<div class="bgcf-styleguide__row">
			<?php
			bgcf_button( array( 'text' => 'Disabled', 'variant' => 'primary', 'attrs' => array( 'disabled' => true ) ) );
			bgcf_button( array( 'text' => 'Submitting', 'variant' => 'primary', 'attrs' => array( 'data-bgcf-loading' => 'true' ) ) );
			?>
		</div>
	</section>

	<!-- ================================================================= -->
	<!-- Card -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Card">
		<h2 class="bgcf-styleguide__group-title">Card shell</h2>
		<div class="bgcf-styleguide__grid">
			<article class="bgcf-card">
				<div class="bgcf-card__media">
					<span class="bgcf-card__image" style="display:block;block-size:100%;background:var(--bgcf-color-primary-100);" aria-hidden="true"></span>
				</div>
				<div class="bgcf-card__body">
					<p class="bgcf-card__meta"><span>Education</span></p>
					<h3 class="bgcf-card__title"><a class="bgcf-card__link" href="#">Keeping girls in school</a></h3>
					<p class="bgcf-card__excerpt">The base card shell — media frame, body, stretched title link and hover lift — that program, story and campaign cards extend.</p>
				</div>
			</article>
		</div>
	</section>

	<!-- ================================================================= -->
	<!-- Forms -->
	<!-- ================================================================= -->
	<section class="bgcf-styleguide__group" aria-label="Form controls">
		<h2 class="bgcf-styleguide__group-title">Form controls</h2>
		<div class="bgcf-field" style="max-inline-size:var(--bgcf-measure-narrow);">
			<label class="bgcf-field__label" for="bgcf-sg-name">Full name</label>
			<input id="bgcf-sg-name" type="text" placeholder="Ada Achieng" />
			<span class="bgcf-field__help">Help text uses the muted token.</span>
		</div>
		<div class="bgcf-field" style="max-inline-size:var(--bgcf-measure-narrow);margin-block-start:var(--bgcf-space-4);">
			<label class="bgcf-field__label" for="bgcf-sg-select">Program</label>
			<select id="bgcf-sg-select">
				<option>Medical Support</option>
				<option>Education</option>
				<option>Women Empowerment</option>
				<option>Community Development</option>
			</select>
		</div>
		<form class="bgcf-search-form" role="search" style="max-inline-size:var(--bgcf-measure-narrow);margin-block-start:var(--bgcf-space-4);">
			<label class="bgcf-search-form__label" for="bgcf-sg-search">Search</label>
			<input class="bgcf-search-form__input" id="bgcf-sg-search" type="search" placeholder="Search the site" />
			<?php bgcf_button( array( 'text' => 'Search', 'variant' => 'primary', 'type' => 'submit', 'class' => 'bgcf-search-form__submit' ) ); ?>
		</form>
	</section>

	<?php
	bgcf_section_close(
		array(
			'width' => 'wide',
		)
	);
	?>

</article>

<?php
get_footer();
