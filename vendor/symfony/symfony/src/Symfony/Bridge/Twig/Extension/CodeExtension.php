<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

if (!defined('ENT_SUBSTITUTE')) {
    define('ENT_SUBSTITUTE', 8);
}

/**
 * Twig extension relate to PHP code and used by the profiler and the default exception templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CodeExtension extends \Twig_Extension
{
    private $fileLinkFormat;
    private $rootDir;
    private $charset;

    /**
     * Constructor.
     *
     * @param string $fileLinkFormat The format for links to source files
     * @param string $rootDir        The project root directory
     * @param string $charset        The charset
     */
    public function __construct($fileLinkFormat, $rootDir, $charset)
    {
        $this->fileLinkFormat = empty($fileLinkFormat) ? ini_get('xdebug.file_link_format') : $fileLinkFormat;
        $this->rootDir = str_replace('\\', '/', $rootDir).'/';
        $this->charset = $charset;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('abbr_class', array($this, 'abbrClass'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('abbr_method', array($this, 'abbrMethod'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_args', array($this, 'formatArgs'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_args_as_text', array($this, 'formatArgsAsText')),
            new \Twig_SimpleFilter('file_excerpt', array($this, 'fileExcerpt'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_file', array($this, 'formatFile'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_file_from_text', array($this, 'formatFileFromText'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('file_link', array($this, 'getFileLink'), array('is_safe' => array('html'))),
        );
    }

    public function abbrClass($class)
    {
        $parts = explode('\\', $class);
        $short = array_pop($parts);

        return sprintf("<abbr title=\"%s\">%s</abbr>", $class, $short);
    }

    public function abbrMethod($method)
    {
        if (false !== strpos($method, '::')) {
            list($class, $method) = explode('::', $method, 2);
            $result = sprintf("%s::%s()", $this->abbrClass($class), $method);
        } elseif ('Closure' === $method) {
            $result = sprintf("<abbr title=\"%s\">%s</abbr>", $method, $method);
        } else {
            $result = sprintf("<abbr title=\"%s\">%s</abbr>()", $method, $method);
        }

        return $result;
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    public function formatArgs($args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $parts = explode('\\', $item[1]);
                $short = array_pop($parts);
                $formattedValue = sprintf("<em>object</em>(<abbr title=\"%s\">%s</abbr>)", $item[1], $short);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf("<em>array</em>(%s)", is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('string'  === $item[0]) {
                $formattedValue = sprintf("'%s'", htmlspecialchars($item[1], ENT_QUOTES, $this->charset));
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', var_export(htmlspecialchars((string) $item[1], ENT_QUOTES, $this->charset), true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    public function formatArgsAsText($args)
    {
        return strip_tags($this->formatArgs($args));
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file A file path
     * @param int    $line The selected line number
     *
     * @return string An HTML string
     */
    public function fileExcerpt($file, $line)
    {
        if (is_readable($file)) {
            // highlight_file could throw warnings
            // see https://bugs.php.net/bug.php?id=25725
            $code = @highlight_file($file, true);
            // remove main code/span tags
            $code = preg_replace('#^<code.*?>\s*<span.*?>(.*)</span>\s*</code>#s', '\\1', $code);
            $content = preg_split('#<br />#', $code);

            $lines = array();
            for ($i = max($line - 3, 1), $max = min($line + 3, count($content)); $i <= $max; $i++) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'><code>'.self::fixCodeMarkup($content[$i - 1]).'</code></li>';
            }

            return '<ol start="'.max($line - 3, 1).'">'.implode("\n", $lines).'</ol>';
        }
    }

    /**
     * Formats a file path.
     *
     * @param string  $file An absolute file path
     * @param integer $line The line number
     * @param string  $text Use this text for the link rather than the file path
     *
     * @return string
     */
    public function formatFile($file, $line, $text = null)
    {
        if (null === $text) {
            $file = trim($file);
            $text = $file;
            if (0 === strpos($text, $this->rootDir)) {
                $text = str_replace($this->rootDir, '', str_replace('\\', '/', $text));
                $text = sprintf('<abbr title="%s">kernel.root_dir</abbr>/%s', $this->rootDir, $text);
            }
        }

        $text = "$text at line $line";

        if (false !== $link = $this->getFileLink($file, $line)) {
            return sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, $this->charset), $text);
        }

        return $text;
    }

    /**
     * Returns the link for a given file/line pair.
     *
     * @param string  $file An absolute file path
     * @param integer $line The line number
     *
     * @return string A link of false
     */
    public function getFileLink($file, $line)
    {
        if ($this->fileLinkFormat && is_file($file)) {
            return strtr($this->fileLinkFormat, array('%f' => $file, '%l' => $line));
        }

        return false;
    }

    public function formatFileFromText($text)
    {
        $that = $this;

        return preg_replace_callback('/in ("|&quot;)?(.+?)\1(?: +(?:on|at))? +line (\d+)/s', function ($match) use ($that) {
            return 'in '.$that->formatFile($match[2], $match[3]);
        }, $text);
    }

    public function getName()
    {
        return 'code';
    }

    protected static function fixCodeMarkup($line)
    {
        // </span> ending tag from previous line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $closing && (false === $opening || $closing < $opening)) {
            $line = substr_replace($line, '', $closing, 7);
        }

        // missing </span> tag at the end of line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $opening && (false === $closing || $closing > $opening)) {
            $line .= '</span>';
        }

        return $line;
    }
}
