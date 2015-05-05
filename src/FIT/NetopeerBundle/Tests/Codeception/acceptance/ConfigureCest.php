<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class ConfigureCest
{

	public function _before(WebGuy $I)
	{
		CommonScenarios::login($I);
		CommonScenarios::connectToLocalhostDevice($I);
		$I->click('Configure device');
		$I->waitForText('Config & State data', 10);
		$I->canSeeInCurrentUrl('sections/0/interfaces/');
	}

	public function _after(WebGuy $I)
	{
		$I->amOnPage('/logout');
	}

	public function _turingAddTransition(WebGuy $I) {
		$I->amOnPage('/sections/0/turing-machine/');
		$I->waitForText('turing-machine', 10);
		$I->click('.create-child');
		$I->waitForText('transition-function');
		$I->click('transition-function');
		$I->click('.create-child', '.generatedForm');
		$I->waitForText('delta');
		$I->click('delta');
		$I->wait(3);
		$I->fillField('.generatedForm input.value[name*="--*?1!--*?1!--*?1!--*?1!"]', 'test');
	}

	public function testEditConfig(WebGuy $I) {
		$I->wantTo('create new transition function using submit button');

		$this->_turingAddTransition($I);
		$I->click('Create new node');
		$I->waitForElementNotVisible('#ajax-spinner');

		// see result
		$I->seeNumberOfElements('.message.success', 1);
		$I->waitForText('delta');
		$I->waitForText('test');
	}

	public function testEditConfigWithCommit(WebGuy $I) {
		$I->wantTo('create new transition function using commit all');

		$this->_turingAddTransition($I);
		$I->click('Append changes');

		$I->seeNumberOfElements('form.addedForm', 1);
		$I->click('Commit all changes');
		$I->waitForElementNotVisible('#ajax-spinner');

		// see result
		$I->seeNumberOfElements('.message.success', 1);
		$I->waitForText('delta');
		$I->waitForText('test');
	}
}