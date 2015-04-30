<?php

namespace FIT\NetopeerBundle\Tests\Codeception\_support;

use \WebGuy;

class CommonScenarios {

	public static $deviceUser = "netopeergui";
	public static $devicePass = "netopeergui";

	public static function login($I)
	{
		/**
		 * @var WebGuy $I
		 */
		$I->maximizeWindow();
		$I->wantTo('log in as admin user');
		$I->amOnPage('/logout');
		$I->fillField('Username', 'admin');
		$I->fillField('Password', 'pass');
		$I->click('Log in');
		$I->expect('I am redirected to connections page');
		$I->seeCurrentUrlMatches('/connections/');
		$I->see('List of active connections');
	}

	public static function connectToLocalhostDevice($I)
	{
		/**
		 * @var WebGuy $I
		 */
		$I->wantTo('connect to localhost device');
		$I->amOnPage('/connections/');
		$I->fillField('Host', 'localhost');
		$I->fillField('Port', '830');
		$I->fillField('User', CommonScenarios::$deviceUser);
		$I->fillField('Password', CommonScenarios::$devicePass);
		$I->click('Connect');
		$I->waitForText('Loading...', 10);
		$I->waitForText('Configure device', 50);
		$I->waitForText('History of connected devices', 10);
		$I->waitForText('localhost:830', 2);
		$I->seeNumberOfElements('.message.success', 3);
	}
} 