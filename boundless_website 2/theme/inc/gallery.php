<?php
/**
 * The gallery data layer.
 *
 * DESIGN DECISION, worth reading before changing it. Gallery items are
 * **attachments in the media library**, grouped by the `bgcf_album` taxonomy —
 * not rows in an ACF repeater on the block.
 *
 * A repeater cannot be paginated, filtered or counted without loading the whole
 * field, which makes "load more" and album filters impossible at any real size,
 * and it buries the photos inside one page's post meta where nothing else can
 * reuse them. Attachments give us a queryable, pageable, taxonomy-filterable
 * source that editors already know how to fill — they upload a photo, tick an
 * album, and write the alt text in the field WordPress already provides.
 *
 * SAFEGUARDING (PLAN.md §7). Photographs of identifiable children may only be
 * published with documented guardian consent. The consent flag lives on the
 * attachment (`_bgcf_consent`), and `bgcf_gallery_query()` excludes anything not
 * cleared when the strict mode is on. The policy is enforced in the query, not
 * in a checklist someone has to remember.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy grouping gallery media into albums.
 *
 * @since 1.2.0
 */
define( 'BGCF_ALBUM_TAXONOMY', 'bgcf_album' );

/**
 * Register the album taxonomy on attachments.
 *
 * Public and archived so an album has its own crawlable URL — a gallery that
 * only exists inside one page's JavaScript is invisible to search.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_register_album_taxonomy(): void {
	register_taxonomy(
		BGCF_ALBUM_TAXONOMY,
		array( 'attachment' ),
		array(
			'labels'            => array(
				'name'          => __( 'Albums', 'bgcf' ),
				'singular_name' => __( 'Album', 'bgcf' ),
				'menu_name'     => __( 'Albums', 'bgcf' ),
				'all_items'     => __( 'All albums', 'bgcf' ),
				'add_new_item'  => __( 'Add album', 'bgcf' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'album',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'bgcf_register_album_taxonomy', 5 );

/**
 * Whether a gallery item needs an explicit consent flag to be shown.
 *
 * Defaults to on. Turning it off is a deliberate, documented decision for a
 * library that has been cleared wholesale — not a convenience during a rush.
 *
 * @since 1.2.0
 * @return bool True when unflagged media is hidden.
 */
function bgcf_gallery_requires_consent(): bool {
	/**
	 * Filters the safeguarding consent requirement for gallery media.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $required Whether `_bgcf_consent` must be set.
	 */
	return (bool) apply_filters( 'bgcf_gallery_requires_consent', (bool) bgcf_option( 'gallery_require_consent', true ) );
}

/**
 * The albums that actually contain media, for the filter bar.
 *
 * @since 1.2.0
 * @return WP_Term[] Terms, newest-populated first.
 */
function bgcf_gallery_albums(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => BGCF_ALBUM_TAXONOMY,
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);

	return is_wp_error( $terms ) ? array() : (array) $terms;
}

/**
 * Query a page of gallery items.
 *
 * Returns normalised rows rather than posts so the item partial never has to
 * know it is rendering an attachment — the same partial serves the server-side
 * render and the AJAX append.
 *
 * @since 1.2.0
 *
 * @param string $album    Album term slug or ID. Empty for all albums.
 * @param int    $per_page Items per page. Clamped by the caller.
 * @param int    $paged    Page number, 1-based.
 * @return array{items: array<int, array<string, mixed>>, has_more: bool, total: int}
 */
