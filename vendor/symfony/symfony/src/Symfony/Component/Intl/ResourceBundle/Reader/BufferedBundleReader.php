<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Reader;

use Symfony\Component\Intl\ResourceBundle\Util\RingBuffer;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BufferedBundleReader implements BundleReaderInterface
{
    /**
     * @var BundleReaderInterface
     */
    private $reader;

    private $buffer;

    /**
     * Buffers a given reader.
     *
     * @param BundleReaderInterface $reader     The reader to buffer.
     * @param integer               $bufferSize The number of entries to store
     *                                          in the buffer.
     */
    public function __construct(BundleReaderInterface $reader, $bufferSize)
    {
        $this->reader = $reader;
        $this->buffer = new RingBuffer($bufferSize);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        $hash = $path . '//' . $locale;

        if (!isset($this->buffer[$hash])) {
            $this->buffer[$hash] = $this->reader->read($path, $locale);
        }

        return $this->buffer[$hash];
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales($path)
    {
        return $this->reader->getLocales($path);
    }
}
