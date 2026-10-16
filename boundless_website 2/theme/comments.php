<?php
/**
 * Comments and the comment form.
 *
 * Comments are enabled on blog posts only; every custom post type ships with
 * comment support off. The markup below relies on the `html5` theme support
 * declared in inc/setup.php, which gives WordPress-generated form fields
 * accessible labels and required attributes out of the box.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * Do not load for password-protected posts until the visitor has entered the
 * password — otherwise the discussion leaks the post's context.
 */
if ( post_password_required() ) {
	return;
}

$bgcf_comment_count = (int) get_comments_number();
?>
<section class="bgcf-comments" id="bgcf-comments" aria-labelledby="bgcf-comments-title">

	<?php if ( have_comments() ) : ?>

		<h2 class="bgcf-comments__title" id="bgcf-comments-title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of comments. */
					_n( '%s comment', '%s comments', $bgcf_comment_count, 'bgcf' ),
					number_format_i18n( $bgcf_comment_count )
				)
			);
			?>
		</h2>

		<ol class="bgcf-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 64,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation(
			array(
				'screen_reader_text' => bgcf_get_text( 'comments_nav_label', __( 'Comment navigation', 'bgcf' ) ),
				'aria_label'         => bgcf_get_text( 'comments_nav_label', __( 'Comment navigation', 'bgcf' ) ),
				'class'              => 'bgcf-comments__nav',
			)
		);
		?>

	<?php else : ?>

		<h2 class="bgcf-comments__title bgcf-comments__title--empty" id="bgcf-comments-title">
			<?php bgcf_the_text( 'comments_empty_title', __( 'Join the conversation', 'bgcf' ) ); ?>
		</h2>

	<?php endif; ?>

	<?php if ( ! comments_open() && $bgcf_comment_count > 0 ) : ?>
		<p class="bgcf-comments__closed">
			<?php bgcf_the_text( 'comments_closed', __( 'Comments are closed on this post.', 'bgcf' ) ); ?>
		</p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_form'         => 'bgcf-comment-form',
			'class_submit'       => 'bgcf-btn bgcf-btn--primary bgcf-btn--md',
			'title_reply'        => bgcf_get_text( 'comment_form_title', __( 'Leave a comment', 'bgcf' ) ),
			'title_reply_to'     => bgcf_get_text( 'comment_form_reply_title', __( 'Reply to %s', 'bgcf' ) ),
			'label_submit'       => bgcf_get_text( 'comment_form_submit', __( 'Post comment', 'bgcf' ) ),
			'comment_notes_before' => '',
		)
	);
	?>

</section>
