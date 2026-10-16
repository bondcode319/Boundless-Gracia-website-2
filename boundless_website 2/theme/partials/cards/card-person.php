<?php
/**
 * Person card — portrait, name, role, optional one-line bio.
 *
 * Used by the team directory and by the testimonial attribution block.
 *
 * ALT TEXT. The portrait is DECORATIVE here and gets `alt=""` on purpose: the
 * person's name is rendered as text immediately beside it, so alt text would
 * make a screen reader announce the same name twice in a row. This is the one
 * card where an empty alt is correct rather than a mistake, which is why
 * `fallback` is off too — a generic stand-in portrait under someone's name is
 * worse than no portrait.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Card arguments. See bgcf_card_defaults().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args  = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_title = (string) ( $bgcf_args['title'] ?? '' );
$bgcf_url   = (string) ( $bgcf_args['url'] ?? '' );
$bgcf_role  = (string) ( $bgcf_args['label'] ?? $bgcf_args['eyebrow'] ?? '' );
$bgcf_tag   = in_array( (string) ( $bgcf_args['title_tag'] ?? 'h3' ), array( 'h2', 'h3', 'h4', 'h5' ), true )
	? (string) $bgcf_args['title_tag']
	: 'h3';

$bgcf_image = bgcf_get_post_thumbnail(
	array(
		'attachment_id' => (int) ( $bgcf_args['image'] ?? 0 ),
		'size'          => (string) ( $bgcf_args['image_size'] ?? 'bgcf-team' ),
		'alt'           => '',
		'loading'       => (string) ( $bgcf_args['loading'] ?? 'lazy' ),
		'class'         => 'bgcf-card__image',
		'fallback'      => false,
	)
);
?>
<article class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--person', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>">

	<div class="bgcf-card__portrait">
		<?php
		if ( '' !== $bgcf_image ) {
			echo $bgcf_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		} else {
			/*
			 * No portrait: render the initials rather than a grey silhouette, so
			 * a directory with a few missing photographs still looks finished.
			 * Decorative — the name is right underneath.
			 */
			printf(
				'<span class="bgcf-card__initials" aria-hidden="true">%s</span>',
				esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $bgcf_title ), 0, 1 ) ) )
			);
		}
		?>
	</div>

	<div class="bgcf-card__body">
		<?php
		printf(
			'<%1$s class="bgcf-card__title">%2$s</%1$s>',
			tag_escape( $bgcf_tag ),
			esc_html( $bgcf_title )
		);

		if ( '' !== $bgcf_role ) {
			printf( '<p class="bgcf-card__role">%s</p>', esc_html( $bgcf_role ) );
		}

		if ( '' !== (string) ( $bgcf_args['text'] ?? '' ) ) {
			printf( '<p class="bgcf-card__text">%s</p>', esc_html( (string) $bgcf_args['text'] ) );
		}

		if ( '' !== $bgcf_url ) {
			bgcf_text_link(
				array(
					'text'    => '' !== (string) ( $bgcf_args['link_text'] ?? '' )
						? (string) $bgcf_args['link_text']
						: bgcf_get_text( 'card_read_profile', __( 'Read profile', 'bgcf' ) ),
					'url'     => $bgcf_url,
					'context' => $bgcf_title,
				)
			);
		}
		?>
	</div>
</article>
