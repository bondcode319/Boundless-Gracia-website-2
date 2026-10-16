<?php
/**
 * Single entry content — the article body.
 *
 * The masthead (breadcrumb, `h1`, lead) is drawn by `bgcf_sub_banner()` in
 * single.php, so this partial is only the meta row, the lead image and the
 * prose. Splitting it that way is what lets the Horizon article open with a
 * full-bleed title band and still keep its body in a 720px reading column: the
 * two live in different sections with different container widths, and a single
 * partial could not be both.
 *
 * NO `h1` HERE. There is exactly one `h1` per document and the banner owns it.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Optional partial arguments.
 */

defined( 'ABSPATH' ) || exit;

$bgcf_post_type = (string) get_post_type();
?>
<article <?php post_class( 'bgcf-entry' ); ?>>

	<div class="bgcf-entry-meta bgcf-entry__meta">
		<?php
		bgcf_posted_on();

		if ( 'post' === $bgcf_post_type ) {
			bgcf_reading_time();
			bgcf_entry_terms( 'category' );
		}

		if ( 'bgcf_program' === $bgcf_post_type || 'bgcf_story' === $bgcf_post_type ) {
			bgcf_entry_terms( 'bgcf_program_category' );
		}

		if ( 'bgcf_event' === $bgcf_post_type ) {
			bgcf_entry_terms( 'bgcf_event_type' );
		}
		?>
	</div>

	<?php
	/*
	 * The lead image is drawn here ONLY when the banner did not use it as its
	 * backdrop. Showing the same photograph twice in the first screenful looks
	 * like a duplicate render rather than a design choice, and the banner always
	 * takes the featured image when there is one.
	 *
	 * `loading` stays lazy for the same reason: if this image renders at all, the
	 * banner above it was flat colour and this is below the fold.
	 */
	if ( ! has_post_thumbnail() ) {
		$bgcf_image = bgcf_get_post_thumbnail(
			array(
				'size'     => 'bgcf-story',
				'class'    => 'bgcf-entry__image',
				'fallback' => true,
			)
		);

		if ( '' !== $bgcf_image ) {
			printf( '<figure class="bgcf-entry__media">%s</figure>', $bgcf_image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		}
	}
	?>

	<div class="bgcf-entry__content bgcf-prose">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before'   => '<nav class="bgcf-page-links" aria-label="' . esc_attr( bgcf_get_text( 'page_links_label', __( 'Page sections', 'bgcf' ) ) ) . '">',
				'after'    => '</nav>',
				'pagelink' => '<span class="bgcf-page-links__item">%</span>',
			)
		);
		?>
	</div>

	<?php if ( 'post' === $bgcf_post_type && has_tag() ) : ?>
		<footer class="bgcf-entry__footer">
			<?php bgcf_entry_terms( 'post_tag' ); ?>
		</footer>
	<?php endif; ?>

</article>
