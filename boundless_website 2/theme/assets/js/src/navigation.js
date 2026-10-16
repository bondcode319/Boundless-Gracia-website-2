/**
 * Header behaviour: the mobile drawer, submenu disclosures, the search panel
 * and the sticky header state.
 *
 * All four bind to `data-bgcf-*` attributes emitted by header.php, the drawer
 * partial and `Bgcf_Walker_Nav_Menu`. None of them invent markup: if this file
 * never loads, the drawer stays `hidden`, the submenus stay `hidden`, and the
 * header is a plain bar with working CTAs and a working search page. That is the
 * no-JS baseline, and it is a usable site rather than a broken one.
 *
 * @package Bgcf
 * @since   1.2.0
 */

import { data } from './request.js';

/**
 * Elements that can hold keyboard focus, for the drawer's focus trap.
 *
 * `:not([hidden])` matters: collapsed submenus are in the DOM, and trapping
 * focus into an invisible list is the classic version of this bug.
 *
 * @type {string}
 */
const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])'
]
	.map(function (selector) {
		return selector + ':not([hidden])';
	})
	.join(',');

/**
 * Visible, focusable descendants of a container, in document order.
 *
 * @param {Element} root Container.
 * @returns {Element[]} Focusable elements.
 */
function focusable(root) {
	return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE)).filter(function (element) {
		// offsetParent is null for anything display:none, including a parent's
		// collapsed submenu — cheaper and more reliable than reading styles.
		return element.offsetParent !== null || element === document.activeElement;
	});
}

/**
 * The off-canvas navigation drawer.
 *
 * @returns {void}
 */
