<?php
/**
 * Base class for all communication with mod_netconf,
 * getting and processing input and output data.
 *
 * @author David Alexa <alexa.david@me.com>
 * @author Tomas Cejka <cejkat@cesnet.cz>
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
namespace FIT\NetopeerBundle\Models;

use FIT\Bundle\ModuleDefaultBundle\Controller\ModuleController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManager;

use FIT\NetopeerBundle\Entity\ConnectionSession;

/**
 * Data service, handles all communication between webGUI and mod_netconf.
 */
class Data {

	/* Enumeration of Message type (taken from mod_netconf.c) */
	const REPLY_OK				= 0;
	const REPLY_DATA			= 1;
	const REPLY_ERROR			= 2;
	const REPLY_INFO			= 3;
	const MSG_CONNECT			= 4;
	const MSG_DISCONNECT		= 5;
	const MSG_GET 				= 6;
	const MSG_GETCONFIG			= 7;
	const MSG_EDITCONFIG		= 8;
	const MSG_COPYCONFIG		= 9;
	const MSG_DELETECONFIG		= 10;
	const MSG_LOCK 				= 11;
	const MSG_UNLOCK			= 12;
	const MSG_KILL				= 13;
	const MSG_INFO				= 14;
	const MSG_GENERIC			= 15;
	const MSG_GETSCHEMA			= 16;
	const MSG_RELOADHELLO			= 17;
	const MSG_NTF_GETHISTORY		= 18;
	const MSG_VALIDATE			= 19;

	/* subscription of notifications */
	const CPBLT_NOTIFICATIONS		= "urn:ietf:params:netconf:capability:notification:1.0";
	/*
	when this capability is missing, we cannot execute RPCs before
	notif subscribe finishes :-/
	*/
	const CPBLT_REALTIME_NOTIFICATIONS	= "urn:ietf:params:netconf:capability:interleave:1.0";
	/* modifications of running config */
	const CPBLT_WRITABLERUNNING		= "urn:ietf:params:netconf:capability:writable-running:1.0";
	/* candidate datastore */
	const CPBLT_CANDIDATE			= "urn:ietf:params:netconf:capability:candidate:1.0";
	/* startup datastore */
	const CPBLT_STARTUP			= "urn:ietf:params:netconf:capability:startup:1.0";
	const CPBLT_NETOPEER			= "urn:cesnet:tmc:netopeer:1.0?module=netopeer-cfgnetopeer";
	const CPBLT_NETCONF_BASE10		= "urn:ietf:params:netconf:base:1.0";
	const CPBLT_NETCONF_BASE11		= "urn:ietf:params:netconf:base:1.1";

	//"urn:ietf:params:xml:ns:yang:ietf-netconf-monitoring?module=ietf-netconf-monitoring&revision=2010-10-04"
	//"urn:ietf:params:xml:ns:netconf:base:1.0?module=ietf-netconf&revision=2011-03-08"



	/**
	 * @var ContainerInterface   base bundle container
	 */
	protected $container;
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	protected $logger;
	/**
	 * @var array     array of namespaces for module name
	 */
	private $modelNamespaces;
	/**
	 * @var array|null  array with names of models for creating top menu
	 */
	private $models;
	/**
	 * @var array|null  array of hash identifiers (array of connected devices).
	 */
	private $moduleIdentifiers;
	/**
	 * @var array array of handle* result
	 *
	 * no need to call for example <get> more than once
	 */
	private $handleResultsArr;
	/**
	 * @var array       array of submenu structure for every module
	 */
	private $submenu;

	/**
	 * Constructor with DependencyInjection params.
	 *
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	 * @param \Symfony\Bridge\Monolog\Logger $logger   logging class
	 */
	public function __construct(ContainerInterface $container, $logger)	{
		$this->container = $container;
		$this->logger = $logger;
		$this->models = null;
		$this->modelNamespaces = array();
	}

	/**
	 * Parse $message formatted by Chunked Framing Mechanism (RFC6242)
	 *
	 * @param  string $message input message text
	 * @return string          unwrapped message
	 *
	 * @throws \ErrorException  when message is not formatted correctly
	 */
	private function unwrapRFC6242($message) {
		$response = "";
		if ($message == "") {
			return $response;
		}
		$chunks = explode("\n#", $message);
		$numchunks = sizeof($chunks);
		$i = 0;
		if ($numchunks > 0) {
			do {
				if ($i == 0 && $chunks[$i++] != "") {
					/* something is wrong, message should start by '\n#'
					 */
					$this->logger->warn("Wrong message format, it is not according to RFC6242 (starting with \\n#).", array("message" => var_export($message, true)));
					throw new \ErrorException("Wrong message format, it is not according to RFC6242 (starting with \\n#).");
				}
				if ($i >= $numchunks) {
					$this->logger->warn("Malformed message (RFC6242) - Bad amount of parts.", array("message" => var_export($message, true)));
					throw new \ErrorException("Malformed message (RFC6242) - Bad amount of parts.");  }
				/* echo "chunk length<br>\n"; */
				$len = 0;
				sscanf($chunks[$i], "%i", $len);

				/* echo "chunk data<br>\n"; */
				$nl = strpos($chunks[$i], "\n");
				if ($nl === false) {
					$this->logger->warn("Malformed message (RFC6242) - There is no \\n after chunk-data size.", array("message" => var_export($message, true)));
					throw new \ErrorException("Malformed message (RFC6242) - There is no \\n after chunk-data size.");
				}
				$data = substr($chunks[$i], $nl + 1);
				$realsize = strlen($data);
				if ($realsize != $len) {
					$this->logger->warn("Chunk $i has the length $realsize instead of $len.", array("message" => var_export($message, true)));
					throw new \ErrorException("Chunk $i has the length $realsize instead of $len.");
				}
				$response .= $data;
				$i++;
				if ($chunks[$i][0] == '#') {
					/* ending part */
					break;
				}
			} while ($i<$numchunks);
		}

		return $response;
	}

