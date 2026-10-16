<?php
/**
 * Archive card — the default listing item for any post type.
 *
 * The whole card is not a link: the title is, which keeps the accessible name
 * meaningful and lets the image stay decorative. A "card-wide" anchor produces
 * one enormous link whose accessible name is the title, the date, the excerpt
 * and the word "image" — which is why this does not do that.
 *
 * The heading level is an argument, not a constant: this partial is used on
 * archives (where it sits under an h1, so h2 is right) and inside block
 * sections that already have their own h2 (where it must be h3). A fixed level
 * breaks the document outline in one of the two.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args {
 *     @type string $context Where the card renders: 'archive' (default), 'related', 'search', 'block'.
 *     @type string $size    Image size. Default 'bgcf-card'.
 *     @type string $heading Heading level, 'h2' (default) or 'h3'.
 *     @type bool   $meta    Show the date/reading-time row. Default true.
 *     @type bool   $reveal  Add the scroll-reveal hook. Default false.
 *     @type string $cta     Optional "read more" label rendered under the excerpt.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_context = isset( $args['context'] ) ? sanitize_html_class( (string) $args['context'] ) : 'archive';
$bgcf_size    = isset( $args['size'] ) ? (string) $args['size'] : 'bgcf-card';
$bgcf_heading = isset( $args['heading'] ) && in_array( $args['heading'], array( 'h2', 'h3', 'h4' ), true ) ? (string) $args['heading'] : 'h2';
$bgcf_meta    = ! isset( $args['meta'] ) || (bool) $args['meta'];
$bgcf_reveal  = ! empty( $args['reveal'] );
$bgcf_cta     = isset( $args['cta'] ) ? (string) $args['cta'] : '';
?>
<article <?php post_class( 'bgcf-card bgcf-card--' . $bgcf_context ); ?><?php echo $bgcf_reveal ? ' data-bgcf-reveal' : ''; ?>>

	<?php
	$bgcf_image = bgcf_get_post_thumbnail(
		array(
			'size'  => $bgcf_size,
			'class' => 'bgcf-card__image',
			'link'  => true,
			'sizes' => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
		)
	);

	if ( '' !== $bgcf_image ) {
		printf( '<div class="bgcf-card__media">%s</div>', $bgcf_image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
	}
	?>

	<div class="bgcf-card__body">

		<?php
		printf(
			'<%1$s class="bgcf-card__title"><a class="bgcf-card__link" href="%2$s">%3$s</a></%1$s>',
			tag_escape( $bgcf_heading ),
			esc_url( (string) get_permalink() ),
			esc_html( (string) get_the_title() )
		);
		?>

		<?php if ( $bgcf_meta ) : ?>
			<div class="bgcf-entry-meta bgcf-card__meta">
				<?php
				bgcf_posted_on();

				if ( 'post' === get_post_type() ) {
					bgcf_reading_time();
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( has_excerpt() || 'post' === get_post_type() ) : ?>
			<div class="bgcf-card__excerpt">
				<?php the_excerpt(); ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $bgcf_cta ) : ?>
			<p class="bgcf-card__cta">
				<a class="bgcf-link" href="<?php the_permalink(); ?>">
					<span><?php echo esc_html( $bgcf_cta ); ?></span>
					<?php bgcf_svg_icon( 'arrow-right' ); ?>
					<span class="bgcf-u-visually-hidden">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: post title, so each "read more" link has a unique accessible name. */
								__( 'about %s', 'bgcf' ),
								wp_strip_all_tags( (string) get_the_title() )
							)
						);
						?>
					</span>
				</a>
			</p>
		<?php endif; ?>

	</div>

</article>
