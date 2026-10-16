<?php
/**
 * Events archive — upcoming first, past below.
 *
 * WHY A DEDICATED TEMPLATE. The default archive orders by publish date, which
 * for events is meaningless: an event published last week may be eight months
 * away, and one published a year ago may be tomorrow. Worse, an archive that
 * leads with a finished event tells every visitor the charity is dormant.
 *
 * So this runs two queries against the `start_date` field: upcoming ascending
 * (soonest first — the thing a visitor can still act on), then past descending
 * (most recent first). Both are paginated by the same `paged` query var, and the
 * past list is deliberately capped rather than paginated, because nobody browses
 * to page nine of a charity's finished events.
 *
 * The `start_date` meta is compared as DATETIME, not as a string, so ordering is
 * correct across year boundaries.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$bgcf_section = array(
	'class'      => 'bgcf-archive bgcf-archive--events',
	'background' => 'base',
	'width'      => 'wide',
	'labelledby' => 'bgcf-archive-title',
);

bgcf_section_open( $bgcf_section );

$bgcf_now = (string) current_time( 'Y-m-d H:i:s' );

/**
 * Build one half of the split.
 *
 * @param string $comparison '>=' for upcoming, '<' for past.
 * @param string $order      'ASC' or 'DESC'.
 * @param int    $per_page   Items to return.
 * @param string $now        Current MySQL datetime.
 * @return WP_Query The query.
 */
$bgcf_event_query = static function ( string $comparison, string $order, int $per_page, string $now ): WP_Query {
	return new WP_Query(
		array(
			'post_type'      => 'bgcf_event',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			/*
			 * ACF stores a field's value under its bare name (`start_date`), not
			 * under a prefixed key — the `_start_date` row beside it holds the
			 * field key, not the date. Querying the prefixed name returns an
			 * empty archive, silently.
			 */
			'meta_key'       => 'start_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ordering an event list by its date is the whole point.
			'orderby'        => 'meta_value',
			'order'          => $order,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- As above.
				array(
					'key'     => 'start_date',
					'value'   => $now,
					'compare' => $comparison,
					'type'    => 'DATETIME',
				),
			),
		)
	);
};
?>

	<?php bgcf_breadcrumbs(); ?>

	<header class="bgcf-archive__header">
		<h1 class="bgcf-archive__title" id="bgcf-archive-title">
			<?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?>
		</h1>

		<?php
		$bgcf_description = get_the_archive_description();

		if ( '' !== trim( (string) $bgcf_description ) ) {
			printf( '<div class="bgcf-archive__description">%s</div>', wp_kses_post( $bgcf_description ) );
		}
		?>
	</header>

	<?php
	$bgcf_upcoming = $bgcf_event_query( '>=', 'ASC', 12, $bgcf_now );
	$bgcf_past     = $bgcf_event_query( '<', 'DESC', 6, $bgcf_now );
	?>

	<section class="bgcf-archive__group" aria-labelledby="bgcf-events-upcoming">
		<h2 class="bgcf-archive__group-title" id="bgcf-events-upcoming">
			<?php bgcf_the_text( 'events_upcoming_title', __( 'Coming up', 'bgcf' ) ); ?>
		</h2>

		<?php if ( $bgcf_upcoming->have_posts() ) : ?>
			<div class="bgcf-grid bgcf-grid--cols-3" data-bgcf-reveal-stagger>
				<?php
				while ( $bgcf_upcoming->have_posts() ) :
					$bgcf_upcoming->the_post();

					bgcf_render_partial(
						'content/content-event',
						array(
							'heading' => 'h3',
							'reveal'  => true,
						)
					);
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<p class="bgcf-archive__empty">
				<?php bgcf_the_text( 'events_none_upcoming', __( 'Nothing is scheduled just now. Our newsletter is the first place new dates appear.', 'bgcf' ) ); ?>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( $bgcf_past->have_posts() ) : ?>
		<section class="bgcf-archive__group bgcf-archive__group--past" aria-labelledby="bgcf-events-past">
			<h2 class="bgcf-archive__group-title" id="bgcf-events-past">
				<?php bgcf_the_text( 'events_past_title', __( 'Recently held', 'bgcf' ) ); ?>
			</h2>

			<div class="bgcf-grid bgcf-grid--cols-3">
				<?php
				while ( $bgcf_past->have_posts() ) :
					$bgcf_past->the_post();

					bgcf_render_partial(
						'content/content-event',
						array(
							'heading' => 'h3',
							'past'    => true,
						)
					);
				endwhile;

				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

<?php
bgcf_section_close( $bgcf_section );

get_footer();
