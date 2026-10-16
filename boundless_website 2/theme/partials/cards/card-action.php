<?php
/**
 * Action card — image, title, one sentence, and a real button.
 *
 * Used on the "Take action" hub, where every card IS the call to action rather
 * than a route to more reading. This is the one card in the family that carries
 * a button instead of a text link, and the one that must NOT stretch its link
 * over the whole card: a stretched link over a card whose affordance is a button
 * produces two overlapping targets and a confusing hit area where the button's
 * edge meets the card's.
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
		'class'         => 'bgcf-card__image',
		'fallback'      => true,
	)
);

$bgcf_label = (string) ( $bgcf_args['link_text'] ?? '' );

if ( '' === $bgcf_label ) {
	$bgcf_label = bgcf_get_text( 'card_get_involved', __( 'Get involved', 'bgcf' ) );
}
?>
<article class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--action', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>">

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
		if ( '' !== $bgcf_url ) {
			/*
			 * `aria_label` carries the card title because "Get involved" on its
			 * own is ambiguous in a grid of ten of them, and unlike the text
			 * link there is no visually hidden suffix inside a button label.
			 */
			bgcf_button(
				array(
					'text'       => $bgcf_label,
					'url'        => $bgcf_url,
					'variant'    => 'primary',
					'size'       => 'sm',
					'target'     => (string) ( $bgcf_args['target'] ?? '' ),
					'class'      => 'bgcf-card__button',
					'aria_label' => '' !== $bgcf_title
						? sprintf(
							/* translators: 1: button label, e.g. "Get involved", 2: the action card's title. */
							_x( '%1$s: %2$s', 'action card button', 'bgcf' ),
							$bgcf_label,
							$bgcf_title
						)
						: '',
				)
			);
		}
		?>
	</div>
</article>
