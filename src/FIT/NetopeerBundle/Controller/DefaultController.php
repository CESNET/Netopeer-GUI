<?php
/**
 * Default controller for all pages directly visible from  webGUI.
 *
 * @file ModuleController.php
 * @author David Alexa <alexa.david@me.com>
 * @author Tomas Cejka <cejkat@cesnet.cz>
 *
 * Copyright (C) 2012-2015 CESNET
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


use FIT\NetopeerBundle\Services\Functionality\NetconfFunctionality;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Default controller for all base pages except of module configuration.
 */
class DefaultController extends BaseController
{

	/**
	 * Shows index page = connections page if logged, login page if not
	 *
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
		/**
		 * @var Session $session
		 */
		$session = $this->getRequest()->getSession();
		$singleInstance = $this->container->getParameter('fit_netopeer.single_instance');

		/**
		 * @var NetconfFunctionality
		 */
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'alerts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'state');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'config');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'leftColumn');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'notifications');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topMenu');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'javascripts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'moduleJavascripts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'moduleStylesheet');

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
		$form = $this->createFormBuilder(null, array('csrf_protection' => false))
			->add('host', 'text', array('attr' => array('value' => $host)))
			->add('port', 'number', array('attr' => array('value' => $port)))
			->add('user', 'text', array('attr' => array('value' => $userName)))
			->add('password', 'password', array('required' => false))
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
				$res = $netconfFunc->handle("connect", $params, false, $result);

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

					$this->get('session')->set('getSchemaWithAjax', $arr);
					$session->getFlashBag()->add('state success', 'Form has been filled up correctly.');

					if (!$singleInstance) {
						$baseConn = $this->get('BaseConnection');
						$baseConn->saveConnectionIntoDB($post_vals['host'], $post_vals['port'], $post_vals['user']);
					} else {
						// update models
						$netconfFunc->updateLocalModels($result);
						setcookie("singleInstanceLoginFailed", false);
						return $this->redirect($this->generateUrl('handleConnection', array('command' => 'get', 'key' => $result)));
					}
				} elseif ($singleInstance) {
					setcookie("singleInstanceLoginFailed", true, time() + 60);
					return $this->redirect($this->generateUrl('_logout'));
				}
			} else {
				$session->getFlashBag()->add('state error', 'Connection - you have not filled up form correctly.');
				if ($singleInstance) {
					setcookie("singleInstanceLoginFailed", true, time() + 60);
					return $this->redirect($this->generateUrl('_logout'));
				}
			}
			$url = $this->get('request')->headers->get('referer');
			//if (!$this->getRequest()->isXmlHttpRequest()) {
				return $this->redirect($url);
			//}
		} elseif ($singleInstance) {
			// auto redirect on configure device action for singleInstance user.
			// We don't want to have this route available at all for these users.
			return $this->redirect($this->generateUrl('handleConnection', array('command' => 'get', 'key' => 0)));
		}
		$connArray = $this->getRequest()->getSession()->get('session-connections');
		$connections = array();
		if (sizeof($connArray) > 0) {
			foreach ($connArray as $key => $c) {
				$connections[$key] = unserialize($c);
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
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		/* reload hello message */
		$params = array('sessions' => array($key));
		if (($res = $netconfFunc->handle("reloadhello", $params) == 0)) {
		}

		$connectionFunc->invalidateAndRebuildMenuStructureForKey($key);

		//reconstructs a routing path and gets a routing array called $route_params
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'alerts');
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'leftColumn');
			$this->addAjaxBlock('FITNetopeerBundle:Default:index.html.twig', 'topMenu');
			ob_clean();
			$this->getRequest()->getSession()->set('isAjax', true);
		}

		$url = $this->getRequest()->headers->get('referer');
		if ($url) {
			return $this->redirect($url);
		} else {
			return $this->redirect($this->generateUrl('section', array('key' => $key)));
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
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		$params = array(
			'connIds' => array($key)
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
		$res = $netconfFunc->handle($command, $params, false);

		if ( $res != 1 && !in_array($command, array("connect", "disconnect"))) {
			return $this->redirect($this->generateUrl('section', array('key' => $key)));
		}

		// if something goes wrong, we will redirect to connections page
		if ( in_array($command, array("get", "connect", "disconnect", "getschema")) ) {
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
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$params = array(
			'connIds' => array($key)
		);

		$res = $netconfFunc->handle('get', $params, false);
		$resp = new Response();
		$resp->setStatusCode(200);
		$resp->headers->set('Cache-Control', 'private');
		$resp->headers->set('Content-Length', strlen($res));
		$resp->headers->set('Content-Type', 'application/force-download');
		$resp->headers->set('Content-Disposition', sprintf('attachment; filename="%s-%s.json"', date("Y-m-d"), $connectionFunc->getHostFromKey($key)));
		$resp->sendHeaders();
		$resp->setContent($res);
		$resp->sendContent();
		die();
	}

	/**
	 * Shows info page with information
	 *
	 * @Route("/info-page/{key}/{action}/", name="infoPage")
	 * @Template("FITModuleDefaultBundle:Module:section.html.twig")
	 *
	 * @param int     $key          key of connected server
	 * @param string  $action       name of the action ("session"|"reload")
	 * @return array
	 */
	public function sessionInfoAction($key, $action)
	{
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		$this->setActiveSectionKey($key);
		$connectionFunc->buildMenuStructure($key);

//		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'moduleJavascripts');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'moduleStylesheet');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'title');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'additionalTitle');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'singleContent');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'alerts');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'topMenu');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'leftColumn');

		// TODO

		if ( $action == "session" ) {
			/**
			 * @var Session $session
			 */
			$session = $this->container->get('request')->getSession();
			$sessionArr = $session->all();

			// format session info output (unserialize object, convert JSON into array)
			if (isset($sessionArr['session-connections'])) {
				$connVarsArr = array();
				foreach ($sessionArr['session-connections'] as $connKey => $conn) {
					if ($connKey != $key) continue;

					$tmp = unserialize($conn);


					$unserialized = (array) unserialize($conn);
					foreach ($unserialized as $key => $value) {
						if (strrpos($key, 'activeController')) {
							$tmpArr = array();
							foreach ($value as $k => $v) {
								$tmpArr[str_replace(":", "_", $k)] = $v;
							}
							$unserialized['activeController'] = $tmpArr;
							unset($unserialized[$key]);
						}
					}
					$connVarsArr['connection-'.$connKey][$connKey] = $unserialized;
					if ($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']) {
						$connVarsArr['connection-'.$connKey][$connKey]['sessionStatus'] = (array) json_decode($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']);
						if (isset($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities'])) {
							$connVarsArr['connection-'.$connKey][$connKey]['capabilities'] = implode("\n", $connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities']);
							unset($connVarsArr['connection-'.$connKey][$connKey]['sessionStatus']['capabilities']);
						}
					}

					$connVarsArr['connection-'.$connKey][$connKey]['nc_features'] = $connectionFunc->getCapabilitiesArrForKey($connKey);
				}
				$sessionArr['session-connections'] = $connVarsArr;
			}

			unset($sessionArr['_security_secured_area']);
			unset($sessionArr['_security_commont_context']);

			if ($this->getRequest()->get('angular') == "true") {
				return new JsonResponse($sessionArr);
			}

			$this->assign('jsonEditable', false);
			$this->assign("stateJson", json_encode($sessionArr));
			$this->assign('hideStateSubmitButton', true);
		} else if ($action == "reload") {
			$params = array('key' => $key);
			$netconfFunc->handle("reloadhello", $params);
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
	 * Gets XML file with requested RPC method only.
	 *
	 * @param        $rpcMethod
	 * @param        $module
	 * @param string $subsection
	 *
	 * @return bool
	 */
	private function getRPCXmlForMethod($rpcMethod, $key, $module) {
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		$rpc = $module.':'.$rpcMethod;
		$json = $netconfFunc->handle('query', array('connIds' => array($key), 'load_children' => true, 'filters' => array('/'.$rpc)));
		return $json;
	}

	/**
	 * Prepares view for RPC form (generates RPC form)
	 *
	 * @Route("/sections/rpc/{key}/{module}/{rpcName}/", name="showRPCForm", requirements={"key" = "\d+"})
	 * @Template("FITModuleDefaultBundle:Module:showRPCForm.html.twig")
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $module
	 * @param $rpcName
	 *
	 * @return array|Response
	 */
	public function showRPCFormAction($key, $module, $rpcName) {
		/**
		 * @var Session $session
		 */
		$session = $this->getRequest()->getSession();
		$session->getFlashBag()->clear();

		$this->addAjaxBlock('FITModuleDefaultBundle:Module:showRPCForm.html.twig', 'modalWindow');
		$this->assign('key', $key);
		$this->assign('module', $module);
		$this->assign('rpcName', $rpcName);

		if ($this->getRequest()->getMethod() == 'POST') {
			$xmlOperations = $this->get("XMLoperations");
			$postVals = $this->getRequest()->get("configDataForm");
			$this->setSectionFormsParams($key);

			$xmlOperations->handleRPCMethodForm($key, $this->getConfigParams(), $postVals);
			$url = $this->get('request')->headers->get('referer');
			return new RedirectResponse($url);
		}

		$this->assign('rpcData', $this->getRPCXmlForMethod($rpcName, $key, $module));

		return $this->getTwigArr();
	}

	/**
	 * Action for empty module creation.
	 *
	 * @Route("/sections/create-empty-module/{key}/", name="createEmptyModule")
	 * @Template("FITNetopeerBundle:Default:createEmptyModule.html.twig")
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 *
	 * @return array|Response
	 */
	public function createEmptyModuleAction($key) {
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'title');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'state');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'alerts');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'topMenu');
		$this->addAjaxBlock('FITNetopeerBundle:Default:createEmptyModule.html.twig', 'leftColumn');
		$this->assign('historyHref', $this->getRequest()->getRequestUri());
		$this->assign('key', $key);

		if ($this->getRequest()->getMethod() == 'POST') {
			$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
			$arr = [];
			$formData = $this->getRequest()->get('form');
			$moduleName = $formData['modulePrefix'] . ':' . $formData['moduleName'];
			$json = '{"'.$moduleName.'":{},"@'.$moduleName.'":{"ietf-netconf:operation":"create"}}';
//			var_dump($this->getStateParams());exit;
			$params = array(
				'connIds' => array($key),
				'target' => 'running',
				'configs' => array($json)
			);
			$res = $netconfFunc->handle('editconfig', $params);
			if ($res != 0) {
				return $this->forward('FITNetopeerBundle:Default:reloadDevice', array('key' => $key));
			}

			if (isset($postVals['redirectUrl'])) {
				return $this->redirect($postVals['redirectUrl']);
			}
		}

		$this->setEmptyModuleForm($key);
		$this->assign('sectionName', 'Empty datastore');
		$this->assign('emptyModuleTitle', 'Create empty module');

		return $this->getTwigArr();
	}
}

