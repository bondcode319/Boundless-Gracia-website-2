/**
 * Scroll reveals and impact counters.
 *
 * Both use IntersectionObserver rather than GSAP's ScrollTrigger for the
 * trigger, and GSAP only for the tween when it is actually on the page. Reason:
 * ScrollTrigger is ~40 KB and is only enqueued by blocks that need it
 * (`bgcf_use_gsap()`), so this module must work without it. GSAP, when present,
 * buys smoother easing — not correctness.
 *
 * ACCESSIBILITY, non-negotiable:
 *   - Reveals are armed only when `html[data-bgcf-motion="on"]`, which main.js
 *     sets from `prefers-reduced-motion`. The SCSS keys its `opacity: 0` off the
 *     same attribute, so content is visible by default and stays visible for
 *     no-JS and reduced-motion visitors.
 *   - Counters render their final value in the HTML. The count-up is a visual
 *     effect over text that is already correct, there is no `aria-live` on it,
 *     and under reduced motion it is skipped entirely rather than sped up.
 *
 * @package Bgcf
 * @since   1.2.0
 */

/**
 * Whether reveal animations may run at all.
 *
 * @returns {boolean} True when motion is welcome and observers are supported.
 */
function canAnimate() {
	return (
		typeof window.IntersectionObserver === 'function' &&
		document.documentElement.getAttribute('data-bgcf-motion') === 'on'
	);
}

/**
 * Reveal elements as they enter the viewport.
 *
 * Elements opt in with `data-bgcf-reveal`. An optional
 * `data-bgcf-reveal-stagger` on a parent staggers its children.
 *
 * @returns {void}
 */
export function initReveal() {
	const targets = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-reveal]'));

	if (!targets.length) {
		return;
	}

	if (!canAnimate()) {
		// Mark everything revealed so no CSS rule can leave content hidden.
		targets.forEach(function (element) {
			element.setAttribute('data-bgcf-revealed', '');
		});

		return;
	}

	const observer = new window.IntersectionObserver(
		function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}

				const element = entry.target;
				const delay = Number(element.getAttribute('data-bgcf-reveal-delay') || 0);

				window.setTimeout(function () {
					element.setAttribute('data-bgcf-revealed', '');
				}, delay);

				// One-shot: re-animating on every scroll past is noise, and it
				// keeps the observer list shrinking as the visitor reads.
				observer.unobserve(element);
			});
		},
		{ rootMargin: '0px 0px -12% 0px', threshold: 0.1 }
	);

	targets.forEach(function (element) {
		// Stagger siblings so a card grid arrives as a wave, not a block.
		const parent = element.parentElement;

		if (parent && parent.hasAttribute('data-bgcf-reveal-stagger')) {
			const index = Array.prototype.indexOf.call(parent.children, element);
			element.setAttribute('data-bgcf-reveal-delay', String(Math.min(index, 8) * 80));
		}

		observer.observe(element);
	});
}

/**
 * Format a number the way the server formatted the final value.
 *
 * Reads the locale from the document so thousands separators match the rest of
 * the page rather than defaulting to en-US mid-animation.
 *
 * @param {number} value Current value.
 * @param {number} decimals Decimal places to keep.
 * @returns {string} Formatted number.
 */
function formatNumber(value, decimals) {
	try {
		return value.toLocaleString(document.documentElement.lang || undefined, {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals
		});
	} catch (formatError) {
		// Same reasoning as the donation form: an unformatted number beats none.
		void formatError;
		return String(value.toFixed(decimals));
	}
}

/**
 * Animate a single counter from zero to its rendered value.
 *
 * @param {Element} element Counter element carrying `data-bgcf-counter`.
 * @returns {void}
 */
function runCounter(element) {
	const target = Number(element.getAttribute('data-bgcf-counter'));

	if (!isFinite(target)) {
		return;
	}

	const decimals = Number(element.getAttribute('data-bgcf-counter-decimals') || 0);
	const duration = Number(element.getAttribute('data-bgcf-counter-duration') || 1600);
	const start = window.performance.now();

	const step = function (now) {
		const progress = Math.min((now - start) / duration, 1);
		// easeOutExpo: fast arrival, slow settle — reads as "counting up" rather
		// than "sliding", which is what a linear ramp looks like.
		const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);

		element.textContent = formatNumber(target * eased, decimals);

		if (progress < 1) {
			window.requestAnimationFrame(step);
		} else {
			// Snap to the server-rendered string so rounding can never leave a
			// counter showing 9,999 where the page says 10,000.
			element.textContent = element.getAttribute('data-bgcf-counter-formatted') || formatNumber(target, decimals);
		}
	};

	window.requestAnimationFrame(step);
}

/**
 * Impact counters.
 *
 * @returns {void}
 */
export function initCounters() {
	const counters = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-counter]'));

	if (!counters.length || !canAnimate()) {
		// Nothing to do: the final value is already in the DOM.
		return;
	}

	const observer = new window.IntersectionObserver(
		function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					runCounter(entry.target);
					observer.unobserve(entry.target);
				}
			});
		},
		{ threshold: 0.4 }
	);

	counters.forEach(function (element) {
		observer.observe(element);
	});
}
