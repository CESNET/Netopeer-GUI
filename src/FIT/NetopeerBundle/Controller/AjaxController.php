<?php
/**
 * File, which handles all Ajax actions.
 *
 * @file AjaxController.php
 * @author David Alexa <alexa.david@me.com>
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
use FIT\NetopeerBundle\Models\AjaxSharedData;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller which handles all Ajax actions
 */
class AjaxController extends BaseController
{
	/**
	 * Change session value for showing single or double column layout
	 *
	 * @Route("/ajax/get-schema/{key}", name="getSchema")
	 *
	 * @param  int      $key 				  session key of current connection
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getSchemaAction($key)
	{
		$schemaData = AjaxSharedData::getInstance();
		
		ob_start();
		$data = $schemaData->getDataForKey($key);
		if (!(isset($data['isInProgress']) && $data['isInProgress'] === true)) {
			$this->updateLocalModels($key);
		}
		$output = ob_get_clean();
		$result['output'] = $output;

		return $this->getSchemaStatusAction($key, $result);
	}

	/**
	 * Get status of get-schema operation
	 *
	 * @Route("/ajax/get-schema-status/{key}", name="getSchemaStatus")
	 *
	 * @param  int      $key 				  session key of current connection
	 * @param  array    $result
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getSchemaStatusAction($key, $result = array())
	{
		$schemaData = AjaxSharedData::getInstance();
		
		$data = $schemaData->getDataForKey($key);
		if (isset($data['isInProgress']) && $data['isInProgress'] === true) {
			$schemaData->setDataForKey($key, 'status', "in progress");
		}

		$data = $schemaData->getDataForKey($key);
		$result['status'] = $data['status'];
		$result['message'] = $data['message'];
		$result['key'] = $key;

		return new Response(json_encode($result));
	}

	/**
	 * Get history of connected devices
	 *
	 * @Route("/ajax/get-connected-devices/", name="historyOfConnectedDevices")
	 * @Template()
	 *
	 * @return array    $result
	 */
	public function historyOfConnectedDevicesAction()
	{
		$this->addAjaxBlock("FITNetopeerBundle:Ajax:historyOfConnectedDevices.html.twig", "historyOfConnectedDevices");
		try {
		/**
		 * @var \FIT\NetopeerBundle\Entity\User $user
		 */
			$user = $this->get('security.context')->getToken()->getUser();
			$this->assign('isProfile', false);
			if ($user instanceof \FIT\NetopeerBundle\Entity\User) {
				$this->assign('connectedDevices', $user->getConnectedDevicesInHistory());
			}
		} catch (\ErrorException $e) {
			// we don't care
		}


		return $this->getTwigArr();
	}

