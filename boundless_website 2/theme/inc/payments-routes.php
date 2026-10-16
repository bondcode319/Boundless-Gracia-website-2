<?php
/**
 * Donation endpoints: AJAX, webhook, return URL, reconciliation.
 *
 * SINGLE RESPONSIBILITY: the public surface of the payment flow. Everything here
 * is reachable by a stranger, so everything here is guarded, sanitized and rate
 * limited. The gateway calls live in inc/payments.php; the record and fulfilment
 * logic in inc/donations.php.
 *
 * THREE SIGNALS, ONE TRUTH. A donation can be confirmed by:
 *   - the webhook            (fast, but may never arrive or may be replayed)
 *   - the return URL         (only fires if the donor comes back to the browser)
 *   - the reconciliation job (slow, but always fires)
 * None of them is trusted on its own. Each one triggers the same server-side
 * verify call, and fulfilment is idempotent, so all three firing for one payment
 * produces one receipt.
 *
 * Endpoint contract (duplicated in assets/js/src/donation-form.js — treat it as
 * public API):
 *   POST admin-ajax.php?action=bgcf_donation_start
 *        -> { redirect, reference, token, mode } | error { code, message }
 *   POST admin-ajax.php?action=bgcf_donation_status
 *        -> { status, label, amount }
 *   POST /wp-json/bgcf/v1/flutterwave/webhook   (Flutterwave only)
 *   GET  /?bgcf-payment-return=1&tx_ref=…       (browser return from checkout)
 *
 * @package Bgcf
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST namespace for payment routes.
 *
 * @since 1.1.0
 */
define( 'BGCF_PAYMENTS_REST_NAMESPACE', 'bgcf/v1' );

/**
 * Cron hook that reconciles pending donations.
 *
 * @since 1.1.0
 */
define( 'BGCF_DONATION_CRON_HOOK', 'bgcf_donations_reconcile' );

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

/**
 * The URL Flutterwave returns the donor to after checkout.
 *
 * Deliberately a query arg on the home URL rather than a pretty permalink: it
 * needs no rewrite rules, so it cannot break on a flushed permalink structure —
 * and a payment return URL that 404s after a deploy is a lost donation.
 *
 * @since 1.1.0
 * @return string Absolute URL.
 */
function bgcf_donation_return_url(): string {
	return add_query_arg( 'bgcf-payment-return', '1', home_url( '/' ) );
}

/**
 * The webhook URL to paste into the Flutterwave dashboard.
 *
 * @since 1.1.0
 * @return string Absolute URL.
 */
function bgcf_donation_webhook_url(): string {
	return rest_url( BGCF_PAYMENTS_REST_NAMESPACE . '/flutterwave/webhook' );
}

/**
 * Where a donor lands once we know the outcome.
 *
 * @since 1.1.0
 *
 * @param string $token  Donation public token.
 * @param string $status Resulting status.
 * @return string Absolute URL.
 */
function bgcf_donation_result_url( string $token, string $status ): string {
	$page_id = absint( bgcf_donation_setting( 'thank_you_page', 0 ) );
	$base    = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );

	return add_query_arg(
		array(
			'donation' => rawurlencode( $token ),
			'status'   => rawurlencode( $status ),
		),
		(string) $base
	);
}

/* -------------------------------------------------------------------------
 * AJAX: start a donation
 * ---------------------------------------------------------------------- */

/**
 * Create a donation and hand the browser somewhere to send the donor.
 *
 * Follows THE PATTERN from inc/ajax.php: guard, sanitize, validate, act, respond.
 * Nothing the browser sends is trusted — not the amount (clamped against the
 * settings), not the reference (generated here), not the currency (fixed).
 *
 * @since 1.1.0
 * @return void Terminates the request.
 */
