<?php

namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use FIT\NetopeerBundle\Models\XMLoperations;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DefaultController extends BaseController
{

	private $paramsState, $paramsConfig;
	private $filterForms;

	/**
	 * @Route("/", name="_home")
	 * @Template()
	 *
	 * Prepares form for connection to the server and table with active
	 * connection list
	 */
	public function indexAction()
	{
		// DependencyInjection (DI) - defined in Resources/config/services.yml
		$dataClass = $this->get('DataModel');

		// build form for connection to the server
		$form = $this->createFormBuilder()
			->add('host', 'text')
			->add('port', 'number', array('attr' => array('value' => '22')))
			->add('user', 'text')
			->add('password', 'password')
			->getForm();
		$this->assign('form', $form->createView());

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
					),
				);

				// state flash = state -> left column in the layout
				$dataClass->setFlashState('state');
				$res = $dataClass->handle("connect", $params);

				// if connection is broken (Could not connect)
				if ( $res = 1 ) {
					// redirect back to the connection page
					return $this->redirect($this->generateUrl('_home'));
				}

				$this->getRequest()->getSession()->setFlash('state success', 'Form had been filled up correctly.');
				return $this->redirect($this->generateUrl('_home'));
			} else {
				$this->getRequest()->getSession()->setFlash('state error', 'You have not filled up form correctly.');
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
	 * @Route("/changeColumnLayout/{newValue}/", name="changeColumnLayout")
	 *
	 * Change session value for showing single or double column layout
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
	 * @Route("/handle/{command}/{key}/", name="handleConnection")
	 *
	 * Handle actions and execute them in Models/Data
	 */
	public function handleConnectionAction($command, $key)
	{
		$dataClass = $this->get('DataModel');
		$params = array(
			'key' => $key,
			'filter' => ''
		);

		if ( ($command === "get") || ($command  === "info") ) {
			$dataClass->setFlashState('state');
		} else {
			$dataClass->setFlashState('config');
		}

		$res = $dataClass->handle($command, $params);
		// if something goes wrong, we will redirect to connections page
		if ( $res != 1 ) {
			return $this->redirect($this->generateUrl('section', array('key' => $key)));
		}

		if ( in_array($command, array("connect", "disconnect")) ) {
			return $this->redirect($this->generateUrl('_home'));
		} else {
			$url = $this->get('request')->headers->get('referer');
			return new RedirectResponse($url);
		}
	}

	/**
	 * @Route("/info-page/{key}/{action}/", name="infoPage")
	 * @Template("FITNetopeerBundle:Default:section.html.twig")
	 *
	 * Shows info page with informatins
	 */
	public function sessionInfoAction($key, $action)
	{
		$dataClass = $this->get('DataModel');
		parent::setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		if ( $action == "session" ) {
			$session = $this->container->get('request')->getSession();
			$sessionArr = $session->all();

			$xml = XMLoperations::createXML("session", $sessionArr);
			$xml = simplexml_load_string($xml->saveXml(), 'SimpleXMLIterator');

			$this->assign("stateArr", $xml);
		}

		$this->assign('singleColumnLayout', true);
		// because we do not allow changing layout, controls will be hidden
		$this->assign('hideColumnControl', true);

		$routeParams = array('key' => $key, 'module' => null, 'subsection' => null);
		$this->assign('routeParams', $routeParams);
		$this->assign('activeAction', $action);
		return $this->getTwigArr();
	}

	/**
	 * @Route("/sections/{key}/", name="section")
	 * @Route("/sections/{key}/{module}/", name="module")
	 * @Route("/sections/{key}/{module}/{subsection}/", name="subsection")
	 * @Template("FITNetopeerBundle:Default:section.html.twig")
	 *
	 * Prepares section = whole get&get-config part of server
	 * Shows module part = first level of connected server (except of root)
	 * Prepares subsection = second level of connected server tree
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$dataClass = $this->get('DataModel');
		parent::setActiveSectionKey($key);

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $this->loadFilters($module, $subsection);
		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		// if form has been send, we well proccess it
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
			$dataClass->buildMenuStructure($key);
			$modules = $dataClass->getModels();
			if (isset($modules[0])) {
				$module1st = $modules[0];
				if (!isset($module1st["params"]["module"])) {
					/* ERROR - wrong structure of model entry */
					$this->get('data_logger')
						->err("Cannot get first model (redirect to 1st tab).",
						array("message" => "\$module1st[\"params\"][\"module\"] is not set"));
				}
				$retArr['module'] = $module1st["params"]["module"];
				return $this->redirect($this->generateUrl($routeName, $retArr));
			}
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
			$host = unserialize($connArray[$key]);
			$this->assign('sectionName', $host->host);

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
	 * @param  string &$module     module name
	 * @param  string &$subsection subsection name
	 * @return array             array with config and state filter
	 */
	private function loadFilters(&$module, &$subsection) {
		// if file filter.txt exists in models, we will use it
		$filterState = $filterConfig = "";
		$add2path = $module;

		// if subsection is defined, we will add it to path
		if ( $subsection ) {
			$add2path .= '/'.$subsection;
		}

		$file = __DIR__.'/../Data/models/'.$add2path.'/filter.txt';

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
	 * @param $key   key of associative array
	 * @param $value value of associative array
	 */
	private function setStateParams($key, $value) {
		$this->paramsState[$key] = $value;
	}

	/**
	 * Set values of config array
	 * @param $key   key of associative array
	 * @param $value value of associative array
	 */
	private function setConfigParams($key, $value) {
		$this->paramsConfig[$key] = $value;
	}

	/**
	 * Set default values to config and state arrays
	 * @param {int} $key     	key of connected server
	 * @param $filterState		state filter
	 * @param $filterConfig 	config filter
	 * @param $sourceConfig 	source param of config
	 */
	private function setSectionFormsParams($key, $filterState = "", $filterConfig = "", $sourceConfig = "") {

		// if isset sourceConfig in session and we haven't set it in method call
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
	 * @param  {int} $key        key of connected server
	 * @param  {strign} $module     =             null module name
	 * @param  {string} $subsection =             null subsection name
	 * @return redirect to page, which was this method called from
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

		// processing remove node form
		} elseif ( is_array($this->getRequest()->get('removeNodeForm')) ) {
			$res = $this->handleRemoveNodeForm($key);
		}

		// we will redirect page after completition, because we want to load edited get and get-config
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
		return $this->redirect($this->generateUrl($routeName, $retArr));
	}

	/**
	 * devides string into the array (name, value) (according to the XML tree node => value)
	 * @param  {string} $postKey post value
	 * @return {array}           modified array
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
	 * @param  {string} $value encoded XPath string
	 * @return {string}        decoded XPath string
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
	 * @param  {int} $key    				session key of current connection
	 * @param  {string} $config 			XML document which will be send
	 * @param  {string} $target = "running" target source
	 * @return {int}						return 0 on success
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
			// for debuggind purposes, we will save result into the temp file
			file_put_contents(__DIR__.'/../Data/models/tmp/merged.yin', $merged);
		} else {
			$this->get('logger')->err('Edit-config failed.', array('params', $editConfigParams));
			// throw new \ErrorException('Edit-config failed.');
			$res = 1;
		}
		return $res;
	}

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
	 * @param  {simpleXmlElement} $configXml   config XML
	 * @param  {string} $elementName name of the element
	 * @param  {string} $xpath       XPath to the element
	 * @param  {string} $val         new value
	 * @return {SimpleXMLElement} 	 modiefied node
	 */
	private function elementValReplace(&$configXml, $elementName, $xpath, $val)
	{
		$isAttribute = false;

		// if element is an attribute, it will have prefix at-
		if ( strrpos($elementName, 'at-') === 0 ) {
			$elementName = substr($elementName, 3);
			$isAttribute = true;
		}

		// get node according to xPath query
		$node = $configXml->xpath('/xmlns:'.$xpath);

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
	 * @param  {int} &$key session key for current server
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
	 * @param  {int} &$key session key for current server
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
	 * @param  {int} &$key session key of current connection
	 * @return {int}       result code
	 */		
	private function handleEditConfigForm(&$key) {
		$post_vals = $this->getRequest()->get('configDataForm');
		$res = 0;
		$this->get('DataModel')->setFlashState('config');

		try {

			if ( ($configXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$configXml = simplexml_load_string($configXml, 'SimpleXMLIterator');

				// save to temp file - for debuggind
				file_put_contents(__DIR__.'/../Data/models/tmp/original.yin', $configXml->asXml());

				// we will get namespaces from original getconfig and set them to simpleXml object, 'cause we need it for XPath queries
				$xmlNameSpaces = $configXml->getNamespaces();
				if ( isset($xmlNameSpaces[""]) ) {
					$configXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
				}

				// foreach over all post values
				foreach ( $post_vals as $postKey => $val ) {
					$values = $this->divideInputName($postKey);
					$elementName = $values[0];
					$xpath = $this->decodeXPath($values[1]);
					$xpath = substr($xpath, 1); // removes slash at the begining

					$this->elementValReplace($configXml, $elementName, $xpath, $val);
				}

				// for debuggind, edited configXml will be saved into temp file
				file_put_contents(__DIR__.'/../Data/models/tmp/edited.yin', $configXml->asXml());

				$res = $this->executeEditConfig($key, $configXml->asXml());
				$this->get('session')->setFlash('config success', "Config has been edited successfully.");
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
	 * could be changed by user
	 * @param  {int} &$key session key of current connection
	 * @return {int}       result code
	 */
	private function handleDuplicateNodeForm(&$key)	{
		$post_vals = $this->getRequest()->get('duplicatedNodeForm');
		$res = 0;
		$this->get('DataModel')->setFlashState('config');

		try {
			// nacteme originalni (nezmeneny) getconfig
			if ( ($originalXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debuggind
				file_put_contents(__DIR__.'/../Data/models/tmp/original.yin', $tmpConfigXml->asXml());

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
								$createString = "\n".str_replace('<?xml version="1.0"?'.'>', '', $node->asXml());
							}
						} catch (\ErrorException $e) {
							// nothing happeds - attribute is already there
						}
					}
				}
				$createTree = $this->completeRequestTree($parentNode[0], $createString);
				// for debuggind, edited configXml will be saved into temp file
				file_put_contents(__DIR__.'/../Data/models/tmp/newElem.yin', $createTree->asXml());
				$res = $this->executeEditConfig($key, $createTree->asXml());

				$this->getRequest()->getSession()->setFlash('config success', "Record has been added.");
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
	 * could be changed by user
	 * @param  {int} &$key 				session key of current connection
	 * @param  {strign} $module 		module name
	 * @param  {string} $subsection  	subsection name
	 * @return {int}       result code
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
	 * removes node from config XML tree
	 * @param  {int} &$key session key of current connection
	 * @return {int}       result code
	 */
	private function handleRemoveNodeForm(&$key) {
		$post_vals = $this->getRequest()->get('removeNodeForm');
		$res = 0;

		try {
			if ( ($originalXml = $this->get('DataModel')->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
				$tmpConfigXml = simplexml_load_string($originalXml);

				// save to temp file - for debuggind
				file_put_contents(__DIR__.'/../Data/models/tmp/original.yin', $tmpConfigXml->asXml());

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

				// for debuggind, edited configXml will be saved into temp file
				file_put_contents(__DIR__.'/../Data/models/tmp/removeNode.yin', $tmpConfigXml->asXml());
				$this->executeEditConfig($key, $tmpConfigXml->asXml());

				$this->getRequest()->getSession()->setFlash('config success', "Record has been removed.");
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
