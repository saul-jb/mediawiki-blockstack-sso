window.validate = function() {
	console.log('Validating Blockstack response...');
	BlockstackCommon.isSignedIn().then(function(userData) {
		var username = userData.username;
		var realname = userData.profile.name;

		console.log( BlockstackCommon.phpSignIn(userData) );

	}).catch(function(err) {
		console.error(err.data);
	});
};
