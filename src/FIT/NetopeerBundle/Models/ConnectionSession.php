<?php
/**
 *  @todo move to Entity folder, merge with MyConnection?
 */
namespace FIT\NetopeerBundle\Models;

class ConnectionSession {
	/**
	 * @var time of connection start
	 */
	public $time;

	/**
	 * @var identification key of connection
	 */
	public $hash;

	/**
	 * @var identification key of connection stored in DB
	 */
	public $dbIdentifier;

	/**
	 * @var target hostname
	 */
	public $host;

	/**
	 * @var target port
	 */
	public $port;

	/**
	 * @var logged username
	 */
	public $user;

	/**
	 * @var locked by us
	 */
	public $locked = false;

	/**
	 * @var session info
	 */
	public $session_status = "";

	function __construct($session_hash, $host, $port, $user)
	{
		$this->hash = $session_hash;
		$this->dbIdentifier = array();
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$newtime = new \DateTime();
		$this->time = $newtime->format("d.m.Y H:i:s");
		$this->locked = false;
	}
}
