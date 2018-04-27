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
		if ( $bsUser->isLinked() ) {
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
		//return AuthenticationResponse::newPass( $user->getName() );
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
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso-form-merge', $options['bsName'] ),
					wfMessage( 'blockstacksso-link-help' )
				) ];
				break;

			case AuthManager::ACTION_REMOVE:
				$user = User::newFromName( $options['username'] );
				if ( !$user || !BlockstackUser::newFromUserId( $user->getId() )->isLinked() ) {
					return [];
				}
				return [ new BlockstackRemoveAuthenticationRequest( $user->getId() ) ];
				break;

			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		return false;
	}


	/**
	 * Can we unlink the account?
	 */
	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
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
		if ( get_class( $req ) === BlockstackRemoveAuthenticationRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			if( is_object( $user ) ) {
				BlockstackUser::newFromUserId( $user->getId() )->remove();
			}
		}
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
	}
}
