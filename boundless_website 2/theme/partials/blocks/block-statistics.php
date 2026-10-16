<?php
/**
 * Block: Statistics — `bgcf/statistics`.
 *
 * The impact band: four figures that count up as they scroll into view.
 *
 * Rendering goes through `bgcf_card_grid( 'stat', … )` rather than being built
 * here. That matters because the counter's accessibility contract — the real,
 * locale-formatted value in the markup, overwritten and then restored by the
 * animation — is subtle enough that having two copies of it would guarantee one
 * of them drifts. `partials/cards/card-stat.php` is the one copy, and it carries
 * the explanation.
 *
 * `sand` is the default band: in the Horizon rhythm the impact figures sit on
 * the warm tint, between the projects grid and the second photographic band.
 *
 * ACF fields (group: Block — Statistics):
 *   heading (text), lead (textarea), background (select),
 *   stats (repeater: value number, decimals, prefix, suffix, label, icon)
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_stats   = (array) bgcf_field( 'stats', null, array() );

$bgcf_heading_id = 'bgcf-stats-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => (string) bgcf_field( 'background', null, 'sand' ),
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_stats ) ) {
	bgcf_empty_state( __( 'Statistics: add the impact figures you want to highlight in the block settings.', 'bgcf' ) );

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

foreach ( $bgcf_stats as $bgcf_stat ) {
	if ( ! is_array( $bgcf_stat ) ) {
		continue;
	}

	$bgcf_cards[] = array(
		'value'    => (string) ( $bgcf_stat['value'] ?? '' ),
		'decimals' => (int) ( $bgcf_stat['decimals'] ?? 0 ),
		'prefix'   => (string) ( $bgcf_stat['prefix'] ?? '' ),
		'suffix'   => (string) ( $bgcf_stat['suffix'] ?? '' ),
		'label'    => (string) ( $bgcf_stat['label'] ?? '' ),
		'icon'     => (string) ( $bgcf_stat['icon'] ?? '' ),
	);
}

/*
 * Four across is the design, and `bgcf_card_grid_open()` treats the count as an
 * upper bound — three figures render as three, not as three plus a gap.
 *
 * `tag => 'div'`, not the default list: these are four independent description
 * lists, not four items of one list, and wrapping them in a `ul` would announce
 * "list, 4 items" around content that is already four `dl`s.
 */
bgcf_card_grid(
	'stat',
	$bgcf_cards,
	array(
		'columns' => 4,
		'tag'     => 'div',
		'class'   => 'bgcf-stats-grid',
	)
);

bgcf_section_close( $bgcf_section );
