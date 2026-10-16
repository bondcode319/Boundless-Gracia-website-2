<?php
/**
 * Block: Partners — `bgcf/partners`.
 *
 * Logo wall or marquee sourced from the Partners post type.
 *
 * ACF fields (group: Block — Partners):
 *   heading (text), lead (textarea), layout (select grid|marquee),
 *   partner_type (taxonomy -> bgcf_partner_type), partners (relationship ->
 *   bgcf_partner), limit (number), grayscale (true/false)
 *
 * ACCESSIBILITY, and the three things marquees always get wrong:
 *   1. It must not move at all under `prefers-reduced-motion` — handled in CSS
 *      by only applying the animation inside `motion-ok`, so there is no
 *      JavaScript to fail.
 *   2. It must pause on hover AND on focus, or a keyboard user tabbing through
 *      the logos is chasing a moving target.
 *   3. Each logo's accessible name is the partner's name. Never "logo", never
 *      the filename. Where a partner has a website the logo is a link and the
 *      name is the link text.
 *
 * The marquee duplicates the list for a seamless loop; the duplicate is
 * `aria-hidden` so the names are not announced twice.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading   = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead      = (string) bgcf_field( 'lead', null, '' );
$bgcf_layout    = (string) bgcf_field( 'layout', null, 'grid' );
$bgcf_limit     = max( 1, min( 36, (int) bgcf_field( 'limit', null, 12 ) ) );
$bgcf_grayscale = (bool) bgcf_field( 'grayscale', null, true );
$bgcf_type      = bgcf_field( 'partner_type', null, 0 );

$bgcf_layout     = 'marquee' === $bgcf_layout ? 'marquee' : 'grid';
$bgcf_heading_id = 'bgcf-partners-title-' . (string) wp_unique_id();

$bgcf_query_args = array(
	'post_type'           => 'bgcf_partner',
	'post_status'         => 'publish',
	'posts_per_page'      => $bgcf_limit,
	'no_found_rows'       => true,
	'orderby'             => 'menu_order title',
	'order'               => 'ASC',
	'ignore_sticky_posts' => true,
);

$bgcf_term_id = $bgcf_type instanceof WP_Term ? (int) $bgcf_type->term_id : absint( is_array( $bgcf_type ) ? ( $bgcf_type['term_id'] ?? 0 ) : $bgcf_type );

if ( $bgcf_term_id > 0 ) {
	$bgcf_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Editor-chosen filter.
		array(
			'taxonomy' => 'bgcf_partner_type',
			'field'    => 'term_id',
			'terms'    => $bgcf_term_id,
		),
	);
}

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => bgcf_classnames(
		array(
			(string) ( $args['class'] ?? '' ),
			'bgcf-block-partners--' . $bgcf_layout,
			'bgcf-block-partners--grayscale' => $bgcf_grayscale,
		)
	),
	'background' => 'base',
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

$bgcf_partners = new WP_Query( $bgcf_query_args );

if ( ! $bgcf_partners->have_posts() ) {
	bgcf_empty_state( __( 'Partners: add partner organisations, then choose which to show here.', 'bgcf' ) );

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

/**
 * Render the logo list.
 *
 * Declared as a closure rather than repeated, because the marquee needs the
 * same list twice and a copy-paste would drift.
 *
 * @param WP_Query $query    Partner query.
 * @param bool     $decorative Whether this copy is hidden from assistive tech.
 * @return void
 */
$bgcf_render_logos = static function ( WP_Query $query, bool $decorative = false ) {
	?>
	<ul class="bgcf-partners__list"<?php echo $decorative ? ' aria-hidden="true"' : ''; ?>>
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();

			$bgcf_name = (string) get_the_title();
			$bgcf_url  = (string) bgcf_field( 'website', get_the_ID(), '' );
			$bgcf_logo = bgcf_get_post_thumbnail(
				array(
					'size'     => 'bgcf-partner-logo',
					'class'    => 'bgcf-partners__logo',
					'fallback' => false,
					// The accessible name of the image IS the partner name: this
					// is informative content, not decoration.
					'alt'      => $bgcf_name,
				)
			);
			?>
			<li class="bgcf-partners__item">
				<?php
				if ( '' === $bgcf_logo ) {
					// No logo uploaded: the name in text is a better outcome than
					// an empty tile.
					printf( '<span class="bgcf-partners__name">%s</span>', esc_html( $bgcf_name ) );
				} elseif ( '' !== $bgcf_url && ! $decorative ) {
					printf(
						'<a class="bgcf-partners__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="bgcf-u-visually-hidden">%3$s</span></a>',
						esc_url( $bgcf_url ),
						$bgcf_logo, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
						esc_html(
							sprintf(
								/* translators: %s: partner name. */
								__( '%s (opens in a new tab)', 'bgcf' ),
								$bgcf_name
							)
						)
					);
				} else {
					echo $bgcf_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				}
				?>
			</li>
			<?php
		endwhile;

		$query->rewind_posts();
		?>
	</ul>
	<?php
};
?>

<div class="bgcf-partners" data-bgcf-reveal>
	<?php if ( 'marquee' === $bgcf_layout ) : ?>
		<div class="bgcf-partners__marquee">
			<div class="bgcf-partners__marquee-track">
				<?php
				$bgcf_render_logos( $bgcf_partners, false );
				// Second copy: purely so the loop has no visible seam.
				$bgcf_render_logos( $bgcf_partners, true );
				?>
			</div>
		</div>
	<?php else : ?>
		<?php $bgcf_render_logos( $bgcf_partners, false ); ?>
	<?php endif; ?>
</div>

<?php
wp_reset_postdata();

bgcf_section_close( $bgcf_section );
