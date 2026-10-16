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

/** Background choices shared by several blocks. */
const bgChoices = {
	base: 'White',
	surface: 'Light grey',
	primary: 'Deep blue',
	secondary: 'Emerald green'
};

/** Icon slugs available to editors, matching assets/images/icons/. */
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
	'check-circle': 'Tick'
};

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
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select('background', 'Background', bgChoices, { default_value: 'primary' }),
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
		fields: [
			f.text('heading', 'Heading'),
			f.textarea('lead', 'Supporting text'),
			f.select(
				'source',
				'Which programmes?',
				{ latest: 'Most recent', manual: 'Ones I choose', category: 'All in a category' },
				{ default_value: 'latest' }
			),
			f.relationship('programs', 'Programmes', ['bgcf_program'], {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'manual' }]],
				instructions: 'Drag to reorder. The order here is the order on the page.'
			}),
			f.taxonomy('category', 'Category', 'bgcf_program_category', {
				conditional_logic: [[{ field: 'source', operator: '==', value: 'category' }]]
			}),
			f.number('limit', 'How many to show', { default_value: 3, min: 1, max: 12 }),
			f.select('columns', 'Columns', { 2: 'Two', 3: 'Three', 4: 'Four' }, { default_value: '3' }),
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
			f.text('copyright', 'Copyright line', { instructions: 'Leave empty for an automatic line with the current year.' })
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