function bgcf_ajax_donation_start(): void {
	bgcf_ajax_guard();

	// Honeypot: a real donor never fills a field they cannot see. Fails with a
	// generic error so a bot learns nothing.
	bgcf_ajax_reject_bots();

	/*
	 * IDEMPOTENCY. A donor with two tabs open, or a flaky connection that
	 * retries the POST, used to create two donation records and two mobile-money
	 * prompts: the client-side busy flag cannot see the other tab, and the
	 * references genuinely differ so Flutterwave's own duplicate protection does
	 * not apply either. The browser now sends a key it generates once per
	 * attempt; if we have already answered that key, we replay the original
	 * answer instead of charging again.
	 */
	$idempotency_key = sanitize_text_field( (string) bgcf_ajax_param( 'idempotency_key' ) );
	$replay          = bgcf_donation_replay( $idempotency_key );

	if ( is_array( $replay ) ) {
		wp_send_json_success( $replay );
	}

	if ( bgcf_donation_rate_limited() ) {
		wp_send_json_error(
			array(
				'code'    => 'rate_limited',
				'message' => __( 'Too many attempts. Please wait a few minutes and try again.', 'bgcf' ),
			),
			429
		);
	}

	if ( ! bgcf_flw_is_configured() ) {
		wp_send_json_error(
			array(
				'code'    => 'unavailable',
				'message' => __( 'Online giving is temporarily unavailable. Please try again later.', 'bgcf' ),
			),
			503
		);
	}

	/* 2. SANITIZE ------------------------------------------------------- */
	$amount    = absint( bgcf_ajax_param( 'amount', 'absint', 0 ) );
	$method    = sanitize_key( (string) bgcf_ajax_param( 'method' ) );
	$network   = strtoupper( sanitize_text_field( (string) bgcf_ajax_param( 'network' ) ) );
	$name      = sanitize_text_field( (string) bgcf_ajax_param( 'name' ) );
	$email     = sanitize_email( (string) bgcf_ajax_param( 'email' ) );
	$phone_raw = sanitize_text_field( (string) bgcf_ajax_param( 'phone' ) );
	$campaign  = absint( bgcf_ajax_param( 'campaign', 'absint', 0 ) );
	$message   = sanitize_textarea_field( (string) bgcf_ajax_param( 'message' ) );
	$anonymous = (bool) bgcf_ajax_param( 'anonymous', 'absint', 0 );
	$cover_fee = (bool) bgcf_ajax_param( 'cover_fee', 'absint', 0 );
	$consent   = (bool) bgcf_ajax_param( 'consent', 'absint', 0 );

	/* 3. VALIDATE ------------------------------------------------------- */
	$methods = bgcf_donation_methods();

	if ( ! isset( $methods[ $method ] ) ) {
		bgcf_donation_fail( 'invalid_method', __( 'Please choose how you would like to give.', 'bgcf' ) );
	}

	if ( ! is_email( $email ) ) {
		bgcf_donation_fail( 'invalid_email', __( 'Please enter an email address we can send your receipt to.', 'bgcf' ), 'email' );
	}

	if ( bgcf_donation_setting( 'consent_required', true ) && ! $consent ) {
		bgcf_donation_fail(
			'consent_required',
			__( 'Please confirm we may store your details to process your gift and send your receipt.', 'bgcf' ),
			'consent'
		);
	}

	$min = absint( bgcf_donation_setting( 'min_amount', 1000 ) );
	$max = absint( bgcf_donation_setting( 'max_amount', 20000000 ) );

	if ( $amount < $min || $amount > $max ) {
		bgcf_donation_fail(
			'invalid_amount',
			sprintf(
				/* translators: 1: minimum amount, 2: maximum amount. */
				__( 'Please enter an amount between %1$s and %2$s.', 'bgcf' ),
				bgcf_donation_format_amount( $min ),
				bgcf_donation_format_amount( $max )
			),
			'amount'
		);
	}

	$phone = '';

	if ( 'mobile_money' === $method ) {
		$phone = bgcf_flw_normalize_msisdn( $phone_raw );

		if ( '' === $phone ) {
			bgcf_donation_fail(
				'invalid_phone',
				__( 'Please enter a Ugandan mobile money number, for example 0772 123 456.', 'bgcf' ),
				'phone'
			);
		}

		if ( ! array_key_exists( $network, bgcf_flw_networks() ) ) {
			// The donor left it on "auto": infer from the number, and only give up
			// if the prefix is genuinely unfamiliar.
			$network = bgcf_flw_guess_network( $phone );
		}

		if ( ! array_key_exists( $network, bgcf_flw_networks() ) ) {
			bgcf_donation_fail(
				'invalid_network',
				__( 'Please choose whether this is an MTN or an Airtel number.', 'bgcf' ),
				'network'
			);
		}
	} elseif ( '' !== $phone_raw ) {
		// Card giving: the phone is optional metadata, not a rail.
		$phone = bgcf_flw_normalize_msisdn( $phone_raw );
	}

	/* 4. ACT ------------------------------------------------------------ */

	/*
	 * Fee cover. The browser shows a running total, but the number charged is
	 * computed HERE from the configured percentage — otherwise the fee is a
	 * value the client supplies, and "cover the fee" becomes "name your own
	 * total". `amount` stays the gift (what the campaign raised and what the
	 * receipt reports as the donation); `charged` is what leaves the donor's
	 * account.
	 */
	$fee     = $cover_fee ? bgcf_fee_for_amount( $amount ) : 0;
	$charged = $amount + $fee;

	if ( $charged > $max ) {
		// Covering the fee must never push a donor over the ceiling the gateway
		// will accept; drop the fee rather than failing a valid gift.
		$fee     = 0;
		$charged = $amount;
	}

	$donation_id = bgcf_donation_create(
		array(
			'amount'    => $amount,
			'fee'       => $fee,
			'charged'   => $charged,
			'currency'  => bgcf_donation_currency(),
			'method'    => $method,
			'network'   => 'mobile_money' === $method ? $network : '',
			'name'      => $name,
			'email'     => $email,
			'phone'     => $phone,
			'anonymous' => $anonymous,
			'campaign'  => $campaign,
			'message'   => $message,
			'consent'   => $consent,
		)
	);

	if ( is_wp_error( $donation_id ) ) {
		bgcf_donation_fail( 'record_failed', __( 'We could not start your donation. Please try again.', 'bgcf' ) );
	}

	$tx_ref = (string) bgcf_donation_get( (int) $donation_id, 'tx_ref' );
	$token  = (string) bgcf_donation_get( (int) $donation_id, 'public_token' );

	$meta = array(
		'donation_id' => (int) $donation_id,
		'campaign'    => $campaign,
		'site'        => home_url( '/' ),
	);

	if ( 'mobile_money' === $method ) {
		$response = bgcf_flw_charge_mobile_money(
			array(
				'amount'       => $charged,
				'currency'     => bgcf_donation_currency(),
				'email'        => $email,
				'phone_number' => $phone,
				'network'      => $network,
				'tx_ref'       => $tx_ref,
				'fullname'     => $name,
				'redirect_url' => bgcf_donation_return_url(),
				'meta'         => $meta,
			)
		);
	} else {
		$response = bgcf_flw_create_checkout(
			array(
				'amount'          => $charged,
				'currency'        => bgcf_donation_currency(),
				'email'           => $email,
				'name'            => $name,
				'phone'           => $phone,
				'tx_ref'          => $tx_ref,
				'payment_options' => bgcf_donation_payment_options(),
				'redirect_url'    => bgcf_donation_return_url(),
				'title'           => (string) bgcf_donation_setting( 'checkout_title', get_bloginfo( 'name' ) ),
				'description'     => sprintf(
					/* translators: %s: formatted amount. */
					__( 'Donation of %s', 'bgcf' ),
					bgcf_donation_format_amount( $charged )
				),
				'logo'            => bgcf_donation_checkout_logo(),
				'meta'            => $meta,
			)
		);
	}

	if ( is_wp_error( $response ) ) {
		bgcf_donation_set_status( (int) $donation_id, 'failed', array( 'reason' => $response->get_error_code() ) );

		wp_send_json_error(
			array(
				'code'    => 'gateway_error',
				// The provider's own message is safe to surface (it says things like
				// "amount below minimum"), and it is the difference between a donor
				// retrying and a donor giving up.
				'message' => $response->get_error_message(),
			),
			502
		);
	}

	// Remember the provider's id immediately — it makes every later requery a
	// direct lookup instead of a reference search.
	if ( isset( $response['id'] ) ) {
		update_post_meta( (int) $donation_id, '_bgcf_flw_id', absint( $response['id'] ) );
	}

	$redirect = bgcf_donation_extract_redirect( $response );

	if ( '' === $redirect ) {
		bgcf_flw_log( 'no redirect in charge response', array( 'donation' => (int) $donation_id ) );

		wp_send_json_error(
			array(
				'code'    => 'no_redirect',
				'message' => __( 'The payment provider did not return a payment page. Please try again.', 'bgcf' ),
			),
			502
		);
	}

	/* 5. RESPOND -------------------------------------------------------- */
	$payload = array(
		'redirect'  => esc_url_raw( $redirect ),
		'reference' => $tx_ref,
		'token'     => $token,
		'method'    => $method,
		'mode'      => bgcf_flw_mode(),
		'message'   => 'mobile_money' === $method
			? __( 'Approve the payment prompt on your phone to complete your gift.', 'bgcf' )
			: __( 'Taking you to our secure payment page…', 'bgcf' ),
	);

	// Remember the answer so a duplicate submit of the same attempt is replayed
	// rather than charged again.
	bgcf_donation_remember_replay( $idempotency_key, $payload );

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_bgcf_donation_start', 'bgcf_ajax_donation_start' );
add_action( 'wp_ajax_nopriv_bgcf_donation_start', 'bgcf_ajax_donation_start' );

/**
 * AJAX: report the current status of a donation.
 *
 * Keyed on the opaque public token, never on the transaction reference, so
 * knowing (or guessing) a reference reveals nothing. The response carries no
 * donor PII — only what the page needs to stop spinning.
 *
 * @since 1.1.0
 * @return void Terminates the request.
 */
function bgcf_ajax_donation_status(): void {
	bgcf_ajax_guard();

	$token       = sanitize_text_field( (string) bgcf_ajax_param( 'token' ) );
	$donation_id = bgcf_donation_find_by_token( $token );

	if ( 0 === $donation_id ) {
		wp_send_json_error(
			array(
				'code'    => 'not_found',
				'message' => __( 'We could not find that donation.', 'bgcf' ),
			),
			404
		);
	}

	$status = bgcf_donation_status( $donation_id );

	// Poll the provider at most once every 10 seconds per donation, however
	// often the browser asks. The webhook is the primary signal; this is a
	// convenience for the donor watching the page.
	if ( 'pending' === $status ) {
		$last = absint( bgcf_donation_get( $donation_id, 'requeried_at', 0 ) );

		if ( ( time() - $last ) >= 10 ) {
			$result = bgcf_donation_requery( $donation_id );
			$status = is_wp_error( $result ) ? $status : (string) $result;
		}
	}

	$labels = bgcf_donation_statuses();

	wp_send_json_success(
		array(
			'status' => $status,
			'label'  => (string) ( $labels[ $status ] ?? $status ),
			'amount' => bgcf_donation_format_amount(
				(int) bgcf_donation_get( $donation_id, 'amount', 0 ),
				(string) bgcf_donation_get( $donation_id, 'currency', '' )
			),
			'url'    => 'pending' === $status ? '' : esc_url_raw( bgcf_donation_result_url( $token, $status ) ),
		)
	);
}
add_action( 'wp_ajax_bgcf_donation_status', 'bgcf_ajax_donation_status' );
add_action( 'wp_ajax_nopriv_bgcf_donation_status', 'bgcf_ajax_donation_status' );

/**
 * Terminate an AJAX request with a field-aware validation error.
 *
 * @since 1.1.0
 *
 * @param string $code    Machine-readable code the front end branches on.
 * @param string $message Human-readable message.
 * @param string $field   Field name to focus, if any.
 * @return void Terminates the request.
 */
function bgcf_donation_fail( string $code, string $message, string $field = '' ): void {
	wp_send_json_error(
		array(
			'code'    => $code,
			'message' => $message,
			'field'   => $field,
		),
		422
	);
}

/**
 * The cache key holding a remembered response for an idempotency key.
 *
 * Scoped by the visitor's session as well as the key, so one donor's key can
 * never return another donor's redirect. The key alone is client-supplied and
 * therefore guessable; the pairing is not.
 *
 * @since 1.2.0
 *
 * @param string $key Client-supplied idempotency key.
 * @return string Transient name, or '' when the key is unusable.
 */
function bgcf_donation_replay_key( string $key ): string {
	$key = trim( $key );

	// Length-bounded: an arbitrarily long key becomes an arbitrarily long cache
	// write, and 16 characters of entropy is already far more than one donor
	// needs to distinguish their own attempts.
	if ( strlen( $key ) < 8 || strlen( $key ) > 128 ) {
		return '';
	}

	$session = function_exists( 'bgcf_flw_client_ip' ) ? bgcf_flw_client_ip() : '';

	return 'bgcf_idem_' . md5( $key . '|' . $session );
}

/**
 * A previous answer for this idempotency key, if we have one.
 *
 * @since 1.2.0
 *
 * @param string $key Idempotency key.
 * @return array<string, mixed>|null The original response payload, or null.
 */
function bgcf_donation_replay( string $key ): ?array {
	$name = bgcf_donation_replay_key( $key );

	if ( '' === $name ) {
		return null;
	}

	$stored = get_transient( $name );

	return is_array( $stored ) ? $stored : null;
}

/**
 * Remember the answer given for an idempotency key.
 *
 * Fifteen minutes: long enough to cover a double submit, a browser retry or a
 * donor pressing back and forward, and short enough that a donor who genuinely
 * wants to give twice in one sitting is not blocked. A genuinely new gift uses
 * a new key, which the front end generates per attempt.
 *
 * @since 1.2.0
 *
 * @param string               $key     Idempotency key.
 * @param array<string, mixed> $payload Response payload to replay.
 * @return void
 */
function bgcf_donation_remember_replay( string $key, array $payload ): void {
	$name = bgcf_donation_replay_key( $key );

	if ( '' === $name ) {
		return;
	}

	set_transient( $name, $payload, 15 * MINUTE_IN_SECONDS );
}

/**
 * Throttle donation attempts per IP.
 *
 * Generous by design — a donor retrying a failed mobile money prompt three times
 * is normal behaviour, a script hammering the charge endpoint is not.
 *
 * @since 1.1.0
 * @return bool True when the caller has exceeded the limit.
 */
function bgcf_donation_rate_limited(): bool {
	$ip = bgcf_flw_client_ip();

	if ( '' === $ip ) {
		return false;
	}

	/**
	 * Filters the donation rate limit.
	 *
	 * @since 1.1.0
	 *
	 * @param array{limit:int, window:int} $config Attempts allowed per window (seconds).
	 */
	$config = (array) apply_filters(
		'bgcf_donation_rate_limit',
		array(
			'limit'  => 12,
			'window' => 15 * MINUTE_IN_SECONDS,
		)
	);

	$key   = 'bgcf_don_rl_' . md5( $ip );
	$count = (int) get_transient( $key );

	if ( $count >= (int) $config['limit'] ) {
		return true;
	}

	set_transient( $key, $count + 1, (int) $config['window'] );

	return false;
}

/**
 * The `payment_options` string sent to hosted checkout.
 *
 * In UGX this is `card` plus `mobilemoneyuganda` and nothing else — see
 * inc/payments.php for why.
 *
 * @since 1.1.0
 * @return string Comma-separated option list.
 */
function bgcf_donation_payment_options(): string {
	$options = array( 'card' );

	if ( 'UGX' === bgcf_donation_currency() && bgcf_donation_setting( 'enable_mobile', true ) ) {
		$options[] = 'mobilemoneyuganda';
	}

	/**
	 * Filters the hosted checkout payment options.
	 *
	 * @since 1.1.0
	 *
	 * @param string[] $options Flutterwave payment option keys.
	 */
	return implode( ',', (array) apply_filters( 'bgcf_donation_payment_options', $options ) );
}

/**
 * Absolute logo URL for the hosted checkout page.
 *
 * @since 1.1.0
 * @return string URL, or ''.
 */
function bgcf_donation_checkout_logo(): string {
	$logo_id = (int) get_theme_mod( 'custom_logo', 0 );

	if ( $logo_id > 0 ) {
		$src = wp_get_attachment_image_url( $logo_id, 'medium' );

		if ( is_string( $src ) ) {
			return $src;
		}
	}

	return '';
}

/**
 * Pull the authorisation URL out of a charge or checkout response.
 *
 * Mobile money answers with `meta.authorization.redirect`; hosted checkout with
 * `data.link`. One helper so callers do not branch on response shape.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $response Decoded response data.
 * @return string URL, or ''.
 */
function bgcf_donation_extract_redirect( array $response ): string {
	if ( ! empty( $response['link'] ) && is_string( $response['link'] ) ) {
		return $response['link'];
	}

	$redirect = $response['meta']['authorization']['redirect'] ?? '';

	if ( is_string( $redirect ) && '' !== $redirect ) {
		return $redirect;
	}

	// Some charge responses nest the envelope differently; this is the last
	// documented shape before we give up and let the caller error.
	$redirect = $response['authorization']['redirect'] ?? '';

	return is_string( $redirect ) ? $redirect : '';
}

/* -------------------------------------------------------------------------
 * Return URL
 * ---------------------------------------------------------------------- */

/**
 * Handle the donor's return from Flutterwave.
 *
 * The query string here is attacker-controlled — `?status=successful` costs
 * nothing to type. It is used for exactly one thing: knowing which donation to
 * ask the API about. The answer comes from bgcf_donation_requery(), which reads
 * only stored values.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donation_handle_return(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Third-party redirect; authenticity comes from the server-side verify below, not from a nonce.
	if ( ! isset( $_GET['bgcf-payment-return'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
	$tx_ref      = isset( $_GET['tx_ref'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tx_ref'] ) ) : '';
	$donation_id = bgcf_donation_find_by_tx_ref( $tx_ref );

	if ( 0 === $donation_id ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$status = bgcf_donation_status( $donation_id );

	if ( 'pending' === $status ) {
		$result = bgcf_donation_requery( $donation_id );
		$status = is_wp_error( $result ) ? $status : (string) $result;
	}

	wp_safe_redirect(
		bgcf_donation_result_url( (string) bgcf_donation_get( $donation_id, 'public_token' ), $status )
	);
	exit;
}
add_action( 'template_redirect', 'bgcf_donation_handle_return', 1 );

/**
 * Shortcode: the donor-facing outcome of a donation.
 *
 * `[bgcf_donation_result]` on the thank-you page. Pairs with the return URL
 * handler above, which sends the donor here with an opaque token. The status
 * shown is read from the record, never from the query string — a donor (or
 * anyone else) editing `?status=successful` changes nothing.
 *
 * @since 1.1.0
 * @return string Rendered markup.
 */
function bgcf_donation_result_shortcode(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display keyed on an opaque token.
	$token       = isset( $_GET['donation'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['donation'] ) ) : '';
	$donation_id = bgcf_donation_find_by_token( $token );

	return bgcf_render_partial(
		'global/donation-result',
		array(
			'donation_id' => $donation_id,
			'token'       => $token,
		),
		false
	);
}
add_shortcode( 'bgcf_donation_result', 'bgcf_donation_result_shortcode' );

/* -------------------------------------------------------------------------
 * Webhook
 * ---------------------------------------------------------------------- */

/**
 * Register the Flutterwave webhook route.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_register_payment_routes(): void {
	register_rest_route(
		BGCF_PAYMENTS_REST_NAMESPACE,
		'/flutterwave/webhook',
		array(
			'methods'             => 'POST',
			'callback'            => 'bgcf_handle_flutterwave_webhook',
			'permission_callback' => 'bgcf_flw_webhook_permission',
		)
	);
}
add_action( 'rest_api_init', 'bgcf_register_payment_routes' );

/**
 * Authenticate an inbound webhook.
 *
 * v3 sends the secret hash verbatim in `verif-hash`. Compared in constant time
 * against the configured value; Flutterwave's IPs are dynamic, so this signature
 * is the whole of the authentication.
 *
 * @since 1.1.0
 *
 * @param WP_REST_Request $request Inbound request.
 * @return bool|WP_Error True when authentic.
 */
function bgcf_flw_webhook_permission( WP_REST_Request $request ) {
	$signature = (string) $request->get_header( 'verif_hash' );

	if ( '' === $signature ) {
		$signature = (string) $request->get_header( 'verif-hash' );
	}

	if ( bgcf_flw_verify_webhook_signature( $signature ) ) {
		return true;
	}

	bgcf_flw_log( 'webhook signature rejected' );

	return new WP_Error(
		'bgcf_invalid_signature',
		__( 'Invalid signature.', 'bgcf' ),
		array( 'status' => 401 )
	);
}

/**
 * Process a Flutterwave webhook.
 *
 * Returns 200 in every case where the signature was valid — including events we
 * do not care about and references we do not recognise. A non-200 puts the event
 * into Flutterwave's retry queue (3 attempts, 30 minutes apart), which is for
 * failures we can actually recover from, not for "not mine".
 *
 * The body is a trigger, never proof: the amount and status are read from our
 * own verify call, not from this payload.
 *
 * @since 1.1.0
 *
 * @param WP_REST_Request $request Inbound request.
 * @return WP_REST_Response Always 200.
 */
function bgcf_handle_flutterwave_webhook( WP_REST_Request $request ): WP_REST_Response {
	$body  = (array) $request->get_json_params();
	$event = isset( $body['event'] ) ? sanitize_text_field( (string) $body['event'] ) : '';
	$data  = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

	/**
	 * Fires for every authenticated Flutterwave webhook.
	 *
	 * Transfers, subscriptions and chargebacks land here too; hook this to
	 * extend beyond collections without touching this function.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $event Event name.
	 * @param array<string, mixed> $data  Event payload.
	 */
	do_action( 'bgcf_flutterwave_webhook', $event, $data );

	$tx_ref = isset( $data['tx_ref'] ) ? sanitize_text_field( (string) $data['tx_ref'] ) : '';

	if ( '' === $tx_ref ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$donation_id = bgcf_donation_find_by_tx_ref( $tx_ref );

	if ( 0 === $donation_id ) {
		bgcf_flw_log( 'webhook for unknown reference', array( 'event' => $event ) );

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	update_post_meta( $donation_id, '_bgcf_last_event', $event );

	if ( isset( $data['id'] ) ) {
		update_post_meta( $donation_id, '_bgcf_flw_id', absint( $data['id'] ) );
	}

	$result = bgcf_donation_requery( $donation_id );

	return new WP_REST_Response(
		array(
			'received' => true,
			'status'   => is_wp_error( $result ) ? 'deferred' : $result,
		),
		200
	);
}

/* -------------------------------------------------------------------------
 * Reconciliation
 * ---------------------------------------------------------------------- */

/**
 * Add the reconciliation interval.
 *
 * @since 1.1.0
 *
 * @param array<string, array<string, mixed>> $schedules Cron schedules.
 * @return array<string, array<string, mixed>> Filtered schedules.
 */
function bgcf_donation_cron_schedule( array $schedules ): array {
	$schedules['bgcf_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 minutes (BGCF donations)', 'bgcf' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'bgcf_donation_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Payment reconciliation; 15 minutes is the point.

/**
 * Ensure the reconciliation job is scheduled.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donation_schedule_cron(): void {
	if ( ! wp_next_scheduled( BGCF_DONATION_CRON_HOOK ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'bgcf_fifteen_minutes', BGCF_DONATION_CRON_HOOK );
	}
}
add_action( 'init', 'bgcf_donation_schedule_cron' );

/**
 * Unschedule the job when the theme is switched away.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donation_unschedule_cron(): void {
	$timestamp = wp_next_scheduled( BGCF_DONATION_CRON_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, BGCF_DONATION_CRON_HOOK );
	}
}
add_action( 'switch_theme', 'bgcf_donation_unschedule_cron' );

/**
 * Re-ask Flutterwave about donations still sitting at pending.
 *
 * The safety net, and the reason the flow is correct even when webhooks are
 * lost, delayed or misconfigured. Never rely on webhooks alone.
 *
 * Donations younger than two minutes are skipped (the donor is probably still
 * looking at the handset prompt), and ones older than the abandonment window are
 * closed out so the queue cannot grow forever.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donation_reconcile(): void {
	if ( ! bgcf_flw_is_configured() ) {
		return;
	}

	/**
	 * Filters the reconciliation parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array{batch:int, min_age:int, abandon_after:int} $config Tuning values in seconds.
	 */
	$config = (array) apply_filters(
		'bgcf_donation_reconcile_config',
		array(
			'batch'         => 20,
			'min_age'       => 2 * MINUTE_IN_SECONDS,
			'abandon_after' => DAY_IN_SECONDS,
		)
	);

	$query = new WP_Query(
		array(
			'post_type'              => BGCF_DONATION_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => (int) $config['batch'],
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Scheduled job, bounded batch.
				array(
					'key'     => '_bgcf_status',
					'value'   => 'pending',
					'compare' => '=',
				),
			),
		)
	);

	foreach ( $query->posts as $donation_id ) {
		$donation_id = (int) $donation_id;
		$created     = absint( bgcf_donation_get( $donation_id, 'created_gmt', 0 ) );
		$age         = time() - $created;

		if ( $created > 0 && $age < (int) $config['min_age'] ) {
			continue;
		}

		$result = bgcf_donation_requery( $donation_id );

		if ( is_wp_error( $result ) ) {
			continue;
		}

		if ( 'pending' === $result && $created > 0 && $age > (int) $config['abandon_after'] ) {
			bgcf_donation_set_status( $donation_id, 'abandoned', array( 'reason' => 'reconciliation_timeout' ) );
		}
	}
}
add_action( BGCF_DONATION_CRON_HOOK, 'bgcf_donation_reconcile' );
