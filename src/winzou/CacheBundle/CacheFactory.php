<?php

/*
 * This file is part of winzouCacheBundle.
 *
 * winzouCacheBundle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * winzouCacheBundle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace winzou\CacheBundle;

use winzou\CacheBundle\Cache\AbstractCache;
use winzou\CacheBundle\Cache\CacheInterface;

class CacheFactory
{
    /** @var array $drivers */
    private $drivers;
    
    /** @var array $options */
    private $options;
    
    /** @var int $exceptionCode */
    const EXCEPTION_CODE = 999;
    
    /**
     * Constructor.
     *
     * @param array $drivers The list of available drivers, key=driver name, value=driver class
     * @param array $options Options to pass to the driver
     */
    public function __construct(array $drivers, array $options = array())
    {
        $this->drivers = $drivers;
        $this->options = $options;
    }
    
    /**
     * Builder.
     *
     * @param string $driver The cache driver to use
     * @param array $options Options to pass to the driver
     * @param bool $byPassCheck If you want to perform a configuration check, set false (but you should know in advance which driver is supporteed by your configuration)
     * @return AbstractCache
     */
    public function getCache($driver, array $options = array(), $byPassCheck = true)
    {
        if (!$this->driverExists($driver)) {
            throw new \Exception('The cache driver "'.$driver.'" is not supported by the bundle.', self::EXCEPTION_CODE);
        }
        
        $class = $this->drivers[$driver];
        
        if (!$byPassCheck && !$class::isSupported()) {
            throw new \Exception('The cache driver "'.$driver.'" is not supported by your running configuration.', self::EXCEPTION_CODE);
        }
        
        $options = array_merge($this->options, $options);
        
        $cache = new $class($options);
        
        if (!$cache instanceof CacheInterface) {
            throw new \Exception('The cache driver "'.$driver.'" must implement CacheInterface.');
        }
        
        return $cache;
    }
    
    /**
     * Try to initialise any of the requested driver, check if it exists and it's supported. Fallback to File
     *
     * @param array|string $drivers The ordered list of drivers to try, can be a string if only one
     * @param array $options Options to pass to the driver
     * @return Cache\AbstractCache
     */
    public function getCacheFallback($drivers, array $options = array())
    {
        // allow single driver
        if (!is_array($drivers)) {
            $drivers = array($drivers);
        }
        
        // fallback to file, this one should work
        // we are sure that array will work, but as it's not a persistent driver, we prefer throw an Exception
        $drivers[] = 'file';
        
        foreach ($drivers as $driver) {
            try {
                $cache = $this->getCache($driver, $options, false);
            } catch (\Exception $e) {
                // if it's not an exception thrown by the getCache method, we rethrow it
                if ($e->getCode() != self::EXCEPTION_CODE) {
                    throw $e;
                }
                // otherwise do nothing, try next driver
            }
        }
        
        if (!isset($cache) || !$cache instanceof AbstractCache) {
            throw new \Exception('Unable to initialise any of the required drivers ("'.implode('", "', $drivers).'").');
        }
        
        return $cache;
    }
    
    /**
     * Check if the given driver is supported by the bundle
     *
     * @param string $driver
     * @return bool
     */
    public function driverExists($driver)
    {
        return isset($this->drivers[$driver]);
    }
}