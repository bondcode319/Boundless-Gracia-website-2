<?php
/**
 * Custom post types and taxonomies.
 *
 * Registered in the theme (not a plugin) by explicit decision: the whole site is
 * this theme, the CPTs have no meaning without its templates, and keeping them
 * here makes the deliverable portable as a single artefact. The trade-off is
 * documented in ARCHITECTURE.md ("Coupling and migration risks") — deactivating
 * the theme hides this content from the front end.
 *
 * SLUGS ARE A ONE-WAY DOOR. Post type keys, taxonomy keys and rewrite slugs are
 * written into the database (`wp_posts.post_type`, `wp_term_taxonomy.taxonomy`),
 * into every permalink, and into every ACF location rule. Changing one after
 * content exists is a data migration plus a redirect map — not a rename. Get
 * them right now; the registry table in ARCHITECTURE.md is the source of truth.
 *
 * Visibility policy:
 *   - Public with archives : program, event, campaign, story  (they have URLs)
 *   - Public, no archive   : team                             (single bio pages)
 *   - Admin-only           : partner, testimonial, report     (surfaced through
 *                            blocks and page templates, never as standalone URLs)
 * Admin-only was chosen deliberately: promoting a CPT to public later is free,
 * demoting one after it has indexed URLs is not.
 *
 * @package Bgcf
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The theme's post type keys, mapped to a human label key.
 *
 * Use this instead of hardcoding post type strings in queries.
 *
 * @since 1.0.0
 * @return string[] Post type keys.
 */
function bgcf_get_post_types(): array {
	return array(
		'bgcf_program',
		'bgcf_event',
		'bgcf_campaign',
		'bgcf_story',
		'bgcf_team',
		'bgcf_partner',
		'bgcf_testimonial',
		'bgcf_report',
	);
}

/**
 * The theme's taxonomy keys.
 *
 * @since 1.0.0
 * @return string[] Taxonomy keys.
 */
function bgcf_get_taxonomies(): array {
	return array(
		'bgcf_program_category',
		'bgcf_event_type',
		'bgcf_department',
		'bgcf_partner_type',
		'bgcf_report_year',
	);
}

