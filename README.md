# Blockstack SSO extension for MediaWiki
not functional yet...

This extension uses the [Blockstack SSO common PHP class](https://github.com/saul-avikar/Blockstack-SSO) to create a new authentication provider allowing users to sign in or create accounts with their [Blockstack](https://blockstack.org/) profile, which is a decentralised identity system that uses the [Bitcoin](http://bitcoin.org) blockchain.

A new "sign in with blockstack" button appears on the wikis login form allowing users to select one of their Blockstack profiles, either from their locally installed Blockstack application, or using the [web-based Blockstack browser](http://browser.blockstack.org/).

If the selected profile is already in use on the wiki the user will then be automatically logged in. But otherwise the user will be prompted to specify a username and password to connect the Blockstack ID to. If the wiki allows public creation of accounts, then the username can be non-existent and the account will be created with the specified password (Note that the password is only necessary if the Blockstack logins are later disabled).

The user has the ability to disconnect their Blockstack ID from their account from their preferences page in the __Blockstack__ tab.

## Installation
Put the code into your wiki's __extensions__ directory and load it the usual way using __wfLoadExtension__, you also need to set the __$wgBlockstackSsoPath__ variable to point at the location of the [Blockstack SSO common PHP class](https://github.com/saul-avikar/Blockstack-SSO) in your environment.

For example:
```php
wfLoadExtension( 'BlockstackSso' );
$wgBlockstackSsoPath = '/var/www/htdocs/BlockstackSso';
```
