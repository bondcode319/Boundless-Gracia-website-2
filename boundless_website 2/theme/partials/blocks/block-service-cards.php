<?php
/**
 * Block: Service Cards — `bgcf/service-cards`.
 *
 * A grid of text-only cards from an ACF repeater. Unlike the listing blocks,
 * this one holds its own content rather than querying a post type: the items are
 * usually short statements of what a programme includes, and making each of them
 * a post would mean eight posts nobody ever links to.
 *
 * Defaults to the DARK band, because that is what the service card is designed
 * for (see `_card.scss`) — on a light band its near-white fill is nearly
 * invisible, so the editor has to choose that deliberately.
 *
 * ACF fields (group: Block — Service Cards):
 *   eyebrow (text), heading (text), lead (textarea), background (select),
 *   columns (select 2|3|4), align (select start|center),
 *   items (repeater: icon, title, text, link)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_items   = bgcf_field( 'items', null, array() );
$bgcf_align   = 'center' === (string) bgcf_field( 'align', null, 'center' ) ? 'center' : 'start';

$bgcf_heading_id = 'bgcf-services-' . (string) wp_unique_id();

$bgcf_cards = array();

if ( is_array( $bgcf_items ) ) {
	foreach ( $bgcf_items as $bgcf_item ) {
		if ( ! is_array( $bgcf_item ) ) {
			continue;
		}

		$bgcf_link = $bgcf_item['link'] ?? array();

		$bgcf_cards[] = array(
			'icon'      => (string) ( $bgcf_item['icon'] ?? '' ),
			'title'     => (string) ( $bgcf_item['title'] ?? '' ),
			'text'      => (string) ( $bgcf_item['text'] ?? '' ),
			'url'       => is_array( $bgcf_link ) ? (string) ( $bgcf_link['url'] ?? '' ) : (string) $bgcf_link,
			'target'    => is_array( $bgcf_link ) ? (string) ( $bgcf_link['target'] ?? '' ) : '',
			// The link's own title is the editor's label for the affordance; it
			// is more specific than the generic "Learn more" default.
			'link_text' => is_array( $bgcf_link ) ? (string) ( $bgcf_link['title'] ?? '' ) : '',
		);
	}
}

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => (string) bgcf_field( 'background', null, 'ink' ),
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_cards ) ) {
	bgcf_empty_state( __( 'Service cards: add at least one item in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'eyebrow' => (string) bgcf_field( 'eyebrow', null, '' ),
		'title'   => $bgcf_heading,
		'lead'    => (string) bgcf_field( 'lead', null, '' ),
		'id'      => $bgcf_heading_id,
		'align'   => $bgcf_align,
	)
);

bgcf_card_grid(
	'service',
	$bgcf_cards,
	array(
		'columns' => (int) bgcf_field( 'columns', null, 3 ),
		'label'   => '' !== $bgcf_heading ? $bgcf_heading : bgcf_get_text( 'services_label', __( 'What this includes', 'bgcf' ) ),
	)
);

bgcf_section_close( $bgcf_section );
