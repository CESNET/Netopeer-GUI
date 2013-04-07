<?php
//require_once '../WebDriver/WebDriver.php';
//require_once '../WebDriver/WebDriver/Driver.php';
//require_once '../WebDriver/WebDriver/MockDriver.php';
//require_once '../WebDriver/WebDriver/WebElement.php';
//require_once '../WebDriver/WebDriver/MockElement.php';
//require_once '../WebDriver/WebDriver/FirefoxProfile.php';

class DefaultTestCase extends PHPUnit_Extensions_SeleniumTestCase
{
	private static $browserUrl = "https://sauvignon.liberouter.org/symfony/app.php/";

	protected function setUp()
	{
		$this->setBrowser("*firefox");
		$this->setBrowserUrl(self::$browserUrl);
	}

	public function testLogin()
	{
		$this->open(self::$browserUrl."login/");
		$this->checkImages();
		$this->type("id=username", "dalexa");
		$this->type("id=password", "dfadfadsfasf");
		$this->click("name=login");
		$this->waitForPageToLoad("30000");
		$this->verifyTextPresent("The presented password is invalid.");
		if ($this->loginCorrectly()) {
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

	public function testConnect()
	{
		$this->open("/symfony/app.php/");
		if ($this->loginCorrectly()) {
			$this->checkImages();
			$this->type("id=form_host", "localhost");
			$this->type("id=form_user", "alexadav");
			$this->type("id=form_password", "w.uLR9dj");
			$this->click("css=input[type=\"submit\"]");
			$this->waitForPageToLoad("30000");
			try {
				$this->assertFalse($this->isTextPresent("Could not connect to socket. Error: Connection refused"));
			} catch (PHPUnit_Framework_AssertionFailedError $e) {
				array_push($this->verificationErrors, $e->toString());
				throw new \Exception('Could not connect to socket. Error: Connection refused.');
			}
		}
	}

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
}