<?php
/**
 * Event card.
 *
 * Used by archive-bgcf_event.php and anywhere events are listed.
 *
 * THE DATE IS A `<time datetime>`, with a machine-readable attribute and a
 * human-readable body. That is not pedantry: it is what lets a browser, an
 * assistive technology or a crawler understand "Sat 14 Mar" as a date, and it
 * is the same value the Event structured data publishes.
 *
 * A finished event keeps its card but says so, and loses its registration
 * button — a live "Register" link on a past event is a support email.
 *
 * @package Bgcf
 * @since   1.2.0
 *
 * @var array<string, mixed> $args {
 *     @type string $heading Heading level. Default 'h3'.
 *     @type bool   $past    Whether the event has already happened.
 *     @type bool   $reveal  Add the scroll-reveal hook.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_heading = isset( $args['heading'] ) && in_array( $args['heading'], array( 'h2', 'h3', 'h4' ), true ) ? (string) $args['heading'] : 'h3';
$bgcf_past    = ! empty( $args['past'] );
$bgcf_reveal  = ! empty( $args['reveal'] );

$bgcf_start = (string) bgcf_field( 'start_date', get_the_ID(), '' );
$bgcf_end   = (string) bgcf_field( 'end_date', get_the_ID(), '' );
$bgcf_venue = (string) bgcf_field( 'venue', get_the_ID(), '' );
$bgcf_url   = (string) bgcf_field( 'register_url', get_the_ID(), '' );
$bgcf_free  = (bool) bgcf_field( 'is_free', get_the_ID(), true );

$bgcf_start_ts = '' !== $bgcf_start ? strtotime( $bgcf_start ) : false;
$bgcf_end_ts   = '' !== $bgcf_end ? strtotime( $bgcf_end ) : false;
?>
<article <?php post_class( bgcf_classnames( array( 'bgcf-card', 'bgcf-card--event', 'bgcf-card--past' => $bgcf_past ) ) ); ?><?php echo $bgcf_reveal ? ' data-bgcf-reveal' : ''; ?>>

	<?php
	$bgcf_image = bgcf_get_post_thumbnail(
		array(
			'size'  => 'bgcf-card',
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

		<?php if ( false !== $bgcf_start_ts ) : ?>
			<p class="bgcf-card__date">
				<?php bgcf_svg_icon( 'calendar' ); ?>
				<time datetime="<?php echo esc_attr( (string) wp_date( DATE_W3C, $bgcf_start_ts ) ); ?>">
					<?php echo esc_html( (string) wp_date( (string) get_option( 'date_format' ), $bgcf_start_ts ) ); ?>
				</time>

				<?php
				// Only show the end date when it differs: "14 Mar – 14 Mar" is
				// noise, and an end time on a one-hour event belongs on the page.
				if ( false !== $bgcf_end_ts && wp_date( 'Y-m-d', $bgcf_end_ts ) !== wp_date( 'Y-m-d', $bgcf_start_ts ) ) :
					?>
					<span aria-hidden="true">–</span>
					<time datetime="<?php echo esc_attr( (string) wp_date( DATE_W3C, $bgcf_end_ts ) ); ?>">
						<?php echo esc_html( (string) wp_date( (string) get_option( 'date_format' ), $bgcf_end_ts ) ); ?>
					</time>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php
		printf(
			'<%1$s class="bgcf-card__title"><a class="bgcf-card__link" href="%2$s">%3$s</a></%1$s>',
			tag_escape( $bgcf_heading ),
			esc_url( (string) get_permalink() ),
			esc_html( (string) get_the_title() )
		);
		?>

		<?php if ( '' !== $bgcf_venue ) : ?>
			<p class="bgcf-card__meta">
				<?php bgcf_svg_icon( 'map-pin' ); ?>
				<span><?php echo esc_html( $bgcf_venue ); ?></span>
			</p>
		<?php endif; ?>

		<?php if ( has_excerpt() ) : ?>
			<div class="bgcf-card__excerpt"><?php the_excerpt(); ?></div>
		<?php endif; ?>

		<?php if ( $bgcf_past ) : ?>
			<p class="bgcf-card__badge"><?php bgcf_the_text( 'event_past_badge', __( 'This event has finished', 'bgcf' ) ); ?></p>
		<?php elseif ( '' !== $bgcf_url ) : ?>
			<?php
			bgcf_button(
				array(
					'text'       => $bgcf_free
						? bgcf_get_text( 'event_register_free', __( 'Register free', 'bgcf' ) )
						: bgcf_get_text( 'event_register', __( 'Get tickets', 'bgcf' ) ),
					'url'        => $bgcf_url,
					'variant'    => 'secondary',
					'size'       => 'sm',
					// Each card's button needs a unique accessible name, or a
					// screen-reader user hears "Register free" eight times with no
					// way to tell which event is which.
					'aria_label' => sprintf(
						/* translators: %s: event title. */
						__( 'Register for %s', 'bgcf' ),
						wp_strip_all_tags( (string) get_the_title() )
					),
				)
			);
			?>
		<?php endif; ?>

	</div>

</article>
