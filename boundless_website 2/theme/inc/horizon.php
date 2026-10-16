<?php
/**
 * Horizon layout system — the card family and the three band renderers.
 *
 * WHAT THIS MODULE IS FOR
 * -----------------------
 * The Horizon design puts six card shapes and three band shapes on almost every
 * page. Before this module existed, each block partial built its own card markup
 * and the six cards drifted: three different heading levels, two different
 * "Read more" affordances, one card that forgot its image dimensions. The fix is
 * that a card is data plus a type, and exactly one partial renders each type.
 *
 * So: block partials and page templates gather CONTENT and call these helpers.
 * They never write card markup. If a card needs a new affordance, it is added
 * here and every page that uses that card gets it.
 *
 * THE CARD CONTRACT
 * -----------------
 * All six types accept the same argument bundle (see bgcf_card_defaults()) and
 * ignore the keys that do not apply to them. That is deliberate: a listing block
 * can map a post to the bundle once and switch card type from a field without
 * re-mapping anything, which is what makes "show these as news cards instead"
 * a one-line change.
 *
 * Every card is a LINKED CARD when it has a URL, and the link wraps the title
 * only — never the whole card. A card-sized `<a>` swallows the text selection,
 * breaks right-click-copy on the description and reads as one enormous link to a
 * screen reader. The whole-card click target is restored with a stretched
 * pseudo-element instead (see `components/_card.scss`), which keeps the
 * accessible name short and the pointer target large.
 *
 * ESCAPING. Same contract as inc/template-tags.php: `bgcf_get_*` returns escaped
 * markup, the bare verb echoes it, and the partials escape at the point of
 * output. A caller never escapes before handing content in.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The card types Horizon ships.
 *
 * Each key is a type name and each value is the partial that renders it,
 * relative to `partials/` and without the extension. Registering a type here
 * without creating the partial produces an editor-facing empty state rather
 * than a fatal error.
 *
 * - `project` Image on top, tinted body, title, description, text link. The
 *             workhorse: programmes, campaigns, ways to give.
 * - `stat`    Icon, rule, large number, short label. No link.
 * - `service` Flat card with no image, for lists of offers on a dark band.
 * - `news`    Image on top, warm body, date, title, text link.
 * - `action`  Image, title, and a real button — used where the card IS the
 *             call to action rather than a route to more reading.
 * - `person`  Portrait, name, role, optional one-line bio.
 *
 * @since 2.0.0
 * @return array<string, string> Type name => partial slug.
 */
function bgcf_card_types(): array {
	/**
	 * Filters the card type registry.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, string> $types Type name => partial slug.
	 */
	return (array) apply_filters(
		'bgcf_card_types',
		array(
			'project' => 'cards/card-project',
			'stat'    => 'cards/card-stat',
			'service' => 'cards/card-service',
			'news'    => 'cards/card-news',
			'action'  => 'cards/card-action',
			'person'  => 'cards/card-person',
		)
	);
}

/**
 * The card type a post type's listings should use.
 *
 * ONE MAP, shared by the archive template, the related-reading band and the
 * search results, so the three places that render a grid of one post type agree
 * about how it should look. When this lived inline in archive.php, the related
 * band applied the same filter against an empty default array and fell back to
 * news cards for everything — which dated a programme to the day its post was
 * published.
 *
 * The choice is editorial, not cosmetic: a news card leads with a date and so
 * says "this happened"; a project card leads with a summary and so says "this
 * exists". A programme is not an event.
 *
 * @since 2.0.0
 *
 * @param string $post_type Post type name.
 * @return string Card type from bgcf_card_types().
 */
