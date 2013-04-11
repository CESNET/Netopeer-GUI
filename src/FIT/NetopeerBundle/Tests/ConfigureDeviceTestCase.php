<?php
require_once 'DefaultTestCase.php';

class ConfigureDeviceTestCase extends DefaultTestCase
{
	/**
	 * test Configure device link
	 */
	public function testConfigureDevice() {
		$this->open(self::$browserUrl);

		// login to webGUI
		if ($this->loginCorrectly()) {
			$this->connectToDevice();

			for ($second = 0; ; $second++) {
				if ($second >= 60) $this->fail("Could not process get-schema. Connection timeout.");
				try {
					if ($this->isTextPresent("Configure device")) break;
				} catch (Exception $e) {}
				sleep(1);
			}

			$this->click("link=Configure device");
			$this->waitForPageToLoad("30000");
			$this->checkPageError();

			$this->checkColumnsChange();

			$this->assertTrue($this->isElementPresent("css=.tooltip"), "Tooltip not presented.");

			// check lock and unlock
			$this->click("//nav[@id='top']/a[2]/span");
			$this->waitForPageToLoad("30000");
			$this->assertTrue($this->isTextPresent("Successfully locked."), "Error while locking data-store.");
			$this->checkPageError();

			$this->click("//nav[@id='top']/a[2]/span");
			$this->waitForPageToLoad("30000");
			$this->assertTrue($this->isTextPresent("Successfully unlocked."), "Error while unlocking data-store.");

			// check, if link All is presented
			$this->assertTrue($this->isElementPresent("link=All"), "No module ALL exists.");
			$this->click("link=All");
			$this->waitForPageToLoad("30000");

			$this->checkColumnsChange();

			$this->click("link=Netopeer");
			$this->waitForPageToLoad("30000");

			try {
				$this->assertEquals("on", $this->getValue("name=configDataForm[module-allowed_-*-*?1!-*?1!-*?2!]"), "Netopeer module is not on???");
			} catch (PHPUnit_Framework_AssertionFailedError $e) {
				array_push($this->verificationErrors, $e->toString());
			}
			$this->assertEquals("on", $this->getValue("name=configDataForm[module-allowed_-*-*?1!-*?1!-*?2!]"), "Netopeer module is not on???");
			$this->click("name=configDataForm[module-allowed_-*-*?1!-*?2!-*?2!]");
			$this->click("css=input[type=\"submit\"]");
			$this->waitForPageToLoad("30000");
			$this->checkPageError();

			$this->assertTrue($this->isElementPresent("css=div.alert.error"), "Alert with Could not turn on Combo  should appear.");
		}
	}
}