	/**
	 * Get hash for current connection
	 *
	 * @todo: presunout do separatni sluzby
	 *
	 * @param  int|array $keys      array of session keys
	 * @param  bool $associative    return array with connId as key
	 * @return array
	 */
	private function getHashFromKeys($keys, $associative = false) {
		if (is_int($keys)) {
			$keys = array($keys);
		}

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
		$capabilities = $sessionStatus->capabilities;

		$arr = array();
		if (is_array($capabilities) && count($capabilities)) {
			foreach ($capabilities as $connKey => $value) {
				$regex = "/(.*)\?module=(.*)&revision=([0-9|-]*)/";
				preg_match($regex, $value, $matches);
				if ($matches !== null && count($matches) == 4) {
					$arr[$matches[1]] = array(
						'hash' => $this->getModelIdentificator($matches[2], $matches[3], $matches[1]),
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
	 * get path for module name, includes identifier
	 *
	 * @param  int    $key        session key
	 * @param  string $moduleName name of element
	 * @param  string  $ns        namespace of module will be used instead of module name
	 *
	 * @return string           relative path on success, false on error
	 */
	private function getModulePathByRootModuleName($key, $moduleName, $ns = '') {
		if (!is_array($this->moduleIdentifiers) || !count($this->moduleIdentifiers)) {
			$this->getModuleIdentifiersForCurrentDevice($key);
		}

		$modelNamespaces = $this->getModelNamespaces($key);
		if (isset($modelNamespaces[$moduleName])) {
			$cnt = count($modelNamespaces[$moduleName]);
			if ($cnt == 1) {
				$namespace = $modelNamespaces[$moduleName];
				if (isset($this->moduleIdentifiers[$namespace])) {
					return $this->getModulePathByNS($key, $namespace);
				}
			}
		} elseif (isset($this->moduleIdentifiers[$ns])) {
			return $this->getModulePathByNS($key, $ns);
		}
		return false;
	}

	/**
	 * get path for module namespace
	 *
	 * @param $key    Identifier of connection (connected device ID)
	 * @param $ns
	 *
	 * @return bool|string
	 */
	public function getModulePathByNS($key, $ns) {
		if (!is_array($this->moduleIdentifiers) || !count($this->moduleIdentifiers)) {
			$this->getModuleIdentifiersForCurrentDevice($key);
		}
		if (isset($this->moduleIdentifiers[$ns])) {
			return $this->moduleIdentifiers[$ns]['hash'] .
			"/" . $this->moduleIdentifiers[$ns]['moduleName'] .
			"/" . $this->moduleIdentifiers[$ns]['revision'];
		}
		return false;
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
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
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
		$em = $this->container->get('doctrine')->getManager();
		$repository = $em->getRepository("FITNetopeerBundle:ModuleController");

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
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
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
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
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
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
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
			$cpblts =  json_decode($con->sessionStatus);
			foreach ($cpblts->capabilities as $cpblt) {
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
		if ($this->checkCapabilityForKey($key, $this::CPBLT_NOTIFICATIONS) === true &&
				$this->checkCapabilityForKey($key, $this::CPBLT_REALTIME_NOTIFICATIONS) === true) {
			$ncFeatures["nc_feature_notification"] = true;
		}
		if ($this->checkCapabilityForKey($key, $this::CPBLT_STARTUP) === true) {
			$ncFeatures["nc_feature_startup"] = true;
		}
		if ($this->checkCapabilityForKey($key, $this::CPBLT_CANDIDATE) === true) {
			$ncFeatures["nc_feature_candidate"] = true;
		}
		if ($this->checkCapabilityForKey($key, $this::CPBLT_WRITABLERUNNING) === true) {
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
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
		$sessionConnections[$key] = serialize($conn);
		$session->set('session-connections', $sessionConnections);
	}

	/**
	 * Read response from socket
	 *
	 * @param  resource &$sock 		socket descriptor
	 * @return string             trimmed string that was read
	 */
	private function readnetconf2(&$sock) {
		$response = "";
		do {
			$tmp = "";
			$tmp = fread($sock, 4096);
			if ($tmp != "") {
				$response .= $tmp;
			}
			if (strlen($tmp) < 4096) {
				break;
			}
		} while ($tmp != "");
		$status = stream_get_meta_data($sock);
		if (!$response && $status["timed_out"] == true) {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Reached timeout for reading response.");
		}
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrapRFC6242($response);
		} catch (\ErrorException $e) {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not read NetConf. Error: ".$e->getMessage());
			return 1;
		}

		return trim($response);
	}

	/**
	 * Read response from socket
	 *
	 * @param  resource &$sock 		socket descriptor
	 * @return string             trimmed string that was read
	 */
	private function readnetconf(&$sock) {
		$response = "";
		$tmp = "";
		$tmp = fread($sock, 1024);
		if ($tmp === false) {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Reading failure.");
		}

		$response = $tmp;
		// message is wrapped in "\n#strlen($m)\n$m\n##\n"
		// get size:
		$size = 0;
		$lines = explode("\n", $tmp);
		if (count($lines) >= 2) {
			$size = strlen($lines[0]) + 1 + strlen($lines[1]) + 1;
			$size += intval(substr($lines[1], 1)) + 5;
		}

		while (strlen($response) < $size) {
			$tmp = "";
			$tmp = fread($sock, $size - strlen($response));
			if ($tmp === false) {
				$this->container->get('request')->getSession()->getFlashBag()->add('error', "Reading failure.");
				break;
			}
			$response .= $tmp;
			//echo strlen($response) ."/". $size ."\n";
		}
		$status = stream_get_meta_data($sock);
		if (!$response && $status["timed_out"] == true) {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Reached timeout for reading response.");
			//echo "Reached timeout for reading response.";
		}
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrapRFC6242($response);
		} catch (\ErrorException $e) {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not read NetConf. Error: ".$e->getMessage());
			//echo "unwrap exception";
			return 1;
		}
		//echo "readnetconf time consumed: ". (microtime(true) - $start);

		return trim($response);
	}

	/**
	 * Sets error message based on JSON ERROR CODE.
	 *
	 * @return array  errorCode and message for this errorCode.
	 */
	private function getJsonError() {
		$res = 0;
		switch ($errorCode = json_last_error()) {
			case JSON_ERROR_NONE:
				$res = 'No errors';
				break;
			case JSON_ERROR_DEPTH:
				$res = 'Maximum stack depth exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$res = 'Underflow or the modes mismatch';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$res = 'Unexpected control character found';
				break;
			case JSON_ERROR_SYNTAX:
				$res = 'Syntax error, malformed JSON';
				break;
			case JSON_ERROR_UTF8:
				$res = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
			default:
				$res = 'Unknown error';
				break;
		}
		return array('errorCode' => $errorCode, 'message' => $res);
	}

	/**
	 * Adds params from sourceArr to targetArr if is defined in optionalParams
	 *
	 * @param array $targetArr
	 * @param array $sourceArr
	 * @param array $params
	 *
	 * @return mixed
	 */
	private function addOptionalParams(array $targetArr, array $sourceArr, array $optionalParams) {
		foreach ($optionalParams as $param) {
			if (isset($sourceArr[$param])) {
				$targetArr[$param] = $sourceArr[$param];
			}
		}

		return $targetArr;
	}

	/**
	 * Request to create NETCONF session (connect)
	 * key: type (int), value: 4
	 * key: user (string)
	 *
	 * Optional:
	 * key: host (string), "localhost" if not specified
	 * key: port (string), "830" if not specified
	 * key: pass (string), value: plain text password, mandatory if “privatekey” is not set
	 * key: privatekey (string), value: filesystem path to the private key, if set, “pass” parameter is optional and changes into the pass for this private key
	 *
	 * @param  resource &$sock     socket descriptor
	 * @param  array    &$params  connection params for mod_netconf
	 * @param  mixed    &$result  result of searching of new connection in all connections
	 * @return int                0 on success
	 */
	private function handle_connect(&$sock, &$params, &$result = null) {
		$session = $this->container->get('request')->getSession();

		$connectParams = array(
			"type" => self::MSG_CONNECT,
			"host" => $params["host"],
			"port" => $params["port"],
			"user" => $params["user"],
			"pass" => $params["pass"],
			"capabilities" => $params["capabilities"],
		);
		$connectParams = $this->addOptionalParams($connectParams, $params, array('host', 'port', 'user', 'pass', 'privatekey'));

		$connect = json_encode($connectParams);
		$this->write2socket($sock, $connect);
		$response = $this->readnetconf($sock);
		$decoded = json_decode($response, true);

		if ($decoded) {
			$newConnection = reset($decoded);
		}

		if (isset($newConnection["type"]) && ($newConnection["type"] == self::REPLY_OK)) {
			$param = array( "sessions" => array($newConnection['session']));
			$status = $this->handle_info($sock, $param);
			$newconnection = new ConnectionSession($newConnection['session'], $params["host"], $params["port"], $params["user"]);
			$newconnection->sessionStatus = json_encode($status);
			$newconnection = serialize($newconnection);

			if ( !$sessionConnections = $session->get("session-connections") ) {
				$session->set("session-connections", array($newconnection));
			} else {
				$sessionConnections[] = $newconnection;
				$session->set("session-connections", $sessionConnections);
			}

			$session->getFlashBag()->add('success', "Successfully connected.");
			$result = array_search($newconnection, $session->get("session-connections"));

			return 0;
		} else {
			$this->logger->addError("Could not connect.", array("error" => (isset($newConnection["errors"])?" Error: ".var_export($newConnection["errors"], true) : var_export($this->getJsonError(), true))));
			if (isset($newConnection['errors'])) {
				foreach ($newConnection['errors'] as $error) {
					$session->getFlashBag()->add('error', $error);
				}
			} else {
				$session->getFlashBag()->add('error', "Could not connect. Unknown error.");
			}
			return 1;
		}
	}

	/**
	 * Request to close NETCONF session (disconnect)
	 * key: type (int), value: 5
	 * key: sessions (array of ints), value: array of SIDs
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return int             		0 on success, 1 on error
	 */
	private function handle_disconnect(&$sock, &$params) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
		$sessionKeys = $this->getHashFromKeys($params['connIds'], true);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_DISCONNECT,
			"sessions" 	=> array_keys($sessionKeys)
		));

		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Session ".$sid." successfully disconnected.");
			} else {
				$this->logger->addError("Could not disconnect.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not disconnect session ".$sid." from server. ");
			}

			$key = array_search($sid, $sessionKeys);
			if ($key) {
				unset( $sessionConnections[$key] );
			}
		}

		$session->set("session-connections", $sessionConnections);
	}

