<?php

namespace FIT\Bundle\ModuleNacmBundle\Controller;

use FIT\NetopeerBundle\Controller\ModuleControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class ModuleController extends \FIT\NetopeerBundle\Controller\ModuleController implements ModuleControllerInterface
{
	/**
	 * @inheritdoc
	 *
	 * @Template("FITModuleNacmBundle:Module:section.html.twig")
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$dataClass = $this->get('DataModel');

		if ($this->getRequest()->getSession()->get('isLocking') !== true) {
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'title');
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'additionalTitle');
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'state');
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'leftColumn');
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'topPart');
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'moduleJavascripts');
			$this->assign('historyHref', $this->getRequest()->getRequestUri());
		}
		$this->getRequest()->getSession()->remove('isLocking');
		$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'alerts');

		if ($this->getRequest()->getSession()->get('isAjax') === true) {
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'topMenu');
		}

		if ($dataClass->checkLoggedKeys() === 1) {
			$url = $this->get('request')->headers->get('referer');
			if (!strlen($url)) {
				$url = $this->generateUrl('connections');
			}
			return $this->redirect($url);
		}
		$this->setActiveSectionKey($key);
		$dataClass->buildMenuStructure($key);

		// now, we could set forms params with filter (even if we don't have module or subsection)
		// filter will be empty
		$filters = $dataClass->loadFilters($module, $subsection);

		$this->assign('rpcMethods', $this->createRPCListFromModel($module, $subsection));

		$this->setSectionFormsParams($key, $filters['state'], $filters['config']);

		// if form has been send, we well process it
		if ($this->getRequest()->getMethod() == 'POST') {
			$this->processSectionForms($key, $module, $subsection);
			// the code below wont be precess, because at the end of processSectionForms
			// is redirect executed
		}

		// we will prepare filter form in column
		$this->setSectionFilterForms($key);

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


		/* Show the first module we have */
		if ( $module == null ) {
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

		$activeNotifications = $this->getRequest()->getSession()->get('activeNotifications');
		if ( !isset($activeNotifications[$key]) || $activeNotifications[$key] !== true ) {
			$activeNotifications[$key] = true;
			$this->getRequest()->getSession()->set('activeNotifications', $activeNotifications);
			$this->addAjaxBlock('FITModuleNacmBundle:Module:section.html.twig', 'notifications');
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
		$this->assign('valuesTypeaheadPath', $valuesTypeaheadPath);

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

		return $this->getTwigArr();
	}

}
