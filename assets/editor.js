(function () {
	'use strict';

	var button = document.getElementById('metapress-ai-generate');
	if (!button) return;

	var fields = ['focus_keyphrase', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];
	var container = document.getElementById('metapress-ai-suggestions');
	var message = document.getElementById('metapress-ai-message');
	var spinner = document.getElementById('metapress-ai-spinner');
	var fieldsPanel = document.getElementById('metapress-ai-fields');
	var toggleFields = document.getElementById('metapress-ai-toggle-fields');

	function updateCounts() {
		document.querySelectorAll('.metapress-ai-count').forEach(function (counter) {
			var input = document.getElementById(counter.dataset.for);
			counter.textContent = input.value.length + ' characters';
		});
	}

	function escapeHtml(value) {
		var element = document.createElement('div');
		element.textContent = value || '';
		return element.innerHTML;
	}

	function render(suggestions) {
		container.innerHTML = suggestions.map(function (suggestion, index) {
			return '<section class="metapress-ai-card" data-index="' + index + '"><h4>Option ' + (index + 1) + '</h4>' +
				'<strong>' + escapeHtml(suggestion.seo_title) + '</strong><p>' + escapeHtml(suggestion.meta_description) + '</p>' +
				'<p><small>Keyphrase: ' + escapeHtml(suggestion.focus_keyphrase) + '</small></p>' +
				'<button type="button" class="button metapress-ai-apply">' + escapeHtml(MetaPressAI.labels.apply) + '</button></section>';
		}).join('');

		container.querySelectorAll('.metapress-ai-apply').forEach(function (applyButton, index) {
			applyButton.addEventListener('click', function () {
				applyButton.disabled = true;
				message.textContent = MetaPressAI.labels.applying;
				fields.forEach(function (field) {
					document.getElementById('metapress-ai-' + field).value = suggestions[index][field] || '';
				});
				updateCounts();
				fetch(MetaPressAI.applyEndpoint, {
					method: 'POST', credentials: 'same-origin',
					headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-WP-Nonce': MetaPressAI.nonce},
					body: JSON.stringify({post_id: MetaPressAI.postId, metadata: suggestions[index]})
				}).then(function (response) {
					return response.text().then(function (body) {
						var data; try { data = JSON.parse(body); } catch (error) { throw new Error('The server returned an invalid response (HTTP ' + response.status + ').'); }
						if (!response.ok) throw new Error(data.message || 'Applying metadata failed.'); return data;
					});
				}).then(function () {
					message.textContent = MetaPressAI.labels.applied + ' ';
					var refresh = document.createElement('button'); refresh.type = 'button'; refresh.className = 'button button-link'; refresh.textContent = MetaPressAI.labels.refresh;
					refresh.addEventListener('click', function () { window.location.reload(); }); message.appendChild(refresh);
				}).catch(function (error) { message.textContent = error.message; }).finally(function () { applyButton.disabled = false; });
			});
		});
	}

	if (toggleFields && fieldsPanel) {
		toggleFields.addEventListener('click', function () {
			var opening = fieldsPanel.hidden;
			fieldsPanel.hidden = !opening;
			toggleFields.setAttribute('aria-expanded', opening ? 'true' : 'false');
			toggleFields.textContent = opening ? MetaPressAI.labels.hideFields : MetaPressAI.labels.showFields;
		});
	}

	button.addEventListener('click', function () {
		if (!MetaPressAI.postId) {
			message.textContent = 'Save the draft once before generating metadata.';
			return;
		}
		button.disabled = true;
		spinner.classList.add('is-active');
		message.textContent = MetaPressAI.labels.generating;
		fetch(MetaPressAI.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-WP-Nonce': MetaPressAI.nonce},
			body: JSON.stringify({post_id: MetaPressAI.postId, focus_keyphrase: document.getElementById('metapress-ai-focus_keyphrase').value})
		}).then(function (response) {
			return response.text().then(function (body) {
				var data;
				try {
					data = JSON.parse(body);
				} catch (error) {
					if (response.status === 401 || response.status === 403 || /<!doctype|<html/i.test(body)) {
						throw new Error('WordPress returned an HTML page (HTTP ' + response.status + '). Refresh this editor and sign in again. If it happens after waiting, the web server timed out while contacting the AI provider.');
					}
					throw new Error('The server returned an invalid response (HTTP ' + response.status + '). Check the WordPress and PHP error logs.');
				}
				if (!response.ok) throw new Error(data.message || 'Generation failed (HTTP ' + response.status + ').');
				return data;
			});
		}).then(function (data) {
			render(data.suggestions);
			message.textContent = '';
		}).catch(function (error) {
			message.textContent = error.message;
		}).finally(function () {
			button.disabled = false;
			spinner.classList.remove('is-active');
		});
	});

	document.querySelectorAll('.metapress-ai-fields textarea').forEach(function (input) { input.addEventListener('input', updateCounts); });
	updateCounts();
}());
