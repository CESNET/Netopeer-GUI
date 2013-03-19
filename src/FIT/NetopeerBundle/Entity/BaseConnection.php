<?php
/**
 * Base entity of connected device.
 *
 * Holds all information about connected device,
 * which are saved in database for history
 * connected devices.
 *
 * @author  David Alexa
 */
namespace FIT\NetopeerBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class with Entity of connected device.
 *
 * @ORM\Entity
 * @ORM\Table(name="connection")
 */
class BaseConnection {
	/**
	 * @var int constant for kind history
	 */
	public static $kindHistory = 1;

	/**
	 * @var int constant for kind profile
	 */
	public static $kindProfile = 2;

	/**
	 * Unique numeric identifier
	 *
	 * @var integer $id
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @var string target hostname
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	protected $host;

	/**
	 * @var int target port
	 *
	 * @ORM\Column(type="integer")
	 */
	protected $port;

	/**
	 * @var string logged username
	 *
	 * @ORM\Column(type="string", length=100)
	 */
	protected $username;

	/**
	 * @var int   user id
	 *
	 * @ORM\ManyToOne(targetEntity="User")
	 * @ORM\JoinColumn(name="userId", referencedColumnName="id", onDelete="cascade")
	 */
	protected $userId;

	/**
	 * @var \DateTime  creation time
	 *
	 * @ORM\Column(type="datetime")
	 */
	protected $createdTime;

	/**
	 * @var \DateTime  time of the last access
	 *
	 * @ORM\Column(type="datetime")
	 */
	protected $accessTime;

	/**
	 * @var int   kind of the connection (history or profile)
	 *
	 * @ORM\Column(type="integer", options={"default" = 1})
	 */
	protected $kind;

	/**
	 * @var bool   is this connection enabled
	 *
	 * @ORM\Column(type="boolean", options={"default" = 1})
	 */
	protected $enabled;

	/**
	 * @var ContainerInterface   base bundle container
	 */
	protected $em;

	/**
	 * @var \Symfony\Component\Security\Core\SecurityContextInterface $securityContext
	 */
	protected $securityContext;
	/**
	 * @var \Symfony\Bridge\Monolog\Logger       instance of logging class
	 */
	protected $logger;


	/**
	 * Constructor with DependencyInjection params.
	 *
	 * @param \Doctrine\ORM\EntityManager $em
	 * @param \Symfony\Component\Security\Core\SecurityContextInterface $securityContext
	 * @param \Symfony\Bridge\Monolog\Logger $logger   logging class
	 */
	public function __construct(EntityManager $em, SecurityContextInterface $securityContext, $logger) {
		$this->em = $em;
		$this->securityContext = $securityContext;
		$this->logger = $logger;

		$dateTime = new \DateTime();
		$this->setCreatedTime($dateTime);
		$this->setAccessTime($dateTime);
		$this->setEnabled(true);
	}

