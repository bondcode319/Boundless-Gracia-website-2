<?php
/**
 * Mega-panel navigation.
 *
 * Horizon's header is four dropdowns and a Donate button. Each dropdown opens a
 * full-width panel holding a four-column grid of destinations, every one with a
 * round icon, a title and a one-line description. Below the `lg` breakpoint the
 * same menu becomes an accordion inside the off-canvas drawer.
 *
 * WHY NOT A Walker_Nav_Menu SUBCLASS
 * ----------------------------------
 * Walker is the right tool when the output is a faithful nested `<ul>` of
 * arbitrary depth. This output is neither: it is exactly two levels, the first
 * level renders as a `<button>` rather than an `<a>`, the second level is
 * wrapped in a panel and a container that have no counterpart in the menu tree,
 * and the same tree is rendered twice in two different shapes. Expressed as a
 * Walker that becomes a set of `$depth === 0` branches in five methods, none of
 * which can be read in isolation. Building the tree once and rendering it
 * explicitly is longer but comprehensible, and it is what lets the drawer and
 * the bar share one source of truth.
 *
 * THE TRIGGER IS A BUTTON, AND THAT COSTS SOMETHING
 * -------------------------------------------------
 * A top-level item that owns a panel renders as `<button aria-expanded>`, which
 * is the correct disclosure pattern: it announces its state, it is operable from
 * the keyboard without a modifier, and it does not promise navigation it will
 * not perform. The cost is that the parent's OWN page becomes unreachable from
 * the menu — an "About us" button that opens a panel can no longer go to
 * `/about-us`. That page is therefore re-inserted as the first cell of its own
 * panel (see bgcf_mega_panel_cells()). A menu built the other way round, with a
 * link that also toggles, is the pattern that strands keyboard users: activating
 * it navigates away before the panel can be read.
 *
 * ESCAPING. All output is escaped here, at the point of output. Menu item titles
 * arrive from `wp_setup_nav_menu_item()` already filtered through `the_title`,
 * and are escaped again on the way out regardless.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a two-level tree from a registered menu location.
 *
 * Items deeper than the second level are DISCARDED rather than flattened. A
 * mega panel is a flat grid of destinations by design; silently flattening a
 * third level would put a grandchild beside its own parent with no indication
 * of the relationship, which is worse than not showing it. The editor finds out
 * through the admin notice in `bgcf_mega_menu_depth_notice()`.
 *
 * @since 2.0.0
 *
 * @param string $location Registered nav menu location.
 * @return array<int, array{item: WP_Post, children: array<int, WP_Post>}> Top-level items.
 */
function bgcf_mega_menu_tree( string $location ): array {
	$locations = (array) get_nav_menu_locations();
	$menu_id   = (int) ( $locations[ $location ] ?? 0 );

	if ( ! $menu_id ) {
		return array();
	}

	$items = wp_get_nav_menu_items( $menu_id );

	if ( ! is_array( $items ) || empty( $items ) ) {
		return array();
	}

	$tree    = array();
	$ordered = array();

	// First pass: top-level items, in menu order.
	foreach ( $items as $item ) {
		if ( ! $item instanceof WP_Post || 0 !== (int) $item->menu_item_parent ) {
			continue;
		}

		$ordered[ (int) $item->ID ] = array(
			'item'     => $item,
			'children' => array(),
		);
	}

	// Second pass: attach children whose parent is top-level. A child whose
	// parent is itself a child is third-level and is dropped here.
	foreach ( $items as $item ) {
		if ( ! $item instanceof WP_Post ) {
			continue;
		}

		$parent = (int) $item->menu_item_parent;

		if ( 0 === $parent || ! isset( $ordered[ $parent ] ) ) {
			continue;
		}

		$ordered[ $parent ]['children'][] = $item;
	}

	foreach ( $ordered as $branch ) {
		$tree[] = $branch;
	}

	/**
	 * Filters the mega-menu tree.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array{item: WP_Post, children: array<int, WP_Post>}> $tree     Menu tree.
	 * @param string                                                          $location Menu location.
	 */
	return (array) apply_filters( 'bgcf_mega_menu_tree', $tree, $location );
}

