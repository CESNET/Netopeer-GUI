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
 * Xcache cache driver.
 *
 * @link    www.doctrine-project.org
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  David Abdemoulaie <dave@hobodave.com>
 */
class XcacheCache extends AbstractCache
{
    /**
     * {@inheritdoc}
     */
    public function getIds()
    {
        $this->_checkAuth();
        $keys = array();

        for ($i = 0, $count = xcache_count(XC_TYPE_VAR); $i < $count; $i++) {
            $entries = xcache_list(XC_TYPE_VAR, $i);

            if (is_array($entries['cache_list'])) {
                foreach ($entries['cache_list'] as $entry) {
                    $keys[] = $entry['name'];
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
        return $this->_doContains($id) ? unserialize(xcache_get($id)) : false;
    }

    /**
     * {@inheritdoc}
     */
    protected function _doContains($id)
    {
        return xcache_isset($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doSave($id, $data, $lifeTime = 0)
    {
        return xcache_set($id, serialize($data), (int) $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    protected function _doDelete($id)
    {
        return xcache_unset($id);
    }


    /**
     * Checks that xcache.admin.enable_auth is Off
     *
     * @throws \BadMethodCallException When xcache.admin.enable_auth is On
     * @return void
     */
    protected function _checkAuth()
    {
        if (ini_get('xcache.admin.enable_auth')) {
            throw new \BadMethodCallException('To use all features of \Doctrine\Common\Cache\XcacheCache, you must set "xcache.admin.enable_auth" to "Off" in your php.ini.');
        }
    }
	
	/**
     * {@inheritdoc}
     */
	public static function isSupported()
	{
		return function_exists('xcache_get');
	}
}