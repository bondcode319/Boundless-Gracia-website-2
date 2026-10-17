/**
 * Page content for the static Vercel build.
 *
 * All copy here is original and written for this build. Every figure, name and
 * date is an invented placeholder and must be replaced before launch
 * (ASSUMPTIONS.md §12). No copy, image or layout is reproduced from any other
 * organisation's site.
 *
 * @package Bgcf
 */

import {
	ORG, btn, card, esc, icon, photo, sectionClose, sectionHeading, sectionOpen,
} from './static-lib.mjs';

/* ------------------------------------------------------------------ *
 * Shared bands.
 * ------------------------------------------------------------------ */

/**
 * The band that opens every page except the homepage.
 *
 * @param {Object} options eyebrow, title, lead, crumbs.
 * @returns {string} Markup.
 */
function masthead( { eyebrow, title, lead, crumbs = [] } ) {
	const trail = crumbs.length
		? `<nav class="bgcf-breadcrumbs" aria-label="Breadcrumb">
			<ol class="bgcf-breadcrumbs__list">
				<li class="bgcf-breadcrumbs__item"><a class="bgcf-breadcrumbs__link" href="/">Home</a></li>
				${ crumbs.map( ( c ) => `<li class="bgcf-breadcrumbs__item"><span class="bgcf-breadcrumbs__current" aria-current="page">${ esc( c ) }</span></li>` ).join( '' ) }
			</ol>
		</nav>`
		: '';

	return `${ sectionOpen( { background: 'primary', spacing: 'md', labelledby: 'bgcf-page-title' } ) }
	${ trail }
	<div class="bgcf-section-heading bgcf-section-heading--start">
		<p class="bgcf-section-heading__eyebrow">${ esc( eyebrow ) }</p>
		<h1 class="bgcf-section-heading__title" id="bgcf-page-title">${ esc( title ) }</h1>
		<p class="bgcf-section-heading__lead">${ esc( lead ) }</p>
	</div>
${ sectionClose() }`;
}

/** The giving band reused across pages. */
function donationBanner() {
	return `${ sectionOpen( { background: 'secondary', spacing: 'md', labelledby: 'bgcf-give-title' } ) }
	<div class="bgcf-donation-banner__inner">
		<div class="bgcf-donation-banner__text">
			<h2 class="bgcf-donation-banner__heading" id="bgcf-give-title">UGX 50,000 keeps one child in school for a term</h2>
			<p>That is uniform, exercise books, exam fees and a hot meal every school
			day. Give once, or set up a monthly gift and sponsor a child through a
			full academic year.</p>
		</div>
		<div class="bgcf-cta__actions">
			${ btn( 'Donate now', '/donate/', { variant: 'primary', iconName: 'heart', size: 'lg' } ) }
			${ btn( 'Other ways to help', '/get-involved/', { variant: 'ghost' } ) }
		</div>
	</div>
${ sectionClose() }`;
}

/** The newsletter sign-up band. */
function newsletterBand() {
	return `${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-news-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Stay with us',
		title: 'Four updates a year, nothing else',
		lead: 'Term results, project news and the annual report. No appeals between campaigns, and one click to leave.',
		id: 'bgcf-news-title',
	} ) }
	<form class="bgcf-newsletter bgcf-newsletter--footer" data-bgcf-static-form="newsletter" novalidate>
		<div class="bgcf-newsletter__row">
			<div class="bgcf-field bgcf-newsletter__field">
				<label class="bgcf-field__label" for="bgcf-news-name">Your name <span class="bgcf-field__help">(optional)</span></label>
				<input type="text" id="bgcf-news-name" name="name" autocomplete="name" />
			</div>
			<div class="bgcf-field bgcf-newsletter__field">
				<label class="bgcf-field__label" for="bgcf-news-email">Email address</label>
				<input type="email" id="bgcf-news-email" name="email" autocomplete="email" required />
			</div>
			<button type="submit" class="bgcf-btn bgcf-btn--primary bgcf-newsletter__submit">
				<span class="bgcf-btn__label">Subscribe</span>
			</button>
		</div>
		<p class="bgcf-form-status" data-bgcf-static-status role="status"></p>
	</form>
${ sectionClose() }`;
}

/** The closing call to action. */
function closingCta() {
	return `${ sectionOpen( { background: 'primary', spacing: 'md', labelledby: 'bgcf-cta-title' } ) }
	<div class="bgcf-cta">
		<div class="bgcf-cta__copy">
			<p class="bgcf-cta__eyebrow">Work with us</p>
			<h2 class="bgcf-cta__heading" id="bgcf-cta-title">Partnership starts with a conversation</h2>
			<p class="bgcf-cta__text">Schools, clinics, local government and companies who want to
			put something back — we would rather talk through what you have in mind than
			send you a brochure.</p>
			<div class="bgcf-cta__actions">
				${ btn( 'Contact the team', '/contact/', { variant: 'primary', iconName: 'mail' } ) }
				${ btn( 'Ways to get involved', '/get-involved/', { variant: 'ghost' } ) }
			</div>
		</div>
	</div>
${ sectionClose() }`;
}

/* ------------------------------------------------------------------ *
 * Pages.
 * ------------------------------------------------------------------ */

