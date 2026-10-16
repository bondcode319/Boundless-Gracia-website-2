<?php
/**
 * Block patterns — the starter pages.
 *
 * WHY PATTERNS AND NOT A DEMO IMPORTER
 * ------------------------------------
 * A fresh install of this theme has a working homepage the moment an editor
 * inserts one pattern. The alternative — a demo-content importer that creates
 * posts — has two problems this avoids: it writes rows nobody asked for, and the
 * content it writes is indistinguishable from the organisation's real content
 * six months later, so nobody ever dares delete it. A pattern is inserted
 * deliberately, becomes the editor's own content immediately, and leaves nothing
 * behind if they undo it.
 *
 * THE COPY IN HERE IS REAL PLACEHOLDER COPY, not lorem ipsum. It is written to
 * the right length for each slot, in the organisation's voice, and it is
 * specific enough that an editor replacing it knows what the slot is for — "UGX
 * 50,000 keeps one child in school for a term" tells them this field wants a
 * costed outcome, where "Lorem ipsum dolor" tells them nothing. Every line is
 * original to this theme.
 *
 * NO IMAGES ARE REFERENCED. A pattern cannot know the attachment IDs on the
 * install it is inserted into, and a pattern that points at ID 42 renders a
 * broken image on every site but the one it was authored on. Every band in these
 * patterns is designed to render correctly with no image — the photographic
 * bands fall back to flat deep blue, the cards fall back to the global default
 * image — so the inserted page looks finished and the editor adds photographs
 * slot by slot. `assets/images/IMAGE-MANIFEST.md` is the list of what to supply.
 *
 * SERIALISATION. ACF block attributes hold `name => value` pairs plus a `_name`
 * pointer to each field's key. `bgcf_pattern_block()` writes both, so a pattern
 * stays in step with the generated field keys automatically — the keys are
 * derived from the group id and the field name, which `build/acf-fields.mjs`
 * guarantees are stable.
 *
 * @package Bgcf
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serialise one ACF block for use inside a pattern.
 *
 * @since 2.0.0
 *
 * @param string               $slug  Block slug, e.g. 'hero'.
 * @param string               $group ACF group id the fields belong to, e.g. 'hero'.
 *                                    Usually the slug with hyphens replaced by
 *                                    underscores, which is why it is explicit.
 * @param array<string, mixed> $data  Field name => value.
 * @param string               $align Block alignment: 'full', 'wide' or ''.
 * @return string Block comment markup.
 */
function bgcf_pattern_block( string $slug, string $group, array $data = array(), string $align = 'full' ): string {
	$payload = array();

	foreach ( $data as $name => $value ) {
		$payload[ $name ]        = $value;
		$payload[ '_' . $name ] = 'field_bgcf_' . $group . '_' . $name;
	}

	$attributes = array(
		/*
		 * `acf/` and not `bgcf/`: ACF 5 rewrites an explicitly namespaced block
		 * to `acf/{slug}` on registration, and a pattern naming a block that is
		 * not in the registry renders as an "unsupported block" placeholder.
		 * `bgcf_render_block()` and `bgcf_get_block_editor_names()` accept both
		 * namespaces, so `acf/` is the safe one to serialise.
		 */
		'name' => 'acf/' . $slug,
		'data' => $payload,
		'mode' => 'preview',
	);

	if ( '' !== $align ) {
		$attributes['align'] = $align;
	}

	return sprintf(
		'<!-- wp:acf/%1$s %2$s /-->',
		$slug,
		(string) wp_json_encode( $attributes )
	);
}

/**
 * Serialise one row of an ACF repeater for use inside a pattern.
 *
 * ACF flattens a repeater into `{name}` (the row count) plus
 * `{name}_{index}_{subfield}` for every value, each with its own `_` pointer
 * whose key is the SUB-FIELD's key — not the row's. Getting that wrong produces
 * a repeater that saves but renders empty, with no error anywhere.
 *
 * @since 2.0.0
 *
 * @param string                           $group Group id.
 * @param string                           $name  Repeater field name.
 * @param array<int, array<string, mixed>> $rows  Rows of sub-field name => value.
 * @return array<string, mixed> Flattened data to merge into a block's payload.
 */
function bgcf_pattern_rows( string $group, string $name, array $rows ): array {
	$data = array( $name => count( $rows ) );

	foreach ( array_values( $rows ) as $index => $row ) {
		foreach ( $row as $field => $value ) {
			$key                  = $name . '_' . $index . '_' . $field;
			$data[ $key ]         = $value;
			$data[ '_' . $key ] = 'field_bgcf_' . $group . '_' . $field;
		}
	}

	return $data;
}

/**
 * Register the theme's pattern category.
 *
 * @since 2.0.0
 * @return void
 */