function bgcf_card_type_for_post_type( string $post_type ): string {
	/**
	 * Filters the post type to card type map.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, string> $map Post type => card type.
	 */
	$map = (array) apply_filters(
		'bgcf_archive_card_types',
		array(
			'post'             => 'news',
			'bgcf_story'       => 'news',
			'bgcf_event'       => 'news',
			'bgcf_program'     => 'project',
			'bgcf_campaign'    => 'project',
			'bgcf_report'      => 'project',
			'bgcf_team'        => 'person',
			'bgcf_testimonial' => 'project',
		)
	);

	$type = (string) ( $map[ $post_type ] ?? 'project' );

	// A filter that returns a type with no partial behind it would render an
	// editor-facing error on every card in the grid.
	return array_key_exists( $type, bgcf_card_types() ) ? $type : 'project';
}

/**
 * The shared card argument bundle.
 *
 * Documented here rather than in six partials so there is one place to read
 * what a card can be given.
 *
 * @since 2.0.0
 * @return array<string, mixed> Defaults.
 */
function bgcf_card_defaults(): array {
	return array(
		// Identity and routing.
		'title'       => '',
		'title_tag'   => 'h3',
		'url'         => '',
		'target'      => '',

		// Body.
		'eyebrow'     => '',
		'text'        => '',
		'meta'        => array(),

		// Media. `image` is an attachment ID; `icon` is a slug from
		// assets/images/icons/. A card never has both.
		'image'       => 0,
		'image_size'  => 'bgcf-card',
		'image_alt'   => '',
		'icon'        => '',

		// Stat card only. `decimals` is how many the count-up animates to —
		// "4.2 million" needs one, "12,400 children" needs none, and animating
		// a whole number through decimal places looks like a bug.
		'value'       => '',
		'decimals'    => 0,
		'prefix'      => '',
		'suffix'      => '',
		'label'       => '',

		// Affordance. `link_text` labels the text link; `button` turns the
		// affordance into a real button (the action card's default).
		'link_text'   => '',
		'button'      => false,

		// Presentation.
		'class'       => '',
		'loading'     => 'lazy',

		/*
		 * Above-the-fold cards must not be lazy-loaded; the first row of a grid
		 * in a hero-adjacent band is the LCP element on several templates.
		 */
		'priority'    => false,
	);
}

/**
 * Render one card.
 *
 * @since 2.0.0
 *
 * @param string               $type Card type. See bgcf_card_types().
 * @param array<string, mixed> $args Card arguments. See bgcf_card_defaults().
 * @return void
 */
