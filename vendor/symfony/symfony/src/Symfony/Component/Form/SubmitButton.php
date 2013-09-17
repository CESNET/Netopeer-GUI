<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * A button that submits the form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitButton extends Button implements ClickableInterface
{
    /**
     * @var Boolean
     */
    private $clicked = false;

    /**
     * {@inheritdoc}
     */
    public function isClicked()
    {
        return $this->clicked;
    }

    /**
     * Submits data to the button.
     *
     * @param null|string $submittedData The data.
     * @param Boolean     $clearMissing  Not used.
     *
     * @return SubmitButton The button instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     */
    public function submit($submittedData, $clearMissing = true)
    {
        parent::submit($submittedData, $clearMissing);

        $this->clicked = null !== $submittedData;

        return $this;
    }
}
