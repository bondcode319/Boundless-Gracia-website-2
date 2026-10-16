<?php
/**
 * Theme setup: supports, navigation menus, image sizes, widget areas, i18n.
 *
 * Single responsibility: declare what the theme *is* to WordPress. No rendering,
 * no queries, no asset handling (see inc/enqueue.php).
 *
 * The editor colour palette and font sizes are expressed as CSS custom
 * properties (`var(--bgcf-color-*)`, `var(--bgcf-font-size-*)`) so this file
 * contains zero raw colour values. The properties themselves are declared once
 * by the design system in `assets/scss/settings/` and must be present in BOTH
 * `assets/css/main.css` and `assets/css/editor.css` — see ARCHITECTURE.md,
 * "Coupling and migration risks".
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register core theme supports.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_theme_supports(): void {
	load_theme_textdomain( 'bgcf', BGCF_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
			'navigation-widgets',
		)
	);

	add_theme_support(
		'custom-logo',
		array(
			'height'               => 96,
			'width'                => 320,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		)
	);

	// Compiled editor stylesheet. Authored in assets/scss/editor.scss.
	add_editor_style( 'assets/css/editor.css' );

	/*
	 * Brand lock-in. Editors compose pages from bgcf/* blocks using the palette
	 * below; arbitrary colours, gradients and font sizes are disabled so no page
	 * can drift off-brand. Remove these three lines only with design sign-off.
	 */
	add_theme_support( 'disable-custom-colors' );
	add_theme_support( 'disable-custom-gradients' );
	add_theme_support( 'disable-custom-font-sizes' );
	add_theme_support( 'editor-gradient-presets', array() );

	add_theme_support( 'editor-color-palette', bgcf_get_editor_color_palette() );
	add_theme_support( 'editor-font-sizes', bgcf_get_editor_font_sizes() );

	/*
	 * Core block patterns are removed: every homepage and inner-page section is
	 * a bgcf/* ACF block (see inc/blocks.php). Core patterns would let editors
	 * insert unstyled, unaudited markup.
	 */
	remove_theme_support( 'core-block-patterns' );

	bgcf_setup_register_image_sizes();
}
add_action( 'after_setup_theme', 'bgcf_setup_theme_supports' );

/**
 * Editor colour palette, locked to the brand tokens.
 *
 * Values are CSS custom properties, never literals — the hex values live in
 * `assets/scss/settings/_colors.scss` and nowhere else. `slug` is a public
 * contract: it generates `.has-{slug}-color` / `.has-{slug}-background-color`
 * classes that get saved into post content. Renaming a slug orphans every
 * post that already uses it.
 *
 * @since 1.0.0
 * @return array<int, array<string, string>> Palette definition for WordPress.
 */
function bgcf_get_editor_color_palette(): array {
	$palette = array(
		array(
			'name'  => __( 'Deep Blue', 'bgcf' ),
			'slug'  => 'primary',
			'color' => 'var(--bgcf-color-primary)',
		),
		array(
			'name'  => __( 'Emerald Green', 'bgcf' ),
			'slug'  => 'secondary',
			'color' => 'var(--bgcf-color-secondary)',
		),
		array(
			'name'  => __( 'Action Orange', 'bgcf' ),
			'slug'  => 'cta',
			'color' => 'var(--bgcf-color-cta)',
		),
		array(
			'name'  => __( 'Accent Yellow', 'bgcf' ),
			'slug'  => 'accent',
			'color' => 'var(--bgcf-color-accent)',
		),
		array(
			'name'  => __( 'Light Gray', 'bgcf' ),
			'slug'  => 'surface',
			'color' => 'var(--bgcf-color-surface)',
		),
		array(
			'name'  => __( 'White', 'bgcf' ),
			'slug'  => 'base',
			'color' => 'var(--bgcf-color-base)',
		),
		array(
			'name'  => __( 'Dark Text', 'bgcf' ),
			'slug'  => 'ink',
			'color' => 'var(--bgcf-color-ink)',
		),
	);

	/**
	 * Filters the editor colour palette.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, string>> $palette Palette definition.
	 */
	return (array) apply_filters( 'bgcf_editor_color_palette', $palette );
}

/**
 * Editor font sizes, mapped to the type scale custom properties.
 *
 * @since 1.0.0
 * @return array<int, array<string, mixed>> Font size definition for WordPress.
 */
function bgcf_get_editor_font_sizes(): array {
	$sizes = array(
		array(
			'name' => __( 'Small', 'bgcf' ),
			'slug' => 'small',
			'size' => 'var(--bgcf-font-size-sm)',
		),
		array(
			'name' => __( 'Body', 'bgcf' ),
			'slug' => 'body',
			'size' => 'var(--bgcf-font-size-base)',
		),
		array(
			'name' => __( 'Lead', 'bgcf' ),
			'slug' => 'lead',
			'size' => 'var(--bgcf-font-size-lg)',
		),
		array(
			'name' => __( 'Heading', 'bgcf' ),
			'slug' => 'heading',
			'size' => 'var(--bgcf-font-size-xl)',
		),
		array(
			'name' => __( 'Display', 'bgcf' ),
			'slug' => 'display',
			'size' => 'var(--bgcf-font-size-2xl)',
		),
	);

	/**
	 * Filters the editor font sizes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $sizes Font size definition.
	 */
	return (array) apply_filters( 'bgcf_editor_font_sizes', $sizes );
}

