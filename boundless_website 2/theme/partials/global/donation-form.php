<?php
/**
 * Donation form.
 *
 * The site's money surface. Rendered by the Donation Banner block, the donate
 * page template, and anywhere else a gift can be started — always through
 * bgcf_render_partial( 'global/donation-form', $args ).
 *
 * PROGRESSIVE ENHANCEMENT: this is a real <form> that posts to admin-ajax.php.
 * With JavaScript the submit is intercepted and the donor is redirected to the
 * provider without a page reload; without it the browser posts the form and
 * follows the JSON... which it cannot render. So the no-JS path is handled by
 * keeping every field native and required, and by the noscript notice below
 * telling the donor how else to give. Do not add a field that only works with JS.
 *
 * ACCESSIBILITY: amount presets are radio inputs in a fieldset, not buttons, so
 * arrow keys work and screen readers announce the group and the selection. The
 * status region is `aria-live="polite"` — mobile money takes ten seconds to come
 * back and a silent spinner is where donors give up.
 *
 * CONVERSION. Three things here exist because they measurably change whether a
 * visitor finishes giving, and all three are server-driven so the figures shown
 * are the figures charged:
 *   - An impact line under the amount ("UGX 50,000 feeds a child for a month").
 *   - An opt-in to cover the processing fee, with a live total.
 *   - For a campaign, a progress bar computed from the donation records.
 *
 * PRIVACY. The form collects a name, an email and sometimes a phone number, so
 * it asks for consent and links the privacy policy. The newsletter form has
 * required this since it shipped; the form handling money should not be the
 * laxer of the two.
 *
 * @package Bgcf
 * @since   1.1.0
 *
 * @var array<string, mixed> $args {
 *     @type int    $campaign Campaign post ID to attribute the gift to. Default 0.
 *     @type string $heading  Optional form heading.
 *     @type string $class    Extra class names.
 *     @type string $id       DOM id prefix. Auto-generated when empty.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_campaign = absint( $args['campaign'] ?? 0 );
$bgcf_uid      = (string) ( $args['id'] ?? '' );
$bgcf_uid      = '' !== $bgcf_uid ? sanitize_html_class( $bgcf_uid ) : 'bgcf-donate-' . wp_rand( 1000, 9999 );
$bgcf_methods  = bgcf_donation_methods();
$bgcf_presets  = bgcf_donation_amount_presets();
$bgcf_default  = absint( bgcf_donation_setting( 'default_amount', 0 ) );
$bgcf_currency = bgcf_donation_currency();
$bgcf_fee      = bgcf_fee_config();
$bgcf_fee_on   = bgcf_fee_cover_enabled();
$bgcf_impacts  = bgcf_impact_lines();
$bgcf_consent  = (bool) bgcf_donation_setting( 'consent_required', true );
$bgcf_privacy  = bgcf_option( 'privacy_link', '' );
$bgcf_privacy  = is_array( $bgcf_privacy ) ? (string) ( $bgcf_privacy['url'] ?? '' ) : (string) $bgcf_privacy;

if ( ! bgcf_flw_is_configured() || empty( $bgcf_methods ) ) {
	bgcf_empty_state( __( 'Donations: add the Flutterwave keys to wp-config.php and enable at least one payment method under Donations → Settings.', 'bgcf' ) );

	printf(
		'<p class="bgcf-donate__unavailable">%s</p>',
		esc_html(
			bgcf_get_text(
				'donation_unavailable',
				__( 'Online giving is temporarily unavailable. Please contact us and we will help you give another way.', 'bgcf' )
			)
		)
	);

	return;
}
?>

<form
	class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-donate', (string) ( $args['class'] ?? '' ) ) ) ); ?>"
	id="<?php echo esc_attr( $bgcf_uid ); ?>"
	action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	method="post"
	novalidate
	data-bgcf-donation-form
	data-bgcf-campaign="<?php echo esc_attr( (string) $bgcf_campaign ); ?>"
	data-bgcf-fee-percent="<?php echo esc_attr( (string) $bgcf_fee['percent'] ); ?>"
	data-bgcf-fee-flat="<?php echo esc_attr( (string) $bgcf_fee['flat'] ); ?>"
	data-bgcf-fee-cap="<?php echo esc_attr( (string) $bgcf_fee['cap'] ); ?>"
>
	<?php if ( ! empty( $args['heading'] ) ) : ?>
		<h3 class="bgcf-donate__heading" id="<?php echo esc_attr( $bgcf_uid . '-heading' ); ?>">
			<?php echo esc_html( (string) $args['heading'] ); ?>
		</h3>
	<?php endif; ?>

	<?php if ( bgcf_flw_is_sandbox() ) : ?>
		<p class="bgcf-donate__sandbox" role="status">
			<?php esc_html_e( 'Test mode: no real money will be taken. Mobile money prompts approve themselves automatically after a few seconds.', 'bgcf' ); ?>
		</p>
	<?php endif; ?>

	<input type="hidden" name="action" value="bgcf_donation_start" />
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bgcf_ajax' ) ); ?>" />
	<input type="hidden" name="campaign" value="<?php echo esc_attr( (string) $bgcf_campaign ); ?>" />

	<?php
	/*
	 * Idempotency key. Filled in by JavaScript with a value unique to this
	 * attempt, and reused if the request is retried — see the server side in
	 * bgcf_donation_replay(). Left empty without JS, where a double submit is
	 * a full page reload and the browser's own resubmit warning applies.
	 */
	?>
	<input type="hidden" name="idempotency_key" value="" data-bgcf-idempotency />

	<?php
	/*
	 * Honeypot. Hidden from sight and from assistive technology, never from a
	 * bot's form parser. Anything that fills it is rejected server-side.
	 */
	?>
	<div class="bgcf-donate__hp" aria-hidden="true">
		<label for="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>"><?php esc_html_e( 'Leave this field empty', 'bgcf' ); ?></label>
		<input type="text" id="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>" name="hp" tabindex="-1" autocomplete="off" />
	</div>

	<?php if ( ! empty( $bgcf_presets ) ) : ?>
		<fieldset class="bgcf-donate__amounts">
			<legend class="bgcf-donate__legend">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: currency code. */
						__( 'Choose an amount (%s)', 'bgcf' ),
						$bgcf_currency
					)
				);
				?>
			</legend>

			<div class="bgcf-donate__amount-grid">
				<?php foreach ( $bgcf_presets as $bgcf_preset ) : ?>
					<?php $bgcf_preset_id = $bgcf_uid . '-amount-' . $bgcf_preset; ?>
					<div class="bgcf-donate__amount">
						<input
							type="radio"
							class="bgcf-donate__amount-input"
							id="<?php echo esc_attr( $bgcf_preset_id ); ?>"
							name="preset_amount"
							value="<?php echo esc_attr( (string) $bgcf_preset ); ?>"
							<?php checked( $bgcf_default, $bgcf_preset ); ?>
							data-bgcf-amount-preset
						/>
						<label class="bgcf-donate__amount-label" for="<?php echo esc_attr( $bgcf_preset_id ); ?>">
							<?php echo esc_html( number_format_i18n( $bgcf_preset ) ); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>

	<div class="bgcf-donate__field">
		<label class="bgcf-donate__label" for="<?php echo esc_attr( $bgcf_uid . '-amount' ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: currency code. */
					__( 'Amount (%s)', 'bgcf' ),
					$bgcf_currency
				)
			);
			?>
			<span class="bgcf-donate__required" aria-hidden="true">*</span>
		</label>
		<input
			type="number"
			class="bgcf-donate__input"
			id="<?php echo esc_attr( $bgcf_uid . '-amount' ); ?>"
			name="amount"
			inputmode="numeric"
			step="1"
			min="<?php echo esc_attr( (string) absint( bgcf_donation_setting( 'min_amount', 1000 ) ) ); ?>"
			max="<?php echo esc_attr( (string) absint( bgcf_donation_setting( 'max_amount', 20000000 ) ) ); ?>"
			value="<?php echo esc_attr( $bgcf_default > 0 ? (string) $bgcf_default : '' ); ?>"
			required
			data-bgcf-amount-input
		/>
	</div>

	<?php if ( ! empty( $bgcf_impacts ) ) : ?>
		<?php
		/*
		 * Impact lines. All of them are rendered and all but the matching one
		 * are hidden, so the copy is in the HTML for search engines and for a
		 * no-JS visitor, and switching lines costs no request. `aria-live` is
		 * deliberately absent: this is supporting copy, and announcing a new
		 * sentence on every keystroke in the amount field would be hostile.
		 */
		$bgcf_amount_now = $bgcf_default;
		$bgcf_match      = null;

		foreach ( $bgcf_impacts as $bgcf_line ) {
			if ( $bgcf_amount_now >= $bgcf_line['amount'] ) {
				$bgcf_match = $bgcf_line['amount'];
			}
		}
		?>
		<div class="bgcf-donate__impacts">
			<?php foreach ( $bgcf_impacts as $bgcf_line ) : ?>
				<p
					class="bgcf-donate__impact"
					data-bgcf-impact="<?php echo esc_attr( (string) $bgcf_line['amount'] ); ?>"
					<?php echo $bgcf_match === $bgcf_line['amount'] ? '' : 'hidden'; ?>
				>
					<?php bgcf_svg_icon( 'check-circle' ); ?>
					<span><?php echo esc_html( $bgcf_line['text'] ); ?></span>
				</p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<fieldset class="bgcf-donate__methods">
		<legend class="bgcf-donate__legend"><?php esc_html_e( 'How would you like to give?', 'bgcf' ); ?></legend>

		<?php $bgcf_first = true; ?>
		<?php foreach ( $bgcf_methods as $bgcf_method => $bgcf_method_label ) : ?>
			<?php $bgcf_method_id = $bgcf_uid . '-method-' . $bgcf_method; ?>
			<div class="bgcf-donate__method">
				<input
					type="radio"
					class="bgcf-donate__method-input"
					id="<?php echo esc_attr( $bgcf_method_id ); ?>"
					name="method"
					value="<?php echo esc_attr( $bgcf_method ); ?>"
					<?php checked( $bgcf_first ); ?>
					data-bgcf-method
				/>
				<label class="bgcf-donate__method-label" for="<?php echo esc_attr( $bgcf_method_id ); ?>">
					<?php echo esc_html( $bgcf_method_label ); ?>
				</label>
			</div>
			<?php $bgcf_first = false; ?>
		<?php endforeach; ?>
	</fieldset>

	<?php if ( isset( $bgcf_methods['mobile_money'] ) ) : ?>
		<div class="bgcf-donate__mobile" data-bgcf-mobile-fields>
			<div class="bgcf-donate__field">
				<label class="bgcf-donate__label" for="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>">
					<?php esc_html_e( 'Mobile money number', 'bgcf' ); ?>
				</label>
				<input
					type="tel"
					class="bgcf-donate__input"
					id="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>"
					name="phone"
					autocomplete="tel"
					inputmode="tel"
					placeholder="0772 123 456"
					data-bgcf-phone
				/>
				<p class="bgcf-donate__help">
					<?php esc_html_e( 'You will get a prompt on this phone to approve the payment.', 'bgcf' ); ?>
				</p>
			</div>

			<div class="bgcf-donate__field">
				<label class="bgcf-donate__label" for="<?php echo esc_attr( $bgcf_uid . '-network' ); ?>">
					<?php esc_html_e( 'Network', 'bgcf' ); ?>
				</label>
				<select
					class="bgcf-donate__input"
					id="<?php echo esc_attr( $bgcf_uid . '-network' ); ?>"
					name="network"
					data-bgcf-network
				>
					<option value=""><?php esc_html_e( 'Detect from my number', 'bgcf' ); ?></option>
					<?php foreach ( bgcf_flw_networks() as $bgcf_network => $bgcf_network_label ) : ?>
						<option value="<?php echo esc_attr( $bgcf_network ); ?>">
							<?php echo esc_html( $bgcf_network_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	<?php endif; ?>

	<div class="bgcf-donate__field">
		<label class="bgcf-donate__label" for="<?php echo esc_attr( $bgcf_uid . '-name' ); ?>">
			<?php esc_html_e( 'Your name', 'bgcf' ); ?>
		</label>
		<input
			type="text"
			class="bgcf-donate__input"
			id="<?php echo esc_attr( $bgcf_uid . '-name' ); ?>"
			name="name"
			autocomplete="name"
		/>
	</div>

	<div class="bgcf-donate__field">
		<label class="bgcf-donate__label" for="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>">
			<?php esc_html_e( 'Email address', 'bgcf' ); ?>
			<span class="bgcf-donate__required" aria-hidden="true">*</span>
		</label>
		<input
			type="email"
			class="bgcf-donate__input"
			id="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>"
			name="email"
			autocomplete="email"
			required
		/>
		<p class="bgcf-donate__help"><?php esc_html_e( 'We send your receipt here. We never share it.', 'bgcf' ); ?></p>
	</div>

	<div class="bgcf-donate__field bgcf-donate__field--check">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $bgcf_uid . '-anonymous' ); ?>"
			name="anonymous"
			value="1"
		/>
		<label for="<?php echo esc_attr( $bgcf_uid . '-anonymous' ); ?>">
			<?php esc_html_e( 'Keep my gift anonymous publicly', 'bgcf' ); ?>
		</label>
	</div>

	<?php if ( $bgcf_fee_on ) : ?>
		<div class="bgcf-donate__fee">
			<div class="bgcf-form__check">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $bgcf_uid . '-fee' ); ?>"
					name="cover_fee"
					value="1"
					data-bgcf-cover-fee
				/>
				<label for="<?php echo esc_attr( $bgcf_uid . '-fee' ); ?>">
					<?php
					echo esc_html(
						bgcf_get_text(
							'donate_cover_fee',
							__( 'Add the processing fee so my whole gift reaches the children', 'bgcf' )
						)
					);
					?>
					<span class="bgcf-donate__fee-amount">
						(+<?php echo esc_html( $bgcf_currency ); ?>&nbsp;<span data-bgcf-fee-amount>0</span>)
					</span>
				</label>
			</div>

			<p class="bgcf-donate__total">
				<?php esc_html_e( 'Total today:', 'bgcf' ); ?>
				<strong>
					<?php echo esc_html( $bgcf_currency ); ?>
					<span data-bgcf-total><?php echo esc_html( number_format_i18n( $bgcf_default ) ); ?></span>
				</strong>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $bgcf_consent ) : ?>
		<div class="bgcf-form__check bgcf-donate__consent">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>"
				name="consent"
				value="1"
				required
			/>
			<label for="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>">
				<?php
				echo esc_html(
					bgcf_get_text(
						'donate_consent',
						__( 'I agree that my details may be stored to process this gift and send my receipt.', 'bgcf' )
					)
				);
				?>
				<?php if ( '' !== $bgcf_privacy ) : ?>
					<a href="<?php echo esc_url( $bgcf_privacy ); ?>">
						<?php bgcf_the_text( 'privacy_link_label', __( 'Privacy policy', 'bgcf' ) ); ?>
					</a>
				<?php endif; ?>
			</label>
		</div>
	<?php endif; ?>

	<?php
	bgcf_button(
		array(
			'text'       => bgcf_get_text( 'donate_submit', __( 'Give now', 'bgcf' ) ),
			'variant'    => 'primary',
			'size'       => 'lg',
			'type'       => 'submit',
			'full_width' => true,
			'class'      => 'bgcf-donate__submit',
			'attrs'      => array( 'data-bgcf-donate-submit' => '' ),
		)
	);
	?>

	<p class="bgcf-donate__status" role="status" aria-live="polite" data-bgcf-donate-status></p>

	<p class="bgcf-donate__secure">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: currency code. */
				__( 'Payments are processed securely by Flutterwave in %s. We never see or store your card or mobile money PIN.', 'bgcf' ),
				$bgcf_currency
			)
		);
		?>
	</p>

	<noscript>
		<p class="bgcf-donate__help">
			<?php esc_html_e( 'This form needs JavaScript to reach our payment provider. Please enable it, or contact us and we will send you a payment link.', 'bgcf' ); ?>
		</p>
	</noscript>
</form>
