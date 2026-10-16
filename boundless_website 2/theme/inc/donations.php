<?php
/**
 * Donation records: settings, storage, state machine, fulfilment.
 *
 * SINGLE RESPONSIBILITY: what a donation *is* in this site. The gateway lives in
 * inc/payments.php; the public endpoints (AJAX, webhook, return URL, cron) live
 * in inc/payments-routes.php. This file is the only place that writes a donation
 * status or decides that money has actually arrived.
 *
 * THE RULE: value is never granted — no receipt, no "thank you", no counted
 * total — from a browser redirect, a client callback or a webhook body. Those
 * are triggers. The only proof is a server-side call to
 * `GET /v3/transactions/{id}/verify` whose response passes all four checks in
 * bgcf_donation_apply_verification(). Every path (webhook, return URL,
 * reconciliation cron) funnels through that one function, and fulfilment is
 * idempotent because all three will routinely fire for the same payment.
 *
 * Donation records hold donor PII (name, email, phone). The post type is
 * private, uncreatable through the UI, and restricted to a capability that only
 * administrators get by default — see bgcf_donations_capability().
 *
 * @package Bgcf
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post type key for donation records.
 *
 * @since 1.1.0
 */
define( 'BGCF_DONATION_POST_TYPE', 'bgcf_donation' );

/**
 * Option name holding the (non-secret) donation settings.
 *
 * @since 1.1.0
 */
define( 'BGCF_DONATION_OPTION', 'bgcf_donation_settings' );

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Donation settings, merged over their defaults.
 *
 * Secrets are NOT here — see bgcf_flw_credential(). These are the presentation
 * and policy values an administrator may safely change in wp-admin.
 *
 * @since 1.1.0
 * @return array<string, mixed> Settings.
 */
function bgcf_donation_settings(): array {
	$defaults = array(
		'currency'          => 'UGX',
		// Whole shillings. UGX has no minor units in practice.
		'amounts'           => array( 20000, 50000, 100000, 250000 ),
		'default_amount'    => 50000,
		'min_amount'        => 1000,
		'max_amount'        => 20000000,
		'enable_mobile'     => true,
		'enable_card'       => true,
		'thank_you_page'    => 0,
		'checkout_title'    => '',
		'receipt_enabled'   => true,
		'notify_enabled'    => true,
		'notify_email'      => '',
		/*
		 * Fee cover. Flutterwave's Uganda collection fee is a percentage with a
		 * cap; offering donors the chance to absorb it typically recovers most of
		 * it, and the figures must match what is actually charged or the "total"
		 * shown to the donor is a lie. Percent is in whole percent.
		 */
		'fee_cover_enabled' => true,
		'fee_percent'       => 3.0,
		'fee_flat'          => 0,
		'fee_cap'           => 10000,
		/*
		 * Impact lines: "UGX 50,000 feeds a child for a month". Stored as
		 * `amount|text` rows so the form can show the highest line the donor's
		 * amount qualifies for. Copy belongs to the content team, not to code.
		 */
		'impact_lines'      => array(),
		// Whether the donation form asks for explicit consent to store contact
		// details. Mandatory in the EEA/UK and simply correct everywhere.
		'consent_required'  => true,
	);

	$stored = get_option( BGCF_DONATION_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();

	/**
	 * Filters the resolved donation settings.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $settings Merged settings.
	 */
	return (array) apply_filters( 'bgcf_donation_settings', array_merge( $defaults, $stored ) );
}

/**
 * Read a single donation setting.
 *
 * @since 1.1.0
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback when the key is absent or empty.
 * @return mixed Setting value.
 */
function bgcf_donation_setting( string $key, $default = '' ) {
	$settings = bgcf_donation_settings();

	if ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) {
		return $default;
	}

	return $settings[ $key ];
}

/**
 * The collection currency.
 *
 * UGX by default and in practice: Flutterwave settles this account locally, and
 * the whole method matrix below assumes it.
 *
 * @since 1.1.0
 * @return string ISO 4217 code.
 */
function bgcf_donation_currency(): string {
	return strtoupper( (string) bgcf_donation_setting( 'currency', 'UGX' ) );
}

/**
 * Suggested giving amounts, cleaned and ordered.
 *
 * @since 1.1.0
 * @return int[] Whole-currency amounts.
 */
