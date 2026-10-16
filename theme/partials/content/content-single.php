<?php
/**
 * Single entry content.
 *
 * Used by single.php for posts and, until Phase 4 adds per-type templates, for
 * every custom post type too.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Optional partial arguments.
 */

defined( 'ABSPATH' ) || exit;

$bgcf_post_type = (string) get_post_type();
?>
<article <?php post_class( 'bgcf-entry' ); ?>>

	<?php bgcf_breadcrumbs(); ?>

	<header class="bgcf-entry__header">
		<h1 class="bgcf-entry__title"><?php the_title(); ?></h1>

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
	</header>

	<?php
	$bgcf_image = bgcf_get_post_thumbnail(
		array(
			'size'          => 'bgcf-story',
			'class'         => 'bgcf-entry__image',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'fallback'      => false,
		)
	);

	if ( '' !== $bgcf_image ) {
		printf( '<figure class="bgcf-entry__media">%s</figure>', $bgcf_image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
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
