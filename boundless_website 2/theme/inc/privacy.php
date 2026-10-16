<?php
/**
 * Personal data: exporters, erasers and the retention policy.
 *
 * WHY THIS FILE EXISTS. The theme stores personal data about real people in
 * four places — donation records, newsletter subscribers, volunteer
 * applications and contact messages — none of which WordPress knows about. So
 * when a visitor exercises their right of access or erasure, Tools → Export
 * Personal Data returns nothing and the organisation unknowingly answers a
 * lawful request with a false negative. Registering here is what makes the
 * built-in tools tell the truth.
 *
 * ERASURE IS NOT DELETION, for donations. A completed gift is a financial
 * record the charity is required to keep (and that its auditors will ask for),
 * so an erasure request anonymises the donor on the record rather than removing
 * the row: the amount, the date and the reference survive; the name, email and
 * phone do not. That is the standard reconciliation of the right to erasure
 * with a legal retention obligation, and the distinction is made explicit in
 * the response so nobody has to guess what happened.
 *
 * Leads carry no such obligation and are deleted outright.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the exporters.
 *
 * @since 1.2.0
 *
 * @param array<string, array<string, mixed>> $exporters Registered exporters.
 * @return array<string, array<string, mixed>> Filtered exporters.
 */
function bgcf_register_privacy_exporters( array $exporters ): array {
	$exporters['bgcf-donations'] = array(
		'exporter_friendly_name' => __( 'Boundless Gracia donations', 'bgcf' ),
		'callback'               => 'bgcf_export_donation_data',
	);

	$exporters['bgcf-leads'] = array(
		'exporter_friendly_name' => __( 'Boundless Gracia sign-ups and enquiries', 'bgcf' ),
		'callback'               => 'bgcf_export_lead_data',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'bgcf_register_privacy_exporters' );

/**
 * Register the erasers.
 *
 * @since 1.2.0
 *
 * @param array<string, array<string, mixed>> $erasers Registered erasers.
 * @return array<string, array<string, mixed>> Filtered erasers.
 */
function bgcf_register_privacy_erasers( array $erasers ): array {
	$erasers['bgcf-donations'] = array(
		'eraser_friendly_name' => __( 'Boundless Gracia donations', 'bgcf' ),
		'callback'             => 'bgcf_erase_donation_data',
	);

	$erasers['bgcf-leads'] = array(
		'eraser_friendly_name' => __( 'Boundless Gracia sign-ups and enquiries', 'bgcf' ),
		'callback'             => 'bgcf_erase_lead_data',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'bgcf_register_privacy_erasers' );

/**
 * Find records belonging to an email address.
 *
 * @since 1.2.0
 *
 * @param string $post_type Post type to search.
 * @param string $meta_key  Meta key holding the email.
 * @param string $email     Email address.
 * @param int    $page      1-based page number.
 * @param int    $per_page  Records per page.
 * @return int[] Post IDs.
 */
function bgcf_privacy_find( string $post_type, string $meta_key, string $email, int $page, int $per_page = 50 ): array {
	$query = new WP_Query(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'paged'                  => max( 1, $page ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_key'               => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Subject-access lookup; correctness over speed.
			'meta_value'             => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
		)
	);

	return array_map( 'intval', (array) $query->posts );
}

/**
 * Export a donor's donation history.
 *
 * @since 1.2.0
 *
 * @param string $email Email address of the data subject.
 * @param int    $page  1-based page number.
 * @return array{data: array<int, array<string, mixed>>, done: bool}
 */
function bgcf_export_donation_data( string $email, int $page = 1 ): array {
	$email = sanitize_email( $email );
	$ids   = bgcf_privacy_find( BGCF_DONATION_POST_TYPE, '_bgcf_donor_email', $email, $page );
	$items = array();

	$labels = array(
		'tx_ref'      => __( 'Reference', 'bgcf' ),
		'status'      => __( 'Status', 'bgcf' ),
		'amount'      => __( 'Gift amount', 'bgcf' ),
		'fee'         => __( 'Processing fee covered', 'bgcf' ),
		'charged'     => __( 'Total charged', 'bgcf' ),
		'currency'    => __( 'Currency', 'bgcf' ),
		'method'      => __( 'Payment method', 'bgcf' ),
		'donor_name'  => __( 'Name given', 'bgcf' ),
		'donor_email' => __( 'Email given', 'bgcf' ),
		'donor_phone' => __( 'Phone given', 'bgcf' ),
		'message'     => __( 'Message', 'bgcf' ),
	);

	foreach ( $ids as $donation_id ) {
		$data = array();

		foreach ( $labels as $field => $label ) {
			$value = bgcf_donation_get( $donation_id, $field, '' );

			if ( '' === $value || null === $value ) {
				continue;
			}

			$data[] = array(
				'name'  => $label,
				'value' => is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value,
			);
		}

		$created = absint( bgcf_donation_get( $donation_id, 'created_gmt', 0 ) );

		if ( $created > 0 ) {
			$data[] = array(
				'name'  => __( 'Date', 'bgcf' ),
				'value' => wp_date( 'Y-m-d H:i', $created ),
			);
		}

		$items[] = array(
			'group_id'    => 'bgcf-donations',
			'group_label' => __( 'Donations', 'bgcf' ),
			'item_id'     => 'bgcf-donation-' . $donation_id,
			'data'        => $data,
		);
	}

	return array(
		'data' => $items,
		'done' => count( $ids ) < 50,
	);
}

/**
 * Export a person's sign-ups and enquiries.
 *
 * @since 1.2.0
 *
 * @param string $email Email address of the data subject.
 * @param int    $page  1-based page number.
 * @return array{data: array<int, array<string, mixed>>, done: bool}
 */
function bgcf_export_lead_data( string $email, int $page = 1 ): array {
	$email = sanitize_email( $email );
	$items = array();
	$more  = false;

	$sources = array(
		BGCF_SUBSCRIBER_POST_TYPE => array(
			'label'  => __( 'Newsletter subscription', 'bgcf' ),
			'fields' => array(
				'email'      => __( 'Email', 'bgcf' ),
				'name'       => __( 'Name', 'bgcf' ),
				'source'     => __( 'Signed up from', 'bgcf' ),
				'consent_at' => __( 'Consent given', 'bgcf' ),
			),
		),
		BGCF_VOLUNTEER_POST_TYPE  => array(
			'label'  => __( 'Volunteer application', 'bgcf' ),
			'fields' => array(
				'reference'    => __( 'Reference', 'bgcf' ),
				'name'         => __( 'Name', 'bgcf' ),
				'email'        => __( 'Email', 'bgcf' ),
				'phone'        => __( 'Phone', 'bgcf' ),
				'location'     => __( 'Location', 'bgcf' ),
				'skills'       => __( 'Skills', 'bgcf' ),
				'availability' => __( 'Availability', 'bgcf' ),
				'status'       => __( 'Status', 'bgcf' ),
			),
		),
		BGCF_MESSAGE_POST_TYPE    => array(
			'label'  => __( 'Enquiry', 'bgcf' ),
			'fields' => array(
				'name'    => __( 'Name', 'bgcf' ),
				'email'   => __( 'Email', 'bgcf' ),
				'phone'   => __( 'Phone', 'bgcf' ),
				'subject' => __( 'Subject', 'bgcf' ),
			),
		),
	);

	foreach ( $sources as $post_type => $config ) {
		$ids = bgcf_privacy_find( $post_type, '_bgcf_email', $email, $page );

		if ( count( $ids ) >= 50 ) {
			$more = true;
		}

		foreach ( $ids as $lead_id ) {
			$data = array();

			foreach ( (array) $config['fields'] as $field => $label ) {
				$value = bgcf_lead_get( $lead_id, $field, '' );

				if ( '' === $value || null === $value ) {
					continue;
				}

				if ( 'consent_at' === $field && is_numeric( $value ) ) {
					$value = wp_date( 'Y-m-d H:i', (int) $value );
				}

				$data[] = array(
					'name'  => $label,
					'value' => is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value,
				);
			}

			$content = (string) get_post_field( 'post_content', $lead_id );

			if ( '' !== $content ) {
				$data[] = array(
					'name'  => __( 'Message', 'bgcf' ),
					'value' => $content,
				);
			}

			$items[] = array(
				'group_id'    => 'bgcf-leads',
				'group_label' => __( 'Sign-ups and enquiries', 'bgcf' ),
				'item_id'     => 'bgcf-lead-' . $lead_id,
				'data'        => $data,
			);
		}
	}

	return array(
		'data' => $items,
		'done' => ! $more,
	);
}

/**
 * Anonymise a donor on their donation records.
 *
 * The gift itself is retained — see the file header. What is removed is
 * everything that identifies the person who made it.
 *
 * @since 1.2.0
 *
 * @param string $email Email address of the data subject.
 * @param int    $page  1-based page number.
 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
 */
function bgcf_erase_donation_data( string $email, int $page = 1 ): array {
	$email = sanitize_email( $email );
	$ids   = bgcf_privacy_find( BGCF_DONATION_POST_TYPE, '_bgcf_donor_email', $email, $page );

	foreach ( $ids as $donation_id ) {
		update_post_meta( $donation_id, '_bgcf_donor_name', '' );
		update_post_meta( $donation_id, '_bgcf_donor_email', '' );
		update_post_meta( $donation_id, '_bgcf_donor_phone', '' );
		update_post_meta( $donation_id, '_bgcf_message', '' );
		update_post_meta( $donation_id, '_bgcf_anonymised_at', time() );

		// The post title contains the donor's name, so it has to change too.
		wp_update_post(
			array(
				'ID'         => $donation_id,
				'post_title' => sprintf(
					/* translators: %s: formatted donation amount. */
					__( '%s from an anonymised donor', 'bgcf' ),
					bgcf_donation_format_amount(
						(int) bgcf_donation_get( $donation_id, 'amount', 0 ),
						(string) bgcf_donation_get( $donation_id, 'currency', '' )
					)
				),
			)
		);
	}

	$messages = array();

	if ( ! empty( $ids ) ) {
		$messages[] = __( 'Donation records were anonymised rather than deleted: the charity is required to retain the financial record of a completed gift. The amount, date and reference remain; the donor’s name, email, phone number and message have been removed.', 'bgcf' );
	}

	return array(
		'items_removed'  => ! empty( $ids ),
		'items_retained' => ! empty( $ids ),
		'messages'       => $messages,
		'done'           => count( $ids ) < 50,
	);
}

/**
 * Delete a person's sign-ups and enquiries.
 *
 * @since 1.2.0
 *
 * @param string $email Email address of the data subject.
 * @param int    $page  1-based page number.
 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
 */
function bgcf_erase_lead_data( string $email, int $page = 1 ): array {
	$email   = sanitize_email( $email );
	$removed = false;
	$more    = false;

	foreach ( array( BGCF_SUBSCRIBER_POST_TYPE, BGCF_VOLUNTEER_POST_TYPE, BGCF_MESSAGE_POST_TYPE ) as $post_type ) {
		$ids = bgcf_privacy_find( $post_type, '_bgcf_email', $email, $page );

		if ( count( $ids ) >= 50 ) {
			$more = true;
		}

		foreach ( $ids as $lead_id ) {
			// Delete the uploaded CV with the application. Leaving an orphaned
			// file in uploads is the most common way an "erased" application is
			// still readable by anyone with the URL.
			$resume = absint( bgcf_lead_get( $lead_id, 'resume', 0 ) );

			if ( $resume > 0 ) {
				wp_delete_attachment( $resume, true );
			}

			wp_delete_post( $lead_id, true );
			$removed = true;
		}
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => ! $more,
	);
}

/* -------------------------------------------------------------------------
 * Retention
 * ---------------------------------------------------------------------- */

/**
 * Cron hook that applies the retention policy.
 *
 * @since 1.2.0
 */
define( 'BGCF_RETENTION_CRON_HOOK', 'bgcf_apply_retention' );

/**
 * Schedule the retention job.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_schedule_retention(): void {
	if ( ! wp_next_scheduled( BGCF_RETENTION_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', BGCF_RETENTION_CRON_HOOK );
	}
}
add_action( 'init', 'bgcf_schedule_retention' );

/**
 * Unschedule it when the theme is switched away.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_unschedule_retention(): void {
	$timestamp = wp_next_scheduled( BGCF_RETENTION_CRON_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, BGCF_RETENTION_CRON_HOOK );
	}
}
add_action( 'switch_theme', 'bgcf_unschedule_retention' );

/**
 * Delete abandoned donation attempts and stale enquiries.
 *
 * "Keep everything forever" is not a retention policy, and an abandoned
 * donation attempt — a record with a name, an email and a phone number attached
 * to a payment that never happened — has no value to anyone after a few months.
 * Successful donations are never touched here.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_apply_retention(): void {
	/**
	 * Filters the retention windows, in days. Zero disables a rule.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, int> $policy Rule => days.
	 */
	$policy = (array) apply_filters(
		'bgcf_retention_policy',
		array(
			'abandoned_donations' => 180,
			'handled_messages'    => 730,
		)
	);

	$days = absint( $policy['abandoned_donations'] ?? 0 );

	if ( $days > 0 ) {
		$query = new WP_Query(
			array(
				'post_type'      => BGCF_DONATION_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'before' => $days . ' days ago',
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Scheduled job, bounded batch.
					'relation' => 'OR',
					array(
						'key'     => '_bgcf_status',
						'value'   => 'abandoned',
						'compare' => '=',
					),
					array(
						'key'     => '_bgcf_status',
						'value'   => 'failed',
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $query->posts as $donation_id ) {
			// Never delete a record that was ever fulfilled, whatever its status
			// says now — a sticky terminal status can only have come from a real
			// payment, and the option-based claim is the authority.
			if ( get_option( '_bgcf_fulfilled_' . (int) $donation_id ) ) {
				continue;
			}

			wp_delete_post( (int) $donation_id, true );
		}
	}

	$days = absint( $policy['handled_messages'] ?? 0 );

	if ( $days > 0 ) {
		$query = new WP_Query(
			array(
				'post_type'      => BGCF_MESSAGE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'before' => $days . ' days ago',
					),
				),
			)
		);

		foreach ( $query->posts as $message_id ) {
			wp_delete_post( (int) $message_id, true );
		}
	}
}
add_action( BGCF_RETENTION_CRON_HOOK, 'bgcf_apply_retention' );
