<?php
/**
 * Template tags — the reusable render helpers that keep templates declarative.
 *
 * Contract for every function in this file:
 *
 * - `bgcf_get_*()` RETURNS an escaped string and never echoes.
 * - `bgcf_the_*()` / bare verbs ECHO already-escaped output.
 * - Escaping happens here, once, at the point of output. A template that calls
 *   a template tag must not re-escape, and must not build markup by hand.
 * - Every user-facing string arrives as an argument (from ACF, a CPT, a menu or
 *   the customizer) or through `bgcf_text()`, which supplies a filterable
 *   default. Templates contain no literal copy.
 *
 * This module has no side effects beyond the two filters it declares, and is
 * loaded second (right after setup) so everything else can rely on it.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a partial from `partials/`.
 *
 * The single entry point for template parts. Uses get_template_part() so child
 * themes and Polylang-aware overrides keep working.
 *
 * @since 1.0.0
 *
 * @param string               $slug Partial path relative to `partials/`, without
 *                                   the `.php` extension. E.g. `content/content-page`
 *                                   or `blocks/block-hero`.
 * @param array<string, mixed> $args Variables exposed to the partial as `$args`.
 * @param bool                 $echo Whether to echo (true) or return (false).
 * @return string Rendered markup when `$echo` is false, otherwise an empty string.
 */
function bgcf_render_partial( string $slug, array $args = array(), bool $echo = true ): string {
	$slug = ltrim( str_replace( array( '..', '\\' ), '', $slug ), '/' );

	if ( '' === $slug ) {
		return '';
	}

	if ( $echo ) {
		get_template_part( 'partials/' . $slug, null, $args );
		return '';
	}

	ob_start();
	get_template_part( 'partials/' . $slug, null, $args );

	return (string) ob_get_clean();
}

/**
 * Resolve an editable interface string.
 *
 * Order of precedence: ACF option `text_{$key}` -> `$default` -> filter. This is
 * how Phase 1 templates stay free of hardcoded copy before the ACF options
 * pages carry real content: pass a translatable default, let the CMS win.
 *
 * @since 1.0.0
 *
 * @param string $key     Option key, without the `text_` prefix. Snake case.
 * @param string $default Translatable fallback.
 * @return string Unescaped string. Escape at the point of output.
 */
function bgcf_get_text( string $key, string $default = '' ): string {
	$key   = sanitize_key( $key );
	$value = function_exists( 'bgcf_option' ) ? (string) bgcf_option( 'text_' . $key, '' ) : '';

	if ( '' === trim( $value ) ) {
		$value = $default;
	}

	/**
	 * Filters a resolved interface string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   Resolved string.
	 * @param string $key     Text key.
	 * @param string $default Fallback that was supplied by the caller.
	 */
	return (string) apply_filters( 'bgcf_text', $value, $key, $default );
}

/**
 * Echo an escaped interface string.
 *
 * @since 1.0.0
 *
 * @param string $key     Option key, without the `text_` prefix.
 * @param string $default Translatable fallback.
 * @return void
 */
function bgcf_the_text( string $key, string $default = '' ): void {
	echo esc_html( bgcf_get_text( $key, $default ) );
}

/**
 * Join conditional CSS class names.
 *
 * Accepts strings and `class => bool` pairs; falsy entries are dropped.
 *
 * @since 1.0.0
 *
 * @param array<int|string, mixed> $classes Class list.
 * @return string Space-separated, de-duplicated class list (unescaped).
 */
function bgcf_classnames( array $classes ): string {
	$out = array();

	foreach ( $classes as $key => $value ) {
		if ( is_int( $key ) ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$out[] = trim( $value );
			}
			continue;
		}

		if ( $value ) {
			$out[] = trim( (string) $key );
		}
	}

	$out = array_unique( array_filter( explode( ' ', implode( ' ', $out ) ) ) );

	return implode( ' ', $out );
}

/**
 * Build an escaped HTML attribute string.
 *
 * `true` renders a boolean attribute, `false`/`null` drops it entirely.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $attrs Attribute name => value.
 * @return string Escaped attribute string, leading space included when non-empty.
 */