	/**
	 * NETCONF <get> (returns merged data)
	 * key: type (int), value: 6
	 * key: sessions (array of ints), value: array of SIDs
	 * key: strict (bool), value: whether return error on unknown data
	 *
	 * Optional:
	 * key: filter (string), value: xml subtree filter
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params 	array of values for mod_netconf (type, params...)
	 * @return mixed		          decoded data on success
	 */
	public function handle_get(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$getParams = array(
			"type" 		=> self::MSG_GET,
			"sessions" 	=> $this->getHashFromKeys($params['connIds']),
			"source" 	=> "running",
		);
		$getParams = $this->addOptionalParams($getParams, $params, array('filters'));

		$decoded = $this->execute_operation($sock, $getParams);

		return $this->checkDecodedData($decoded);
	}

	/**
	 * NETCONF <get-config> (returns array of responses merged with schema)
	 * key: type (int), value: 7
	 * key: sessions (array of ints), value: array of SIDs
	 * key: source (string), value: running|startup|candidate
	 * key: strict (bool), value: whether return error on unknown data
	 *
	 * Optional:
	 * key: filter (string), value: xml subtree filter
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)	 *
	 * @return mixed                decoded data on success, 1 on error
	 */
	public function handle_getconfig(&$sock, &$params)	{
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$getconfigParams = array(
			"type" 		=> self::MSG_GETCONFIG,
			"sessions" 	=> $this->getHashFromKeys($params['connIds']),
			"source" 	=> $params['source'],
		);
		$this->addOptionalParams($getconfigParams, $params, array('filters'));

		$decoded = $this->execute_operation($sock, $getconfigParams);
		return $this->checkDecodedData($decoded);
	}

