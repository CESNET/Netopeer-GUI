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
		/**
		 * @var \FIT\NetopeerBundle\Models\Data $dataClass
		 */
		$dataClass = $this->get('DataModel');
		$schemaData = AjaxSharedData::getInstance();
		
		ob_start();
		$data = $schemaData->getDataForKey($key);
		if (!(isset($data['isInProgress']) && $data['isInProgress'] === true)) {
			$dataClass->updateLocalModels($key);
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

		// $history is 1 on error (we will show flash message) or array on success
		if ($history !== 1) {
			return new Response(json_encode($history));
		} else {
			return $this->getAjaxAlertsRespose();
		}
	}
}
