/**
 * The AJAX transport, and the nonce recovery that makes it survive page caching.
 *
 * THE PROBLEM THIS SOLVES. Every nonce in this theme is printed into the HTML —
 * in `bgcfData.nonce` and in each form's hidden field. Under a full-page cache
 * (WP Rocket, Cloudflare, Varnish) that HTML is served to thousands of visitors
 * for hours, so the nonce they POST back is as old as the cache entry. Nonces
 * are valid for 24 hours and tick over every 12, which means a cached page
 * reliably starts failing nonce verification while looking perfectly fine. On a
 * donation form that is silent, total conversion loss.
 *
 * THE FIX. One retry, driven by the server's own answer: when a request comes
 * back with `bgcf_invalid_nonce`, fetch a fresh nonce from an uncacheable
 * endpoint, replace it everywhere on the page, and replay the request exactly
 * once. A second failure is a real failure and is surfaced to the user.
 *
 * Only ONE refresh is ever in flight, however many forms fail at once — the
 * promise is memoised, so ten simultaneous failures produce one network call.
 *
 * @package Bgcf
 * @since   1.2.0
 */

/**
 * Localized bridge from inc/enqueue.php. Read lazily: this module is imported
 * at parse time, before `bgcfData` is guaranteed to exist.
 *
 * @returns {Object} The data bridge, never null.
 */
export function data() {
	return window.bgcfData || {};
}

/**
 * In-flight nonce refresh, shared by every caller.
 *
 * @type {?Promise<string>}
 */
let refreshing = null;

/**
 * The nonce the next request should use.
 *
 * @returns {string} Current nonce, or '' when none was localized.
 */
export function nonce() {
	return String(data().nonce || '');
}

/**
 * Write a new nonce into the data bridge and into every form that carries one.
 *
 * Forms hold their own hidden field (they must work before this script runs),
 * so a refresh has to update both places or the next submit fails again.
 *
 * @param {string} value Fresh nonce.
 * @returns {void}
 */
function applyNonce(value) {
	const bridge = data();
	bridge.nonce = value;

	Array.prototype.slice
		.call(document.querySelectorAll('[data-bgcf-nonce], input[name="nonce"]'))
		.forEach(function (input) {
			input.value = value;
		});
}

/**
 * Fetch a fresh nonce.
 *
 * The endpoint is deliberately a REST route rather than admin-ajax: hosts and
 * CDNs are far more likely to be configured to bypass `/wp-json/` than to
 * bypass a query string.
 *
 * @returns {Promise<string>} The new nonce, or '' when it could not be renewed.
 */
export function refreshNonce() {
	if (refreshing) {
		return refreshing;
	}

	const url = String(data().nonceUrl || '');

	if (!url) {
		return Promise.resolve('');
	}

	refreshing = fetch(url, {
		method: 'GET',
		credentials: 'same-origin',
		cache: 'no-store',
		headers: { Accept: 'application/json' }
	})
		.then(function (response) {
			return response.ok ? response.json() : null;
		})
		.then(function (body) {
			const fresh = body && body.nonce ? String(body.nonce) : '';

			if (fresh) {
				applyNonce(fresh);
			}

			return fresh;
		})
		.catch(function () {
			return '';
		})
		.then(function (fresh) {
			// Allow a later failure to try again, but only after this one settles.
			refreshing = null;
			return fresh;
		});

	return refreshing;
}

/**
 * Was this response rejected because the nonce was stale?
 *
 * @param {Object} result Parsed `wp_send_json_*` envelope.
 * @returns {boolean} True when a nonce refresh is worth attempting.
 */
function isNonceFailure(result) {
	return Boolean(result && result.success === false && result.data && result.data.code === 'bgcf_invalid_nonce');
}

/**
 * POST to admin-ajax.php, recovering once from a stale nonce.
 *
 * @param {FormData|URLSearchParams} body   Request body. The nonce is injected.
 * @param {boolean}                  [retry=true] Internal: whether a retry is still allowed.
 * @returns {Promise<Object>} The parsed envelope: `{ success, data }`.
 */
export function post(body, retry) {
	const allowRetry = retry !== false;
	const url = String(data().ajaxUrl || '');

	if (!url) {
		return Promise.resolve({
			success: false,
			data: { code: 'no_endpoint', message: String((data().i18n || {}).genericError || '') }
		});
	}

	body.set('nonce', nonce());

	return fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		body: body
	})
		.then(function (response) {
			// A 403 still carries a JSON envelope; parse it rather than guessing.
			return response.json().catch(function () {
				return {
					success: false,
					data: { code: 'bad_response', message: String((data().i18n || {}).genericError || '') }
				};
			});
		})
		.then(function (result) {
			if (!isNonceFailure(result) || !allowRetry) {
				return result;
			}

			return refreshNonce().then(function (fresh) {
				return fresh ? post(body, false) : result;
			});
		});
}

/**
 * Build a FormData body from a form, preserving files.
 *
 * @param {HTMLFormElement} form Source form.
 * @returns {FormData} Request body.
 */
export function bodyFrom(form) {
	return new FormData(form);
}