function bgcf_donation_amount_presets(): array {
	$amounts = (array) bgcf_donation_setting( 'amounts', array() );
	$amounts = array_filter( array_map( 'absint', $amounts ) );

	sort( $amounts );

	return array_values( array_unique( $amounts ) );
}

/**
 * The payment methods on offer, after settings and currency constraints.
 *
 * Flutterwave supports `card` and `mobilemoneyuganda` in UGX and nothing else.
 * A method that cannot settle is never offered, whatever the setting says.
 *
 * @since 1.1.0
 * @return array<string, string> Method key => display label.
 */
function bgcf_donation_methods(): array {
	$methods = array();

	if ( 'UGX' === bgcf_donation_currency() && bgcf_donation_setting( 'enable_mobile', true ) ) {
		$methods['mobile_money'] = __( 'Mobile Money (MTN or Airtel)', 'bgcf' );
	}

	if ( bgcf_donation_setting( 'enable_card', true ) ) {
		$methods['card'] = __( 'Debit or credit card', 'bgcf' );
	}

	/**
	 * Filters the donation payment methods offered on the front end.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $methods Method key => label.
	 */
	return (array) apply_filters( 'bgcf_donation_methods', $methods );
}

/**
 * Format an amount for display.
 *
 * Deliberately not `number_format_i18n()` with decimals: UGX is quoted in whole
 * shillings and ".00" on a donation amount reads as a rounding error.
 *
 * @since 1.1.0
 *
 * @param int    $amount   Amount in whole currency units.
 * @param string $currency Currency code. Defaults to the configured currency.
 * @return string Display string, e.g. "UGX 50,000".
 */
function bgcf_donation_format_amount( int $amount, string $currency = '' ): string {
	$currency = '' !== $currency ? strtoupper( $currency ) : bgcf_donation_currency();

	return sprintf( '%s %s', $currency, number_format_i18n( $amount ) );
}

/**
 * Capability required to see donor records.
 *
 * @since 1.1.0
 * @return string Capability name.
 */
function bgcf_donations_capability(): string {
	/**
	 * Filters the capability that gates donation records.
	 *
	 * Donation records contain donor names, emails and phone numbers. Grant this
	 * to a finance role deliberately; do not widen it to `edit_posts`.
	 *
	 * @since 1.1.0
	 *
	 * @param string $capability Capability name.
	 */
	return (string) apply_filters( 'bgcf_donations_capability', 'manage_options' );
}

/* -------------------------------------------------------------------------
 * Post type
 * ---------------------------------------------------------------------- */

/**
 * The statuses a donation record can hold.
 *
 * `pending`     charge created, donor has not finished (or we have not heard).
 * `successful`  verified against the Flutterwave API. Terminal, and the only
 *               status that counts as money.
 * `failed`      Flutterwave reported a terminal failure.
 * `abandoned`   never completed and too old to keep polling.
 *
 * @since 1.1.0
 * @return array<string, string> Status key => label.
 */
function bgcf_donation_statuses(): array {
	return array(
		'pending'    => __( 'Pending', 'bgcf' ),
		'successful' => __( 'Successful', 'bgcf' ),
		'failed'     => __( 'Failed', 'bgcf' ),
		'abandoned'  => __( 'Abandoned', 'bgcf' ),
	);
}

/**
 * Register the donation record post type.
 *
 * Not public, not creatable, not in REST: these are financial records, not
 * content. They exist in wp-admin so the finance team can reconcile without an
 * SQL client.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_register_donation_post_type(): void {
	$capability = bgcf_donations_capability();

	register_post_type(
		BGCF_DONATION_POST_TYPE,
		array(
			'labels'              => array(
				'name'               => _x( 'Donations', 'post type general name', 'bgcf' ),
				'singular_name'      => _x( 'Donation', 'post type singular name', 'bgcf' ),
				'menu_name'          => _x( 'Donations', 'admin menu', 'bgcf' ),
				'all_items'          => __( 'All Donations', 'bgcf' ),
				'edit_item'          => __( 'Donation', 'bgcf' ),
				'search_items'       => __( 'Search Donations', 'bgcf' ),
				'not_found'          => __( 'No donations found.', 'bgcf' ),
				'not_found_in_trash' => __( 'No donations found in Trash.', 'bgcf' ),
			),
			'description'         => __( 'One record per attempted donation, successful or not.', 'bgcf' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-money-alt',
			'menu_position'       => 27,
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'          => $capability,
				'read_post'          => $capability,
				'delete_post'        => $capability,
				'edit_posts'         => $capability,
				'edit_others_posts'  => $capability,
				'publish_posts'      => $capability,
				'read_private_posts' => $capability,
				'delete_posts'       => $capability,
				// Records are created by the payment flow, never by a human.
				'create_posts'       => 'do_not_allow',
			),
		)
	);
}
add_action( 'init', 'bgcf_register_donation_post_type', 5 );

/* -------------------------------------------------------------------------
 * Records
 * ---------------------------------------------------------------------- */

