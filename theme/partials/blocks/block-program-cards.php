<?php
/**
 * Block: Program Cards — `bgcf/program-cards`.
 *
 * Queries the Programs post type. Three selection modes so editors can either
 * curate an order, filter by category, or let the newest surface automatically.
 *
 * ACF fields (group: Block — Program Cards):
 *   heading (text), lead (textarea), source (select latest|manual|category),
 *   programs (relationship -> bgcf_program), category (taxonomy ->
 *   bgcf_program_category), limit (number, default 3), columns (select 2|3|4),
 *   show_excerpt (true/false), archive_cta (link)
 *
 * QUERY RULES, enforced below and worth keeping: always `no_found_rows`
 * (nothing here paginates, so counting rows is wasted work), always
 * `post_status => publish` (a draft programme must not leak onto the homepage),
 * and never `posts_per_page => -1` — an editor who adds 400 programmes should
 * not be able to take the homepage down.
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
$bgcf_limit    = (int) bgcf_field( 'limit', null, 3 );
$bgcf_columns  = (int) bgcf_field( 'columns', null, 3 );
$bgcf_cta      = bgcf_field( 'archive_cta', null, array() );
$bgcf_manual   = (array) bgcf_field( 'programs', null, array() );
$bgcf_category = bgcf_field( 'category', null, 0 );

$bgcf_limit   = max( 1, min( 12, $bgcf_limit ) );
$bgcf_columns = in_array( $bgcf_columns, array( 2, 3, 4 ), true ) ? $bgcf_columns : 3;

$bgcf_heading_id = 'bgcf-programs-title-' . (string) wp_unique_id();

$bgcf_query_args = array(
	'post_type'              => 'bgcf_program',
	'post_status'            => 'publish',
	'posts_per_page'         => $bgcf_limit,
	'no_found_rows'          => true,
	'ignore_sticky_posts'    => true,
	'update_post_term_cache' => false,
);

if ( 'manual' === $bgcf_source && ! empty( $bgcf_manual ) ) {
	// ACF Relationship returns posts, IDs or arrays depending on configuration.
	$bgcf_ids = array_values(
		array_filter(
			array_map(
				static function ( $item ) {
					if ( $item instanceof WP_Post ) {
						return (int) $item->ID;
					}

					if ( is_array( $item ) ) {
						return (int) ( $item['ID'] ?? 0 );
					}

					return absint( $item );
				},
				$bgcf_manual
			)
		)
	);

	$bgcf_query_args['post__in']       = $bgcf_ids;
	$bgcf_query_args['orderby']        = 'post__in';
	$bgcf_query_args['posts_per_page'] = min( count( $bgcf_ids ), 12 );
} elseif ( 'category' === $bgcf_source ) {
	$bgcf_term = $bgcf_category instanceof WP_Term ? (int) $bgcf_category->term_id : absint( is_array( $bgcf_category ) ? ( $bgcf_category['term_id'] ?? 0 ) : $bgcf_category );

	if ( $bgcf_term > 0 ) {
		$bgcf_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Editor-chosen filter; this is the feature.
			array(
				'taxonomy' => 'bgcf_program_category',
				'field'    => 'term_id',
				'terms'    => $bgcf_term,
			),
		);
	}
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

$bgcf_programs = new WP_Query( $bgcf_query_args );

if ( ! $bgcf_programs->have_posts() ) {
	bgcf_empty_state( __( 'Program cards: publish a programme, or choose which programmes to show in the block settings.', 'bgcf' ) );

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

<div class="bgcf-grid bgcf-grid--cols-<?php echo esc_attr( (string) $bgcf_columns ); ?>" data-bgcf-reveal-stagger>
	<?php
	while ( $bgcf_programs->have_posts() ) :
		$bgcf_programs->the_post();

		bgcf_render_partial(
			'content/content',
			array(
				'context' => 'block',
				'heading' => 'h3',
				'meta'    => false,
				'reveal'  => true,
				'cta'     => bgcf_get_text( 'program_card_cta', __( 'Learn more', 'bgcf' ) ),
			)
		);
	endwhile;

	wp_reset_postdata();
	?>
</div>

<?php
$bgcf_archive_button = bgcf_get_link_button(
	$bgcf_cta,
	array(
		'variant' => 'secondary',
		'icon'    => 'arrow-right',
	)
);

if ( '' !== $bgcf_archive_button ) {
	printf(
		'<div class="bgcf-section__footer">%s</div>',
		$bgcf_archive_button // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
	);
}

bgcf_section_close( $bgcf_section );