function bgcf_card( string $type, array $args = array() ): void {
	echo bgcf_get_card( $type, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the partial.
}

/**
 * Build one card.
 *
 * @since 2.0.0
 *
 * @param string               $type Card type. See bgcf_card_types().
 * @param array<string, mixed> $args Card arguments. See bgcf_card_defaults().
 * @return string Escaped markup, or an empty string when the card has no content.
 */
function bgcf_get_card( string $type, array $args = array() ): string {
	$types = bgcf_card_types();
	$type  = sanitize_key( $type );

	if ( ! array_key_exists( $type, $types ) ) {
		ob_start();
		bgcf_empty_state(
			sprintf(
				/* translators: 1: card type that was requested, 2: comma-separated list of valid types. */
				__( 'Unknown card type "%1$s". Available types: %2$s.', 'bgcf' ),
				$type,
				implode( ', ', array_keys( $types ) )
			)
		);

		return (string) ob_get_clean();
	}

	$args = wp_parse_args( $args, bgcf_card_defaults() );

	/**
	 * Filters a card's arguments before it renders.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $args Resolved card arguments.
	 * @param string               $type Card type.
	 */
	$args = (array) apply_filters( 'bgcf_card_args', $args, $type );

	// A card with neither a title nor a stat value has nothing to say. Rendering
	// the empty shell is worse than rendering nothing: it leaves a hole in the
	// grid that looks like a broken image.
	if ( '' === trim( (string) $args['title'] ) && '' === trim( (string) $args['value'] ) ) {
		return '';
	}

	$args['type'] = $type;

	// `priority` is the readable way for a caller to say "this card is above the
	// fold"; it resolves to the two attributes that actually matter.
	if ( $args['priority'] ) {
		$args['loading'] = 'eager';
	}

	return bgcf_render_partial( $types[ $type ], $args, false );
}

/**
 * Map a post onto the card argument bundle.
 *
 * Every listing block needs the same six lines of `get_the_title()` /
 * `get_permalink()` / `get_post_thumbnail_id()`, and every one of them is a
 * chance to forget the excerpt fallback or the featured-image default. This is
 * that mapping, once.
 *
 * The excerpt is taken from the manual excerpt when there is one and generated
 * from the content otherwise, trimmed to a card-sized length — a card is not a
 * place to discover that a programme description runs to 400 words.
 *
 * @since 2.0.0
 *
 * @param int|WP_Post          $post      Post to map.
 * @param array<string, mixed> $overrides Arguments merged over the mapped values.
 * @return array<string, mixed> Card arguments, or an empty array when the post is invalid.
 */
function bgcf_card_args_from_post( $post, array $overrides = array() ): array {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	/**
	 * Filters the word count a card excerpt is trimmed to.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $words Word count.
	 * @param WP_Post $post  Post being mapped.
	 */
	$words = max( 6, (int) apply_filters( 'bgcf_card_excerpt_words', 24, $post ) );

	$excerpt = has_excerpt( $post )
		? (string) get_the_excerpt( $post )
		: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), $words, '…' );

	$args = array(
		'title' => (string) get_the_title( $post ),
		'url'   => (string) get_permalink( $post ),
		'text'  => wp_trim_words( wp_strip_all_tags( $excerpt ), $words, '…' ),
		'image' => (int) get_post_thumbnail_id( $post ),
	);

	/**
	 * Filters the card arguments mapped from a post.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $args      Mapped arguments, before overrides.
	 * @param WP_Post              $post      Post that was mapped.
	 * @param array<string, mixed> $overrides Caller overrides, not yet applied.
	 */
	$args = (array) apply_filters( 'bgcf_card_args_from_post', $args, $post, $overrides );

	return array_merge( $args, $overrides );
}

/**
 * Normalise an ACF Relationship / Post Object value to post IDs.
 *
 * ACF returns posts, IDs or arrays depending on how the field was configured,
 * and the configuration can be changed in wp-admin after the block partial was
 * written. Every listing block needs this coercion, and getting it wrong shows
 * up as an empty grid rather than as an error.
 *
 * @since 2.0.0
 *
 * @param mixed $value ACF field value.
 * @return int[] Post IDs, in the order the editor arranged them.
 */
function bgcf_to_post_ids( $value ): array {
	if ( ! is_array( $value ) ) {
		$value = array( $value );
	}

	return array_values(
		array_filter(
			array_map(
				static function ( $item ) {
					if ( $item instanceof WP_Post ) {
						return (int) $item->ID;
					}

					if ( is_array( $item ) ) {
						return (int) ( $item['ID'] ?? 0 );
					}

					return absint( $item );
				},
				$value
			)
		)
	);
}

/**
 * Normalise an ACF Taxonomy field value to a term ID.
 *
 * Same reasoning as bgcf_to_post_ids().
 *
 * @since 2.0.0
 *
 * @param mixed $value ACF field value.
 * @return int Term ID, or 0.
 */
function bgcf_to_term_id( $value ): int {
	if ( $value instanceof WP_Term ) {
		return (int) $value->term_id;
	}

	if ( is_array( $value ) ) {
		// A multi-select taxonomy field returns a list; the listing blocks all
		// filter by a single term, so the first is the one that was meant.
		$first = reset( $value );

		if ( $first instanceof WP_Term ) {
			return (int) $first->term_id;
		}

		return absint( is_array( $first ) ? ( $first['term_id'] ?? 0 ) : $first );
	}

	return absint( $value );
}

