<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitTypeTest extends TypeTestCase
{
    public function testCreateSubmitButtonInstances()
    {
        $this->assertInstanceOf('Symfony\Component\Form\SubmitButton', $this->factory->create('submit'));
    }

    public function testNotClickedByDefault()
    {
        $button = $this->factory->create('submit');

        $this->assertFalse($button->isClicked());
    }

    public function testNotClickedIfSubmittedWithNull()
    {
        $button = $this->factory->create('submit');
        $button->submit(null);

        $this->assertFalse($button->isClicked());
    }

    public function testClickedIfSubmittedWithEmptyString()
    {
        $button = $this->factory->create('submit');
        $button->submit('');

        $this->assertTrue($button->isClicked());
    }

    public function testClickedIfSubmittedWithUnemptyString()
    {
        $button = $this->factory->create('submit');
        $button->submit('foo');

        $this->assertTrue($button->isClicked());
    }

    public function testSubmitCanBeAddedToForm()
    {
        $form = $this->factory
            ->createBuilder('form')
            ->getForm();

        $this->assertSame($form, $form->add('send', 'submit'));
    }
}
