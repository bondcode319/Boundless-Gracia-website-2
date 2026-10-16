<?php
/**
 * wp-admin screens for leads, plus CSV export for leads and donations.
 *
 * WHAT THIS IS FOR. A list table that shows a post title and a date is useless
 * for triage: nobody can work a volunteer queue without seeing the email, the
 * skills and the status. So every lead type gets real columns, a status
 * dropdown that saves without opening the record, and an export.
 *
 * WHY EXPORT MATTERS MORE THAN IT LOOKS. A charity's finance and fundraising
 * work happens in spreadsheets. Without an export, the only routes to the data
 * are "ask a developer" and "copy 400 rows by hand" — which is how donor data
 * ends up in someone's personal Google Drive. One authenticated,
 * capability-checked, nonce-verified download is the safer option.
 *
 * Every export is capability-checked, nonce-verified, streamed (so a large list
 * cannot exhaust memory) and logged.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The lead post types this module manages, mapped to their columns.
 *
 * @since 1.2.0
 * @return array<string, array<string, string>> Post type => column key => label.
 */
function bgcf_lead_admin_columns(): array {
	return array(
		BGCF_SUBSCRIBER_POST_TYPE => array(
			'bgcf_email'  => __( 'Email', 'bgcf' ),
			'bgcf_name'   => __( 'Name', 'bgcf' ),
			'bgcf_source' => __( 'Signed up from', 'bgcf' ),
		),
		BGCF_VOLUNTEER_POST_TYPE  => array(
			'bgcf_email'      => __( 'Email', 'bgcf' ),
			'bgcf_phone'      => __( 'Phone', 'bgcf' ),
			'bgcf_location'   => __( 'Location', 'bgcf' ),
			'bgcf_skills'     => __( 'Skills', 'bgcf' ),
			'bgcf_resume'     => __( 'CV', 'bgcf' ),
			'bgcf_status'     => __( 'Status', 'bgcf' ),
			'bgcf_reference'  => __( 'Reference', 'bgcf' ),
		),
		BGCF_MESSAGE_POST_TYPE    => array(
			'bgcf_email'   => __( 'Email', 'bgcf' ),
			'bgcf_phone'   => __( 'Phone', 'bgcf' ),
			'bgcf_subject' => __( 'Subject', 'bgcf' ),
			'bgcf_status'  => __( 'Status', 'bgcf' ),
		),
	);
}

/**
 * Register the columns for each lead type.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_lead_admin_setup(): void {
	foreach ( bgcf_lead_admin_columns() as $post_type => $columns ) {
		add_filter(
			"manage_{$post_type}_posts_columns",
			static function ( array $existing ) use ( $columns ): array {
				// Keep the title first and the date last; insert ours between.
				$title = array_slice( $existing, 0, 2, true );
				$rest  = array_slice( $existing, 2, null, true );

				return array_merge( $title, $columns, $rest );
			}
		);

		add_action(
			"manage_{$post_type}_posts_custom_column",
			'bgcf_lead_admin_render_column',
			10,
			2
		);
	}
}
add_action( 'admin_init', 'bgcf_lead_admin_setup' );

/**
 * Render one lead column.
 *
 * @since 1.2.0
 *
 * @param string $column  Column key.
 * @param int    $post_id Lead post ID.
 * @return void
 */
function bgcf_lead_admin_render_column( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'bgcf_email':
			$email = (string) bgcf_lead_get( $post_id, 'email' );

			if ( '' !== $email ) {
				printf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
			}
			break;

		case 'bgcf_skills':
			$skills = (array) bgcf_lead_get( $post_id, 'skills', array() );
			echo esc_html( implode( ', ', array_map( 'strval', $skills ) ) );
			break;

		case 'bgcf_resume':
			$resume = absint( bgcf_lead_get( $post_id, 'resume', 0 ) );

			if ( $resume > 0 ) {
				printf(
					'<a href="%1$s" download>%2$s</a>',
					esc_url( (string) wp_get_attachment_url( $resume ) ),
					esc_html__( 'Download', 'bgcf' )
				);
			} else {
				echo '—';
			}
			break;

		case 'bgcf_status':
			$status   = (string) bgcf_lead_get( $post_id, 'status', 'new' );
			$statuses = bgcf_volunteer_statuses();

			printf(
				'<span class="bgcf-status bgcf-status--%1$s">%2$s</span>',
				esc_attr( $status ),
				esc_html( (string) ( $statuses[ $status ] ?? $status ) )
			);
			break;

		default:
			// Every other column is a plain meta value named after the column.
			echo esc_html( (string) bgcf_lead_get( $post_id, str_replace( 'bgcf_', '', $column ) ) );
			break;
	}
}

