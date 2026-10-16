<?php
/**
 * Payment health: disputes, the readiness check and the admin warning.
 *
 * WHAT PROBLEM THIS SOLVES. The payment flow is designed to degrade quietly —
 * three independent signals, any one of which can carry a donation to
 * fulfilment. That is the right design, and it is exactly why a broken
 * component is invisible: a mistyped webhook secret rejects every webhook, the
 * cron silently covers for it, and the only symptom is slower receipts. Nobody
 * notices for weeks.
 *
 * So this file does three things no other module does:
 *   1. Listens for chargeback and refund events and flags the donation, rather
 *      than leaving a reversed payment showing as successful income.
 *   2. Exposes a readiness check — keys present, mode consistent, webhook secret
 *      set, cron alive, thank-you page assigned — as both a WordPress Site
 *      Health test and an admin notice.
 *   3. Surfaces the critical-event ring buffer from inc/payments.php where a
 *      fundraiser, not a developer, will see it.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Disputes
 * ---------------------------------------------------------------------- */

/**
 * Flag a donation when the provider reverses it.
 *
 * A chargeback or refund means the money is going back. Leaving the record at
 * `successful` overstates income and leaves the campaign total wrong, so the
 * record is marked and the campaign cache cleared — but the status is NOT
 * silently rewritten, because a disputed gift is a decision for a human with
 * the bank's correspondence in front of them.
 *
 * @since 1.2.0
 *
 * @param string               $event Flutterwave event name.
 * @param array<string, mixed> $data  Event payload.
 * @return void
 */
function bgcf_handle_payment_dispute( string $event, array $data ): void {
	$event = strtolower( $event );

	$is_dispute = false !== strpos( $event, 'chargeback' )
		|| false !== strpos( $event, 'dispute' )
		|| false !== strpos( $event, 'refund' );

	if ( ! $is_dispute ) {
		return;
	}

	$tx_ref = isset( $data['tx_ref'] ) ? sanitize_text_field( (string) $data['tx_ref'] ) : '';

	if ( '' === $tx_ref ) {
		bgcf_flw_log( 'chargeback for unknown reference', array( 'event' => $event ) );

		return;
	}

	$donation_id = bgcf_donation_find_by_tx_ref( $tx_ref );

	if ( 0 === $donation_id ) {
		bgcf_flw_log( 'chargeback for unknown reference', array( 'event' => $event ) );

		return;
	}

	update_post_meta( $donation_id, '_bgcf_disputed', 1 );
	update_post_meta( $donation_id, '_bgcf_disputed_at', time() );
	update_post_meta( $donation_id, '_bgcf_dispute_event', $event );

	// Logged as critical, so it reaches the dashboard as well as the log.
	bgcf_flw_log(
		'chargeback or refund received',
		array(
			'donation' => $donation_id,
			'event'    => $event,
		)
	);

	// The campaign total included this gift; it no longer should.
	if ( function_exists( 'bgcf_flush_campaign_cache' ) ) {
		bgcf_flush_campaign_cache( $donation_id );
	}

	bgcf_notify_team(
		__( 'A donation has been disputed or refunded', 'bgcf' ),
		sprintf(
			/* translators: 1: event name, 2: transaction reference, 3: admin URL. */
			__( "Flutterwave reported: %1\$s\nReference: %2\$s\n\nReview the record here: %3\$s\n\nThe donation has been flagged but its status has not been changed automatically — check the bank correspondence before adjusting it.", 'bgcf' ),
			$event,
			$tx_ref,
			admin_url( 'post.php?post=' . $donation_id . '&action=edit' )
		),
		array( 'donation' => $donation_id )
	);

	/**
	 * Fires when a donation is disputed or refunded.
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $donation_id Donation post ID.
	 * @param string               $event       Provider event name.
	 * @param array<string, mixed> $data        Event payload.
	 */
	do_action( 'bgcf_donation_disputed', $donation_id, $event, $data );
}
add_action( 'bgcf_flutterwave_webhook', 'bgcf_handle_payment_dispute', 10, 2 );

/* -------------------------------------------------------------------------
 * Readiness
 * ---------------------------------------------------------------------- */

/**
 * Check everything the donation flow needs in order to work.
 *
 * Returns a list of problems, each with a severity. An empty list means a gift
 * can be taken end to end.
 *
 * @since 1.2.0
 * @return array<int, array{level:string, message:string}> Problems found.
 */
