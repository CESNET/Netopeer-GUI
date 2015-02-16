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
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);
		$testXML = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function>
<delta>
	<label>test</label>
	<output>
		<state>2</state>
		<symbol myatt="8">3</symbol>
		<head-move>right</head-move>
	</output>
</delta>
</transition-function>
</turing-machine>';
		$testXMLObject = simplexml_load_string($testXML);
		$testXMLObject->registerXPathNamespace("xmlns", "http://example.net/turing-machine");

		// set head-move to left, new index to 10
		$xpath = '*/*/*[1]/*[2]/*[3]';
		$res = $xmlOp->elementValReplace($testXMLObject, 'head-move', $xpath, 'left', 'xmlns:', 10);
		$expected = '<head-move index="10">left</head-move>';
		$this->assertEquals($expected, $res->asXML(), 'element val replace 1');

		// set label to new_test
		$xpath = '*/*/*[1]/*[1]';
		$res = $xmlOp->elementValReplace($testXMLObject, 'label', $xpath, 'new_test');
		$expected = '<label>new_test</label>';
		$this->assertEquals($expected, $res->asXML(), 'element val replace 2');

		// set myatt of symbol to value 10
		$xpath = '*/*/*[1]/*[2]/*[2]';
		$res = $xmlOp->elementValReplace($testXMLObject, 'at-myatt', $xpath, '10');
		$expected = '<symbol myatt="10">3</symbol>';
		$this->assertEquals($expected, $res->asXML(), 'element val replace with attributes 3');

		// expected false on not existing xpath
		$xpath = '*/*/*[50]/*[78]';
		$res = $xmlOp->elementValReplace($testXMLObject, 'xxx', $xpath, '84');
		$this->assertEquals(array(), $res, 'element val replace 4 - unexisting namespace');
	}

	/**
	 * @expectedExceptionMessage Undefined namespace prefix
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testElementValReplaceException()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);
		$testXML = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function>
</transition-function>
</turing-machine>';
		$testXMLObject = simplexml_load_string($testXML);
		$testXMLObject->registerXPathNamespace("xmlns", "http://example.net/turing-machine");

		// expected false on not existing namespace
		$xpath = '*/*/*[50]/*[78]';
		$res = $xmlOp->elementValReplace($testXMLObject, 'xxx', $xpath, '84', 'xxx:');
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

	public function testRemoveChildrenExceptOfKeyElements()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testInsertNewElemIntoXMLTree()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);
		$testXML = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function>
<delta>
	<label>test</label>
</delta>
</transition-function>
</turing-machine>';
		$testXMLObject = simplexml_load_string($testXML);
		$testXMLObject->registerXPathNamespace("xmlns", "http://example.net/turing-machine");

		// insert output element
		$xpath = '*/*/*[1]';
		$res = $xmlOp->insertNewElemIntoXMLTree($testXMLObject, $xpath, 'output', '');
		$expected = '<output xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0" xc:operation="create"/>';
		$this->assertEquals($expected, $res->asXML(), 'insert new elem 1');

		// insert state element with value
		$xpath = '*/*/*[1]/*[2]';
		$res = $xmlOp->insertNewElemIntoXMLTree($testXMLObject, $xpath, 'state', '2');
		$expected = '<state xc:operation="create">2</state>';
		$this->assertEquals($expected, $res->asXML(), 'insert new elem 2');

		// insert symbol element with value
		$xpath = '*/*/*[1]/*[2]';
		$res = $xmlOp->insertNewElemIntoXMLTree($testXMLObject, $xpath, 'symbol', '3');
		$expected = '<symbol xc:operation="create">3</symbol>';
		$this->assertEquals($expected, $res->asXML(), 'insert new elem 3');

		// check if whole XML matches
		$expectedResString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function>
<delta>
	<label>test</label>
<output xmlns:xc="urn:ietf:params:xml:ns:netconf:base:1.0" xc:operation="create"><state xc:operation="create">2</state><symbol xc:operation="create">3</symbol></output></delta>
</transition-function>
</turing-machine>
';
		$this->assertEquals($expectedResString, $testXMLObject->asXML(), 'insert new elem DOM matches');
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

	public function testRemoveXmlHeader()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$headers = array(
			'<?xml version="1.0"?>',
		  '<?xml version="1.0" encoding="UTF-8"?>',
		  '<?xml version="1.0" encoding="UTF-16" standalone="yes"?>'
		);
		$xml = '<turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine>';

		$i = 1;
		foreach ($headers as $header) {
			$text = $header . $xml;
			$this->assertEquals($xml, $xmlOp->removeXmlHeader($text), 'remove XML header '.$i++);
		}
	}

	public function testLoadModel()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);
		$res = $xmlOp->loadModel();

		$this->assertTrue($res instanceof \SimpleXMLElement, 'model found and loaded correctly');
	}

	public function testMergeXMLWithModel()
	{
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	public function testIsResponseValidXML()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$validXml = array(
			'<test></test>',
			'<turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine>',
			'<turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine><turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine>',
		);

		$invalidXml = array(
			'<test></test_false>',
			'<turing-machine xmlns="http://example.net/turing-machine"><transition-functionx><delta><label>test</label></delta><delta><label>test2</label></delta></transition-function></turing-machine>',
			'<turing-machine xmlns="http://example.net/turing-machine"><transition-function><delta><label>test</label></delta><delta><label>test2</delta></transition-function></turing-machine>',
		);

		$i = 1;
		foreach ($validXml as $xml) {
			$this->assertTrue($xmlOp->isResponseValidXML($xml), 'xml is valid '.$i++);
		}

		$i = 1;
		foreach ($invalidXml as $xml) {
			$this->assertFalse($xmlOp->isResponseValidXML($xml), 'xml is invalid '.$i++);
		}
	}

	public function testGetElementParent()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$testXMLString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function>
<delta>
	<label>test</label>
</delta>
<delta>
	<label>test2</label>
</delta>
<connection-type eltype="container">
	<connection-type eltype="choice">
		<persistent-connection eltype="case">
			<persistent>true</persistent>
		</persistent-connection>
		<periodic-connection eltype="case">
			<timeout-mins eltype="leaf">10</timeout-mins>
			<linger-secs eltype="leaf">20</linger-secs>
		</periodic-connection>
	</connection-type>
</connection-type>
<connection-type></connection-type>
</transition-function>
</turing-machine>';
		$testXML = simplexml_load_string($testXMLString);
		$testXML->registerXPathNamespace('xmlns', 'http://example.net/turing-machine');

		$element = $testXML->xpath('/*/xmlns:transition-function');
		$expected = $testXML->xpath('/xmlns:turing-machine');
		$this->assertEquals($expected[0]->asXml(), $xmlOp->getElementParent($element[0])->asXML(), 'get element parent 1');

		$element = $testXML->xpath('//xmlns:label[text()="test2"]');
		$expected = $testXML->xpath('/*/*/xmlns:delta[2]');
		$this->assertEquals($expected[0]->asXml(), $xmlOp->getElementParent($element[0])->asXML(), 'get element parent 2');

		$element = $testXML->xpath('//xmlns:timeout-mins');
		$expected = $testXML->xpath('/*/*/*[3]');
		$this->assertEquals($expected[0]->asXml(), $xmlOp->getElementParent($element[0])->asXML(), 'get element parent 3');

		$element = $testXML->xpath('//xmlns:persistent');
		$expected = $testXML->xpath('/*/*/*[3]');
		$this->assertEquals($expected[0]->asXml(), $xmlOp->getElementParent($element[0])->asXML(), 'get element parent 4');
	}

	public function testCheckElemMatch()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$testXMLString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function eltype="not" description="test">
