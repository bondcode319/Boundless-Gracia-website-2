<?php
/**
 * Flutterwave gateway client (API v3).
 *
 * SINGLE RESPONSIBILITY: talk to Flutterwave. Nothing in this file knows what a
 * donation is, renders markup, or decides whether a receipt gets sent — that is
 * inc/donations.php. Nothing outside this file may call the Flutterwave API
 * directly or read a secret key.
 *
 * Why v3 and not v4: Uganda mobile money — the rail that actually matters here —
 * is one documented v3 endpoint, every SDK and plugin targets v3, and v3 has no
 * announced deprecation. v4's OAuth token dance buys us nothing for a single-
 * currency donation form. Do NOT mix versions: keys, base URLs, payload shapes
 * and webhook signatures all differ.
 *
 * UGX reality check (developer.flutterwave.com/docs/uganda): in UGX Flutterwave
 * supports `card` and `mobilemoneyuganda` only. There is no bank transfer, no
 * USSD, no virtual account and no bill payment in UGX. The form is built around
 * that constraint; do not add method options that cannot settle.
 *
 * CREDENTIALS LIVE IN wp-config.php, NEVER IN THE DATABASE. See PAYMENTS.md:
 *
 *     define( 'BGCF_FLW_PUBLIC_KEY',  'FLWPUBK_TEST-...-X' );
 *     define( 'BGCF_FLW_SECRET_KEY',  'FLWSECK_TEST-...-X' );
 *     define( 'BGCF_FLW_SECRET_HASH', 'a-long-random-string-you-invent' );
 *
 * The mode (test/sandbox vs live) is derived from the key prefix, not from a
 * setting — a checkbox that disagrees with the key is how test money ends up
 * being treated as real money.
 *
 * @package Bgcf
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flutterwave API v3 base URL.
 *
 * There is no separate sandbox host in v3 — the key decides the mode.
 *
 * @since 1.1.0
 */
define( 'BGCF_FLW_API_BASE', 'https://api.flutterwave.com/v3' );

/**
 * Read a gateway credential.
 *
 * Resolution order: wp-config constant, then environment variable. Options and
 * ACF are deliberately not consulted — a secret in wp_options is one careless
 * database export away from being public.
 *
 * @since 1.1.0
 *
 * @param string $name Credential name: 'public_key', 'secret_key', 'secret_hash',
 *                     or 'encryption_key'.
 * @return string Credential value, or '' when unset.
 */
function bgcf_flw_credential( string $name ): string {
	$map = array(
		'public_key'     => 'BGCF_FLW_PUBLIC_KEY',
		'secret_key'     => 'BGCF_FLW_SECRET_KEY',
		'secret_hash'    => 'BGCF_FLW_SECRET_HASH',
		'encryption_key' => 'BGCF_FLW_ENCRYPTION_KEY',
	);

	if ( ! isset( $map[ $name ] ) ) {
		return '';
	}

	$constant = $map[ $name ];

	if ( defined( $constant ) ) {
		return trim( (string) constant( $constant ) );
	}

	$env = getenv( $constant );

	return is_string( $env ) ? trim( $env ) : '';
}

/**
 * Current gateway mode.
 *
 * Derived from the secret key prefix: `FLWSECK_TEST-` is sandbox, anything else
 * is live. Unconfigured installs report 'test' so nothing can accidentally
 * present itself as a live payment surface.
 *
 * @since 1.1.0
 * @return string 'test' or 'live'.
 */
function bgcf_flw_mode(): string {
	$secret = bgcf_flw_credential( 'secret_key' );

	if ( '' === $secret || 0 === strpos( $secret, 'FLWSECK_TEST-' ) ) {
		return 'test';
	}

	return 'live';
}

/**
 * Whether the gateway is in sandbox (test) mode.
 *
 * @since 1.1.0
 * @return bool True in sandbox.
 */
