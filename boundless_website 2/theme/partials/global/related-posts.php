<?php
/**
 * Related reading — the band that closes an article.
 *
 * Three more items of the same post type, as news cards, on the warm band. An
 * article that ends at the footer is a dead end; this is the cheapest fix for
 * it, and because the cards come from the same post type the suggestion is
 * always coherent (a programme leads to programmes, a story to stories).
 *
 * SELECTION: the same taxonomy term where there is one, falling back to the most
 * recent. "Related" by shared category is a genuine relationship; "related" by
 * recency alone is just "more", and labelling it as related is a small lie — so
 * the heading changes with the strategy that was used.
 *
 * Renders nothing when there is nothing to suggest, rather than an empty band.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args {
 *     @type string $post_type Post type to pull from. Required.
 *     @type int    $exclude   Post ID to leave out — usually the one being read.
 *     @type int    $limit     How many. Default 3.
 *     @type string $card_type Card type. Defaults to the same mapping the
 *                             archive template uses, so a programme's related
 *                             band shows project cards and an article's shows
 *                             news cards.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args      = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_post_type = (string) ( $bgcf_args['post_type'] ?? 'post' );
$bgcf_exclude   = (int) ( $bgcf_args['exclude'] ?? 0 );
$bgcf_limit     = max( 1, min( 4, (int) ( $bgcf_args['limit'] ?? 3 ) ) );

if ( ! post_type_exists( $bgcf_post_type ) ) {
	return;
}

/*
 * The card type comes from the same shared map archive.php uses, so the two
 * places that render a grid of one post type agree about how it should look. A
 * dated news card for a programme would imply the programme started on that
 * date rather than that its post was published then.
 */
$bgcf_card_type = (string) ( $bgcf_args['card_type'] ?? '' );

if ( '' === $bgcf_card_type ) {
	$bgcf_card_type = bgcf_card_type_for_post_type( $bgcf_post_type );
}

/*
 * The taxonomy that makes two items of this type genuinely related. Not every
 * type has one, and `post` uses core's `category` rather than a theme taxonomy.
 */
$bgcf_taxonomy = 'post' === $bgcf_post_type ? 'category' : 'bgcf_program_category';

$bgcf_terms = $bgcf_exclude && taxonomy_exists( $bgcf_taxonomy )
	? wp_get_post_terms( $bgcf_exclude, $bgcf_taxonomy, array( 'fields' => 'ids' ) )
	: array();

$bgcf_terms = is_wp_error( $bgcf_terms ) ? array() : $bgcf_terms;

$bgcf_query_args = array(
	'post_type'              => $bgcf_post_type,
	'post_status'            => 'publish',
	'posts_per_page'         => $bgcf_limit,
	'post__not_in'           => $bgcf_exclude ? array( $bgcf_exclude ) : array(),
	'no_found_rows'          => true,
	'ignore_sticky_posts'    => true,
	'update_post_term_cache' => false,
);

$bgcf_is_related = ! empty( $bgcf_terms );

if ( $bgcf_is_related ) {
	$bgcf_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- This relationship is the feature.
		array(
			'taxonomy' => $bgcf_taxonomy,
			'field'    => 'term_id',
			'terms'    => $bgcf_terms,
		),
	);
}

$bgcf_query = new WP_Query( $bgcf_query_args );

/*
 * Nothing shares a term: fall back to the most recent of the same type, and
 * change the heading to match, because these are not related — they are simply
 * the next thing to read.
 */
if ( ! $bgcf_query->have_posts() && $bgcf_is_related ) {
	unset( $bgcf_query_args['tax_query'] );

	$bgcf_is_related = false;
	$bgcf_query      = new WP_Query( $bgcf_query_args );
}

if ( ! $bgcf_query->have_posts() ) {
	return;
}

$bgcf_heading = $bgcf_is_related
	? bgcf_get_text( 'related_title', __( 'Related reading', 'bgcf' ) )
	: bgcf_get_text( 'more_title', __( 'More from Boundless Gracia', 'bgcf' ) );

$bgcf_heading_id = 'bgcf-related-' . (string) wp_unique_id();

$bgcf_section = array(
	'class'      => 'bgcf-related',
	'background' => 'bloom',
	'width'      => 'wide',
	'labelledby' => $bgcf_heading_id,
);

bgcf_section_open( $bgcf_section );

bgcf_section_heading(
	array(
		'title' => $bgcf_heading,
		'id'    => $bgcf_heading_id,
		'align' => 'center',
	)
);

$bgcf_cards = array();

while ( $bgcf_query->have_posts() ) {
	$bgcf_query->the_post();

	$bgcf_overrides = array();

	// Only a news card shows a date, and only it should be given one.
	if ( 'news' === $bgcf_card_type ) {
		$bgcf_overrides['meta'] = array(
			'date'     => (string) get_the_date(),
			'datetime' => (string) get_the_date( DATE_W3C ),
		);
	}

	// A project card leads with the item's own summary where it has one.
	if ( 'project' === $bgcf_card_type ) {
		$bgcf_summary = (string) bgcf_field( 'summary', get_the_ID(), '' );

		if ( '' !== trim( $bgcf_summary ) ) {
			$bgcf_overrides['text'] = $bgcf_summary;
		}
	}

	$bgcf_cards[] = bgcf_card_args_from_post( get_post(), $bgcf_overrides );
}

wp_reset_postdata();

bgcf_card_grid(
	$bgcf_card_type,
	$bgcf_cards,
	array(
		'columns' => 3,
		'label'   => $bgcf_heading,
	)
);

bgcf_section_close( $bgcf_section );
