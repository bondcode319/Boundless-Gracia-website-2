/**
 * Mega-panel navigation behaviour.
 *
 * Binds to the `data-bgcf-mega-*` hooks emitted by `inc/mega-menu.php`. Without
 * this file the header is four buttons that do nothing and a Donate button that
 * works — which is why every destination inside a panel is also reachable from
 * the drawer and from the footer. That is the no-JS baseline: a usable site, not
 * a broken menu.
 *
 * THE OPEN/CLOSE DANCE
 * --------------------
 * A panel is `hidden` while closed, because that is the only thing that reliably
 * removes its links from the tab order and the accessibility tree. `hidden`
 * implies `display: none`, which cannot be transitioned — so opening is two
 * steps: drop `hidden`, then add `.is-open` on the next animation frame, once
 * the browser has had a layout pass to establish the start state. Closing is the
 * reverse, and `hidden` goes back only after the transition would have finished.
 *
 * The timeout fallback matters: under `prefers-reduced-motion` the duration
 * tokens collapse to zero and `transitionend` never fires, so a close that
 * waited only for the event would leave the panel visible forever.
 *
 * KEYBOARD, which is the part that is usually wrong:
 *   - The trigger is a `<button aria-expanded>`. Enter and Space toggle it; that
 *     is free, from using the right element.
 *   - Escape closes the open panel and returns focus to its trigger.
 *   - Tabbing out of a panel closes it — checked on `focusout`, not on `keydown`,
 *     so it is correct for Shift+Tab and for a click that moves focus too.
 *   - Arrow Left/Right move between triggers along the bar, which is the
 *     WAI-ARIA menubar convention and the only way to traverse four panels
 *     without tabbing through every cell of each one.
 *
 * HOVER is a convenience layered on top, with a close delay so a diagonal mouse
 * path from a trigger to the first cell does not dismiss the panel under the
 * cursor. It is never the contract: everything works without a pointer.
 *
 * @package Bgcf
 * @since   2.0.0
 */

/**
 * How long a panel stays open after the pointer leaves, in milliseconds.
 *
 * Long enough to cross the gap between the trigger and the panel on a slow
 * diagonal; short enough that it does not feel stuck.
 *
 * @type {number}
 */
const HOVER_CLOSE_DELAY = 180;

/**
 * How long to wait before putting the `hidden` attribute back, in milliseconds.
 *
 * Must be at least the CSS transition duration (`--bgcf-duration-fast`, 120ms).
 * Erring long is harmless — the panel is already invisible and non-interactive
 * by then; erring short makes it vanish mid-fade.
 *
 * @type {number}
 */
const HIDE_DELAY = 200;

/**
 * Wire the mega menu.
 *
 * @returns {void}
 */
