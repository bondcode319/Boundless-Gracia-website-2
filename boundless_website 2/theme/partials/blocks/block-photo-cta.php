<?php
/**
 * Block: Photo CTA Band — `bgcf/photo-cta`.
 *
 * Adapter over `bgcf_photo_cta()`. See inc/horizon.php for the band, and
 * `tools/_elevation.scss` for why the scrim is what makes an editor-chosen
 * photograph safe to put white text on.
 *
 * ACF fields (group: Block — Photo CTA):
 *   eyebrow (text), heading (text), text (textarea), image (image),
 *   link (link), link_alt (link), scrim (range 55-85)
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

bgcf_photo_cta(
	array(
		'eyebrow'  => (string) bgcf_field( 'eyebrow', null, '' ),
		'title'    => (string) bgcf_field( 'heading', null, '' ),
		'text'     => (string) bgcf_field( 'text', null, '' ),
		'image'    => bgcf_image_id( bgcf_field( 'image', null, 0 ) ),
		'link'     => bgcf_field( 'link', null, '' ),
		'link_alt' => bgcf_field( 'link_alt', null, '' ),
		'scrim'    => (int) bgcf_field( 'scrim', null, 62 ),
		'id'       => (string) ( $args['anchor'] ?? '' ),
		'class'    => (string) ( $args['class'] ?? '' ),
	)
);
