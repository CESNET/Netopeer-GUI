<?php

/*
 * Copyright 2010 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\SecurityExtraBundle\Generator;

use JMS\SecurityExtraBundle\Metadata\MethodMetadata;
use JMS\SecurityExtraBundle\Metadata\ServiceMetadata;
use Symfony\Component\DependencyInjection\Definition;
use \ReflectionClass;
use \ReflectionMethod;

/**
 * Generates the proxy class which has security checks built-in according to
 * the given metadata information.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 */
class ProxyClassGenerator
{
    private $classCount = array();

    /**
     * Generates the proxy class
     *
     * @param Definition $definition
     * @param ServiceMetadata $metadata
     * @return array<string, string>
     */
    public function generate(Definition $definition, ServiceMetadata $metadata)
    {
        list($className, $proxy) = $this->getClassDefinition($definition);
        foreach ($metadata->methodMetadata as $name => $method) {
            $reflection = $method->reflection;

            $proxy .= $this->getMethodDefinition($reflection);

            $proxy .= '    static $jmsSecurityExtra__metadata = '.$this->getMethodSecurityMetadata($method).';

    ';

            $proxy .= '    return ';

            $proxy .= '$this->jmsSecurityExtraBundle__methodSecurityInterceptor->invoke(
    ';
            $proxy .= '        '.$this->getSecureMethodInvocation($method).',
    ';
            $proxy .= '        $jmsSecurityExtra__metadata
    ';
            $proxy .= '    );';

            $proxy .= '
    }

    ';
        }

        return array($className, substr($proxy, 0, -6).'}');
    }

    private function getMethodSecurityMetadata(MethodMetadata $method)
    {
        $metadata = var_export($method->getAsArray(), true);

        $staticReplaces = array(
            "\n" => '',
            'array (' => 'array(',
        );
        $metadata = strtr($metadata, $staticReplaces);

        $regexReplaces = array(
            '/\s+/' => ' ',
        		'/\(\s+/' => '(',
            '/[0-9]+\s+=>\s+/' => '',
            '/,\s*\)/' => ')',
        );
        $metadata = preg_replace(array_keys($regexReplaces), array_values($regexReplaces), $metadata);

        return $metadata;
    }

    private function getSecureMethodInvocation(MethodMetadata $method)
    {
        $code = 'new MethodInvocation('
                .var_export($method->reflection->getDeclaringClass()->getName(), true)
                .', '.var_export($method->reflection->getName(), true)
                .', $this'
                .', array(';

        $arguments = array();
        foreach ($method->reflection->getParameters() as $param) {
            $arguments[] = '$'.$param->getName();
        }
        $code .= implode(', ', $arguments).'))';

        return $code;
    }

    private function getClassDefinition(Definition $definition)
    {
        $baseClass = $definition->getClass();
        if (false !== $pos = strrpos($baseClass, '\\')) {
            $className = substr($baseClass, $pos + 1);
        } else {
            $className = $baseClass;
        }

        if (isset($this->classCount[$className])) {
            $className .= '_'.(++$this->classCount[$className]);
        } else {
            $this->classCount[$className] = 1;
        }

        $requiredFiles = '';
        if (null !== $file = $definition->getFile()) {
            $requiredFiles .= sprintf("\nrequire_once %s;", var_export($definition->getFile(), true));
        }
        if ('' !== $requiredFiles) {
            $requiredFiles .= "\n";
        }

        return array($className, sprintf('<?php

namespace SecurityProxies;

use JMS\SecurityExtraBundle\Security\Authorization\Interception\MethodInvocation;
use JMS\SecurityExtraBundle\Security\Authorization\Interception\MethodSecurityInterceptor;
%s
/**
 * This class has been auto-generated. Manual changes will be lost.
 * Last updated at '.date('r').'
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class %s extends \%s
{
    private $jmsSecurityExtraBundle__methodSecurityInterceptor;

    public function jmsSecurityExtraBundle__setMethodSecurityInterceptor(MethodSecurityInterceptor $interceptor)
    {
        $this->jmsSecurityExtraBundle__methodSecurityInterceptor = $interceptor;
    }

    ', $requiredFiles, $className, $baseClass));
    }

    private function getMethodDefinition(ReflectionMethod $method)
    {
        $def = '';
        if ($method->isProtected()) {
            $def .= 'protected ';
        } else {
            $def .= 'public ';
        }

        if ($method->isStatic()) {
            $def .= 'static ';
        }

        if ($method->returnsReference()) {
            $def .= '&';
        }

        $def .= 'function '.$method->getName().'(';
        $parameters = $method->getParameters();
        foreach ($parameters as $param) {
            if (null !== $class = $param->getClass()) {
                $def .= '\\'.$class->getName().' ';
            } else if ($param->isArray()) {
                $def .= 'array ';
            }

            if ($param->isPassedByReference()) {
                $def .= '&';
            }

            $def .= '$'.$param->getName();

            if ($param->isOptional()) {
                $def .= ' = '.var_export($param->getDefaultValue(), true);
            }

            $def .= ', ';
        }

        if (count($parameters) > 0) {
            $def = substr($def, 0, -2);
        }

        $def .= ')
    {
    ';

        return $def;
    }
}