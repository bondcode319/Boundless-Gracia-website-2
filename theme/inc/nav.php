<?php
/**
 * Navigation: the submenu walker, the social icon map and the language switcher.
 *
 * WHY A WALKER. `wp_nav_menu()` renders a parent item as a link whose only
 * affordance for its children is `:hover`. That is unusable on touch, invisible
 * to keyboard users and inaccessible to screen readers. The walker below adds a
 * real `<button aria-expanded>` *beside* the parent link, so the parent stays a
 * navigable destination and the submenu becomes a standard disclosure. One
 * markup shape serves the desktop dropdown and the mobile accordion; only the
 * CSS differs.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nav menu walker that emits an accessible disclosure for every submenu.
 *
 * @since 1.2.0
 */
class Bgcf_Walker_Nav_Menu extends Walker_Nav_Menu {

	/**
	 * ID of the item whose children are currently being rendered.
	 *
	 * Set by start_el() for parent items and read by start_lvl(), which the
	 * walker always calls immediately afterwards.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	private $bgcf_current_item_id = 0;

	/**
	 * Open a submenu list.
	 *
	 * The id is predictable from the parent item so `aria-controls` can point at
	 * it without a second pass over the tree.
	 *
	 * @since 1.2.0
	 *
	 * @param string            $output Menu HTML, by reference.
	 * @param int               $depth  Current depth.
	 * @param stdClass|iterable $args   wp_nav_menu arguments.
	 * @return void
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= sprintf(
			"\n%s<ul class=\"bgcf-nav__submenu sub-menu\" id=\"%s\" data-bgcf-submenu hidden>\n",
			$indent,
			esc_attr( $this->bgcf_submenu_id() )
		);
	}

	/**
	 * Close a submenu list.
	 *
	 * @since 1.2.0
	 *
	 * @param string            $output Menu HTML, by reference.
	 * @param int               $depth  Current depth.
	 * @param stdClass|iterable $args   wp_nav_menu arguments.
	 * @return void
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= str_repeat( "\t", $depth ) . "</ul>\n";
	}

	/**
	 * Render one item, appending a disclosure button when it has children.
	 *
	 * The parent's own `start_el()` builds the link; we only append. That keeps
	 * every core filter (`nav_menu_link_attributes`, `the_title`, plugin
	 * injections) working, which a hand-rolled element would break.
	 *
	 * @since 1.2.0
	 *
	 * @param string            $output Menu HTML, by reference.
	 * @param WP_Post           $item   Menu item.
	 * @param int               $depth  Current depth.
	 * @param stdClass|iterable $args   wp_nav_menu arguments.
	 * @param int               $id     Current item ID.
	 * @return void
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$before = $output;

		parent::start_el( $output, $item, $depth, $args, $id );

		$has_children = in_array( 'menu-item-has-children', (array) ( $item->classes ?? array() ), true );

		if ( ! $has_children ) {
			return;
		}

		$this->bgcf_current_item_id = (int) $item->ID;

		$added = substr( $output, strlen( $before ) );

		$button = sprintf(
			'<button type="button" class="bgcf-nav__toggle" aria-expanded="false" aria-controls="%1$s" data-bgcf-submenu-toggle><span class="bgcf-nav__toggle-label">%2$s</span>%3$s</button>',
			esc_attr( $this->bgcf_submenu_id() ),
			esc_html(
				sprintf(
					/* translators: %s: parent menu item title. */
					__( 'Show submenu for %s', 'bgcf' ),
					wp_strip_all_tags( (string) $item->title )
				)
			),
			bgcf_get_svg_icon( 'chevron-down', array( 'class' => 'bgcf-nav__toggle-icon' ) )
		);

		// Insert the button inside the <li>, directly after the anchor, so the
		// link and its control are siblings in one focus order.
		$output = $before . preg_replace( '#(</a>)#', '$1' . $button, $added, 1 );
	}

	/**
	 * ID of the current item's submenu list.
	 *
	 * @since 1.2.0
	 * @return string DOM id.
	 */
	private function bgcf_submenu_id(): string {
		return 'bgcf-submenu-' . (int) $this->bgcf_current_item_id;
	}
}

/**
 * Render a theme menu with the disclosure walker.
 *
 * One call site for every menu location means the walker, depth and fallback
 * behaviour can never drift between the header and the drawer.
 *
 * @since 1.2.0
 *
 * @param string               $location  Registered nav menu location.
 * @param array<string, mixed> $overrides wp_nav_menu() argument overrides.
 * @return void
 */
function bgcf_nav_menu( string $location, array $overrides = array() ): void {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}

	wp_nav_menu(
		wp_parse_args(
			$overrides,
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => 'bgcf-nav bgcf-nav--primary',
				'depth'          => 2,
				'walker'         => new Bgcf_Walker_Nav_Menu(),
				'fallback_cb'    => false,
			)
		)
	);
}

