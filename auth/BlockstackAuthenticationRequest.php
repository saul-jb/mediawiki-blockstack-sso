<?php
/**
 * BlockstackAuthenticationRequest implementation
 */
namespace BlockstackSso\Auth;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;

/**
 * Implements a BlockstackAuthenticationRequest by extending a ButtonAuthenticationRequest
 * and describes the credentials used/needed by this AuthenticationRequest.
 */
class BlockstackAuthenticationRequest extends ButtonAuthenticationRequest {
	public function __construct( \Message $label, \Message $help ) {
		parent::__construct(
			BlockstackPrimaryAuthenticationProvider::BLOCKSTACK_BUTTON,
			$label,
			$help,
			true
		);
	}

	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [];
		}
		return parent::getFieldInfo();
	}
}