/**
 * Register the theme image sizes.
 *
 * Every size is intentional and maps to a known component. Adding a size later
 * requires regenerating thumbnails across the media library, so add
 * deliberately. Aspect ratios are fixed by design and must match the SCSS
 * component ratios and the placeholder-image manifest.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_register_image_sizes(): void {
	set_post_thumbnail_size( 800, 600, true );

	// Full-bleed hero / donation banner background. 16:9.
	add_image_size( 'bgcf-hero', 1920, 1080, true );

	// Program, campaign, event and post cards. 4:3.
	add_image_size( 'bgcf-card', 800, 600, true );

	// Success story hero and before/after panes. 3:2.
	add_image_size( 'bgcf-story', 1200, 800, true );

	// Team member portrait. 4:5.
	add_image_size( 'bgcf-team', 600, 750, true );

	// Partner logo, letterboxed (no crop) so logo lock-ups are never clipped.
	add_image_size( 'bgcf-partner-logo', 360, 180, false );

	// Annual report cover, ISO 216 (A4) portrait ratio.
	add_image_size( 'bgcf-report-cover', 600, 848, true );
}

/**
 * Expose the theme image sizes in the media picker.
 *
 * @since 1.0.0
 *
 * @param array<string, string> $sizes Registered size name => label.
 * @return array<string, string> Filtered sizes.
 */
function bgcf_setup_custom_image_size_names( array $sizes ): array {
	return array_merge(
		$sizes,
		array(
			'bgcf-hero'         => __( 'BGCF Hero (16:9)', 'bgcf' ),
			'bgcf-card'         => __( 'BGCF Card (4:3)', 'bgcf' ),
			'bgcf-story'        => __( 'BGCF Story (3:2)', 'bgcf' ),
			'bgcf-team'         => __( 'BGCF Portrait (4:5)', 'bgcf' ),
			'bgcf-partner-logo' => __( 'BGCF Partner Logo', 'bgcf' ),
			'bgcf-report-cover' => __( 'BGCF Report Cover', 'bgcf' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'bgcf_setup_custom_image_size_names' );

/**
 * Register navigation menu locations.
 *
 * Location slugs are a public contract: header.php, footer.php and the ACF
 * options pages reference them by name. Renaming a location silently empties
 * the menu on the front end.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_register_menus(): void {
	register_nav_menus(
		array(
			'primary'         => __( 'Primary Navigation', 'bgcf' ),
			'footer_quick'    => __( 'Footer — Quick Links', 'bgcf' ),
			'footer_programs' => __( 'Footer — Programs', 'bgcf' ),
			'legal'           => __( 'Legal / Utility', 'bgcf' ),
			'social'          => __( 'Social Profiles', 'bgcf' ),
		)
	);
}
add_action( 'after_setup_theme', 'bgcf_setup_register_menus' );

/**
 * Set the global content width used by oEmbeds and wide alignments.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_content_width(): void {
	/**
	 * Filters the theme content width in pixels.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width Content width.
	 */
	$width = (int) apply_filters( 'bgcf_content_width', 800 );

	if ( ! isset( $GLOBALS['content_width'] ) ) {
		$GLOBALS['content_width'] = $width; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
add_action( 'after_setup_theme', 'bgcf_setup_content_width', 0 );

/**
 * Register widget areas.
 *
 * Deliberately minimal: page composition is handled by bgcf/* blocks. The blog
 * sidebar is the one place widgets earn their keep.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_register_sidebars(): void {
	register_sidebar(
		array(
			'name'          => __( 'Blog Sidebar', 'bgcf' ),
			'id'            => 'bgcf-blog-sidebar',
			'description'   => __( 'Shown beside blog archives and single posts.', 'bgcf' ),
			'before_widget' => '<section id="%1$s" class="bgcf-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="bgcf-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'bgcf_setup_register_sidebars' );

/**
 * Flush rewrite rules once when the theme is activated.
 *
 * The CPTs in inc/cpt.php own public URLs; without this, program/event/story
 * permalinks 404 until permalinks are re-saved manually.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_setup_flush_rewrites(): void {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'bgcf_setup_flush_rewrites' );

/**
 * Add a machine-readable state class to the body element.
 *
 * @since 1.0.0
 *
 * @param string[] $classes Body classes.
 * @return string[] Filtered classes.
 */
function bgcf_setup_body_classes( array $classes ): array {
	$classes[] = 'bgcf-body';

	if ( ! is_active_sidebar( 'bgcf-blog-sidebar' ) ) {
		$classes[] = 'bgcf-body--no-sidebar';
	}

	if ( is_singular() && ! is_front_page() ) {
		$classes[] = 'bgcf-body--singular';
	}

	return $classes;
}
add_filter( 'body_class', 'bgcf_setup_body_classes' );
