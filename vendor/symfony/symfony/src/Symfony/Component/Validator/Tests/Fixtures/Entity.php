<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Symfony\Component\Validator\Tests\Fixtures\ConstraintA
 * @Assert\GroupSequence({"Foo", "Entity"})
 */
class Entity extends EntityParent implements EntityInterface
{
    /**
     * @Assert\NotNull
     * @Assert\Range(min=3)
     * @Assert\All({@Assert\NotNull, @Assert\Range(min=3)}),
     * @Assert\All(constraints={@Assert\NotNull, @Assert\Range(min=3)})
     * @Assert\Collection(fields={
     *   "foo" = {@Assert\NotNull, @Assert\Range(min=3)},
     *   "bar" = @Assert\Range(min=5)
     * })
     * @Assert\Choice(choices={"A", "B"}, message="Must be one of %choices%")
     */
    protected $firstName;
    protected $lastName;
    public $reference;
    private $internal;
    public $data = 'Overridden data';

    public function __construct($internal = null)
    {
        $this->internal = $internal;
    }

    public function getInternal()
    {
        return $this->internal.' from getter';
    }

    /**
     * @Assert\NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    public function getData()
    {
        return 'Overridden data';
    }
}