function home() {
	const stats = [
		{ value: '2,480', label: 'Children supported since 2014', icon: 'graduation' },
		{ value: '1,150', label: 'School places funded last year', icon: 'book' },
		{ value: '94', suffix: '%', label: 'Sponsored pupils who sat their finals', icon: 'target' },
		{ value: '83', suffix: '%', label: 'Of every shilling spent on programmes', icon: 'shield' },
	];

	const statItems = stats.map( ( stat ) => `<div class="bgcf-stats__item" data-bgcf-reveal>
		${ icon( stat.icon, 'bgcf-stats__icon' ) }
		<dd class="bgcf-stats__value">
			<span class="bgcf-stats__number" data-bgcf-counter="${ esc( stat.value.replace( /,/g, '' ) ) }">${ esc( stat.value ) }</span>
			${ stat.suffix ? `<span class="bgcf-stats__affix">${ esc( stat.suffix ) }</span>` : '' }
		</dd>
		<dt class="bgcf-stats__label">${ esc( stat.label ) }</dt>
	</div>` ).join( '' );

	const programs = [
		{
			title: 'Education & scholarships',
			excerpt: 'School fees, uniforms and exam registration for children whose families cannot carry the cost, from P1 through to senior six.',
			url: '/programs/#education',
			badge: 'Programme',
			subject: 'A classroom of primary pupils working at their desks',
			tone: 1,
		},
		{
			title: 'Health & nutrition',
			excerpt: 'A termly health screening, treatment referrals and a daily hot meal at each of the four partner schools we work with.',
			url: '/programs/#health',
			badge: 'Programme',
			subject: 'A community health worker weighing a young child',
			tone: 2,
		},
		{
			title: 'Child protection',
			excerpt: 'Safeguarding training for teachers, a case-work service for children at risk, and family mediation that keeps siblings together.',
			url: '/programs/#protection',
			badge: 'Programme',
			subject: 'A social worker talking with a family outdoors',
			tone: 3,
		},
	].map( card ).join( '' );

	const testimonials = [
		{
			quote: 'I sat my senior four exams in a uniform the foundation paid for. I am now in my second year of a nursing diploma, and my younger sister has the same sponsorship I had.',
			name: 'Sarah N.',
			role: 'Sponsored since 2019, Nansana',
		},
		{
			quote: 'What changed was not only the fees. Somebody came to the house, sat with us, and asked what was actually stopping the children from attending. Nobody had done that before.',
			name: 'Joseph K.',
			role: 'Parent, Wakiso District',
		},
		{
			quote: 'Attendance in my class went from roughly sixty per cent to over ninety in two terms. The meal is the reason. A hungry child does not learn, and does not come back.',
			name: 'Grace A.',
			role: 'Teacher, partner primary school',
		},
	].map( ( t ) => `<figure class="bgcf-testimonial">
		${ icon( 'quote', 'bgcf-testimonial__mark' ) }
		<blockquote class="bgcf-testimonial__quote"><p>${ esc( t.quote ) }</p></blockquote>
		<figcaption class="bgcf-testimonial__cite">
			<span class="bgcf-testimonial__name">${ esc( t.name ) }</span>
			<span class="bgcf-testimonial__role">${ esc( t.role ) }</span>
		</figcaption>
	</figure>` ).join( '' );

	const partners = [
		'Wakiso District Education Office', 'Nansana Municipal Council',
		'Kampala Teaching Hospital', 'East Africa Youth Trust',
		'Hoima Road Traders Association', 'Uganda Scholars Network',
	].map( ( name ) => `<li class="bgcf-partners__item">
		<span class="bgcf-partners__name">${ esc( name ) }</span>
	</li>` ).join( '' );

	return `
${ sectionOpen( { background: 'primary', spacing: 'none', width: false, labelledby: 'bgcf-hero-title' } ) }
	<div class="bgcf-hero">
		<div class="bgcf-container bgcf-container--wide bgcf-hero__inner">
			<div class="bgcf-hero__copy">
				<p class="bgcf-hero__eyebrow">Nansana, Uganda · since ${ ORG.founded }</p>
				<h1 class="bgcf-hero__heading" id="bgcf-hero-title">Every child deserves a future <em>they can reach</em></h1>
				<p class="bgcf-hero__lead">We keep children in school, in good health and safe at
				home — working street by street in Nansana and the wider Wakiso District, with
				the families who know what their children need.</p>
				<div class="bgcf-hero__actions">
					${ btn( 'Donate now', '/donate/', { variant: 'primary', iconName: 'heart', size: 'lg' } ) }
					${ btn( 'See our programmes', '/programs/', { variant: 'ghost', size: 'lg' } ) }
				</div>
				<dl class="bgcf-hero__stats">
					<div class="bgcf-hero__stat">
						<dd class="bgcf-hero__stat-value">2,480</dd>
						<dt class="bgcf-hero__stat-label">children supported</dt>
					</div>
					<div class="bgcf-hero__stat">
						<dd class="bgcf-hero__stat-value">12</dd>
						<dt class="bgcf-hero__stat-label">partner schools</dt>
					</div>
					<div class="bgcf-hero__stat">
						<dd class="bgcf-hero__stat-value">83%</dd>
						<dt class="bgcf-hero__stat-label">spent on programmes</dt>
					</div>
				</dl>
			</div>
			<div class="bgcf-hero__cards">
				<div class="bgcf-hero__card">
					${ icon( 'graduation', 'bgcf-hero__card-icon' ) }
					<p class="bgcf-hero__card-title">Sponsor a child</p>
					<p class="bgcf-hero__card-text">UGX 180,000 a month covers a full school year.</p>
				</div>
				<div class="bgcf-hero__card">
					${ icon( 'handshake', 'bgcf-hero__card-icon' ) }
					<p class="bgcf-hero__card-title">Volunteer locally</p>
					<p class="bgcf-hero__card-text">Tutoring, health days and holiday clubs.</p>
				</div>
			</div>
		</div>
	</div>
${ sectionClose( false ) }

${ sectionOpen( { background: 'primary', spacing: 'md', labelledby: 'bgcf-stats-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Our impact',
		title: 'Ten years, counted honestly',
		lead: 'Figures are from our most recent annual report and are updated once a year, after the accounts are signed off.',
		id: 'bgcf-stats-title',
	} ) }
	<dl class="bgcf-stats" data-bgcf-reveal-stagger>${ statItems }</dl>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-programs-title' } ) }
	${ sectionHeading( {
		eyebrow: 'What we do',
		title: 'Four programmes, one child at a time',
		lead: 'Each programme exists because families in Nansana told us it was the thing standing between their child and a finished education.',
		id: 'bgcf-programs-title',
	} ) }
	<div class="bgcf-grid bgcf-grid--cols-3">${ programs }</div>
	<div class="bgcf-section__footer">
		${ btn( 'All four programmes', '/programs/', { variant: 'secondary', iconName: 'arrow-right' } ) }
	</div>
${ sectionClose() }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-about-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-2">
		<div>${ photo( 'Children walking to school along a red-earth road in Nansana', '4x3', 2 ) }</div>
		<div class="bgcf-u-flow">
			<p class="bgcf-u-eyebrow">Who we are</p>
			<h2 id="bgcf-about-title">We started with eleven children and one borrowed classroom</h2>
			<p class="bgcf-u-lead">In ${ ORG.founded }, a group of parents and teachers in Nansana
			pooled what they had to keep eleven children in school for one more term. That
			arrangement never really ended — it just grew.</p>
			<p>Today we are a registered Ugandan organisation working across twelve partner
			schools, but the method has not changed. We ask the family what is actually in the
			way. Sometimes it is fees. Often it is a uniform, a meal, a medical bill, or
			somebody to speak to the head teacher. We deal with whichever one it is.</p>
			<p>${ btn( 'More about us', '/about/', { variant: 'link', iconName: 'arrow-right' } ) }</p>
		</div>
	</div>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-voices-title' } ) }
	${ sectionHeading( {
		eyebrow: 'In their words',
		title: 'The people this is for',
		id: 'bgcf-voices-title',
	} ) }
	<div class="bgcf-grid bgcf-grid--cols-3">${ testimonials }</div>
${ sectionClose() }

${ donationBanner() }

${ sectionOpen( { background: 'base', spacing: 'sm', labelledby: 'bgcf-partners-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Working together',
		title: 'Who we work with',
		id: 'bgcf-partners-title',
	} ) }
	<ul class="bgcf-partners__list">${ partners }</ul>
${ sectionClose() }

${ newsletterBand() }
`;
}

