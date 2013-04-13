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

			$this->assertTrue($this->isElementPresent("css=.tooltip"), "Tooltip not presented");

			// check lock and unlock
			$this->click("//nav[@id='top']/a[2]/span");
			$this->waitForPageToLoad("30000");

			sleep(2);
			$this->assertTrue($this->isTextPresent("Successfully locked."), "Error while locking data-store, error occured: ".$this->getText("css=.alert"));
			$this->checkPageError();

			$this->click("//nav[@id='top']/a[2]/span");
			$this->waitForPageToLoad("30000");

			sleep(2);
			$this->assertTrue($this->isTextPresent("Successfully unlocked."), "Error while unlocking data-store, error occured: ".$this->getText("css=.alert"));

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
			$this->click("name=configDataForm[module-allowed_-*-*?1!-*?2!-*?2!]");
			$this->click("css=input[type=\"submit\"]");
			$this->waitForPageToLoad("30000");
			$this->checkPageError();

			$this->assertTrue($this->isElementPresent("css=div.alert.error"), "Alert with Could not turn on Combo  should appear, error occured: ".$this->getText("css=.alert"));


			if ($this->isTextPresent("Hanic probes")) {
				$this->click("link=Hanic probes");
				$this->waitForPageToLoad("30000");
				$this->checkPageError();

				if ($this->isTextPresent("Exporters")) {
					$this->click("link=Exporters");
					$this->checkPageError();

					$this->click("xpath=(//img[@alt='Add sibling'])[2]");
					for ($second = 0; ; $second++) {
						if ($second >= 60) $this->fail("Could not create form for adding sibling.");
						try {
							if ($this->isElementPresent("css=.generatedForm")) break;
						} catch (Exception $e) {}
						sleep(1);
					}


					$this->waitForPageToLoad("30000");
					$this->click("xpath=(//img[@alt='Add sibling'])[2]");
					$this->type("name=duplicatedNodeForm[id_-*-*?1!-*?2!-*?1!-*?1!]", "180");
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					sleep(2);

					$this->assertTrue($this->isTextPresent("Record has been added"), "Could not duplicate node, error occured: ".$this->getText("css=.alert"));
					$this->click("xpath=(//img[@alt='Add sibling'])[5]");
					sleep(2);
					$this->type("name=duplicatedNodeForm[id_-*-*?1!-*?2!-*?2!-*?7!-*?1!]", "181");
					$this->select("name=duplicatedNodeForm[protocol_transport_-*-*?1!-*?2!-*?2!-*?7!-*?6!]", "label=UDP");
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					sleep(2);
					$this->assertTrue($this->isTextPresent("Record has been added"), "Could not duplicate node, error occured: ".$this->getText("css=.alert"));
					$this->click("xpath=(//img[@alt='Remove child'])[6]");
					sleep(2);
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					sleep(2);
					$this->assertTrue($this->isTextPresent("Failed to apply configuration to device."), "Node has been removed, even thought it shouldn't. This message appears: ".$this->getText("css=.alert"));

					$this->click("xpath=(//img[@alt='Remove child'])[5]");
					sleep(2);
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					sleep(2);
					$this->assertTrue($this->isTextPresent("Record has been removed."), "Could not remove node, error occured: ".$this->getText("css=.alert"));

					$this->select("name=configDataForm[protocol_transport_-*-*?1!-*?2!-*?1!-*?8!-*?6!]", "label=TCP");
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					$this->assertTrue($this->isTextPresent("Config has been edited successfully."), "Could not edit config correctly, error occured: ".$this->getText("css=.alert"));
					$this->assertSelectedValue("css=select[name=\"configDataForm[protocol_transport_-*-*?1!-*?2!-*?1!-*?8!-*?6!]\"]", "TCP", "Change to value TCP was not successfull, error occured: ".$this->getText("css=.alert"));
					$this->click("xpath=(//img[@alt='Remove child'])[4]");
					sleep(2);
					$this->click("css=input[type=\"submit\"]");
					$this->waitForPageToLoad("30000");

					sleep(2);
					$this->assertTrue($this->isTextPresent("Record has been removed."), "Could not remove node, error occured: ".$this->getText("css=.alert"));

				} else {
					$this->fail("Could not test Hanic probes/Exporters duplicate, edit and remove node.");
				}
			} else {
				$this->fail("Could not test Hanic probes duplicate node.");
			}
		}
	}
}