<?php
/**
 * BlockstackPrimaryAuthenticationProvider implementation
 */

namespace BlockstackSso\Auth;
use BlockstackSso;
use BlockstackSso\BlockstackUser;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use StatusValue;
use User;

/**
 * Implements a primary authentication provider to authenticate an user using a Blockstack forum
 * account where this user has access to. On beginning of the authentication, the provider
 * maybe redirects the user to an external authentication provider (a Blockstack forum) to
 * authenticate and permit the access to the data of the foreign account, before it actually
 * authenticates the user.
 */
class BlockstackPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	/** Name of the button of the BlockstackAuthenticationRequest */
	const BLOCKSTACK_BUTTON = 'blockstacksso';

	public static $bsUser;

	/**
	 * @var null|BlockstackUserInfoAuthenticationRequest
	 */
	private $autoCreateLinkRequest;

	/**
	 * We only begin the authentication if we have type=blockstack in the query string which means the client-side
	 * Blockstack authentication has made this request including the authentication info we must validate
	 */
	public function beginPrimaryAuthentication( array $reqs ) {
		global $wgRequest;
		if( $wgRequest->getText( self::BLOCKSTACK_BUTTON ) ) {

			// Get all the post values
			$did  = $wgRequest->getText( 'bsDid' );
			$bsUser = BlockstackUser::newFromDid( $did );

			// Is this Blockstack ID already linked to an account?
			if( $bsUser->isLinked() ) {

				// Verfify the request by ensuring it was made by a holder of the shared secret
				$verify = $wgRequest->getText( 'wpVerify' );
				$token  = $wgRequest->getText( 'wpLoginToken' );
				$secret = $bsUser->getSecret();
				$hash = md5( $secret . $token );
				if( $verify != $hash ) {
					wfDebugLog( __METHOD__, "Verification failed: $secret:$token:$hash" );
					return AuthenticationResponse::newFail( wfMessage( 'blockstacksso-verification-failed' ) );
				}

				return AuthenticationResponse::newPass( $bsUser->getWikiUser()->getName() );
			}

			// No it's not linked yet,
			else {				

				// Set the shared secret for this Blockstack ID
				$bsUser->setSecret( $wgRequest->getText( 'wpSecretKey' ) );
				$bsUser->setName( $wgRequest->getText( 'bsName' ) );
				$bsUser->save();

				// Return UI to ask the user for the linking account details
				return AuthenticationResponse::newUI(
					[ new BlockstackServerAuthenticationRequest( $reqs ) ],
					wfMessage( 'blockstacksso-form-merge', $bsUser->getName() )
				);
			}

		}
		return AuthenticationResponse::newAbstain();
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		wfDebugLog('Foo', 'continuing primary auth');
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail( wfMessage( 'blockstacksso-error-no-authentication-workflow' ) );
		}

		// Get the wiki user we're linking to
		$user = User::newFromName( $request->username );

		// Check the credentials
		if( $user->getId() == 0 || !$user->checkPassword( $request->password ) ) {
			return AuthenticationResponse::newUI(
				[ new BlockstackServerAuthenticationRequest( $reqs ) ],
				wfMessage( 'wrongpassword' )
			);
		}

		// If the wiki account already has an associated Blockstack ID
		$bsUser = BlockstackUser::newFromUserId( $user->getId() );
		if ( $bsUser !== false ) {
			return AuthenticationResponse::newUI(
				[ new BlockstackServerAuthenticationRequest( $reqs ) ],
				wfMessage( 'blockstacksso-unlink-first', $bsUser->getName() )
			);
		}

		// Link the account
		$bsUser = BlockstackUser::newFromDid( $request->bsDid );
		$bsUser->setWikiUser( $user );
		$bsUser->save();

		$resp = AuthenticationResponse::newPass( $user->getName() );
		$resp->linkRequest = new BlockstackServerAuthenticationRequest();
		return $resp;
		return AuthenticationResponse::newPass( $user->getName() );
	}

	public function autoCreatedAccount( $user, $source ) {
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {

			// When first visiting the login page, add out button
			case AuthManager::ACTION_LOGIN:
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso' ),
					wfMessage( 'blockstacksso-loginbutton-help' )
				) ];
				break;
			case AuthManager::ACTION_LINK:
				wfDebugLog('Foo', 'ACTION_LINK');
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso-form-merge', $options['bsName'] ),
					wfMessage( 'blockstacksso-link-help' )
				) ];
				break;
			case AuthManager::ACTION_REMOVE:
				wfDebugLog('Foo', 'ACTION_REMOVE');
				$user = User::newFromName( $options['username'] );
				if ( !$user || !BlockstackUser::newFromUserId( $user->getId() )->isLinked() ) {
					return [];
				}
				return [ new BlockstackRemoveAuthenticationRequest( $user->getId() ) ];
				break;
			case AuthManager::ACTION_CREATE:
				wfDebugLog('Foo', 'ACTION_CREATE');
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso-create' ),
					wfMessage( 'blockstacksso-link-help' )
				) ];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		wfDebugLog('Foo - test user exists', $username);
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		wfDebugLog('Foo - test user can auth', $username);
		return false;
	}

	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {

		// Can we unlink the account?
		if ( get_class( $req ) === BlockstackRemoveAuthenticationRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			if( is_object( $user ) && BlockstackUser::newFromUserId( $user->getId() )->isLinked() ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'blockstacksso-change-account-not-linked' ) );
			}
		}

		return StatusValue::newGood( 'ignored' );
	}

	/**
	 * Do the actual unlinking process
	 */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		wfDebugLog('Foo', __METHOD__);
		if ( get_class( $req ) === BlockstackRemoveAuthenticationRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			if( is_object( $user ) ) {
				BlockstackUser::newFromUserId( $user->getId() )->remove();
			}
		}
	}

	public function providerNormalizeUsername( $username ) {
		return null;
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackUserInfoAuthenticationRequest::class );
		if ( $request ) {
			if ( BlockstackUser::isXFUserIdFree( $request->userInfo['user_id'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;
				return $resp;
			}
		}
		return $this->beginBlockstackAuthentication( $reqs, self::BLOCKSTACK_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-error-no-authentication-workflow' )
			);
		}
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}
		try {
			$userInfo = $xfUser->get( 'me' );
			$isXFUserIdFree = BlockstackUser::isXFUserIdFree( $userInfo['user']['user_id'] );
			if ( $isXFUserIdFree ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new BlockstackUserInfoAuthenticationRequest( $userInfo );
				return $resp;
			}
			return AuthenticationResponse::newFail( wfMessage( 'blockstacksso-link-other' ) );
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-generic-error', $e->getMessage() )
			);
		}
	}

	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$userInfo = $response->linkRequest->userInfo;
		$user->setEmail( $userInfo['user']['user_email'] );
		$user->saveSettings();
		BlockstackUser::connectWithBlockstackUser( $user, $userInfo['user']['user_id'] );

		return null;
	}

	public function beginPrimaryAccountLink( $user, array $reqs ) {
		wfDebugLog('Foo', __METHOD__);
		return $this->beginBlockstackAuthentication( $reqs, self::BLOCKSTACK_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		wfDebugLog('Foo', __METHOD__);
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-error-no-authentication-workflow' )
			);
		}
		print 'continuing link...';
	}
}
