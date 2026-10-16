<?php
/**
 * Block: Action Grid — `bgcf/action-grid`.
 *
 * The body of the Take Action page: a grid of ways to help, each with its own
 * image, title, sentence and button.
 *
 * EVERY CARD HERE IS A PRIMARY ACTION, which breaks the usual rule of one
 * primary call to action per screen. That is deliberate and it is the page's
 * whole purpose — a visitor arriving at "take action" has already decided to
 * act and is choosing HOW, so ranking one option above the others would be
 * guessing on their behalf. The rule still holds everywhere else on the site,
 * and this is the only block that renders action cards.
 *
 * ACF fields (group: Block — Action Grid):
 *   eyebrow (text), heading (text), lead (textarea), background (select),
 *   columns (select 2|3|4), items (repeater: image, eyebrow, title, text, link)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_items   = bgcf_field( 'items', null, array() );

$bgcf_heading_id = 'bgcf-actions-' . (string) wp_unique_id();

$bgcf_cards = array();

if ( is_array( $bgcf_items ) ) {
	foreach ( $bgcf_items as $bgcf_index => $bgcf_item ) {
		if ( ! is_array( $bgcf_item ) ) {
			continue;
		}

		$bgcf_link = $bgcf_item['link'] ?? array();

		$bgcf_cards[] = array(
			'image'     => bgcf_image_id( $bgcf_item['image'] ?? 0 ),
			'eyebrow'   => (string) ( $bgcf_item['eyebrow'] ?? '' ),
			'title'     => (string) ( $bgcf_item['title'] ?? '' ),
			'text'      => (string) ( $bgcf_item['text'] ?? '' ),
			'url'       => is_array( $bgcf_link ) ? (string) ( $bgcf_link['url'] ?? '' ) : (string) $bgcf_link,
			'target'    => is_array( $bgcf_link ) ? (string) ( $bgcf_link['target'] ?? '' ) : '',
			'link_text' => is_array( $bgcf_link ) ? (string) ( $bgcf_link['title'] ?? '' ) : '',
			/*
			 * The first row of an action grid is above the fold on the Take
			 * Action page, where this block is the first band after the banner.
			 * Three is the common column count, so the first three images load
			 * eagerly and the rest stay lazy.
			 */
			'priority'  => $bgcf_index < 3,
		);
	}
}

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => (string) bgcf_field( 'background', null, 'soft' ),
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_cards ) ) {
	bgcf_empty_state( __( 'Action grid: add at least one way to get involved in the block settings.', 'bgcf' ) );

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

bgcf_card_grid(
	'action',
	$bgcf_cards,
	array(
		'columns' => (int) bgcf_field( 'columns', null, 3 ),
		'label'   => '' !== $bgcf_heading ? $bgcf_heading : bgcf_get_text( 'actions_label', __( 'Ways to get involved', 'bgcf' ) ),
	)
);

bgcf_section_close( $bgcf_section );
