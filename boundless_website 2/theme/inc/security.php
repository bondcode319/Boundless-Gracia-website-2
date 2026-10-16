<?php
/**
 * Security hardening — Phase 1 baseline.
 *
 * Scope of this module: reduce the theme's public attack surface and provide the
 * shared request-verification helper that every form and AJAX handler must use.
 *
 * Out of scope (owned by security-engineer in Phases 6 and 8): upload MIME/size
 * validation for volunteer résumés, rate limiting, reCAPTCHA, honeypots, and the
 * Wordfence configuration. Hook points for those are declared here so the later
 * work has somewhere to land without editing existing functions.
 *
 * Server-level concerns deliberately NOT handled here (document them in
 * DEPLOYMENT.md instead):
 *   - Directory listing must be disabled at the web-server level
 *     (`Options -Indexes` on Apache, `autoindex off;` on Nginx). A theme cannot
 *     do this reliably, and index.php stub files are security theatre.
 *   - HSTS and Content-Security-Policy belong at the edge (Cloudflare) or in the
 *     server config, where they can be set for static assets too.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Disable the plugin/theme file editors.
 *
 * Preferred location is wp-config.php (it also covers the case where this theme
 * is inactive). Defining it here guarantees the baseline even if the constant
 * was forgotten during provisioning. See DEPLOYMENT.md.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Remove version fingerprints and legacy discovery endpoints from wp_head.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_security_clean_head(): void {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
}
add_action( 'init', 'bgcf_security_clean_head' );

/**
 * Strip the WordPress version from the generator tag and feeds.
 *
 * @since 1.0.0
 * @return string Always an empty string.
 */
function bgcf_security_remove_generator(): string {
	return '';
}
add_filter( 'the_generator', 'bgcf_security_remove_generator' );

/**
 * Disable XML-RPC.
 *
 * Nothing in this build uses it; it is a standing brute-force and pingback
 * amplification vector. Blocking it at the web server as well is recommended.
 *
 * @since 1.0.0
 * @return bool Always false.
 */
function bgcf_security_disable_xmlrpc(): bool {
	return false;
}
add_filter( 'xmlrpc_enabled', 'bgcf_security_disable_xmlrpc' );
add_filter( 'pings_open', '__return_false', 20 );

/**
 * Remove the X-Pingback response header.
 *
 * @since 1.0.0
 *
 * @param array<string, string> $headers Response headers.
 * @return array<string, string> Filtered headers.
 */
function bgcf_security_remove_pingback_header( array $headers ): array {
	unset( $headers['X-Pingback'] );

	return $headers;
}
add_filter( 'wp_headers', 'bgcf_security_remove_pingback_header', 20 );

/**
 * Send baseline security response headers on front-end requests.
 *
 * Kept conservative on purpose: these are safe with Cloudflare, WP Rocket and
 * the block editor. CSP is intentionally absent — it needs per-environment
 * tuning and belongs at the edge.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_security_send_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	$headers = array(
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'Permissions-Policy'     => 'camera=(), microphone=(), payment=(), interest-cohort=()',
		'Cross-Origin-Resource-Policy' => 'same-origin',
	);

	/**
	 * Filters the security headers sent on every request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $headers Header name => value.
	 */
	$headers = (array) apply_filters( 'bgcf_security_headers', $headers );

	foreach ( $headers as $name => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}

		header( sprintf( '%s: %s', $name, $value ), true );
	}
}
add_action( 'send_headers', 'bgcf_security_send_headers' );

/**
 * Block REST user enumeration for unauthenticated requests.
 *
 * Authenticated editors keep full access — the block editor needs the users
 * endpoint for author selection.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $endpoints REST endpoints.
 * @return array<string, mixed> Filtered endpoints.
 */
function bgcf_security_restrict_user_endpoints( array $endpoints ): array {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

	return $endpoints;
}
add_filter( 'rest_endpoints', 'bgcf_security_restrict_user_endpoints' );

/**
 * Block `?author=N` enumeration on the front end.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_security_block_author_scan(): void {
	if ( is_admin() || is_user_logged_in() || ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only guard, no state change.
		return;
	}

	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}
add_action( 'template_redirect', 'bgcf_security_block_author_scan' );

/**
 * Never reveal whether a username exists on failed login.
 *
 * @since 1.0.0
 * @return string Generic error message.
 */
