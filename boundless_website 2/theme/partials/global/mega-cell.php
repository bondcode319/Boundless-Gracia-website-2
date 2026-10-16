<?php
/**
 * One cell of a mega panel: round icon, title, one-line description.
 *
 * THE WHOLE CELL IS THE LINK, and here that is correct — unlike on a card. A
 * panel cell has no selectable prose and no secondary action, so its accessible
 * name is "Title. Description." and nothing is lost by making the anchor wrap
 * both. The icon is decorative and excluded from the name.
 *
 * @package Bgcf
 * @since   2.0.0
 *
 * @var array<string, mixed> $args Cell data from bgcf_mega_panel_cells().
 */

defined( 'ABSPATH' ) || exit;

$bgcf_cell  = isset( $args ) && is_array( $args ) ? $args : array();
$bgcf_title = trim( (string) ( $bgcf_cell['title'] ?? '' ) );
$bgcf_url   = trim( (string) ( $bgcf_cell['url'] ?? '' ) );

if ( '' === $bgcf_title || '' === $bgcf_url ) {
	return;
}

$bgcf_description = trim( (string) ( $bgcf_cell['description'] ?? '' ) );
$bgcf_is_current  = ! empty( $bgcf_cell['current'] );
?>
<li class="bgcf-mega__cell">
	<a
		class="<?php echo esc_attr( bgcf_classnames( array( 'bgcf-mega__link', 'is-current' => $bgcf_is_current ) ) ); ?>"
		href="<?php echo esc_url( $bgcf_url ); ?>"
		<?php
		if ( $bgcf_is_current ) {
			echo ' aria-current="page"';
		}

		if ( '_blank' === (string) ( $bgcf_cell['target'] ?? '' ) ) {
			echo ' target="_blank" rel="noopener noreferrer"';
		}
		?>
	>
		<span class="bgcf-mega__icon" aria-hidden="true">
			<?php bgcf_svg_icon( (string) ( $bgcf_cell['icon'] ?? 'arrow-up-right' ) ); ?>
		</span>

		<span class="bgcf-mega__body">
			<span class="bgcf-mega__title"><?php echo esc_html( $bgcf_title ); ?></span>

			<?php if ( '' !== $bgcf_description ) : ?>
				<span class="bgcf-mega__desc"><?php echo esc_html( $bgcf_description ); ?></span>
			<?php endif; ?>
		</span>
	</a>
</li>
