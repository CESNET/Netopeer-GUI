<?php
/**
 * File with Entity of connected device.
 *
 * Holds all information about connected device, which
 * will be stored in session array after successful connection.
 *
 * @author  David Alexa
 */
namespace FIT\NetopeerBundle\Entity;

/**
 * Class with Entity of connected device.
 */
class ConnectionSession {
	/**
	 * @var string time of connection start
	 */
	public $time;

	/**
	 * @var string identification key of connection
	 */
	public $hash;

	/**
	 * @var string target hostname
	 */
	public $host;

	/**
	 * @var int target port
	 */
	public $port;

	/**
	 * @var string logged username
	 */
	public $user;

	/**
	 * @var bool locked by us
	 */
	public $locked = false;

	/**
	 * @var string session info
	 */
	public $sessionStatus = "";

	/**
	 * Creates new instance and fill in all class variables except of sessionStatus.
	 *
	 * @param string $session_hash  identification key of connection
	 * @param string $host          target hostname
	 * @param int    $port          target port
	 * @param string $user          logged username
	 */
	function __construct($session_hash, $host, $port, $user)
	{
		$this->hash = $session_hash;
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$newtime = new \DateTime();
		$this->time = $newtime->format("d.m.Y H:i:s");
		$this->locked = false;
	}
}
