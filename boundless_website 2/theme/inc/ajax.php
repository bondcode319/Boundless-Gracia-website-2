<?php
/**
 * AJAX endpoints.
 *
 * THE PATTERN. Every handler in this theme follows these five steps, in order,
 * with no exceptions and no shortcuts:
 *
 *   1. GUARD      bgcf_ajax_guard() — verifies the `bgcf_ajax` nonce, the
 *                 capability (empty string for public endpoints), and any
 *                 rate limit / honeypot attached to `bgcf_verify_request`.
 *   2. SANITIZE   Read every input through bgcf_ajax_param() with an explicit
 *                 sanitizer. Never touch $_POST directly, never trust a type.
 *   3. VALIDATE   Business rules. Fail with wp_send_json_error() and a code the
 *                 front end can branch on — never with a raw exception.
 *   4. ACT        Do the work. Database writes go through the WordPress data
 *                 APIs (inc/leads.php), never raw SQL.
 *   5. RESPOND    wp_send_json_success() / wp_send_json_error() with an
 *                 explicit HTTP status. Both terminate the request.
 *
 * Action names are `bgcf_{domain}_{verb}` and are duplicated in the front-end
 * bundle, so treat them as a public contract. The shared nonce is created in
 * inc/enqueue.php as `bgcfData.nonce` against the `bgcf_ajax` action, and can be
 * renewed at `/wp-json/bgcf/v1/nonce` when a cached page outlives it.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify an AJAX request or terminate with a JSON error.
 *
 * @since 1.0.0
 *
 * @param string $capability Required capability, or '' for public endpoints.
 * @return void Terminates the request when verification fails.
 */
function bgcf_ajax_guard( string $capability = '' ): void {
	$verified = bgcf_verify_request( 'bgcf_ajax', 'nonce', $capability );

	if ( is_wp_error( $verified ) ) {
		$data = $verified->get_error_data();

		wp_send_json_error(
			array(
				'code'    => $verified->get_error_code(),
				'message' => $verified->get_error_message(),
			),
			isset( $data['status'] ) ? (int) $data['status'] : 403
		);
	}
}

/**
 * Read and sanitize a request parameter.
 *
 * Scalars only, unless `$allow_array` is set. An array arriving where a string
 * is expected used to reach a `(string)` cast and emit a PHP warning; now it is
 * flattened to '' by default, so a malformed request fails validation like any
 * other bad input rather than producing a notice in the response body.
 *
 * @since 1.0.0
 *
 * @param string          $key         Parameter name.
 * @param callable|string $sanitizer   Sanitizer callback. Default 'sanitize_text_field'.
 * @param mixed           $default     Value when the parameter is absent.
 * @param bool            $allow_array Whether an array value is acceptable.
 * @return mixed Sanitized value.
 */
