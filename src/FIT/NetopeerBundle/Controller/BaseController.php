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
	 * @return 					array of assigned variables to template
	 */
	protected function getTwigArr() {

		if ( $this->getRequest()->getSession()->get('singleColumnLayout') == null ) {
			$this->getRequest()->getSession()->set('singleColumnLayout', true);
		}

		// if singleColumnLayout is not set, we will set default value
		if ( !array_key_exists('singleColumnLayout', $this->twigArr) ) {
			$this->assign('singleColumnLayout', $this->getRequest()->getSession()->get('singleColumnLayout'));
		}

		$session = $this->getRequest()->getSession();
		$flashes = $session->getFlashes();
		$stateFlashes = $configFlashes = $singleFlashes = $allFlashes = array();

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

			$allFlashes[$key] = $message;
		}

		$this->assign("stateFlashes", $stateFlashes);
		$this->assign("configFlashes", $configFlashes);
		$this->assign("singleFlashes", $singleFlashes);
		$this->assign("allFlashes", $allFlashes);

		$dataClass = $this->get('DataModel');
		$dataClass->buildMenuStructure($this->activeSectionKey); // vytvorime strukturu menu
		$this->assign('topmenu', $dataClass->getModels());
		$this->assign('submenu', $dataClass->getSubmenu($this->submenuUrl));

		try {
			if ($this->getRequest()->get('key') != "") {
				$conn = $session->get('session-connections');
				$conn = unserialize($conn[$this->getRequest()->get('key')]);
				if ($conn !== false) {
					$this->assign('lockedConn', $conn->locked);
				}
			}
		} catch (\ErrorException $e) {
			$this->get('logger')->notice('Trying to use foreign session key', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('error', "Trying to use unknown connection. Please, connect to the device.");
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
