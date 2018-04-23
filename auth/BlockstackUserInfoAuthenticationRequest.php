<?php
/**
 * BlockstackUserInfoAuthenticationRequest implementation
 */

namespace BlockstackSso\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use BlockstackSso\BlockstackUser;

/**
 * An AUthenticationRequest that holds Blockstack user information.
 */
class BlockstackUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	/** @var array An array of infos (provided from Blockstack)
	 * about an user. */
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		$blockstackUser = BlockstackUser::newFromUserInfo( $this->userInfo );
		return [
			'provider' => wfMessage( 'blockstacksso-auth-service-name' ),
			'account' =>
				$blockstackUser ? new \RawMessage( '$1', [ $blockstackUser->getFullNameWithId() ] ) :
					wfMessage( 'blockstacksso-auth-service-unknown-account' )
		];
	}
}
