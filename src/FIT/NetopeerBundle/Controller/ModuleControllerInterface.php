<?php
/**
 * Created by PhpStorm.
 * User: info
 * Date: 07.12.14
 * Time: 17:21
 */

namespace FIT\NetopeerBundle\Controller;


interface ModuleControllerInterface {

	/**
	 * Prepares section, module or subsection action data
	 *
	 * Prepares section = whole get&get-config part of server
	 * Shows module part = first level of connected server (except of root)
	 * Prepares subsection = second level of connected server tree
	 *
	 * @param int           $key          key of connected server
	 * @param null|string   $module       name of the module
	 * @param null|string   $subsection   name of the subsection
	 * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function moduleAction($key, $module = null, $subsection = null);
} 