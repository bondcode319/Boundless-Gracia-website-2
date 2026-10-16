<?php
/**
 * Volunteer application form.
 *
 * Submits to `bgcf_volunteer_submit` (inc/ajax.php) through the shared handler
 * in assets/js/src/forms.js.
 *
 * THE CV UPLOAD is optional on purpose. Requiring a document excludes the
 * volunteers a community charity most wants to hear from — people applying from
 * a phone, who have no CV file and no way to make one. The server validates
 * whatever does arrive by its real content type, not its extension, and stores
 * it as a private attachment (see bgcf_handle_resume_upload()).
 *
 * @package Bgcf
 * @since   1.2.0
 *
 * @var array<string, mixed> $args {
 *     @type string $class  Extra class names.
 *     @type string $skills Newline-separated skill options.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_uid     = 'bgcf-vol-' . wp_unique_id();
$bgcf_privacy = bgcf_option( 'privacy_link', '' );
$bgcf_privacy = is_array( $bgcf_privacy ) ? (string) ( $bgcf_privacy['url'] ?? '' ) : (string) $bgcf_privacy;

$bgcf_skills = array_filter( array_map( 'trim', explode( "\n", (string) ( $args['skills'] ?? '' ) ) ) );

if ( empty( $bgcf_skills ) ) {
	$bgcf_skills = array(
		__( 'Teaching and tutoring', 'bgcf' ),
		__( 'Healthcare', 'bgcf' ),
		__( 'Administration', 'bgcf' ),
		__( 'Fundraising', 'bgcf' ),
		__( 'Construction and repairs', 'bgcf' ),
		__( 'Photography and media', 'bgcf' ),
		__( 'Driving', 'bgcf' ),
	);
}
?>
<form
	class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-form', 'bgcf-contact-form', 'bgcf-volunteer-form', (string) ( $args['class'] ?? '' ) ) ) ); ?>"
	action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	method="post"
	enctype="multipart/form-data"
	data-bgcf-ajax-form="volunteer"
>
	<input type="hidden" name="action" value="bgcf_volunteer_submit" />
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
		<input type="text" class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-name' ); ?>" name="name" autocomplete="name" required />
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>">
			<?php esc_html_e( 'Email address', 'bgcf' ); ?>
			<span class="bgcf-field__required" aria-hidden="true">*</span>
		</label>
		<input type="email" class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-email' ); ?>" name="email" autocomplete="email" required />
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>">
			<?php esc_html_e( 'Phone number', 'bgcf' ); ?>
		</label>
		<input type="tel" class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-phone' ); ?>" name="phone" autocomplete="tel" />
	</div>

	<div class="bgcf-field">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-location' ); ?>">
			<?php esc_html_e( 'Where are you based?', 'bgcf' ); ?>
		</label>
		<input type="text" class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-location' ); ?>" name="location" />
	</div>

	<fieldset class="bgcf-field bgcf-contact-form__wide">
		<legend class="bgcf-field__label"><?php esc_html_e( 'What could you help with?', 'bgcf' ); ?></legend>

		<div class="bgcf-volunteer-form__skills">
			<?php foreach ( $bgcf_skills as $bgcf_index => $bgcf_skill ) : ?>
				<?php $bgcf_skill_id = $bgcf_uid . '-skill-' . (int) $bgcf_index; ?>
				<div class="bgcf-form__check">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $bgcf_skill_id ); ?>"
						name="skills[]"
						value="<?php echo esc_attr( $bgcf_skill ); ?>"
					/>
					<label for="<?php echo esc_attr( $bgcf_skill_id ); ?>"><?php echo esc_html( $bgcf_skill ); ?></label>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<div class="bgcf-field bgcf-contact-form__wide">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-availability' ); ?>">
			<?php esc_html_e( 'When are you usually free?', 'bgcf' ); ?>
		</label>
		<input
			type="text"
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-availability' ); ?>"
			name="availability"
			placeholder="<?php esc_attr_e( 'e.g. weekday mornings, or one Saturday a month', 'bgcf' ); ?>"
		/>
	</div>

	<div class="bgcf-field bgcf-contact-form__wide">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-message' ); ?>">
			<?php esc_html_e( 'Tell us a little about yourself', 'bgcf' ); ?>
		</label>
		<textarea class="bgcf-field__input" id="<?php echo esc_attr( $bgcf_uid . '-message' ); ?>" name="message" rows="5"></textarea>
	</div>

	<div class="bgcf-field bgcf-contact-form__wide">
		<label class="bgcf-field__label" for="<?php echo esc_attr( $bgcf_uid . '-resume' ); ?>">
			<?php esc_html_e( 'Your CV', 'bgcf' ); ?>
		</label>
		<input
			type="file"
			class="bgcf-field__input"
			id="<?php echo esc_attr( $bgcf_uid . '-resume' ); ?>"
			name="resume"
			accept=".pdf,.doc,.docx,.odt"
		/>
		<p class="bgcf-field__help">
			<?php esc_html_e( 'Optional. PDF or Word, up to 5 MB. You are just as welcome without one.', 'bgcf' ); ?>
		</p>
	</div>

	<div class="bgcf-contact-form__wide">
		<div class="bgcf-form__check">
			<input type="checkbox" id="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>" name="consent" value="1" required />
			<label for="<?php echo esc_attr( $bgcf_uid . '-consent' ); ?>">
				<?php esc_html_e( 'I agree that my details may be stored so my application can be considered.', 'bgcf' ); ?>
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
				'text'    => bgcf_get_text( 'volunteer_submit', __( 'Send my application', 'bgcf' ) ),
				'variant' => 'primary',
				'size'    => 'lg',
				'type'    => 'submit',
				'icon'    => 'arrow-right',
			)
		);
		?>

		<p class="bgcf-form__status" role="status" aria-live="polite" data-bgcf-form-status></p>
	</div>
</form>