function bgcf_get_attrs( array $attrs ): string {
	$out = '';

	foreach ( $attrs as $name => $value ) {
		$name = strtolower( (string) preg_replace( '/[^A-Za-z0-9_:.\-]/', '', (string) $name ) );

		if ( '' === $name || null === $value || false === $value ) {
			continue;
		}

		if ( true === $value ) {
			$out .= ' ' . esc_attr( $name );
			continue;
		}

		if ( is_array( $value ) ) {
			$value = implode( ' ', array_map( 'strval', $value ) );
		}

		$out .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
	}

	return $out;
}

/**
 * Build a featured image (or any attachment) as responsive, CLS-safe markup.
 *
 * Always emits width/height (via wp_get_attachment_image) plus srcset/sizes.
 * Above-the-fold callers must pass `loading => 'eager'` and
 * `fetchpriority => 'high'`; everything else keeps the lazy default.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args {
 *     Optional arguments.
 *
 *     @type int|WP_Post|null $post          Post to read the thumbnail from. Default current post.
 *     @type int              $attachment_id Explicit attachment ID, bypassing the post thumbnail.
 *     @type string           $size          Registered image size. Default 'bgcf-card'.
 *     @type string           $class         Extra class names.
 *     @type string           $sizes         `sizes` attribute. Default from WordPress.
 *     @type string           $loading       'lazy' (default) or 'eager'.
 *     @type string           $fetchpriority 'auto' (default), 'high' or 'low'.
 *     @type string           $alt           Alt override. Defaults to the attachment alt text.
 *     @type bool             $fallback      Use the ACF global fallback image when empty. Default true.
 *     @type bool             $link          Wrap the image in the post permalink. Default false.
 * }
 * @return string Escaped `<img>` markup, or an empty string when nothing renders.
 */
function bgcf_get_post_thumbnail( array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'post'          => null,
			'attachment_id' => 0,
			'size'          => 'bgcf-card',
			'class'         => '',
			'sizes'         => '',
			'loading'       => 'lazy',
			'fetchpriority' => 'auto',
			'alt'           => '',
			'fallback'      => true,
			'link'          => false,
		)
	);

	$post          = get_post( $args['post'] );
	$attachment_id = (int) $args['attachment_id'];

	if ( ! $attachment_id && $post instanceof WP_Post ) {
		$attachment_id = (int) get_post_thumbnail_id( $post );
	}

	if ( ! $attachment_id && $args['fallback'] && function_exists( 'bgcf_option' ) ) {
		$fallback = bgcf_option( 'default_featured_image', 0 );
		$attachment_id = is_array( $fallback ) ? (int) ( $fallback['ID'] ?? 0 ) : (int) $fallback;
	}

	if ( ! $attachment_id ) {
		return '';
	}

	$attr = array(
		'class'    => bgcf_classnames( array( 'bgcf-image', $args['class'] ) ),
		'loading'  => 'eager' === $args['loading'] ? 'eager' : 'lazy',
		'decoding' => 'async',
	);

	if ( 'auto' !== $args['fetchpriority'] ) {
		$attr['fetchpriority'] = 'high' === $args['fetchpriority'] ? 'high' : 'low';
	}

	if ( '' !== $args['sizes'] ) {
		$attr['sizes'] = $args['sizes'];
	}

	if ( '' !== $args['alt'] ) {
		$attr['alt'] = $args['alt'];
	}

	// wp_get_attachment_image() escapes attributes and adds width/height/srcset.
	$html = wp_get_attachment_image( $attachment_id, $args['size'], false, $attr );

	if ( '' === $html ) {
		return '';
	}

	if ( $args['link'] && $post instanceof WP_Post ) {
		$html = sprintf(
			'<a class="bgcf-image-link" href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a>',
			esc_url( (string) get_permalink( $post ) ),
			$html
		);
	}

	/**
	 * Filters the rendered thumbnail markup.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $html Image markup.
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $args Resolved arguments.
	 */
	return (string) apply_filters( 'bgcf_post_thumbnail_html', $html, $attachment_id, $args );
}

/**
 * Echo a responsive featured image.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args See bgcf_get_post_thumbnail().
 * @return void
 */
