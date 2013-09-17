<?php

namespace Sensio\Bundle\FrameworkExtraBundle\Tests\Request\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\DateTimeParamConverter;

class DateTimeParamConverterTest extends \PHPUnit_Framework_TestCase
{
    private $converter;

    public function setUp()
    {
        $this->converter = new DateTimeParamConverter();
    }

    public function testSupports()
    {
        $config = $this->createConfiguration("DateTime");
        $this->assertTrue($this->converter->supports($config));

        $config = $this->createConfiguration(__CLASS__);
        $this->assertFalse($this->converter->supports($config));

        $config = $this->createConfiguration();
        $this->assertFalse($this->converter->supports($config));
    }

    public function testApply()
    {
        $request = new Request(array(), array(), array('start' => '2012-07-21 00:00:00'));
        $config = $this->createConfiguration("DateTime", "start");

        $this->converter->apply($request, $config);

        $this->assertInstanceOf("DateTime", $request->attributes->get('start'));
        $this->assertEquals('2012-07-21', $request->attributes->get('start')->format('Y-m-d'));
    }

    public function testApplyWithFormatInvalidDate404Exception()
    {
        $request = new Request(array(), array(), array('start' => '2012-07-21'));
        $config = $this->createConfiguration("DateTime", "start");
        $config->expects($this->any())->method('getOptions')->will($this->returnValue(array('format' => 'd.m.Y')));

        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', 'Invalid date given.');
        $this->converter->apply($request, $config);
    }

    public function createConfiguration($class = null, $name = null)
    {
        $config = $this->getMock(
            'Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface', array(
            'getClass', 'getAliasName', 'getOptions', 'getName', 'allowArray'
        ));
        if ($name !== null) {
            $config->expects($this->any())
                   ->method('getName')
                   ->will($this->returnValue($name));
        }
        if ($class !== null) {
            $config->expects($this->any())
                   ->method('getClass')
                   ->will($this->returnValue($class));
        }

        return $config;
    }
}
