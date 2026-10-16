<?php
/**
 * Block: Newsletter — `bgcf/newsletter`.
 *
 * Email sign-up submitted over AJAX to `bgcf_newsletter_subscribe`
 * (see inc/ajax.php). The form itself lives in partials/global/newsletter-form.php
 * so the footer sign-up and this block are literally the same form — the consent
 * wording, honeypot and status region cannot drift apart.
 *
 * ACF fields (group: Block — Newsletter):
 *   heading (text), text (textarea), background (select surface|primary),
 *   consent_text (wysiwyg), submit_label (text), privacy_link (link)
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading    = (string) bgcf_field( 'heading', null, '' );
$bgcf_text       = (string) bgcf_field( 'text', null, '' );
$bgcf_background = (string) bgcf_field( 'background', null, 'surface' );

$bgcf_heading_id = 'bgcf-newsletter-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => in_array( $bgcf_background, array( 'surface', 'primary' ), true ) ? $bgcf_background : 'surface',
	'spacing'    => 'md',
	'width'      => 'narrow',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( '' === $bgcf_heading ) {
	bgcf_empty_state( __( 'Newsletter: add a heading and consent text in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'title' => $bgcf_heading,
		'lead'  => $bgcf_text,
		'id'    => $bgcf_heading_id,
		'align' => 'center',
	)
);

bgcf_render_partial(
	'global/newsletter-form',
	array(
		'class'        => 'bgcf-newsletter--block',
		'source'       => 'block',
		'consent_text' => (string) bgcf_field( 'consent_text', null, '' ),
		'submit_label' => (string) bgcf_field( 'submit_label', null, '' ),
		'privacy_link' => bgcf_field( 'privacy_link', null, '' ),
	)
);

bgcf_section_close( $bgcf_section );
