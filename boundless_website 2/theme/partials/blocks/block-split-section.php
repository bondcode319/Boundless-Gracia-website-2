<?php
/**
 * Block: Split Section — `bgcf/split-section`.
 *
 * A thin adapter: it reads the ACF fields and hands them to
 * `bgcf_split_section()`, which owns the markup. Nothing about the layout lives
 * here, which is what lets a page template render the identical band without
 * going through the editor.
 *
 * ACF fields (group: Block — Split Section):
 *   eyebrow (text), heading (text), text (wysiwyg), image (image),
 *   media_side (select start|end), ratio (select even|text|media),
 *   background (select), link (link), link_style (select primary|secondary),
 *   cards (repeater: icon, title, text), card_cols (select 1|2)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_cards = array();
$bgcf_rows  = bgcf_field( 'cards', null, array() );

if ( is_array( $bgcf_rows ) ) {
	foreach ( $bgcf_rows as $bgcf_row ) {
		if ( ! is_array( $bgcf_row ) ) {
			continue;
		}

		$bgcf_cards[] = array(
			'icon'  => (string) ( $bgcf_row['icon'] ?? '' ),
			'title' => (string) ( $bgcf_row['title'] ?? '' ),
			'text'  => (string) ( $bgcf_row['text'] ?? '' ),
		);
	}
}

bgcf_split_section(
	array(
		'eyebrow'    => (string) bgcf_field( 'eyebrow', null, '' ),
		'title'      => (string) bgcf_field( 'heading', null, '' ),
		'text'       => (string) bgcf_field( 'text', null, '' ),
		'image'      => bgcf_image_id( bgcf_field( 'image', null, 0 ) ),
		'media_side' => (string) bgcf_field( 'media_side', null, 'start' ),
		'ratio'      => (string) bgcf_field( 'ratio', null, 'even' ),
		'background' => (string) bgcf_field( 'background', null, 'soft' ),
		'link'       => bgcf_field( 'link', null, '' ),
		'link_style' => (string) bgcf_field( 'link_style', null, 'primary' ),
		'cards'      => $bgcf_cards,
		'card_type'  => 'service',
		'card_cols'  => max( 1, min( 2, (int) bgcf_field( 'card_cols', null, 2 ) ) ),
		'id'         => (string) ( $args['anchor'] ?? '' ),
		'class'      => (string) ( $args['class'] ?? '' ),
	)
);
