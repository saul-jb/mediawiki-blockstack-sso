# Blockstack SSO extension for MediaWiki

This extension uses the [Blockstack SSO single-signon for PHP apps](https://github.com/saul-avikar/Blockstack-SSO) to create an additional login button on the login form allowing users to sign in with their [Blockstack](https://blockstack.org/) profile, which is a decentralised identity system that uses the [Bitcoin](http://bitcoin.org) blockchain.

It's just like the familiar sign-in with Google/facebook/Twitter buttons we see everywhere, but with Blockstack you own your profile rather than relying on a company to look after it for you, and trusting that they'll respect your privacy and will always grant you access to your data.

After a user clicks on the "sign in with blockstack" button, they're directed to the Blockstack browser - either the local distributed app running on their own device, or otherwise the [web-based Blockstack browser](http://browser.blockstack.org/).

If the selected Blockstack ID is already in use on the wiki, the user will then be automatically logged in to the account it's been previously associated with. Otherwise the user will be prompted to specify a username and password so they can link the Blockstack ID to one of the wiki's existing user accounts.

If the selected account is already linked to a different Blockstack ID, then the user will be prompted to either unlink the account before continuing, or to select a different account to link to. Unlinking accounts is done from the _Special:UnlinkAccounts_ page in the wiki.

Note that currently the extension does not support account creation.

## Installation
Put the code into your wiki's _extensions_ directory and load it the usual way using _wfLoadExtension_ as usual:
```php
wfLoadExtension( 'BlockstackSso' );
```

Note that this extension depends on the [Blockstack SSO single-signon for PHP apps](https://github.com/saul-avikar/Blockstack-SSO), and expects it to be situated in the base directory of the extension and named _BlockstackCommon_ (case-sensitive). If you're running many different sites that all allow signing in with Blockstack, then it's best to keeo just one copy of the common dependency in your environment and symlink to them from each site that needs it.