/**
 * Build the query a listing block runs.
 *
 * Three selection modes, because editors genuinely want all three: curate an
 * exact order by hand, filter by a taxonomy term, or let the newest surface
 * automatically. Writing that logic once rather than once per block is the
 * point of this function — the three listing blocks had three copies of it and
 * two of them had drifted.
 *
 * QUERY RULES, enforced here and not negotiable per block:
 *
 *   - `no_found_rows`: nothing in a card grid paginates, so counting the total
 *     is work thrown away on every page load.
 *   - `post_status => publish`: a draft programme must not leak onto the
 *     homepage, and `WP_Query`'s default includes private posts for logged-in
 *     editors — which means an editor previewing the page sees cards a visitor
 *     will not.
 *   - A HARD CEILING of twelve, and never `posts_per_page => -1`. An editor who
 *     adds four hundred programmes should not be able to take the homepage down
 *     by doing so.
 *
 * @since 2.0.0
 *
 * @param string               $post_type Post type to query.
 * @param array<string, mixed> $args {
 *     Selection arguments, usually read straight from ACF fields.
 *
 *     @type string $source   'latest' (default), 'manual' or 'term'.
 *     @type mixed  $manual   Relationship field value, for 'manual'.
 *     @type mixed  $term     Taxonomy field value, for 'term'.
 *     @type string $taxonomy Taxonomy to filter on, for 'term'.
 *     @type int    $limit    Maximum posts. Clamped to 1-12. Default 3.
 * }
 * @return WP_Query The executed query.
 */
function bgcf_block_query( string $post_type, array $args = array() ): WP_Query {
	$args = wp_parse_args(
		$args,
		array(
			'source'   => 'latest',
			'manual'   => array(),
			'term'     => 0,
			'taxonomy' => '',
			'limit'    => 3,
		)
	);

	$limit = max( 1, min( 12, (int) $args['limit'] ) );

	$query_args = array(
		'post_type'              => $post_type,
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => false,
	);

	if ( 'manual' === $args['source'] ) {
		$ids = bgcf_to_post_ids( $args['manual'] );

		if ( ! empty( $ids ) ) {
			$query_args['post__in']       = $ids;
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = min( count( $ids ), 12 );
		}
	} elseif ( 'term' === $args['source'] && '' !== $args['taxonomy'] ) {
		$term_id = bgcf_to_term_id( $args['term'] );

		if ( $term_id > 0 ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Editor-chosen filter; this is the feature.
				array(
					'taxonomy' => (string) $args['taxonomy'],
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			);
		}
	}

	/**
	 * Filters a listing block's query arguments.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments.
	 * @param string               $post_type  Post type being queried.
	 * @param array<string, mixed> $args       Resolved selection arguments.
	 */
	$query_args = (array) apply_filters( 'bgcf_block_query_args', $query_args, $post_type, $args );

	return new WP_Query( $query_args );
}

/**
 * Open a card grid.
 *
 * The column count is an UPPER BOUND, not a promise — `components/_grid.scss`
 * collapses the tracks on narrow viewports without a media query. Pass the
 * count the design asks for at desktop width and let the CSS handle the rest.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args {
 *     Optional arguments.
 *
 *     @type int    $columns Desktop column count, 2-4. Default 3.
 *     @type string $class   Extra class names.
 *     @type string $tag     'ul' (default) or 'div'. A grid of cards is a list;
 *                           use 'div' only when the cards are not siblings in
 *                           meaning.
 *     @type string $label   Accessible name for the list.
 *     @type bool   $stagger Reveal the cards in sequence on scroll. Default true.
 * }
 * @return void
 */
function bgcf_card_grid_open( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'columns' => 3,
			'class'   => '',
			'tag'     => 'ul',
			'label'   => '',
			'stagger' => true,
		)
	);

	$columns = min( 4, max( 2, (int) $args['columns'] ) );
	$tag     = 'div' === $args['tag'] ? 'div' : 'ul';

	$attrs = array(
		'class' => bgcf_classnames(
			array(
				'bgcf-grid',
				'bgcf-grid--cols-' . $columns,
				'ul' === $tag ? 'bgcf-grid--list' : '',
				(string) $args['class'],
			)
		),
	);

	/*
	 * The stagger is opt-OUT rather than opt-in because every card grid in the
	 * design has it, and a grid that forgot to ask would be the odd one out.
	 * `assets/js/src/motion.js` only arms it when the visitor has not asked for
	 * reduced motion, so the attribute is inert rather than conditional.
	 */
	if ( $args['stagger'] ) {
		$attrs['data-bgcf-reveal-stagger'] = true;
	}

	if ( '' !== $args['label'] ) {
		$attrs['aria-label'] = (string) $args['label'];
	}

	echo '<' . tag_escape( $tag ) . bgcf_get_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