function about() {
	const timeline = [
		{ year: '2014', title: 'Eleven children, one borrowed classroom', text: 'Parents and teachers in Nansana cover a term of fees between them. Nobody intends it to become an organisation.' },
		{ year: '2016', title: 'Registered with the NGO Bureau', text: 'A permit, a bank account and a committee. The first full academic year is funded for forty children.' },
		{ year: '2018', title: 'The school meal starts', text: 'Attendance data from two partner schools shows hunger, not fees, is the main reason children stop coming. A kitchen opens.' },
		{ year: '2021', title: 'Safeguarding becomes a programme', text: 'Case work, teacher training and a reporting line are separated out from general welfare and properly staffed.' },
		{ year: '2024', title: 'Twelve partner schools', text: 'The tenth year closes with 2,480 children supported since the beginning and the first independently audited accounts.' },
	].map( ( item ) => `<li class="bgcf-timeline__item" data-bgcf-reveal>
		<p class="bgcf-timeline__year">${ esc( item.year ) }</p>
		<div class="bgcf-timeline__text">
			<h3 class="bgcf-timeline__title">${ esc( item.title ) }</h3>
			<p>${ esc( item.text ) }</p>
		</div>
	</li>` ).join( '' );

	const team = [
		{ name: 'Agnes Nakato', role: 'Executive Director', subject: 'Portrait of the executive director' },
		{ name: 'David Ssemwogerere', role: 'Programmes Manager', subject: 'Portrait of the programmes manager' },
		{ name: 'Rebecca Auma', role: 'Safeguarding Lead', subject: 'Portrait of the safeguarding lead' },
		{ name: 'Moses Kigongo', role: 'Finance & Compliance', subject: 'Portrait of the finance lead' },
	].map( ( person, index ) => `<article class="bgcf-card">
		<div class="bgcf-card__media">${ photo( person.subject, '1x1', ( index % 5 ) + 1 ) }</div>
		<div class="bgcf-card__body">
			<h3 class="bgcf-card__title">${ esc( person.name ) }</h3>
			<p class="bgcf-card__meta">${ esc( person.role ) }</p>
		</div>
	</article>` ).join( '' );

	const values = [
		{ icon: 'shield', title: 'The child comes first', text: 'Every decision is tested against whether it is good for the child in front of us, not for the organisation.' },
		{ icon: 'globe', title: 'Local by default', text: 'Our staff are from the communities we work in. Decisions are made in Nansana, not somewhere else on their behalf.' },
		{ icon: 'target', title: 'Count it or do not claim it', text: 'If we cannot evidence a number, we do not publish it. Our accounts are independently audited every year.' },
		{ icon: 'handshake', title: 'Families are partners', text: 'We do not arrive with a solution. We ask what is in the way, and we work on that.' },
	].map( ( value ) => `<article class="bgcf-card">
		<div class="bgcf-card__body">
			${ icon( value.icon, 'bgcf-stats__icon' ) }
			<h3 class="bgcf-card__title">${ esc( value.title ) }</h3>
			<p class="bgcf-card__excerpt">${ esc( value.text ) }</p>
		</div>
	</article>` ).join( '' );

	return `
${ masthead( {
	eyebrow: 'About us',
	title: 'A Ugandan organisation, run from Nansana',
	lead: 'We are small on purpose, close to the families we work with, and accountable for every shilling.',
	crumbs: [ 'About us' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-mission-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-2">
		<div class="bgcf-u-flow">
			<p class="bgcf-u-eyebrow">Mission</p>
			<h2 id="bgcf-mission-title">What we are here to do</h2>
			<p class="bgcf-u-lead">To remove the practical obstacles that stop a child in Wakiso
			District from finishing their education — and to stay with that child until they have.</p>
			<p class="bgcf-u-eyebrow">Vision</p>
			<h3>What we are working towards</h3>
			<p>A district where no child's schooling ends because of a uniform nobody could buy,
			a meal nobody could provide, or an adult nobody held to account.</p>
		</div>
		<div>${ photo( 'Staff and parents meeting around a table at the foundation office', '4x3', 3 ) }</div>
	</div>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-values-title' } ) }
	${ sectionHeading( { eyebrow: 'How we work', title: 'Four things we do not compromise on', id: 'bgcf-values-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-4">${ values }</div>
${ sectionClose() }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-history-title' } ) }
	${ sectionHeading( { eyebrow: 'Our history', title: 'Ten years, in order', id: 'bgcf-history-title' } ) }
	<ol class="bgcf-timeline" data-bgcf-reveal-stagger>${ timeline }</ol>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-team-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Our people',
		title: 'Who you will actually speak to',
		lead: 'A staff of eleven, supported by a volunteer board that meets quarterly.',
		id: 'bgcf-team-title',
	} ) }
	<div class="bgcf-grid bgcf-grid--cols-4">${ team }</div>
${ sectionClose() }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-reports-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Accountability',
		title: 'Our accounts are public',
		lead: 'Audited annual reports, our safeguarding policy and our constitution. Ask us for anything that is not here.',
		id: 'bgcf-reports-title',
	} ) }
	<div class="bgcf-section__footer">
		${ btn( 'Annual report 2025 (PDF)', '#', { variant: 'secondary', iconName: 'download' } ) }
		${ btn( 'Child safeguarding policy', '/safeguarding/', { variant: 'ghost', iconName: 'shield' } ) }
	</div>
${ sectionClose() }

${ donationBanner() }
`;
}

