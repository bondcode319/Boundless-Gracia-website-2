<?php
/**
 * Blog sidebar.
 *
 * The only widget area in the theme. Everything else is composed from blocks.
 * Renders nothing at all when empty, so no template needs to guard the call.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'bgcf-blog-sidebar' ) ) {
	return;
}
?>
<aside class="bgcf-sidebar" id="bgcf-sidebar" aria-label="<?php echo esc_attr( bgcf_get_text( 'sidebar_label', __( 'Blog sidebar', 'bgcf' ) ) ); ?>">
	<div class="bgcf-container bgcf-container--default">
		<?php dynamic_sidebar( 'bgcf-blog-sidebar' ); ?>
	</div>
</aside>
