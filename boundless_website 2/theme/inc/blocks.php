<?php
/**
 * Gutenberg blocks — the `bgcf/*` namespace.
 *
 * Strategy: ALL custom blocks are ACF blocks (PHP-rendered), not native JS
 * blocks. Rationale:
 *
 *   - One rendering path. A block partial and a template partial are the same
 *     file shape and share the same template tags, so a section can be rendered
 *     from a block, a page template or an archive without duplication.
 *   - No build step or React dependency for content components; the design
 *     system stays the single source of truth for markup.
 *   - Server rendering means markup fixes ship without re-saving posts (no
 *     block validation invalidation).
 *
 * Cost, accepted knowingly: ACF Pro is required for editing, and editor preview
 * fidelity depends on `assets/css/editor.css` mirroring the front end.
 *
 * Naming: block name `bgcf/{slug}`; render partial
 * `partials/blocks/block-{slug}.php`; root CSS class `.bgcf-block-{slug}`.
 * The slug is written into post content the moment an editor saves — renaming a
 * block orphans every page that uses it. See ARCHITECTURE.md.
 *
 * Namespace note: ACF 6 preserves an explicit namespace passed to
 * acf_register_block_type(); ACF 5 rewrites it to `acf/{slug}`. Every consumer
 * in this theme is namespace-agnostic (see bgcf_render_block() and
 * bgcf_get_block_editor_names()) so both behaviours render identically. ACF
 * field group location rules must target whichever name the install actually
 * registers — verify once, in Phase 5, against WP_Block_Type_Registry.
 *
 * Phase 1 registers the plumbing and ships empty-state partials. Phases 3 and 5
 * add the ACF field groups and the real markup; no registration change needed.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the `bgcf` block category so theme blocks group together in the
 * inserter, above core categories.
 *
 * @since 1.0.0
 *
 * @param array<int, array<string, mixed>> $categories Block categories.
 * @return array<int, array<string, mixed>> Filtered categories.
 */
function bgcf_register_block_category( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'bgcf',
			'title' => __( 'Boundless Gracia', 'bgcf' ),
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'bgcf_register_block_category' );

/**
 * The block registry.
 *
 * Add a block by adding a row here and creating the matching partial. Nothing
 * else needs to change.
 *
 * @since 1.0.0
 * @return array<string, array<string, mixed>> Block slug => definition.
 */
