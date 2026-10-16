<?php
/**
 * Homepage.
 *
 * The homepage is composed entirely of `bgcf/*` blocks placed on the static
 * front page in the editor — there is no hardcoded section order here, by
 * design. If an editor rearranges the hero, statistics and campaign sections,
 * the homepage rearranges.
 *
 * Two states are handled:
 *   1. A static front page is assigned  -> render its blocks.
 *   2. The front page shows latest posts -> fall through to index.php so the
 *      site still works before Settings > Reading has been configured.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_page() ) {
	// Latest-posts homepage: index.php already does this correctly.
	require BGCF_DIR . '/index.php';
	return;
}

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'bgcf-home' ); ?>>
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
		</article>
		<?php
	endwhile;
else :
	bgcf_render_partial( 'content/content-none' );
endif;

get_footer();
