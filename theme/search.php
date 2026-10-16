<?php
/**
 * Search results.
 *
 * The result count is announced in the h1 so screen-reader users get the same
 * information sighted users do, without a live region.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-search',
	'background' => 'base',
	'width'      => 'default',
	'labelledby' => 'bgcf-search-title',
);

$bgcf_found = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );

bgcf_section_open( $bgcf_section );
?>

	<?php bgcf_breadcrumbs(); ?>

	<header class="bgcf-search__header">
		<h1 class="bgcf-search__title" id="bgcf-search-title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: number of results, 2: search term. */
					_n( '%1$s result for “%2$s”', '%1$s results for “%2$s”', $bgcf_found, 'bgcf' ),
					number_format_i18n( $bgcf_found ),
					get_search_query()
				)
			);
			?>
		</h1>

		<div class="bgcf-search__form">
			<?php get_search_form(); ?>
		</div>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="bgcf-search__list">
			<?php
			while ( have_posts() ) :
				the_post();

				bgcf_render_partial( 'content/content-search', array( 'context' => 'search' ) );
			endwhile;
			?>
		</div>

		<?php bgcf_pagination(); ?>

	<?php else : ?>

		<?php bgcf_render_partial( 'content/content-none', array( 'context' => 'search' ) ); ?>

	<?php endif; ?>

<?php
bgcf_section_close( $bgcf_section );

get_footer();
