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
 * File cache driver with lifetime support.
 *
 * @author Thibaut Cuvelier
 */
class LifetimeFileCache extends FileCache
{
    /**
     * {@inheritdoc}
     */
    protected function _doFetch($id)
    {
        $data = parent::_doFetch($id);
        return $data['data'];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function _doContains($id)
    {
        if (!parent::_doContains($id)) {
            return false;
        }
        
        if (!$this->isValidLife($id)) {
            $this->_doDelete($id);
            return false;
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function _doSave($id, $data, $lifeTime = 0)
    {
        return parent::_doSave($id, array('data' => $data, 'lt' => $lifeTime), $lifeTime);
    }
    
    /**
     * Check is the lifetime is stil valid
     * @param string $id
     * @return bool
     */
    private function isValidLife($id)
    {
        $filename = $this->getFileName($id);
        $content  = parent::_doFetch($id);
        
        return ((time() - filemtime($filename)) < $content['lt']);
    }
    
    /**
     * Delete cache entries where the id matches a PHP regular expressions
     * @return array $deleted  Array of the deleted cache ids
     */
    public function deleteDeads()
    {
        $deleted = array();

        $ids = $this->getIds();

        foreach ($ids as $id) {
            if ($this->isValidLife($id)) {
                $this->_doDelete($id);
                $deleted[] = $id;
            }
        }

        return $deleted;
    }
}