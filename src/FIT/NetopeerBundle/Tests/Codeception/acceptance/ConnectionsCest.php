<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class UserCest
{

	public function _before(WebGuy $I)
	{
		CommonScenarios::login($I);
	}

	public function _after(WebGuy $I)
	{
		$I->amOnPage('/logout');
	}

	public function _connectToLocalhostDevice(WebGuy $I)
	{
		CommonScenarios::connectToLocalhostDevice($I);
	}

	public function connectToLocalhostDevice2(WebGuy $I)
	{
		CommonScenarios::connectToLocalhostDevice($I);

		$I->wantTo('connect to second localhost device');
		$I->amOnPage('/connections/');

		$I->expectTo('connect to localhost device');
		$I->waitForText('localhost:830', 10);
		$I->click('#block--historyOfConnectedDevices a.device-item');
		$I->fillField('Password', CommonScenarios::$devicePass);
		$I->click('Connect');
		$I->waitForText('Loading...', 10);
		$I->waitForText('Configure device', 50, '#row-1');
		$I->seeNumberOfElements('.message.success', 3);
		$I->seeNumberOfElements('tr', 2);

		$I->waitForElementNotVisible('#ajax-spinner');

		$I->expectTo('disconnect from second device');
		$I->click('Disconnect', '#row-1');

		$I->waitForElementNotVisible('#row-1');

		$I->click('.ico-alerts');
		$I->canSee('Successfully disconnected.');
		$I->seeNumberOfElements('.message.success', 1);
		$I->seeNumberOfElements('tr', 1);
	}
}