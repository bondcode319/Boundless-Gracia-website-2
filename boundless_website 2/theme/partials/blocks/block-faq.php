<?php
/**
 * Block: FAQ — `bgcf/faq`.
 *
 * Accordion of questions and answers, and the data source for FAQPage JSON-LD:
 * the schema builder in inc/seo-schema.php reads the same items this partial
 * collects, through `bgcf_collect_faq_items()`, so the markup and the structured
 * data can never disagree. A rich result that promises an answer the page does
 * not contain is a manual action waiting to happen.
 *
 * ACF fields (group: Block — FAQ):
 *   heading (text), lead (textarea), items (repeater: question text,
 *   answer wysiwyg), default_open (number, 0 for none), single (true/false),
 *   schema (true/false)
 *
 * ACCESSIBILITY: a `<button aria-expanded>` inside each heading — not a
 * clickable div, and not a button wrapping a heading. The panel stays in the
 * DOM and is hidden with the `hidden` attribute, so browser find-in-page can
 * still reach it after it is expanded and the content is never removed from the
 * accessibility tree by CSS alone.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Render context from bgcf_render_block().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = (string) bgcf_field( 'heading', null, '' );
$bgcf_lead    = (string) bgcf_field( 'lead', null, '' );
$bgcf_items   = (array) bgcf_field( 'items', null, array() );
$bgcf_open    = (int) bgcf_field( 'default_open', null, 0 );
$bgcf_single  = (bool) bgcf_field( 'single', null, false );
$bgcf_schema  = (bool) bgcf_field( 'schema', null, true );

$bgcf_heading_id = 'bgcf-faq-title-' . (string) wp_unique_id();

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => (string) ( $args['anchor'] ?? '' ),
	'class'      => (string) ( $args['class'] ?? '' ),
	'background' => 'base',
	'spacing'    => 'md',
	'width'      => 'narrow',
	'labelledby' => '' !== $bgcf_heading ? $bgcf_heading_id : '',
);

bgcf_section_open( $bgcf_section );

if ( empty( $bgcf_items ) ) {
	bgcf_empty_state( __( 'FAQ: add questions and answers in the block settings.', 'bgcf' ) );

	bgcf_section_close( $bgcf_section );

	return;
}

bgcf_section_heading(
	array(
		'title' => $bgcf_heading,
		'lead'  => $bgcf_lead,
		'id'    => $bgcf_heading_id,
	)
);

$bgcf_collected = array();
$bgcf_index     = 0;
?>

<div
	class="bgcf-faq"
	data-bgcf-accordion
	<?php echo $bgcf_single ? 'data-bgcf-accordion-single' : ''; ?>
>
	<?php foreach ( $bgcf_items as $bgcf_item ) : ?>
		<?php
		$bgcf_question = (string) ( $bgcf_item['question'] ?? '' );
		$bgcf_answer   = (string) ( $bgcf_item['answer'] ?? '' );

		if ( '' === $bgcf_question || '' === $bgcf_answer ) {
			continue;
		}

		++$bgcf_index;

		$bgcf_collected[] = array(
			'question' => $bgcf_question,
			'answer'   => $bgcf_answer,
		);

		$bgcf_slug     = sanitize_title( $bgcf_question );
		$bgcf_panel_id = 'bgcf-faq-answer-' . $bgcf_slug;
		$bgcf_btn_id   = 'bgcf-faq-question-' . $bgcf_slug;
		$bgcf_expanded = $bgcf_open === $bgcf_index;
		?>
		<div class="bgcf-faq__item" id="<?php echo esc_attr( $bgcf_btn_id ); ?>" data-bgcf-accordion-item>
			<h3 class="bgcf-faq__heading">
				<button
					type="button"
					class="bgcf-faq__trigger"
					aria-expanded="<?php echo $bgcf_expanded ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $bgcf_panel_id ); ?>"
					data-bgcf-accordion-toggle
				>
					<span class="bgcf-faq__question"><?php echo esc_html( $bgcf_question ); ?></span>
					<?php bgcf_svg_icon( 'chevron-down', array( 'class' => 'bgcf-faq__icon' ) ); ?>
				</button>
			</h3>

			<div
				class="bgcf-faq__panel"
				id="<?php echo esc_attr( $bgcf_panel_id ); ?>"
				role="region"
				aria-labelledby="<?php echo esc_attr( $bgcf_btn_id ); ?>"
				<?php echo $bgcf_expanded ? '' : 'hidden'; ?>
			>
				<div class="bgcf-prose"><?php echo wp_kses_post( $bgcf_answer ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<?php
/*
 * Hand the items to the schema builder. Collected after rendering so only
 * questions that actually appeared on the page can be described in the JSON-LD.
 */
if ( $bgcf_schema && ! empty( $bgcf_collected ) && function_exists( 'bgcf_register_faq_items' ) ) {
	bgcf_register_faq_items( $bgcf_collected );
}

bgcf_section_close( $bgcf_section );
