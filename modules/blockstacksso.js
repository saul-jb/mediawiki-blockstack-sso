$('#mw-input-blockstacksso').click(function() {
	BlockstackCommon.login(
		mw.config.get('wgServer') + mw.config.get('wgScript') + '?title=' + mw.config.get('wgPageName'),
		mw.config.get( 'blockstackManifestUrl' ),
		'http://localhost:8888'
	).then( (url) => {
		window.location.replace( url );
	}).catch( (err) => {
		console.error( "Error: " + err );
	});

});
