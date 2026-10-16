<?php
/**
 * SEO output: canonical URLs, Open Graph / Twitter meta, JSON-LD scaffolding.
 *
 * DELEGATION RULE — read before adding anything here.
 *
 * RankMath is the site's SEO plugin. It already emits titles, meta
 * descriptions, canonicals, Open Graph, Twitter cards, robots directives, and
 * Organization/WebSite/Breadcrumb schema. Duplicating any of that produces
 * conflicting tags, which is worse than emitting nothing.
 *
 * Therefore every output function in this module is wrapped in
 * bgcf_seo_is_delegated(). The theme only fills gaps:
 *
 *   - Canonicals on paginated archives, which core skips and some
 *     configurations of RankMath leave off.
 *   - OG/Twitter fallbacks driven by the SEO Defaults ACF options page, so a
 *     plugin-less environment still shares correctly.
 *   - Domain-specific JSON-LD that no plugin can know about: DonateAction,
 *     Event with an offer, Person for team members, FAQPage from the FAQ block.
 *     Phase 7 (seo-specialist) populates `bgcf_schema_graph`; this file only
 *     provides the pipe.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether an SEO plugin owns meta output on this request.
 *
 * @since 1.0.0
 * @return bool True when the theme must stay quiet.
 */
function bgcf_seo_is_delegated(): bool {
	$delegated = class_exists( 'RankMath' ) || defined( 'WPSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );

	/**
	 * Filters whether SEO output is delegated to a plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $delegated True when a plugin handles meta output.
	 */
	return (bool) apply_filters( 'bgcf_seo_is_delegated', $delegated );
}

/**
 * The canonical URL for the current request.
 *
 * @since 1.0.0
 * @return string Canonical URL, or an empty string when none applies.
 */
function bgcf_seo_get_canonical_url(): string {
	$url = '';

	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_singular() ) {
		$url = (string) get_permalink();
	} elseif ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? (string) reset( $post_type ) : (string) $post_type;
		$link      = get_post_type_archive_link( $post_type );
		$url       = $link ? $link : '';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$link = get_term_link( $term );
			$url  = is_wp_error( $link ) ? '' : $link;
		}
	} elseif ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		$url     = $blog_id ? (string) get_permalink( $blog_id ) : home_url( '/' );
	}

	if ( '' !== $url ) {
		$paged = (int) get_query_var( 'paged' );

		if ( $paged > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $paged . '/';
		}
	}

	/**
	 * Filters the canonical URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Canonical URL.
	 */
	return (string) apply_filters( 'bgcf_seo_canonical_url', $url );
}

/**
 * A description for the current request.
 *
 * @since 1.0.0
 * @return string Plain-text description, trimmed to a sensible length.
 */
function bgcf_seo_get_description(): string {
	$description = '';

	if ( is_singular() ) {
		$description = (string) get_the_excerpt();
	} elseif ( is_archive() ) {
		$description = wp_strip_all_tags( (string) get_the_archive_description() );
	}

	if ( '' === trim( $description ) && function_exists( 'bgcf_option' ) ) {
		$description = (string) bgcf_option( 'seo_default_description', '' );
	}

	if ( '' === trim( $description ) ) {
		$description = (string) get_bloginfo( 'description', 'display' );
	}

	$description = wp_strip_all_tags( $description );

	/**
	 * Filters the meta description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description Description text.
	 */
	return (string) apply_filters( 'bgcf_seo_description', wp_trim_words( $description, 32, '' ) );
}

/**
 * A share image URL for the current request.
 *
 * Falls back to the SEO Defaults options page so no page is ever shared
 * imageless.
 *
 * @since 1.0.0
 * @return string Image URL, or an empty string.
 */
function bgcf_seo_get_image_url(): string {
	$url = '';

	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id(), 'bgcf-hero' );
		$url = is_array( $src ) ? (string) $src[0] : '';
	}

	if ( '' === $url && function_exists( 'bgcf_option' ) ) {
		$fallback      = bgcf_option( 'seo_default_share_image', 0 );
		$attachment_id = function_exists( 'bgcf_image_id' ) ? bgcf_image_id( $fallback ) : 0;

		if ( $attachment_id ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'bgcf-hero' );
			$url = is_array( $src ) ? (string) $src[0] : '';
		}
	}

	/**
	 * Filters the share image URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Image URL.
	 */
	return (string) apply_filters( 'bgcf_seo_image_url', $url );
}

