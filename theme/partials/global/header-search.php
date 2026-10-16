<?php
/**
 * Header search panel.
 *
 * A disclosure, not an overlay: the panel expands below the header bar and
 * pushes nothing, so the page does not reflow under the visitor's cursor.
 *
 * `hidden` in the closed state keeps the input out of the tab order — a search
 * field a keyboard user can focus but not see is worse than no search at all.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bgcf-site-header__search" id="bgcf-header-search" data-bgcf-search-panel hidden>
	<div class="bgcf-container bgcf-container--wide">
		<?php get_search_form(); ?>
	</div>
</div>
