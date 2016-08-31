<?php
/**
 * Controller for module action = base module controller (which is
 * extended in custom module bundle (for example ModuleDefaultBundle,
 * ModuleXmlBundle...)
 *
 * @file GenerateController.php
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


use FIT\NetopeerBundle\Models\Data;
use FIT\NetopeerBundle\Models\XMLoperations;
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
	static protected $defaultModuleAction = "FIT\Bundle\ModuleDefaultBundle\Controller\ModuleController::moduleAction";

	/**
	 * base method for preparing variables for module action
	 *
	 * @param      $bundleName
	 * @param      $key
	 * @param null $module
	 * @param null $subsection
	 *
	 * * @return RedirectResponse|null   redirectResponse when current page is not correct, null on some failure
	 */
	protected function prepareVariablesForModuleAction($bundleName, $key, $module = null, $subsection = null)
	{
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$this->bundleName = $bundleName;

		if ($this->getRequest()->getSession()->get('isLocking') !== true) {
//			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'moduleJavascripts');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'moduleStylesheet');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'title');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'additionalTitle');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'state');
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'leftColumn');
			$this->assign('historyHref', $this->getRequest()->getRequestUri());
		}
		$this->getRequest()->getSession()->remove('isLocking');
		$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'alerts');
		$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'topMenu');

		if ($connectionFunc->checkLoggedKeys() === 1) {
			$url = $this->get('request')->headers->get('referer');
			if (!strlen($url)) {
				$url = $this->generateUrl('connections');
			}
			return $this->redirect($url);
		}

		/* build correct menu structure for this module, generates module structure too */
		$connectionFunc->buildMenuStructure($key);

		/* Show the first module we have */
		if ( $module == null ) {
//			in angular we want to show the only section route
//			return $this->redirectToFirstModule($key);
		}

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $connectionFunc->loadFilters($module, $subsection);
		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		/** prepare necessary data for left column */
		$this->setActiveSectionKey($key);
		$this->setModuleOrSectionName($key, $module, $subsection);
		$this->assign('rpcMethods', $this->createRPCListFromModel($key, $module));
		$this->setModuleOutputStyles($key, $module);

		// if form has been send, we well process it
		if ($this->getRequest()->getMethod() == 'POST') {
			return $this->processSectionForms($key, $module, $subsection);
//			return;
		}

		// we will prepare filter form in column
		$this->setSectionFilterForms($key);

		$activeNotifications = $this->getRequest()->getSession()->get('activeNotifications');
		if ( !isset($activeNotifications[$key]) || $activeNotifications[$key] !== true ) {
			$activeNotifications[$key] = true;
			$this->getRequest()->getSession()->set('activeNotifications', $activeNotifications);
			$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'notifications');
		}

		// load model tree dump
		$modelTree = $connectionFunc->getModelTreeDump($module);
		if ($modelTree) {
			$this->assign('modelTreeDump', $modelTree);
		}

		return null;
	}

	/**
	 * base method for getting data for module action
	 *
	 * @param      $bundleName
	 * @param      $key
	 * @param null $module
	 * @param null $subsection
	 *
	 * @return json|null   JSON with state data
	 */
	protected function loadDataForModuleAction($bundleName, $key, $module = null, $subsection = null) {
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		if ($module === 'add-new-module') {
			$json = '';
			$this->assign('stateJson', $json);
			return $json;
		}

		// loading state part = get Action
		// we will load it every time, because state column will we show everytime
		try {

			// we can not call get on other than running datastore
			$params = $this->getStateParams();
			$command = 'get';
			if ( isset($params['source']) && $params['source'] !== 'running' ) {
				$command = 'getconfig';
				$params = $this->getConfigParams();
			}

			if ( ($json = $netconfFunc->handle($command, $params)) != 1 ) {
				$this->assign("stateJson", $json);
				return $json;
			}
		} catch (\ErrorException $e) {
			$this->get('data_logger')->err("State: Could not parse filter correctly.", array("message" => $e->getMessage()));
			$this->getRequest()->getSession()->getFlashBag()->add('state error', "Could not parse filter correctly. ");
		}

		return null;
	}

	/**
	 * @param int    $key    ID of connection
	 * @param string $module name of current module
	 *
	 * @return array
	 */
	protected function setModuleOutputStyles($key, $module) {
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$controllers = array();

		$namespace = $connectionFunc->getNamespaceForModule($key, $module);
		$record = $connectionFunc->getModuleControllers($module, $namespace);
		if ($record) {
			$controllers = $record->getControllerActions();
		}

		// always add ModuleDefaultBundle at the end (if is not included already)
		if (!in_array(self::$defaultModuleAction, $controllers)) {
			$controllers[] = self::$defaultModuleAction;
		}

		// prepare array with ModuleControllers for choice element in form
		$modifiedControllers = array();
		foreach ($controllers as $controller) {
			$parts = explode("\\", $controller);
			$name = str_replace(array('Module', 'Bundle'), '', $parts[2]);
			$modifiedControllers[$controller] = $name;
		}

		// build form for controller output change
		$conn = $connectionFunc->getConnectionSessionForKey($key);
		$controllerAction = $conn->getActiveControllersForNS($connectionFunc->getNamespaceForModule($key, $module));

		$form = $this->createFormBuilder(null, array('csrf_protection' => false))
				->add('controllerAction', 'choice', array(
								'choices' => $modifiedControllers,
								'required' => true,
								'data' => $controllerAction,
								'attr' => array(
										'class' => 'js-auto-submit-on-change'
								)
						))
				->getForm();
		$this->assign('moduleStylesForm', $form->createView());

		// handle change controller output style
		if ($this->getRequest()->getMethod() == 'POST') {
			$postVals = $this->getRequest()->get("form");
			if ( isset($postVals['controllerAction']) ) {
				$conn->setActiveController($namespace, $postVals['controllerAction']);
				$connectionFunc->persistConnectionSessionForKey($key, $conn);
			}
		}

		return $modifiedControllers;
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
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		// if we have module, we are definitely in module or subsection action, so we could load names
		if ( $module ) {
			parent::setSubmenuUrl($module);
			$this->assign('sectionName', $connectionFunc->getSectionName($module));

			if ( $subsection ) {
				$this->assign('subsectionName', $connectionFunc->getSubsectionName($subsection));
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
		}

		$routeParams = array('key' => $key, 'module' => $module, 'subsection' => $subsection);
		$this->assign('routeParams', $routeParams);
		$this->assign('isModule', true);
	}

	/**
	 * Redirect to first available module or All section if no module is available
	 *
	 * @param $key      ID of connection
	 *
	 * @return RedirectResponse
	 */
	protected function redirectToFirstModule($key) {
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$retArr['key'] = $key;
		$routeName = 'module';
		$modules = $connectionFunc->getModuleIdentifiersForCurrentDevice($key);
		if (count($modules)) {
			$first = array_shift($modules);
			if (!isset($first["moduleName"])) {
				/* ERROR - wrong structure of model entry */
				$this->get('data_logger')
						->err("Cannot get first model (redirect to 1st tab).",
								array("message" => "\$first[\"moduleName\"] is not set"));
			}
			$retArr['module'] = $first["moduleName"];
			return $this->redirect($this->generateUrl($routeName, $retArr));
		} else {
			return $this->redirect($this->generateUrl("module", array('key' => $key, 'module' => 'all')));
		}
	}

	/**
	 * process all kinds of form in section, module or subsection
	 *
	 * @param  int    $key                key of connected server
	 * @param  string $module = null      module name
	 * @param  string $subsection = null  subsection name
	 * @return int
	 */
	protected function processSectionForms($key, $module = null, $subsection = null) {
		$post_vals = $this->getRequest()->get("form");

		if ( !isset($this->filterForms['state']) || !isset($this->filterForms['config']) ) {
			$this->setSectionFilterForms($key);
		}

		// processing filter on config part
		if ( isset($post_vals['formType']) && $post_vals['formType'] == "formConfig" ) {
			$this->addAjaxBlock('FITNetopeerBundle:Default:connections.html.twig', 'topMenu');
			return $this->handleFilterConfig($key);

			// processing form on config - edit Config
		} elseif ( isset($post_vals['formType']) && $post_vals['formType'] == "formCopyConfig" ) {
			return $this->handleCopyConfig($key);
		}

		/*
		 * TODO: remove?
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
		*/
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
				->add('source', 'choice', array(
								'label' => "Source:",
								'choices' => $datastores,
								'data' => $this->getCurrentDatastoreForKey($key),
				        'attr' => array('class' => 'js-auto-submit-on-change')
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
			$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');
			if ($addConfigSection) {
				$this->addAjaxBlock($bundleName.':Module:section.html.twig', 'config');
			}

			// getcofig part
			if ( ($json = $netconfFunc->handle('getconfig', $this->getConfigParams(), $merge)) != 1 ) {

				$this->assign("configJson", $json);
				return;
				// TODO;

				// we have only root module
				if ($xml->count() == 0 && $xml->getName() == XMLoperations::$customRootElement) {
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
	 * Checks if we have empty module in XML
	 *
	 * @param int    $key
	 * @param string $xml    result of prepareVariablesForModuleAction()
	 */
	protected  function checkEmptyRootModule($key, $xml) {
		if ($xml instanceof \SimpleXMLIterator && $xml->count() == 0) {
			$isEmptyModule = true;
			if ($xml->getName() == XMLoperations::$customRootElement) {
				$this->setEmptyModuleForm($this->getRequest()->get('key'));
				$isEmptyModule = false;
				$this->assign('forceShowFormConfig', true);
			}
			$this->assign('isEmptyModule', $isEmptyModule);
			$this->assign('key', $this->getRequest()->get('key'));
			$this->assign('additionalTitle', 'Create empty root element');
			$this->assign('redirectUrl', $this->getRequest()->getRequestUri());
			$this->setEmptyModuleForm($key);
			$template = $this->get('twig')->loadTemplate('FITNetopeerBundle:Default:createEmptyModule.html.twig');
			$html = $template->renderBlock('singleContent', $this->getAssignedVariablesArr());

			$this->assign('additionalForm', $html);
		} else {
			$this->assign('showRootElem', true);
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
			$this->setStateParams("connIds", array($key));
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
			$this->setConfigParams("connIds", array($key));
//			$this->setConfigParams("filter", $post_vals["filter"]);

			$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
			$conn = $connectionFunc->getConnectionSessionForKey($key);
			$conn->setCurrentDatastore($post_vals['source']);
			$connectionFunc->persistConnectionSessionForKey($key, $conn);

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
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		$this->filterForms['copyConfig']->bind($this->getRequest());

		if ( $this->filterForms['copyConfig']->isValid() ) {
			$post_vals = $this->getRequest()->get("form");
			$this->setConfigParams("connIds", array($key));
			$source = $this->getCurrentDatastoreForKey($key);
			if ($source === null) {
				$source = 'running';
			}
			$target = $post_vals['target'];
			$params = array('connIds' => array($key), 'source' => $source, 'target' => $target);
			$netconfFunc->handle('copyconfig', $params, false);
			return 0;
		} else {
			$this->getRequest()->getSession()->getFlashBag()->add('error', 'Copy config - you have not filled up form correctly.');
			return  1;
		}
	}
}