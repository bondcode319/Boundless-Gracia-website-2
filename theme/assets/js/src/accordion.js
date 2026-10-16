/**
 * FAQ accordion.
 *
 * The server renders every panel open-able: each question is a `<button
 * aria-expanded>` inside a heading, and each answer is a sibling region that is
 * `hidden` only when the server marked it closed. Without JavaScript the panels
 * stay in whatever state the server chose and the text is still readable, which
 * is why nothing here is responsible for *revealing* content — only for toggling.
 *
 * Deliberately not `<details>`: the FAQ answers carry rich content and headings,
 * and `<details>` still has inconsistent heading semantics and print behaviour
 * across engines. A disclosure button plus a region is the APG pattern and it
 * prints correctly.
 *
 * @package Bgcf
 * @since   1.2.0
 */

/**
 * Wire every accordion on the page.
 *
 * `data-bgcf-accordion-single` on the root makes the group exclusive (opening
 * one closes the others). Default is independent, which is what an FAQ wants:
 * visitors compare answers.
 *
 * @returns {void}
 */
export function initAccordions() {
	const roots = Array.prototype.slice.call(document.querySelectorAll('[data-bgcf-accordion]'));

	if (!roots.length) {
		return;
	}

	roots.forEach(function (root) {
		const single = root.hasAttribute('data-bgcf-accordion-single');
		const toggles = Array.prototype.slice.call(root.querySelectorAll('[data-bgcf-accordion-toggle]'));

		toggles.forEach(function (toggle) {
			const panel = document.getElementById(toggle.getAttribute('aria-controls'));

			if (!panel) {
				return;
			}

			toggle.addEventListener('click', function () {
				const expanded = toggle.getAttribute('aria-expanded') === 'true';

				if (!expanded && single) {
					toggles.forEach(function (other) {
						if (other === toggle) {
							return;
						}

						const otherPanel = document.getElementById(other.getAttribute('aria-controls'));

						other.setAttribute('aria-expanded', 'false');

						if (otherPanel) {
							otherPanel.hidden = true;
						}
					});
				}

				toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				panel.hidden = expanded;
			});
		});
	});

	// Deep links: /faq#question-7 should arrive with that answer open, or the
	// visitor lands on a collapsed page and assumes the link is broken.
	const hash = window.location.hash.replace('#', '');

	if (!hash) {
		return;
	}

	const target = document.getElementById(hash);

	if (!target) {
		return;
	}

	const item = target.closest('[data-bgcf-accordion-item]') || target;
	const toggle = item.querySelector ? item.querySelector('[data-bgcf-accordion-toggle]') : null;

	if (toggle && toggle.getAttribute('aria-expanded') !== 'true') {
		toggle.click();
	}
}
