<?php
/**
 * Empty state for any loop that returned nothing.
 *
 * Copy comes from bgcf_get_text() so editors can change it without a deploy.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args {
 *     @type string $context 'archive' (default) or 'search'.
 * }
 */

defined( 'ABSPATH' ) || exit;

$bgcf_context = isset( $args['context'] ) ? (string) $args['context'] : 'archive';
$bgcf_is_search = 'search' === $bgcf_context;
?>
<div class="bgcf-empty bgcf-empty--<?php echo esc_attr( sanitize_html_class( $bgcf_context ) ); ?>">

	<h2 class="bgcf-empty__title">
		<?php
		if ( $bgcf_is_search ) {
			bgcf_the_text( 'empty_search_title', __( 'No results found', 'bgcf' ) );
		} else {
			bgcf_the_text( 'empty_archive_title', __( 'Nothing here yet', 'bgcf' ) );
		}
		?>
	</h2>

	<p class="bgcf-empty__text">
		<?php
		if ( $bgcf_is_search ) {
			bgcf_the_text( 'empty_search_text', __( 'Try a different word, or browse our programs and stories.', 'bgcf' ) );
		} else {
			bgcf_the_text( 'empty_archive_text', __( 'New content is on its way. Please check back soon.', 'bgcf' ) );
		}
		?>
	</p>

	<?php if ( $bgcf_is_search ) : ?>
		<div class="bgcf-empty__search">
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>

</div>
