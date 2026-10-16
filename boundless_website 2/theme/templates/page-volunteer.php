<?php
/**
 * Template Name: Volunteer
 * Template Post Type: page
 *
 * The page's own blocks carry the pitch — why volunteer, what the roles are,
 * testimonials from existing volunteers — and this template adds the
 * application form underneath. That split is deliberate: the persuading is
 * content the team will rewrite often, and the form is not.
 *
 * @package Bgcf
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'bgcf-volunteer' ); ?>>
		<?php
		$bgcf_intro = array(
			'class'      => 'bgcf-volunteer__intro',
			'background' => 'base',
			'width'      => 'default',
			'labelledby' => 'bgcf-volunteer-title',
		);

		bgcf_section_open( $bgcf_intro );

		bgcf_breadcrumbs();
		?>

		<h1 class="bgcf-volunteer__title" id="bgcf-volunteer-title"><?php the_title(); ?></h1>

		<?php
		bgcf_section_close( $bgcf_intro );

		// Blocks placed on the page render at full width, outside the container.
		the_content();

		$bgcf_form = array(
			'class'      => 'bgcf-volunteer__apply',
			'background' => 'surface',
			'width'      => 'default',
			'labelledby' => 'bgcf-volunteer-form-title',
		);

		bgcf_section_open( $bgcf_form );

		bgcf_section_heading(
			array(
				'title' => bgcf_get_text( 'volunteer_form_title', __( 'Apply to volunteer', 'bgcf' ) ),
				'lead'  => bgcf_get_text(
					'volunteer_form_lead',
					__( 'Tell us a little about yourself and we will be in touch within a week.', 'bgcf' )
				),
				'id'    => 'bgcf-volunteer-form-title',
			)
		);

		bgcf_render_partial(
			'global/volunteer-form',
			array( 'skills' => (string) bgcf_field( 'volunteer_skills', null, '' ) )
		);

		bgcf_section_close( $bgcf_form );
		?>
	</article>
	<?php
endwhile;

get_footer();
