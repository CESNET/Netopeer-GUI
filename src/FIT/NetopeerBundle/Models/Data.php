<?php
/**
 * Base class for all communication with mod_netconf,
 * getting and processing input and output data.
 */
namespace FIT\NetopeerBundle\Models;

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

	/**
	 * @var ContainerInterface   base bundle container
	 */
	protected $container;
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	protected $logger;
	/**
	 * @var string    current state of flash messages
	 */
	private $flashState;
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
		$this->setFlashState('single');
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
	 * @param  int $key      session key
	 * @return string
	 */
	private function getHashFromKey($key) {
		$conn = $this->getConnFromKey($key);

		if (isset($conn->hash)) {
			return $conn->hash;
		}
		//throw new \ErrorException("No identification key was found.");
		return "NOHASH";
	}

	/**
	 * Find hash identifiers from DB for key
	 *
	 * @param  int $key session key
	 * @return array  return array of identifiers on success, false on error
	 */
	private function getModuleIdentifiersForCurrentDevice($key) {
		$conn = $this->getConnFromKey($key);
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
		}

		return false;
	}

	/**
	 * get path for module name, includes identifier
	 *
	 * @param  int $key      session key
	 * @param  string $moduleName name of element
	 * @return string           relative path on success, false on error
	 */
	private function getModulePathByRootModuleName($key, $moduleName) {
		if (!is_array($this->moduleIdentifiers) || !count($this->moduleIdentifiers)) {
			$this->getModuleIdentifiersForCurrentDevice($key);
		}

		if (isset($this->modelNamespaces[$moduleName])) {
			$cnt = count($this->modelNamespaces[$moduleName]);
			if ($cnt == 1) {
				$namespace = $this->modelNamespaces[$moduleName];
				if (isset($this->moduleIdentifiers[$namespace])) {
					return $this->moduleIdentifiers[$namespace]['hash'] .
							"/" . $this->moduleIdentifiers[$namespace]['moduleName'] .
							"/" . $this->moduleIdentifiers[$namespace]['revision'];
				}
			}
		}
		return false;
	}

	/**
	 * Find instance of SessionConnection.class for key.
	 *
	 * @param  int $key      session key
	 * @return bool|\ConnectionSession
	 */
	private function getConnFromKey($key) {
		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');
		if (isset($sessionConnections[$key]) && $key !== '') {
			return unserialize($sessionConnections[$key]);
		}
		return false;
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
	 * Updates array of SessionConnections.
	 *
	 * @param  int $key      session key
	 */
	private function updateConnLock($key) {
		$conn = $this->getConnFromKey($key);

		if ($conn == false) {
			return;
		}

		$session = $this->container->get('request')->getSession();
		$sessionConnections = $session->get('session-connections');

		$conn->locked = !$conn->locked;

		$sessionConnections[$key] = serialize($conn);
		$session->set('session-connections', $sessionConnections);
	}

	/**
	 * Read response from socket
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
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Reached timeout for reading response.");
		}
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrapRFC6242($response);
		} catch (\ErrorException $e) {
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Could not read NetConf. Error: ".$e->getMessage());
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
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Reading failure.");
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
				$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Reading failure.");
				break;
			}
			$response .= $tmp;
			//echo strlen($response) ."/". $size ."\n";
		}
		$status = stream_get_meta_data($sock);
		if (!$response && $status["timed_out"] == true) {
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Reached timeout for reading response.");
			//echo "Reached timeout for reading response.";
		}
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrapRFC6242($response);
		} catch (\ErrorException $e) {
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Could not read NetConf. Error: ".$e->getMessage());
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
	 * Handles connection to the socket
	 * @param  resource &$sock     socket descriptor
	 * @param  array    &$params  connection params for mod_netconf
	 * @param  mixed    &$result  result of searching of new connection in all connections
	 * @return int                0 on success
	 */
	private function handle_connect(&$sock, &$params, &$result = null) {
		$session = $this->container->get('request')->getSession();

		$connect = json_encode(array(
					"type" => self::MSG_CONNECT,
					"host" => $params["host"],
					"port" => $params["port"],
					"user" => $params["user"],
					"pass" => $params["pass"],
					"capabilities" => $params["capabilities"],
					));
		$this->write2socket($sock, $connect);
		$response = $this->readnetconf($sock);
		$decoded = json_decode($response, true);

		if ($decoded && ($decoded["type"] == self::REPLY_OK)) {
			$param = array( "session" => $decoded["session"] );
			$status = $this->handle_info($sock, $param);
			$newconnection = new ConnectionSession($decoded["session"], $params["host"], $params["port"], $params["user"]);
			$newconnection->sessionStatus = json_encode($status);
			$newconnection = serialize($newconnection);

			if ( !$sessionConnections = $session->get("session-connections") ) {
				$session->set("session-connections", array($newconnection));
			} else {
				$sessionConnections[] = $newconnection;
				$session->set("session-connections", $sessionConnections);
			}

			$session->setFlash($this->flashState .' success', "Successfully connected.");
			$result = array_search($newconnection, $session->get("session-connections"));
			/*
			$session->setFlash($this->flashState .' success', "Successfully connected.".
			"--".var_export($decoded).
			"--".var_export($newconnection, true).
			"--".var_export($sessionConnections, true));
			*/
			return 0;
		} else {
			$this->logger->err("Could not connect.", array("error" => var_export($this->getJsonError(), true)));
			$session->setFlash($this->flashState .' error', "Could not connect.".(isset($decoded["error-message"])?" Error: ".$decoded["error-message"]:""));
			return 1;
		}
	}

	/**
	 * Removes <?xml?> header from text.
	 *
	 * @param   string &$text  string to remove XML header in
	 * @return  mixed         returns an array if the subject parameter
	 *                        is an array, or a string otherwise.	If matches
	 *                        are found, the new subject will be returned,
	 *                        otherwise subject will be returned unchanged
	 *                        or null if an error occurred.
	 */
	public function removeXmlHeader(&$text)
	{
		return preg_replace("/<\?xml .*\?".">/i", "n", $text);
	}

	/**
	 * handle get action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params 	array of values for mod_netconf (type, params...)
	 * @return mixed		          decoded data on success
	 */
	public function handle_get(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}

		$sessionKey = $this->getHashFromKey($params['key']);

		$get_params = array(
			"type" 		=> self::MSG_GET,
			"session" 	=> $sessionKey,
			"source" 	=> "running",
		);
		if ($params['filter'] !== "") {
			$get_params["filter"] = $params['filter'];
		}

		$decoded = $this->execute_operation($sock, $get_params);

		return $this->checkDecodedData($decoded);
	}

	/**
	 * handle get config action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return mixed          		decoded data on success, 1 on error
	 */
	public function handle_getconfig(&$sock, &$params)	{
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}
		$sessionKey = $this->getHashFromKey($params['key']);

		$getconfigparams = array(
			"type" 		=> self::MSG_GETCONFIG,
			"session" 	=> $sessionKey,
			"source" 	=> $params['source'],
		);
		if(isset($params['filter']) && $params['filter'] !== "") {
			$getconfigparams["filter"] = $params['filter'];
		}
		$decoded = $this->execute_operation($sock, $getconfigparams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * handle edit config action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return mixed          		decoded data on success, 1 on error
	 */
	private function handle_editconfig(&$sock, &$params) {
		if ( $this->checkLoggedKeys() != 0) {
			return 1;
		}
		$sessionKey = $this->getHashFromKey($params['key']);
		/* syntax highlighting problem if XML def. is in one string */
		$replaceWhatArr = array(
			"<?xml version=\"1.0\"?>",
			"<root>",
			"</root>"
		);
		$replaceWithArr = array(
			"",
			"",
			""
		);
		$params['config'] = str_replace($replaceWhatArr, $replaceWithArr, $params['config']);

		/* edit-config to store new values */
		$params = array(
			"type" => self::MSG_EDITCONFIG,
			"session" => $sessionKey,
			"target" => $params['target'],
			"config" => $params['config']
		);
		$decoded = $this->execute_operation($sock, $params);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * handle get config action
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
		$requestKey = $params['key'];
		$sessionKey = $this->getHashFromKey($params['key']);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_DISCONNECT,
			"session" 	=> $sessionKey
		));

		if ($decoded["type"] === self::REPLY_OK) {
			$session->setFlash($this->flashState .' success', "Successfully disconnected.");
		} else {
			$this->logger->err("Could not disconnecd.", array("error" => var_export($decoded, true)));
			$session->setFlash($this->flashState .' error', "Could not disconnect from server. ");
		}

		unset( $sessionConnections[ $requestKey] );
		$session->set("session-connections", array_values( $sessionConnections ));
	}

	/**
	 * handle lock action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return int       		      0 on success, 1 on error
	 */
	private function handle_lock(&$sock, &$params) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKey = $this->getHashFromKey($params['key']);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_LOCK,
			"target"	=> "running", /*TODO let user decide */
			"session" 	=> $sessionKey
		));

		if ($decoded["type"] === self::REPLY_OK) {
			$session->setFlash($this->flashState .' success', "Successfully locked.");
			$this->updateConnLock($params['key']);
		} else {
			$this->logger->err("Could not lock.", array("error" => var_export($decoded, true)));
			$session->setFlash($this->flashState .' error', "Could not lock datastore. ");
		}
	}

	/**
	 * handle unlock action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return int             		0 on success, 1 on error
	 */
	private function handle_unlock(&$sock, &$params) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKey = $this->getHashFromKey($params['key']);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_UNLOCK,
			"target"	=> "running", /*TODO let user decide */
			"session" 	=> $sessionKey
		));

		if ($decoded["type"] === self::REPLY_OK) {
			$session->setFlash($this->flashState .' success', "Successfully unlocked.");
			$this->updateConnLock($params['key']);
		} else {
			$this->logger->err("Could not unlock.", array("error" => var_export($decoded, true)));
			$session->setFlash($this->flashState .' error', "Could not unlock datastore. ");
		}
	}

	/**
	 * handle info action
	 *
	 * @param  resource &$sock   	socket descriptor
	 * @param  array    &$params   array of values for mod_netconf (type, params...)
	 * @return int       		      0 on success, 1 on error
	 */
	private function handle_info(&$sock, &$params) {
		if (isset($params["session"]) && ($params["session"] !== "")) {
			$sessionKey = $params['session'];
		} else {
			if ($this->checkLoggedKeys() != 0) {
				return 1;
			}
			$session = $this->container->get('request')->getSession();
			$sessionKey = $this->getHashFromKey($params['key']);
		}

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_INFO,
			"session" 	=> $sessionKey
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->logger->err("Could get session info.", array("error" => var_export($decoded, true)));
			$session->setFlash($this->flashState .' error', "Could not get session info.");
		}

		return $decoded;
	}

	/**
	 * handle unlock action
	 *
	 * @param  resource &$sock    socket descriptor
	 * @param  array    &$params   must contain "identifier" of schema, can contain "version" and "format" of schema
	 * @param  mixed    &$result  decoded data from response
	 * @return int             		0 on success, 1 on error
	 */
	private function handle_getschema(&$sock, &$params, &$result) {
		if ($this->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKey = $this->getHashFromKey($params['key']);
		/* TODO check if: "urn:ietf:params:xml:ns:yang:ietf-netconf-monitoring?module=ietf-netconf-monitoring"
		is in capabilities */

		$arguments = array(
			"type" 		=> self::MSG_GETSCHEMA,
			"session" 	=> $sessionKey,
			"identifier"	=> $params["identifier"], /* TODO escape string $params["identifier"]? */
		);

		if (isset($params["format"])) $arguments["format"] = $params["format"];
		if (isset($params["version"])) $arguments["version"] = $params["version"];

		$decoded = $this->execute_operation($sock, $arguments);


		if ($decoded["type"] === self::REPLY_DATA) {
			$result = $decoded["data"];
			return 0;
		} else {
			$this->logger->err("Get-schema failed.", array("error" => var_export($decoded, true)));
			$session->setFlash($this->flashState .' error', "Get-schema failed."
				. (isset($decoded["error-message"])?" Reason: ".$decoded["error-message"]:"")
				. (isset($decoded["bad-element"])?" (".  $decoded["bad-element"]  .")":"")
			);
			return 1;
		}
	}

	/**
	 * checks, if logged keys are valid
	 *
	 * @return int       		0 on success, 1 on error
	 */
	private function checkLoggedKeys() {
		$session = $this->container->get('request')->getSession();
		if ( !count($session->get("session-connections")) ) {
			$session->setFlash($this->flashState .' error', "Not logged in.");
			return 1;
		}
		$req = $this->container->get('request');

		if ( !in_array( $req->get('key'), array_keys($session->get("session-connections")) ) ) {
			$session->setFlash($this->flashState .' error', "You are not allow to see this connection. Bad Index of key.");
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
			$session->setFlash($this->flashState .' error', $status['message']);
			return 1;
		//} elseif ( $decoded == null ) {
		//	$this->logger->err('Could not decode response from socket', array('error' => "Empty response."));
		//	$session->setFlash($this->flashState .' error', "Could not decode response from socket. Error: Empty response.");
		//	return 1;
		} elseif (($decoded['type'] != self::REPLY_OK) && ($decoded['type'] != self::REPLY_DATA)) {
			$this->logger->warn('Error: ', array('error' => $decoded['error-message']));
			$session->setFlash($this->flashState .' error', "Error: " . $decoded['error-message']);
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
		if (isset($this->handleResultsArr[$command])) {

			if ($merge && isset($this->handleResultsArr[$command][$hashedParams]['merged'])) {
				return $this->handleResultsArr[$command][$hashedParams]['merged'];
			} elseif (isset($this->handleResultsArr[$command][$hashedParams]['unmerged'])) {
				return $this->handleResultsArr[$command][$hashedParams]['unmerged'];
			}
		}

		try {
			$sock = fsockopen('unix:///tmp/mod_netconf.sock', NULL, $errno, $errstr);
		} catch (\ErrorException $e) {
			$this->logger->err('Could not connect to socket.', array($errstr));
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Could not connect to socket. Error: $errstr");
			return 1;
		}

		if ($errno != 0) {
			$this->logger->err('Could not connect to socket.', array($errstr));
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Could not connect to socket. Error: $errstr");
			return 1;
		}
		//stream_set_timeout($sock, 5, 500);
		stream_set_blocking($sock, 1);

		switch ($command) {
			case "connect":
				$res = $this->handle_connect($sock, $params, $result);
				break;
			case "get":
				$res = $this->handle_get($sock, $params);
				break;
			case "getconfig":
				$res = $this->handle_getconfig($sock, $params);
				break;
			case "editconfig":
				$this->logger->info("Handle editConfig: ", array('configToSend' => $params['config']));
				$this->validateXml($params['config']);
				$res = $this->handle_editconfig($sock, $params);
				break;
			case "disconnect":
				$res = $this->handle_disconnect($sock, $params);
				break;
			case "lock":
				$res = $this->handle_lock($sock, $params);
				break;
			case "unlock":
				$res = $this->handle_unlock($sock, $params);
				break;
			case "info":
				$res = $this->handle_info($sock, $params);
				break;
			case "getschema":
				$res = $this->handle_getschema($sock, $params, $result);
				break;
			default:
				$this->container->get('request')->getSession()->setFlash($this->flashState .' info', printf("Command not implemented yet. (%s)", $command));
				return 1;
		}

		fclose($sock);
		$this->logger->info("Handle result: ".$command, array('response' => $res));

		if ($command === "info") {
			$this->handleResultsArr['info'] = $res;
			return $res;
		}

		if ( isset($res) && $res !== 1 && $res !== -1) {
			if (!$this->isResponseValid($res)) {
				$this->container->get('request')->getSession()->setFlash($this->flashState . ' error', "Requested XML from server is not valid.");
				return 0;
			}

			// echo $this->removeXmlHeader($a);
			//die();
			if ($merge) {
				// load model
				$notEditedPath = $this->getModelsDir();
				$path = $this->getPathToModels();
				$modelFile = $path . 'wrapped.wyin';

				$this->logger->info("Trying to find model in ", array('pathToFile' => $modelFile));

				if ( file_exists($modelFile) ) {
					$this->logger->info("Model found in ", array('pathToFile' => $modelFile));
					if ( $path != $notEditedPath ) {
						$model = simplexml_load_file($modelFile);
						try {
							$res = $this->mergeWithModel($model, $res);
						} catch (\ErrorException $e) {
							// TODO
							$this->logger->err("Could not merge with model");
						}
					} else {
						// TODO: if is not set module direcotory, we have to set model to merge with
						// problem: we have to load all models (for example combo, comet-tester...)
						$this->logger->warn("Could not find model in ", array('pathToFile' => $modelFile));
					}
				} else {
					$this->logger->warn("Could not find model in ", array('pathToFile' => $modelFile));
				}
				$this->handleResultsArr[$command][$hashedParams]['merged'] = $res;
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
	 * Check, if XML response is valid.
	 *
	 * @param string            &$xmlString       xml response
	 * @return int  1 on success, 0 on error
	 */
	private function isResponseValid(&$xmlString) {
		try {
			$simpleXMLRes = simplexml_load_string($xmlString);
		} catch (\ErrorException $e) {
			// sometimes is exactly one root node missing
			// we will check, if is not XML valid with root node
			$xmlString = "<root>".$xmlString."</root>";
			try {
				$simpleXMLRes = simplexml_load_string($xmlString);
			} catch (\ErrorException $e) {
				return 0;
			}
			return 1;
		}
		return 1;
	}

	/**
	 * Get parent for element.
	 *
	 * @param $element
	 * @return bool|\SimpleXMLElement
	 */
	private function getElementParent($element) {
		$parents = $element->xpath("parent::*");
		if ($parents) {
			return $parents[0];
		}
		return false;
	}

	/**
	 * Check if two elements match.
	 *
	 * @param $model_el
	 * @param $possible_el
	 * @return bool
	 */
	private function checkElemMatch($model_el, $possible_el) {
		$mel = $this->getElementParent($model_el);
		$pel = $this->getElementParent($possible_el);
		while ($pel && $mel) {
			if ($pel->getName() !== $mel->getName()) {
				return false;
			}
			$pel = $this->getElementParent($pel);
			$mel = $this->getElementParent($mel);
		}
		return true;
	}

	/**
	 * Completes tree structure for target element.
	 *
	 * @param $source
	 * @param $target
	 */
	private function completeAttributes(&$source, &$target) {
		if ($source->attributes()) {
			$attrs = $source->attributes();
			if (in_array($attrs["eltype"], array("leaf","list","leaf-list", "container"))) {
				foreach ($source->attributes() as $key => $val) {
					$target->addAttribute($key, $val);
				}
			}
		}
	}

	/**
	 * Find corresponding $el in configuration model $model and complete attributes from $model.
	 *
	 * @param  \SimpleXMLElement &$model with data model
	 * @param  \SimpleXMLElement $el     with element of response
	 */
	private function findAndComplete(&$model, $el) {
		$modelns = $model->getNamespaces();
		$model->registerXPathNamespace("c", $modelns[""]);
		$found = $model->xpath("//c:". $el->getName());
		if (sizeof($found) == 1) {
			$this->completeAttributes($found[0], $el);
		} else {
			//echo "Not found unique<br>";
			foreach ($found as $found_el) {
				if ($this->checkElemMatch($el, $found_el)) {
					$this->completeAttributes($found_el, $el);
					break;
				}
			}
		}
	}

	/**
	 * Go through $root_el tree that represents the response from Netconf server.
	 *
	 * @param  \SimpleXMLElement &$model  with data model
	 * @param  \SimpleXMLElement $root_el with element of response
	 */
	private function mergeRecursive(&$model, $root_el) {
		foreach ($root_el as $ch) {
			$this->findAndComplete($model, $ch);
			$this->mergeRecursive($model, $ch);
		}

		foreach ($root_el->children as $ch) {
			$this->findAndComplete($model, $ch);
			$this->mergeRecursive($model, $ch);
		}
	}

	/**
	 * Add attributes from configuration model to response such as config, mandatory, type.
	 *
	 * @param  \SimpleXMLElement  $model 	data configuration model
	 * @param  string             $result data from netconf server
	 * @return string								      the result of merge
	 */
	private function mergeWithModel($model, $result) {
		if ($result) {
			$resxml = simplexml_load_string($result);

			$this->mergeRecursive($model, $resxml);

			return $resxml->asXML();
		} else {
			return $result;
		}
	}

	/**
	 * Validates input string against validation files saved in models directory.
	 * For now, only two validation step are set up - RelaxNG (*.rng) and Schema (*.xsd)
	 *
	 * @param string $xml   xml string to validate with RelaxNG and Schema, if available
	 * @return int          0 on success, 1 on error
	 */
	private function validateXml($xml) {
		$finder = new Finder();
		$domDoc = new \DOMDocument();
		$xml = "<mynetconfbase:data  xmlns:mynetconfbase='urn:ietf:params:xml:ns:netconf:base:1.0'>".$xml."</mynetconfbase:data>";
		$domDoc->loadXML($xml);

		$iterator = $finder
				->files()
				->name("/.*data\.(rng|xsd)$/")
				->in($this->getPathToModels());

		try {
			foreach ($iterator as $file) {
				$path = $file->getRealPath();
				if (strpos($path, "rng")) {
					$domDoc->relaxNGValidate($path);
				} else if (strpos($path, "xsd")) {
					$domDoc->schemaValidate($path);
				}
			}
		} catch (\ErrorException $e) {
			$this->logger->warn("XML is not valid.", array('error' => $e->getMessage(), 'xml' => $xml, 'RNGfile' => $path));
			return 1;
		}

		return 0;

	}

	/**
	 * Get XML code of model for creating new node.
	 *
	 * @todo  implement this method (load XML from model)
	 *
	 * @param $xPath
	 * @param $key
	 * @param $module
	 * @param $subsection
	 * @return string
	 */
	public function getXMLFromModel($xPath, $key, $module, $subsection) {
		$xml = <<<XML
<?xml version="1.0"?>
<root>
	<exporter eltype="list" config="true" key="id host port" iskey="false">
	  <id eltype="leaf" config="true" type="uint8" description="Exporter identification sent to the collector." iskey="true">0</id>
	  <host eltype="leaf" config="true" type="string" description="Hostname (or IPv4/6 address) of the collector where to send data." iskey="true">collector-test.ipv4.liberouter.org</host>
	  <port eltype="leaf" config="true" type="uint16" description="Port of the collector where to send data." iskey="true">3010</port>
	  <timeout_active eltype="leaf" config="true" type="uint16" default="180" iskey="false">300</timeout_active>
	  <timeout_inactive eltype="leaf" config="true" type="uint8" default="10" iskey="false">30</timeout_inactive>
	  <cpu_mask eltype="leaf" config="true" type="uint8" default="1" description="Mask of allowed CPUs." iskey="false">12</cpu_mask>
	  <flowcache_size eltype="leaf" config="true" type="uint8" default="19" description="Queue (flowcache) size in power of 2." iskey="false">25</flowcache_size>
	  <protocol_export eltype="leaf" config="true" type="enumeration" default="NetFlow v9" description="Flow information export protocol." iskey="false">NetFlow v9</protocol_export>
	  <protocol_ip eltype="leaf" config="true" type="enumeration" default="IPv4" description="Force IP protocol when connecting to the collector." iskey="false">IPv4</protocol_ip>
	  <protocol_transport eltype="leaf" config="true" type="enumeration" default="TCP" description="Transport protocol for the IPFIX protocol." iskey="false">UDP</protocol_transport>
	</exporter>
</root>
XML;

		return $xml;
	}

	/**
	 * Sets current flash state - but only for allowed kinds
	 *
	 * @param   string  $state    kind of flash state
	 * @throws  \ErrorException   if flash state is not in allowedState array
	 * @return  int               0 on error
	 */
	public function setFlashState($state) {
		$allowedState = array("config", "state", "single");

		if ( !in_array($state, $allowedState) ) {
			$this->logger->notice("Wrong flash state.", array($state));
			throw new \ErrorException("Wrong flash state.");
		}
		$this->flashState = $state;
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
		if ( !isset($this->models) || !count($this->models) ) {
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

							if ( !in_array(array("name" => $nodeKey, 'index' => $i), $allowedModels) ) {
								$allowedModels[] = array("name" => $nodeKey, 'index' => $i);
							}

							foreach ($submenu as $subKey => $tmp) {
								if ( !in_array(array("name" => $subKey, 'index' => 0), $allowedSubmenu) ) {
									$allowedSubmenu[] = array("name" => $subKey, 'index' => 0);
								}
								foreach ($tmp as $subSubKey => $tmp2) {
									if ( !in_array(array("name" => $subSubKey, 'index' => 0), $allowedSubmenu) ) {
										$allowedSubmenu[] = array("name" => $subSubKey, 'index' => 0);
									}
								}
							}
						}
					}
				}
			} catch (\ErrorException $e) {
				$this->logger->err("Could not build MenuStructure", array('key' => $key, 'path' => $path, 'error' => $e->getMessage()));
				// nothing
			}
			$this->modelNamespaces = $namespaces;

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
					$folders[] = array(
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
			$this->models = $folders;
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
		$this->submenu[$moduleWithIndex] = $folders;

		return $folders;
	}

	/**
	 * Get models.
	 *
	 * @return array|null
	 */
	public function getModels() {
		return $this->models;
	}

	/**
	 * Get submenu for key.
	 *
	 * @param  int  $key session key of current connection
	 * @return array
	 */
	public function getSubmenu($key) {
		return isset($this->submenu[$key]) ? $this->submenu[$key] : array();
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
