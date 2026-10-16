<?php
/**
 * Page content.
 *
 * The h1 lives here so every page has exactly one, and block content renders
 * beneath it. Templates that need a custom hero (which supplies its own h1)
 * should not include this partial.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Optional. `hide_title` (bool) suppresses the h1.
 */

defined( 'ABSPATH' ) || exit;

$bgcf_hide_title = ! empty( $args['hide_title'] );

$bgcf_section = array(
	'class'      => 'bgcf-page',
	'background' => 'base',
	'width'      => 'default',
);

bgcf_section_open( $bgcf_section );
?>

	<?php bgcf_breadcrumbs(); ?>

	<article <?php post_class( 'bgcf-page__article' ); ?>>

		<?php if ( ! $bgcf_hide_title ) : ?>
			<header class="bgcf-page__header">
				<h1 class="bgcf-page__title"><?php the_title(); ?></h1>
			</header>
		<?php endif; ?>

		<?php
		$bgcf_image = bgcf_get_post_thumbnail(
			array(
				'size'          => 'bgcf-hero',
				'class'         => 'bgcf-page__image',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'fallback'      => false,
			)
		);

		if ( '' !== $bgcf_image ) {
			printf( '<figure class="bgcf-page__media">%s</figure>', $bgcf_image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		}
		?>

		<div class="bgcf-page__content bgcf-prose">
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

	</article>

<?php
bgcf_section_close( $bgcf_section );
