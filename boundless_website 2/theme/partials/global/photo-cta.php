<?php
/**
 * Photographic call-to-action band.
 *
 * Full-bleed photograph behind a deep-blue scrim, centred white heading, one
 * sentence, one button. Rendered through `bgcf_photo_cta()`.
 *
 * WHY THE IMAGE IS A CSS BACKGROUND AND NOT AN `<img>`. This band's photograph
 * is decorative: the heading carries the whole message, and the band reads
 * identically with the image replaced by flat colour. A decorative image that
 * must crop to an arbitrary band height, bleed past the container and sit under
 * a scrim is a background's job. The cost is no `srcset`, which is why
 * `bgcf-hero` is the only crop offered here and why the band is excluded from
 * the LCP-critical path on every template that uses it (it is never the first
 * band on a page).
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Band arguments. See bgcf_photo_cta().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args = isset( $args ) && is_array( $args ) ? $args : array();

$bgcf_tag = in_array( (string) ( $bgcf_args['title_tag'] ?? 'h2' ), array( 'h1', 'h2', 'h3' ), true )
	? (string) $bgcf_args['title_tag']
	: 'h2';

$bgcf_heading_id = 'bgcf-photo-cta-' . substr( md5( (string) ( $bgcf_args['title'] ?? '' ) ), 0, 8 );

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $bgcf_args['id'] ?? '' ),
	'class'      => bgcf_classnames( array( 'bgcf-photo-cta', (string) ( $bgcf_args['class'] ?? '' ) ) ),
	'background' => 'photo',
	'spacing'    => (string) ( $bgcf_args['spacing'] ?? 'md' ),
	'width'      => 'default',
	'image'      => (int) ( $bgcf_args['image'] ?? 0 ),
	'image_size' => 'bgcf-hero',
	// Clamped to 55-85 by bgcf_section_open(), so an editor who types 20 gets
	// the readable floor rather than an unreadable band.
	'scrim'      => (int) ( $bgcf_args['scrim'] ?? 62 ),
	'labelledby' => $bgcf_heading_id,
);

bgcf_section_open( $bgcf_section );
?>

	<div class="bgcf-photo-cta__inner">
		<?php
		bgcf_section_heading(
			array(
				'eyebrow' => (string) ( $bgcf_args['eyebrow'] ?? '' ),
				'title'   => (string) ( $bgcf_args['title'] ?? '' ),
				'lead'    => (string) ( $bgcf_args['text'] ?? '' ),
				'tag'     => $bgcf_tag,
				'id'      => $bgcf_heading_id,
				'align'   => 'center',
				'class'   => 'bgcf-photo-cta__heading',
			)
		);

		$bgcf_primary   = bgcf_get_link_button(
			$bgcf_args['link'] ?? '',
			array(
				'variant' => 'primary',
				'size'    => 'lg',
				'icon'    => 'arrow-right',
			)
		);
		$bgcf_secondary = bgcf_get_link_button(
			$bgcf_args['link_alt'] ?? '',
			array(
				'variant' => 'ghost',
				'size'    => 'lg',
				'class'   => 'bgcf-btn--on-photo',
			)
		);

		if ( '' !== $bgcf_primary || '' !== $bgcf_secondary ) {
			echo '<div class="bgcf-photo-cta__actions">';
			echo $bgcf_primary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
			echo $bgcf_secondary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
			echo '</div>';
		}
		?>
	</div>

<?php
bgcf_section_close( $bgcf_section );