/**
 * Map a social profile URL to an icon slug.
 *
 * Editors paste URLs into a normal WordPress menu; deriving the icon from the
 * host means they never have to pick one, and a typo degrades to a text label
 * rather than a wrong logo.
 *
 * @since 1.2.0
 *
 * @param string $url Profile URL.
 * @return string Icon slug, or '' when the network is unrecognised.
 */
function bgcf_social_icon_for_url( string $url ): string {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$host = strtolower( preg_replace( '/^www\./', '', $host ) );

	$map = array(
		'facebook.com'  => 'facebook',
		'fb.com'        => 'facebook',
		'twitter.com'   => 'x',
		'x.com'         => 'x',
		'instagram.com' => 'instagram',
		'linkedin.com'  => 'linkedin',
		'youtube.com'   => 'youtube',
		'youtu.be'      => 'youtube',
		'wa.me'         => 'whatsapp',
		'whatsapp.com'  => 'whatsapp',
		'tiktok.com'    => 'tiktok',
	);

	/**
	 * Filters the social host => icon slug map.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $map Host => icon slug.
	 */
	$map = (array) apply_filters( 'bgcf_social_icon_map', $map );

	return (string) ( $map[ $host ] ?? '' );
}

/**
 * Prefix social menu links with their brand icon.
 *
 * The visible text label is kept in the DOM and hidden with CSS rather than
 * removed, so the accessible name is always the network name — never "icon".
 *
 * @since 1.2.0
 *
 * @param string   $item_output Rendered item HTML.
 * @param WP_Post  $item        Menu item.
 * @param int      $depth       Depth.
 * @param stdClass $args        wp_nav_menu arguments.
 * @return string Filtered HTML.
 */
function bgcf_social_menu_icons( string $item_output, $item, int $depth, $args ): string {
	if ( 'social' !== ( $args->theme_location ?? '' ) ) {
		return $item_output;
	}

	$icon = bgcf_get_svg_icon(
		bgcf_social_icon_for_url( (string) ( $item->url ?? '' ) ),
		array( 'class' => 'bgcf-nav__icon' )
	);

	if ( '' === $icon ) {
		return $item_output;
	}

	return (string) preg_replace( '#(<span class="bgcf-nav__label">)#', $icon . '$1', $item_output, 1 );
}
add_filter( 'walker_nav_menu_start_el', 'bgcf_social_menu_icons', 10, 4 );

/**
 * Whether more than one language is published.
 *
 * @since 1.2.0
 * @return bool True when a language switcher is worth rendering.
 */
function bgcf_has_languages(): bool {
	return function_exists( 'pll_the_languages' ) || ( function_exists( 'icl_get_languages' ) );
}

/**
 * Render the language switcher.
 *
 * Polylang and WPML are both optional; absent either, this renders nothing at
 * all rather than an empty control.
 *
 * @since 1.2.0
 *
 * @param string $class Extra class names.
 * @return void
 */
function bgcf_language_switcher( string $class = '' ): void {
	if ( ! bgcf_has_languages() ) {
		return;
	}

	$items = '';

	if ( function_exists( 'pll_the_languages' ) ) {
		$items = (string) pll_the_languages(
			array(
				'show_flags'             => 0,
				'show_names'             => 1,
				'display_names_as'       => 'slug',
				'hide_if_no_translation' => 0,
				'echo'                   => 0,
			)
		);
	} elseif ( function_exists( 'icl_get_languages' ) ) {
		foreach ( (array) icl_get_languages( 'skip_missing=0' ) as $language ) {
			$items .= sprintf(
				'<li class="%1$s"><a href="%2$s" lang="%3$s">%4$s</a></li>',
				esc_attr( empty( $language['active'] ) ? '' : 'current-lang' ),
				esc_url( (string) ( $language['url'] ?? '' ) ),
				esc_attr( (string) ( $language['language_code'] ?? '' ) ),
				esc_html( strtoupper( (string) ( $language['language_code'] ?? '' ) ) )
			);
		}
	}

	if ( '' === trim( $items ) ) {
		return;
	}

	printf(
		'<nav class="%1$s" aria-label="%2$s">%3$s<ul class="bgcf-lang__list">%4$s</ul></nav>',
		esc_attr( bgcf_classnames( array( 'bgcf-lang', $class ) ) ),
		esc_attr( bgcf_get_text( 'nav_language_label', __( 'Language', 'bgcf' ) ) ),
		bgcf_get_svg_icon( 'globe', array( 'class' => 'bgcf-lang__icon' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised in helper.
		wp_kses_post( $items )
	);
}