function bgcf_get_block_definitions(): array {
	$blocks = array(
		'hero'            => array(
			'title'       => __( 'Hero', 'bgcf' ),
			'description' => __( 'Full-width opening section: image, overlay, headline and two calls to action.', 'bgcf' ),
			'icon'        => 'cover-image',
			'keywords'    => array( 'hero', 'banner', 'header', 'intro' ),
			'align'       => 'full',
			'supports'    => array( 'align' => array( 'full' ) ),
		),
		'statistics'      => array(
			'title'       => __( 'Statistics', 'bgcf' ),
			'description' => __( 'Band of animated impact counters.', 'bgcf' ),
			'icon'        => 'chart-bar',
			'keywords'    => array( 'stats', 'counter', 'impact', 'numbers' ),
			'align'       => 'full',
		),
		'cta'             => array(
			'title'       => __( 'Call to Action', 'bgcf' ),
			'description' => __( 'Headline, supporting text and buttons on a brand background.', 'bgcf' ),
			'icon'        => 'megaphone',
			'keywords'    => array( 'cta', 'action', 'button', 'volunteer' ),
			'align'       => 'wide',
		),
		'donation-banner' => array(
			'title'       => __( 'Donation Banner', 'bgcf' ),
			'description' => __( 'Donation prompt with amount presets and the Flutterwave giving form (mobile money and cards).', 'bgcf' ),
			'icon'        => 'heart',
			'keywords'    => array( 'donate', 'give', 'fundraising', 'banner' ),
			'align'       => 'full',
		),
		'program-cards'   => array(
			'title'       => __( 'Program Cards', 'bgcf' ),
			'description' => __( 'Grid of programs pulled from the Programs post type.', 'bgcf' ),
			'icon'        => 'grid-view',
			'keywords'    => array( 'programs', 'cards', 'grid', 'work' ),
			'align'       => 'wide',
		),
		'gallery'         => array(
			'title'       => __( 'Gallery', 'bgcf' ),
			'description' => __( 'Filterable photo and video grid with a lightbox.', 'bgcf' ),
			'icon'        => 'format-gallery',
			'keywords'    => array( 'gallery', 'photos', 'media', 'lightbox' ),
			'align'       => 'wide',
		),
		'testimonials'    => array(
			'title'       => __( 'Testimonials', 'bgcf' ),
			'description' => __( 'Carousel of quotes from the Testimonials post type.', 'bgcf' ),
			'icon'        => 'format-quote',
			'keywords'    => array( 'testimonial', 'quote', 'review', 'carousel' ),
			'align'       => 'wide',
		),
		'timeline'        => array(
			'title'       => __( 'Timeline', 'bgcf' ),
			'description' => __( 'Chronological milestones, used for the foundation history.', 'bgcf' ),
			'icon'        => 'clock',
			'keywords'    => array( 'timeline', 'history', 'milestones', 'about' ),
			'align'       => 'wide',
		),
		'partners'        => array(
			'title'       => __( 'Partners', 'bgcf' ),
			'description' => __( 'Logo wall or marquee of supporting organisations.', 'bgcf' ),
			'icon'        => 'networking',
			'keywords'    => array( 'partners', 'logos', 'supporters', 'sponsors' ),
			'align'       => 'full',
		),
		'faq'             => array(
			'title'       => __( 'FAQ', 'bgcf' ),
			'description' => __( 'Accessible accordion of questions and answers.', 'bgcf' ),
			'icon'        => 'editor-help',
			'keywords'    => array( 'faq', 'questions', 'accordion', 'help' ),
			'align'       => 'wide',
		),
		'newsletter'      => array(
			'title'       => __( 'Newsletter', 'bgcf' ),
			'description' => __( 'Email sign-up form submitted over AJAX.', 'bgcf' ),
			'icon'        => 'email-alt',
			'keywords'    => array( 'newsletter', 'subscribe', 'email', 'signup' ),
			'align'       => 'wide',
		),

		/*
		 * ------------------------------------------------------------------
		 * Horizon bands (2.0.0).
		 *
		 * These six are the editor's access to the Horizon layout system: the
		 * bands and card grids that `inc/horizon.php` renders. A page built
		 * from them alternates backgrounds and never invents a new one, which
		 * is what the band rhythm depends on.
		 * ------------------------------------------------------------------
		 */
		'split-section'   => array(
			'title'       => __( 'Split Section', 'bgcf' ),
			'description' => __( 'Image on one side, heading and text on the other. The workhorse band: choose the side, the column ratio and the background.', 'bgcf' ),
			'icon'        => 'align-pull-left',
			'keywords'    => array( 'split', 'image', 'text', 'two column', 'about' ),
			'align'       => 'full',
			'supports'    => array( 'align' => array( 'full' ) ),
		),
		'photo-cta'       => array(
			'title'       => __( 'Photo CTA Band', 'bgcf' ),
			'description' => __( 'Full-bleed photograph behind a scrim, with a centred heading, one sentence and a button. Used to break up the coloured bands.', 'bgcf' ),
			'icon'        => 'format-image',
			'keywords'    => array( 'cta', 'photo', 'banner', 'band', 'action' ),
			'align'       => 'full',
			'supports'    => array( 'align' => array( 'full' ) ),
		),
		'service-cards'   => array(
			'title'       => __( 'Service Cards', 'bgcf' ),
			'description' => __( 'Grid of text-only cards, each with an icon, a title and a short description. Designed to sit on a dark band.', 'bgcf' ),
			'icon'        => 'screenoptions',
			'keywords'    => array( 'services', 'cards', 'offer', 'list', 'what we do' ),
			'align'       => 'full',
		),
		'news-cards'      => array(
			'title'       => __( 'News Cards', 'bgcf' ),
			'description' => __( 'Latest posts or success stories as dated cards. Pulls automatically, or pick the items by hand.', 'bgcf' ),
			'icon'        => 'admin-post',
			'keywords'    => array( 'news', 'blog', 'posts', 'stories', 'latest' ),
			'align'       => 'full',
		),
		'action-grid'     => array(
			'title'       => __( 'Action Grid', 'bgcf' ),
			'description' => __( 'Grid of ways to get involved, each with an image, a title and its own button. The body of the Take Action page.', 'bgcf' ),
			'icon'        => 'grid-view',
			'keywords'    => array( 'action', 'involved', 'volunteer', 'ways to help' ),
			'align'       => 'full',
		),
		'team-grid'       => array(
			'title'       => __( 'Team Grid', 'bgcf' ),
			'description' => __( 'People from the Team post type, as portrait cards, optionally grouped by department.', 'bgcf' ),
			'icon'        => 'groups',
			'keywords'    => array( 'team', 'people', 'staff', 'board', 'trustees' ),
			'align'       => 'full',
		),
	);

	/**
	 * Filters the block registry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $blocks Block definitions.
	 */
	return (array) apply_filters( 'bgcf_block_definitions', $blocks );
}

/**
 * The canonical, namespaced name of a theme block.
 *
 * @since 1.0.0
 *
 * @param string $slug Block slug without namespace, e.g. 'hero'.
 * @return string Canonical block name, e.g. 'bgcf/hero'.
 */
function bgcf_get_block_name( string $slug ): string {
	return 'bgcf/' . sanitize_key( $slug );
}

/**
 * Every name a theme block may be registered under, both namespaces.
 *
 * @since 1.0.0
 * @return string[] Block names.
 */
function bgcf_get_block_editor_names(): array {
	$names = array();

	foreach ( array_keys( bgcf_get_block_definitions() ) as $slug ) {
		$slug    = sanitize_key( (string) $slug );
		$names[] = 'bgcf/' . $slug;
		$names[] = 'acf/' . $slug;
	}

	return $names;
}

