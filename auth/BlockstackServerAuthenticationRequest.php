<?php
/**
 * BlockstackServerAuthenticationRequest implementation
 */

namespace BlockstackSso\Auth;

use MediaWiki\Auth\AuthenticationRequest;

/**
 * Implements a BlockstackServerAuthenticationRequest that holds the data returned by a
 * redirect from Blockstack into the authentication workflow.
 */
class BlockstackServerAuthenticationRequest extends AuthenticationRequest {

	private $did = 0;

	public function __construct( $did ) {
		$this->did = $did;
	}

	public function getFieldInfo() {
		global $wgRequest;
		return [
			'username' => [
				'type' => 'string',
				'label' => wfMessage( 'userlogin-yourname' ),
				'help' => wfMessage( 'blockstacklogin-username-help' ),
				'optional' => false,
			],
			'password' => [
				'type' => 'password',
				'label' => wfMessage( 'userlogin-yourpassword' ),
				'help' => wfMessage( 'blockstacklogin-password-help' ),
				'optional' => false,
			],
			'bsDid' => [
				'type' => 'hidden',
				'value' => $this->did //$wgRequest->getText( 'bsDid' )
			],
		];
	}
}
