<?php
/**
 * File with settings of user.
 *
 * Holds all available settings for user;
 *
 * @author  David Alexa
 */
namespace FIT\NetopeerBundle\Entity;

/**
 * Class with settings of user.
 */
class UserSettings {

	/**
	 * @var int duration in days for leaving devices in history
	 */
	protected $historyDuration;

	/**
	 * initialize User settings object and sets default values
	 */
	public function __construct() {
		$this->historyDuration = 30;
	}

	/**
	 * set duration in days for leaving devices in history
	 *
	 * @param int $duration
	 */
	public function setHistoryDuration($duration) {
		$this->historyDuration = $duration;
	}

	/**
	 * get duration in days for leaving devices in history
	 *
	 * @return int
	 */
	public function getHistoryDuration() {
		return $this->historyDuration;
	}
}