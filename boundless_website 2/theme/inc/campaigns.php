<?php
/**
 * Campaign fundraising progress, fee cover and impact lines.
 *
 * THE RULE, restated from partials/blocks/block-donation-banner.php because it
 * is the one thing in this file that must not be "optimised" away: a campaign's
 * raised total is **computed from the donation records**, never stored in a
 * field an editor can type into. A hand-entered total goes stale the moment the
 * next gift arrives, and a charity publishing a number that overstates what it
 * has actually received is a regulatory problem, not a display bug.
 *
 * The computation is cached in a transient, and the cache is cleared whenever a
 * donation completes — so the figure is cheap to render and still correct within
 * one page load of the gift that changed it.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * How long a campaign total may be served from cache.
 *
 * Five minutes is the ceiling, not the expected staleness: completed donations
 * flush the cache immediately (see bgcf_flush_campaign_cache()). The TTL only
 * covers the case where a donation is recorded by something that does not fire
 * our action — a manual edit, a direct database change, an importer.
 *
 * @since 1.2.0
 */
define( 'BGCF_CAMPAIGN_CACHE_TTL', 5 * MINUTE_IN_SECONDS );

/**
 * The amount raised for a campaign, in whole currency units.
 *
 * Counts only `successful` donations. Pending gifts are not money, and
 * presenting them as raised is the single easiest way to overstate a total.
 *
 * @since 1.2.0
 *
 * @param int $campaign_id Campaign post ID, or 0 for all donations.
 * @return int Amount raised.
 */
function bgcf_campaign_raised( int $campaign_id = 0 ): int {
	$key    = 'bgcf_raised_' . $campaign_id;
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	global $wpdb;

	/*
	 * A direct query, deliberately. The alternative — WP_Query over donation
	 * posts, then summing the amount meta in PHP — loads every donation record
	 * into memory to produce one integer, and gets slower with every gift the
	 * charity receives. This is the one place in the theme where SQL is the
	 * right tool, and it is parameterised.
	 */
	if ( $campaign_id > 0 ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over donation meta; cached in the transient above.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( amount.meta_value ), 0 )
				 FROM {$wpdb->postmeta} AS amount
				 INNER JOIN {$wpdb->postmeta} AS status
				   ON status.post_id = amount.post_id AND status.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} AS campaign
				   ON campaign.post_id = amount.post_id AND campaign.meta_key = %s
				 INNER JOIN {$wpdb->posts} AS donation
				   ON donation.ID = amount.post_id AND donation.post_type = %s
				 WHERE amount.meta_key = %s
				   AND status.meta_value = %s
				   AND campaign.meta_value = %d
				   /*
				    * A charged-back or refunded gift is money going the other
				    * way. Counting it would publish a total the bank statement
				    * does not support.
				    */
				   AND NOT EXISTS (
				     SELECT 1 FROM {$wpdb->postmeta} AS disputed
				     WHERE disputed.post_id = amount.post_id
				       AND disputed.meta_key = '_bgcf_disputed'
				       AND disputed.meta_value = '1'
				   )",
				'_bgcf_status',
				'_bgcf_campaign',
				BGCF_DONATION_POST_TYPE,
				'_bgcf_amount',
				'successful',
				$campaign_id
			)
		);
	} else {
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( amount.meta_value ), 0 )
				 FROM {$wpdb->postmeta} AS amount
				 INNER JOIN {$wpdb->postmeta} AS status
				   ON status.post_id = amount.post_id AND status.meta_key = %s
				 INNER JOIN {$wpdb->posts} AS donation
				   ON donation.ID = amount.post_id AND donation.post_type = %s
				 WHERE amount.meta_key = %s
				   AND status.meta_value = %s
				   AND NOT EXISTS (
				     SELECT 1 FROM {$wpdb->postmeta} AS disputed
				     WHERE disputed.post_id = amount.post_id
				       AND disputed.meta_key = '_bgcf_disputed'
				       AND disputed.meta_value = '1'
				   )",
				'_bgcf_status',
				BGCF_DONATION_POST_TYPE,
				'_bgcf_amount',
				'successful'
			)
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$total = (int) $total;

	set_transient( $key, $total, BGCF_CAMPAIGN_CACHE_TTL );

	return $total;
}

/**
 * The number of donors who have given to a campaign.
 *
 * @since 1.2.0
 *
 * @param int $campaign_id Campaign post ID, or 0 for all.
 * @return int Donation count.
 */
function bgcf_campaign_donor_count( int $campaign_id = 0 ): int {
	$key    = 'bgcf_donors_' . $campaign_id;
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$query = new WP_Query(
		array(
			'post_type'              => BGCF_DONATION_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array_values( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Count only; result cached.
				array_filter(
					array(
						array(
							'key'     => '_bgcf_status',
							'value'   => 'successful',
							'compare' => '=',
						),
						array(
							'key'     => '_bgcf_disputed',
							'compare' => 'NOT EXISTS',
						),
						$campaign_id > 0
							? array(
								'key'     => '_bgcf_campaign',
								'value'   => $campaign_id,
								'compare' => '=',
							)
							: null,
					)
				)
			),
		)
	);

	$count = (int) $query->found_posts;

	set_transient( $key, $count, BGCF_CAMPAIGN_CACHE_TTL );

	return $count;
}

