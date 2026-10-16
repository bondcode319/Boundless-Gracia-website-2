<?php
/**
 * Admin experience.
 *
 * The people who use this site every day are programme staff, not developers.
 * This module exists to make wp-admin legible to them: a welcome panel that
 * says where to edit what, content types ordered by how often they are touched,
 * and the noisy default widgets removed.
 *
 * Loaded last so it can reorder menus that inc/cpt.php and inc/acf.php created.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the dashboard welcome widget.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_register_dashboard_widget(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'bgcf_welcome',
		__( 'Boundless Gracia — where to edit what', 'bgcf' ),
		'bgcf_admin_render_dashboard_widget',
		null,
		null,
		'normal',
		'high'
	);
}
add_action( 'wp_dashboard_setup', 'bgcf_admin_register_dashboard_widget', 20 );

/**
 * Render the dashboard welcome widget.
 *
 * The link list is filterable so later phases can add rows (donations, volunteer
 * applications) without editing this function.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_render_dashboard_widget(): void {
	$links = array(
		array(
			'label'       => __( 'Site Options', 'bgcf' ),
			'description' => __( 'Header, footer, global calls to action and SEO defaults.', 'bgcf' ),
			'url'         => admin_url( 'admin.php?page=bgcf-site-options' ),
		),
		array(
			'label'       => __( 'Pages', 'bgcf' ),
			'description' => __( 'Every page is built from Boundless Gracia blocks in the editor.', 'bgcf' ),
			'url'         => admin_url( 'edit.php?post_type=page' ),
		),
		array(
			'label'       => __( 'Programs', 'bgcf' ),
			'description' => __( 'Areas of work shown across the homepage and programme pages.', 'bgcf' ),
			'url'         => admin_url( 'edit.php?post_type=bgcf_program' ),
		),
		array(
			'label'       => __( 'Campaigns', 'bgcf' ),
			'description' => __( 'Fundraising appeals with goals, totals and deadlines.', 'bgcf' ),
			'url'         => admin_url( 'edit.php?post_type=bgcf_campaign' ),
		),
		array(
			'label'       => __( 'Menus', 'bgcf' ),
			'description' => __( 'Primary navigation, footer link columns and social profiles.', 'bgcf' ),
			'url'         => admin_url( 'nav-menus.php' ),
		),
	);

	/**
	 * Filters the dashboard welcome widget links.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{label: string, description: string, url: string}> $links Link rows.
	 */
	$links = (array) apply_filters( 'bgcf_dashboard_links', $links );

	printf( '<p>%s</p>', esc_html__( 'All content on this website is editable here — nothing is hardcoded.', 'bgcf' ) );

	echo '<ul class="bgcf-dashboard-links">';

	foreach ( $links as $link ) {
		printf(
			'<li><a href="%1$s"><strong>%2$s</strong></a> — %3$s</li>',
			esc_url( (string) ( $link['url'] ?? '' ) ),
			esc_html( (string) ( $link['label'] ?? '' ) ),
			esc_html( (string) ( $link['description'] ?? '' ) )
		);
	}

	echo '</ul>';

	bgcf_admin_render_payment_incidents();
}

/**
 * List the recent critical payment events on the dashboard.
 *
 * WHY HERE. inc/payments.php records a short ring buffer of money-at-risk
 * events — rejected webhooks, verification mismatches, chargebacks. Those used
 * to exist only in a server log that the people running the charity cannot
 * reach and would not read. On the dashboard, "the webhook secret has been
 * wrong since Tuesday" becomes something a fundraiser can see and escalate.
 *
 * Renders nothing when there is nothing wrong, so the widget stays quiet on a
 * healthy site.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_admin_render_payment_incidents(): void {
	if ( ! function_exists( 'bgcf_flw_incidents' ) || ! current_user_can( bgcf_donations_capability() ) ) {
		return;
	}

	$incidents = array_slice( bgcf_flw_incidents(), 0, 5 );

	if ( empty( $incidents ) ) {
		return;
	}

	printf(
		'<h3 class="bgcf-dashboard-heading">%s</h3>',
		esc_html__( 'Recent payment warnings', 'bgcf' )
	);

	echo '<ul class="bgcf-dashboard-incidents">';

	foreach ( $incidents as $incident ) {
		$time = absint( $incident['time'] ?? 0 );

		printf(
			'<li><time datetime="%1$s">%2$s</time> — %3$s</li>',
			esc_attr( $time > 0 ? (string) wp_date( DATE_W3C, $time ) : '' ),
			esc_html(
				$time > 0
					? sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours". */
						__( '%s ago', 'bgcf' ),
						human_time_diff( $time )
					)
					: __( 'unknown time', 'bgcf' )
			),
			esc_html( (string) ( $incident['message'] ?? '' ) )
		);
	}

	echo '</ul>';

	printf(
		'<p><a href="%1$s">%2$s</a></p>',
		esc_url( admin_url( 'site-health.php' ) ),
		esc_html__( 'Run the donation health check', 'bgcf' )
	);
}

/**
 * Remove dashboard widgets nobody on this project uses.
 *
 * WordPress Events and News in particular makes an external request on every
 * dashboard load.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_remove_dashboard_widgets(): void {
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
}
add_action( 'wp_dashboard_setup', 'bgcf_admin_remove_dashboard_widgets', 30 );

/**
 * Enable custom top-level admin menu ordering.
 *
 * @since 1.0.0
 * @return bool Always true.
 */
