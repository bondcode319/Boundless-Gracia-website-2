/**
 * Gallery: album filters, "load more" and the lightbox.
 *
 * The server renders a complete, linked grid. Every enhancement here is
 * additive:
 *   - Filters are links to the filtered archive URL that JS upgrades into
 *     in-place filtering, so they work (and are crawlable) without it.
 *   - "Load more" is a link to page 2 that JS turns into an append.
 *   - Each item links to the full-size file; the lightbox intercepts the click.
 *     With JS off, the link opens the image. Nothing is lost.
 *
 * LIGHTBOX ACCESSIBILITY — the part implementations usually skip:
 *   - `role="dialog" aria-modal="true"` with an accessible name from the caption.
 *   - Focus moves in on open and returns to the triggering link on close.
 *   - Tab is trapped; ESC closes; arrow keys move between items.
 *   - Previous/next/close are real buttons with text labels.
 *   - The backdrop is click-to-close but is not the only way out.
 *
 * @package Bgcf
 * @since   1.2.0
 */

import { data, post } from './request.js';

/**
 * Build the lightbox shell once and reuse it for every gallery.
 *
 * Created lazily: a page with a gallery nobody opens should not pay for the
 * dialog markup.
 *
 * @returns {Object} The lightbox controller.
 */
function createLightbox() {
	const i18n = data().i18n || {};

	const overlay = document.createElement('div');
	overlay.className = 'bgcf-lightbox';
	overlay.setAttribute('role', 'dialog');
	overlay.setAttribute('aria-modal', 'true');
	overlay.hidden = true;

	overlay.innerHTML =
		'<div class="bgcf-lightbox__backdrop" data-close></div>' +
		'<div class="bgcf-lightbox__panel">' +
		'<figure class="bgcf-lightbox__figure">' +
		'<img class="bgcf-lightbox__image" alt="" />' +
		'<figcaption class="bgcf-lightbox__caption" data-caption></figcaption>' +
		'</figure>' +
		'<button type="button" class="bgcf-lightbox__close" data-close>' +
		'<span class="bgcf-u-visually-hidden">' +
		String(i18n.close || 'Close') +
		'</span><span aria-hidden="true">&times;</span></button>' +
		'<button type="button" class="bgcf-lightbox__nav bgcf-lightbox__nav--prev" data-prev>' +
		'<span class="bgcf-u-visually-hidden">' +
		String(i18n.prevSlide || 'Previous') +
		'</span><span aria-hidden="true">&#8249;</span></button>' +
		'<button type="button" class="bgcf-lightbox__nav bgcf-lightbox__nav--next" data-next>' +
		'<span class="bgcf-u-visually-hidden">' +
		String(i18n.nextSlide || 'Next') +
		'</span><span aria-hidden="true">&#8250;</span></button>' +
		'<p class="bgcf-lightbox__count" data-count aria-live="polite"></p>' +
		'</div>';

	document.body.appendChild(overlay);

	const image = overlay.querySelector('.bgcf-lightbox__image');
	const caption = overlay.querySelector('[data-caption]');
	const count = overlay.querySelector('[data-count]');
	const closeButton = overlay.querySelector('[data-close].bgcf-lightbox__close');

	let items = [];
	let index = 0;
	let opener = null;

	/**
	 * Render the item at the current index.
	 *
	 * @returns {void}
	 */
	function render() {
		const item = items[index];

		if (!item) {
			return;
		}

		image.src = item.src;
		image.alt = item.alt;
		caption.textContent = item.caption;
		caption.hidden = item.caption === '';
		overlay.setAttribute('aria-label', item.caption || item.alt || String(i18n.gallery || 'Gallery'));

		count.textContent = String(index + 1) + ' / ' + String(items.length);

		// A single-item gallery has nothing to page through.
		overlay.querySelector('[data-prev]').hidden = items.length < 2;
		overlay.querySelector('[data-next]').hidden = items.length < 2;
	}

	/**
	 * Move through the set, wrapping at both ends.
	 *
	 * @param {number} delta Offset.
	 * @returns {void}
	 */
	function move(delta) {
		if (!items.length) {
			return;
		}

		index = (index + delta + items.length) % items.length;
		render();
	}

	/**
	 * Close the lightbox and restore focus.
	 *
	 * @returns {void}
	 */
	function close() {
		overlay.hidden = true;
		document.documentElement.classList.remove('bgcf-has-lightbox');

		// Drop the source so a large image is not held in memory while closed.
		image.removeAttribute('src');

		if (opener && typeof opener.focus === 'function') {
			opener.focus();
		}

		opener = null;
	}

	/**
	 * Open the lightbox on a given item.
	 *
	 * @param {Object[]} set     Items in the gallery.
	 * @param {number}   start   Index to show first.
	 * @param {Element}  trigger Element that opened it, for focus restoration.
	 * @returns {void}
	 */
	function open(set, start, trigger) {
		items = set;
		index = start;
		opener = trigger;

		overlay.hidden = false;
		document.documentElement.classList.add('bgcf-has-lightbox');
		render();
		closeButton.focus();
	}

	overlay.addEventListener('click', function (event) {
		if (event.target.closest('[data-close]')) {
			close();
		} else if (event.target.closest('[data-prev]')) {
			move(-1);
		} else if (event.target.closest('[data-next]')) {
			move(1);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (overlay.hidden) {
			return;
		}

		if (event.key === 'Escape') {
			close();
		} else if (event.key === 'ArrowLeft') {
			move(-1);
		} else if (event.key === 'ArrowRight') {
			move(1);
		} else if (event.key === 'Tab') {
			// Trap: the panel's buttons are the only focusable things inside.
			const focusables = Array.prototype.slice
				.call(overlay.querySelectorAll('button:not([hidden])'))
				.filter(function (button) {
					return button.offsetParent !== null;
				});

			if (!focusables.length) {
				return;
			}

			const first = focusables[0];
			const last = focusables[focusables.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});

	return { open: open };
}

/**
 * Read the lightbox item list out of a gallery's DOM.
 *
 * Derived from the markup rather than a JSON blob so an appended page of results
 * is picked up with no extra bookkeeping.
 *
 * @param {Element} grid Gallery grid.
 * @returns {Object[]} Items.
 */
function itemsIn(grid) {
	return Array.prototype.slice.call(grid.querySelectorAll('[data-bgcf-lightbox-item]')).map(function (link) {
		const img = link.querySelector('img');

		return {
			src: link.getAttribute('href'),
			alt: img ? img.getAttribute('alt') || '' : '',
			caption: link.getAttribute('data-bgcf-caption') || ''
		};
	});
}

/**
 * Wire galleries: lightbox, filters and load-more.
 *
 * @returns {void}
 */
export function initGalleries() {
	const galleries = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-gallery]'));

	if (!galleries.length) {
		return;
	}

	let lightbox = null;
	const i18n = data().i18n || {};

	galleries.forEach(function (gallery) {
		const grid = gallery.querySelector('[data-bgcf-gallery-grid]');

		if (!grid) {
			return;
		}

		/* --- Lightbox ---------------------------------------------------- */
		if (gallery.hasAttribute('data-bgcf-lightbox')) {
			grid.addEventListener('click', function (event) {
				const link = event.target.closest('[data-bgcf-lightbox-item]');

				if (!link) {
					return;
				}

				event.preventDefault();

				if (!lightbox) {
					lightbox = createLightbox();
				}

				const set = itemsIn(grid);
				const start = Array.prototype.slice
					.call(grid.querySelectorAll('[data-bgcf-lightbox-item]'))
					.indexOf(link);

				lightbox.open(set, Math.max(start, 0), link);
			});
		}

		/* --- Filters ----------------------------------------------------- */
		const filters = Array.prototype.slice.call(gallery.querySelectorAll('[data-bgcf-gallery-filter]'));

		filters.forEach(function (filter) {
			filter.addEventListener('click', function (event) {
				event.preventDefault();

				const album = filter.getAttribute('data-bgcf-gallery-filter');

				filters.forEach(function (other) {
					const active = other === filter;
					other.setAttribute('aria-pressed', active ? 'true' : 'false');
				});

				// Filtering is client-side over the items already present, then
				// the load-more cursor is reset so paging continues within the
				// chosen album.
				Array.prototype.slice.call(grid.children).forEach(function (item) {
					const itemAlbum = item.getAttribute('data-bgcf-album') || '';
					item.hidden = album !== '' && itemAlbum !== album;
				});

				gallery.setAttribute('data-bgcf-album', album);
				gallery.setAttribute('data-bgcf-paged', '1');

				const more = gallery.querySelector('[data-bgcf-gallery-more]');

				if (more) {
					more.hidden = false;
				}
			});
		});

		/* --- Load more --------------------------------------------------- */
		const more = gallery.querySelector('[data-bgcf-gallery-more]');

		if (!more) {
			return;
		}

		more.addEventListener('click', function (event) {
			event.preventDefault();

			if (more.disabled) {
				return;
			}

			more.disabled = true;
			const label = more.querySelector('.bgcf-btn__label');
			const resting = label ? label.textContent : '';

			if (label) {
				label.textContent = String(i18n.loading || 'Loading…');
			}

			const body = new FormData();
			body.set('action', 'bgcf_gallery_load_more');
			body.set('paged', String(Number(gallery.getAttribute('data-bgcf-paged') || 1) + 1));
			body.set('per_page', gallery.getAttribute('data-bgcf-per-page') || '12');
			body.set('album', gallery.getAttribute('data-bgcf-album') || '');
			body.set('post_id', gallery.getAttribute('data-bgcf-post') || '0');
			body.set('block_id', gallery.getAttribute('data-bgcf-block') || '');

			post(body)
				.then(function (result) {
					if (!result || !result.success) {
						const message = String((result && result.data && result.data.message) || i18n.genericError || '');
						more.insertAdjacentHTML('beforebegin', '<p class="bgcf-form__error" role="status">' + message + '</p>');
						return;
					}

					const payload = result.data || {};

					grid.insertAdjacentHTML('beforeend', String(payload.html || ''));
					gallery.setAttribute('data-bgcf-paged', String(payload.paged || 1));

					if (!payload.hasMore) {
						more.hidden = true;
					}

					// Move focus to the first new item so a keyboard user is not
					// returned to the top of a grid that just grew.
					const appended = grid.querySelector('[data-bgcf-new] [data-bgcf-lightbox-item], [data-bgcf-new] a');

					if (appended) {
						appended.focus({ preventScroll: true });
					}

					Array.prototype.slice.call(grid.querySelectorAll('[data-bgcf-new]')).forEach(function (node) {
						node.removeAttribute('data-bgcf-new');
					});
				})
				.then(function () {
					more.disabled = false;

					if (label) {
						label.textContent = resting;
					}
				});
		});
	});
}