export function initDrawer() {
	const drawer = document.querySelector('[data-bgcf-drawer]');
	const toggle = document.querySelector('[data-bgcf-drawer-toggle]');

	if (!drawer || !toggle) {
		return;
	}

	const backdrop = document.querySelector('[data-bgcf-drawer-backdrop]');
	const closers = Array.prototype.slice.call(drawer.querySelectorAll('[data-bgcf-drawer-close]'));
	const label = toggle.querySelector('[data-bgcf-drawer-label]');
	const i18n = data().i18n || {};
	const labels = {
		open: String(i18n.menuOpen || 'Menu'),
		close: String(i18n.menuClose || 'Close')
	};

	let isOpen = false;

	/**
	 * Lock or release background scrolling.
	 *
	 * `position: fixed` on the body is avoided deliberately — it loses the
	 * scroll position on iOS. `overflow: hidden` plus the drawer's own
	 * `overscroll-behavior: contain` is enough.
	 *
	 * @param {boolean} locked Whether to lock.
	 * @returns {void}
	 */
	function lockScroll(locked) {
		document.documentElement.classList.toggle('bgcf-has-drawer', locked);
	}

	/**
	 * Open the drawer and move focus into it.
	 *
	 * @returns {void}
	 */
	function open() {
		if (isOpen) {
			return;
		}

		isOpen = true;
		drawer.hidden = false;

		if (backdrop) {
			backdrop.hidden = false;
		}

		// Force a frame so the transition has a start state to animate from.
		window.requestAnimationFrame(function () {
			drawer.setAttribute('data-bgcf-open', '');

			if (backdrop) {
				backdrop.setAttribute('data-bgcf-open', '');
			}
		});

		toggle.setAttribute('aria-expanded', 'true');
		lockScroll(true);

		if (label) {
			label.textContent = labels.close;
		}

		const first = closers[0] || focusable(drawer)[0];

		if (first) {
			first.focus();
		}
	}

	/**
	 * Close the drawer and return focus to the button that opened it.
	 *
	 * @param {boolean} [restoreFocus=true] Whether to move focus back.
	 * @returns {void}
	 */
	function close(restoreFocus) {
		if (!isOpen) {
			return;
		}

		isOpen = false;
		drawer.removeAttribute('data-bgcf-open');

		if (backdrop) {
			backdrop.removeAttribute('data-bgcf-open');
		}

		toggle.setAttribute('aria-expanded', 'false');
		lockScroll(false);

		if (label) {
			label.textContent = labels.open;
		}

		/**
		 * Hide only once the exit transition has finished, so the panel does not
		 * vanish instantly. `transitionend` is not guaranteed to fire (reduced
		 * motion collapses the duration to zero), hence the timeout fallback.
		 *
		 * @returns {void}
		 */
		const finish = function () {
			if (!isOpen) {
				drawer.hidden = true;

				if (backdrop) {
					backdrop.hidden = true;
				}
			}
		};

		window.setTimeout(finish, 260);

		if (restoreFocus !== false) {
			toggle.focus();
		}
	}

	toggle.addEventListener('click', function () {
		if (isOpen) {
			close();
		} else {
			open();
		}
	});

	closers.forEach(function (button) {
		button.addEventListener('click', function () {
			close();
		});
	});

	if (backdrop) {
		backdrop.addEventListener('click', function () {
			close();
		});
	}

	document.addEventListener('keydown', function (event) {
		if (!isOpen) {
			return;
		}

		if (event.key === 'Escape') {
			close();
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		const items = focusable(drawer);

		if (!items.length) {
			return;
		}

		const first = items[0];
		const last = items[items.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	});

	// Following a link inside the drawer navigates away; on a same-page anchor it
	// does not, and leaving the panel covering the target is a dead end.
	drawer.addEventListener('click', function (event) {
		const link = event.target.closest('a[href]');

		if (link && link.getAttribute('href').charAt(0) === '#') {
			close(false);
		}
	});

	// A viewport that grows past the desktop breakpoint leaves the drawer open
	// over a header that already shows the full menu.
	if (typeof window.matchMedia === 'function') {
		const desktop = window.matchMedia('(min-width: 1024px)');

		const onChange = function (event) {
			if (event.matches) {
				close(false);
			}
		};

		if (typeof desktop.addEventListener === 'function') {
			desktop.addEventListener('change', onChange);
		} else if (typeof desktop.addListener === 'function') {
			desktop.addListener(onChange);
		}
	}
}

/**
 * Submenu disclosures for the CLASSIC nav walker.
 *
 * One behaviour for both placements: the button toggles `hidden` on the list it
 * owns and flips `aria-expanded`.
 *
 * STATUS IN 2.0: this finds nothing on a default install and returns
 * immediately. The Horizon header renders a two-level mega menu instead, handled
 * by `mega-menu.js`, and the drawer's accordion is handled by `accordion.js`.
 *
 * It is kept for the same reason `Bgcf_Walker_Nav_Menu` is kept in inc/nav.php:
 * a child theme or a plugin may render a classic dropdown, and that menu needs
 * working disclosures. If you are adding navigation, do not extend this — use
 * the mega menu.
 *
 * @returns {void}
 */
export function initSubmenus() {
	const toggles = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-submenu-toggle]'));

	if (!toggles.length) {
		return;
	}

	/**
	 * Collapse every open submenu except the one passed.
	 *
	 * Scoped to the toggle's own menu so closing a header dropdown never
	 * collapses the drawer's accordion.
	 *
	 * @param {?Element} except Toggle to leave alone.
	 * @param {?Element} scope  Menu to search within.
	 * @returns {void}
	 */
	function closeOthers(except, scope) {
		toggles.forEach(function (other) {
			if (other === except) {
				return;
			}

			if (scope && !scope.contains(other)) {
				return;
			}

			const panel = document.getElementById(other.getAttribute('aria-controls'));

			other.setAttribute('aria-expanded', 'false');

			if (panel) {
				panel.hidden = true;
			}
		});
	}

	toggles.forEach(function (toggle) {
		const panel = document.getElementById(toggle.getAttribute('aria-controls'));

		if (!panel) {
			return;
		}

		toggle.addEventListener('click', function () {
			const expanded = toggle.getAttribute('aria-expanded') === 'true';

			if (!expanded) {
				closeOthers(toggle, toggle.closest('.bgcf-nav'));
			}

			toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			panel.hidden = expanded;
		});

		// ESC inside a submenu closes it and returns focus to its own button —
		// the standard disclosure contract (WAI-ARIA APG, Disclosure pattern).
		panel.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				toggle.setAttribute('aria-expanded', 'false');
				panel.hidden = true;
				toggle.focus();
			}
		});
	});

	// Clicking elsewhere closes header dropdowns. The drawer is excluded: its
	// accordion should stay as the visitor left it while they scroll.
	document.addEventListener('click', function (event) {
		if (event.target.closest('[data-bgcf-drawer]')) {
			return;
		}

		if (!event.target.closest('.bgcf-nav--primary')) {
			closeOthers(null, null);
		}
	});

	document.addEventListener('focusin', function (event) {
		if (!event.target.closest('.bgcf-nav--primary') && !event.target.closest('[data-bgcf-drawer]')) {
			closeOthers(null, null);
		}
	});
}

