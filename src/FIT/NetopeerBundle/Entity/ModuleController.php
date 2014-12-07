<?php

namespace FIT\NetopeerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ModuleController
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class ModuleController
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="moduleName", type="string", length=50)
     */
    private $moduleName;

    /**
     * @var string
     *
     * @ORM\Column(name="moduleNamespace", type="string", length=255)
     */
    private $moduleNamespace;

    /**
     * @var array
     *
     * @ORM\Column(name="controllerActions", type="simple_array")
     */
    private $controllerActions;


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
     * Set moduleName
     *
     * @param string $moduleName
     * @return ModuleController
     */
    public function setModuleName($moduleName)
    {
        $this->moduleName = $moduleName;
    
        return $this;
    }

    /**
     * Get moduleName
     *
     * @return string 
     */
    public function getModuleName()
    {
        return $this->moduleName;
    }

    /**
     * Set moduleNamespace
     *
     * @param string $moduleNamespace
     * @return ModuleController
     */
    public function setModuleNamespace($moduleNamespace)
    {
        $this->moduleNamespace = $moduleNamespace;
    
        return $this;
    }

    /**
     * Get moduleNamespace
     *
     * @return string 
     */
    public function getModuleNamespace()
    {
        return $this->moduleNamespace;
    }

    /**
     * Set controllerActions
     *
     * @param array $controllerActions
     * @return ModuleController
     */
    public function setControllerActions($controllerActions)
    {
        $this->controllerActions = $controllerActions;
    
        return $this;
    }

    /**
     * Get controllerActions
     *
     * @return array 
     */
    public function getControllerActions()
    {
        return $this->controllerActions;
    }
}