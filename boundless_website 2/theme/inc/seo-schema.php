<?php
/**
 * Structured data: the producers for `bgcf_schema_graph`.
 *
 * inc/seo.php built the extension point and left it empty. This file fills it.
 *
 * TWO GRAPHS, for one unavoidable reason. Most nodes can be built in `wp_head`
 * — the organisation, the website, the breadcrumb trail, the current post. FAQ
 * and event nodes cannot: their data lives in blocks that have not rendered yet
 * when the head is written. Those are collected during the body render and
 * emitted in `wp_footer`. JSON-LD anywhere in the document is valid and
 * Google reads both positions; splitting it is honest about when the data
 * exists, and the alternative (parsing post content twice in the head) is
 * slower and quietly wrong for dynamic blocks.
 *
 * DELEGATION. When RankMath or Yoast is active they own the graph, and
 * `bgcf_seo_is_delegated()` is already true, so everything here is skipped. Two
 * `@graph` blocks disagreeing about the same organisation is worse than one
 * imperfect one.
 *
 * NGO vs Organization: schema.org/NGO is the precise type and Google treats it
 * as an Organization subtype, so the richer type costs nothing and says more.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stable `@id` for a graph node.
 *
 * Nodes reference each other by `@id`, so these must be absolute, unique and
 * identical between page loads. A fragment on the home URL is the convention.
 *
 * @since 1.2.0
 *
 * @param string $fragment Fragment name, e.g. 'organization'.
 * @return string Absolute node id.
 */
function bgcf_schema_id( string $fragment ): string {
	return home_url( '/#' . $fragment );
}

/**
 * The organisation node.
 *
 * @since 1.2.0
 * @return array<string, mixed> Schema node.
 */
function bgcf_schema_organization(): array {
	$node = array(
		'@type' => 'NGO',
		'@id'   => bgcf_schema_id( 'organization' ),
		'name'  => (string) get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);

	$description = (string) bgcf_option( 'org_description', get_bloginfo( 'description' ) );

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	$logo_id = (int) get_theme_mod( 'custom_logo', 0 );

	if ( $logo_id > 0 ) {
		$logo = wp_get_attachment_image_src( $logo_id, 'full' );

		if ( is_array( $logo ) ) {
			$node['logo'] = array(
				'@type'  => 'ImageObject',
				'@id'    => bgcf_schema_id( 'logo' ),
				'url'    => (string) $logo[0],
				'width'  => (int) $logo[1],
				'height' => (int) $logo[2],
			);

			// Google reads `image` for the knowledge panel and `logo` for the
			// publisher badge; pointing both at the same node satisfies each.
			$node['image'] = array( '@id' => bgcf_schema_id( 'logo' ) );
		}
	}

	$phone   = (string) bgcf_option( 'contact_phone', '' );
	$email   = (string) bgcf_option( 'contact_email', '' );
	$address = (string) bgcf_option( 'contact_address', '' );

	if ( '' !== $phone ) {
		$node['telephone'] = $phone;
	}

	if ( '' !== $email ) {
		$node['email'] = $email;
	}

	if ( '' !== $address ) {
		$node['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => wp_strip_all_tags( $address ),
			'addressCountry'  => (string) bgcf_option( 'org_country', 'UG' ),
		);
	}

	$registration = (string) bgcf_option( 'org_registration_number', '' );

	if ( '' !== $registration ) {
		// A charity's registration number is the single most useful trust signal
		// structured data can carry.
		$node['identifier'] = $registration;
	}

	$profiles = bgcf_schema_social_profiles();

	if ( ! empty( $profiles ) ) {
		$node['sameAs'] = $profiles;
	}

	return $node;
}

/**
 * Social profile URLs, taken from the social menu.
 *
 * Reusing the menu means the profiles in the footer and the profiles in the
 * structured data cannot disagree, and the editor maintains one list.
 *
 * @since 1.2.0
 * @return string[] Absolute URLs.
 */
function bgcf_schema_social_profiles(): array {
	$locations = get_nav_menu_locations();
	$menu_id   = (int) ( $locations['social'] ?? 0 );

	if ( 0 === $menu_id ) {
		return array();
	}

	$items = wp_get_nav_menu_items( $menu_id );

	if ( ! is_array( $items ) ) {
		return array();
	}

	$urls = array();

	foreach ( $items as $item ) {
		$url = esc_url_raw( (string) ( $item->url ?? '' ) );

		if ( '' !== $url ) {
			$urls[] = $url;
		}
	}

	return array_values( array_unique( $urls ) );
}

