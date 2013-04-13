<?php
require_once 'DefaultTestCase.php';

class DeviceConnectionTestCase extends DefaultTestCase
{
	/**
	 * test connection to the device, also with handling
	 * history and profiles of connected devices,
	 * and logout from device
	 *
	 * @throws Exception
	 */
	public function testDeviceConnection()
	{
		$this->open(self::$browserUrl);

		// login to webGUI
		if ($this->loginCorrectly()) {
			$this->connectToDevice();

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
			for ($i = 0; $i < 2; $i++) {
				$this->click("link=disconnect");
				$this->waitForPageToLoad("30000");
				$this->assertTrue($this->isTextPresent("Successfully disconnected."), "Could not disconnect from device.");
			}

			$this->assertTrue($this->isTextPresent('You are not connected to any server'), "Did not disconnet from all devices");

			sleep(2);

			// delete device from history
			$this->click("//div[@id='history-of-connected-devices']/a/span");
			sleep(2);
			$this->isTextPresent("Device has been");
		}
	}
}