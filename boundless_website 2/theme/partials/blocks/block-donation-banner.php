<?php
/**
 * Block: Donation Banner — `bgcf/donation-banner`.
 *
 * The site's primary conversion surface. Renders the Flutterwave donation form
 * (partials/global/donation-form.php) beside the campaign copy, so a gift can be
 * started without leaving the page.
 *
 * ACF fields (group: Block — Donation Banner):
 *   heading (text), text (textarea), background_image (image),
 *   campaign (post object -> bgcf_campaign), show_form (true/false),
 *   donate_cta (link — used when show_form is off)
 *
 * The block never touches money itself: amounts, methods, limits and the
 * thank-you page all come from Donations → Settings, and the charge is created
 * server-side by inc/payments-routes.php. Nothing here may be trusted by the
 * payment flow — the amount a donor picks in this markup is re-validated on the
 * server against those settings.
 *
 * PROGRESS FIGURES come from the donation records at render time, via
 * bgcf_campaign_progress(), and are cached in a transient that completed gifts
 * flush. A "raised" total stored in ACF goes stale silently and overstates what
 * the charity has actually received, which is a compliance problem rather than
 * merely a display bug — so there is no field for it and there must never be.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => 'primary',
	'spacing'    => 'lg',
	'width'      => 'default',
);

$bgcf_heading   = (string) bgcf_field( 'heading', null, '' );
$bgcf_text      = (string) bgcf_field( 'text', null, '' );
$bgcf_campaign  = bgcf_field( 'campaign', null, 0 );
$bgcf_show_form = (bool) bgcf_field( 'show_form', null, true );
$bgcf_cta       = bgcf_field( 'donate_cta', null, array() );

// ACF Post Object fields return a WP_Post, an ID or an array depending on how
// the field was configured; the partial should not have to care.
if ( $bgcf_campaign instanceof WP_Post ) {
	$bgcf_campaign = (int) $bgcf_campaign->ID;
} elseif ( is_array( $bgcf_campaign ) ) {
	$bgcf_campaign = (int) ( $bgcf_campaign['ID'] ?? 0 );
}

$bgcf_campaign = absint( $bgcf_campaign );

$bgcf_progress = $bgcf_campaign > 0 ? bgcf_campaign_progress( $bgcf_campaign ) : null;

bgcf_section_open( $bgcf_section );

if ( '' === $bgcf_heading && '' === $bgcf_text && ! $bgcf_show_form ) {
	bgcf_empty_state( __( 'Donation banner: add a heading, or switch the donation form on in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}
?>

<div class="bgcf-donation-banner__inner">
	<div class="bgcf-donation-banner__copy">
		<?php if ( '' !== $bgcf_heading ) : ?>
			<h2 class="bgcf-donation-banner__heading"><?php echo esc_html( $bgcf_heading ); ?></h2>
		<?php endif; ?>

		<?php if ( '' !== $bgcf_text ) : ?>
			<div class="bgcf-donation-banner__text"><?php echo wp_kses_post( wpautop( $bgcf_text ) ); ?></div>
		<?php endif; ?>

		<?php if ( is_array( $bgcf_progress ) && $bgcf_progress['goal'] > 0 ) : ?>
			<?php
			/*
			 * The bar itself is decorative — every number it encodes is in the
			 * text beside it, so `aria-hidden` is correct and a progressbar role
			 * would only add a percentage a screen reader already heard.
			 */
			?>
			<div class="bgcf-progress">
				<div class="bgcf-progress__track" aria-hidden="true">
					<div
						class="bgcf-progress__bar"
						style="--bgcf-progress: <?php echo esc_attr( (string) $bgcf_progress['percent'] ); ?>%;"
					></div>
				</div>

				<p class="bgcf-progress__figures">
					<strong><?php echo esc_html( bgcf_donation_format_amount( $bgcf_progress['raised'] ) ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: goal amount, 2: percentage of goal reached. */
							__( 'raised of our %1$s goal (%2$s%%)', 'bgcf' ),
							bgcf_donation_format_amount( $bgcf_progress['goal'] ),
							number_format_i18n( $bgcf_progress['percent'] )
						)
					);
					?>
				</p>

				<p class="bgcf-progress__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of supporters. */
							_n( '%s supporter so far', '%s supporters so far', $bgcf_progress['donors'], 'bgcf' ),
							number_format_i18n( $bgcf_progress['donors'] )
						)
					);

					if ( null !== $bgcf_progress['days_left'] ) {
						echo ' · ';
						echo esc_html(
							0 === $bgcf_progress['days_left']
								? __( 'final day', 'bgcf' )
								: sprintf(
									/* translators: %s: number of days remaining. */
									_n( '%s day left', '%s days left', $bgcf_progress['days_left'], 'bgcf' ),
									number_format_i18n( $bgcf_progress['days_left'] )
								)
						);
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! $bgcf_show_form ) : ?>
			<?php
			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				$bgcf_cta,
				array(
					'variant' => 'primary',
					'size'    => 'lg',
				)
			);
			?>
		<?php endif; ?>
	</div>

	<?php if ( $bgcf_show_form ) : ?>
		<div class="bgcf-donation-banner__form">
			<?php
			bgcf_render_partial(
				'global/donation-form',
				array(
					'campaign' => $bgcf_campaign,
					'class'    => 'bgcf-donate--banner',
				)
			);
			?>
		</div>
	<?php endif; ?>
</div>

<?php
bgcf_section_close( $bgcf_section );
