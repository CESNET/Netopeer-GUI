<?php
 
namespace FIT\NetopeerBundle\Models;
 
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

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

	/**
	 * @var ContainerInterface
	 */
	protected $container;
	protected $logger;

	private $flashState;
	private $models;
	private $submenu;

	public function __construct(ContainerInterface $container, $logger)
	{
		$this->container = $container;
		$this->logger = $logger;
		$this->setFlashState('single');
	}
	/**
	  Parse $message formatted by Chunked Framing Mechanism
	  (RFC6242)
	  \param[in] $message - input message text
	  \return string - unwrapped message
	 */
	private function unwrap_rfc6242($message)
	{
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
	  \brief Read response from socket
	  \param[in,out] $sock socket descriptor
	  \return trimmed string that was read
	 */
	private function readnetconf(&$sock) {
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
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrap_rfc6242($response);
		} catch (\ErrorException $e) {
			$this->container->get('request')->getSession()->setFlash($this->flashState .' error', "Could not read NetConf. Error: ".$e->getMessage());
			return 1;
		}

		return trim($response);
	}

	private function getJsonError() {
		$session = $this->container->get('request')->getSession();
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
	  \param[in,out] $sock socket descriptor
	  \return 0 on success
	 */
	private function handle_connect(&$sock, &$params)	{
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

		if ($decoded["type"] == self::REPLY_OK) {
			$sessionkey = $decoded["session"];
			$newtime = new \DateTime();
			$newtime = $newtime->format("d.m.Y H:i:s");
			if ( !$sessionKeys = $session->get("session-keys") ) {
				$session->set("session-keys", array($sessionkey));
			} else {				
				$session->set("session-keys", array_merge($sessionKeys, array($sessionkey)));
			}
			if ( !$sessionHosts = $session->get('hosts') ) {
				$session->set("hosts", array( $params['host'] ));
			} else {
				$session->set("hosts", array_merge($sessionHosts, array( $params['host'] )));
			}
			if ( !$sessionTime = $session->get('times') ) {
				$session->set("times", array( $newtime ));
			} else {
				$session->set("times", array_merge($sessionTime, array( $newtime )));
			}
			$session->setFlash($this->flashState .' success', "Successfully connected.");
			return 0;
		} else {
			$this->logger->err("Could not connect.", array("error" => var_export($this->getJsonError(), true)));
			$session->setFlash($this->flashState .' error', "Could not connect.".(isset($decoded["error-message"])?" Error: ".$decoded["error-message"]:""));
			return 1;
		}
	}

	/**
	 \param[in,out] $sock socket descriptor
	 \return decoded data on success
	*/
	public function handle_get(&$sock, &$params){
		if ( $this->check_logged_keys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeysArr = $session->get('session-keys');
		$sessionKey = $sessionKeysArr[ $params['key'] ];

		$decoded = $this->execute_operation($sock, array(
			"type" 		=> self::MSG_GET,
			"session" 	=> $sessionKey,
			"source" 	=> "running",
			"filter" 	=> $params['filter']
		));
		
		return $this->checkDecodedData($decoded);
	}

	/**
	 \param[in,out] $sock socket descriptor
	 \return decoded data on success
	*/
	public function handle_getconfig(&$sock, &$params)	{
		if ( $this->check_logged_keys() != 0) {
			return 1;
		}
		$sessionKeysArr = $this->container->get('request')->getSession()->get('session-keys');
		$sessionKey = $sessionKeysArr[ $params['key'] ];

		$decoded = $this->execute_operation($sock, array(
			"type" 		=> self::MSG_GETCONFIG,
			"session" 	=> $sessionKey,
			"source" 	=> $params['source'],
			"filter" 	=> $params['filter']
		));
		return $this->checkDecodedData($decoded);
	}

	/**
	 \param[in,out] $sock socket descriptor
	 \return 0 on success
	*/
	function handle_editconfig(&$sock, &$params)
	{
		if ( $this->check_logged_keys() != 0) {
			return 1;
		}
		$sessionKeysArr = $this->container->get('request')->getSession()->get('session-keys');
		$sessionKey = $sessionKeysArr[ $params['key'] ];
		
		/* copy-config to store new values */
		$decoded = $this->execute_operation($sock, array(
			"type" => self::MSG_EDITCONFIG,
			"session" => $sessionKey,
			"target" => $params['target'],
			"config" => $params['config']
		));

		return $this->checkDecodedData($decoded);
	}

	/**
	 \param[in,out] $sock socket descriptor
	 \return 0 on success
	*/
	private function handle_disconnect(&$sock, &$params) {
		if ($this->check_logged_keys() != 0) {
			return 1;
		}
		$session = $this->container->get('request')->getSession();
		$sessionKeysArr = $session->get('session-keys');
		$sessionHostsArr = $session->get('hosts');
		$sessionTimesArr = $session->get('times');
		$requestKey = $params['key'];
		$sessionKey = $sessionKeysArr[ $requestKey ];

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

		unset( $sessionKeysArr[ $requestKey] );
		unset( $sessionHostsArr[ $requestKey ] );
		unset( $sessionTimesArr[ $requestKey ] );
		$session->set("session-keys", array_values( $sessionKeysArr ));
		$session->set("hosts", array_values( $sessionHostsArr ));
		$session->set("times", array_values( $sessionTimesArr ));
	}

	/**
	 \return 0 on success
	 */
	private function check_logged_keys() {
		$session = $this->container->get('request')->getSession();
		if ( !count($session->get("session-keys")) ) {
			$session->setFlash($this->flashState .' error', "Not logged in.");
			return 1;
		}
		if ( !strlen($this->container->get('request')->get('key')) ) {
			$session->setFlash($this->flashState .' error', "You are not allow to see this connection. No index of key.");
			return 1;
		}
		if ( !in_array( $this->container->get('request')->get('key'), array_keys($session->get("session-keys")) ) ) {
			$session->setFlash($this->flashState .' error', "You are not allow to see this connection. Bad Index of key.");
			return 1;
		}
		return 0;
	}

	/**
	 * \return decoded data on success
	 */
	private function checkDecodedData(&$decoded) {
		$session = $this->container->get('request')->getSession();
		$status = $this->getJsonError();

		if ( $status['errorCode'] ) {
			$this->logger->warn('Checking decoded data:', array('error' => $status['message']));
			$session->setFlash($this->flashState .' error', $status['message']);
			return 1;
		} elseif ( $decoded == null ) {
			$this->logger->err('Could not decode response from socket', array('error' => "Empty response."));
			$session->setFlash($this->flashState .' error', "Could not decode response from socket. Error: Empty response.");
			return 1;
		} elseif (($decoded['type'] != self::REPLY_OK) && ($decoded['type'] != self::REPLY_DATA)) {
			$this->logger->warn('Could not decode response from socket', array('error' => $decoded['error-message']));
			$session->setFlash($this->flashState .' error', "Could not decode response from socket. Error: " . $decoded['error-message']);
			return 1;
		} 
		return isset($decoded["data"]) ? $decoded["data"] : 0;
	}

	/**
	  Wrap message for Chunked Framing Mechanism (RFC6242)
	  and write it into the socket.
	  \param[in,out] $sock socket descriptor
	  \param[in] $message message text
	 */
	private function write2socket(&$sock, $message)
	{
		$final_message = sprintf("\n#%d\n%s\n##\n", strlen($message), $message);
		fwrite($sock, $final_message);
	}

	/**
	  \param[in,out] $sock socket descriptor
	  \param[in] $params array of values for mod_netconf (type, params...)
	  \return array - response from mod_netconf
	 */
	private function execute_operation(&$sock, $params)
	{
		$operation = json_encode($params);
		$this->write2socket($sock, $operation);
		$response = $this->readnetconf($sock);
		return json_decode($response, true);
	}

	public function handle($command, $params = array(), $merge = true) {
		$errno = 0;
		$errstr = "";

		$logParams = $params;
		if ( $command == "connect" ) {
			// nebudeme do logu vkladat heslo
			unset($logParams['pass']);
		}
		$this->logger->info('Handle: '.$command.' with params', $logParams);

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
		stream_set_timeout($sock, 1, 100);

		switch ($command) {			
			case "connect":
				$res = $this->handle_connect($sock, $params);
				break;
			case "get":
				$res = $this->handle_get($sock, $params);
				break;
			case "getconfig":
				$res = $this->handle_getconfig($sock, $params);
				break;
			case "editconfig":
				$res = $this->handle_editconfig($sock, $params);
				break;
			case "disconnect":
				$res = $this->handle_disconnect($sock, $params);
				break;
			default:
				$this->container->get('request')->getSession()->setFlash($this->flashState .' info', printf("Command not implemented yet. (%s)", $command));
		}

		fclose($sock);

		$this->logger->info("Handle result: ".$command, array('response' => $res));

		if ( isset($res) && $res !== 1 ) {
			if ($merge) {
				$path = $notEditedPath = __DIR__ . '/../Data/models/';

				// add module directory if is set in route
				if ( $this->container->get('request')->get('module') != null ) {
					$path .= $this->container->get('request')->get('module').'/';
				}
				// add subsection directory if is set in route and wrapped file in subsection directory exists
				if ( $this->container->get('request')->get('subsection') != null 
					&& file_exists($path . $this->container->get('request')->get('subsection').'/wrapped.wyin')) {
					$path .= $this->container->get('request')->get('subsection').'/';
				}

				// load model
				$modelFile = $path . 'wrapped.wyin';

				if ( file_exists($modelFile) ) {
					if ( $path != $notEditedPath ) {
						$model = simplexml_load_file($modelFile);
						$res = $this->mergeWithModel($model, $res);	
					} else {
						// TODO: if is not set module direcotory, we have to set model to merge with
						// problem: we have to load all models (for example combo, comet-tester...)
					}	
				} else {
					$this->logger->warn("Could not find model on ", array('pathToFile' => $modelFile));
				}
			}
			return $res;
		} else {
			return 1;
		}
		return 0;
	}

	/**
	Find corresponding $el in configuration model $model
	and complete attributes from $model.
	\param[in] $model - SimpleXMLElement with data model
	\param[in] $el - SimpleXMLElement with element of response
	*/
	private function find_and_complete(&$model, $el)
	{
		$modelns = $model->getNamespaces();
		$model->registerXPathNamespace("c", $modelns[""]);
		$found = $model->xpath("//c:". $el->getName());
		if (sizeof($found) == 1) {
			$found_el = $found[0];
			if ($found_el->attributes()) {
				$attrs = $found_el->attributes();
				if (in_array($attrs["eltype"], array("leaf","list","leaf-list", "container"))) {
					foreach ($found[0]->attributes() as $key => $val) {
						$el->addAttribute($key, $val);
					}
				}
			}
		} else {
			//echo "Not found unique<br>";
			// TODO handle deep search if there are more results
		}
	}

	/**
	Go through $root_el tree that represents
	the response from Netconf server.
	\param[in] $model - SimpleXMLElement with data model
	\param[in] $root_el - SimpleXMLElement with element of response
	*/
	private function mergeRecursive(&$model, $root_el)
	{
		//echo "Rootel";
		foreach ($root_el as $ch) {
			$this->find_and_complete($model, $ch);
			$this->mergeRecursive($model, $ch);
		}
		//echo "children";
		foreach ($root_el->children as $ch) {
			$this->find_and_complete($model, $ch);
			$this->mergeRecursive($model, $ch);
		}
	}

	/**
	Add attributes from configuration model to response
	such as config, mandatory, type.
	\param[in] $model - data configuration model SimpleXMLElement
	\param[in,out] $result - data from netconf server, the result of merge
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

	public function setFlashState($state) {
		$allowedState = array("config", "state", "single");

		if ( !in_array($state, $allowedState) ) {
			$this->logger->notice("Wrong flash state.", array($state));
			throw new \ErrorException("Wrong flash state.");
		}
		$this->flashState = $state;
		return 0;
	}

	// prepares top menu
	public function buildMenuStructure($key, $path = "") {
		$finder = new Finder();

		$params = array(
			'key' => $key,
			'source' => 'running',
			'filter' => "",
		);

		$allowedModels = array();
		$allowedSubmenu = array();

		try {
			// nacteme si get z pripojeneho serveru
			if ( ($xml = $this->handle('get', $params, false)) != 1 ) {
				$xml = simplexml_load_string($xml, 'SimpleXMLIterator');
				$xmlNameSpaces = $xml->getNamespaces();

				if ( isset($xmlNameSpaces[""]) ) {
					$xml->registerXPathNamespace("xmlns", $xmlNameSpaces[""]);
					$nodes = $xml->xpath('/xmlns:*');
				} else {
					$node = $xml->xpath('/*');
				}

				// ziskame nejvyssi uzly (bez rootu) pro vytvoreni horniho menu
				foreach ($nodes as $node) {
					foreach ($node as $nodeKey => $submenu) {
						if ( !in_array($nodeKey, $allowedModels) ) {
							$allowedModels[] = $nodeKey;	
						}	    				

						foreach ($submenu as $subKey => $tmp) {
							if ( !in_array($key, $allowedSubmenu) ) {
								$allowedSubmenu[] = $subKey;
							}
						}	    				
					}
				}	    		
			}    
		} catch (\ErrorException $e) {
			$this->logger->err("Could not build MenuStructure", array('key' => $key, 'path' => $path, 'error' => $e->getMessage()));
			// throw new \ErrorException($e->getMessage());
			// nothing
		}

		// nyni zkontrolujeme, zda uzly z get souhlasi se strukturou v modelech. Pokud ne, nevypiseme je
		$dir = __DIR__."/../Data/models/";
		if ( !file_exists($dir) ) {
			$this->models = array();
		} else {
			$iterator = $finder
			  ->directories()
			  ->depth(0)
			  ->sortByName()
			  ->in($dir);

			$folders = array();
			foreach ($iterator as $folder) {
				$model = $folder->getFileName();

				if ( in_array($model, $allowedModels) ) {
					$folders[] = array(
						'path' => "module", 
						"params" => array(
							'key' => $key,
							'module' => $model,
						), 
						"title" => "detail of ".$this->getSectionName($model), 
						"name" => $this->getSectionName($model),
						"children" => $this->buildSubmenu($key, $model, $allowedSubmenu),
					);
				}
			}
			$this->models = $folders;
		}
	}

	// prepares left submenu
	private function buildSubmenu($key, $module, $allowedSubmenu, $path = "") { 
		$finder = new Finder();

		if ( $path != "" ) {
			$completePath = $path . "/" . $module;
		} else {
			$completePath = $module;
		}

		// zjistime, zda uzly z get jsou obsazeny i v modelech. Pokud ne, nezobrazime je v levem menu
		$dir = __DIR__."/../Data/models/".$completePath;
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

				if ( in_array($subsection, $allowedSubmenu) ) {
					$folders[] = array(
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
		$this->submenu[$module] = $folders;

		return $folders;
	}

	public function getModels() {
		return $this->models;
	}

	public function getSubmenu($key) {
		return isset($this->submenu[$key]) ? $this->submenu[$key] : array();
	}

	public function getSectionName($section) {
		return ucfirst( str_replace(array('-', '_'), ' ', $section) );
	}

	public function getSubsectionName($subsection) {
		return $this->getSectionName($subsection);
	}

	private function addLog($str) {
		$this->logger->addInfo($str);
	}

}