/**
 * Close a card grid.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args Must mirror bgcf_card_grid_open()'s `tag`.
 * @return void
 */
function bgcf_card_grid_close( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'tag' => 'ul' ) );

	echo '</' . tag_escape( 'div' === $args['tag'] ? 'div' : 'ul' ) . '>';
}

/**
 * Render a list of cards inside a grid.
 *
 * The common case — a block has an array of card bundles and wants them in a
 * grid — in one call. When a grid is a `ul`, each card is wrapped in an `li`
 * here rather than in the card partial, so the same partial can also be used
 * outside a list (in a split section, say).
 *
 * @since 2.0.0
 *
 * @param string                           $type  Card type.
 * @param array<int, array<string, mixed>> $cards Card argument bundles.
 * @param array<string, mixed>             $grid  Grid arguments. See bgcf_card_grid_open().
 * @return int Number of cards actually rendered.
 */
function bgcf_card_grid( string $type, array $cards, array $grid = array() ): int {
	$grid = wp_parse_args( $grid, array( 'tag' => 'ul' ) );

	$rendered = array();

	foreach ( $cards as $card ) {
		if ( ! is_array( $card ) ) {
			continue;
		}

		$markup = bgcf_get_card( $type, $card );

		if ( '' === $markup ) {
			continue;
		}

		$rendered[] = $markup;
	}

	if ( empty( $rendered ) ) {
		return 0;
	}

	bgcf_card_grid_open( $grid );

	$is_list = 'div' !== $grid['tag'];

	foreach ( $rendered as $markup ) {
		/*
		 * `data-bgcf-reveal` goes on the LIST ITEM, not on the card. motion.js
		 * staggers an element by looking at its parent for
		 * `data-bgcf-reveal-stagger`, and the card's parent is the `<li>` — a
		 * card marked for reveal inside an unmarked `<li>` animates, but all at
		 * once. In `div` mode the card IS the direct child, so it carries the
		 * attribute itself (see partials/cards/card-stat.php).
		 */
		if ( $is_list ) {
			echo '<li class="bgcf-grid__item" data-bgcf-reveal>';
		}

		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the card partial.

		if ( $is_list ) {
			echo '</li>';
		}
	}

	bgcf_card_grid_close( $grid );

	return count( $rendered );
}

/**
 * Build the plain text link that closes a card.
 *
 * Horizon's cards end in a text link rather than a button: a grid of six
 * buttons is six competing primary actions, and the card's own click target
 * already covers the pointer case. The arrow is decorative and moves on hover.
 *
 * ACCESSIBLE NAME. "Read more" repeated nine times down a page is a known
 * screen-reader failure (WCAG 2.4.4) — the links are indistinguishable out of
 * context. `$context` is appended in a visually hidden span so the accessible
 * name becomes "Read more about Safe Water for Schools" while the visible text
 * stays short.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args {
 *     Link arguments.
 *
 *     @type string $text    Visible label. Required.
 *     @type string $url     Destination. Required.
 *     @type string $context What the link leads to, for the accessible name.
 *     @type string $target  Link target. '_blank' adds a safe rel.
 *     @type string $class   Extra class names.
 *     @type bool   $stretch Make the link cover the whole card. Default true.
 * }
 * @return string Escaped markup, or an empty string when text or URL is missing.
 */
