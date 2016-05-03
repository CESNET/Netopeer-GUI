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
namespace FIT\NetopeerBundle\Services\Functionality;

use FIT\Bundle\ModuleDefaultBundle\Controller\ModuleController;
use FIT\NetopeerBundle\Services\Functionality\ConnectionFunctionality;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManager;

use FIT\NetopeerBundle\Entity\ConnectionSession;

/**
 * Data service, handles all communication between webGUI and mod_netconf.
 */
class NetconfFunctionality {

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

	const SCH_QUERY  = 100;
	const SCH_MERGE  = 101;

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
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var ConnectionFunctioality
	 */
	protected $connectionFunctionality;

	/**
	 * @return Logger
	 */
	public function getLogger() {
		return $this->logger;
	}

	/**
	 * @param Logger $logger
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
	 * @return ConnectionFunctioality
	 */
	public function getConnectionFunctionality() {
		return $this->connectionFunctionality;
	}

	/**
	 * @param ConnectionFunctioality $connectionFunctionality
	 */
	public function setConnectionFunctionality( $connectionFunctionality ) {
		$this->connectionFunctionality = $connectionFunctionality;
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
					$this->getLogger()->addWarning("Wrong message format, it is not according to RFC6242 (starting with \\n#).", array("message" => var_export($message, true)));
					throw new \ErrorException("Wrong message format, it is not according to RFC6242 (starting with \\n#).");
				}
				if ($i >= $numchunks) {
					$this->getLogger()->addWarning("Malformed message (RFC6242) - Bad amount of parts.", array("message" => var_export($message, true)));
					throw new \ErrorException("Malformed message (RFC6242) - Bad amount of parts.");  }
				/* echo "chunk length<br>\n"; */
				$len = 0;
				sscanf($chunks[$i], "%i", $len);

