<?php
/**
 * BlockstackPrimaryAuthenticationProvider implementation
 */

namespace BlockstackSso\Auth;

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;

use MediaWiki\MediaWikiServices;
use StatusValue;
use User;
use BlockstackSso\BlockstackUser;
use BlockstackBDClient\Scopes;
use BlockstackBDClient\Clients\OAuth2Client;

/**
 * Implements a primary authentication provider to authenticate an user using a Blockstack forum
 * account where this user has access to. On beginning of the authentication, the provider
 * maybe redirects the user to an external authentication provider (a Blockstack forum) to
 * authenticate and permit the access to the data of the foreign account, before it actually
 * authenticates the user.
 */
class BlockstackPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	/** Name of the button of the BlockstackAuthenticationRequest */
	const BLOCKSTACK_BUTTONREQUEST_NAME = 'blockstacksso';

	/**
	 * @var null|BlockstackUserInfoAuthenticationRequest
	 */
	private $autoCreateLinkRequest;

	/**
	 * We only begin the authentication if we have type=blockstack in the query string which means the client-side
	 * Blockstack authentication has made this request including the authentication info we must validate
	 */
	public function beginPrimaryAuthentication( array $reqs ) {
		if( $_GET['type'] = 'blockstack' ) {
			wfDebugLog('Foo', 'begin primary auth');
			//return AuthenticationResponse::newRedirect( [ new BlockstackServerAuthenticationRequest() ], $req->returnToUrl );
			
			/**
			 * authenticate the request, check if the account is already linked and if so log in
			 */
			
			return AuthenticationResponse::newPass( 'Nad' );
		} else return AuthenticationResponse::newAbstain();
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		wfDebugLog('Foo', 'continuing primary auth');
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-error-no-authentication-workflow' )
			);
		}
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'blockstacksso' );
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}

		try {
			$userInfo = $xfUser->get( 'me' );
			if ( $userInfo === false ) {
				$errors = implode( $xfUser->getErrors(), ', ' );
				return AuthenticationResponse::newFail(
					wfMessage( 'blockstacksso-external-error', $errors )
				);
			}
			$connectedUser = BlockstackUser::getUserFromXFUserId( $userInfo['user']['user_id'] );
			$mwUser = User::newFromName( $userInfo['user']['username'] );
			if ( $connectedUser ) {
				return AuthenticationResponse::newPass( $connectedUser->getName() );
			} elseif ( $config->get( 'BlockstackSsoAutoCreate' ) && $mwUser->isAnon() ) {
				$this->autoCreateLinkRequest =
					new BlockstackUserInfoAuthenticationRequest( $userInfo['user'] );

				return AuthenticationResponse::newPass( $mwUser->getName() );
			} elseif ( $config->get( 'BlockstackSsoAutoCreate' ) && !$mwUser->isAnon() ) {
				// in this case, BlockstackSso is configured to autocreate accounts, however, the
				// account with the username of the blockstack board is already registered, but not
				// connected with this blockstack account. AuthManager would already give a warning
				// like "The account is not associated with any wiki account", however, as
				// BlockstackSso is configured to autocreate accounts this is not enough
				// information for most of the users reading that (and expecting their account to
				// be autocreated). That's why we throw another error here with some more
				// information and a help link.
				return AuthenticationResponse::newFail(
					wfMessage(
						'blockstacksso-local-exists',
						$mwUser->getName()
					)
				);
			} else {
				$resp = AuthenticationResponse::newPass( null );
				$resp->linkRequest = new BlockstackUserInfoAuthenticationRequest( $userInfo['user'] );
				$resp->createRequest = $resp->linkRequest;
				return $resp;
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-generic-error', $e->getMessage() )
			);
		}
	}

	public function autoCreatedAccount( $user, $source ) {
		if ( $this->autoCreateLinkRequest !== null && isset( $this->autoCreateLinkRequest->userInfo['user_id'] ) ) {
			BlockstackUser::connectWithBlockstackUser( $user,
				$this->autoCreateLinkRequest->userInfo['user_id'] );
			if ( isset( $this->autoCreateLinkRequest->userInfo['user_email'] ) ) {
				$user->setEmailWithConfirmation( $this->autoCreateLinkRequest->userInfo['user_email'] );
				$user->saveSettings();
			}
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {

			// When first visiting the login page
			case AuthManager::ACTION_LOGIN:
				wfDebugLog('Foo', 'ACTION_LOGIN');
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso' ),
					wfMessage( 'blockstacksso-loginbutton-help' )
				) ];
				break;
			case AuthManager::ACTION_LINK:
				wfDebugLog('Foo', 'ACTION_LINK');
				// TODO: Probably not the best message currently.
				return [ new BlockstackAuthenticationRequest(
					wfMessage( 'blockstacksso-form-merge' ),
					wfMessage( 'blockstacksso-link-help' )
				) ];
				break;
			case AuthManager::ACTION_REMOVE:
				wfDebugLog('Foo', 'ACTION_REMOVE');
				$user = User::newFromName( $options['username'] );
				if ( !$user || !BlockstackUser::hasConnectedXFUserAccount( $user ) ) {
					return [];
				}
				$xfUserId = BlockstackUser::getXFUserIdFromUser( $user );
				return [ new BlockstackRemoveAuthenticationRequest( $xfUserId ) ];
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
		wfDebugLog('Foo - '.__METHOD__, $req->action . ' : ' . $req->username);
		if ( get_class( $req ) === BlockstackRemoveAuthenticationRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			if ( $user && $req->getBlockstackUserId() === BlockstackUser::getXFUserIdFromUser( $user ) ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'blockstacksso-change-account-not-linked' ) );
			}
		}
		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		wfDebugLog('Foo', __METHOD__);
		if ( get_class( $req ) === BlockstackRemoveAuthenticationRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			BlockstackUser::terminateXFUserConnection( $user, $req->getBlockstackUserId() );
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
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}
		try {
			$userInfo = $xfUser->get( 'me' );
			$xfUserId = $userInfo['user']['user_id'];
			$potentialUser = BlockstackUser::getUserFromXFUserId( $xfUserId );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'blockstacksso-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'blockstacksso-link-same' ) );
			} else {
				$result = BlockstackUser::connectWithBlockstackUser( $user, $xfUserId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstacksso-generic-error', $e->getMessage() )
			);
		}
	}
}
