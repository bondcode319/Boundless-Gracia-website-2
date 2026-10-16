<?php
/**
 * Archive template — post type archives, taxonomy terms, dates and authors.
 *
 * Phase 4 adds `archive-bgcf_program.php`, `archive-bgcf_event.php` and
 * `archive-bgcf_story.php` where the listing needs filters or an
 * upcoming/past split. This file stays the correct, accessible default.
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
			<?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?>
		</h1>

		<?php
		$bgcf_archive_description = get_the_archive_description();

		if ( '' !== trim( (string) $bgcf_archive_description ) ) {
			printf(
				'<div class="bgcf-archive__description">%s</div>',
				wp_kses_post( $bgcf_archive_description )
			);
		}
		?>
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

if ( is_post_type_archive( 'post' ) || is_category() || is_tag() ) {
	get_sidebar();
}

get_footer();
