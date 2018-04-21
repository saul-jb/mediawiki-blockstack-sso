<?php
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationRequest;

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
			'provider' => wfMessage( 'blockstack-auth-service-name' ),
			'account' =>
				$blockstackUser ? new \RawMessage( '$1', [ $blockstackUser->getFullNameWithId() ] ) :
					wfMessage( 'blockstack-unknown-account' )
		];
	}
}