	/**
	 * Remove device from history or profile
	 *
	 * @Route("/ajax/remove-device/{connectionId}", name="removeFromHistoryOrProfile")
	 *
	 * @param int $connectionId   ID of connection
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function removeFromHistoryOrProfileAction($connectionId)
	{
		/**
		 * @var \FIT\NetopeerBundle\Entity\BaseConnection $baseConn
		 */
		$baseConn = $this->get("BaseConnection");
		$result = array();
		$result['result'] = $baseConn->removeDeviceWithId($connectionId);
		if ($result['result'] == 0) {
			$result['status'] = "success";
			$result['message'] = "Device has been removed.";
		} else {
			$result['status'] = "error";
			$result['message'] = "Could not remove device from the list.";
		}
		return new Response(json_encode($result));
	}

	/**
	 * Add device from history to profiles
	 *
	 * @Route("/ajax/add-device-to-profiles/{connectionId}", name="addFromHistoryToProfiles")
	 *
	 * @param int $connectionId   ID of connection
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function addFromHistoryToProfilesAction($connectionId)
	{
		/**
		 * @var \FIT\NetopeerBundle\Entity\BaseConnection $baseConn
		 */
		$baseConn = $this->get("BaseConnection");
		$conn = $baseConn->getConnectionForCurrentUserById($connectionId);
		$result = array();
		$result['result'] = $baseConn->saveConnectionIntoDB($conn->getHost(), $conn->getPort(), $conn->getUsername(), $baseConn::$kindProfile);
		if ($result['result'] === 0) {
			$result['status'] = "success";
			$result['message'] = "Device has been added into profiles.";
		} else {
			$result['status'] = "error";
			$result['message'] = "Could not add device into profiles.";
		}

		return new Response(json_encode($result));
	}

	/**
	 * Get saved profiles of connected devices
	 *
	 * @Route("/ajax/get-profiles/", name="profilesOfConnectedDevices")
	 * @Template("FITNetopeerBundle:Ajax:historyOfConnectedDevices.html.twig")
	 *
	 * @return array    $result
	 */
	public function profilesOfConnectedDevicesAction()
	{
		$this->addAjaxBlock("FITNetopeerBundle:Ajax:historyOfConnectedDevices.html.twig", "profilesOfConnectedDevices");

		try {
		/**
		 * @var \FIT\NetopeerBundle\Entity\User $user
		 */
			$user = $this->get('security.context')->getToken()->getUser();
			$this->assign('isProfile', true);
			if ($user instanceof \FIT\NetopeerBundle\Entity\User) {
				$this->assign('connectedDevices', $user->getConnectedDevicesInProfiles());
			}
		} catch (\ErrorException $e) {
			// we don't care
		}


		return $this->getTwigArr();
	}

	/**
	 * Get connected device attributes.
	 *
	 * @Route("/ajax/get-connected-device-attr/{connectedDeviceId}/", name="connectedDeviceAttr")
	 * @Template()
	 *
	 * @var int $connectedDeviceId
	 * @return array|bool    $result
	 */
	public function connectedDeviceAttrAction($connectedDeviceId)
	{
		$baseConn = $this->get("BaseConnection");
		/**
		 * @var \FIT\NetopeerBundle\Entity\BaseConnection $device
		 */
		$device = $baseConn->getConnectionForCurrentUserById($connectedDeviceId);

		if ($device) {
			$result['host'] = $device->getHost();
			$result['port'] = $device->getPort();
			$result['userName'] = $device->getUsername();
			return new Response(json_encode($result));
		} else {
			return new Response(false);
		}
	}

	/**
	 * Process getting history of notifications
	 *
	 * @Route("/ajax/get-notifications-history/{connectedDeviceId}/", name="notificationsHistory")
	 * @Route("/ajax/get-notifications-history/{connectedDeviceId}/{from}/", name="notificationsHistoryFrom")
	 * @Route("/ajax/get-notifications-history/{connectedDeviceId}/{from}/{to}/", name="notificationsHistoryTo")
	 * @Route("/ajax/get-notifications-history/{connectedDeviceId}/{from}/{to}/{max}/", name="notificationsHistoryMax")
	 * @Template()
	 *
	 * @param $connectedDeviceId
	 * @param null|int $from    start time in seconds
	 * @param int $to           end time in seconds (0 == now)
	 * @param int $max          max number of notifications
	 * @return Response
	 */
	public function getNotificationsHistoryAction($connectedDeviceId, $from = null, $to = 0, $max = 50)
	{
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');

		$params['key'] = $connectedDeviceId;
		$params['from'] = ($from ? $from : time() - 12 * 60 * 60);
		$params['to'] = $to;
		$params['max'] = $max;

		$history = $dataClass->handle("notificationsHistory", $params);

		if ($history !== 1) {
			echo "History: <br/>";
			echo htmlspecialchars($history);
			exit;
//			return new Response(json_encode($result));
		} else {
			echo "Error in history: <br />";
			var_dump($this->getRequest()->getSession()->getFlashes());
			exit;
//			return new Response(false);
		}
	}


	/**
	* Get one model and process it.
	*
	* @param array &$schparams  key, identifier, version, format for get-schema
	* @param string $identifier identifier of folder in /tmp/symfony directory
	* @return int               0 on success, 1 on error
	*/
	private function getschema(&$schparams, $identifier)
	{
		$dataClass = $this->get('DataModel');
		$data = "";
		$path = "/tmp/symfony/";
		@mkdir($path, 0700, true);
		$path .= "/$identifier";

		if (file_exists($path)) {
			/* already exists */
			return 1;
		}

		if ($dataClass->handle("getschema", $schparams, false, $data) == 0) {
			$schparams["user"] = $dataClass->getUserFromKey($schparams["key"]);
			file_put_contents($path, $data);
			$schparams["path"] = $path;
			return 0;
		} else {
			$this->getRequest()->getSession()->setFlash('error', 'Getting model failed.');
			return 1;
		}
		return 0;
	}

	/**
	 * Process <get-schema> action based on schparams.
	 *
	 * @param array &$schparams   get-schema parameters
	 * @return int                0 on success, 1 on error
	 */
	private function processSchema(&$schparams)
	{
		$dataClass = $this->get('DataModel');
		$path = $schparams["path"];

		@system(__DIR__."/../bin/nmp.sh -i \"$path\" -o \"".$dataClass->getModelsDir()."\"");
		return 1;
	}

	/**
	* Get available configuration data models,
	* store them and transform them.
	*
	* @param  int   $key 	index of session-connection
	* @return void
	*/
	private function updateLocalModels($key)
	{
		$schemaData = AjaxSharedData::getInstance();
		$schemaData->setDataForKey($key, 'isInProgress', true);

		$dataClass = $this->get('DataModel');
		$ns = "urn:ietf:params:xml:ns:yang:ietf-netconf-monitoring";
		$params = array(
			'key' => $key,
			'filter' => '<netconf-state xmlns="'.$ns.'"><schemas/></netconf-state>',
		);

		$xml = $dataClass->handle('get', $params);
		if (($xml !== 1) && ($xml !== "")) {
			$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
			if ($xml === false) {
				/* invalid message received */
				$schemaData->setDataForKey($key, 'isInProgress', false);
				$schemaData->setDataForKey($key, 'status', "error");
				$schemaData->setDataForKey($key, 'message', "Getting the list of schemas failed.");
				return;
			}
			$xml->registerXPathNamespace("xmlns", $ns);
			$schemas = $xml->xpath("//xmlns:schema");

			$this->get('data_logger')->info("Trying to find models for namespaces: ", array('namespaces', var_export($schemas)));

			$list = array();
			$lock = sem_get(12345678, 1, 0666, 1);
			sem_acquire($lock); /* critical section */
			foreach ($schemas as $sch) {
				$schparams = array("key" => $params["key"],
					"identifier" => (string)$sch->identifier,
					"version" => (string)$sch->version,
					"format" => (string)$sch->format);
				$ident = $schparams["identifier"]."@".$schparams["version"].".".$schparams["format"];
				if (file_exists($dataClass->getModelsDir()."/$ident")) {
					continue;
				} else if ($this->getschema($schparams, $ident) == 1) {
					//break; /* not get the rest on error */
				} else {
					$list[] = $schparams;
				}
			}
			sem_release($lock);

			$this->get('data_logger')->info("Not found models for namespaces: ", array('namespaces', var_export($list)));

			/* non-critical - only models, that I downloaded will be processed, others already exist */
			foreach ($list as $schema) {
				$this->processSchema($schema);
			}
			$schemaData->setDataForKey($key, 'status', "success");
			$schemaData->setDataForKey($key, 'message', "Configuration data models were updated.");
		} else {
			$schemaData->setDataForKey($key, 'status', "error");
			$schemaData->setDataForKey($key, 'message', "Getting the list of schemas failed.");
		}
		$schemaData->setDataForKey($key, 'isInProgress', false);
	}
}
