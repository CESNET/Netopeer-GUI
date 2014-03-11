<?php
/**
 * File with Entity of user.
 *
 * Holds all information about logged user.
 *
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
 */
namespace FIT\NetopeerBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Doctrine\ORM\Mapping as ORM;
use FIT\NetopeerBundle\Entity\BaseConnection as BaseConnection;
use FIT\NetopeerBundle\Entity\UserSettings as UserSettings;

/**
 * Class with Entity of logged user.
 * 
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User implements UserInterface, EquatableInterface {

	/**
	 * @var int unique identifier
	 *
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @var string unique username
	 *
	 * @ORM\Column(type="string", length=64, nullable=true, unique=true)
	 * @ORM\JoinColumn(onDelete="cascade")
	 */
	protected $username;

	/**
	 * @var string  salted password
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected $password;

	/**
	 * @var string  salt for salting password
	 * @ORM\Column(type="string", length=32)
	 */
	protected $salt;

	/**
	 * @var UserCustomData
	 * @ORM\OneToOne(targetEntity="UserCustomData")
	 * @ORM\JoinColumn(name="user_data", referencedColumnName="id")
	 */
	protected $customData;



	/**
	 * initialize User object and generates salt for password
	 */
	public function __construct() {
		$this->salt = md5(uniqid(null, true));
		if (!$this->customData instanceof UserCustomData) {
			$this->customData  = new UserCustomData();
		}
		$this->customData  = new \Doctrine\Common\Collections\ArrayCollection();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSalt() {
		return $this->salt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * {@inheritdoc}
	 */
	public function eraseCredentials() {
	}

	/**
	 * {@inheritDoc}
	 */
	public function isEqualTo(UserInterface $user) {
		if (!$user instanceof User) {
			return false;
		}

		if ($this->password !== $user->getPassword()) {
			return false;
		}

		if ($this->getSalt() !== $user->getSalt()) {
			return false;
		}

		if ($this->username !== $user->getUsername()) {
			return false;
		}

		return true;
	}


  /**
   * Set id
   *
   * @param integer $id
   */
  public function setId($id)
  {
      $this->id = $id;
  }

  /**
   * Get id
   *
   * @return integer
   */
  public function getId()
  {
      return $this->id;
  }

  /**
   * Set username
   *
   * @param string $username
   */
  public function setUsername($username)
  {
      $this->username = $username;
  }

  /**
   * Set password
   *
   * @param string $password
   */
  public function setPassword($password)
  {
      $this->password = $password;
  }

  /**
   * Set salt
   *
   * @param string $salt
   */
  public function setSalt($salt)
  {
      $this->salt = $salt;
  }

	/**
	 * don't know, why this method must exist, but some
	 * error occurred without
	 *
	 * @return array
	 */
	public function __sleep()
	{
		return array('id');
	}

	/**
	 * Set user settings
	 *
	 * @param UserSettings $settings
	 */
	public function setSettings(UserSettings $settings)
	{
		$this->customData->setSettings($settings);
	}

	/**
	 * Get user settings
	 *
	 * @return UserSettings
	 */
	public function getSettings()
	{
		return $this->customData->getSettings();
	}

	/**
	 * Add connection either to history or profile
	 *
	 * @param BaseConnection $conn
	 * @param int            $kind kind of connection
	 */
	public function addConnection(BaseConnection $conn, $kind)
	{
		$this->customData->addConnection($conn, $kind);
	}

	/**
	 * Get connectedDevicesInHistory
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getConnectedDevicesInHistory()
	{
		return $this->customData->getConnectedDevicesInHistory();
	}

	/**
	 * Get connectedDevicesInProfiles
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getConnectedDevicesInProfiles()
	{
		return $this->customData->getConnectedDevicesInProfiles();
	}

	/**
	 * Add connectedDevicesInHistory
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory
	 *
	 * @return User
	 */
	public function addConnectedDevicesInHistory(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory)
	{
		$this->customData->addConnectedDevicesInHistory($connectedDevicesInHistory);

		return $this;
	}

	/**
	 * Remove connectedDevicesInHistory
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory
	 */
	public function removeConnectedDevicesInHistory(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory)
	{
		$this->customData->removeConnectedDevicesInHistory($connectedDevicesInHistory);
	}

	/**
	 * Add connectedDevicesInProfiles
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles
	 *
	 * @return User
	 */
	public function addConnectedDevicesInProfile(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles)
	{
		$this->addConnectedDevicesInProfile($connectedDevicesInProfiles);

		return $this;
	}

	/**
	 * Remove connectedDevicesInProfiles
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles
	 */
	public function removeConnectedDevicesInProfile(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles)
	{
		$this->customData->removeConnectedDevicesInProfile($connectedDevicesInProfiles);
	}

	/**
	 * Set roles
	 *
	 * @param string $roles
	 *
	 * @return SamlUser
	 */
	public function setRoles($roles)
	{
		$this->customData->setRoles($roles);

		return $this;
	}

	/**
	 * Get roles
	 *
	 * @return string
	 */
	public function getRoles()
	{
		return $this->customData->getRoles();
	}

    /**
     * Set customData
     *
     * @param \FIT\NetopeerBundle\Entity\UserCustomData $customData
     * @return User
     */
    public function setCustomData(\FIT\NetopeerBundle\Entity\UserCustomData $customData = null)
    {
        $this->customData = $customData;
    
        return $this;
    }

    /**
     * Get customData
     *
     * @return \FIT\NetopeerBundle\Entity\UserCustomData 
     */
    public function getCustomData()
    {
        return $this->customData;
    }
}