function bgcf_flw_is_sandbox(): bool {
	return 'test' === bgcf_flw_mode();
}

/**
 * Whether the gateway has everything it needs to take a payment.
 *
 * The secret hash counts: without it the webhook endpoint cannot authenticate
 * callers, and a donation flow whose only proof of payment is a browser
 * redirect is not a donation flow, it is an honour system.
 *
 * @since 1.1.0
 * @return bool True when public key, secret key and secret hash are all present.
 */
function bgcf_flw_is_configured(): bool {
	return '' !== bgcf_flw_credential( 'public_key' )
		&& '' !== bgcf_flw_credential( 'secret_key' )
		&& '' !== bgcf_flw_credential( 'secret_hash' );
}

/**
 * Perform an authenticated request against the Flutterwave API.
 *
 * Returns the decoded `data` envelope on success. Every failure path — transport
 * error, non-2xx status, unparseable body, `status: "error"` — comes back as a
 * WP_Error so callers never have to guess at the shape.
 *
 * Timeouts are NOT failures. Flutterwave waits ~25–28 s before giving up, and a
 * charge may well have gone through in that time. Callers must requery (verify),
 * never blind-retry a money-moving call.
 *
 * @since 1.1.0
 *
 * @param string               $method HTTP method, 'GET' or 'POST'.
 * @param string               $path   Path below the API base, e.g. '/payments'.
 * @param array<string, mixed> $body   Request body (POST) or query args (GET).
 * @return array<string, mixed>|WP_Error Decoded `data` payload, or WP_Error.
 */
function bgcf_flw_request( string $method, string $path, array $body = array() ) {
	$secret = bgcf_flw_credential( 'secret_key' );

	if ( '' === $secret ) {
		return new WP_Error(
			'bgcf_flw_not_configured',
			__( 'Online giving is not available right now. Please try again later.', 'bgcf' ),
			array( 'status' => 503 )
		);
	}

	$method = strtoupper( $method );
	$url    = BGCF_FLW_API_BASE . '/' . ltrim( $path, '/' );

	$args = array(
		'method'  => $method,
		// Above Flutterwave's own ~28 s ceiling, so we see their answer rather than our own timeout.
		'timeout' => 45,
		'headers' => array(
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);

	if ( 'GET' === $method ) {
		$url = add_query_arg( array_map( 'strval', $body ), $url );
	} else {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		bgcf_flw_log(
			'transport error',
			array(
				'path'  => $path,
				'error' => $response->get_error_message(),
			)
		);

		return new WP_Error(
			'bgcf_flw_unreachable',
			__( 'We could not reach the payment provider. Please try again in a moment.', 'bgcf' ),
			array( 'status' => 502 )
		);
	}

	$code    = (int) wp_remote_retrieve_response_code( $response );
	$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $decoded ) ) {
		bgcf_flw_log(
			'unparseable response',
			array(
				'path' => $path,
				'http' => $code,
			)
		);

		return new WP_Error(
			'bgcf_flw_bad_response',
			__( 'The payment provider returned an unexpected response.', 'bgcf' ),
			array( 'status' => 502 )
		);
	}

	$status = isset( $decoded['status'] ) ? (string) $decoded['status'] : '';

	if ( $code < 200 || $code >= 300 || 'success' !== $status ) {
		$message = isset( $decoded['message'] ) ? (string) $decoded['message'] : __( 'The payment could not be started.', 'bgcf' );

		bgcf_flw_log(
			'api error',
			array(
				'path'    => $path,
				'http'    => $code,
				'message' => $message,
			)
		);

		return new WP_Error(
			'bgcf_flw_api_error',
			$message,
			array(
				'status'   => 502,
				'http'     => $code,
				'response' => $decoded,
			)
		);
	}

	return isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : $decoded;
}