function bgcf_the_post_thumbnail( array $args = array() ): void {
	echo bgcf_get_post_thumbnail( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
}

/**
 * Build a button or button-styled link.
 *
 * The only sanctioned way to render a CTA. Renders an `<a>` when a URL is given
 * and a `<button>` otherwise, so semantics always match behaviour.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args {
 *     Button arguments.
 *
 *     @type string               $text          Visible label. Required.
 *     @type string               $url           Destination. Renders an `<a>` when set.
 *     @type string               $variant       'primary' (default), 'secondary', 'ghost', 'link'.
 *     @type string               $size          'md' (default), 'sm', 'lg'.
 *     @type string               $target        Link target. '_blank' adds a safe rel.
 *     @type string               $rel           Extra rel tokens.
 *     @type string               $icon          Icon slug from assets/images/icons/.
 *     @type string               $icon_position 'before' or 'after' (default).
 *     @type string               $class         Extra class names.
 *     @type string               $aria_label    Accessible name override.
 *     @type string               $type          Button type when no URL. Default 'button'.
 *     @type bool                 $full_width    Stretch to the container width.
 *     @type array<string, mixed> $attrs         Extra attributes, escaped on output.
 * }
 * @return string Escaped markup, or an empty string when there is no label.
 */
function bgcf_get_button( array $args = array() ): string {
	/**
	 * Filters button arguments before rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 */
	$args = (array) apply_filters( 'bgcf_button_args', $args );

	$args = wp_parse_args(
		$args,
		array(
			'text'          => '',
			'url'           => '',
			'variant'       => 'primary',
			'size'          => 'md',
			'target'        => '',
			'rel'           => '',
			'icon'          => '',
			'icon_position' => 'after',
			'class'         => '',
			'aria_label'    => '',
			'type'          => 'button',
			'full_width'    => false,
			'attrs'         => array(),
		)
	);

	$text = trim( (string) $args['text'] );

	if ( '' === $text ) {
		return '';
	}

	$variants = array( 'primary', 'secondary', 'ghost', 'link' );
	$sizes    = array( 'sm', 'md', 'lg' );
	$variant  = in_array( $args['variant'], $variants, true ) ? $args['variant'] : 'primary';
	$size     = in_array( $args['size'], $sizes, true ) ? $args['size'] : 'md';

	$classes = bgcf_classnames(
		array(
			'bgcf-btn',
			'bgcf-btn--' . $variant,
			'bgcf-btn--' . $size,
			'bgcf-btn--block' => (bool) $args['full_width'],
			'bgcf-btn--icon'  => '' !== $args['icon'],
			(string) $args['class'],
		)
	);

	$attrs = is_array( $args['attrs'] ) ? $args['attrs'] : array();
	$attrs['class'] = $classes;

	if ( '' !== $args['aria_label'] ) {
		$attrs['aria-label'] = $args['aria_label'];
	}

	$icon  = '' !== $args['icon'] ? bgcf_get_svg_icon( (string) $args['icon'], array( 'class' => 'bgcf-btn__icon' ) ) : '';
	$label = '<span class="bgcf-btn__label">' . esc_html( $text ) . '</span>';
	$inner = 'before' === $args['icon_position'] ? $icon . $label : $label . $icon;

	$url = (string) $args['url'];

	if ( '' === $url ) {
		$attrs['type'] = in_array( $args['type'], array( 'button', 'submit', 'reset' ), true ) ? $args['type'] : 'button';

		return '<button' . bgcf_get_attrs( $attrs ) . '>' . $inner . '</button>';
	}

	/*
	 * esc_url_raw() validates the protocol without HTML-encoding; bgcf_get_attrs()
	 * then runs esc_attr(). Using esc_url() here would double-encode ampersands.
	 */
	$attrs['href'] = esc_url_raw( $url );

	if ( '' !== $args['target'] ) {
		$attrs['target'] = $args['target'];
	}

	$rel = trim( (string) $args['rel'] );

	if ( '_blank' === $args['target'] ) {
		$rel = trim( $rel . ' noopener noreferrer' );
	}

	if ( '' !== $rel ) {
		$attrs['rel'] = $rel;
	}

	return '<a' . bgcf_get_attrs( $attrs ) . '>' . $inner . '</a>';
}

/**
 * Echo a button.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args See bgcf_get_button().
 * @return void
 */
function bgcf_button( array $args = array() ): void {
	echo bgcf_get_button( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
}

/**
 * Build a button from an ACF Link field value.
 *
 * ACF returns `array{ title: string, url: string, target: string }` (or a plain
 * URL string when the return format is set to URL). Normalising here means block
 * partials never have to.
 *
 * @since 1.0.0
 *
 * @param mixed                $link      ACF link value.
 * @param array<string, mixed> $overrides Button args merged over the link data.
 * @return string Escaped markup, or an empty string when the link is empty.
 */
function bgcf_get_link_button( $link, array $overrides = array() ): string {
	$args = array();

	if ( is_array( $link ) ) {
		$args = array(
			'text'   => (string) ( $link['title'] ?? '' ),
			'url'    => (string) ( $link['url'] ?? '' ),
			'target' => (string) ( $link['target'] ?? '' ),
		);
	} elseif ( is_string( $link ) && '' !== $link ) {
		$args = array( 'url' => $link );
	}

	if ( empty( $args['url'] ) ) {
		return '';
	}

	return bgcf_get_button( array_merge( $args, $overrides ) );
}

/**
 * The closed set of section background roles.
 *
 * SINGLE SOURCE OF TRUTH, and the reason the "no two adjacent bands share a
 * background" rule in the Horizon layout system is enforceable rather than
 * aspirational. Three places agree on this list and all three must be changed
 * together:
 *
 *   1. this function (validation),
 *   2. `assets/scss/settings/_colors.scss` `$surfaces` (the colour values),
 *   3. `assets/scss/components/_section.scss` (the `--bg-*` modifiers).
 *
 * `base` and `surface` are retained as aliases of `page` and `soft` so content
 * saved by the previous theme generation keeps rendering; they are not offered
 * in the editor.
 *
 * @since 2.0.0
 * @return string[] Valid background role names.
 */
function bgcf_section_backgrounds(): array {
	/**
	 * Filters the valid section background roles.
	 *
	 * Adding a role here without adding the matching SCSS modifier produces a
	 * section with no background at all, so extend the stylesheet first.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $roles Background role names.
	 */
	return (array) apply_filters(
		'bgcf_section_backgrounds',
		array(
			'none',
			'page',
			'soft',
			'sand',
			'bloom',
			'leaf',
			'ink',
			'ink-deep',
			'secondary',
			'photo',
			// Aliases kept for backwards compatibility.
			'base',
			'surface',
			'primary',
		)
	);
}

/**
 * Open a page section wrapper.
 *
 * Guarantees a consistent landmark, spacing rhythm and container width for every
 * block and template section. Must be paired with bgcf_section_close().
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args {
 *     Optional arguments.
 *
 *     @type string               $tag        Wrapper element. Default 'section'.
 *     @type string               $id         DOM id / block anchor.
 *     @type string               $class      Extra class names, e.g. the block name.
 *     @type string               $background Band role. One of bgcf_section_backgrounds().
 *                                            Default 'page'.
 *     @type string               $spacing    'md' (default), 'none', 'sm', 'lg'.
 *     @type string|false         $width      Container width: 'default', 'wide', 'narrow', 'full',
 *                                            or false to skip the container entirely.
 *     @type int                  $image      Attachment ID painted behind a 'photo' band. Ignored
 *                                            for every other background role.
 *     @type string               $image_size Registered size used for the band image. Default
 *                                            'bgcf-hero'.
 *     @type int                  $scrim      Scrim opacity percentage over a 'photo' band.
 *                                            Clamped to 55-85; default 62. Ignored for every
 *                                            other background role.
 *     @type string               $aria_label Accessible name for the section landmark.
 *     @type string               $labelledby ID of the heading that names the section.
 *     @type array<string, mixed> $attrs      Extra attributes.
 * }
 * @return void
 */
function bgcf_section_open( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'tag'        => 'section',
			'id'         => '',
			'class'      => '',
			'background' => 'page',
			'spacing'    => 'md',
			'width'      => 'default',
			'image'      => 0,
			'image_size' => 'bgcf-hero',
			'scrim'      => 62,
			'aria_label' => '',
			'labelledby' => '',
			'attrs'      => array(),
		)
	);

	$allowed_tags = array( 'section', 'div', 'article', 'aside', 'header', 'footer' );
	$tag          = in_array( $args['tag'], $allowed_tags, true ) ? $args['tag'] : 'section';

	$spacings   = array( 'none', 'sm', 'md', 'lg' );
	$background = in_array( $args['background'], bgcf_section_backgrounds(), true ) ? $args['background'] : 'page';
	$spacing    = in_array( $args['spacing'], $spacings, true ) ? $args['spacing'] : 'md';

	$attrs = is_array( $args['attrs'] ) ? $args['attrs'] : array();

	$attrs['class'] = bgcf_classnames(
		array(
			'bgcf-section',
			'bgcf-section--bg-' . $background,
			'bgcf-section--space-' . $spacing,
			(string) $args['class'],
		)
	);

	/*
	 * A photographic band's image is content, so it cannot live in the
	 * stylesheet. Both the URL and the scrim opacity are passed as custom
	 * properties rather than as `background-image` / `opacity` declarations, so
	 * that `components/_section.scss` keeps ownership of the sizing, the scrim
	 * colour and the forced-colors fallback — the inline style supplies two
	 * values and no behaviour.
	 *
	 * The 55% floor is enforced here as well as in the SCSS mixin, because this
	 * path takes an editor-supplied number and the mixin only sees build-time
	 * literals.
	 */
	if ( 'photo' === $background ) {
		$declarations = array();
		$image_url    = (string) wp_get_attachment_image_url( (int) $args['image'], (string) $args['image_size'] );

		if ( '' !== $image_url ) {
			$declarations[] = sprintf( '--bgcf-section-image:url(%s)', esc_url( $image_url ) );
		}

		$scrim = min( 85, max( 55, (int) $args['scrim'] ) );

		if ( 62 !== $scrim ) {
			$declarations[] = sprintf( '--bgcf-section-scrim:%d%%', $scrim );
		}

		if ( ! empty( $declarations ) ) {
			$attrs['style'] = implode( ';', $declarations );
		}
	}

	if ( '' !== $args['id'] ) {
		$attrs['id'] = sanitize_html_class( (string) $args['id'] );
	}

	if ( '' !== $args['labelledby'] ) {
		$attrs['aria-labelledby'] = sanitize_html_class( (string) $args['labelledby'] );
	} elseif ( '' !== $args['aria_label'] ) {
		$attrs['aria-label'] = $args['aria_label'];
	}

	echo '<' . tag_escape( $tag ) . bgcf_get_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

	if ( false !== $args['width'] ) {
		$widths = array( 'default', 'wide', 'narrow', 'full' );
		$width  = in_array( $args['width'], $widths, true ) ? $args['width'] : 'default';

		printf(
			'<div class="%s">',
			esc_attr( bgcf_classnames( array( 'bgcf-container', 'bgcf-container--' . $width ) ) )
		);
	}

	/**
	 * Fires immediately inside an opened section container.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Resolved section arguments.
	 */
	do_action( 'bgcf_section_start', $args );
}

