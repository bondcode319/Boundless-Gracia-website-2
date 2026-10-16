<?php
/**
 * Block: Hero — `bgcf/hero`.
 *
 * Full-bleed opening section. On the homepage this block owns the page's single
 * `<h1>`; on inner pages it must render an `<h2>` instead. The heading level is
 * therefore a field, not a constant.
 *
 * ACF fields (group: Block — Hero):
 *   background_image (image), overlay_opacity (range), eyebrow (text),
 *   heading (text), heading_level (select h1|h2), lead (textarea),
 *   primary_cta (link), secondary_cta (link), stats (repeater: value, label),
 *   floating_cards (repeater: icon, title, text)
 *
 * PERFORMANCE: the background image is the LCP element on nearly every page it
 * appears on, so it is rendered eagerly with `fetchpriority="high"` and is never
 * lazy-loaded. Lazy-loading your own LCP image is the most common self-inflicted
 * Core Web Vitals failure there is.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block():
 *                                 block, slug, content, is_preview, post_id,
 *                                 anchor, class, is_example.
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_level   = (string) bgcf_field( 'heading_level', null, 'h1' );
$bgcf_eyebrow = (string) bgcf_field( 'eyebrow', null, '' );
$bgcf_lead    = (string) bgcf_field( 'lead', null, '' );
$bgcf_image   = bgcf_image_id( bgcf_field( 'background_image', null, 0 ) );
$bgcf_overlay = (int) bgcf_field( 'overlay_opacity', null, 55 );
$bgcf_stats   = (array) bgcf_field( 'stats', null, array() );
$bgcf_cards   = (array) bgcf_field( 'floating_cards', null, array() );

$bgcf_level = in_array( $bgcf_level, array( 'h1', 'h2' ), true ) ? $bgcf_level : 'h1';

// Clamp rather than trust: an overlay above 85% makes the image pointless and
// below 25% fails contrast against white text.
$bgcf_overlay = max( 0, min( 90, $bgcf_overlay ) );

$bgcf_heading_id = 'bgcf-hero-title-' . ( '' !== (string) ( $args['anchor'] ?? '' ) ? sanitize_html_class( (string) $args['anchor'] ) : (string) wp_unique_id() );

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => 'primary',
	'spacing'    => 'none',
	'width'      => false,
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( '' === $bgcf_heading ) {
	bgcf_empty_state( __( 'Hero: add a background image, headline and calls to action in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}
?>

<div class="bgcf-hero" style="--bgcf-hero-overlay: <?php echo esc_attr( (string) ( $bgcf_overlay / 100 ) ); ?>;">

	<?php if ( $bgcf_image > 0 ) : ?>
		<div class="bgcf-hero__media" aria-hidden="true">
			<?php
			bgcf_the_post_thumbnail(
				array(
					'attachment_id' => $bgcf_image,
					'size'          => 'bgcf-hero',
					'class'         => 'bgcf-hero__image',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'sizes'         => '100vw',
					'fallback'      => false,
					// Decorative: the heading carries the meaning, and describing
					// the backdrop twice is noise for a screen-reader user.
					'alt'           => '',
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="bgcf-container bgcf-container--wide bgcf-hero__inner">
		<div class="bgcf-hero__copy">
			<?php if ( '' !== $bgcf_eyebrow ) : ?>
				<p class="bgcf-hero__eyebrow"><?php echo esc_html( $bgcf_eyebrow ); ?></p>
			<?php endif; ?>

			<?php
			/*
			 * `bgcf_get_emphasised_heading()` returns the heading already
			 * escaped, with one phrase wrapped in `<em>`. Escaping it again here
			 * would print the tags as text — see the function's doc block.
			 */
			printf(
				'<%1$s class="bgcf-hero__heading" id="%2$s">%3$s</%1$s>',
				tag_escape( $bgcf_level ),
				esc_attr( $bgcf_heading_id ),
				bgcf_get_emphasised_heading( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					$bgcf_heading,
					(string) bgcf_field( 'heading_emphasis', null, '' )
				)
			);
			?>

			<?php if ( '' !== $bgcf_lead ) : ?>
				<div class="bgcf-hero__lead"><?php echo wp_kses_post( wpautop( $bgcf_lead ) ); ?></div>
			<?php endif; ?>

			<div class="bgcf-hero__actions">
				<?php
				echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					bgcf_field( 'primary_cta', null, array() ),
					array(
						'variant' => 'primary',
						'size'    => 'lg',
						'icon'    => 'heart',
					)
				);

				echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					bgcf_field( 'secondary_cta', null, array() ),
					array(
						'variant' => 'ghost',
						'size'    => 'lg',
						'icon'    => 'arrow-right',
					)
				);
				?>
			</div>

			<?php if ( ! empty( $bgcf_stats ) ) : ?>
				<dl class="bgcf-hero__stats">
					<?php foreach ( $bgcf_stats as $bgcf_stat ) : ?>
						<?php
						$bgcf_value = (string) ( $bgcf_stat['value'] ?? '' );
						$bgcf_label = (string) ( $bgcf_stat['label'] ?? '' );

						if ( '' === $bgcf_value && '' === $bgcf_label ) {
							continue;
						}
						?>
						<div class="bgcf-hero__stat">
							<dt class="bgcf-hero__stat-label"><?php echo esc_html( $bgcf_label ); ?></dt>
							<dd class="bgcf-hero__stat-value"><?php echo esc_html( $bgcf_value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $bgcf_cards ) ) : ?>
			<ul class="bgcf-hero__cards" data-bgcf-reveal-stagger>
				<?php foreach ( $bgcf_cards as $bgcf_card ) : ?>
					<?php
					$bgcf_card_title = (string) ( $bgcf_card['title'] ?? '' );

					if ( '' === $bgcf_card_title ) {
						continue;
					}
					?>
					<li class="bgcf-hero__card" data-bgcf-reveal>
						<?php bgcf_svg_icon( (string) ( $bgcf_card['icon'] ?? 'heart' ), array( 'class' => 'bgcf-hero__card-icon' ) ); ?>
						<p class="bgcf-hero__card-title"><?php echo esc_html( $bgcf_card_title ); ?></p>

						<?php if ( ! empty( $bgcf_card['text'] ) ) : ?>
							<p class="bgcf-hero__card-text"><?php echo esc_html( (string) $bgcf_card['text'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

<?php
bgcf_section_close( $bgcf_section );
