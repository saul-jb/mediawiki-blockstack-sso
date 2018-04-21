<?php
class Blockstack {

	public static $instance = null;

	public static function onRegistration() {
		global $wgAuthManagerAutoConfig, $wgExtensionFunctions;

		// The setting in extension.json merges, but we don't want any of the default login methods
		$wgAuthManagerAutoConfig = [
			'preauth' => [],
			'primaryauth' => [
				BlockstackPrimaryAuthProvider::class => [
					'class' => BlockstackPrimaryAuthProvider::class
				]
			],
			'secondaryauth' => [],
		];
		wfDebugLog( __METHOD__, "Authentication provider replaced" );

		self::$instance = new self();
		$wgExtensionFunctions[] = array( self::$instance, 'setup' );
	}

	/**
	 * Called at extension setup time, install hooks and module resources
	 */
	public function setup() {
		global $wgOut, $wgExtensionAssetsPath, $wgAutoloadClasses, $IP, $wgResourceModules;

		// This gets the remote path even if it's a symlink (MW1.25+)
		$path = $wgExtensionAssetsPath . str_replace( "$IP/extensions", '', dirname( $wgAutoloadClasses[__CLASS__] ) );

		$wgResourceModules['ext.blockstack']['localBasePath'] = __DIR__ . '/modules';
		$wgResourceModules['ext.blockstack']['remoteExtPath'] = "$path/modules";
		$wgOut->addModules( 'ext.blockstack' );
	}

	/**
	 * After the user is logged out, call the post-logout code
	 */
	public static function onUserLogoutComplete( &$user, &$inject_html, $old_name ) {
		self::postLogout();
	}

	/**
	 * Post-login redirect?
	 */
 	private static function postLogout() {
		self::httpRedirect( "https://" . DcsCommon::$qar['Url'] . "/logout" );
	}

	/**
	 * Return a redirect and end the request
	 */
	public static function httpRedirect( $url ) {
		wfDebugLog( __METHOD__, "Redirecting to: $url" );
		header( "Location: $url" );
		self::restInPeace();
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
