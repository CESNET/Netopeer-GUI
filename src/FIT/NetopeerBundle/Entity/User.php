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
	private $salt;

	public function __construct() {
		$this->salt = md5(uniqid(null, true));
	}

	/**
	 * Set id
	 *
	 * @param integer $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
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
	 * Set password
	 *
	 * @param string $password
	 */
	public function setPassword($password) {
		$this->password = $password;
	}

	/**
	 * Set roles
	 *
	 * @param string $roles
	 */
	public function setRoles($roles) {
		$this->roles = $roles;
	}

	/**
	 * Set salt
	 *
	 * @param string $salt
	 */
	public function setSalt($salt) {
		$this->salt = $salt;
	}

	/**
	 * Set username
	 *
	 * @param string $username
	 */
	public function setUsername($username) {
		$this->username = $username;
	}

}