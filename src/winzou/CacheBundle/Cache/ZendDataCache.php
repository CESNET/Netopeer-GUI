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
 * Zend Data Cache cache driver.
 *
 * @link    www.doctrine-project.org
 * @author  Ralph Schindler <ralph.schindler@zend.com>
 */
class ZendDataCache extends AbstractCache
{
    public function __construct()
    {
        $this->setNamespace('_default::'); // zend data cache format for namespaces ends in ::
    }
	
	/**
     * {@inheritdoc}
     */
	public function setNamespace($namespace)
    {
        $this->_namespace = (string) $namespace.'::';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getIds()
    {
        throw new \BadMethodCallException("getIds() is not supported by ZendDataCache");
    }

    /**
     * {@inheritdoc}
     */
    protected function _doFetch($id)
    {
        return zend_shm_cache_fetch($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doContains($id)
    {
        return (zend_shm_cache_fetch($id) !== FALSE);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doSave($id, $data, $lifeTime = 0)
    {
        return zend_shm_cache_store($id, $data, $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doDelete($id)
    {
        return zend_shm_cache_delete($id);
    }
	
	/**
     * {@inheritdoc}
     */
	public static function isSupported()
	{
		return function_exists('zend_shm_cache_fetch');
	}
}