function programs() {
	const detail = [
		{
			id: 'education',
			eyebrow: 'Programme one',
			title: 'Education & scholarships',
			lead: 'School fees, uniforms, books and exam registration — from P1 to senior six.',
			body: [
				'A place at a government primary school is free in principle and expensive in practice. Uniform, exercise books, exam registration and the development levy together come to more than many families in Nansana can raise in a term.',
				'We cover the gap for 1,150 children across twelve partner schools, and we pay the school directly. Each child keeps the same sponsorship year after year, because a scholarship that stops after one term is not a scholarship.',
			],
			points: [ 'Fees paid directly to the school, termly', 'Uniform, shoes and exercise books each year', 'Exam registration for P7, S4 and S6 candidates', 'A termly review with the class teacher' ],
			subject: 'A pupil in uniform writing at a classroom desk',
			tone: 1,
		},
		{
			id: 'health',
			eyebrow: 'Programme two',
			title: 'Health & nutrition',
			lead: 'A hot meal every school day, termly screening, and referral for anything we find.',
			body: [
				'Attendance data from two of our partner schools in 2018 showed the same pattern: children were not dropping out, they were drifting out, and almost all of them were hungry. The kitchen opened the following term and attendance recovered within two.',
				'Alongside the meal, every sponsored child is screened each term for sight, hearing, dental and nutritional problems, and we pay for the referral when something is found.',
			],
			points: [ 'A hot midday meal at four partner schools', 'Termly health screening for every sponsored child', 'Referral costs covered, including transport', 'Deworming and malaria prevention each term' ],
			subject: 'Children queueing with bowls at a school kitchen',
			tone: 2,
		},
		{
			id: 'protection',
			eyebrow: 'Programme three',
			title: 'Child protection',
			lead: 'Safeguarding training, case work, and family mediation that keeps siblings together.',
			body: [
				'Our safeguarding work was separated from general welfare in 2021 and given its own staff, its own reporting line and its own budget, because it had been nobody\'s whole job and that is how children get missed.',
				'We train teachers at every partner school, run a case-work service for children identified as at risk, and mediate with families where the alternative is a child leaving home.',
			],
			points: [ 'Annual safeguarding training for partner-school staff', 'A named case worker for every open case', 'A reporting line independent of the schools', 'Family mediation and follow-up for twelve months' ],
			subject: 'A case worker speaking with a teenager on a school veranda',
			tone: 3,
		},
		{
			id: 'skills',
			eyebrow: 'Programme four',
			title: 'Skills & livelihoods',
			lead: 'For school leavers, and for the mothers holding these households together.',
			body: [
				'Finishing senior four is not the end of the problem if there is no work afterwards. We fund vocational placements — tailoring, catering, motorcycle mechanics, hairdressing — and we run a small savings group for the parents of sponsored children.',
				'Thirty-one school leavers completed a placement last year. The savings groups are self-managed; we provide the training and the ledger, and then we step back.',
			],
			points: [ 'Vocational placements for school leavers', 'A toolkit or start-up grant on completion', 'Parent savings groups, self-managed', 'Business basics training, four sessions a year' ],
			subject: 'A young woman at a sewing machine in a training workshop',
			tone: 4,
		},
	];

	const bands = detail.map( ( programme, index ) => {
		const background = index % 2 === 0 ? 'base' : 'surface';
		const media = `<div>${ photo( programme.subject, '4x3', programme.tone ) }</div>`;
		const copy = `<div class="bgcf-u-flow">
			<p class="bgcf-u-eyebrow">${ esc( programme.eyebrow ) }</p>
			<h2 id="bgcf-${ esc( programme.id ) }-title">${ esc( programme.title ) }</h2>
			<p class="bgcf-u-lead">${ esc( programme.lead ) }</p>
			${ programme.body.map( ( p ) => `<p>${ esc( p ) }</p>` ).join( '' ) }
			<ul class="bgcf-u-stack">
				${ programme.points.map( ( point ) => `<li>${ icon( 'check-circle' ) } ${ esc( point ) }</li>` ).join( '' ) }
			</ul>
		</div>`;

		return `${ sectionOpen( {
			background,
			spacing: 'md',
			id: programme.id,
			labelledby: `bgcf-${ programme.id }-title`,
		} ) }
	<div class="bgcf-grid bgcf-grid--cols-2">
		${ index % 2 === 0 ? media + copy : copy + media }
	</div>
${ sectionClose() }`;
	} ).join( '\n' );

	return `
${ masthead( {
	eyebrow: 'What we do',
	title: 'Our programmes',
	lead: 'Four programmes, each one built around an obstacle a family in Nansana told us about.',
	crumbs: [ 'Programmes' ],
} ) }

${ bands }

${ donationBanner() }
`;
}