/**
 * Events that are always recorded, whatever the debug setting.
 *
 * WHY THIS LIST EXISTS. Logging used to be gated entirely on `WP_DEBUG`, which
 * meant a correctly configured production site had ZERO payment observability.
 * That matters because this system's characteristic failure is not a crash but
 * silent degradation: mistype the webhook secret and every webhook 401s, the
 * reconciliation cron quietly picks up the slack, and the only symptom is that
 * receipts arrive fifteen minutes late. Nothing would ever report it.
 *
 * So a short list of events is recorded unconditionally — the ones that mean
 * money may be at risk. Everything else stays behind the debug flag, because
 * logging every successful API call on a busy site is how you fill a disk.
 *
 * @since 1.2.0
 * @return string[] Message prefixes that are always logged.
 */
function bgcf_flw_critical_events(): array {
	/**
	 * Filters the always-logged payment events.
	 *
	 * @since 1.2.0
	 *
	 * @param string[] $events Message prefixes.
	 */
	return (array) apply_filters(
		'bgcf_flw_critical_events',
		array(
			'webhook signature rejected',
			'verification mismatch',
			'no redirect in charge response',
			'chargeback',
			'refund',
			'request failed',
			'request error',
		)
	);
}

/**
 * Write a gateway diagnostic to the error log.
 *
 * Only ever called with metadata the support desk needs: a path, an HTTP code, a
 * reference, a provider message. Never a key, never a phone number, never a card
 * field — logs get shipped to third parties and read by people who should not
 * see donor data.
 *
 * Critical events (see above) are also recorded as an option so the admin
 * dashboard can surface them to someone who will never read a server log.
 *
 * @since 1.1.0
 *
 * @param string               $message Short description.
 * @param array<string, mixed> $context Safe context values.
 * @return void
 */
function bgcf_flw_log( string $message, array $context = array() ): void {
	$is_critical = false;

	foreach ( bgcf_flw_critical_events() as $event ) {
		if ( false !== stripos( $message, (string) $event ) ) {
			$is_critical = true;
			break;
		}
	}

	/**
	 * Filters whether gateway diagnostics are written to the error log.
	 *
	 * @since 1.1.0
	 *
	 * @param bool   $enabled     Critical events always; others on WP_DEBUG.
	 * @param string $message     The message about to be logged.
	 * @param bool   $is_critical Whether this is a money-at-risk event.
	 */
	$enabled = (bool) apply_filters(
		'bgcf_flw_logging_enabled',
		$is_critical || ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
		$message,
		$is_critical
	);

	if ( $enabled ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate, filtered diagnostic.
			sprintf( '[bgcf/flutterwave] %s %s', $message, wp_json_encode( $context ) )
		);
	}

	if ( $is_critical ) {
		bgcf_flw_record_incident( $message, $context );
	}
}

/**
 * Keep the last few critical payment events where wp-admin can see them.
 *
 * A ring buffer in an option, capped at twenty entries. Deliberately not a
 * custom table and deliberately not unbounded: the point is that the next person
 * to open the dashboard learns the webhook has been failing for three days,
 * which a log file on a server they cannot reach will never tell them.
 *
 * @since 1.2.0
 *
 * @param string               $message Event description.
 * @param array<string, mixed> $context Safe context values.
 * @return void
 */
function bgcf_flw_record_incident( string $message, array $context = array() ): void {
	$incidents = get_option( 'bgcf_payment_incidents', array() );
	$incidents = is_array( $incidents ) ? $incidents : array();

	$incidents[] = array(
		'time'    => time(),
		'message' => sanitize_text_field( $message ),
		// Only scalars: a nested payload could carry donor data into an option
		// that the dashboard then prints.
		'context' => array_filter( $context, 'is_scalar' ),
	);

	update_option( 'bgcf_payment_incidents', array_slice( $incidents, -20 ), false );
}

/**
 * The recorded critical payment events, newest first.
 *
 * @since 1.2.0
 * @return array<int, array<string, mixed>> Incidents.
 */