/**
 * Close a page section wrapper.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args {
 *     Optional arguments. Must mirror the values passed to bgcf_section_open().
 *
 *     @type string       $tag   Wrapper element. Default 'section'.
 *     @type string|false $width Whether a container was opened. Default 'default'.
 * }
 * @return void
 */
function bgcf_section_close( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'tag'   => 'section',
			'width' => 'default',
		)
	);

	/**
	 * Fires immediately before a section container closes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Resolved section arguments.
	 */
	do_action( 'bgcf_section_end', $args );

	if ( false !== $args['width'] ) {
		echo '</div>';
	}

	$allowed_tags = array( 'section', 'div', 'article', 'aside', 'header', 'footer' );
	$tag          = in_array( $args['tag'], $allowed_tags, true ) ? $args['tag'] : 'section';

	echo '</' . tag_escape( $tag ) . '>';
}

/**
 * Return an inline SVG icon from `assets/images/icons/`.
 *
 * Icons are inlined (not sprited) so they inherit `currentColor` and stay
 * crisp; the file is read once per request and cached in memory. Decorative by
 * default — pass a `title` to expose the icon to assistive technology.
 *
 * @since 1.0.0
 *
 * @param string               $name Icon file name without extension, e.g. 'arrow-right'.
 * @param array<string, mixed> $args {
 *     Optional arguments.
 *
 *     @type string $class Extra class names.
 *     @type string $title Accessible name. When set, the icon is not aria-hidden.
 * }
 * @return string Sanitised SVG markup, or an empty string when the icon is missing.
 */
