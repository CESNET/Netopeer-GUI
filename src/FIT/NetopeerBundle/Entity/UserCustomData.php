<?php
/**
 * File with settings of user.
 *
 * Holds all available settings for user;
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

use \FIT\NetopeerBundle\Entity\BaseConnection as BaseConnection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class with custom user info
 *
 * @ORM\Entity
 * @ORM\Table(name="userData")
 */
class UserCustomData {
	/**
	 * @var int unique identifier
	 *
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @var string  string of user roles
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected $roles;

	/**
	 * @var string  serialized array of UserSettings class
	 * @ORM\Column(type="array")
	 */
	protected $settings;

	/**
	 * @var array   array of connected devices (from history)
	 * @ORM\OneToMany(targetEntity="BaseConnection", mappedBy="customUserData")
	 * @ORM\OrderBy({"accessTime" = "DESC"})
	 */
	protected $connectedDevicesInHistory;

	/**
	 * @var array   array of connected devices (from profiles)
	 * @ORM\OneToMany(targetEntity="BaseConnection", mappedBy="customUserData")
	 * @ORM\OrderBy({"host" = "ASC"})
	 */
	protected $connectedDevicesInProfiles;

	public function __construct()
	{
		$this->connectedDevicesInHistory  = new \Doctrine\Common\Collections\ArrayCollection();
		$this->connectedDevicesInProfiles = new \Doctrine\Common\Collections\ArrayCollection();
		$this->settings                   = new UserSettings();
	}

	/**
	 * Set user settings
	 *
	 * @param UserSettings $settings
	 */
	public function setSettings(UserSettings $settings)
	{
		$this->settings = $settings;
	}

	/**
	 * Get user settings
	 *
	 * @return UserSettings
	 */
	public function getSettings()
	{
		return $this->settings;
	}

	/**
	 * Add connection either to history or profile
	 *
	 * @param BaseConnection $conn
	 * @param int            $kind kind of connection
	 */
	public function addConnection(BaseConnection $conn, $kind)
	{
		if ($kind == BaseConnection::$kindProfile) {
			$this->addConnectionToProfiles($conn);
		} else {
			if ($kind == BaseConnection::$kindHistory) {
				$this->addBaseConnection($conn);
			}
		}
	}

	/**
	 * Add connectedDevicesInHistory
	 *
	 * @param BaseConnection $connectedDevicesInHistory
	 */
	public function addBaseConnection(BaseConnection $connectedDevicesInHistory)
	{
		$connectedDevicesInHistory->setKind(BaseConnection::$kindHistory);
		$this->connectedDevicesInHistory[] = $connectedDevicesInHistory;
	}

	/**
	 * Add connectedDevicesInProfile
	 *
	 * @param BaseConnection $connectedDevicesInProfile
	 */
	public function addConnectionToProfiles(BaseConnection $connectedDevicesInProfile)
	{
		$connectedDevicesInProfile->setKind(BaseConnection::$kindProfile);
		$this->connectedDevicesInProfiles[] = $connectedDevicesInProfile;
	}

	/**
	 * Get connectedDevicesInHistory
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getConnectedDevicesInHistory()
	{
		$tmpArr = $this->connectedDevicesInHistory;
		$arr    = array();

		$max   = $this->getSettings()->getHistoryDuration();
		$limit = new \DateTime();
		$limit->modify('- ' . $max . ' day');
		foreach ($tmpArr as $key => $conn) {
			if ($conn->getKind() != BaseConnection::$kindHistory) {
				continue;
			}
			if ($conn->getAccessTime() < $limit) break;
			$arr[] = $conn;
		}

		return $arr;
	}

	/**
	 * Get connectedDevicesInProfiles
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getConnectedDevicesInProfiles()
	{
		$arr = $this->connectedDevicesInProfiles;
		foreach ($arr as $key => $conn) {
			if ($conn->getKind() != BaseConnection::$kindProfile) {
				unset($arr[$key]);
			}
		}

		return $arr;
	}

	/**
	 * Add connectedDevicesInHistory
	 *
	 * @param BaseConnection $connectedDevicesInHistory
	 *
	 * @return User
	 */
	public function addConnectedDevicesInHistory(BaseConnection $connectedDevicesInHistory)
	{
		$this->connectedDevicesInHistory[] = $connectedDevicesInHistory;

		return $this;
	}

	/**
	 * Remove connectedDevicesInHistory
	 *
	 * @param BaseConnection $connectedDevicesInHistory
	 */
	public function removeConnectedDevicesInHistory(BaseConnection $connectedDevicesInHistory)
	{
		$this->connectedDevicesInHistory->removeElement($connectedDevicesInHistory);
	}

	/**
	 * Add connectedDevicesInProfiles
	 *
	 * @param BaseConnection $connectedDevicesInProfiles
	 *
	 * @return User
	 */
	public function addConnectedDevicesInProfile(BaseConnection $connectedDevicesInProfiles)
	{
		$this->connectedDevicesInProfiles[] = $connectedDevicesInProfiles;

		return $this;
	}

	/**
	 * Remove connectedDevicesInProfiles
	 *
	 * @param BaseConnection $connectedDevicesInProfiles
	 */
	public function removeConnectedDevicesInProfile(BaseConnection $connectedDevicesInProfiles)
	{
		$this->connectedDevicesInProfiles->removeElement($connectedDevicesInProfiles);
	}

	/**
	 * Set roles
	 *
	 * @param string $roles
	 */
	public function setRoles($roles)
	{
		$this->roles = $roles;
	}

	/**
	 * Get roles
	 *
	 * @return string
	 */
	public function getRoles()
	{
		return explode(",", $this->roles);
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
     * Set userId
     *
     * @param \FIT\NetopeerBundle\Entity\User $userId
     * @return UserCustomData
     */
    public function setUserId(\FIT\NetopeerBundle\Entity\User $userId = null)
    {
        $this->userId = $userId;
    
        return $this;
    }

    /**
     * Get userId
     *
     * @return \FIT\NetopeerBundle\Entity\User 
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set samlUserId
     *
     * @param \FIT\NetopeerBundle\Entity\SamlUser $samlUserId
     * @return UserCustomData
     */
    public function setSamlUserId(\FIT\NetopeerBundle\Entity\SamlUser $samlUserId = null)
    {
        $this->samlUserId = $samlUserId;
    
        return $this;
    }

    /**
     * Get samlUserId
     *
     * @return \FIT\NetopeerBundle\Entity\SamlUser 
     */
    public function getSamlUserId()
    {
        return $this->samlUserId;
    }
}