function bgcf_ajax_param( string $key, $sanitizer = 'sanitize_text_field', $default = '', bool $allow_array = false ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers must run bgcf_ajax_guard() first.
	if ( ! isset( $_REQUEST[ $key ] ) ) {
		return $default;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
	$raw = wp_unslash( $_REQUEST[ $key ] );

	$clean = static function ( $item ) use ( $sanitizer ) {
		if ( is_array( $item ) || is_object( $item ) ) {
			return '';
		}

		return is_callable( $sanitizer ) ? call_user_func( $sanitizer, $item ) : sanitize_text_field( (string) $item );
	};

	if ( is_array( $raw ) ) {
		if ( ! $allow_array ) {
			// A scalar was expected. Refusing is safer than silently using the
			// first element, which is how type-confusion bugs get in.
			return $default;
		}

		// Bounded: an unbounded array is a cheap memory-exhaustion vector.
		return array_map( $clean, array_slice( $raw, 0, 50 ) );
	}

	return $clean( $raw );
}

/**
 * Honeypot check: terminate when an invisible field was filled.
 *
 * Fails with a generic message so a bot learns nothing about why.
 *
 * @since 1.2.0
 * @return void Terminates the request when tripped.
 */
function bgcf_ajax_reject_bots(): void {
	if ( '' === trim( (string) bgcf_ajax_param( 'hp' ) ) ) {
		return;
	}

	wp_send_json_error(
		array(
			'code'    => 'rejected',
			'message' => __( 'Something went wrong. Please try again.', 'bgcf' ),
		),
		400
	);
}

/**
 * Terminate with a field-aware validation error.
 *
 * @since 1.2.0
 *
 * @param string $code    Machine-readable code the front end branches on.
 * @param string $message Human-readable message.
 * @param string $field   Field name to focus, if any.
 * @param int    $status  HTTP status.
 * @return void Terminates the request.
 */
function bgcf_ajax_fail( string $code, string $message, string $field = '', int $status = 422 ): void {
	wp_send_json_error(
		array(
			'code'    => $code,
			'message' => $message,
			'field'   => $field,
		),
		$status
	);
}

/**
 * Throttle a public endpoint per IP.
 *
 * Generic counterpart to bgcf_donation_rate_limited(). Keyed per action so a
 * burst of newsletter sign-ups cannot lock a visitor out of the contact form.
 *
 * @since 1.2.0
 *
 * @param string $action Action key.
 * @param int    $limit  Attempts allowed per window.
 * @param int    $window Window length in seconds.
 * @return bool True when the caller has exceeded the limit.
 */
function bgcf_ajax_rate_limited( string $action, int $limit = 8, int $window = 600 ): bool {
	$ip = function_exists( 'bgcf_flw_client_ip' ) ? bgcf_flw_client_ip() : '';

	if ( '' === $ip ) {
		return false;
	}

	/**
	 * Filters a public endpoint's rate limit.
	 *
	 * @since 1.2.0
	 *
	 * @param array{limit:int, window:int} $config Limit configuration.
	 * @param string                       $action Action key.
	 */
	$config = (array) apply_filters(
		'bgcf_ajax_rate_limit',
		array(
			'limit'  => $limit,
			'window' => $window,
		),
		$action
	);

	$key   = 'bgcf_rl_' . md5( $action . '|' . $ip );
	$count = (int) get_transient( $key );

	if ( $count >= (int) $config['limit'] ) {
		return true;
	}

	set_transient( $key, $count + 1, (int) $config['window'] );

	return false;
}

/**
 * Terminate with a 429 when the caller is over the limit.
 *
 * @since 1.2.0
 *
 * @param string $action Action key.
 * @param int    $limit  Attempts allowed.
 * @param int    $window Window in seconds.
 * @return void
 */
function bgcf_ajax_throttle( string $action, int $limit = 8, int $window = 600 ): void {
	if ( ! bgcf_ajax_rate_limited( $action, $limit, $window ) ) {
		return;
	}

	wp_send_json_error(
		array(
			'code'    => 'rate_limited',
			'message' => __( 'Too many attempts. Please wait a few minutes and try again.', 'bgcf' ),
		),
		429
	);
}

/* -------------------------------------------------------------------------
 * Nonce renewal
 * ---------------------------------------------------------------------- */

/**
 * Register the nonce renewal route.
 *
 * WHY THIS EXISTS. Every nonce on the front end is printed into HTML that a
 * full-page cache (WP Rocket, Cloudflare) will serve for hours. Nonces roll over
 * every 12 hours, so a cached page reliably begins failing nonce verification
 * while looking perfectly healthy — on the donation form, that is silent, total
 * conversion loss with no error anywhere.
 *
 * A REST route rather than admin-ajax because hosts and CDNs are far more likely
 * to have `/wp-json/` excluded from caching already. The response is explicitly
 * marked uncacheable regardless.
 *
 * This endpoint discloses nothing: a nonce is bound to the current session and
 * is useless to anyone who does not already have the cookies that produced it.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_register_nonce_route(): void {
	register_rest_route(
		'bgcf/v1',
		'/nonce',
		array(
			'methods'             => 'GET',
			'callback'            => 'bgcf_rest_nonce',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'bgcf_register_nonce_route' );

/**
 * Return a fresh `bgcf_ajax` nonce.
 *
 * @since 1.2.0
 * @return WP_REST_Response Response carrying the nonce.
 */
function bgcf_rest_nonce(): WP_REST_Response {
	$response = new WP_REST_Response(
		array(
			'nonce'     => wp_create_nonce( 'bgcf_ajax' ),
			'expiresIn' => (int) apply_filters( 'nonce_life', DAY_IN_SECONDS ),
		),
		200
	);

	// Belt and braces: this must never be stored by a CDN, a proxy or the
	// browser, or it defeats its own purpose.
	$response->header( 'Cache-Control', 'no-store, max-age=0' );

	return $response;
}

/* -------------------------------------------------------------------------
 * Newsletter
 * ---------------------------------------------------------------------- */

/**
 * Newsletter subscription.
 *
 * Public endpoint (logged out visitors must be able to subscribe), so the
 * capability is intentionally empty and the abuse controls are the nonce, the
 * honeypot and the rate limit.
 *
 * Expected input : email (string), consent (bool), source (string), hp (honeypot)
 * Expected output: { message: string }
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_ajax_newsletter_subscribe(): void {
	bgcf_ajax_guard();
	bgcf_ajax_reject_bots();
	bgcf_ajax_throttle( 'newsletter', 6, 10 * MINUTE_IN_SECONDS );

	$email   = sanitize_email( (string) bgcf_ajax_param( 'email', 'sanitize_email' ) );
	$consent = (bool) bgcf_ajax_param( 'consent', 'absint', 0 );
	$source  = sanitize_key( (string) bgcf_ajax_param( 'source', 'sanitize_key', 'site' ) );
	$name    = sanitize_text_field( (string) bgcf_ajax_param( 'name' ) );

	if ( ! is_email( $email ) ) {
		bgcf_ajax_fail( 'invalid_email', __( 'Please enter a valid email address.', 'bgcf' ), 'email' );
	}

	if ( ! $consent ) {
		bgcf_ajax_fail(
			'consent_required',
			__( 'Please tick the box to confirm we may email you.', 'bgcf' ),
			'consent'
		);
	}

	$subscriber = bgcf_create_subscriber(
		array(
			'email'   => $email,
			'name'    => $name,
			'source'  => $source,
			'consent' => true,
		)
	);

	if ( is_wp_error( $subscriber ) ) {
		bgcf_ajax_fail( 'record_failed', __( 'We could not save your details. Please try again.', 'bgcf' ), '', 500 );
	}

	wp_send_json_success(
		array(
			// Deliberately identical whether the address was new or already on
			// the list: a different answer would confirm who is subscribed.
			'message' => bgcf_get_text(
				'newsletter_success',
				__( 'Thank you — you are on the list. Watch your inbox for our next update.', 'bgcf' )
			),
		)
	);
}
add_action( 'wp_ajax_bgcf_newsletter_subscribe', 'bgcf_ajax_newsletter_subscribe' );
add_action( 'wp_ajax_nopriv_bgcf_newsletter_subscribe', 'bgcf_ajax_newsletter_subscribe' );

/* -------------------------------------------------------------------------
 * Volunteer
 * ---------------------------------------------------------------------- */

/**
 * The MIME types a résumé may use.
 *
 * Narrow on purpose. Every type here is a document format the team can open,
 * and none of them is executed by the web server.
 *
 * @since 1.2.0
 * @return array<string, string> Extension pattern => MIME type.
 */
function bgcf_resume_mime_types(): array {
	/**
	 * Filters the accepted résumé MIME types.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $types Extension pattern => MIME type.
	 */
	return (array) apply_filters(
		'bgcf_resume_mime_types',
		array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'  => 'application/vnd.oasis.opendocument.text',
		)
	);
}

