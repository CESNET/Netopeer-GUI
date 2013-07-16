<?php
/**
 * Default controller for all pages directly visible from  webGUI.
 *
 * @file DefaultController.php
 * @author David Alexa <alexa.david@me.com>
 * @author Tomas Cejka <cejkat@cesnet.cz>
 *
 * Copyright (C) 2012-2013 CESNET
 *
 * LICENSE TERMS
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 * 3. Neither the name of the Company nor the names of its contributors
 *    may be used to endorse or promote products derived from this
 *    software without specific prior written permission.
 *
 * ALTERNATIVELY, provided that this notice is retained in full, this
 * product may be distributed under the terms of the GNU General Public
 * License (GPL) version 2 or later, in which case the provisions
 * of the GPL apply INSTEAD OF those given above.
 *
 * This software is provided ``as is'', and any express or implied
 * warranties, including, but not limited to, the implied warranties of
 * merchantability and fitness for a particular purpose are disclaimed.
 * In no event shall the company or contributors be liable for any
 * direct, indirect, incidental, special, exemplary, or consequential
 * damages (including, but not limited to, procurement of substitute
 * goods or services; loss of use, data, or profits; or business
 * interruption) however caused and on any theory of liability, whether
 * in contract, strict liability, or tort (including negligence or
 * otherwise) arising in any way out of the use of this software, even
 * if advised of the possibility of such damage.
 *
 */
namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use FIT\NetopeerBundle\Models\XMLoperations;
use FIT\NetopeerBundle\Entity\BaseConnection;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Default controller for all pages directly visible from
 * webGUI. For example connection management, section detail etc.
 */
class DefaultController extends BaseController
{

	/**
	 * @var array $paramState   array of parameters for <get> command
	 * @var array $paramsConfig array of parameters for <get-config> command
	 */
	private $paramsState, $paramsConfig;

	/**
	 * @var holds configuration of filter form (in both section)
	 */
	private $filterForms;

	/**
	 * Prepares form for connection to the server and table with active connection list
	 *
	 * @Route("/", name="_home")
	 * @Route("/device-{connectedDeviceId}/", name="homeFromHistory")
	 * @Template()
	 *
	 * @param int $connectedDeviceId    id of connected device from history
	 * @return array
	 */
	public function indexAction($connectedDeviceId = NULL)
	{
		// DependencyInjection (DI) - defined in Resources/config/services.yml
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');

		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'alerts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'state');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'config');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'leftColumn');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'notifications');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'topMenu');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'topPart');
		$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'javascripts');

		//TODO: delete only session from refferer
		$this->getRequest()->getSession()->set('activeNotifications', array());

		$host = "";
		$port = "22";
		$userName = "";
		if ($connectedDeviceId !== NULL) {
			/**
			 * @var \FIT\NetopeerBundle\Entity\BaseConnection $baseConn
			 */
			$baseConn = $this->get("BaseConnection");
			$device = $baseConn->getConnectionForCurrentUserById($connectedDeviceId);
			if ($device) {
				$host = $device->getHost();
				$port = $device->getPort();
				$userName = $device->getUsername();
			}
		}

		// build form for connection to the server
		$form = $this->createFormBuilder()
			->add('host', 'text', array('attr' => array('value' => $host)))
			->add('port', 'number', array('attr' => array('value' => $port)))
			->add('user', 'text', array('attr' => array('value' => $userName)))
			->add('password', 'password')
			->getForm();
		$this->assign('form', $form->createView());

