<?php
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

/**
 * Implements a primary authentication provider to authenticate an user using a Blockstack forum
 * account where this user has access to. On beginning of the authentication, the provider
 * maybe redirects the user to an external authentication provider (a Blockstack forum) to
 * authenticate and permit the access to the data of the foreign account, before it actually
 * authenticates the user.
 */
class BlockstackPrimaryAuthProvider extends AbstractPrimaryAuthenticationProvider {

	/**
	 * @var null|BlockstackUserInfoAuthenticationRequest
	 */
	private $autoCreateLinkRequest;

	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginPrimaryAuthentication( $reqs, 'blockstackauth' );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		global $wgBlockstackAuthAutoCreate;
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-error-no-authentication-workflow' ) );
		}
		$blockstackUser = $this->getAuthenticatedBlockstackUserFromRequest( $request );
		if ( $blockstackUser instanceof AuthenticationResponse ) {
			return $blockstackUser;
		}
		try {
			$userInfo = $blockstackUser->get( 'me' );
			if ( $userInfo === false ) {
				$errors = implode( $xfUser->getErrors(), ', ' );
				return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-external-error', $errors ) );
			}
			$connectedUser = BlockstackUser::getUserFromBlockstackUserId( $userInfo['user']['user_id'] );
			$mwUser = User::newFromName( $userInfo['user']['username'] );
			if ( $connectedUser ) {
				return AuthenticationResponse::newPass( $connectedUser->getName() );
			} elseif ( $wgBlockstackAuthAutoCreate && $mwUser->isAnon() ) {
				$this->autoCreateLinkRequest = new BlockstackUserInfoAuthRequest( $userInfo['user'] );
				return AuthenticationResponse::newPass( $mwUser->getName() );
			} elseif ( $wgBlockstackAuthAutoCreate && !$mwUser->isAnon() ) {
				// in this case, BlockstackAuth is configured to autocreate accounts, however, the
				// account with the username of the blockstack board is already registered, but not
				// connected with this blockstack account. AuthManager would already give a warning
				// like "The account is not associated with any wiki account", however, as
				// BlockstackAuth is configured to autocreate accounts this is not enough
				// information for most of the users reading that (and expecting their account to
				// be autocreated). That's why we throw another error here with some more
				// information and a help link.
				return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-local-exists', $mwUser->getName() ) );
			} else {
				$resp = AuthenticationResponse::newPass( null );
				$resp->linkRequest = new BlockstackUserInfoAuthRequest( $userInfo['user'] );
				$resp->createRequest = $resp->linkRequest;
				return $resp;
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstackauth-generic-error', $e->getMessage() )
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

			case AuthManager::ACTION_LOGIN:
				return [ new BlockstackAuthRequest(
					wfMessage( 'blockstackauth' ),
					wfMessage( 'blockstackauth-loginbutton-help' )
				) ];
				break;

			case AuthManager::ACTION_LINK:
				// TODO: Probably not the best message currently.
				return [ new BlockstackAuthRequest(
					wfMessage( 'blockstackauth-form-merge' ),
					wfMessage( 'blockstackauth-link-help' )
				) ];
				break;

			case AuthManager::ACTION_REMOVE:
				$user = User::newFromName( $options['username'] );
				if ( !$user || !BlockstackUser::hasConnectedBlockstackUserAccount( $user ) ) {
					return [];
				}
				$blockstackUserId = BlockstackUser::getBlockstackUserIdFromUser( $user );
				return [ new BlockstackRemoveAuthRequest( $blockstackUserId ) ];
				break;

			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new BlockstackAuthRequest(
					wfMessage( 'blockstackauth-create' ),
					wfMessage( 'blockstackauth-link-help' )
				) ];
				break;

			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		$user = User::newFromName( $username );
		if ( $user ) {
			return BlockstackUser::hasConnectedBlockstackUserAccount( $user );
		}
		return false;
	}

	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
		if ( get_class( $req ) === BlockstackRemoveAuthRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			if ( $user && $req->getBlockstackUserId() === BlockstackUser::getBlockstackUserIdFromUser( $user ) ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'blockstackauth-change-account-not-linked' ) );
			}
		}
		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		if ( get_class( $req ) === BlockstackRemoveAuthRequest::class && $req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			BlockstackUser::terminateBlockstackUserConnection( $user, $req->getBlockstackUserId() );
		}
	}

	public function providerNormalizeUsername( $username ) {
		return null;
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackUserInfoAuthRequest::class );
		if ( $request ) {
			if ( BlockstackUser::isBlockstackUserIdFree( $request->userInfo['user_id'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;
				return $resp;
			}
		}
		return $this->beginBlockstackAuthentication( $reqs, 'blockstackauth' );
	}

	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstackauth-error-no-authentication-workflow' )
			);
		}
		$blockstackUser = $this->getAuthenticatedBlockstackUserFromRequest( $request );
		if ( $blockstackUser instanceof AuthenticationResponse ) {
			return $blockstackUser;
		}
		try {
			$userInfo = $blockstackUser->get( 'me' );
			$isBlockstackUserIdFree = BlockstackUser::isBlockstackUserIdFree( $userInfo['user']['user_id'] );
			if ( $isBlockstackUserIdFree ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new BlockstackUserInfoAuthRequest( $userInfo );
				return $resp;
			}
			return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-link-other' ) );
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstackauth-generic-error', $e->getMessage() )
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
		return $this->beginBlockstackAuthentication( $reqs, 'blockstackauth' );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, BlockstackServerAuthRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstackauth-error-no-authentication-workflow' )
			);
		}
		$blockstackUser = $this->getAuthenticatedBlockstackUserFromRequest( $request );
		if ( $blockstackUser instanceof AuthenticationResponse ) {
			return $blockstackUser;
		}
		try {
			$userInfo = $blockstackUser->get( 'me' );
			$blockstackUserId = $userInfo['user']['user_id'];
			$potentialUser = BlockstackUser::getUserFromBlockstackUserId( $blockstackUserId );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-link-same' ) );
			} else {
				$result = BlockstackUser::connectWithBlockstackUser( $user, $blockstackUserId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'blockstackauth-generic-error', $e->getMessage() )
			);
		}
	}

	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication
	 * request can be handled by BlockstackAuth and, if so, returns an AuthenticationResponse that
	 * redirects to the external authentication site, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginBlockstackAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		$req = BlockstackAuthRequest::getRequestByName( $reqs,
			$buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getBlockstackClient( $req->returnToUrl );
		return AuthenticationResponse::newRedirect( [ new BlockstackServerAuthRequest() ], $client->getAuthenticationRequestUrl() );
	}

	/**
	 * Returns an instance of OAuth2Client, which is set up for the use in an authentication
	 * workflow.
	 *
	 * @param string $returnUrl
	 * @return OAuth2Client
	 */
	public function getBlockstackClient( $returnUrl ) {
		/*$client = new OAuth2Client();
		$client->setBaseUrl( $config->get( 'BlockstackAuthBaseUrl' ) )
			->setClientId( $config->get( 'BlockstackAuthClientId' ) )
			->setClientSecret( $config->get( 'BlockstackAuthClientSecret' ) )
			->addScope( Scopes::READ )
			->setRedirectUri( $returnUrl );
		*/
		return $client;
	}

	/**
	 * Returns an authenticated BlockstackUser object.
	 *
	 * @param $request
	 * @return BlockstackUser|AuthenticationResponse
	 */
	private function getAuthenticatedBlockstackUserFromRequest( BlockstackServerAuthRequest $request ) {
		if ( !$request->accessToken || $request->errorCode ) {
			switch ( $request->errorCode ) {
				case 'access_denied':
					return AuthenticationResponse::newFail( wfMessage( 'blockstackauth-access-denied' ) );
					break;
				default:
					return AuthenticationResponse::newFail( wfMessage(
						'blockstackauth-generic-error', $request->errorCode ? $request->errorCode :
						'unknown' ) );
			}
		}
		$client = $this->getBlockstackClient( $request->returnToUrl );
		$client->authenticate( $request->accessToken );
		$user = new BlockstackUser( $client );
		return $user;
	}
}
