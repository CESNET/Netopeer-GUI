<?php

namespace FIT\NetopeerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class BaseController extends Controller
{
	private $activeSectionKey;
	private $submenuUrl;
	private $twigArr;

	/**
	 * Assignes variable to array, which will be send to template
	 * @param  $key   key of the associative array
	 * @param  $value value of the associative array
	 */
	protected function assign($key, $value) {
		$this->twigArr[$key] = $value;
	}

	/**
	 * Prepares variables to template, sort flashes and prepare menu
	 * @param  $THIS = null 	instance of called by controller
	 * @return 					array of assigned variables to template
	 */
	protected function getTwigArr($THIS = null) {
		if ($THIS != null ) {
			$session = $this->getRequest()->getSession();
			$flashes = $session->getFlashes();
			$stateFlashes = $configFlashes = $singleFlashes = array();

			// rozdelime flash messages dle klice do danych kategorii
			foreach ($flashes as $key => $message) {
				// maly tricek - pokud klic obsahuje slovo state, bude podminka splnena
				if ( strpos($key, 'tate') ) { // klic obsahuje slovo state
					$stateFlashes[$key] = $message;
				} elseif ( strpos($key, 'onfig') ) { // klic obsahuje slovo config
					$configFlashes[$key] = $message;
				} else { // klic obsahuje slovo single
					$singleFlashes[$key] = $message;
				} 

			}

			$this->assign("stateFlashes", $stateFlashes);
			$this->assign("configFlashes", $configFlashes);
			$this->assign("singleFlashes", $singleFlashes);

			$dataClass = $this->get('DataModel');
			$dataClass->buildMenuStructure($this->activeSectionKey); // vytvorime strukturu menu
			$this->assign('topmenu', $dataClass->getModels());
			$this->assign('submenu', $dataClass->getSubmenu($this->submenuUrl));

			if ($this->getRequest()->get('key') != "") {
				$conn = $session->get('session-connections');
				$conn = unserialize($conn[$this->getRequest()->get('key')]);
				$this->assign('lockedConn', $conn->locked);
			}
		}
		return $this->twigArr;
	}

	public function __construct () {
		$this->twigArr = array();	
		$this->activeSectionKey = null;	
	}

	/**
	 * sets info of current section key
	 * @param $key current section key
	 */
	public function setActiveSectionKey($key) {
		$this->activeSectionKey = $key;
	}

	public function setSubmenuUrl($submenuUrl) {
		$this->submenuUrl = $submenuUrl;
	}

}
