<?php
/**
 * Block: Gallery — `bgcf/gallery`.
 *
 * Filterable photo and video grid with a lightbox and AJAX pagination
 * (`bgcf_gallery_load_more`, see inc/ajax.php).
 *
 * Items are attachments grouped by the `bgcf_album` taxonomy — see the header of
 * inc/gallery.php for why that beats an ACF repeater (pagination, filtering and
 * reuse all become possible, and editors write alt text where WordPress already
 * asks for it).
 *
 * ACF fields (group: Block — Gallery):
 *   heading (text), lead (textarea), album (taxonomy -> bgcf_album),
 *   columns (select 2|3|4), enable_filters (true/false),
 *   enable_lightbox (true/false), per_page (number)
 *
 * SAFEGUARDING (PLAN.md §7): images of identifiable children require documented
 * guardian consent. The consent flag is a media-library field and
 * `bgcf_gallery_query()` filters on it, so an uncleared photo cannot reach this
 * markup even if an editor adds it to an album.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading  = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead     = (string) bgcf_field( 'lead', null, '' );
$bgcf_columns  = (int) bgcf_field( 'columns', null, 3 );
$bgcf_per_page = max( 4, min( 48, (int) bgcf_field( 'per_page', null, 12 ) ) );
$bgcf_filters  = (bool) bgcf_field( 'enable_filters', null, true );
$bgcf_light    = (bool) bgcf_field( 'enable_lightbox', null, true );
$bgcf_album    = bgcf_field( 'album', null, 0 );

$bgcf_columns = in_array( $bgcf_columns, array( 2, 3, 4 ), true ) ? $bgcf_columns : 3;

$bgcf_album_slug = '';

if ( $bgcf_album instanceof WP_Term ) {
	$bgcf_album_slug = (string) $bgcf_album->slug;
} elseif ( is_array( $bgcf_album ) ) {
	$bgcf_album_slug = (string) ( $bgcf_album['slug'] ?? '' );
} elseif ( is_numeric( $bgcf_album ) && (int) $bgcf_album > 0 ) {
	$bgcf_term       = get_term( (int) $bgcf_album, BGCF_ALBUM_TAXONOMY );
	$bgcf_album_slug = $bgcf_term instanceof WP_Term ? (string) $bgcf_term->slug : '';
}

$bgcf_heading_id = 'bgcf-gallery-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => 'base',
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

$bgcf_results = bgcf_gallery_query( $bgcf_album_slug, $bgcf_per_page, 1 );

if ( empty( $bgcf_results['items'] ) ) {
	bgcf_empty_state(
		__( 'Gallery: upload photos in Media, tick an album on each one, and confirm publication consent. Then choose the album here.', 'bgcf' )
	);

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'title' => $bgcf_heading,
		'lead'  => $bgcf_lead,
		'id'    => $bgcf_heading_id,
		'align' => 'center',
	)
);

// Filters are only offered when the block is not already pinned to one album,
// and only when there is more than one album to choose between.
$bgcf_albums     = ( $bgcf_filters && '' === $bgcf_album_slug ) ? bgcf_gallery_albums() : array();
$bgcf_has_filter = count( $bgcf_albums ) > 1;
?>

<div
	class="bgcf-gallery"
	data-bgcf-gallery
	data-bgcf-album="<?php echo esc_attr( $bgcf_album_slug ); ?>"
	data-bgcf-paged="1"
	data-bgcf-per-page="<?php echo esc_attr( (string) $bgcf_per_page ); ?>"
	data-bgcf-post="<?php echo esc_attr( (string) absint( $args['post_id'] ?? 0 ) ); ?>"
	<?php echo $bgcf_light ? 'data-bgcf-lightbox' : ''; ?>
>
	<?php if ( $bgcf_has_filter ) : ?>
		<div class="bgcf-gallery__filters" role="group" aria-label="<?php esc_attr_e( 'Filter the gallery by album', 'bgcf' ); ?>">
			<button
				type="button"
				class="bgcf-chip"
				aria-pressed="true"
				data-bgcf-gallery-filter=""
			>
				<?php esc_html_e( 'All', 'bgcf' ); ?>
			</button>

			<?php foreach ( $bgcf_albums as $bgcf_term ) : ?>
				<button
					type="button"
					class="bgcf-chip"
					aria-pressed="false"
					data-bgcf-gallery-filter="<?php echo esc_attr( (string) $bgcf_term->slug ); ?>"
				>
					<?php echo esc_html( (string) $bgcf_term->name ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="bgcf-gallery__grid bgcf-grid--cols-<?php echo esc_attr( (string) $bgcf_columns ); ?>" data-bgcf-gallery-grid>
		<?php
		foreach ( $bgcf_results['items'] as $bgcf_item ) {
			bgcf_render_partial(
				'global/gallery-item',
				array(
					'item'     => $bgcf_item,
					'lightbox' => $bgcf_light,
				)
			);
		}
		?>
	</div>

	<?php if ( ! empty( $bgcf_results['has_more'] ) ) : ?>
		<div class="bgcf-gallery__more">
			<?php
			bgcf_button(
				array(
					'text'    => bgcf_get_text( 'gallery_load_more', __( 'Load more photos', 'bgcf' ) ),
					'variant' => 'secondary',
					'type'    => 'button',
					'attrs'   => array( 'data-bgcf-gallery-more' => '' ),
				)
			);
			?>
		</div>
	<?php endif; ?>
</div>

<?php
bgcf_section_close( $bgcf_section );
