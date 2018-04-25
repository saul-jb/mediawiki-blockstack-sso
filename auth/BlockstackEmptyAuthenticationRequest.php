<?php
namespace BlockstackSso\Auth;

use MediaWiki\Auth\AuthenticationRequest;

class BlockstackEmptyAuthenticationRequest extends AuthenticationRequest {

	public static function getUsernameFromRequests( array $reqs ) {
	}

	/**
	 * We skip login form by returning no fields
	 */
	public function getFieldInfo() {
		return [];
	}
}