function stories() {
	const items = [
		{
			title: 'Sarah is in her second year of nursing',
			excerpt: 'She joined the sponsorship programme in senior one, sat her finals in 2023, and is now training at a hospital in Kampala. Her younger sister has just started.',
			badge: 'Success story',
			meta: '12 March 2026',
			subject: 'A nursing student in uniform outside a teaching hospital',
			tone: 1,
		},
		{
			title: 'What the school kitchen changed',
			excerpt: 'Attendance at one partner school went from 62% to 91% in two terms. The head teacher explains what the meal actually did, and what it did not fix.',
			badge: 'Programme update',
			meta: '28 February 2026',
			subject: 'A school cook serving a midday meal to a queue of pupils',
			tone: 2,
		},
		{
			title: 'Three brothers who stayed together',
			excerpt: 'After their grandmother died, the plan was to split the boys between two districts. A case worker, a landlord and nine months of mediation produced a different outcome.',
			badge: 'Case study',
			meta: '19 January 2026',
			subject: 'Three brothers sitting together outside a family home',
			tone: 3,
		},
		{
			title: 'The savings group that bought a maize mill',
			excerpt: 'Eleven mothers of sponsored children, two years of weekly contributions, and a piece of equipment that now serves four villages.',
			badge: 'Livelihoods',
			meta: '8 December 2025',
			subject: 'Women gathered around a newly installed maize mill',
			tone: 4,
		},
		{
			title: 'Why we publish the numbers we do not like',
			excerpt: 'Six per cent of our sponsored candidates did not sit their finals last year. Here is what happened to them, and what we are changing.',
			badge: 'Accountability',
			meta: '21 November 2025',
			subject: 'A staff member reviewing printed results at a desk',
			tone: 5,
		},
		{
			title: 'A term in the life of a case worker',
			excerpt: 'Rebecca carries nineteen open cases. She kept a diary for one term so that supporters could see what the work actually consists of.',
			badge: 'Behind the work',
			meta: '3 October 2025',
			subject: 'A case worker walking between houses with a folder',
			tone: 1,
		},
	].map( card ).join( '' );

	return `
${ masthead( {
	eyebrow: 'Stories',
	title: 'What actually happened',
	lead: 'Published with the consent of everyone named. Where a child is identifiable, a guardian has signed for it.',
	crumbs: [ 'Stories' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-stories-title' } ) }
	${ sectionHeading( { eyebrow: 'Latest', title: 'Recent stories', id: 'bgcf-stories-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-3">${ items }</div>
${ sectionClose() }

${ newsletterBand() }
`;
}

function events() {
	const upcoming = [
		{ date: '14 November 2026', title: 'Back-to-school kit packing day', place: 'Foundation office, Hoima Road', text: 'Two hundred school kits to assemble before first term. Volunteers welcome from 9am; lunch provided.' },
		{ date: '5 December 2026', title: 'Annual supporters evening', place: 'Nansana Community Hall', text: 'The year in review, the audited accounts, and the plan for 2027. Open to anyone who has supported us.' },
		{ date: '17 January 2027', title: 'Free community health day', place: 'St. Matthias Primary School', text: 'Screening, deworming and immunisation in partnership with the district health office. No registration needed.' },
	].map( ( item ) => `<article class="bgcf-card">
		<div class="bgcf-card__body">
			<p class="bgcf-card__date">${ icon( 'calendar' ) } ${ esc( item.date ) }</p>
			<h3 class="bgcf-card__title">${ esc( item.title ) }</h3>
			<p class="bgcf-card__meta">${ icon( 'map-pin' ) } ${ esc( item.place ) }</p>
			<p class="bgcf-card__excerpt">${ esc( item.text ) }</p>
		</div>
	</article>` ).join( '' );

	const past = [
		{ date: '20 September 2026', title: 'Term three parents meeting' },
		{ date: '8 August 2026', title: 'Vocational placement graduation' },
		{ date: '13 June 2026', title: 'Safeguarding training, cohort four' },
	].map( ( item ) => `<li>
		<article class="bgcf-card">
			<div class="bgcf-card__body">
				<p class="bgcf-card__date">${ icon( 'calendar' ) } ${ esc( item.date ) }</p>
				<h3 class="bgcf-card__title">${ esc( item.title ) }</h3>
			</div>
		</article>
	</li>` ).join( '' );

	return `
${ masthead( {
	eyebrow: 'Events',
	title: 'Come and see the work',
	lead: 'Everything we run is open to supporters. If you want to visit outside these dates, ask us.',
	crumbs: [ 'Events' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-upcoming-title' } ) }
	${ sectionHeading( { eyebrow: 'Diary', title: 'Upcoming events', id: 'bgcf-upcoming-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-3">${ upcoming }</div>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-past-title' } ) }
	${ sectionHeading( { eyebrow: 'Archive', title: 'Recently held', id: 'bgcf-past-title' } ) }
	<ul class="bgcf-archive__list bgcf-archive__group--past">${ past }</ul>
${ sectionClose() }

${ closingCta() }
`;
}