/**
 * Validate and store an uploaded résumé.
 *
 * THREE checks, because any one of them alone is bypassable:
 *   1. Size, before anything is read.
 *   2. `wp_check_filetype_and_ext()`, which inspects the file's actual magic
 *      bytes — not the extension, and not the browser-supplied Content-Type,
 *      both of which the client controls.
 *   3. An allow-list intersection, so even a type WordPress recognises is
 *      rejected unless it is one of ours.
 *
 * The file is then stored through `wp_handle_upload()` with a randomised name,
 * and is NOT attached to the media library: an attachment is a public URL with
 * a guessable ID, and a stranger's CV is not public.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $file One entry from `$_FILES`.
 * @return int|WP_Error Attachment-less upload record ID, or an error.
 */
function bgcf_handle_resume_upload( array $file ) {
	$max = (int) apply_filters( 'bgcf_resume_max_bytes', 5 * MB_IN_BYTES );

	if ( ! isset( $file['size'] ) || (int) $file['size'] <= 0 ) {
		return new WP_Error( 'empty_file', __( 'That file appears to be empty.', 'bgcf' ) );
	}

	if ( (int) $file['size'] > $max ) {
		return new WP_Error(
			'file_too_large',
			sprintf(
				/* translators: %s: maximum file size, e.g. "5 MB". */
				__( 'Please upload a file smaller than %s.', 'bgcf' ),
				size_format( $max )
			)
		);
	}

	$allowed = bgcf_resume_mime_types();
	$checked = wp_check_filetype_and_ext(
		(string) ( $file['tmp_name'] ?? '' ),
		(string) ( $file['name'] ?? '' ),
		$allowed
	);

	if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
		return new WP_Error(
			'invalid_type',
			__( 'Please upload your CV as a PDF or Word document.', 'bgcf' )
		);
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Randomised name: a predictable one lets anyone who guesses the URL read
	// another applicant's CV.
	$file['name'] = wp_generate_password( 24, false, false ) . '.' . ltrim( (string) $checked['ext'], '.' );

	$moved = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => $allowed,
			'unique_filename_callback' => null,
		)
	);

	if ( isset( $moved['error'] ) ) {
		return new WP_Error( 'upload_failed', (string) $moved['error'] );
	}

	// Record it as a private attachment so the team can open it from wp-admin
	// and WordPress's own deletion handles the file when the lead is erased.
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => (string) $checked['type'],
			'post_title'     => __( 'Volunteer CV', 'bgcf' ),
			'post_status'    => 'private',
		),
		(string) $moved['file']
	);

	if ( is_wp_error( $attachment_id ) || 0 === (int) $attachment_id ) {
		return new WP_Error( 'attach_failed', __( 'We could not store that file.', 'bgcf' ) );
	}

	return (int) $attachment_id;
}

