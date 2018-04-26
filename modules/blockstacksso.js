/**
 * Here we redirect to the Blockstack browser to do the authentication using the BlockstackCommon.login() method.
 * First we detect if the local Blockstack dapp is present, and if not we fall back onto the web-based service instead.
 */
 $(document).ready(function() {
 	$('#mw-input-blockstacksso').click(function() {
		var retUrl = mw.config.get('wgServer')
			+ mw.config.get('wgScript')
			+ '&action=blockstack-validate'
			+ '&token=' + encodeURIComponent( document.userlogin.wpLoginToken.value );
		var manUrl = mw.config.get( 'blockstackManifestUrl' );
 		BlockstackCommon.login(retUrl, manUrl).then(function(url) {
 			window.location.replace(url);
 		}).catch(function(err) {
 			console.error("Error: " + err);
 		});
 	});
 });