/**
 * Register every block in the registry with ACF.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_register_blocks(): void {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	foreach ( bgcf_get_block_definitions() as $slug => $definition ) {
		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			continue;
		}

		/*
		 * `supports` is merged key-by-key rather than replaced. A definition
		 * that narrows the alignment options must not silently lose `anchor`
		 * support — the render partials rely on it for section ids.
		 */
		$supports = array(
			'align'    => array( 'wide', 'full' ),
			'anchor'   => true,
			'mode'     => true,
			'jsx'      => false,
			'multiple' => true,
		);

		if ( isset( $definition['supports'] ) && is_array( $definition['supports'] ) ) {
			$supports = array_merge( $supports, $definition['supports'] );
		}

		$defaults = array(
			'title'           => $slug,
			'description'     => '',
			'category'        => 'bgcf',
			'icon'            => 'screenoptions',
			'keywords'        => array(),
			'mode'            => 'preview',
			'align'           => '',
			'render_callback' => 'bgcf_render_block',
			'example'         => array(
				'attributes' => array(
					'mode' => 'preview',
					'data' => array( 'bgcf_is_example' => true ),
				),
			),
		);

		$args             = wp_parse_args( $definition, $defaults );
		$args['name']     = bgcf_get_block_name( $slug );
		$args['supports'] = $supports;

		acf_register_block_type( $args );
	}
}
add_action( 'acf/init', 'bgcf_register_blocks' );

/**
 * Shared render callback for every `bgcf/*` block.
 *
 * Resolves `partials/blocks/block-{slug}.php` and hands it a normalised `$args`
 * bundle. Partials therefore never touch `$block` internals directly.
 *
 * Parameters are deliberately untyped: ACF has changed this callback signature
 * between majors (and may pass null for `$content`), so coercion happens inside
 * rather than at the boundary where a TypeError would white-screen the editor.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $block      Block settings and attributes.
 * @param string|null          $content    Inner block content.
 * @param bool                 $is_preview Whether the block renders in the editor.
 * @param int|string           $post_id    ID of the post holding the block.
 * @return void
 */
function bgcf_render_block( $block, $content = '', $is_preview = false, $post_id = 0 ): void {
	$block   = is_array( $block ) ? $block : array();
	$content = (string) $content;

	$slug = str_replace( array( 'acf/', 'bgcf/' ), '', (string) ( $block['name'] ?? '' ) );
	$slug = sanitize_key( $slug );

	if ( '' === $slug || ! array_key_exists( $slug, bgcf_get_block_definitions() ) ) {
		return;
	}

	$partial = 'blocks/block-' . $slug;

	if ( ! locate_template( 'partials/' . $partial . '.php' ) ) {
		bgcf_empty_state(
			sprintf(
				/* translators: %s: expected partial file path. */
				__( 'Missing block template: %s', 'bgcf' ),
				'partials/' . $partial . '.php'
			)
		);

		return;
	}

	bgcf_render_partial(
		$partial,
		array(
			'block'      => $block,
			'slug'       => $slug,
			'content'    => $content,
			'is_preview' => (bool) $is_preview,
			'post_id'    => $post_id,
			'anchor'     => isset( $block['anchor'] ) ? (string) $block['anchor'] : '',
			'class'      => bgcf_get_block_classes( $block, $slug ),
			'is_example' => ! empty( $block['data']['bgcf_is_example'] ),
		)
	);
}

/**
 * Build the root class list for a block.
 *
 * Combines the block's own BEM root, the editor-chosen alignment, and any
 * `className` set in the block sidebar.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $block Block settings.
 * @param string               $slug  Block slug without namespace.
 * @return string Unescaped class list. Escape with esc_attr() on output.
 */
function bgcf_get_block_classes( array $block, string $slug ): string {
	return bgcf_classnames(
		array(
			'bgcf-block',
			'bgcf-block-' . $slug,
			isset( $block['className'] ) ? (string) $block['className'] : '',
			! empty( $block['align'] ) ? 'align' . sanitize_html_class( (string) $block['align'] ) : '',
		)
	);
}

/**
 * Restrict the inserter to the theme's blocks plus a vetted core subset.
 *
 * Keeps pages composable from audited, on-brand components. Editors with
 * `manage_options` keep the full inserter for troubleshooting.
 *
 * @since 1.0.0
 *
 * @param bool|string[]           $allowed Allowed block types, or true for all.
 * @param WP_Block_Editor_Context $context Editor context.
 * @return bool|string[] Filtered allow list.
 */
function bgcf_allowed_block_types( $allowed, $context ) {
	if ( current_user_can( 'manage_options' ) ) {
		return $allowed;
	}

	$core = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/quote',
		'core/buttons',
		'core/button',
		'core/columns',
		'core/column',
		'core/group',
		'core/separator',
		'core/spacer',
		'core/embed',
		'core/table',
		'core/html',
	);

	$theme = bgcf_get_block_editor_names();

	/**
	 * Filters the blocks available to non-administrator editors.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]                $blocks  Allowed block names.
	 * @param WP_Block_Editor_Context $context Editor context.
	 */
	return (array) apply_filters( 'bgcf_allowed_block_types', array_merge( $theme, $core ), $context );
}
add_filter( 'allowed_block_types_all', 'bgcf_allowed_block_types', 10, 2 );
