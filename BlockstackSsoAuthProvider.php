<?php
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
class BlockstackSsoAuthProvider extends AbstractPrimaryAuthenticationProvider {

	/**
	 * TODO: do we need to log out any already logged in user first?
	 */
	function __construct() {
		global $wgUser;
		if( BLOCKSTACK_LOGGINING_IN ) {
			if( $wgUser->isLoggedIn() ) {
				$wgUser->logout();
				wfDebugLog( __METHOD__, "An already logged in user has been logged out" );
			}
		}
	}

	/**
	 * Use our auth request class when logging in
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		switch( $action ) {

			// Log the user in with the normal MW mechanism but without any login form
			case  MediaWiki\Auth\AuthManager::ACTION_LOGIN:
				wfDebugLog( __METHOD__, "Login action received" );
				return [ new BlockstackSsoAuthRequest() ];
				break;

			// Redirect the user
			case  MediaWiki\Auth\AuthManager::ACTION_CHANGE:
				wfDebugLog( __METHOD__, "Change action received" );
				global $wgUser;
				if( $wgUser->isLoggedIn() ) self::redirect();
				return [];
				break;

			default:
				wfDebugLog( __METHOD__, "Unknown action received" );
				return [];
		}
	}

	/**
	 * Call step 12 after user is logged in too
	 */
	public static function onUserLoginComplete( $user = null, $inject_html = null ) {
		self::redirect();
		return true;
	}

	/**
	 * Ignore all but our class of request
	 */
	public function providerAllowsAuthenticationDataChange( MediaWiki\Auth\AuthenticationRequest $req, $checkData = true ) {
		if( get_class($req) === BlockstackSsoAuthRequest::class ) {
			return StatusValue::newGood();
		} else {
			return StatusValue::newGood('ignored');
		}
	}

	/**
	 * Initialisaing login
	 */
	public function beginPrimaryAuthentication( array $reqs ) {

		// Update user data if user exists
		$user = User::newFromName( $this->info['username'] );
		if( $user->getId() ) $this->updateUserData( $user );

		// Now return with the user name to log in as, creates user if non-existent
		return MediaWiki\Auth\AuthenticationResponse::newPass( $this->info['username'] );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
	}

	/**
	 * Called after a new user has been created
	 */
	public function autoCreatedAccount( $user, $source ) {
		$this->updateUserData( $user );
	}

	public function accountCreationType() {
		return MediaWiki\Auth\PrimaryAuthenticationProvider::TYPE_NONE;
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return User::newFromName( $username )->exists();
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		//return AuthenticationResponse::ABSTAIN;
	}

	public function providerChangeAuthenticationData( MediaWiki\Auth\AuthenticationRequest $req ) {
	}

	/**
	 * Update the user's data (used in step 11 on user creation too)
	 */
	private function updateUserData( $user ) {
		wfDebugLog( __METHOD__, 'Updating data for user "' , $user->getName() . '"' );

		// Set the email address and confirm it if it has changed
		$email = $user->getEmail();
		if( $email != $this->info['email'] ) {
			$user->setEmail( $this->info['email'] );
			$user->confirmEmail();
		}

		// Set real name
		$user->setRealName( $this->info['realname'] );

		// Save the changes to the MW user
		$user->saveSettings();
	}

	/**
	 * Tidy up and redirect the user after login
	 */
	private static function redirect() {
		wfDebugLog( __METHOD__, 'Redirecting to home page' );
		BlockstackSso::httpRedirect( Title::newFromText( 'Home Page' )->getFullUrl() );
	}
}
