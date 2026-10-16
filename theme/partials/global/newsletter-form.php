<?php
/**
 * Newsletter sign-up form.
 *
 * Shared by the `bgcf/newsletter` block and the footer, so the consent wording,
 * the honeypot and the status region can never drift between the two places a
 * visitor can subscribe.
 *
 * Submits to `bgcf_newsletter_subscribe` (inc/ajax.php). The form is a real
 * `<form>` with a native `required` email input, so the browser validates it
 * before any JavaScript runs; the JS only replaces the navigation with an
 * in-place status message.
 *
 * CONSENT. The checkbox is required and unticked by default. Pre-ticked consent
 * is not consent, and this is a children's charity collecting personal data —
 * the standard is the strict reading, not the convenient one.
 *
 * @package Bgcf
 * @since   1.2.0
 *
 * @var array<string, mixed> $args {
 *     @type string $class        Extra class names.
 *     @type string $consent_text Consent label. Falls back to the global string.
 *     @type string $submit_label Button label.
 *     @type mixed  $privacy_link ACF link to the privacy policy.
 *     @type string $source       Where the sign-up came from, stored on the record.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_uid     = 'bgcf-news-' . wp_unique_id();
$bgcf_source  = sanitize_key( (string) ( $args['source'] ?? 'site' ) );
$bgcf_privacy = $args['privacy_link'] ?? bgcf_option( 'privacy_link', '' );

$bgcf_consent = (string) ( $args['consent_text'] ?? '' );

if ( '' === $bgcf_consent ) {
	$bgcf_consent = bgcf_get_text(
		'newsletter_consent',
		__( 'Yes, email me occasional updates about this work. I can unsubscribe at any time.', 'bgcf' )
	);
}

$bgcf_submit = (string) ( $args['submit_label'] ?? '' );

if ( '' === $bgcf_submit ) {
	$bgcf_submit = bgcf_get_text( 'newsletter_submit', __( 'Subscribe', 'bgcf' ) );
}

$bgcf_privacy_url = is_array( $bgcf_privacy ) ? (string) ( $bgcf_privacy['url'] ?? '' ) : (string) $bgcf_privacy;
?>
<form
	class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-newsletter', (string) ( $args['class'] ?? '' ) ) ) ); ?>"
	action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	method="post"
	data-bgcf-ajax-form="newsletter"
>
	<input type="hidden" name="action" value="bgcf_newsletter_subscribe" />
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bgcf_ajax' ) ); ?>" data-bgcf-nonce />
	<input type="hidden" name="source" value="<?php echo esc_attr( $bgcf_source ); ?>" />

	<?php /* Honeypot: hidden from sight and from assistive tech, never from bots. */ ?>
	<div class="bgcf-form__hp" aria-hidden="true">
		<label for="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>"><?php esc_html_e( 'Leave this field empty', 'bgcf' ); ?></label>
		<input type="text" id="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>" name="hp" tabindex="-1" autocomplete="off" />
	</div>

	<div class="bgcf-newsletter__row">
		<div class="bgcf-field bgcf-newsletter__field">
			<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>">
				<?php bgcf_the_text( 'newsletter_email_label', __( 'Email address', 'bgcf' ) ); ?>
			</label>
			<input
				type="email"
				class="bgcf-field__input"
				id="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>"
				name="email"
				autocomplete="email"
				required
				placeholder="<?php echo esc_attr( bgcf_get_text( 'newsletter_email_placeholder', __( 'you@example.com', 'bgcf' ) ) ); ?>"
			/>
		</div>

		<?php
		bgcf_button(
			array(
				'text'    => $bgcf_submit,
				'variant' => 'primary',
				'type'    => 'submit',
				'icon'    => 'arrow-right',
				'class'   => 'bgcf-newsletter__submit',
			)
		);
		?>
	</div>

	<div class="bgcf-form__check">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>"
			name="consent"
			value="1"
			required
		/>
		<label for="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>">
			<?php echo esc_html( $bgcf_consent ); ?>
			<?php if ( '' !== $bgcf_privacy_url ) : ?>
				<a href="<?php echo esc_url( $bgcf_privacy_url ); ?>">
					<?php bgcf_the_text( 'privacy_link_label', __( 'Privacy policy', 'bgcf' ) ); ?>
				</a>
			<?php endif; ?>
		</label>
	</div>

	<p class="bgcf-form__status" role="status" aria-live="polite" data-bgcf-form-status></p>
</form>
