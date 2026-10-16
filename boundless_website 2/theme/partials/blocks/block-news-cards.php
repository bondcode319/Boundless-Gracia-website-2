<?php
/**
 * Block: News Cards — `bgcf/news-cards`.
 *
 * The latest posts, success stories or events as dated cards. Three selection
 * modes via `bgcf_block_query()`; three post types, because "news" on an NGO
 * site means a blog post on one page, a success story on another and an upcoming
 * event on a third, and they all want the same card.
 *
 * ACF fields (group: Block — News Cards):
 *   eyebrow (text), heading (text), lead (textarea),
 *   post_type (select post|bgcf_story|bgcf_event),
 *   source (select latest|manual|term), posts (relationship),
 *   term (taxonomy -> category), limit (number), columns (select 2|3|4),
 *   background (select), archive_cta (link)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading   = (string) bgcf_field( 'heading', null, '' );
$bgcf_post_type = (string) bgcf_field( 'post_type', null, 'post' );

// An editor can only pick from the select, but a filter or an imported block
// could carry anything, and querying an unregistered post type returns an empty
// grid with no explanation.
if ( ! post_type_exists( $bgcf_post_type ) ) {
	$bgcf_post_type = 'post';
}

$bgcf_heading_id = 'bgcf-news-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	// `bloom` is the news band in the Horizon rhythm — the warm tint that says
	// "recent" without using the CTA orange a Donate button needs.
	'background' => (string) bgcf_field( 'background', null, 'bloom' ),
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

$bgcf_query = bgcf_block_query(
	$bgcf_post_type,
	array(
		'source'   => (string) bgcf_field( 'source', null, 'latest' ),
		'manual'   => bgcf_field( 'posts', null, array() ),
		'term'     => bgcf_field( 'term', null, 0 ),
		'taxonomy' => 'post' === $bgcf_post_type ? 'category' : 'bgcf_program_category',
		'limit'    => (int) bgcf_field( 'limit', null, 3 ),
	)
);

if ( ! $bgcf_query->have_posts() ) {
	bgcf_empty_state( __( 'News cards: publish a post, or choose which items to show in the block settings.', 'bgcf' ) );

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

	$bgcf_cards[] = bgcf_card_args_from_post(
		get_post(),
		array(
			'meta' => array(
				// Both forms: the display string the site's date format
				// produces, and the machine-readable value for `<time>`.
				'date'     => (string) get_the_date(),
				'datetime' => (string) get_the_date( DATE_W3C ),
			),
		)
	);
}

wp_reset_postdata();

bgcf_card_grid(
	'news',
	$bgcf_cards,
	array(
		'columns' => (int) bgcf_field( 'columns', null, 3 ),
		'label'   => '' !== $bgcf_heading ? $bgcf_heading : bgcf_get_text( 'news_label', __( 'Latest news', 'bgcf' ) ),
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