function bgcf_get_text_link( array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'text'    => '',
			'url'     => '',
			'context' => '',
			'target'  => '',
			'class'   => '',
			'stretch' => true,
		)
	);

	$text = trim( (string) $args['text'] );
	$url  = trim( (string) $args['url'] );

	if ( '' === $text || '' === $url ) {
		return '';
	}

	$attrs = array(
		'class' => bgcf_classnames(
			array(
				'bgcf-text-link',
				'bgcf-text-link--stretch' => (bool) $args['stretch'],
				(string) $args['class'],
			)
		),
		'href'  => esc_url_raw( $url ),
	);

	if ( '_blank' === $args['target'] ) {
		$attrs['target'] = '_blank';
		$attrs['rel']    = 'noopener noreferrer';
	}

	$context = trim( (string) $args['context'] );
	$suffix  = '';

	if ( '' !== $context ) {
		$suffix = sprintf(
			'<span class="bgcf-u-visually-hidden"> %s</span>',
			esc_html( $context )
		);
	}

	return sprintf(
		'<a%1$s>%2$s%3$s%4$s</a>',
		bgcf_get_attrs( $attrs ),
		esc_html( $text ),
		$suffix,
		bgcf_get_svg_icon( 'arrow-right', array( 'class' => 'bgcf-text-link__icon' ) )
	);
}

/**
 * Echo the plain text link that closes a card.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args See bgcf_get_text_link().
 * @return void
 */
function bgcf_text_link( array $args = array() ): void {
	echo bgcf_get_text_link( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
}

/**
 * Render a split section: media on one side, words on the other.
 *
 * Horizon's most-used band. The three ratios come from the design and are a
 * closed set for the same reason the band colours are: an editor choosing
 * "about two thirds" produces a page where no two split sections line up.
 *
 * STACKING ORDER. Below the `md` breakpoint the columns stack and the IMAGE
 * COMES FIRST regardless of which side it sits on at desktop width. That is a
 * deliberate break from source order: on a phone the photograph is the hook,
 * and a reader who has to scroll past 90 words to reach it has already left.
 * The DOM order is image-first and `order` moves it at desktop, so the stacked
 * order needs no override and the tab order always matches the visual one.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args {
 *     Section arguments.
 *
 *     @type string               $eyebrow    Small label above the heading.
 *     @type string               $title      Heading text. Required.
 *     @type string               $title_tag  Heading level. Default 'h2'.
 *     @type string               $text       Body copy. Allows inline HTML.
 *     @type int                  $image      Attachment ID. Required.
 *     @type string               $image_size Registered size. Default 'bgcf-story'.
 *     @type string               $image_alt  Alt override.
 *     @type string               $media_side 'start' (default) or 'end' — which side the
 *                                            image sits on at desktop width.
 *     @type string               $ratio      'even' (1:1, default), 'text' (text wider,
 *                                            ~7:4) or 'media' (media wider, 1:2).
 *     @type array<string, mixed> $cards      Optional cards rendered under the text.
 *     @type string               $card_type  Card type for `$cards`. Default 'service'.
 *     @type int                  $card_cols  Column count for `$cards`. Default 2.
 *     @type mixed                $link       ACF link array or URL for the button.
 *     @type string               $link_style 'primary' (default) or 'secondary'.
 *     @type string               $id         Section id.
 *     @type string               $background Band role. Default 'soft'.
 *     @type string               $spacing    Vertical rhythm. Default 'md'.
 *     @type bool                 $priority   Above the fold. Default false.
 *     @type string               $class      Extra class names.
 * }
 * @return void
 */
function bgcf_split_section( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow'    => '',
			'title'      => '',
			'title_tag'  => 'h2',
			'text'       => '',
			'image'      => 0,
			'image_size' => 'bgcf-story',
			'image_alt'  => '',
			'media_side' => 'start',
			'ratio'      => 'even',
			'cards'      => array(),
			'card_type'  => 'service',
			'card_cols'  => 2,
			'link'       => '',
			'link_style' => 'primary',
			'id'         => '',
			'background' => 'soft',
			'spacing'    => 'md',
			'priority'   => false,
			'class'      => '',
		)
	);

	if ( '' === trim( (string) $args['title'] ) ) {
		bgcf_empty_state( __( 'Split section: add a heading.', 'bgcf' ) );

		return;
	}

	bgcf_render_partial( 'global/split-section', $args );
}

