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
	/**
	 * Verification code provided by the server. Needs to be sent back in the last leg of the
	 * authorization process.
	 * @var string
	 */
	public $accessToken;

	/**
	 * An error code returned in case of Authentication failure
	 * @var string
	 */
	public $errorCode;

	public function getFieldInfo() {
		return [
			'error' => [
				'type' => 'string',
				'label' => wfMessage( 'blockstacklogin-param-error-label' ),
				'help' => wfMessage( 'blockstacklogin-param-error-help' ),
				'optional' => true,
			],
			'code' => [
				'type' => 'string',
				'label' => wfMessage( 'blockstacklogin-param-code-label' ),
				'help' => wfMessage( 'blockstacklogin-param-code-help' ),
				'optional' => true,
			],
		];
	}

	/**
	 * Load data from query parameters in an OAuth return URL
	 * @param array $data Submitted data as an associative array
	 * @return AuthenticationRequest|null
	 */
	public function loadFromSubmission( array $data ) {
		if ( isset( $data['code'] ) ) {
			$this->accessToken = $data['code'];
			return true;
		}

		if ( isset( $data['error'] ) ) {
			$this->errorCode = $data['error'];
			return true;
		}
		return false;
	}
}