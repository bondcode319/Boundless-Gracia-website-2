<?php
/**
 * 404 — page not found.
 *
 * A 404 on a donation site is a lost supporter, so this page is a route back
 * rather than an apology: a short explanation, a search box, and two links an
 * editor controls.
 *
 * IT OPENS WITH A PHOTOGRAPHIC BAND, like every other page, and
 * `bgcf_header_is_overlay()` returns true for `is_404()` so the header floats
 * on it. The alternative — a bare white page with grey text — reads as a server
 * fault, and a visitor who thinks the site is broken does not try a second link.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-error-404',
	// The banner's own band: a photograph when the options page supplies one,
	// flat deep blue otherwise. Either way the heading is on a dark surface.
	'background' => bgcf_image_id( bgcf_option( 'error_404_image', 0 ) ) ? 'photo' : 'ink',
	'image'      => bgcf_image_id( bgcf_option( 'error_404_image', 0 ) ),
	'spacing'    => 'lg',
	'width'      => 'narrow',
	'labelledby' => 'bgcf-404-title',
);

bgcf_section_open( $bgcf_section );
?>

	<div class="bgcf-error-404__inner">
		<p class="bgcf-error-404__code" aria-hidden="true">404</p>

		<h1 class="bgcf-error-404__title" id="bgcf-404-title">
			<?php bgcf_the_text( 'error_404_title', __( 'We could not find that page', 'bgcf' ) ); ?>
		</h1>

		<p class="bgcf-error-404__text">
			<?php bgcf_the_text( 'error_404_text', __( 'The page may have moved, or the link may be out of date. Try a search, or head back to the homepage.', 'bgcf' ) ); ?>
		</p>

		<div class="bgcf-error-404__search">
			<?php get_search_form(); ?>
		</div>

		<div class="bgcf-error-404__actions">
			<?php
			bgcf_button(
				array(
					'text'    => bgcf_get_text( 'error_404_home_label', __( 'Back to homepage', 'bgcf' ) ),
					'url'     => home_url( '/' ),
					'variant' => 'primary',
					'size'    => 'lg',
				)
			);

			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				bgcf_option( 'error_404_cta', '' ),
				array(
					'variant' => 'ghost',
					'size'    => 'lg',
					'class'   => 'bgcf-btn--on-photo',
				)
			);
			?>
		</div>
	</div>

<?php
bgcf_section_close( $bgcf_section );

/*
 * Programmes, below the band. A visitor who hit a dead link was looking for
 * something; three real destinations are a better answer than a search box
 * alone, and the block is the same one the homepage uses.
 */
$bgcf_programmes = bgcf_block_query( 'bgcf_program', array( 'limit' => 3 ) );

if ( $bgcf_programmes->have_posts() ) {
	$bgcf_programmes_section = array(
		'class'      => 'bgcf-error-404__suggestions',
		'background' => 'page',
		'width'      => 'wide',
		'labelledby' => 'bgcf-404-suggestions',
	);

	bgcf_section_open( $bgcf_programmes_section );

	bgcf_section_heading(
		array(
			'title' => bgcf_get_text( 'error_404_suggestions', __( 'You might be looking for', 'bgcf' ) ),
			'id'    => 'bgcf-404-suggestions',
			'align' => 'center',
		)
	);

	$bgcf_cards = array();

	while ( $bgcf_programmes->have_posts() ) {
		$bgcf_programmes->the_post();

		$bgcf_cards[] = bgcf_card_args_from_post(
			get_post(),
			array( 'text' => (string) bgcf_field( 'summary', get_the_ID(), '' ) )
		);
	}

	wp_reset_postdata();

	bgcf_card_grid( 'project', $bgcf_cards, array( 'columns' => 3 ) );

	bgcf_section_close( $bgcf_programmes_section );
}

get_footer();
