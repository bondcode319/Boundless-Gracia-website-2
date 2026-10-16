<?php
/**
 * Single post and the default single view for every custom post type.
 *
 * Phase 4 adds `single-bgcf_program.php`, `single-bgcf_event.php` and
 * `single-bgcf_story.php` for the richer layouts. Until then every CPT falls
 * back here and renders correctly, which is the point of a fallback.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-single',
	'background' => 'base',
	'width'      => 'default',
);

bgcf_section_open( $bgcf_section );

while ( have_posts() ) :
	the_post();

	bgcf_render_partial( 'content/content-single' );

	the_post_navigation(
		array(
			'screen_reader_text' => bgcf_get_text( 'post_nav_label', __( 'Continue reading', 'bgcf' ) ),
			'aria_label'         => bgcf_get_text( 'post_nav_label', __( 'Continue reading', 'bgcf' ) ),
			'class'              => 'bgcf-post-nav',
			'prev_text'          => '<span class="bgcf-post-nav__direction">' . esc_html( bgcf_get_text( 'post_nav_prev', __( 'Previous', 'bgcf' ) ) ) . '</span> <span class="bgcf-post-nav__title">%title</span>',
			'next_text'          => '<span class="bgcf-post-nav__direction">' . esc_html( bgcf_get_text( 'post_nav_next', __( 'Next', 'bgcf' ) ) ) . '</span> <span class="bgcf-post-nav__title">%title</span>',
		)
	);

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

bgcf_section_close( $bgcf_section );

if ( is_singular( 'post' ) ) {
	get_sidebar();
}

get_footer();
