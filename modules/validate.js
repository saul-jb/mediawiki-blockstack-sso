window.validate = function() {
	console.log('Validating Blockstack response...');
	BlockstackCommon.isSignedIn().then(function(userData) {
		var username = userData.username;
		var realname = userData.profile.name;

		BlockstackCommon.phpSignIn(userData).then(function(data) {
			console.log(data);
		}).catch(function(err) {
			console.error(err.data);
		});

	}).catch(function(err) {
		console.error(err.data);
	});
};