/**
 * The icon slug for a menu item.
 *
 * Read from the ACF field `menu_icon` on the menu item, which is how an editor
 * sets it (Appearance > Menus). Falls back to a neutral mark rather than to
 * nothing: a four-column grid in which two cells have an icon and two do not
 * looks like a rendering bug, so every cell gets one.
 *
 * @since 2.0.0
 *
 * @param WP_Post $item Menu item.
 * @return string Icon slug from assets/images/icons/.
 */
function bgcf_mega_item_icon( WP_Post $item ): string {
	$icon = '';

	if ( function_exists( 'get_field' ) ) {
		$icon = (string) get_field( 'menu_icon', $item );
	}

	if ( '' === trim( $icon ) ) {
		/**
		 * Filters the fallback mega-menu item icon.
		 *
		 * @since 2.0.0
		 *
		 * @param string  $icon Icon slug.
		 * @param WP_Post $item Menu item.
		 */
		$icon = (string) apply_filters( 'bgcf_mega_item_default_icon', 'arrow-up-right', $item );
	}

	return sanitize_key( $icon );
}

/**
 * The cells of one mega panel.
 *
 * Prepends the parent's own destination as the first cell when the parent points
 * somewhere real, so that making the trigger a button does not strand the parent
 * page. See the file header for why that matters.
 *
 * @since 2.0.0
 *
 * @param WP_Post            $parent   Top-level menu item.
 * @param array<int, WP_Post> $children Its second-level items.
 * @return array<int, array{title: string, url: string, description: string, icon: string, target: string, current: bool}> Panel cells.
 */
function bgcf_mega_panel_cells( WP_Post $parent, array $children ): array {
	$cells = array();

	$parent_url = (string) ( $parent->url ?? '' );

	/*
	 * `#` and an empty URL are both how editors mark "this item is only a
	 * container". Neither is a destination, so neither earns an overview cell.
	 */
	if ( '' !== $parent_url && '#' !== $parent_url ) {
		$cells[] = array(
			'title'       => sprintf(
				/* translators: %s: the parent navigation item's title, e.g. "About us". */
				bgcf_get_text( 'nav_overview_pattern', __( '%s overview', 'bgcf' ) ),
				(string) $parent->title
			),
			'url'         => $parent_url,
			'description' => (string) ( $parent->description ?? '' ),
			'icon'        => bgcf_mega_item_icon( $parent ),
			'target'      => (string) ( $parent->target ?? '' ),
			'current'     => in_array( 'current-menu-item', (array) ( $parent->classes ?? array() ), true ),
		);
	}

	foreach ( $children as $child ) {
		if ( ! $child instanceof WP_Post ) {
			continue;
		}

		$cells[] = array(
			'title'       => (string) $child->title,
			'url'         => (string) ( $child->url ?? '' ),
			'description' => (string) ( $child->description ?? '' ),
			'icon'        => bgcf_mega_item_icon( $child ),
			'target'      => (string) ( $child->target ?? '' ),
			'current'     => in_array( 'current-menu-item', (array) ( $child->classes ?? array() ), true ),
		);
	}

	return $cells;
}

/**
 * Render the desktop navigation bar with its mega panels.
 *
 * @since 2.0.0
 *
 * @param string $location Registered nav menu location. Default 'primary'.
 * @return void
 */
