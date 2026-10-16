/**
 * Generates the ACF Pro field groups into `theme/acf-json/`.
 *
 * WHY A GENERATOR. `theme/acf-json/` is the source of truth for field
 * definitions (see inc/acf.php) and ACF rewrites those files whenever an editor
 * saves a group in wp-admin — so they must be real JSON on disk, not PHP calls
 * to `acf_add_local_field_group()`, which ACF renders read-only in the admin UI
 * and which would make every field change a code deploy.
 *
 * Hand-writing them is the problem this file solves: a single text field is
 * ~20 lines of JSON with four keys that must stay unique and stable forever.
 * The definitions below are the readable form; the JSON is the build artifact.
 *
 * Run: `npm run build:acf`
 *
 * IMPORTANT: field and group keys are written into post meta the first time an
 * editor saves. Renaming a key orphans existing content. Add keys; never
 * rename them.
 */

import { mkdirSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(root, 'theme', 'acf-json');

/** ACF stores a modification timestamp; a fixed value keeps the output deterministic. */
const MODIFIED = 1767225600; // 2026-01-01T00:00:00Z

/**
 * Shorthand field constructors.
 *
 * Each returns a partial field; `build()` fills in the key and the defaults
 * ACF expects so the JSON imports cleanly.
 */
const f = {
	text: (name, label, extra = {}) => ({ type: 'text', name, label, ...extra }),
	textarea: (name, label, extra = {}) => ({ type: 'textarea', name, label, rows: 3, new_lines: '', ...extra }),
	wysiwyg: (name, label, extra = {}) => ({ type: 'wysiwyg', name, label, tabs: 'all', toolbar: 'basic', media_upload: 0, ...extra }),
	number: (name, label, extra = {}) => ({ type: 'number', name, label, ...extra }),
	range: (name, label, extra = {}) => ({ type: 'range', name, label, min: 0, max: 100, step: 5, ...extra }),
	truefalse: (name, label, extra = {}) => ({ type: 'true_false', name, label, ui: 1, ...extra }),
	select: (name, label, choices, extra = {}) => ({ type: 'select', name, label, choices, return_format: 'value', ...extra }),
	image: (name, label, extra = {}) => ({ type: 'image', name, label, return_format: 'array', preview_size: 'medium', library: 'all', ...extra }),
	file: (name, label, extra = {}) => ({ type: 'file', name, label, return_format: 'array', library: 'all', ...extra }),
	link: (name, label, extra = {}) => ({ type: 'link', name, label, return_format: 'array', ...extra }),
	email: (name, label, extra = {}) => ({ type: 'email', name, label, ...extra }),
	url: (name, label, extra = {}) => ({ type: 'url', name, label, ...extra }),
	date: (name, label, extra = {}) => ({
		type: 'date_picker',
		name,
		label,
		display_format: 'j F Y',
		return_format: 'Y-m-d',
		first_day: 1,
		...extra
	}),
	datetime: (name, label, extra = {}) => ({
		type: 'date_time_picker',
		name,
		label,
		display_format: 'j F Y g:i a',
		return_format: 'Y-m-d H:i:s',
		first_day: 1,
		...extra
	}),
	post: (name, label, postTypes, extra = {}) => ({
		type: 'post_object',
		name,
		label,
		post_type: postTypes,
		return_format: 'id',
		ui: 1,
		...extra
	}),
	relationship: (name, label, postTypes, extra = {}) => ({
		type: 'relationship',
		name,
		label,
		post_type: postTypes,
		filters: ['search'],
		return_format: 'id',
		...extra
	}),
	taxonomy: (name, label, taxonomy, extra = {}) => ({
		type: 'taxonomy',
		name,
		label,
		taxonomy,
		field_type: 'select',
		add_term: 0,
		save_terms: 0,
		load_terms: 0,
		return_format: 'id',
		multiple: 0,
		allow_null: 1,
		...extra
	}),
	repeater: (name, label, subFields, extra = {}) => ({
		type: 'repeater',
		name,
		label,
		layout: 'block',
		button_label: 'Add row',
		sub_fields: subFields,
		...extra
	}),
	gallery: (name, label, extra = {}) => ({ type: 'gallery', name, label, return_format: 'array', ...extra }),
	page: (name, label, extra = {}) => ({ type: 'page_link', name, label, post_type: ['page'], allow_null: 1, ...extra }),
	message: (name, label, text) => ({ type: 'message', name, label, message: text, new_lines: 'wpautop', esc_html: 0 })
};

/**
 * Band choices shared by every Horizon block.
 *
 * The LABELS describe where a band is used, not what colour it is, because that
 * is the decision an editor is actually making: "the news band" is a more useful
 * prompt than "pale orange", and it keeps a page's rhythm intact when the brand
 * palette changes.
 *
 * This list must stay in step with `bgcf_section_backgrounds()` in
 * inc/template-tags.php and with the `--bg-*` modifiers in
 * assets/scss/components/_section.scss. The legacy `base`/`surface`/`primary`
 * values are still accepted by the PHP but are deliberately NOT offered here —
 * they are aliases, and showing both would mean two ways to pick white.
 */
const bgChoices = {
	page: 'White — the quiet band',
	soft: 'Light grey — the default band',
	sand: 'Warm yellow — the impact band',
	bloom: 'Warm orange — the news band',
	leaf: 'Pale emerald — the programmes band',
	ink: 'Deep blue — a dark band',
	secondary: 'Emerald — a dark band'
};

/**
 * Icon slugs available to editors, matching assets/images/icons/.
 *
 * A closed list rather than a free-text field: a slug with a typo renders
 * nothing, silently, and an editor has no way to see which names are valid.
 * Adding an icon means dropping the SVG into `assets/images/icons/` and adding
 * one line here.
 */
const iconChoices = {
	'': '— none —',
	heart: 'Heart',
	users: 'People',
	graduation: 'Education',
	health: 'Health',
	water: 'Water',
	home: 'Shelter',
	book: 'Books',
	shield: 'Protection',
	handshake: 'Partnership',
	target: 'Goal',
	globe: 'Global',
	'check-circle': 'Tick',
	calendar: 'Calendar',
	'map-pin': 'Location',
	'file-text': 'Document',
	download: 'Download',
	mail: 'Email',
	phone: 'Phone',
	user: 'Person',
	clock: 'Time',
	info: 'Information',
	'arrow-up-right': 'Arrow'
};

/** Column-count choices shared by the card-grid blocks. */
const columnChoices = { 2: 'Two', 3: 'Three', 4: 'Four' };

/**
 * One `location` rule set matching a theme block under either namespace.
 *
 * ACF 6 keeps the explicit `bgcf/` namespace; ACF 5 rewrites it to `acf/`.
 * Two OR'd rules mean the group binds correctly on both without anyone having
 * to check which version is installed. See the namespace note in inc/blocks.php.
 *
 * @param {string} slug Block slug without namespace.
 * @returns {Array} ACF location rules.
 */
const blockLocation = (slug) => [
	[{ param: 'block', operator: '==', value: `bgcf/${slug}` }],
	[{ param: 'block', operator: '==', value: `acf/${slug}` }]
];

/** @param {string} slug Options page menu slug. */
const optionsLocation = (slug) => [[{ param: 'options_page', operator: '==', value: slug }]];

/** @param {string} postType Post type key. */
const postTypeLocation = (postType) => [[{ param: 'post_type', operator: '==', value: postType }]];

/**
 * The field groups.
 *
 * `id` becomes the group key (`group_bgcf_<id>`) and the filename, and prefixes
 * every field key inside it. Keep them stable.
 */
const groups = [
	/* ------------------------------------------------------------------ Blocks */
	{
		id: 'hero',
		title: 'Block — Hero',
		location: blockLocation('hero'),
		description: 'The full-width opening section. The background image is the page’s largest element, so use a wide, well-lit photo at least 1920px across.',
		fields: [
			f.image('background_image', 'Background image', {
				instructions: 'Landscape, at least 1920×1080. Faces should sit in the right half — the headline covers the left.'
			}),
			f.range('overlay_opacity', 'Overlay darkness', {
				default_value: 55,
				min: 25,
				max: 85,
				instructions: 'How much to darken the photo so the headline stays readable. Below 40% white text usually fails contrast.',
				append: '%'
			}),
			f.text('eyebrow', 'Eyebrow', { instructions: 'Short label above the headline, e.g. “Since 2014”.' }),
			f.text('heading', 'Headline', { required: 1 }),
			f.text('heading_emphasis', 'Phrase to emphasise', {
				instructions:
					'Optional. Type one phrase from the headline above, exactly as it appears there, and it will be set apart in the brand yellow. Leave empty for a plain headline.'
			}),
			f.select(
				'heading_level',
				'Headline level',
				{ h1: 'H1 — use on the homepage', h2: 'H2 — use on inner pages' },
				{
					default_value: 'h1',
					instructions: 'Every page needs exactly one H1. If this hero sits on a page whose title is already the H1, choose H2.'
				}
			),
			f.textarea('lead', 'Supporting text', { rows: 3 }),
			f.link('primary_cta', 'Primary button'),
			f.link('secondary_cta', 'Secondary button'),
			f.repeater(
				'stats',
				'Headline figures',
				[f.text('value', 'Figure'), f.text('label', 'Label')],
				{ max: 3, instructions: 'Up to three short proof points shown under the buttons.' }
			),
			f.repeater(
				'floating_cards',
				'Highlight cards',
				[f.select('icon', 'Icon', iconChoices), f.text('title', 'Title'), f.text('text', 'Text')],
				{ max: 3 }
			)
		]
	},
	{
		id: 'statistics',
		title: 'Block — Statistics',
		location: blockLocation('statistics'),
		description: 'A band of impact counters. Figures animate on scroll; the real number is always in the page, so nothing depends on the animation.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('background', 'Band colour', bgChoices, { default_value: 'sand' }),
			f.repeater(
				'stats',
				'Figures',
				[
					f.number('value', 'Number', { required: 1, instructions: 'Digits only. Use the prefix and suffix fields for anything else.' }),
					f.number('decimals', 'Decimal places', { default_value: 0, min: 0, max: 2 }),
					f.text('prefix', 'Prefix', { instructions: 'e.g. “UGX” or “+”.' }),
					f.text('suffix', 'Suffix', { instructions: 'e.g. “%”, “k” or “+”.' }),
					f.text('label', 'Label', { required: 1 }),
					f.select('icon', 'Icon', iconChoices)
				],
				{ min: 2, max: 6, required: 1 }
			)
		]
	},
	{
		id: 'cta',
		title: 'Block — Call to Action',
		location: blockLocation('cta'),
		description: 'A conversion band for anything that is not a donation ask — volunteering, partnering, downloading a report.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading', { required: 1 }),
			f.textarea('text', 'Supporting text'),
			f.select(
				'background',
				'Background',
				{ secondary: 'Emerald green', primary: 'Deep blue', surface: 'Light grey' },
				{ default_value: 'secondary' }
			),
			f.select('layout', 'Layout', { centered: 'Centred', split: 'Image beside text' }, { default_value: 'centered' }),
			f.image('image', 'Image', {
				conditional_logic: [[{ field: 'layout', operator: '==', value: 'split' }]]
			}),
			f.link('primary_cta', 'Primary button'),
			f.link('secondary_cta', 'Secondary button')
		]
	},
	{
		id: 'donation_banner',
		title: 'Block — Donation Banner',
		location: blockLocation('donation-banner'),
		description: 'The giving form. Amounts, payment methods and limits come from Donations → Settings, not from this block — so every form on the site offers the same options.',
		fields: [
			f.message(
				'donation_notice',
				'Before you start',
				'Suggested amounts, payment methods, the processing-fee option and the thank-you page are configured once under **Donations → Settings**. Progress figures are calculated from real donations and cannot be typed in.'
			),
			f.text('heading', 'Heading'),
			f.textarea('text', 'Supporting text'),
			f.image('background_image', 'Background image'),
			f.post('campaign', 'Campaign', ['bgcf_campaign'], {
				allow_null: 1,
				instructions: 'Attributes gifts to a campaign and shows its progress bar. Leave empty for general giving.'
			}),
			f.truefalse('show_form', 'Show the giving form here', {
				default_value: 1,
				instructions: 'Off shows a button to your donate page instead — useful on pages where a full form is too much.'
			}),
			f.link('donate_cta', 'Donate button', {
				conditional_logic: [[{ field: 'show_form', operator: '==', value: '0' }]]
			})
		]
	},
	{
		id: 'program_cards',
		title: 'Block — Program Cards',
		location: blockLocation('program-cards'),
		description: 'Programmes as cards. The card text is each programme’s own one-line summary; the programme’s location appears under it.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('background', 'Band colour', bgChoices, { default_value: 'page' }),
			f.select(
				'source',
				'Which programmes?',
				{ latest: 'Most recent', manual: 'Ones I choose', term: 'All in a category' },
				{ default_value: 'latest' }
			),
			f.relationship('programs', 'Programmes', ['bgcf_program'], {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'manual' }]],
				instructions: 'Drag to reorder. The order here is the order on the page.'
			}),
			f.taxonomy('category', 'Category', 'bgcf_program_category', {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'term' }]]
			}),
			f.number('limit', 'How many to show', {
				default_value: 3,
				min: 1,
				max: 12,
				conditional_logic: [[{ field: 'source', operator: '!=', value: 'manual' }]]
			}),
			f.select('columns', 'Columns', columnChoices, { default_value: '3' }),
			f.truefalse('show_excerpt', 'Show the summary text', { default_value: 1 }),
			f.link('archive_cta', 'Link to all programmes')
		]
	},
	{
		id: 'gallery',
		title: 'Block — Gallery',
		location: blockLocation('gallery'),
		description: 'Photos come from the Media library, grouped by album. Upload there, tick an album, write the alt text, and confirm publication consent.',
		fields: [
			f.message(
				'gallery_notice',
				'Adding photos',
				'Photos are not uploaded into this block. Upload them under **Media**, tick an **Album** on each one, fill in the **Alternative text**, and tick **Publication consent**. Images without consent are never shown publicly.'
			),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.taxonomy('album', 'Show one album only', 'bgcf_album', {
				instructions: 'Leave empty to show every album, with filter buttons.'
			}),
			f.select('columns', 'Columns', { 2: 'Two', 3: 'Three', 4: 'Four' }, { default_value: '3' }),
			f.number('per_page', 'Photos per page', { default_value: 12, min: 4, max: 48 }),
			f.truefalse('enable_filters', 'Show album filters', { default_value: 1 }),
			f.truefalse('enable_lightbox', 'Open photos in a lightbox', { default_value: 1 })
		]
	},
	{
		id: 'testimonials',
		title: 'Block — Testimonials',
		location: blockLocation('testimonials'),
		fields: [
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('source', 'Which quotes?', { latest: 'Most recent', manual: 'Ones I choose' }, { default_value: 'latest' }),
			f.relationship('testimonials', 'Quotes', ['bgcf_testimonial'], {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'manual' }]]
			}),
			f.number('limit', 'How many to show', { default_value: 6, min: 1, max: 24 }),
			f.select('layout', 'Layout', { carousel: 'Carousel', grid: 'Grid' }, { default_value: 'carousel' }),
			f.truefalse('show_rating', 'Show star ratings', { default_value: 0 }),
			f.truefalse('autoplay', 'Advance slides automatically', {
				default_value: 0,
				instructions: 'Adds a pause button, and never moves for visitors who have asked for reduced motion.',
				conditional_logic: [[{ field: 'layout', operator: '==', value: 'carousel' }]]
			})
		]
	},
	{
		id: 'timeline',
		title: 'Block — Timeline',
		location: blockLocation('timeline'),
		fields: [
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('orientation', 'Direction', { vertical: 'Vertical', horizontal: 'Horizontal' }, { default_value: 'vertical' }),
			f.repeater(
				'milestones',
				'Milestones',
				[f.text('year', 'Year'), f.text('title', 'Title', { required: 1 }), f.textarea('text', 'Description'), f.image('image', 'Image')],
				{ min: 2, required: 1 }
			)
		]
	},
	{
		id: 'partners',
		title: 'Block — Partners',
		location: blockLocation('partners'),
		fields: [
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('layout', 'Layout', { grid: 'Grid', marquee: 'Scrolling row' }, { default_value: 'grid' }),
			f.taxonomy('partner_type', 'Partner type', 'bgcf_partner_type'),
			f.number('limit', 'How many to show', { default_value: 12, min: 1, max: 36 }),
			f.truefalse('grayscale', 'Show logos in grey until hovered', { default_value: 1 })
		]
	},
	{
		id: 'faq',
		title: 'Block — FAQ',
		location: blockLocation('faq'),
		fields: [
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.repeater(
				'items',
				'Questions',
				[f.text('question', 'Question', { required: 1 }), f.wysiwyg('answer', 'Answer', { required: 1 })],
				{ min: 1, required: 1 }
			),
			f.number('default_open', 'Open this question by default', {
				default_value: 0,
				min: 0,
				instructions: '0 opens none. 1 opens the first question.'
			}),
			f.truefalse('single', 'Only one answer open at a time', { default_value: 0 }),
			f.truefalse('schema', 'Add FAQ structured data', {
				default_value: 1,
				instructions: 'Lets search engines show these answers directly. Only switch off if another plugin already does it for this page.'
			})
		]
	},
	{
		id: 'newsletter',
		title: 'Block — Newsletter',
		location: blockLocation('newsletter'),
		fields: [
			f.text('heading', 'Heading', { required: 1 }),
			f.textarea('text', 'Supporting text'),
			f.select('background', 'Background', { surface: 'Light grey', primary: 'Deep blue' }, { default_value: 'surface' }),
			f.text('consent_text', 'Consent wording', {
				instructions: 'Shown beside a required, unticked checkbox. Leave empty to use the site default.'
			}),
			f.text('submit_label', 'Button label'),
			f.link('privacy_link', 'Privacy policy link')
		]
	},

	/* --------------------------------------------------------- Horizon bands */
	{
		id: 'split_section',
		title: 'Block — Split Section',
		location: blockLocation('split-section'),
		description:
			'Image on one side, words on the other. Below tablet width the columns stack and the image always goes on top, whichever side it sits on at desktop width.',
		fields: [
			f.text('eyebrow', 'Eyebrow', { instructions: 'A short label above the heading. Optional.' }),
			f.text('heading', 'Heading', { required: 1 }),
			f.wysiwyg('text', 'Text'),
			f.image('image', 'Image', {
				instructions:
					'Portrait or landscape — the image keeps its own shape. At least 1200px on its longest side. Leave empty for a single centred column of text.'
			}),
			f.select('media_side', 'Image side (desktop)', { start: 'Left', end: 'Right' }, { default_value: 'start' }),
			f.select(
				'ratio',
				'Column balance',
				{ even: 'Equal halves', text: 'Wider text column', media: 'Wider image column' },
				{ default_value: 'even' }
			),
			f.select('background', 'Band colour', bgChoices, { default_value: 'soft' }),
			f.link('link', 'Button'),
			f.select('link_style', 'Button style', { primary: 'Orange', secondary: 'Deep blue' }, { default_value: 'primary' }),
			f.message(
				'cards_note',
				'Cards beside the text',
				'Optional. Adds a small grid of icon cards under the text — use it for the "tall image beside four cards" arrangement.'
			),
			f.repeater(
				'cards',
				'Cards',
				[
					f.select('icon', 'Icon', iconChoices),
					f.text('title', 'Title', { required: 1 }),
					f.textarea('text', 'One line about it', { rows: 2 })
				],
				{ max: 6, button_label: 'Add card' }
			),
			f.select('card_cols', 'Cards per row', { 1: 'One', 2: 'Two' }, { default_value: 2 })
		]
	},
	{
		id: 'photo_cta',
		title: 'Block — Photo CTA',
		location: blockLocation('photo-cta'),
		description:
			'A photograph behind a deep-blue wash, with a centred heading and a button. The wash is what keeps the white text readable, so any well-composed photo works.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading', { required: 1 }),
			f.textarea('text', 'One supporting sentence', { rows: 2 }),
			f.image('image', 'Background photograph', {
				instructions: 'Landscape, at least 1920px across. It is cropped to a short band, so keep the subject near the centre.'
			}),
			f.link('link', 'Button'),
			f.link('link_alt', 'Second button', { instructions: 'Optional, lower emphasis.' }),
			f.range('scrim', 'How dark the wash is', {
				min: 55,
				max: 85,
				step: 5,
				default_value: 62,
				append: '%',
				instructions: 'Raise it for a busy or pale photograph. It cannot go below 55% — that is the point where white text stops being readable.'
			})
		]
	},
	{
		id: 'service_cards',
		title: 'Block — Service Cards',
		location: blockLocation('service-cards'),
		description: 'A grid of text-only cards. Designed for a dark band — on a white band the cards have very little to stand out against.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Intro paragraph', { rows: 2 }),
			f.select('background', 'Band colour', bgChoices, { default_value: 'ink' }),
			f.select('columns', 'Cards per row', columnChoices, { default_value: 3 }),
			f.select('align', 'Heading alignment', { center: 'Centred', start: 'Left' }, { default_value: 'center' }),
			f.repeater(
				'items',
				'Cards',
				[
					f.select('icon', 'Icon', iconChoices),
					f.text('title', 'Title', { required: 1 }),
					f.textarea('text', 'Description', { rows: 3 }),
					f.link('link', 'Link', { instructions: 'Optional. The link text you type becomes the card’s link label.' })
				],
				{ min: 1, max: 12, button_label: 'Add card' }
			)
		]
	},
	{
		id: 'news_cards',
		title: 'Block — News Cards',
		location: blockLocation('news-cards'),
		description: 'Recent posts, success stories or events as dated cards.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Intro paragraph', { rows: 2 }),
			f.select(
				'post_type',
				'Show',
				{ post: 'Blog posts', bgcf_story: 'Success stories', bgcf_event: 'Events' },
				{ default_value: 'post' }
			),
			f.select(
				'source',
				'Which items',
				{ latest: 'The most recent', manual: 'Ones I pick', term: 'Everything in one category' },
				{ default_value: 'latest' }
			),
			f.relationship('posts', 'Pick the items', ['post', 'bgcf_story', 'bgcf_event'], {
				max: 12,
				conditional_logic: [[{ field: 'source', operator: '==', value: 'manual' }]]
			}),
			f.taxonomy('term', 'Category', 'category', {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'term' }]]
			}),
			f.number('limit', 'How many', {
				default_value: 3,
				min: 1,
				max: 12,
				conditional_logic: [[{ field: 'source', operator: '!=', value: 'manual' }]]
			}),
			f.select('columns', 'Cards per row', columnChoices, { default_value: 3 }),
			f.select('background', 'Band colour', bgChoices, { default_value: 'bloom' }),
			f.link('archive_cta', 'Button under the grid', { instructions: 'Usually a link to the full list, e.g. “All news”.' })
		]
	},
	{
		id: 'action_grid',
		title: 'Block — Action Grid',
		location: blockLocation('action-grid'),
		description:
			'Ways to get involved, each with its own button. This is the one block where every card is a primary action — that is what the Take Action page is for.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Intro paragraph', { rows: 2 }),
			f.select('background', 'Band colour', bgChoices, { default_value: 'soft' }),
			f.select('columns', 'Cards per row', columnChoices, { default_value: 3 }),
			f.repeater(
				'items',
				'Ways to help',
				[
					f.image('image', 'Image', { instructions: '4:3 landscape, at least 1200×900.' }),
					f.text('eyebrow', 'Small label'),
					f.text('title', 'Title', { required: 1 }),
					f.textarea('text', 'One or two sentences', { rows: 3 }),
					f.link('link', 'Button', { required: 1, instructions: 'The link text you type becomes the button label.' })
				],
				{ min: 1, max: 12, button_label: 'Add a way to help' }
			)
		]
	},
	{
		id: 'team_grid',
		title: 'Block — Team Grid',
		location: blockLocation('team-grid'),
		description: 'People from the Team section, as portrait cards. Grouping uses the Department taxonomy and follows the order you arranged the terms in.',
		fields: [
			f.text('eyebrow', 'Eyebrow'),
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Intro paragraph', { rows: 2 }),
			f.select('background', 'Band colour', bgChoices, { default_value: 'page' }),
			f.truefalse('grouped', 'Group by department', {
				default_value: 1,
				instructions: 'Off shows one flat directory. If no departments have been set up yet, this falls back to a flat directory automatically.'
			}),
			f.select('columns', 'Cards per row', { 3: 'Three', 4: 'Four' }, { default_value: 4 }),
			f.number('limit', 'How many (flat mode)', {
				default_value: 24,
				min: 1,
				max: 60,
				conditional_logic: [[{ field: 'grouped', operator: '!=', value: 1 }]]
			}),
			f.truefalse('show_bio', 'Show the one-line bio', { default_value: 0 })
		]
	},

	/* ----------------------------------------------------------- Options pages */
	{
		id: 'options_header',
		title: 'Options — Header',
		location: optionsLocation('bgcf-header'),
		fields: [
			f.link('header_primary_cta', 'Donate button', { instructions: 'The main action in the header. Usually “Donate”.' }),
			f.link('header_secondary_cta', 'Secondary button', { instructions: 'Optional, e.g. “Volunteer”. Hidden on the narrowest phones.' }),
			f.truefalse('header_show_search', 'Show the search button', { default_value: 1 })
		]
	},
	{
		id: 'options_footer',
		title: 'Options — Footer',
		location: optionsLocation('bgcf-footer'),
		fields: [
			f.wysiwyg('footer_intro', 'About this organisation', { instructions: 'A short paragraph under the logo.' }),
			f.text('footer_quick_title', 'Quick links heading'),
			f.text('footer_programs_title', 'Programs heading'),
			f.text('footer_contact_title', 'Contact heading'),
			f.truefalse('footer_show_newsletter', 'Show the newsletter sign-up', { default_value: 1 }),
			f.text('footer_newsletter_title', 'Newsletter heading'),
			f.text('footer_newsletter_text', 'Newsletter blurb'),
			f.text('copyright', 'Copyright line', { instructions: 'Leave empty for an automatic line with the current year.' }),
			f.text('footer_registration', 'Registration line', {
				instructions: 'Your NGO registration or charity number, shown beside the copyright. E.g. “Registered NGO No. …”.'
			}),
			f.message(
				'badges_note',
				'Accreditation badges',
				'Logos of the bodies you are registered with or accredited by. They are shown at their own proportions on a white plate, never recoloured or cropped — most accrediting bodies’ brand rules require that.'
			),
			f.repeater(
				'footer_badges',
				'Badges',
				[
					f.image('image', 'Logo', { required: 1, instructions: 'SVG preferred, or a PNG at least 720px wide with a transparent background.' }),
					f.text('name', 'Organisation name', {
						required: 1,
						instructions: 'Used as the image’s alt text, so write the name exactly — never the word “logo”.'
					}),
					f.url('url', 'Link', { instructions: 'Optional. Opens in a new tab.' })
				],
				{ max: 8, button_label: 'Add badge' }
			)
		]
	},
	{
		id: 'options_promo',
		title: 'Options — Announcement',
		location: optionsLocation('bgcf-promo'),
		fields: [
			f.message(
				'promo_note',
				'How this works',
				'A small dialog that appears a few seconds after the page loads — for an appeal, a campaign launch or an event. It shows once per visit and is dismissed for the rest of that visit. Clear the heading, or switch it off, and nothing is shown at all.'
			),
			f.truefalse('promo_enabled', 'Show the announcement', { default_value: 0 }),
			f.text('promo_title', 'Heading', { instructions: 'Required — with no heading, nothing is shown.' }),
			f.wysiwyg('promo_text', 'Text', { instructions: 'Two or three sentences at most.' }),
			f.image('promo_image', 'Image', { instructions: 'Optional. 16:9 landscape, at least 1200×675.' }),
			f.link('promo_link', 'Button'),
			f.range('promo_delay', 'Wait before showing', {
				min: 0,
				max: 20,
				step: 1,
				default_value: 4,
				append: ' seconds',
				instructions: 'A few seconds lets a visitor read the page first. Showing it instantly interrupts screen-reader users mid-sentence.'
			}),
			f.date('promo_until', 'Stop showing after', {
				instructions: 'Optional. The announcement disappears on its own after this date, so an appeal that has closed does not keep announcing itself.'
			})
		]
	},

	/* ---------------------------------------------------------- Navigation */
	{
		id: 'nav_item',
		title: 'Menu item — icon',
		location: [[{ param: 'nav_menu_item', operator: '==', value: 'all' }]],
		description: 'Icons appear in the header’s drop-down panels, beside each item’s title and description.',
		style: 'seamless',
		fields: [
			f.select('menu_icon', 'Panel icon', iconChoices, {
				instructions:
					'Shown in the header drop-down panel. The one-line description beside it comes from this item’s own Description field — turn that on under Screen Options at the top of this page if you cannot see it.'
			})
		]
	},
	{
		id: 'options_contact',
		title: 'Options — Contact details',
		location: optionsLocation('bgcf-global-ctas'),
		fields: [
			f.textarea('contact_address', 'Postal address', { rows: 3 }),
			f.text('contact_phone', 'Phone number', { instructions: 'Shown as a tap-to-call link on phones.' }),
			f.email('contact_email', 'Email address'),
			f.text('contact_hours', 'Opening hours'),
			f.text('contact_map_embed', 'Google Maps place query', {
				instructions: 'The place name or coordinates to centre the map on, e.g. “Boundless Gracia, Kampala”.'
			}),
			f.link('error_404_cta', '“Page not found” button', { instructions: 'A second route back for visitors who hit a dead link.' }),
			f.image('error_404_image', '“Page not found” photograph', {
				instructions: 'Optional. Landscape, at least 1920px across. Without one the page opens on a flat deep-blue band, which still works.'
			}),
			f.message(
				'listing_note',
				'Listing pages',
				'These two apply to every list on the site — news, programmes, stories, search results and category pages — so they only have to be set once.'
			),
			f.image('archive_banner_image', 'Listing page banner', {
				instructions: 'Optional. The photograph behind the title on list pages. Without one they open on a flat deep-blue band.'
			}),
			f.image('listing_cta_image', 'Listing page closing band', {
				instructions: 'Optional. The photograph behind the call to action at the foot of every list page.'
			}),
			f.link('privacy_link', 'Privacy policy'),
			f.email('notify_email', 'Send form notifications to', {
				instructions: 'Volunteer applications and contact messages are emailed here. Defaults to the site admin address.'
			})
		]
	},
	{
		id: 'options_seo',
		title: 'Options — SEO defaults',
		location: optionsLocation('bgcf-seo-defaults'),
		fields: [
			f.textarea('seo_default_description', 'Default description', {
				rows: 2,
				instructions: 'Used where a page has no description of its own. Around 155 characters.'
			}),
			f.image('seo_default_image', 'Default share image', { instructions: '1200×630 works everywhere.' }),
			f.image('default_featured_image', 'Fallback featured image', {
				instructions: 'Shown on cards for posts with no image of their own.'
			}),
			f.text('twitter_handle', 'X / Twitter handle', { instructions: 'Without the @.' }),
			f.textarea('org_description', 'Organisation description', {
				rows: 2,
				instructions: 'Used in structured data for search engines and AI assistants.'
			}),
			f.text('org_registration_number', 'Charity registration number', {
				instructions: 'Published in structured data as a trust signal. Leave empty to omit.'
			}),
			f.text('org_country', 'Country code', { default_value: 'UG', instructions: 'Two letters, e.g. UG.' }),
			f.truefalse('gallery_require_consent', 'Require consent before publishing a photo', {
				default_value: 1,
				instructions: 'Strongly recommended. Hides any gallery image not marked as consent-cleared in the Media library.'
			})
		]
	},

	/* ------------------------------------------------------------------- Pages */
	{
		id: 'page_masthead',
		title: 'Page masthead',
		location: postTypeLocation('page'),
		description:
			'The band at the top of the page. The title and the photograph come from the page itself — these two fields are the label above the title and the sentence below it.',
		fields: [
			f.text('page_eyebrow', 'Label above the title', { instructions: 'Optional. Two or three words, e.g. “Our work” or “Get involved”.' }),
			f.textarea('page_lead', 'Sentence below the title', {
				rows: 2,
				instructions: 'Optional. One or two sentences. This is also what search engines and social networks show if no SEO description is set.'
			}),
			f.message(
				'masthead_note',
				'The photograph',
				'Set the page’s Featured image to put a photograph behind the title. Without one the band is flat deep blue, which is a supported look rather than a fallback. Landscape, at least 1920px across.'
			)
		]
	},

	/* -------------------------------------------------------------- Post types */
	{
		id: 'program',
		title: 'Programme details',
		location: postTypeLocation('bgcf_program'),
		fields: [
			f.select('icon', 'Icon', iconChoices),
			f.text('summary', 'One-line summary', { instructions: 'Used on cards and in search results.' }),
			f.text('location', 'Where it runs'),
			f.repeater('outcomes', 'Outcomes', [f.text('value', 'Figure'), f.text('label', 'What it measures')], { max: 4 }),
			f.wysiwyg('approach', 'How it works'),
			f.post('campaign', 'Linked campaign', ['bgcf_campaign'], { allow_null: 1 }),
			f.link('cta', 'Call to action')
		]
	},
	{
		id: 'event',
		title: 'Event details',
		location: postTypeLocation('bgcf_event'),
		fields: [
			f.datetime('start_date', 'Starts', { required: 1 }),
			f.datetime('end_date', 'Ends'),
			f.text('venue', 'Venue', { instructions: 'Leave empty for an online event.' }),
			f.text('address', 'Address'),
			f.url('register_url', 'Registration link'),
			f.truefalse('is_free', 'Free to attend', { default_value: 1 }),
			f.number('price', 'Ticket price', { conditional_logic: [[{ field: 'is_free', operator: '==', value: '0' }]] })
		]
	},
	{
		id: 'campaign',
		title: 'Campaign details',
		location: postTypeLocation('bgcf_campaign'),
		description: 'The amount raised is calculated from real donations. There is deliberately no field for it.',
		fields: [
			f.message(
				'campaign_notice',
				'About the total raised',
				'The progress bar is calculated from completed donations attributed to this campaign. It updates itself — there is no field to type a total into, because a typed total goes stale and overstates what has actually been received.'
			),
			f.number('goal', 'Fundraising goal', { instructions: 'Whole shillings, no separators. Leave empty to hide the progress bar.' }),
			f.date('deadline', 'Deadline'),
			f.text('summary', 'One-line summary'),
			f.wysiwyg('impact', 'What the money does')
		]
	},
	{
		id: 'story',
		title: 'Story details',
		location: postTypeLocation('bgcf_story'),
		description: 'Success stories. Never publish an identifiable child without documented guardian consent.',
		fields: [
			f.message(
				'story_notice',
				'Safeguarding',
				'Do not publish a child’s full name, school, village or an identifiable photograph without documented guardian consent. Use a first name or a pseudonym, and record the consent reference below.'
			),
			f.text('person_name', 'Name used in the story', { instructions: 'A first name or pseudonym.' }),
			f.truefalse('is_pseudonym', 'This is a pseudonym', { default_value: 0 }),
			f.text('consent_reference', 'Consent record reference', { required: 1, instructions: 'Where the signed guardian consent is filed.' }),
			f.text('age_range', 'Age range', { instructions: 'A range rather than an exact age, e.g. “9–11”.' }),
			f.post('program', 'Related programme', ['bgcf_program'], { allow_null: 1 }),
			f.textarea('pull_quote', 'Pull quote', { rows: 2 })
		]
	},
	{
		id: 'team',
		title: 'Team member details',
		location: postTypeLocation('bgcf_team'),
		fields: [
			f.text('role', 'Role', { required: 1 }),
			f.textarea('bio', 'Short biography', { rows: 4 }),
			f.email('email', 'Email address'),
			f.url('linkedin', 'LinkedIn profile')
		]
	},
	{
		id: 'partner',
		title: 'Partner details',
		location: postTypeLocation('bgcf_partner'),
		fields: [
			f.url('website', 'Website'),
			f.textarea('summary', 'What we do together', { rows: 3 }),
			f.text('since', 'Partners since')
		]
	},
	{
		id: 'testimonial',
		title: 'Testimonial details',
		location: postTypeLocation('bgcf_testimonial'),
		fields: [
			f.text('role', 'Who they are', { instructions: 'e.g. “Parent, Kampala” or “Volunteer since 2021”.' }),
			f.number('rating', 'Rating out of 5', { min: 1, max: 5 }),
			f.text('consent_reference', 'Consent record reference')
		]
	},
	{
		id: 'report',
		title: 'Report details',
		location: postTypeLocation('bgcf_report'),
		fields: [
			f.file('document', 'The document', { required: 1, mime_types: 'pdf', instructions: 'PDF only.' }),
			f.taxonomy('year', 'Year', 'bgcf_report_year'),
			f.textarea('summary', 'What it covers', { rows: 3 }),
			f.number('pages', 'Page count')
		]
	}
];

