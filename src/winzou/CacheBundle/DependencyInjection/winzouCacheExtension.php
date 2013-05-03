<?php

/*
 * This file is part of winzouCacheBundle.
 *
 * winzouCacheBundle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * winzouBookBundle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace winzou\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

/**
 * Extension for the bundle winzouCacheExtension
 * @author winzou
 */
class winzouCacheExtension extends Extension
{
    /**
     * @see Symfony\Component\DependencyInjection\Extension.ExtensionInterface::load()
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->process($configuration->getConfigTree(), $configs);
        
        // set an array full of the driver classes
        $container->setParameter('winzou_cache.internal.drivers', $config['driver']);
        
        // Case sensitive
        $config['options']['default_driver'] = strtolower($config['options']['default_driver']);
        
        // we check if the default_driver value is ok
        if (!isset($config['driver'][$config['options']['default_driver']])) {
            throw new \InvalidArgumentException('The driver "'.$config['options']['default_driver'].'" (set in winzou_book.options.default_driver) does not exist.');
        }
        $config['internal']['default_driver_class'] = $config['driver'][$config['options']['default_driver']];
        
        // If the lifetime cache directory is not defined, we set it
        $config['options']['cache_dir_lifetime'] = $config['options']['cache_dir'].DIRECTORY_SEPARATOR.'lifetime';
        
        // Set the parameters
        $this->bindParameter($container, 'winzou_cache', $config);
        
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
    
    /**
     * Set the given parameters to the given container
	 
     * @param ContainerBuilder $container
     * @param string $name
     * @param mixed $value
     */
    private function bindParameter(ContainerBuilder $container, $name, $value)
    {
        if (is_array($value)) {
            foreach ($value as $index => $val) {
                $this->bindParameter($container, $name.'.'.$index, $val);
            }
        } else {
            $container->setParameter($name, $value);
        }
    }
}