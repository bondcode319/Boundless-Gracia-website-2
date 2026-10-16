<?php
/**
 * Single page.
 *
 * Pages are block-composed. The template supplies the landmark, the breadcrumb
 * and the h1, then hands the rest to the editor. Page-specific layouts (About,
 * Contact, Projects, FAQ) get their own file in `templates/` rather than
 * conditionals here.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	bgcf_render_partial( 'content/content-page' );

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
