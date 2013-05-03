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

namespace winzou\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration builder for final dev app/config/config.yml
 * @author winzou
 */
class Configuration
{
    public function getConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('winzou_cache');
        
        $rootNode
            ->children()
                ->arrayNode('factory')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')->defaultValue('winzou\\CacheBundle\\CacheFactory')->end()
                    ->end()
                ->end()
                ->arrayNode('driver')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('abstract')    ->defaultValue('winzou\\CacheBundle\\Cache\\AbstractCache')    ->end()
                        ->scalarNode('apc')         ->defaultValue('winzou\\CacheBundle\\Cache\\ApcCache')         ->end()
                        ->scalarNode('array')       ->defaultValue('winzou\\CacheBundle\\Cache\\ArrayCache')       ->end()
                        ->scalarNode('file')        ->defaultValue('winzou\\CacheBundle\\Cache\\FileCache')        ->end()
                        ->scalarNode('lifetimefile')->defaultValue('winzou\\CacheBundle\\Cache\\LifetimeFileCache')->end()
                        ->scalarNode('memcache')    ->defaultValue('winzou\\CacheBundle\\Cache\\MemcacheCache')    ->end()
                        ->scalarNode('xcache')      ->defaultValue('winzou\\CacheBundle\\Cache\\XcacheCache')      ->end()
                        ->scalarNode('zenddata')    ->defaultValue('winzou\\CacheBundle\\Cache\\ZendDataCache')    ->end()
                    ->end()
                ->end()
                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache_dir_lifetime')->end()
                        ->scalarNode('cache_dir')         ->defaultValue('%kernel.cache_dir%/winzou_cache')->end()
                        ->scalarNode('default_driver')    ->defaultValue('lifetimefile')                   ->end()
                     ->end()
                ->end()
            ->end();
            
        return $treeBuilder->buildTree();
    }
}