function bgcf_register_pattern_category(): void {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'bgcf',
		array(
			'label'       => __( 'Boundless Gracia', 'bgcf' ),
			'description' => __( 'Complete starter pages built from the theme’s own bands.', 'bgcf' ),
		)
	);
}
add_action( 'init', 'bgcf_register_pattern_category', 9 );

/**
 * The homepage pattern: the full eleven-band Horizon blueprint.
 *
 * The band order is the design, and the rule it follows is that no two adjacent
 * bands share a background:
 *
 *   hero (photo) → soft → photo → ink → page → sand → photo → ink → soft →
 *   bloom → footer (ink-deep)
 *
 * Re-ordering the bands in the editor is expected and supported; re-ordering
 * them so two light bands sit together is what makes a long page read as one
 * undifferentiated column, so the pattern starts correct.
 *
 * @since 2.0.0
 * @return string Serialised blocks.
 */
function bgcf_pattern_homepage(): string {
	$blocks = array();

	// 1 — Hero.
	$blocks[] = bgcf_pattern_block(
		'hero',
		'hero',
		array(
			'eyebrow'          => __( 'Kampala, Uganda · since 2014', 'bgcf' ),
			'heading'          => __( 'Every child deserves a place to belong', 'bgcf' ),
			'heading_emphasis' => __( 'a place to belong', 'bgcf' ),
			'heading_level'    => 'h1',
			'lead'             => __( 'We walk with children who have been left without a safety net — through school, through illness, through the years it takes to build a life they choose for themselves.', 'bgcf' ),
			'overlay_opacity'  => 55,
		)
	);

	// 2 — The problem, as a split section on the light band.
	$blocks[] = bgcf_pattern_block(
		'split-section',
		'split_section',
		array(
			'eyebrow'    => __( 'Why this work exists', 'bgcf' ),
			'heading'    => __( 'A school fee is rarely the only thing missing', 'bgcf' ),
			'text'       => '<p>' . __( 'A child who stops coming to school has usually been losing ground for months — a parent who fell ill, a harvest that failed, a long walk that became unsafe. By the time the fees go unpaid, the fees are the smallest part of the problem.', 'bgcf' ) . '</p><p>' . __( 'So we do not start with fees. We start with the household, work out what is actually in the way, and stay long enough to see it change.', 'bgcf' ) . '</p>',
			'media_side' => 'start',
			'ratio'      => 'even',
			'background' => 'soft',
			'link_style' => 'secondary',
		)
	);

	// 3 — First photographic call to action.
	$blocks[] = bgcf_pattern_block(
		'photo-cta',
		'photo_cta',
		array(
			'heading' => __( 'One decision changes one childhood', 'bgcf' ),
			'text'    => __( 'Sponsor a child’s year, and we will show you exactly what it paid for.', 'bgcf' ),
			'scrim'   => 62,
		)
	);

	// 4 — The approach, on the dark band, image right.
	$blocks[] = bgcf_pattern_block(
		'split-section',
		'split_section',
		array(
			'eyebrow'    => __( 'How we work', 'bgcf' ),
			'heading'    => __( 'Ten years in the same neighbourhoods', 'bgcf' ),
			'text'       => '<p>' . __( 'We began in 2014 with eleven children and one rented room in Nansana. We have never moved out of the district, and most of our staff grew up in it.', 'bgcf' ) . '</p><p>' . __( 'That is deliberate. Programmes that arrive, spend and leave look efficient on paper. Staying is what lets us follow a child from a first interview to a first payslip.', 'bgcf' ) . '</p>',
			'media_side' => 'end',
			'ratio'      => 'text',
			'background' => 'ink',
		)
	);

	// 5 — Programmes.
	$blocks[] = bgcf_pattern_block(
		'program-cards',
		'program_cards',
		array(
			'eyebrow'      => __( 'Our programmes', 'bgcf' ),
			'heading'      => __( 'Four things we do, and do properly', 'bgcf' ),
			'lead'         => __( 'Each programme is costed, measured and reported on. Open any one of them to see what it spends and what it has changed.', 'bgcf' ),
			'background'   => 'page',
			'source'       => 'latest',
			'limit'        => 4,
			'columns'      => '4',
			'show_excerpt' => 1,
		)
	);

	// 6 — Impact, on the warm band.
	$blocks[] = bgcf_pattern_block(
		'statistics',
		'statistics',
		array_merge(
			array(
				'eyebrow'    => __( 'Measured, not estimated', 'bgcf' ),
				'heading'    => __( 'Where ten years has got us', 'bgcf' ),
				'lead'       => __( 'Figures from our 2025 annual report, independently audited.', 'bgcf' ),
				'background' => 'sand',
			),
			bgcf_pattern_rows(
				'statistics',
				'stats',
				array(
					array(
						'value'    => 1840,
						'decimals' => 0,
						'suffix'   => '',
						'label'    => __( 'Children supported through school', 'bgcf' ),
						'icon'     => 'graduation',
					),
					array(
						'value'    => 96,
						'decimals' => 0,
						'suffix'   => '%',
						'label'    => __( 'Stayed enrolled the following year', 'bgcf' ),
						'icon'     => 'check-circle',
					),
					array(
						'value'    => 27,
						'decimals' => 0,
						'label'    => __( 'Partner schools and clinics', 'bgcf' ),
						'icon'     => 'handshake',
					),
					array(
						'value'    => 83,
						'decimals' => 0,
						'suffix'   => '%',
						'label'    => __( 'Of every shilling reaches programmes', 'bgcf' ),
						'icon'     => 'target',
					),
				)
			)
		)
	);

	// 7 — Second photographic call to action.
	$blocks[] = bgcf_pattern_block(
		'photo-cta',
		'photo_cta',
		array(
			'eyebrow' => __( 'Get involved', 'bgcf' ),
			'heading' => __( 'We need teachers, drivers, builders and listeners', 'bgcf' ),
			'text'    => __( 'Volunteering here is not a week of photographs. It is a term, a skill, and a child who learns your name.', 'bgcf' ),
			'scrim'   => 66,
		)
	);

	// 8 — What support includes, as service cards on the dark band.
	$blocks[] = bgcf_pattern_block(
		'service-cards',
		'service_cards',
		array_merge(
			array(
				'eyebrow'    => __( 'What support actually involves', 'bgcf' ),
				'heading'    => __( 'Five things a sponsored year pays for', 'bgcf' ),
				'lead'       => __( 'Sponsorship is not a transfer of fees. It is a package, and this is what is in it.', 'bgcf' ),
				'background' => 'ink',
				'columns'    => '3',
				'align'      => 'center',
			),
			bgcf_pattern_rows(
				'service_cards',
				'items',
				array(
					array(
						'icon'  => 'graduation',
						'title' => __( 'School fees and materials', 'bgcf' ),
						'text'  => __( 'Termly fees paid directly to the school, plus uniform, shoes and the exercise books that are quietly the reason half of absences happen.', 'bgcf' ),
					),
					array(
						'icon'  => 'health',
						'title' => __( 'Health cover', 'bgcf' ),
						'text'  => __( 'Registration at a partner clinic, so a fever does not become a month out of class or a debt the family cannot clear.', 'bgcf' ),
					),
					array(
						'icon'  => 'users',
						'title' => __( 'A caseworker who knows them', 'bgcf' ),
						'text'  => __( 'One named person, visiting the household each term, holding the whole picture rather than a file.', 'bgcf' ),
					),
					array(
						'icon'  => 'book',
						'title' => __( 'After-school teaching', 'bgcf' ),
						'text'  => __( 'Small-group catch-up in reading and maths, run by teachers from the child’s own school.', 'bgcf' ),
					),
					array(
						'icon'  => 'target',
						'title' => __( 'A plan for after school', 'bgcf' ),
						'text'  => __( 'From sixteen onward: vocational training, an apprenticeship or university, chosen by the young person and funded to completion.', 'bgcf' ),
					),
				)
			)
		)
	);

	// 9 — Partners.
	$blocks[] = bgcf_pattern_block(
		'partners',
		'partners',
		array(
			'heading' => __( 'The people we do this with', 'bgcf' ),
			'lead'    => __( 'Schools, clinics, local government and the funders who let us plan beyond one year at a time.', 'bgcf' ),
		)
	);

	// 10 — News, on the warm band.
	$blocks[] = bgcf_pattern_block(
		'news-cards',
		'news_cards',
		array(
			'eyebrow'    => __( 'From the field', 'bgcf' ),
			'heading'    => __( 'What has been happening', 'bgcf' ),
			'post_type'  => 'post',
			'source'     => 'latest',
			'limit'      => 3,
			'columns'    => '3',
			'background' => 'bloom',
		)
	);

	/**
	 * Filters the homepage pattern's blocks before they are joined.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $blocks Serialised blocks, in band order.
	 */
	$blocks = (array) apply_filters( 'bgcf_pattern_homepage_blocks', $blocks );

	return implode( "\n\n", $blocks );
}

