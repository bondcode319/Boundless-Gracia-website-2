<?php
/**
 * Sub-banner — the masthead that opens every inner page.
 *
 * One `h1` over a photograph, with the breadcrumb trail beneath it. Rendered
 * through `bgcf_sub_banner()`.
 *
 * THE H1 LIVES HERE, NOT IN THE TEMPLATE. Every inner template calls this
 * partial as its first output and none of them emits its own `h1`. That is what
 * guarantees exactly one `h1` per document (WCAG 1.3.1, and the single most
 * common heading failure in a theme with many templates): there is one place
 * that can produce one, and it is this file.
 *
 * NO BANNER IMAGE IS A SUPPORTED STATE, not a broken one. Without a photograph
 * the band falls back to the deep-blue surface, so the page still opens with a
 * defined masthead and the heading never lands on the page background with no
 * contrast guarantee.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Banner arguments. See bgcf_sub_banner().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args  = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_image = (int) ( $bgcf_args['image'] ?? 0 );
$bgcf_align = 'center' === (string) ( $bgcf_args['align'] ?? 'start' ) ? 'center' : 'start';

$bgcf_section = array(
	'tag'        => 'header',
	'class'      => bgcf_classnames(
		array(
			'bgcf-sub-banner',
			'bgcf-sub-banner--' . $bgcf_align,
			'bgcf-sub-banner--flat' => 0 === $bgcf_image,
			(string) ( $bgcf_args['class'] ?? '' ),
		)
	),
	// The photographic band needs an image to be a photographic band; without
	// one, `photo` would render the scrim over nothing, which is a flat deep
	// blue anyway — but `ink` says so explicitly and skips the pseudo-element.
	'background' => $bgcf_image ? 'photo' : 'ink',
	'spacing'    => 'md',
	'width'      => 'default',
	'image'      => $bgcf_image,
	'image_size' => 'bgcf-hero',
	'scrim'      => (int) ( $bgcf_args['scrim'] ?? 66 ),
);

bgcf_section_open( $bgcf_section );
?>

	<div class="bgcf-sub-banner__inner">
		<?php
		if ( ! empty( $bgcf_args['breadcrumb'] ) ) {
			bgcf_breadcrumbs();
		}

		if ( '' !== (string) ( $bgcf_args['eyebrow'] ?? '' ) ) {
			printf(
				'<p class="bgcf-sub-banner__eyebrow">%s</p>',
				esc_html( (string) $bgcf_args['eyebrow'] )
			);
		}

		printf(
			'<h1 class="bgcf-sub-banner__title">%s</h1>',
			esc_html( (string) ( $bgcf_args['title'] ?? '' ) )
		);

		if ( '' !== trim( (string) ( $bgcf_args['text'] ?? '' ) ) ) {
			printf(
				'<div class="bgcf-sub-banner__lead">%s</div>',
				wp_kses_post( (string) $bgcf_args['text'] )
			);
		}

		$bgcf_cta = bgcf_get_link_button(
			$bgcf_args['link'] ?? '',
			array(
				'variant' => 'primary',
				'size'    => 'lg',
				'class'   => 'bgcf-sub-banner__cta',
			)
		);

		if ( '' !== $bgcf_cta ) {
			echo '<div class="bgcf-sub-banner__actions">';
			echo $bgcf_cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
			echo '</div>';
		}
		?>
	</div>

<?php
bgcf_section_close( $bgcf_section );