		// check, if in prev action has been added new connection. If yes,
		// we will set some variables for AJAX requests in indexTemplate.
		if ($this->get('session')->get('getSchemaWithAjax')) {
			foreach ($this->get('session')->get('getSchemaWithAjax') as $key => $value) {
				$this->assign($key, $value);
			}
			$this->assign("getSchemaWithAjax", true);
			$this->get('session')->set('getSchemaWithAjax', false);
		}
		// process form for connection to the server
		if ($this->getRequest()->getMethod() == 'POST') {
			$form->bindRequest($this->getRequest());

			if ($form->isValid()) {
				$post_vals = $this->getRequest()->get("form");
				$params = array(
					"host" => $post_vals["host"],
					"user" => $post_vals["user"],
					"port" => $post_vals["port"],
					"pass" => $post_vals["password"],
					"capabilities" => array( /* TODO make somehow configurable... */
						"urn:ietf:params:netconf:base:1.0",
						"urn:ietf:params:netconf:base:1.1",
						"urn:ietf:params:netconf:capability:startup:1.0",
						//"urn:ietf:params:netconf:capability:notification:1.0", /* still do not have in libnetconf */
						"urn:ietf:params:netconf:capability:writable-running:1.0",
						"urn:ietf:params:netconf:capability:candidate:1.0",
						"urn:ietf:params:netconf:capability:with-defaults:1.0?basic-mode=explicit&amp;also-supported=report-all,report-all-tagged,trim,explicit",
						"urn:cesnet:tmc:comet:1.0",
						"urn:cesnet:tmc:combo:1.0",
						"urn:cesnet:tmc:hanicprobe:1.0",
						"urn:ietf:params:xml:ns:yang:ietf-netconf-monitoring?module=ietf-netconf-monitoring",
					),
				);

				// state flash = state -> left column in the layout
				$dataClass->setFlashState('state');
				$result = "";
				$res = $dataClass->handle("connect", $params, false, $result);

				// if connection is broken (Could not connect)
				if ($res == 0) {
					$conn = $this->getRequest()->getSession()->get('session-connections');
					$conn = unserialize($conn[$result]);
					$arr = array();
					if ($conn !== false) {
						$arr = array(
							"idForAjaxGetSchema" => $result,
//							'lockedConn' => $conn->locked,
//							'sessionStatus' => $conn->sessionStatus,
							'sessionHash' => $conn->hash,
						);
					}

					if ($this->getRequest()->isXmlHttpRequest()) {
						foreach ($arr as $key => $value) {
							$this->assign($key, $value);
						}
						$this->assign("getSchemaWithAjax", true);
					} else {
						$this->get('session')->set('getSchemaWithAjax', $arr);
					}
					$this->getRequest()->getSession()->setFlash('state success', 'Form has been filled up correctly.');

					$baseConn = $this->get('BaseConnection');
					$baseConn->saveConnectionIntoDB($post_vals['host'], $post_vals['port'], $post_vals['user']);

				}
			} else {
				$this->getRequest()->getSession()->setFlash('state error', 'You have not filled up form correctly.');
			}
			$url = $this->get('request')->headers->get('referer');
			if (!$this->getRequest()->isXmlHttpRequest()) {
				return new RedirectResponse($url);
			}
		}
		$connArray = $this->getRequest()->getSession()->get('session-connections');
		$connections = array();
		if (sizeof($connArray) > 0) {
			foreach ($connArray as $c) {
				$connections[] = unserialize($c);
			}
		}
		$this->assign('sessionConnections', $connections);
		$this->assign('singleColumnLayout', false);
		$this->assign('hideColumnControl', true);
		$this->assign('activeAction', 'home');
		return $this->getTwigArr();
	}

	/**
	 * Change session value for showing single or double column layout
	 *
	 * @Route("/changeColumnLayout/{newValue}/", name="changeColumnLayout")
	 *
	 * @param string $newValue    new value of columns settings
	 * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function changeColumnLayoutAction($newValue)
	{
		$this->get('session')->set('singleColumnLayout', $newValue);
		$this->assign('singleColumnLayout', $newValue);

		//reconstructs a routing path and gets a routing array called $route_params
        $url = $this->get('request')->headers->get('referer');
        return new RedirectResponse($url);
	}

	/**
	 * Reload current device and invalidate cache
	 *
	 * @Route("/reload/{key}/", name="reloadDevice")
	 *
	 * @param int     $key          key of connected device
	 * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function reloadDeviceAction($key)
	{
		$dataClass = $this->get('DataModel');

		/* reload hello message */
		$params = array('key' => $key);
		$dataClass->handle("reloadhello", $params);

		$dataClass->updateLocalModels($key);
		$dataClass->invalidateMenuStructureForKey($key);

		//reconstructs a routing path and gets a routing array called $route_params
		$url = $this->get('request')->headers->get('referer');
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->getRequest()->getSession()->set('isAjax', true);
		}

		return new RedirectResponse($url);
	}

	/**
	 * Handle actions and execute them in Models/Data
	 *
	 * @Route("/handle/{command}/{key}/{identifier}", defaults={"identifier" = ""}, name="handleConnection")
	 *
	 * @param string  $command      name of the command to handle (get, info, getconfig, getschema, connect, disconnect)
	 * @param int     $key          key of connected device
	 * @param string   $identifier  identifier for get-schema
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function handleConnectionAction($command, $key, $identifier = "")
	{
		$dataClass = $this->get('DataModel');
		$params = array(
			'key' => $key,
			'filter' => '',
		);

		if ( ($command === "get") || ($command  === "info") ) {
			$dataClass->setFlashState('state');
		} else {
			$dataClass->setFlashState('config');
		}

		if ($command === "getschema") {
			$params['identifier'] = $identifier;
			$params['format'] = "yin";
		} elseif ($command === "killsession") {
			$params['session-id'] = $identifier;
		}

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->getRequest()->getSession()->set('isAjax', true);
		}

		if ($command === "lock" || $command === "unlock") {
			$this->getRequest()->getSession()->set('isLocking', true);
		}

		$res = $dataClass->handle($command, $params);

		if ( $res != 1 ) {
			return $this->redirect($this->generateUrl('section', array('key' => $key)));
		}

		// if something goes wrong, we will redirect to connections page
		if ( in_array($command, array("connect", "disconnect", "getschema")) ) {
			return $this->redirect($this->generateUrl('_home'));
		} else {
			$url = $this->get('request')->headers->get('referer');
			return $this->redirect($url);
		}
	}

	/**
	 * Shows info page with information
	 *
	 * @Route("/info-page/{key}/{action}/", name="infoPage")
	 * @Template("FITNetopeerBundle:Default:section.html.twig")
	 *
	 * @param int     $key          key of connected server
	 * @param string  $action       name of the action ("session"|"reload")
	 * @return array
	 */
	public function sessionInfoAction($key, $action)
	{
		$dataClass = $this->get('DataModel');
		parent::setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'singleContent');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'alerts');

		if ( $action == "session" ) {
			$session = $this->container->get('request')->getSession();
			$sessionArr = $session->all();

			$xml = XMLoperations::createXML("session", $sessionArr);
			$xml = simplexml_load_string($xml->saveXml(), 'SimpleXMLIterator');

			$this->assign("stateArr", $xml);
		} else if ($action == "reload") {
			echo "Reload info page";
			$params = array('key' => $key);
			var_dump($dataClass->handle("reloadhello", $params));
			die();
		}

		$this->assign('singleColumnLayout', true);
		// because we do not allow changing layout, controls will be hidden
		$this->assign('hideColumnControl', true);

		$routeParams = array('key' => $key, 'module' => null, 'subsection' => null);
		$this->assign('routeParams', $routeParams);
		$this->assign('activeAction', $action);
		$this->assign('stateSectionTitle', "Session info");
		return $this->getTwigArr();
	}

	/**
	 * Prepares section, module or subsection action data
	 *
	 * @Route("/sections/{key}/", name="section")
	 * @Route("/sections/{key}/{module}/", name="module")
	 * @Route("/sections/{key}/{module}/{subsection}/", name="subsection")
	 * @Template("FITNetopeerBundle:Default:section.html.twig")
	 *
	 * Prepares section = whole get&get-config part of server
	 * Shows module part = first level of connected server (except of root)
	 * Prepares subsection = second level of connected server tree
	 *
	 * @param int           $key          key of connected server
	 * @param null|string   $module       name of the module
	 * @param null|string   $subsection   name of the subsection
	 * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$dataClass = $this->get('DataModel');

		if ($this->getRequest()->getSession()->get('isLocking') !== true) {
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'title');
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'additionalTitle');
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'state');
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'leftColumn');
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'topPart');
			$this->assign('historyHref', $this->getRequest()->getRequestUri());
		}
		$this->getRequest()->getSession()->remove('isLocking');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'alerts');

		if ($this->getRequest()->getSession()->get('isAjax') === true) {
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'topMenu');
		}

		if ($dataClass->checkLoggedKeys() === 1) {
			$url = $this->get('request')->headers->get('referer');
			if (!strlen($url)) {
				$url = $this->generateUrl('_home');
			}
			return $this->redirect($url);
		}
		$this->setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $this->loadFilters($module, $subsection);
		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		// if form has been send, we well process it
		if ($this->getRequest()->getMethod() == 'POST') {
			$this->processSectionForms($key, $module, $subsection);
			// the code below wont be precess, because at the end of processSectionForms
			// is redirect executed
		}

		// we will prepare filter form in column
		$this->setSectionFilterForms();

		/* Show the first module we have */
		if ( $module == null ) {
			$retArr['key'] = $key;
			$routeName = 'module';
			$modules = $dataClass->getModels();
			if (count($modules)) {
				$module1st = array_shift($modules);
				if (!isset($module1st["params"]["module"])) {
					/* ERROR - wrong structure of model entry */
					$this->get('data_logger')
						->err("Cannot get first model (redirect to 1st tab).",
						array("message" => "\$module1st[\"params\"][\"module\"] is not set"));
				}
				$retArr['module'] = $module1st["params"]["module"];
				return $this->redirect($this->generateUrl($routeName, $retArr));
			} else {
				return $this->redirect($this->generateUrl("module", array('key' => $key, 'module' => 'All')));
			}
		}

		$activeNotifications = $this->getRequest()->getSession()->get('activeNotifications');
		if ( !isset($activeNotifications[$key]) || $activeNotifications[$key] !== true ) {
			$activeNotifications[$key] = true;
			$this->getRequest()->getSession()->set('activeNotifications', $activeNotifications);
			$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'notifications');
		}

		// if we have module, we are definitely in module or subsection action, so we could load names
		if ( $module ) {
			parent::setSubmenuUrl($module);
			$this->assign('sectionName', $dataClass->getSectionName($module));

			if ( $subsection ) {
				$this->assign('subsectionName', $dataClass->getSubsectionName($subsection));
			}

		// we are in section
		} else {
			$connArray = $this->getRequest()->getSession()->get('session-connections');
			if (isset($connArray[$key])) {
				$host = unserialize($connArray[$key]);
				$this->assign('sectionName', $host->host);
			} else {
				$this->getRequest()->getSession()->setFlash('state error', "You try to load device you are not connected to.");
				return $this->redirect($this->generateUrl("_home", array()));
			}

			// because we do not allow changing layout in section, controls will be hidden
			$this->assign('hideColumnControl', true);
		}

		// loading state part = get Action
		// we will load it every time, because state column will we show everytime
		try {
			$dataClass->setFlashState('state');

			if ( ($xml = $dataClass->handle('get', $this->paramsState)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
				$this->assign("stateArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("State: Could not parse filter correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('state error', "Could not parse filter correctly. ");
		}

		// we will load config part only if two column layout is enabled or we are on section (which has two column always)
		if ( $module == null || ($module != null && $this->get('session')->get('singleColumnLayout') != "true") ) {
			try {
				$dataClass->setFlashState('config');
				$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'config');
				// getcofig part
				if ( ($xml = $dataClass->handle('getconfig', $this->paramsConfig)) != 1 ) {
					$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
					$this->assign("configArr", $xml);
				}
			} catch (\ErrorException $e) {
				$this->get('data_logger')->err("Config: Could not parse XML file correctly.", array("message" => $e->getMessage()));
				$this->getRequest()->getSession()->setFlash('config error', "Could not parse XML file correctly. ");
			}

			$this->assign('singleColumnLayout', false);
		} else {
			$this->assign('singleColumnLayout', true);
		}

		$routeParams = array('key' => $key, 'module' => $module, 'subsection' => $subsection);
		$this->assign('routeParams', $routeParams);

		return $this->getTwigArr();
	}

	/**
	 * loading file with filter specification for current module or subsection
	 *
	 * @param  string $module     module name
	 * @param  string $subsection subsection name
	 * @return array              array with config and state filter
	 */
	private function loadFilters(&$module, &$subsection) {
		// if file filter.txt exists in models, we will use it
		$filterState = $filterConfig = "";

		$dataClass = $this->get('DataModel');
		$path = $dataClass->getPathToModels($module);

		// if subsection is defined, we will add it to path
		if ( $subsection ) {
			$path .= $subsection.'/';
		}

		$file = $path.'filter.txt';

		// if file with filter does not exist, only empty filter will be returned
		if ( file_exists($file) ) {
			$filterState = $filterConfig = file_get_contents($file);
		}

		return array(
			'config' => $filterConfig,
			'state' => $filterState
		);
	}

	/**
	 * Set values of state array
	 *
	 * @param mixed $key   key of associative array
	 * @param mixed $value value of associative array
	 */
	private function setStateParams($key, $value) {
		$this->paramsState[$key] = $value;
	}

	/**
	 * Set values of config array
	 *
	 * @param mixed $key   key of associative array
	 * @param mixed $value value of associative array
	 */
	private function setConfigParams($key, $value) {
		$this->paramsConfig[$key] = $value;
	}

	/**
	 * Set default values to config and state arrays
	 *
	 * @param int     $key          key of connected server
	 * @param string  $filterState  state filter
	 * @param string  $filterConfig config filter
	 * @param string  $sourceConfig source param of config
	 */
	private function setSectionFormsParams($key, $filterState = "", $filterConfig = "", $sourceConfig = "") {

		// if is set sourceConfig in session and we haven't set it in method call
		// we will use value from session
		if ( $this->get('session')->get('sourceConfig') != null && $sourceConfig == "") {
			$sourceConfig = $this->get('session')->get('sourceConfig');
		// else we will use default value running
		} else if ( $sourceConfig == "" ) {
			$sourceConfig = 'running';
		}

		$this->setStateParams('key', $key);
		$this->setStateParams('filter', $filterState);

		$this->setConfigParams('key', $key);
		$this->setConfigParams('source', $sourceConfig);
		$this->setConfigParams('filter', $filterState);
	}

	/**
	 * prepares filter forms for both sections
	 */
	private function setSectionFilterForms() {
		// state part
		$this->filterForms['state'] = $this->createFormBuilder()
			->add('formType', 'hidden', array(
				'data' => 'formState',
			))
			->add('filter', 'text', array(
				'label' => "Filter",
				'required' => false
			))
			->getForm();

		// config part
		$this->filterForms['config'] = $this->createFormBuilder()
			->add('formType', 'hidden', array(
				'data' => 'formConfig',
			))
			->add('filter', 'text', array(
				'label' => "Filter",
				'required' => false
			))
			->add('source', 'choice', array(
				'choices' => array(
					'running' => 'Running',
					'startup' => 'Start-up',
					'candidate' => 'Candidate',
				),
				'data' => $this->get('session')->get('sourceConfig')
			))
			->getForm();

		$this->assign('formState', $this->filterForms['state']->createView());
		$this->assign('formConfig', $this->filterForms['config']->createView());
	}

	/**
	 * process all kinds of form in section, module or subsection
	 *
	 * @param  int    $key                key of connected server
	 * @param  string $module = null      module name
	 * @param  string $subsection = null  subsection name
	 * @return RedirectResponse           redirect to page, which was this method called from
	 */
	private function processSectionForms($key, $module = null, $subsection = null) {
		$dataClass = $this->get('DataModel');
		$post_vals = $this->getRequest()->get("form");

		if ( !isset($this->filterForms['state']) || !isset($this->filterForms['config']) ) {
			$this->setSectionFilterForms();
		}

		// processing filter on state part
		if ( isset($post_vals['formType']) && $post_vals['formType'] == "formState") {
			$res = $this->handleFilterState($key);

		// processing filter on config part
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formConfig" ) {
			$res = $this->handleFilterConfig($key);

		// processing form on config - edit Config
		} elseif ( is_array($this->getRequest()->get('configDataForm')) ) {
			$res = $this->handleEditConfigForm($key);

		// processing duplicate node form
		} elseif ( is_array($this->getRequest()->get('duplicatedNodeForm')) ) {
			$res = $this->handleDuplicateNodeForm($key);

		// processing generate node form
		} elseif ( is_array($this->getRequest()->get('generateNodeForm')) ) {
			$res = $this->handleGenerateNodeForm($key, $moduel, $subsection);

		// processing new node form
		} elseif ( is_array($this->getRequest()->get('newNodeForm')) ) {
			$res = $this->handleNewNodeForm($key, $moduel, $subsection);

		// processing remove node form
		} elseif ( is_array($this->getRequest()->get('removeNodeForm')) ) {
			$res = $this->handleRemoveNodeForm($key);
		}

		// we will redirect page after completion, because we want to load edited get and get-config
		// and what's more, flash message lives exactly one redirect, so without redirect flash message
		// would stay on the next page, what we do not want...
		$retArr['key'] = $key;
		$routeName = 'section';
		if ( $module ) {
			$retArr['module'] = $module;
			$routeName = 'module';
		}
		if ( $subsection ) {
			$retArr['subsection'] = $subsection;
			$routeName = 'subsection';
		}

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->getRequest()->getSession()->set('isAjax', true);
		}
		return $this->redirect($this->generateUrl($routeName, $retArr));
	}

	/**
	 * divides string into the array (name, value) (according to the XML tree node => value)
	 *
	 * @param  string $postKey post value
	 * @return array           modified array
	 */
	private function divideInputName($postKey)
	{
		$values = explode('_', $postKey);
		$cnt = count($values);
		if ($cnt > 2) {
			$last = $values[$cnt-1];
			$values = array(implode("_", array_slice($values, 0, $cnt-1)), $last);
		}
		return $values;
	}

	/**
	 * decodes XPath value
	 *
	 * @param  string $value encoded XPath string
	 * @return string        decoded XPath string
	 */
	private function decodeXPath($value) {
		return str_replace(
			array('-', '?', '!'),
			array('/', '[', ']'),
			$value
		);
	}

	/**
	 * sends modified XML to server
	 *
	 * @param  int    $key    	session key of current connection
	 * @param  string $config 	XML document which will be send
	 * @param  string $target = "running" target source
	 * @return int						  return 0 on success, 1 on error
	 */
	private function executeEditConfig($key, $config, $target = "running") {
		$res = 0;
		$editConfigParams = array(
				'key' 	 => $key,
				'target' => $target,
				'config' => str_replace('<?xml version="1.0"?'.'>', '', $config)
				);

		// edit-cofig
		if ( ($merged = $this->get('DataModel')->handle('editconfig', $editConfigParams)) != 1 ) {
			// for debugging purposes, we will save result into the temp file
			if ($this->container->getParameter('kernel.environment') == 'dev') {
				file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/merged.yin', $merged);
			}
		} else {
			$this->get('logger')->err('Edit-config failed.', array('params', $editConfigParams));
			// throw new \ErrorException('Edit-config failed.');
			$res = 1;
		}
		return $res;
	}

	/**
	 * Completes request tree (XML) with necessary nodes (parent nodes).
	 * Tree must be valid for edit-config action
	 *
	 * @param \SimpleXMLElement  $tmpConfigXml
	 * @param string            $config_string
	 * @return \SimpleXMLElement
	 */
	private function completeRequestTree(&$tmpConfigXml, $config_string) {

		$subroot = simplexml_load_file($this->get('DataModel')->getPathToModels() . 'wrapped.wyin');
		$xmlNameSpaces = $subroot->getNamespaces();

		if ( isset($xmlNameSpaces[""]) ) {
			$subroot->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
		}
		$ns = $subroot->xpath("//xmlns:namespace");
		$namespace = "";
		if (sizeof($ns)>0) {
			$namespace = $ns[0]->attributes()->uri;
		}
		$pos_subroot = $subroot->xpath('//xmlns:'.$tmpConfigXml->getName().'/ancestor::*');
		$config = $config_string;
		for ($i=sizeof($pos_subroot)-1; $i>0; $i--) {
			//if ($pos_subroot[$i]->
			$config .= "</".$pos_subroot[$i]->getName().">\n";

			if ($i == 1) {
				$config = "<".$pos_subroot[$i]->getName().
					($namespace!==""?" xmlns=\"$namespace\"":"").
					" xmlns:xc=\"urn:ietf:params:xml:ns:netconf:base:1.0\"".
					">\n".$config;
			} else {
				$config = "<".$pos_subroot[$i]->getName().
					">\n".$config;
			}
		}
		$result = simplexml_load_string($config);
		$result->registerXPathNamespace('xmlns', $namespace);

		return $result;
	}

	/**
	 * updates (modifies) value of XML node
	 *
	 * @param  string $configXml   xml file
	 * @param  string $elementName name of the element
	 * @param  string $xpath       XPath to the element
	 * @param  string $val         new value
	 * @param  string $xPathPrefix
	 * @return \SimpleXMLElement   modified node
	 */
	private function elementValReplace(&$configXml, $elementName, $xpath, $val, $xPathPrefix = "xmlns:")
	{
		$isAttribute = false;

		// if element is an attribute, it will have prefix at-
		if ( strrpos($elementName, 'at-') === 0 ) {
			$elementName = substr($elementName, 3);
			$isAttribute = true;
		}

		// get node according to xPath query
		$node = $configXml->xpath('/'.$xPathPrefix.$xpath);

		if (isset($node[0])) {
			$node = $node[0];
		}
		
		// set new value for node
		if ( $isAttribute === true ) {
			$elem = $node[0];
			$elem[$elementName] = $val;
		} else {
			if (isset($node[0])) {
				$elem = $node[0];
			} else {
				$elem = $node;
			}

			if (isset($elem->$elementName) && (sizeof($elem->$elementName) > 0)) {
				$e = $elem->$elementName;
				$e[0] = str_replace("\r", '', $val); // removes \r from value
			} else {
				if ( !is_array($elem) ) {
					$elem[0] = str_replace("\r", '', $val);
				}
			}
		}

		return $elem;
	}

	/**
	 * sets new filter for state part
	 *
	 * @param  int $key   session key for current server
	 */
	private function handleFilterState(&$key) {
		$this->get('DataModel')->setFlashState('state');
		
		$this->filterForms['state']->bindRequest($this->getRequest());

		if ( $this->filterForms['state']->isValid() ) {
			$this->paramsState = array(
				"key" => $key,
				"filter" => $post_vals["filter"],
			);
		} else {
			$this->getRequest()->getSession()->setFlash('error', 'You have not filled up form correctly.');
		}
	}

	/**
	 * sets new filter for config part
	 *
	 * @param  int $key     session key for current server
	 */
	private function handleFilterConfig(&$key) {
		$this->get('DataModel')->setFlashState('config');

		$this->filterForms['config']->bindRequest($this->getRequest());

		if ( $this->filterForms['config']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->filterForms['config'] = array(
				"key" => $key,
				"filter" => $post_vals["filter"],
				"source" => $post_vals['source'],
			);
			$this->get('session')->set('sourceConfig', $post_vals['source']);
		} else {
			$this->getRequest()->getSession()->setFlash('error', 'You have not filled up form correctly.');
		}
	}

	/**
	 * handles edit config form - changes config values into the $_POST values
	 * and sends them to editConfig process
	 *
	 * @param  int  $key  session key of current connection
	 * @throws \ErrorException
	 * @return int        result code
	 */
	private function handleEditConfigForm(&$key) {
		$post_vals = $this->getRequest()->get('configDataForm');
		$res = 0;
		$this->get('DataModel')->setFlashState('config');

		try {

			if ( ($configXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$configXml = simplexml_load_string($configXml, 'SimpleXMLIterator');

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $configXml->asXml());
				}

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $configXml->getNamespaces();

				if ( isset($xmlNameSpaces[""]) ) {
					$configXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
					$xPathPrefix = "xmlns:";
				} else {
					// we will use this xmlns as backup for XPath request
					$configXml->registerXPathNamespace("xmlns", "urn:cesnet:tmc:hanicprobe:1.0");
					$xPathPrefix = "";
				}

				// foreach over all post values
				foreach ( $post_vals as $postKey => $val ) {
					$values = $this->divideInputName($postKey);
					$elementName = $values[0];
					$xpath = $this->decodeXPath($values[1]);
					$xpath = substr($xpath, 1); // removes slash at the begining

					$this->elementValReplace($configXml, $elementName, $xpath, $val, $xPathPrefix);
				}

				// for debugging, edited configXml will be saved into temp file
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/edited.yin', $configXml->asXml());
				}

				$res = $this->executeEditConfig($key, $configXml->asXml());
				if ($res !== 1) {
					$this->get('session')->setFlash('config success', "Config has been edited successfully.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->get('logger')->warn('Could not save config correctly.', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('config error', "Could not save config correctly. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * duplicates node in config - values of duplicated nodes (elements)
	 *
	 * could be changed by user
	 *
	 * @param  int  $key  session key of current connection
	 * @throws \ErrorException
	 * @return int        result code
	 */
	private function handleDuplicateNodeForm(&$key)	{
		$post_vals = $this->getRequest()->get('duplicatedNodeForm');
		$res = 0;
		$this->get('DataModel')->setFlashState('config');

		try {
			// load original (not modified) getconfig
			if ( ($originalXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $tmpConfigXml->asXml());
				}

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $tmpConfigXml->getNamespaces();
				if ( isset($xmlNameSpaces[""]) ) {
					$tmpConfigXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
				}
			}

			// if we have XML configuration
			if (isset($tmpConfigXml)) {

				// we will go through all posted values
				$newLeafs = array();

//				$tmpConfigXml = $this->completeRequestTree($tmpConfigXml, $tmpConfigXml->asXml());

				/* fill values */
				$i = 0;
				$createString = "";

				foreach ( $post_vals as $postKey => $val ) {
					$values = $this->divideInputName($postKey);
					// values[0] - label
					// values[1] - encoded xPath
					
					if ($postKey == "parent") {
						$xpath = $this->decodeXPath($val);
						// get node according to xPath query
						$parentNode = $tmpConfigXml->xpath($xpath);
					} else if ( count($values) != 2 ) {
						$this->get('logger')->err('newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $values, 'postKey' => $postKey));
						throw new \ErrorException("newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1! ". var_export(array('values' => $values, 'postKey' => $postKey), true));
					} else {
						$xpath = $this->decodeXPath($values[1]);
						$xpath = substr($xpath, 1, strripos($xpath, "/") - 1);

						$node = $this->elementValReplace($tmpConfigXml, $values[0], $xpath, $val);
						try {
							if ( is_object($node) ) {
								$node->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");
							}
						} catch (\ErrorException $e) {
							// nothing happened - attribute is already there
						}
					}
				}

				$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $parentNode[0]->asXml());
				$createTree = $this->completeRequestTree($parentNode[0], $createString);

				// for debugging, edited configXml will be saved into temp file
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/newElem.yin', $createTree->asXml());
				}
				$res = $this->executeEditConfig($key, $createTree->asXml());

				if ($res == 0) {
			    $this->getRequest()->getSession()->setFlash('config success', "Record has been added.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->get('logger')->warn('Could not save new node correctly.', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('config error', "Could not save new node correctly. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * create new node in config - according to the values in XML model
	 *
	 * could be changed by user
	 *
	 * @param  int      $key 				  session key of current connection
	 * @param  string   $module 		  module name
	 * @param  string   $subsection  	subsection name
	 * @return int                    result code
	 */
	private function handleGenerateNodeForm(&$key, &$module, &$subsection)	{
		$post_vals = $this->getRequest()->get('generatedNodeForm');
		$res = 0;
		$this->get('DataModel')->setFlashState('config');

		// TODO: load XML file - https://sauvignon.liberouter.org/symfony/generate/2/-%252A-%252A%253F1%2521-%252A%253F2%2521-%252A%253F1%2521/0/hanic-probe/exporters/model.xml
		// this URL should be generated from route (path = generateFromModel, params: '2' = level (whatever, not used in this case); 'xPath' = url_encode($xPath), 'key' = $key, 'module' = $module, 'subsection' = subsection, '_format' = 'xml')
		//
		// change values to $_POST ones if XML file has been loaded correctly
		// generate (completeTree) output XML for edit-config

		return $res;
	}

	/**
	 * create new node in config - according to the values in XML model
	 *
	 * could be changed by user
	 *
	 * @param  int      $key 				  session key of current connection
	 * @param  string   $module 		  module name
	 * @param  string   $subsection  	subsection name
	 * @return int                    result code
	 */
	private function handleNewNodeForm(&$key, &$module, &$subsection)	{
		$post_vals = $this->getRequest()->get('newNodeForm');
		$res = 0;
/*
		try {
			// load original (not modified) getconfig
			if ( ($originalXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $tmpConfigXml->asXml());
				}

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $tmpConfigXml->getNamespaces();
				if ( isset($xmlNameSpaces[""]) ) {
					$tmpConfigXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
				}
			}

			// if we have XML configuration
			if (isset($tmpConfigXml)) {

				// we will go through all posted values
				$newLeafs = array();

				// fill values
				$i = 0;
				$createString = "";

				foreach ( $post_vals as $postKey => $val ) {
					$values = $this->divideInputName($postKey);
					// values[0] - label
					// values[1] - encoded xPath

					if ($postKey == "parent") {
						$xpath = $this->decodeXPath($val);
						// get node according to xPath query
						$parentNode = $tmpConfigXml->xpath($xpath);
					} else if ( count($values) != 2 ) {
						$this->get('logger')->err('newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $values, 'postKey' => $postKey));
						throw new \ErrorException("newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1! ". var_export(array('values' => $values, 'postKey' => $postKey), true));
					} else {
						$xpath = $this->decodeXPath($values[1]);
						$xpath = substr($xpath, 1, strripos($xpath, "/") - 1);

						$node = $this->elementValReplace($tmpConfigXml, $values[0], $xpath, $val);
						try {
							if ( is_object($node) ) {
								$node->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");
							}
						} catch (\ErrorException $e) {
							// nothing happened - attribute is already there
						}
					}
				}

				$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $parentNode[0]->asXml());
				$createTree = $this->completeRequestTree($parentNode[0], $createString);

				$res = $this->executeEditConfig($key, $createTree->asXml());

				if ($res == 0) {
					$this->getRequest()->getSession()->setFlash('config success', "Record has been added.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->get('logger')->warn('Could not save new node correctly.', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('config error', "Could not save new node correctly. Error: ".$e->getMessage());
		}
*/
		return $res;
	}

	/**
	 * removes node from config XML tree
	 *
	 * @param  int  $key session key of current connection
	 * @throws \ErrorException  when get-config could not be loaded
	 * @return int       result code
	 */
	private function handleRemoveNodeForm(&$key) {
		$post_vals = $this->getRequest()->get('removeNodeForm');
		$res = 0;

		try {
			if ( ($originalXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $tmpConfigXml->asXml());
				}

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $tmpConfigXml->getNamespaces();
				if ( isset($xmlNameSpaces[""]) ) {
					$tmpConfigXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
				}

				$xpath = $this->decodeXPath($post_vals["parent"]);
				$toDelete = $tmpConfigXml->xpath($xpath);
				$deletestring = "";

				foreach ($toDelete as $td) {
					//$td->registerXPathNamespace("xc", "urn:ietf:params:xml:ns:netconf:base:1.0");
					$td->addAttribute("xc:operation", "remove", "urn:ietf:params:xml:ns:netconf:base:1.0");
					$deletestring .= "\n".str_replace('<?xml version="1.0"?'.'>', '', $td->asXml());
				}

				$deleteTree = $this->completeRequestTree($toDelete[0], $deletestring);

				// for debugging, edited configXml will be saved into temp file
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					file_put_contents($this->get('kernel')->getRootDir().'/logs/tmp-files/removeNode.yin', $tmpConfigXml->asXml());
				}
				$res = $this->executeEditConfig($key, $tmpConfigXml->asXml());
				if ($res == 0) {
					$this->getRequest()->getSession()->setFlash('config success', "Record has been removed.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}
		} catch (\ErrorException $e) {
			$this->get('logger')->warn('Could not remove node correctly.', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('config error', "Could not remove node correctly. ".$e->getMessage());
		}

		return $res;

	}	
}
