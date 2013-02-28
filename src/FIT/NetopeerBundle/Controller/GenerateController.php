<?php
/**
 * Controller for custom text output, for example XML or HTML file.
 *
 * @author David Alexa
 */
namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Generate simple output, for example model in XML or HTML (using templates)
 */
class GenerateController extends BaseController
{
	/**
	 * Gets XML subtree from model (according to given xPath) and shows it either as rendered HTML or as clean XML
	 *
	 * @Route("/generate/{level}/{xPath}/{key}/{module}/{subsection}/model.{_format}", defaults={"module" = null, "subsection" = null, "_format" = "html"}, requirements={"_format" = "html|xml"}, name="generateFromModel")
	 * @Template()
	 *
	 * @param $level
	 * @param $xPath
	 * @param int $key                    section key
	 * @param string|null $module         module identifier (url)
	 * @param string|null $subsection     subsection identifier (url)
	 * @param string      $_format        output format
	 * @return array
	 */
	public function generateXMLFromModelAction($level, $xPath, $key, $module = null, $subsection = null, $_format = 'html') {
		// DependencyInjection (DI) - defined in Resources/config/services.yml
		$dataClass = $this->get('DataModel');

		// get XML tree from model
		$xml = $dataClass->getXMLFromModel(urldecode($xPath), $key, $module, $subsection);

		// if we want to get html, we will build tree for HTML form
		if ( $_format == 'html' ) {
			$simpleXml = simplexml_load_string($xml, 'SimpleXMLIterator');
			$this->assign('level', $level);
			$this->assign('xmlArr', $simpleXml);	
		// or we just want to see XML (for example, for adding new values in Data.php or load as SimpleXML)
		} else {
			echo $xml;
			exit;
		}

		return $this->getTwigArr();
	}
}