/**
 * The "ways to get involved" pattern, for the Action hub page.
 *
 * @since 2.0.0
 * @return string Serialised blocks.
 */
function bgcf_pattern_take_action(): string {
	$blocks = array();

	$blocks[] = bgcf_pattern_block(
		'action-grid',
		'action_grid',
		array_merge(
			array(
				'heading'    => __( 'Choose how you help', 'bgcf' ),
				'lead'       => __( 'All of these matter, and none of them matters more than the others. Pick the one that fits your life.', 'bgcf' ),
				'background' => 'soft',
				'columns'    => '3',
			),
			bgcf_pattern_rows(
				'action_grid',
				'items',
				array(
					array(
						'eyebrow' => __( 'Monthly', 'bgcf' ),
						'title'   => __( 'Sponsor a child’s year', 'bgcf' ),
						'text'    => __( 'UGX 180,000 a month covers fees, materials, health cover and a caseworker. You get two written updates a year.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Sponsor a child', 'bgcf' ),
							'url'    => '/donate/',
							'target' => '',
						),
					),
					array(
						'eyebrow' => __( 'One-off', 'bgcf' ),
						'title'   => __( 'Give to the fund that needs it', 'bgcf' ),
						'text'    => __( 'Unrestricted gifts are the ones that let us act in the week something goes wrong rather than the quarter after.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Give once', 'bgcf' ),
							'url'    => '/donate/',
							'target' => '',
						),
					),
					array(
						'eyebrow' => __( 'Your time', 'bgcf' ),
						'title'   => __( 'Volunteer a term', 'bgcf' ),
						'text'    => __( 'Teaching, driving, building, bookkeeping, mentoring. We ask for a term because children deserve consistency.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Volunteer with us', 'bgcf' ),
							'url'    => '/volunteer/',
							'target' => '',
						),
					),
					array(
						'eyebrow' => __( 'Your workplace', 'bgcf' ),
						'title'   => __( 'Partner as an organisation', 'bgcf' ),
						'text'    => __( 'Match your staff’s giving, fund a programme outright, or lend us the skills you already pay for.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Talk to us', 'bgcf' ),
							'url'    => '/contact/',
							'target' => '',
						),
					),
					array(
						'eyebrow' => __( 'Your circle', 'bgcf' ),
						'title'   => __( 'Fundraise for us', 'bgcf' ),
						'text'    => __( 'A birthday, a run, a church collection. We will send you everything you need and a figure to aim at.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Start fundraising', 'bgcf' ),
							'url'    => '/contact/',
							'target' => '',
						),
					),
					array(
						'eyebrow' => __( 'Free', 'bgcf' ),
						'title'   => __( 'Tell one person', 'bgcf' ),
						'text'    => __( 'Most of our supporters came because somebody they trust mentioned us. That is not a small thing to offer.', 'bgcf' ),
						'link'    => array(
							'title'  => __( 'Read a story to share', 'bgcf' ),
							'url'    => '/stories/',
							'target' => '',
						),
					),
				)
			)
		)
	);

	$blocks[] = bgcf_pattern_block(
		'photo-cta',
		'photo_cta',
		array(
			'heading' => __( 'Not sure which of these is you?', 'bgcf' ),
			'text'    => __( 'Tell us what you have got — time, money, a skill, a contact — and we will tell you honestly where it goes furthest.', 'bgcf' ),
			'scrim'   => 64,
		)
	);

	return implode( "\n\n", $blocks );
}