function getInvolved() {
	const ways = [
		{ icon: 'heart', title: 'Give monthly', text: 'UGX 180,000 a month sponsors one child for a full academic year — fees, uniform, meals and screening.', cta: 'Sponsor a child', url: '/donate/' },
		{ icon: 'handshake', title: 'Volunteer', text: 'Tutoring, holiday clubs, health days and kit packing. Two hours a week is useful; a whole Saturday is better.', cta: 'Apply to volunteer', url: '#volunteer-form' },
		{ icon: 'globe', title: 'Partner with us', text: 'Schools, clinics, local government and companies. We would rather talk through an idea than send a brochure.', cta: 'Start a conversation', url: '/contact/' },
		{ icon: 'book', title: 'Donate goods', text: 'Exercise books, shoes, sanitary products and school furniture. Ask first — unsolicited deliveries cost us money.', cta: 'See what we need', url: '/contact/' },
		{ icon: 'target', title: 'Fundraise', text: 'A run, a bake sale, a birthday appeal. We will give you the figures and the photographs you are allowed to use.', cta: 'Get a fundraising pack', url: '/contact/' },
		{ icon: 'file-text', title: 'Leave a legacy', text: 'A gift in your will funds a child\'s whole school career. Our finance lead can talk you through it.', cta: 'Discuss a legacy gift', url: '/contact/' },
	].map( ( way ) => `<article class="bgcf-card">
		<div class="bgcf-card__body">
			${ icon( way.icon, 'bgcf-stats__icon' ) }
			<h3 class="bgcf-card__title">${ esc( way.title ) }</h3>
			<p class="bgcf-card__excerpt">${ esc( way.text ) }</p>
			<p class="bgcf-card__cta">${ btn( way.cta, way.url, { variant: 'link', iconName: 'arrow-right' } ) }</p>
		</div>
	</article>` ).join( '' );

	return `
${ masthead( {
	eyebrow: 'Get involved',
	title: 'Six ways to be useful',
	lead: 'Money helps most, but it is not the only thing we need, and it is not the only thing you can give.',
	crumbs: [ 'Get involved' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-ways-title' } ) }
	${ sectionHeading( { eyebrow: 'Ways to help', title: 'Pick whichever fits', id: 'bgcf-ways-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-3">${ ways }</div>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', id: 'volunteer-form', labelledby: 'bgcf-vol-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Volunteer',
		title: 'Tell us what you can do',
		lead: 'Everyone who works with children completes a safeguarding check first. We will explain the process when we reply.',
		id: 'bgcf-vol-title',
	} ) }
	<form class="bgcf-form" data-bgcf-static-form="volunteer" novalidate>
		<div class="bgcf-field">
			<label class="bgcf-field__label" for="vol-name">Full name</label>
			<input type="text" id="vol-name" name="name" autocomplete="name" required />
		</div>
		<div class="bgcf-field">
			<label class="bgcf-field__label" for="vol-email">Email address</label>
			<input type="email" id="vol-email" name="email" autocomplete="email" required />
		</div>
		<div class="bgcf-field">
			<label class="bgcf-field__label" for="vol-phone">Phone number</label>
			<input type="tel" id="vol-phone" name="phone" autocomplete="tel" />
		</div>
		<div class="bgcf-field">
			<label class="bgcf-field__label" for="vol-area">What would you like to help with?</label>
			<select id="vol-area" name="area">
				<option>Tutoring and homework support</option>
				<option>Holiday clubs and sports</option>
				<option>Health days</option>
				<option>Kit packing and logistics</option>
				<option>Professional skills (accounting, legal, IT)</option>
			</select>
		</div>
		<div class="bgcf-field">
			<label class="bgcf-field__label" for="vol-message">Anything else we should know?</label>
			<textarea id="vol-message" name="message" rows="5"></textarea>
		</div>
		<div class="bgcf-field bgcf-field--honeypot" aria-hidden="true">
			<label for="vol-website">Leave this field empty</label>
			<input type="text" id="vol-website" name="website" tabindex="-1" autocomplete="off" />
		</div>
		<button type="submit" class="bgcf-btn bgcf-btn--primary bgcf-btn--lg">
			<span class="bgcf-btn__label">Send my application</span>
		</button>
		<p class="bgcf-form-status" data-bgcf-static-status role="status"></p>
	</form>
${ sectionClose() }

${ donationBanner() }
`;
}

function donate() {
	const amounts = [
		{ amount: 'UGX 50,000', text: 'Keeps one child in school for a term' },
		{ amount: 'UGX 180,000', text: 'Sponsors one child for a full month' },
		{ amount: 'UGX 540,000', text: 'A full term of fees, meals and screening' },
		{ amount: 'UGX 2,160,000', text: 'One child, one complete academic year' },
	].map( ( tier ) => `<article class="bgcf-card">
		<div class="bgcf-card__body">
			<h3 class="bgcf-card__title">${ esc( tier.amount ) }</h3>
			<p class="bgcf-card__excerpt">${ esc( tier.text ) }</p>
		</div>
	</article>` ).join( '' );

	const faqs = [
		{ q: 'How much of my gift reaches the children?', a: 'Eighty-three per cent of everything we spend goes to programme costs. The rest covers audit, rent, compliance and the staff who run the case work. We publish the split in the annual report and the accounts are independently audited.' },
		{ q: 'Can I pay by mobile money?', a: 'Yes — MTN and Airtel are the rails most of our Ugandan donors use. Card payments are also accepted for donors giving from outside Uganda.' },
		{ q: 'Can I choose which child I sponsor?', a: 'We match you rather than let you browse. Children are not a catalogue, and the child who most needs a sponsor is rarely the one who photographs best. You will receive termly reports on the child you support.' },
		{ q: 'Is my donation tax deductible?', a: 'For Ugandan taxpayers, donations to a registered NGO may be deductible. We issue a receipt for every gift. Donors elsewhere should check their own rules — we will provide whatever documentation your authority asks for.' },
		{ q: 'Can I stop a monthly gift?', a: 'At any time, by replying to any email we send you or calling the office. We will not ask you to justify it.' },
	].map( ( item, index ) => `<div class="bgcf-faq__item">
		<h3 class="bgcf-faq__heading">
			<button type="button" class="bgcf-faq__trigger" aria-expanded="false" aria-controls="faq-panel-${ index }" id="faq-trigger-${ index }" data-bgcf-accordion-trigger>
				<span>${ esc( item.q ) }</span>
				${ icon( 'chevron-down', 'bgcf-faq__icon' ) }
			</button>
		</h3>
		<div class="bgcf-faq__panel" id="faq-panel-${ index }" role="region" aria-labelledby="faq-trigger-${ index }" hidden>
			<p>${ esc( item.a ) }</p>
		</div>
	</div>` ).join( '' );

	return `
${ masthead( {
	eyebrow: 'Donate',
	title: 'Give to a child you will hear about again',
	lead: 'Every donor receives termly reports on the child they support, and the audited accounts once a year.',
	crumbs: [ 'Donate' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', id: 'donate-form', labelledby: 'bgcf-amount-title' } ) }
	${ sectionHeading( {
		eyebrow: 'Choose an amount',
		title: 'What your gift does',
		lead: 'Figures are what the programme actually costs us, not a marketing round number.',
		id: 'bgcf-amount-title',
	} ) }
	<div class="bgcf-grid bgcf-grid--cols-4">${ amounts }</div>

	<div class="bgcf-section__footer">
		<p class="bgcf-u-measure">
			Online giving is handled by Flutterwave, which accepts MTN and Airtel mobile
			money in Uganda as well as international cards. We never see or store your
			payment details.
		</p>
		${ btn( 'Give by mobile money or card', '#', { variant: 'primary', iconName: 'heart', size: 'lg' } ) }
	</div>
${ sectionClose() }

${ sectionOpen( { background: 'surface', spacing: 'md', labelledby: 'bgcf-bank-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-2">
		<div class="bgcf-u-flow">
			<p class="bgcf-u-eyebrow">Other ways to give</p>
			<h2 id="bgcf-bank-title">Bank transfer and standing orders</h2>
			<p>For larger gifts and institutional donors, a direct transfer costs us less in
			fees than a card payment — which means more of it reaches the programme.</p>
			<p>Contact <a href="mailto:${ esc( ORG.email ) }">${ esc( ORG.email ) }</a> for our
			bank details and we will send them with a signed request letter.</p>
		</div>
		<div>${ photo( 'A finance officer reconciling receipts at the foundation office', '4x3', 5 ) }</div>
	</div>
${ sectionClose() }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-faq-title' } ) }
	${ sectionHeading( { eyebrow: 'Questions', title: 'Before you give', id: 'bgcf-faq-title' } ) }
	<div class="bgcf-faq" data-bgcf-accordion>${ faqs }</div>
${ sectionClose() }
`;
}

