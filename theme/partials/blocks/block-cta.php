<?php
/**
 * Block: Call to Action — `bgcf/cta`.
 *
 * Generic conversion band: heading, supporting copy, one or two buttons.
 * Used for the volunteer banner and any inner-page prompt that is not a
 * donation ask (use bgcf/donation-banner for those).
 *
 * ACF fields (group: Block — CTA):
 *   eyebrow (text), heading (text), text (textarea), background
 *   (select primary|secondary|surface), image (image), layout (select
 *   centered|split), primary_cta (link), secondary_cta (link)
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_eyebrow    = (string) bgcf_field( 'eyebrow', null, '' );
$bgcf_heading    = (string) bgcf_field( 'heading', null, '' );
$bgcf_text       = (string) bgcf_field( 'text', null, '' );
$bgcf_background = (string) bgcf_field( 'background', null, 'secondary' );
$bgcf_layout     = (string) bgcf_field( 'layout', null, 'centered' );
$bgcf_image      = bgcf_image_id( bgcf_field( 'image', null, 0 ) );
$bgcf_primary    = bgcf_field( 'primary_cta', null, array() );
$bgcf_secondary  = bgcf_field( 'secondary_cta', null, array() );

$bgcf_layout     = 'split' === $bgcf_layout ? 'split' : 'centered';
$bgcf_heading_id = 'bgcf-cta-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => bgcf_classnames(
		array(
			(string) ( $args['class'] ?? '' ),
			'bgcf-block-cta--' . $bgcf_layout,
		)
	),
	'background' => in_array( $bgcf_background, array( 'primary', 'secondary', 'surface' ), true ) ? $bgcf_background : 'secondary',
	'spacing'    => 'md',
	'width'      => 'default',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

$bgcf_buttons = bgcf_get_link_button(
	$bgcf_primary,
	array(
		'variant' => 'primary',
		'size'    => 'lg',
	)
) . bgcf_get_link_button(
	$bgcf_secondary,
	array(
		'variant' => 'ghost',
		'size'    => 'lg',
	)
);

if ( '' === $bgcf_heading && '' === $bgcf_buttons ) {
	bgcf_empty_state( __( 'Call to action: add a heading and at least one button in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}
?>

<div class="bgcf-cta" data-bgcf-reveal>
	<?php if ( 'split' === $bgcf_layout && $bgcf_image > 0 ) : ?>
		<div class="bgcf-cta__media">
			<?php
			bgcf_the_post_thumbnail(
				array(
					'attachment_id' => $bgcf_image,
					'size'          => 'bgcf-card',
					'class'         => 'bgcf-cta__image',
					'sizes'         => '(min-width: 1024px) 40vw, 100vw',
					'fallback'      => false,
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="bgcf-cta__copy">
		<?php if ( '' !== $bgcf_eyebrow ) : ?>
			<p class="bgcf-cta__eyebrow"><?php echo esc_html( $bgcf_eyebrow ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $bgcf_heading ) : ?>
			<h2 class="bgcf-cta__heading" id="<?php echo esc_attr( $bgcf_heading_id ); ?>">
				<?php echo esc_html( $bgcf_heading ); ?>
			</h2>
		<?php endif; ?>

		<?php if ( '' !== $bgcf_text ) : ?>
			<div class="bgcf-cta__text"><?php echo wp_kses_post( wpautop( $bgcf_text ) ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== $bgcf_buttons ) : ?>
			<div class="bgcf-cta__actions">
				<?php echo $bgcf_buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in bgcf_get_link_button(). ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
bgcf_section_close( $bgcf_section );
