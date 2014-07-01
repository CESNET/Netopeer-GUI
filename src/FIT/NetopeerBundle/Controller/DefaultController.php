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
use FIT\NetopeerBundle\Models\Array2XML;
use FIT\NetopeerBundle\Entity\BaseConnection;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

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
	 * @var array $rpcs         holds XML for rpc methods
	 */
	private static $rpcs;

	/**
	 * @Route("/", name="_home")
	 *
	 * @return RedirectResponse
	 */
	public function indexAction() {
		$securityContext = $this->get('security.context');
		if( $securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED') ){
			// authenticated REMEMBERED, FULLY will imply REMEMBERED (NON anonymous)
			return $this->redirect($this->generateUrl('connections'));
		}
		return $this->redirect($this->generateUrl('_login'));
	}

	/**
	 * Prepares form for connection to the server and table with active connection list
	 *
	 * @Route("/connections/", name="connections")
	 * @Route("/connections/device-{connectedDeviceId}/", name="homeFromHistory")
	 * @Template()
	 *
	 * @param int $connectedDeviceId    id of connected device from history
	 * @return array
	 */
	public function connectionsAction($connectedDeviceId = NULL)
	{
		// DependencyInjection (DI) - defined in Resources/config/services.yml
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');

		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'alerts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'state');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'config');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'leftColumn');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'notifications');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topMenu');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topPart');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'javascripts');

		//TODO: delete only session from refferer
		$this->getRequest()->getSession()->set('activeNotifications', array());

		$host = "";
		$port = "830";
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
			$form->handleRequest($this->getRequest());

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
					),
				);

				// state flash = state -> left column in the layout
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
					$this->getRequest()->getSession()->getFlashBag()->add('state success', 'Form has been filled up correctly.');

					$baseConn = $this->get('BaseConnection');
					$baseConn->saveConnectionIntoDB($post_vals['host'], $post_vals['port'], $post_vals['user']);

				}
			} else {
				$this->getRequest()->getSession()->getFlashBag()->add('state error', 'You have not filled up form correctly.');
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
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');

		/* reload hello message */
		$params = array('key' => $key);
		if (($res = $dataClass->handle("reloadhello", $params) == 0)) {

		}

		$dataClass->updateLocalModels($key);
		$dataClass->invalidateMenuStructureForKey($key);

		//reconstructs a routing path and gets a routing array called $route_params
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'alerts');
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'leftColumn');
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'topMenu');
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'topPart');
			ob_clean();
			$this->getRequest()->getSession()->set('isAjax', true);
		}

		$url = $this->getRequest()->headers->get('referer');
		if ($url) {
			return new RedirectResponse($url);
		} else {
			return new RedirectResponse($this->generateUrl('section', array('key' => $key)));
		}
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
			$params['target'] = $this->getCurrentDatastoreForKey($key);
			$this->getRequest()->getSession()->set('isLocking', true);
		}

		$res = $dataClass->handle($command, $params, false);

		if ( $res != 1 ) {
			return $this->redirect($this->generateUrl('section', array('key' => $key)));
		}

		// if something goes wrong, we will redirect to connections page
		if ( in_array($command, array("connect", "disconnect", "getschema")) ) {
			return $this->redirect($this->generateUrl('connections'));
		} else {
			$url = $this->get('request')->headers->get('referer');
			return $this->redirect($url);
		}
	}

	/**
	 * Handle and execute backup of connection
	 *
	 * @Route("/backup/{key}", name="handleBackup")
	 *
	 * @param int     $key          key of connected device
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handleBackupAction($key)
	{
		$dataClass = $this->get('DataModel');
		$params = array(
			'key' => $key,
			'filter' => '',
		);

		$res = $dataClass->handle('backup', $params, false);
		$resp = new Response();
		$resp->setStatusCode(200);
		$resp->headers->set('Cache-Control', 'private');
		$resp->headers->set('Content-Length', strlen($res));
		$resp->headers->set('Content-Type', 'application/force-download');
		$resp->headers->set('Content-Disposition', sprintf('attachment; filename="%s-%s.xml"', date("Y-m-d"), $dataClass->getHostFromKey($key)));
		$resp->sendHeaders();
		$resp->setContent($res);
		$resp->sendContent();
		die();
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
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');
		parent::setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'singleContent');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'alerts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'topMenu');

		if ( $action == "session" ) {
			$session = $this->container->get('request')->getSession();
			$sessionArr = $session->all();

			// format session info output (unserialize object, convert JSON into array)
			if (isset($sessionArr['session-connections'])) {
				$connVarsArr = array();
				foreach ($sessionArr['session-connections'] as $connKey => $conn) {
					$connVarsArr['connection-'.$connKey][$connKey] = (array) unserialize($conn);
					if ($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']) {
						$connVarsArr['connection-'.$connKey][$connKey]['sessionStatus'] = (array) json_decode($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']);
						if (isset($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities'])) {
							$connVarsArr['connection-'.$connKey][$connKey]['capabilities'] = implode("\n", $connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities']);
							unset($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities']);
						}
					}
					
					$connVarsArr['connection-'.$connKey][$connKey]['nc_features'] = $dataClass->getCapabilitiesArrForKey($connKey);
				}
				$sessionArr['session-connections'] = $connVarsArr;
			}

			unset($sessionArr['_security_secured_area']);

			$xml = Array2XML::createXML("session", $sessionArr);
			$xml = simplexml_load_string($xml->saveXml(), 'SimpleXMLIterator');

			$this->assign("stateArr", $xml);
			$this->assign('hideStateSubmitButton', true);
		} else if ($action == "reload") {
			$params = array('key' => $key);
			$dataClass->handle("reloadhello", $params);
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
	  Create array (with subarrays) of input elements of RPC method
	*/
	private function getRPCinputAttributesAndChildren(\SimpleXMLElement $root_elem, \SimpleXMLElement &$xmlEl) {
                $attr = $root_elem->attributes();
                $inputName = (string) $root_elem->getName();
                $child = $xmlEl->addChild($inputName);
                foreach ($attr as $n => $a) {
                        $child->addAttribute($n, $a);
                }
	}

	/**
	 * @param $module
	 * @param $subsection
	 *
	 * @return array
	 */
	private function createRPCListFromModel($module, $subsection = "")
	{
		if (!empty($this::$rpcs)) return $this::$rpcs;
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');
		$rpcs = $dataClass->loadRPCsModel($module, $subsection);
		$rpcxml = simplexml_load_string($rpcs["rpcs"]);
		$rpcs = array();
		if ($rpcxml) {
			foreach ($rpcxml as $rpc) {
				$name = (string) $rpc->attributes()->name;
				if (isset($rpcs[$name])) {
					continue;
				}

				$xmlEl = new SimpleXMLElement("<".$name."></".$name.">");
				$xmlEl->addAttribute('description', $rpc->description->text);

				$ns = $rpc->getNamespaces();
				$xmlEl->addAttribute('namespace', !empty($ns) ? array_pop($ns) : 'false');

				if ($rpc->input) {
					foreach ($rpc->input->children() as $leafs) {
						$this->getRPCinputAttributesAndChildren($leafs, $xmlEl);
					}
				}
				$rpcs[$name] = $xmlEl;
			}
		}
		$this::$rpcs = $rpcs;
		return $rpcs;
	}

	private function getRPCXmlForMethod($rpcMethod, $module, $subsection = "") {
		$rpcs = $this->createRPCListFromModel($module, $subsection);
		return isset($rpcs[$rpcMethod]) ? $rpcs[$rpcMethod] : false;
	}

	/**
	 * Prepares section, module or subsection action data
	 *
	 * @Route("/sections/{key}/", name="section")
	 * @Route("/sections/{key}/{module}/", name="module", requirements={"key" = "\d+"})
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
				$url = $this->generateUrl('connections');
			}
			return $this->redirect($url);
		}
		$this->setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $dataClass->loadFilters($module, $subsection);

		$this->assign('rpcMethods', $this->createRPCListFromModel($module, $subsection));

		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		// if form has been send, we well process it
		if ($this->getRequest()->getMethod() == 'POST') {
			$this->processSectionForms($key, $module, $subsection);
			// the code below wont be precess, because at the end of processSectionForms
			// is redirect executed
		}

		// we will prepare filter form in column
		$this->setSectionFilterForms($key);

		// path for creating node typeahead
		$valuesTypeaheadPath = $this->generateUrl("getValuesForLabel", array('formId' => "FORMID", 'key' => $key, 'xPath' => "XPATH"));

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

			$valuesTypeaheadPath = $this->generateUrl("getValuesForLabelWithModule", array('formId' => "FORMID", 'key' => $key, 'module' => $module, 'xPath' => "XPATH"));

		// we are in section
		} else {
			$connArray = $this->getRequest()->getSession()->get('session-connections');
			if (isset($connArray[$key])) {
				$host = unserialize($connArray[$key]);
				$this->assign('sectionName', $host->host);
			} else {
				$this->getRequest()->getSession()->getFlashBag()->add('state error', "You try to load device you are not connected to.");
				return $this->redirect($this->generateUrl("connections", array()));
			}

			// because we do not allow changing layout in section, controls will be hidden
			$this->assign('hideColumnControl', true);

			$valuesTypeaheadPath = $this->generateUrl("getValuesForLabelWithModule", array('formId' => "FORMID", 'key' => $key, 'module' => $module, 'subsection' => $subsection, 'xPath' => "XPATH"));
		}

		$routeParams = array('key' => $key, 'module' => $module, 'subsection' => $subsection);
		$this->assign('routeParams', $routeParams);
		$this->assign('valuesTypeaheadPath', $valuesTypeaheadPath);

		// loading state part = get Action
		// we will load it every time, because state column will we show everytime
		try {

			if ($module === 'all') {
				$merge = false;
			} else {
				$merge = true;
			}
			$isEmptyModule = false;

			if ( ($xml = $dataClass->handle('get', $this->getStateParams(), $merge)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');

				// we have only root module
				if ($xml->count() == 0) {
					$isEmptyModule = true;
					$this->assign('isEmptyModule', $isEmptyModule);
				} else {
					$this->assign('showRootElem', true);
				}

				$this->assign("stateArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("State: Could not parse filter correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->getFlashBag()->add('state error', "Could not parse filter correctly. ");
		}

		// load model tree dump
		$modelTree = $dataClass->getModelTreeDump($module);
		if ($modelTree) {
			$this->assign('modelTreeDump', $modelTree);
		}

		// we will load config part only if two column layout is enabled or we are on section (which has two column always)
		$tmp = $this->getConfigParams();
		if ($module == null || ($module != null && $tmp['source'] !== "running" && !$isEmptyModule)) {
			$this->loadConfigArr(false, $merge);
			$this->setOnlyConfigSection();
		} else if ( $module == null || $module == 'all' || ($module != null && $this->get('session')->get('singleColumnLayout') != "true") ) {
			$this->loadConfigArr(true, $merge);
			$this->assign('singleColumnLayout', false);
			if ($module == 'all') {
				$this->assign('hideColumnControl', true);
			}
		} else if ($this->get('session')->get('singleColumnLayout') != "true") {
			$this->loadConfigArr(false, $merge);
			$this->assign('singleColumnLayout', true);
			$this->setOnlyConfigSection();
		} else {
			$conn = $dataClass->getConnFromKey($key);
			if ($conn->getCurrentDatastore() !== "running") {
				$this->loadConfigArr(false, $merge);
				$this->setOnlyConfigSection();
			}
			$this->assign('singleColumnLayout', true);
		}

		return $this->getTwigArr();
	}

	/**
	 * @Route("/sections/rpc/{key}/{module}/{rpcName}/", name="showRPCForm", requirements={"key" = "\d+"})
	 * @Template("FITNetopeerBundle:Default:showRPCForm.html.twig")
	 *
	 * @param $key
	 * @param $module
	 * @param $rpcName
	 *
	 * @return array|Response
	 */
	public function showRPCFormAction($key, $module, $rpcName) {
		$this->getRequest()->getSession()->getFlashBag()->clear();

		$this->addAjaxBlock('FITNetopeerBundle:Default:showRPCForm.html.twig', 'modalWindow');
		$this->assign('key', $key);
		$this->assign('module', $module);
		$this->assign('rpcName', $rpcName);
		// path for creating node typeahead
		$valuesTypeaheadPath = $this->generateUrl("getValuesForLabel", array('formId' => "FORMID", 'key' => $key, 'xPath' => "XPATH"));
		$this->assign('valuesTypeaheadPath', $valuesTypeaheadPath);

		if ($this->getRequest()->getMethod() == 'POST') {
			$xmlOperations = $this->get("XMLoperations");
			$postVals = $this->getRequest()->get("configDataForm");
			$this->setSectionFormsParams($key);

			$result = "";
			$res = $xmlOperations->handleRPCMethodForm($key, $this->getConfigParams(), $postVals);
			$url = $this->get('request')->headers->get('referer');
			return new RedirectResponse($url);
		}

		$this->assign('rpcArr', $this->getRPCXmlForMethod($rpcName, $module));

		return $this->getTwigArr();
	}

	/**
	 * @Route("/sections/create-empty-module/{key}/", name="createEmptyModule")
	 * @Template("FITNetopeerBundle:Default:createEmptyModule.html.twig")
	 *
	 * @param $key
	 *
	 * @return array|Response
	 */
	public function createEmptyModuleAction($key) {
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'state');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'alerts');
		$this->assign('historyHref', $this->getRequest()->getRequestUri());
		$this->assign('key', $key);

		if ($this->getRequest()->getMethod() == 'POST') {
			$xmlOperations = $this->get("XMLoperations");
			$postVals = $this->getRequest()->get("form");
			$this->setSectionFormsParams($key);

			$res = $xmlOperations->handleCreateEmptyModuleForm($key, $this->getConfigParams(), $postVals);
			if ($res != 0) {
				$this->forward('reloadDeviceAction', array('key' => $key));
			}

			if (isset($postVals['redirectUrl'])) {
				return $this->redirect($postVals['redirectUrl']);
			}
		}

		$this->setEmptyModuleForm($key);
		$this->assign('sectionName', 'Empty datastore');

		return $this->getTwigArr();
	}

	/**
	 * prepares form for empty module (root element) insertion
	 *
	 * @param $key
	 */
	private function setEmptyModuleForm($key) {
		$dataClass = $this->get("DataModel");
		$tmpArr = $dataClass->getModuleIdentifiersForCurrentDevice($key);

		// use small hack when appending space at the end of key, which will fire all options in typeahead
		if (!empty($tmpArr)) {
			foreach ($tmpArr as $key => $item) {
				$tmpArr[$key . " "] = $item;
			}
		}

		$form = $this->createFormBuilder()
				->add('name', 'text', array(
								'label' => "Module name"
						))
				->add('namespace', 'text', array(
								'label' => "Namespace",
								'attr' => array(
										'class' => 'typeaheadNS',
										'data-provide' => 'typeahead',
										'data-source' => !empty($tmpArr) ? json_encode(array_keys($tmpArr)) : '',
								    'data-min-length' => 0
								)
						))
				->getForm();

		$this->assign('emptyModuleForm', $form->createView());
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

	public function getStateParams() {
		return $this->paramsState;
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

	public function getConfigParams() {
		return $this->paramsConfig;
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
		/**
		 * @var $dataClass \FIT\NetopeerBundle\Models\Data
		 */
		$dataClass = $this->get('DataModel');
		$conn = $dataClass->getConnFromKey($key);

		if ($conn) {
			if ($sourceConfig !== "") {
				$conn->setCurrentDatastore($sourceConfig);
			}
			$this->setConfigParams('source', $conn->getCurrentDatastore());
		}

		$this->setStateParams('key', $key);
		$this->setStateParams('filter', $filterState);

		$this->setConfigParams('key', $key);
		$this->setConfigParams('filter', $filterConfig);
	}

	/**
	 * prepares filter forms for both sections
	 *
	 * @param int     $key          key of connected server
	 */
	private function setSectionFilterForms($key) {
		/* prepareGlobalTwigVariables is needed to init nc_feature
		array with available datastores... */
		$this->prepareGlobalTwigVariables();
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
		$datastores = array('running' => 'Running');
		$twigarr = $this->getAssignedVariablesArr();
		$ncFeatures = $twigarr["ncFeatures"];
		if (isset($ncFeatures["nc_feature_startup"])) {
				$datastores['startup'] = 'Start-up';
		}
		if (isset($ncFeatures["nc_feature_candidate"])) {
				$datastores['candidate'] = 'Candidate';
		}
		$this->filterForms['config'] = $this->createFormBuilder()
			->add('formType', 'hidden', array(
				'data' => 'formConfig',
			))
//			->add('filter', 'text', array(
//				'label' => "Filter",
//				'required' => false
//			))
			->add('source', 'choice', array(
				'label' => "Source:",
				'choices' => $datastores,
				'data' => $this->getCurrentDatastoreForKey($key)
			))
			->getForm();

		$targets = $datastores;
		$current_source = $this->getCurrentDatastoreForKey($key);
		if ($current_source !== null && $current_source !== "") {
			unset($targets[$current_source]);
		} else {
			unset($targets["running"]);
		}
		$this->filterForms['copyConfig'] = $this->createFormBuilder()
			->add('formType', 'hidden', array(
				'data' => 'formCopyConfig',
			))
			->add('target', 'choice', array(
				'label' => "copy to:",
				'choices' => $targets,
			))
			->getForm();

		$this->assign("dataStore", $this->getCurrentDatastoreForKey($key));
		$this->assign('formState', $this->filterForms['state']->createView());
		$this->assign('formConfig', $this->filterForms['config']->createView());
		$this->assign('formCopyConfig', $this->filterForms['copyConfig']->createView());
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
			$this->setSectionFilterForms($key);
		}

		// processing filter on state part
		if ( isset($post_vals['formType']) && $post_vals['formType'] == "formState") {
			$res = $this->handleFilterState($key);

		// processing filter on config part
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formConfig" ) {
			$res = $this->handleFilterConfig($key);

		// processing form on config - edit Config
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formCopyConfig" ) {
			$res = $this->handleCopyConfig($key);

		// processing form on config - edit Config
		} elseif ( is_array($this->getRequest()->get('configDataForm')) ) {
			$res = $this->get('XMLoperations')->handleEditConfigForm($key, $this->getConfigParams());

		// processing duplicate node form
		} elseif ( is_array($this->getRequest()->get('duplicatedNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleDuplicateNodeForm($key, $this->getConfigParams());

		// processing generate node form
		} elseif ( is_array($this->getRequest()->get('generateNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleGenerateNodeForm($key, $this->getConfigParams(), $module, $subsection);

		// processing new node form
		} elseif ( is_array($this->getRequest()->get('newNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleNewNodeForm($key, $this->getConfigParams());

		// processing remove node form
		} elseif ( is_array($this->getRequest()->get('removeNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleRemoveNodeForm($key, $this->getConfigParams());
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
	 * sets new filter for state part
	 *
	 * @param  int $key   session key for current server
	 * @return int 1 on error, 0 on success
	 */
	private function handleFilterState(&$key) {

		$this->filterForms['state']->bind($this->getRequest());

		if ( $this->filterForms['state']->isValid() ) {
			$this->setStateParams("key", $key);
			$this->setStateParams("filter", $post_vals["filter"]);
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'You have not filled up form correctly.');
			return 1;
		}
	}

	/**
	 * sets new filter for config part
	 *
	 * @param  int $key     session key for current server
	 * @return int 1 on error, 0 on success
	 */
	private function handleFilterConfig(&$key) {

		$this->filterForms['config']->bind($this->getRequest());

		if ( $this->filterForms['config']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->setConfigParams("key", $key);
//			$this->setConfigParams("filter", $post_vals["filter"]);

			/**
			 * @var $dataClass \FIT\NetopeerBundle\Models\Data
			 */
			$dataClass = $this->get('DataModel');
			$conn = $dataClass->getConnFromKey($key);
			$conn->setCurrentDatastore($post_vals['source']);
			$dataClass->persistConnectionForKey($key, $conn);

			$this->setConfigParams("source", $post_vals['source']);

			if ($post_vals['source'] !== 'running') {
				$this->setOnlyConfigSection();
			}
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'You have not filled up form correctly.');
			return  1;
		}
	}

	/**
	 * Execute copy-config
	 *
	 * @param  int $key     session key for current server
	 * @return int 1 on error, 0 on success
	 */
	private function handleCopyConfig(&$key) {
		$dataClass = $this->get('DataModel');

		$this->filterForms['copyConfig']->bind($this->getRequest());

		if ( $this->filterForms['copyConfig']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->setConfigParams("key", $key);
			$source = $this->getCurrentDatastoreForKey($key);
			if ($source === null) {
				$source = 'running';
			}
			$target = $post_vals['target'];
			$params = array('key' => $key, 'source' => $source, 'target' => $target);
			$dataClass->handle('copyconfig', $params, false);
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'You have not filled up form correctly.');
			return  1;
		}
	}

	/**
	 * sets all necessary steps for display config part in single column mode
	 */
	private function setOnlyConfigSection() {
		$this->get('session')->set('singleColumnLayout', false);
		$this->assign('singleColumnLayout', false);
		$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'state');
		$this->assign('hideColumnControl', true);
		$this->assign('showConfigFilter', true);

		$template = $this->get('twig')->loadTemplate('FITNetopeerBundle:Default:section.html.twig');
		$html = $template->renderBlock('config', $this->getAssignedVariablesArr());
		$this->assign('configSingleContent', $html);

		$this->get('session')->set('singleColumnLayout', true);
		$this->assign('singleColumnLayout', true);
	}

	/**
	 * loads array of config values
	 */
	private function loadConfigArr($addConfigSection = true, $merge = true) {
		try {
			$dataClass = $this->get('dataModel');
			if ($addConfigSection) {
				$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'config');
			}

			// getcofig part
			if ( ($xml = $dataClass->handle('getconfig', $this->getConfigParams(), $merge)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');

				// we have only root module
				if ($xml->count() == 0 && $xml->getName() == 'root') {
					$this->setEmptyModuleForm($this->getRequest()->get('key'));
					$this->assign('key', $this->getRequest()->get('key'));
					$this->assign('additionalTitle', 'Create empty root element');
					$this->assign('redirectUrl', $this->getRequest()->getRequestUri());

					$this->assign('isEmptyModule', false);
					$this->assign('showRootElem', false);
					$template = $this->get('twig')->loadTemplate('FITNetopeerBundle:Default:createEmptyModule.html.twig');
					$html = $template->renderBlock('singleContent', $this->getAssignedVariablesArr());

					$this->assign('additionalForm', $html);
				} elseif ($xml->count() == 0) {
					$this->assign('isEmptyModule', true);
					$this->assign('showRootElem', false);
				} else {
					$this->assign('isEmptyModule', false);
					$this->assign('showRootElem', true);
				}

				$this->assign("configArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("Config: Could not parse XML file correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->getFlashBag()->add('config error', "Could not parse XML file correctly. ");
		}
	}

	private function getCurrentDatastoreForKey($key) {
		/**
		 * @var $dataClass \FIT\NetopeerBundle\Models\Data
		 */
		$dataClass = $this->get('DataModel');
		$conn = $dataClass->getConnFromKey($key);

		if ($conn) {
			return $conn->getCurrentDatastore();
		} else {
			return false;
		}

	}
}

