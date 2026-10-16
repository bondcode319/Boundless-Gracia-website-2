/**
 * Swiper carousels (testimonials, partner marquee, story sliders).
 *
 * Swiper is enqueued on demand by `bgcf_use_swiper()`, so `window.Swiper` may
 * legitimately be absent. When it is, the markup stays what the server rendered:
 * a horizontally scrollable list with CSS scroll-snap. That is a deliberate
 * progressive-enhancement floor, not a fallback nobody tested — the SCSS styles
 * `.bgcf-carousel` without `data-bgcf-carousel-ready` as a usable swipe list.
 *
 * ACCESSIBILITY:
 *   - Real `<button>` previous/next controls come from the server; Swiper is
 *     pointed at them rather than generating its own divs.
 *   - `a11y` is left on (it manages `aria-live` on the container and labels the
 *     controls), with our own localized strings.
 *   - Autoplay never starts under reduced motion, and always stops on
 *     interaction and on focus — a carousel that moves under a keyboard user's
 *     cursor fails WCAG 2.2.2.
 *
 * @package Bgcf
 * @since   1.2.0
 */

import { data } from './request.js';

/**
 * Whether autoplay is permissible for this visitor.
 *
 * @returns {boolean} True when motion is welcome.
 */
function motionOk() {
	return document.documentElement.getAttribute('data-bgcf-motion') === 'on';
}

/**
 * Read a carousel's per-instance options from its data attributes.
 *
 * The server owns these values (they come from ACF), so the markup is the only
 * configuration surface — no options are hardcoded here.
 *
 * @param {Element} root Carousel root.
 * @returns {Object} Swiper options.
 */
function optionsFor(root) {
	const perView = Number(root.getAttribute('data-bgcf-per-view') || 1);
	const autoplay = root.hasAttribute('data-bgcf-autoplay') && motionOk();
	const loop = root.hasAttribute('data-bgcf-loop');
	const i18n = data().i18n || {};

	const options = {
		slidesPerView: 1,
		spaceBetween: 24,
		loop: loop,
		watchOverflow: true,
		// Keyboard support is only enabled when the carousel has focus, so arrow
		// keys still scroll the page everywhere else.
		keyboard: { enabled: true, onlyInViewport: true },
		a11y: {
			enabled: true,
			prevSlideMessage: String(i18n.prevSlide || 'Previous'),
			nextSlideMessage: String(i18n.nextSlide || 'Next'),
			paginationBulletMessage: String(i18n.goToSlide || 'Go to slide {{index}}')
		},
		navigation: {
			prevEl: root.querySelector('[data-bgcf-carousel-prev]'),
			nextEl: root.querySelector('[data-bgcf-carousel-next]')
		},
		breakpoints: {
			640: { slidesPerView: Math.min(2, perView) },
			1024: { slidesPerView: perView }
		}
	};

	const pagination = root.querySelector('[data-bgcf-carousel-pagination]');

	if (pagination) {
		options.pagination = { el: pagination, clickable: true };
	}

	if (autoplay) {
		options.autoplay = {
			delay: Number(root.getAttribute('data-bgcf-autoplay') || 6000),
			disableOnInteraction: false,
			pauseOnMouseEnter: true
		};
	}

	return options;
}

/**
 * Initialise every carousel on the page.
 *
 * @returns {void}
 */
export function initCarousels() {
	const roots = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-carousel]'));

	if (!roots.length || typeof window.Swiper !== 'function') {
		return;
	}

	roots.forEach(function (root) {
		const instance = new window.Swiper(root, optionsFor(root));

		root.setAttribute('data-bgcf-carousel-ready', '');

		const pause = root.querySelector('[data-bgcf-carousel-pause]');

		// Any autoplay needs a visible stop control (WCAG 2.2.2). The button is
		// rendered by the server only when autoplay is configured.
		if (pause && instance.autoplay) {
			pause.hidden = false;

			pause.addEventListener('click', function () {
				const running = instance.autoplay.running && !instance.autoplay.paused;
				const i18n = data().i18n || {};

				if (running) {
					instance.autoplay.stop();
					pause.setAttribute('aria-pressed', 'true');
					pause.setAttribute('aria-label', String(i18n.playSlides || 'Start the slideshow'));
				} else {
					instance.autoplay.start();
					pause.setAttribute('aria-pressed', 'false');
					pause.setAttribute('aria-label', String(i18n.pauseSlides || 'Pause the slideshow'));
				}
			});
		}

		// Stop autoplay the moment a keyboard user lands inside, and leave it
		// stopped: silently resuming moves content out from under them.
		if (instance.autoplay) {
			root.addEventListener('focusin', function () {
				instance.autoplay.stop();

				if (pause) {
					pause.setAttribute('aria-pressed', 'true');
				}
			});
		}
	});
}
