$('#mw-input-blockstacksso').click(function() {
	var img = new Image()
	img.src = 'http://localhost:8888/images/icon-nav-profile.svg';
	setTimeout(function() {
		console.log(img.height);

		var retUrl = mw.config.get('wgServer') + mw.config.get('wgScript') + '?title=' + mw.config.get('wgPageName');
		var manUrl = mw.config.get( 'blockstackManifestUrl' );

		if(img.height > 0) {
			console.log('Local blockstack CORS proxy responded, using local service');
			BlockstackCommon.login( retUrl, manUrl, 'http://localhost:8888' ).then((url) => {
				window.location.replace(url);
			}).catch((err) => {
				console.error("Error: " + err);
			});
		}

		else {
			console.log('Local blockstack CORS roxy not present, falling back on web service');
			BlockstackCommon.login( retUrl, manUrl ).then((url) => {
				window.location.replace(url);
			}).catch((err) => {
				console.error("Error: " + err);
			});
		}
	}, 100);

});