function bgcf_mega_menu( string $location = 'primary' ): void {
	$tree = bgcf_mega_menu_tree( $location );

	if ( empty( $tree ) ) {
		return;
	}

	echo '<ul class="bgcf-mega" data-bgcf-mega>';

	foreach ( $tree as $branch ) {
		$item     = $branch['item'];
		$children = (array) $branch['children'];
		$title    = (string) $item->title;
		$url      = (string) ( $item->url ?? '' );
		$classes  = (array) ( $item->classes ?? array() );

		// `current-menu-parent` and `current-menu-ancestor` both mean "the page
		// being viewed lives under this item"; either should light the trigger.
		$is_current = (bool) array_intersect(
			$classes,
			array( 'current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'current_page_parent' )
		);

		if ( empty( $children ) ) {
			// A top-level item with no panel is an ordinary link.
			printf(
				'<li class="bgcf-mega__item"><a class="%1$s" href="%2$s"%3$s%4$s>%5$s</a></li>',
				esc_attr( bgcf_classnames( array( 'bgcf-mega__top-link', 'is-current' => $is_current ) ) ),
				esc_url( $url ),
				$is_current ? ' aria-current="page"' : '',
				'_blank' === (string) ( $item->target ?? '' ) ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( $title )
			);

			continue;
		}

		$panel_id = 'bgcf-mega-panel-' . (int) $item->ID;
		$cells    = bgcf_mega_panel_cells( $item, $children );

		echo '<li class="bgcf-mega__item bgcf-mega__item--has-panel" data-bgcf-mega-item>';

		printf(
			'<button type="button" class="%1$s" aria-expanded="false" aria-controls="%2$s" data-bgcf-mega-trigger>%3$s%4$s</button>',
			esc_attr( bgcf_classnames( array( 'bgcf-mega__trigger', 'is-current' => $is_current ) ) ),
			esc_attr( $panel_id ),
			esc_html( $title ),
			bgcf_get_svg_icon( 'chevron-down', array( 'class' => 'bgcf-mega__chevron' ) )
		);

		/*
		 * `hidden` (not `display:none` in CSS) is what keeps the panel out of the
		 * tab order and the accessibility tree while closed. The script removes
		 * the attribute to open it and the stylesheet animates from there, so a
		 * no-JS visitor gets a menu that is simply never opened rather than a
		 * panel permanently stuck over the page.
		 */
		printf(
			'<div class="bgcf-mega__panel" id="%1$s" data-bgcf-mega-panel hidden>',
			esc_attr( $panel_id )
		);

		echo '<div class="bgcf-container bgcf-container--wide bgcf-mega__panel-inner">';
		printf(
			'<ul class="bgcf-mega__grid" aria-label="%s">',
			esc_attr(
				sprintf(
					/* translators: %s: the parent navigation item's title. */
					bgcf_get_text( 'nav_panel_label_pattern', __( '%s menu', 'bgcf' ) ),
					$title
				)
			)
		);

		foreach ( $cells as $cell ) {
			bgcf_render_partial( 'global/mega-cell', $cell );
		}

		echo '</ul>';
		echo '</div>';
		echo '</div>';
		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Render the same menu as an accordion, for the mobile drawer.
 *
 * Two independent renderings of one menu is a cost accepted knowingly: a single
 * list cannot be both a horizontal bar with absolutely positioned panels and a
 * vertical accordion without CSS that fights itself at the breakpoint. The
 * duplicate is hidden from assistive technology at whichever width it is not the
 * active navigation (see `_site-header.scss` and `_drawer.scss`), so a screen
 * reader never meets the same menu twice.
 *
 * @since 2.0.0
 *
 * @param string $location Registered nav menu location. Default 'primary'.
 * @return void
 */
function bgcf_mega_menu_drawer( string $location = 'primary' ): void {
	$tree = bgcf_mega_menu_tree( $location );

	if ( empty( $tree ) ) {
		return;
	}

	echo '<ul class="bgcf-drawer-nav" data-bgcf-accordion>';

	foreach ( $tree as $branch ) {
		$item     = $branch['item'];
		$children = (array) $branch['children'];
		$title    = (string) $item->title;
		$classes  = (array) ( $item->classes ?? array() );

		$is_current = (bool) array_intersect(
			$classes,
			array( 'current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'current_page_parent' )
		);

		if ( empty( $children ) ) {
			printf(
				'<li class="bgcf-drawer-nav__item"><a class="%1$s" href="%2$s"%3$s>%4$s</a></li>',
				esc_attr( bgcf_classnames( array( 'bgcf-drawer-nav__link', 'is-current' => $is_current ) ) ),
				esc_url( (string) ( $item->url ?? '' ) ),
				$is_current ? ' aria-current="page"' : '',
				esc_html( $title )
			);

			continue;
		}

		$panel_id = 'bgcf-drawer-panel-' . (int) $item->ID;
		$cells    = bgcf_mega_panel_cells( $item, $children );

		echo '<li class="bgcf-drawer-nav__item bgcf-drawer-nav__item--group">';

		/*
		 * The current branch starts OPEN. A visitor who opened the drawer on a
		 * page three levels down should see where they are, not have to guess
		 * which of four collapsed groups contains it.
		 */
		/*
		 * `data-bgcf-accordion-toggle` is the hook the EXISTING accordion module
		 * binds to (assets/js/src/accordion.js). The drawer needs exactly the
		 * disclosure behaviour that module already implements — toggle `hidden`
		 * on the element named by `aria-controls`, flip `aria-expanded` — so it
		 * reuses it rather than shipping a second implementation of the same
		 * four lines. The wrapper carries `data-bgcf-accordion` to scope it.
		 */
		printf(
			'<button type="button" class="bgcf-drawer-nav__toggle" aria-expanded="%1$s" aria-controls="%2$s" data-bgcf-accordion-toggle>%3$s%4$s</button>',
			$is_current ? 'true' : 'false',
			esc_attr( $panel_id ),
			esc_html( $title ),
			bgcf_get_svg_icon( 'chevron-down', array( 'class' => 'bgcf-drawer-nav__chevron' ) )
		);

		printf(
			'<ul class="bgcf-drawer-nav__sub" id="%1$s"%2$s>',
			esc_attr( $panel_id ),
			$is_current ? '' : ' hidden'
		);

		foreach ( $cells as $cell ) {
			printf(
				'<li class="bgcf-drawer-nav__sub-item"><a class="%1$s" href="%2$s"%3$s>%4$s</a></li>',
				esc_attr( bgcf_classnames( array( 'bgcf-drawer-nav__sub-link', 'is-current' => ! empty( $cell['current'] ) ) ) ),
				esc_url( (string) $cell['url'] ),
				! empty( $cell['current'] ) ? ' aria-current="page"' : '',
				esc_html( (string) $cell['title'] )
			);
		}

		echo '</ul>';
		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Warn an editor who has built a third menu level that it will not render.
 *
 * Silent truncation is the failure mode this avoids: the editor sees the item in
 * the menu screen, does not see it on the site, and has no way to find out why.
 *
 * @since 2.0.0
 * @return void
 */
function bgcf_mega_menu_depth_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen instanceof WP_Screen || 'nav-menus' !== $screen->id ) {
		return;
	}

	$locations = (array) get_nav_menu_locations();
	$menu_id   = (int) ( $locations['primary'] ?? 0 );

	if ( ! $menu_id ) {
		return;
	}

	$items = wp_get_nav_menu_items( $menu_id );

	if ( ! is_array( $items ) ) {
		return;
	}

	$parents = array();

	foreach ( $items as $item ) {
		if ( 0 !== (int) $item->menu_item_parent ) {
			$parents[ (int) $item->ID ] = (int) $item->menu_item_parent;
		}
	}

	// A third-level item is one whose parent is itself a child.
	$too_deep = 0;

	foreach ( $parents as $parent_id ) {
		if ( isset( $parents[ $parent_id ] ) ) {
			++$too_deep;
		}
	}

	if ( ! $too_deep ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: number of menu items nested too deeply. */
				_n(
					'%d item in the primary menu is nested three levels deep. The header menu renders two levels, so that item will not appear on the site. Move it up one level.',
					'%d items in the primary menu are nested three levels deep. The header menu renders two levels, so those items will not appear on the site. Move them up one level.',
					$too_deep,
					'bgcf'
				),
				$too_deep
			)
		)
	);
}
add_action( 'admin_notices', 'bgcf_mega_menu_depth_notice' );
