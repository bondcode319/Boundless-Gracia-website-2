<?php
/**
 * Single post — the Horizon article template.
 *
 * Also the default single view for any custom post type without its own
 * template. `single-bgcf_program.php` and `single-bgcf_story.php` handle the
 * two types whose layouts are genuinely different; everything else renders
 * correctly here, which is the point of a fallback.
 *
 * STRUCTURE: a full-bleed title band, then one reading column, then related
 * reading. The column is `narrow` (760px, so roughly 70 characters per line at
 * the body size) because an article is the one page type where line length
 * matters more than filling the viewport.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	bgcf_sub_banner(
		array(
			'eyebrow' => get_post_type_object( (string) get_post_type() ) instanceof WP_Post_Type
				? get_post_type_object( (string) get_post_type() )->labels->singular_name
				: '',
			'title'   => (string) get_the_title(),
			'text'    => has_excerpt() ? (string) get_the_excerpt() : '',
		)
	);

	$bgcf_section = array(
		'class'      => 'bgcf-single',
		'background' => 'page',
		'width'      => 'narrow',
	);

	bgcf_section_open( $bgcf_section );

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

	bgcf_section_close( $bgcf_section );

	/*
	 * Related reading, as news cards on the warm band. Three from the same post
	 * type, excluding this one — so the article ends with somewhere to go rather
	 * than with the footer.
	 */
	bgcf_render_partial(
		'global/related-posts',
		array(
			'post_type' => (string) get_post_type(),
			'exclude'   => (int) get_the_ID(),
		)
	);
endwhile;

get_footer();