/**
 * The website node, with the search action.
 *
 * @since 1.2.0
 * @return array<string, mixed> Schema node.
 */
function bgcf_schema_website(): array {
	return array(
		'@type'           => 'WebSite',
		'@id'             => bgcf_schema_id( 'website' ),
		'url'             => home_url( '/' ),
		'name'            => (string) get_bloginfo( 'name' ),
		'publisher'       => array( '@id' => bgcf_schema_id( 'organization' ) ),
		'inLanguage'      => get_bloginfo( 'language' ),
		'potentialAction' => array(
			array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		),
	);
}

/**
 * The breadcrumb node, built from the same items the visible trail uses.
 *
 * @since 1.2.0
 * @return array<string, mixed>|null Schema node, or null when there is no trail.
 */
function bgcf_schema_breadcrumbs(): ?array {
	if ( is_front_page() ) {
		return null;
	}

	// The same list the visible trail renders — see bgcf_get_breadcrumb_items().
	// Building it twice is how a page ends up with structured data describing a
	// path the page does not show.
	$items = bgcf_get_breadcrumb_items();

	if ( count( $items ) < 2 ) {
		return null;
	}

	$elements = array();
	$position = 0;

	foreach ( $items as $item ) {
		$title = (string) ( $item['label'] ?? '' );

		if ( '' === $title ) {
			continue;
		}

		++$position;

		$element = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $title,
		);

		$url = (string) ( $item['url'] ?? '' );

		if ( '' !== $url ) {
			$element['item'] = esc_url_raw( $url );
		}

		$elements[] = $element;
	}

	if ( count( $elements ) < 2 ) {
		return null;
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => bgcf_schema_id( 'breadcrumb' ),
		'itemListElement' => $elements,
	);
}

/**
 * The node describing the current singular post.
 *
 * Maps each post type to the type that actually describes it: an event is an
 * Event (so it can win a date-rich result), a report is a Report, a story and a
 * blog post are Articles. Describing an event as an Article throws away every
 * feature the Event type exists for.
 *
 * @since 1.2.0
 * @return array<string, mixed>|null Schema node, or null when not singular.
 */
