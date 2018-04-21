<?php
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
class BlockstackAuthRequest extends ButtonAuthenticationRequest {

	public function __construct( \Message $label, \Message $help ) {
		parent::__construct(
			'blockstackauth',
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
