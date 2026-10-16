<?php
/**
 * Block: Timeline — `bgcf/timeline`.
 *
 * Chronological milestones. Primary use is the foundation history on the About
 * page.
 *
 * ACF fields (group: Block — Timeline):
 *   heading (text), lead (textarea), orientation (select vertical|horizontal),
 *   milestones (repeater: year, title, text, image)
 *
 * SEMANTICS: an ordered list, because the order carries meaning — a screen
 * reader announcing "3 of 7" is telling the visitor where they are in the
 * charity's history. The connecting line is decorative and lives in CSS; it is
 * never an element, and the year markers are not icons.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading     = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead        = (string) bgcf_field( 'lead', null, '' );
$bgcf_orientation = (string) bgcf_field( 'orientation', null, 'vertical' );
$bgcf_milestones  = (array) bgcf_field( 'milestones', null, array() );

$bgcf_orientation = 'horizontal' === $bgcf_orientation ? 'horizontal' : 'vertical';
$bgcf_heading_id  = 'bgcf-timeline-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => bgcf_classnames(
		array(
			(string) ( $args['class'] ?? '' ),
			'bgcf-block-timeline--' . $bgcf_orientation,
		)
	),
	'background' => 'base',
	'spacing'    => 'md',
	'width'      => 'default',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_milestones ) ) {
	bgcf_empty_state( __( 'Timeline: add the milestones you want to show in the block settings.', 'bgcf' ) );

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

<ol class="bgcf-timeline" data-bgcf-reveal-stagger>
	<?php foreach ( $bgcf_milestones as $bgcf_milestone ) : ?>
		<?php
		$bgcf_year  = (string) ( $bgcf_milestone['year'] ?? '' );
		$bgcf_title = (string) ( $bgcf_milestone['title'] ?? '' );
		$bgcf_text  = (string) ( $bgcf_milestone['text'] ?? '' );
		$bgcf_image = bgcf_image_id( $bgcf_milestone['image'] ?? 0 );

		if ( '' === $bgcf_title && '' === $bgcf_year ) {
			continue;
		}
		?>
		<li class="bgcf-timeline__item" data-bgcf-reveal>
			<?php if ( '' !== $bgcf_year ) : ?>
				<p class="bgcf-timeline__year"><?php echo esc_html( $bgcf_year ); ?></p>
			<?php endif; ?>

			<div class="bgcf-timeline__body">
				<?php if ( '' !== $bgcf_title ) : ?>
					<h3 class="bgcf-timeline__title"><?php echo esc_html( $bgcf_title ); ?></h3>
				<?php endif; ?>

				<?php if ( '' !== $bgcf_text ) : ?>
					<div class="bgcf-timeline__text"><?php echo wp_kses_post( wpautop( $bgcf_text ) ); ?></div>
				<?php endif; ?>

				<?php
				if ( $bgcf_image > 0 ) {
					printf(
						'<div class="bgcf-timeline__media">%s</div>',
						bgcf_get_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
							array(
								'attachment_id' => $bgcf_image,
								'size'          => 'bgcf-card',
								'class'         => 'bgcf-timeline__image',
								'sizes'         => '(min-width: 768px) 50vw, 100vw',
								'fallback'      => false,
							)
						)
					);
				}
				?>
			</div>
		</li>
	<?php endforeach; ?>
</ol>

<?php
bgcf_section_close( $bgcf_section );
