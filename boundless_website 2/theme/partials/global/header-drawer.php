<?php
/**
 * Mobile navigation drawer.
 *
 * The off-canvas panel the header's menu button controls. Everything a visitor
 * can reach from the desktop header is reachable here: navigation (with the same
 * accessible submenu disclosures), search, the giving CTAs and the contact
 * details, because a phone user who cannot find the donate button is a lost
 * donation.
 *
 * ACCESSIBILITY CONTRACT, enforced by assets/js/src/navigation.js:
 *   - `hidden` until opened, so nothing inside is tabbable while closed. This
 *     is the whole reason the panel is not merely translated off-screen.
 *   - Focus moves to the close button on open and back to the menu button on
 *     close.
 *   - Tab is trapped inside while open; ESC closes; the backdrop closes.
 *   - `aria-modal` + `role="dialog"`: it covers the page on small screens, so it
 *     is a dialog, and announcing it as one is honest.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_phone   = (string) bgcf_option( 'contact_phone', '' );
$bgcf_email   = (string) bgcf_option( 'contact_email', '' );
$bgcf_primary = bgcf_option( 'header_primary_cta', '' );
$bgcf_second  = bgcf_option( 'header_secondary_cta', '' );
?>
<div class="bgcf-drawer-backdrop" data-bgcf-drawer-backdrop hidden></div>

<div
	class="bgcf-drawer"
	id="bgcf-drawer"
	role="dialog"
	aria-modal="true"
	aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_drawer_label', __( 'Site menu', 'bgcf' ) ) ); ?>"
	data-bgcf-drawer
	hidden
>
	<div class="bgcf-drawer__head">
		<p class="bgcf-drawer__title"><?php bgcf_the_text( 'nav_menu_label', __( 'Menu', 'bgcf' ) ); ?></p>

		<button type="button" class="bgcf-drawer__close" data-bgcf-drawer-close>
			<?php bgcf_svg_icon( 'close' ); ?>
			<span class="bgcf-u-visually-hidden"><?php bgcf_the_text( 'nav_menu_close', __( 'Close menu', 'bgcf' ) ); ?></span>
		</button>
	</div>

	<div class="bgcf-drawer__body">

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="bgcf-drawer__nav" aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_primary_label', __( 'Primary', 'bgcf' ) ) ); ?>">
				<?php
				/*
				 * The same tree the desktop mega bar renders, as an accordion.
				 * Two renderings, one source — see inc/mega-menu.php for why a
				 * single list cannot be both shapes.
				 */
				bgcf_mega_menu_drawer( 'primary' );
				?>
			</nav>
		<?php endif; ?>

		<div class="bgcf-drawer__search">
			<?php get_search_form(); ?>
		</div>

		<div class="bgcf-drawer__actions">
			<?php
			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				$bgcf_primary,
				array(
					'variant'    => 'primary',
					'size'       => 'lg',
					'icon'       => 'heart',
					'full_width' => true,
				)
			);

			echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				$bgcf_second,
				array(
					'variant'    => 'secondary',
					'full_width' => true,
				)
			);
			?>
		</div>

		<?php if ( '' !== $bgcf_phone || '' !== $bgcf_email ) : ?>
			<ul class="bgcf-drawer__contact">
				<?php if ( '' !== $bgcf_phone ) : ?>
					<li>
						<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $bgcf_phone ) ); ?>">
							<?php bgcf_svg_icon( 'phone' ); ?>
							<span><?php echo esc_html( $bgcf_phone ); ?></span>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $bgcf_email ) : ?>
					<li>
						<a href="<?php echo esc_url( 'mailto:' . $bgcf_email ); ?>">
							<?php bgcf_svg_icon( 'mail' ); ?>
							<span><?php echo esc_html( $bgcf_email ); ?></span>
						</a>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<?php
		bgcf_language_switcher( 'bgcf-lang--drawer' );

		if ( has_nav_menu( 'social' ) ) :
			?>
			<nav class="bgcf-drawer__social" aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_social_label', __( 'Social profiles', 'bgcf' ) ) ); ?>">
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
			<?php
		endif;
		?>
	</div>
</div>
