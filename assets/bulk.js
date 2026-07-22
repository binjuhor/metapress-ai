(function () {
	'use strict';
	var ids = MetaPressAIBulk.ids.slice();
	var total = ids.length;
	var done = 0;
	var status = document.getElementById('metapress-ai-bulk-status');
	var results = document.getElementById('metapress-ai-bulk-results');
	var bar = document.getElementById('metapress-ai-progress-bar');

	function addResult(id, success, text, url) {
		var item = document.createElement('li');
		if (url) {
			var link = document.createElement('a'); link.href = url; link.textContent = text; item.appendChild(link);
		} else item.textContent = 'Post #' + id + ': ' + text;
		item.className = success ? 'success' : 'error'; results.appendChild(item);
	}

	function next() {
		if (!ids.length) { status.textContent = 'Bulk generation finished: ' + done + ' of ' + total + ' processed.'; return; }
		var id = ids.shift();
		status.textContent = 'Generating metadata for item ' + (done + 1) + ' of ' + total + '…';
		fetch(MetaPressAIBulk.endpoint, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':MetaPressAIBulk.nonce}, body:JSON.stringify({post_id:id,job:MetaPressAIBulk.job})})
			.then(function(response){return response.text().then(function(body){var data;try{data=JSON.parse(body);}catch(error){throw new Error(/<!doctype|<html/i.test(body)?'WordPress returned HTML (HTTP '+response.status+'). Refresh and sign in again, or check for an upstream timeout.':'Invalid server response (HTTP '+response.status+').');}if(!response.ok)throw new Error(data.message||'Generation failed.');return data;});})
			.then(function(data){addResult(id,true,data.title,data.edit_url);})
			.catch(function(error){addResult(id,false,error.message);})
			.finally(function(){done++; bar.style.width = Math.round((done / total) * 100) + '%'; next();});
	}
	next();
}());