/**
 * Render a photographic call-to-action band.
 *
 * Full-bleed photograph, scrim, centred white heading, one sentence, one
 * button. Used twice on the homepage to break up the colour bands; the scrim is
 * what makes it safe to let an editor choose the photograph (see
 * `tools/_elevation.scss` `photo-scrim`).
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args {
 *     Band arguments.
 *
 *     @type string $eyebrow    Small label above the heading.
 *     @type string $title      Heading text. Required.
 *     @type string $title_tag  Heading level. Default 'h2'.
 *     @type string $text       One supporting sentence.
 *     @type int    $image      Background attachment ID.
 *     @type mixed  $link       ACF link array or URL for the primary button.
 *     @type mixed  $link_alt   Optional second, lower-emphasis link.
 *     @type int    $scrim      Scrim opacity percentage, 55-85. Default 62.
 *     @type string $id         Section id.
 *     @type string $spacing    Vertical rhythm. Default 'md'.
 *     @type string $class      Extra class names.
 * }
 * @return void
 */
function bgcf_photo_cta( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow'   => '',
			'title'     => '',
			'title_tag' => 'h2',
			'text'      => '',
			'image'     => 0,
			'link'      => '',
			'link_alt'  => '',
			'scrim'     => 62,
			'id'        => '',
			'spacing'   => 'md',
			'class'     => '',
		)
	);

	if ( '' === trim( (string) $args['title'] ) ) {
		bgcf_empty_state( __( 'Photo call-to-action band: add a heading.', 'bgcf' ) );

		return;
	}

	bgcf_render_partial( 'global/photo-cta', $args );
}

/**
 * Render the banner that opens every inner page.
 *
 * One `h1` over a photograph, with the breadcrumb trail beneath it. Pages that
 * have no banner image fall back to the deep-blue band rather than to nothing,
 * so the page still opens with a defined masthead and the `h1` never lands on
 * the page background.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args {
 *     Banner arguments.
 *
 *     @type string $eyebrow    Small label above the heading.
 *     @type string $title      Page title. Defaults to the queried object's title.
 *     @type string $text       One supporting sentence.
 *     @type int    $image      Background attachment ID. Defaults to the featured image.
 *     @type bool   $breadcrumb Render the breadcrumb trail. Default true.
 *     @type mixed  $link       Optional call to action.
 *     @type string $align      'start' (default) or 'center'.
 *     @type string $class      Extra class names.
 * }
 * @return void
 */
function bgcf_sub_banner( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow'    => '',
			'title'      => '',
			'text'       => '',
			'image'      => 0,
			'breadcrumb' => true,
			'link'       => '',
			'align'      => 'start',
			'class'      => '',
		)
	);

	if ( '' === trim( (string) $args['title'] ) ) {
		$args['title'] = is_singular() ? (string) get_the_title() : wp_strip_all_tags( get_the_archive_title() );
	}

	if ( ! $args['image'] && is_singular() ) {
		$args['image'] = (int) get_post_thumbnail_id();
	}

	bgcf_render_partial( 'global/sub-banner', $args );
}

