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

<?php
/*
 * The newsletter sign-up used to live at the bottom of this column. In the
 * Horizon footer it is its own light card in the right-hand half — see
 * `partials/global/subscribe-card.php`, rendered directly from footer.php.
 * Keeping it out of here means the contact column is only contact details, and
 * there is exactly one subscribe form in the footer rather than two that drift.
 */

