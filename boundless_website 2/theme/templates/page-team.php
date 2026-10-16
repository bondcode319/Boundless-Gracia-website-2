<?php
/**
 * Template Name: Team directory
 * Template Post Type: page
 *
 * The people page: masthead, the page's own introduction, then the team grouped
 * by department.
 *
 * The grid itself is `bgcf/team-grid`, so an editor can place it anywhere and
 * configure it. This template only renders it automatically when the page has
 * no blocks — which is the common case, because "Our team" is a page nobody
 * wants to compose.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	bgcf_sub_banner(
		array(
			'eyebrow' => (string) bgcf_field( 'page_eyebrow', null, '' ),
			'title'   => (string) get_the_title(),
			'text'    => (string) bgcf_field( 'page_lead', null, '' ),
		)
	);

	if ( has_blocks( get_post() ) ) {
		the_content();
	} else {
		if ( '' !== trim( (string) get_the_content() ) ) {
			$bgcf_intro = array(
				'class'      => 'bgcf-team__intro',
				'background' => 'page',
				'width'      => 'narrow',
				'spacing'    => 'sm',
			);

			bgcf_section_open( $bgcf_intro );
			echo '<div class="bgcf-prose">';
			the_content();
			echo '</div>';
			bgcf_section_close( $bgcf_intro );
		}

		/*
		 * The team grid, rendered through the block's own partial rather than
		 * reimplemented here. `bgcf_field()` returns the ACF defaults when there
		 * is no block context, which is exactly the configuration this page
		 * wants: grouped by department, four across, no bios.
		 */
		bgcf_render_partial(
			'blocks/block-team-grid',
			array(
				'anchor' => 'team',
				'class'  => 'bgcf-block bgcf-block-team-grid',
			)
		);
	}

	/*
	 * The volunteer ask. A visitor who has just read about the team is the most
	 * likely person on the site to want to join it.
	 */
	bgcf_photo_cta(
		array(
			'title' => bgcf_get_text( 'team_cta_title', __( 'There is a place here for you too', 'bgcf' ) ),
			'text'  => bgcf_get_text( 'team_cta_text', __( 'Our work runs on volunteers — teachers, builders, drivers, mentors and people who are simply willing to show up.', 'bgcf' ) ),
			'image' => bgcf_image_id( bgcf_option( 'listing_cta_image', 0 ) ),
			'link'  => bgcf_option( 'header_secondary_cta', '' ),
		)
	);
endwhile;

get_footer();