	/** NETCONF <edit-config>
	 * key: type (int), value: 8
	 * key: sessions (array of ints), value: array of SIDs
	 * key: target (string), value: running|startup|candidate
	 * key: configs (array of sJSON, with the same order as sessions), value: array of edit configuration data according to NETCONF RFC for each session
	 *
	 * Optional:
	 * key: source (string), value: config|url, default value: config
	 * key: default-operation (string), value: merge|replace|none
	 * key: error-option (string), value: stop-on-error|continue-on-error|rollback-on-error
	 * key: uri-source (string), required when "source" is "url", value: uri
	 * key: test-option (string), value: notset|testset|set|test, default value: testset
	 *
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return mixed                decoded data on success, 1 on error
	 */
	private function handle_editconfig(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		/* edit-config to store new values */
		$editparams = array(
			"type" => self::MSG_EDITCONFIG,
			"sessions" => $this->getHashFromKeys($params['connIds']),
			"target" => $params['target'],
			"configs" => $params['configs'],
		);
		$editparams = $this->addOptionalParams($editparams, $params, array('source', 'default-operation', 'error-option', 'uri-source', 'test-option'));

		$decoded = $this->execute_operation($sock, $editparams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * NETCONF <copy-config>
	 * key: type (int), value: 9
	 * key: sessions (array of ints), value: array of SIDs
	 * key: source (string), value: running|startup|candidate|url|config
	 * key: target (string), value: running|startup|candidate|url
	 *
	 * Optional:
	 * key: uri-source (string), required when "source" is "url", value: uri
	 * key: uri-target (string), required when "target" is "url", value: uri
	 * key: configs (array of sJSON, with the same order as sessions), required when “source” is “config”, value: array of new complete configuration data for each session,
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return mixed                decoded data on success, 1 on error
	 */
	private function handle_copyconfig(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$copyParams = array(
			"type" => self::MSG_COPYCONFIG,
			"sessions" => $this->getHashFromKeys($params['connIds']),
			"source" => $params['source'],
			"target" => $params['target'],
		);
		$copyParams = $this->addOptionalParams($copyParams, $params, array('uri-source', 'uri-target', 'configs'));

		$decoded = $this->execute_operation($sock, $copyParams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * NETCONF <delete-config>
	 * key: type (int), value: 10
	 * key: sessions (array of ints), value: array of SIDs
	 * key: target (string), value: running|startup|candidate|url
	 * Optional:
	 * key: url (string), value: target URL
 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return mixed                decoded data on success, 1 on error
	 */
	private function handle_deleteconfig(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$deleteParams = array(
			"type" => self::MSG_DELETECONFIG,
			"sessions" => $this->getHashFromKeys($params['connIds']),
			"target" => $params['target'],
		);
		$deleteParams = $this->addOptionalParams($deleteParams, $params, array('url'));

		$decoded = $this->execute_operation($sock, $deleteParams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * NETCONF <lock>
	 * key: type (int), value: 11
	 * key: sessions (array of ints), value: array of SIDs
	 * key: target (string), value: running|startup|candidate
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 *
	 * @return null|int                  1 on error
	 */
	private function handle_lock(&$sock, &$params) {

		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeys = $this->getHashFromKeys($params['connIds'], true);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_LOCK,
			"target"	=> $params['target'],
			"session" 	=> array_values($sessionKeys)
		));

		$lockedConnIds = array();
		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Session ".$sid." successfully locked.");
				$lockedConnIds[] = array_search($sid, $sessionKeys);
			} else {
				$this->logger->addError("Could not lock.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not lock datastore for session " .$sid. ". ");
			}
		}

		$this->updateConnLock($lockedConnIds, $params['target']);
	}

	/**
	 * NETCONF <unlock>
	 * key: type (int), value: 12
	 * key: sessions (array of ints), value: array of SIDs
	 * key: target (string), value: running|startup|candidate
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array &$params array of values for mod_netconf (type, params...)
	 *
	 * @return int                    0 on success, 1 on error
	 */
	private function handle_unlock(&$sock, &$params) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeys = $this->getHashFromKeys($params['connIds'], true);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_UNLOCK,
			"target"	=> $params['target'],
			"sessions" 	=> array_values($sessionKeys),
		));

