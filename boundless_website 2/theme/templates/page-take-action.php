<?php
/**
 * Template Name: Action hub
 * Template Post Type: page
 *
 * The "ways to get involved" page: a masthead, then a grid of action cards each
 * with its own button, then a closing band.
 *
 * THIS TEMPLATE IS ALMOST EMPTY ON PURPOSE. Everything between the masthead and
 * the footer is composed from blocks — the action grid, a split section, a photo
 * band — because the set of ways to help changes with the season and should not
 * require a deploy. What the template contributes is the masthead and the
 * fallback: a page saved with no blocks yet still renders a usable hub, built
 * from the Volunteer and Donate links the options page already holds.
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
			'align'   => 'center',
		)
	);

	if ( has_blocks( get_post() ) ) {
		the_content();
	} else {
		/*
		 * The no-blocks fallback. An editor who publishes this page before
		 * composing it gets the two actions that always exist rather than a
		 * blank page between the banner and the footer.
		 */
		$bgcf_section = array(
			'class'      => 'bgcf-action-hub',
			'background' => 'soft',
			'width'      => 'wide',
			'labelledby' => 'bgcf-action-hub-title',
		);

		bgcf_section_open( $bgcf_section );

		if ( '' !== trim( (string) get_the_content() ) ) {
			echo '<div class="bgcf-prose bgcf-action-hub__intro">';
			the_content();
			echo '</div>';
		}

		bgcf_section_heading(
			array(
				'title' => bgcf_get_text( 'action_hub_title', __( 'Ways to get involved', 'bgcf' ) ),
				'id'    => 'bgcf-action-hub-title',
				'align' => 'center',
			)
		);

		$bgcf_fallback = array();

		foreach ( array( 'header_primary_cta', 'header_secondary_cta' ) as $bgcf_option_key ) {
			$bgcf_link = bgcf_option( $bgcf_option_key, '' );

			if ( ! is_array( $bgcf_link ) || empty( $bgcf_link['url'] ) ) {
				continue;
			}

			$bgcf_fallback[] = array(
				'title'     => (string) ( $bgcf_link['title'] ?? '' ),
				'url'       => (string) $bgcf_link['url'],
				'link_text' => (string) ( $bgcf_link['title'] ?? '' ),
			);
		}

		if ( ! empty( $bgcf_fallback ) ) {
			bgcf_card_grid( 'action', $bgcf_fallback, array( 'columns' => 2 ) );
		}

		bgcf_empty_state( __( 'Action hub: add an Action Grid block to this page to list the ways people can help.', 'bgcf' ) );

		bgcf_section_close( $bgcf_section );
	}
endwhile;

get_footer();
