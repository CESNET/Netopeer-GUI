<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

use Symfony\Component\DomCrawler\Field\FormField;

/**
 * Form represents an HTML form.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Form extends Link implements \ArrayAccess
{
    /**
     * @var \DOMNode
     */
    private $button;

    /**
     * @var Field\FormField[]
     */
    private $fields;

    /**
     * Constructor.
     *
     * @param \DOMNode $node       A \DOMNode instance
     * @param string   $currentUri The URI of the page where the form is embedded
     * @param string   $method     The method to use for the link (if null, it defaults to the method defined by the form)
     *
     * @throws \LogicException if the node is not a button inside a form tag
     *
     * @api
     */
    public function __construct(\DOMNode $node, $currentUri, $method = null)
    {
        parent::__construct($node, $currentUri, $method);

        $this->initialize();
    }

    /**
     * Gets the form node associated with this form.
     *
     * @return \DOMNode A \DOMNode instance
     */
    public function getFormNode()
    {
        return $this->node;
    }

    /**
     * Sets the value of the fields.
     *
     * @param array $values An array of field values
     *
     * @return Form
     *
     * @api
     */
    public function setValues(array $values)
    {
        foreach ($values as $name => $value) {
            $this->fields->set($name, $value);
        }

        return $this;
    }

    /**
     * Gets the field values.
     *
     * The returned array does not include file fields (@see getFiles).
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getValues()
    {
        $values = array();
        foreach ($this->fields->all() as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if (!$field instanceof Field\FileFormField && $field->hasValue()) {
                $values[$name] = $field->getValue();
            }
        }

        return $values;
    }

    /**
     * Gets the file field values.
     *
     * @return array An array of file field values.
     *
     * @api
     */
    public function getFiles()
    {
        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE', 'PATCH'))) {
            return array();
        }

        $files = array();

        foreach ($this->fields->all() as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if ($field instanceof Field\FileFormField) {
                $files[$name] = $field->getValue();
            }
        }

        return $files;
    }

    /**
     * Gets the field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getPhpValues()
    {
        $qs = http_build_query($this->getValues(), '', '&');
        parse_str($qs, $values);

        return $values;
    }

    /**
     * Gets the file field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getPhpFiles()
    {
        $qs = http_build_query($this->getFiles(), '', '&');
        parse_str($qs, $values);

        return $values;
    }

    /**
     * Gets the URI of the form.
     *
     * The returned URI is not the same as the form "action" attribute.
     * This method merges the value if the method is GET to mimics
     * browser behavior.
     *
     * @return string The URI
     *
     * @api
     */
    public function getUri()
    {
        $uri = parent::getUri();

        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE', 'PATCH')) && $queryString = http_build_query($this->getValues(), null, '&')) {
            $sep = false === strpos($uri, '?') ? '?' : '&';
            $uri .= $sep.$queryString;
        }

        return $uri;
    }

    protected function getRawUri()
    {
        return $this->node->getAttribute('action');
    }

    /**
     * Gets the form method.
     *
     * If no method is defined in the form, GET is returned.
     *
     * @return string The method
     *
     * @api
     */
    public function getMethod()
    {
        if (null !== $this->method) {
            return $this->method;
        }

        return $this->node->getAttribute('method') ? strtoupper($this->node->getAttribute('method')) : 'GET';
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     *
     * @return Boolean true if the field exists, false otherwise
     *
     * @api
     */
    public function has($name)
    {
        return $this->fields->has($name);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     *
     * @throws \InvalidArgumentException when the name is malformed
     *
     * @api
     */
    public function remove($name)
    {
        $this->fields->remove($name);
    }

    /**
     * Gets a named field.
     *
     * @param string $name The field name
     *
     * @return FormField The field instance
     *
     * @throws \InvalidArgumentException When field is not present in this form
     *
     * @api
     */
    public function get($name)
    {
        return $this->fields->get($name);
    }

    /**
     * Sets a named field.
     *
     * @param FormField $field The field
     *
     * @api
     */
    public function set(FormField $field)
    {
        $this->fields->add($field);
    }

    /**
     * Gets all fields.
     *
     * @return FormField[] An array of fields
     *
     * @api
     */
    public function all()
    {
        return $this->fields->all();
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     *
     * @return Boolean true if the field exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Gets the value of a field.
     *
     * @param string $name The field name
     *
     * @return FormField The associated Field instance
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet($name)
    {
        return $this->fields->get($name);
    }

    /**
     * Sets the value of a field.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetSet($name, $value)
    {
        $this->fields->set($name, $value);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     */
    public function offsetUnset($name)
    {
        $this->fields->remove($name);
    }

    /**
     * Sets the node for the form.
     *
     * Expects a 'submit' button \DOMNode and finds the corresponding form element.
     *
     * @param \DOMNode $node A \DOMNode instance
     *
     * @throws \LogicException If given node is not a button or input or does not have a form ancestor
     */
    protected function setNode(\DOMNode $node)
    {
        $this->button = $node;
        if ('button' == $node->nodeName || ('input' == $node->nodeName && in_array($node->getAttribute('type'), array('submit', 'button', 'image')))) {
            if ($node->hasAttribute('form')) {
                // if the node has the HTML5-compliant 'form' attribute, use it
                $formId = $node->getAttribute('form');
                $form = $node->ownerDocument->getElementById($formId);
                if (null === $form) {
                    throw new \LogicException(sprintf('The selected node has an invalid form attribute (%s).', $formId));
                }
                $this->node = $form;

                return;
            }
            // we loop until we find a form ancestor
            do {
                if (null === $node = $node->parentNode) {
                    throw new \LogicException('The selected node does not have a form ancestor.');
                }
            } while ('form' != $node->nodeName);
        } elseif ('form' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to submit on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }

    private function initialize()
    {
        $this->fields = new FormFieldRegistry();

        $document = new \DOMDocument('1.0', 'UTF-8');
        $node = $document->importNode($this->node, true);
        $button = $document->importNode($this->button, true);
        $root = $document->appendChild($document->createElement('_root'));
        $root->appendChild($node);
        $root->appendChild($button);
        $xpath = new \DOMXPath($document);

        // add descendant elements to the form
        $fieldNodes = $xpath->query('descendant::input | descendant::button | descendant::textarea | descendant::select', $root);
        foreach ($fieldNodes as $node) {
            $this->addField($node, $button);
        }

        // find form elements corresponding to the current form by the HTML5 form attribute
        if ($this->node->hasAttribute('id')) {
            $formId = Crawler::xpathLiteral($this->node->getAttribute('id'));
            $xpath = new \DOMXPath($this->node->ownerDocument);
            $fieldNodes = $xpath->query(sprintf('descendant::input[@form=%s] | descendant::button[@form=%s] | descendant::textarea[@form=%s] | descendant::select[@form=%s]', $formId, $formId, $formId, $formId));
            foreach ($fieldNodes as $node) {
                $this->addField($node, $button);
            }
        }
    }

    private function addField(\DOMNode $node, \DOMNode $button)
    {
        if (!$node->hasAttribute('name') || !$node->getAttribute('name')) {
            return;
        }

        $nodeName = $node->nodeName;

        if ($node === $button) {
            $this->set(new Field\InputFormField($node));
        } elseif ('select' == $nodeName || 'input' == $nodeName && 'checkbox' == $node->getAttribute('type')) {
            $this->set(new Field\ChoiceFormField($node));
        } elseif ('input' == $nodeName && 'radio' == $node->getAttribute('type')) {
            if ($this->has($node->getAttribute('name'))) {
                $this->get($node->getAttribute('name'))->addChoice($node);
            } else {
                $this->set(new Field\ChoiceFormField($node));
            }
        } elseif ('input' == $nodeName && 'file' == $node->getAttribute('type')) {
            $this->set(new Field\FileFormField($node));
        } elseif ('input' == $nodeName && !in_array($node->getAttribute('type'), array('submit', 'button', 'image'))) {
            $this->set(new Field\InputFormField($node));
        } elseif ('textarea' == $nodeName) {
            $this->set(new Field\TextareaFormField($node));
        }
    }
}