/**
 * Emphasise one phrase inside a heading.
 *
 * The Horizon hero sets one clause of its headline apart — "every child deserves
 * *a place to belong*" — and that emphasis is part of the design rather than a
 * decoration.
 *
 * WHY A SECOND FIELD AND NOT HTML IN THE HEADING. The obvious implementation is
 * to let the heading field hold markup and run it through `wp_kses` with an
 * `<em>`-only allowlist. That is safe, but it means an editor types angle
 * brackets into a plain text input and finds out whether they got it right by
 * reloading the page. Instead the editor types the phrase on its own, in a
 * second field, and this function finds it.
 *
 * The matching is deliberately strict: an exact, case-sensitive substring. A
 * fuzzy match that emphasised the wrong words would be worse than one that
 * quietly emphasises nothing, and a phrase that does not appear in the heading
 * is an editor mistake the preview makes obvious.
 *
 * ESCAPING. The heading is escaped FIRST and the phrase is escaped with it, so
 * the needle still matches inside the escaped haystack (a phrase containing an
 * apostrophe becomes `&#039;` on both sides). The `<em>` tags are the only
 * markup this function introduces, so the result is safe to echo unescaped —
 * and must not be escaped again by the caller, or the tags become visible text.
 *
 * @since 2.0.0
 *
 * @param string $heading Heading text, unescaped.
 * @param string $phrase  Phrase within it to emphasise, unescaped. May be empty.
 * @return string Escaped heading, with the phrase wrapped in `<em>` if found.
 */
function bgcf_get_emphasised_heading( string $heading, string $phrase ): string {
	$html = esc_html( $heading );
	$phrase = trim( $phrase );

	if ( '' === $phrase ) {
		return $html;
	}

	$needle   = esc_html( $phrase );
	$position = strpos( $html, $needle );

	if ( false === $position ) {
		return $html;
	}

	return substr_replace(
		$html,
		'<em>' . $needle . '</em>',
		$position,
		strlen( $needle )
	);
}

/**
 * Whether the header should overlay the first band instead of sitting above it.
 *
 * True only when the page opens with a full-bleed photographic hero, which is
 * the one band designed to have a transparent header laid over it.
 *
 * WHY THIS IS A SERVER-SIDE DECISION. The alternative is to let the front end
 * work it out after paint, and both failure modes are visible: a header that
 * renders solid and is then made transparent flashes on every hero page, and one
 * that renders transparent on a page with no hero puts white text on a white
 * band until the script runs. Neither is acceptable, and both are avoidable —
 * the information needed is in the post content, which is available here.
 *
 * DETECTION. The first non-empty block in the content must be the hero. Not
 * "contains a hero" — a hero placed halfway down a page is a mid-page band and
 * the header must stay solid above it. `parse_blocks()` emits anonymous blocks
 * for the whitespace between real ones, so those are skipped before the test.
 *
 * @since 2.0.0
 * @return bool True when the header overlays the first band.
 */
function bgcf_header_is_overlay(): bool {
	$overlay = false;

	// The 404 template opens with its own hero band, and has no post content to
	// inspect.
	if ( is_404() ) {
		$overlay = true;
	} elseif ( is_singular() || is_front_page() ) {
		$post = get_post();

		if ( $post instanceof WP_Post && has_blocks( $post->post_content ) ) {
			foreach ( parse_blocks( $post->post_content ) as $block ) {
				$name = (string) ( $block['blockName'] ?? '' );

				// Anonymous blocks are the whitespace between real ones.
				if ( '' === $name ) {
					continue;
				}

				$overlay = in_array( $name, array( 'bgcf/hero', 'acf/hero' ), true );

				break;
			}
		}
	}

	/**
	 * Filters whether the header overlays the first band.
	 *
	 * Page templates that build a photographic masthead of their own — the
	 * donation hub, for instance — return true here rather than duplicating the
	 * header markup.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $overlay Whether to overlay.
	 */
	return (bool) apply_filters( 'bgcf_header_is_overlay', $overlay );
}

/**
 * Resolve the column ratio modifier for a split section.
 *
 * Kept as a function rather than inlined in the partial so the allow-list has
 * one home and an unknown value degrades to the even split instead of emitting
 * a class with no stylesheet behind it.
 *
 * @since 2.0.0
 *
 * @param string $ratio Requested ratio.
 * @return string One of 'even', 'text', 'media'.
 */
function bgcf_split_ratio( string $ratio ): string {
	return in_array( $ratio, array( 'even', 'text', 'media' ), true ) ? $ratio : 'even';
}