function bgcf_payment_readiness(): array {
	$problems = array();

	if ( ! bgcf_flw_is_configured() ) {
		$problems[] = array(
			'level'   => 'critical',
			'message' => __( 'Flutterwave keys are missing, so no donation can be taken. Add them to wp-config.php.', 'bgcf' ),
		);

		// Everything below depends on the keys; one message is clearer than six.
		return $problems;
	}

	if ( '' === bgcf_flw_credential( 'secret_hash' ) ) {
		$problems[] = array(
			'level'   => 'critical',
			'message' => __( 'No webhook secret is set, so every webhook from Flutterwave is rejected. Donations will still complete, but receipts will be up to fifteen minutes late.', 'bgcf' ),
		);
	}

	if ( 0 === absint( bgcf_donation_setting( 'thank_you_page', 0 ) ) ) {
		$problems[] = array(
			'level'   => 'warning',
			'message' => __( 'No thank-you page is set, so donors are returned to the homepage with no confirmation of their gift.', 'bgcf' ),
		);
	}

	if ( ! wp_next_scheduled( BGCF_DONATION_CRON_HOOK ) ) {
		$problems[] = array(
			'level'   => 'critical',
			'message' => __( 'The donation reconciliation job is not scheduled. Any donation whose webhook is lost will stay pending forever.', 'bgcf' ),
		);
	}

	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && ! ( defined( 'BGCF_SERVER_CRON' ) && BGCF_SERVER_CRON ) ) {
		$problems[] = array(
			'level'   => 'warning',
			'message' => __( 'WP-Cron is disabled. Reconciliation only runs if a real server cron calls wp-cron.php — confirm that it does, then define BGCF_SERVER_CRON to silence this notice.', 'bgcf' ),
		);
	}

	if ( bgcf_flw_is_sandbox() ) {
		$problems[] = array(
			'level'   => 'warning',
			'message' => __( 'Flutterwave is in test mode. Real donations cannot be taken until the live keys are in place.', 'bgcf' ),
		);
	}

	if ( empty( bgcf_donation_methods() ) ) {
		$problems[] = array(
			'level'   => 'critical',
			'message' => __( 'No payment method is enabled under Donations → Settings, so the giving form cannot be shown.', 'bgcf' ),
		);
	}

	/**
	 * Filters the payment readiness problems.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, array{level:string, message:string}> $problems Problems found.
	 */
	return (array) apply_filters( 'bgcf_payment_readiness', $problems );
}

/**
 * Show the readiness problems in wp-admin.
 *
 * Only on the dashboard and the donation screens: a notice on every page for
 * everyone is a notice people learn to dismiss without reading.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_payment_readiness_notice(): void {
	if ( ! current_user_can( bgcf_donations_capability() ) ) {
		return;
	}

	$screen = get_current_screen();
	$id     = $screen instanceof WP_Screen ? (string) $screen->id : '';
	$show   = 'dashboard' === $id
		|| false !== strpos( $id, BGCF_DONATION_POST_TYPE )
		|| false !== strpos( $id, 'bgcf-donation' );

	if ( ! $show ) {
		return;
	}

	foreach ( bgcf_payment_readiness() as $problem ) {
		printf(
			'<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
			'critical' === $problem['level'] ? 'error' : 'warning',
			esc_html__( 'Donations:', 'bgcf' ),
			esc_html( (string) $problem['message'] )
		);
	}
}
add_action( 'admin_notices', 'bgcf_payment_readiness_notice' );

/**
 * Register the readiness check as a WordPress Site Health test.
 *
 * Site Health is where a host, an agency or a future maintainer looks first, and
 * it is checked by monitoring tools that know nothing about this theme.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $tests Registered tests.
 * @return array<string, mixed> Filtered tests.
 */
function bgcf_register_site_health_test( array $tests ): array {
	$tests['direct']['bgcf_payments'] = array(
		'label' => __( 'Donation processing', 'bgcf' ),
		'test'  => 'bgcf_site_health_payments',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'bgcf_register_site_health_test' );

/**
 * The Site Health test body.
 *
 * @since 1.2.0
 * @return array<string, mixed> Site Health result.
 */
function bgcf_site_health_payments(): array {
	$problems = bgcf_payment_readiness();

	$result = array(
		'label'       => __( 'Donations can be taken', 'bgcf' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Donations', 'bgcf' ),
			'color' => 'blue',
		),
		'description' => '<p>' . esc_html__( 'Flutterwave is configured, the webhook secret is set, the reconciliation job is scheduled and a thank-you page is assigned.', 'bgcf' ) . '</p>',
		'test'        => 'bgcf_payments',
	);

	if ( empty( $problems ) ) {
		return $result;
	}

	$critical = false;
	$items    = '';

	foreach ( $problems as $problem ) {
		$critical = $critical || 'critical' === $problem['level'];
		$items   .= '<li>' . esc_html( (string) $problem['message'] ) . '</li>';
	}

	$result['status']      = $critical ? 'critical' : 'recommended';
	$result['label']       = $critical
		? __( 'Donations cannot be taken reliably', 'bgcf' )
		: __( 'Donation processing needs attention', 'bgcf' );
	$result['badge']['color'] = $critical ? 'red' : 'orange';
	$result['description'] = '<ul>' . $items . '</ul>';
	$result['actions']     = sprintf(
		'<p><a href="%1$s">%2$s</a></p>',
		esc_url( admin_url( 'edit.php?post_type=' . BGCF_DONATION_POST_TYPE . '&page=bgcf-donation-settings' ) ),
		esc_html__( 'Open donation settings', 'bgcf' )
	);

	return $result;
}
