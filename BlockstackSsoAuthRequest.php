<?php
use MediaWiki\Auth\AuthenticationRequest;
class BlockstackSsoAuthRequest extends AuthenticationRequest {

	public static function getUsernameFromRequests( array $reqs ) {
	}

	/**
	 * We skip login form by returning no fields
	 */
	public function getFieldInfo() {
		return [];
	}
}
