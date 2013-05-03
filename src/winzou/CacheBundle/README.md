winzouCacheBundle
============

What's that?
--------------
winzouCacheBundle provides a simple cache management. Now you can use a cache system without reinventing it.
It supports Apc, XCache, File, ZendData and Array.

Installation
-------------

### 1. Add this bundle to your project:

**Using composer**

Add the following lines in your `composer.json` file:

```
"require": {
    ...
    "winzou/cache-bundle": "dev-master"
}
```

Now, run composer to download the bundle:

```bash
$ composer update
```

### 2. Add this bundle to your application's kernel:

```php
<?php
// app/AppKernel.php
public function registerBundles()
{
  $bundles = array(
      // ...
      new winzou\CacheBundle\winzouCacheBundle(),
      // ...
  );
}
```

Usage
-----
In your controller:

    $cache = $this->get('winzou_cache.apc');
    // or
    $cache = $this->get('winzou_cache.file');
    // or
    $cache = $this->get('winzou_cache.memcache');
    // or
    $cache = $this->get('winzou_cache.array');
    // or
    $cache = $this->get('winzou_cache.xcache');
    // or
    $cache = $this->get('winzou_cache.zenddata');
    // or
    $cache = $this->get('winzou_cache'); // in that case, it will use the default driver defined in config.yml, see below

    $cache->save('bar', array('foo', 'bar'));
    
    if ($cache->contains('bar')) {
        $bar = $cache->fetch('bar');
    }
    
    $cache->delete('bar');

See Cache\AbstractCache for all the available methods.

Configuration
-------------
When using FileCache, if you don't want to store your cache files in `%kernel.cache_dir%/winzou_cache` (default value), then define the absolute path in your config.yml:

    winzou_cache:
        options:
            cache_dir: %kernel.cache_dir%/MyAppCache
    # or    cache_dir: /tmp/MyAppCache/%kernel.environment%

If you want to define in only one place the driver you want to use, you will like the default_driver option:

    winzou_cache:
        options:
            default_driver: apc # default is "lifetimefile"
    
    # and then $cache = $this->get('winzou_cache')

You can now access the ApcCache with the `winzou_cache` service. And if you want to change the driver, you have to modify only one value in your config.yml.

If you don't define the default_driver and use $this->get('winzou_cache'), then you are using the FileCache.

Raw access
----------
You can overwrite any option just by using the factory service. See these two very similar methods:

    $factory = $this->get('winzou_cache.factory');
    $cache = $factory->getCache('file', array('cache_dir' => '/tmp/cache'));

Or by defining your own service:

    your_cache:
        factory_service: winzou_cache.factory
        factory_method:  get
        class:           %winzou_cache.driver.abstract%
        arguments:
            - file                       # just modify this value to use another cache
            - {'cache_dir': /tmp/cache } # you can omit this if you don't use FileCache or if the default value is ok for you
    
    # and then $cache = $this->get('your_cache')
