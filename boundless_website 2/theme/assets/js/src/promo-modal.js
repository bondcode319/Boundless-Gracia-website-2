/**
 * Seasonal announcement modal.
 *
 * Binds to the `data-bgcf-promo-*` hooks from
 * `partials/global/promo-modal.php`. The partial renders nothing unless an
 * editor has both enabled the modal and given it a heading, so this module's
 * usual outcome is to find nothing and return.
 *
 * WHY IT WAITS
 * ------------
 * The dialog is shown after a delay the editor sets (default four seconds),
 * never during first paint. Two reasons, and both are accessibility rather than
 * taste: stealing focus while the page is still rendering interrupts a screen
 * reader mid-sentence, and a dialog that appears before a visitor has read the
 * heading behind it is an interruption rather than an announcement.
 *
 * It also never appears while the drawer is open, and never on a page the
 * visitor arrived at by following the modal's own link — the first would trap
 * focus between two dialogs, and the second would announce a campaign to
 * somebody already reading it.
 *
 * ONCE PER SESSION, in `sessionStorage`. Not a cookie: a cookie for a purely
 * local UI preference would drag the site into consent-banner territory for no
 * benefit. Not `localStorage`: that is "never again", which hides a December
 * appeal from a visitor who dismissed its announcement in March.
 *
 * Every storage access is wrapped: in a private window, with site data blocked,
 * or inside some embedded webviews, reading `sessionStorage` throws rather than
 * returning null. The fallback is to show the modal, which is the behaviour that
 * loses nothing.
 *
 * @package Bgcf
 * @since   2.0.0
 */

/**
 * The `sessionStorage` key holding the dismissal flag.
 *
 * @type {string}
 */
const STORAGE_KEY = 'bgcf-promo-dismissed';

/**
 * Elements that can hold keyboard focus, for the focus trap.
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
 * Read the dismissal flag, treating any storage failure as "not dismissed".
 *
 * @returns {boolean} True when this session has already dismissed the modal.
 */
function wasDismissed() {
	try {
		return window.sessionStorage.getItem(STORAGE_KEY) === '1';
	} catch {
		// Optional catch binding: the error is genuinely not interesting here.
		// Any storage failure means "we cannot know", and the safe answer to
		// "has this been dismissed?" is no.
		return false;
	}
}

/**
 * Record the dismissal, ignoring any storage failure.
 *
 * A failure here means the modal will reappear on the next page view, which is
 * mildly annoying and entirely harmless — so it is swallowed rather than
 * reported.
 *
 * @returns {void}
 */
function remember() {
	try {
		window.sessionStorage.setItem(STORAGE_KEY, '1');
	} catch {
		// Intentionally empty: see the doc comment above.
	}
}

/**
 * Wire the promo modal.
 *
 * @returns {void}
 */
export function initPromoModal() {
	const modal = document.querySelector('[data-bgcf-promo]');

	if (!modal || wasDismissed()) {
		return;
	}

	const backdrop = document.querySelector('[data-bgcf-promo-backdrop]');
	const closers = Array.prototype.slice.call(modal.querySelectorAll('[data-bgcf-promo-close]'));

	/**
	 * Where focus was before the dialog opened, so it can be put back.
	 *
	 * @type {?Element}
	 */
	let origin = null;

	/**
	 * Whether the dialog is currently showing.
	 *
	 * @type {boolean}
	 */
	let isOpen = false;

	/**
	 * Visible, focusable descendants of the dialog, in document order.
	 *
	 * @returns {Element[]} Focusable elements.
	 */
	function focusable() {
		return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE));
	}

	/**
	 * Close the dialog and return focus to wherever it came from.
	 *
	 * @returns {void}
	 */
	function close() {
		if (!isOpen) {
			return;
		}

		isOpen = false;
		modal.classList.remove('is-open');

		if (backdrop) {
			backdrop.classList.remove('is-open');
		}

		document.documentElement.classList.remove('bgcf-has-modal');
		remember();

		window.setTimeout(function () {
			if (!isOpen) {
				modal.hidden = true;

				if (backdrop) {
					backdrop.hidden = true;
				}
			}
		}, 260);

		// `origin` can have been removed from the document in the meantime (a
		// dynamically replaced header, say), in which case focusing it throws.
		if (origin && document.contains(origin) && typeof origin.focus === 'function') {
			origin.focus();
		}
	}

	/**
	 * Show the dialog and move focus into it.
	 *
	 * @returns {void}
	 */
	function open() {
		if (isOpen || wasDismissed()) {
			return;
		}

		// Never two dialogs at once: the drawer is modal too, and opening this
		// over it would trap focus between them.
		const drawer = document.querySelector('[data-bgcf-drawer]');

		if (drawer && !drawer.hidden) {
			return;
		}

		isOpen = true;
		origin = document.activeElement;

		modal.hidden = false;

		if (backdrop) {
			backdrop.hidden = false;
		}

		document.documentElement.classList.add('bgcf-has-modal');

		window.requestAnimationFrame(function () {
			modal.classList.add('is-open');

			if (backdrop) {
				backdrop.classList.add('is-open');
			}
		});

		// Focus the close button rather than the CTA. The first focusable thing
		// in a dialog a visitor did not ask for should be the way out of it.
		const first = closers[0] || focusable()[0];

		if (first) {
			first.focus();
		}
	}

	closers.forEach(function (button) {
		button.addEventListener('click', close);
	});

	if (backdrop) {
		backdrop.addEventListener('click', close);
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

		const items = focusable();

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

	// Following the modal's own CTA counts as acting on it, so it should not
	// return on the next page.
	modal.addEventListener('click', function (event) {
		if (event.target.closest('a[href]')) {
			remember();
		}
	});

	const delay = Math.max(0, Number(modal.getAttribute('data-bgcf-promo-delay') || 4)) * 1000;

	window.setTimeout(open, delay);
}
