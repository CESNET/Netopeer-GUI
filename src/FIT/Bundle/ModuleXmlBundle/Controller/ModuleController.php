<?php

namespace FIT\Bundle\ModuleXmlBundle\Controller;

use FIT\NetopeerBundle\Controller\ModuleControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ModuleController extends \FIT\NetopeerBundle\Controller\ModuleController implements ModuleControllerInterface
{
	/**
	 * @inheritdoc
	 *
	 * @Template("FITModuleXmlBundle:Module:section.html.twig")
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$res = $this->prepareVariablesForModuleAction("FITModuleXmlBundle", $key, $module, $subsection);

		/* parent module did not prepares data, but returns redirect response,
		 * so we will follow this redirect
		 */
		if ($res instanceof RedirectResponse) {
			return $res;

			// data were prepared correctly
		} else {
			return $this->getTwigArr();
		}
	}

}