function contact() {
	return `
${ masthead( {
	eyebrow: 'Contact',
	title: 'Talk to us',
	lead: 'The office is staffed on weekdays. We answer email within two working days.',
	crumbs: [ 'Contact' ],
} ) }

${ sectionOpen( { background: 'base', spacing: 'md', labelledby: 'bgcf-contact-title' } ) }
	<div class="bgcf-grid bgcf-grid--cols-2">

		<div class="bgcf-u-flow">
			<h2 id="bgcf-contact-title">Where to find us</h2>
			<address class="bgcf-site-footer__address">
				${ ORG.address.map( ( line ) => esc( line ) ).join( '<br />' ) }
			</address>
			<ul class="bgcf-site-footer__contact">
				<li class="bgcf-site-footer__contact-item">
					${ icon( 'phone' ) } <a href="tel:${ esc( ORG.phoneHref ) }">${ esc( ORG.phone ) }</a>
				</li>
				<li class="bgcf-site-footer__contact-item">
					${ icon( 'mail' ) } <a href="mailto:${ esc( ORG.email ) }">${ esc( ORG.email ) }</a>
				</li>
				<li class="bgcf-site-footer__contact-item">
					${ icon( 'clock' ) } <span>${ esc( ORG.hours ) }</span>
				</li>
			</ul>
			<p class="bgcf-u-eyebrow">${ esc( ORG.regNo ) }</p>
			<h3>Reporting a safeguarding concern</h3>
			<p>If a child is at immediate risk, contact the Uganda Police Child and Family
			Protection Unit first. To report a concern about anyone connected to this
			organisation, email our safeguarding lead directly — that line does not go
			through the general inbox.</p>
		</div>

		<div>
			<h2>Send us a message</h2>
			<form class="bgcf-form" data-bgcf-static-form="contact" novalidate>
				<div class="bgcf-field">
					<label class="bgcf-field__label" for="c-name">Your name</label>
					<input type="text" id="c-name" name="name" autocomplete="name" required />
				</div>
				<div class="bgcf-field">
					<label class="bgcf-field__label" for="c-email">Email address</label>
					<input type="email" id="c-email" name="email" autocomplete="email" required />
				</div>
				<div class="bgcf-field">
					<label class="bgcf-field__label" for="c-subject">Subject</label>
					<select id="c-subject" name="subject">
						<option>General enquiry</option>
						<option>Donations and sponsorship</option>
						<option>Volunteering</option>
						<option>Partnership</option>
						<option>Press</option>
					</select>
				</div>
				<div class="bgcf-field">
					<label class="bgcf-field__label" for="c-message">Message</label>
					<textarea id="c-message" name="message" rows="6" required></textarea>
				</div>
				<div class="bgcf-field bgcf-field--honeypot" aria-hidden="true">
					<label for="c-website">Leave this field empty</label>
					<input type="text" id="c-website" name="website" tabindex="-1" autocomplete="off" />
				</div>
				<button type="submit" class="bgcf-btn bgcf-btn--primary bgcf-btn--lg">
					<span class="bgcf-btn__label">Send message</span>
				</button>
				<p class="bgcf-form-status" data-bgcf-static-status role="status"></p>
			</form>
		</div>

	</div>
${ sectionClose() }
`;
}

/**
 * A policy page: a masthead followed by a single prose column.
 *
 * @param {Object} options eyebrow, title, lead, crumb, sections.
 * @returns {string} Markup.
 */
function policyPage( { eyebrow, title, lead, crumb, sections } ) {
	const body = sections.map( ( section ) => `<h2>${ esc( section.heading ) }</h2>
		${ section.paragraphs.map( ( p ) => `<p>${ esc( p ) }</p>` ).join( '' ) }
		${ section.list ? `<ul>${ section.list.map( ( li ) => `<li>${ esc( li ) }</li>` ).join( '' ) }</ul>` : '' }` ).join( '' );

	return `
${ masthead( { eyebrow, title, lead, crumbs: [ crumb ] } ) }

${ sectionOpen( { background: 'base', spacing: 'md', width: 'narrow' } ) }
	<div class="bgcf-prose">${ body }</div>
${ sectionClose() }
`;
}