function bgcf_gallery_query( string $album = '', int $per_page = 12, int $paged = 1 ): array {
	$args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif' ),
		'posts_per_page' => max( 1, $per_page ),
		'paged'          => max( 1, $paged ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( '' !== $album ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Term filter is the point of the endpoint.
			array(
				'taxonomy' => BGCF_ALBUM_TAXONOMY,
				'field'    => is_numeric( $album ) ? 'term_id' : 'slug',
				'terms'    => is_numeric( $album ) ? absint( $album ) : $album,
			),
		);
	} else {
		// With no album chosen, show only media that was put in an album. The
		// media library also holds logos, report covers and page images, and
		// none of those belong in a public photo gallery.
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- See above.
			array(
				'taxonomy' => BGCF_ALBUM_TAXONOMY,
				'operator' => 'EXISTS',
			),
		);
	}

	if ( bgcf_gallery_requires_consent() ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Safeguarding gate; correctness beats speed here.
			array(
				'key'     => '_bgcf_consent',
				'value'   => '1',
				'compare' => '=',
			),
		);
	}

	/**
	 * Filters the gallery query arguments.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $args  WP_Query arguments.
	 * @param string               $album Requested album.
	 */
	$args = (array) apply_filters( 'bgcf_gallery_query_args', $args, $album );

	$query = new WP_Query( $args );
	$items = array();

	foreach ( $query->posts as $attachment ) {
		$items[] = bgcf_gallery_item( (int) $attachment->ID );
	}

	return array(
		'items'    => $items,
		'total'    => (int) $query->found_posts,
		'has_more' => (int) $query->max_num_pages > max( 1, $paged ),
	);
}

/**
 * Normalise one attachment into a gallery row.
 *
 * @since 1.2.0
 *
 * @param int $attachment_id Attachment ID.
 * @return array<string, mixed> Gallery item.
 */
function bgcf_gallery_item( int $attachment_id ): array {
	$terms = wp_get_object_terms( $attachment_id, BGCF_ALBUM_TAXONOMY, array( 'fields' => 'slugs' ) );
	$video = (string) get_post_meta( $attachment_id, '_bgcf_video_url', true );

	return array(
		'id'        => $attachment_id,
		'album'     => is_wp_error( $terms ) ? '' : (string) ( $terms[0] ?? '' ),
		'caption'   => (string) wp_get_attachment_caption( $attachment_id ),
		'alt'       => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'full'      => (string) wp_get_attachment_image_url( $attachment_id, 'large' ),
		'video_url' => $video,
	);
}

/**
 * Mark an attachment as consent-cleared for publication.
 *
 * Exposed as a media-library field rather than hidden meta, because the person
 * who knows whether consent exists is the person uploading the photo.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $fields Attachment form fields.
 * @param WP_Post              $post   Attachment.
 * @return array<string, mixed> Filtered fields.
 */
function bgcf_gallery_consent_field( array $fields, $post ): array {
	$checked = (string) get_post_meta( (int) $post->ID, '_bgcf_consent', true );

	$fields['bgcf_consent'] = array(
		'label' => __( 'Publication consent', 'bgcf' ),
		'input' => 'html',
		'html'  => sprintf(
			'<label><input type="checkbox" name="attachments[%1$d][bgcf_consent]" value="1" %2$s /> %3$s</label>',
			(int) $post->ID,
			checked( $checked, '1', false ),
			esc_html__( 'Documented consent is on file for everyone identifiable in this image.', 'bgcf' )
		),
		'helps' => __( 'Required before the image can appear in a public gallery. See the safeguarding policy.', 'bgcf' ),
	);

	return $fields;
}
add_filter( 'attachment_fields_to_edit', 'bgcf_gallery_consent_field', 10, 2 );

/**
 * Save the consent flag.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $post       Attachment post data.
 * @param array<string, mixed> $attachment Submitted fields.
 * @return array<string, mixed> Unmodified post data.
 */
function bgcf_gallery_save_consent( array $post, array $attachment ): array {
	if ( ! current_user_can( 'edit_post', (int) ( $post['ID'] ?? 0 ) ) ) {
		return $post;
	}

	if ( empty( $attachment['bgcf_consent'] ) ) {
		delete_post_meta( (int) $post['ID'], '_bgcf_consent' );
	} else {
		update_post_meta( (int) $post['ID'], '_bgcf_consent', '1' );
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'bgcf_gallery_save_consent', 10, 2 );
