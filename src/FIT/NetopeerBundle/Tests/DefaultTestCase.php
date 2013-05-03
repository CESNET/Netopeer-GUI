<?php
/**
 * Parent class for all Selenium test cases.
 */
class DefaultTestCase extends PHPUnit_Extensions_SeleniumTestCase
{
	/**
	 * @var string  default URL for test
	 */
	protected static $browserUrl = "https://sauvignon.liberouter.org/symfony/app.php/";

	/**
	 * @inheritdoc
	 */
	protected function setUp()
	{
//		$this->setBrowser("*firefox");
		$this->setBrowser("*safari");
		$this->setBrowserUrl(self::$browserUrl);
	}

	/**
	 * overload default method call and add some more actions,
	 * for example resizing window to maximum available space
	 *
	 * @param string $url  url, which we want to open
	 * $return @inheritdoc
	 */
	protected function open($url) {
		$this->windowMaximize();
		parent::open($url);
		$this->checkPageError();
	}

	/**
	 * checking changing layout between single and double columns
	 */
	public function checkColumnsChange() {
		$this->click("link=Double column");
		$this->waitForPageToLoad("30000");
		$this->checkPageError();
		$this->assertTrue($this->isTextPresent("Config data only"), "Could not change to double-column layout.");

		$this->click("link=Single column");
		$this->waitForPageToLoad("30000");
		$this->checkPageError();
		$this->assertFalse($this->isTextPresent("Config data only"), "Could not change to single-column layout.");
	}

	/**
	 * connect to device with right credentials
	 *
	 * @throws Exception
	 */
	public function connectToDevice() {
		$this->checkPageError();

		// type connection credentials and try to connect to the device
		$this->type("id=form_host", "sauvignon.liberouter.org");
		$this->type("id=form_user", "seleniumTest");
		$this->type("id=form_password", "seleniumTestPass");
		$this->click("css=input[type=\"submit\"]");
		$this->waitForPageToLoad("30000");
		try {
			$this->assertFalse($this->isTextPresent("Could not connect"));
		} catch (PHPUnit_Framework_AssertionFailedError $e) {
			throw new \Exception('Could not connect to server.');
		}
	}

	/**
	 * login to webgui with right credentials
	 *
	 * @return bool
	 */
	public function loginCorrectly() {
		try {
			$this->assertFalse($this->isTextPresent("Log in is required for this site!"));
			return true;
		} catch (PHPUnit_Framework_AssertionFailedError $e) {
		}

			$this->type("id=username", "seleniumTest");
			$this->type("id=password", "seleniumTestPass");
			$this->click("name=login");
			$this->waitForPageToLoad("30000");

		$this->checkPageError();

		try {
			$this->assertFalse($this->isTextPresent("Log in is required for this site!"));
			return true;
		} catch (PHPUnit_Framework_AssertionFailedError $e) {
			return false;
		}
	}

	/**
	 * checks, if all images are loaded correctly
	 *
	 * @throws Exception
	 */
	public function checkImages() {
		try {
			$this->assertEquals('1', $this->getEval('
				var elems = window.document.getElementsByTagName("img");
				var allOk = true;

				for(var i = 0; i < elems.length; i++) {
					var src = elems[i].src;
					if (src.indexOf("googleads") > 0) {
						continue;
					}
					allOk &= elems[i].complete && typeof elems[i].naturalWidth != "undefined" && elems[i].naturalWidth > 0;
				}
				Number(allOk); // getEval returns result of last statement. This statement has value of the variable as result.
			'));
		} catch (\Exception $e) {
			throw new \Exception('Some images were not loaded correctly.');
		}
	}

	/**
	 * checks appearance of Error document, Exception, Warning...
	 *
	 * @throws Exception when error, exception appears
	 */
	protected function checkPageError()	{
		$this->checkImages();

		try {
			$this->assertFalse($this->isElementPresent('css=.block_exception_detected'), 'Exception found, error 500.');
			$this->assertFalse($this->isTextPresent("404 Not Found"), "Error 404");
			$this->assertFalse($this->isTextPresent("Warning: "), "Warning appears.");
			$this->assertFalse($this->isTextPresent("Fatal error: "), "Fatal error appears.");
		} catch (PHPUnit_Framework_AssertionFailedError $e) {
			throw new \Exception('Error while loading page: ' . $this->getTitle() . ' with error ' . $e->toString());
		}
	}
}