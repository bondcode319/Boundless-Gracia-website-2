<?php
/**
 * Search result item.
 *
 * Shows the content type alongside the title, because a search that mixes
 * programs, events, stories and posts is confusing without it.
 *
 * @package Bgcf
 * @since   1.0.0
 *
 * @var array<string, mixed> $args Optional partial arguments.
 */

defined( 'ABSPATH' ) || exit;

$bgcf_type   = get_post_type_object( (string) get_post_type() );
$bgcf_label  = $bgcf_type instanceof WP_Post_Type ? $bgcf_type->labels->singular_name : '';
?>
<article <?php post_class( 'bgcf-result' ); ?>>

	<h2 class="bgcf-result__title">
		<a class="bgcf-result__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<div class="bgcf-entry-meta bgcf-result__meta">
		<?php
		if ( '' !== $bgcf_label ) {
			printf(
				'<span class="bgcf-entry-meta__item bgcf-entry-meta__item--type">%s</span>',
				esc_html( $bgcf_label )
			);
		}

		bgcf_posted_on();
		?>
	</div>

	<div class="bgcf-result__excerpt">
		<?php the_excerpt(); ?>
	</div>

</article>
