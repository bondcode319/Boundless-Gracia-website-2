<?php
/**
 * Project card — image on top, tinted body, title, description, text link.
 *
 * Horizon's workhorse card: programmes, campaigns, ways to give, related
 * reading. Rendered only through `bgcf_card( 'project', … )`; see inc/horizon.php
 * for the argument bundle and for why the link wraps the title rather than the
 * whole card.
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
$bgcf_tag   = in_array( (string) ( $bgcf_args['title_tag'] ?? 'h3' ), array( 'h2', 'h3', 'h4', 'h5' ), true )
	? (string) $bgcf_args['title_tag']
	: 'h3';

$bgcf_image = bgcf_get_post_thumbnail(
	array(
		'attachment_id' => (int) ( $bgcf_args['image'] ?? 0 ),
		'size'          => (string) ( $bgcf_args['image_size'] ?? 'bgcf-card' ),
		'alt'           => (string) ( $bgcf_args['image_alt'] ?? '' ),
		'loading'       => (string) ( $bgcf_args['loading'] ?? 'lazy' ),
		'fetchpriority' => ! empty( $bgcf_args['priority'] ) ? 'high' : 'auto',
		'class'         => 'bgcf-card__image',
		'fallback'      => true,
	)
);

$bgcf_link_text = (string) ( $bgcf_args['link_text'] ?? '' );

if ( '' === $bgcf_link_text ) {
	$bgcf_link_text = bgcf_get_text( 'card_read_more', __( 'Read more', 'bgcf' ) );
}
?>
<article class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--project', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>">

	<?php if ( '' !== $bgcf_image ) : ?>
		<div class="bgcf-card__media">
			<?php echo $bgcf_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="bgcf-card__body">

		<?php if ( '' !== (string) ( $bgcf_args['eyebrow'] ?? '' ) ) : ?>
			<p class="bgcf-card__eyebrow"><?php echo esc_html( (string) $bgcf_args['eyebrow'] ); ?></p>
		<?php endif; ?>

		<?php
		printf(
			'<%1$s class="bgcf-card__title">%2$s</%1$s>',
			tag_escape( $bgcf_tag ),
			esc_html( $bgcf_title )
		);
		?>

		<?php if ( '' !== (string) ( $bgcf_args['text'] ?? '' ) ) : ?>
			<p class="bgcf-card__text"><?php echo esc_html( (string) $bgcf_args['text'] ); ?></p>
		<?php endif; ?>

		<?php
		if ( ! empty( $bgcf_args['meta'] ) && is_array( $bgcf_args['meta'] ) ) {
			echo '<ul class="bgcf-card__meta">';

			foreach ( $bgcf_args['meta'] as $bgcf_meta ) {
				if ( '' === trim( (string) $bgcf_meta ) ) {
					continue;
				}

				printf( '<li class="bgcf-card__meta-item">%s</li>', esc_html( (string) $bgcf_meta ) );
			}

			echo '</ul>';
		}

		/*
		 * The text link is also the card's click target (it stretches over the
		 * card via a pseudo-element). `context` supplies the card title to the
		 * accessible name so a grid of nine "Read more" links is nine distinct
		 * links to a screen reader.
		 */
		bgcf_text_link(
			array(
				'text'    => $bgcf_link_text,
				'url'     => $bgcf_url,
				'context' => $bgcf_title,
				'target'  => (string) ( $bgcf_args['target'] ?? '' ),
			)
		);
		?>
	</div>
</article>