	/**
	 * Saves  connection info into DB - for history
	 *
	 * @param  string   $host      hostname
	 * @param  int      $port      target port
	 * @param  string   $username
	 *
	 * @return int 0 on success, 1 on fail
	 */
	public function saveConnectionIntoDB($host, $port, $username) {
		$repository = $this->em->getRepository('FITNetopeerBundle:BaseConnection');
		$user = $this->securityContext->getToken()->getUser();
		$connection = $repository->findOneBy(
			array('host' => $host, 'port' => $port, 'username' => $username, 'userId' => $user->getId())
		);

		try {
			$em = $this->em;

			// we will create new record for this connection
			if (!$connection) {
				$this->setHost($host);
				$this->setPort($port);
				$this->setUsername($username);

				$user->addBaseConnection($this);
				$this->setUserId($user);

				$em->persist($this);
				$em->persist($user);

				$this->logger->info("History of connection added into DB.", array(
					"host" => $host,
					"port" => $port,
					"username" => $username
				));

			// else we will just modify access time
			} else {
				$connection->setAccessTime();
				$em->persist($connection);

				$this->logger->info("Modify history of connection in DB.", array(
					"id"  => $connection->getId(),
					"host" => $host,
					"port" => $port,
					"username" => $username
				));
			}

			$em->flush();

		} catch (\ErrorException $e) {
			$this->logger->err("Could not add connection into DB.", array(
				"host" => $host,
				"port" => $port,
				"username" => $username,
				"error" => $e->getMessage()
			));
			return 1;
		}

		return 0;
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
   * Set host
   *
   * @param string $host
   */
  public function setHost($host)
  {
      $this->host = $host;
  }

  /**
   * Get host
   *
   * @return string
   */
  public function getHost()
  {
      return $this->host;
  }

  /**
   * Set port
   *
   * @param integer $port
   */
  public function setPort($port)
  {
      $this->port = $port;
  }

  /**
   * Get port
   *
   * @return integer
   */
  public function getPort()
  {
      return $this->port;
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
   * Get username
   *
   * @return string
   */
  public function getUsername()
  {
      return $this->username;
  }

  /**
   * Set createdTime
   *
   * @param \datetime $createdTime
   */
  public function setCreatedTime($createdTime)
  {
      $this->createdTime = $createdTime;
  }

  /**
   * Get createdTime
   *
   * @return \datetime
   */
  public function getCreatedTime()
  {
      return $this->createdTime;
  }

  /**
   * Set accessTime
   *
   * @param \DateTime|int $accessTime
   */
  public function setAccessTime($accessTime = 0)
  {
	    if ($accessTime === 0) {
		    $accessTime = new \DateTime();
	    }
      $this->accessTime = $accessTime;
  }

  /**
   * Get accessTime
   *
   * @return \datetime
   */
  public function getAccessTime()
  {
      return $this->accessTime;
  }

  /**
   * Set enabled
   *
   * @param boolean $enabled
   */
  public function setEnabled($enabled)
  {
      $this->enabled = $enabled;
  }

  /**
   * Get enabled
   *
   * @return boolean
   */
  public function getEnabled()
  {
      return $this->enabled;
  }

  /**
   * Set userId
   *
   * @param \FIT\NetopeerBundle\Entity\User $userId
   */
  public function setUserId(\FIT\NetopeerBundle\Entity\User $userId)
  {
      $this->userId = $userId;
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
	 * Get baseConnection for logged user by connection id
	 *
	 * @param $connectedDeviceId
	 * @return BaseConnection
	 */
	public function getConnectionForCurrentUserById($connectedDeviceId) {
		/**
		 * @var \FIT\NetopeerBundle\Entity\User $user
		 */
		$user = $this->securityContext->getToken()->getUser();
		$em = $this->em;

		if (!$user instanceof \FIT\NetopeerBundle\Entity\User) {
			return false;
		}
		try {
			/**
			 * @var BaseConnection $device
			 */
			$device = $em->getRepository('FITNetopeerBundle:BaseConnection')->findOneBy(array(
			"id" => $connectedDeviceId,
			"userId" => $user->getId(),
			));
		} catch (\ErrorException $e) {
			// we don't care
		}

		return $device;
	}



  /**
   * Set kind
   *
   * @param int $kind
   */
  public function setKind(\int $kind)
  {
      $this->kind = $kind;
  }

  /**
   * Get kind
   *
   * @return int
   */
  public function getKind()
  {
      return $this->kind;
  }


	public function removeDeviceWithId($deviceId) {
		/**
		 * @var \FIT\NetopeerBundle\Entity\User $user
		 */
		$user = $this->securityContext->getToken()->getUser();
		$em = $this->em;

		if (!$user instanceof \FIT\NetopeerBundle\Entity\User) {
			return false;
		}
		try {
			/**
			 * @var BaseConnection $device
			 */
			$device = $em->getRepository('FITNetopeerBundle:BaseConnection')->findOneBy(array(
				"id" => $deviceId
			));
			$em->remove($device);
			$em->flush();
			return true;
		} catch (\ErrorException $e) {
			return false;
		}
	}
}