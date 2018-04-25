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
 		BlockstackCommon.login( retUrl, manUrl ).then((url) => {
 			window.location.replace(url);
 		}).catch((err) => {
 			console.error("Error: " + err);
 		});
 	});
 });