				/* echo "chunk data<br>\n"; */
				$nl = strpos($chunks[$i], "\n");
				if ($nl === false) {
					$this->getLogger()->addWarning("Malformed message (RFC6242) - There is no \\n after chunk-data size.", array("message" => var_export($message, true)));
					throw new \ErrorException("Malformed message (RFC6242) - There is no \\n after chunk-data size.");
				}
				$data = substr($chunks[$i], $nl + 1);
				$realsize = strlen($data);
				if ($realsize != $len) {
					$this->getLogger()->addWarning("Chunk $i has the length $realsize instead of $len.", array("message" => var_export($message, true)));
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
			$this->getSession()->getFlashBag()->add('error', "Reading failure.");
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
				$this->getSession()->getFlashBag()->add('error', "Reading failure.");
				break;
			}
			$response .= $tmp;
			//echo strlen($response) ."/". $size ."\n";
		}
		$status = stream_get_meta_data($sock);
		if (!$response && $status["timed_out"] == true) {
			$this->getSession()->getFlashBag()->add('error', "Reached timeout for reading response.");
			//echo "Reached timeout for reading response.";
		}
		/* "unchunk" frames (RFC6242) */
		try {
			$response = $this->unwrapRFC6242($response);
		} catch (\ErrorException $e) {
			$this->getSession()->getFlashBag()->add('error', "Could not read NetConf. Error: ".$e->getMessage());
			//echo "unwrap exception";
			return 1;
		}
		//echo "readnetconf time consumed: ". (microtime(true) - $start);

		return trim($response);
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
	 * @param array $optionalParams
	 *
	 * @return mixed
	 */
	private function addOptionalParams(array $targetArr, array $sourceArr, array $optionalParams) {
		foreach ($optionalParams as $param) {
			if (isset($sourceArr[$param])) {
				/**
				 * if we want to set filter, filter must have some value
				 * empty filter returns empty response, not all data
				 */
				if ($param !== "filter" || ($param === "filter" && trim($sourceArr[$param]) !== "")) {
					$targetArr[$param] = trim($sourceArr[$param]);
				}
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
		$session = $this->getSession();

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
			$this->getLogger()->addError("Could not connect.", array("error" => (isset($newConnection["errors"])?" Error: ".var_export($newConnection["errors"], true) : var_export($this->getJsonError(), true))));
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
		if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->getSession();
		$sessionConnections = $session->get('session-connections');
		$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_DISCONNECT,
			"sessions" 	=> $sessionKeys
		));

		if (sizeof($decoded)) {
			foreach ( $decoded as $sid => $response ) {
				if ( $response["type"] === self::REPLY_OK ) {
					$session->getFlashBag()->add( 'success', "Session " . $sid . " successfully disconnected." );
				} else {
					$this->getLogger()->addError( "Could not disconnect.",
						array( "error" => var_export( $response, true ) ) );
					$session->getFlashBag()->add( 'error', "Could not disconnect session " . $sid . " from server. " );
				}

				$key = array_search( $sid, $sessionKeys );
				if ( $key ) {
					unset( $sessionConnections[ $key ] );
				}
			}
		} else {
			foreach ($params['connIds'] as $key) {
				unset( $sessionConnections[ $key ] );
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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$getParams = array(
			"type" 		=> self::MSG_GET,
			"sessions" 	=> $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
			"source" 	=> isset($params['source']) ? $params['source'] : 'running',
			"strict"   => isset($params['strict']) ? $params['strict'] : false,
		);
		$getParams = $this->addOptionalParams($getParams, $params, array('filter'));

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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$getconfigParams = array(
			"type" 		=> self::MSG_GETCONFIG,
			"sessions" 	=> $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
			"source" 	=> $params['source'],
			"strict"   => isset($params['strict']) ? $params['strict'] : false,
		);
		$this->addOptionalParams($getconfigParams, $params, array('filter'));

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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		/* edit-config to store new values */
		$editparams = array(
			"type" => self::MSG_EDITCONFIG,
			"sessions" => $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
			"target" => $params['target'],
			"configs" => $params['configs'],
			"default-operation" => 'merge',
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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$copyParams = array(
			"type" => self::MSG_COPYCONFIG,
			"sessions" => $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$deleteParams = array(
			"type" => self::MSG_DELETECONFIG,
			"sessions" => $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
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

		if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->getSession();
		$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds'], true);

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_LOCK,
			"target"	=> $params['target'],
			"sessions" 	=> array_values($sessionKeys)
		));

		$lockedConnIds = array();
		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Session ".$sid." successfully locked.");
				$lockedConnIds[] = array_search($sid, $sessionKeys);
			} else {
				$this->getLogger()->addError("Could not lock.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not lock datastore for session " .$sid. ". ");
			}
		}

		$this->getConnectionFunctionality()->updateConnLock($lockedConnIds, $params['target']);
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
		if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->getSession();
		$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds'], true);

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
				$this->getLogger()->addError("Could not unlock.", array("error" => var_export($response, true)));
				$session->getFlashBag()->add('error', "Could not unlock session ".$sid.". ");
			}
		}

		$this->getConnectionFunctionality()->updateConnLock($lockedConnIds, $params['target']);
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
		if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->getSession();
		$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds'], true);

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
				$this->getLogger()->addError("Could not kill session.", array("error" => var_export($response, true)));
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
			if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);
		}

		$session = $this->getSession();
		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_INFO,
			"sessions" 	=> $sessionKeys
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->getLogger()->addError("Could get session info.", array("error" => var_export($decoded, true)));
			$session->getFlashBag()->add('error', "Could not get session info.");
		}

		return $decoded;
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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$genericParams = array(
			"type" => self::MSG_GENERIC,
			"sessions" => $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
			"contents" => $params['contents'],
		);

		$decoded = $this->execute_operation($sock, $genericParams);

		foreach ($decoded as $sid => $response) {
			if ($response["type"] === self::REPLY_OK) {
				$session->getFlashBag()->add('success', "Successful call of method.");
			} else {
				$this->getLogger()->addError("User RPC call.", array("error" => var_export($response, true)));
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
		if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}
		$session = $this->getSession();
		$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds'], true);

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
		$session = $this->getSession();

		if (isset($params["sessions"]) && ($params["sessions"] !== "")) {
			$sessionKeys = $params['sessions'];
		} else {
			if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);
		}

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_RELOADHELLO,
			"sessions" 	=> $sessionKeys
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->getLogger()->addError("Could get reload hello.", array("error" => var_export($decoded, true)));
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
			$session = $this->getSession();
			$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);
		}

		$decoded = $this->execute_operation($sock,	array(
			"type" 		=> self::MSG_NTF_GETHISTORY,
			"sessions" 	=> $sessionKeys,
			"from" => $params['from'],
			"to" => $params['to']
		));

		if (!$decoded) {
			/* error occurred, unexpected response */
			$this->getLogger()->addError("Could get notifications history.", array("error" => var_export($decoded, true)));
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
		if ( $this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
			return 1;
		}

		$validateParams = array(
			"type" 		=> self::MSG_VALIDATE,
			"sessions" 	=> $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']),
			"target" 	=> $params['target'],
		);
		$validateParams = $this->addOptionalParams($validateParams, $params, array('url'));

		$decoded = $this->execute_operation($sock, $validateParams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * Query schema node by XPATH
	 * key: type (int), value: 100
	 * key: sessions (array of ints), value: array of SIDs
	 * key: filters (array of strings with same index order as sessions), value: array of XPath (with "prefix" = module name) values of target node in schema (start with '/') or module names (do not start with '/')
	 * Optional:
	 * key: load_children(boolean, default = false), value: if set to true, children schema information will be loaded too. Otherwise only part "$@name": {'children': [...]} will be loaded.
	 *
	 * @param $sock
	 * @param $params
	 *
	 * @return int|mixed
	 */
	public function handle_query(&$sock, &$params) {
		$session = $this->getSession();

		if (isset($params["sessions"]) && ($params["sessions"] !== "")) {
			$sessionKeys = $params['sessions'];
		} else {
			if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);
		}

		$queryParams = array(
			"type" 		=> self::SCH_QUERY,
			"sessions" 	=> $sessionKeys,
			"filters" 	=> $params['filters'],
//			"load_children" => true
		);
		$queryParams = $this->addOptionalParams($queryParams, $params, array('load_children'));

		$decoded = $this->execute_operation($sock, $queryParams);
		return $this->checkDecodedData($decoded);
	}

	/**
	 * Query schema node by XPATH
	 * key: type (int), value: 100
	 * key: sessions (array of ints), value: array of SIDs
	 * key: configurations (array of sJSON with same index order as sessions array), value: array of clean sJSON configurations without schema information
	 *
	 * @param $sock
	 * @param $params
	 *
	 * @return int|mixed
	 */
	public function handle_merge(&$sock, &$params) {
		$session = $this->getSession();

		if (isset($params["sessions"]) && ($params["sessions"] !== "")) {
			$sessionKeys = $params['sessions'];
		} else {
			if ($this->getConnectionFunctionality()->checkLoggedKeys() != 0) {
				return 1;
			}
			$sessionKeys = $this->getConnectionFunctionality()->getHashFromKeys($params['connIds']);
		}

		$mergeParams = array(
			"type" 		=> self::SCH_QUERY,
			"sessions" 	=> $sessionKeys,
			"configurations" 	=> $params['configurations'],
		);

		$decoded = $this->execute_operation($sock, $mergeParams);
		return $this->checkDecodedData($decoded);
	}



	/**
	 * checks decoded data, if there an error occurs
	 *
	 * @param  mixed  &$decoded 	decoded data
	 * @return mixed				    decoded data on success, 1 on error
	 */
	private function checkDecodedData(&$decoded) {
		$session = $this->getSession();
		$status = $this->getJsonError();

		if ( $status['errorCode'] ) {
			$this->getLogger()->addWarning('Checking decoded data:', array('error' => $status['message']));
			$session->getFlashBag()->add('error', $status['message']);
			return 1;
		}

		if (!sizeof($decoded)) {
			$session->getFlashBag()->add('error', 'Empty response');
			return 1;
		}

		foreach ($decoded as $sid => $response) {
			if (($response['type'] != self::REPLY_OK) && ($response['type'] != self::REPLY_DATA)) {
				$this->getLogger()->addWarning('Error: ', array('errors' => $response['errors']));
				if (sizeof($response['errors'])) {
					foreach ($response['errors'] as $error) {
						$session->getFlashBag()->add('error', $error);
					}
				}
				// throw new \ErrorException($response['error-message']);
				return 1;
			}
			return isset($response["data"]) ? $response["data"] : -1;
		}
	}

	/**
	 * executes operation - sends message into the socket
	 *
	 * @param  resource   &$sock  			socket descriptor
	 * @param  array      $params 	  array of values for mod_netconf (type, params...)
	 * @return array         	        response from mod_netconf
	 */
	private function execute_operation(&$sock, $params)	{
		$this->logger->addInfo('Params for netconf: ' . var_export($params, true));
		$operation = json_encode($params);
		$this->write2socket($sock, $operation);
		$response = $this->readnetconf($sock);
		return json_decode($response, true);
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
		$this->getLogger()->addInfo('Handle: '.$command.' with params', array('params' => $logParams));

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
			$this->getLogger()->addError('Backend is not running or socket file does not exist.', array($socket_path));
			$this->getSession()->getFlashBag()->add('error', "Backend is not running or socket file does not exist: ".$socket_path);
                        return 1;
                }
                if (!is_readable($socket_path)) {
			$this->getLogger()->addError('Socket is not readable.', array($socket_path));
			$this->getSession()->getFlashBag()->add('error', "Socket is not readable: ".$socket_path);
                        return 1;
                }
                if (!is_writable($socket_path)) {
			$this->getLogger()->addError('Socket is not writable.', array($socket_path));
			$this->getSession()->getFlashBag()->add('error', "Socket is not writable: ".$socket_path);
                        return 1;
                }
                $socket_path = 'unix://'.$socket_path;
		try {
			$sock = fsockopen($socket_path, NULL, $errno, $errstr);
		} catch (\ErrorException $e) {
			$this->getLogger()->addError('Could not connect to socket.', array($errstr));
			$this->getSession()->getFlashBag()->add('error', "Could not connect to socket. Error: $errstr");
			return 1;
		}

		if ($errno != 0) {
			$this->getLogger()->addError('Could not connect to socket.', array($errstr));
			$this->getSession()->getFlashBag()->add('error', "Could not connect to socket. Error: $errstr");
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
				$this->getLogger()->addInfo("Handle editConfig: ", array('configToSend' => var_export($params['configs'], true)));
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
				$res = $this->handle_validate($sock, $params);
				break;
			case "query":
				$res = $this->handle_query($sock, $params);
				break;
			case "merge":
				$res = $this->handle_merge($sock, $params);
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
				$this->getSession()->getFlashBag()->add('info', printf("Command not implemented yet. (%s)", $command));
				return 1;
		}

		fclose($sock);
		$this->getLogger()->addInfo("Handle result: ".$command, array('response' => $res));

		if ( isset($res) && $res !== 1 && $res !== -1) {
			return $res;
		} else if ($res !== -1) {
			return 1;
		}
		return 0;
	}
}
