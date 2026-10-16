/**
 * Boundless Gracia Children Foundation — front-end behaviour.
 *
 * SOURCE FILE. `npm run build:js` bundles and minifies this (with everything it
 * imports from this directory) to assets/js/main.js — handle `bgcf-app`,
 * deferred, every page. Never edit the compiled file; it is overwritten on every
 * build.
 *
 * ES6, dependency-free. GSAP and Swiper are NOT imported here — they are
 * registered in inc/enqueue.php and enqueued on demand by the blocks that use
 * them, so this baseline bundle stays small.
 *
 * Conventions (ARCHITECTURE §3):
 *   - JS binds to `data-bgcf-*` attributes, NEVER to classes. A class is for
 *     styling; a data attribute is for behaviour.
 *   - `bgcfData` is the localized bridge from inc/enqueue.php (ajaxUrl, nonce,
 *     i18n, …). Treat it as public — it carries no secrets.
 *
 * @package Bgcf
 * @since   1.0.0
 */

import { initDonationForm, initDonationResult } from './donation-form.js';
import { initDrawer, initSearchToggle, initStickyHeader, initSubmenus } from './navigation.js';
import { initCounters, initReveal } from './motion.js';
import { initCarousels } from './carousel.js';
import { initAccordions } from './accordion.js';
import { initAjaxForms } from './forms.js';
import { initGalleries } from './gallery.js';
import { initMaps } from './map.js';
import { initMegaMenu } from './mega-menu.js';
import { initPromoModal } from './promo-modal.js';

(function () {
	'use strict';

	/**
	 * The localized data bridge. Always an object, even if enqueue somehow ran
	 * without it, so callers never have to null-check.
	 *
	 * @type {Object}
	 */
	const data = window.bgcfData || {};

	/**
	 * Does the user tolerate motion? Read live (not cached) because the OS
	 * setting can change while the page is open.
	 *
	 * NOTE: this is the only authority on reduced motion. `bgcfData` cannot
	 * carry it — the server has no way to know, and a cached page would freeze
	 * one visitor's preference for everyone.
	 *
	 * @returns {boolean} True when reduced motion is NOT requested.
	 */
	function motionAllowed() {
		return (
			typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: no-preference)').matches
		);
	}

	/**
	 * Run a callback once the DOM is parsed. `defer` means we are usually already
	 * past DOMContentLoaded, so check readyState first.
	 *
	 * @param {Function} fn Callback.
	 * @returns {void}
	 */
	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn, { once: true });
		} else {
			fn();
		}
	}

	/**
	 * Small helper: collect elements carrying a data hook.
	 *
	 * @param {string}     hook  Attribute name, e.g. 'data-bgcf-header'.
	 * @param {ParentNode} [root=document] Search root.
	 * @returns {Element[]} Matching elements.
	 */
	function hooks(hook, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll('[' + hook + ']'));
	}

	/**
	 * Motion gate. Sets `html[data-bgcf-motion="on"|"off"]`, which the SCSS
	 * `reveal-from()` mixin and motion.js both key off: reveal animations only
	 * arm when the value is "on", so no-JS and reduced-motion users never meet an
	 * invisible page.
	 *
	 * Re-evaluates when the OS preference changes.
	 *
	 * @returns {void}
	 */
	function initMotionGate() {
		const root = document.documentElement;

		const apply = function () {
			root.setAttribute('data-bgcf-motion', motionAllowed() ? 'on' : 'off');
		};

		apply();

		if (typeof window.matchMedia === 'function') {
			const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
			// addEventListener is the modern API; addListener the Safari fallback.
			if (typeof mq.addEventListener === 'function') {
				mq.addEventListener('change', apply);
			} else if (typeof mq.addListener === 'function') {
				mq.addListener(apply);
			}
		}
	}

	/**
	 * Progressive-enhancement marker. Swaps `no-js` for `js` on <html> so CSS can
	 * style the enhanced state without a flash.
	 *
	 * @returns {void}
	 */
	function initJsFlag() {
		const root = document.documentElement;
		root.classList.remove('no-js');
		root.classList.add('js');
	}

	/**
	 * The module registry.
	 *
	 * Order matters in exactly one place: `motionGate` must run before anything
	 * that reads `data-bgcf-motion` (reveal, counters, carousel autoplay). Object
	 * key order is insertion order in every engine we target, so declaring it
	 * first is sufficient.
	 *
	 * @type {Object<string, Function>}
	 */
	const modules = {
		motionGate: initMotionGate,
		jsFlag: initJsFlag,
		drawer: initDrawer,
		megaMenu: initMegaMenu,
		submenus: initSubmenus,
		searchToggle: initSearchToggle,
		stickyHeader: initStickyHeader,
		reveal: initReveal,
		counters: initCounters,
		carousels: initCarousels,
		accordions: initAccordions,
		ajaxForms: initAjaxForms,
		galleries: initGalleries,
		maps: initMaps,
		donationForm: initDonationForm,
		donationResult: initDonationResult,
		// Last, and deliberately so: the promo modal checks whether the drawer
		// is open before showing itself, so the drawer has to be wired first.
		promoModal: initPromoModal
	};

	/**
	 * Modules already run, so a late registration is executed immediately
	 * instead of being silently dropped.
	 *
	 * @type {Object<string, boolean>}
	 */
	const booted = {};

	/**
	 * Whether `boot()` has already run for this page.
	 *
	 * @type {boolean}
	 */
	let hasBooted = false;

	/**
	 * Run one module, isolating failures so a single broken behaviour never
	 * takes the rest of the page's JS down with it.
	 *
	 * @param {string} name Module name, for the error message.
	 * @returns {void}
	 */
	function run(name) {
		if (booted[name]) {
			return;
		}

		booted[name] = true;

		try {
			modules[name]();
		} catch (error) {
			if (window.console && window.console.error) {
				// `window.console` rather than the bare `console` global: the
				// no-console lint rule guards accidental debug logging, and this
				// is a deliberate, guarded error report.
				window.console.error('[bgcf] module "' + name + '" failed:', error);
			}
		}
	}

	/**
	 * Run every registered module.
	 *
	 * @returns {void}
	 */
	function boot() {
		hasBooted = true;
		Object.keys(modules).forEach(run);
	}

	// Expose a tiny namespace so block bundles and inline scripts can register
	// behaviours without re-implementing the boot loop or the helpers.
	window.bgcf = window.bgcf || {};
	window.bgcf.data = data;
	window.bgcf.ready = ready;
	window.bgcf.hooks = hooks;
	window.bgcf.motionAllowed = motionAllowed;

	/**
	 * Register a behaviour.
	 *
	 * Because this bundle is deferred, `boot()` has almost always already run by
	 * the time a later script calls this — so a registration that arrives after
	 * boot is executed immediately rather than being added to a registry nobody
	 * will read again. (The previous implementation only stored it, which meant
	 * every late registration was a silent no-op.)
	 *
	 * @param {string}   name Unique module name.
	 * @param {Function} fn   Initialiser.
	 * @returns {void}
	 */
	window.bgcf.register = function (name, fn) {
		if (typeof fn !== 'function') {
			return;
		}

		modules[name] = fn;

		if (hasBooted) {
			ready(function () {
				run(name);
			});
		}
	};

	ready(boot);
})();