function bgcf_get_svg_icon( string $name, array $args = array() ): string {
	static $cache = array();

	$name = sanitize_key( $name );

	if ( '' === $name ) {
		return '';
	}

	if ( ! array_key_exists( $name, $cache ) ) {
		$path          = BGCF_DIR . '/assets/images/icons/' . $name . '.svg';
		$cache[ $name ] = file_exists( $path )
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme asset, not remote.
			? (string) file_get_contents( $path )
			: '';
	}

	$svg = $cache[ $name ];

	if ( '' === $svg ) {
		return '';
	}

	$args  = wp_parse_args( $args, array( 'class' => '', 'title' => '' ) );
	$class = bgcf_classnames( array( 'bgcf-icon', 'bgcf-icon--' . $name, (string) $args['class'] ) );

	$svg = preg_replace( '/<\?xml.*?\?>|<!--.*?-->|<!DOCTYPE.*?>/is', '', $svg );
	$svg = trim( (string) $svg );

	$attributes = ' class="' . esc_attr( $class ) . '" focusable="false"';

	if ( '' !== $args['title'] ) {
		$attributes .= ' role="img" aria-label="' . esc_attr( (string) $args['title'] ) . '"';
	} else {
		$attributes .= ' aria-hidden="true"';
	}

	$svg = preg_replace( '/<svg\b/', '<svg' . $attributes, $svg, 1 );

	return wp_kses( (string) $svg, bgcf_get_kses_svg() );
}

