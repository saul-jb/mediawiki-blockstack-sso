/**
 * This is step 1 initiated by the user clicking the Blockstack login button
 * Here we redirect to the Blockstack browser to do the authentication using the BlockstackCommon.login() method.
 * - the return URL is the blockstack-validate action that will validate the response client-side and then POST
 *   the final data back to the wiki login page for final server-side validation and login continuation
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
