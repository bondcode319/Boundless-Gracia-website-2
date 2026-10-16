/**
 * Click-to-load map embed.
 *
 * WHY NOT JUST EMBED IT. A Google Maps iframe pulls roughly a megabyte of
 * script and tiles and sets third-party cookies the moment the page loads —
 * before the visitor has consented to anything, and whether or not they ever
 * look at the map. On a contact page most visitors want the phone number.
 *
 * So the server renders a notice and a button, and the iframe is created here
 * only when the button is pressed. Nothing is requested from Google until then,
 * which also means the page needs no cookie banner on the map's account.
 *
 * The iframe gets a title (an untitled iframe is an unlabelled landmark) and
 * `loading="lazy"`.
 *
 * @package Bgcf
 * @since   1.2.0
 */

import { data } from './request.js';

/**
 * Wire every click-to-load map on the page.
 *
 * @returns {void}
 */
export function initMaps() {
	const maps = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-map]'));

	if (!maps.length) {
		return;
	}

	maps.forEach(function (root) {
		const button = root.querySelector('[data-bgcf-map-load]');
		const src = root.getAttribute('data-bgcf-map-src');

		if (!button || !src) {
			return;
		}

		button.addEventListener('click', function () {
			const frame = document.createElement('iframe');

			frame.src = src;
			frame.className = 'bgcf-map__frame';
			frame.loading = 'lazy';
			frame.setAttribute('title', String((data().i18n || {}).mapTitle || 'Map of our location'));
			frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
			frame.setAttribute('allowfullscreen', '');

			// Replace the whole placeholder: leaving the button behind invites a
			// second click that would stack a second iframe.
			root.textContent = '';
			root.appendChild(frame);
			root.setAttribute('data-bgcf-map-loaded', '');

			// Focus was on a button that no longer exists, so move it to the
			// iframe rather than letting it fall back to <body>.
			frame.setAttribute('tabindex', '-1');
			frame.focus();
		});
	});
}