/**
 * A status selector on the volunteer and message edit screens.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_lead_status_metabox(): void {
	foreach ( array( BGCF_VOLUNTEER_POST_TYPE, BGCF_MESSAGE_POST_TYPE ) as $post_type ) {
		add_meta_box(
			'bgcf-lead-status',
			__( 'Status', 'bgcf' ),
			'bgcf_lead_status_metabox_render',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'bgcf_lead_status_metabox' );

/**
 * Render the status selector.
 *
 * @since 1.2.0
 *
 * @param WP_Post $post Lead post.
 * @return void
 */
function bgcf_lead_status_metabox_render( WP_Post $post ): void {
	$current  = (string) bgcf_lead_get( (int) $post->ID, 'status', 'new' );
	$statuses = bgcf_volunteer_statuses();

	wp_nonce_field( 'bgcf_lead_status', 'bgcf_lead_status_nonce' );

	echo '<label class="screen-reader-text" for="bgcf-lead-status-field">' . esc_html__( 'Status', 'bgcf' ) . '</label>';
	echo '<select name="bgcf_lead_status" id="bgcf-lead-status-field" class="widefat">';

	foreach ( $statuses as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $current, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
}

/**
 * Save the status selector.
 *
 * @since 1.2.0
 *
 * @param int $post_id Lead post ID.
 * @return void
 */
function bgcf_lead_status_save( int $post_id ): void {
	if ( ! isset( $_POST['bgcf_lead_status_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['bgcf_lead_status_nonce'] ) ), 'bgcf_lead_status' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['bgcf_lead_status'] ) ) {
		return;
	}

	$status = sanitize_key( wp_unslash( (string) $_POST['bgcf_lead_status'] ) );

	if ( ! array_key_exists( $status, bgcf_volunteer_statuses() ) ) {
		return;
	}

	$previous = (string) bgcf_lead_get( $post_id, 'status', 'new' );

	if ( $previous === $status ) {
		return;
	}

	update_post_meta( $post_id, '_bgcf_status', $status );

	// An audit line, because "who moved this application to declined, and when"
	// is a question that gets asked.
	$log   = (array) get_post_meta( $post_id, '_bgcf_status_log', true );
	$log[] = array(
		'from' => $previous,
		'to'   => $status,
		'user' => get_current_user_id(),
		'time' => time(),
	);

	update_post_meta( $post_id, '_bgcf_status_log', array_slice( $log, -50 ) );
}
add_action( 'save_post', 'bgcf_lead_status_save' );

/* -------------------------------------------------------------------------
 * CSV export
 * ---------------------------------------------------------------------- */

/**
 * The export definitions: which post type, which columns, which capability.
 *
 * @since 1.2.0
 * @return array<string, array<string, mixed>> Export key => definition.
 */
function bgcf_export_definitions(): array {
	return array(
		'subscribers' => array(
			'post_type'  => BGCF_SUBSCRIBER_POST_TYPE,
			'capability' => bgcf_leads_capability(),
			'columns'    => array(
				'email'      => __( 'Email', 'bgcf' ),
				'name'       => __( 'Name', 'bgcf' ),
				'source'     => __( 'Source', 'bgcf' ),
				'consent_at' => __( 'Consent given', 'bgcf' ),
			),
		),
		'volunteers'  => array(
			'post_type'  => BGCF_VOLUNTEER_POST_TYPE,
			'capability' => bgcf_leads_capability(),
			'columns'    => array(
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
		'messages'    => array(
			'post_type'  => BGCF_MESSAGE_POST_TYPE,
			'capability' => bgcf_leads_capability(),
			'columns'    => array(
				'name'    => __( 'Name', 'bgcf' ),
				'email'   => __( 'Email', 'bgcf' ),
				'phone'   => __( 'Phone', 'bgcf' ),
				'subject' => __( 'Subject', 'bgcf' ),
				'status'  => __( 'Status', 'bgcf' ),
			),
		),
		'donations'   => array(
			'post_type'  => BGCF_DONATION_POST_TYPE,
			'capability' => bgcf_donations_capability(),
			'columns'    => array(
				'tx_ref'      => __( 'Reference', 'bgcf' ),
				'status'      => __( 'Status', 'bgcf' ),
				'amount'      => __( 'Gift', 'bgcf' ),
				'fee'         => __( 'Fee covered', 'bgcf' ),
				'charged'     => __( 'Charged', 'bgcf' ),
				'currency'    => __( 'Currency', 'bgcf' ),
				'method'      => __( 'Method', 'bgcf' ),
				'network'     => __( 'Network', 'bgcf' ),
				'donor_name'  => __( 'Donor', 'bgcf' ),
				'donor_email' => __( 'Email', 'bgcf' ),
				'campaign'    => __( 'Campaign ID', 'bgcf' ),
				'mode'        => __( 'Mode', 'bgcf' ),
				'created_gmt' => __( 'Created', 'bgcf' ),
				'verified_at' => __( 'Verified', 'bgcf' ),
			),
		),
	);
}

/**
 * Add an export button above each list table.
 *
 * @since 1.2.0
 *
 * @param string $which Position: 'top' or 'bottom'.
 * @return void
 */
function bgcf_export_button( string $which ): void {
	if ( 'top' !== $which ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	foreach ( bgcf_export_definitions() as $key => $definition ) {
		if ( $definition['post_type'] !== $screen->post_type ) {
			continue;
		}

		if ( ! current_user_can( (string) $definition['capability'] ) ) {
			return;
		}

		printf(
			'<a class="button" href="%1$s">%2$s</a> ',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=bgcf_export&export=' . $key ),
					'bgcf_export_' . $key
				)
			),
			esc_html__( 'Export CSV', 'bgcf' )
		);

		return;
	}
}
add_action( 'manage_posts_extra_tablenav', 'bgcf_export_button' );

/**
 * Stream a CSV export.
 *
 * Streamed in batches through `php://output` rather than assembled in a string:
 * a charity with 50,000 subscribers would otherwise exhaust the memory limit,
 * and the failure mode of that is a blank page with no explanation.
 *
 * @since 1.2.0
 * @return void Exits.
 */
function bgcf_export_handle(): void {
	$key         = isset( $_GET['export'] ) ? sanitize_key( wp_unslash( (string) $_GET['export'] ) ) : '';
	$definitions = bgcf_export_definitions();

	if ( ! isset( $definitions[ $key ] ) ) {
		wp_die( esc_html__( 'Unknown export.', 'bgcf' ), '', array( 'response' => 400 ) );
	}

	$definition = $definitions[ $key ];

	if ( ! current_user_can( (string) $definition['capability'] ) ) {
		wp_die( esc_html__( 'You do not have permission to export this data.', 'bgcf' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'bgcf_export_' . $key );

	/**
	 * Fires before a data export is streamed.
	 *
	 * Personal data is leaving the system, so the event is worth recording even
	 * though the request was authorised.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key     Export key.
	 * @param int    $user_id Who exported it.
	 */
	do_action( 'bgcf_data_exported', $key, get_current_user_id() );

	$filename = sprintf( 'bgcf-%s-%s.csv', $key, wp_date( 'Y-m-d' ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$output = fopen( 'php://output', 'w' );

	if ( false === $output ) {
		wp_die( esc_html__( 'Could not start the export.', 'bgcf' ) );
	}

	// UTF-8 BOM: without it Excel on Windows mangles every non-ASCII name, and
	// Ugandan names are full of characters it gets wrong.
	fwrite( $output, "\xEF\xBB\xBF" );

	fputcsv( $output, array_values( (array) $definition['columns'] ) );

	$paged    = 1;
	$per_page = 200;

	do {
		$query = new WP_Query(
			array(
				'post_type'              => (string) $definition['post_type'],
				'post_status'            => 'any',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			break;
		}

		foreach ( $query->posts as $post ) {
			$row = array();

			foreach ( array_keys( (array) $definition['columns'] ) as $field ) {
				$value = get_post_meta( (int) $post->ID, '_bgcf_' . $field, true );

				if ( is_array( $value ) ) {
					$value = implode( '; ', array_map( 'strval', $value ) );
				}

				// Timestamps are stored as epoch integers and are unreadable in a
				// spreadsheet; convert the ones that are dates.
				if ( in_array( $field, array( 'created_gmt', 'verified_at', 'consent_at' ), true ) && is_numeric( $value ) && (int) $value > 0 ) {
					$value = wp_date( 'Y-m-d H:i', (int) $value );
				}

				$row[] = bgcf_csv_escape( (string) $value );
			}

			fputcsv( $output, $row );
		}

		// Free the post cache between batches, or streaming 50,000 rows still
		// ends in an out-of-memory error — just more slowly.
		wp_cache_flush();
		++$paged;
	} while ( true );

	fclose( $output );
	exit;
}
add_action( 'admin_post_bgcf_export', 'bgcf_export_handle' );

/**
 * Neutralise a CSV formula injection.
 *
 * A donor name of `=HYPERLINK("http://evil","Click")` is executed by Excel and
 * Google Sheets when the export is opened. Prefixing the dangerous leading
 * characters with a single quote renders the cell as text. The data we export
 * came from public forms, so this is not hypothetical.
 *
 * @since 1.2.0
 *
 * @param string $value Cell value.
 * @return string Safe cell value.
 */
function bgcf_csv_escape( string $value ): string {
	if ( '' === $value ) {
		return $value;
	}

	return in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
}
