<?php
/**
 * Footer contact column: address, phone, email and the newsletter sign-up.
 *
 * Every string comes from Site Options → Footer / Contact. The column renders
 * only the rows that are filled in, so a site without a street address does not
 * show an empty line.
 *
 * The address is marked up as a real `<address>` and the phone and email are
 * actionable links — on a phone, "call us" should be one tap, not a copy-paste.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_address = (string) bgcf_option( 'contact_address', '' );
$bgcf_phone   = (string) bgcf_option( 'contact_phone', '' );
$bgcf_email   = (string) bgcf_option( 'contact_email', '' );
$bgcf_hours   = (string) bgcf_option( 'contact_hours', '' );
?>
<h2 class="bgcf-site-footer__heading">
	<?php bgcf_the_text( 'footer_contact_title', __( 'Get in touch', 'bgcf' ) ); ?>
</h2>

<ul class="bgcf-site-footer__contact">
	<?php if ( '' !== $bgcf_address ) : ?>
		<li class="bgcf-site-footer__contact-item">
			<?php bgcf_svg_icon( 'map-pin' ); ?>
			<address class="bgcf-site-footer__address"><?php echo wp_kses_post( wpautop( $bgcf_address ) ); ?></address>
		</li>
	<?php endif; ?>

	<?php if ( '' !== $bgcf_phone ) : ?>
		<li class="bgcf-site-footer__contact-item">
			<?php bgcf_svg_icon( 'phone' ); ?>
			<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $bgcf_phone ) ); ?>">
				<?php echo esc_html( $bgcf_phone ); ?>
			</a>
		</li>
	<?php endif; ?>

	<?php if ( '' !== $bgcf_email ) : ?>
		<li class="bgcf-site-footer__contact-item">
			<?php bgcf_svg_icon( 'mail' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . $bgcf_email ); ?>">
				<?php echo esc_html( $bgcf_email ); ?>
			</a>
		</li>
	<?php endif; ?>

	<?php if ( '' !== $bgcf_hours ) : ?>
		<li class="bgcf-site-footer__contact-item">
			<?php bgcf_svg_icon( 'clock' ); ?>
			<span><?php echo esc_html( $bgcf_hours ); ?></span>
		</li>
	<?php endif; ?>
</ul>

<?php if ( (bool) bgcf_option( 'footer_show_newsletter', true ) ) : ?>
	<div class="bgcf-site-footer__newsletter">
		<h2 class="bgcf-site-footer__heading">
			<?php bgcf_the_text( 'footer_newsletter_title', __( 'Stay in the loop', 'bgcf' ) ); ?>
		</h2>

		<?php
		$bgcf_news_intro = (string) bgcf_option( 'footer_newsletter_text', '' );

		if ( '' !== $bgcf_news_intro ) {
			printf( '<p class="bgcf-site-footer__newsletter-text">%s</p>', esc_html( $bgcf_news_intro ) );
		}

		bgcf_render_partial(
			'global/newsletter-form',
			array(
				'class'  => 'bgcf-newsletter--footer',
				'source' => 'footer',
			)
		);
		?>
	</div>
<?php endif; ?>
