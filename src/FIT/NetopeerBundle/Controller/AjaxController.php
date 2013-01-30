<?php

namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use FIT\NetopeerBundle\Models\XMLoperations;
use FIT\NetopeerBundle\Models\AjaxSharedData;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AjaxController extends BaseController
{
	/**
	 * @Route("/ajax/get-schema/{key}", name="getSchema")
	 *
	 * Change session value for showing single or double column layout
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
	 * @Route("/ajax/get-schema-status/{key}", name="getSchemaStatus")
	 *
	 * Get status of get-schema operation
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
	* @param {array} &$schparams key, identifier, version, format for get-schema
	* @return {int} 0 on success, 1 on error
	*/
	private function getschema(&$schparams)
	{
		$dataClass = $this->get('DataModel');
		$data = "";
		if ($dataClass->handle("getschema", $schparams, false, $data) == 0) {
			$path = "/tmp/symfony/".$schparams["identifier"].".".$schparams["format"];
			file_put_contents($path, $data);
			@system("/tmp/symfony/nmp.sh -i \"$path\" -o \"/tmp/symgen/\"");
		} else {
			$this->getRequest()->getSession()->setFlash('error', 'Getting model failed.');
			return 1;
		}
		return 0;
	}

	/**
	* Get available configuration data models,
	* store them and transform them.
	*
	* @param  {int} $key 	index of session-connection
	* @return {void}
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

			if ($schemas) {
				/* TODO not to delete everything? */
				@system("mkdir -p /tmp/symgen /tmp/symfony; rm -rf /tmp/symgen/*");
			}
			foreach ($schemas as $sch) {
				/* TODO if ( is not up-to date ) { */
					$schparams = array("key" => $params["key"],
						"identifier" => (string)$sch->identifier,
						"version" => (string)$sch->version,
						"format" => (string)$sch->format);
					if ($this->getschema($schparams) == 1) {
						break; /* not get the rest on error */
					}
				/* } */
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