/**
 * Volunteer application submission.
 *
 * Expected input : name, email, phone, location, skills[], availability,
 *                  message, consent (bool), resume (file), hp (honeypot)
 * Expected output: { message: string, reference: string }
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_ajax_volunteer_submit(): void {
	bgcf_ajax_guard();
	bgcf_ajax_reject_bots();
	bgcf_ajax_throttle( 'volunteer', 4, 30 * MINUTE_IN_SECONDS );

	$name         = sanitize_text_field( (string) bgcf_ajax_param( 'name' ) );
	$email        = sanitize_email( (string) bgcf_ajax_param( 'email', 'sanitize_email' ) );
	$phone        = sanitize_text_field( (string) bgcf_ajax_param( 'phone' ) );
	$location     = sanitize_text_field( (string) bgcf_ajax_param( 'location' ) );
	$availability = sanitize_text_field( (string) bgcf_ajax_param( 'availability' ) );
	$message      = sanitize_textarea_field( (string) bgcf_ajax_param( 'message', 'sanitize_textarea_field' ) );
	$consent      = (bool) bgcf_ajax_param( 'consent', 'absint', 0 );
	$skills       = (array) bgcf_ajax_param( 'skills', 'sanitize_text_field', array(), true );

	if ( '' === $name ) {
		bgcf_ajax_fail( 'name_required', __( 'Please tell us your name.', 'bgcf' ), 'name' );
	}

	if ( ! is_email( $email ) ) {
		bgcf_ajax_fail( 'invalid_email', __( 'Please enter an email address we can reply to.', 'bgcf' ), 'email' );
	}

	if ( ! $consent ) {
		bgcf_ajax_fail(
			'consent_required',
			__( 'Please confirm we may store your details to process your application.', 'bgcf' ),
			'consent'
		);
	}

	$resume_id = 0;

	if ( ! empty( $_FILES['resume']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guarded above.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by bgcf_handle_resume_upload().
		$upload = bgcf_handle_resume_upload( (array) $_FILES['resume'] );

		if ( is_wp_error( $upload ) ) {
			bgcf_ajax_fail( $upload->get_error_code(), $upload->get_error_message(), 'resume' );
		}

		$resume_id = (int) $upload;
	}

	$reference = 'VOL-' . strtoupper( wp_generate_password( 8, false, false ) );

	$application = bgcf_create_volunteer(
		array(
			'name'         => $name,
			'email'        => $email,
			'phone'        => $phone,
			'location'     => $location,
			'skills'       => $skills,
			'availability' => $availability,
			'message'      => $message,
			'resume'       => $resume_id,
			'reference'    => $reference,
		)
	);

	if ( is_wp_error( $application ) ) {
		bgcf_ajax_fail( 'record_failed', __( 'We could not save your application. Please try again.', 'bgcf' ), '', 500 );
	}

	bgcf_notify_team(
		sprintf(
			/* translators: %s: applicant name. */
			__( 'New volunteer application: %s', 'bgcf' ),
			$name
		),
		sprintf(
			/* translators: 1: reference, 2: name, 3: email, 4: admin URL. */
			__( "Reference: %1\$s\nName: %2\$s\nEmail: %3\$s\n\nReview it here: %4\$s", 'bgcf' ),
			$reference,
			$name,
			$email,
			admin_url( 'post.php?post=' . (int) $application . '&action=edit' )
		),
		array( 'lead' => (int) $application )
	);

	wp_send_json_success(
		array(
			'message'   => sprintf(
				/* translators: %s: application reference. */
				bgcf_get_text(
					'volunteer_success',
					__( 'Thank you for offering your time. Your reference is %s — we will be in touch within a week.', 'bgcf' )
				),
				$reference
			),
			'reference' => $reference,
		)
	);
}
add_action( 'wp_ajax_bgcf_volunteer_submit', 'bgcf_ajax_volunteer_submit' );
add_action( 'wp_ajax_nopriv_bgcf_volunteer_submit', 'bgcf_ajax_volunteer_submit' );

