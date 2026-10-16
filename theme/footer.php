<?php
/**
 * Site footer and the closing half of the document.
 *
 * CONTRACT: this file closes the `<main>` element opened in header.php.
 *
 * Phase 2 (theme-developer) builds the four-column footer — quick links,
 * programs, contact details, newsletter and social profiles — entirely from the
 * Footer options page. The link columns below are menu-driven placeholders with
 * the correct landmarks and heading levels already in place.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_footer_menus = array(
	'footer_quick'    => bgcf_get_text( 'footer_quick_title', __( 'Quick links', 'bgcf' ) ),
	'footer_programs' => bgcf_get_text( 'footer_programs_title', __( 'Our programs', 'bgcf' ) ),
);
?>
		<?php do_action( 'bgcf_after_main_content' ); ?>
	</main><!-- #bgcf-main -->

	<?php do_action( 'bgcf_before_footer' ); ?>

	<footer class="bgcf-site-footer" id="bgcf-site-footer">
		<div class="bgcf-container bgcf-container--wide bgcf-site-footer__inner">

			<div class="bgcf-site-footer__brand">
				<?php
				printf(
					'<p class="bgcf-site-footer__title"><a class="bgcf-site-footer__home" href="%1$s" rel="home">%2$s</a></p>',
					esc_url( home_url( '/' ) ),
					esc_html( (string) get_bloginfo( 'name', 'display' ) )
				);

				$bgcf_footer_intro = (string) bgcf_option( 'footer_intro', '' );

				if ( '' !== $bgcf_footer_intro ) {
					printf( '<div class="bgcf-site-footer__intro">%s</div>', wp_kses_post( $bgcf_footer_intro ) );
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
				 * The address, phone, email and newsletter sign-up are rendered
				 * by partials/global/footer-contact.php above; this hook is for
				 * anything a plugin or child theme wants to add after them.
				 *
				 * @since 1.0.0
				 */
				do_action( 'bgcf_footer_contact' );
				?>
			</div>

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

				<?php if ( has_nav_menu( 'social' ) ) : ?>
					<nav class="bgcf-site-footer__social" aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_social_label', __( 'Social profiles', 'bgcf' ) ) ); ?>">
						<?php
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
