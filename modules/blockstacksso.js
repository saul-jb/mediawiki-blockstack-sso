$(document).ready(function() {
	$('#mw-input-blockstacksso').click(function() {

		/**
		 * This image preload is a test of local Blockstack dapp presence
		 * even though there is a local service on port 1337 that can be tested without CORS trouble,
		 * it can't get around the "mixed content" problem (requesting http://localhost:1337 from an HTTPS page over XHR).
		 * An image can be loaded from the dapp service though without these limitations, we can then test after a small
		 * delay (100ms) whether we have a non-zero height for the image.
		 */
		var img = new Image()
		img.src = 'http://localhost:8888/images/icon-nav-profile.svg';
		setTimeout(function() {
			var retUrl = mw.config.get('wgServer') + mw.config.get('wgScript') + '?title=' + mw.config.get('wgPageName');
			var manUrl = mw.config.get( 'blockstackManifestUrl' );

			// Image height is non-zero, Blockstack dapp is serving on local port 8888
			if(img.height > 0) {
				console.log('Local blockstack CORS proxy responded, using local service');
				BlockstackCommon.login( retUrl, manUrl, 'http://localhost:8888' ).then((url) => {
					window.location.replace(url);
				}).catch((err) => {
					console.error("Error: " + err);
				});
			}

			// Image height is zero, fallback to web-based Blockstack service
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
});
