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
namespace FIT\NetopeerBundle\Services\Managers;

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