<?php
/**
 * Donations in wp-admin: settings screen, list table, record detail.
 *
 * SINGLE RESPONSIBILITY: the administrator's view of the payment flow. No money
 * logic lives here — this screen reads state and writes the non-secret settings,
 * nothing else.
 *
 * Secrets are intentionally NOT editable here. The settings screen reports
 * whether each credential is present and which mode the key implies, but the
 * values themselves only ever come from wp-config.php or the environment (see
 * inc/payments.php). A "paste your secret key" field is how a secret ends up in
 * a database backup, a staging clone and eventually a support ticket.
 *
 * @package Bgcf
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the donation settings screen.
 *
 * Sits under the Donations menu, so the records and the configuration that
 * produced them are one click apart.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_admin_menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . BGCF_DONATION_POST_TYPE,
		__( 'Donation Settings', 'bgcf' ),
		__( 'Settings', 'bgcf' ),
		bgcf_donations_capability(),
		'bgcf-donation-settings',
		'bgcf_donations_render_settings_page'
	);
}
add_action( 'admin_menu', 'bgcf_donations_admin_menu' );

/**
 * Register the settings, sections and fields.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_register_settings(): void {
	register_setting(
		'bgcf_donation_settings_group',
		BGCF_DONATION_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bgcf_donations_sanitize_settings',
			'default'           => array(),
		)
	);

	add_settings_section(
		'bgcf_donation_amounts',
		__( 'Amounts', 'bgcf' ),
		'bgcf_donations_amounts_section_intro',
		'bgcf-donation-settings'
	);

	add_settings_section(
		'bgcf_donation_methods_section',
		__( 'Payment methods', 'bgcf' ),
		'bgcf_donations_methods_section_intro',
		'bgcf-donation-settings'
	);

	add_settings_section(
		'bgcf_donation_followup',
		__( 'After the donation', 'bgcf' ),
		'__return_false',
		'bgcf-donation-settings'
	);

	$fields = array(
		'currency'        => array( __( 'Currency', 'bgcf' ), 'bgcf_donation_amounts', 'text' ),
		'amounts'         => array( __( 'Suggested amounts', 'bgcf' ), 'bgcf_donation_amounts', 'amounts' ),
		'default_amount'  => array( __( 'Pre-selected amount', 'bgcf' ), 'bgcf_donation_amounts', 'number' ),
		'min_amount'      => array( __( 'Minimum amount', 'bgcf' ), 'bgcf_donation_amounts', 'number' ),
		'max_amount'      => array( __( 'Maximum amount', 'bgcf' ), 'bgcf_donation_amounts', 'number' ),
		'enable_mobile'   => array( __( 'Mobile money', 'bgcf' ), 'bgcf_donation_methods_section', 'checkbox' ),
		'enable_card'     => array( __( 'Cards', 'bgcf' ), 'bgcf_donation_methods_section', 'checkbox' ),
		'checkout_title'  => array( __( 'Checkout title', 'bgcf' ), 'bgcf_donation_methods_section', 'text' ),
		'thank_you_page'  => array( __( 'Thank-you page', 'bgcf' ), 'bgcf_donation_followup', 'page' ),
		'receipt_enabled' => array( __( 'Email the donor a receipt', 'bgcf' ), 'bgcf_donation_followup', 'checkbox' ),
		'notify_enabled'  => array( __( 'Notify the team', 'bgcf' ), 'bgcf_donation_followup', 'checkbox' ),
		'notify_email'    => array( __( 'Notification address', 'bgcf' ), 'bgcf_donation_followup', 'email' ),
	);

	foreach ( $fields as $key => $field ) {
		list( $label, $section, $type ) = $field;

		add_settings_field(
			'bgcf_donation_' . $key,
			$label,
			'bgcf_donations_render_field',
			'bgcf-donation-settings',
			$section,
			array(
				'key'       => $key,
				'type'      => $type,
				'label_for' => 'bgcf_donation_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'bgcf_donations_register_settings' );

/**
 * Intro copy for the amounts section.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_amounts_section_intro(): void {
	echo '<p>' . esc_html__( 'Amounts are in whole currency units — Ugandan shillings have no cents, so enter 50000, not 50000.00.', 'bgcf' ) . '</p>';
}

/**
 * Intro copy for the methods section.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_methods_section_intro(): void {
	echo '<p>' . esc_html__( 'In Ugandan shillings Flutterwave settles mobile money and cards only. Bank transfer, USSD and virtual accounts are not available in UGX, so they are not offered.', 'bgcf' ) . '</p>';
}

/**
 * Render a settings field.
 *
 * @since 1.1.0
 *
 * @param array<string, string> $args Field arguments: key, type, label_for.
 * @return void
 */
