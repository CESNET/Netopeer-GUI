<?php
/**
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
 */
namespace FIT\NetopeerBundle\Services\Functionality;

use Doctrine\Common\Cache\WinCacheCache;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\Session;
use winzou\CacheBundle\Cache\LifetimeFileCache;

class ConnectionFunctionality {
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	protected $logger;

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var LifetimeFileCache
	 */
	protected $cache;

	/**
	 * @var EntityManager
	 */
	protected $entityManager;

	/**
	 * @var Container
	 */
	protected $container;

	protected $modelNamespaces;
	protected $models;

	/**
	 * @return \Symfony\Bridge\Monolog\Logger
	 */
	public function getLogger() {
		return $this->logger;
	}

	/**
	 * @param \Symfony\Bridge\Monolog\Logger $logger
	 */
	public function setLogger( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return Session
	 */
	public function getSession() {
		return $this->session;
	}

	/**
	 * @param Session $session
	 */
	public function setSession( $session ) {
		$this->session = $session;
	}

	/**
	 * @return LifetimeFileCache
	 */
	public function getCache() {
		return $this->cache;
	}

	/**
	 * @param LifetimeFileCache $cache
	 */
	public function setCache( $cache ) {
		$this->cache = $cache;
	}

	/**
	 * @return EntityManager
	 */
	public function getEntityManager() {
		return $this->entityManager;
	}

	/**
	 * @param EntityManager $entityManager
	 */
	public function setEntityManager( $entityManager ) {
		$this->entityManager = $entityManager;
	}

	/**
	 * @return Container
	 */
	public function getContainer() {
		return $this->container;
	}

	/**
	 * @param Container $container
	 */
	public function setContainer( $container ) {
		$this->container = $container;
	}

	/**
	 * Get models.
	 *
	 * @param  int    $key        session key of current connection
	 * @return array|null
	 */
	public function getModels($key) {
		$hashedKey = $this->getHashFromKeys($key);
		$hashedKey = array_pop($hashedKey);
		if ($hashedKey && $this->getCache()->contains('menuStructure_'.$hashedKey)) {
//			$this->logger->addInfo("Cached file for menuStructure found.", array('key' => 'menuStructure_'.$hashedKey));
			return $this->getCache()->fetch('menuStructure_'.$hashedKey);
		}
		return $this->models;
	}

	/**
	 * save model folder structure
	 *
	 * @param  int    $key        session key of current connection
	 * @param  array  $models    array to persist
	 * @param   int   $lifetime   cache lifetime in seconds
	 */
	public function setModels($key, $models, $lifetime = 6000) {
		$hashedKey = $this->getHashFromKeys($key);
		$hashedKey = array_pop($hashedKey);
		$this->models = $models;
		$this->getCache()->save('menuStructure_'.$hashedKey, $models, $lifetime);
	}


	/**
	 * Get hash of current connection
	 *
	 * @param  int|array $keys      array of session keys
	 * @param  bool $associative    return array with connId as key
	 * @return array
	 */
	public function getHashFromKeys($keys, $associative = false) {
		if (!is_array($keys)) {
			$keys = array($keys);
		}

		if (!sizeof($keys)) return "NOHASH";

		$res = array();
		foreach ($keys as $key) {
			$conn = $this->getConnectionSessionForKey($key);

			if (isset($conn->hash)) {
				if ($associative) {
					$res[$key] = $conn->hash;
				} else {
					$res[] = $conn->hash;
				}
			}
		}

		if (!empty($res)) return $res;

		return "NOHASH";
	}

	/**
	 * Find hash identifiers from DB for key
	 *
	 * @param  int $key session key
	 * @return array  return array of identifiers on success, false on error
	 */
	public function getModuleIdentifiersForCurrentDevice($key) {
		$conn = $this->getConnectionSessionForKey($key);
		if (!$conn) {
			return false;
		}
		$sessionStatus = json_decode($conn->sessionStatus);
		$sid = $this->getHashFromKeys($key)[0];
		$capabilities = $sessionStatus->$sid->capabilities;

		$arr = array();
		if (is_array($capabilities) && count($capabilities)) {
			foreach ($capabilities as $connKey => $value) {
				$regex = "/(.*)\?module=(.*)&revision=([0-9|-]*)/";
				preg_match($regex, $value, $matches);
				if ($matches !== null && count($matches) == 4) {
					$arr[$matches[1]] = array(
						'ns' => $matches[1],
						'moduleName' => $matches[2],
						'revision' => $matches[3],
					);
				}
			}
			$this->moduleIdentifiers = $arr;
			return $arr;
		}

		return false;
	}

	/**
	 * Get names of root element for specified module identifiers
	 *
	 * @param       $key
	 * @param array $identifiers
	 *
	 * @return array
	 */
	public function getRootNamesForModuleIdentifiers($key, array $identifiers) {
		$newArr = array();
		foreach ($identifiers as $ns => $ident) {
			$ident['rootElem'] = $this->getRootNameForNS($key, $ident['ns']);
			$newArr[$ns] = $ident;
		}

		return $newArr;
	}

	/**
	 * Get name of root element for module NS
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $ns
	 *
	 * @return string
	 */
	public function getRootNameForNS($key, $ns) {
		$path = $this->getModelsDir().$this->getModulePathByNS($key, $ns);
		$file = $path . '/filter.txt';
		$rootElem = "";
		if ( file_exists($file) ) {
			$dom = new \DomDocument;
			$dom->load($file);
			$rootElem = $dom->documentElement->tagName;
		}

		return $rootElem;
	}

	/**
	 * Find instance of SessionConnection.class for key.
	 *
	 * @todo: presunout do separatni sluzby
	 *
	 * @param  int $key      session key
	 * @return bool|ConnectionSession
	 */
	public function getConnectionSessionForKey($key) {
		$sessionConnections = $this->getSession()->get('session-connections');
		if (isset($sessionConnections[$key]) && $key !== '') {
			return unserialize($sessionConnections[$key]);
		}
		return false;
	}

	/**
	 * get ModuleControllers instance for given module namespace and ID of connection
	 *
	 * @param string $module    module name
	 * @param string $namespace module namespace
	 *
	 * @return ModuleController|null
	 */
	public function getModuleControllers($module, $namespace) {
		$repository = $this->getEntityManager()->getRepository("FITNetopeerBundle:ModuleController");

		return $repository->findOneBy(array(
			'moduleName' => $module,
			'moduleNamespace' => $namespace
		));
	}

	/**
	 * Get port of SessionConnection for key.
	 *
	 * @param  int $key      session key
	 * @return string
	 */
	public function getPortFromKey($key) {
		$sessionConnections = $this->getSession()->get('session-connections');
		if (isset($sessionConnections[$key]) && $key !== '') {
			$con = unserialize($sessionConnections[$key]);
			return $con->port;
		}
		return "";
	}

	/**
	 * Get user of SessionConnection for key.
	 *
	 * @param  int $key      session key
	 * @return string
	 */
	public function getUserFromKey($key) {
		$sessionConnections = $this->getSession()->get('session-connections');
		if (isset($sessionConnections[$key]) && $key !== '') {
			$con = unserialize($sessionConnections[$key]);
			return $con->user;
		}
		return "";
	}

	/**
	 * Get host of SessionConnection for key.
	 *
	 * @param  int $key      session key
	 * @return string
	 */
	public function getHostFromKey($key) {
		$sessionConnections = $this->getSession()->get('session-connections');
		if (isset($sessionConnections[$key]) && $key !== '') {
			$con = unserialize($sessionConnections[$key]);
			return $con->host;
		}
		return "";
	}

	/**
	 * Check if capability for feature is available.
	 *
	 * @param  int $key      session key
	 * @param  string $feature      name of feature/capability that is checked (constants Data::CPBLT_* can be used)
	 * @return bool
	 */
	protected function checkCapabilityForKey($key, $feature) {
		$con = $this->getConnectionSessionForKey($key);
		if ($con) {
			$first = json_decode($con->sessionStatus, true);
			$cpblts =  array_pop($first);
			foreach ($cpblts['capabilities'] as $cpblt) {
				if (strpos($cpblt, $feature, 0) === 0) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Gets array of available capabilities for all features.
	 *
	 * @param int $key      session key
	 * @return array        array of nc features
	 */
	public function getCapabilitiesArrForKey($key) {
		$ncFeatures = Array();
		if ($this->checkCapabilityForKey($key, NetconfFunctionality::CPBLT_NOTIFICATIONS) === true &&
		    $this->checkCapabilityForKey($key, NetconfFunctionality::CPBLT_REALTIME_NOTIFICATIONS) === true) {
			$ncFeatures["nc_feature_notification"] = true;
		}
		if ($this->checkCapabilityForKey($key, NetconfFunctionality::CPBLT_STARTUP) === true) {
			$ncFeatures["nc_feature_startup"] = true;
		}
		if ($this->checkCapabilityForKey($key, NetconfFunctionality::CPBLT_CANDIDATE) === true) {
			$ncFeatures["nc_feature_candidate"] = true;
		}
		if ($this->checkCapabilityForKey($key, NetconfFunctionality::CPBLT_WRITABLERUNNING) === true) {
			$ncFeatures["nc_feature_writablerunning"] = true;
		}

		return $ncFeatures;
	}

	/**
	 * Updates array of SessionConnections.
	 *
	 * @todo: presunout do connection functionality
	 *
	 * @param  int|array $keys      session connection keys
	 * @param  string $targetDataStore    target datastore identifier
	 */
	private function updateConnLock($keys, $targetDataStore) {
		if (is_int($keys)) {
			$keys = array($keys);
		}
		foreach ($keys as $key) {
			$conn = $this->getConnectionSessionForKey($key);

			if ($conn == false) {
				continue;
			}

			$conn->toggleLockOfDatastore($targetDataStore);
			$this->persistConnectionSessionForKey($key, $conn);
		}
	}

	/**
	 * serializes ConnectionSession object into session
	 *
	 * @param  int $key      session key
	 * @param  ConnectionSession $conn
	 */
	public function persistConnectionSessionForKey($key, $conn) {
		$sessionConnections = $this->getSession()->get('session-connections');
		$sessionConnections[$key] = serialize($conn);
		$this->getSession()->set('session-connections', $sessionConnections);
	}

	/**
	 * checks, if logged keys are valid
	 *
	 * @return int       		0 on success, 1 on error
	 */
	public function checkLoggedKeys() {
		if ( !count($this->getSession()->get("session-connections")) ) {
			$this->getSession()->getFlashBag()->add('error', "Not logged in.");
			return 1;
		}

		if ( !in_array( $this->getContainer()->get('request')->get('key'), array_keys($this->getSession()->get("session-connections")) ) ) {
			$this->getSession()->getFlashBag()->add('error', "You are not allow to see this connection. Bad Index of key.");
			return 1;
		}
		return 0;
	}




	/**
	 * Prepares top menu - gets items from server response
	 *
	 * @param  int    $key  session key of current connection
	 * @param  string $path
	 */
	public function buildMenuStructure($key, $path = "") {

		// we will build menu structure only if we have not build it before
		if ( !$this->getModels($key) || !$this->getModelNamespaces($key) ) {
			$models = array();
			$namespaces = array();

			$netconfFunc = $this->getContainer()->get('fitnetopeerbundle.service.netconf.functionality');
			$json = json_decode($netconfFunc->handle('get', array('connIds' => array($key))), true);

			$modifiedJson = array();
			foreach ($json as $name => $val) {
				$newKey = substr($name, 0, strpos($name, ':'));
				$modifiedJson[$newKey] = array($name => $val);
			}

			if ($this->getModuleIdentifiersForCurrentDevice($key)) {
				foreach ( $this->getModuleIdentifiersForCurrentDevice( $key ) as $ns => $values ) {

					$i                         = 0;
					$moduleName                = $values['moduleName'];

					if (isset($modifiedJson[$moduleName])) {
						$configuration = $modifiedJson[$moduleName];
					} else {
						$configuration = array();
					}

					$models[ $moduleName ]     = array(
						'path'      => "module",
						"params"    => array(
							'key'    => $key,
							'module' => $moduleName,
						),
						"title"     => "detail of " . $this->getSectionName( $moduleName ),
						"name"      => $this->getSectionName( $moduleName ),
						"children"  => $this->buildSubmenu( $key, $moduleName, $configuration ),
						"namespace" => $ns,
						"version"   => $values['revision'],
					);
					$namespaces[ $moduleName ] = $ns;
				}
			} else {
				$this->getLogger()->addError("Could not build MenuStructure", array('key' => $key));
				// nothing
			}
			$this->setModelNamespaces($key, $namespaces);
			$this->setModels($key, $models);
		}
	}

	/**
	 * prepares left submenu - modules of current top menu item
	 *
	 * @param  int  $key session key of current connection
	 * @param array $module       array with indexes: name and index
	 * @param string $path
	 * @return array
	 */
	private function buildSubmenu($key, $module, $configuration) {
		$submodule = array();

		if (sizeof($configuration)) {
			$configuration = array_pop($configuration);
			foreach ($configuration as $subsection => $val) {
				if (strpos($subsection, '$@') !== false) continue;

				if (isset($configuration['$@'.$subsection])) {
					$schema = $configuration['$@'.$subsection];
					if (isset($schema['eltype']) && $schema['eltype'] === "container") {
						$submodule[] = array(
							'path' => "subsection",
							"params" => array(
								'key' => $key,
								'module' => $module,
								'subsection' => $subsection,
							),
							"title" => "detail of ".$this->getSubsectionName($subsection),
							"name" => $this->getSubsectionName($subsection),
							// "children" => $this->getSubmenu($key, $completePath),
						);
					}
				}
			}
		}

		return $submodule;
	}

	/**
	 * loading file with filter specification for current module or subsection
	 *
	 * @param  string $module     module name
	 * @param  string $subsection subsection name
	 * @return array              array with config and state filter
	 */
	public function loadFilters(&$module, &$subsection) {
		$filterState = $filterConfig = "";

		$namespaces = $this->getModelNamespaces($this->getContainer()->get('request')->get('key'));
		if (isset($namespaces[$module])) {
			$namespace = $namespaces[$module];
			$filter = new \SimpleXMLElement("<".$module."></".$module.">");
			$filter->addAttribute('xmlns', $namespace);
			if ( $subsection ) {
				$filter->addChild($subsection);
			}

			$filterState = $filterConfig  = str_replace('<?xml version="1.0"?'.'>', '', $filter->asXml());
		}

		return array(
			'config' => $filterConfig,
			'state' => $filterState
		);
	}

	/**
	 * loading file with RPCs for current module or subsection
	 *
	 * @param  string $module     module name
	 * @param  string $subsection subsection name
	 * @return array              array with config and state filter
	 */
	public function loadRPCsModel($module, $subsection) {
		return;
		// TODO
		$path = $this->getPathToModels($module);
		$file = $path.'rpc.wyin';

		$rpcs_model = "";
		if ( file_exists($file) ) {
			$rpcs_model = file_get_contents($file);
		}

		return array(
			'rpcs' => $rpcs_model,
		);
	}

	/**
	 * Get model namespaces.
	 *
	 * @param  int    $key        session key of current connection
	 * @return array|null
	 */
	public function getModelNamespaces($key) {
		if (!$this->modelNamespaces) {
			$hashedKey = $this->getHashFromKeys($key);
			$hashedKey = array_pop($hashedKey);
			if ($hashedKey && $this->getCache()->contains('modelNamespaces_'.$hashedKey)) {
//			$this->getLogger()->addInfo("Cached file for modelNamespaces found.", array('key' => 'modelNamespaces_'.$hashedKey));
				return $this->getCache()->fetch('modelNamespaces_'.$hashedKey);
			}
		}
		return $this->modelNamespaces;
	}

	/**
	 * get namespace for given module name
	 *
	 * @param int $key      ID of connection
	 * @param string $module   name of module
	 *
	 * @return bool
	 */
	public function getNamespaceForModule($key, $module) {
		$namespaces = $this->getModelNamespaces($key);
		if (isset($namespaces[$module])) {
			return $namespaces[$module];
		} else {
			return false;
		}
	}

	/**
	 * save model folder structure
	 *
	 * @param  int    $key        session key of current connection
	 * @param  array  $namespaces array of namespaces to persist
	 * @param   int   $lifetime   cache lifetime in seconds
	 */
	public function setModelNamespaces($key, $namespaces, $lifetime = 6000) {
		$hashedKey = $this->getHashFromKeys($key);
		$hashedKey = array_pop($hashedKey);
		$this->modelNamespaces = $namespaces;
		$this->getCache()->save('modelNamespaces_'.$hashedKey, $namespaces, $lifetime);
	}

	/**
	 * Invalidates and rebuild menu structure
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 */
	public function invalidateAndRebuildMenuStructureForKey($key) {
		$this->invalidateMenuStructureForKey($key);
		$this->buildMenuStructure($key);
	}

	/**
	 * Invalidates cached files for menu structure
	 *
	 * @param  int    $key        session key of current connection
	 */
	public function invalidateMenuStructureForKey($key) {
		$hashedKey = $this->getHashFromKeys($key);
		if ($hashedKey && $this->getCache()->contains('modelNamespaces_'.$hashedKey)) {
			$this->getLogger()->addInfo("Invalidate cached file", array('key' => 'modelNamespaces_'.$hashedKey));
			$this->getCache()->delete('modelNamespaces_'.$hashedKey);
		}
		if ($hashedKey && $this->getCache()->contains('menuStructure_'.$hashedKey)) {
			$this->getLogger()->addInfo("Invalidate cached file", array('key' => 'menuStructure_'.$hashedKey));
			$this->getCache()->delete('menuStructure_'.$hashedKey);
		}
		$this->getCache()->deleteDeads();
	}

	/**
	 * Get model tree file from models dir.
	 *
	 * @param string $moduleName
	 *
	 * @return string|int
	 */
	public function getModelTreeDump($moduleName = '')
	{
		return;
		// TODO
		$path = $this->getPathToModels($moduleName).'tree.txt';

		if (file_exists($path)) {
			return file_get_contents($path);
		}

		return 0;
	}

	/**
	 * Get submenu for key.
	 *
	 * @param  string $index      index in array of submenu structure
	 * @param  int    $key        session key of current connection
	 * @return array
	 */
	public function getSubmenu($index, $key) {
		$models = $this->getModels($key);
		return isset($models[$index]['children']) ? $models[$index]['children'] : array();
	}

	/**
	 * Get name for section.
	 *
	 * @param $section
	 * @return string
	 */
	public function getSectionName($section) {
		return ucfirst( str_replace(array('-', '_'), ' ', $section) );
	}

	/**
	 * Get name for subsection.
	 *
	 * @param $subsection
	 * @return string
	 */
	public function getSubsectionName($subsection) {
		return $this->getSectionName($subsection);
	}

	/**
	 * Get identificator (hash) of model - it is used as directory name of model
	 *
	 * @param string $name       module name from conf. model
	 * @param string $version    version of conf. model
	 * @param string $ns         namespace
	 * @return string            hashed identificator
	 */
	public function getModelIdentificator($name, $version, $ns)
	{
//		$ident = "$name|$version|$ns";
		$ident = $ns;
		return sha1($ident);
	}
}