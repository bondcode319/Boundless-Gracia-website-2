<?php
/**
 * Block: Testimonials — `bgcf/testimonials`.
 *
 * Swiper carousel (or a static grid) over the Testimonials post type.
 * bgcf_use_swiper() is called from here, so Swiper only loads on pages that
 * actually carousel — which is the whole reason the enqueue layer registers it
 * without enqueueing it.
 *
 * ACF fields (group: Block — Testimonials):
 *   heading (text), lead (textarea), source (select latest|manual),
 *   testimonials (relationship -> bgcf_testimonial), limit (number),
 *   layout (select carousel|grid), show_rating (true/false), autoplay (true/false)
 *
 * ACCESSIBILITY: real previous/next buttons are rendered server-side and handed
 * to Swiper rather than letting it generate divs; autoplay is opt-in, never
 * starts under reduced motion, ships with a visible pause control (WCAG 2.2.2)
 * and stops permanently once a keyboard user focuses inside. Without JS the
 * markup is a scroll-snap list that still swipes on touch and still reads in
 * order — hence `bgcf-carousel` styling that does not depend on initialisation.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading  = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead     = (string) bgcf_field( 'lead', null, '' );
$bgcf_source   = (string) bgcf_field( 'source', null, 'latest' );
$bgcf_layout   = (string) bgcf_field( 'layout', null, 'carousel' );
$bgcf_limit    = max( 1, min( 24, (int) bgcf_field( 'limit', null, 6 ) ) );
$bgcf_rating   = (bool) bgcf_field( 'show_rating', null, false );
$bgcf_autoplay = (bool) bgcf_field( 'autoplay', null, false );
$bgcf_manual   = (array) bgcf_field( 'testimonials', null, array() );

$bgcf_layout     = 'grid' === $bgcf_layout ? 'grid' : 'carousel';
$bgcf_heading_id = 'bgcf-testimonials-title-' . (string) wp_unique_id();

$bgcf_query_args = array(
	'post_type'           => 'bgcf_testimonial',
	'post_status'         => 'publish',
	'posts_per_page'      => $bgcf_limit,
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
);

if ( 'manual' === $bgcf_source && ! empty( $bgcf_manual ) ) {
	$bgcf_ids = array_values(
		array_filter(
			array_map(
				static function ( $item ) {
					if ( $item instanceof WP_Post ) {
						return (int) $item->ID;
					}

					return is_array( $item ) ? (int) ( $item['ID'] ?? 0 ) : absint( $item );
				},
				$bgcf_manual
			)
		)
	);

	$bgcf_query_args['post__in']       = $bgcf_ids;
	$bgcf_query_args['orderby']        = 'post__in';
	$bgcf_query_args['posts_per_page'] = min( count( $bgcf_ids ), 24 );
}

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => 'surface',
	'spacing'    => 'md',
	'width'      => 'wide',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

$bgcf_quotes = new WP_Query( $bgcf_query_args );

if ( ! $bgcf_quotes->have_posts() ) {
	bgcf_empty_state( __( 'Testimonials: add a testimonial, or choose which quotes to show in the block settings.', 'bgcf' ) );

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

$bgcf_is_carousel = 'carousel' === $bgcf_layout;

if ( $bgcf_is_carousel ) {
	bgcf_use_swiper();
}
?>

<div
	class="<?php echo esc_attr( $bgcf_is_carousel ? 'bgcf-carousel swiper' : 'bgcf-grid bgcf-grid--cols-3' ); ?>"
	<?php if ( $bgcf_is_carousel ) : ?>
		data-bgcf-carousel
		data-bgcf-per-view="3"
		<?php echo $bgcf_autoplay ? 'data-bgcf-autoplay="7000"' : ''; ?>
	<?php else : ?>
		data-bgcf-reveal-stagger
	<?php endif; ?>
>
	<div class="<?php echo esc_attr( $bgcf_is_carousel ? 'bgcf-carousel__track swiper-wrapper' : 'bgcf-grid__inner' ); ?>">
		<?php
		while ( $bgcf_quotes->have_posts() ) :
			$bgcf_quotes->the_post();

			$bgcf_role    = (string) bgcf_field( 'role', get_the_ID(), '' );
			$bgcf_stars   = (int) bgcf_field( 'rating', get_the_ID(), 0 );
			$bgcf_photo   = bgcf_get_post_thumbnail(
				array(
					'size'     => 'bgcf-team',
					'class'    => 'bgcf-testimonial__avatar',
					'fallback' => false,
					'alt'      => '',
				)
			);
			$bgcf_classes = $bgcf_is_carousel ? 'bgcf-testimonial bgcf-carousel__slide swiper-slide' : 'bgcf-testimonial';
			?>
			<figure class="<?php echo esc_attr( $bgcf_classes ); ?>"<?php echo $bgcf_is_carousel ? '' : ' data-bgcf-reveal'; ?>>
				<?php bgcf_svg_icon( 'quote', array( 'class' => 'bgcf-testimonial__mark' ) ); ?>

				<blockquote class="bgcf-testimonial__quote">
					<?php the_content(); ?>
				</blockquote>

				<?php if ( $bgcf_rating && $bgcf_stars > 0 ) : ?>
					<p class="bgcf-testimonial__rating">
						<?php
						// The rating is text for assistive technology, not five
						// identical star icons it would have to read one by one.
						echo esc_html(
							sprintf(
								/* translators: 1: given rating, 2: maximum rating. */
								__( 'Rated %1$s out of %2$s', 'bgcf' ),
								number_format_i18n( min( 5, $bgcf_stars ) ),
								number_format_i18n( 5 )
							)
						);
						?>
					</p>
				<?php endif; ?>

				<figcaption class="bgcf-testimonial__cite">
					<?php
					if ( '' !== $bgcf_photo ) {
						echo $bgcf_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					}
					?>
					<span class="bgcf-testimonial__meta">
						<span class="bgcf-testimonial__name"><?php the_title(); ?></span>
						<?php if ( '' !== $bgcf_role ) : ?>
							<span class="bgcf-testimonial__role"><?php echo esc_html( $bgcf_role ); ?></span>
						<?php endif; ?>
					</span>
				</figcaption>
			</figure>
			<?php
		endwhile;

		wp_reset_postdata();
		?>
	</div>

	<?php if ( $bgcf_is_carousel ) : ?>
		<div class="bgcf-carousel__controls">
			<button type="button" class="bgcf-carousel__btn" data-bgcf-carousel-prev>
				<?php bgcf_svg_icon( 'chevron-left' ); ?>
				<span class="bgcf-u-visually-hidden"><?php esc_html_e( 'Previous testimonial', 'bgcf' ); ?></span>
			</button>

			<?php if ( $bgcf_autoplay ) : ?>
				<button
					type="button"
					class="bgcf-carousel__btn"
					aria-pressed="false"
					aria-label="<?php esc_attr_e( 'Pause the slideshow', 'bgcf' ); ?>"
					data-bgcf-carousel-pause
					hidden
				>
					<?php bgcf_svg_icon( 'minus' ); ?>
				</button>
			<?php endif; ?>

			<button type="button" class="bgcf-carousel__btn" data-bgcf-carousel-next>
				<?php bgcf_svg_icon( 'chevron-right' ); ?>
				<span class="bgcf-u-visually-hidden"><?php esc_html_e( 'Next testimonial', 'bgcf' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
</div>

<?php
bgcf_section_close( $bgcf_section );
