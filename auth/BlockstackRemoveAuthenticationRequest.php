<?php
/**
 * BlockstackRemoveAuthenticationRequest implementation
 */

namespace BlockstackSso\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use BlockstackSso\BlockstackUser;
use User;

/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a Blockstack account and a local wiki account.
 */
class BlockstackRemoveAuthenticationRequest extends AuthenticationRequest {

	private $userId = null;

	public function __construct( $userId ) {
		$this->userId = $userId;
	}

	public function getFieldInfo() {
		return [];
	}

	/**
	 * Returns the User ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getUserId() {
		return $this->userId;
	}

	public function describeCredentials() {
		$user = User::newFromId( $this->userId );
		return [
			'provider' => wfMessage( 'blockstacksso-auth-service-name' ),
			'account' => new \RawMessage( '$1', [ $user->getName() ] )
		];
	}
}
