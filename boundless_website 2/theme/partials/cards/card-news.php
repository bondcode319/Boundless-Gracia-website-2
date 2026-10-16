<?php
/**
 * News card — image on top, warm body, date, title, text link.
 *
 * Distinct from the project card by its body tint and by leading with the date:
 * a news item's value is partly its recency, and burying the date under the
 * title makes a two-year-old post look current.
 *
 * The date is a real `<time datetime>` so the machine-readable value is correct
 * regardless of the site's display format, and `aria-hidden` is NOT used on it:
 * the date is information here, not decoration.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Card arguments. See bgcf_card_defaults().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_args  = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_title = (string) ( $bgcf_args['title'] ?? '' );
$bgcf_url   = (string) ( $bgcf_args['url'] ?? '' );
$bgcf_tag   = in_array( (string) ( $bgcf_args['title_tag'] ?? 'h3' ), array( 'h2', 'h3', 'h4', 'h5' ), true )
	? (string) $bgcf_args['title_tag']
	: 'h3';

$bgcf_image = bgcf_get_post_thumbnail(
	array(
		'attachment_id' => (int) ( $bgcf_args['image'] ?? 0 ),
		'size'          => (string) ( $bgcf_args['image_size'] ?? 'bgcf-card' ),
		'alt'           => (string) ( $bgcf_args['image_alt'] ?? '' ),
		'loading'       => (string) ( $bgcf_args['loading'] ?? 'lazy' ),
		'class'         => 'bgcf-card__image',
		'fallback'      => true,
	)
);

/*
 * `date` and `datetime` arrive through `meta` as a two-key array when the
 * caller mapped a post, and as a plain string when an editor typed one. Both
 * shapes resolve here so the partial has a single code path.
 */
$bgcf_meta     = isset( $bgcf_args['meta'] ) && is_array( $bgcf_args['meta'] ) ? $bgcf_args['meta'] : array();
$bgcf_date     = (string) ( $bgcf_meta['date'] ?? ( is_string( reset( $bgcf_meta ) ) ? reset( $bgcf_meta ) : '' ) );
$bgcf_datetime = (string) ( $bgcf_meta['datetime'] ?? '' );
?>
<article class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--news', (string) ( $bgcf_args['class'] ?? '' ) ) ) ); ?>">

	<?php if ( '' !== $bgcf_image ) : ?>
		<div class="bgcf-card__media">
			<?php echo $bgcf_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="bgcf-card__body">

		<?php
		if ( '' !== $bgcf_date ) {
			printf(
				'<p class="bgcf-card__date"><time%1$s>%2$s</time></p>',
				'' !== $bgcf_datetime ? ' datetime="' . esc_attr( $bgcf_datetime ) . '"' : '',
				esc_html( $bgcf_date )
			);
		}

		printf(
			'<%1$s class="bgcf-card__title">%2$s</%1$s>',
			tag_escape( $bgcf_tag ),
			esc_html( $bgcf_title )
		);
		?>

		<?php if ( '' !== (string) ( $bgcf_args['text'] ?? '' ) ) : ?>
			<p class="bgcf-card__text"><?php echo esc_html( (string) $bgcf_args['text'] ); ?></p>
		<?php endif; ?>

		<?php
		bgcf_text_link(
			array(
				'text'    => '' !== (string) ( $bgcf_args['link_text'] ?? '' )
					? (string) $bgcf_args['link_text']
					: bgcf_get_text( 'card_read_story', __( 'Read the story', 'bgcf' ) ),
				'url'     => $bgcf_url,
				'context' => $bgcf_title,
				'target'  => (string) ( $bgcf_args['target'] ?? '' ),
			)
		);
		?>
	</div>
</article>
