<?php
/**
 * File, which handles all Ajax actions.
 *
 * @file AjaxController.php
 * @author David Alexa <alexa.david@me.com>
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

use FIT\NetopeerBundle\Controller\BaseController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller which handles all Ajax actions
 */
class AjaxController extends BaseController
{
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
			$this->assign('connectedDevices', $user->getConnectedDevicesInHistory());
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
			$result['message'] = array('success' => array("Device has been removed."));
		} else {
			$result['message'] = array('error' => array("Could not remove device from the list."));
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
			$result['message'] = array('success' => array("Device has been added into profiles."));
		} else {
			$result['message'] = array('error' => array("Could not add device into profiles."));
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
			$this->assign('connectedDevices', $user->getConnectedDevicesInProfiles());
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
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		$params['key'] = $connectedDeviceId;
		$params['from'] = ($from ? $from : time() - 12 * 60 * 60);
		$params['to'] = $to;
		$params['max'] = $max;

		$history = $netconfFunc->handle("notificationsHistory", $params);

		// $history is 1 on error (we will show flash message) or array on success
		if ($history !== 1) {
			return new Response(json_encode($history));
		} else {
			return $this->getAjaxAlertsRespose();
		}
	}

	/**
	 * Call validate in mod_netconf
	 *
	 * @Route("/ajax/validate-source/{key}/{target}", name="validateSource")
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $target
	 * @return array
	 */
	public function validateSource($key, $target)
	{
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');

		$params = array(
			'connIds' => array($key),
			'target' => $target
		);

		$res = $netconfFunc->handle('validate', $params, false);
		$this->getRequest()->getSession()->getFlashBag()->add('state '.(!$res ? 'success' : 'error'), 'Datastore '.$target.' is '.($res ? 'in' : '').'valid.');
		$this->addAjaxBlock('FITModuleDefaultBundle:Module:section.html.twig', 'alerts');
		$this->removeAjaxBlock('state');
		$this->assign('dataStore', $target);
		return $this->getTwigArr();
	}

	/**
	 * Lookup IP address.
	 *
	 * @Route("/ajax/lookup-ip/{ip}/", name="lookupIp")
	 * @Template()
	 *
	 * @var int $connectedDeviceId
	 * @return array|bool    $result
	 */
	public function lookupipAction($ip)
	{
		if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $ip)) {

			/**
			 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
			 */
			$cache = $this->container->get('winzou_cache');
			$hashedIp = md5($ip);
			$cacheArr = array();

			if ($cache->contains('lookupip_'.$hashedIp)) {
				$cacheArr = $cache->fetch('lookupip_'.$hashedIp);
			} else {
				// load lookup HTML content
				$lookup = file_get_contents('http://ip-whois-lookup.com/lookup.php?ip='.$ip);
				try {
					$regex = '/\<p class\=\"maptxt\"\>(.*)\<\/p\>/';
					// get all necessary information
					preg_match_all($regex, $lookup, $ipInfoArr);
					if (count($ipInfoArr) > 1) {
						$cacheArr['items'] = array();
						foreach ($ipInfoArr[1] as $item) {
							$cacheArr['items'][] = strip_tags($item);
							if (strpos($item, 'Latitude: ') !== false) {
								$cacheArr['latitude'] = str_replace("Latitude: ", "", $item);
							}
							if (strpos($item, 'Longitude: ') !== false) {
								$cacheArr['longitude'] = str_replace("Longitude: ", "", $item);
							}
						}
					}

					// save to cache (for one day)
					$cache->save('lookupip_'.$hashedIp, $cacheArr, 24 * 60 * 60);
				} catch (\ErrorException $e) {
					return new Response(false);
				}
			}
			if (count($cacheArr)) {
				$this->assign('latitude', $cacheArr['latitude']);
				$this->assign('longitude', $cacheArr['longitude']);
				$tmp = $cacheArr['items'];
				if (count($tmp)) {
					$this->assign('lookupTitle', array_shift($tmp));
					$this->assign('ipInfo', $tmp);
				}
			}

			return $this->getAssignedVariablesArr();
		}
	}

	/**
	 * @param $key
	 * @param $filter
	 *
	 * @Route("/ajax/schema/", name="loadSchemaByFilter")
	 *
	 * @return JsonResponse
	 */
	public function loadSchemaByFilterAction() {
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
		if ($this->getRequest()->getContent() !== "") {
			$requestParams = json_decode($this->getRequest()->getContent(), true);
		} else {
			$requestParams['filters'] = $this->getRequest()->get('filters');
			$requestParams['connIds'] = $this->getRequest()->get('connIds');
		}

		if (isset($requestParams['filters']) && array_key_exists(0, $requestParams['filters']) && $requestParams['filters'][0] !== '/') {
			$params = array(
				'connIds' => $requestParams['connIds'],
				'filters' => array($requestParams['filters'])
			);
			$res = $netconfFunc->handle('query', $params);
			return new JsonResponse(json_decode($res));
		}
		return new JsonResponse([]);
	}
}
