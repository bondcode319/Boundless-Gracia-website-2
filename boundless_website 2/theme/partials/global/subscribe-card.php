<?php
/**
 * Subscribe card — the light card that carries the footer's sign-up form.
 *
 * The one bright object on the dark footer band, and the only form in it. It
 * re-declares the light-surface colour roles rather than inheriting the band's
 * inverted ones, which is what lets the shared newsletter form render here with
 * normal dark-on-light inputs and no footer-specific variant.
 *
 * `bgcf-u-on-light` is the mechanism (see `utilities/_visibility.scss`): it is
 * the inverse of `tools/elevation`'s `on-dark`, and it exists precisely for
 * islands of light surface inside a dark band.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! (bool) bgcf_option( 'footer_show_newsletter', true ) ) {
	return;
}

$bgcf_subscribe_text = (string) bgcf_option( 'footer_newsletter_text', '' );
?>
<div class="bgcf-site-footer__subscribe">
	<div class="bgcf-subscribe-card bgcf-u-on-light">
		<h2 class="bgcf-subscribe-card__title">
			<?php bgcf_the_text( 'footer_newsletter_title', __( 'Stay in the loop', 'bgcf' ) ); ?>
		</h2>

		<?php if ( '' !== $bgcf_subscribe_text ) : ?>
			<p class="bgcf-subscribe-card__text"><?php echo esc_html( $bgcf_subscribe_text ); ?></p>
		<?php endif; ?>

		<?php
		bgcf_render_partial(
			'global/newsletter-form',
			array(
				'class'     => 'bgcf-newsletter--card',
				'source'    => 'footer',
				'show_name' => true,
			)
		);
		?>
	</div>
</div>