/**
 * The header search disclosure.
 *
 * @returns {void}
 */
export function initSearchToggle() {
	const toggle = document.querySelector('[data-bgcf-search-toggle]');
	const panel = document.querySelector('[data-bgcf-search-panel]');

	if (!toggle || !panel) {
		return;
	}

	/**
	 * Close the panel.
	 *
	 * @param {boolean} [restoreFocus=false] Whether to focus the toggle.
	 * @returns {void}
	 */
	function close(restoreFocus) {
		toggle.setAttribute('aria-expanded', 'false');
		panel.hidden = true;

		if (restoreFocus) {
			toggle.focus();
		}
	}

	toggle.addEventListener('click', function () {
		const expanded = toggle.getAttribute('aria-expanded') === 'true';

		if (expanded) {
			close();
			return;
		}

		toggle.setAttribute('aria-expanded', 'true');
		panel.hidden = false;

		const field = panel.querySelector('input[type="search"]');

		if (field) {
			field.focus();
		}
	});

	panel.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			close(true);
		}
	});
}

/**
 * Sticky-header state.
 *
 * Adds `data-bgcf-stuck` once the page has scrolled past the threshold, which
 * the stylesheet uses to apply the compact height and the shadow. On an overlay
 * header it also adds `.is-stuck`, which is what turns the transparent bar
 * solid.
 *
 * TWO THRESHOLDS, because the two headers are answering different questions.
 * A solid header only needs to know "has the page moved at all", so that it can
 * grow a shadow — 8px. A transparent header is asking "is the bar still over the
 * hero photograph", and switching at 8px would make it turn solid on the
 * slightest scroll while three-quarters of the hero is still on screen. 40px is
 * past an accidental trackpad nudge and still well inside the hero.
 *
 * WHY A CLASS AND AN ATTRIBUTE. The attribute is the pre-existing hook, used by
 * the compact-height rule on every header. The class is what the overlay
 * treatment keys off, and it is a class because `_mega-menu.scss` has to
 * negate it (`:not(.is-stuck)`) from a descendant selector — which reads far
 * better as a class than as `:not([data-bgcf-stuck])`.
 *
 * Read inside `requestAnimationFrame` so the scroll listener never measures
 * layout on the scroll thread.
 *
 * @returns {void}
 */
export function initStickyHeader() {
	const header = document.querySelector('[data-bgcf-header]');

	if (!header) {
		return;
	}

	const isOverlay = header.classList.contains('bgcf-site-header--overlay');
	const threshold = isOverlay ? 40 : 8;

	let ticking = false;
	let lastState = false;

	const update = function () {
		const stuck = window.scrollY > threshold;

		if (stuck !== lastState) {
			lastState = stuck;
			header.toggleAttribute('data-bgcf-stuck', stuck);
			header.classList.toggle('is-stuck', stuck);
		}

		ticking = false;
	};

	window.addEventListener(
		'scroll',
		function () {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		},
		{ passive: true }
	);

	update();
}
