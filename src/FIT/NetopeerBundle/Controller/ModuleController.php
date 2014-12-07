<?php
/**
 * Created by PhpStorm.
 * User: info
 * Date: 07.12.14
 * Time: 17:33
 */

namespace FIT\NetopeerBundle\Controller;


use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class ModuleController extends BaseController {
	/**
	 * @var array   holds configuration of filter form (in both section)
	 */
	private $filterForms;

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
	protected function loadConfigArr($addConfigSection = true, $merge = true) {
		try {
			$dataClass = $this->get('dataModel');
			if ($addConfigSection) {
				$this->addAjaxBlock('FITNetopeerBundle:Default:section.html.twig', 'config');
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