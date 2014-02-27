<?php

namespace FIT\NetopeerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="saml_sso_state")
 */
class SamlUser extends \AerialShip\SamlSPBundle\Entity\SSOStateEntity implements UserInterface
{

	/**
	 * initialize User object and generates salt for password
	 */
	public function __construct()
	{
		$this->connectedDevicesInHistory  = new \Doctrine\Common\Collections\ArrayCollection();
		$this->connectedDevicesInProfiles = new \Doctrine\Common\Collections\ArrayCollection();
		$this->settings                   = new UserSettings();
		$this->setRoles('ROLE_ADMIN');
	}

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @var string username
	 *
	 * @ORM\Column(type="string", length=64, nullable=true)
	 * @ORM\JoinColumn(onDelete="cascade")
	 */
	protected $username;

	/**
	 * @var string targetedId
	 *
	 * @ORM\Column(type="string", length=64, nullable=true, name="targeted_id")
	 * @ORM\JoinColumn(onDelete="cascade")
	 */
	protected $targetedID;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=32, name="provider_id", nullable=true)
	 */
	protected $providerID;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=32, name="auth_svc_name")
	 */
	protected $authenticationServiceName;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=64, name="session_index", nullable=true)
	 */
	protected $sessionIndex;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=64, name="name_id")
	 */
	protected $nameID;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=64, name="name_id_format")
	 */
	protected $nameIDFormat;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime", name="created_on")
	 */
	protected $createdOn;

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
	 * @ORM\OneToMany(targetEntity="BaseConnection", mappedBy="userId")
	 * @ORM\OrderBy({"accessTime" = "DESC"})
	 */
	protected $connectedDevicesInHistory;

	/**
	 * @var array   array of connected devices (from profiles)
	 * @ORM\OneToMany(targetEntity="BaseConnection", mappedBy="userId")
	 * @ORM\OrderBy({"host" = "ASC"})
	 */
	protected $connectedDevicesInProfiles;

	/**
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * Set providerID
	 *
	 * @param string $providerID
	 *
	 * @return SamlUser
	 */
	public function setProviderID($providerID)
	{
		$this->providerID = $providerID;

		return $this;
	}

	/**
	 * Get providerID
	 *
	 * @return string
	 */
	public function getProviderID()
	{
		return $this->providerID;
	}

	/**
	 * Set authenticationServiceName
	 *
	 * @param string $authenticationServiceName
	 *
	 * @return SamlUser
	 */
	public function setAuthenticationServiceName($authenticationServiceName)
	{
		$this->authenticationServiceName = $authenticationServiceName;

		return $this;
	}

	/**
	 * Get authenticationServiceName
	 *
	 * @return string
	 */
	public function getAuthenticationServiceName()
	{
		return $this->authenticationServiceName;
	}

	/**
	 * Set sessionIndex
	 *
	 * @param string $sessionIndex
	 *
	 * @return SamlUser
	 */
	public function setSessionIndex($sessionIndex)
	{
		$this->sessionIndex = $sessionIndex;

		return $this;
	}

	/**
	 * Get sessionIndex
	 *
	 * @return string
	 */
	public function getSessionIndex()
	{
		return $this->sessionIndex;
	}

	/**
	 * Set nameID
	 *
	 * @param string $nameID
	 *
	 * @return SamlUser
	 */
	public function setNameID($nameID)
	{
		$this->nameID = $nameID;

		return $this;
	}

	/**
	 * Get nameID
	 *
	 * @return string
	 */
	public function getNameID()
	{
		return $this->nameID;
	}

	/**
	 * Set nameIDFormat
	 *
	 * @param string $nameIDFormat
	 *
	 * @return SamlUser
	 */
	public function setNameIDFormat($nameIDFormat)
	{
		$this->nameIDFormat = $nameIDFormat;

		return $this;
	}

	/**
	 * Get nameIDFormat
	 *
	 * @return string
	 */
	public function getNameIDFormat()
	{
		return $this->nameIDFormat;
	}

	/**
	 * Set createdOn
	 *
	 * @param \DateTime $createdOn
	 *
	 * @return SamlUser
	 */
	public function setCreatedOn($createdOn)
	{
		$this->createdOn = $createdOn;

		return $this;
	}

	/**
	 * Get createdOn
	 *
	 * @return \DateTime
	 */
	public function getCreatedOn()
	{
		return $this->createdOn;
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
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory
	 *
	 * @return User
	 */
	public function addConnectedDevicesInHistory(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory)
	{
		$this->connectedDevicesInHistory[] = $connectedDevicesInHistory;

		return $this;
	}

	/**
	 * Remove connectedDevicesInHistory
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory
	 */
	public function removeConnectedDevicesInHistory(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInHistory)
	{
		$this->connectedDevicesInHistory->removeElement($connectedDevicesInHistory);
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
		$this->connectedDevicesInProfiles[] = $connectedDevicesInProfiles;

		return $this;
	}

	/**
	 * Remove connectedDevicesInProfiles
	 *
	 * @param \FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles
	 */
	public function removeConnectedDevicesInProfile(\FIT\NetopeerBundle\Entity\BaseConnection $connectedDevicesInProfiles)
	{
		$this->connectedDevicesInProfiles->removeElement($connectedDevicesInProfiles);
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
		$this->roles = $roles;

		return $this;
	}

	/**
	 * Get roles
	 *
	 * @return string
	 */
	public function getRoles()
	{
		return array($this->roles);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPassword() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSalt() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function eraseCredentials() {
	}

	/**
	 * Set username
	 *
	 * @param string $username
	 *
	 * @return SamlUser
	 */
	public function setUsername($username)
	{
		$this->username = $username;

		return $this;
	}

	/**
	 * Get username
	 *
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}

    /**
     * Set targetedID
     *
     * @param string $targetedID
     * @return SamlUser
     */
    public function setTargetedID($targetedID)
    {
        $this->targetedID = $targetedID;
    
        return $this;
    }

    /**
     * Get targetedID
     *
     * @return string 
     */
    public function getTargetedID()
    {
        return $this->targetedID;
    }
}