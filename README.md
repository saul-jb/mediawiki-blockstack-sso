# Blockstack SSO extension for MediaWiki

This extension uses the [Blockstack SSO single-signon for PHP apps](https://github.com/saul-avikar/Blockstack-SSO) to create an additional button on the login form allowing users to sign in with their [Blockstack](https://blockstack.org/) profile, which is a decentralised identity system that uses the [Bitcoin](http://bitcoin.org) blockchain.

It's just like the familiar sign-in with Google/facebook/Twitter buttons we see everywhere, but with Blockstack you own your profile rather than relying on a company to look after it for you, and trusting that they'll respect your privacy and will always grant you access to your data.

After a user clicks on the "sign in with blockstack" button, they're directed to the Blockstack browser - either the local distributed app running on their own device, or if that's not installed, the [web-based Blockstack browser](http://browser.blockstack.org/). The Blockstack app presents the use with a sign-in form advising them of the access the being requested and allowing the to select which of the Blockstack IDs they'd like to sign in with.

If the selected Blockstack ID is already in use on the wiki, the user will then be automatically logged in to the account it's been previously associated with. Otherwise the user will be prompted to specify a username and password so they can link the Blockstack ID to one of the wiki's existing user accounts.

If the selected account is already linked to a different Blockstack ID, then the user will be prompted to either unlink the account before continuing, or to select a different account to link to. Unlinking accounts is done from the _Special:UnlinkAccounts_ page in the wiki.

Note that currently the extension does not support account creation.

## Installation
Put the code into your wiki's _extensions_ directory and load it the usual way using _wfLoadExtension_ as usual:
```php
wfLoadExtension( 'BlockstackSso' );
```

Note that this extension depends on the [Blockstack SSO single-signon for PHP apps](https://github.com/saul-avikar/Blockstack-SSO), and expects it to be situated in the base directory of the extension and named _BlockstackCommon_ (case-sensitive). If you're running many different sites that all allow signing in with Blockstack, then it's best to keeo just one copy of the common dependency in your environment and symlink to them from each site that needs it.

## Technical details
Blockstack's authentication mechanism is very different than the usual OAuth type of pattern used by Google and Facebook etc because there is no central server to authenticate with. All authentication is done between the client and their locallay installed Blockstack service which is peer in a decentralised work. If you don't have the service installed, the authentication will be done by their centralised web version of the service, but it still needs to be done client-side in JavaScript.

When a user clicks the "Login with Blockstack" button, the browser is directed to the local Blockstack service (or the web version if the local one does not respond). This redirection is done by in JavaScript (by [blockstacksso.js](https://github.com/saul-avikar/mediawiki-blockstack-sso/blob/master/modules/blockstacksso.js)) rather than by the server in response to the form being posted, because the redirection URL request is created by key-exchange with the service first.

After the user has been redirected to the Blockstack service and selected the ID they wish to login with, they're redirected back to a page which executes the [validate.js](https://github.com/saul-avikar/mediawiki-blockstack-sso/blob/master/modules/validate.js) script. This script authenticates the Blockstack response using the methods in [blockstack-common.js](https://github.com/saul-avikar/blockstack-sso/blob/master/blockstack-common.js) of the [Blockstack SSO single-signon for PHP apps](https://github.com/saul-avikar/Blockstack-SSO). After it's authenticated the response, it then checks with the wiki using the ''blockstack-checkuser'' action if that Blockstack ID is in use already or not. If it's not, then it will include a shared secret unique to the domain and ID in the posted data that will be used to validate subsequent login requests. If the ID is already in use, then it uses the shared secret to contruct a hash from the shared secret and the login token that the server can use to valdiate the request with. The data is then posted to the server in the form of a normal login request, but including the extra Blockstack information (the Blockstack ID and username, and the secret or validation hash).

After the server has received the login request, it checks if the Blockstack ID is already linked to an account, and if so it validates the request and signs the user into the linked account. If it's not linked yet, it creates an entry for the new Blockstack ID and stores the shared secret along with it, and then presents a continuation form for the user to specify the credentials for the account they'd like to link to. After they've specifed valid details (a user that's not already linked to a Blockstack ID and the correct password for the account), the wiki user ID is added to the Blockstack ID entry, and the user is signed in.

The linked account entries are stored in a database table called ''blockstacksso'' which stores the Blockstack decenralised ID, the Blockstack username, the shared secret and the wiki user ID for each entry.

## See also

* [The MediaWiki page for the extension](https://www.mediawiki.org/wiki/Extension:BlockstackSSO)
* [Blockstack SSO for Wordpress](https://github.com/saul-avikar/wordpress-blockstack-sso)