/**
 * Register the patterns.
 *
 * @since 2.0.0
 * @return void
 */
function bgcf_register_patterns(): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$patterns = array(
		'bgcf/homepage'    => array(
			'title'       => __( 'Homepage — the full page', 'bgcf' ),
			'description' => __( 'The complete eleven-band homepage: hero, the case for the work, two photographic calls to action, programmes, impact figures, what support includes, partners and recent news. Insert it on the page set as your front page, then replace the copy and add photographs band by band.', 'bgcf' ),
			'content'     => 'bgcf_pattern_homepage',
			'keywords'    => array( 'homepage', 'front page', 'home', 'starter' ),
		),
		'bgcf/take-action' => array(
			'title'       => __( 'Take action — ways to get involved', 'bgcf' ),
			'description' => __( 'A grid of six ways to help, each with its own button, and a closing call to action. Use it on a page set to the Action hub template.', 'bgcf' ),
			'content'     => 'bgcf_pattern_take_action',
			'keywords'    => array( 'action', 'involved', 'volunteer', 'donate', 'starter' ),
		),
	);

	foreach ( $patterns as $name => $pattern ) {
		if ( ! is_callable( $pattern['content'] ) ) {
			continue;
		}

		register_block_pattern(
			$name,
			array(
				'title'       => $pattern['title'],
				'description' => $pattern['description'],
				'categories'  => array( 'bgcf' ),
				'keywords'    => $pattern['keywords'],
				'content'     => call_user_func( $pattern['content'] ),
			)
		);
	}
}
add_action( 'init', 'bgcf_register_patterns', 11 );
