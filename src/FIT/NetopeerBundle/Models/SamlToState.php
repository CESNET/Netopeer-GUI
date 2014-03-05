<?php

namespace FIT\NetopeerBundle\Models;

use FIT\NetopeerBundle\Entity\SamlUser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use AerialShip\SamlSPBundle\Bridge\SamlSpInfo;
use AerialShip\SamlSPBundle\Security\Core\User\UserManagerInterface as UserManagerInterface;


class SamlToState implements UserManagerInterface
{
	/**
	 * @var ContainerInterface   base bundle container
	 */
	public $container;
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	public $logger;

	/**
	 * Constructor with DependencyInjection params.
	 *
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	 * @param \Symfony\Bridge\Monolog\Logger $logger   logging class
	 */
	public function __construct(ContainerInterface $container, $logger)	{
		$this->container = $container;
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function loadUserBySamlInfo(SamlSpInfo $samlInfo)
	{
		$user = $this->loadUserByTargetedID($samlInfo->getAttributes()['eduPersonTargetedID']->getFirstValue());

		return $user;
	}

	private function loadUserByTargetedID($targetedID) {
		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');

		$user = $repository->findOneBy(
				array('targetedID' => $targetedID)
		);

		if ($user) {
			return $user;
		}

		throw new \Symfony\Component\Security\Core\Exception\UsernameNotFoundException();
	}

	/**
	 * {@inheritdoc}
	 */
	public function createUserFromSamlInfo(SamlSpInfo $samlInfo)
	{
		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');

		$user = $repository->findOneBy(
				array('nameID' => $samlInfo->getNameID()->getValue())
		);

		if ($user) {
			$user->setUsername($samlInfo->getAttributes()['eduPersonPrincipalName']->getFirstValue());
			$user->setTargetedID($samlInfo->getAttributes()['eduPersonTargetedID']->getFirstValue());
		} else {
			$user = new SamlUser();
			$user->setUsername($samlInfo->getAttributes()['eduPersonPrincipalName']->getFirstValue());
			$user->setTargetedID($samlInfo->getAttributes()['eduPersonTargetedID']->getFirstValue());

			$user->setSessionIndex($samlInfo->getAuthnStatement()->getSessionIndex());

			$user->setProviderID($samlInfo->getNameID()->getSPProvidedID());
			$user->setAuthenticationServiceName($samlInfo->getAuthenticationServiceID());
			$user->setNameID($samlInfo->getNameID()->getValue());
			$user->setNameIDFormat($samlInfo->getNameID()->getFormat());
		}

		$em = $this->container->get('doctrine')->getManager();
		$em->persist($user);
		$em->flush();

		return $user;
	}

	public function loadUserByUsername($username)
	{
		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');

		$user = $repository->findOneBy(
				array('username' => $username)
		);

		if ($user) {
			return $user;
		}

		throw new \Symfony\Component\Security\Core\Exception\UsernameNotFoundException();
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function refreshUser(UserInterface $user)
	{
		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');

		$newUser = $repository->findOneBy(
				array('nameID' => $user->getNameID())
		);

		if (!$newUser) {
			throw new \Symfony\Component\Security\Core\Exception\UsernameNotFoundException();
		}

		return $newUser;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsClass($class)
	{
		return true;
	}

}