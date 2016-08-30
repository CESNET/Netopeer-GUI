<?php

namespace FIT\Bundle\ModuleDefaultBundle\Controller;

use FIT\NetopeerBundle\Controller\ModuleControllerInterface;
use FIT\NetopeerBundle\Models\XMLoperations;
use FIT\NetopeerBundle\Services\Functionality\NetconfFunctionality;
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
		$netconfFunc = $this->get('fitnetopeerbundle.service.netconf.functionality');

		$res = $this->prepareVariablesForModuleAction("FITModuleDefaultBundle", $key, $module, $subsection);

		if ($this->getRequest()->getMethod() == 'POST') {
			$this->setSectionFilterForms( $key );

			$configParams = $this->getConfigParams();
			$postData = $this->getRequest()->getContent();
			$postArr = json_decode($postData, true);

			if (isset($postArr['action']) && $postArr['action'] == "commit") {
				$params = array(
					'connIds' => array($key)
				);
				$res = $netconfFunc->handle('commit', $params, true, $result);

				$this->get('session')->set('isAjax', true);
				return $this->getTwigArr();
			} elseif (strpos($postData, 'form') !== 0) {
				$params = array(
					'connIds' => array($key),
					'target' => $configParams['source'],
					'configs' => array($postData)
				);
				$res = $netconfFunc->handle('editconfig', $params, true, $result);

				$this->get('session')->set('isAjax', true);
				return $this->getTwigArr();
			}
		}

		/* parent module did not prepares data, but returns redirect response,
		 * so we will follow this redirect
		 */
		if ($res instanceof RedirectResponse) {
			return $res;
		}

		if ($this->getRequest()->get('angular') == "true") {
			$resData = $this->loadDataForModuleAction("FITModuleDefaultBundle", $key, $module, $subsection);

			// load content of snippets
			$this->get('session')->set('isAjax', true);
			$this->removeAjaxBlock('topMenu');
			$content = json_decode($this->getTwigArr()->getContent(), true);

			$conn = $connectionFunc->getConnectionSessionForKey($key);
			$res = array(
				'variables' => array(
					'jsonEditable' => true,
					'datastore' => $conn->getCurrentDatastore(),
				),
				'configuration' => json_decode($resData),
				'snippets' => $content['snippets'],
			);
			return new JsonResponse($res);
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
