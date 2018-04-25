# Blockstack SSO extension for MediaWiki
not functional yet...

This extension uses the [Blockstack SSO common PHP class](https://github.com/saul-avikar/Blockstack-SSO) to create a new authentication provider allowing users to sign in or create accounts with their [Blockstack](https://blockstack.org/) profile, which is a decentralised identity system that uses the [Bitcoin](http://bitcoin.org) blockchain.

A new "sign in with blockstack" button appears on the wiki's login form allowing users to select one of their Blockstack profiles, either from their locally installed Blockstack application, or using the [web-based Blockstack browser](http://browser.blockstack.org/).

If the selected Blockstack ID is already in use on the wiki, the user will then be automatically logged in to the account it's been previously associated with. Otherwise the user will be prompted to specify a username and password to connect the Blockstack ID to one of the wiki's accounts.

If the wiki allows public creation of accounts, then the specified username is allowed to be non-existent, in which case the account will be created and the Blockstack ID associated with it. Usually the wiki will require further steps to initially create the account such as email confirmation, so the Blockstack login will only work on newly created accounts after these steps have been carried out.

The user has the ability to disconnect their Blockstack ID from their account in the _Blockstack_ tab of the preferences page.

## Installation
Put the code into your wiki's __xtensions_ directory and load it the usual way using _wfLoadExtension_, you also need to set the _$wgBlockstackSsoPath_ variable to point at the location of the [Blockstack SSO common PHP class](https://github.com/saul-avikar/Blockstack-SSO) in your environment.

For example:
```php
wfLoadExtension( 'BlockstackSso' );
$wgBlockstackSsoPath = '/var/www/htdocs/BlockstackSso';
```
