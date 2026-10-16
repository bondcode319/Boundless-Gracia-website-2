<?php
/**
 * Block: Statistics — `bgcf/statistics`.
 *
 * Band of impact counters, animated on scroll.
 *
 * ACF fields (group: Block — Statistics):
 *   heading (text), lead (textarea), background (select base|surface|primary),
 *   stats (repeater: value number, prefix, suffix, label, icon)
 *
 * ACCESSIBILITY, and the reason this partial looks the way it does: the final
 * value is rendered into the DOM here, already formatted for the locale. The
 * count-up in assets/js/src/motion.js only *overwrites* that text, and it
 * restores the server-rendered string on the last frame. So:
 *   - A screen reader reads the real number, once, with no live region.
 *   - A no-JS visitor sees the real number.
 *   - A reduced-motion visitor sees the real number and no animation.
 * Animating from zero with no value in the markup — the usual implementation —
 * tells assistive technology the charity has helped 0 children.
 *
 * Semantics: a figure and its label are a description list, not two divs. The
 * value is the `<dd>` even though it reads first visually; CSS reverses the
 * order so the DOM can stay in the order that makes sense when read aloud.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading    = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead       = (string) bgcf_field( 'lead', null, '' );
$bgcf_background = (string) bgcf_field( 'background', null, 'primary' );
$bgcf_stats      = (array) bgcf_field( 'stats', null, array() );

$bgcf_heading_id = 'bgcf-stats-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => in_array( $bgcf_background, array( 'base', 'surface', 'primary' ), true ) ? $bgcf_background : 'primary',
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_stats ) ) {
	bgcf_empty_state( __( 'Statistics: add the impact figures you want to highlight in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'title' => $bgcf_heading,
		'lead'  => $bgcf_lead,
		'id'    => $bgcf_heading_id,
		'align' => 'center',
	)
);
?>

<dl class="bgcf-stats" data-bgcf-reveal-stagger>
	<?php foreach ( $bgcf_stats as $bgcf_stat ) : ?>
		<?php
		$bgcf_label = (string) ( $bgcf_stat['label'] ?? '' );
		$bgcf_raw   = (string) ( $bgcf_stat['value'] ?? '' );

		if ( '' === $bgcf_label || '' === $bgcf_raw ) {
			continue;
		}

		$bgcf_numeric   = (float) preg_replace( '/[^0-9.\-]/', '', $bgcf_raw );
		$bgcf_decimals  = (int) ( $bgcf_stat['decimals'] ?? 0 );
		$bgcf_formatted = number_format_i18n( $bgcf_numeric, $bgcf_decimals );
		$bgcf_prefix    = (string) ( $bgcf_stat['prefix'] ?? '' );
		$bgcf_suffix    = (string) ( $bgcf_stat['suffix'] ?? '' );
		$bgcf_icon      = (string) ( $bgcf_stat['icon'] ?? '' );
		?>
		<div class="bgcf-stats__item" data-bgcf-reveal>
			<?php if ( '' !== $bgcf_icon ) : ?>
				<?php bgcf_svg_icon( $bgcf_icon, array( 'class' => 'bgcf-stats__icon' ) ); ?>
			<?php endif; ?>

			<dd class="bgcf-stats__value">
				<?php if ( '' !== $bgcf_prefix ) : ?>
					<span class="bgcf-stats__affix"><?php echo esc_html( $bgcf_prefix ); ?></span>
				<?php endif; ?>

				<span
					class="bgcf-stats__number"
					data-bgcf-counter="<?php echo esc_attr( (string) $bgcf_numeric ); ?>"
					data-bgcf-counter-decimals="<?php echo esc_attr( (string) $bgcf_decimals ); ?>"
					data-bgcf-counter-formatted="<?php echo esc_attr( $bgcf_formatted ); ?>"
				><?php echo esc_html( $bgcf_formatted ); ?></span>

				<?php if ( '' !== $bgcf_suffix ) : ?>
					<span class="bgcf-stats__affix"><?php echo esc_html( $bgcf_suffix ); ?></span>
				<?php endif; ?>
			</dd>

			<dt class="bgcf-stats__label"><?php echo esc_html( $bgcf_label ); ?></dt>
		</div>
	<?php endforeach; ?>
</dl>

<?php
bgcf_section_close( $bgcf_section );
