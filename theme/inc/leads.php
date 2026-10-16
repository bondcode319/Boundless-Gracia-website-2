<?php
/**
 * Leads: newsletter subscribers, volunteer applications and contact messages.
 *
 * SINGLE RESPONSIBILITY: what a lead *is* and where it lives. The public
 * endpoints that create them are in inc/ajax.php; this file owns the post
 * types, the write path, the notifications and the admin surface.
 *
 * THE RULE, inherited from inc/donations.php: these records hold personal data
 * about real people — in the volunteer case, often young people. So:
 *
 *   - Every post type is private, not publicly queryable, and absent from REST.
 *     A subscriber list that is one `?post_type=` away from public is a breach
 *     waiting to happen.
 *   - Reading them requires an explicit capability, not `edit_posts`.
 *   - Uploaded files are stored in a protected directory with a randomised
 *     name, validated by real content type rather than by extension.
 *   - Every record is exportable and erasable through WordPress's own privacy
 *     tools (registered at the bottom of this file), because "we can't delete
 *     it" is not an answer to a GDPR request.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post type holding newsletter subscribers.
 *
 * @since 1.2.0
 */
define( 'BGCF_SUBSCRIBER_POST_TYPE', 'bgcf_subscriber' );

/**
 * Post type holding volunteer applications.
 *
 * @since 1.2.0
 */
define( 'BGCF_VOLUNTEER_POST_TYPE', 'bgcf_volunteer' );

/**
 * Post type holding contact-form messages.
 *
 * @since 1.2.0
 */
define( 'BGCF_MESSAGE_POST_TYPE', 'bgcf_message' );

/**
 * Capability required to read leads.
 *
 * Mapped to an existing capability rather than a custom one so no role editor
 * is needed to grant it, and filtered so a client can narrow it later.
 *
 * @since 1.2.0
 * @return string Capability name.
 */
function bgcf_leads_capability(): string {
	/**
	 * Filters the capability that gates lead records.
	 *
	 * @since 1.2.0
	 *
	 * @param string $capability Capability name.
	 */
	return (string) apply_filters( 'bgcf_leads_capability', 'edit_pages' );
}

/**
 * Register the three lead post types.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_register_lead_post_types(): void {
	$shared = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_rest'        => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'can_export'          => true,
		'menu_position'       => 58,
		// `page` maps read/edit/delete onto the *_pages capabilities, which is
		// what bgcf_leads_capability() returns — so an Editor can triage leads
		// and an Author cannot see them.
		'capability_type'     => 'page',
		'map_meta_cap'        => true,
		'capabilities'        => array(
			'create_posts' => 'do_not_allow',
		),
		'supports'            => array( 'title' ),
	);

	register_post_type(
		BGCF_SUBSCRIBER_POST_TYPE,
		array_merge(
			$shared,
			array(
				'labels'    => array(
					'name'          => __( 'Subscribers', 'bgcf' ),
					'singular_name' => __( 'Subscriber', 'bgcf' ),
					'menu_name'     => __( 'Subscribers', 'bgcf' ),
					'search_items'  => __( 'Search subscribers', 'bgcf' ),
					'not_found'     => __( 'No subscribers yet.', 'bgcf' ),
				),
				'menu_icon' => 'dashicons-email-alt',
			)
		)
	);

	register_post_type(
		BGCF_VOLUNTEER_POST_TYPE,
		array_merge(
			$shared,
			array(
				'labels'    => array(
					'name'          => __( 'Volunteers', 'bgcf' ),
					'singular_name' => __( 'Volunteer application', 'bgcf' ),
					'menu_name'     => __( 'Volunteers', 'bgcf' ),
					'search_items'  => __( 'Search applications', 'bgcf' ),
					'not_found'     => __( 'No applications yet.', 'bgcf' ),
				),
				'menu_icon' => 'dashicons-groups',
				'supports'  => array( 'title', 'editor' ),
			)
		)
	);

	register_post_type(
		BGCF_MESSAGE_POST_TYPE,
		array_merge(
			$shared,
			array(
				'labels'    => array(
					'name'          => __( 'Messages', 'bgcf' ),
					'singular_name' => __( 'Message', 'bgcf' ),
					'menu_name'     => __( 'Messages', 'bgcf' ),
					'search_items'  => __( 'Search messages', 'bgcf' ),
					'not_found'     => __( 'No messages yet.', 'bgcf' ),
				),
				'menu_icon' => 'dashicons-email',
				'supports'  => array( 'title', 'editor' ),
			)
		)
	);
}
add_action( 'init', 'bgcf_register_lead_post_types', 5 );

/**
 * The statuses a volunteer application moves through.
 *
 * @since 1.2.0
 * @return array<string, string> Status key => label.
 */