/**
 * Echo an inline SVG icon.
 *
 * @since 1.0.0
 *
 * @param string               $name Icon slug.
 * @param array<string, mixed> $args See bgcf_get_svg_icon().
 * @return void
 */
function bgcf_svg_icon( string $name, array $args = array() ): void {
	echo bgcf_get_svg_icon( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised with wp_kses in helper.
}

/**
 * Allowed HTML for inline SVG output.
 *
 * Use with wp_kses() whenever SVG markup comes from anywhere but this theme's
 * own icon directory.
 *
 * @since 1.0.0
 * @return array<string, array<string, bool>> wp_kses allowlist.
 */
function bgcf_get_kses_svg(): array {
	$common = array(
		'class'           => true,
		'id'              => true,
		'fill'            => true,
		'fill-rule'       => true,
		'clip-rule'       => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
		'opacity'         => true,
		'transform'       => true,
	);

	return array(
		'svg'      => array_merge(
			$common,
			array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'role'        => true,
				'focusable'   => true,
				'aria-hidden' => true,
				'aria-label'  => true,
			)
		),
		'title'    => array( 'id' => true ),
		'desc'     => array( 'id' => true ),
		'g'        => $common,
		'path'     => array_merge( $common, array( 'd' => true ) ),
		'circle'   => array_merge( $common, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
		'ellipse'  => array_merge( $common, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) ),
		'rect'     => array_merge( $common, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) ),
		'line'     => array_merge( $common, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) ),
		'polyline' => array_merge( $common, array( 'points' => true ) ),
		'polygon'  => array_merge( $common, array( 'points' => true ) ),
		'use'      => array_merge( $common, array( 'href' => true, 'xlink:href' => true ) ),
		'defs'     => array(),
	);
}

/**
 * The breadcrumb trail, as data.
 *
 * Separated from the rendering so inc/seo-schema.php can build the
 * BreadcrumbList from the identical list the visible trail uses. Two
 * independent trail builders is how a page ends up with structured data that
 * describes a path the page does not show.
 *
 * @since 1.2.0
 * @return array<int, array{label: string, url: string}> Trail items, root first.
 */