function bgcf_donations_render_field( array $args ): void {
	$key      = (string) $args['key'];
	$type     = (string) $args['type'];
	$id       = 'bgcf_donation_' . $key;
	$name     = BGCF_DONATION_OPTION . '[' . $key . ']';
	$settings = bgcf_donation_settings();
	$value    = $settings[ $key ] ?? '';

	switch ( $type ) {
		case 'checkbox':
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			break;

		case 'amounts':
			printf(
				'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" /><p class="description">%4$s</p>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( implode( ', ', array_map( 'absint', (array) $value ) ) ),
				esc_html__( 'Comma separated, for example: 20000, 50000, 100000, 250000', 'bgcf' )
			);
			break;

		case 'number':
			printf(
				'<input type="number" min="0" step="1" class="regular-text" id="%1$s" name="%2$s" value="%3$s" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) absint( $value ) )
			);
			break;

		case 'page':
			wp_dropdown_pages(
				array(
					'name'              => $name,
					'id'                => $id,
					'selected'          => absint( $value ),
					'show_option_none'  => __( '— Use the home page —', 'bgcf' ),
					'option_none_value' => '0',
				)
			);
			break;

		case 'email':
			printf(
				'<input type="email" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( (string) get_option( 'admin_email' ) )
			);
			break;

		default:
			printf(
				'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
	}
}

/**
 * Sanitize the settings before they are stored.
 *
 * Every value is coerced to its expected type here; the front end reads these
 * back through bgcf_donation_setting() without re-checking.
 *
 * @since 1.1.0
 *
 * @param mixed $input Raw submitted values.
 * @return array<string, mixed> Clean settings.
 */
function bgcf_donations_sanitize_settings( $input ): array {
	$input = is_array( $input ) ? $input : array();

	$amounts = array();

	if ( isset( $input['amounts'] ) ) {
		$raw     = is_array( $input['amounts'] ) ? $input['amounts'] : explode( ',', (string) $input['amounts'] );
		$amounts = array_values( array_filter( array_map( 'absint', $raw ) ) );
		sort( $amounts );
	}

	$currency = strtoupper( sanitize_text_field( (string) ( $input['currency'] ?? 'UGX' ) ) );
	$currency = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'UGX';

	$min = absint( $input['min_amount'] ?? 1000 );
	$max = absint( $input['max_amount'] ?? 20000000 );

	if ( $max > 0 && $max < $min ) {
		// A max below the min would reject every donation. Swap rather than save
		// a configuration that silently breaks giving.
		list( $min, $max ) = array( $max, $min );
	}

	return array(
		'currency'        => $currency,
		'amounts'         => $amounts,
		'default_amount'  => absint( $input['default_amount'] ?? 0 ),
		'min_amount'      => max( 1, $min ),
		'max_amount'      => max( 1, $max ),
		'enable_mobile'   => ! empty( $input['enable_mobile'] ),
		'enable_card'     => ! empty( $input['enable_card'] ),
		'thank_you_page'  => absint( $input['thank_you_page'] ?? 0 ),
		'checkout_title'  => sanitize_text_field( (string) ( $input['checkout_title'] ?? '' ) ),
		'receipt_enabled' => ! empty( $input['receipt_enabled'] ),
		'notify_enabled'  => ! empty( $input['notify_enabled'] ),
		'notify_email'    => sanitize_email( (string) ( $input['notify_email'] ?? '' ) ),
	);
}

/**
 * Render the settings screen.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_render_settings_page(): void {
	if ( ! current_user_can( bgcf_donations_capability() ) ) {
		return;
	}

	$mode       = bgcf_flw_mode();
	$configured = bgcf_flw_is_configured();
	$next_run   = wp_next_scheduled( BGCF_DONATION_CRON_HOOK );

	$credentials = array(
		'BGCF_FLW_PUBLIC_KEY'  => bgcf_flw_credential( 'public_key' ),
		'BGCF_FLW_SECRET_KEY'  => bgcf_flw_credential( 'secret_key' ),
		'BGCF_FLW_SECRET_HASH' => bgcf_flw_credential( 'secret_hash' ),
	);

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Donation Settings', 'bgcf' ); ?></h1>

		<h2><?php esc_html_e( 'Gateway status', 'bgcf' ); ?></h2>

		<?php if ( 'test' === $mode ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'Sandbox (test) mode.', 'bgcf' ); ?></strong>
					<?php esc_html_e( 'The configured secret key is a test key, so no real money can move. Swap in live keys and re-register the webhook for live mode before launch.', 'bgcf' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-success inline">
				<p><strong><?php esc_html_e( 'Live mode. Real payments are being taken.', 'bgcf' ); ?></strong></p>
			</div>
		<?php endif; ?>

		<?php if ( ! $configured ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'Giving is switched off until the public key, secret key and webhook secret hash are all defined in wp-config.php. See PAYMENTS.md.', 'bgcf' ); ?></p>
			</div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:52rem;margin-bottom:2rem;">
			<tbody>
				<?php foreach ( $credentials as $constant => $value ) : ?>
					<tr>
						<th scope="row" style="width:16rem;"><code><?php echo esc_html( $constant ); ?></code></th>
						<td>
							<?php if ( '' === $value ) : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'Not set', 'bgcf' ); ?></span>
							<?php else : ?>
								<?php
								// Fingerprint only: enough to confirm which key is loaded,
								// never enough to use it.
								echo esc_html( substr( $value, 0, 12 ) . '…' . substr( $value, -4 ) );
								?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'bgcf' ); ?></th>
					<td>
						<input type="text" class="large-text code" readonly value="<?php echo esc_attr( bgcf_donation_webhook_url() ); ?>" onfocus="this.select()" />
						<p class="description"><?php esc_html_e( 'Paste this into Flutterwave → Settings → Webhooks, with the same secret hash, for BOTH test and live mode.', 'bgcf' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Return URL', 'bgcf' ); ?></th>
					<td><code><?php echo esc_html( bgcf_donation_return_url() ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reconciliation', 'bgcf' ); ?></th>
					<td>
						<?php if ( $next_run ) : ?>
							<?php
							printf(
								/* translators: %s: human-readable time difference. */
								esc_html__( 'Next pending-donation check in %s.', 'bgcf' ),
								esc_html( human_time_diff( time(), (int) $next_run ) )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Not scheduled. Check that WP-Cron is running.', 'bgcf' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<form action="options.php" method="post">
			<?php
			settings_fields( 'bgcf_donation_settings_group' );
			do_settings_sections( 'bgcf-donation-settings' );
			submit_button( __( 'Save settings', 'bgcf' ) );
			?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Donation list table
 * ---------------------------------------------------------------------- */

/**
 * Columns for the donations list.
 *
 * @since 1.1.0
 *
 * @param array<string, string> $columns Default columns.
 * @return array<string, string> Filtered columns.
 */
function bgcf_donations_columns( array $columns ): array {
	return array(
		'cb'             => $columns['cb'] ?? '',
		'bgcf_amount'    => __( 'Amount', 'bgcf' ),
		'bgcf_status'    => __( 'Status', 'bgcf' ),
		'bgcf_donor'     => __( 'Donor', 'bgcf' ),
		'bgcf_method'    => __( 'Method', 'bgcf' ),
		'bgcf_reference' => __( 'Reference', 'bgcf' ),
		'bgcf_mode'      => __( 'Mode', 'bgcf' ),
		'date'           => $columns['date'] ?? __( 'Date', 'bgcf' ),
	);
}
add_filter( 'manage_' . BGCF_DONATION_POST_TYPE . '_posts_columns', 'bgcf_donations_columns' );

/**
 * Render a donations list column.
 *
 * @since 1.1.0
 *
 * @param string $column  Column key.
 * @param int    $post_id Donation post ID.
 * @return void
 */
function bgcf_donations_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'bgcf_amount':
			echo '<strong>' . esc_html(
				bgcf_donation_format_amount(
					(int) bgcf_donation_get( $post_id, 'amount', 0 ),
					(string) bgcf_donation_get( $post_id, 'currency', '' )
				)
			) . '</strong>';
			break;

		case 'bgcf_status':
			$status = bgcf_donation_status( $post_id );
			$labels = bgcf_donation_statuses();
			$colors = array(
				'successful' => '#1E8A5A',
				'pending'    => '#F26A21',
				'failed'     => '#b32d2e',
				'abandoned'  => '#646970',
			);

			printf(
				'<span style="color:%1$s;font-weight:600;">%2$s</span>',
				esc_attr( $colors[ $status ] ?? '#1A2430' ),
				esc_html( $labels[ $status ] ?? $status )
			);

			if ( bgcf_donation_get( $post_id, 'mismatch', 0 ) ) {
				echo '<br /><span style="color:#b32d2e;">' . esc_html__( 'Needs review — provider data did not match this record.', 'bgcf' ) . '</span>';
			}
			break;

		case 'bgcf_donor':
			$name  = (string) bgcf_donation_get( $post_id, 'donor_name' );
			$email = (string) bgcf_donation_get( $post_id, 'donor_email' );

			echo esc_html( '' !== $name ? $name : __( 'Not given', 'bgcf' ) );

			if ( '' !== $email ) {
				echo '<br /><span class="description">' . esc_html( $email ) . '</span>';
			}

			if ( bgcf_donation_get( $post_id, 'anonymous', 0 ) ) {
				echo '<br /><em>' . esc_html__( 'Anonymous publicly', 'bgcf' ) . '</em>';
			}
			break;

		case 'bgcf_method':
			$method  = (string) bgcf_donation_get( $post_id, 'method' );
			$network = (string) bgcf_donation_get( $post_id, 'network' );

			echo esc_html( 'mobile_money' === $method ? __( 'Mobile money', 'bgcf' ) : __( 'Card', 'bgcf' ) );

			if ( '' !== $network ) {
				echo '<br /><span class="description">' . esc_html( $network ) . '</span>';
			}
			break;

		case 'bgcf_reference':
			echo '<code>' . esc_html( (string) bgcf_donation_get( $post_id, 'tx_ref' ) ) . '</code>';

			$flw_ref = (string) bgcf_donation_get( $post_id, 'flw_ref' );

			if ( '' !== $flw_ref ) {
				echo '<br /><span class="description">' . esc_html( $flw_ref ) . '</span>';
			}
			break;

		case 'bgcf_mode':
			echo esc_html( strtoupper( (string) bgcf_donation_get( $post_id, 'mode', 'test' ) ) );
			break;
	}
}
add_action( 'manage_' . BGCF_DONATION_POST_TYPE . '_posts_custom_column', 'bgcf_donations_column_content', 10, 2 );