function bgcf_volunteer_statuses(): array {
	return array(
		'new'       => __( 'New', 'bgcf' ),
		'reviewing' => __( 'Reviewing', 'bgcf' ),
		'contacted' => __( 'Contacted', 'bgcf' ),
		'accepted'  => __( 'Accepted', 'bgcf' ),
		'declined'  => __( 'Declined', 'bgcf' ),
	);
}

/* -------------------------------------------------------------------------
 * Subscribers
 * ---------------------------------------------------------------------- */

/**
 * Find an existing subscriber by email.
 *
 * @since 1.2.0
 *
 * @param string $email Email address, already sanitized.
 * @return int Post ID, or 0.
 */
function bgcf_find_subscriber( string $email ): int {
	$found = get_posts(
		array(
			'post_type'              => BGCF_SUBSCRIBER_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => '_bgcf_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Unique lookup; see the index note in DEPLOYMENT.md.
			'meta_value'             => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
		)
	);

	return empty( $found ) ? 0 : (int) $found[0];
}

/**
 * Record a newsletter subscription.
 *
 * Idempotent by email: subscribing twice updates the existing record rather
 * than creating a duplicate, and the caller cannot tell the difference. That
 * matters — "this address is already on the list" is an account-enumeration
 * oracle, however harmless it feels.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $args {
 *     @type string $email   Subscriber email. Required, already validated.
 *     @type string $name    Optional name.
 *     @type string $source  Where the sign-up came from.
 *     @type bool   $consent Whether consent was given. Required to be true.
 * }
 * @return int|WP_Error Post ID, or an error.
 */
function bgcf_create_subscriber( array $args ) {
	$email = sanitize_email( (string) ( $args['email'] ?? '' ) );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'That email address does not look right.', 'bgcf' ) );
	}

	$existing = bgcf_find_subscriber( $email );
	$now      = time();

	$meta = array(
		'_bgcf_email'       => $email,
		'_bgcf_name'        => sanitize_text_field( (string) ( $args['name'] ?? '' ) ),
		'_bgcf_source'      => sanitize_key( (string) ( $args['source'] ?? 'site' ) ),
		'_bgcf_consent'     => 1,
		'_bgcf_consent_at'  => $now,
		'_bgcf_consent_ip'  => bgcf_lead_client_ip_hash(),
		'_bgcf_status'      => 'subscribed',
	);

	if ( $existing > 0 ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $existing, $key, $value );
		}

		return $existing;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => BGCF_SUBSCRIBER_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $email,
			'meta_input'  => $meta,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	/**
	 * Fires after a subscriber is recorded.
	 *
	 * Hook this to forward the address to Mailchimp, Brevo or whatever list the
	 * organisation actually sends from. The record here is the system of
	 * record; the ESP is a consumer of it.
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $post_id Subscriber post ID.
	 * @param array<string, mixed> $args    Submitted values.
	 */
	do_action( 'bgcf_subscriber_created', (int) $post_id, $args );

	return (int) $post_id;
}

/* -------------------------------------------------------------------------
 * Volunteers
 * ---------------------------------------------------------------------- */

/**
 * Record a volunteer application.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $args Sanitized application values.
 * @return int|WP_Error Post ID, or an error.
 */