function bgcf_flw_incidents(): array {
	$incidents = get_option( 'bgcf_payment_incidents', array() );
	$incidents = is_array( $incidents ) ? $incidents : array();

	return array_reverse( $incidents );
}

/**
 * Normalise a Ugandan mobile number to MSISDN form.
 *
 * Flutterwave wants `256XXXXXXXXX`. Donors type `0772…`, `+256 772 …`,
 * `256-772-…` and every variation in between; rejecting those is a conversion
 * bug, not validation.
 *
 * @since 1.1.0
 *
 * @param string $phone Raw phone input.
 * @return string Normalised MSISDN, or '' when it cannot be a UG mobile number.
 */
function bgcf_flw_normalize_msisdn( string $phone ): string {
	$digits = preg_replace( '/\D+/', '', $phone );

	if ( ! is_string( $digits ) || '' === $digits ) {
		return '';
	}

	// 0772123456 -> 256772123456.
	if ( 10 === strlen( $digits ) && 0 === strpos( $digits, '0' ) ) {
		$digits = '256' . substr( $digits, 1 );
	}

	// 772123456 -> 256772123456.
	if ( 9 === strlen( $digits ) ) {
		$digits = '256' . $digits;
	}

	if ( 12 !== strlen( $digits ) || 0 !== strpos( $digits, '256' ) ) {
		return '';
	}

	return $digits;
}

/**
 * Guess the mobile money network from a Ugandan MSISDN.
 *
 * A hint only — the donor's explicit choice always wins. Prefixes move as the
 * regulator reallocates ranges, hence the filter.
 *
 * @since 1.1.0
 *
 * @param string $msisdn Normalised MSISDN (256XXXXXXXXX).
 * @return string 'MTN', 'AIRTEL', or '' when unknown.
 */
function bgcf_flw_guess_network( string $msisdn ): string {
	/**
	 * Filters the MSISDN prefix to network map.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $prefixes Two-digit local prefix => network.
	 */
	$prefixes = (array) apply_filters(
		'bgcf_flw_network_prefixes',
		array(
			'77' => 'MTN',
			'78' => 'MTN',
			'76' => 'MTN',
			'39' => 'MTN',
			'70' => 'AIRTEL',
			'74' => 'AIRTEL',
			'75' => 'AIRTEL',
			'20' => 'AIRTEL',
		)
	);

	$local = substr( $msisdn, 3, 2 );

	return isset( $prefixes[ $local ] ) ? (string) $prefixes[ $local ] : '';
}

/**
 * The mobile money networks Flutterwave accepts for UGX.
 *
 * @since 1.1.0
 * @return array<string, string> Network code => display label.
 */
function bgcf_flw_networks(): array {
	return array(
		'MTN'    => __( 'MTN Mobile Money', 'bgcf' ),
		'AIRTEL' => __( 'Airtel Money', 'bgcf' ),
	);
}

/**
 * Generate a unique transaction reference.
 *
 * Server-side only, always. A client-supplied reference lets an attacker replay
 * or collide with someone else's payment. The mode is baked into the string so
 * a sandbox record is recognisable at a glance in the Flutterwave dashboard and
 * in our own admin list.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation record ID.
 * @return string Transaction reference, e.g. `BGCF-TEST-412-1737012345-a1b2c3d4`.
 */
function bgcf_flw_generate_tx_ref( int $donation_id ): string {
	return sprintf(
		'BGCF-%s-%d-%d-%s',
		strtoupper( bgcf_flw_mode() ),
		$donation_id,
		time(),
		wp_generate_password( 8, false, false )
	);
}

/**
 * Compute the checkout payload hash.
 *
 * Sent as `payload_hash` on hosted checkout so the amount, currency, email and
 * reference cannot be edited in the browser between our server and Flutterwave's.
 *
 * SHA256(secret) -> SHA256(amount + currency + email + tx_ref + that).
 *
 * @since 1.1.0
 *
 * @param string $amount   Amount exactly as sent in the payload.
 * @param string $currency Currency code.
 * @param string $email    Customer email exactly as sent in the payload.
 * @param string $tx_ref   Transaction reference.
 * @return string Hex SHA-256 hash, or '' when the secret key is missing.
 */
