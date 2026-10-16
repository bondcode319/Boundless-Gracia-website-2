<?php
/**
 * Search form.
 *
 * Overrides the core form so the label, placeholder and button copy come from
 * bgcf_get_text() and the classes match the design system. The label is always
 * present in the DOM; hiding it visually is the stylesheet's job, not the
 * markup's.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$bgcf_search_id = 'bgcf-search-field-' . wp_unique_id();
?>
<form role="search" method="get" class="bgcf-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="bgcf-search-form__label" for="<?php echo esc_attr( $bgcf_search_id ); ?>">
		<?php bgcf_the_text( 'search_label', __( 'Search this site', 'bgcf' ) ); ?>
	</label>

	<input
		type="search"
		class="bgcf-search-form__input"
		id="<?php echo esc_attr( $bgcf_search_id ); ?>"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php echo esc_attr( bgcf_get_text( 'search_placeholder', __( 'Search…', 'bgcf' ) ) ); ?>"
		autocomplete="off"
	/>

	<button type="submit" class="bgcf-btn bgcf-btn--primary bgcf-btn--md bgcf-search-form__submit">
		<span class="bgcf-btn__label"><?php bgcf_the_text( 'search_submit', __( 'Search', 'bgcf' ) ); ?></span>
	</button>
</form>