/**
 * Output canonical, Open Graph and Twitter tags.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_seo_render_head_meta(): void {
	if ( bgcf_seo_is_delegated() || is_404() || is_search() ) {
		return;
	}

	$canonical   = bgcf_seo_get_canonical_url();
	$description = bgcf_seo_get_description();
	$image       = bgcf_seo_get_image_url();
	$title       = wp_get_document_title();
	$site_name   = (string) get_bloginfo( 'name', 'display' );

	if ( '' !== $canonical ) {
		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
	}

	if ( '' !== $description ) {
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
	}

	printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) );
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $site_name ) );
	printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( get_locale() ) );

	if ( '' !== $canonical ) {
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $canonical ) );
	}

	if ( '' !== $description ) {
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
	}

	if ( '' !== $image ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:card" content="%s" />' . "\n", 'summary_large_image' );

		/*
		 * Dimensions matter more than they look. Without them a crawler has to
		 * fetch and measure the image before it can lay out the card, so the
		 * first share of a new page often renders with no image at all - the
		 * most common reason a correct og:image "does not work".
		 */
		$dimensions = bgcf_seo_get_image_dimensions( $image );

		if ( $dimensions['width'] > 0 && $dimensions['height'] > 0 ) {
			printf( '<meta property="og:image:width" content="%d" />' . "\n", (int) $dimensions['width'] );
			printf( '<meta property="og:image:height" content="%d" />' . "\n", (int) $dimensions['height'] );
		}

		if ( '' !== $dimensions['alt'] ) {
			printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $dimensions['alt'] ) );
		}

		// X/Twitter reads its own image tag and does not reliably fall back to
		// og:image once a twitter:card has been declared.
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );

		if ( '' !== $dimensions['alt'] ) {
			printf( '<meta name="twitter:image:alt" content="%s" />' . "\n", esc_attr( $dimensions['alt'] ) );
		}
	} else {
		printf( '<meta name="twitter:card" content="%s" />' . "\n", 'summary' );
	}

	printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );

	if ( '' !== $description ) {
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
	}

	$handle = (string) bgcf_option( 'twitter_handle', '' );

	if ( '' !== $handle ) {
		printf( '<meta name="twitter:site" content="%s" />' . "\n", esc_attr( '@' . ltrim( $handle, '@' ) ) );
	}

	// Article timestamps: used by aggregators and by Open Graph consumers to
	// decide whether to badge a shared link as fresh or dated.
	if ( is_singular() && ! is_front_page() ) {
		printf( '<meta property="article:published_time" content="%s" />' . "\n", esc_attr( (string) get_the_date( DATE_W3C ) ) );
		printf( '<meta property="article:modified_time" content="%s" />' . "\n", esc_attr( (string) get_the_modified_date( DATE_W3C ) ) );
	}
}
add_action( 'wp_head', 'bgcf_seo_render_head_meta', 5 );

/**
 * Dimensions and alt text for the social share image.
 *
 * Resolved from the attachment when the URL belongs to one, which is the
 * normal case: the share image comes from the featured image or the ACF
 * fallback. A remote URL returns zeros and the tags are simply omitted -
 * downloading someone else's image to measure it is not this function's job.
 *
 * @since 1.2.0
 *
 * @param string $url Image URL.
 * @return array{width:int, height:int, alt:string}
 */
function bgcf_seo_get_image_dimensions( string $url ): array {
	$empty = array(
		'width'  => 0,
		'height' => 0,
		'alt'    => '',
	);

	if ( '' === $url ) {
		return $empty;
	}

	$attachment_id = (int) attachment_url_to_postid( $url );

	if ( 0 === $attachment_id ) {
		return $empty;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );

	if ( ! is_array( $meta ) ) {
		return $empty;
	}

	return array(
		'width'  => (int) ( $meta['width'] ?? 0 ),
		'height' => (int) ( $meta['height'] ?? 0 ),
		'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Output the JSON-LD graph.
 *
 * Emits nothing until something adds nodes to `bgcf_schema_graph`. Phase 7 adds
 * the Organization, WebSite, BreadcrumbList, Article, Event, Person, FAQPage and
 * DonateAction builders — each as its own filter callback, so they can be
 * enabled and tested independently.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_seo_render_schema(): void {
	/**
	 * Filters the JSON-LD graph nodes for the current request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $graph Schema.org nodes.
	 */
	$graph = (array) apply_filters( 'bgcf_schema_graph', array() );

	$graph = array_values( array_filter( $graph ) );

	if ( empty( $graph ) ) {
		return;
	}

	/*
	 * JSON_HEX_TAG escapes angle brackets to their unicode escapes, which makes
	 * it impossible to break out of the script element. That is the correct
	 * escaping for a JSON-LD payload — running it through esc_html() or
	 * wp_kses_post() would corrupt the JSON, because entities are not decoded
	 * inside a <script> element.
	 */
	$payload = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);

	if ( false === $payload ) {
		return;
	}

	echo '<script type="application/ld+json">' . $payload . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by JSON_HEX_TAG|JSON_HEX_AMP above.
}
add_action( 'wp_head', 'bgcf_seo_render_schema', 20 );

/**
 * Keep admin-only content types out of sitemaps and search engines.
 *
 * @since 1.0.0
 *
 * @param string[] $post_types Post types included in the core sitemap.
 * @return string[] Filtered post types.
 */
function bgcf_seo_sitemap_post_types( array $post_types ): array {
	$excluded = array( 'bgcf_partner', 'bgcf_testimonial', 'bgcf_report' );

	foreach ( $excluded as $type ) {
		unset( $post_types[ $type ] );
	}

	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'bgcf_seo_sitemap_post_types' );

/**
 * Separator used in the document title.
 *
 * @since 1.0.0
 * @return string Title separator.
 */
function bgcf_seo_title_separator(): string {
	/**
	 * Filters the document title separator.
	 *
	 * @since 1.0.0
	 *
	 * @param string $separator Separator character.
	 */
	return (string) apply_filters( 'bgcf_title_separator', '·' );
}
add_filter( 'document_title_separator', 'bgcf_seo_title_separator' );
