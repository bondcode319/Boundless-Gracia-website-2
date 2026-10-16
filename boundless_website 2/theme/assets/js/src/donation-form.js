/**
 * Donation form behaviour.
 *
 * SOURCE FILE. Bundled into assets/js/main.js by `npm run build:js`.
 *
 * Contract with inc/payments-routes.php (treat as public API):
 *   POST admin-ajax.php action=bgcf_donation_start
 *     -> { success: true,  data: { redirect, reference, token, method, mode } }
 *     -> { success: false, data: { code, message, field } }
 *   POST admin-ajax.php action=bgcf_donation_status
 *     -> { success: true,  data: { status, label, amount, url } }
 *
 * This file NEVER decides that a payment succeeded. It starts a charge, hands
 * the donor to the provider, and reports what the server says. The amount it
 * sends is a suggestion the server re-validates; the redirect it follows is a
 * URL the server obtained from Flutterwave.
 *
 * Two things here exist specifically to stop donors being charged twice or
 * turned away for no reason:
 *   - Every submit carries an IDEMPOTENCY KEY, generated once per form instance
 *     and reused on retries. The server returns the original donation for a
 *     repeated key instead of creating a second charge, which closes the
 *     "two tabs, two mobile-money prompts" hole that a client-side busy flag
 *     cannot.
 *   - All requests go through `request.js`, which transparently recovers from a
 *     nonce expired by full-page caching. Before this, a cached donate page
 *     silently stopped accepting donations after 12 hours.
 *
 * Binds to `data-bgcf-*` attributes only, never to classes.
 *
 * @package Bgcf
 * @since   1.1.0
 */

import { bodyFrom, data as bridge, post } from './request.js';

/**
 * A random, collision-resistant idempotency key.
 *
 * `crypto.randomUUID` where available; a timestamp plus randomness otherwise.
 * It only has to be unique per donor attempt, not globally unguessable — the
 * server scopes it to the session and a short time window.
 *
 * @returns {string} The key.
 */
function idempotencyKey() {
	if (window.crypto && typeof window.crypto.randomUUID === 'function') {
		return window.crypto.randomUUID();
	}

	return 'bgcf-' + String(Date.now()) + '-' + Math.random().toString(36).slice(2, 12);
}

/**
 * Wire up every donation form on the page.
 *
 * @returns {void}
 */
