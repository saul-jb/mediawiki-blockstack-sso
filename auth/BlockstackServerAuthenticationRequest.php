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

	public function getFieldInfo() {
		return [
			'username' => [
				'type' => 'string',
				'label' => wfMessage( 'username' ),
				'help' => wfMessage( 'blockstacklogin-username-help' ),
				'optional' => false,
			],
			'password' => [
				'type' => 'password',
				'label' => wfMessage( 'password' ),
				'help' => wfMessage( 'blockstacklogin-password-help' ),
				'optional' => false,
			],
			'authResponse' => [
				'type' => 'hidden',
				'value' => 'foo'
			],
		];
	}
}