export function initMegaMenu() {
	const bar = document.querySelector('[data-bgcf-mega]');

	if (!bar) {
		return;
	}

	const items = Array.prototype.slice.call(bar.querySelectorAll('[data-bgcf-mega-item]'));

	if (!items.length) {
		return;
	}

	/**
	 * Every trigger on the bar, including those with no panel, in document
	 * order. Arrow-key traversal walks this list, so a plain top-level link has
	 * to be in it or the arrows skip over it.
	 *
	 * @type {Element[]}
	 */
	const controls = Array.prototype.slice.call(
		bar.querySelectorAll('[data-bgcf-mega-trigger], .bgcf-mega__top-link')
	);

	/**
	 * Panel records: the trigger, its panel, and the item that owns both.
	 *
	 * Declared before the functions that read it so the dependency is visible in
	 * reading order. A top-level item with no panel contributes nothing here —
	 * it is in `controls` (for arrow traversal) but has nothing to open.
	 *
	 * @type {Object[]}
	 */
	const entries = items
		.map(function (item) {
			const trigger = item.querySelector('[data-bgcf-mega-trigger]');
			const panel = item.querySelector('[data-bgcf-mega-panel]');

			if (!trigger || !panel) {
				return null;
			}

			return { item: item, trigger: trigger, panel: panel };
		})
		.filter(Boolean);

	if (!entries.length) {
		return;
	}

	/**
	 * The panel that is currently open, or null.
	 *
	 * @type {?Object}
	 */
	let current = null;

	/**
	 * Pending hover-close timer.
	 *
	 * @type {?number}
	 */
	let closeTimer = null;

	/**
	 * Cancel a pending hover-close.
	 *
	 * @returns {void}
	 */
	function cancelClose() {
		if (closeTimer !== null) {
			window.clearTimeout(closeTimer);
			closeTimer = null;
		}
	}

	/**
	 * Close a panel.
	 *
	 * @param {Object}  entry                 Panel record.
	 * @param {boolean} [restoreFocus=false]  Move focus back to the trigger.
	 * @returns {void}
	 */
	function close(entry, restoreFocus) {
		if (!entry || entry.panel.hidden) {
			return;
		}

		entry.panel.classList.remove('is-open');
		entry.trigger.setAttribute('aria-expanded', 'false');

		if (current === entry) {
			current = null;
		}

		// Guard the deferred hide: by the time it runs the panel may have been
		// reopened, and hiding it then would be a ghost close.
		window.setTimeout(function () {
			if (!entry.panel.classList.contains('is-open')) {
				entry.panel.hidden = true;
			}
		}, HIDE_DELAY);

		if (restoreFocus) {
			entry.trigger.focus();
		}
	}

	/**
	 * Close every panel except one.
	 *
	 * @param {?Object} except Panel record to leave open.
	 * @returns {void}
	 */
	function closeAll(except) {
		entries.forEach(function (entry) {
			if (entry !== except) {
				close(entry);
			}
		});
	}

	/**
	 * Open a panel, closing any other.
	 *
	 * @param {Object} entry Panel record.
	 * @returns {void}
	 */
	function open(entry) {
		cancelClose();

		if (!entry.panel.hidden && entry.panel.classList.contains('is-open')) {
			return;
		}

		closeAll(entry);

		entry.panel.hidden = false;
		entry.trigger.setAttribute('aria-expanded', 'true');
		current = entry;

		// One frame, so the transition has a start state to animate from. See
		// the file header.
		window.requestAnimationFrame(function () {
			// Re-check: a fast pointer can have closed it again already.
			if (current === entry) {
				entry.panel.classList.add('is-open');
			}
		});
	}

	entries.forEach(function (entry) {
		entry.trigger.addEventListener('click', function () {
			if (entry.trigger.getAttribute('aria-expanded') === 'true') {
				close(entry);
			} else {
				open(entry);
			}
		});

		// Hover, on the ITEM rather than on the trigger, so moving the pointer
		// down into the panel does not count as leaving.
		entry.item.addEventListener('mouseenter', function () {
			// Pointer-driven opening only where there is a real pointer. On a
			// touch screen `mouseenter` fires on tap, which would open the panel
			// and then immediately have the click handler close it again.
			if (window.matchMedia && !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
				return;
			}

			open(entry);
		});

		entry.item.addEventListener('mouseleave', function () {
			cancelClose();
			closeTimer = window.setTimeout(function () {
				close(entry);
			}, HOVER_CLOSE_DELAY);
		});

		// Focus moving out of the item closes it. `relatedTarget` is where focus
		// went; null means it left the document entirely (a browser chrome
		// click), and closing then would be wrong — the visitor is coming back.
		entry.item.addEventListener('focusout', function (event) {
			if (event.relatedTarget && !entry.item.contains(event.relatedTarget)) {
				close(entry);
			}
		});
	});

	// -------------------------------------------------------------------------
	// Keyboard, bound once on the bar rather than per trigger.
	// -------------------------------------------------------------------------
	bar.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			if (current) {
				close(current, true);
				event.preventDefault();
			}

			return;
		}

		if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
			return;
		}

		// Arrow traversal applies to the bar itself. Inside an open panel the
		// arrows belong to the browser (and to any text the cell contains), so
		// a keypress that did not come from a top-level control is left alone.
		const index = controls.indexOf(event.target);

		if (index === -1) {
			return;
		}

		event.preventDefault();

		const step = event.key === 'ArrowRight' ? 1 : -1;
		// Wrap, which is what a menubar does: pressing Left on the first item
		// goes to the last rather than doing nothing.
		const next = controls[(index + step + controls.length) % controls.length];

		if (next) {
			next.focus();
		}
	});

	// A click anywhere outside the bar closes the open panel. Bound on the
	// document in the bubble phase, so a click on a panel cell still navigates.
	document.addEventListener('click', function (event) {
		if (current && !bar.contains(event.target)) {
			close(current);
		}
	});

	// Shrinking past the desktop breakpoint hides the bar; a panel left open
	// would be hidden with it and would come back open on the way up.
	if (typeof window.matchMedia === 'function') {
		const desktop = window.matchMedia('(min-width: 1024px)');

		const onChange = function (event) {
			if (!event.matches) {
				closeAll(null);
			}
		};

		if (typeof desktop.addEventListener === 'function') {
			desktop.addEventListener('change', onChange);
		} else if (typeof desktop.addListener === 'function') {
			desktop.addListener(onChange);
		}
	}
}
