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

	/**
	 * @Route("/", name="_home")
	 * @Template()
	 *
	 * Prepares form for connection to the server and table with active
	 * connection list
	 */
	public function indexAction()
	{
		$dataClass = $this->get('DataModel');
		
		// formular pro pripojeni k serveru
		$form = $this->createFormBuilder()
			->add('host', 'text')
			->add('port', 'number', array('attr' => array('value' => '22')))
			->add('user', 'text')
			->add('password', 'password')
			->getForm();
		$this->assign('form', $form->createView());

		// zpracovani formulare pro pripojeni k serveru
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

				$dataClass->setFlashState('state');
				$res = $dataClass->handle("connect", $params);

				if ( $res = 1 ) {
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
		return $this->getTwigArr($this);
	}

	/**
	 * @Route("/changeColumnLayout/{newValue}/", name="changeColumnLayout")
	 *
	 * Change session value for showing single column layout
	 */
	public function changeColumnLayoutAction($newValue)
	{
		$this->get('session')->set('singleColumnLayout', $newValue);

		try {
			parent::clearCache();
		} catch (\ErrorException $e) {
			$this->getRequest()->getSession()->setFlash('single error', "Could not change number of columns.");
		}

		//reconstructs a routing path and gets a routing array called $route_params
        $url = $this->get('request')->headers->get('referer');
        return new RedirectResponse($url);
	}

	/**
	 * @Route("/handle/{command}/{key}/", name="handleConnection")
	 *
	 * Handle actions from Models/Data
	 */
	public function handleConnectionAction($command, $key)
	{
		$dataClass = $this->get('DataModel');
		$params = array(
			'key' => $key,
			'filter' => ''
		);

		if ( $command === "get" ) {
			$dataClass->setFlashState('state'); 
		} else {
			$dataClass->setFlashState('config');    		   		
		}

		$res = $dataClass->handle($command, $params);
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
		if ( $module ) {
			parent::setSubmenuUrl($module);
			$this->assign('sectionName', $dataClass->getSectionName($module));
			if ( $subsection ) $this->assign('subsectionName', $dataClass->getSubsectionName($subsection)); 
		} else {
			$connArray = $this->getRequest()->getSession()->get('session-connections');
			$host = unserialize($connArray[$key]);
			$this->assign('sectionName', $host->host);
		}
		
		if ( $module ) {
			// pokud existuje filtr v modelech, pouzijeme jej
			$filterState = $filterConfig = "";
			$add2path = $module;
			if ( $subsection ) $add2path .= '/'.$subsection;
			$file = __DIR__.'/../Data/models/'.$add2path.'/filter.txt';
			if ( file_exists($file) ) {
				$filterState = $filterConfig = file_get_contents($file);
			}
		}

		$this->setSectionFormsParams($key, $filterState, $filterConfig);
		try {
			$dataClass->setFlashState('state');
			// ziskame state cast
			if ( ($xml = $dataClass->handle('get', $this->paramsState)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
				$this->assign("stateArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("State: Could not parse XML file correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('state error', "Could not parse XML file correctly. ");
		}

		try {
			$dataClass->setFlashState('config');
			// ziskame getcofig cast
			if ( ($xml = $dataClass->handle('getconfig', $this->paramsConfig)) != 1 ) {    		
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
				$res = $this->setSectionForms($key);
				if ( $res == 1 ) {
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
				$this->assign("configArr", $xml);
			}    
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("Config: Could not parse XML file correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('config error', "Could not parse XML file correctly. ");
		}

		return $this->getTwigArr($this);
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
	private function setSectionFormsParams($key, $filterState = "", $filterConfig = "", $sourceConfig = 'running') {

		$this->setStateParams('key', $key);
		$this->setStateParams('filter', $filterState);

		$this->setConfigParams('key', $key);
		$this->setConfigParams('source', $sourceConfig);
		$this->setConfigParams('filter', $filterState);
	}

	/**
	 * Prepares state and config forms
	 * @param $key     								key of connected server
	 * @param {SimpleXMLIterator} 	&$configXml 	config XML file
	 */
	private function setSectionForms($key) {
		$dataClass = $this->get('DataModel');
		$res = 0;
		
		// state part
		$formState = $this->createFormBuilder()
			->add('formType', 'hidden', array(
				'data' => 'formState',
			))
			->add('filter', 'text', array(
				'label' => "Filter",
				'required' => false
			))
			->getForm();
		
		// config part
		$formConfig = $this->createFormBuilder()
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
				)
			))
			->getForm();

		if ($this->getRequest()->getMethod() == 'POST') {
			$post_vals = $this->getRequest()->get("form");

			// zpracovani filtru u state casti
			if ( isset($post_vals['formType']) && $post_vals['formType'] == "formState") {
				$dataClass->setFlashState('state');
				$formState->bindRequest($this->getRequest());

				if ( $formState->isValid() ) {
					$this->paramsState = array(
						"key" => $key,
						"filter" => $post_vals["filter"],
					);
					$res = 1;
				} else {
					$this->getRequest()->getSession()->setFlash('error', 'You have not filled up form correctly.');
				}	
			// zpracovani filtru u config casti
			} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formConfig" ) {
				$dataClass->setFlashState('config');
				$formConfig->bindRequest($this->getRequest());

				if ($formConfig->isValid()) {
					$post_vals = $this->getRequest()->get("form");
					$this->paramsConfig = array(
						"key" => $key,
						"filter" => $post_vals["filter"],
						"source" => $post_vals['source'],
					);
					$res = 1;
				} else {
					$this->getRequest()->getSession()->setFlash('error', 'You have not filled up form correctly.');
				}
			// zpracovani formulare (nastaveni) konfiguracni casti
			} elseif ( is_array($this->getRequest()->get('configDataForm')) ) {	
				$post_vals = $this->getRequest()->get('configDataForm');
				
				$dataClass->setFlashState('config');
						
				try {

					if ( ($configXml = $dataClass->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
						$configXml = simplexml_load_string($configXml, 'SimpleXMLIterator');

						// vlozime do souboru - ladici ucely
						file_put_contents(__DIR__.'/../Data/models/tmp/original.yin', $configXml->asXml());

						// z originalniho getconfigu zjistime namespaces a nastavime je k simpleXml objektu, aby bylo mozne pouzivat xPath dotazy
						$xmlNameSpaces = $configXml->getNamespaces();

						if ( isset($xmlNameSpaces[""]) ) {
							$configXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
						}

						// projdeme vsechny odeslane hodnoty formulare
						foreach ( $post_vals as $postKey => $val ) {
							$values = explode('_', $postKey);

							$isAttribute = false;
							$elementName = $values[0];

							// zjistime, jestli se jedna o atribut
							if ( strrpos($elementName, 'at-') === 0 ) {
								$elementName = substr($elementName, 3);
								$isAttribute = true;
							}

							// ziskame originalni xPath = dekodujeme
							$xpath = str_replace(
								array('-', '?', '!'), 
								array('/', '[', ']'),
								$values[1]
							);
							$xpath = substr($xpath, 1);

							// ziskame uzel stromu pomoci xPath
							$node = $configXml->xpath('/xmlns:'.$xpath);
							
							// nastavime mu novou hodnotu
							if ( $isAttribute === true ) {
								$elem = $node[0];
								$elem[$elementName] = $val;
							} else {
								if ( isset($node[0]) ) {
									$elem = $node[0];
								} else { 
									$elem = $node;
								}
								
								$elem[0] = str_replace("\r", '', $val);
							}
						}

						// pro ladici ucely vlozime upravena data do souboru
						file_put_contents(__DIR__.'/../Data/models/tmp/edited.yin', $configXml->asXml());
						
						// nastavime si parametry pro edit-config
						$editConfigParams = array(
							'key' 	 => $key,
							'target' => 'running',
							'config' => $configXml->asXml()
							);

						// provedeme edit-cofig
						if ( ($merged = $dataClass->handle('editconfig', $editConfigParams)) != 1 ) {
							// pro ladici ucely vlozime upravene XML do souboru
							file_put_contents(__DIR__.'/../Data/models/tmp/merged.yin', $merged);
						} else {
							$this->get('logger')->err('In executing EditConfig.', array('params', $editConfigParams));
							throw new \ErrorException('Error in executing EditConfig.');
						}

						$this->getRequest()->getSession()->setFlash('config success', "Config has been saved correctly.");
						$res = 1;
					} else {
						throw new \ErrorException("Could not load config.");
					}

				} catch (\ErrorException $e) {
					$this->get('logger')->warn('Could not save config correctly.', array('error' => $e->getMessage()));
					$this->getRequest()->getSession()->setFlash('config error', "Could not save config correctly. Error: ".$e->getMessage());
				}
			} elseif ( is_array($this->getRequest()->get('duplicatedNodeForm')) ) {
				$post_vals = $this->getRequest()->get('duplicatedNodeForm');
				$dataClass->setFlashState('config');

				try {
					// $this->setSectionFormsParams($key);
					// nacteme originalni (nezmeneny) getconfig
					if ( ($originalXml = $dataClass->handle('getconfig', $this->paramsConfig, false)) != 1 ) {
						$tmpConfigXml = simplexml_load_string($originalXml);

						// vlozime do souboru - ladici ucely
						file_put_contents(__DIR__.'/../Data/models/tmp/original.yin', $tmpConfigXml->asXml());

						// z originalniho getconfigu zjistime namespaces a nastavime je k simpleXml objektu, aby bylo mozne pouzivat xPath dotazy
						$xmlNameSpaces = $tmpConfigXml->getNamespaces();

						if ( isset($xmlNameSpaces[""]) ) {
							$tmpConfigXml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
						}
					}

					// pokud mame konfiguracni XML
					if (isset($tmpConfigXml)) {

						// projdeme vsechny odeslane hodnoty formulare
						$newLeafs = array();

						$i = 0;
						foreach ( $post_vals as $postKey => $val ) {
							$values = explode('_', $postKey);
							// values[0] - label
							// values[1] - encoded xPath

							if ($postKey == 'parent') {
								try {
									// ziskame originalni xPath = dekodujeme
									$xpath = str_replace(
										array('-', '?', '!'), 
										array('/', '[', ']'),
										$val
									);
									$xpath = substr($xpath, 1);
									$xpath = substr($xpath, 0, strripos($xpath, "/"));
									$coverNode = $tmpConfigXml->xpath('/xmlns:'.$xpath);

									// duplicating of node, which we were duplicating using JS. Because we
									// have to use xPath for original node, we will modify original node, 
									// not duplicated!
									$duplicatedNode = $tmpConfigXml->xpath('/xmlns:'.$xpath);
									$dom_thing = dom_import_simplexml($duplicatedNode);
									$dom_node  = dom_import_simplexml($coverNode);
									$dom_new   = $dom_thing->appendChild($dom_node->cloneNode(true));

									$tmpConfigXml  = simplexml_import_dom($dom_new);
								} catch (\ErrorException $e) {
									$this->get('logger')->err('Could not find node for duplicate.', array('exception' => $e));
									throw new \ErrorException("Could not find node for duplicate.");
								}

							} else if ( count($values) != 2 ) {
								$this->get('logger')->err('newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!', array('values' => $values, 'postKey' => $postKey));
								throw new \ErrorException("newNodeForm must contain exactly 2 params, example container_-*-*?1!-*?2!-*?1!");
							} else {	// ziskame originalni xPath = dekodujeme
								$xpath = str_replace(
									array('-', '?', '!'), 
									array('/', '[', ']'),
									$values[1]
								);
								$xpath = substr($xpath, 1, strripos($xpath, "/") - 1);

								// ziskame uzel stromu pomoci xPath
								$node = $tmpConfigXml->xpath('/xmlns:'.$xpath);

								if ( isset($node[0]) ) {
									$elem = $node[0];
								} else { 
									$elem = $node;
								}

								$elem[0] = str_replace("\r", '', $val);
							}
						} 

						// pro ladici ucely vlozime upravena data do souboru
						file_put_contents(__DIR__.'/../Data/models/tmp/newElem.yin', $tmpConfigXml->asXml());

						$this->getRequest()->getSession()->setFlash('config success', "New node has been saved correctly.");	
						$res = 1;
					}

					$res = 0;
					
				} catch (\ErrorException $e) {
					$this->get('logger')->warn('Could not save new node correctly.', array('error' => $e->getMessage()));
					$this->getRequest()->getSession()->setFlash('config error', "Could not save new node correctly. ".$e->getMessage());
				}
			}
		} 
		
		// priradime si vytvorene formulare do sablony
		$this->assign('formState', $formState->createView());
		$this->assign('formConfig', $formConfig->createView());

		return $res;
	}
}

