<?php
/**
 * Split section — media on one side, words on the other.
 *
 * Rendered through `bgcf_split_section()`; see inc/horizon.php for the argument
 * bundle and for the reasoning behind the image-first DOM order.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Section arguments. See bgcf_split_section().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args = isset( $args ) && is_array( $args ) ? $args : array();

$bgcf_ratio = bgcf_split_ratio( (string) ( $bgcf_args['ratio'] ?? 'even' ) );
$bgcf_side  = 'end' === (string) ( $bgcf_args['media_side'] ?? 'start' ) ? 'end' : 'start';
$bgcf_tag   = in_array( (string) ( $bgcf_args['title_tag'] ?? 'h2' ), array( 'h1', 'h2', 'h3' ), true )
	? (string) $bgcf_args['title_tag']
	: 'h2';

// The heading names the section landmark, so it needs a stable id to point
// `aria-labelledby` at. Derived from the section id when there is one and from
// the heading text otherwise.
$bgcf_id       = (string) ( $bgcf_args['id'] ?? '' );
$bgcf_heading_id = 'bgcf-split-' . substr( md5( $bgcf_id . (string) ( $bgcf_args['title'] ?? '' ) ), 0, 8 );

$bgcf_image = bgcf_get_post_thumbnail(
	array(
		'attachment_id' => (int) ( $bgcf_args['image'] ?? 0 ),
		'size'          => (string) ( $bgcf_args['image_size'] ?? 'bgcf-story' ),
		'alt'           => (string) ( $bgcf_args['image_alt'] ?? '' ),
		'loading'       => ! empty( $bgcf_args['priority'] ) ? 'eager' : 'lazy',
		'fetchpriority' => ! empty( $bgcf_args['priority'] ) ? 'high' : 'auto',
		'class'         => 'bgcf-split__image',
		'fallback'      => false,
		/*
		 * A split image occupies half the container at desktop width and all of
		 * it below the stack point. Without this, the browser's default `sizes`
		 * of `100vw` makes it download the 1800px crop to fill a 560px box.
		 */
		'sizes'         => '(min-width: 768px) 48vw, 100vw',
	)
);

$bgcf_section = array(
	'tag'        => 'section',
	'id'         => $bgcf_id,
	'class'      => bgcf_classnames(
		array(
			'bgcf-split',
			'bgcf-split--ratio-' . $bgcf_ratio,
			'bgcf-split--media-' . $bgcf_side,
			'bgcf-split--no-media' => '' === $bgcf_image,
			(string) ( $bgcf_args['class'] ?? '' ),
		)
	),
	'background' => (string) ( $bgcf_args['background'] ?? 'soft' ),
	'spacing'    => (string) ( $bgcf_args['spacing'] ?? 'md' ),
	'width'      => 'default',
	'labelledby' => $bgcf_heading_id,
);

bgcf_section_open( $bgcf_section );
?>

	<div class="bgcf-split__inner">

		<?php
		/*
		 * MEDIA FIRST IN THE DOM, ALWAYS. `bgcf-split--media-end` moves it with
		 * `order` at the stack-up breakpoint. Doing it this way round means the
		 * phone layout needs no override and the keyboard order matches the
		 * visual order in both arrangements.
		 */
		if ( '' !== $bgcf_image ) :
			?>
			<div class="bgcf-split__media">
				<?php echo $bgcf_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
			</div>
		<?php endif; ?>

		<div class="bgcf-split__content">
			<?php
			bgcf_section_heading(
				array(
					'eyebrow' => (string) ( $bgcf_args['eyebrow'] ?? '' ),
					'title'   => (string) ( $bgcf_args['title'] ?? '' ),
					'tag'     => $bgcf_tag,
					'id'      => $bgcf_heading_id,
					'class'   => 'bgcf-split__heading',
				)
			);

			if ( '' !== trim( (string) ( $bgcf_args['text'] ?? '' ) ) ) {
				printf(
					'<div class="bgcf-split__text bgcf-prose">%s</div>',
					wp_kses_post( (string) $bgcf_args['text'] )
				);
			}

			// Cards nested inside a split section: the "2x2 service cards beside
			// a tall image" arrangement from the Horizon blueprint. The headings
			// step down one level from the section heading so the outline stays
			// sequential.
			if ( ! empty( $bgcf_args['cards'] ) && is_array( $bgcf_args['cards'] ) ) {
				$bgcf_cards = array_map(
					static function ( $card ) use ( $bgcf_tag ) {
						if ( ! is_array( $card ) ) {
							return array();
						}

						$card['title_tag'] = 'h2' === $bgcf_tag ? 'h3' : 'h4';

						return $card;
					},
					$bgcf_args['cards']
				);

				bgcf_card_grid(
					(string) ( $bgcf_args['card_type'] ?? 'service' ),
					$bgcf_cards,
					array(
						'columns' => (int) ( $bgcf_args['card_cols'] ?? 2 ),
						'class'   => 'bgcf-split__cards',
					)
				);
			}

			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				$bgcf_args['link'] ?? '',
				array(
					'variant' => 'secondary' === (string) ( $bgcf_args['link_style'] ?? 'primary' ) ? 'secondary' : 'primary',
					'class'   => 'bgcf-split__cta',
				)
			);
			?>
		</div>
	</div>

<?php
bgcf_section_close( $bgcf_section );
