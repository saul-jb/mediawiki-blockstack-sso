window.validate = function() {
	console.log('Validating Blockstack response...');
	BlockstackCommon.isSignedIn().then(function(userData) {
		BlockstackCommon.phpSignIn(userData).then(function(data) {
			console.log('Blockstack authentication successful');
			var username = userData.username;
			var realname = userData.profile.name;
			var wikiuser = userData.login ? userData.login.username : false;

			// TODO: POST this data to the login form for server-side validation

		}).catch(function(err) {
			console.error(err.data);
		});
	}).catch(function(err) {
		console.error(err.data);
	});
};
