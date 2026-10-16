<?php
/**
 * Asset registration and enqueueing.
 *
 * The single place any CSS or JS enters a page. No other file may call
 * wp_enqueue_style()/wp_enqueue_script() with a raw URL, and no template may
 * emit a <script> or <style> tag. Blocks and templates opt into the heavy
 * libraries on demand through bgcf_use_gsap() / bgcf_use_swiper(), which is how
 * the 95+ Lighthouse target survives Phase 3.
 *
 * Handle naming: `bgcf-{name}`. Vendor libraries keep their own name behind the
 * prefix (`bgcf-gsap`, `bgcf-swiper`) so a plugin shipping its own copy of GSAP
 * cannot collide with ours.
 *
 * Vendor files are expected at `assets/js/vendor/` and `assets/css/vendor/`;
 * the devops build copies them out of node_modules. Registration is safe before
 * they exist — nothing is emitted until something enqueues it.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Absolute URL for a theme asset.
 *
 * @since 1.0.0
 *
 * @param string $relative Path relative to `assets/`, e.g. 'css/main.css'.
 * @return string Asset URL.
 */
function bgcf_asset_uri( string $relative ): string {
	return BGCF_ASSETS . '/' . ltrim( $relative, '/' );
}

/**
 * Cache-busting version string for a theme asset.
 *
 * Development (WP_DEBUG or SCRIPT_DEBUG): the file modification time, so a
 * recompile is visible on the next reload without touching the theme version.
 * Production: the theme version, so a deploy busts everything at once and CDN
 * cache keys stay stable between requests.
 *
 * @since 1.0.0
 *
 * @param string $relative Path relative to `assets/`.
 * @return string Version string.
 */
function bgcf_asset_version( string $relative ): string {
	$is_dev = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

	if ( $is_dev ) {
		$path = BGCF_DIR . '/assets/' . ltrim( $relative, '/' );

		if ( file_exists( $path ) ) {
			return (string) filemtime( $path );
		}
	}

	return BGCF_VERSION;
}

/**
 * Register every asset the theme knows about.
 *
 * Registration is separate from enqueueing on purpose: GSAP and Swiper are
 * registered here and enqueued only by the blocks and templates that need them.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_register_assets(): void {
	// Vendor scripts — deferred, no global side effects until used.
	wp_register_script(
		'bgcf-gsap',
		bgcf_asset_uri( 'js/vendor/gsap.min.js' ),
		array(),
		bgcf_asset_version( 'js/vendor/gsap.min.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_register_script(
		'bgcf-gsap-scrolltrigger',
		bgcf_asset_uri( 'js/vendor/scroll-trigger.min.js' ),
		array( 'bgcf-gsap' ),
		bgcf_asset_version( 'js/vendor/scroll-trigger.min.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_register_script(
		'bgcf-swiper',
		bgcf_asset_uri( 'js/vendor/swiper-bundle.min.js' ),
		array(),
		bgcf_asset_version( 'js/vendor/swiper-bundle.min.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// Swiper's stylesheet shares the handle name; styles and scripts are
	// separate registries in WordPress, so this is intentional, not a clash.
	wp_register_style(
		'bgcf-swiper',
		bgcf_asset_uri( 'css/vendor/swiper-bundle.min.css' ),
		array(),
		bgcf_asset_version( 'css/vendor/swiper-bundle.min.css' )
	);

	// Compiled design system.
	wp_register_style(
		'bgcf-main',
		bgcf_asset_uri( 'css/main.css' ),
		array(),
		bgcf_asset_version( 'css/main.css' )
	);

	// Theme behaviour: navigation, forms, observers, block initialisers.
	wp_register_script(
		'bgcf-app',
		bgcf_asset_uri( 'js/main.js' ),
		array(),
		bgcf_asset_version( 'js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bgcf_register_assets', 5 );

/**
 * Enqueue the front-end baseline.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_enqueue_frontend_assets(): void {
	wp_enqueue_style( 'bgcf-main' );
	wp_enqueue_script( 'bgcf-app' );

	/**
	 * Filters the data bridge exposed to the front-end bundle as `bgcfData`.
	 *
	 * The AJAX nonce lives here; inc/ajax.php verifies it against the same
	 * `bgcf_ajax` action. Never put secrets or capability decisions in this
	 * object — it is public.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Script data.
	 */
	$data = (array) apply_filters(
		'bgcf_script_data',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'restUrl'  => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'bgcf_ajax' ),
			/*
			 * Where the front end goes when the nonce above has expired inside a
			 * cached page. See assets/js/src/request.js — without this, a page
			 * served from a full-page cache stops accepting form submissions
			 * roughly twelve hours after it was cached, silently.
			 */
			'nonceUrl' => esc_url_raw( rest_url( 'bgcf/v1/nonce' ) ),
			'homeUrl'  => home_url( '/' ),
			'locale'   => get_locale(),
			/*
			 * NOTE: reduced motion is deliberately NOT sent from here. The server
			 * cannot know the visitor's preference, and a cached page would pin
			 * one visitor's answer for everyone. assets/js/src/main.js reads it
			 * live from matchMedia and publishes it as html[data-bgcf-motion].
			 */
			'i18n'     => array(
				'menuOpen'      => __( 'Menu', 'bgcf' ),
				'menuClose'     => __( 'Close', 'bgcf' ),
				'loading'       => __( 'Loading…', 'bgcf' ),
				'loadMore'      => __( 'Load more', 'bgcf' ),
				'close'         => __( 'Close', 'bgcf' ),
				'gallery'       => __( 'Gallery', 'bgcf' ),
				'mapTitle'      => __( 'Map of our location', 'bgcf' ),
				'prevSlide'     => __( 'Previous', 'bgcf' ),
				'nextSlide'     => __( 'Next', 'bgcf' ),
				'goToSlide'     => __( 'Go to slide {{index}}', 'bgcf' ),
				'pauseSlides'   => __( 'Pause the slideshow', 'bgcf' ),
				'playSlides'    => __( 'Start the slideshow', 'bgcf' ),
				'genericError'  => __( 'Something went wrong. Please try again.', 'bgcf' ),
				'formSuccess'   => __( 'Thank you — we have received your details.', 'bgcf' ),
				'requiredField' => __( 'This field is required.', 'bgcf' ),
			),
		)
	);

	wp_localize_script( 'bgcf-app', 'bgcfData', $data );

	if ( is_singular() && comments_open() && (bool) get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'bgcf_enqueue_frontend_assets', 10 );

