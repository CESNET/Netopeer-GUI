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
		$nameID = $samlInfo->getNameID()->getValue();

		$user = $this->loadUserByNameId($nameID);
		$attr = $samlInfo->getAttributes();
		$username = $attr['uid'];
		$username = $username->getValues();
		$user->setUsername($username[0]);

		$em = $this->container->get('doctrine')->getManager();
		$repository = $em->getRepository('FITNetopeerBundle:SamlUser');
		$em->persist($user);
		$em->flush();

		return $user;
	}

	private function loadUserByNameId($nameId) {
		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');

		$user = $repository->findOneBy(
				array('nameID' => $nameId)
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
		$user = new SamlUser();
		$user->setUsername($samlInfo->getAttributes('username'));
		$user->setNameID($samlInfo->getNameID()->getValue());

		$repository = $this->container->get('doctrine')->getManager()->getRepository('FITNetopeerBundle:SamlUser');
		$repository->persist($user);
		$repository->flush();
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

		throwException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException);
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function refreshUser(UserInterface $user)
	{
		return $this->loadUserByNameId($user->getNameID());
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsClass($class)
	{
		return true;
	}

}