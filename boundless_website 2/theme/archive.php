<?php
/**
 * Archive — the Horizon CMS listing template.
 *
 * Covers post type archives, taxonomy terms, dates and authors.
 * `archive-bgcf_event.php` overrides it for events, which need an
 * upcoming/past split.
 *
 * STRUCTURE: masthead, card grid, pagination, closing call to action.
 *
 * THE CARD TYPE FOLLOWS THE CONTENT. A programme archive wants project cards (a
 * photograph and a summary); a blog archive wants news cards (a photograph and a
 * date). Choosing per post type here, rather than using one card everywhere,
 * costs four lines and is the difference between a listing that reads as a
 * catalogue and one that reads as a feed.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

// The card type follows the content; see bgcf_card_type_for_post_type() for why
// the map is shared with the related-reading band rather than declared here.
$bgcf_card_type = bgcf_card_type_for_post_type( (string) get_post_type() );

bgcf_sub_banner(
	array(
		'title' => wp_strip_all_tags( get_the_archive_title() ),
		'text'  => (string) get_the_archive_description(),
		// An archive has no featured image of its own, so the banner falls back
		// to the flat deep-blue band unless the options page supplies one.
		'image' => bgcf_image_id( bgcf_option( 'archive_banner_image', 0 ) ),
	)
);

$bgcf_section = array(
	'class'      => 'bgcf-archive',
	'background' => 'page',
	'width'      => 'wide',
);

bgcf_section_open( $bgcf_section );

if ( have_posts() ) {
	$bgcf_cards = array();
	$bgcf_index = 0;

	while ( have_posts() ) {
		the_post();

		$bgcf_overrides = array(
			// The first row is above the fold on an archive — the banner above it
			// is short and the grid starts immediately.
			'priority' => $bgcf_index < 3,
		);

		if ( 'news' === $bgcf_card_type ) {
			$bgcf_overrides['meta'] = array(
				'date'     => (string) get_the_date(),
				'datetime' => (string) get_the_date( DATE_W3C ),
			);
		}

		if ( 'person' === $bgcf_card_type ) {
			$bgcf_overrides['label'] = (string) bgcf_field( 'role', get_the_ID(), '' );
			$bgcf_overrides['text']  = '';
		}

		$bgcf_cards[] = bgcf_card_args_from_post( get_post(), $bgcf_overrides );

		++$bgcf_index;
	}

	bgcf_card_grid(
		$bgcf_card_type,
		$bgcf_cards,
		array(
			'columns' => 'person' === $bgcf_card_type ? 4 : 3,
			'label'   => wp_strip_all_tags( get_the_archive_title() ),
		)
	);

	bgcf_pagination();
} else {
	bgcf_render_partial( 'content/content-none' );
}

bgcf_section_close( $bgcf_section );

/*
 * The closing band. Every Horizon listing ends with one, and it is the same
 * editable band the homepage uses, so the whole site's listings share one place
 * to change the ask.
 */
bgcf_photo_cta(
	array(
		'title'    => bgcf_get_text( 'listing_cta_title', __( 'Your support turns these pages into outcomes', 'bgcf' ) ),
		'text'     => bgcf_get_text( 'listing_cta_text', __( 'Every programme on this site is funded by people who chose to act. Join them.', 'bgcf' ) ),
		'image'    => bgcf_image_id( bgcf_option( 'listing_cta_image', 0 ) ),
		'link'     => bgcf_option( 'header_primary_cta', '' ),
		'link_alt' => bgcf_option( 'header_secondary_cta', '' ),
	)
);

get_footer();
