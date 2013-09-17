<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints\Collection;

use Symfony\Component\Validator\Constraints\Required as BaseRequired;

/**
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated in 2.3, to be removed in 3.0. Use
 *             {@link \Symfony\Component\Validator\Constraints\Required} instead.
 */
class Required extends BaseRequired
{
}
