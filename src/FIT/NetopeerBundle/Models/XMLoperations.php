<?php
/**
 * XML operations, which are necessary for processing XML modifications.
 *
 * @file XMLoperations.php
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
namespace FIT\NetopeerBundle\Models;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use FIT\NetopeerBundle\Models\Data as Data;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\Finder\Finder;
use FIT\NetopeerBundle\Models\MergeXML;

class XMLoperations {
	/**
	 * @var ContainerInterface   base bundle container
	 */
	public $container;
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	public $logger;
	/**
	 * @var \FIT\NetopeerBundle\Models\Data       instance of data class
	 */
	public $dataModel;

	public static $customRootElement = 'netopeer-root';

	/**
	 * Constructor with DependencyInjection params.
	 *
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	 * @param \Symfony\Bridge\Monolog\Logger $logger   logging class
	 * @param Data $dataModel data class
	 */
	public function __construct(ContainerInterface $container, $logger, Data $dataModel)	{
		$this->container = $container;
		$this->logger = $logger;
		$this->dataModel = $dataModel;
	}



	/**
	 * divides string into the array (name, value) (according to the XML tree node => value)
	 *
	 * @param  string $postKey post value
	 * @return array           in format ('name', 'value')
	 */
	public function divideInputName($postKey)
	{
		$values = explode('_', $postKey);
		$cnt = count($values);
		if ($cnt > 2) {
			$last = $values[$cnt-1];
			$values = array(implode("_", array_slice($values, 0, $cnt-1)), $last);
		} elseif ($cnt == 2) {

		} elseif ($cnt == 1) {
			$values = array('name', $values[0]);
		} else {
			$values = array('name', 'value');
		}
		return $values;
	}

	/**
	 * decodes XPath value (custom coding from JS)
	 *
	 * @param  string $value encoded XPath string from JS form
	 * @return string        decoded XPath string
	 */
	public function decodeXPath($value) {
		return str_replace(
			array('--', '?', '!'),
			array('/', '[', ']'),
			$value
		);
	}

	/**
	 * Completes request tree (XML) with necessary parent nodes.
	 * Tree must be valid for edit-config action.
	 *
	 * @param \SimpleXMLElement $parent current parent of new content to be completed (add all his parents)
	 * @param string            $newConfigString
	 * @param null              $wrappedPath
	 *
	 * @return \SimpleXMLElement
	 */
	public function completeRequestTree(&$parent, $newConfigString, $wrappedPath = null) {
		if (is_null($wrappedPath)) {
			$wrappedPath = $this->dataModel->getPathToModels() . 'wrapped.wyin';
		}
		$subroot = simplexml_load_file($wrappedPath);
		$xmlNameSpaces = $subroot->getNamespaces();

		if ( isset($xmlNameSpaces[""]) ) {
			$subroot->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
		}
		$ns = $subroot->xpath("//xmlns:namespace");
		$namespace = "";
		if (sizeof($ns)>0) {
			$namespace = $ns[0]->attributes()->uri;
		}

		$parent = $parent->xpath("parent::*");
		while ($parent) {
			$pos_subroot[] = $parent[0];
			$parent = $parent[0]->xpath("parent::*");
		}
		$config = $newConfigString;

		if (isset($pos_subroot)) {
			for ($i = 0; $i < sizeof($pos_subroot); $i++) {
				/**
				 * @var SimpleXMLElement $subroot
				 */
				$subroot = $pos_subroot[$i];
				$domNode = dom_import_simplexml($subroot);


				// key elements must be added into config XML
				$newdoc = new \DOMDocument;
				$node = $newdoc->importNode($domNode, true);
				$newdoc->appendChild($node);
				$keyElems = $this->removeChildrenExceptOfKeyElements($node, $node->childNodes, true);

				$childrenConfig = "";
				if ($keyElems > 0) {
					$simpleSubRoot = simplexml_import_dom($node);
					foreach ($simpleSubRoot->children() as $child) {
						$childrenConfig .= $child->asXml();
					}
				}

				$tmp = $subroot->getName();
				$config .= "</".$subroot->getName().">\n";

				if ($i == sizeof($pos_subroot) - 1) {
					$config = "<".$subroot->getName().
							($namespace!==""?" xmlns=\"$namespace\"":"").
							" xmlns:xc=\"urn:ietf:params:xml:ns:netconf:base:1.0\"".
							">\n".$childrenConfig.$config;
				} else {
					$config = "<".$subroot->getName().
							">\n".$childrenConfig.$config;
				}
			}
		}
		$result = simplexml_load_string($config);
		$result->registerXPathNamespace('xmlns', $namespace);

		return $result;
	}

	/**
	 * @param string $sourceXml
	 * @param string $newXml
	 * @param array  $params    parametrs for MergeXML constructor
	 * @param int    $output    kind of output of MergeXML
	 *
	 * @return \DOMDocument|false
	 */
	public function mergeXml ($sourceXml, $newXml, $params = array(), $output = 0) {
		$defaultParams = array(
			'join' => false, // common root name if any source has different root name (default is *root*, specifying *false* denies different names)
		  'updn' => true, // traverse the nodes by name sequence (*true*, default) or overall sequence (*false*),
		  'stay' => 'all', // use the *stay* attribute value to deny overwriting of specific node (default is *all*, can be array of values, string or empty)
		  'clone' => '', // use the *clone* attribute value to clone specific nodes if they already exists (empty by default, can be array of values, string or empty)
		);
		$params = array_merge($defaultParams, $params);

		$sourceDoc = new \DOMDocument();
		$sourceDoc->loadXML($sourceXml);

		$newDoc = new \DOMDocument();
		$newDoc->loadXML($newXml);

		try {
			$mergeXml = new MergeXML();
			@$mergeXml->AddSource($sourceXml);
			@$mergeXml->AddSource($newDoc);

			if (@$mergeXml->error->code != "") {
				return false;
			}
		} catch (Exception $e) {
			return false;
		}
		return $mergeXml->Get($output);
	}

	/**
	 * updates (modifies) value of XML node
	 *
	 * @param  \SimpleXMLElement $configXml   xml file
	 * @param  string $elementName name of the element
	 * @param  string $xpath       XPath to the element (without trailing '/', which is added automatically)
	 * @param  string $val         new value
	 * @param  string $xPathPrefix
	 * @param  int    $newIndex    new index of elem in parent cover (selectable plugin)
	 *
	 * @return \SimpleXMLElement|array   modified node, empty array if element was not found
	 */
	public function elementValReplace(&$configXml, $elementName, $xpath, $val, $xPathPrefix = "xmlns:", $newIndex = -1)
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
				if ($val != "") {
					$e[0] = str_replace("\r", '', $val); // removes \r from value
				}
				if ($newIndex !== -1) {
					$elem->addAttribute($xPathPrefix."index", $newIndex);
				}
			} else {
				if ( !is_array($elem) ) {
					if ($val != "") {
						$elem[0] = str_replace("\r", '', $val);
					}
					if ($newIndex !== -1) {
						$elem[0]->addAttribute($xPathPrefix."index", $newIndex);
					}
				}
			}
		}

		return $elem;
	}


	/**
	 * handles edit config form - changes config values into the $_POST values
	 * and sends them to editConfig process
	 *
	 * @param  int $key  session key of current connection
	 * @param  array $configParams    array of config params
	 * @return int        result code
	 */
	public function handleEditConfigForm(&$key, $configParams) {
		$post_vals = $this->container->get('request')->get('configDataForm');
		$res = 0;

		try {

			if ( ($originalXml = $this->dataModel->handle('getconfig', $configParams, false)) != 1 ) {

				$configXml = simplexml_load_string($originalXml, 'SimpleXMLIterator');

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $configXml->asXml());
				}

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $configXml->getNamespaces();

				if ( isset($xmlNameSpaces[""]) ) {
					$configXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
					$xmlNamespace = $xmlNameSpaces[""];
					$xPathPrefix = "xmlns:";
				} else {
					// we will use this xmlns as backup for XPath request
					$configXml->registerXPathNamespace("xmlns", "urn:cesnet:tmc:hanicprobe:1.0");
					$xmlNamespace = "urn:cesnet:tmc:hanicprobe:1.0";
					$xPathPrefix = "";
				}

				// foreach over all post values
				$parentNodesForSorting = array();
				$processSorting = false;
				foreach ( $post_vals as $postKey => $val ) {
					if ($postKey == 'commit_all_button') continue;

					$index = -1;

					// divide string, if index is set
					if (strpos($postKey, "|") !== false) {
						$arr = explode("|", $postKey);

						if (sizeof($arr) == 2 && strpos($postKey, "index") !== false) {
							$postKey = $arr[1];
							$index = str_replace("index", "", $arr[0]);

							$processSorting = true;
						}
					}

					$values = $this->divideInputName($postKey);
					$elementName = $values[0];
					$xpath = $this->decodeXPath($values[1]);
					$xpath = substr($xpath, 1); // removes slash at the begining

					$modifiedElem = $this->elementValReplace($configXml, $elementName, $xpath, $val, $xPathPrefix, $index);
					if ($index != -1 && $modifiedElem instanceof \SimpleXMLIterator) {
						array_push($parentNodesForSorting, $modifiedElem);
					}
				}

				if (sizeof($parentNodesForSorting)) {
					$items = array();
					$parent = false;
					foreach ($parentNodesForSorting as $child) {
						$childDOM = dom_import_simplexml($child[0]);
						$items[] = $childDOM;
						$par = $child->xpath("parent::*");
						$parent = dom_import_simplexml($par[0]);
					}

					// deleting must be separated
					foreach ($items as $child) {
						if ($parent) $parent->removeChild($child);
					}

					usort($items, function($a, $b) {
						$indA = 0;
						$indB = 0;

						foreach($a->attributes as $name => $node) {
							if ($name == "index") {
								$indA = $node->nodeValue;
								break;
							}
						}

						foreach($b->attributes as $name => $node) {
							if ($name == "index") {
								$indB = $node->nodeValue;
								break;
							}
						}


						return $indA - $indB;
					});

					foreach ($items as $child) {
						$child->removeAttribute('index');
						if ($parent) $parent->appendChild($child);
					}

					if ($parent) {
						$parentSimple = simplexml_import_dom($parent);
						$parentSimple->addAttribute("xc:operation", "replace", "urn:ietf:params:xml:ns:netconf:base:1.0");
					}
				}

				// for debugging, edited configXml will be saved into temp file
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/edited.yin', $configXml->asXml());
				}

				// check, if newNodeForm was send too
				if (sizeof($this->container->get('request')->get('newNodeForm'))) {
					$newNodeForms = $this->container->get('request')->get('newNodeForm');
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/testing.yin', var_export($newNodeForms, true));

					// create empty simpleXmlObject for adding all newNodeForms
					$currentRoot = $configXml->xpath("/xmlns:*");
					$newNodesXML = new SimpleXMLElement("<".$currentRoot[0]->getName()." xmlns='".$xmlNamespace."'></".$currentRoot[0]->getName().">");

					foreach ($newNodeForms as $newNodeFormVals) {
						$newNodeConfigXML = simplexml_load_string($originalXml, 'SimpleXMLIterator');

						// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
						$xmlNameSpaces = $newNodeConfigXML->getNamespaces();

						if ( isset($xmlNameSpaces[""]) ) {
							$newNodeConfigXML->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
							$newNodesXML->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
						} else {
							// we will use this xmlns as backup for XPath request
							$newNodeConfigXML->registerXPathNamespace("xmlns", "urn:cesnet:tmc:hanicprobe:1.0");
							$newNodesXML->registerXPathNamespace("xmlns", "urn:cesnet:tmc:hanicprobe:1.0");
						}
						$toAdd = $this->processNewNodeForm($newNodeConfigXML, $newNodeFormVals);

						// merge new node XML with previous one
						if (($out = $this->mergeXml($newNodesXML->asXML(), $toAdd)) !== false) {
							$newNodesXML = simplexml_load_string($out->saveXML());
						}
					}

					// finally merge the request with edited values
					if (($out = $this->mergeXml($configXml->asXML(), $newNodesXML->asXML())) !== false) {
						$configXml = simplexml_load_string($out->saveXML());
					}
				}

				// sort final config xml
				$params['attributesWhiteList'] = array('model-level-index');
				$xmlString = $configXml->asXML();
				$xmlString = $this->mergeXMLWithModel($xmlString, $params);
				$sortedXml = $this->sortXMLByModelLevelIndex($xmlString, true);

				$res = $this->executeEditConfig($key, $sortedXml, $configParams['source']);
				if ($res !== 1) {
					$this->container->get('session')->getFlashBag()->add('success', "Config has been edited successfully.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->logger->warn('Could not save config correctly.', array('error' => $e->getMessage()));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not save config correctly. Error: ".$e->getMessage());
		}


		return $res;
	}

	/**
	 * handles form for creating empty module
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $configParams
	 * @param $postVals
	 *
	 * @return int
	 */
	public function handleCreateEmptyModuleForm($key, $configParams, $postVals) {
		$name = $postVals['name'];
		$namespace = $postVals['namespace'];
		$res = 0;

		$xmlTree = new \SimpleXMLElement('<'.$name.'></'.$name.'>');
		$xmlTree->addAttribute('xmlns', $namespace);
		$xmlTree->registerXPathNamespace('xc', 'urn:ietf:params:xml:ns:netconf:base:1.0');

		$xmlTree->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");

		$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $xmlTree->asXML());

		try {
			$res = $this->executeEditConfig($key, $createString, $configParams['source']);

			if ($res == 0) {
				$this->container->get('request')->getSession()->getFlashBag()->add('success', "New module was created.");
			}
		} catch (\ErrorException $e) {
			$this->logger->warn('Could not create empty module.', array('error' => $e->getMessage(), 'xml' => $createString));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not create empty module. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * Handles form for RPC method call
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $configParams
	 * @param $postVals
	 *
	 * @return int
	 * @throws \ErrorException
	 */
	public function handleRPCMethodForm($key, $configParams, $postVals) {
		$name = $postVals['rootElemName'];
		$namespace = $postVals['rootElemNamespace'];
		$res = 0;

		$xmlTree = new \SimpleXMLElement('<'.$name.'></'.$name.'>');
		$xmlTree->registerXPathNamespace('xc', 'urn:ietf:params:xml:ns:netconf:base:1.0');

		if ($namespace !== 'false' && $namespace !== '') {
			$xmlTree->registerXPathNamespace('rpcMod', $namespace);
			$xmlTree->addAttribute('xmlns', $namespace);
		}

		// we will go through all post values
		$skipArray = array('rootElemName', 'rootElemNamespace');
		foreach ( $postVals as $labelKey => $labelVal ) {
			if (in_array($labelKey, $skipArray)) continue;
			$label = $this->divideInputName($labelKey);
			// values[0] - label
			// values[1] - encoded xPath

			if ( count($label) != 2 ) {
				$this->logger->err('RPCMethodForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $label, 'postKey' => $labelKey));
				throw new \ErrorException("Could not proccess all form fields.");

			} else {
				$xpath = $this->decodeXPath($label[1]);
				$xpath = substr($xpath, 1);

				$node = $this->insertNewElemIntoXMLTree($xmlTree, $xpath, $label[0], $labelVal, '', $addCreateNS = false);

				array_push($skipArray, $labelKey);
			}
		}

		$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $xmlTree->asXML());

		try {
			$res = $this->dataModel->handle("userrpc", array(
					'key' => $key,
					'content' => $createString,
				), false, $result);
			/* RPC can return output data in $result */

			if ($res == 0) {
				$this->container->get('request')->getSession()->getFlashBag()->add('success', "RPC method invocation was successful.");
			}
		} catch (\ErrorException $e) {
			$this->logger->warn('Could not invocate RPC method.', array('error' => $e->getMessage(), 'xml' => $createString));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not invocate RPC method. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * duplicates node in config - values of duplicated nodes (elements)
	 *
	 * could be changed by user
	 *
	 * @param  int  $key  session key of current connection
	 * @param  array $configParams    array of config params
	 * @throws \ErrorException
	 * @return int        result code
	 */
	public function handleDuplicateNodeForm(&$key, $configParams)	{
		$post_vals = $this->container->get('request')->get('duplicatedNodeForm');
		$res = 0;

		try {
			// load original (not modified) getconfig
			if ( ($originalXml = $this->dataModel->handle('getconfig', $configParams, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $tmpConfigXml->asXml());
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
						$this->logger->err('newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $values, 'postKey' => $postKey));
						throw new \ErrorException("newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1! ". var_export(array('values' => $values, 'postKey' => $postKey), true));
					} else {
						$xpath = $this->decodeXPath($values[1]);
						$xpath = substr($xpath, 1, strripos($xpath, "/") - 1);

						$node = $this->elementValReplace($tmpConfigXml, $values[0], $xpath, $val);
						try {
							if ( is_object($node) ) {
								@$node->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");
							}
						} catch (\ContextErrorException $e) {
							// nothing happened - attribute is already there
						}
					}
				}

				$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $parentNode[0]->asXml());
				$createTree = $this->completeRequestTree($parentNode[0], $createString);

				// for debugging, edited configXml will be saved into temp file
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/newElem.yin', $createTree->asXml());
				}
				$res = $this->executeEditConfig($key, $createTree->asXml(), $configParams['source']);

				if ($res == 0) {
					$this->container->get('request')->getSession()->getFlashBag()->add('success', "Record has been added.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->logger->warn('Could not save new node correctly.', array('error' => $e->getMessage()));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not save new node correctly. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * create new node
	 *
	 * @param  int      $key 				  session key of current connection
	 * @param  array $configParams    array of config params
	 * @return int                    result code
	 */
	public function handleNewNodeForm(&$key, $configParams)	{
		$post_vals = $this->container->get('request')->get('newNodeForm');
		$res = 0;

		try {
			// load original (not modified) getconfig
			if ( ($originalXml = $this->dataModel->handle('getconfig', $configParams, true)) != 1 ) {
				/** @var \SimpleXMLElement $configXml */
				$configXml = simplexml_load_string($originalXml);

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $configXml->getNamespaces();
				if ( isset($xmlNameSpaces[""]) ) {
					$namespace = $xmlNameSpaces[""];
					$configXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
				} elseif (sizeof($xmlNameSpaces) == 0) {
					$namespace = 'urn:ietf:params:xml:ns:yang:yin:1';
					$configXml->registerXPathNamespace("xmlns", 'urn:ietf:params:xml:ns:yang:yin:1');
				}
			}

			// if we have XML configuration
			if (isset($configXml)) {
				$createTreeXML = $this->processNewNodeForm($configXml, $post_vals);

				$res = $this->executeEditConfig($key, $createTreeXML, $configParams['source']);

				if ($res == 0) {
					$this->container->get('request')->getSession()->getFlashBag()->add('success', "Record has been added.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}

		} catch (\ErrorException $e) {
			$this->logger->warn('Could not save new node correctly.', array('error' => $e->getMessage()));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not save new node correctly. Error: ".$e->getMessage());
		}

		return $res;
	}

	/**
	 * @param \SimpleXMLElement $configXml
	 * @param array             $post_vals
	 *
	 * @return mixed|string
	 * @throws \ErrorException
	 */
	private function processNewNodeForm(&$configXml, $post_vals) {
		$keyElemsCnt = 0;
		$dom = new \DOMDocument();
		$skipArray = array();

		// load parent value
		if (array_key_exists('parent', $post_vals)) {
			$parentPath = $post_vals['parent'];

			$xpath = $this->decodeXPath($parentPath);
			// get node according to xPath query
			/** @var \SimpleXMLElement $tmpParentNode */
			$parentNode = $configXml->xpath($xpath);

			array_push($skipArray, 'parent');

			// we have to delete all children from parent node (because of xpath selector for new nodes), except from key nodes
			$domNode = dom_import_simplexml($parentNode[0]);
			$keyElemsCnt = $this->removeChildrenExceptOfKeyElements($domNode, $domNode->childNodes);

		} else {
			throw new \ErrorException("Could not set parent node for new elements.");
		}

		// we will go through all post values
		$speciallyAddedNodes = array();
		foreach ( $post_vals as $labelKey => $labelVal ) {
			if (in_array($labelKey, $skipArray)) continue;
			$label = $this->divideInputName($labelKey);
			// values[0] - label
			// values[1] - encoded xPath

			// load parent node


			if ( count($label) != 2 ) {
				$this->logger->err('newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $label, 'postKey' => $labelKey));
				throw new \ErrorException("Could not proccess all form fields.");

			} else {
				$valueKey = str_replace('label', 'value', $labelKey);
				$value = $post_vals[$valueKey];

				array_push($skipArray, $labelKey);
				array_push($skipArray, $valueKey);

				$xpath = $this->decodeXPath($label[1]);
				$xpath = substr($xpath, 1, strripos($xpath, "/") - 1);

				$addedNodes = $this->insertNewElemIntoXMLTree($configXml, $xpath, $labelVal, $value);
				// we have created some other element
				if (sizeof($addedNodes) >= 2) {
					for ($i = 1; $i < sizeof($addedNodes); $i++) {
						array_push($speciallyAddedNodes, $addedNodes[$i]);
					}
				}
			}
		}

		if ($keyElemsCnt > 0 && isset($domNode)) {
			$dom->importNode($domNode, true);
			$this->moveCustomKeyAttributesIntoElements($dom, $domNode, $keyElemsCnt);
		}

		$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $parentNode[0]->asXml());
		$createTree = $this->completeRequestTree($parentNode[0], $createString);
		$createTreeXML = $createTree->asXML();

		// add all "specially added nodes" - mainly leafrefs and so
		if (sizeof($speciallyAddedNodes)) {
			// append special nodes into empty root element
			foreach ($speciallyAddedNodes as $node) {
				$nodeCreateString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $node[0]->asXml());
				$nodeCreateTree = $this->completeRequestTree($node[0], $nodeCreateString);

				// finally merge the request
				if (($out = $this->mergeXml($createTreeXML, $nodeCreateTree->asXML())) !== false) {
					$createTree = simplexml_load_string($out->saveXML());
				}
			}

			$createTreeXML = $createTree->asXML();
		}

		return $createTreeXML;
	}

	/**
	 * @param \DOMDocument $dom
	 * @param \DOMElement  $domNode
	 * @param              $keyElementsCnt
	 *
	 * @return int         number of moved items
	 */
	public function moveCustomKeyAttributesIntoElements($dom, $domNode, $keyElementsCnt) {
		$attributesArr = array();
		$totalMoved = 0;

		if ($domNode->hasAttributes()) {
			foreach ($domNode->attributes as $attr) {
				if (strpos($attr->nodeName, "GUIcustom_") === 0) {
					$elemName = str_replace("GUIcustom_", "", $attr->nodeName);
					$elemValue = $attr->nodeValue;

					if ($domNode->hasChildNodes()) {
						$domNode->insertBefore(new \DOMElement($elemName, $elemValue), $domNode->childNodes->item(0));
					} else {
						$domNode->appendChild(new \DOMElement($elemName, $elemValue));
					}

					$attributesArr[] = $attr->nodeName;
					$totalMoved++;
				}
			}
			// remove must be in new foreach, previous deletes only first one
			foreach ($attributesArr as $attrName) {
				$domNode->removeAttribute($attrName);
			}
		}

		if ($totalMoved < $keyElementsCnt && $domNode->hasChildNodes()) {
			foreach($domNode->childNodes as $child) {
				$totalMoved += $this->moveCustomKeyAttributesIntoElements($dom, $child, $keyElementsCnt);
			}
		}

		return $totalMoved;
	}

	/**
	 * Sorts given XML file by attribute model-level-number recursively
	 *
	 * @param      $xml
	 * @param bool $removeIndexAttr
	 *
	 * @return string
	 */
	public function sortXMLByModelLevelIndex($xml, $removeIndexAttr = true) {
		$xslt = '
		<xsl:stylesheet version="1.0"
		    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
		    <xsl:output method="xml" indent="yes"/>
		    <xsl:template match="@* | node()">
		        <xsl:copy>
		            <xsl:apply-templates select="@* | node()">
		                <xsl:sort select="@model-level-index" data-type="number"/>
		            </xsl:apply-templates>
		        </xsl:copy>
		    </xsl:template>
		</xsl:stylesheet>';

		$xsldoc = new \DOMDocument();
		$xsldoc->loadXML($xslt);

		$xmldoc = new \DOMDocument();
		$xmldoc->loadXML($xml);

		$xsl = new \XSLTProcessor();
		$xsl->importStyleSheet($xsldoc);

		$res = $xsl->transformToXML($xmldoc);

		// remove attribute model-level-index
		if ($removeIndexAttr) {
			$res = preg_replace('/ model-level-index="\d+"/', '', $res);
		}

		return $res;
	}

	/**
	 * removes all children of element except of key elements, which has to remain
	 *
	 * @param      \DOMElement  $domNode
	 * @param      \DOMNodeList $domNodeChildren
	 * @param bool              $leaveKey
	 * @param bool              $recursive
	 *
	 * @return int  number of key elements, that remains
	 */
	public function removeChildrenExceptOfKeyElements($domNode, $domNodeChildren, $leaveKey = false, $recursive = false)
	{
		$keyElemIndex = $keyElemsCnt = 0;

		while ($domNodeChildren->length > $keyElemIndex) {
			$isKey = $isCreated = false;
			$child = $domNodeChildren->item($keyElemIndex);

			if ($child->hasAttributes()) {
				foreach ($child->attributes as $attr) {
					if ($attr->nodeName == "iskey" && $attr->nodeValue == "true") {
						if ($child->hasAttributes()) {
							foreach ($child->attributes as $attr) {
								if ($attr->nodeName !== 'xc:operation') {
									$attributesArr[] = $attr->nodeName;
								}
							}
							// remove must be in new foreach, previous deletes only first one
							foreach ($attributesArr as $attrName) {
								$child->removeAttribute($attrName);
							}
						}
						if ($leaveKey == true) {
							$keyElemIndex++;
							$isKey = true;
						} else if (isset($child)) {
							$nodeName = $child->nodeName;
							$nodeValue = $child->nodeValue;
							$domNode->setAttribute("GUIcustom_".$nodeName, $nodeValue);
						}
						$keyElemsCnt++;
					} elseif ($attr->nodeName == "xc:operation" && $attr->nodeValue == "create") {
						$keyElemIndex++;
						$isCreated = true;
					}
				}
			}

			if ((!$isKey && !$isCreated) || $leaveKey == false) {
				try {
					$childrenRemains = 0;

					// recursively check all children for their key elements
					if (sizeof($child->childNodes) && $recursive) {
						foreach ($child->childNodes as $chnode) {
							if (sizeof($chnode->childNodes)) {
								$childrenRemains += $this->removeChildrenExceptOfKeyElements($chnode, $chnode->childNodes, $leaveKey, $recursive);
							}
						}
					}

					if ($childrenRemains == 0) {
						$domNode->removeChild($child);
					} else {
						$keyElemIndex++;
					}

				} catch (\DOMException $e) {

				}
			}
		}

		if ($domNode->hasAttributes()) {
			foreach ($domNode->attributes as $attr) {
				if (strpos($attr->nodeName, "GUIcustom_") !== 0) {
					$attributesArr[] = $attr->nodeName;
				}
			}
			// remove must be in new foreach, previous deletes only first one
			foreach ($attributesArr as $attrName) {
				$domNode->removeAttribute($attrName);
			}
		}
		return $keyElemsCnt;
	}

	/**
	 * inserts new element into given XML tree
	 *
	 * @param  \SimpleXMLElement $configXml xml file
	 * @param  string            $xpath     XPath to the element (without initial /)
	 * @param  string            $label     label value
	 * @param  string            $value     new value
	 * @param  string            $xPathPrefix
	 * @param bool               $addCreateNS
	 *
	 * @return array             array of \SimpleXMLElement modified node, which is always first response
	 */
	public function insertNewElemIntoXMLTree(&$configXml, $xpath, $label, $value, $xPathPrefix = "xmlns:", $addCreateNS = true)
	{
		/**
		 * get node according to xPath query
		 * @var \SimpleXMLElement $node
		 * @var \SimpleXMLElement $elem
		 * @var \SimpleXMLElement $elemModel
		 */
		$node = $configXml->xpath('/'.$xPathPrefix.$xpath);
		$retArr = array();

		if ($value === "" || $value === false) {
			$elem = $node[0]->addChild($label);
		} else {
			$elem = $node[0]->addChild($label, $value);
		}
		$elemIndex = sizeof($node[0]->children());

		if ($addCreateNS) {
			$elem->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");
		}

		array_push($retArr, $elem);

		// we have to check new insterted element model (load model to XML)
		$xml = $configXml->asXML();
		$xml = $this->mergeXMLWithModel($xml);
		$tmpXml = simplexml_load_string($xml);
		if (isset($configXml->getNamespaces()[""])) {
			$tmpXml->registerXPathNamespace(str_replace(":", "", $xPathPrefix), $configXml->getNamespaces()[""]);
		}
		$elemWithModel = $tmpXml->xpath('/'.$xPathPrefix.$xpath.'/*['.$elemIndex.']');

		/* We don't want to auto generate leaf-ref now
		if ($elemWithModel[0]) {
			$elemModel = $elemWithModel[0];
			$leafRefPath = "";
			$isLeafRef = false;
			foreach ($elemModel->attributes() as $key => $val) {
				if ($key == 'type' && $val[0] == 'leafref') {
					$isLeafRef = true;
				} elseif ($key == 'leafref-path') {
					$leafRefPath = $val[0];
				}

				if ($isLeafRef && $leafRefPath != "") {
					$refElem = $this->addElementRefOnXPath($configXml, (string)$elem[0], $leafRefPath, $xPathPrefix);
					if ($refElem instanceof \SimpleXMLElement) {
						array_push($retArr, $refElem);
					}
					break;
				}
			}
		}
		*/

		return $retArr;
	}

	/**
	 * @param \SimpleXMLElement       $configXml
	 * @param        $refValue      value to add
	 * @param        $leafRefPath   xpath to target ref
	 * @param string $xPathPrefix
	 * @param bool   $addCreateNS
	 *
	 * @return \SimpleXMLElement|bool     first added element (root of possible subtree)
	 */
	public function addElementRefOnXPath(&$configXml, $refValue, $leafRefPath, $xPathPrefix = "xmlns:", $addCreateNS = true) {

		// check if target leaf ref does not exists already (we don't have to add add)
		$xpath = str_replace("/", "/xmlns:", $leafRefPath);
		$targets = $configXml->xpath($xpath);
		if (sizeof($targets)) {
			foreach ($targets as $target) {
				$val = (string)$target;
				if ($val == $refValue) {
					return true;
				}
			}
		}

		// start with first part of xpath
		$pathLevels = explode('/', $leafRefPath);
		$xpath = "";

		$currentRoot = $configXml->xpath("/xmlns:*");

		// go throug all xpath parts and check, if element already exists
		for ($i = 0; $i < sizeof($pathLevels); $i++) {
			$path = $pathLevels[$i];
			if ($path == '') continue;

			$xpath .= "/".$xPathPrefix.$path;
			$elem = $configXml->xpath($xpath);

			// remove all children from root element
			$isLastElement = ($i == sizeof($pathLevels) - 1);
			if (sizeof($elem) && ($i == sizeof($pathLevels) - 2)) {
				$domNode = dom_import_simplexml($elem[0]);
				$newdoc = new \DOMDocument;
				$node = $newdoc->importNode($domNode, true);
				$newdoc->appendChild($node);
				$this->removeChildrenExceptOfKeyElements($node, $node->childNodes, $leaveKey = false);

				$newConfigXml = simplexml_import_dom($node);
				$newConfigXml->registerXPathNamespace(str_replace(":", "", $xPathPrefix), $configXml->getNamespaces()[""]);
				$configXml = $newConfigXml;
				$elem = $configXml->xpath($xpath);
			}

			// if element does not exists, create one
			if (!sizeof($elem)) {

				// last elem does not exists, create new one
				$elem = $currentRoot[0]->addChild($path);

				if ($addCreateNS) {
					$elem->addAttribute("xc:operation", "create", "urn:ietf:params:xml:ns:netconf:base:1.0");
				}

				if (!isset($firstAddedElement)) {
					$firstAddedElement = $elem;
				}

				$currentRoot = $elem;
			}

			// set correct ref value to last element
			if ($isLastElement) {
				$elem[0] = $refValue;
			}

			$currentRoot = $elem;

		}

		if (!isset($firstAddedElement)) {
			$firstAddedElement = $currentRoot;
		}

		return ($firstAddedElement[0] instanceof \SimpleXMLElement) ? $firstAddedElement[0] : $firstAddedElement;
	}

	/**
	 * removes node from config XML tree
	 *
	 * @param  int  $key session key of current connection
	 * @param  array $configParams    array of config params
	 * @throws \ErrorException  when get-config could not be loaded
	 * @return int       result code
	 */
	public function handleRemoveNodeForm(&$key, $configParams) {
		$post_vals = $this->container->get('request')->get('removeNodeForm');
		$res = 0;

		try {
			if ( ($originalXml = $this->dataModel->handle('getconfig', $configParams, true)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debugging
				if ($this->container->getParameter('kernel.environment') == 'dev') {
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/original.yin', $tmpConfigXml->asXml());
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
					@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/removeNode.yin', $tmpConfigXml->asXml());
				}
				$res = $this->executeEditConfig($key, $deleteTree->asXml(), $configParams['source']);
				if ($res == 0) {
					$this->container->get('request')->getSession()->getFlashBag()->add('success', "Record has been removed.");
				}
			} else {
				throw new \ErrorException("Could not load config.");
			}
		} catch (\ErrorException $e) {
			$this->logger->warn('Could not remove node correctly.', array('error' => $e->getMessage()));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not remove node correctly. ".$e->getMessage());
		}

		return $res;

	}

	/**
	 * sends modified XML to server
	 *
	 * @param  int    $key    session key of current connection
	 * @param  string $config XML document which will be send
	 * @param  string $target = "running" target source
	 *
	 * @param array   $additionalParams
	 *
	 * @return int              return 0 on success, 1 on error
	 */
	private function executeEditConfig($key, $config, $target = "running", $additionalParams = array()) {
		$res = 0;
		$editConfigParams = array(
			'key' 	 => $key,
			'target' => $target,
			'config' => str_replace('<?xml version="1.0"?'.'>', '', $config)
		);
		$editConfigParams = array_merge($editConfigParams, $additionalParams);

		// edit-cofig
		if ( ($merged = $this->dataModel->handle('editconfig', $editConfigParams)) != 1 ) {
			// for debugging purposes, we will save result into the temp file
			if ($this->container->getParameter('kernel.environment') == 'dev') {
				@file_put_contents($this->container->get('kernel')->getRootDir().'/logs/tmp-files/merged.yin', $merged);
			}
		} else {
			$this->logger->err('Edit-config failed.', array('params', $editConfigParams));
			// throw new \ErrorException('Edit-config failed.');
			$res = 1;
		}
		return $res;
	}

	/**
	 * Removes <?xml?> header from text.
	 *
	 * @param   string &$text  string to remove XML header in
	 * @return  mixed         returns an array if the subject parameter
	 *                        is an array, or a string otherwise.	If matches
	 *                        are found, the new subject will be returned,
	 *                        otherwise subject will be returned unchanged
	 *                        or null if an error occurred.
	 */
	public function removeXmlHeader(&$text) {
		return preg_replace("/<\?xml .*\?".">/i", "", $text);
	}

	/**
	 * @return \SimpleXMLElement|false
	 */
	public function loadModel() {
		$notEditedPath = $this->dataModel->getModelsDir();
		$path = $this->dataModel->getPathToModels();
		$modelFile = $path . 'wrapped.wyin';
		$res = false;

		$this->logger->info("Trying to find model in ", array('pathToFile' => $modelFile));
		if ( file_exists($modelFile) ) {
			$this->logger->info("Model found in ", array('pathToFile' => $modelFile));
			if ( $path != $notEditedPath ) {
				try {
					$res = simplexml_load_file($modelFile);
				} catch (\ErrorException $e) {
					$this->logger->err("Could not load model");
				}
			}
		} else {
			// TODO: if is not set module direcotory, we have to set model to merge with, maybe custom model?
			$this->logger->warn("Could not find model in ", array('pathToFile' => $modelFile));
		}
		return $res;
	}

	/**
	 * Merge given XML with data model
	 *
	 * @param string $xml            XML string
	 * @return array|false    false on error, merged array on success
	 * @param array             $params   modification parameters for merge
	 */
	public function mergeXMLWithModel(&$xml, $params = array()) {
		// load model
		$model = $this->loadModel();
		$res = false;

		if ($model !== false) {
			try {
				$res = $this->mergeWithModel($model, $xml, $params);
			} catch (\ErrorException $e) {
				// TODO
				$this->logger->err("Could not merge with model:", array('error' => $e->getMessage()));
			}
		}
		return $res;
	}


/**
 * Check, if XML response is valid.
 *
 * @param string            &$xmlString       xml response
 * @return bool
 */
public function isResponseValidXML(&$xmlString) {
	$e = false;
	try {
		@$simpleXMLRes = simplexml_load_string($xmlString);
	} catch (\ErrorException $e) {
		// Exception will be handled bellow
	}
	if ( (isset($simpleXMLRes) && $simpleXMLRes === false) || $e !== false) {
		// sometimes is exactly one root node missing
		// we will check, if is not XML valid with root node
		$xmlString = "<".self::$customRootElement.">".$xmlString."</".self::$customRootElement.">";
		try {
			@$simpleXMLRes = simplexml_load_string($xmlString);
			if (!($simpleXMLRes instanceof \SimpleXMLElement)) {
				return false;
			}
		} catch (\ErrorException $e) {
			return false;
		}
	}
	return true;
}

/**
 * Get parent for element.
 *
 * @param $element
 * @return bool|\SimpleXMLElement
 */
public function getElementParent($element) {

	$parentsChoice = $element->xpath("parent::*[@eltype='case']/parent::*[@eltype='choice']/parent::*");
	if (sizeof($parentsChoice)) {
		return $parentsChoice[0];
	}

	$parents = $element->xpath("parent::*");
	if ($parents) {
		return $parents[0];
	}
	return false;
}

/**
 * Check if two elements match.
 *
 * @param $model_el       element from model
 * @param $possible_el    element to match the model
 * @return bool
 */
public function checkElemMatch($model_el, $possible_el) {
	$mel = $this->getElementParent($model_el);
	$pel = $this->getElementParent($possible_el);

	if ($mel instanceof \SimpleXMLElement && $pel instanceof \SimpleXMLElement) {
		while ($pel && $mel) {
			if ($pel->getName() !== $mel->getName()) {
				return false;
			}
			$pel = $this->getElementParent($pel);
			$mel = $this->getElementParent($mel);
		}
		return true;
	} else {
		return false;
	}
}

	/**
	 * Completes tree structure for target element.
	 *
	 * $params['attributesWhiteList'] = set array of white listed attributes to add
	 *                                  default empty array() - add all
	 *
	 * @param \SimpleXMLElement $source
	 * @param \SimpleXMLElement $target
	 * @param array             $params   modification parameters for merge
	 */
public function completeAttributes(&$source, &$target, $params = array()) {
	if (isset($params['attributesWhiteList']) && sizeof($params['attributesWhiteList'])) {
		$filterAttributes = $params['attributesWhiteList'];
	}
	if ($source->attributes()) {
		$attrs = $source->attributes();
//		var_dump($source->getName());
		if (in_array($attrs["eltype"], array("leaf","list","leaf-list", "container", "choice", "case"))) {
			foreach ($source->attributes() as $key => $val) {

				// skip attributes which are not in whitelist
				if (isset($filterAttributes) && !in_array($key, $filterAttributes)) continue;

				try {
					@$target->addAttribute($key, $val);
				} catch (\ErrorException $e) {
//					$this->logger->addWarning('Error in adding attributes: ', array('error' => $e->getMessage()));
				}

			}
		}
	}
}

/**
 * Find corresponding $el in configuration model $model and complete attributes from $model.
 *
 * @param  \SimpleXMLElement &$model with data model
 * @param  \SimpleXMLElement $el     with element of response
 * @param array              $params   modification parameters for merge
 */
public function findAndComplete(&$model, $el, $params = array()) {
	$modelns = $model->getNamespaces();
	$model->registerXPathNamespace("c", $modelns[""]);
	$found = $model->xpath("//c:". $el->getName());

	if (sizeof($found) == 1) {
		$this->completeAttributes($found[0], $el, $params);
	} else {
//		echo "Not found unique<br>";
		foreach ($found as $found_el) {
			if ($this->checkElemMatch($found_el, $el)) {
				$this->completeAttributes($found_el, $el, $params);
				break;
			}
		}
	}
}

/**
 * Go through $root_el tree that represents the response from Netconf server.
 *
 * @param  \SimpleXMLElement &$model  with data model
 * @param  \SimpleXMLElement $root_el with element of response
 * @param array              $params   modification parameters for merge
 */
public function mergeRecursive(&$model, $root_el, $params = array()) {
		if ($root_el->count() == 0) {
			$this->findAndComplete($model, $root_el, $params);
			// TODO: repair merge with root element (no parents)
		}

		foreach ($root_el as $ch) {
			$this->findAndComplete($model, $ch, $params);
			$this->mergeRecursive($model, $ch, $params);
		}

		foreach ($root_el->children as $ch) {
			$this->findAndComplete($model, $ch, $params);
			$this->mergeRecursive($model, $ch, $params);
		}
	}

	/**
	 * Add attributes from configuration model to response such as config, mandatory, type.
	 *
	 * @param  \SimpleXMLElement  $model 	data configuration model
	 * @param  string             $result data from netconf server
	 * @return string								      the result of merge
	 * @param array               $params   modification parameters for merge
	 */
	public function mergeWithModel($model, $result, $params = array()) {
		if ($result) {
			$resxml = simplexml_load_string($result);

			$this->mergeRecursive($model, $resxml, $params);

			return $resxml->asXML();
		} else {
			return $result;
		}
	}

	/**
	 * Validates input string against validation files saved in models directory.
	 * For now, only two validation step are set up - RelaxNG (*.rng) and Schema (*.xsd)
	 *
	 * @param string $xml   xml string to validate with RelaxNG and Schema, if available
	 * @return bool
	 */
	public function validateXml($xml) {
		$finder = new Finder();
		$domDoc = new \DOMDocument();
		$xml = "<mynetconfbase:data  xmlns:mynetconfbase='urn:ietf:params:xml:ns:netconf:base:1.0'>".$xml."</mynetconfbase:data>";
		$domDoc->loadXML($xml);

		$iterator = $finder
				->files()
				->name("/.*data\.(rng|xsd)$/")
				->in($this->dataModel->getPathToModels());

		try {
			foreach ($iterator as $file) {
				$path = $file->getRealPath();
				if (strpos($path, "rng")) {
					try {
						if (!@$domDoc->relaxNGValidate($path)) {
							return false;
						}
					} catch (\ContextErrorException $e) {
						$this->logger->addWarning($e->getMessage());
						return false;
					}
				} else if (strpos($path, "xsd")) {
					try {
						if (!@$domDoc->schemaValidate($path)) {
							return false;
						}
					} catch (\ContextErrorException $e) {
						$this->logger->addWarning($e->getMessage());
						return false;
					}
				}
			}
		} catch (\ErrorException $e) {
			$this->logger->addWarning("XML is not valid.", array('error' => $e->getMessage(), 'xml' => $xml, 'RNGfile' => $path));
			return false;
		}

		return true;

	}

	/**
	 * loads available values for element from model
	 *
	 * @param $formId     unique form identifier (for caching response)
	 * @param $xPath
	 *
	 * @return array
	 */
	public function getAvailableLabelValuesForXPath($formId, $xPath) {

		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');

		if ($cache->contains('getResponseForFormId_'.$formId)) {
			$xml = $cache->fetch('getResponseForFormId_'.$formId);
		} else {
			$xml = $this->loadModel()->asXML();
			$cache->save('getResponseForFormId_'.$formId, $xml, 1000);
		}

		$labelsArr = array();
		$attributesArr = array();
		$elemsArr= array();
		if ($xml !== false) {
			$dom = new \DOMDocument();
			$dom->loadXML($xml);

			$decodedXPath = str_replace("/", "/xmlns:", $this->decodeXPath($xPath))."/*";
			if (strpos($xPath, '----') !== false) {
				// we have to correct xpath selector if xpath start with '//'
				$decodedXPath = str_replace('xmlns:/', '/', $decodedXPath);
			} else {
				// we have to remove all array selectors [D]
				$decodedXPath = preg_replace('/\[[0-9]+\]/', '', $decodedXPath);
				// we have to add one level for "module" (root) element, which in model in addition to getconfig response
				$decodedXPath = '/xmlns:*'.$decodedXPath;
			}
			$domXpath = new \DOMXPath($dom);

			$context = $dom->documentElement;
			foreach( $domXpath->query('namespace::*', $context) as $node ) {
				$domXpath->registerNamespace($node->nodeName, $node->nodeValue);
			}

			$elements = $domXpath->query($decodedXPath);

			if (!is_null($elements)) {
				foreach ($elements as $element) {
					$isChoice = $isConfig = false;
					$elemsArr[$element->nodeName] = simplexml_import_dom($element, 'SimpleXMLIterator');
					if ($element->hasAttributes()) {
						foreach ($element->attributes as $attr) {
							// if element is choice, we should load case statements bellow
							if ($attr->nodeName == "eltype" && $attr->nodeValue == "choice") {
								$isChoice = true;
							} else if ($attr->nodeName == "config" && $attr->nodeValue == "true") {
								$isConfig = true;
							}
							$attributesArr[$element->nodeName][$attr->nodeName] = $attr->nodeValue;
						}
					}

					if (!$isConfig) {
						continue;
					}
					
					// load case statement (children of element choice)
					if ($isChoice) {
						if ($element->hasChildNodes()) {
							foreach ($element->childNodes as $child) {
								$isAllowedEltype = $isConfig = false;

								if ($child->hasAttributes()) {
									foreach ($child->attributes as $attr) {
										$isSubCase = false;

										// check if is confing
										if ($attr->nodeName == "config" && $attr->nodeValue == "true") {
											$isConfig = true;
										}

										// load only available elementtypes
										if ($attr->nodeName == "eltype" && in_array($attr->nodeValue, array('case', 'container', 'leaf', 'leaf-list', 'list'))) {
											$isAllowedEltype = true;

											// if its case statement, try to load child with same name and complete its attributes
											if ($attr->nodeValue == "case" && $child->hasChildNodes()) {
												foreach ($child->childNodes as $subchild) {
													if ($subchild->nodeName == $child->nodeName && $subchild->hasAttributes()) {
														$isSubCase = true;
														foreach ($subchild->attributes as $attr) {
															$attributesArr[$child->nodeName][$attr->nodeName] = $attr->nodeValue;
														}
													}
												}
											}
										}
										if (!$isSubCase) {
											$attributesArr[$child->nodeName][$attr->nodeName] = $attr->nodeValue;
										}
										if ($isAllowedEltype && $isConfig) {
											array_push($labelsArr, $child->nodeName);
											break;
										}
									}
								}
							}
						}
					} else {
						array_push($labelsArr, $element->nodeName);
					}

				}
			}
		}
		$labelsArr = array_values(array_unique($labelsArr));

		$retArr['labels'] = $labelsArr;
		$retArr['labelsAttributes'] = $attributesArr;
		$retArr['elems'] = $elemsArr;
		return $retArr;
	}

	/**
	 * @param \SimpleXMLIterator $element
	 * @param \Twig_Template     $template
	 *
	 * @param string             $formId
	 * @param string             $xPath
	 * @param string             $requiredChildren
	 * @param array              $identityRefs
	 *
	 * @return array|bool
	 */
	public function getChildrenValues($element, $template, $formId, $xPath = "", $requiredChildren = "", $identityRefs = array()) {
		$retArr = array();
		$targetAttributes = array('key', 'iskey', 'mandatory');

		foreach ($element as $label => $el) {
			$attributesArr = array_fill_keys($targetAttributes, false);

			foreach ($element->attributes() as $name => $attr) {
				if ($name == "key") {
					$attributesArr[$name] = (string)$attr[0];
				}
			}

			foreach ($el->attributes() as $name => $attr) {
				if (in_array($name, array('iskey', 'mandatory'))) {
					$attributesArr[$name] = (string)$attr[0];
				}
			}

			if ( (($attributesArr['iskey'] !== "true" && $attributesArr['key'] == false)
					||
					($requiredChildren !== "" && $label != $requiredChildren))
					&& $attributesArr['mandatory'] == false
			) {
				continue;
			}

			if ($attributesArr['key'] !== false) {
				$requiredChildren = $attributesArr['key'];
			} else {
				$requiredChildren = "";
			}

			$twigArr = array();
			$twigArr['key'] = "";
			$twigArr['xpath'] = "";
			$twigArr['element'] = $el;
			$twigArr['useHiddenInput'] = true;
			$twigArr['moduleIdentityRefs'] = $identityRefs;

			$newXPath = $xPath . "/*";
			$res = $this->getAvailableLabelValuesForXPath($formId, $newXPath);

			$retArr[$label] = array();
			if (isset($res['labelsAttributes'][$label])) {
				$retArr[$label]['labelAttributes'] = $res['labelsAttributes'][$label];
			}
			$retArr[$label]['valueElem'] = $this->removeMultipleWhitespaces($template->renderBlock('configInputElem', $twigArr));
			$retArr[$label]['children'] = $this->getChildrenValues($el, $template, $formId, $newXPath, $requiredChildren);
		}

		return sizeof($retArr) ? $retArr : false;
	}

	public function removeMultipleWhitespaces($str) {
		return preg_replace( "/\s+/", " ", $str );
	}
}
