$('#mw-input-blockstacksso').click(function() {
	var retUrl = mw.config.get('wgServer') + mw.config.get('wgScript') + '?title=' + mw.config.get('wgPageName');
	var manUrl = mw.config.get( 'blockstackManifestUrl' );
	$.ajax({
		type: 'GET',
		url: 'http://localhost:1337',
		timeout: 100,
		success: function(text) {
			console.log('Local blockstack CORS proxy responded, using local service');
			BlockstackCommon.login( retUrl, manUrl, 'http://localhost:8888' ).then((url) => {
				window.location.replace(url);
			}).catch((err) => {
				console.error("Error: " + err);
			});
		},
		error: function(err) {
			console.log('Local blockstack CORS roxy not present, falling back on web service');
			BlockstackCommon.login( retUrl, manUrl ).then((url) => {
				window.location.replace(url);
			}).catch((err) => {
				console.error("Error: " + err);
			});
		}
	});
});
