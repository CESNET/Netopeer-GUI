<?php
require_once 'DefaultTestCase.php';

class LoginTestCase extends DefaultTestCase
{
	/**
	 * test login to webGUI
	 *
	 * @throws Exception
	 */
	public function testLogin()
	{
		$this->open(self::$browserUrl);

		if ($this->isTextPresent("Log out")) {
			$this->click("link=Log out");
			$this->waitForPageToLoad("30000");
		}

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
}