		$lockedConnIds = array();
		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Session ".$sid." successfully unlocked.");
				$lockedConnIds[] = array_search($sid, $sessionKeys);
			} else {
				$this->logger->addError("Could not unlock.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not unlock session ".$sid.". ");
			}
		}

		$this->updateConnLock($lockedConnIds, $params['target']);
	}

	/**
	 * NETCONF <kill-session>
	 * key: type (int), value: 13
	 * key: sessions (array of ints), value: array of SIDs
	 * key: session-id (int), value: SID of the session to kill
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params must contain "session-id"
	 * @param  mixed    &$result decoded data from response
	 *
	 * @return int                0 on success, 1 on error
	 */
	private function handle_killsession(&$sock, &$params, &$result) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeys = $this->getHashFromKeys($params['connIds'], true);

		$arguments = array(
			"type" 		=> self::MSG_KILL,
			"sessions" 	=> array_values($sessionKeys),
			"session-id"	=> $params["session-id"],
		);

		$decoded = $this->execute_operation($sock, $arguments);

		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Session ".$sid." successfully killed.");
			} else {
				$this->logger->addError("Could not kill session.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not kill session ".$sid.".");
			}
		}
	}

	/**
	 * Provide information about NETCONF session
	 * key: type (int), value: 14
	 * key: sessions (array of ints), value: array of SIDs
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return int                  0 on success, 1 on error
	 */
	private function handle_info(&$sock, &$params) {
		if (isset($params["sessions"])) {
			$sessionKeys = $params['sessions'];
		} else {
			if ($this->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getHashFromKeys($params['connIds']);
		}

		$session = $this->container->get('request')->getSession();
		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_INFO,
			"sessions" 	=> $sessionKeys
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->logger->addError("Could get session info.", array("error" => var_export($decoded, true)));
			$session->getFlashBag()->add('error', "Could not get session info.");
		}

		return $this->checkDecodedData($decoded);
	}

	/**
	 * Perform generic operation not included in base NETCONF
	 * key: type (int), value: 15
	 * key: sessions (array of ints), value: array of SIDs
	 * key: contents (array of sJSON with same index order as sessions array), value: array of sJSON data as content of the NETCONF's <rpc> envelope
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return int                  0 on success, 1 on error
	 */
	private function handle_generic(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$genericParams = array(
			"type" => self::MSG_GENERIC,
			"sessions" => $this->getHashFromKeys($params['connIds']),
			"contents" => $params['contents'],
		);

		$decoded = $this->execute_operation($sock, $genericParams);

		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Successful call of method.");
			} else {
				$this->logger->addError("User RPC call.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "RPC error: ".
				                                      ((isset($response["errors"]) && sizeof($response['errors'])) ? $response["errors"][0] : ""));
			}
		}

		return $this->checkDecodedData($decoded);
	}

	/**
	 * handle getschema action
	 * key: type (int), value: 16
	 * key: sessions (array of ints), value: array of SIDs
	 * key: identifiers (array of strings with same index order as sessions array), value: array of schema identifiers
	 * Optional:
	 * key: format (string), value: format of the schema (yin or yang)
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params must contain "identifier" of schema, can contain "version" and "format" of schema
	 * @param  mixed    &$result decoded data from response
	 *
	 * @return int                    0 on success, 1 on error
	 */
	private function handle_getschema(&$sock, &$params, &$result) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeys = $this->getHashFromKeys($params['connIds'], true);

		$arguments = array(
			"type" 		=> self::MSG_GETSCHEMA,
			"sessions" 	=> array_values($sessionKeys),
			"identifiers"	=> $params["identifiers"],
		);
		$arguments = $this->addOptionalParams($arguments, $params, array('format', 'version'));

		$decoded = $this->execute_operation($sock, $arguments);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * Update hello message of NETCONF session
	 * key: type (int), value: 17
	 * key: sessions (array of ints), value: array of SIDs
	 *
	 * Result is the same as from handle_info()
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return int                  0 on success, 1 on error
	 */
	private function handle_reloadhello(&$sock, &$params) {
		$session = $this->container->get('request')->getSession();

		if (isset($params["sessions"]) && ($params["sessions"] !== "")) {
			$sessionKeys = $params['sessions'];
		} else {
			if ($this->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getHashFromKeys($params['connIds']);
		}

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_RELOADHELLO,
			"sessions" 	=> $sessionKeys
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->logger->addError("Could get reload hello.", array("error" => var_export($decoded, true)));
			$session->getFlashBag()->add('error', "Could not reload device informations.");
			return 1;
		}

		return $this->checkDecodedData($decoded);
	}

	/**
	 * Provide list of notifications from past.
	 * key: type (int), value: 18
	 * key: sessions (array of ints), value: array of SIDs
	 * key: from (int64), value: start time in history
	 * key: to (int64), value: end time
	 *
	 * @param  resource &$sock   socket descriptor
	 * @param  array    &$params array of values for mod_netconf (type, params...)
	 *
	 * @return int                  0 on success, 1 on error
	 */
	private function handle_notif_history(&$sock, &$params) {
		if (isset($params["sessions"]) && ($params["sessions"] !== "")) {
			$sessionKeys = $params['sessions'];
		} else {
			$session = $this->container->get('request')->getSession();
			$sessionKeys = $this->getHashFromKeys($params['connIds']);
		}

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_NTF_GETHISTORY,
			"sessions" 	=> $sessionKeys,
			"from" => $params['from'],
			"to" => $params['to']
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->logger->addError("Could get notifications history.", array("error" => var_export($decoded, true)));
			$session->getFlashBag()->add('error', "Could not get notifications history.");
			return 1;
		}

		return $this->checkDecodedData($decoded);
	}


	/**
	 * Validate datastore or url
	 * key: type (int), value: 19
	 * key: sessions (array of ints), value: array of SIDs
	 * key: target (string), value: running|startup|candidate|url
	 * Required when target is "url":
	 * key: url (string), value: URL of datastore to validate
	 *
	 * @param $sock
	 * @param $params
	 *
	 * @return int|mixed
	 */
	public function handle_validate(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$validateParams = array(
			"type" 		=> self::MSG_VALIDATE,
			"session" 	=> $this->getHashFromKeys($params['connIds']),
			"target" 	=> $params['target'],
		);
		$validateParams = $this->addOptionalParams($validateParams, $params, array('url'));

		$decoded = $this->execute_operation($sock, $validateParams);
		return $this->checkDecodedData($decoded);
	}



	/**
	 * checks, if logged keys are valid
	 *
	 * @return int       		0 on success, 1 on error
	 */
	public function checkLoggedKeys() {
		$session = $this->container->get('request')->getSession();
		if ( !count($session->get("session-connections")) ) {
			$session->getFlashBag()->add('error', "Not logged in.");
			return 1;
		}
		$req = $this->container->get('request');

		if ( !in_array( $req->get('key'), array_keys($session->get("session-connections")) ) ) {
			$session->getFlashBag()->add('error', "You are not allow to see this connection. Bad Index of key.");
			return 1;
		}
		return 0;
	}

	/**
	 * checks decoded data, if there an error occurs
	 *
	 * @param  mixed  &$decoded 	decoded data
	 * @return mixed				    decoded data on success, 1 on error
	 */
	private function checkDecodedData(&$decoded) {
		$session = $this->container->get('request')->getSession();
		$status = $this->getJsonError();

		if ( $status['errorCode'] ) {
			$this->logger->warn('Checking decoded data:', array('error' => $status['message']));
			$session->getFlashBag()->add('error', $status['message']);
			return 1;
		//} elseif ( $decoded == null ) {
		//	$this->logger->addError('Could not decode response from socket', array('error' => "Empty response."));
		//	$session->getFlashBag()->add('error', "Could not decode response from socket. Error: Empty response.");
		//	return 1;
		} elseif (($decoded['type'] != self::REPLY_OK) && ($decoded['type'] != self::REPLY_DATA)) {
			$this->logger->warn('Error: ', array('errors' => $decoded['errors']));
			if (sizeof($decoded['errors'])) {
				foreach ($decoded['errors'] as $error) {
					$session->getFlashBag()->add('error', $error);
				}
			}
			// throw new \ErrorException($decoded['error-message']);
			return 1;
		}
		return isset($decoded["data"]) ? $decoded["data"] : -1;
	}

	/**
	 * Wrap message for Chunked Framing Mechanism (RFC6242) and write it into the socket.
	 *
	 * @param  resource &$sock   			socket descriptor
	 * @param  string   $message 	    message text
	 */
	private function write2socket(&$sock, $message)	{
		$final_message = sprintf("\n#%d\n%s\n##\n", strlen($message), $message);
		fwrite($sock, $final_message);
	}

	/**
	 * executes operation - sends message into the socket
	 *
	 * @param  resource   &$sock  			socket descriptor
	 * @param  array      $params 	  array of values for mod_netconf (type, params...)
	 * @return array         	        response from mod_netconf
	 */
	private function execute_operation(&$sock, $params)	{
		$operation = json_encode($params);
		$this->write2socket($sock, $operation);
		$response = $this->readnetconf($sock);
		return json_decode($response, true);
	}

	/**
	 * Get path to directory of data models.
	 *
	 * @return string      path to models folder
	 */
	public function getModelsDir() {
		return __DIR__ . '/../Data/models/';
	}

	/**
	 * gets model dir name for module
	 * @param  string $moduleName   name of module
	 * @return string               dir name on success, false on error
	 */
	public function getModelDirForName($moduleName) {
		$key = $this->container->get('request')->get('key');
		$res = $this->getModulePathByRootModuleName($key, $moduleName);
		if ($res) {
			return $res;
		}
		return false;
	}

	/**
	 * checks if model dir for module exists
	 * @param  string $moduleName   name of module
	 * @return bool
	 */
	public function existsModelDirForName($moduleName) {
		$key = $this->container->get('request')->get('key');
		$res = $this->getModulePathByRootModuleName($key, $moduleName);
		if ($res) {
			return true;
		}
		return false;
	}

	/**
	 * get path to models in file system
	 * @param  string $moduleName  name of the module
	 * @return string               path to wrapped model file
	 */
	public function getPathToModels($moduleName = "") {
		$path = $this->getModelsDir();

		if ($moduleName == "") {
			$moduleName = $this->container->get('request')->get('module');
		}
		// add module directory if is set in route
		if ($moduleName != "") {
			$modelDir = $this->getModelDirForName($moduleName);
			if ($modelDir) {
				$path .= $modelDir . '/';
			}
		}
		// add subsection directory if is set in route and wrapped file in subsection directory exists
		if ( $this->container->get('request')->get('subsection') != null
				&& file_exists($path . $this->container->get('request')->get('subsection').'/wrapped.wyin')) {
			$path .= $this->container->get('request')->get('subsection').'/';
		}
		return $path;
	}

	/**
	 * handles all actions, which are allowed on socket
	 * this is the only one entry point for calling methods such a <get>, <get-config>, <validate>...
	 *
	 * @param  string   $command 			kind of action (command)
	 * @param  array    $params       parameters for mod_netconf
	 * @param  bool     $merge        should be action handle with merge with model
	 * @param  mixed    $result
	 * @return int						        0 on success, 1 on error
	 */
	public function handle($command, $params = array(), $merge = true, &$result = null) {
		$errno = 0;
		$errstr = "";

		$logParams = $params;
		if ( $command == "connect" ) {
			// we won't log password
			unset($logParams['pass']);
		}
		$this->logger->info('Handle: '.$command.' with params', array('params' => $logParams));

		/**
		 * check, if current command had not been called in the past. If yes, we will
		 * return previous response (and not ask again for response from server).
		 */
		$hashedParams = sha1(json_encode($params));
//		if (isset($this->handleResultsArr[$command])) {
//
//			if ($merge && isset($this->handleResultsArr[$command][$hashedParams]['merged'])) {
//				return $this->handleResultsArr[$command][$hashedParams]['merged'];
//			} elseif (isset($this->handleResultsArr[$command][$hashedParams]['unmerged'])) {
//				return $this->handleResultsArr[$command][$hashedParams]['unmerged'];
//			}
//		}

                $socket_path = '/var/run/netopeerguid.sock';
                if (!file_exists($socket_path)) {
			$this->logger->addError('Backend is not running or socket file does not exist.', array($socket_path));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Backend is not running or socket file does not exist: ".$socket_path);
                        return 1;
                }
                if (!is_readable($socket_path)) {
			$this->logger->addError('Socket is not readable.', array($socket_path));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Socket is not readable: ".$socket_path);
                        return 1;
                }
                if (!is_writable($socket_path)) {
			$this->logger->addError('Socket is not writable.', array($socket_path));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Socket is not writable: ".$socket_path);
                        return 1;
                }
                $socket_path = 'unix://'.$socket_path;
		try {
			$sock = fsockopen($socket_path, NULL, $errno, $errstr);
		} catch (\ErrorException $e) {
			$this->logger->addError('Could not connect to socket.', array($errstr));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not connect to socket. Error: $errstr");
			return 1;
		}

		if ($errno != 0) {
			$this->logger->addError('Could not connect to socket.', array($errstr));
			$this->container->get('request')->getSession()->getFlashBag()->add('error', "Could not connect to socket. Error: $errstr");
			return 1;
		}
		//stream_set_timeout($sock, 5, 500);
		stream_set_blocking($sock, 1);

		switch ($command) {
			case "connect":
				$res = $this->handle_connect($sock, $params, $result);
				break;
			case "disconnect":
				$res = $this->handle_disconnect($sock, $params);
				break;
			case "get":
				$res = $this->handle_get($sock, $params);
				break;
			case "getconfig":
				$res = $this->handle_getconfig($sock, $params);
				break;
			case "editconfig":
				$this->logger->info("Handle editConfig: ", array('configToSend' => $params['config']));
				$this->container->get('XMLoperations')->validateXml($params['config']);
				$res = $this->handle_editconfig($sock, $params);
				break;
			case "copyconfig":
				$res = $this->handle_copyconfig($sock, $params);
				break;
			case "deleteconfig":
				$res = $this->handle_deleteconfig($sock, $params);
				break;
			case "lock":
				$res = $this->handle_lock($sock, $params);
				break;
			case "unlock":
				$res = $this->handle_unlock($sock, $params);
				break;
			case "killsession":
				$res = $this->handle_killsession($sock, $params, $result);
				break;
			case "info":
				$res = $this->handle_info($sock, $params);
				break;
			case "userrpc":
			case "generic":
				$res = $this->handle_generic($sock, $params, $result);
				break;
			case "getschema":
				$res = $this->handle_getschema($sock, $params, $result);
				break;
			case "reloadhello":
				$res = $this->handle_reloadhello($sock, $params);
				break;
			case "notif_history":
				// JSON encoded data OR 1 on error, so we can return it now
				return $this->handle_notif_history($sock, $params);
				break;
			case "validate":
				$res = $this->handle_validate($sock, $params, $result);
				break;
			case "backup":
				$params["source"] = "startup";
				$res_startup = "<startup>".$this->handle_getconfig($sock, $params)."</startup>";
				$params["source"] = "running";
				$res_running = "<running>".$this->handle_getconfig($sock, $params)."</running>";
				$params["source"] = "candidate";
				$res_candidate = "<candidate>".$this->handle_getconfig($sock, $params)."</candidate>";
				$res = "<webgui-backup>".$res_startup.$res_running.$res_candidate."</webgui-backup>";
				return $res;
			default:
				$this->container->get('request')->getSession()->getFlashBag()->add('info', printf("Command not implemented yet. (%s)", $command));
				return 1;
		}

		fclose($sock);
		$this->logger->info("Handle result: ".$command, array('response' => $res));

		if ($command === "info") {
			$this->handleResultsArr['info'] = $res;
		}

		if ( isset($res) && $res !== 1 && $res !== -1) {
			if (!$this->container->get('XMLoperations')->isResponseValidXML($res)) {
				$this->container->get('request')->getSession()->getFlashBag()->add( 'error', "Requested XML from server is not valid.");
				return 0;
			}

			if ($merge) {
				$res = $this->container->get('XMLoperations')->mergeXMLWithModel($res);
				if ($res !== -1) {
					$this->handleResultsArr[$command][$hashedParams]['merged'] = $res;
				} else {
					return $res;
				}
			} else {
				$this->handleResultsArr[$command][$hashedParams]['unmerged'] = $res;
			}

			return $res;
		} else if ($res !== -1) {
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
			$finder = new Finder();

			$params = array(
				'key' => $key,
				'source' => 'running',
				'filter' => "",
			);

			$allowedModels = array();
			$allowedSubmenu = array();
			$namespaces = array();

			try {
				// load GET XML from server
				if ( ($xml = $this->handle('get', $params, false)) != 1 ) {
					$xml = simplexml_load_string($xml, 'SimpleXMLIterator');

					$xmlNameSpaces = $xml->getNamespaces();

					if ( isset($xmlNameSpaces[""]) ) {
						$xml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
						$nodes = $xml->xpath('/xmlns:*');
					} else {
						$nodes = $xml->xpath('/*');
					}

					// get first level nodes (so without root) as items for top menu
					foreach ($nodes as $node) {
						foreach ($node as $nodeKey => $submenu) {
							$ns = $submenu->getNameSpaces();
							$i = 0;
							if (isset($namespaces[$nodeKey])) {
								$i = 1;
								while(isset($namespaces[$nodeKey.$i])) {
									$i++;
								}
								$namespaces[$nodeKey.$i] = $ns[""];
							} else {
								$namespaces[$nodeKey] = $ns[""];
							}

							if (!in_array(array("name" => $nodeKey, 'index' => $i), $allowedModels) ) {
								$allowedModels[] = array("name" => $nodeKey, 'index' => $i);
							}

							foreach ($submenu as $subKey => $tmp) {
								if ( !in_array(array("name" => $subKey, 'index' => 0), $allowedSubmenu) ) {
									$allowedSubmenu[] = array("name" => $subKey, 'index' => 0);
								}
							}
						}
					}
				}
			} catch (\ErrorException $e) {
				$this->logger->addError("Could not build MenuStructure", array('key' => $key, 'path' => $path, 'error' => $e->getMessage()));
				// nothing
			}
			$this->setModelNamespaces($key, $namespaces);


			// we will check, if nodes from GET are same as models structure
			// if not, they won't be displayed
			$folders = array();
			sort($allowedModels);
			foreach ($allowedModels as $module) {
				$moduleWithIndex = $module['name'];
				if ($module['index'] !== 0) {
					$moduleWithIndex .= $module['index'];
				}
				if ($this->existsModelDirForName($moduleWithIndex)) {
					$folders[$moduleWithIndex] = array(
						'path' => "module",
						"params" => array(
							'key' => $key,
							'module' => $moduleWithIndex,
						),
						"title" => "detail of ".$this->getSectionName($module['name']),
						"name" => $this->getSectionName($module['name']),
						"children" => $this->buildSubmenu($key, $module, $allowedSubmenu),
						"namespace" => $namespaces[$moduleWithIndex],
					);
				}
			}
			$this->setModels($key, $folders);
		}
	}

	/**
	 * prepares left submenu - modules of current top menu item
	 *
	 * @param  int  $key session key of current connection
	 * @param array $module       array with indexes: name and index
	 * @param $allowedSubmenu
	 * @param string $path
	 * @return array
	 */
	private function buildSubmenu($key, $module, $allowedSubmenu, $path = "") {
		$finder = new Finder();

		$moduleWithIndex = $module['name'];
		if ($module['index'] !== 0) {
			$moduleWithIndex .= $module['index'];
		}

		// we will check, if nodes from GET are same as models structure
		// if not, they won't be displayed
		$dir = $this->getPathToModels($moduleWithIndex);
		if ( !file_exists($dir) ) {
			$folders = array();
		} else {
			$iterator = $finder
			  ->directories()
			  ->sortByName()
			  ->depth(0)
			  ->in($dir);

			$folders = array();
			foreach ($iterator as $folder) {
				$subsection = $folder->getRelativePathname();
				if ( in_array(array("name" => $subsection, "index" => 0), $allowedSubmenu) ) {
					$folders[] = array(
						'path' => "subsection",
						"params" => array(
							'key' => $key,
							'module' => $moduleWithIndex,
							'subsection' => $subsection,
						),
						"title" => "detail of ".$this->getSubsectionName($subsection),
						"name" => $this->getSubsectionName($subsection),
						// "children" => $this->getSubmenu($key, $completePath),
					);
				}
			}
		}
		return $folders;
	}

	/**
	 * loading file with filter specification for current module or subsection
	 *
	 * @param  string $module     module name
	 * @param  string $subsection subsection name
	 * @return array              array with config and state filter
	 */
	public function loadFilters(&$module, &$subsection) {
		// if file filter.txt exists in models, we will use it
		$filterState = $filterConfig = "";

		$namespaces = $this->getModelNamespaces($this->container->get('request')->get('key'));
		if (isset($namespaces[$module])) { $namespace = $namespaces[$module];
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
	 * Get models.
	 *
	 * @param  int    $key        session key of current connection
	 * @return array|null
	 */
	public function getModels($key = -1) {
		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');

		if ($key === -1) {
			$key = $this->container->get('request')->get('key');
		}
		$hashedKey = $this->getHashFromKeys($key);
		if ($hashedKey && $cache->contains('menuStructure_'.$hashedKey)) {
//			$this->logger->addInfo("Cached file for menuStructure found.", array('key' => 'menuStructure_'.$hashedKey));
			return $cache->fetch('menuStructure_'.$hashedKey);
		}
		return $this->models;
	}

	/**
	 * save model folder structure
	 *
	 * @param  int    $key        session key of current connection
	 * @param  array  $folders    array to persist
	 * @param   int   $lifetime   cache lifetime in seconds
	 */
	public function setModels($key, $folders, $lifetime = 6000) {
		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');
		$hashedKey = $this->getHashFromKeys($key);
		$this->models = $folders;
		$cache->save('menuStructure_'.$hashedKey, $folders, $lifetime);
	}

	/**
	 * Get model namespaces.
	 *
	 * @param  int    $key        session key of current connection
	 * @return array|null
	 */
	public function getModelNamespaces($key) {
		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');
		$hashedKey = $this->getHashFromKeys($key);
		if ($hashedKey && $cache->contains('modelNamespaces_'.$hashedKey)) {
//			$this->logger->addInfo("Cached file for modelNamespaces found.", array('key' => 'modelNamespaces_'.$hashedKey));
			return $cache->fetch('modelNamespaces_'.$hashedKey);
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
		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');
		$hashedKey = $this->getHashFromKeys($key);
		$this->modelNamespaces = $namespaces;
		$cache->save('modelNamespaces_'.$hashedKey, $namespaces, $lifetime);
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
		/**
		 * @var \winzou\CacheBundle\Cache\LifetimeFileCache $cache
		 */
		$cache = $this->container->get('winzou_cache');
		$hashedKey = $this->getHashFromKeys($key);
		if ($hashedKey && $cache->contains('modelNamespaces_'.$hashedKey)) {
			$this->logger->addInfo("Invalidate cached file", array('key' => 'modelNamespaces_'.$hashedKey));
			$cache->delete('modelNamespaces_'.$hashedKey);
		}
		if ($hashedKey && $cache->contains('menuStructure_'.$hashedKey)) {
			$this->logger->addInfo("Invalidate cached file", array('key' => 'menuStructure_'.$hashedKey));
			$cache->delete('menuStructure_'.$hashedKey);
		}
		$cache->deleteDeads();
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
		$path = $this->getPathToModels($moduleName).'tree.txt';

		if (file_exists($path)) {
			return file_get_contents($path);
		}

		return 0;
	}

	/**
	 * Loads file with identities for identity refs and loads content of this file with json_decode
	 *
	 * @return array|int      0 on error, json decoded array on success
	 */
	public function loadIdentityRefs() {
		$path = $this->getModelsDir();

		// identites.json is located in tmp dir
		$path .= 'tmp/identities.json';

		if ($content = file_get_contents($path)) {
			return json_decode($content, true);
		}

		return 0;
	}

	/**
	 * Loads only identity refs for given module
	 *
	 * @param $key
	 * @param $module
	 *
	 * @return array|int
	 */
	public function loadIdentityRefsForModule($key, $module) {
		$idrefs = $this->loadIdentityRefs();
		$identities = array();

		if ($idrefs) {
			$ns = $this->getNamespaceForModule($key, $module);
			$prefix = array_search($ns, $idrefs['prefixes']);
			if ($prefix) {
				foreach ($idrefs['identities'] as $key => $values) {
					if (strpos($key, $prefix.":") === 0) {
						asort($values);
						$identities[str_replace($prefix.":", '', $key)] = $values;
					}
				}
			}

			return $identities;
		}

		return 0;
	}

	/**
	 * Get one model and process it.
	 *
	 * @param array &$schparams  key, identifier, version, format for get-schema
	 * @param string $identifier identifier of folder in modelsDir directory
	 * @return int               0 on success, 1 on error
	 */
	private function getschema(&$schparams, $identifier)
	{
		$data = "";
		$path = $this->getModelsDir()."/tmp/";
		@mkdir($path, 0700, true);
		$path .= "/$identifier";

		if (file_exists($path)) {
			/* already exists */
			$schparams["path"] = $path;
			return -1;
		}

		if ($this->handle("getschema", $schparams, false, $data) == 0) {
			$schparams["user"] = $this->getUserFromKey($schparams["key"]);
			file_put_contents($path, $data);
			$schparams["path"] = $path;
			return 0;
		} else {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', 'Getting models failed.');
			return 1;
		}
		return 0;
	}

	/**
	 * Process <get-schema> action based on schparams.
	 *
	 * @param array &$schparams   get-schema parameters
	 * @return int                0 on success, 1 on error
	 */
	private function processSchema(&$schparams)
	{
		$path = $schparams["path"];

		$res = @system(__DIR__."/../bin/nmp.sh -i \"$path\" -o \"".$this->getModelsDir()."\"");
		ob_clean();
		$this->logger->addInfo("Process schema result (Pyang console): ", array('path' => $path, 'modelDir' => $this->getModelsDir(), 'res' => $res));
		return 1;
	}

	/**
	 * Get available configuration data models,
	 * store them and transform them.
	 *
	 * @param  int   $key 	index of session-connection
	 * @return void
	 */
	public function updateLocalModels($key)
	{
		$ns = "urn:ietf:params:xml:ns:yang:ietf-netconf-monitoring";
		$params = array(
			'key' => $key,
			'filter' => '<netconf-state xmlns="'.$ns.'"><schemas><schema><identifier/><version/><format>yin</format><namespace/><location>NETCONF</location></schema></schemas></netconf-state>',
		);

		$xml = $this->handle('get', $params, false);
		// TODO: try/catch na simplexml_load_string
		if (($xml !== 1) && ($xml !== "")) {
			$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
			if ($xml === false) {
				/* invalid message received */
				$this->container->get('request')->getSession()->getFlashBag()->add('error', 'Getting the list of schemas failed.');
				return;
			}
			$xml->registerXPathNamespace("xmlns", $ns);
			$schemas = $xml->xpath("//xmlns:schema");

			$this->logger->addInfo("Trying to find models for namespaces: ", array('namespaces' => var_export($schemas, true)));

			$list = array();
			$lock = sem_get(12345678, 1, 0666, 1);
			sem_acquire($lock); /* critical section */
			foreach ($schemas as $sch) {
				$schparams = array("key" => $params["key"],
					"identifier" => (string)$sch->identifier,
					"version" => (string)$sch->version,
					"format" => (string)$sch->format);
				$ident = $schparams["identifier"]."@".$schparams["version"].".".$schparams["format"];
				if (file_exists($this->getModelsDir()."/$ident")) {
					$this->addLog("Model found, skipping: ", array('ident', $ident));
					continue;
				} else if ($this->getschema($schparams, $ident) == -1) {
					$this->logger->addInfo("Get schema file found, but no models.", array("ident" => $ident));
					$list[] = $schparams;
				} else if ($this->getschema($schparams, $ident) == 1) {
					continue; // get schema failed, skipping
				} else {
					$list[] = $schparams;
				}
			}
			sem_release($lock);


			$res = @system(__DIR__."/../bin/find-identities.sh '".$this->getModelsDir()."/tmp/'");
			ob_clean();
			/* non-critical - only models, that I downloaded will be processed, others already exist */
			foreach ($list as $schema) {
				$this->processSchema($schema);
			}
			$this->container->get('request')->getSession()->getFlashBag()->add('success', 'Configuration data models were updated.');
		} else {
			$this->container->get('request')->getSession()->getFlashBag()->add('error', 'Getting the list of schemas failed.');
		}
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

	/**
	 * Add text to info log.
	 *
	 * @param $str
	 */
	private function addLog($str) {
		$this->logger->addInfo($str);
	}

}