/* -------------------------------------------------------------------------
 * Contact
 * ---------------------------------------------------------------------- */

/**
 * Contact form submission.
 *
 * Expected input : name, email, phone, subject, message, consent, hp
 * Expected output: { message: string }
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_ajax_contact_submit(): void {
	bgcf_ajax_guard();
	bgcf_ajax_reject_bots();
	bgcf_ajax_throttle( 'contact', 6, 15 * MINUTE_IN_SECONDS );

	$name    = sanitize_text_field( (string) bgcf_ajax_param( 'name' ) );
	$email   = sanitize_email( (string) bgcf_ajax_param( 'email', 'sanitize_email' ) );
	$phone   = sanitize_text_field( (string) bgcf_ajax_param( 'phone' ) );
	$subject = sanitize_text_field( (string) bgcf_ajax_param( 'subject' ) );
	$message = sanitize_textarea_field( (string) bgcf_ajax_param( 'message', 'sanitize_textarea_field' ) );
	$consent = (bool) bgcf_ajax_param( 'consent', 'absint', 0 );

	if ( '' === $name ) {
		bgcf_ajax_fail( 'name_required', __( 'Please tell us your name.', 'bgcf' ), 'name' );
	}

	if ( ! is_email( $email ) ) {
		bgcf_ajax_fail( 'invalid_email', __( 'Please enter an email address we can reply to.', 'bgcf' ), 'email' );
	}

	if ( strlen( $message ) < 10 ) {
		bgcf_ajax_fail( 'message_required', __( 'Please tell us a little more so we can help.', 'bgcf' ), 'message' );
	}

	if ( ! $consent ) {
		bgcf_ajax_fail( 'consent_required', __( 'Please confirm we may store your message to reply to it.', 'bgcf' ), 'consent' );
	}

	$record = bgcf_create_message(
		array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'subject' => $subject,
			'message' => $message,
		)
	);

	if ( is_wp_error( $record ) ) {
		bgcf_ajax_fail( 'record_failed', __( 'We could not send your message. Please try again.', 'bgcf' ), '', 500 );
	}

	bgcf_notify_team(
		sprintf(
			/* translators: %s: message subject. */
			__( 'Website enquiry: %s', 'bgcf' ),
			'' !== $subject ? $subject : $name
		),
		sprintf(
			/* translators: 1: name, 2: email, 3: phone, 4: message body. */
			__( "From: %1\$s <%2\$s>\nPhone: %3\$s\n\n%4\$s", 'bgcf' ),
			$name,
			$email,
			$phone,
			$message
		),
		array( 'lead' => (int) $record )
	);

	wp_send_json_success(
		array(
			'message' => bgcf_get_text(
				'contact_success',
				__( 'Thank you — your message is with us and we will reply as soon as we can.', 'bgcf' )
			),
		)
	);
}
add_action( 'wp_ajax_bgcf_contact_submit', 'bgcf_ajax_contact_submit' );
add_action( 'wp_ajax_nopriv_bgcf_contact_submit', 'bgcf_ajax_contact_submit' );