function safeguarding() {
	return policyPage( {
		eyebrow: 'Policy',
		title: 'Child safeguarding',
		lead: 'Every adult who works or volunteers with us is screened, trained and accountable. This is the short version; the full policy is available on request.',
		crumb: 'Child safeguarding',
		sections: [
			{
				heading: 'Our commitment',
				paragraphs: [
					'Children have the right to be safe wherever they meet this organisation — at a partner school, at a health day, at our office, or in their own home during a visit from one of our staff.',
					'We accept that safeguarding failures happen in organisations that assume they cannot. Ours is designed on the opposite assumption.',
				],
			},
			{
				heading: 'What we require of everyone who works with us',
				paragraphs: [ 'Before any contact with children, every member of staff and every volunteer must:' ],
				list: [
					'Complete a criminal record check and provide two references we take up directly',
					'Sign the code of conduct and complete safeguarding training before their first placement',
					'Never be alone with a child where they cannot be observed',
					'Never photograph a child without the documented consent of a guardian',
					'Report any concern the same day, whether or not they believe it is serious',
				],
			},
			{
				heading: 'Images and stories of children',
				paragraphs: [
					'We never publish an identifiable child without written consent from a guardian, recorded and retained. Consent can be withdrawn at any time and we will remove the material.',
					'We do not use a child\'s full name alongside their photograph, and we do not publish details that would let someone locate a child.',
				],
			},
			{
				heading: 'Reporting a concern',
				paragraphs: [
					'If a child is at immediate risk, contact the Uganda Police Child and Family Protection Unit first.',
					`To report a concern about anyone connected to this organisation, email our safeguarding lead at ${ ORG.email }. That line is monitored by the safeguarding lead and the board chair, and does not pass through the general inbox. You may report anonymously.`,
					'Every report is acknowledged within one working day and recorded, whatever the outcome.',
				],
			},
		],
	} );
}

function privacy() {
	return policyPage( {
		eyebrow: 'Policy',
		title: 'Privacy notice',
		lead: 'What we collect, why we collect it, and how to make us delete it.',
		crumb: 'Privacy notice',
		sections: [
			{
				heading: 'What this site collects',
				paragraphs: [
					'This website collects nothing until you choose to send it something. There is no advertising, no tracking pixel and no third-party analytics on these pages.',
					'When you submit the contact, volunteer or newsletter form, we receive the details you typed into it and nothing else.',
				],
			},
			{
				heading: 'What we do with it',
				paragraphs: [ 'We use what you send us only to reply to you and to do the thing you asked for.' ],
				list: [
					'Contact form: to answer your enquiry',
					'Volunteer form: to assess your application and arrange a safeguarding check',
					'Newsletter: to send you four updates a year, until you tell us to stop',
				],
			},
			{
				heading: 'Donations',
				paragraphs: [
					'Online payments are processed by Flutterwave. Your card or mobile money details are entered on their systems and are never seen, transmitted or stored by us.',
					'We keep a record of the amount, the date and your contact details, because we are required to account for every donation we receive.',
				],
			},
			{
				heading: 'Your rights',
				paragraphs: [
					`Write to ${ ORG.email } and we will tell you what we hold about you, correct it, or delete it. We will not ask you why.`,
					'We do not sell, rent or share supporter data with anyone, and we never will.',
				],
			},
		],
	} );
}

function notFound() {
	return `
${ sectionOpen( { background: 'primary', spacing: 'lg', labelledby: 'bgcf-404-title' } ) }
	<div class="bgcf-empty">
		<h1 class="bgcf-empty__title" id="bgcf-404-title">That page is not here</h1>
		<p class="bgcf-empty__text">The link may be out of date, or we may have moved the page.
		Try the programmes, or tell us what you were looking for.</p>
		<div class="bgcf-cta__actions">
			${ btn( 'Go to the homepage', '/', { variant: 'primary', iconName: 'home' } ) }
			${ btn( 'Contact us', '/contact/', { variant: 'ghost' } ) }
		</div>
	</div>
${ sectionClose() }
`;
}

/* ------------------------------------------------------------------ *
 * The page manifest the generator walks.
 * ------------------------------------------------------------------ */

export const PAGES = [
	{
		url: '/',
		file: 'index.html',
		title: 'Home',
		description: 'Boundless Gracia Children Foundation keeps children in Nansana and Wakiso District in school, in good health and safe at home. Sponsor a child from UGX 180,000 a month.',
		render: home,
	},
	{
		url: '/about/',
		file: 'about/index.html',
		title: 'About us',
		description: 'A Ugandan child-focused organisation run from Nansana since 2014. Our mission, our history, our people and our audited accounts.',
		render: about,
	},
	{
		url: '/programs/',
		file: 'programs/index.html',
		title: 'Programmes',
		description: 'Education and scholarships, health and nutrition, child protection, and skills and livelihoods across twelve partner schools in Wakiso District.',
		render: programs,
	},
	{
		url: '/stories/',
		file: 'stories/index.html',
		title: 'Stories',
		description: 'Success stories, programme updates and case studies from the children, families and staff of Boundless Gracia Children Foundation.',
		render: stories,
	},
	{
		url: '/events/',
		file: 'events/index.html',
		title: 'Events',
		description: 'Kit packing days, community health days and the annual supporters evening. Everything we run is open to supporters.',
		render: events,
	},
	{
		url: '/get-involved/',
		file: 'get-involved/index.html',
		title: 'Get involved',
		description: 'Give monthly, volunteer, partner with us, donate goods, fundraise or leave a legacy. Six ways to support children in Nansana.',
		render: getInvolved,
	},
	{
		url: '/donate/',
		file: 'donate/index.html',
		title: 'Donate',
		description: 'UGX 50,000 keeps one child in school for a term. Give by MTN or Airtel mobile money, card or bank transfer.',
		render: donate,
	},
	{
		url: '/contact/',
		file: 'contact/index.html',
		title: 'Contact',
		description: 'Our office on Hoima Road in Nansana, Wakiso District. Phone, email, opening hours and a contact form.',
		render: contact,
	},
	{
		url: '/safeguarding/',
		file: 'safeguarding/index.html',
		title: 'Child safeguarding',
		description: 'How Boundless Gracia Children Foundation screens, trains and holds accountable everyone who works with children, and how to report a concern.',
		render: safeguarding,
	},
	{
		url: '/privacy/',
		file: 'privacy/index.html',
		title: 'Privacy notice',
		description: 'What this site collects, what we do with it, and how to ask us to delete it.',
		render: privacy,
	},
	{
		url: '/404',
		file: '404.html',
		title: 'Page not found',
		description: 'That page is not here.',
		render: notFound,
		noindex: true,
	},
];
