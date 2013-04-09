<?php
class DefaultTestCase extends PHPUnit_Extensions_SeleniumTestCase
{
	private static $browserUrl = "https://sauvignon.liberouter.org/symfony/app.php/";

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
	 * test login to webGUI
	 *
	 * @throws Exception
	 */
	public function testLogin()
	{
		$this->open(self::$browserUrl);

		$this->checkPageError();
		$this->checkImages();

		// check invalid username and password
		$this->type("id=username", "dfasfdahsofhdasdfiasjdfpasjdfpasijfpasjfdpasdf");
		$this->type("id=password", "dfadfadsfasf");
		$this->click("name=login");
		$this->waitForPageToLoad("30000");
		$this->assertFalse($this->isTextPresent("Bad credentials."), "Checking invalid username and password failed");

		// check valid username and invalid password
		$this->type("id=username", "dalexa");
		$this->type("id=password", "dfadfadsfasf");
		$this->click("name=login");
		$this->waitForPageToLoad("30000");
		$this->assertTrue($this->isTextPresent("The presented password is invalid."), "Checking valid username and invalid password failed");

		// if connected correctly
		if ($this->loginCorrectly()) {
			// try to log out
			$this->click("link=Log out");
			$this->waitForPageToLoad("30000");
			try {
				$this->assertTrue($this->isTextPresent("Log in is required for this site!"));
			} catch (PHPUnit_Framework_AssertionFailedError $e) {
				throw new \Exception('Could not log out.');
			}
		} else {
			throw new \Exception('Could not log in correctly.');
		}
	}

	/**
	 * test connection to the device, also with handling
	 * history and profiles of connected devices,
	 * and logout from device
	 *
	 * @throws Exception
	 */
	public function testDeviceConnection()
	{
		$this->open("/symfony/app.php/");
		$this->checkPageError();

		// login to webGUI
		if ($this->loginCorrectly()) {
			$this->checkPageError();
			$this->checkImages();

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

			sleep(3);

			// connect to device from history
			$this->click("css=a.device-item");

			sleep(3);

			// type password (other credentials are from history)
			$this->type("id=form_password", "seleniumTestPass");
			$this->click("css=input[type=\"submit\"]");
			$this->waitForPageToLoad("30000");
			try {
				$this->assertFalse($this->isTextPresent("Could not connect"));
			} catch (PHPUnit_Framework_AssertionFailedError $e) {
				throw new \Exception('Could not connect to server for second time.');
			}

			$this->isTextPresent("Form has been filled up correctly.");
			sleep(2);

			// add device from history to profiles of connected devices
			$this->click("//div[@id='history-of-connected-devices']/a/span[2]");
			$this->isTextPresent("Device has been");

			sleep(2);
			// delete device from history of connected devices
			$this->click("//div[@id='profiles-of-connected-devices']/a/span");
			sleep(2);
			$this->isTextPresent("Device has been");

			// connect once more time to device from history
			$this->click("css=a.device-item");
			$this->type("id=form_password", "seleniumTestPass");
			$this->click("css=input[type=\"submit\"]");
			$this->waitForPageToLoad("30000");

			// disconnect from devices
			$this->click("link=disconnect");
			$this->waitForPageToLoad("30000");
			$this->click("link=disconnect");
			$this->waitForPageToLoad("30000");

			sleep(2);

			// delete device from history
			$this->click("//div[@id='history-of-connected-devices']/a/span");
			sleep(2);
			$this->isTextPresent("Device has been");
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

		try {
			$this->type("id=username", "seleniumTest");
			$this->type("id=password", "seleniumTestPass");
			$this->click("name=login");
			$this->waitForPageToLoad("30000");
			$this->verifyTextNotPresent("Log in is required for this site!");
			return true;
		} catch (\Exception $e) {
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
		try {
			$this->assertFalse($this->isElementPresent('css=.block_exception_detected'), 'Exception found, error 500.');
			$this->assertFalse($this->isTextPresent("404 Not Found"), "Error 404");
			$this->assertFalse($this->isTextPresent("Warning: "), "Warning appears.");
			$this->assertFalse($this->isTextPresent("Fatal error: "), "Fatal error appears.");
		} catch (PHPUnit_Framework_AssertionFailedError $e) {
			throw new \Exception('Error while loading page ' . $this->getTitle() . ' with error ' . $e->toString());
		}
	}
}