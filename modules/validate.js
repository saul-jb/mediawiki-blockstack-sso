window.validate = function() {
	console.log('Validating Blockstack response...');
	BlockstackCommon.isSignedIn().then(function(userData) {
		var username = userData.username;
		var realname = userData.profile.name;
		console.log(userData);
		/*
		var url = window.location.orgin + "/authenticate";

		BlockstackCommon.phpSignIn( userData, url ).then( ( res ) => {
			window.location.replace( "http:\/\/" + window.location.hostname + "/wp-admin/" );
		}).catch( ( err ) => {
			// failed for some reason
			console.error( err.data );
		});
		*/
	}).catch(function(err) {
		console.error(err.data);
	});
};