/**
 * Rewrite conditional-logic field names into field keys.
 *
 * @param {Array|number|undefined} logic  Authored rule groups, or 0.
 * @param {string}                 prefix Key prefix of the owning group.
 * @returns {Array|number} ACF conditional logic.
 */
function resolveConditions(logic, prefix) {
	if (!Array.isArray(logic)) {
		return 0;
	}

	return logic.map((group) =>
		group.map((rule) => ({
			...rule,
			field: rule.field.startsWith('field_') ? rule.field : `field_bgcf_${prefix}_${rule.field}`
		}))
	);
}

/**
 * Fill in the keys and the defaults ACF expects.
 *
 * @param {Object} field  Field definition.
 * @param {string} prefix Key prefix.
 * @returns {Object} Complete ACF field.
 */
function buildField(field, prefix) {
	const key = `field_bgcf_${prefix}_${field.name}`;

	const complete = {
		key,
		label: field.label ?? '',
		name: field.name,
		aria_label: '',
		type: field.type,
		instructions: field.instructions ?? '',
		required: field.required ?? 0,
		conditional_logic: 0,
		wrapper: { width: '', class: '', id: '' },
		...field
	};

	/*
	 * After the spread, not before: `...field` would otherwise put the authored
	 * value back. Conditional logic is written against field NAMES for
	 * readability, but ACF matches on field KEYS at runtime.
	 */
	complete.conditional_logic = resolveConditions(field.conditional_logic, prefix);

	delete complete.sub_fields;

	if (Array.isArray(field.sub_fields)) {
		complete.sub_fields = field.sub_fields.map((sub) => buildField(sub, `${prefix}_${field.name}`));
	}

	return complete;
}

mkdirSync(outDir, { recursive: true });

// Remove previously generated files so a renamed group cannot linger and
// double up with its replacement in the admin UI.
for (const entry of readdirSync(outDir)) {
	if (entry.startsWith('group_bgcf_') && entry.endsWith('.json')) {
		unlinkSync(join(outDir, entry));
	}
}

let count = 0;

for (const group of groups) {
	const key = `group_bgcf_${group.id}`;

	const json = {
		key,
		title: group.title,
		fields: group.fields.map((field) => buildField(field, group.id)),
		location: group.location,
		menu_order: 0,
		position: 'normal',
		style: 'default',
		label_placement: 'top',
		instruction_placement: 'label',
		hide_on_screen: '',
		active: true,
		description: group.description ?? '',
		show_in_rest: 0,
		modified: MODIFIED
	};

	writeFileSync(join(outDir, `${key}.json`), `${JSON.stringify(json, null, 4)}\n`, 'utf8');
	count += 1;
}

process.stdout.write(`Wrote ${count} ACF field groups to theme/acf-json/\n`);
