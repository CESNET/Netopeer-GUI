<?php

namespace FIT\NetopeerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * FIT\NetopeerBundle\Entity\MyConnection
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class MyConnection
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="hash", type="string", unique=true)
     */
    private $hash;    

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $modelName;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected $modelVersion;

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected $rootElem;

    /**
     * @ORM\Column(type="text", length=255)
     */
    protected $namespace;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $hostname;

    /**
     * @ORM\Column(type="integer")
     */
    protected $port;

    /**
     * @ORM\Column(type="string")
     */
    protected $username;


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
     * Set hash
     *
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * Get hash
     *
     * @return string 
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Set modelName
     *
     * @param string $modelName
     */
    public function setModelName($modelName)
    {
        $this->modelName = $modelName;
    }

    /**
     * Get modelName
     *
     * @return string 
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * Set modelVersion
     *
     * @param string $modelVersion
     */
    public function setModelVersion($modelVersion)
    {
        $this->modelVersion = $modelVersion;
    }

    /**
     * Get modelVersion
     *
     * @return string 
     */
    public function getModelVersion()
    {
        return $this->modelVersion;
    }

    /**
     * Set rootElem
     *
     * @param string $rootElem
     */
    public function setRootElem($rootElem)
    {
        $this->rootElem = $rootElem;
    }

    /**
     * Get rootElem
     *
     * @return string 
     */
    public function getRootElem()
    {
        return $this->rootElem;
    }

    /**
     * Set namespace
     *
     * @param text $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Get namespace
     *
     * @return text 
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set hostname
     *
     * @param string $hostname
     */
    public function setHostname($hostname)
    {
        $this->hostname = $hostname;
    }

    /**
     * Get hostname
     *
     * @return string 
     */
    public function getHostname()
    {
        return $this->hostname;
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
}