/**
 * Create a pending donation record and its transaction reference.
 *
 * The record is written *before* Flutterwave is called, so a charge can never
 * exist that we have no row for. If the charge call then fails, we are left with
 * a pending record that the reconciliation job resolves — which is the safe
 * direction to fail in.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args {
 *     Donation attributes. All values must already be sanitized.
 *
 *     @type int    $amount    Whole currency units.
 *     @type string $currency  Currency code.
 *     @type string $method    'mobile_money' or 'card'.
 *     @type string $network   'MTN' or 'AIRTEL' (mobile money only).
 *     @type string $name      Donor name.
 *     @type string $email     Donor email.
 *     @type string $phone     Normalised MSISDN.
 *     @type bool   $anonymous Whether to hide the donor publicly.
 *     @type int    $campaign  Campaign post ID, or 0.
 *     @type string $message   Donor message.
 * }
 * @return int|WP_Error Donation post ID, or WP_Error.
 */
function bgcf_donation_create( array $args ) {
	$amount   = absint( $args['amount'] ?? 0 );
	$currency = strtoupper( (string) ( $args['currency'] ?? bgcf_donation_currency() ) );

	$donation_id = wp_insert_post(
		array(
			'post_type'   => BGCF_DONATION_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: 1: formatted amount, 2: donor name or fallback. */
				__( '%1$s from %2$s', 'bgcf' ),
				bgcf_donation_format_amount( $amount, $currency ),
				(string) ( $args['name'] ?? '' ) !== '' ? (string) $args['name'] : __( 'a supporter', 'bgcf' )
			),
		),
		true
	);

	if ( is_wp_error( $donation_id ) ) {
		return $donation_id;
	}

	$donation_id = (int) $donation_id;
	$tx_ref      = bgcf_flw_generate_tx_ref( $donation_id );

	$meta = array(
		'_bgcf_tx_ref'       => $tx_ref,
		// Opaque handle the browser may hold. The tx_ref never leaves the server
		// in a form a third party could use to poll someone else's donation.
		'_bgcf_public_token' => wp_generate_password( 24, false, false ),
		'_bgcf_status'       => 'pending',
		'_bgcf_mode'         => bgcf_flw_mode(),
		'_bgcf_amount'       => $amount,
		/*
		 * `amount` is the GIFT — what the campaign raised and what the receipt
		 * calls the donation. `charged` is what actually left the donor's
		 * account, which is larger when they chose to cover the processing fee.
		 * Keeping them apart is what stops the fee being counted as income.
		 */
		'_bgcf_fee'          => absint( $args['fee'] ?? 0 ),
		'_bgcf_charged'      => absint( $args['charged'] ?? $amount ),
		'_bgcf_currency'     => $currency,
		'_bgcf_method'       => (string) ( $args['method'] ?? '' ),
		'_bgcf_network'      => (string) ( $args['network'] ?? '' ),
		'_bgcf_donor_name'   => (string) ( $args['name'] ?? '' ),
		'_bgcf_donor_email'  => (string) ( $args['email'] ?? '' ),
		'_bgcf_donor_phone'  => (string) ( $args['phone'] ?? '' ),
		'_bgcf_anonymous'    => ! empty( $args['anonymous'] ) ? 1 : 0,
		'_bgcf_campaign'     => absint( $args['campaign'] ?? 0 ),
		'_bgcf_message'      => (string) ( $args['message'] ?? '' ),
		'_bgcf_consent'      => ! empty( $args['consent'] ) ? 1 : 0,
		'_bgcf_consent_at'   => ! empty( $args['consent'] ) ? time() : 0,
		'_bgcf_created_gmt'  => time(),
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $donation_id, $key, $value );
	}

	/**
	 * Fires after a pending donation record is created.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $donation_id Donation post ID.
	 * @param array<string, mixed> $args        Sanitized creation arguments.
	 */
	do_action( 'bgcf_donation_created', $donation_id, $args );

	return $donation_id;
}

