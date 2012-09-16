<?php

namespace FIT\NetopeerBundle\Twig;

use Twig_Extension;
use Twig_Function_Method;

class NetopeerTwigExtension extends Twig_Extension
{
    public function getFunctions()
    {
        return array(
            'isNumberType' => new Twig_Function_Method($this, 'isNumberType'),
            'isUrlType' => new Twig_Function_Method($this, 'isUrlType'),
        );
    }

    public function isNumberType($string)
    {
        if ( strrpos($string, 'int') === false ) {
            return false;
        }

        return true;
    }

    public function isUrlType($string)
    {
        if ( $string == "inet:uri" ) return true;
        return false;
    }

    public function getName()
    {
        return 'netopeer_twig_extension';
    }
}
