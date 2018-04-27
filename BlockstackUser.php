<?php
/**
 * The instances of this class represent rows in the blockstacksso database table,
 * each is a lnk between a Blockstack ID and a wiki user
 */
namespace BlockstackSso;
use BlockstackSso;
use User;

class BlockstackUser {

	const TABLENAME = 'blockstacksso';

	private $did;            // The Blockstack distributed ID
	private $name;           // The Blockstack username
	private $secret;         // The shared secret used to communicate with the Blockstack client-side service
	private $userId = 0;     // The ID of the associated wiki user
	private $exists = false; // Whether this instance has a corresponding row in the database or not

	private function __construct( $did = '' ) {
		$this->did = $did;
		$this->init();
	}

	/**
	 * Create a new BlockstackUser instance from a distributed Blockstack ID
	 */
	public static function newFromDid( $did ) {
		return new self( $did );
	}

	/**
	 * Create a new BlockstackUser instance from a wiki user ID
	 */
	public static function newFromUserId( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		if( $row = $dbr->selectRow( self::TABLENAME, 'bs_did', ['bs_user' => $id] ) ) {
			return new self( $row->bs_did );
		}
		return new self();
	}

	/**
	 * Loads the data of the person represented by the Blockstack User ID.
	 */
	private function init() {
		$dbr = wfGetDB( DB_SLAVE );
		if( !$dbr->tableExists( self::TABLENAME ) ) $this->addDatabaseTable(); // Add our DB table if it doesn't exist
		if( $row = $dbr->selectRow( self::TABLENAME, '*', ['bs_did' => $this->did] ) ) {
			$this->name = $row->bs_name;
			$this->secret = $row->bs_secret;
			$this->userId = $row->bs_user;
			$this->exists = true;
		} else $this->exists = false;
	}

	/**
	 * Add our database table if it doesn't exist
	 */
	private function addDatabaseTable() {
		global $wgSiteNotice;
		$dbw = wfGetDB( DB_MASTER );
		$table = $dbw->tableName( self::TABLENAME );
		$dbw->query( "CREATE TABLE $table (
			bs_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
			bs_did    VARCHAR(40)  NOT NULL,
			bs_name   VARCHAR(128) NOT NULL,
			bs_secret VARCHAR(128)  NOT NULL,
			bs_user   INT UNSIGNED NOT NULL,
			PRIMARY KEY (bs_id)
		)" );
		if( $dbw->tableExists( self::TABLENAME ) ) $wgSiteNotice = wfMessage( 'blockstacksso-tablecreated' )->text();
		else throw new MWException( wfMessage( 'blockstacksso-tablenotcreated' )->text() );
	}

	/**
	 * Update the database to match the current user details, create if necessary
	 */
	public function save() {
		$dbw = wfGetDB( DB_MASTER );
		$row = [
			'bs_did'    => $this->did,
			'bs_name'   => $this->name,
			'bs_secret' => $this->secret,
			'bs_user'   => $this->userId
		];

		// Update the row if it exists already
		if( $dbw->selectRow( self::TABLENAME, '*', ['bs_did' => $this->did] ) ) {
			$dbw->update( self::TABLENAME, $row, ['bs_did' => $this->did] );
		}

		// User doesn't exist, create now
		else {
			$dbw->insert( self::TABLENAME, $row );
			$this->exists = true;
		}
	}

	/**
	 * Remove this Blockstack user from the table
	 */
	public function remove() {
		if( $this->exists ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete( self::TABLENAME, ['bs_did' => $this->did] );
			$this->exists = false;
		}
	}

	/**
	 * Returns whether or not the instance has an associated wiki account yet
	 */
	public function isLinked() {
		return (bool)$this->userId;
	}

	/**
	 * Returns whether or not the instance exists in the database
	 * - note that an instance can exist but still have no associated wiki user yet
	 */
	public function exists() {
		return $this->exists;
	}



	// The following are all getters and setters for the object state

	public function getDid() {
		return $this->did;
	}

	public function getName() {
		return $this->name;
	}

	public function setName( $name ) {
		$this->name = $name;
	}

	public function getSecret() {
		return $this->secret;
	}

	public function setSecret( $secret ) {
		$this->secret = $secret;
	}

	public function getWikiUser() {
		return User::newFromId( $this->userId );
	}

	public function getWikiUserId() {
		return $this->userId;
	}

	public function setWikiUser( User $user ) {
		$this->userId = $user->getId();
	}

	public function setWikiUserId( int $id ) {
		$this->userId = $id;
	}
}