function bgcf_flw_payload_hash( string $amount, string $currency, string $email, string $tx_ref ): string {
	$secret = bgcf_flw_credential( 'secret_key' );

	if ( '' === $secret ) {
		return '';
	}

	$hashed_secret = hash( 'sha256', $secret );

	return hash( 'sha256', $amount . $currency . $email . $tx_ref . $hashed_secret );
}

/**
 * Charge a Ugandan mobile money account.
 *
 * `POST /v3/charges?type=mobile_money_uganda`. The response carries an
 * authorisation URL the donor must visit; they then approve the debit on their
 * handset. In sandbox this auto-authorises after a few seconds.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args {
 *     Charge arguments.
 *
 *     @type int    $amount       Whole shillings.
 *     @type string $currency     Currency code. Default 'UGX'.
 *     @type string $email        Donor email.
 *     @type string $phone_number Normalised MSISDN.
 *     @type string $network      'MTN' or 'AIRTEL'.
 *     @type string $tx_ref       Server-generated reference.
 *     @type string $fullname     Donor name.
 *     @type string $redirect_url Return URL.
 *     @type array  $meta         Arbitrary metadata echoed back on webhooks.
 * }
 * @return array<string, mixed>|WP_Error Decoded data payload, or WP_Error.
 */
function bgcf_flw_charge_mobile_money( array $args ) {
	$payload = array(
		'tx_ref'       => (string) $args['tx_ref'],
		'amount'       => (string) (int) $args['amount'],
		'currency'     => (string) ( $args['currency'] ?? 'UGX' ),
		'network'      => (string) $args['network'],
		'email'        => (string) $args['email'],
		'phone_number' => (string) $args['phone_number'],
		'fullname'     => (string) ( $args['fullname'] ?? '' ),
		'redirect_url' => (string) ( $args['redirect_url'] ?? '' ),
		'client_ip'    => bgcf_flw_client_ip(),
		'meta'         => (array) ( $args['meta'] ?? array() ),
	);

	return bgcf_flw_request(
		'POST',
		'/charges?type=mobile_money_uganda',
		array_filter( $payload, 'bgcf_flw_is_present' )
	);
}

/**
 * Create a hosted checkout link.
 *
 * `POST /v3/payments`. Used for card giving (and as the fallback surface if a
 * donor would rather pick their method on Flutterwave's page). Returns
 * `data.link`; send the donor there.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args {
 *     Checkout arguments.
 *
 *     @type int    $amount          Whole shillings.
 *     @type string $currency        Currency code.
 *     @type string $tx_ref          Server-generated reference.
 *     @type string $email           Donor email.
 *     @type string $name            Donor name.
 *     @type string $phone           Donor phone.
 *     @type string $payment_options Comma-separated method list.
 *     @type string $redirect_url    Return URL.
 *     @type string $title           Checkout title.
 *     @type string $description     Checkout description.
 *     @type string $logo            Absolute logo URL.
 *     @type array  $meta            Arbitrary metadata.
 * }
 * @return array<string, mixed>|WP_Error Decoded data payload, or WP_Error.
 */
