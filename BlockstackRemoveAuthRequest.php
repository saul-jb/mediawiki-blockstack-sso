<?php
use MediaWiki\Auth\AuthenticationRequest;

/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a Blockstack account and a local wiki account.
 */
class BlockstackRemoveAuthenticationRequest extends AuthenticationRequest {

	private $blockstackUserId = null;

	public function __construct( $blockstackUserId ) {
		$this->xenForoUserId = $blockstackUserId;
	}

	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->blockstackUserId;
	}

	public function getFieldInfo() {
		return [];
	}

	/**
	 * Returns the Blockstack ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getBlockstackUserId() {
		return $this->blockstackUserId;
	}

	public function describeCredentials() {
		$blockstackUser = BlockstackUser::newFromBlockstackUserId( (int)$this->blockstackUserId );
		return [
			'provider' => wfMessage( 'blockstack-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $blockstackUser->getFullNameWithId() ] ),
		];
	}
}
