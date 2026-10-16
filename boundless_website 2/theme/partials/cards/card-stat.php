<?php
/**
 * Stat card — icon, rule, large figure, short label.
 *
 * The figure counts up when the card scrolls into view
 * (`assets/js/src/motion.js`) and renders as plain text when JavaScript is
 * unavailable or the visitor has asked for reduced motion.
 *
 * ACCESSIBILITY, and the reason this partial looks the way it does: the FINAL
 * VALUE is rendered into the DOM here, already formatted for the locale. The
 * count-up only overwrites that text and restores the server-rendered string on
 * its last frame. So a screen reader reads the real number, once, with no live
 * region; a no-JS visitor sees the real number; a reduced-motion visitor sees
 * the real number and no animation.
 *
 * Animating up from a zero that is baked into the markup — the usual
 * implementation — tells assistive technology the charity has helped 0 children.
 *
 * SEMANTICS. A figure and its label are a description list, not two divs. The
 * value is the `<dd>` even though it reads first visually, and
 * `_card.scss` reverses the visual order, so the DOM can stay in the order that
 * makes sense read aloud: "children reached — 12,400", not "12,400" followed by
 * a term with no context.
 *
 * `data-bgcf-counter-formatted` is the contract with motion.js: it is the string
 * the animation must land on, so that a locale using spaces as thousands
 * separators does not end up with a comma on the final frame.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Card arguments. See bgcf_card_defaults().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args  = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_raw   = trim( (string) ( $bgcf_args['value'] ?? '' ) );
$bgcf_label = trim( (string) ( $bgcf_args['label'] ?? $bgcf_args['title'] ?? '' ) );

// Both halves are required: a figure with no label means nothing, and a label
// with no figure is an empty promise.
if ( '' === $bgcf_raw || '' === $bgcf_label ) {
	return;
}

$bgcf_numeric   = (float) preg_replace( '/[^0-9.\-]/', '', $bgcf_raw );
$bgcf_decimals  = max( 0, min( 4, (int) ( $bgcf_args['decimals'] ?? 0 ) ) );
$bgcf_formatted = number_format_i18n( $bgcf_numeric, $bgcf_decimals );
$bgcf_prefix    = trim( (string) ( $bgcf_args['prefix'] ?? '' ) );
$bgcf_suffix    = trim( (string) ( $bgcf_args['suffix'] ?? '' ) );
$bgcf_icon      = (string) ( $bgcf_args['icon'] ?? '' );
?>
<div class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--stat', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>" data-bgcf-reveal>

	<?php if ( '' !== $bgcf_icon ) : ?>
		<span class="bgcf-card__icon" aria-hidden="true">
			<?php bgcf_svg_icon( $bgcf_icon ); ?>
		</span>
	<?php endif; ?>

	<span class="bgcf-card__rule" aria-hidden="true"></span>

	<dl class="bgcf-card__figure-list">
		<dd class="bgcf-card__figure">
			<?php if ( '' !== $bgcf_prefix ) : ?>
				<span class="bgcf-card__figure-prefix"><?php echo esc_html( $bgcf_prefix ); ?></span>
			<?php endif; ?>

			<span
				class="bgcf-card__figure-value"
				data-bgcf-counter="<?php echo esc_attr( (string) $bgcf_numeric ); ?>"
				data-bgcf-counter-decimals="<?php echo esc_attr( (string) $bgcf_decimals ); ?>"
				data-bgcf-counter-formatted="<?php echo esc_attr( $bgcf_formatted ); ?>"
			><?php echo esc_html( $bgcf_formatted ); ?></span>

			<?php if ( '' !== $bgcf_suffix ) : ?>
				<span class="bgcf-card__figure-suffix"><?php echo esc_html( $bgcf_suffix ); ?></span>
			<?php endif; ?>
		</dd>

		<dt class="bgcf-card__label"><?php echo esc_html( $bgcf_label ); ?></dt>
	</dl>

	<?php if ( '' !== (string) ( $bgcf_args['text'] ?? '' ) ) : ?>
		<p class="bgcf-card__text"><?php echo esc_html( (string) $bgcf_args['text'] ); ?></p>
	<?php endif; ?>
</div>