function bgcf_flw_create_checkout( array $args ) {
	$amount   = (string) (int) $args['amount'];
	$currency = (string) ( $args['currency'] ?? 'UGX' );
	$email    = (string) $args['email'];
	$tx_ref   = (string) $args['tx_ref'];

	$payload = array(
		'tx_ref'          => $tx_ref,
		'amount'          => $amount,
		'currency'        => $currency,
		'redirect_url'    => (string) ( $args['redirect_url'] ?? '' ),
		'payment_options' => (string) ( $args['payment_options'] ?? 'card,mobilemoneyuganda' ),
		'payload_hash'    => bgcf_flw_payload_hash( $amount, $currency, $email, $tx_ref ),
		'customer'        => array_filter(
			array(
				'email'       => $email,
				'name'        => (string) ( $args['name'] ?? '' ),
				'phonenumber' => (string) ( $args['phone'] ?? '' ),
			),
			'bgcf_flw_is_present'
		),
		'customizations'  => array_filter(
			array(
				'title'       => (string) ( $args['title'] ?? '' ),
				'description' => (string) ( $args['description'] ?? '' ),
				'logo'        => (string) ( $args['logo'] ?? '' ),
			),
			'bgcf_flw_is_present'
		),
		'meta'            => (array) ( $args['meta'] ?? array() ),
	);

	return bgcf_flw_request( 'POST', '/payments', array_filter( $payload, 'bgcf_flw_is_present' ) );
}

/**
 * Verify a transaction by Flutterwave's transaction id.
 *
 * `GET /v3/transactions/{id}/verify`. THE gate before any donation is treated as
 * paid. See bgcf_donation_apply_verification() for the four checks that must
 * follow this call.
 *
 * @since 1.1.0
 *
 * @param int $transaction_id Flutterwave transaction id.
 * @return array<string, mixed>|WP_Error Verified transaction data, or WP_Error.
 */
function bgcf_flw_verify_transaction( int $transaction_id ) {
	return bgcf_flw_request( 'GET', sprintf( '/transactions/%d/verify', $transaction_id ) );
}

/**
 * Verify a transaction by our own reference.
 *
 * `GET /v3/transactions/verify_by_reference`. Used by the reconciliation job,
 * which knows the tx_ref it generated but may never have been told a
 * transaction id (dropped webhook, donor closed the tab).
 *
 * @since 1.1.0
 *
 * @param string $tx_ref Transaction reference.
 * @return array<string, mixed>|WP_Error Verified transaction data, or WP_Error.
 */
function bgcf_flw_verify_by_reference( string $tx_ref ) {
	return bgcf_flw_request( 'GET', '/transactions/verify_by_reference', array( 'tx_ref' => $tx_ref ) );
}

/**
 * Constant-time check of an inbound webhook signature.
 *
 * v3 sends the secret hash verbatim in the `verif-hash` header. Flutterwave's
 * egress IPs are dynamic, so the signature is the only authentication available
 * — do not substitute an IP allowlist for it.
 *
 * @since 1.1.0
 *
 * @param string $signature Value of the `verif-hash` header.
 * @return bool True when the signature matches the configured secret hash.
 */
function bgcf_flw_verify_webhook_signature( string $signature ): bool {
	$expected = bgcf_flw_credential( 'secret_hash' );

	if ( '' === $expected || '' === $signature ) {
		return false;
	}

	return hash_equals( $expected, $signature );
}

/**
 * Best-effort client IP, for Flutterwave's fraud signals.
 *
 * Proxy headers are attacker-controlled, so this reads REMOTE_ADDR only: a hint
 * we forward, never an input we make a decision on.
 *
 * @since 1.1.0
 * @return string IP address, or ''.
 */
function bgcf_flw_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip     = filter_var( $remote, FILTER_VALIDATE_IP );

	return is_string( $ip ) ? $ip : '';
}

/**
 * Array filter callback: keep values that are actually present.
 *
 * `array_filter()` with no callback would drop `0` and `'0'`; Flutterwave
 * rejects nulls and empty strings but is fine with zeros.
 *
 * @since 1.1.0
 *
 * @param mixed $value Candidate value.
 * @return bool True to keep.
 */
function bgcf_flw_is_present( $value ): bool {
	if ( null === $value ) {
		return false;
	}

	if ( is_string( $value ) ) {
		return '' !== trim( $value );
	}

	if ( is_array( $value ) ) {
		return ! empty( $value );
	}

	return true;
}
