<?php
/**
 * Single programme — the Horizon programme page.
 *
 * The richest template on the site, and the one whose shape is fixed rather
 * than editor-composed, because a programme page has a job to do in a fixed
 * order: say what this is, show that it works, say what it costs, ask.
 *
 *   1. Photographic masthead with the programme's name and one-line summary.
 *   2. The outcome figures, as stat cards on the warm band.
 *   3. The approach — a split section, the programme's own prose beside its
 *      featured image.
 *   4. Whatever bands the editor added in the block editor.
 *   5. The campaign ask, if this programme is linked to a live campaign.
 *   6. Other programmes.
 *
 * Steps 2 and 5 RENDER NOTHING when their data is absent, rather than rendering
 * an empty band. A programme with no outcomes recorded yet is a normal state —
 * it is a programme that started last month — and a band reading "Outcomes"
 * with nothing under it is worse than no band.
 *
 * ACF fields (group: Programme details): icon, summary, location,
 * outcomes (repeater: value, label), approach (wysiwyg), campaign (post object),
 * cta (link).
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$bgcf_id       = (int) get_the_ID();
	$bgcf_summary  = (string) bgcf_field( 'summary', $bgcf_id, '' );
	$bgcf_location = (string) bgcf_field( 'location', $bgcf_id, '' );
	$bgcf_outcomes = (array) bgcf_field( 'outcomes', $bgcf_id, array() );
	$bgcf_approach = (string) bgcf_field( 'approach', $bgcf_id, '' );

	// ------------------------------------------------------------------ 1. Masthead
	bgcf_sub_banner(
		array(
			// The programme's location is the eyebrow: it is the single most
			// useful fact about a programme and it has nowhere else to go.
			'eyebrow' => '' !== $bgcf_location
				? $bgcf_location
				: bgcf_get_text( 'programme_eyebrow', __( 'Programme', 'bgcf' ) ),
			'title'   => (string) get_the_title(),
			'text'    => $bgcf_summary,
			'link'    => bgcf_field( 'cta', $bgcf_id, '' ),
		)
	);

	// ------------------------------------------------------------------ 2. Outcomes
	if ( ! empty( $bgcf_outcomes ) ) {
		$bgcf_outcomes_section = array(
			'class'      => 'bgcf-programme__outcomes',
			'background' => 'sand',
			'width'      => 'wide',
			'labelledby' => 'bgcf-programme-outcomes',
		);

		bgcf_section_open( $bgcf_outcomes_section );

		bgcf_section_heading(
			array(
				'title' => bgcf_get_text( 'programme_outcomes_title', __( 'What this programme has changed', 'bgcf' ) ),
				'id'    => 'bgcf-programme-outcomes',
				'align' => 'center',
			)
		);

		$bgcf_cards = array();

		foreach ( $bgcf_outcomes as $bgcf_outcome ) {
			if ( ! is_array( $bgcf_outcome ) ) {
				continue;
			}

			$bgcf_cards[] = array(
				'value' => (string) ( $bgcf_outcome['value'] ?? '' ),
				'label' => (string) ( $bgcf_outcome['label'] ?? '' ),
				'icon'  => (string) bgcf_field( 'icon', $bgcf_id, '' ),
			);
		}

		bgcf_card_grid(
			'stat',
			$bgcf_cards,
			array(
				'columns' => 4,
				'tag'     => 'div',
				'class'   => 'bgcf-stats-grid',
			)
		);

		bgcf_section_close( $bgcf_outcomes_section );
	}

	// ------------------------------------------------------------------ 3. Approach
	if ( '' !== trim( $bgcf_approach ) ) {
		bgcf_split_section(
			array(
				'eyebrow'    => bgcf_get_text( 'programme_approach_eyebrow', __( 'How it works', 'bgcf' ) ),
				'title'      => bgcf_get_text( 'programme_approach_title', __( 'Our approach', 'bgcf' ) ),
				'text'       => $bgcf_approach,
				'image'      => (int) get_post_thumbnail_id(),
				'media_side' => 'end',
				'ratio'      => 'text',
				'background' => 'page',
			)
		);
	}

	// ------------------------------------------------- 4. Editor-composed bands
	if ( has_blocks( get_post() ) ) {
		the_content();
	} elseif ( '' !== trim( (string) get_the_content() ) ) {
		$bgcf_prose = array(
			'class'      => 'bgcf-programme__prose',
			'background' => 'soft',
			'width'      => 'narrow',
		);

		bgcf_section_open( $bgcf_prose );
		echo '<div class="bgcf-prose">';
		the_content();
		echo '</div>';
		bgcf_section_close( $bgcf_prose );
	}

	// ------------------------------------------------------------------ 5. The ask
	$bgcf_campaign = bgcf_to_post_ids( bgcf_field( 'campaign', $bgcf_id, 0 ) );
	$bgcf_campaign = (int) ( $bgcf_campaign[0] ?? 0 );

	if ( $bgcf_campaign > 0 && 'publish' === get_post_status( $bgcf_campaign ) ) {
		bgcf_photo_cta(
			array(
				'eyebrow' => bgcf_get_text( 'programme_campaign_eyebrow', __( 'Fund this work', 'bgcf' ) ),
				'title'   => (string) get_the_title( $bgcf_campaign ),
				'text'    => (string) bgcf_field( 'summary', $bgcf_campaign, '' ),
				'image'   => (int) get_post_thumbnail_id( $bgcf_campaign ),
				'link'    => array(
					'title' => bgcf_get_text( 'programme_campaign_cta', __( 'Give to this campaign', 'bgcf' ) ),
					'url'   => (string) get_permalink( $bgcf_campaign ),
				),
			)
		);
	}

	// ------------------------------------------------------ 6. Other programmes
	bgcf_render_partial(
		'global/related-posts',
		array(
			'post_type' => 'bgcf_program',
			'exclude'   => $bgcf_id,
		)
	);
endwhile;

get_footer();