function bgcf_get_breadcrumb_items(): array {
	if ( is_front_page() ) {
		return array();
	}

	$items = array(
		array(
			'label' => bgcf_get_text( 'breadcrumb_home', __( 'Home', 'bgcf' ) ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post_type = get_post_type();

		if ( $post_type && 'post' !== $post_type && 'page' !== $post_type ) {
			$archive_link = get_post_type_archive_link( $post_type );
			$object       = get_post_type_object( $post_type );

			if ( $archive_link && $object instanceof WP_Post_Type ) {
				$items[] = array(
					'label' => $object->labels->name,
					'url'   => $archive_link,
				);
			}
		}

		$items[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_archive() ) {
		$items[] = array(
			'label' => wp_strip_all_tags( get_the_archive_title() ),
			'url'   => '',
		);
	} elseif ( is_search() ) {
		$items[] = array(
			'label' => bgcf_get_text( 'breadcrumb_search', __( 'Search results', 'bgcf' ) ),
			'url'   => '',
		);
	} elseif ( is_404() ) {
		$items[] = array(
			'label' => bgcf_get_text( 'breadcrumb_404', __( 'Page not found', 'bgcf' ) ),
			'url'   => '',
		);
	}

	/**
	 * Filters the breadcrumb trail items.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{label: string, url: string}> $items Trail items, root first.
	 */
	return (array) apply_filters( 'bgcf_breadcrumb_items', $items );
}

/**
 * Breadcrumb trail.
 *
 * When RankMath is active its own breadcrumb is used so the two never disagree.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_breadcrumbs(): void {
	if ( is_front_page() ) {
		return;
	}

	if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
		rank_math_the_breadcrumbs();
		return;
	}

	$items = bgcf_get_breadcrumb_items();

	if ( count( $items ) < 2 ) {
		return;
	}

	printf(
		'<nav class="bgcf-breadcrumbs" aria-label="%s"><ol class="bgcf-breadcrumbs__list">',
		esc_attr( bgcf_get_text( 'breadcrumb_label', __( 'Breadcrumb', 'bgcf' ) ) )
	);

	$last = count( $items ) - 1;

	foreach ( array_values( $items ) as $index => $item ) {
		$label = (string) ( $item['label'] ?? '' );
		$url   = (string) ( $item['url'] ?? '' );

		if ( '' === $label ) {
			continue;
		}

		echo '<li class="bgcf-breadcrumbs__item">';

		if ( '' !== $url && $index !== $last ) {
			printf(
				'<a class="bgcf-breadcrumbs__link" href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html( $label )
			);
		} else {
			printf(
				'<span class="bgcf-breadcrumbs__current" aria-current="page">%s</span>',
				esc_html( $label )
			);
		}

		echo '</li>';
	}

	echo '</ol></nav>';
}

/**
 * Estimated reading time for a post.
 *
 * @since 1.0.0
 *
 * @param int|WP_Post|null $post Post to measure. Default current post.
 * @return int Whole minutes, minimum 1.
 */
function bgcf_get_reading_time( $post = null ): int {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return 1;
	}

	/**
	 * Filters the words-per-minute reading speed.
	 *
	 * @since 1.0.0
	 *
	 * @param int $wpm Words per minute.
	 */
	$wpm = max( 1, (int) apply_filters( 'bgcf_reading_time_wpm', 200 ) );

	$words   = (int) str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	$minutes = (int) max( 1, (int) ceil( $words / $wpm ) );

	/**
	 * Filters the calculated reading time.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $minutes Reading time in minutes.
	 * @param WP_Post $post    Post object.
	 */
	return (int) apply_filters( 'bgcf_reading_time', $minutes, $post );
}

/**
 * Echo the reading time as a translated, pluralised string.
 *
 * @since 1.0.0
 *
 * @param int|WP_Post|null $post Post to measure. Default current post.
 * @return void
 */
function bgcf_reading_time( $post = null ): void {
	$minutes = bgcf_get_reading_time( $post );

	printf(
		'<span class="bgcf-entry-meta__item bgcf-entry-meta__item--reading-time">%s</span>',
		esc_html(
			sprintf(
				/* translators: %s: estimated reading time in whole minutes. */
				_n( '%s min read', '%s min read', $minutes, 'bgcf' ),
				number_format_i18n( $minutes )
			)
		)
	);
}

/**
 * Echo the published date as a machine-readable time element.
 *
 * @since 1.0.0
 *
 * @param int|WP_Post|null $post Post. Default current post.
 * @return void
 */
function bgcf_posted_on( $post = null ): void {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	printf(
		'<time class="bgcf-entry-meta__item bgcf-entry-meta__item--date" datetime="%1$s">%2$s</time>',
		esc_attr( (string) get_the_date( DATE_W3C, $post ) ),
		esc_html( (string) get_the_date( '', $post ) )
	);
}

/**
 * Echo a post's terms for a taxonomy as a list of links.
 *
 * @since 1.0.0
 *
 * @param string           $taxonomy Taxonomy slug.
 * @param int|WP_Post|null $post     Post. Default current post.
 * @return void
 */
function bgcf_entry_terms( string $taxonomy, $post = null ): void {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || ! taxonomy_exists( $taxonomy ) ) {
		return;
	}

	$terms = get_the_terms( $post, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}

	$object = get_taxonomy( $taxonomy );
	$label  = $object instanceof WP_Taxonomy ? $object->labels->name : $taxonomy;

	printf( '<ul class="bgcf-term-list" aria-label="%s">', esc_attr( $label ) );

	foreach ( $terms as $term ) {
		$link = get_term_link( $term );

		if ( is_wp_error( $link ) ) {
			continue;
		}

		printf(
			'<li class="bgcf-term-list__item"><a class="bgcf-term-list__link" href="%1$s">%2$s</a></li>',
			esc_url( $link ),
			esc_html( $term->name )
		);
	}

	echo '</ul>';
}

/**
 * Echo accessible archive pagination.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args Overrides passed to the_posts_pagination().
 * @return void
 */
function bgcf_pagination( array $args = array() ): void {
	the_posts_pagination(
		wp_parse_args(
			$args,
			array(
				'mid_size'           => 1,
				'prev_text'          => bgcf_get_text( 'pagination_prev', __( 'Previous', 'bgcf' ) ),
				'next_text'          => bgcf_get_text( 'pagination_next', __( 'Next', 'bgcf' ) ),
				'screen_reader_text' => bgcf_get_text( 'pagination_label', __( 'Page navigation', 'bgcf' ) ),
				'aria_label'         => bgcf_get_text( 'pagination_label', __( 'Page navigation', 'bgcf' ) ),
				'class'              => 'bgcf-pagination',
			)
		)
	);
}

/**
 * Echo a heading with a consistent eyebrow / title / lead structure.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args {
 *     Optional arguments.
 *
 *     @type string $eyebrow Small label above the title.
 *     @type string $title   Heading text.
 *     @type string $lead    Supporting paragraph. Allows inline HTML.
 *     @type string $tag     Heading level, 'h1'–'h6'. Default 'h2'.
 *     @type string $id      Heading id, for aria-labelledby.
 *     @type string $align   'start' (default) or 'center'.
 *     @type string $class   Extra class names.
 * }
 * @return void
 */
function bgcf_section_heading( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow' => '',
			'title'   => '',
			'lead'    => '',
			'tag'     => 'h2',
			'id'      => '',
			'align'   => 'start',
			'class'   => '',
		)
	);

	if ( '' === trim( (string) $args['title'] ) && '' === trim( (string) $args['eyebrow'] ) ) {
		return;
	}

	$levels = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	$tag    = in_array( $args['tag'], $levels, true ) ? $args['tag'] : 'h2';
	$align  = 'center' === $args['align'] ? 'center' : 'start';

	printf(
		'<div class="%s">',
		esc_attr( bgcf_classnames( array( 'bgcf-section-heading', 'bgcf-section-heading--' . $align, (string) $args['class'] ) ) )
	);

	if ( '' !== $args['eyebrow'] ) {
		printf( '<p class="bgcf-section-heading__eyebrow">%s</p>', esc_html( (string) $args['eyebrow'] ) );
	}

	if ( '' !== $args['title'] ) {
		printf(
			'<%1$s class="bgcf-section-heading__title"%2$s>%3$s</%1$s>',
			tag_escape( $tag ),
			'' !== $args['id'] ? ' id="' . esc_attr( sanitize_html_class( (string) $args['id'] ) ) . '"' : '',
			esc_html( (string) $args['title'] )
		);
	}

	if ( '' !== $args['lead'] ) {
		printf(
			'<div class="bgcf-section-heading__lead">%s</div>',
			wp_kses_post( (string) $args['lead'] )
		);
	}

	echo '</div>';
}

/**
 * Echo an editor-facing empty state.
 *
 * Used by block partials when required content is missing. Renders only for
 * users who can edit the post, and never on the public front end, so visitors
 * never see scaffolding.
 *
 * @since 1.0.0
 *
 * @param string $message Instruction for the editor.
 * @return void
 */
function bgcf_empty_state( string $message ): void {
	if ( ! is_admin() && ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	printf(
		'<p class="bgcf-empty-state" role="note">%s</p>',
		esc_html( $message )
	);
}