function bgcf_admin_enable_menu_order(): bool {
	return true;
}
add_filter( 'custom_menu_order', 'bgcf_admin_enable_menu_order' );

/**
 * Order the admin menu by editing frequency, not by registration order.
 *
 * Content first, configuration after. Any menu item not listed keeps its
 * position at the end, so plugins are never hidden by this filter.
 *
 * @since 1.0.0
 *
 * @param string[] $menu_order Current menu slugs in order.
 * @return string[] Reordered menu slugs.
 */
function bgcf_admin_menu_order( array $menu_order ): array {
	$preferred = array(
		'index.php',
		'bgcf-site-options',
		'edit.php?post_type=page',
		'edit.php?post_type=bgcf_program',
		'edit.php?post_type=bgcf_campaign',
		'edit.php?post_type=bgcf_event',
		'edit.php?post_type=bgcf_story',
		'edit.php',
		'edit.php?post_type=bgcf_team',
		'edit.php?post_type=bgcf_partner',
		'edit.php?post_type=bgcf_testimonial',
		'edit.php?post_type=bgcf_report',
		'upload.php',
	);

	/**
	 * Filters the preferred admin menu order.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $preferred Menu slugs, most important first.
	 */
	$preferred = (array) apply_filters( 'bgcf_admin_menu_order', $preferred );

	$ordered   = array_values( array_intersect( $preferred, $menu_order ) );
	$remaining = array_values( array_diff( $menu_order, $ordered ) );

	return array_merge( $ordered, $remaining );
}
add_filter( 'menu_order', 'bgcf_admin_menu_order' );

/**
 * Style the login screen with the theme's design tokens.
 *
 * The login screen does not load the front-end stylesheet, so it gets its own
 * compiled sheet. The only inline value is the logo URL, which is content and
 * therefore cannot live in a static file.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_login_styles(): void {
	wp_enqueue_style(
		'bgcf-login',
		bgcf_asset_uri( 'css/login.css' ),
		array(),
		bgcf_asset_version( 'css/login.css' )
	);

	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( ! $logo_id && function_exists( 'bgcf_option' ) && function_exists( 'bgcf_image_id' ) ) {
		$logo_id = bgcf_image_id( bgcf_option( 'login_logo', 0 ) );
	}

	if ( ! $logo_id ) {
		return;
	}

	$src = wp_get_attachment_image_src( $logo_id, 'full' );

	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return;
	}

	wp_add_inline_style(
		'bgcf-login',
		sprintf( ':root{--bgcf-login-logo:url("%s");}', esc_url_raw( (string) $src[0] ) )
	);
}
add_action( 'login_enqueue_scripts', 'bgcf_admin_login_styles' );

/**
 * Point the login logo at the site, not at wordpress.org.
 *
 * @since 1.0.0
 * @return string Home URL.
 */
function bgcf_admin_login_header_url(): string {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'bgcf_admin_login_header_url' );

/**
 * Use the site name as the login logo's accessible text.
 *
 * @since 1.0.0
 * @return string Site name.
 */
function bgcf_admin_login_header_text(): string {
	return (string) get_bloginfo( 'name', 'display' );
}
add_filter( 'login_headertext', 'bgcf_admin_login_header_text' );

/**
 * Stop the editor fetching remote block patterns from wordpress.org.
 *
 * @since 1.0.0
 * @return bool Always false.
 */
function bgcf_admin_disable_remote_patterns(): bool {
	return false;
}
add_filter( 'should_load_remote_block_patterns', 'bgcf_admin_disable_remote_patterns' );

/**
 * Remove the block directory (install-a-block-from-the-editor) assets.
 *
 * Editors must not be able to install plugins from inside a post.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_disable_block_directory(): void {
	remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
}
add_action( 'admin_init', 'bgcf_admin_disable_block_directory' );

/**
 * Replace the admin footer credit.
 *
 * @since 1.0.0
 * @return string Footer text.
 */
function bgcf_admin_footer_text(): string {
	return sprintf(
		/* translators: %s: site name. */
		esc_html__( '%s — content managed website.', 'bgcf' ),
		esc_html( (string) get_bloginfo( 'name', 'display' ) )
	);
}
add_filter( 'admin_footer_text', 'bgcf_admin_footer_text' );

/**
 * Show the featured image column on the reference content types.
 *
 * Partners and team members are identified by their image far faster than by
 * their title.
 *
 * @since 1.0.0
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string> Filtered columns.
 */
function bgcf_admin_add_thumbnail_column( array $columns ): array {
	$out = array();

	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$out['bgcf_thumb'] = __( 'Image', 'bgcf' );
		}

		$out[ $key ] = $label;
	}

	return $out;
}

/**
 * Render the featured image column.
 *
 * @since 1.0.0
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function bgcf_admin_render_thumbnail_column( string $column, int $post_id ): void {
	if ( 'bgcf_thumb' !== $column ) {
		return;
	}

	echo get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes attachment markup.
}

/**
 * Attach the image column to the reference content types.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_admin_setup_columns(): void {
	foreach ( array( 'bgcf_team', 'bgcf_partner', 'bgcf_testimonial', 'bgcf_report' ) as $post_type ) {
		add_filter( "manage_{$post_type}_posts_columns", 'bgcf_admin_add_thumbnail_column' );
		add_action( "manage_{$post_type}_posts_custom_column", 'bgcf_admin_render_thumbnail_column', 10, 2 );
	}
}
add_action( 'admin_init', 'bgcf_admin_setup_columns' );
