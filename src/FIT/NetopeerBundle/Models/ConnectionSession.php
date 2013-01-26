<?php

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
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$newtime = new \DateTime();
		$this->time = $newtime->format("d.m.Y H:i:s");
		$this->locked = false;
	}
}
