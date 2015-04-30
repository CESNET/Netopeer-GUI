<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class NacmConfigureCest
{

	public function _before(WebGuy $I)
	{
		CommonScenarios::login($I);
		CommonScenarios::connectToLocalhostDevice($I);
		$I->click('Configure device');
		$I->waitForText('Config & State data', 10);
		$I->canSeeInCurrentUrl('sections/0/interfaces/');
		$I->click('Nacm');
		$I->waitForText('enable-nacm');
		$I->canSeeInCurrentUrl('sections/0/nacm/');
	}

	public function _after(WebGuy $I)
	{
		$I->amOnPage('/logout');
	}

	public function _addGroups(WebGuy $I) {
		$I->click('.create-child[rel="--*?1!"]');
		$I->waitForElement('.typeahead');
		$I->wait(3);
		$I->click('groups');
		$I->wait(3);
		$I->click('.create-child', '.generatedForm');
		$I->waitForElement('.typeahead');
		$I->wait(3);
		$I->click('.create-child[rel="--*?1!--*?1!--*?1!"]');
		$I->waitForElement('input.value[name*="--*?1!--*?1!--*?1!--*?1!"]');
		$inputValue = 'test-name'.time();
		$I->fillField('input.value[name*="--*?1!--*?1!--*?1!--*?1!"]', $inputValue);

		$I->waitForText($inputValue);
		$I->seeNumberOfElements('.message.success', 1);
	}

	public function testEditConfig(WebGuy $I) {
		$I->wantTo('create new interface using submit button');

		$this->_addGroups($I);
		$I->click('Create new node');

		// see result
		$I->seeNumberOfElements('.message.success', 1);
		$I->waitForText('delta');
		$I->waitForText('test');
	}

	public function _testEditConfigWithCommit(WebGuy $I) {
		$I->wantTo('create new interface using commit all');

		$this->_addGroups($I);
		$I->click('Append changes');

		$I->seeNumberOfElements('form.addedForm', 1);
		$I->click('Commit all changes');

		// see result
		$I->seeNumberOfElements('.message.success', 1);
		$I->waitForText('delta');
		$I->waitForText('test');
	}
}