/**
 * Find a donation by its transaction reference.
 *
 * @since 1.1.0
 *
 * @param string $tx_ref Transaction reference.
 * @return int Donation post ID, or 0.
 */
function bgcf_donation_find_by_tx_ref( string $tx_ref ): int {
	return bgcf_donation_find_by_meta( '_bgcf_tx_ref', $tx_ref );
}

/**
 * Find a donation by its public token.
 *
 * @since 1.1.0
 *
 * @param string $token Public token.
 * @return int Donation post ID, or 0.
 */
function bgcf_donation_find_by_token( string $token ): int {
	return bgcf_donation_find_by_meta( '_bgcf_public_token', $token );
}

/**
 * Look a donation up by a unique meta value.
 *
 * @since 1.1.0
 *
 * @param string $key   Meta key.
 * @param string $value Meta value.
 * @return int Donation post ID, or 0.
 */
function bgcf_donation_find_by_meta( string $key, string $value ): int {
	if ( '' === $value ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => BGCF_DONATION_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			// Indexed meta lookup on a unique value; the alternative is a raw
			// $wpdb query, which buys nothing here.
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				),
			),
		)
	);

	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

/**
 * Read a donation field.
 *
 * @since 1.1.0
 *
 * @param int    $donation_id Donation post ID.
 * @param string $key         Meta key without the `_bgcf_` prefix.
 * @param mixed  $default     Fallback when empty.
 * @return mixed Meta value.
 */
