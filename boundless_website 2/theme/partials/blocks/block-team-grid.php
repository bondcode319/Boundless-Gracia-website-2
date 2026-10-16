<?php
/**
 * Block: Team Grid — `bgcf/team-grid`.
 *
 * People from the Team post type as portrait cards, optionally grouped under
 * department headings.
 *
 * THE GROUPED MODE IS THE INTERESTING ONE. A flat directory of thirty people
 * tells a visitor nothing about who does what; grouped by department it answers
 * "who leads this" and "who are the trustees" at a glance. Each group is its own
 * `<section>` with its own `h3`, so the page outline reflects the structure
 * rather than being thirty sibling cards under one heading.
 *
 * Groups are rendered in the DEPARTMENT TAXONOMY's own term order, not
 * alphabetically: an editor who has put Leadership before Programmes before
 * Trustees meant that order, and alphabetising it would put the board first.
 *
 * ACF fields (group: Block — Team Grid):
 *   eyebrow (text), heading (text), lead (textarea), background (select),
 *   grouped (true/false), columns (select 3|4), limit (number),
 *   show_bio (true/false), departments (taxonomy, multiple)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading  = (string) bgcf_field( 'heading', null, '' );
$bgcf_grouped  = (bool) bgcf_field( 'grouped', null, true );
$bgcf_columns  = (int) bgcf_field( 'columns', null, 4 );
$bgcf_show_bio = (bool) bgcf_field( 'show_bio', null, false );
$bgcf_limit    = max( 1, min( 60, (int) bgcf_field( 'limit', null, 24 ) ) );

$bgcf_heading_id = 'bgcf-team-' . (string) wp_unique_id();

/**
 * Map one team member to the person-card bundle.
 *
 * Closure rather than a named function: it needs `$bgcf_show_bio` and it is
 * meaningless outside this block, so lifting it into `inc/` would be adding a
 * public API for one caller.
 *
 * @param WP_Post $member Team member post.
 * @return array<string, mixed> Card arguments.
 */
$bgcf_to_card = static function ( WP_Post $member ) use ( $bgcf_show_bio ): array {
	$role = (string) bgcf_field( 'role', $member->ID, '' );

	return bgcf_card_args_from_post(
		$member,
		array(
			'label' => $role,
			// A role is not a bio. When bios are off, the card shows the name
			// and the role only — and the excerpt that `bgcf_card_args_from_post`
			// generated has to be cleared, or the card shows the first
			// twenty-four words of the profile under the name regardless.
			'text'  => $bgcf_show_bio ? (string) bgcf_field( 'bio', $member->ID, '' ) : '',
			// A profile is only worth linking to when there is one to read.
			'url'   => '' !== trim( (string) get_post_field( 'post_content', $member ) )
				? (string) get_permalink( $member )
				: '',
		)
	);
};

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => (string) bgcf_field( 'background', null, 'page' ),
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

bgcf_section_heading(
	array(
		'eyebrow' => (string) bgcf_field( 'eyebrow', null, '' ),
		'title'   => $bgcf_heading,
		'lead'    => (string) bgcf_field( 'lead', null, '' ),
		'id'      => $bgcf_heading_id,
		'align'   => 'center',
	)
);

$bgcf_rendered = 0;

if ( $bgcf_grouped && taxonomy_exists( 'bgcf_department' ) ) {
	$bgcf_departments = get_terms(
		array(
			'taxonomy'   => 'bgcf_department',
			'hide_empty' => true,
			// Term order as the editor arranged it; see the file header.
			'orderby'    => 'term_order',
		)
	);

	if ( ! is_wp_error( $bgcf_departments ) ) {
		foreach ( $bgcf_departments as $bgcf_department ) {
			$bgcf_query = bgcf_block_query(
				'bgcf_team',
				array(
					'source'   => 'term',
					'term'     => $bgcf_department->term_id,
					'taxonomy' => 'bgcf_department',
					'limit'    => 12,
				)
			);

			if ( ! $bgcf_query->have_posts() ) {
				continue;
			}

			$bgcf_cards = array();

			while ( $bgcf_query->have_posts() ) {
				$bgcf_query->the_post();
				$bgcf_cards[] = $bgcf_to_card( get_post() );
			}

			wp_reset_postdata();

			printf(
				'<section class="bgcf-team-group"><h3 class="bgcf-team-group__title">%s</h3>',
				esc_html( $bgcf_department->name )
			);

			if ( '' !== trim( (string) $bgcf_department->description ) ) {
				printf(
					'<p class="bgcf-team-group__text">%s</p>',
					esc_html( (string) $bgcf_department->description )
				);
			}

			$bgcf_rendered += bgcf_card_grid(
				'person',
				array_map(
					static function ( $card ) {
						$card['title_tag'] = 'h4';

						return $card;
					},
					$bgcf_cards
				),
				array(
					'columns' => $bgcf_columns,
					'label'   => $bgcf_department->name,
				)
			);

			echo '</section>';
		}
	}
}

// Flat mode, and the fallback when the department taxonomy has no terms yet —
// a team with no departments assigned should still render as a directory.
if ( 0 === $bgcf_rendered ) {
	$bgcf_query = bgcf_block_query( 'bgcf_team', array( 'limit' => $bgcf_limit ) );

	if ( $bgcf_query->have_posts() ) {
		$bgcf_cards = array();

		while ( $bgcf_query->have_posts() ) {
			$bgcf_query->the_post();
			$bgcf_cards[] = $bgcf_to_card( get_post() );
		}

		wp_reset_postdata();

		$bgcf_rendered = bgcf_card_grid(
			'person',
			$bgcf_cards,
			array(
				'columns' => $bgcf_columns,
				'label'   => '' !== $bgcf_heading ? $bgcf_heading : bgcf_get_text( 'team_label', __( 'Our team', 'bgcf' ) ),
			)
		);
	}
}

if ( 0 === $bgcf_rendered ) {
	bgcf_empty_state( __( 'Team grid: add people under Team in the admin menu.', 'bgcf' ) );
}

bgcf_section_close( $bgcf_section );
