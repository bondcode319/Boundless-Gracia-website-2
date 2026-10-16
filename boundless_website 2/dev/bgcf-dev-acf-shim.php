<?php
/**
 * Plugin Name: BGCF Dev — ACF Pro block stand-in
 * Description: LOCAL DEVELOPMENT ONLY. Registers the theme's bgcf/* blocks with
 *              core and resolves bgcf_field() from the block's own attribute
 *              data, so the Horizon bands and the starter patterns can be
 *              rendered on an install that has no ACF Pro licence. Delete this
 *              file the moment ACF Pro is installed — it is not a substitute
 *              for the plugin and it does not touch the editor UI.
 * Version:     0.1.0
 *
 * @package BgcfDev
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data bundle of the block currently rendering, innermost last.
 *
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['bgcf_dev_block_stack'] = array();

/**
 * Register every theme block with core when ACF Pro is absent.
 *
 * Both namespaces are registered: templates and the inserter use `bgcf/*`,
 * while the starter patterns serialise `acf/*` (see inc/patterns.php).
 *
 * @return void
 */
function bgcf_dev_register_blocks(): void {
	if ( function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	if ( ! function_exists( 'bgcf_get_block_definitions' ) || ! function_exists( 'bgcf_render_block' ) ) {
		return;
	}

	$attributes = array(
		'name'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'data'      => array(
			'type'    => 'object',
			'default' => array(),
		),
		'mode'      => array(
			'type'    => 'string',
			'default' => 'preview',
		),
		'align'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'className' => array(
			'type'    => 'string',
			'default' => '',
		),
		'anchor'    => array(
			'type'    => 'string',
			'default' => '',
		),
	);

	foreach ( array_keys( bgcf_get_block_definitions() ) as $slug ) {
		$slug = sanitize_key( (string) $slug );

		if ( '' === $slug ) {
			continue;
		}

		foreach ( array( 'bgcf/' . $slug, 'acf/' . $slug ) as $name ) {
			if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				continue;
			}

			register_block_type(
				$name,
				array(
					'api_version'     => 2,
					'attributes'      => $attributes,
					'render_callback' => 'bgcf_dev_render_block',
				)
			);
		}
	}
}
add_action( 'init', 'bgcf_dev_register_blocks', 20 );

/**
 * Render one block through the theme's own callback.
 *
 * @param array<string, mixed> $attributes Parsed block attributes.
 * @param string               $content    Inner block content.
 * @param WP_Block|null        $block      Block instance.
 * @return string Rendered markup.
 */
function bgcf_dev_render_block( $attributes, $content = '', $block = null ): string {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$name = isset( $block->name ) ? (string) $block->name : (string) ( $attributes['name'] ?? '' );

	$bundle = $attributes;
	$bundle['name'] = $name;
	$bundle['data'] = isset( $attributes['data'] ) && is_array( $attributes['data'] ) ? $attributes['data'] : array();

	$GLOBALS['bgcf_dev_block_stack'][] = $bundle['data'];

	ob_start();
	bgcf_render_block( $bundle, (string) $content, false, get_the_ID() );
	$markup = (string) ob_get_clean();

	array_pop( $GLOBALS['bgcf_dev_block_stack'] );

	return $markup;
}

/**
 * Resolve bgcf_field() against the rendering block's attribute data.
 *
 * ACF flattens a repeater to `{name}` (row count) plus `{name}_{i}_{sub}`;
 * this unflattens it back to the array of rows the partials expect.
 *
 * @param mixed           $value    Value resolved so far.
 * @param string          $selector Field name.
 * @param int|string|null $post_id  Post context.
 * @param mixed           $default  Caller fallback.
 * @return mixed
 */
function bgcf_dev_resolve_field( $value, $selector, $post_id, $default ) {
	if ( null !== $post_id ) {
		return $value;
	}

	$stack = $GLOBALS['bgcf_dev_block_stack'];

	if ( empty( $stack ) ) {
		return $value;
	}

	$data = end( $stack );

	if ( ! is_array( $data ) || ! array_key_exists( $selector, $data ) ) {
		return $value;
	}

	$raw = $data[ $selector ];

	// A repeater: the stored value is the row count, rows live in sibling keys.
	if ( is_numeric( $raw ) && isset( $data[ $selector . '_0_' . bgcf_dev_first_subfield( $data, $selector ) ] ) ) {
		return bgcf_dev_rows( $data, $selector, (int) $raw );
	}

	$is_empty = null === $raw || false === $raw || '' === $raw || ( is_array( $raw ) && empty( $raw ) );

	return $is_empty ? $default : $raw;
}
add_filter( 'bgcf_field_value', 'bgcf_dev_resolve_field', 10, 4 );

/**
 * Name of the first sub-field found for a repeater's row 0.
 *
 * @param array<string, mixed> $data     Flat block data.
 * @param string               $selector Repeater field name.
 * @return string Sub-field name, or '' when none is present.
 */
function bgcf_dev_first_subfield( array $data, string $selector ): string {
	$prefix = $selector . '_0_';

	foreach ( array_keys( $data ) as $key ) {
		if ( 0 === strpos( (string) $key, $prefix ) ) {
			return substr( (string) $key, strlen( $prefix ) );
		}
	}

	return '';
}

/**
 * Rebuild a repeater's rows from flattened keys.
 *
 * @param array<string, mixed> $data     Flat block data.
 * @param string               $selector Repeater field name.
 * @param int                  $count    Row count.
 * @return array<int, array<string, mixed>> Rows.
 */
function bgcf_dev_rows( array $data, string $selector, int $count ): array {
	$rows = array();

	for ( $index = 0; $index < $count; $index++ ) {
		$prefix = $selector . '_' . $index . '_';
		$row    = array();

		foreach ( $data as $key => $value ) {
			$key = (string) $key;

			// Skip ACF's `_field` key pointers.
			if ( 0 === strpos( $key, '_' ) ) {
				continue;
			}

			if ( 0 === strpos( $key, $prefix ) ) {
				$row[ substr( $key, strlen( $prefix ) ) ] = $value;
			}
		}

		if ( ! empty( $row ) ) {
			$rows[] = $row;
		}
	}

	return $rows;
}
