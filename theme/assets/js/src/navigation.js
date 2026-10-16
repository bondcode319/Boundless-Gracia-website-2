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
 * Submenu disclosures, in the header bar and in the drawer.
 *
 * One behaviour for both: the button toggles `hidden` on the list it owns and
 * flips `aria-expanded`. In the header bar, CSS also opens the panel on hover —
 * hover is a convenience, the button is the contract.
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
 * Adds `data-bgcf-stuck` once the page has scrolled past the header, which the
 * stylesheet uses to apply the compact height and the shadow. Read inside
 * `requestAnimationFrame` so the scroll listener never measures layout on the
 * scroll thread.
 *
 * @returns {void}
 */
export function initStickyHeader() {
	const header = document.querySelector('[data-bgcf-header]');

	if (!header) {
		return;
	}

	let ticking = false;
	let lastState = false;

	const update = function () {
		const stuck = window.scrollY > 8;

		if (stuck !== lastState) {
			lastState = stuck;
			header.toggleAttribute('data-bgcf-stuck', stuck);
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
