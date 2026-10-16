<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * Contact details beside a working contact form, with the page's own block
 * content above both — so an editor can still write an introduction, add a
 * hero, or drop in an FAQ without touching this file.
 *
 * THE MAP is an iframe, loaded only when the visitor asks for it. An
 * always-embedded Google Map costs ~900 KB, sets third-party cookies before
 * anyone has consented to anything, and is the single heaviest thing on a
 * typical charity contact page. The placeholder shows the address and a button;
 * the embed replaces it on click. Visitors who just want the phone number never
 * pay for a map they did not look at.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$bgcf_section = array(
		'class'      => 'bgcf-contact',
		'background' => 'base',
		'width'      => 'default',
		'labelledby' => 'bgcf-contact-title',
	);

	bgcf_section_open( $bgcf_section );
	?>

	<?php bgcf_breadcrumbs(); ?>

	<header class="bgcf-contact__header">
		<h1 class="bgcf-contact__title" id="bgcf-contact-title"><?php the_title(); ?></h1>
	</header>

	<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
		<div class="bgcf-prose bgcf-contact__intro">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<div class="bgcf-contact__layout">
		<div class="bgcf-contact__form">
			<h2><?php bgcf_the_text( 'contact_form_title', __( 'Send us a message', 'bgcf' ) ); ?></h2>

			<?php
			bgcf_render_partial(
				'global/contact-form',
				array( 'subjects' => (string) bgcf_field( 'contact_subjects', null, '' ) )
			);
			?>
		</div>

		<aside class="bgcf-contact__details" aria-labelledby="bgcf-contact-details-title">
			<h2 id="bgcf-contact-details-title">
				<?php bgcf_the_text( 'contact_details_title', __( 'Other ways to reach us', 'bgcf' ) ); ?>
			</h2>

			<?php
			$bgcf_address = (string) bgcf_option( 'contact_address', '' );
			$bgcf_phone   = (string) bgcf_option( 'contact_phone', '' );
			$bgcf_email   = (string) bgcf_option( 'contact_email', '' );
			$bgcf_hours   = (string) bgcf_option( 'contact_hours', '' );
			$bgcf_map     = (string) bgcf_option( 'contact_map_embed', '' );
			?>

			<ul class="bgcf-contact__list">
				<?php if ( '' !== $bgcf_address ) : ?>
					<li>
						<?php bgcf_svg_icon( 'map-pin' ); ?>
						<address><?php echo wp_kses_post( wpautop( $bgcf_address ) ); ?></address>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $bgcf_phone ) : ?>
					<li>
						<?php bgcf_svg_icon( 'phone' ); ?>
						<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $bgcf_phone ) ); ?>">
							<?php echo esc_html( $bgcf_phone ); ?>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $bgcf_email ) : ?>
					<li>
						<?php bgcf_svg_icon( 'mail' ); ?>
						<a href="<?php echo esc_url( 'mailto:' . $bgcf_email ); ?>"><?php echo esc_html( $bgcf_email ); ?></a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $bgcf_hours ) : ?>
					<li>
						<?php bgcf_svg_icon( 'clock' ); ?>
						<span><?php echo esc_html( $bgcf_hours ); ?></span>
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( '' !== $bgcf_map ) : ?>
				<?php
				/*
				 * Click-to-load. The iframe URL is built here and handed to the
				 * button as a data attribute; no map request is made until the
				 * visitor presses it.
				 */
				$bgcf_map_src = add_query_arg(
					array(
						'q'      => rawurlencode( $bgcf_map ),
						'output' => 'embed',
					),
					'https://www.google.com/maps'
				);
				?>
				<div class="bgcf-map" data-bgcf-map data-bgcf-map-src="<?php echo esc_url( $bgcf_map_src ); ?>">
					<p class="bgcf-map__notice">
						<?php esc_html_e( 'The map is loaded from Google, which may set cookies on your device.', 'bgcf' ); ?>
					</p>

					<?php
					bgcf_button(
						array(
							'text'    => bgcf_get_text( 'contact_map_load', __( 'Show the map', 'bgcf' ) ),
							'variant' => 'secondary',
							'type'    => 'button',
							'icon'    => 'map-pin',
							'attrs'   => array( 'data-bgcf-map-load' => '' ),
						)
					);
					?>
				</div>
			<?php endif; ?>
		</aside>
	</div>

	<?php
	bgcf_section_close( $bgcf_section );

endwhile;

get_footer();
