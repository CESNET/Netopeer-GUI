<?php

namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use FIT\NetopeerBundle\Models\AjaxSharedData;
use FIT\NetopeerBundle\Entity\MyConnection;

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
	* Get one model and process it.
	*
	* @param array $schparams   key, identifier, version, format for get-schema
	* @return int               0 on success, 1 on error
	*/
	private function getschema(&$schparams)
	{
		$dataClass = $this->get('DataModel');
		$data = "";
		if ($dataClass->handle("getschema", $schparams, false, $data) == 0) {
			$schparams["user"] = $dataClass->getUserFromKey($schparams["key"]);
			$path = "/tmp/symfony/".$schparams["user"];
			@mkdir($path, 0700, true);
			$path .= "/".$schparams["identifier"].".".$schparams["format"];
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
	 * @param array $schparams    get-schema parameters
	 * @return int                0 on success, 1 on error
	 */
	private function processSchema(&$schparams)
	{
		$dataClass = $this->get('DataModel');
		$host = $dataClass->getHostFromKey($schparams["key"]);
		$port = $dataClass->getPortFromKey($schparams["key"]);
		$user = $schparams["user"];
		$path = $schparams["path"];

		ob_clean();
		ob_start();
		@system(__DIR__."/../bin/nmp.sh -i \"$path\" -o \"".$dataClass->getModelsDir()."\" -u \"$user\" -t \"$host\" -p \"$port\"");
		$response = ob_get_contents();

		preg_match("/\{(.*)\}/", $response, $jsonArr);
		if (count($jsonArr) > 1) {
			return $this->saveConnectionInDB($schparams["key"], json_decode($jsonArr[0]));
		}
		return 1;
	}

	/**
	 * Saves unique connection info into DB
	 *
	 * @param  string   $key       session key
	 * @param  array    $jsonArr   JSON object of params
	 * @throws \ErrorException
	 * @return int 0 on success, 1 on fail
	 */
	private function saveConnectionInDB($key, $jsonArr) {
		$conn = new MyConnection();
		$conn->setHash($jsonArr->identifier);
		$conn->setModelName($jsonArr->module);
		$conn->setModelVersion($jsonArr->version);
		$conn->setRootElem($jsonArr->root_element);
		$conn->setNamespace($jsonArr->ns);
		$conn->setHostname($jsonArr->host);
		$conn->setPort($jsonArr->port);
		$conn->setUsername($jsonArr->user);

		try {
			$em = $this->getDoctrine()->getEntityManager();
			$em->persist($conn);
			$em->flush();

			$this->get('logger')->info("Connection added into DB.", array("arr" => var_export($jsonArr)));

		} catch (\PDOException $e) {
			$this->getDoctrine()->resetEntityManager();
			if ($e->getCode() == 23000) { // duplicate entry
				$this->get('logger')->err("Duplicate connection.", array("arr" => var_export($jsonArr)));
				// we don't care about
			} else {
				throw new \ErrorException($e->getMessage());
			}
		} catch (\ErrorException $e) {
			$this->get('logger')->err("Could not add connection into DB.", array("arr" => var_export($jsonArr)));
			return 1;
		}

		$sessionConnections = $this->get('session')->get('session-connections');
		$sessionConn = unserialize($sessionConnections[$key]);
		$sessionConn->dbIdentifier[$jsonArr->identifier] = true;
		$sessionConnections[$key] = serialize($sessionConn);

		$this->get('session')->set('session-connections', $sessionConnections);

		return 0;
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

			$sessionConnections = $this->get('session')->get('session-connections');
			$sessionConn = unserialize($sessionConnections[$key]);
			$sessionConn->dbIdentifier = array();
			$sessionConnections[$key] = serialize($sessionConn);
			$this->get('session')->set('session-connections', $sessionConnections);

			$list = array();
			foreach ($schemas as $sch) {
				$schparams = array("key" => $params["key"],
					"identifier" => (string)$sch->identifier,
					"version" => (string)$sch->version,
					"format" => (string)$sch->format);
				if ($this->getschema($schparams) == 1) {
					//break; /* not get the rest on error */
				} else {
					$list[] = $schparams;
				}
			}
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