function bgcf_schema_singular(): ?array {
	if ( ! is_singular() ) {
		return null;
	}

	$post_id = (int) get_queried_object_id();

	if ( 0 === $post_id ) {
		return null;
	}

	$type = (string) get_post_type( $post_id );
	$url  = (string) get_permalink( $post_id );

	$node = array(
		'@id'        => $url . '#primary',
		'headline'   => wp_strip_all_tags( (string) get_the_title( $post_id ) ),
		'url'        => $url,
		'inLanguage' => get_bloginfo( 'language' ),
		'publisher'  => array( '@id' => bgcf_schema_id( 'organization' ) ),
		'isPartOf'   => array( '@id' => bgcf_schema_id( 'website' ) ),
	);

	$description = bgcf_seo_get_description();

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	$image = bgcf_seo_get_image_url();

	if ( '' !== $image ) {
		$node['image'] = $image;
	}

	switch ( $type ) {
		case 'bgcf_event':
			$node['@type'] = 'Event';
			$node['name']  = $node['headline'];
			unset( $node['headline'] );

			$start = (string) bgcf_field( 'start_date', $post_id, '' );
			$end   = (string) bgcf_field( 'end_date', $post_id, '' );
			$venue = (string) bgcf_field( 'venue', $post_id, '' );

			if ( '' !== $start ) {
				$node['startDate'] = bgcf_schema_date( $start );
			}

			if ( '' !== $end ) {
				$node['endDate'] = bgcf_schema_date( $end );
			}

			if ( '' !== $venue ) {
				$node['location'] = array(
					'@type'   => 'Place',
					'name'    => $venue,
					'address' => (string) bgcf_field( 'address', $post_id, $venue ),
				);
			} else {
				// Google requires a location on an Event; an online event must say
				// so explicitly rather than omit it.
				$node['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
				$node['location']            = array(
					'@type' => 'VirtualLocation',
					'url'   => $url,
				);
			}

			$node['organizer'] = array( '@id' => bgcf_schema_id( 'organization' ) );
			break;

		case 'bgcf_campaign':
			$node['@type'] = 'DonateAction';
			$node['name']  = $node['headline'];
			unset( $node['headline'] );
			$node['recipient'] = array( '@id' => bgcf_schema_id( 'organization' ) );
			break;

		case 'bgcf_report':
			$node['@type'] = 'Report';
			break;

		case 'bgcf_program':
			$node['@type']    = 'Service';
			$node['name']     = $node['headline'];
			unset( $node['headline'] );
			$node['provider'] = array( '@id' => bgcf_schema_id( 'organization' ) );
			break;

		case 'page':
			$node['@type'] = 'WebPage';
			$node['name']  = $node['headline'];
			unset( $node['headline'] );
			break;

		default:
			$node['@type']         = 'Article';
			$node['datePublished'] = (string) get_the_date( DATE_W3C, $post_id );
			$node['dateModified']  = (string) get_the_modified_date( DATE_W3C, $post_id );
			$node['author']        = array(
				'@type' => 'Person',
				'name'  => (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			);
			break;
	}

	return $node;
}

/**
 * Normalise a date field to ISO 8601.
 *
 * ACF date fields come back in whatever display format the field was given, and
 * schema.org requires ISO 8601. An unparseable value is returned untouched
 * rather than guessed at — a wrong date in a rich result is worse than none.
 *
 * @since 1.2.0
 *
 * @param string $value Stored date value.
 * @return string ISO 8601 date, or the original string.
 */
function bgcf_schema_date( string $value ): string {
	$timestamp = strtotime( $value );

	return false === $timestamp ? $value : (string) wp_date( DATE_W3C, $timestamp );
}

/**
 * Assemble the head graph.
 *
 * @since 1.2.0
 *
 * @param array<int, array<string, mixed>> $graph Existing nodes.
 * @return array<int, array<string, mixed>> Filtered nodes.
 */
function bgcf_schema_build_graph( array $graph ): array {
	$graph[] = bgcf_schema_organization();
	$graph[] = bgcf_schema_website();

	foreach ( array( bgcf_schema_breadcrumbs(), bgcf_schema_singular() ) as $node ) {
		if ( is_array( $node ) ) {
			$graph[] = $node;
		}
	}

	return $graph;
}
add_filter( 'bgcf_schema_graph', 'bgcf_schema_build_graph' );

/* -------------------------------------------------------------------------
 * Late graph: FAQ
 * ---------------------------------------------------------------------- */

/**
 * FAQ items collected while the page rendered.
 *
 * @since 1.2.0
 * @var array<int, array{question:string, answer:string}>
 */
$GLOBALS['bgcf_faq_items'] = array();

/**
 * Record FAQ items for the late graph.
 *
 * Called by partials/blocks/block-faq.php *after* it has rendered, so only
 * questions that actually appear on the page are described.
 *
 * @since 1.2.0
 *
 * @param array<int, array{question:string, answer:string}> $items FAQ items.
 * @return void
 */
function bgcf_register_faq_items( array $items ): void {
	foreach ( $items as $item ) {
		$question = trim( (string) ( $item['question'] ?? '' ) );
		$answer   = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );

		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$GLOBALS['bgcf_faq_items'][] = array(
			'question' => $question,
			'answer'   => $answer,
		);
	}
}

/**
 * Emit the late graph in the footer.
 *
 * @since 1.2.0
 * @return void
 */
function bgcf_schema_render_late(): void {
	if ( bgcf_seo_is_delegated() || is_admin() ) {
		return;
	}

	$items = (array) ( $GLOBALS['bgcf_faq_items'] ?? array() );

	if ( empty( $items ) ) {
		return;
	}

	$questions = array();

	foreach ( $items as $item ) {
		$questions[] = array(
			'@type'          => 'Question',
			'name'           => (string) $item['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => (string) $item['answer'],
			),
		);
	}

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type'      => 'FAQPage',
				'@id'        => bgcf_seo_get_canonical_url() . '#faq',
				'mainEntity' => $questions,
			),
		),
	);

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}
add_action( 'wp_footer', 'bgcf_schema_render_late', 20 );