<connection-type eltype="container" description="text" attr="xxx" cokoliv="fijao"/>
	<connection-type />
</transition-function>
<transition-function>
	<connection-type />
</transition-function>
</turing-machine>';
		$testXML = simplexml_load_string($testXMLString);
		$testXML->registerXPathNamespace('xmlns', 'http://example.net/turing-machine');

		// elements should match
		$sourceXpath = "/xmlns:*/*[1]/*[1]";
		$targetXpath = "/xmlns:*/*[2]/*[1]";
		$source = $testXML->xpath($sourceXpath);
		$target = $testXML->xpath($targetXpath);
		$this->assertTrue($xmlOp->checkElemMatch($source[0], $target[0]), 'check element match 1');

		// elements should not match
		$sourceXpath = "/xmlns:*/*[1]/*[1]";
		$targetXpath = "/xmlns:*/*[1]";
		$source = $testXML->xpath($sourceXpath);
		$target = $testXML->xpath($targetXpath);
		$this->assertFalse($xmlOp->checkElemMatch($source[0], $target[0]), 'check element match 2');
	}

	public function testCompleteAttributes()
	{
		$xmlOp = new XMLoperations($this->container, $this->logger, $this->dataModel);

		$testXMLString = '<?xml version="1.0"?>
<turing-machine xmlns="http://example.net/turing-machine">
<transition-function eltype="not" description="test">
<connection-type eltype="container" description="text" attr="xxx" cokoliv="fijao"/>
<connection-type />
</transition-function>
<transition-function>
	<connection-type />
</transition-function>
</turing-machine>';
		$testXML = simplexml_load_string($testXMLString);
		$testXML->registerXPathNamespace('xmlns', 'http://example.net/turing-machine');

		// copy all attributes
		$sourceXpath = "/xmlns:*/*[1]/*[1]";
		$targetXpath = "/xmlns:*/*[1]/*[2]";
		$source = $testXML->xpath($sourceXpath);
		$target = $testXML->xpath($targetXpath);
		$xmlOp->completeAttributes($source[0], $target[0]);
		$this->assertEquals($source[0]->asXml(), $target[0]->asXml(), 'complete attributes 1');

		// here is not enabled eltype, so no attributes should be copied
		$sourceXpath = "/xmlns:*/*[1]";
		$targetXpath = "/xmlns:*/*[2]";
		$source = $testXML->xpath($sourceXpath);
		$target = $testXML->xpath($targetXpath);
		$xmlOp->completeAttributes($source[0], $target[0]);
		$expectedString = '<transition-function>
	<connection-type/>
</transition-function>';
		$this->assertEquals($expectedString, $target[0]->asXml(), 'complete attributes 2');
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
 