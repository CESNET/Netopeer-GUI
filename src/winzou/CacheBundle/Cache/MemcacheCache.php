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

use \Memcache;

/**
 * Memcache cache driver.
 
 * @link    www.doctrine-project.org
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  David Abdemoulaie <dave@hobodave.com>
 * @author  winzou
 */
class MemcacheCache extends AbstractCache
{
    /**
     * @var Memcache
     */
    private $_memcache;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options = array())
    {
		// Memcache instance is not required, we can create one
        if (!isset($options['memcache']) || !$options['memcache'] instanceof Memcache) {
            $options['memcache'] = new Memcache;
        }

        $this->setMemcache($options['memcache']);
    }

    /**
     * Sets the memcache instance to use.
     *
     * @param Memcache $memcache
     */
    public function setMemcache(Memcache $memcache)
    {
        $this->_memcache = $memcache;
    }

    /**
     * Gets the memcache instance used by the cache.
     *
     * @return Memcache
     */
    public function getMemcache()
    {
        return $this->_memcache;
    }

    /**
     * {@inheritdoc}
     */
    public function getIds()
    {
        $keys = array();
        $allSlabs = $this->_memcache->getExtendedStats('slabs');

        foreach ($allSlabs as $server => $slabs) {
            if (is_array($slabs)) {
                foreach (array_keys($slabs) as $slabId) {
                    $dump = $this->_memcache->getExtendedStats('cachedump', (int) $slabId);

                    if ($dump) {
                        foreach ($dump as $entries) {
                            if ($entries) {
                                $keys = array_merge($keys, array_keys($entries));
                            }
                        }
                    }
                }
            }
        }
        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    protected function _doFetch($id)
    {
        return $this->_memcache->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doContains($id)
    {
        return (bool) $this->_memcache->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doSave($id, $data, $lifeTime = 0)
    {
        return $this->_memcache->set($id, $data, 0, (int) $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doDelete($id)
    {
        return $this->_memcache->delete($id);
    }
	
	/**
     * {@inheritdoc}
     */
	public static function isSupported()
	{
		return class_exists('Memcache');
	}
}