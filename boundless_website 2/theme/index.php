<?php
/**
 * Fallback template.
 *
 * WordPress uses this when no more specific template matches, so it must render
 * correctly for any query. Everything more specific (archive.php, single.php,
 * search.php) exists to give a better answer than this one.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-archive',
	'background' => 'base',
	'width'      => 'default',
	'labelledby' => 'bgcf-archive-title',
);

bgcf_section_open( $bgcf_section );
?>

	<?php bgcf_breadcrumbs(); ?>

	<header class="bgcf-archive__header">
		<h1 class="bgcf-archive__title" id="bgcf-archive-title">
			<?php
			if ( is_home() && ! is_front_page() ) {
				echo esc_html( (string) get_the_title( (int) get_option( 'page_for_posts' ) ) );
			} else {
				bgcf_the_text( 'archive_title', __( 'Latest updates', 'bgcf' ) );
			}
			?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="bgcf-archive__list">
			<?php
			while ( have_posts() ) :
				the_post();

				bgcf_render_partial( 'content/content', array( 'context' => 'archive' ) );
			endwhile;
			?>
		</div>

		<?php bgcf_pagination(); ?>

	<?php else : ?>

		<?php bgcf_render_partial( 'content/content-none' ); ?>

	<?php endif; ?>

<?php
bgcf_section_close( $bgcf_section );

get_footer();
