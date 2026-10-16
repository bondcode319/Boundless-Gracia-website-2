<?php
/**
 * Donation result.
 *
 * What the donor sees after coming back from Flutterwave. Rendered by the
 * `[bgcf_donation_result]` shortcode on the thank-you page.
 *
 * The status comes from the donation record, which only ever changes after a
 * server-side verify. A pending record is reported honestly as "still
 * processing" and polled by JS — telling a donor their gift failed because the
 * mobile money callback was four seconds late costs a donation and a supporter.
 *
 * No PII is echoed here beyond the amount and the reference: the page is
 * reachable by anyone holding the token, including over a shared link.
 *
 * @package Bgcf
 * @since   1.1.0
 *
 * @var array<string, mixed> $args {
 *     @type int    $donation_id Donation post ID, or 0 when the token is unknown.
 *     @type string $token       Opaque donation token from the query string.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_donation_id = absint( $args['donation_id'] ?? 0 );
$bgcf_token       = (string) ( $args['token'] ?? '' );

if ( 0 === $bgcf_donation_id ) :
	?>
	<div class="bgcf-donation-result bgcf-donation-result--unknown">
		<p class="bgcf-donation-result__message">
			<?php esc_html_e( 'We could not find that donation. If you were charged, email us your reference and we will sort it out.', 'bgcf' ); ?>
		</p>
	</div>
	<?php
	return;
endif;

$bgcf_status = bgcf_donation_status( $bgcf_donation_id );
$bgcf_amount = bgcf_donation_format_amount(
	(int) bgcf_donation_get( $bgcf_donation_id, 'amount', 0 ),
	(string) bgcf_donation_get( $bgcf_donation_id, 'currency', '' )
);
$bgcf_ref    = (string) bgcf_donation_get( $bgcf_donation_id, 'tx_ref' );

$bgcf_messages = array(
	'successful' => sprintf(
		/* translators: %s: formatted amount. */
		__( 'Thank you. Your gift of %s has been received, and a receipt is on its way to your email.', 'bgcf' ),
		$bgcf_amount
	),
	'pending'    => sprintf(
		/* translators: %s: formatted amount. */
		__( 'Your gift of %s is still being confirmed. If you were asked to approve a prompt on your phone, please complete it — this page will update by itself.', 'bgcf' ),
		$bgcf_amount
	),
	'failed'     => __( 'That payment did not go through, and you have not been charged. Please try again, or use a different method.', 'bgcf' ),
	'abandoned'  => __( 'This donation was not completed. You have not been charged.', 'bgcf' ),
);
?>

<div
	class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-donation-result', 'bgcf-donation-result--' . $bgcf_status ) ) ); ?>"
	data-bgcf-donation-result="<?php echo esc_attr( $bgcf_token ); ?>"
	data-bgcf-status="<?php echo esc_attr( $bgcf_status ); ?>"
	data-bgcf-timeout-text="<?php esc_attr_e( 'This is taking longer than usual. We will email you as soon as the payment is confirmed.', 'bgcf' ); ?>"
>
	<p class="bgcf-donation-result__message" data-bgcf-result-message>
		<?php echo esc_html( (string) ( $bgcf_messages[ $bgcf_status ] ?? $bgcf_messages['pending'] ) ); ?>
	</p>

	<p class="bgcf-donation-result__reference">
		<?php esc_html_e( 'Reference:', 'bgcf' ); ?>
		<code><?php echo esc_html( $bgcf_ref ); ?></code>
	</p>

	<?php if ( 'test' === (string) bgcf_donation_get( $bgcf_donation_id, 'mode', 'test' ) ) : ?>
		<p class="bgcf-donation-result__sandbox">
			<?php esc_html_e( 'Test mode: this was a sandbox transaction and no money moved.', 'bgcf' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( in_array( $bgcf_status, array( 'failed', 'abandoned' ), true ) ) : ?>
		<?php
		bgcf_button(
			array(
				'text'    => __( 'Try again', 'bgcf' ),
				'url'     => home_url( '/' ),
				'variant' => 'primary',
			)
		);
		?>
	<?php endif; ?>
</div>
