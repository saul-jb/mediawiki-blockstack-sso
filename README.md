# Blockstack SSO extension for MediaWiki
not functional yet...

## Installation
Put the code into your wiki's __extensions__ directory and load it the usual way using __wfLoadExtension__, you also need to set the __$wgBlockstackSsoPath__ variable to point at the location of the [Blockstack SSO common PHP class](https://github.com/saul-avikar/Blockstack-SSO) in your environment.

For example:
```
wfLoadExtension( 'BlockstackSso' );
$wgBlockstackSsoPath = '/var/www/htdocs/BlockstackSso';
```
