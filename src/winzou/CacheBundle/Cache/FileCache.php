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

namespace winzou\CacheBundle\Cache;

/**
 * File cache driver.
 *
 * @author winzou
 */
class FileCache extends AbstractCache
{
    /** @var string $_cacheDir */
    private $_cacheDir;
    
    /** @var string $separator */
    private $_separator = '--s--';

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options = array())
    {
        // Cache directory is required
        if (!isset($options['cache_dir'])) {
            throw new \InvalidArgumentException('The option "cache_dir" must be passed to the FileCache constructor.');
        }
        $this->setCacheDir($options['cache_dir']);
    }
    
    /**
     * Set the cache directory to use.
     *
     * @param string $cacheDir
     */
    public function setCacheDir($cacheDir)
    {
        if (!$cacheDir) {
            throw new \InvalidArgumentException('The parameter $cacheDir must not be empty.');
        }
        
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true)) {
            throw new \RuntimeException('Unable to create the directory "'.$cacheDir.'"');
        }
        
        if (in_array(substr($cacheDir, -1), array('\\', '/'))) {
            $cacheDir = substr($cacheDir, 0, -1);
        }
        
        $this->_cacheDir = $cacheDir;
    }
    
    /**
     * Get the file name from a cache id.
     *
     * @param string $id
     */
    protected function getFileName($id)
    {
        return $this->_cacheDir
            .DIRECTORY_SEPARATOR
            .str_replace(DIRECTORY_SEPARATOR, $this->_separator, $id);
    }
    
    /**
     * Get the cache id from a file name.
     *
     * @param string $file
     */
    protected function getKeyName($file)
    {
        return str_replace($this->_separator, DIRECTORY_SEPARATOR, basename($file));
    }
    
    /**
     * {@inheritdoc}
     */
    public function getIds()
    {
        $keys = glob($this->_cacheDir.DIRECTORY_SEPARATOR.'*');
        $keys = array_map(array($this, 'getKeyName'), $keys);
        
        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    protected function _doFetch($id)
    {
        return unserialize(file_get_contents($this->getFileName($id)));
    }

    /**
     * {@inheritdoc}
     */
    protected function _doContains($id)
    {
        return file_exists($this->getFileName($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function _doSave($id, $data, $lifeTime = 0)
    {
        return (bool) file_put_contents($this->getFileName($id), serialize($data));
    }

    /**
     * {@inheritdoc}
     */
    protected function _doDelete($id)
    {   
        $file = $this->getFileName($id);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public static function isSupported()
    {
        return function_exists('file_put_contents');
    }
}
