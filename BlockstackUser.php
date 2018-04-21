<?php
use MediaWiki\MediaWikiServices;
class BlockstackUser {

	/**
	 * @var string The Blockstack User ID of this object
	 */
	private $blockstackUserId = '';
	private $userData = null;

	/**
	 * BlockstackUser constructor.
	 * @param integer $blockstackUserId The Blockstack User ID
	 */
	private function __construct( $blockstackUserId ) {
		$this->blockstackUserId = $blockstackUserId;
	}

	/**
	 * Creates a new BlockstackUser object based on the given Blockstack User ID. This function
	 * will start a request to the XenFor API to find out the information about
	 * the Blockstack User.
	 *
	 * @param int $blockstackUserId The Blockstack User ID
	 * @return BlockstackUser
	 */
	public static function newFromBlockstackUserId( $blockstackUserId ) {
		$user = new self( $blockstackUserId );
		$user->init();
		return $user;
	}

	/**
	 * Creates a new Blockstack User object based on the given user data. This
	 * function will not start a request to the Blockstack API and takes the
	 * information given in the $userInfo array as they are.
	 *
	 * @param array $userInfo An array of information about the user returned by the Blockstack API
	 * @return BlockstackUser|null Returns the Blockstack User object or null, if the
	 *  $userInfo array does not contain an "user_id" key.
	 */
	public static function newFromUserInfo( $userInfo ) {
		if ( !is_array( $userInfo ) ) {
			throw new \InvalidArgumentException( 'The first paramater of ' . __METHOD__ .
				' is required to be an array, ' .
				get_class( $userInfo ) . ' given.' );
		}
		if ( !isset( $userInfo['user']['user_id'] ) ) {
			return null;
		}
		$user = new self( $userInfo['user']['user_id'] );
		$user->userData = $userInfo['user'];
		return $user;
	}

	/**
	 * Loads the data of the person represented by the Blockstack User ID.
	 */
	private function init() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'blockstackauth' );
		$client = new UnauthenticatedClient();
		$client->setBaseUrl( $config->get( 'BlockstackAuthBaseUrl' ) );
		$user = new \BlockstackBDClient\Users\User( $client );
		$userInfo = $user->get( $this->blockstackUserId );
		if ( $userInfo ) {
			$this->userData = $userInfo['user'];
		}
	}
	/**
	 * Returns the requested user data of the user.
	 *
	 * @param string $data The data to retrieve
	 * @return null
	 */
	public function getData( $data ) {
		if ( $this->userData !== null && isset( $this->userData[$data] ) ) {
			return $this->userData[$data];
		}
		return null;
	}

	/**
	 * Returns the username and the user id, or the user id only, if no username was returned by
	 * the api.
	 *
	 * @return string
	 */
	public function getFullNameWithId() {
		if ( $this->getData( 'username' ) ) {
			return $this->getData( 'username' ) . ' ' . wfMessage( 'parentheses', $this->blockstackUserId );
		}
		return $this->blockstackUserId;
	}

	/**
	 * Check, if the data for the ID could be loaded.
	 * @return bool Returns true, if data could be loaded, false otherwise
	 */
	public function isDataLoaded() {
		return $this->userData !== null;
	}

	/**
	 * Check, if the Blockstack user ID is already connected to another wiki account or not.
	 *
	 * @param string $blockstackUserId
	 * @param int $flags
	 * @return bool
	 */
	public static function isBlockstackUserIdFree( $blockstackUserId, $flags = User::READ_LATEST ) {
		return self::getUserFromBlockstackUserId( $blockstackUserId, $flags ) === null;
	}

	/**
	 * Loads the Blockstack user Id from a User Id set to this object.
	 *
	 * @param User $user The user to get the Blockstack user Id for
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|int Null, if no Blockstack user ID connected with this User ID, the id
	 * otherwise
	 */
	public static function getBlockstackUserIdFromUser( User $user, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST ) ? wfGetDB( DB_MASTER ) : wfGetDB( DB_REPLICA );
		$s = $db->select(
			'user_blockstack_user',
			[ 'user_blockstackuserid' ],
			[ 'user_id' => $user->getId() ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);
		if ( $s !== false ) {
			foreach ( $s as $obj ) {
				return $obj->user_blockstackuserid;
			}
		}
		// Invalid user_id
		return null;
	}

	/**
	 * Helper function for load* functions. Loads the Blockstack Id from a
	 * User Id set to this object.
	 *
	 * @param string $blockstackUserId The Blockstack User ID to get the user to
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|User The local User account connected with the Blockstack user ID if
	 * the Blockstack user ID is connected to an User, null otherwise.
	 */
	public static function getUserFromBlockstackUserId( $blockstackUserId, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST ) ? wfGetDB( DB_MASTER ) : wfGetDB( DB_REPLICA );
		$s = $db->selectRow(
			'user_blockstack_user',
			[ 'user_id' ],
			[ 'user_blockstackuserid' => $blockstackUserId ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);
		if ( $s !== false ) {
			// Initialise user table data;
			return User::newFromId( $s->user_id );
		}
		// Invalid user_id
		return null;
	}

	/**
	 * Returns true, if this user object is connected with a blockstack account,
	 * otherwise false.
	 *
	 * @param User $user The user to check
	 * @return bool
	 */
	public static function hasConnectedBlockstackUserAccount( User $user ) {
		return (bool)self::getBlockstackUserIdFromUser( $user );
	}

	/**
	 * Terminates a connection between this wiki account and the
	 * connected Blockstack account.
	 *
	 * @param User $user The user to connect from where to remove the connection
	 * @param string $blockstackUserId The Blockstack ID to remove
	 * @return bool
	 */
	public static function terminateBlockstackUserConnection( User $user, $blockstackUserId ) {
		$connectedId = self::getBlockstackUserIdFromUser( $user );

		// make sure, that the user has a connected user account
		if ( $connectedId === null ) {
			// already terminated
			return true;
		}

		// get DD master
		$dbr = wfGetDB( DB_MASTER );

		// try to delete the row with this blockstack id
		if (
			$dbr->delete(
				"user_blockstack_user",
				"user_blockstackuserid = " . $blockstackUserId,
				__METHOD__
			)
		) {
			return true;
		}

		// something went wrong
		return false;
	}

	/**
	 * Insert's or update's the Blockstack ID connected with this user account.
	 *
	 * @param User $user The user to connect the Blockstack ID with
	 * @param String $blockstackUserId The new Blockstack ID
	 * @return bool Whether the insert/update statement was successful
	 */
	public static function connectWithBlockstackUser( User $user, $blockstackUserId ) {
		$dbr = wfGetDB( DB_MASTER );
		return $dbr->insert(
			"user_blockstack_user",
			[
				'user_id' => $user->getId(),
				'user_blockstackuserid' => $blockstackUserId
			]
		);
	}
}
