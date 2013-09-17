<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates values and graphs of objects and arrays.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
interface ValidatorInterface
{
    /**
     * Validates a value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $value    The value to validate
     * @param array|null $groups   The validation groups to validate.
     * @param Boolean    $traverse Whether to traverse the value if it is traversable.
     * @param Boolean    $deep     Whether to traverse nested traversable values recursively.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validate($value, $groups = null, $traverse = false, $deep = false);

    /**
     * Validates a property of a value against its current value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $containingValue The value containing the property.
     * @param string     $property        The name of the property to validate.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validateProperty($containingValue, $property, $groups = null);

    /**
     * Validate a property of a value against a potential value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param string     $containingValue The value containing the property.
     * @param string     $property        The name of the property to validate
     * @param string     $value           The value to validate against the
     *                                    constraints of the property.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validatePropertyValue($containingValue, $property, $value, $groups = null);

    /**
     * Validates a value against a constraint or a list of constraints.
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param array|null              $groups      The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validateValue($value, $constraints, $groups = null);

    /**
     * Returns the factory for metadata instances.
     *
     * @return MetadataFactoryInterface The metadata factory.
     *
     * @api
     */
    public function getMetadataFactory();
}
