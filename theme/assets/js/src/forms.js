/**
 * The generic AJAX form handler: newsletter, volunteer and contact.
 *
 * ONE handler for all three, because they differ only in their action name and
 * their success copy. Every form carries `data-bgcf-ajax-form="<kind>"`, a real
 * `action` attribute, a nonce field and a `[data-bgcf-form-status]` region, and
 * the handler does the same five things in the same order for each:
 *
 *   1. Let the browser validate first (`reportValidity`), so native messages
 *      and native focus handling do the work they already do well.
 *   2. Disable the submit and announce a busy state.
 *   3. POST through `request.js`, which recovers from a stale nonce.
 *   4. On a field error, focus that field and describe the problem on it.
 *   5. On success, replace the form with the success message — the form is gone,
 *      so it cannot be submitted twice.
 *
 * The status region is `role="status" aria-live="polite"` in the markup, so
 * every outcome is announced without stealing focus.
 *
 * @package Bgcf
 * @since   1.2.0
 */

import { bodyFrom, data, post } from './request.js';

/**
 * Write a message into a form's status region.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {string}          message Message text.
 * @param {string}          tone    'busy', 'error' or 'success'.
 * @returns {void}
 */
function announce(form, message, tone) {
	const status = form.querySelector('[data-bgcf-form-status]');

	if (!status) {
		return;
	}

	status.textContent = message;
	status.setAttribute('data-bgcf-tone', tone);
}

/**
 * Attach an error message to a specific field.
 *
 * `aria-describedby` is appended rather than replaced so a field that already
 * has help text keeps it — otherwise fixing one error silently deletes the
 * instructions that would prevent the next one.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {string}          name    Field name from the server response.
 * @param {string}          message Error text.
 * @returns {boolean} True when a field was found and marked.
 */
function markField(form, name, message) {
	if (!name) {
		return false;
	}

	const field = form.querySelector('[name="' + name + '"]');

	if (!field) {
		return false;
	}

	const id = (field.id || name) + '-error';
	let error = document.getElementById(id);

	if (!error) {
		error = document.createElement('p');
		error.id = id;
		error.className = 'bgcf-form__error';
		field.insertAdjacentElement('afterend', error);
	}

	error.textContent = message;
	field.setAttribute('aria-invalid', 'true');

	const described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);

	if (described.indexOf(id) === -1) {
		described.push(id);
		field.setAttribute('aria-describedby', described.join(' '));
	}

	if (typeof field.focus === 'function') {
		field.focus();
	}

	return true;
}

/**
 * Clear every error marker on a form before a new attempt.
 *
 * @param {HTMLFormElement} form The form.
 * @returns {void}
 */
function clearErrors(form) {
	Array.prototype.slice.call(form.querySelectorAll('.bgcf-form__error')).forEach(function (node) {
		node.remove();
	});

	Array.prototype.slice.call(form.querySelectorAll('[aria-invalid="true"]')).forEach(function (field) {
		field.removeAttribute('aria-invalid');
	});
}

/**
 * Replace a submitted form with its success message.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {string}          message Success copy from the server.
 * @returns {void}
 */
function showSuccess(form, message) {
	const panel = document.createElement('div');
	panel.className = 'bgcf-form__success';
	// `role="status"` on the replacement, and focus moved to it, because the
	// element the visitor was interacting with has just been removed; without
	// this, focus falls to <body> and a screen reader announces nothing.
	panel.setAttribute('role', 'status');
	panel.setAttribute('tabindex', '-1');
	panel.textContent = message;

	form.replaceWith(panel);
	panel.focus();
}

/**
 * Wire every AJAX form on the page.
 *
 * @returns {void}
 */
export function initAjaxForms() {
	const forms = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-ajax-form]'));

	if (!forms.length) {
		return;
	}

	const i18n = data().i18n || {};

	forms.forEach(function (form) {
		form.addEventListener('submit', function (event) {
			// Let the browser's own validation run and report first.
			if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
				return;
			}

			event.preventDefault();

			if (form.hasAttribute('data-bgcf-busy')) {
				return;
			}

			form.setAttribute('data-bgcf-busy', '');
			clearErrors(form);

			const submit = form.querySelector('[type="submit"]');

			if (submit) {
				submit.disabled = true;
			}

			announce(form, String(i18n.loading || 'Sending…'), 'busy');

			post(bodyFrom(form))
				.then(function (result) {
					if (result && result.success) {
						showSuccess(form, String((result.data && result.data.message) || i18n.formSuccess || ''));
						return;
					}

					const payload = (result && result.data) || {};
					const message = String(payload.message || i18n.genericError || '');

					// Mark the offending field when the server named one; the
					// status region carries the message either way, so the
					// announcement does not depend on the field being found.
					markField(form, String(payload.field || ''), message);
					announce(form, message, 'error');
				})
				.catch(function () {
					announce(form, String(i18n.genericError || ''), 'error');
				})
				.then(function () {
					form.removeAttribute('data-bgcf-busy');

					if (submit) {
						submit.disabled = false;
					}
				});
		});
	});
}
