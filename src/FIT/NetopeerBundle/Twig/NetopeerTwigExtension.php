<?php

namespace FIT\NetopeerBundle\Twig;

use Twig_Extension;
use Twig_Function_Method;

/**
 * Registers custom functions, which could be used in templates
 */
class NetopeerTwigExtension extends Twig_Extension
{
	/**
	 * Array of my defined functions
	 *
	 * @return array
	 */
	public function getFunctions()
    {
        return array(
            'isNumberType' => new Twig_Function_Method($this, 'isNumberType'),
            'isUrlType' => new Twig_Function_Method($this, 'isUrlType'),
            'explode' => new Twig_Function_Method($this, 'explodeString')
        );
    }

	/**
	 * Check if param is number
	 *
	 * @param string|mixed $string  value (string), which we check, if is a number
	 * @return bool
	 */
	public function isNumberType($string)
    {
        if ( strrpos($string, 'int') === false ) {
            return false;
        }

        return true;
    }

	/**
	 * Check if param is URI
	 *
	 * @param string  $string check, if value is an URI
	 * @return bool
	 */
	public function isUrlType($string)
    {
        if ( $string == "inet:uri" ) return true;
        return false;
    }

	/**
	 * Explode function
	 *
	 * @param string $delimiter
	 * @param string $string      string to explode
	 * @return array
	 */
	public function explodeString($delimiter, $string) {
        return explode($delimiter, $string);
    }

	/**
	 * Get name of this extension
	 *
	 * @return string
	 */
	public function getName()
    {
        return 'netopeer_twig_extension';
    }
}
