<?php
/**
 * File with Entity of user.
 *
 * Holds all information about logged user.
 *
 * @author  David Alexa
 */
namespace FIT\NetopeerBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use FIT\NetopeerBundle\Entity\BaseConnection as BaseConnection;

/**
 * Class with Entity of logged user.
 * 
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User implements UserInterface {

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
	 * @ORM\Column(type="string", length="64", nullable="true", unique="true")
	 * @ORM\JoinColumn(onDelete="cascade")
	 */
	protected $username;

	/**
	 * @var string  salted password
	 * @ORM\Column(type="string", nullable="true")
	 */
	protected $password;

	/**
	 * @var string  string of user roles
	 * @ORM\Column(type="string", nullable="true")
	 */
	protected $roles;

	/**
	 * @var string  salt for salting password
	 * @ORM\Column(type="string", length=32)
	 */
	protected $salt;

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
	 * initialize User object and generates salt for password
	 */
	public function __construct() {
		$this->salt = md5(uniqid(null, true));
		$this->connectedDevicesInHistory = new \Doctrine\Common\Collections\ArrayCollection();
		$this->connectedDevicesInProfiles = new \Doctrine\Common\Collections\ArrayCollection();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRoles() {
		return explode(",", $this->roles);
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
	public function equals(UserInterface $user) {
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
   * Set roles
   *
   * @param string $roles
   */
  public function setRoles($roles)
  {
      $this->roles = $roles;
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
	 * Add connection either to history or profile
	 *
	 * @param BaseConnection $conn
	 * @param int $kind   kind of connection
	 */
	public function addConnection(BaseConnection $conn, $kind)
	{
		if ($kind == BaseConnection::$kindProfile) {
			$this->addConnectionToProfiles($conn);
		} else if ($kind == BaseConnection::$kindHistory) {
			$this->addBaseConnection($conn);
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
	  $arr = $this->connectedDevicesInHistory;
	  foreach ($arr as $key => $conn) {
		  if ($conn->getKind() != BaseConnection::$kindHistory) {
			  unset($arr[$key]);
		  }
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
}