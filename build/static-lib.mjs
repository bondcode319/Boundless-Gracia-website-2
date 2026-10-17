/**
 * Shared data and markup helpers for the static Vercel build.
 *
 * CONTRACT. Nothing here invents a style. The static site is painted by
 * `theme/assets/css/main.css`, copied verbatim, so every helper below emits
 * the BEM classes that stylesheet already defines. If a class is not in that
 * stylesheet it does not belong in this file.
 *
 * @package Bgcf
 */

import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
export const THEME = join( ROOT, 'theme' );
export const OUT = join( ROOT, 'public' );

export const BASE_URL = 'https://boundlessgracia.org';

/*
 * PLACEHOLDERS. Every value in this object is invented and must be replaced
 * before launch — see ASSUMPTIONS.md §12. It is deliberately specific rather
 * than lorem ipsum so an editor can see what each field is for.
 */
export const ORG = {
	name: 'Boundless Gracia Children Foundation',
	short: 'Boundless Gracia',
	tagline: 'Every child deserves a future they can reach',
	founded: 2014,
	address: [ 'Plot 14, Hoima Road', 'Nansana, Wakiso District', 'Kampala, Uganda' ],
	phone: '+256 772 000 000',
	phoneHref: '+256772000000',
	email: 'hello@boundlessgracia.org',
	regNo: 'NGO Bureau Permit No. INDR000000000',
	hours: 'Monday to Friday, 8:30am – 5:00pm EAT',
	social: [
		{ label: 'Facebook', icon: 'facebook', url: '#' },
		{ label: 'Instagram', icon: 'instagram', url: '#' },
		{ label: 'LinkedIn', icon: 'linkedin', url: '#' },
	],
};

export const NAV = [
	{ label: 'Home', url: '/' },
	{ label: 'About us', url: '/about/' },
	{ label: 'Programmes', url: '/programs/' },
	{ label: 'Stories', url: '/stories/' },
	{ label: 'Events', url: '/events/' },
	{ label: 'Get involved', url: '/get-involved/' },
	{ label: 'Contact', url: '/contact/' },
];

export const FOOTER_PROGRAMS = [
	{ label: 'Education & scholarships', url: '/programs/#education' },
	{ label: 'Health & nutrition', url: '/programs/#health' },
	{ label: 'Child protection', url: '/programs/#protection' },
	{ label: 'Skills & livelihoods', url: '/programs/#skills' },
];

/**
 * Escape for HTML text or a double-quoted attribute.
 *
 * @param {unknown} value Raw value.
 * @returns {string} Escaped string.
 */
export const esc = ( value ) =>
	String( value )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );

const icons = new Map();

/**
 * Read the theme's SVG icon set into memory once.
 *
 * @returns {Promise<void>}
 */
export async function loadIcons() {
	const names = [
		'heart', 'menu', 'close', 'search', 'chevron-down', 'chevron-right',
		'arrow-right', 'arrow-up-right', 'mail', 'phone', 'map-pin', 'clock',
		'graduation', 'health', 'shield', 'handshake', 'book', 'target',
		'calendar', 'quote', 'check', 'check-circle', 'globe', 'facebook',
		'instagram', 'linkedin', 'plus', 'minus', 'home', 'download',
		'file-text', 'external', 'info', 'play',
	];

	for ( const name of names ) {
		try {
			const svg = await readFile( join( THEME, 'assets/images/icons', `${ name }.svg` ), 'utf8' );

			icons.set( name, svg.trim() );
		} catch {
			/* Not every name above exists in the set; skip quietly. */
		}
	}
}

/**
 * Inline an icon, hidden from assistive technology.
 *
 * @param {string} name      Icon file name without extension.
 * @param {string} className Class to apply.
 * @returns {string} SVG markup, or an empty string when unknown.
 */
export function icon( name, className = 'bgcf-icon' ) {
	const svg = icons.get( name );

	if ( ! svg ) {
		return '';
	}

	return svg.replace( '<svg ', `<svg class="${ esc( className ) }" aria-hidden="true" focusable="false" ` );
}

/**
 * A link styled as a button.
 *
 * @param {string} text    Visible label.
 * @param {string} url     Destination.
 * @param {Object} options variant, iconName, size.
 * @returns {string} Markup.
 */
