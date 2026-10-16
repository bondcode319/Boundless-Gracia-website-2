<?php
/**
 * Page content.
 *
 * A Horizon page is a masthead followed by a run of full-bleed bands. This
 * partial renders the masthead (which owns the page's single `h1`) and then
 * hands everything below it to the editor.
 *
 * TWO CONTENT MODES, and choosing between them automatically is the whole point
 * of this file:
 *
 *   1. BLOCK-COMPOSED. Every `bgcf/*` block opens its own `<section>` with its
 *      own band colour and its own container. Wrapping that in a container here
 *      would cap each band at 1160px and leave a white gutter down both sides of
 *      what is meant to be a full-bleed deep-blue band. So block content is
 *      emitted bare.
 *
 *   2. PROSE. A page someone wrote in paragraphs — a policy, a job description,
 *      a thank-you page — has no sections of its own, and emitting it bare would
 *      run 140-character lines edge to edge. So it gets a narrow reading
 *      container and the prose styles.
 *
 * `has_blocks()` decides, and it is the right test rather than a guess: it
 * returns true exactly when the content contains block delimiters, which is
 * exactly when the content supplies its own sections. A page that mixes the two
 * (a hero block followed by typed paragraphs) takes mode 1, and the paragraphs
 * land in a `core/paragraph` block that the editor styles correctly anyway.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args {
 *     @type bool   $hide_banner Suppress the masthead entirely. For templates
 *                               whose first block IS the masthead (a hero), so
 *                               the page does not open with two of them.
 *     @type string $eyebrow     Masthead eyebrow.
 *     @type string $lead        Masthead supporting sentence.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args = isset( $args ) && is_array( $args ) ? $args : array();

/*
 * A page whose content opens with a hero block already has a full-bleed masthead
 * and an `h1` inside it. Rendering the banner as well would give the page two
 * mastheads and two `h1`s — so the same test that decides whether the header
 * overlays the page decides whether the banner is drawn.
 */
$bgcf_hide_banner = ! empty( $bgcf_args['hide_banner'] ) || bgcf_header_is_overlay();

if ( ! $bgcf_hide_banner ) {
	bgcf_sub_banner(
		array(
			'eyebrow' => (string) ( $bgcf_args['eyebrow'] ?? bgcf_field( 'page_eyebrow', null, '' ) ),
			'title'   => (string) get_the_title(),
			'text'    => (string) ( $bgcf_args['lead'] ?? bgcf_field( 'page_lead', null, '' ) ),
		)
	);
}

$bgcf_is_blocks = has_blocks( get_post() );
?>

<article <?php post_class( 'bgcf-page' ); ?>>
	<?php
	if ( $bgcf_is_blocks ) {
		// Mode 1: the blocks are the layout. See the file header.
		the_content();
	} else {
		// Mode 2: prose.
		$bgcf_section = array(
			'class'      => 'bgcf-page__prose',
			'background' => 'page',
			'width'      => 'narrow',
		);

		bgcf_section_open( $bgcf_section );

		$bgcf_image = bgcf_get_post_thumbnail(
			array(
				'size'     => 'bgcf-story',
				'class'    => 'bgcf-page__image',
				'fallback' => false,
			)
		);

		// Only when there is no banner above: the banner already used the
		// featured image as its backdrop, and showing it twice in the first
		// screenful looks like a mistake.
		if ( '' !== $bgcf_image && $bgcf_hide_banner ) {
			printf( '<figure class="bgcf-page__media">%s</figure>', $bgcf_image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		}

		echo '<div class="bgcf-page__content bgcf-prose">';

		the_content();

		wp_link_pages(
			array(
				'before'   => '<nav class="bgcf-page-links" aria-label="' . esc_attr( bgcf_get_text( 'page_links_label', __( 'Page sections', 'bgcf' ) ) ) . '">',
				'after'    => '</nav>',
				'pagelink' => '<span class="bgcf-page-links__item">%</span>',
			)
		);

		echo '</div>';

		bgcf_section_close( $bgcf_section );
	}
	?>
</article>
