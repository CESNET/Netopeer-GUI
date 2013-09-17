<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             the helper "form_start()" instead.
 */
class FormEnctypeNode extends SearchAndRenderBlockNode
{
    public function compile(\Twig_Compiler $compiler)
    {
        parent::compile($compiler);

        // Uncomment this as soon as the deprecation note should be shown
        // $compiler->write('trigger_error(\'The helper form_enctype(form) is deprecated since version 2.3 and will be removed in 3.0. Use form_start(form) instead.\', E_USER_DEPRECATED)');
    }
}
