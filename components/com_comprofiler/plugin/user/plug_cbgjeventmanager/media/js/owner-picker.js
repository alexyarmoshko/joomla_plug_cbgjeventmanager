/**
 * CB GJ Event Manager - owner picker typeahead (vanilla JS, no jQuery dependency).
 *
 * Scoped member search injected into the native GroupJive event edit form. The visible field is a
 * search box; the hidden cbgjeventmanager_owner_id field carries the selected user id and is cleared
 * whenever the search text changes after a selection, so a stale id can never be submitted.
 *
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */
(function () {
	'use strict';

	function initPicker(root) {
		if (root.getAttribute('data-cbgjem-ready') === '1') {
			return;
		}
		root.setAttribute('data-cbgjem-ready', '1');

		var search = root.querySelector('.cbgjeventmanager-owner-search');
		var hidden = root.querySelector('.cbgjeventmanager-owner-id');
		var results = root.querySelector('.cbgjeventmanager-owner-results');
		var selected = root.querySelector('.cbgjeventmanager-owner-selected');

		if (!search || !hidden || !results) {
			return;
		}

		var baseUrl = root.getAttribute('data-url') || '';
		var tokenName = root.getAttribute('data-token-name') || '';
		var tokenValue = root.getAttribute('data-token-value') || '';
		var minChars = parseInt(root.getAttribute('data-min-chars') || '2', 10);
		var debounceTimer = null;
		var lastController = null;

		function clearSelection() {
			hidden.value = '';
			if (selected) {
				selected.textContent = '';
			}
		}

		function hideResults() {
			results.style.display = 'none';
			results.innerHTML = '';
		}

		function choose(id, name) {
			hidden.value = String(id);
			search.value = name;
			if (selected) {
				selected.textContent = '✓ ' + name;
			}
			hideResults();
		}

		function render(items) {
			results.innerHTML = '';

			if (!items || !items.length) {
				hideResults();
				return;
			}

			items.forEach(function (item) {
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = 'javascript:void(0);';
				a.className = 'dropdown-item';
				a.textContent = item.name;
				a.addEventListener('click', function () {
					choose(item.id, item.name);
				});
				li.appendChild(a);
				results.appendChild(li);
			});

			results.style.display = 'block';
		}

		function query(term) {
			if (lastController && typeof lastController.abort === 'function') {
				lastController.abort();
			}

			var url = baseUrl
				+ (baseUrl.indexOf('?') === -1 ? '?' : '&')
				+ encodeURIComponent(tokenName) + '=' + encodeURIComponent(tokenValue)
				+ '&q=' + encodeURIComponent(term);

			var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };

			if (typeof AbortController !== 'undefined') {
				lastController = new AbortController();
				opts.signal = lastController.signal;
			}

			fetch(url, opts)
				.then(function (resp) { return resp.ok ? resp.json() : null; })
				.then(function (data) {
					if (data && data.results) {
						render(data.results);
					} else {
						hideResults();
					}
				})
				.catch(function () { /* aborted or network error: ignore */ });
		}

		search.addEventListener('input', function () {
			// Any edit to the text invalidates a previous selection.
			clearSelection();

			var term = search.value.trim();

			if (debounceTimer) {
				window.clearTimeout(debounceTimer);
			}

			if (term.length < minChars) {
				hideResults();
				return;
			}

			debounceTimer = window.setTimeout(function () {
				query(term);
			}, 250);
		});

		search.addEventListener('blur', function () {
			// Delay so a result click registers before the list is hidden.
			window.setTimeout(hideResults, 200);
		});
	}

	// GroupJive 4 renders the plugin's row after the form's Update/Cancel buttons. Move it into
	// the field list, just above the row holding the submit button. If that row is not found the
	// picker stays where it is: still inside the form, so it still works.
	function placeRow(root) {
		var row = root.closest('.gjEventManagerOwnerRow');
		var form = row ? row.closest('form') : null;
		var submit = form ? form.querySelector('.gjButtonSubmit') : null;
		var buttons = submit ? submit.closest('.row') : null;

		if (!buttons || buttons === row || buttons.parentNode !== row.parentNode) {
			return;
		}

		buttons.parentNode.insertBefore(row, buttons);
	}

	function initAll() {
		var roots = document.querySelectorAll('.cbgjeventmanager-owner-picker');
		Array.prototype.forEach.call(roots, function (root) {
			placeRow(root);
			initPicker(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