export function btn( text, url, options = {} ) {
	const { variant = 'primary', iconName = '', size = '' } = options;

	const classes = [ 'bgcf-btn', `bgcf-btn--${ variant }`, size ? `bgcf-btn--${ size }` : '' ]
		.filter( Boolean )
		.join( ' ' );

	const glyph = iconName ? icon( iconName, 'bgcf-btn__icon' ) : '';

	return `<a class="${ classes }" href="${ esc( url ) }">${ glyph }<span class="bgcf-btn__label">${ esc( text ) }</span></a>`;
}

/**
 * Open a full-width band. Mirrors bgcf_section_open() in template-tags.php.
 *
 * @param {Object} options Band configuration.
 * @returns {string} Opening markup.
 */
export function sectionOpen( options = {} ) {
	const {
		background = 'base',
		spacing = 'md',
		width = 'wide',
		id = '',
		labelledby = '',
		className = '',
	} = options;

	const classes = [
		'bgcf-section',
		`bgcf-section--bg-${ background }`,
		`bgcf-section--space-${ spacing }`,
		className,
	].filter( Boolean ).join( ' ' );

	const attrs = [
		`class="${ classes }"`,
		id ? `id="${ esc( id ) }"` : '',
		labelledby ? `aria-labelledby="${ esc( labelledby ) }"` : '',
	].filter( Boolean ).join( ' ' );

	const container = false === width ? '' : `<div class="bgcf-container bgcf-container--${ width }">`;

	return `<section ${ attrs }>${ container }`;
}

/**
 * Close a band opened by sectionOpen().
 *
 * @param {string|false} width The same width passed to sectionOpen().
 * @returns {string} Closing markup.
 */
export function sectionClose( width = 'wide' ) {
	return `${ false === width ? '' : '</div>' }</section>`;
}

/**
 * A band heading.
 *
 * @param {Object} options eyebrow, title, lead, id, align, level.
 * @returns {string} Markup.
 */
export function sectionHeading( options ) {
	const { eyebrow = '', title, lead = '', id = '', align = 'center', level = 'h2' } = options;

	return `<div class="bgcf-section-heading bgcf-section-heading--${ align }">
	${ eyebrow ? `<p class="bgcf-section-heading__eyebrow">${ esc( eyebrow ) }</p>` : '' }
	<${ level } class="bgcf-section-heading__title"${ id ? ` id="${ esc( id ) }"` : '' }>${ esc( title ) }</${ level }>
	${ lead ? `<p class="bgcf-section-heading__lead">${ esc( lead ) }</p>` : '' }
</div>`;
}

/**
 * A decorative placeholder standing in for a photograph.
 *
 * No photography ships with this build (PLAN.md §7, ASSUMPTIONS.md §13), so
 * every image slot renders a token-coloured panel carrying the subject the
 * final licensed image must show.
 *
 * @param {string} subject What the final photograph depicts.
 * @param {string} ratio   A bgcf-u-ratio-* suffix.
 * @param {number} tone    Which of the five placeholder tones to use.
 * @returns {string} Markup.
 */
export function photo( subject, ratio = '16x9', tone = 1 ) {
	return `<div class="bgcf-photo bgcf-photo--tone-${ tone } bgcf-u-ratio-${ ratio }" role="img" aria-label="${ esc( subject ) }">
	<span class="bgcf-photo__note" aria-hidden="true">${ esc( subject ) }</span>
</div>`;
}

/**
 * A content card.
 *
 * @param {Object} options Card fields.
 * @returns {string} Markup.
 */
export function card( options ) {
	const { title, excerpt, url = '#', badge = '', meta = '', subject = '', tone = 1, cta = 'Read more' } = options;

	return `<article class="bgcf-card">
	<div class="bgcf-card__media">${ photo( subject || title, '16x9', tone ) }</div>
	<div class="bgcf-card__body">
		${ badge ? `<p class="bgcf-card__badge">${ esc( badge ) }</p>` : '' }
		<h3 class="bgcf-card__title"><a class="bgcf-card__link" href="${ esc( url ) }">${ esc( title ) }</a></h3>
		${ meta ? `<p class="bgcf-card__meta">${ esc( meta ) }</p>` : '' }
		<p class="bgcf-card__excerpt">${ esc( excerpt ) }</p>
		<p class="bgcf-card__cta">${ esc( cta ) } ${ icon( 'arrow-right' ) }</p>
	</div>
</article>`;
}
