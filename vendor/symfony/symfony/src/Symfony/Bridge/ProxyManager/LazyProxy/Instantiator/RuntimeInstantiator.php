<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

/**
 * Runtime lazy loading proxy generator.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RuntimeInstantiator implements InstantiatorInterface
{
    /**
     * @var \ProxyManager\Factory\LazyLoadingValueHolderFactory
     */
    private $factory;

    /**
     * Constructor
     */
    public function __construct()
    {
        $config = new Configuration();

        $config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());

        $this->factory = new LazyLoadingValueHolderFactory($config);
    }

    /**
     * {@inheritDoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
    {
        return $this->factory->createProxy(
            $definition->getClass(),
            function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
                $proxy->setProxyInitializer(null);

                $wrappedInstance = call_user_func($realInstantiator);

                return true;
            }
        );
    }
}
