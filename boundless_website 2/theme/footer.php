<?php
/**
 * Site footer and the closing half of the document.
 *
 * CONTRACT: this file closes the `<main>` element opened in header.php.
 *
 * THE HORIZON FOOTER has two halves and a bar:
 *
 *   - Left half: the brand mark, a one-paragraph statement of what the
 *     organisation does, the social row, and the contact lines.
 *   - Right half: the subscribe card — a light card on the dark band, so the
 *     one form in the footer is also the one bright object in it.
 *   - Under both: the accreditation badges, a brand rule, and the bar carrying
 *     the copyright, the registration number and the policy links.
 *
 * WHY THE SUBSCRIBE CARD IS A LIGHT CARD. A form on a deep-blue band has to
 * either invert its inputs — white-on-blue fields, which read as disabled and
 * fail contrast on their placeholder text — or sit on its own light surface.
 * The second is the honest option and it also gives the footer its focal point.
 *
 * Every string and link in here comes from Site Options → Footer. Two columns
 * are menu-driven (Quick links, Our programs) and render only when the matching
 * menu location has a menu assigned, so an unconfigured site shows a narrower
 * footer rather than empty headings.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_footer_menus = array(
	'footer_quick'    => bgcf_get_text( 'footer_quick_title', __( 'Quick links', 'bgcf' ) ),
	'footer_programs' => bgcf_get_text( 'footer_programs_title', __( 'Our programs', 'bgcf' ) ),
);

$bgcf_footer_intro = (string) bgcf_option( 'footer_intro', '' );
$bgcf_registration = (string) bgcf_option( 'footer_registration', '' );
$bgcf_badges       = bgcf_option( 'footer_badges', array() );
?>
		<?php do_action( 'bgcf_after_main_content' ); ?>
	</main><!-- #bgcf-main -->

	<?php do_action( 'bgcf_before_footer' ); ?>

	<footer class="bgcf-site-footer bgcf-section--bg-ink-deep" id="bgcf-site-footer">
		<div class="bgcf-container bgcf-container--wide">

			<div class="bgcf-site-footer__top">

				<div class="bgcf-site-footer__brand">
					<?php
					printf(
						'<p class="bgcf-site-footer__title"><a class="bgcf-site-footer__home" href="%1$s" rel="home">%2$s</a></p>',
						esc_url( home_url( '/' ) ),
						esc_html( (string) get_bloginfo( 'name', 'display' ) )
					);

					if ( '' !== $bgcf_footer_intro ) {
						printf(
							'<div class="bgcf-site-footer__intro">%s</div>',
							wp_kses_post( $bgcf_footer_intro )
						);
					}

					if ( has_nav_menu( 'social' ) ) {
						printf(
							'<nav class="bgcf-site-footer__social" aria-label="%s">',
							esc_attr( bgcf_get_text( 'nav_social_label', __( 'Social profiles', 'bgcf' ) ) )
						);

						wp_nav_menu(
							array(
								'theme_location' => 'social',
								'container'      => false,
								'menu_class'     => 'bgcf-nav bgcf-nav--social',
								'depth'          => 1,
								'link_before'    => '<span class="bgcf-nav__label">',
								'link_after'     => '</span>',
								'fallback_cb'    => false,
							)
						);

						echo '</nav>';
					}
					?>
				</div>

				<?php foreach ( $bgcf_footer_menus as $bgcf_location => $bgcf_title ) : ?>
					<?php if ( has_nav_menu( $bgcf_location ) ) : ?>
						<nav class="bgcf-site-footer__column" aria-label="<?php echo esc_attr( $bgcf_title ); ?>">
							<h2 class="bgcf-site-footer__heading"><?php echo esc_html( $bgcf_title ); ?></h2>
							<?php
							wp_nav_menu(
								array(
									'theme_location' => $bgcf_location,
									'container'      => false,
									'menu_class'     => 'bgcf-nav bgcf-nav--footer',
									'depth'          => 1,
									'fallback_cb'    => false,
								)
							);
							?>
						</nav>
					<?php endif; ?>
				<?php endforeach; ?>

				<div class="bgcf-site-footer__column bgcf-site-footer__column--contact">
					<?php
					bgcf_render_partial( 'global/footer-contact' );

					/**
					 * Fires at the end of the footer contact column.
					 *
					 * @since 1.0.0
					 */
					do_action( 'bgcf_footer_contact' );
					?>
				</div>

				<?php bgcf_render_partial( 'global/subscribe-card' ); ?>

			</div>

			<?php
			/*
			 * Accreditation badges. These are the one place on the site where a
			 * third party's logo appears, so they are never recoloured and never
			 * cropped: each is rendered at its own aspect ratio inside a fixed
			 * height. The alt text is the body's name, because a badge a visitor
			 * cannot read is a trust signal that does not signal anything.
			 */
			if ( ! empty( $bgcf_badges ) && is_array( $bgcf_badges ) ) :
				?>
				<ul class="bgcf-site-footer__badges" aria-label="<?php echo esc_attr( bgcf_get_text( 'footer_badges_label', __( 'Accreditations and memberships', 'bgcf' ) ) ); ?>">
					<?php
					foreach ( $bgcf_badges as $bgcf_badge ) {
						$bgcf_badge_id   = bgcf_image_id( $bgcf_badge['image'] ?? 0 );
						$bgcf_badge_name = (string) ( $bgcf_badge['name'] ?? '' );

						if ( ! $bgcf_badge_id ) {
							continue;
						}

						$bgcf_badge_img = bgcf_get_post_thumbnail(
							array(
								'attachment_id' => $bgcf_badge_id,
								'size'          => 'bgcf-partner-logo',
								'alt'           => $bgcf_badge_name,
								'class'         => 'bgcf-site-footer__badge-image',
								'fallback'      => false,
							)
						);

						if ( '' === $bgcf_badge_img ) {
							continue;
						}

						$bgcf_badge_url = (string) ( $bgcf_badge['url'] ?? '' );

						echo '<li class="bgcf-site-footer__badge">';

						if ( '' !== $bgcf_badge_url ) {
							printf(
								'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
								esc_url( $bgcf_badge_url ),
								$bgcf_badge_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
							);
						} else {
							echo $bgcf_badge_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
						}

						echo '</li>';
					}
					?>
				</ul>
				<?php
			endif;
			?>

			<hr class="bgcf-site-footer__rule" />

		</div>

		<div class="bgcf-site-footer__bar">
			<div class="bgcf-container bgcf-container--wide bgcf-site-footer__bar-inner">

				<p class="bgcf-site-footer__copyright">
					<?php
					echo esc_html(
						bgcf_get_text(
							'copyright',
							sprintf(
								/* translators: 1: current year, 2: site name. */
								__( '© %1$s %2$s. All rights reserved.', 'bgcf' ),
								(string) wp_date( 'Y' ),
								(string) get_bloginfo( 'name', 'display' )
							)
						)
					);
					?>
				</p>

				<?php if ( '' !== $bgcf_registration ) : ?>
					<p class="bgcf-site-footer__registration"><?php echo esc_html( $bgcf_registration ); ?></p>
				<?php endif; ?>

				<?php if ( has_nav_menu( 'legal' ) ) : ?>
					<nav class="bgcf-site-footer__legal" aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_legal_label', __( 'Legal', 'bgcf' ) ) ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'legal',
								'container'      => false,
								'menu_class'     => 'bgcf-nav bgcf-nav--legal',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>

			</div>
		</div>
	</footer>

	<?php do_action( 'bgcf_after_footer' ); ?>

</div><!-- #bgcf-site -->

<?php wp_footer(); ?>
</body>
</html>
