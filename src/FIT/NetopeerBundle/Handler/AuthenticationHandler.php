<?php
/**
 * @author David Alexa <alexa.david@me.com>
 *
 * Copyright (C) 2012-2013 CESNET
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
 *
 */
namespace FIT\NetopeerBundle\Handler;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class AuthenticationHandler implements AuthenticationFailureHandlerInterface, LogoutSuccessHandlerInterface
{
	private $container;

	public function __construct(ContainerInterface $container)	{
		$this->container = $container;
	}

	/**
	 * @inheritDocs
	 */
	public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
	{
		$request->getSession()->setFlash('error', $exception->getMessage());
		$this->disconnectAllConnections($request);
		$url = $this->container->get('router')->generate('login');

		return new RedirectResponse($url);
	}

	/**
	 * @inheritDocs
	 */
	public function onLogoutSuccess(Request $request)
	{
		$this->disconnectAllConnections($request);
		$url = $this->container->get('router')->generate('login');

		return new RedirectResponse($url);
	}

	/**
	 * Loads all connections from session and forwards disconnect action for all of them
	 *
	 * @param Request $request
	 */
	private function disconnectAllConnections(Request $request) {
		$connections = $request->getSession()->get('session-connections');
		if (isset($connections)) {
			foreach ($connections as $key => $conn) {
				$path = array(
						'name'  => 'handleConnection',
						'command' => 'disconnect',
						'key' => $key,
						'_controller' => "FITNetopeerBundle:Default:handleConnection"
				);
				$subRequest = $request->duplicate(array(), null, $path);

				$this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
			}
		}
	}
}