<?php
/**
 * Seasonal announcement modal.
 *
 * An appeal, a campaign launch or an event, shown once per session. Entirely
 * optional and entirely editor-controlled: with no heading set, nothing is
 * rendered and no script runs.
 *
 * WHY IT IS DISMISSED PER SESSION AND NOT PER VISITOR. A modal that never
 * returns is invisible to a donor who dismissed it in March and came back in
 * December for the appeal it was announcing. A modal that returns on every page
 * view is an obstacle. Once per session is the compromise, and `sessionStorage`
 * is where the flag lives (assets/js/src/promo-modal.js) — not a cookie, because
 * a cookie would need a consent banner for something that is purely a UI
 * preference, and not `localStorage`, because that is the "never again"
 * behaviour we just rejected.
 *
 * ACCESSIBILITY CONTRACT, enforced by assets/js/src/promo-modal.js:
 *   - `hidden` until shown, so nothing inside is tabbable before then.
 *   - `role="dialog"` + `aria-modal` + `aria-labelledby` pointing at the
 *     heading, so the dialog announces what it is.
 *   - Focus moves in on open and returns to where it was on close.
 *   - Tab is trapped inside; ESC closes; the backdrop closes.
 *   - It is NOT shown until the page has loaded and the visitor has had a
 *     moment: stealing focus during first paint interrupts a screen-reader
 *     user mid-sentence.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

// Editors disable the modal by clearing the heading, which is less error-prone
// than a separate on/off toggle they have to remember to flip back.
$bgcf_promo_title = trim( (string) bgcf_option( 'promo_title', '' ) );

if ( '' === $bgcf_promo_title || ! (bool) bgcf_option( 'promo_enabled', false ) ) {
	return;
}

$bgcf_promo_text  = (string) bgcf_option( 'promo_text', '' );
$bgcf_promo_link  = bgcf_option( 'promo_link', '' );
$bgcf_promo_image = bgcf_option( 'promo_image', 0 );
$bgcf_promo_delay = max( 0, min( 20, (int) bgcf_option( 'promo_delay', 4 ) ) );

/*
 * A campaign that has ended should stop announcing itself without anyone having
 * to remember to switch it off. An empty end date means "no end date".
 */
$bgcf_promo_until = (string) bgcf_option( 'promo_until', '' );

if ( '' !== $bgcf_promo_until ) {
	$bgcf_ends = strtotime( $bgcf_promo_until . ' 23:59:59' );

	if ( $bgcf_ends && $bgcf_ends < current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Compared against a site-local date the editor typed.
		return;
	}
}
?>
<div class="bgcf-promo-backdrop" data-bgcf-promo-backdrop hidden></div>

<div
	class="bgcf-promo"
	id="bgcf-promo"
	role="dialog"
	aria-modal="true"
	aria-labelledby="bgcf-promo-title"
	data-bgcf-promo
	data-bgcf-promo-delay="<?php echo esc_attr( (string) $bgcf_promo_delay ); ?>"
	hidden
>
	<div class="bgcf-promo__panel">

		<button type="button" class="bgcf-promo__close" data-bgcf-promo-close>
			<?php bgcf_svg_icon( 'close' ); ?>
			<span class="bgcf-u-visually-hidden">
				<?php bgcf_the_text( 'promo_close', __( 'Close announcement', 'bgcf' ) ); ?>
			</span>
		</button>

		<?php
		$bgcf_promo_media = bgcf_get_post_thumbnail(
			array(
				'attachment_id' => bgcf_image_id( $bgcf_promo_image ),
				'size'          => 'bgcf-card',
				'alt'           => '',
				'class'         => 'bgcf-promo__image',
				'fallback'      => false,
			)
		);

		if ( '' !== $bgcf_promo_media ) {
			printf( '<div class="bgcf-promo__media">%s</div>', $bgcf_promo_media ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		}
		?>

		<div class="bgcf-promo__body">
			<h2 class="bgcf-promo__title" id="bgcf-promo-title"><?php echo esc_html( $bgcf_promo_title ); ?></h2>

			<?php if ( '' !== trim( $bgcf_promo_text ) ) : ?>
				<div class="bgcf-promo__text"><?php echo wp_kses_post( $bgcf_promo_text ); ?></div>
			<?php endif; ?>

			<?php
			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				$bgcf_promo_link,
				array(
					'variant' => 'primary',
					'size'    => 'lg',
					'class'   => 'bgcf-promo__cta',
				)
			);
			?>
		</div>
	</div>
</div>
