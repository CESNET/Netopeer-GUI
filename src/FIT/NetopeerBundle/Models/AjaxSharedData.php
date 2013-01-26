<?php

namespace FIT\NetopeerBundle\Models;

class AjaxSharedData {
	protected $data;
	protected static $instance;

  protected function __construct() {
    echo "new instance";
  }

  public static function getInstance() {
      if (!isset(static::$instance)) {
          static::$instance = new static;
      }
      return static::$instance;
  }

  public function setDataForKey($key, $arrayKey, $value) {
    if (!isset($this->data[$key])) {
      $this->data[$key] = array();
    }
  	$this->data[$key][$arrayKey] = $value;
  }

  public function getDataForKey($key) {
  	return $this->data[$key];
  }
}
