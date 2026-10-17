/**
 * Static-site generator for the Vercel deployment.
 *
 * WHY THIS EXISTS. The production site is the WordPress theme in `theme/`.
 * Vercel has no PHP runtime, so it cannot serve that theme. This script emits
 * a flat HTML mirror into `public/` using the theme's own compiled stylesheet,
 * its own behaviour bundle, its own fonts and its own class vocabulary, so the
 * static site and the WordPress site look and behave the same.
 *
 * It is deliberately dependency-free: Vercel runs it with bare Node.
 *
 * Usage: npm run build:static
 *
 * @package Bgcf
 */

import { cp, mkdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';

import {
	BASE_URL, NAV, ORG, OUT, THEME, btn, esc, icon, loadIcons,
} from './static-lib.mjs';
import { PAGES } from './static-pages.mjs';

/* ------------------------------------------------------------------ *
 * Layout.
 * ------------------------------------------------------------------ */

/**
 * The site header, plus the off-canvas drawer it controls.
 *
 * The navigation list is rendered twice — once for the bar, once for the
 * drawer — exactly as header.php does, because one list cannot be both a
 * horizontal bar and an accordion without CSS that fights itself.
 *
 * @param {string} current The current page URL, for aria-current.
 * @returns {string} Markup.
 */
function siteHeader( current ) {
	const navItems = NAV.map( ( item ) => {
		const isCurrent = item.url === current;

		return `<li class="bgcf-nav__item"><a href="${ esc( item.url ) }"${ isCurrent ? ' aria-current="page"' : '' }>${ esc( item.label ) }</a></li>`;
	} ).join( '' );

	return `<header class="bgcf-site-header" id="bgcf-site-header" data-bgcf-header>
	<div class="bgcf-container bgcf-container--wide bgcf-site-header__inner">

		<div class="bgcf-site-header__branding">
			<a class="bgcf-site-header__home" href="/" rel="home">
				<img src="/assets/img/logo.png" alt="${ esc( ORG.name ) }" width="200" height="56" decoding="async" />
			</a>
		</div>

		<nav class="bgcf-site-header__nav" aria-label="Primary" data-bgcf-desktop-nav>
			<ul class="bgcf-nav bgcf-nav--primary">${ navItems }</ul>
		</nav>

		<div class="bgcf-site-header__actions">
			${ btn( 'Volunteer', '/get-involved/', { variant: 'ghost' } ) }
			${ btn( 'Donate', '/donate/', { variant: 'primary', iconName: 'heart' } ) }

			<button type="button" class="bgcf-site-header__menu-btn" aria-expanded="false" aria-controls="bgcf-drawer" data-bgcf-drawer-toggle>
				<span class="bgcf-site-header__menu-icon" aria-hidden="true">
					${ icon( 'menu', 'bgcf-site-header__menu-open' ) }
					${ icon( 'close', 'bgcf-site-header__menu-close' ) }
				</span>
				<span class="bgcf-site-header__menu-label" data-bgcf-drawer-label>Menu</span>
			</button>
		</div>

	</div>
</header>

<div class="bgcf-drawer-backdrop" data-bgcf-drawer-backdrop hidden></div>

<div class="bgcf-drawer" id="bgcf-drawer" data-bgcf-drawer hidden>
	<div class="bgcf-drawer__head">
		<p class="bgcf-drawer__title">Menu</p>
		<button type="button" class="bgcf-drawer__close" data-bgcf-drawer-close>
			${ icon( 'close' ) }<span class="bgcf-u-visually-hidden">Close menu</span>
		</button>
	</div>
	<div class="bgcf-drawer__body">
		<nav aria-label="Mobile">
			<ul class="bgcf-nav bgcf-nav--drawer">${ navItems }</ul>
		</nav>
		<div class="bgcf-drawer__actions">
			${ btn( 'Donate', '/donate/', { variant: 'primary', iconName: 'heart' } ) }
			${ btn( 'Volunteer', '/get-involved/', { variant: 'secondary' } ) }
		</div>
		<div class="bgcf-drawer__contact">
			<p>${ esc( ORG.address.join( ', ' ) ) }</p>
			<p><a href="tel:${ esc( ORG.phoneHref ) }">${ esc( ORG.phone ) }</a></p>
			<p><a href="mailto:${ esc( ORG.email ) }">${ esc( ORG.email ) }</a></p>
		</div>
	</div>
</div>`;
}

/**
 * The four-column footer and its legal bar.
 *
 * @returns {string} Markup.
 */
function siteFooter() {
	const quick = NAV.filter( ( item ) => '/' !== item.url )
		.map( ( item ) => `<li class="bgcf-nav__item"><a href="${ esc( item.url ) }">${ esc( item.label ) }</a></li>` )
		.join( '' );

	const programmes = [
		{ label: 'Education & scholarships', url: '/programs/#education' },
		{ label: 'Health & nutrition', url: '/programs/#health' },
		{ label: 'Child protection', url: '/programs/#protection' },
		{ label: 'Skills & livelihoods', url: '/programs/#skills' },
	].map( ( item ) => `<li class="bgcf-nav__item"><a href="${ esc( item.url ) }">${ esc( item.label ) }</a></li>` ).join( '' );

	const social = ORG.social.map( ( item ) => `<li class="bgcf-nav__item">
		<a href="${ esc( item.url ) }">${ icon( item.icon, 'bgcf-nav__icon' ) }<span class="bgcf-nav__label">${ esc( item.label ) }</span></a>
	</li>` ).join( '' );

	return `<footer class="bgcf-site-footer" id="bgcf-site-footer">
	<div class="bgcf-container bgcf-container--wide bgcf-site-footer__inner">

		<div class="bgcf-site-footer__brand">
			<p class="bgcf-site-footer__title"><a class="bgcf-site-footer__home" href="/" rel="home">${ esc( ORG.name ) }</a></p>
			<div class="bgcf-site-footer__intro">
				<p>A Ugandan child-focused organisation working in Nansana and the wider Wakiso
				District since ${ ORG.founded }. We keep children in school, in good health and
				safe at home.</p>
				<p class="bgcf-u-eyebrow">${ esc( ORG.regNo ) }</p>
			</div>
		</div>

		<nav class="bgcf-site-footer__column" aria-label="Quick links">
			<h2 class="bgcf-site-footer__heading">Quick links</h2>
			<ul class="bgcf-nav bgcf-nav--footer">${ quick }</ul>
		</nav>

		<nav class="bgcf-site-footer__column" aria-label="Our programmes">
			<h2 class="bgcf-site-footer__heading">Our programmes</h2>
			<ul class="bgcf-nav bgcf-nav--footer">${ programmes }</ul>
		</nav>

		<div class="bgcf-site-footer__column bgcf-site-footer__column--contact">
			<h2 class="bgcf-site-footer__heading">Get in touch</h2>
			<address class="bgcf-site-footer__address">${ ORG.address.map( ( line ) => esc( line ) ).join( '<br />' ) }</address>
			<ul class="bgcf-site-footer__contact">
				<li class="bgcf-site-footer__contact-item">${ icon( 'phone' ) }<a href="tel:${ esc( ORG.phoneHref ) }">${ esc( ORG.phone ) }</a></li>
				<li class="bgcf-site-footer__contact-item">${ icon( 'mail' ) }<a href="mailto:${ esc( ORG.email ) }">${ esc( ORG.email ) }</a></li>
				<li class="bgcf-site-footer__contact-item">${ icon( 'clock' ) }<span>${ esc( ORG.hours ) }</span></li>
			</ul>
		</div>

	</div>

	<div class="bgcf-site-footer__bar">
		<div class="bgcf-container bgcf-container--wide bgcf-site-footer__bar-inner">
			<p class="bgcf-site-footer__copyright">© ${ new Date().getFullYear() } ${ esc( ORG.name ) }. All rights reserved.</p>
			<nav class="bgcf-site-footer__legal" aria-label="Legal">
				<ul class="bgcf-nav bgcf-nav--legal">
					<li class="bgcf-nav__item"><a href="/privacy/">Privacy notice</a></li>
					<li class="bgcf-nav__item"><a href="/safeguarding/">Child safeguarding</a></li>
				</ul>
			</nav>
			<nav class="bgcf-site-footer__social" aria-label="Social profiles">
				<ul class="bgcf-nav bgcf-nav--social">${ social }</ul>
			</nav>
		</div>
	</div>
</footer>`;
}

/**
 * The data bridge the behaviour bundle expects WordPress to localise.
 *
 * It carries no secrets in WordPress either (see inc/enqueue.php), so a static
 * stub is a faithful substitute rather than a workaround.
 *
 * @returns {string} A script element.
 */
function dataBridge() {
	const data = {
		ajaxUrl: '',
		restUrl: '',
		nonce: '',
		nonceUrl: '',
		homeUrl: '/',
		locale: 'en_US',
		i18n: {
			menuOpen: 'Menu',
			menuClose: 'Close',
			loading: 'Loading…',
			loadMore: 'Load more',
			close: 'Close',
			gallery: 'Gallery',
			mapTitle: 'Map of our location',
			prevSlide: 'Previous',
			nextSlide: 'Next',
			goToSlide: 'Go to slide {{index}}',
			pauseSlides: 'Pause the slideshow',
			playSlides: 'Start the slideshow',
			genericError: 'Something went wrong. Please try again.',
			formSuccess: 'Thank you — we have received your details.',
			requiredField: 'This field is required.',
		},
	};

	return `<script>window.bgcfData=${ JSON.stringify( data ) };</script>`;
}

/**
 * Wrap page content in the full document.
 *
 * @param {Object} page The page manifest entry plus its rendered body.
 * @returns {string} A complete HTML document.
 */
function layout( page ) {
	const { title, description, url, body, noindex = false } = page;

	const fullTitle = '/' === url ? `${ ORG.name } — ${ ORG.tagline }` : `${ title } — ${ ORG.short }`;

	const schema = {
		'@context': 'https://schema.org',
		'@type': 'NGO',
		name: ORG.name,
		alternateName: ORG.short,
		url: BASE_URL,
		email: ORG.email,
		telephone: ORG.phone,
		foundingDate: String( ORG.founded ),
		address: {
			'@type': 'PostalAddress',
			streetAddress: ORG.address[ 0 ],
			addressLocality: 'Nansana',
			addressRegion: 'Wakiso District',
			addressCountry: 'UG',
		},
	};

	return `<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>${ esc( fullTitle ) }</title>
<meta name="description" content="${ esc( description ) }" />
${ noindex ? '<meta name="robots" content="noindex" />' : `<link rel="canonical" href="${ esc( BASE_URL + url ) }" />` }

<meta property="og:type" content="website" />
<meta property="og:site_name" content="${ esc( ORG.name ) }" />
<meta property="og:title" content="${ esc( fullTitle ) }" />
<meta property="og:description" content="${ esc( description ) }" />
<meta property="og:url" content="${ esc( BASE_URL + url ) }" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="theme-color" content="#123B63" />

<link rel="icon" href="/assets/img/logo.png" />
<link rel="preload" href="/assets/fonts/poppins-700-latin.woff2" as="font" type="font/woff2" crossorigin />
<link rel="preload" href="/assets/fonts/inter-var-latin.woff2" as="font" type="font/woff2" crossorigin />
<link rel="stylesheet" href="/assets/css/main.css" />
<link rel="stylesheet" href="/assets/css/static.css" />

<script type="application/ld+json">${ JSON.stringify( schema ) }</script>
<script>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
</head>

<body class="bgcf-static">

<a class="bgcf-skip-link" href="#bgcf-main">Skip to main content</a>

<div class="bgcf-site" id="bgcf-site">

${ siteHeader( url ) }

	<main class="bgcf-main" id="bgcf-main" tabindex="-1">
${ body }
	</main>

${ siteFooter() }

</div>

${ dataBridge() }
<script src="/assets/js/main.js" defer></script>
<script src="/assets/js/static-forms.js" defer></script>

</body>
</html>
`;
}

/* ------------------------------------------------------------------ *
 * Assets.
 * ------------------------------------------------------------------ */

/**
 * Copy the theme's compiled assets into the output directory.
 *
 * The stylesheet, bundle and fonts are copied rather than regenerated, so the
 * static site can never drift from the WordPress one.
 *
 * @returns {Promise<void>}
 */
async function copyAssets() {
	await mkdir( join( OUT, 'assets/css' ), { recursive: true } );
	await mkdir( join( OUT, 'assets/js' ), { recursive: true } );
	await mkdir( join( OUT, 'assets/img' ), { recursive: true } );

	await cp( join( THEME, 'assets/css/main.css' ), join( OUT, 'assets/css/main.css' ) );
	await cp( join( THEME, 'assets/js/main.js' ), join( OUT, 'assets/js/main.js' ) );
	await cp( join( THEME, 'assets/fonts' ), join( OUT, 'assets/fonts' ), { recursive: true } );

	/*
	 * The organisation's own logo files, carried over from the existing
	 * project (ASSUMPTIONS.md §13). These are the only real images that ship.
	 */
	await cp( join( OUT, '..', 'Branding/Boundless Gracia logo color.png' ), join( OUT, 'assets/img/logo.png' ) );
	await cp( join( OUT, '..', 'Branding/Boundless Gracia Logo.png' ), join( OUT, 'assets/img/logo-white.png' ) );
}

/**
 * The one stylesheet this build adds on top of the theme's.
 *
 * It styles exactly two things the WordPress theme has no need for: the
 * photograph placeholders, and a hero that has no background image. Every
 * value is a design token — no hex literal appears here (CLAUDE.md).
 *
 * @returns {string} CSS.
 */
function staticCss() {
	return `/* Additions for the static build only. Tokens only — never a hex literal. */

/*
 * Photograph placeholders.
 *
 * No photography ships with this build (PLAN.md §7). Every image slot renders
 * a token-coloured panel naming the subject the licensed image must show, so a
 * missing photograph is visible rather than silent.
 */
.bgcf-photo {
	position: relative;
	display: flex;
	align-items: flex-end;
	overflow: hidden;
	border-radius: var(--bgcf-radius-lg);
	background-color: var(--bgcf-color-primary-100);
	background-image: linear-gradient(135deg, var(--bgcf-color-primary-100), var(--bgcf-color-primary-200));
}

.bgcf-photo--tone-2 {
	background-color: var(--bgcf-color-secondary-100);
	background-image: linear-gradient(135deg, var(--bgcf-color-secondary-100), var(--bgcf-color-secondary-200));
}

.bgcf-photo--tone-3 {
	background-color: var(--bgcf-color-accent-100);
	background-image: linear-gradient(135deg, var(--bgcf-color-accent-100), var(--bgcf-color-accent-200));
}

.bgcf-photo--tone-4 {
	background-color: var(--bgcf-color-cta-50);
	background-image: linear-gradient(135deg, var(--bgcf-color-cta-50), var(--bgcf-color-cta-100));
}

.bgcf-photo--tone-5 {
	background-color: var(--bgcf-color-neutral-100);
	background-image: linear-gradient(135deg, var(--bgcf-color-neutral-100), var(--bgcf-color-neutral-200));
}

.bgcf-photo__note {
	padding: var(--bgcf-space-4);
	color: var(--bgcf-color-primary-800);
	font-family: var(--bgcf-font-body);
	font-size: var(--bgcf-font-size-xs);
	line-height: 1.4;
}

/* The card media box has no intrinsic height without an <img> inside it. */
.bgcf-card__media .bgcf-photo {
	width: 100%;
	border-radius: 0;
}

/*
 * The hero carries no photograph in this build, so the deep-blue band is the
 * design rather than a fallback (ASSUMPTIONS.md §13). Restore the padding the
 * scrim would otherwise have provided.
 */
.bgcf-static .bgcf-hero {
	padding-block: var(--bgcf-space-16);
}

/* Programme feature lists: align the tick with the first line of its item. */
.bgcf-static .bgcf-u-stack li {
	display: flex;
	gap: var(--bgcf-space-3);
	align-items: flex-start;
}

.bgcf-static .bgcf-u-stack li > svg {
	flex: 0 0 auto;
	margin-block-start: 0.2em;
	color: var(--bgcf-color-secondary);
}
`;
}

/**
 * Progressive enhancement for the static forms.
 *
 * The WordPress build posts these through admin-ajax with a nonce. A static
 * host has no endpoint, so until one is configured the forms refuse to submit
 * and say so, rather than appearing to succeed. Configure an endpoint by
 * setting `window.bgcfFormEndpoint` before this file loads.
 *
 * @returns {string} JavaScript.
 */
function staticFormsJs() {
	return `/* Static form handling. See build/static-site.mjs. */
(function () {
	'use strict';

	var endpoint = window.bgcfFormEndpoint || '';

	function setStatus(form, message, isError) {
		var status = form.querySelector('[data-bgcf-static-status]');

		if (!status) {
			return;
		}

		status.textContent = message;
		status.setAttribute('data-bgcf-status', isError ? 'error' : 'success');
	}

	function handle(event) {
		var form = event.target;

		event.preventDefault();

		if (!form.checkValidity()) {
			setStatus(form, 'Please check the highlighted fields and try again.', true);
			form.reportValidity();
			return;
		}

		/* Honeypot: a bot fills every field it finds. */
		var trap = form.querySelector('input[name="website"]');

		if (trap && trap.value !== '') {
			setStatus(form, 'Thank you — we have received your details.', false);
			form.reset();
			return;
		}

		if (!endpoint) {
			setStatus(
				form,
				'This form is not connected yet. Please email ${ ORG.email } and we will reply within two working days.',
				true
			);
			return;
		}

		setStatus(form, 'Sending…', false);

		fetch(endpoint, {
			method: 'POST',
			headers: { Accept: 'application/json' },
			body: new FormData(form)
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Request failed');
				}

				setStatus(form, 'Thank you — we have received your details.', false);
				form.reset();
			})
			.catch(function () {
				setStatus(form, 'Something went wrong. Please try again, or email ${ ORG.email }.', true);
			});
	}

	var forms = document.querySelectorAll('[data-bgcf-static-form]');

	for (var i = 0; i < forms.length; i++) {
		forms[i].addEventListener('submit', handle);
	}
})();
`;
}

/* ------------------------------------------------------------------ *
 * Build.
 * ------------------------------------------------------------------ */

/**
 * Generate the whole static site.
 *
 * @returns {Promise<void>}
 */
async function build() {
	await rm( OUT, { recursive: true, force: true } );
	await mkdir( OUT, { recursive: true } );

	await loadIcons();
	await copyAssets();

	await writeFile( join( OUT, 'assets/css/static.css' ), staticCss(), 'utf8' );
	await writeFile( join( OUT, 'assets/js/static-forms.js' ), staticFormsJs(), 'utf8' );

	for ( const page of PAGES ) {
		const html = layout( { ...page, body: page.render() } );
		const target = join( OUT, page.file );

		await mkdir( dirname( target ), { recursive: true } );
		await writeFile( target, html, 'utf8' );

		process.stdout.write( `  ${ page.file }\n` );
	}

	/* robots.txt and a sitemap, so the deployment is indexable from day one. */
	const indexable = PAGES.filter( ( page ) => ! page.noindex );

	const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${ indexable.map( ( page ) => `	<url><loc>${ BASE_URL }${ page.url }</loc></url>` ).join( '\n' ) }
</urlset>
`;

	await writeFile( join( OUT, 'sitemap.xml' ), sitemap, 'utf8' );
	await writeFile(
		join( OUT, 'robots.txt' ),
		`User-agent: *\nAllow: /\n\nSitemap: ${ BASE_URL }/sitemap.xml\n`,
		'utf8'
	);

	process.stdout.write( `\nBuilt ${ PAGES.length } pages into public/\n` );
}

await build();
