<?php
/**
 * Service card — no image, flat fill, title, short description, optional link.
 *
 * Designed to sit on a DARK band, which is why the fill is the neutral paper
 * tint rather than one of the brand tints: a tinted card on a deep-blue band
 * reads as a lighter patch of the band, while a near-white card reads as paper
 * laid on top of it. On a light band the card keeps a visible border so the
 * boundary survives even where the fill and the band are close in value.
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
?>
<article class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--service', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>">

	<?php if ( '' !== (string) ( $bgcf_args['icon'] ?? '' ) ) : ?>
		<span class="bgcf-card__icon" aria-hidden="true">
			<?php bgcf_svg_icon( (string) $bgcf_args['icon'] ); ?>
		</span>
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
	// A service card is often purely descriptive, so the link is optional —
	// unlike the project card, which is always a route to somewhere.
	if ( '' !== $bgcf_url ) {
		bgcf_text_link(
			array(
				'text'    => '' !== (string) ( $bgcf_args['link_text'] ?? '' )
					? (string) $bgcf_args['link_text']
					: bgcf_get_text( 'card_learn_more', __( 'Learn more', 'bgcf' ) ),
				'url'     => $bgcf_url,
				'context' => $bgcf_title,
				'target'  => (string) ( $bgcf_args['target'] ?? '' ),
			)
		);
	}
	?>
</article>