export function initDonationForm() {
	const data = bridge();
	const forms = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-donation-form]'));

	forms.forEach(function (form) {
		const amountInput = form.querySelector('[data-bgcf-amount-input]');
		const presets = Array.prototype.slice.call(form.querySelectorAll('[data-bgcf-amount-preset]'));
		const methods = Array.prototype.slice.call(form.querySelectorAll('[data-bgcf-method]'));
		const mobileFields = form.querySelector('[data-bgcf-mobile-fields]');
		const phone = form.querySelector('[data-bgcf-phone]');
		const status = form.querySelector('[data-bgcf-donate-status]');
		const submit = form.querySelector('[data-bgcf-donate-submit]');
		const keyField = form.querySelector('[data-bgcf-idempotency]');
		const feeToggle = form.querySelector('[data-bgcf-cover-fee]');
		const feeAmount = form.querySelector('[data-bgcf-fee-amount]');
		const totalOut = form.querySelector('[data-bgcf-total]');
		const impacts = Array.prototype.slice.call(form.querySelectorAll('[data-bgcf-impact]'));

		// One key per form instance, reused across retries of the same attempt.
		if (keyField && !keyField.value) {
			keyField.value = idempotencyKey();
		}

		/**
		 * Write a message into the polite live region.
		 *
		 * @param {string} message Text to announce.
		 * @param {string} tone    'info' | 'error'.
		 * @returns {void}
		 */
		function announce(message, tone) {
			if (!status) {
				return;
			}
			status.textContent = message;
			status.setAttribute('data-tone', tone || 'info');
		}

		/**
		 * The amount currently entered, as a number.
		 *
		 * @returns {number} Amount, or 0.
		 */
		function currentAmount() {
			const value = Number(amountInput ? amountInput.value : 0);
			return isFinite(value) && value > 0 ? value : 0;
		}

		/**
		 * Recalculate the processing fee and the donor's total.
		 *
		 * The percentage and cap come from the server as data attributes, so the
		 * figures shown here always match what the server will charge. This is
		 * display only — the server recomputes the total before charging.
		 *
		 * @returns {void}
		 */
		function syncTotal() {
			if (!feeToggle || !totalOut) {
				return;
			}

			const amount = currentAmount();
			const percent = Number(form.getAttribute('data-bgcf-fee-percent') || 0);
			const flat = Number(form.getAttribute('data-bgcf-fee-flat') || 0);
			const cap = Number(form.getAttribute('data-bgcf-fee-cap') || 0);

			let fee = 0;

			if (feeToggle.checked && amount > 0) {
				fee = Math.round((amount * percent) / 100 + flat);

				if (cap > 0) {
					fee = Math.min(fee, cap);
				}
			}

			const format = function (value) {
				try {
					return value.toLocaleString(document.documentElement.lang || undefined);
				} catch (formatError) {
					// Intl is unavailable or the tag is invalid; a bare number is
					// still correct, just unformatted.
					void formatError;
					return String(value);
				}
			};

			if (feeAmount) {
				feeAmount.textContent = format(fee);
			}

			totalOut.textContent = format(amount + fee);
		}

		/**
		 * Show the impact line that matches the amount entered.
		 *
		 * "UGX 50,000 buys a term of school meals" converts far better than a
		 * bare number. Each line declares the minimum amount it applies to; the
		 * highest matching line wins.
		 *
		 * @returns {void}
		 */
		function syncImpact() {
			if (!impacts.length) {
				return;
			}

			const amount = currentAmount();
			let best = null;

			impacts.forEach(function (node) {
				const threshold = Number(node.getAttribute('data-bgcf-impact') || 0);

				if (amount >= threshold && (!best || threshold >= Number(best.getAttribute('data-bgcf-impact') || 0))) {
					best = node;
				}
			});

			impacts.forEach(function (node) {
				node.hidden = node !== best;
			});
		}

		/**
		 * Show or hide the mobile money fields for the chosen method, and keep
		 * `required` in sync so native validation matches what the server will
		 * actually insist on.
		 *
		 * @returns {void}
		 */
		function syncMethod() {
			const chosen = form.querySelector('[data-bgcf-method]:checked');
			const isMobile = chosen && chosen.value === 'mobile_money';

			if (mobileFields) {
				mobileFields.hidden = !isMobile;
			}

			if (phone) {
				if (isMobile) {
					phone.setAttribute('required', 'required');
				} else {
					phone.removeAttribute('required');
				}
			}
		}

		presets.forEach(function (preset) {
			preset.addEventListener('change', function () {
				if (amountInput && preset.checked) {
					amountInput.value = preset.value;
					syncTotal();
					syncImpact();
				}
			});
		});

		if (amountInput) {
			// Typing a custom amount must clear the preset, or the donor sees a
			// highlighted 50,000 next to the 75,000 they just typed.
			amountInput.addEventListener('input', function () {
				presets.forEach(function (preset) {
					preset.checked = preset.value === amountInput.value;
				});

				syncTotal();
				syncImpact();
			});
		}

		if (feeToggle) {
			feeToggle.addEventListener('change', syncTotal);
		}

		methods.forEach(function (method) {
			method.addEventListener('change', syncMethod);
		});

		syncMethod();
		syncTotal();
		syncImpact();

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (form.hasAttribute('data-bgcf-busy')) {
				return;
			}

			if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
				return;
			}

			form.setAttribute('data-bgcf-busy', '');

			if (submit) {
				submit.disabled = true;
			}

			announce((data.i18n && data.i18n.loading) || 'Loading…', 'info');

			post(bodyFrom(form))
				.then(function (result) {
					if (result && result.success && result.data && result.data.redirect) {
						announce(result.data.message || '', 'info');

						// Remember the handle so a "still processing" page can poll
						// for this donation after the round trip. Private mode or
						// blocked storage throws here; that is fine. Polling is a
						// nicety — the webhook and the reconciliation job are what
						// actually settle the donation.
						try {
							window.sessionStorage.setItem('bgcfDonationToken', result.data.token);
						} catch (storageError) {
							void storageError;
						}

						window.location.assign(result.data.redirect);
						return;
					}

					const error = (result && result.data) || {};

					announce(
						error.message || (data.i18n && data.i18n.genericError) || 'Something went wrong. Please try again.',
						'error'
					);

					if (error.field) {
						const field = form.querySelector('[name="' + error.field + '"]');

						if (field && typeof field.focus === 'function') {
							field.focus();
						}
					}

					// A fresh key for a genuinely new attempt: the previous one is
					// now associated with a failed charge server-side, and reusing
					// it would return that failure forever.
					if (keyField) {
						keyField.value = idempotencyKey();
					}

					form.removeAttribute('data-bgcf-busy');

					if (submit) {
						submit.disabled = false;
					}
				})
				.catch(function () {
					announce(
						(data.i18n && data.i18n.genericError) || 'Something went wrong. Please try again.',
						'error'
					);

					form.removeAttribute('data-bgcf-busy');

					if (submit) {
						submit.disabled = false;
					}
				});
		});
	});
}

/**
 * Poll for the outcome of a donation that came back still pending.
 *
 * Mobile money authorisation can land after the browser does, so the result
 * page asks the server every few seconds rather than telling the donor their
 * gift failed. Polling stops at a terminal status or after the ceiling below —
 * whatever happens, the webhook and the reconciliation job settle the record.
 *
 * @returns {void}
 */
export function initDonationResult() {
	const root = document.querySelector('[data-bgcf-donation-result]');

	if (!root) {
		return;
	}

	const token = root.getAttribute('data-bgcf-donation-result');
	const message = root.querySelector('[data-bgcf-result-message]');

	if (!token || root.getAttribute('data-bgcf-status') !== 'pending') {
		return;
	}

	const intervalMs = 5000;
	const maxAttempts = 24; // Two minutes; after that the page stops asking.
	let attempts = 0;

	const poll = function () {
		attempts += 1;

		const body = new FormData();
		body.set('action', 'bgcf_donation_status');
		body.set('token', token);

		post(body)
			.then(function (result) {
				if (!result || !result.success || !result.data) {
					if (attempts < maxAttempts) {
						window.setTimeout(poll, intervalMs);
					}

					return;
				}

				if (result.data.status !== 'pending') {
					// Reload onto the settled URL so the page renders the real
					// outcome from the server rather than patching it in JS.
					window.location.assign(result.data.url || window.location.href);
					return;
				}

				if (attempts < maxAttempts) {
					window.setTimeout(poll, intervalMs);
				} else if (message) {
					message.textContent = root.getAttribute('data-bgcf-timeout-text') || message.textContent;
				}
			})
			.catch(function () {
				if (attempts < maxAttempts) {
					window.setTimeout(poll, intervalMs);
				}
			});
	};

	window.setTimeout(poll, intervalMs);
}