/**
 * A campaign's fundraising goal.
 *
 * @since 1.2.0
 *
 * @param int $campaign_id Campaign post ID.
 * @return int Goal in whole currency units, or 0 when none is set.
 */
function bgcf_campaign_goal( int $campaign_id ): int {
	return absint( bgcf_field( 'goal', $campaign_id, 0 ) );
}

/**
 * Everything needed to render a progress bar for a campaign.
 *
 * @since 1.2.0
 *
 * @param int $campaign_id Campaign post ID.
 * @return array{goal:int, raised:int, percent:int, donors:int, deadline:string, days_left:?int}
 */
function bgcf_campaign_progress( int $campaign_id ): array {
	$goal   = bgcf_campaign_goal( $campaign_id );
	$raised = bgcf_campaign_raised( $campaign_id );

	// Capped at 100 for the bar's width; the real figures are still printed, so
	// an over-funded campaign reads as "120% of target" in text and a full bar.
	$percent = $goal > 0 ? (int) min( 100, round( ( $raised / $goal ) * 100 ) ) : 0;

	$deadline  = (string) bgcf_field( 'deadline', $campaign_id, '' );
	$days_left = null;

	if ( '' !== $deadline ) {
		$timestamp = strtotime( $deadline );

		if ( false !== $timestamp ) {
			$days_left = (int) max( 0, ceil( ( $timestamp - time() ) / DAY_IN_SECONDS ) );
		}
	}

	return array(
		'goal'      => $goal,
		'raised'    => $raised,
		'percent'   => $percent,
		'donors'    => bgcf_campaign_donor_count( $campaign_id ),
		'deadline'  => $deadline,
		'days_left' => $days_left,
	);
}

/**
 * Clear the cached totals after a donation settles.
 *
 * @since 1.2.0
 *
 * @param int $donation_id Donation post ID.
 * @return void
 */
function bgcf_flush_campaign_cache( int $donation_id = 0 ): void {
	$campaign = $donation_id > 0 ? absint( bgcf_donation_get( $donation_id, 'campaign', 0 ) ) : 0;

	delete_transient( 'bgcf_raised_0' );
	delete_transient( 'bgcf_donors_0' );

	if ( $campaign > 0 ) {
		delete_transient( 'bgcf_raised_' . $campaign );
		delete_transient( 'bgcf_donors_' . $campaign );
	}
}
add_action( 'bgcf_donation_completed', 'bgcf_flush_campaign_cache' );

/* -------------------------------------------------------------------------
 * Fee cover
 * ---------------------------------------------------------------------- */

/**
 * Whether the donation form offers to let donors absorb the processing fee.
 *
 * @since 1.2.0
 * @return bool True when the option is shown.
 */
function bgcf_fee_cover_enabled(): bool {
	return (bool) bgcf_donation_setting( 'fee_cover_enabled', true );
}

/**
 * The processing-fee configuration, as the front end needs it.
 *
 * @since 1.2.0
 * @return array{percent:float, flat:int, cap:int}
 */
function bgcf_fee_config(): array {
	return array(
		'percent' => (float) bgcf_donation_setting( 'fee_percent', 3.0 ),
		'flat'    => absint( bgcf_donation_setting( 'fee_flat', 0 ) ),
		'cap'     => absint( bgcf_donation_setting( 'fee_cap', 0 ) ),
	);
}

/**
 * The fee a donor would cover on a given amount.
 *
 * THE SERVER IS THE AUTHORITY. The browser computes the same number to display
 * a running total, but the charge is built from this function — otherwise the
 * fee is a value the client can set, and "cover the fee" becomes "choose your
 * own total".
 *
 * @since 1.2.0
 *
 * @param int $amount Gift amount in whole currency units.
 * @return int Fee in whole currency units.
 */
function bgcf_fee_for_amount( int $amount ): int {
	if ( $amount <= 0 || ! bgcf_fee_cover_enabled() ) {
		return 0;
	}

	$config = bgcf_fee_config();
	$fee    = (int) round( ( $amount * $config['percent'] ) / 100 ) + $config['flat'];

	if ( $config['cap'] > 0 ) {
		$fee = min( $fee, $config['cap'] );
	}

	return max( 0, $fee );
}

/* -------------------------------------------------------------------------
 * Impact lines
 * ---------------------------------------------------------------------- */

/**
 * The configured impact lines, parsed and sorted.
 *
 * Stored as `amount|text` strings so the settings screen stays one textarea
 * rather than a repeater nobody maintains.
 *
 * @since 1.2.0
 * @return array<int, array{amount:int, text:string}> Lines, lowest amount first.
 */
function bgcf_impact_lines(): array {
	$raw   = (array) bgcf_donation_setting( 'impact_lines', array() );
	$lines = array();

	foreach ( $raw as $row ) {
		$parts = explode( '|', (string) $row, 2 );

		if ( count( $parts ) < 2 ) {
			continue;
		}

		$amount = absint( trim( $parts[0] ) );
		$text   = sanitize_text_field( trim( $parts[1] ) );

		if ( 0 === $amount || '' === $text ) {
			continue;
		}

		$lines[] = array(
			'amount' => $amount,
			'text'   => $text,
		);
	}

	usort(
		$lines,
		static function ( array $a, array $b ): int {
			return $a['amount'] <=> $b['amount'];
		}
	);

	return $lines;
}
