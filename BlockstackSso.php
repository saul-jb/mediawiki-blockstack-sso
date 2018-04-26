<?php
class BlockstackSso {

	const TABLENAME = 'blockstacksso';

	public static $instance = null;

	private static $authResponse;
	
	public static $blockstackRequest = false;

	/**
	 * Called when the extension is first loaded
	 */
	public static function onRegistration() {
		global $wgExtensionFunctions;
		self::$instance = new self();
		$wgExtensionFunctions[] = array( self::$instance, 'setup' );
	}

	/**
	 * Called at extension setup time, install hooks and module resources
	 */
	public function setup() {
		global $wgRequest, $wgGroupPermissions, $wgOut, $wgExtensionAssetsPath, $wgAutoloadClasses, $IP, $wgResourceModules;

		// Add our DB table if it doesn't exist
		$this->addDatabaseTable();

		// Get script path accounting for symlinks
		$path = str_replace( "$IP/extensions", '', dirname( $wgAutoloadClasses[__CLASS__] ) );

		// Not using UnknownAction hook for these since we need to bypass permissions
		if( $wgRequest->getText('action') == 'blockstack-manifest' ) $this->returnManifest();
		if( $wgRequest->getText('action') == 'blockstack-validate' ) $this->returnValidation( $wgExtensionAssetsPath . $path );

		// This gets the remote path even if it's a symlink (MW1.25+)
		$wgResourceModules['ext.blockstackcommon']['localBasePath'] = __DIR__ . '/BlockstackCommon';
		$wgResourceModules['ext.blockstackcommon']['remoteExtPath'] = $path . '/BlockstackCommon';
		$wgOut->addModules( 'ext.blockstackcommon' );

		// Fancytree script and styles
		$wgResourceModules['ext.blockstacksso']['localBasePath'] = __DIR__ . '/modules';
		$wgResourceModules['ext.blockstacksso']['remoteExtPath'] = "$path/modules";
		$wgOut->addModules( 'ext.blockstacksso' );
		$wgOut->addStyle( "$path/styles/blockstacksso.css" );
		$wgOut->addJsConfigVars( 'blockstackManifestUrl', $this->manifestUrl() );
	}

	/**
	 * AuthChangeFormFields hook handler. Give the "Login with Blockstack" button a larger
	 * weight so that it shows below that password login button
	 */
	public static function onAuthChangeFormFields( array $requests, array $fieldInfo, array &$formDescriptor, $action ) {
		if ( isset( $formDescriptor['blockstacksso'] ) ) {
			$formDescriptor['blockstacksso'] = array_merge( $formDescriptor['blockstacksso'],
				[
					'weight' => 100,
					'flags' => [],
					'class' => \HTMLButtonField::class
				]
			);
			unset( $formDescriptor['blockstacksso']['type'] );
		}
	}

	/**
	 * Add our database table if it doesn't exist
	 */
	private function addDatabaseTable() {
		global $wgSiteNotice;
		$dbw = wfGetDB( DB_MASTER );
		if( !$dbw->tableExists( BlockstackSso::TABLENAME ) ) {
			$table = $dbw->tableName( BlockstackSso::TABLENAME );
			$dbw->query( "CREATE TABLE $table (
				bs_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
				bs_key  VARCHAR(128) NOT NULL,
				bs_user INT UNSIGNED NOT NULL,
				PRIMARY KEY (bs_id)
			)" );
			if( $dbw->tableExists( BlockstackSso::TABLENAME ) ) $wgSiteNotice = wfMessage( 'blockstacksso-tablecreated' )->text();
			else throw new MWException( wfMessage( 'blockstacksso-tablenotcreated' )->text() );
		}
		return true;
	}

	/**
	 * Returns an array of the salt and secret key known by the wiki and the JS side via the blockstack browser
	 * - if there is no secret yet, then a salt is created and stored/returned which the JS will make the secret from
	 */
	private function getSecret() {
		$dbr = wfGetDB( DB_SLAVE );
		if( $row = $dbr->selectRow( BlockstackSso::TABLENAME, 'bs_key', ['bs_id' => 0] ) ) {
			list( $salt, $key ) = explode( ':', $row->bs_key );
		}

		// There is no row zero yet, create one with a salt value in it
		else {
			$dbw = wfGetDB( DB_MASTER );
			$salt = MWCryptRand::generateHex( 32 );
			$dbw->insert( BlockstackSso::TABLENAME, ['bs_key' => $salt . ':'] );
		}

		return [$salt, $key];
	}

	/**
	 * Set the shared secret
	 */
	private function setSecret( $key ) {
		global $wgSiteNotice;
		$dbw = wfGetDB( DB_MASTER );
		$row = $dbw->selectRow( BlockstackSso::TABLENAME, 'bs_key', ['bs_id' => 0] );
		$dbw->update( BlockstackSso::TABLENAME, ['bs_key' => $row->bs_key . $key], ['bs_id' => 0] );
		$wgSiteNotice = wfMessage( 'blockstacksso-secretcreated' );
	}

	/**
	 * Return a JS page that validates a Blockstack response and POSTs the data to the login page
	 */
	public function returnValidation( $path ) {
		global $wgOut;

		// Supply the URL the final data should be posted to
		$url = Title::newFromText( 'UserLogin', NS_SPECIAL )->getLocalUrl();
		$data = 'window.action="' . $url ."\";\n";

		// Supply the secret salt if we don't yet have our key
		list( $salt, $key ) = $this->getSecret();
		$data .= 'window.salt="' . ( $key ? '' : $salt ) ."\";\n";

		// Add script headers to load our validation script and the blockstack JS
		$blockstack = "<script src=\"$path/BlockstackCommon/blockstack-common.min.js\"></script>\n";
		$validation = "<script src=\"$path/modules/validate.js\"></script>\n";

		// Output as a minimal HTML page and exit
		$wgOut->disable();
		$head = "<head>\n<title>Blockstack validation page</title>\n{$blockstack}{$validation}<script>\n{$data}</script>\n</head>\n";
		echo "<!DOCTYPE html>\n<html>\n$head<body onload=\"window.validate()\"></body></html>\n";
		self::restInPeace();
	}

	/**
	 * Return the JSON manifest with the correct headers and exit
	 */
	private function returnManifest() {
		global $wgOut, $wgSitename, $wgServer, $wgLogo;
		$wgOut->disable();
		header( 'Content-Type: application/json' );
		header("Access-Control-Allow-Origin: *");
		$manifest = [
			"name" => $wgSitename,
			"start_url" => $wgServer,
			"description" => wfMessage( 'sitesubtitle' )->text(),
			"icons" => [
				[
					"src" => $wgLogo,
					"type" => 'image/' . ( preg_match( '|^.+(.\w+)$|', $wgLogo, $m ) ? $m[1] : 'jpg' )
				]
			]
		];
		echo json_encode( $manifest );
		self::restInPeace();
	}

	/**
	 * Return the URL to the manifest
	 */
	private function manifestUrl() {
		global $wgServer, $wgScriptPath;
		return $wgServer . $wgScriptPath . '?action=blockstack-manifest';
	}

	/**
	 * Die nicely
	 */
	public static function restInPeace() {
		global $mediaWiki;
		if( is_object( $mediaWiki ) ) $mediaWiki->restInPeace();
		exit;
	}
}