/**
 * Enqueue GSAP (and optionally ScrollTrigger) on demand.
 *
 * Call from a block partial or template when motion is actually rendered. Safe
 * to call repeatedly. Late enqueues still work because both handles are footer
 * scripts.
 *
 * @since 1.0.0
 *
 * @param bool $with_scroll_trigger Also load ScrollTrigger. Default true.
 * @return void
 */
function bgcf_use_gsap( bool $with_scroll_trigger = true ): void {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_script( 'bgcf-gsap' );

	if ( $with_scroll_trigger ) {
		wp_enqueue_script( 'bgcf-gsap-scrolltrigger' );
	}
}

/**
 * Enqueue Swiper (script + stylesheet) on demand.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_use_swiper(): void {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style( 'bgcf-swiper' );
	wp_enqueue_script( 'bgcf-swiper' );
}

/**
 * Enqueue block editor assets.
 *
 * `add_editor_style()` in inc/setup.php covers the canvas; this covers the
 * editor chrome (block inspector, ACF block previews).
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_enqueue_editor_assets(): void {
	wp_enqueue_style(
		'bgcf-editor',
		bgcf_asset_uri( 'css/editor.css' ),
		array(),
		bgcf_asset_version( 'css/editor.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'bgcf_enqueue_editor_assets' );

/**
 * Defer scripts that were registered without a loading strategy.
 *
 * WordPress 6.3+ handles `strategy` natively for our own handles; this is the
 * safety net for third-party plugin handles the theme opts in via the filter.
 * Never defer jQuery or anything a plugin inlines against.
 *
 * @since 1.0.0
 *
 * @param string $tag    Script tag HTML.
 * @param string $handle Script handle.
 * @return string Filtered tag.
 */
function bgcf_defer_scripts( string $tag, string $handle ): string {
	/**
	 * Filters the handles force-deferred by the theme.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $handles Script handles.
	 */
	$handles = (array) apply_filters( 'bgcf_deferred_script_handles', array() );

	if ( ! in_array( $handle, $handles, true ) || false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
		return $tag;
	}

	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'bgcf_defer_scripts', 10, 2 );

/**
 * Add resource hints for self-hosted fonts.
 *
 * Fonts are self-hosted (see assets/fonts/), so there is no third-party
 * preconnect to add. The preloads themselves are emitted by
 * bgcf_preload_fonts() below, because `wp_resource_hints` cannot express the
 * `as`/`crossorigin` pair a font preload requires.
 *
 * @since 1.0.0
 *
 * @param string[] $urls          Resource URLs.
 * @param string   $relation_type Hint type.
 * @return string[] Filtered URLs.
 */
function bgcf_resource_hints( array $urls, string $relation_type ): array {
	/**
	 * Filters the theme resource hints.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $urls          Resource URLs or hint arrays.
	 * @param string   $relation_type One of 'dns-prefetch', 'preconnect', 'prefetch', 'prerender'.
	 */
	return (array) apply_filters( 'bgcf_resource_hints', $urls, $relation_type );
}
add_filter( 'wp_resource_hints', 'bgcf_resource_hints', 10, 2 );

/**
 * The web fonts that are worth preloading.
 *
 * ONLY the faces used above the fold, and only the Latin subsets. A preload is
 * a promise that the file is needed immediately; preloading the extended subset
 * or the italic that appears once in the footer costs bandwidth during the
 * critical window and pushes the LCP out — the opposite of the intent.
 *
 * Poppins 600 is the headline weight (the hero h1 is the LCP element on most
 * pages) and Inter is the variable body face used by the first paragraph.
 *
 * @since 1.2.0
 * @return string[] Paths relative to `assets/`.
 */
function bgcf_preloaded_fonts(): array {
	/**
	 * Filters the preloaded font list.
	 *
	 * @since 1.2.0
	 *
	 * @param string[] $fonts Paths relative to the theme asset root.
	 */
	return (array) apply_filters(
		'bgcf_preloaded_fonts',
		array(
			'fonts/inter-var-latin.woff2',
			'fonts/poppins-600-latin.woff2',
		)
	);
}

/**
 * Emit `<link rel="preload">` for the critical fonts.
 *
 * Printed at priority 1 so it lands before the stylesheet: the browser can then
 * start both downloads in the same round trip instead of discovering the font
 * only after the CSS has parsed. `crossorigin` is mandatory even for same-origin
 * fonts — without it the preload is fetched in a different mode than the CSS
 * request and the file is downloaded twice.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_preload_fonts(): void {
	if ( is_admin() ) {
		return;
	}

	foreach ( bgcf_preloaded_fonts() as $font ) {
		$path = BGCF_DIR . '/assets/' . ltrim( (string) $font, '/' );

		// Never promise a file that is not there: a 404 preload is a wasted
		// connection and a console warning on every page.
		if ( ! file_exists( $path ) ) {
			continue;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous" />' . "\n",
			esc_url( bgcf_asset_uri( (string) $font ) )
		);
	}
}
add_action( 'wp_head', 'bgcf_preload_fonts', 1 );