/**
 * Filter the donations list by status.
 *
 * @since 1.1.0
 *
 * @param string $post_type Current post type.
 * @return void
 */
function bgcf_donations_status_filter( string $post_type ): void {
	if ( BGCF_DONATION_POST_TYPE !== $post_type ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
	$current = isset( $_GET['bgcf_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['bgcf_status'] ) ) : '';

	echo '<select name="bgcf_status">';
	printf( '<option value="">%s</option>', esc_html__( 'All statuses', 'bgcf' ) );

	foreach ( bgcf_donation_statuses() as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $current, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
}
add_action( 'restrict_manage_posts', 'bgcf_donations_status_filter' );

/**
 * Apply the status filter to the donations query.
 *
 * @since 1.1.0
 *
 * @param WP_Query $query Current admin query.
 * @return void
 */
function bgcf_donations_filter_query( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || BGCF_DONATION_POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
	$status = isset( $_GET['bgcf_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['bgcf_status'] ) ) : '';

	if ( '' === $status || ! array_key_exists( $status, bgcf_donation_statuses() ) ) {
		return;
	}

	$query->set(
		'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only filter.
		array(
			array(
				'key'     => '_bgcf_status',
				'value'   => $status,
				'compare' => '=',
			),
		)
	);
}
add_action( 'pre_get_posts', 'bgcf_donations_filter_query' );

/* -------------------------------------------------------------------------
 * Donation detail
 * ---------------------------------------------------------------------- */

/**
 * Add the read-only detail panel to the donation edit screen.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_add_meta_box(): void {
	add_meta_box(
		'bgcf-donation-detail',
		__( 'Transaction detail', 'bgcf' ),
		'bgcf_donations_render_meta_box',
		BGCF_DONATION_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'bgcf_donations_add_meta_box' );

/**
 * Render the donation detail panel.
 *
 * Read-only on purpose. A donation record is an account of what a payment
 * provider did; an editable one is a fiction waiting to be written.
 *
 * @since 1.1.0
 *
 * @param WP_Post $post Donation post.
 * @return void
 */
function bgcf_donations_render_meta_box( WP_Post $post ): void {
	$id     = (int) $post->ID;
	$labels = bgcf_donation_statuses();
	$status = bgcf_donation_status( $id );

	$rows = array(
		__( 'Status', 'bgcf' )             => (string) ( $labels[ $status ] ?? $status ),
		__( 'Amount', 'bgcf' )             => bgcf_donation_format_amount(
			(int) bgcf_donation_get( $id, 'amount', 0 ),
			(string) bgcf_donation_get( $id, 'currency', '' )
		),
		__( 'Charged amount', 'bgcf' )     => (string) bgcf_donation_get( $id, 'charged_amount', '—' ),
		__( 'Our reference', 'bgcf' )      => (string) bgcf_donation_get( $id, 'tx_ref' ),
		__( 'Flutterwave ID', 'bgcf' )     => (string) bgcf_donation_get( $id, 'flw_id', '—' ),
		__( 'Flutterwave ref', 'bgcf' )    => (string) bgcf_donation_get( $id, 'flw_ref', '—' ),
		__( 'Processor response', 'bgcf' ) => (string) bgcf_donation_get( $id, 'processor_response', '—' ),
		__( 'Method', 'bgcf' )             => trim( (string) bgcf_donation_get( $id, 'method' ) . ' ' . (string) bgcf_donation_get( $id, 'network' ) ),
		__( 'Mode', 'bgcf' )               => strtoupper( (string) bgcf_donation_get( $id, 'mode', 'test' ) ),
		__( 'Donor', 'bgcf' )              => (string) bgcf_donation_get( $id, 'donor_name', '—' ),
		__( 'Email', 'bgcf' )              => (string) bgcf_donation_get( $id, 'donor_email', '—' ),
		__( 'Phone', 'bgcf' )              => (string) bgcf_donation_get( $id, 'donor_phone', '—' ),
		__( 'Message', 'bgcf' )            => (string) bgcf_donation_get( $id, 'message', '—' ),
		__( 'Last webhook event', 'bgcf' ) => (string) bgcf_donation_get( $id, 'last_event', '—' ),
	);

	$campaign_id = absint( bgcf_donation_get( $id, 'campaign', 0 ) );

	if ( $campaign_id > 0 ) {
		$rows[ __( 'Campaign', 'bgcf' ) ] = (string) get_the_title( $campaign_id );
	}

	echo '<table class="widefat striped"><tbody>';

	foreach ( $rows as $label => $value ) {
		printf(
			'<tr><th scope="row" style="width:14rem;">%1$s</th><td>%2$s</td></tr>',
			esc_html( $label ),
			esc_html( '' !== $value ? $value : '—' )
		);
	}

	echo '</tbody></table>';

	if ( bgcf_donation_get( $id, 'mismatch', 0 ) ) {
		echo '<p style="color:#b32d2e;"><strong>' . esc_html__( 'This record was left pending because the verified transaction did not match its amount, currency or reference. Investigate in the Flutterwave dashboard before treating it as income.', 'bgcf' ) . '</strong></p>';
	}
}

/**
 * Remove the Publish box from donation records.
 *
 * A donation record is an account of what a payment provider did. There is
 * nothing here for an editor to publish, schedule or revert, and an Update
 * button on a financial record is an invitation to "fix" one.
 *
 * Hooked to `add_meta_boxes` (not `admin_menu`): core registers `submitdiv`
 * immediately before firing that action, so anything earlier is removed before
 * it exists.
 *
 * @since 1.1.0
 * @return void
 */
function bgcf_donations_remove_publish_box(): void {
	remove_meta_box( 'submitdiv', BGCF_DONATION_POST_TYPE, 'side' );
}
add_action( 'add_meta_boxes', 'bgcf_donations_remove_publish_box', 20 );
