<?php

namespace FIT\NetopeerBundle\Models;

/**
 * This class holds shared data for Ajax operations
 */
class AjaxSharedData {
	/**
	 * @var array   shared data
	 */
	protected $data;

	/**
	 * @var AjaxSharedData  instance for singleton
	 */
	protected static $instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
    
  }

	/**
	 * Get instance of this class
	 *
	 * @return AjaxSharedData
	 */
	public static function getInstance() {
      if (!isset(static::$instance)) {
          static::$instance = new static;
      }
      return static::$instance;
  }

	/**
	 * Set keys and values of shared data array
	 *
	 * @param mixed $key
	 * @param mixed $arrayKey
	 * @param mixed $value
	 */
	public function setDataForKey($key, $arrayKey, $value) {
    if (!isset($this->data[$key])) {
      $this->data[$key] = array();
    }
  	$this->data[$key][$arrayKey] = $value;
  }

	/**
	 * Get value from shared data array
	 *
	 * @param mixed   $key    key of shared array
	 * @return mixed
	 */
	public function getDataForKey($key) {
  	return $this->data[$key];
  }
}
