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

		/* If this is a Blockstack athentication response?
		if( array_key_exists( 'type', $_GET ) && $_GET['type'] == 'blockstack' ) {
			self::blockstackRequest = true;

			// Does the response require authenticating?
			if( array_key_exists( 'authResponse', $_GET ) ) {

				// We need to return a minimal JS page that resolves the response
				// - this is because Blockstack requires the response to be authenicated client-side
				// - it also allows us to POST the data as if it were from the normal login form
				self::$authResponse = $_GET['authResponse'];
			}

			// It's already authenticated, convert the data to look like a MediaWiki login form submission
			else {
				$_SERVER['REQUEST_METHOD'] = 'POST';
				$_POST['wpName'] = $_GET['wpName'];
				$_POST['wpLoginToken'] = $_GET['token'];
				$_POST['authAction'] = 'login';
				$_POST[\BlockstackSso\Auth\BlockstackPrimaryAuthenticationProvider::BLOCKSTACK_BUTTON] = 'login';
				wfDebugLog( 'Foo', 'changing blockstack auth response into a login form submission' );
			}
		}
		*/

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
		if( $wgRequest->getText('action') == 'blockstack-manifest' ) self::returnManifest();
		if( $wgRequest->getText('action') == 'blockstack-validate' ) self::returnValidation( $wgExtensionAssetsPath . $path );

		// This gets the remote path even if it's a symlink (MW1.25+)
		$wgResourceModules['ext.blockstackcommon']['localBasePath'] = __DIR__ . '/BlockstackCommon';
		$wgResourceModules['ext.blockstackcommon']['remoteExtPath'] = $path . '/BlockstackCommon';
		$wgOut->addModules( 'ext.blockstackcommon' );

		// Fancytree script and styles
		$wgResourceModules['ext.blockstacksso']['localBasePath'] = __DIR__ . '/modules';
		$wgResourceModules['ext.blockstacksso']['remoteExtPath'] = "$path/modules";
		$wgOut->addModules( 'ext.blockstacksso' );
		$wgOut->addStyle( "$path/styles/blockstacksso.css" );
		$wgOut->addJsConfigVars( 'blockstackManifestUrl', self::manifestUrl() );
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
		global $wgSitenotice;
		$dbw = wfGetDB( DB_MASTER );
		if( !$dbw->tableExists( BlockstackSso::TABLENAME ) ) {
			$table = $dbw->tableName( BlockstackSso::TABLENAME );
			$dbw->query( "CREATE TABLE $table (
				bs_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
				bs_key  INT UNSIGNED NOT NULL,
				bs_user INT UNSIGNED NOT NULL,
				PRIMARY KEY (bs_id)
			)" );
		}
		if( $dbw->tableExists( BlockstackSso::TABLENAME ) ) $wgSitenotice = wfMessage( 'blockstacksso-tablecreated' )->text();
		else die( wfMessage( 'blockstacksso-tablenotcreated' )->text() );
		return true;
	}

	/**
	 * Return a JS page that validates a Blockstack response and POSTs the data to the login page
	 */
	public static function returnValidation( $path ) {
		global $wgOut;
		$wgOut->disable();
		$blockstack = "<script src=\"$path/BlockstackCommon/blockstack-common.min.js\"></script>";
		$validation = "<script src=\"$path/modules/validate.js\"></script>";
		$head = "<head><title>Blockstack validation page</title>$blockstack$validation</head>";
		echo "<!DOCTYPE html>\n<html>$head<body onload=\"window.validate()\"></body></html>";
		self::restInPeace();
	}

	/**
	 * Return the JSON manifest with the correct headers and exit
	 */
	public static function returnManifest() {
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
	public static function manifestUrl() {
		global $wgServer, $wgScriptPath;
		return $wgServer . $wgScriptPath . '?action=blockstack-manifest';
	}

	/**
	 * Die nicely
	 */
	private static function restInPeace() {
		global $mediaWiki;
		if( is_object( $mediaWiki ) ) $mediaWiki->restInPeace();
		exit;
	}
}