function bgcf_donation_get( int $donation_id, string $key, $default = '' ) {
	$value = get_post_meta( $donation_id, '_bgcf_' . $key, true );

	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Current status of a donation.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation post ID.
 * @return string One of bgcf_donation_statuses().
 */
function bgcf_donation_status( int $donation_id ): string {
	$status = (string) bgcf_donation_get( $donation_id, 'status', 'pending' );

	return array_key_exists( $status, bgcf_donation_statuses() ) ? $status : 'pending';
}

/**
 * Move a donation to a new status.
 *
 * Terminal statuses are sticky: once a donation is `successful`, nothing — not a
 * late webhook, not a retried verification — may take it back to pending or
 * failed. That direction of travel only ever corrupts reporting.
 *
 * @since 1.1.0
 *
 * @param int                  $donation_id Donation post ID.
 * @param string               $status      New status.
 * @param array<string, mixed> $context     Optional context stored for auditing.
 * @return bool True when the status changed.
 */
function bgcf_donation_set_status( int $donation_id, string $status, array $context = array() ): bool {
	if ( ! array_key_exists( $status, bgcf_donation_statuses() ) ) {
		return false;
	}

	$current = bgcf_donation_status( $donation_id );

	if ( $current === $status || 'successful' === $current ) {
		return false;
	}

	update_post_meta( $donation_id, '_bgcf_status', $status );
	update_post_meta( $donation_id, '_bgcf_status_updated', time() );

	if ( ! empty( $context ) ) {
		update_post_meta( $donation_id, '_bgcf_last_context', wp_json_encode( $context ) );
	}

	/**
	 * Fires when a donation changes status.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $donation_id Donation post ID.
	 * @param string               $status      New status.
	 * @param string               $previous    Previous status.
	 * @param array<string, mixed> $context     Context values.
	 */
	do_action( 'bgcf_donation_status_changed', $donation_id, $status, $current, $context );

	return true;
}

/* -------------------------------------------------------------------------
 * Verification and fulfilment
 * ---------------------------------------------------------------------- */

/**
 * Apply a verified Flutterwave transaction to a donation record.
 *
 * THE gate. Every inbound signal (webhook, return URL, cron) ends here, and this
 * is the only function permitted to mark a donation successful.
 *
 * Four checks, all of them, every time — the skill's §7 rule:
 *   1. the provider says `successful`;
 *   2. the reference is the one WE generated for THIS donation;
 *   3. the currency is the one we asked for;
 *   4. the amount is at least the amount we asked for.
 *
 * Check 2 is what stops a real, successful UGX 1,000 payment being replayed
 * against a UGX 1,000,000 record.
 *
 * @since 1.1.0
 *
 * @param int                  $donation_id Donation post ID.
 * @param array<string, mixed> $data        `data` envelope from a verify call.
 * @return string Resulting status.
 */
function bgcf_donation_apply_verification( int $donation_id, array $data ): string {
	$expected_ref = (string) bgcf_donation_get( $donation_id, 'tx_ref' );

	/*
	 * Compare against what we ASKED THE GATEWAY FOR, which is the charged
	 * amount — the gift plus any processing fee the donor chose to cover. Using
	 * the gift amount here would reject every fee-covered donation as a
	 * mismatch, because the provider would correctly report the larger figure.
	 * `charged` falls back to `amount` for records created before fee cover
	 * existed, so historic donations still verify.
	 */
	$expected_amount   = (float) bgcf_donation_get( $donation_id, 'charged', bgcf_donation_get( $donation_id, 'amount', 0 ) );
	$expected_currency = strtoupper( (string) bgcf_donation_get( $donation_id, 'currency', bgcf_donation_currency() ) );

	$status   = strtolower( (string) ( $data['status'] ?? '' ) );
	$tx_ref   = (string) ( $data['tx_ref'] ?? '' );
	$currency = strtoupper( (string) ( $data['currency'] ?? '' ) );
	$amount   = (float) ( $data['amount'] ?? 0 );

	// Record what the provider told us, whatever the outcome — this is the
	// reconciliation trail the finance team works from.
	if ( isset( $data['id'] ) ) {
		update_post_meta( $donation_id, '_bgcf_flw_id', absint( $data['id'] ) );
	}

	if ( isset( $data['flw_ref'] ) ) {
		update_post_meta( $donation_id, '_bgcf_flw_ref', sanitize_text_field( (string) $data['flw_ref'] ) );
	}

	if ( isset( $data['charged_amount'] ) ) {
		update_post_meta( $donation_id, '_bgcf_charged_amount', (float) $data['charged_amount'] );
	}

	if ( isset( $data['processor_response'] ) ) {
		update_post_meta( $donation_id, '_bgcf_processor_response', sanitize_text_field( (string) $data['processor_response'] ) );
	}

	update_post_meta( $donation_id, '_bgcf_verified_at', time() );

	$mismatch = ( '' === $tx_ref || $tx_ref !== $expected_ref )
		|| ( $currency !== $expected_currency )
		|| ( $amount < $expected_amount );

	if ( 'successful' === $status && $mismatch ) {
		// A successful payment that does not match this record is a red flag, not
		// a donation. Park it for a human; never fulfil, never silently discard.
		bgcf_flw_log(
			'verification mismatch',
			array(
				'donation'         => $donation_id,
				'expected_ref'     => $expected_ref,
				'reported_ref'     => $tx_ref,
				'expected_amount'  => $expected_amount,
				'reported_amount'  => $amount,
				'expected_currency' => $expected_currency,
				'reported_currency' => $currency,
			)
		);

		update_post_meta( $donation_id, '_bgcf_mismatch', 1 );
		bgcf_donation_set_status( $donation_id, 'pending', array( 'reason' => 'verification_mismatch' ) );

		return bgcf_donation_status( $donation_id );
	}

	if ( 'successful' === $status ) {
		bgcf_donation_mark_successful( $donation_id );

		return 'successful';
	}

	if ( in_array( $status, array( 'failed', 'cancelled', 'error' ), true ) ) {
		bgcf_donation_set_status( $donation_id, 'failed', array( 'provider_status' => $status ) );

		return 'failed';
	}

	// 'pending', 'processing', or anything unfamiliar: leave it pending and let
	// the reconciliation job ask again.
	return bgcf_donation_status( $donation_id );
}

/**
 * Atomically claim the right to fulfil a donation.
 *
 * WHY NOT `add_post_meta( …, $unique = true )`. That looks atomic and is not:
 * WordPress implements it as a SELECT for an existing row followed by an
 * INSERT, with nothing but PHP between them and no unique constraint on
 * `wp_postmeta` to catch a loser. Two signals arriving in the same instant —
 * which this architecture makes routine, since the webhook and the browser
 * return fire at almost the same moment — can both pass the SELECT, both
 * insert, and both be told they won. The donor then gets two receipts and
 * `bgcf_donation_completed` fires twice, so every consumer counting totals
 * over-counts.
 *
 * `add_option()` IS atomic, because `wp_options.option_name` carries a UNIQUE
 * index: the database itself rejects the second INSERT, and `add_option()`
 * returns false for the caller that lost. That makes the option row — not the
 * meta row — the real lock. It is written with `autoload = false` so it never
 * enters the alloptions cache.
 *
 * @since 1.2.0
 *
 * @param int $donation_id Donation post ID.
 * @return bool True when this caller holds the claim.
 */
function bgcf_donation_claim_fulfilment( int $donation_id ): bool {
	$key = '_bgcf_fulfilled_' . $donation_id;

	/*
	 * Clear any cached "this option does not exist" marker first. Without it a
	 * concurrent process that already looked for this option would be served a
	 * cached miss and add_option() would be skipped before it reached the
	 * database, putting the race back where it started.
	 */
	wp_cache_delete( 'notoptions', 'options' );

	return (bool) add_option( $key, (string) time(), '', false );
}

/**
 * Mark a donation successful, exactly once.
 *
 * Idempotency is enforced with a unique meta row rather than a status check:
 * the webhook, the return URL and the cron job can all arrive within the same
 * second, and `add_post_meta( …, true )` is the cheapest guard that survives
 * that. A donor gets one receipt, and the total is counted once.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation post ID.
 * @return bool True when this call was the one that fulfilled it.
 */
function bgcf_donation_mark_successful( int $donation_id ): bool {
	bgcf_donation_set_status( $donation_id, 'successful' );

	if ( ! bgcf_donation_claim_fulfilment( $donation_id ) ) {
		return false; // Already fulfilled by an earlier signal.
	}

	// Audit trail. The claim above is the guard; this is the record of when it
	// was taken, kept with the donation so it survives option cleanup.
	update_post_meta( $donation_id, '_bgcf_fulfilled', time() );

	if ( bgcf_donation_setting( 'receipt_enabled', true ) ) {
		bgcf_donation_send_receipt( $donation_id );
	}

	if ( bgcf_donation_setting( 'notify_enabled', true ) ) {
		bgcf_donation_notify_admin( $donation_id );
	}

	/**
	 * Fires once, when a donation is confirmed paid.
	 *
	 * The hook for anything that must happen exactly once: campaign totals,
	 * CRM sync, thank-you automation.
	 *
	 * @since 1.1.0
	 *
	 * @param int $donation_id Donation post ID.
	 */
	do_action( 'bgcf_donation_completed', $donation_id );

	return true;
}

/**
 * Re-ask Flutterwave about a donation and apply the answer.
 *
 * Prefers the transaction id when we have one (cheapest, most direct), and falls
 * back to our own reference when we do not — which is the normal case for a
 * donation whose webhook never arrived.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation post ID.
 * @return string|WP_Error Resulting status, or WP_Error when the lookup failed.
 */
function bgcf_donation_requery( int $donation_id ) {
	$flw_id = absint( bgcf_donation_get( $donation_id, 'flw_id', 0 ) );
	$tx_ref = (string) bgcf_donation_get( $donation_id, 'tx_ref' );

	$data = $flw_id > 0
		? bgcf_flw_verify_transaction( $flw_id )
		: bgcf_flw_verify_by_reference( $tx_ref );

	update_post_meta( $donation_id, '_bgcf_requeried_at', time() );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return bgcf_donation_apply_verification( $donation_id, $data );
}

/* -------------------------------------------------------------------------
 * Notifications
 * ---------------------------------------------------------------------- */

/**
 * Email the donor a receipt.
 *
 * Plain text on purpose: it lands in inboxes, renders on a feature phone, and
 * carries no tracking. The reference is the donor's handle for every support
 * conversation that follows, so it is the one thing that must be in there.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation post ID.
 * @return bool True when wp_mail() accepted the message.
 */
function bgcf_donation_send_receipt( int $donation_id ): bool {
	$email = sanitize_email( (string) bgcf_donation_get( $donation_id, 'donor_email' ) );

	if ( ! is_email( $email ) ) {
		return false;
	}

	$name     = (string) bgcf_donation_get( $donation_id, 'donor_name', __( 'Friend', 'bgcf' ) );
	$amount   = bgcf_donation_format_amount(
		(int) bgcf_donation_get( $donation_id, 'amount', 0 ),
		(string) bgcf_donation_get( $donation_id, 'currency', '' )
	);
	$tx_ref   = (string) bgcf_donation_get( $donation_id, 'tx_ref' );
	$org      = get_bloginfo( 'name' );
	$sandbox  = 'test' === (string) bgcf_donation_get( $donation_id, 'mode', 'test' );

	$subject = sprintf(
		/* translators: %s: organisation name. */
		__( 'Thank you for supporting %s', 'bgcf' ),
		$org
	);

	$lines = array(
		sprintf(
			/* translators: %s: donor first name. */
			__( 'Dear %s,', 'bgcf' ),
			$name
		),
		'',
		sprintf(
			/* translators: 1: formatted amount, 2: organisation name. */
			__( 'We have received your gift of %1$s to %2$s. Thank you.', 'bgcf' ),
			$amount,
			$org
		),
		'',
		sprintf(
			/* translators: %s: transaction reference. */
			__( 'Your reference: %s', 'bgcf' ),
			$tx_ref
		),
		sprintf(
			/* translators: %s: date and time. */
			__( 'Date: %s', 'bgcf' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
		),
		'',
		__( 'Please keep this email for your records.', 'bgcf' ),
		'',
		$org,
		home_url( '/' ),
	);

	if ( $sandbox ) {
		array_unshift(
			$lines,
			__( '*** TEST MODE — no money has moved. This is a sandbox transaction. ***', 'bgcf' ),
			''
		);
	}

	/**
	 * Filters the donor receipt email.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $email       { to, subject, message, headers }.
	 * @param int                  $donation_id Donation post ID.
	 */
	$mail = (array) apply_filters(
		'bgcf_donation_receipt_email',
		array(
			'to'      => $email,
			'subject' => $subject,
			'message' => implode( "\n", $lines ),
			'headers' => array(),
		),
		$donation_id
	);

	return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
}

/**
 * Notify the organisation that a donation arrived.
 *
 * @since 1.1.0
 *
 * @param int $donation_id Donation post ID.
 * @return bool True when wp_mail() accepted the message.
 */
function bgcf_donation_notify_admin( int $donation_id ): bool {
	$to = sanitize_email( (string) bgcf_donation_setting( 'notify_email', get_option( 'admin_email' ) ) );

	if ( ! is_email( $to ) ) {
		return false;
	}

	$amount = bgcf_donation_format_amount(
		(int) bgcf_donation_get( $donation_id, 'amount', 0 ),
		(string) bgcf_donation_get( $donation_id, 'currency', '' )
	);

	$message = implode(
		"\n",
		array(
			sprintf(
				/* translators: 1: formatted amount, 2: payment method. */
				__( 'New donation: %1$s via %2$s.', 'bgcf' ),
				$amount,
				(string) bgcf_donation_get( $donation_id, 'method' )
			),
			sprintf(
				/* translators: %s: donor name. */
				__( 'Donor: %s', 'bgcf' ),
				(string) bgcf_donation_get( $donation_id, 'donor_name', __( 'Not given', 'bgcf' ) )
			),
			sprintf(
				/* translators: %s: transaction reference. */
				__( 'Reference: %s', 'bgcf' ),
				(string) bgcf_donation_get( $donation_id, 'tx_ref' )
			),
			sprintf(
				/* translators: %s: gateway mode. */
				__( 'Mode: %s', 'bgcf' ),
				(string) bgcf_donation_get( $donation_id, 'mode', 'test' )
			),
			'',
			admin_url( 'post.php?post=' . $donation_id . '&action=edit' ),
		)
	);

	return (bool) wp_mail(
		$to,
		sprintf(
			/* translators: %s: formatted amount. */
			__( 'Donation received: %s', 'bgcf' ),
			$amount
		),
		$message
	);
}