/**
 * Register all custom post types.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_register_post_types(): void {

	/* ---------------------------------------------------------------------
	 * Programs — /programs/{slug}/
	 * The core content type. Everything else references it.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_program',
		array(
			'labels'          => array(
				'name'                  => _x( 'Programs', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Program', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Programs', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Program', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Program', 'bgcf' ),
				'new_item'              => __( 'New Program', 'bgcf' ),
				'edit_item'             => __( 'Edit Program', 'bgcf' ),
				'view_item'             => __( 'View Program', 'bgcf' ),
				'view_items'            => __( 'View Programs', 'bgcf' ),
				'all_items'             => __( 'All Programs', 'bgcf' ),
				'search_items'          => __( 'Search Programs', 'bgcf' ),
				'not_found'             => __( 'No programs found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No programs found in Trash.', 'bgcf' ),
				'archives'              => __( 'Program Archives', 'bgcf' ),
				'featured_image'        => __( 'Program Image', 'bgcf' ),
				'set_featured_image'    => __( 'Set program image', 'bgcf' ),
				'remove_featured_image' => __( 'Remove program image', 'bgcf' ),
				'use_featured_image'    => __( 'Use as program image', 'bgcf' ),
				'items_list'            => __( 'Programs list', 'bgcf' ),
				'items_list_navigation' => __( 'Programs list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter programs list', 'bgcf' ),
			),
			'description'     => __( 'Long-running areas of work, e.g. Medical Support or Education.', 'bgcf' ),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'programs',
			'menu_icon'       => 'dashicons-heart',
			'menu_position'   => 20,
			'hierarchical'    => false,
			'has_archive'     => 'programs',
			'rewrite'         => array(
				'slug'       => 'programs',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Events — /events/{slug}/
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_event',
		array(
			'labels'          => array(
				'name'                  => _x( 'Events', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Event', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Events', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Event', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Event', 'bgcf' ),
				'new_item'              => __( 'New Event', 'bgcf' ),
				'edit_item'             => __( 'Edit Event', 'bgcf' ),
				'view_item'             => __( 'View Event', 'bgcf' ),
				'view_items'            => __( 'View Events', 'bgcf' ),
				'all_items'             => __( 'All Events', 'bgcf' ),
				'search_items'          => __( 'Search Events', 'bgcf' ),
				'not_found'             => __( 'No events found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No events found in Trash.', 'bgcf' ),
				'archives'              => __( 'Event Archives', 'bgcf' ),
				'featured_image'        => __( 'Event Image', 'bgcf' ),
				'set_featured_image'    => __( 'Set event image', 'bgcf' ),
				'remove_featured_image' => __( 'Remove event image', 'bgcf' ),
				'use_featured_image'    => __( 'Use as event image', 'bgcf' ),
				'items_list'            => __( 'Events list', 'bgcf' ),
				'items_list_navigation' => __( 'Events list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter events list', 'bgcf' ),
			),
			'description'     => __( 'Dated activities: fundraisers, outreach days, volunteer drives.', 'bgcf' ),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'events',
			'menu_icon'       => 'dashicons-calendar-alt',
			'menu_position'   => 21,
			'hierarchical'    => false,
			'has_archive'     => 'events',
			'rewrite'         => array(
				'slug'       => 'events',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Campaigns — /campaigns/{slug}/
	 * Fundraising appeals. The public "Projects" page is a page template that
	 * queries this type; it is intentionally decoupled from the archive URL.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_campaign',
		array(
			'labels'          => array(
				'name'                  => _x( 'Campaigns', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Campaign', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Campaigns', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Campaign', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Campaign', 'bgcf' ),
				'new_item'              => __( 'New Campaign', 'bgcf' ),
				'edit_item'             => __( 'Edit Campaign', 'bgcf' ),
				'view_item'             => __( 'View Campaign', 'bgcf' ),
				'view_items'            => __( 'View Campaigns', 'bgcf' ),
				'all_items'             => __( 'All Campaigns', 'bgcf' ),
				'search_items'          => __( 'Search Campaigns', 'bgcf' ),
				'not_found'             => __( 'No campaigns found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No campaigns found in Trash.', 'bgcf' ),
				'archives'              => __( 'Campaign Archives', 'bgcf' ),
				'featured_image'        => __( 'Campaign Image', 'bgcf' ),
				'set_featured_image'    => __( 'Set campaign image', 'bgcf' ),
				'remove_featured_image' => __( 'Remove campaign image', 'bgcf' ),
				'use_featured_image'    => __( 'Use as campaign image', 'bgcf' ),
				'items_list'            => __( 'Campaigns list', 'bgcf' ),
				'items_list_navigation' => __( 'Campaigns list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter campaigns list', 'bgcf' ),
			),
			'description'     => __( 'Time-boxed fundraising appeals with a goal, a total raised and a deadline.', 'bgcf' ),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'campaigns',
			'menu_icon'       => 'dashicons-megaphone',
			'menu_position'   => 22,
			'hierarchical'    => false,
			'has_archive'     => 'campaigns',
			'rewrite'         => array(
				'slug'       => 'campaigns',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Success Stories — /stories/{slug}/
	 * Shares the Program Category taxonomy with programs so a story can be
	 * filtered by the area of work it belongs to.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_story',
		array(
			'labels'          => array(
				'name'                  => _x( 'Success Stories', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Success Story', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Success Stories', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Success Story', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Success Story', 'bgcf' ),
				'new_item'              => __( 'New Success Story', 'bgcf' ),
				'edit_item'             => __( 'Edit Success Story', 'bgcf' ),
				'view_item'             => __( 'View Success Story', 'bgcf' ),
				'view_items'            => __( 'View Success Stories', 'bgcf' ),
				'all_items'             => __( 'All Success Stories', 'bgcf' ),
				'search_items'          => __( 'Search Success Stories', 'bgcf' ),
				'not_found'             => __( 'No success stories found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No success stories found in Trash.', 'bgcf' ),
				'archives'              => __( 'Success Story Archives', 'bgcf' ),
				'featured_image'        => __( 'Story Image', 'bgcf' ),
				'set_featured_image'    => __( 'Set story image', 'bgcf' ),
				'remove_featured_image' => __( 'Remove story image', 'bgcf' ),
				'use_featured_image'    => __( 'Use as story image', 'bgcf' ),
				'items_list'            => __( 'Success stories list', 'bgcf' ),
				'items_list_navigation' => __( 'Success stories list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter success stories list', 'bgcf' ),
			),
			'description'     => __( 'Beneficiary outcomes told as before/after narratives. Requires documented consent.', 'bgcf' ),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'stories',
			'menu_icon'       => 'dashicons-awards',
			'menu_position'   => 23,
			'hierarchical'    => false,
			'has_archive'     => 'stories',
			'rewrite'         => array(
				'slug'       => 'stories',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Team — /team/{slug}/, no archive (the leadership grid lives on About).
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_team',
		array(
			'labels'          => array(
				'name'                  => _x( 'Team', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Team Member', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Team', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Team Member', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Team Member', 'bgcf' ),
				'new_item'              => __( 'New Team Member', 'bgcf' ),
				'edit_item'             => __( 'Edit Team Member', 'bgcf' ),
				'view_item'             => __( 'View Team Member', 'bgcf' ),
				'view_items'            => __( 'View Team', 'bgcf' ),
				'all_items'             => __( 'All Team Members', 'bgcf' ),
				'search_items'          => __( 'Search Team', 'bgcf' ),
				'not_found'             => __( 'No team members found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No team members found in Trash.', 'bgcf' ),
				'archives'              => __( 'Team Archives', 'bgcf' ),
				'featured_image'        => __( 'Portrait', 'bgcf' ),
				'set_featured_image'    => __( 'Set portrait', 'bgcf' ),
				'remove_featured_image' => __( 'Remove portrait', 'bgcf' ),
				'use_featured_image'    => __( 'Use as portrait', 'bgcf' ),
				'items_list'            => __( 'Team list', 'bgcf' ),
				'items_list_navigation' => __( 'Team list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter team list', 'bgcf' ),
			),
			'description'     => __( 'Board, leadership and staff profiles.', 'bgcf' ),
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'team',
			'menu_icon'       => 'dashicons-groups',
			'menu_position'   => 24,
			'hierarchical'    => false,
			'has_archive'     => false,
			'rewrite'         => array(
				'slug'       => 'team',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Partners — admin only. Surfaced by the bgcf/partners block.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_partner',
		array(
			'labels'          => array(
				'name'                  => _x( 'Partners', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Partner', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Partners', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Partner', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Partner', 'bgcf' ),
				'new_item'              => __( 'New Partner', 'bgcf' ),
				'edit_item'             => __( 'Edit Partner', 'bgcf' ),
				'view_item'             => __( 'View Partner', 'bgcf' ),
				'view_items'            => __( 'View Partners', 'bgcf' ),
				'all_items'             => __( 'All Partners', 'bgcf' ),
				'search_items'          => __( 'Search Partners', 'bgcf' ),
				'not_found'             => __( 'No partners found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No partners found in Trash.', 'bgcf' ),
				'featured_image'        => __( 'Logo', 'bgcf' ),
				'set_featured_image'    => __( 'Set logo', 'bgcf' ),
				'remove_featured_image' => __( 'Remove logo', 'bgcf' ),
				'use_featured_image'    => __( 'Use as logo', 'bgcf' ),
				'items_list'            => __( 'Partners list', 'bgcf' ),
				'items_list_navigation' => __( 'Partners list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter partners list', 'bgcf' ),
			),
			'description'     => __( 'Supporting organisations, shown as logos that link off-site.', 'bgcf' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_nav_menus' => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_in_rest'    => true,
			'rest_base'       => 'partners',
			'menu_icon'       => 'dashicons-networking',
			'menu_position'   => 25,
			'hierarchical'    => false,
			'has_archive'     => false,
			'rewrite'         => false,
			'supports'        => array( 'title', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Testimonials — admin only. Surfaced by the bgcf/testimonials block.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_testimonial',
		array(
			'labels'          => array(
				'name'                  => _x( 'Testimonials', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Testimonial', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Testimonials', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Testimonial', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Testimonial', 'bgcf' ),
				'new_item'              => __( 'New Testimonial', 'bgcf' ),
				'edit_item'             => __( 'Edit Testimonial', 'bgcf' ),
				'view_item'             => __( 'View Testimonial', 'bgcf' ),
				'view_items'            => __( 'View Testimonials', 'bgcf' ),
				'all_items'             => __( 'All Testimonials', 'bgcf' ),
				'search_items'          => __( 'Search Testimonials', 'bgcf' ),
				'not_found'             => __( 'No testimonials found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'bgcf' ),
				'featured_image'        => __( 'Photo', 'bgcf' ),
				'set_featured_image'    => __( 'Set photo', 'bgcf' ),
				'remove_featured_image' => __( 'Remove photo', 'bgcf' ),
				'use_featured_image'    => __( 'Use as photo', 'bgcf' ),
				'items_list'            => __( 'Testimonials list', 'bgcf' ),
				'items_list_navigation' => __( 'Testimonials list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter testimonials list', 'bgcf' ),
			),
			'description'     => __( 'Short quotes from beneficiaries, volunteers, donors and partners.', 'bgcf' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_nav_menus' => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_in_rest'    => true,
			'rest_base'       => 'testimonials',
			'menu_icon'       => 'dashicons-format-quote',
			'menu_position'   => 26,
			'hierarchical'    => false,
			'has_archive'     => false,
			'rewrite'         => false,
			'supports'        => array( 'title', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	/* ---------------------------------------------------------------------
	 * Reports — admin only. Listed on About / Resources; the public artefact is
	 * the PDF, not a WordPress URL.
	 * ------------------------------------------------------------------ */
	register_post_type(
		'bgcf_report',
		array(
			'labels'          => array(
				'name'                  => _x( 'Reports', 'post type general name', 'bgcf' ),
				'singular_name'         => _x( 'Report', 'post type singular name', 'bgcf' ),
				'menu_name'             => _x( 'Reports', 'admin menu', 'bgcf' ),
				'name_admin_bar'        => _x( 'Report', 'add new on admin bar', 'bgcf' ),
				'add_new'               => __( 'Add New', 'bgcf' ),
				'add_new_item'          => __( 'Add New Report', 'bgcf' ),
				'new_item'              => __( 'New Report', 'bgcf' ),
				'edit_item'             => __( 'Edit Report', 'bgcf' ),
				'view_item'             => __( 'View Report', 'bgcf' ),
				'view_items'            => __( 'View Reports', 'bgcf' ),
				'all_items'             => __( 'All Reports', 'bgcf' ),
				'search_items'          => __( 'Search Reports', 'bgcf' ),
				'not_found'             => __( 'No reports found.', 'bgcf' ),
				'not_found_in_trash'    => __( 'No reports found in Trash.', 'bgcf' ),
				'featured_image'        => __( 'Cover', 'bgcf' ),
				'set_featured_image'    => __( 'Set cover', 'bgcf' ),
				'remove_featured_image' => __( 'Remove cover', 'bgcf' ),
				'use_featured_image'    => __( 'Use as cover', 'bgcf' ),
				'items_list'            => __( 'Reports list', 'bgcf' ),
				'items_list_navigation' => __( 'Reports list navigation', 'bgcf' ),
				'filter_items_list'     => __( 'Filter reports list', 'bgcf' ),
			),
			'description'     => __( 'Annual reports, financial statements and downloadable publications.', 'bgcf' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_nav_menus' => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_in_rest'    => true,
			'rest_base'       => 'reports',
			'menu_icon'       => 'dashicons-media-document',
			'menu_position'   => 27,
			'hierarchical'    => false,
			'has_archive'     => false,
			'rewrite'         => false,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'bgcf_register_post_types', 5 );

/**
 * Register all custom taxonomies.
 *
 * Runs after post types (priority 6) so every object type already exists.
 *
 * @since 1.0.0
 * @return void
 */
function bgcf_register_taxonomies(): void {

	/* ---------------------------------------------------------------------
	 * Program Category — shared by Programs and Success Stories.
	 * The shared attachment is what lets "stories from the Education program"
	 * work as one query. Detaching it later orphans the story terms.
	 * ------------------------------------------------------------------ */
	register_taxonomy(
		'bgcf_program_category',
		array( 'bgcf_program', 'bgcf_story' ),
		array(
			'labels'            => array(
				'name'              => _x( 'Program Categories', 'taxonomy general name', 'bgcf' ),
				'singular_name'     => _x( 'Program Category', 'taxonomy singular name', 'bgcf' ),
				'menu_name'         => __( 'Program Categories', 'bgcf' ),
				'all_items'         => __( 'All Program Categories', 'bgcf' ),
				'edit_item'         => __( 'Edit Program Category', 'bgcf' ),
				'view_item'         => __( 'View Program Category', 'bgcf' ),
				'update_item'       => __( 'Update Program Category', 'bgcf' ),
				'add_new_item'      => __( 'Add New Program Category', 'bgcf' ),
				'new_item_name'     => __( 'New Program Category Name', 'bgcf' ),
				'parent_item'       => __( 'Parent Program Category', 'bgcf' ),
				'parent_item_colon' => __( 'Parent Program Category:', 'bgcf' ),
				'search_items'      => __( 'Search Program Categories', 'bgcf' ),
				'not_found'         => __( 'No program categories found.', 'bgcf' ),
				'back_to_items'     => __( 'Back to Program Categories', 'bgcf' ),
			),
			'description'       => __( 'Areas of work. Shared by Programs and Success Stories.', 'bgcf' ),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'program-categories',
			'rewrite'           => array(
				'slug'         => 'program-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);

	/* ---------------------------------------------------------------------
	 * Event Type
	 * ------------------------------------------------------------------ */
	register_taxonomy(
		'bgcf_event_type',
		array( 'bgcf_event' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Event Types', 'taxonomy general name', 'bgcf' ),
				'singular_name' => _x( 'Event Type', 'taxonomy singular name', 'bgcf' ),
				'menu_name'     => __( 'Event Types', 'bgcf' ),
				'all_items'     => __( 'All Event Types', 'bgcf' ),
				'edit_item'     => __( 'Edit Event Type', 'bgcf' ),
				'view_item'     => __( 'View Event Type', 'bgcf' ),
				'update_item'   => __( 'Update Event Type', 'bgcf' ),
				'add_new_item'  => __( 'Add New Event Type', 'bgcf' ),
				'new_item_name' => __( 'New Event Type Name', 'bgcf' ),
				'search_items'  => __( 'Search Event Types', 'bgcf' ),
				'not_found'     => __( 'No event types found.', 'bgcf' ),
				'back_to_items' => __( 'Back to Event Types', 'bgcf' ),
			),
			'description'       => __( 'Fundraiser, outreach, workshop, volunteer drive, and so on.', 'bgcf' ),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'event-types',
			'rewrite'           => array(
				'slug'       => 'event-type',
				'with_front' => false,
			),
		)
	);

	/* ---------------------------------------------------------------------
	 * Department — groups team members on the About page.
	 * ------------------------------------------------------------------ */
	register_taxonomy(
		'bgcf_department',
		array( 'bgcf_team' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Departments', 'taxonomy general name', 'bgcf' ),
				'singular_name' => _x( 'Department', 'taxonomy singular name', 'bgcf' ),
				'menu_name'     => __( 'Departments', 'bgcf' ),
				'all_items'     => __( 'All Departments', 'bgcf' ),
				'edit_item'     => __( 'Edit Department', 'bgcf' ),
				'view_item'     => __( 'View Department', 'bgcf' ),
				'update_item'   => __( 'Update Department', 'bgcf' ),
				'add_new_item'  => __( 'Add New Department', 'bgcf' ),
				'new_item_name' => __( 'New Department Name', 'bgcf' ),
				'search_items'  => __( 'Search Departments', 'bgcf' ),
				'not_found'     => __( 'No departments found.', 'bgcf' ),
				'back_to_items' => __( 'Back to Departments', 'bgcf' ),
			),
			'description'       => __( 'Board, leadership, programs, operations, and so on.', 'bgcf' ),
			'public'            => false,
			'show_ui'           => true,
			'publicly_queryable' => false,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'departments',
			'rewrite'           => false,
		)
	);

	/* ---------------------------------------------------------------------
	 * Partner Type
	 * ------------------------------------------------------------------ */
	register_taxonomy(
		'bgcf_partner_type',
		array( 'bgcf_partner' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Partner Types', 'taxonomy general name', 'bgcf' ),
				'singular_name' => _x( 'Partner Type', 'taxonomy singular name', 'bgcf' ),
				'menu_name'     => __( 'Partner Types', 'bgcf' ),
				'all_items'     => __( 'All Partner Types', 'bgcf' ),
				'edit_item'     => __( 'Edit Partner Type', 'bgcf' ),
				'view_item'     => __( 'View Partner Type', 'bgcf' ),
				'update_item'   => __( 'Update Partner Type', 'bgcf' ),
				'add_new_item'  => __( 'Add New Partner Type', 'bgcf' ),
				'new_item_name' => __( 'New Partner Type Name', 'bgcf' ),
				'search_items'  => __( 'Search Partner Types', 'bgcf' ),
				'not_found'     => __( 'No partner types found.', 'bgcf' ),
				'back_to_items' => __( 'Back to Partner Types', 'bgcf' ),
			),
			'description'       => __( 'Corporate, institutional, NGO, government, media.', 'bgcf' ),
			'public'            => false,
			'show_ui'           => true,
			'publicly_queryable' => false,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'partner-types',
			'rewrite'           => false,
		)
	);

	/* ---------------------------------------------------------------------
	 * Report Year.
	 *
	 * NOTE: the taxonomy key is `bgcf_report_year`, never `year`. `year` is a
	 * reserved WordPress query var (date queries) and registering it as a public
	 * taxonomy query var breaks archive routing site-wide.
	 * ------------------------------------------------------------------ */
	register_taxonomy(
		'bgcf_report_year',
		array( 'bgcf_report' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Report Years', 'taxonomy general name', 'bgcf' ),
				'singular_name' => _x( 'Report Year', 'taxonomy singular name', 'bgcf' ),
				'menu_name'     => __( 'Years', 'bgcf' ),
				'all_items'     => __( 'All Years', 'bgcf' ),
				'edit_item'     => __( 'Edit Year', 'bgcf' ),
				'view_item'     => __( 'View Year', 'bgcf' ),
				'update_item'   => __( 'Update Year', 'bgcf' ),
				'add_new_item'  => __( 'Add New Year', 'bgcf' ),
				'new_item_name' => __( 'New Year', 'bgcf' ),
				'search_items'  => __( 'Search Years', 'bgcf' ),
				'not_found'     => __( 'No years found.', 'bgcf' ),
				'back_to_items' => __( 'Back to Years', 'bgcf' ),
			),
			'description'       => __( 'Publication year used to group reports.', 'bgcf' ),
			'public'            => false,
			'show_ui'           => true,
			'publicly_queryable' => false,
			'query_var'         => false,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'report-years',
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'bgcf_register_taxonomies', 6 );

/**
 * Sort admin-only reference types by menu order, then title.
 *
 * Editors curate the order of partners, testimonials and team members by drag
 * handle; the front end must respect it without every query repeating orderby.
 *
 * @since 1.0.0
 *
 * @param WP_Query $query Query instance, passed by reference by WordPress.
 * @return void
 */
function bgcf_order_reference_post_types( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$ordered = array( 'bgcf_team', 'bgcf_partner', 'bgcf_testimonial' );

	if ( in_array( (string) $query->get( 'post_type' ), $ordered, true ) && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'bgcf_order_reference_post_types' );
