<?php
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