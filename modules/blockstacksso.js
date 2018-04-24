/**
 * Here we redirect to the Blockstack browser to do the authentication using the BlockstackCommon.login() method.
 * First we detect if the local Blockstack dapp is present, and if not we fall back onto the web-based service instead.
 *
 * This detection is rather difficult because the services are on HTTP, but the site we're logging in to may be on HTTPS
 * in which case a non-HTTPS XHR request will not be allowed.
 * 
 * To get around this, an image is loaded from the dapp service which doesn't suffer from the "mixed content" restriction.
 * We can then test after a small delay (100ms) whether we have a non-zero height for the image.
 */
$(document).ready(function() {
	$('#mw-input-blockstacksso').click(function() {

		// Request the test image from the local dapp service
		var img = new Image()
		img.src = 'http://localhost:8888/images/icon-nav-profile.svg';

		// Wait 100ms to give the image time to load and then start the redirect procedure
		setTimeout(function() {
			var retUrl = mw.config.get('wgServer') + mw.config.get('wgScript') + '?title=' + mw.config.get('wgPageName');
			var manUrl = mw.config.get( 'blockstackManifestUrl' );

			// Test-image height is non-zero, Blockstack dapp is serving on local port 8888
			if(img.height > 0) {
				console.log('Local blockstack CORS proxy responded, using local service');
				BlockstackCommon.login( retUrl, manUrl, 'http://localhost:8888' ).then((url) => {
					window.location.replace(url);
				}).catch((err) => {
					console.error("Error: " + err);
				});
			}

			// Test-image height is zero, fallback to web-based Blockstack service
			else {
				console.log('Local blockstack CORS proxy not present, falling back on web service');
				BlockstackCommon.login( retUrl, manUrl ).then((url) => {
					window.location.replace(url);
				}).catch((err) => {
					console.error("Error: " + err);
				});
			}
		}, 100);
	});
});
