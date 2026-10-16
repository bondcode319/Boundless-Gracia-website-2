<?php
/**
 * One gallery item.
 *
 * Shared by the server-side render of `bgcf/gallery` and by the AJAX append in
 * `bgcf_gallery_load_more` — the same file produces both, so an appended page
 * can never look or behave differently from the first.
 *
 * The item is an `<a>` to the full-size file. The lightbox intercepts the click;
 * with JavaScript off, the link opens the image. That is the whole
 * progressive-enhancement story and it is why there is no `<div onclick>` here.
 *
 * @package Bgcf
 * @since   1.2.0
 *
 * @var array<string, mixed> $args {
 *     @type array<string, mixed> $item     Row from bgcf_gallery_item().
 *     @type bool                 $lightbox Whether the lightbox is enabled.
 *     @type bool                 $is_new   Mark as freshly appended, for focus handling.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_item = (array) ( $args['item'] ?? array() );
$bgcf_id   = absint( $bgcf_item['id'] ?? 0 );

if ( 0 === $bgcf_id ) {
	return;
}

$bgcf_caption  = (string) ( $bgcf_item['caption'] ?? '' );
$bgcf_full     = (string) ( $bgcf_item['full'] ?? '' );
$bgcf_video    = (string) ( $bgcf_item['video_url'] ?? '' );
$bgcf_lightbox = ! empty( $args['lightbox'] ) && '' === $bgcf_video;
$bgcf_href     = '' !== $bgcf_video ? $bgcf_video : $bgcf_full;

$bgcf_thumb = bgcf_get_post_thumbnail(
	array(
		'attachment_id' => $bgcf_id,
		'size'          => 'bgcf-card',
		'class'         => 'bgcf-gallery__image',
		'sizes'         => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
		'fallback'      => false,
	)
);

if ( '' === $bgcf_thumb ) {
	return;
}
?>
<figure
	class="bgcf-gallery__item"
	data-bgcf-album="<?php echo esc_attr( (string) ( $bgcf_item['album'] ?? '' ) ); ?>"
	<?php echo ! empty( $args['is_new'] ) ? 'data-bgcf-new' : ''; ?>
>
	<a
		class="bgcf-gallery__link"
		href="<?php echo esc_url( $bgcf_href ); ?>"
		<?php if ( $bgcf_lightbox ) : ?>
			data-bgcf-lightbox-item
			data-bgcf-caption="<?php echo esc_attr( $bgcf_caption ); ?>"
		<?php else : ?>
			target="_blank" rel="noopener noreferrer"
		<?php endif; ?>
	>
		<?php
		echo $bgcf_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.

		if ( '' !== $bgcf_video ) {
			bgcf_svg_icon( 'play', array( 'class' => 'bgcf-gallery__play' ) );
		}
		?>

		<?php
		/*
		 * Every link needs an accessible name, and the thumbnail's alt text
		 * describes the photo, not the action. Without this the screen-reader
		 * user hears the description and has no idea it opens anything.
		 */
		?>
		<span class="bgcf-u-visually-hidden">
			<?php
			echo esc_html(
				'' !== $bgcf_video
					? __( 'Watch the video', 'bgcf' )
					: __( 'View the larger image', 'bgcf' )
			);
			?>
		</span>
	</a>

	<?php if ( '' !== $bgcf_caption ) : ?>
		<figcaption class="bgcf-gallery__caption"><?php echo esc_html( $bgcf_caption ); ?></figcaption>
	<?php endif; ?>
</figure>
