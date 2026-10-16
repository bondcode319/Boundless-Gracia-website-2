<?php
/**
 * 404 — page not found.
 *
 * A 404 on a donation site is a lost supporter, so this page offers a route
 * back: search, and an editable set of links from the Global CTAs options page.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-error-404',
	'background' => 'surface',
	'width'      => 'narrow',
	'labelledby' => 'bgcf-404-title',
);

bgcf_section_open( $bgcf_section );
?>

	<h1 class="bgcf-error-404__title" id="bgcf-404-title">
		<?php bgcf_the_text( 'error_404_title', __( 'We could not find that page', 'bgcf' ) ); ?>
	</h1>

	<p class="bgcf-error-404__text">
		<?php bgcf_the_text( 'error_404_text', __( 'The page may have moved or the link may be out of date. Try a search, or head back to the homepage.', 'bgcf' ) ); ?>
	</p>

	<div class="bgcf-error-404__search">
		<?php get_search_form(); ?>
	</div>

	<div class="bgcf-error-404__actions">
		<?php
		bgcf_button(
			array(
				'text'    => bgcf_get_text( 'error_404_home_label', __( 'Back to homepage', 'bgcf' ) ),
				'url'     => home_url( '/' ),
				'variant' => 'primary',
			)
		);

		echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
			bgcf_option( 'error_404_cta', '' ),
			array( 'variant' => 'ghost' )
		);
		?>
	</div>

<?php
bgcf_section_close( $bgcf_section );

get_footer();
