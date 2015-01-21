<?php
/**
 * Controller for module action = base module controller (which is
 * extended in custom module bundle (for example ModuleDefaultBundle,
 * ModuleXmlBundle...)
 *
 * @file GenerateController.php
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


use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Class ModuleController
 *
 * @package FIT\NetopeerBundle\Controller
 */
class ModuleController extends BaseController {
	/**
	 * @var array   holds configuration of filter form (in both section)
	 */
	private $filterForms;
	private $bundleName;

	/**
	 * base method for getting data for module action
	 *
	 * @param      $bundleName
	 * @param      $key
	 * @param null $module
	 * @param null $subsection
	 *
	 * @return null|RedirectResponse
	 */
	protected function prepareDataForModuleAction($bundleName, $key, $module = null, $subsection = null)
	{
		$dataClass = $this->get('DataModel');
		$this->bundleName = $bundleName;

		if ($this->getRequest()->getSession()->get('isLocking') !== true) {
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'title');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'additionalTitle');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'state');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'leftColumn');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'topPart');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'moduleJavascripts');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'moduleStylesheet');
			$this->assign('historyHref', $this->getRequest()->getRequestUri());
		}
		$this->getRequest()->getSession()->remove('isLocking');
		$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'alerts');

		if ($this->getRequest()->getSession()->get('isAjax') === true) {
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'topMenu');
		}

		if ($dataClass->checkLoggedKeys() === 1) {
			$url = $this->get('request')->headers->get('referer');
			if (!strlen($url)) {
				$url = $this->generateUrl('connections');
			}
			return $this->redirect($url);
		}

		/* Show the first module we have */
		if ( $module == null ) {
			return $this->redirectToFirstModule($key);
		}

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $dataClass->loadFilters($module, $subsection);
		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		/* build correct menu structure for this module */
		$this->setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);
		$this->setModuleOrSectionName($key, $module, $subsection);
		$this->assign('rpcMethods', $this->createRPCListFromModel($module, $subsection));

		// if form has been send, we well process it
		if ($this->getRequest()->getMethod() == 'POST') {
			$this->processSectionForms($key, $module, $subsection);
			// the code below wont be precess, because at the end of processSectionForms
			// is redirect executed
		}

		// we will prepare filter form in column
		$this->setSectionFilterForms($key);
		$this->generateTypeaheadPath($key, $module, $subsection);

		$activeNotifications = $this->getRequest()->getSession()->get('activeNotifications');
		if ( !isset($activeNotifications[$key]) || $activeNotifications[$key] !== true ) {
			$activeNotifications[$key] = true;
			$this->getRequest()->getSession()->set('activeNotifications', $activeNotifications);
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'notifications');
		}


		// loading state part = get Action
		// we will load it every time, because state column will we show everytime
		try {

			if ($module === 'all') {
				$merge = false;
			} else {
				$merge = true;
			}
			$isEmptyModule = false;

			if ( ($xml = $dataClass->handle('get', $this->getStateParams(), $merge)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');

				// we have only root module
				if ($xml->count() == 0) {
					$isEmptyModule = true;
					if ($xml->getName() == 'root') {
						$this->setEmptyModuleForm($this->getRequest()->get('key'));
						$isEmptyModule = false;
						$this->assign('forceShowFormConfig', true);
					}
					$this->assign('isEmptyModule', $isEmptyModule);
					$this->assign('key', $this->getRequest()->get('key'));
					$this->assign('additionalTitle', 'Create empty root element');
					$this->assign('redirectUrl', $this->getRequest()->getRequestUri());
					$this->setEmptyModuleForm($key);
					$template = $this->get('twig')->loadTemplate('FITModuleDefaultBundle:Module:createEmptyModule.html.twig');
					$html = $template->renderBlock('singleContent', $this->getAssignedVariablesArr());

					$this->assign('additionalForm', $html);
				} else {
					$this->assign('showRootElem', true);
				}

				$this->assign("stateArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("State: Could not parse filter correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->getFlashBag()->add('state error', "Could not parse filter correctly. ");
		}

		// load model tree dump
		$modelTree = $dataClass->getModelTreeDump($module);
		if ($modelTree) {
			$this->assign('modelTreeDump', $modelTree);
		}

		// we will load config part only if two column layout is enabled or we are on section (which has two column always)
		$tmp = $this->getConfigParams();
		if ($module == null || ($module != null && $tmp['source'] !== "running" && !$isEmptyModule)) {
			$this->loadConfigArr(false, $merge);
			$this->setOnlyConfigSection();
		} else if ( $module == null || $module == 'all' || ($module != null && $this->get('session')->get('singleColumnLayout') != "true") ) {
			$this->loadConfigArr(true, $merge);
			$this->assign('singleColumnLayout', false);
			if ($module == 'all') {
				$this->assign('hideColumnControl', true);
			}
		} else if ($this->get('session')->get('singleColumnLayout') != "true") {
			$this->loadConfigArr(false, $merge);
			$this->assign('singleColumnLayout', true);
			$this->setOnlyConfigSection();
		} else {
			$conn = $dataClass->getConnFromKey($key);
			if ($conn->getCurrentDatastore() !== "running") {
				$this->loadConfigArr(false, $merge);
				$this->setOnlyConfigSection();
			}
			$this->assign('singleColumnLayout', true);
		}
	}

	/**
	 * Sets section and subsection names for current module or section
	 *
	 * @param $key
	 * @param $module
	 * @param $subsection
	 *
	 * @return null|RedirectResponse
	 */
	protected function setModuleOrSectionName($key, $module, $subsection) {
		$dataClass = $this->get('DataModel');
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
			if (isset($connArray[$key])) {
				$host = unserialize($connArray[$key]);
				$this->assign('sectionName', $host->host);
			} else {
				$this->getRequest()->getSession()->getFlashBag()->add('state error', "You try to load device you are not connected to.");
				return $this->redirect($this->generateUrl("connections", array()));
			}

			// because we do not allow changing layout in section, controls will be hidden
			$this->assign('hideColumnControl', true);
		}

		$routeParams = array('key' => $key, 'module' => $module, 'subsection' => $subsection);
		$this->assign('routeParams', $routeParams);
	}

	/**
	 * generates and assign typeahead placeholder path (for using in JS typeadhead function)
	 *
	 * @param $key
	 * @param $module
	 * @param $subsection
	 */
	protected function generateTypeaheadPath($key, $module, $subsection) {
		// path for creating node typeahead
		$typeaheadParams = array(
				'formId' => "FORMID",
				'key' => $key,
				'xPath' => "XPATH"
		);
		$valuesTypeaheadPath = $this->generateUrl('getValuesForLabel', $typeaheadParams);
		if (!is_null($module)) {
			$typeaheadParams['module'] = $module;
			$valuesTypeaheadPath = $this->generateUrl('getValuesForLabelWithModule', $typeaheadParams);
		}
		if (!is_null($subsection)) {
			$typeaheadParams['subsection'] = $subsection;
			$valuesTypeaheadPath = $this->generateUrl('getValuesForLabelWithSubsection', $typeaheadParams);
		}

		$this->assign('valuesTypeaheadPath', $valuesTypeaheadPath);
	}

	/**
	 * Redirect to first available module or All section if no module is available
	 *
	 * @param $key      ID of connection
	 *
	 * @return RedirectResponse
	 */
	protected function redirectToFirstModule($key) {
		$dataClass = $this->get('DataModel');
		$retArr['key'] = $key;
		$routeName = 'module';
		$modules = $dataClass->getModels();
		if (count($modules)) {
			$module1st = array_shift($modules);
			if (!isset($module1st["params"]["module"])) {
				/* ERROR - wrong structure of model entry */
				$this->get('data_logger')
						->err("Cannot get first model (redirect to 1st tab).",
								array("message" => "\$module1st[\"params\"][\"module\"] is not set"));
			}
			$retArr['module'] = $module1st["params"]["module"];
			return $this->redirect($this->generateUrl($routeName, $retArr));
		} else {
			return $this->redirect($this->generateUrl("module", array('key' => $key, 'module' => 'All')));
		}
	}

	/**
	 * process all kinds of form in section, module or subsection
	 *
	 * @param  int    $key                key of connected server
	 * @param  string $module = null      module name
	 * @param  string $subsection = null  subsection name
	 * @return RedirectResponse           redirect to page, which was this method called from
	 */
	protected function processSectionForms($key, $module = null, $subsection = null) {
		$post_vals = $this->getRequest()->get("form");

		if ( !isset($this->filterForms['state']) || !isset($this->filterForms['config']) ) {
			$this->setSectionFilterForms($key);
		}

		// processing filter on state part
		if ( isset($post_vals['formType']) && $post_vals['formType'] == "formState") {
			$res = $this->handleFilterState($key, $post_vals);
			$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topMenu');

			// processing filter on config part
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formConfig" ) {
			$res = $this->handleFilterConfig($key);
			$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topMenu');

			// processing form on config - edit Config
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formCopyConfig" ) {
			$res = $this->handleCopyConfig($key);

			// processing form on config - edit Config
		} elseif ( is_array($this->getRequest()->get('configDataForm')) ) {
			$res = $this->get('XMLoperations')->handleEditConfigForm($key, $this->getConfigParams());

			// processing duplicate node form
		} elseif ( is_array($this->getRequest()->get('duplicatedNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleDuplicateNodeForm($key, $this->getConfigParams());

			// processing generate node form
		} elseif ( is_array($this->getRequest()->get('generateNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleGenerateNodeForm($key, $this->getConfigParams(), $module, $subsection);

			// processing new node form
		} elseif ( is_array($this->getRequest()->get('newNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleNewNodeForm($key, $this->getConfigParams());

			// processing remove node form
		} elseif ( is_array($this->getRequest()->get('removeNodeForm')) ) {
			$res = $this->get('XMLoperations')->handleRemoveNodeForm($key, $this->getConfigParams());
		}

		// we will redirect page after completion, because we want to load edited get and get-config
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

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->getRequest()->getSession()->set('isAjax', true);
		}
		return $this->redirect($this->generateUrl($routeName, $retArr));
	}

	/**
	 * prepares filter forms for both sections
	 *
	 * @param int     $key          key of connected server
	 */
	protected function setSectionFilterForms($key) {
		/* prepareGlobalTwigVariables is needed to init nc_feature
		array with available datastores... */
		$this->prepareGlobalTwigVariables();
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
		$datastores = array('running' => 'Running');
		$twigarr = $this->getAssignedVariablesArr();
		$ncFeatures = $twigarr["ncFeatures"];
		if (isset($ncFeatures["nc_feature_startup"])) {
			$datastores['startup'] = 'Start-up';
		}
		if (isset($ncFeatures["nc_feature_candidate"])) {
			$datastores['candidate'] = 'Candidate';
		}
		$this->filterForms['config'] = $this->createFormBuilder()
				->add('formType', 'hidden', array(
								'data' => 'formConfig',
						))
//			->add('filter', 'text', array(
//				'label' => "Filter",
//				'required' => false
//			))
				->add('source', 'choice', array(
								'label' => "Source:",
								'choices' => $datastores,
								'data' => $this->getCurrentDatastoreForKey($key)
						))
				->getForm();

		$targets = $datastores;
		$current_source = $this->getCurrentDatastoreForKey($key);
		if ($current_source !== null && $current_source !== "") {
			unset($targets[$current_source]);
		} else {
			unset($targets["running"]);
		}
		$this->filterForms['copyConfig'] = $this->createFormBuilder()
				->add('formType', 'hidden', array(
								'data' => 'formCopyConfig',
						))
				->add('target', 'choice', array(
								'label' => "copy to:",
								'choices' => $targets,
						))
				->getForm();

		$this->assign("dataStore", $this->getCurrentDatastoreForKey($key));
		$this->assign('formState', $this->filterForms['state']->createView());
		$this->assign('formConfig', $this->filterForms['config']->createView());
		$this->assign('formCopyConfig', $this->filterForms['copyConfig']->createView());
	}

	/**
	 * loads array of config values
	 */
	protected function loadConfigArr($addConfigSection = true, $merge = true, $bundleName = "FITModuleDefaultBundle") {
		if ($bundleName != "FITModuleDefaultBundle" && $this->bundleName) {
			$bundleName = $this->bundleName;
		}
		try {
			$dataClass = $this->get('dataModel');
			if ($addConfigSection) {
				$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'config');
			}

			// getcofig part
			if ( ($xml = $dataClass->handle('getconfig', $this->getConfigParams(), $merge)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');

				// we have only root module
				if ($xml->count() == 0 && $xml->getName() == 'root') {
					$this->setEmptyModuleForm($this->getRequest()->get('key'));
					$this->assign('key', $this->getRequest()->get('key'));
					$this->assign('additionalTitle', 'Create empty root element');
					$this->assign('redirectUrl', $this->getRequest()->getRequestUri());

					$this->assign('isEmptyModule', false);
					$this->assign('showRootElem', false);
					$template = $this->get('twig')->loadTemplate('FITNetopeerBundle:Default:createEmptyModule.html.twig');
					$html = $template->renderBlock('singleContent', $this->getAssignedVariablesArr());

					$this->assign('additionalForm', $html);
				} elseif ($xml->count() == 0) {
					$this->assign('isEmptyModule', true);
					$this->assign('showRootElem', false);
				} else {
					$this->assign('isEmptyModule', false);
					$this->assign('showRootElem', true);
				}

				$this->assign("configArr", $xml);
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("Config: Could not parse XML file correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->getFlashBag()->add('config error', "Could not parse XML file correctly. ");
		}
	}

	/**
	 * sets new filter for state part
	 *
	 * @param  int $key session key for current server
	 * @param      $post_vals
	 *
	 * @return int 1 on error, 0 on success
	 */
	private function handleFilterState(&$key, $post_vals) {

		$this->filterForms['state']->bind($this->getRequest());

		if ( $this->filterForms['state']->isValid() ) {
			$this->setStateParams("key", $key);
			$this->setStateParams("filter", $post_vals["filter"]);
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'State filter - you have not filled up form correctly.');
			return 1;
		}
	}

	/**
	 * sets new filter for config part
	 *
	 * @param  int $key     session key for current server
	 * @return int 1 on error, 0 on success
	 */
	private function handleFilterConfig(&$key) {

		$this->filterForms['config']->bind($this->getRequest());

		if ( $this->filterForms['config']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->setConfigParams("key", $key);
//			$this->setConfigParams("filter", $post_vals["filter"]);

			/**
			 * @var $dataClass \FIT\NetopeerBundle\Models\Data
			 */
			$dataClass = $this->get('DataModel');
			$conn = $dataClass->getConnFromKey($key);
			$conn->setCurrentDatastore($post_vals['source']);
			$dataClass->persistConnectionForKey($key, $conn);

			$this->setConfigParams("source", $post_vals['source']);

			if ($post_vals['source'] !== 'running') {
				$this->setOnlyConfigSection();
			}
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'Config filter - you have not filled up form correctly.');
			return  1;
		}
	}

	/**
	 * Execute copy-config
	 *
	 * @param  int $key     session key for current server
	 * @return int 1 on error, 0 on success
	 */
	private function handleCopyConfig(&$key) {
		$dataClass = $this->get('DataModel');

		$this->filterForms['copyConfig']->bind($this->getRequest());

		if ( $this->filterForms['copyConfig']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->setConfigParams("key", $key);
			$source = $this->getCurrentDatastoreForKey($key);
			if ($source === null) {
				$source = 'running';
			}
			$target = $post_vals['target'];
			$params = array('key' => $key, 'source' => $source, 'target' => $target);
			$dataClass->handle('copyconfig', $params, false);
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'Copy config - you have not filled up form correctly.');
			return  1;
		}
	}
} 