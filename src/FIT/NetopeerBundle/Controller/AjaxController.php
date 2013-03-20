<?php
/**
 * File, which handles all Ajax actions.
 *
 * @author David Alexa, Tomas Cejka
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
		$path .= "/$identifier.".$schparams["format"];

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
		$host = $dataClass->getHostFromKey($schparams["key"]);
		$port = $dataClass->getPortFromKey($schparams["key"]);
		$user = $schparams["user"];
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

		if (($xml = $dataClass->handle('get', $params)) != 1 ) {
			$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
			$xml->registerXPathNamespace("xmlns", $ns);
			$schemas = $xml->xpath("//xmlns:schema");

			$list = array();
			$lock = sem_get(12345678, 1, 0666, 1);
			sem_acquire($lock); /* critical section */
			foreach ($schemas as $sch) {
				$schparams = array("key" => $params["key"],
					"identifier" => (string)$sch->identifier,
					"version" => (string)$sch->version,
					"format" => (string)$sch->format);
				$ident = $dataClass->getModelIdentificator($schparams["identifier"], $schparams["version"],((string) $sch->namespace));

				if (file_exists($dataClass->getModelsDir()."/$ident")) {
					continue;
				} else if ($this->getschema($schparams, $ident) == 1) {
					//break; /* not get the rest on error */
				} else {
					$list[] = $schparams;
				}
			}
			sem_release($lock);
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
