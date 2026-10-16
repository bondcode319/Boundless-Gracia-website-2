<?php
/**
 * Block: Program Cards — `bgcf/program-cards`.
 *
 * The programmes grid. Queries the Programs post type through
 * `bgcf_block_query()` (three selection modes: curated order, category filter,
 * or newest first) and renders project cards.
 *
 * WHY `summary` RATHER THAN THE EXCERPT. A programme's ACF `summary` field is a
 * single sentence written for exactly this card. The generated excerpt is the
 * first twenty-four words of the programme page, which starts with context
 * rather than with a summary and reads as a sentence cut off mid-thought. The
 * excerpt is the fallback, not the source.
 *
 * ACF fields (group: Block — Program Cards):
 *   eyebrow (text), heading (text), lead (textarea), background (select),
 *   source (select latest|manual|term), programs (relationship -> bgcf_program),
 *   category (taxonomy -> bgcf_program_category), limit (number, default 3),
 *   columns (select 2|3|4), show_excerpt (true/false), archive_cta (link)
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_show_text = (bool) bgcf_field( 'show_excerpt', null, true );

$bgcf_heading_id = 'bgcf-programs-title-' . (string) wp_unique_id();

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

$bgcf_query = bgcf_block_query(
	'bgcf_program',
	array(
		'source'   => (string) bgcf_field( 'source', null, 'latest' ),
		'manual'   => bgcf_field( 'programs', null, array() ),
		'term'     => bgcf_field( 'category', null, 0 ),
		'taxonomy' => 'bgcf_program_category',
		'limit'    => (int) bgcf_field( 'limit', null, 3 ),
	)
);

if ( ! $bgcf_query->have_posts() ) {
	bgcf_empty_state( __( 'Program cards: publish a programme, or choose which programmes to show in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'eyebrow' => (string) bgcf_field( 'eyebrow', null, '' ),
		'title'   => $bgcf_heading,
		'lead'    => (string) bgcf_field( 'lead', null, '' ),
		'id'      => $bgcf_heading_id,
		'align'   => 'center',
	)
);

$bgcf_cards = array();

while ( $bgcf_query->have_posts() ) {
	$bgcf_query->the_post();

	$bgcf_id      = get_the_ID();
	$bgcf_summary = (string) bgcf_field( 'summary', $bgcf_id, '' );
	$bgcf_overrides = array(
		'link_text' => bgcf_get_text( 'program_card_cta', __( 'Learn more', 'bgcf' ) ),
	);

	if ( ! $bgcf_show_text ) {
		$bgcf_overrides['text'] = '';
	} elseif ( '' !== trim( $bgcf_summary ) ) {
		$bgcf_overrides['text'] = $bgcf_summary;
	}

	// The programme's location is useful on a card and invisible on the
	// programme page's own hero, so it is surfaced here as card meta.
	$bgcf_location = (string) bgcf_field( 'location', $bgcf_id, '' );

	if ( '' !== trim( $bgcf_location ) ) {
		$bgcf_overrides['meta'] = array( $bgcf_location );
	}

	$bgcf_cards[] = bgcf_card_args_from_post( get_post(), $bgcf_overrides );
}

wp_reset_postdata();

bgcf_card_grid(
	'project',
	$bgcf_cards,
	array(
		'columns' => (int) bgcf_field( 'columns', null, 3 ),
		'label'   => '' !== $bgcf_heading ? $bgcf_heading : bgcf_get_text( 'programs_label', __( 'Our programmes', 'bgcf' ) ),
	)
);

$bgcf_archive_button = bgcf_get_link_button(
	bgcf_field( 'archive_cta', null, array() ),
	array(
		'variant' => 'secondary',
		'icon'    => 'arrow-right',
	)
);

if ( '' !== $bgcf_archive_button ) {
	printf(
		'<div class="bgcf-section__footer">%s</div>',
		$bgcf_archive_button // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
	);
}

bgcf_section_close( $bgcf_section );