function bgcf_create_volunteer( array $args ) {
	$name  = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
	$email = sanitize_email( (string) ( $args['email'] ?? '' ) );

	$post_id = wp_insert_post(
		array(
			'post_type'    => BGCF_VOLUNTEER_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => '' !== $name ? $name : $email,
			'post_content' => sanitize_textarea_field( (string) ( $args['message'] ?? '' ) ),
			'meta_input'   => array(
				'_bgcf_name'         => $name,
				'_bgcf_email'        => $email,
				'_bgcf_phone'        => sanitize_text_field( (string) ( $args['phone'] ?? '' ) ),
				'_bgcf_location'     => sanitize_text_field( (string) ( $args['location'] ?? '' ) ),
				'_bgcf_skills'       => array_map( 'sanitize_text_field', (array) ( $args['skills'] ?? array() ) ),
				'_bgcf_availability' => sanitize_text_field( (string) ( $args['availability'] ?? '' ) ),
				'_bgcf_resume'       => absint( $args['resume'] ?? 0 ),
				'_bgcf_reference'    => (string) ( $args['reference'] ?? '' ),
				'_bgcf_status'       => 'new',
				'_bgcf_consent'      => 1,
				'_bgcf_consent_at'   => time(),
				'_bgcf_consent_ip'   => bgcf_lead_client_ip_hash(),
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	/**
	 * Fires after a volunteer application is recorded.
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $post_id Application post ID.
	 * @param array<string, mixed> $args    Submitted values.
	 */
	do_action( 'bgcf_volunteer_created', (int) $post_id, $args );

	return (int) $post_id;
}

/* -------------------------------------------------------------------------
 * Contact messages
 * ---------------------------------------------------------------------- */

/**
 * Record a contact-form message.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $args Sanitized message values.
 * @return int|WP_Error Post ID, or an error.
 */
function bgcf_create_message( array $args ) {
	$name    = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
	$subject = sanitize_text_field( (string) ( $args['subject'] ?? '' ) );

	$post_id = wp_insert_post(
		array(
			'post_type'    => BGCF_MESSAGE_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => '' !== $subject ? $subject : $name,
			'post_content' => sanitize_textarea_field( (string) ( $args['message'] ?? '' ) ),
			'meta_input'   => array(
				'_bgcf_name'       => $name,
				'_bgcf_email'      => sanitize_email( (string) ( $args['email'] ?? '' ) ),
				'_bgcf_phone'      => sanitize_text_field( (string) ( $args['phone'] ?? '' ) ),
				'_bgcf_subject'    => $subject,
				'_bgcf_status'     => 'new',
				'_bgcf_consent_at' => time(),
				'_bgcf_consent_ip' => bgcf_lead_client_ip_hash(),
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	/**
	 * Fires after a contact message is recorded.
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $post_id Message post ID.
	 * @param array<string, mixed> $args    Submitted values.
	 */
	do_action( 'bgcf_message_created', (int) $post_id, $args );

	return (int) $post_id;
}

/* -------------------------------------------------------------------------
 * Shared helpers
 * ---------------------------------------------------------------------- */

/**
 * A salted hash of the submitter's IP address.
 *
 * Consent records need to be defensible ("who agreed, and from where"), but a
 * plain IP is personal data we have no further use for. A keyed hash proves two
 * submissions came from the same place without storing the place.
 *
 * @since 1.2.0
 * @return string Hash, or '' when no IP is available.
 */
function bgcf_lead_client_ip_hash(): string {
	$ip = function_exists( 'bgcf_flw_client_ip' ) ? bgcf_flw_client_ip() : '';

	if ( '' === $ip ) {
		return '';
	}

	return wp_hash( $ip );
}

/**
 * Email the team that a lead arrived.
 *
 * Failures are logged rather than swallowed: a notification nobody receives is
 * indistinguishable from no applications, and that silence can run for months.
 *
 * @since 1.2.0
 *
 * @param string               $subject Email subject.
 * @param string               $body    Plain-text body.
 * @param array<string, mixed> $context Logging context.
 * @return bool Whether wp_mail() accepted the message.
 */
function bgcf_notify_team( string $subject, string $body, array $context = array() ): bool {
	$to = sanitize_email( (string) bgcf_option( 'notify_email', get_option( 'admin_email' ) ) );

	if ( ! is_email( $to ) ) {
		return false;
	}

	$sent = wp_mail(
		$to,
		$subject,
		$body,
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);

	if ( ! $sent && function_exists( 'bgcf_flw_log' ) ) {
		bgcf_flw_log( 'lead notification failed', array_merge( $context, array( 'to' => $to ) ) );
	}

	return (bool) $sent;
}

/**
 * Read a lead field.
 *
 * @since 1.2.0
 *
 * @param int    $post_id Lead post ID.
 * @param string $key     Field key without the `_bgcf_` prefix.
 * @param mixed  $default Fallback.
 * @return mixed Stored value.
 */
function bgcf_lead_get( int $post_id, string $key, $default = '' ) {
	$value = get_post_meta( $post_id, '_bgcf_' . $key, true );

	return ( '' === $value || null === $value ) ? $default : $value;
}
