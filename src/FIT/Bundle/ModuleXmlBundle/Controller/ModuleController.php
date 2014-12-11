<?php

namespace FIT\Bundle\ModuleXmlBundle\Controller;

use FIT\NetopeerBundle\Controller\ModuleControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class ModuleController extends \FIT\NetopeerBundle\Controller\ModuleController implements ModuleControllerInterface
{
	/**
	 * @inheritdoc
	 *
	 * @Template("FITModuleXmlBundle:Module:section.html.twig")
	 */
	public function moduleAction($key, $module = null, $subsection = null)
	{
		$this->prepareDataForModuleAction("FITModuleXmlBundle", $key, $module, $subsection);

		return $this->getTwigArr();
	}

}
