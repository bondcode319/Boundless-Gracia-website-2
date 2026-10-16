<?php
/**
 * Template Name: Donation hub
 * Template Post Type: page
 *
 * The giving page. A photographic masthead with the donation form sitting ON it,
 * then whatever "ways to give" bands the editor has composed below.
 *
 * WHY THE FORM IS IN THE MASTHEAD and not further down the page: this is the one
 * page where the visitor has already decided. Every pixel between arriving and
 * the amount buttons is a chance to lose them, and the industry evidence on
 * donation pages is consistent about it. So the form is the first thing on the
 * page, above the fold, with the reassurance (what the money does, who runs the
 * organisation, how the payment is handled) underneath it where a visitor who
 * wants it can find it.
 *
 * THE FORM IS A LIGHT CARD on the photograph, not an inverted form. White-on-
 * blue inputs read as disabled and their placeholder text fails contrast; a
 * light card is honest and it is also the strongest focal point on the page.
 * `bgcf-u-on-light` restores the light-surface colour roles inside it.
 *
 * THE HEADER OVERLAYS THIS PAGE. `bgcf_header_is_overlay()` is filtered to true
 * for this template, because the masthead is a full-bleed photographic band
 * exactly like a hero — the filter is the sanctioned way for a template to say
 * so, rather than duplicating the header.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'bgcf_header_is_overlay', '__return_true' );

get_header();

while ( have_posts() ) :
	the_post();

	$bgcf_image = (int) get_post_thumbnail_id();

	$bgcf_masthead = array(
		'tag'        => 'header',
		'class'      => 'bgcf-donate-hero',
		'background' => $bgcf_image ? 'photo' : 'ink',
		'image'      => $bgcf_image,
		// Raised from the 62% default: the donation form sits on this band and a
		// form needs a calmer backdrop than a heading does.
		'scrim'      => 72,
		'spacing'    => 'lg',
		'width'      => 'wide',
		'labelledby' => 'bgcf-donate-title',
	);

	bgcf_section_open( $bgcf_masthead );
	?>

	<div class="bgcf-donate-hero__inner">

		<div class="bgcf-donate-hero__copy">
			<?php
			bgcf_breadcrumbs();

			$bgcf_eyebrow = (string) bgcf_field( 'page_eyebrow', null, '' );

			if ( '' !== $bgcf_eyebrow ) {
				printf( '<p class="bgcf-donate-hero__eyebrow">%s</p>', esc_html( $bgcf_eyebrow ) );
			}
			?>

			<h1 class="bgcf-donate-hero__title" id="bgcf-donate-title"><?php the_title(); ?></h1>

			<?php
			$bgcf_lead = (string) bgcf_field( 'page_lead', null, '' );

			if ( '' !== $bgcf_lead ) {
				printf( '<div class="bgcf-donate-hero__lead">%s</div>', wp_kses_post( wpautop( $bgcf_lead ) ) );
			}

			/*
			 * The impact lines — "UGX 50,000 keeps one child in school for a
			 * term" — come from inc/campaigns.php, which derives them from the
			 * campaign's own costings. They belong beside the form rather than
			 * below it: an amount button means nothing until the visitor knows
			 * what the amount buys.
			 */
			$bgcf_impact = function_exists( 'bgcf_impact_lines' ) ? (array) bgcf_impact_lines() : array();

			if ( ! empty( $bgcf_impact ) ) {
				printf(
					'<ul class="bgcf-donate-hero__impact" aria-label="%s">',
					esc_attr( bgcf_get_text( 'donate_impact_label', __( 'What your gift pays for', 'bgcf' ) ) )
				);

				foreach ( $bgcf_impact as $bgcf_line ) {
					printf(
						'<li class="bgcf-donate-hero__impact-item">%1$s<span>%2$s</span></li>',
						bgcf_get_svg_icon( 'check-circle' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised in helper.
						esc_html( (string) $bgcf_line )
					);
				}

				echo '</ul>';
			}
			?>
		</div>

		<div class="bgcf-donate-hero__form">
			<div class="bgcf-donate-card bgcf-u-on-light">
				<?php
				/*
				 * The same donation form partial the `bgcf/donation-banner`
				 * block renders, so the amount presets, the mobile-money
				 * handling and the Flutterwave wiring have one implementation.
				 * See inc/payments-routes.php and PAYMENTS.md.
				 */
				bgcf_render_partial(
					'global/donation-form',
					array(
						'source' => 'donate-page',
						'class'  => 'bgcf-donation--hub',
					)
				);
				?>
			</div>
		</div>

	</div>

	<?php
	bgcf_section_close( $bgcf_masthead );

	/*
	 * Everything below the masthead is the editor's: "ways to give" card bands,
	 * a split section about financial accountability, the FAQ, the partner row.
	 * Emitted bare so each block's own band reaches the screen edge.
	 */
	if ( has_blocks( get_post() ) ) {
		the_content();
	} else {
		$bgcf_prose = array(
			'class'      => 'bgcf-donate__prose',
			'background' => 'page',
			'width'      => 'narrow',
		);

		bgcf_section_open( $bgcf_prose );
		echo '<div class="bgcf-prose">';
		the_content();
		echo '</div>';
		bgcf_section_close( $bgcf_prose );
	}
endwhile;

get_footer();
