<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageSelector;

class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    protected $tmpDir;

    protected function setUp()
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_translation';
        $this->deleteTmpDir();
    }

    public function tearDown()
    {
        $this->deleteTmpDir();
    }

    protected function deleteTmpDir()
    {
        if (!file_exists($dir = $this->tmpDir)) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }

    public function testTransWithoutCaching()
    {
        $translator = $this->getTranslator($this->getLoader());
        $translator->setLocale('fr');
        $translator->setFallbackLocale(array('en', 'es', 'pt-PT', 'pt_BR'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
    }

    public function testTransWithCaching()
    {
        // prime the cache
        $translator = $this->getTranslator($this->getLoader(), array('cache_dir' => $this->tmpDir));
        $translator->setLocale('fr');
        $translator->setFallbackLocale(array('en', 'es', 'pt-PT', 'pt_BR'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));

        // do it another time as the cache is primed now
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir));
        $translator->setLocale('fr');
        $translator->setFallbackLocale(array('en', 'es', 'pt-PT', 'pt_BR'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
    }

    public function testGetLocale()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $request
            ->expects($this->once())
            ->method('getLocale')
            ->will($this->returnValue('en'))
        ;

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $container
            ->expects($this->exactly(2))
            ->method('isScopeActive')
            ->with('request')
            ->will($this->onConsecutiveCalls(false, true))
        ;

        $container
            ->expects($this->once())
            ->method('has')
            ->with('request')
            ->will($this->returnValue(true))
        ;

        $container
            ->expects($this->once())
            ->method('get')
            ->with('request')
            ->will($this->returnValue($request))
        ;

        $translator = new Translator($container, new MessageSelector());

        $this->assertNull($translator->getLocale());
        $this->assertSame('en', $translator->getLocale());
    }

    protected function getCatalogue($locale, $messages)
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($messages as $key => $translation) {
            $catalogue->set($key, $translation);
        }

        return $catalogue;
    }

    protected function getLoader()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $loader
            ->expects($this->at(0))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(
                'foo' => 'foo (FR)',
            ))))
        ;
        $loader
            ->expects($this->at(1))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('en', array(
                'foo'    => 'foo (EN)',
                'bar'    => 'bar (EN)',
                'choice' => '{0} choice 0 (EN)|{1} choice 1 (EN)|]1,Inf] choice inf (EN)',
            ))))
        ;
        $loader
            ->expects($this->at(2))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('es', array(
                'foobar' => 'foobar (ES)',
            ))))
        ;
        $loader
            ->expects($this->at(3))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('pt-PT', array(
                'foobarfoo' => 'foobarfoo (PT-PT)',
            ))))
        ;
        $loader
            ->expects($this->at(4))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('pt_BR', array(
                'other choice' => '{0} other choice 0 (PT-BR)|{1} other choice 1 (PT-BR)|]1,Inf] other choice inf (PT-BR)',
            ))))
        ;

        return $loader;
    }

    protected function getContainer($loader)
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue($loader))
        ;

        return $container;
    }

    public function getTranslator($loader, $options = array())
    {
        $translator = new Translator(
            $this->getContainer($loader),
            new MessageSelector(),
            array('loader' => array('loader')),
            $options
        );

        $translator->addResource('loader', 'foo', 'fr');
        $translator->addResource('loader', 'foo', 'en');
        $translator->addResource('loader', 'foo', 'es');
        $translator->addResource('loader', 'foo', 'pt-PT'); // European Portuguese
        $translator->addResource('loader', 'foo', 'pt_BR'); // Brazilian Portuguese

        return $translator;
    }
}
