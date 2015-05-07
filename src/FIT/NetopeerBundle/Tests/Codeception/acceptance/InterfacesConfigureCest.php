<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class InterfaceConfigureCest
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

	public function _addInterface(WebGuy $I) {
		$I->waitForText('interfaces', 10);
		$I->click('.create-child[rel="--*?1!"]');
		CommonScenarios::waitAndClickInTypeahead($I, 'interface');
		$I->fillField('.generatedForm input.value[name*="--*?1!--*?1!--*?1!"]', 'test-name'.time());
		$I->selectOption('.generatedForm select[name*="--*?1!--*?1!--*?2!"]', 'ianaift:other');

		$I->click('.create-child', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'description');
		$I->fillField('input.value[name*="--*?1!--*?1!--*?3!"]', 'loopback interface');
	}

	public function testEditConfig(WebGuy $I) {
		$I->wantTo('create new interface using submit button');

		$this->_addInterface($I);
		$I->click('Create new node');

		// see result
		CommonScenarios::checkNumberOfFlashes($I, 1);
		$I->seeNumberOfElements('.level-0.interface', 2);
	}

	public function testEditConfigWithCommit(WebGuy $I) {
		$I->wantTo('create new interface using commit all');

		$this->_addInterface($I);
		$I->click('Append changes');

		$I->seeNumberOfElements('form.addedForm', 1);
		$I->click('Commit all changes');

		// see result
		CommonScenarios::checkNumberOfFlashes($I, 1);
		$I->seeNumberOfElements('.level-0.interface', 3);
	}
}