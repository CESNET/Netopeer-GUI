<?php

namespace FIT\Bundle\ModuleDefaultBundle\Controller;

use FIT\NetopeerBundle\Controller\ModuleControllerInterface;
use FIT\NetopeerBundle\Models\XMLoperations;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ModuleController extends \FIT\NetopeerBundle\Controller\ModuleController implements ModuleControllerInterface
{
	/**
	 * @inheritdoc
	 *
	 * @Route("/sections/{key}/", name="section")
	 * @Route("/sections/{key}/{module}/", name="module", requirements={"key" = "\d+"})
	 * @Route("/sections/{key}/{module}/{subsection}/", name="subsection")
	 * @Template("FITModuleDefaultBundle:Module:section.html.twig")
	 *
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$connectionFunc = $this->get('fitnetopeerbundle.service.connection.functionality');
		$res = $this->prepareVariablesForModuleAction("FITModuleDefaultBundle", $key, $module, $subsection);

		/* parent module did not prepares data, but returns redirect response,
		 * so we will follow this redirect
		 */
		if ($res instanceof RedirectResponse) {
			return $res;
		}

		if ($this->getRequest()->isXmlHttpRequest() || $this->getRequest()->get('angular') == "true") {
			$res = $this->loadDataForModuleAction("FITModuleDefaultBundle", $key, $module, $subsection);
			return new JsonResponse(json_decode($res));
		} else {
			$this->assign('singleColumnLayout', true);
			return $this->getTwigArr();
		}

		// check if we have only root module
		$this->checkEmptyRootModule($key, $res);

		// we will load config part only if two column layout is enabled or we are in all section or datastore is not running (which has two column always)
		$tmp = $this->getConfigParams();
		if ($module === 'all') {
			$merge = false;
		} else {
			$merge = true;
		}
		if ($module == null || ($module != null && $tmp['source'] !== "running" && !$this->getAssignedValueForKey('isEmptyModule'))) {
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
			$conn = $connectionFunc->getConnectionSessionForKey($key);
			if ($conn->getCurrentDatastore() !== "running") {
				$this->loadConfigArr(false, $merge);
				$this->setOnlyConfigSection();
			}
			$this->assign('singleColumnLayout', true);
		}

		return $this->getTwigArr();
	}
}
