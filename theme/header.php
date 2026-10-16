<?php
/**
 * Site header and the opening half of the document.
 *
 * CONTRACT: this file opens `<main id="bgcf-main">`; footer.php closes it. No
 * template may open or close the main landmark itself. That guarantees exactly
 * one `<main>` per document and a skip link that always lands somewhere.
 *
 * STRUCTURE. One navigation list is rendered twice — once for the desktop bar,
 * once inside the mobile drawer — because a single list cannot be both a
 * horizontal bar and an off-canvas accordion without CSS that fights itself.
 * The duplicate is hidden from assistive technology at whichever breakpoint it
 * is not the active navigation (see `_site-header.scss`), so a screen reader
 * never meets the same menu twice.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_has_search = (bool) bgcf_option( 'header_show_search', true );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="bgcf-skip-link" href="#bgcf-main">
	<?php bgcf_the_text( 'skip_to_content', __( 'Skip to main content', 'bgcf' ) ); ?>
</a>

<div class="bgcf-site" id="bgcf-site">

	<?php do_action( 'bgcf_before_header' ); ?>

	<header class="bgcf-site-header" id="bgcf-site-header" data-bgcf-header>
		<div class="bgcf-container bgcf-container--wide bgcf-site-header__inner">

			<div class="bgcf-site-header__branding">
				<?php
				if ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					printf(
						'<p class="bgcf-site-header__title"><a class="bgcf-site-header__home" href="%1$s" rel="home">%2$s</a></p>',
						esc_url( home_url( '/' ) ),
						esc_html( (string) get_bloginfo( 'name', 'display' ) )
					);
				}
				?>
			</div>

			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<nav class="bgcf-site-header__nav" aria-label="<?php echo esc_attr( bgcf_get_text( 'nav_primary_label', __( 'Primary', 'bgcf' ) ) ); ?>" data-bgcf-desktop-nav>
					<?php bgcf_nav_menu( 'primary' ); ?>
				</nav>
			<?php endif; ?>

			<div class="bgcf-site-header__actions">
				<?php
				bgcf_language_switcher( 'bgcf-lang--header' );

				if ( $bgcf_has_search ) :
					?>
					<button
						type="button"
						class="bgcf-site-header__icon-btn"
						aria-expanded="false"
						aria-controls="bgcf-header-search"
						data-bgcf-search-toggle
					>
						<?php bgcf_svg_icon( 'search' ); ?>
						<span class="bgcf-u-visually-hidden" data-bgcf-search-label>
							<?php bgcf_the_text( 'search_toggle', __( 'Search', 'bgcf' ) ); ?>
						</span>
					</button>
					<?php
				endif;

				echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					bgcf_option( 'header_secondary_cta', '' ),
					array(
						'variant' => 'ghost',
						'class'   => 'bgcf-site-header__cta',
					)
				);

				echo bgcf_get_link_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
					bgcf_option( 'header_primary_cta', '' ),
					array(
						'variant' => 'primary',
						'class'   => 'bgcf-site-header__cta',
						'icon'    => 'heart',
					)
				);

				/**
				 * Fires inside the header action area, before the menu button.
				 *
				 * @since 1.0.0
				 */
				do_action( 'bgcf_header_actions' );
				?>

				<button
					type="button"
					class="bgcf-site-header__menu-btn"
					aria-expanded="false"
					aria-controls="bgcf-drawer"
					data-bgcf-drawer-toggle
				>
					<span class="bgcf-site-header__menu-icon" aria-hidden="true">
						<?php
						bgcf_svg_icon( 'menu', array( 'class' => 'bgcf-site-header__menu-open' ) );
						bgcf_svg_icon( 'close', array( 'class' => 'bgcf-site-header__menu-close' ) );
						?>
					</span>
					<span class="bgcf-site-header__menu-label" data-bgcf-drawer-label>
						<?php bgcf_the_text( 'nav_menu_label', __( 'Menu', 'bgcf' ) ); ?>
					</span>
				</button>
			</div>

		</div>

		<?php
		if ( $bgcf_has_search ) {
			bgcf_render_partial( 'global/header-search' );
		}
		?>
	</header>

	<?php
	bgcf_render_partial( 'global/header-drawer' );

	do_action( 'bgcf_after_header' );
	?>

	<main class="bgcf-main" id="bgcf-main" tabindex="-1">
		<?php do_action( 'bgcf_before_main_content' ); ?>
