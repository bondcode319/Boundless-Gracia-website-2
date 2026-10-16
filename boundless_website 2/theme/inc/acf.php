<?php
/**
 * Advanced Custom Fields integration: JSON sync, options pages, field access.
 *
 * ACF Pro is a hard dependency for content, but NOT for the theme booting. Every
 * function here degrades gracefully when ACF is absent so a licence lapse or a
 * plugin-less staging box shows an unstyled-but-working site rather than a
 * fatal error. Read field values through bgcf_field()/bgcf_option() only —
 * never call get_field() directly from a template or a block partial.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Absolute path to the version-controlled field group directory.
 *
 * @since 1.0.0
 * @return string Directory path, no trailing slash.
 */
function bgcf_acf_json_path(): string {
	return BGCF_DIR . '/acf-json';
}

/**
 * Write field group JSON into the theme instead of the database.
 *
 * Saving a field group in wp-admin writes `acf-json/group_xxx.json`, which is
 * committed. That file is the source of truth; the database copy is a cache.
 *
 * @since 1.0.0
 *
 * @param string $path Default save path.
 * @return string Theme save path.
 */
function bgcf_acf_json_save_point( string $path ): string {
	$theme_path = bgcf_acf_json_path();

	return is_dir( $theme_path ) && wp_is_writable( $theme_path ) ? $theme_path : $path;
}
add_filter( 'acf/settings/save_json', 'bgcf_acf_json_save_point' );

/**
 * Load field groups from the theme.
 *
 * The default path is dropped so a stale database group can never shadow the
 * committed definition.
 *
 * @since 1.0.0
 *
 * @param string[] $paths Registered load paths.
 * @return string[] Filtered load paths.
 */
function bgcf_acf_json_load_point( array $paths ): array {
	unset( $paths[0] );
	$paths[] = bgcf_acf_json_path();

	return $paths;
}
add_filter( 'acf/settings/load_json', 'bgcf_acf_json_load_point' );

/**
 * Register the ACF options pages.
 *
 * These carry every global string, link and image on the site: nothing in
 * header.php or footer.php is hardcoded. Menu slugs are referenced by
 * bgcf_option() lookups and by admin menu ordering — treat them as stable API.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_acf_register_options_pages(): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	/**
	 * Filters the capability required to edit the theme options pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability Capability name.
	 */
	$capability = (string) apply_filters( 'bgcf_options_capability', 'edit_theme_options' );

	acf_add_options_page(
		array(
			'page_title'  => __( 'Site Options', 'bgcf' ),
			'menu_title'  => __( 'Site Options', 'bgcf' ),
			'menu_slug'   => 'bgcf-site-options',
			'capability'  => $capability,
			'position'    => 3,
			'icon_url'    => 'dashicons-admin-customizer',
			'redirect'    => true,
			'update_button'   => __( 'Save options', 'bgcf' ),
			'updated_message' => __( 'Site options saved.', 'bgcf' ),
		)
	);

	$subpages = array(
		'bgcf-header' => array(
			'page_title' => __( 'Header', 'bgcf' ),
			'menu_title' => __( 'Header', 'bgcf' ),
		),
		'bgcf-footer' => array(
			'page_title' => __( 'Footer', 'bgcf' ),
			'menu_title' => __( 'Footer', 'bgcf' ),
		),
		'bgcf-global-ctas' => array(
			'page_title' => __( 'Global CTAs', 'bgcf' ),
			'menu_title' => __( 'Global CTAs', 'bgcf' ),
		),
		'bgcf-promo' => array(
			'page_title' => __( 'Announcement', 'bgcf' ),
			'menu_title' => __( 'Announcement', 'bgcf' ),
		),
		'bgcf-seo-defaults' => array(
			'page_title' => __( 'SEO Defaults', 'bgcf' ),
			'menu_title' => __( 'SEO Defaults', 'bgcf' ),
		),
	);

	foreach ( $subpages as $slug => $titles ) {
		acf_add_options_sub_page(
			array(
				'page_title'  => $titles['page_title'],
				'menu_title'  => $titles['menu_title'],
				'menu_slug'   => $slug,
				'parent_slug' => 'bgcf-site-options',
				'capability'  => $capability,
			)
		);
	}
}
add_action( 'acf/init', 'bgcf_acf_register_options_pages' );

/**
 * Read an ACF field value safely.
 *
 * The only sanctioned way to read ACF data in this theme.
 *
 * @since 1.0.0
 *
 * @param string          $selector Field name or key.
 * @param int|string|null $post_id  Post ID, 'option', or null for the current post.
 * @param mixed           $default  Value returned when ACF is missing or the field is empty.
 * @return mixed Field value or `$default`. Unescaped — escape at output.
 */
function bgcf_field( string $selector, $post_id = null, $default = '' ) {
	$value = $default;

	if ( function_exists( 'get_field' ) ) {
		$raw = get_field( $selector, $post_id );

		$is_empty = null === $raw || false === $raw || '' === $raw || ( is_array( $raw ) && empty( $raw ) );

		if ( ! $is_empty ) {
			$value = $raw;
		}
	}

	/**
	 * Filters a resolved ACF value.
	 *
	 * Useful for Polylang string overrides and for stubbing content in
	 * environments without an ACF licence.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed           $value    Resolved value.
	 * @param string          $selector Field name or key.
	 * @param int|string|null $post_id  Post context.
	 * @param mixed           $default  Caller-supplied fallback.
	 */
	return apply_filters( 'bgcf_field_value', $value, $selector, $post_id, $default );
}

/**
 * Read a value from the theme options pages.
 *
 * @since 1.0.0
 *
 * @param string $selector Field name or key.
 * @param mixed  $default  Fallback value.
 * @return mixed Field value or `$default`.
 */
function bgcf_option( string $selector, $default = '' ) {
	return bgcf_field( $selector, 'option', $default );
}

/**
 * Whether a field has a usable value.
 *
 * @since 1.0.0
 *
 * @param string          $selector Field name or key.
 * @param int|string|null $post_id  Post context.
 * @return bool True when the field resolves to something renderable.
 */
function bgcf_has_field( string $selector, $post_id = null ): bool {
	$value = bgcf_field( $selector, $post_id, null );

	return ! ( null === $value || '' === $value || ( is_array( $value ) && empty( $value ) ) );
}

/**
 * Normalise an ACF Image field to an attachment ID.
 *
 * Tolerates every ACF return format (array, ID, URL) so partials do not have to
 * care how the field was configured.
 *
 * @since 1.0.0
 *
 * @param mixed $image ACF image value.
 * @return int Attachment ID, or 0.
 */
function bgcf_image_id( $image ): int {
	if ( is_array( $image ) ) {
		return (int) ( $image['ID'] ?? $image['id'] ?? 0 );
	}

	if ( is_numeric( $image ) ) {
		return (int) $image;
	}

	if ( is_string( $image ) && '' !== $image ) {
		return (int) attachment_url_to_postid( $image );
	}

	return 0;
}

/**
 * Warn administrators once when ACF Pro is missing.
 *
 * Content is authored entirely in ACF, so a missing plugin means an empty site.
 * Fail visibly in wp-admin, silently on the front end.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_acf_dependency_notice(): void {
	if ( class_exists( 'ACF' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
		esc_html__( 'Boundless Gracia theme:', 'bgcf' ),
		esc_html__( 'Advanced Custom Fields Pro is required. Page content, options pages and all custom blocks are unavailable until it is activated.', 'bgcf' )
	);
}
add_action( 'admin_notices', 'bgcf_acf_dependency_notice' );
