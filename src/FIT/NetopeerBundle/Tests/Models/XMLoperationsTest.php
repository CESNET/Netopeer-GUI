<?php
/**
 * Created by PhpStorm.
 * User: dalexa
 * Date: 06/02/15
 * Time: 09:41
 */

namespace FIT\NetopeerBundle\Tests\Models;

use FIT\NetopeerBundle\Models\XMLoperations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\SimpleXMLElement;

class XMLoperationsTest extends WebTestCase {
	/**
	 * @var \Symfony\Component\DependencyInjection\ContainerInterface $container
	 */
	private $container;

	/**
	 * @var \Symfony\Bridge\Monolog\Logger $logger
	 */
	private $logger;

	/**
	 * @var \FIT\NetopeerBundle\Models\Data $dataModel
	 */
	private $dataModel;

	/**
	 * {@inheritDoc}
	 */
	public function setUp() {
		static::$kernel = static::createKernel();
		static::$kernel->boot();
		$this->container = static::$kernel->getContainer();
		$this->logger = $this->container->get('logger');
		$this->dataModel = $this->container->get('DataModel');

		parent::setUp();
	}


	public function testDivideInputName()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$configuration = array(
			array('state_--*--*?1!--*?1!--*?2!--*?1!', array('state', '--*--*?1!--*?1!--*?2!--*?1!')),
			array('symbol_--*--*?1!--*?2!--*?3!--*?2!', array('symbol', '--*--*?1!--*?2!--*?3!--*?2!')),
		  array('enable-nacm_--*--*?1!', array('enable-nacm', '--*--*?1!')),
		  array('test_konec_--*--*?1!', array('test_konec', '--*--*?1!')),
		  array('test__konec_tady_je--*--*?1!xxx', array('test__konec_tady', 'je--*--*?1!xxx')),
		  array('xxxx', array('name', 'xxxx')),
		  array('', array('name', '')),
		);

		foreach ($configuration as $conf) {
			$this->assertEquals($conf[1], $xmlOp->divideInputName($conf[0]), 'divided name differs for input: '.$conf[0]);
		}

	}

	public function testDecodeXPath()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$configuration = array(
			array('--*--*?1!--*?1!--*?2!--*?1!', '/*/*[1]/*[1]/*[2]/*[1]'),
			array('--*--*?1!--*?2!--*?3!--*?2!', '/*/*[1]/*[2]/*[3]/*[2]'),
			array('--*--*?1!_aaI', '/*/*[1]_aaI'),
			array('--*--*?@contains(xxx)!', '/*/*[@contains(xxx)]'),
			array('--*--*?position()<3!', '/*/*[position()<3]'),
			array('--*--*?1!', '/*/*[1]'),
		);

		foreach ($configuration as $conf) {
			$this->assertEquals($conf[1], $xmlOp->decodeXPath($conf[0]), 'decoded xpath differs for input: '.$conf[0]);
		}
	}

	public function testCompleteRequestTree()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		// add transition function and delta element into empty root element
		$emptyRootModule = '<?xml version="1.0"?><turing-machine xmlns="http://example.net/turing-machine" eltype="container" config="true" description="State data and configuration of a Turing Machine." iskey="false"/>';
		$createString = '<turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine>';
		$node = simplexml_load_string('<?xml version="1.0"?>'.$createString);
		$node->registerXPathNamespace("xmlns", "http://example.net/turing-machine");
		$parent = $node->xpath('/xmlns:*');
		$res = $xmlOp->completeRequestTree($parent[0], $createString);
		$this->assertEquals($node->asXML(), $res->asXML(), 'complete request 1 failed');


		// use result of previous operation and add some next children to first delta
		$node = $res;
		$createString = '<delta><label>test</label><output xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0" xc:operation="create"><state xc:operation="create">2</state><symbol xc:operation="create">3</symbol><head-move xc:operation="create">right</head-move></output></delta>';
		$parent = $node->xpath('//xmlns:delta');

		$res = $xmlOp->completeRequestTree($parent[0], $createString);
		$expectedResString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine" xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0">
<transition-function>
<delta><label>test</label><output xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0" xc:operation="create"><state xc:operation="create">2</state><symbol xc:operation="create">3</symbol><head-move xc:operation="create">right</head-move></output></delta></transition-function>
</turing-machine>';
		$this->assertEquals($expectedResString, trim($res->asXML()), 'complete request 2 failed');

		// use result of first operation and add some next children to second delta
		$res = $xmlOp->completeRequestTree($parent[1], str_replace("test", "test2", $createString));
		$expectedResString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine" xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0">
<transition-function>
<delta><label>test2</label><output xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0" xc:operation="create"><state xc:operation="create">2</state><symbol xc:operation="create">3</symbol><head-move xc:operation="create">right</head-move></output></delta></transition-function>
</turing-machine>';
		$this->assertEquals($expectedResString, trim($res->asXML()), 'complete request 3 failed');
	}

	public function testElementValReplace()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleEditConfigForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleCreateEmptyModuleForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleRPCMethodForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}


	public function testHandleDuplicateNodeForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleGenerateNodeForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleNewNodeForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testMoveCustomKeyAttributesIntoElements()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testRemoveChildren()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testInsertNewElemIntoXMLTree()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testHandleRemoveNodeForm()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testExecuteEditConfig()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testLoadModel()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testMergeXMLWithModel()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testIsResponseValidXML()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testGetElementParent()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testCheckElemMatch()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testCompleteAttributes()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testFindAndComplete()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testMergeRecursive()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testMergeWithModel()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testValidateXml()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testGetAvailableLabelValuesForXPath()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testGetChildrenValues()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testRemoveMultipleWhitespaces()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}


}
 