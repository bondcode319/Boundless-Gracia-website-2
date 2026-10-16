<?php
/**
 * Contact form.
 *
 * Submits to `bgcf_contact_submit` (inc/ajax.php) through the shared handler in
 * assets/js/src/forms.js, so the nonce recovery, the busy state, the field-level
 * error focus and the success panel all behave exactly as they do on the
 * newsletter and volunteer forms.
 *
 * WHY NOT CONTACT FORM 7. CF7 is listed in the stack and is still welcome — if
 * it is active, an editor can drop its shortcode anywhere. But a charity's
 * primary contact route should not be unavailable because a plugin licence
 * lapsed or an update broke, and CF7 cannot honour this theme's consent,
 * honeypot and rate-limit contract without configuration nobody will remember.
 * This is the floor; CF7 is the option.
 *
 * @package Bgcf
 * @since   1.2.0
 *
 * @var array<string, mixed> $args {
 *     @type string $class    Extra class names.
 *     @type string $subjects Optional newline-separated subject choices.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_uid     = 'bgcf-contact-' . wp_unique_id();
$bgcf_privacy = bgcf_option( 'privacy_link', '' );
$bgcf_privacy = is_array( $bgcf_privacy ) ? (string) ( $bgcf_privacy['url'] ?? '' ) : (string) $bgcf_privacy;

$bgcf_subjects = array_filter(
	array_map( 'trim', explode( "\n", (string) ( $args['subjects'] ?? '' ) ) )
);
?>
<form
	class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-form', 'bgcf-contact-form', (string) ( $args['class'] ?? '' ) ) ) ); ?>"
	action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	method="post"
	data-bgcf-ajax-form="contact"
>
	<input type="hidden" name="action" value="bgcf_contact_submit" />
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bgcf_ajax' ) ); ?>" data-bgcf-nonce />

	<div class="bgcf-form__hp" aria-hidden="true">
		<label for="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>"><?php esc_html_e( 'Leave this field empty', 'bgcf' ); ?></label>
		<input type="text" id="<?php echo esc_attr( $bgcf_uid . '-hp' ); ?>" name="hp" tabindex="-1" autocomplete="off" />
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-name' ); ?>">
			<?php esc_html_e( 'Your name', 'bgcf' ); ?>
			<span class="bgcf-field__required" aria-hidden="true">*</span>
		</label>
		<input
			type="text"
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-name' ); ?>"
			name="name"
			autocomplete="name"
			required
		/>
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>">
			<?php esc_html_e( 'Email address', 'bgcf' ); ?>
			<span class="bgcf-field__required" aria-hidden="true">*</span>
		</label>
		<input
			type="email"
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>"
			name="email"
			autocomplete="email"
			required
		/>
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>">
			<?php esc_html_e( 'Phone number', 'bgcf' ); ?>
		</label>
		<input
			type="tel"
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>"
			name="phone"
			autocomplete="tel"
		/>
		<p class="bgcf-field__help"><?php esc_html_e( 'Optional — only if you would rather we called.', 'bgcf' ); ?></p>
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-subject' ); ?>">
			<?php esc_html_e( 'What is this about?', 'bgcf' ); ?>
		</label>

		<?php if ( ! empty( $bgcf_subjects ) ) : ?>
			<select class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-subject' ); ?>" name="subject">
				<option value=""><?php esc_html_e( 'Please choose…', 'bgcf' ); ?></option>
				<?php foreach ( $bgcf_subjects as $bgcf_subject ) : ?>
					<option value="<?php echo esc_attr( $bgcf_subject ); ?>"><?php echo esc_html( $bgcf_subject ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input
				type="text"
				class="bgcf-field__input"
				id="<?php echo esc_attr( $bgcf_uid . '-subject' ); ?>"
				name="subject"
			/>
		<?php endif; ?>
	</div>

	<div class="bgcf-field bgcf-contact-form__wide">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-message' ); ?>">
			<?php esc_html_e( 'Your message', 'bgcf' ); ?>
			<span class="bgcf-field__required" aria-hidden="true">*</span>
		</label>
		<textarea
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-message' ); ?>"
			name="message"
			rows="6"
			required
			minlength="10"
		></textarea>
	</div>

	<div class="bgcf-contact-form__wide">
		<div class="bgcf-form__check">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>"
				name="consent"
				value="1"
				required
			/>
			<label for="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>">
				<?php esc_html_e( 'I agree that my details may be stored so you can reply to me.', 'bgcf' ); ?>
				<?php if ( '' !== $bgcf_privacy ) : ?>
					<a href="<?php echo esc_url( $bgcf_privacy ); ?>">
						<?php bgcf_the_text( 'privacy_link_label', __( 'Privacy policy', 'bgcf' ) ); ?>
					</a>
				<?php endif; ?>
			</label>
		</div>

		<?php
		bgcf_button(
			array(
				'text'    => bgcf_get_text( 'contact_submit', __( 'Send message', 'bgcf' ) ),
				'variant' => 'primary',
				'size'    => 'lg',
				'type'    => 'submit',
				'icon'    => 'arrow-right',
				'class'   => 'bgcf-contact-form__submit',
			)
		);
		?>

		<p class="bgcf-form__status" role="status" aria-live="polite" data-bgcf-form-status></p>
	</div>
</form>