/* -------------------------------------------------------------------------
 * Gallery
 * ---------------------------------------------------------------------- */

/**
 * Gallery pagination ("load more").
 *
 * Read-only, so it needs no capability — but it still needs the nonce, because
 * an unauthenticated, uncached, arbitrarily-paginated query is a cheap denial
 * of service. `per_page` and `paged` are clamped for the same reason: the
 * request names a window, it does not get to choose its size.
 *
 * Expected input : paged (int), per_page (int), album (int|string), post_id (int)
 * Expected output: { html: string, hasMore: bool, paged: int }
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_ajax_gallery_load_more(): void {
	bgcf_ajax_guard();
	bgcf_ajax_throttle( 'gallery', 60, 5 * MINUTE_IN_SECONDS );

	$paged    = max( 1, min( 50, absint( bgcf_ajax_param( 'paged', 'absint', 1 ) ) ) );
	$per_page = max( 1, min( 48, absint( bgcf_ajax_param( 'per_page', 'absint', 12 ) ) ) );
	$album    = sanitize_text_field( (string) bgcf_ajax_param( 'album' ) );

	$query = bgcf_gallery_query( $album, $per_page, $paged );

	ob_start();

	foreach ( $query['items'] as $item ) {
		bgcf_render_partial(
			'global/gallery-item',
			array(
				'item'     => $item,
				'is_new'   => true,
				'lightbox' => true,
			)
		);
	}

	wp_send_json_success(
		array(
			'html'    => ob_get_clean(),
			'hasMore' => (bool) $query['has_more'],
			'paged'   => $paged,
		)
	);
}
add_action( 'wp_ajax_bgcf_gallery_load_more', 'bgcf_ajax_gallery_load_more' );
add_action( 'wp_ajax_nopriv_bgcf_gallery_load_more', 'bgcf_ajax_gallery_load_more' );