function bgcf_security_generic_login_error(): string {
	return esc_html__( 'Those credentials are not valid. Please try again.', 'bgcf' );
}
add_filter( 'login_errors', 'bgcf_security_generic_login_error' );

/**
 * Normalise uploaded file names.
 *
 * Transliterates accents, lowercases, and collapses separators so uploads never
 * produce URL-hostile or duplicate-cased filenames.
 *
 * @since 1.0.0
 *
 * @param string $filename Sanitised file name from WordPress.
 * @return string Normalised file name.
 */
function bgcf_security_sanitize_filename( string $filename ): string {
	$info      = pathinfo( $filename );
	$extension = isset( $info['extension'] ) ? '.' . strtolower( $info['extension'] ) : '';
	$name      = isset( $info['extension'] )
		? substr( $filename, 0, - ( strlen( $info['extension'] ) + 1 ) )
		: $filename;

	$name = remove_accents( $name );
	$name = strtolower( $name );
	$name = preg_replace( '/[^a-z0-9]+/', '-', $name );
	$name = trim( (string) $name, '-' );

	if ( '' === $name ) {
		$name = 'file-' . wp_generate_password( 8, false, false );
	}

	return $name . $extension;
}
add_filter( 'sanitize_file_name', 'bgcf_security_sanitize_filename', 20 );

/**
 * Disallow SVG uploads unless explicitly enabled.
 *
 * SVG is an executable format. Theme icons are shipped in the repo and inlined
 * by bgcf_get_svg_icon(); editors have no need to upload them.
 *
 * @since 1.0.0
 *
 * @param array<string, string> $mimes Allowed MIME types.
 * @return array<string, string> Filtered MIME types.
 */
function bgcf_security_restrict_mime_types( array $mimes ): array {
	unset( $mimes['svg'], $mimes['svgz'] );

	/**
	 * Filters the allowed upload MIME types.
	 *
	 * Phase 6 extends this for volunteer résumé uploads (PDF/DOC), together with
	 * real content-type verification — an allowlist alone is not validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $mimes Allowed MIME types.
	 */
	return (array) apply_filters( 'bgcf_allowed_mime_types', $mimes );
}
add_filter( 'upload_mimes', 'bgcf_security_restrict_mime_types' );

/**
 * Verify a state-changing request: nonce, capability, and origin.
 *
 * THE mandatory gate for every form handler and AJAX endpoint in this theme. Do
 * not hand-roll nonce checks elsewhere.
 *
 * @since 1.0.0
 *
 * @param string $action     Nonce action, e.g. 'bgcf_ajax'.
 * @param string $nonce_key  Request key holding the nonce. Default 'nonce'.
 * @param string $capability Required capability, or an empty string for public
 *                           endpoints (newsletter sign-up, etc.).
 * @return true|WP_Error True when the request may proceed, WP_Error otherwise.
 */
function bgcf_verify_request( string $action, string $nonce_key = 'nonce', string $capability = '' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This IS the nonce check.
	$nonce = isset( $_REQUEST[ $nonce_key ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $nonce_key ] ) ) : '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		return new WP_Error(
			'bgcf_invalid_nonce',
			__( 'Your session expired. Please reload the page and try again.', 'bgcf' ),
			array( 'status' => 403 )
		);
	}

	if ( '' !== $capability && ! current_user_can( $capability ) ) {
		return new WP_Error(
			'bgcf_forbidden',
			__( 'You do not have permission to do that.', 'bgcf' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Filters the result of a request verification.
	 *
	 * Phase 6 attaches rate limiting, honeypot and reCAPTCHA checks here by
	 * returning a WP_Error. Returning anything other than `true` blocks the
	 * request.
	 *
	 * @since 1.0.0
	 *
	 * @param true|WP_Error $result     Verification result so far.
	 * @param string        $action     Nonce action.
	 * @param string        $capability Required capability.
	 */
	return apply_filters( 'bgcf_verify_request', true, $action, $capability );
}
